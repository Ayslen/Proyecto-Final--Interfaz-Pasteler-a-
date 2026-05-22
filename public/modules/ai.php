<?php
// =========================================================================
// 1. Cargar el entorno y verificar permisos del proyecto
// =========================================================================
require_once __DIR__ . '/../../app/bootstrap.php';

if (class_exists('Auth')) {
    Auth::requirePermission('ai', 'view');
}

// =========================================================================
// 2. Procesar la petición asíncrona del chat (POST)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ask_ai') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    
    $userMessage = $_POST['message'] ?? '';
    if (empty($userMessage)) {
        $input = json_decode(file_get_contents('php://input'), true);
        $userMessage = $input['message'] ?? '';
    }

    if (empty($userMessage)) {
        echo json_encode(['reply' => 'La pregunta no puede estar vacía.']);
        exit;
    }

    $userId = 1; 
    $roleName = 'admin'; 
    if (class_exists('Auth')) {
        if (method_exists('Auth', 'id')) { $userId = Auth::id(); }
        if (method_exists('Auth', 'role')) { $roleName = Auth::role(); }
    }

    // Clasificar categorías respetando estrictamente tu ENUM de la base de datos
    $queryType = 'general';
    $lowerMsg = mb_strtolower($userMessage);
    if (strpos($lowerMsg, 'producc') !== false || strpos($lowerMsg, 'unidades') !== false || strpos($lowerMsg, 'hizo') !== false || strpos($lowerMsg, 'fabric') !== false) {
        $queryType = 'produccion';
    } elseif (strpos($lowerMsg, 'inventario') !== false || strpos($lowerMsg, 'materia') !== false || strpos($lowerMsg, 'stock') !== false || strpos($lowerMsg, 'existencia') !== false || strpos($lowerMsg, 'empaque') !== false || strpos($lowerMsg, 'fresa') !== false || strpos($lowerMsg, 'cacao') !== false || strpos($lowerMsg, 'capacillo') !== false) {
        $queryType = 'inventario';
    } elseif (strpos($lowerMsg, 'recomiend') !== false || strpos($lowerMsg, 'suger') !== false || strpos($lowerMsg, 'pastel') !== false || strpos($lowerMsg, 'catál') !== false || strpos($lowerMsg, 'precio') !== false) {
        $queryType = 'recomendacion';
    }

    // 🔑 Tu API Key Activa de Groq
    $apiKey = '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'; 
    $apiUrl = 'https://api.groq.com/openai/v1/chat/completions';
    $modelName = 'llama-3.3-70b-versatile'; 

    $aiReply = null;
    $errorMessage = null;
    $success = 1; // TINYINT(1) -> 1 significa True/Éxito por defecto
    $dbContextData = "No se requirieron datos en tiempo real de la base de datos.";
    $generatedSql = 'GENERAL';
    
    $conexionActiva = null; 

    // Conexión a MySQL
    try {
        $dbHost = '127.0.0.1';
        $dbName = 'pasteleria_manager';
        $dbUser = 'root';
        $dbPass = '';
        $dbPort = '3306';
        $dbCharset = 'utf8mb4';

        $dbConfigFile = __DIR__ . '/../../app/config/database.php';
        if (file_exists($dbConfigFile)) {
            $loadedConfig = require $dbConfigFile;
            if (is_array($loadedConfig)) {
                $dbHost = $loadedConfig['host'] ?? $dbHost;
                $dbName = $loadedConfig['database'] ?? $dbName;
                $dbUser = $loadedConfig['username'] ?? $dbUser;
                $dbPass = $loadedConfig['password'] ?? $dbPass;
                $dbPort = $loadedConfig['port'] ?? $dbPort;
                $dbCharset = $loadedConfig['charset'] ?? $dbCharset;
            }
        }

        $dsn = "mysql:host=$dbHost;dbname=$dbName;port=$dbPort;charset=$dbCharset";
        $conexionActiva = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (Exception $e) {
        $errorMessage = "Error de conexión local: " . $e->getMessage();
        $success = 0;
    }

    if ($conexionActiva !== null) {
        try {
            // FASE 1: Obtener comando SQL real sin campos inventados
            $sqlPrompt = "Eres un traductor estricto de lenguaje natural a consultas SQL para MySQL.\n"
                       . "Genera EXCLUSIVAMENTE una consulta SELECT válida utilizando únicamente este esquema real de tablas:\n"
                       . "- materias_primas (id, nombre, unidad, stock_actual, stock_minimo)\n"
                       . "- productos (id, nombre, categoria, precio, activo) -- Nota: categoria es ENUM('pastel','cupcake','galleta','postre')\n"
                       . "- produccion_diaria (id, fecha, producto_id, cantidad, linea)\n\n"
                       . "REGLAS CRÍTICAS DE TRADUCCIÓN:\n"
                       . "1. Si preguntan por insumos o empaques, haz un SELECT únicamente a la tabla materias_primas.\n"
                       . "2. ¡PROHIBIDO!: No uses ni inventes columnas llamadas 'stock_maximo' o 'categoria' dentro de materias_primas. Busca los elementos usando la columna 'nombre' con un LIKE. Ejemplo: WHERE nombre LIKE '%Empaque%'.\n"
                       . "3. En el SELECT de materias_primas selecciona estrictamente: id, nombre, unidad, stock_actual, stock_minimo.\n"
                       . "4. Devuelve únicamente la consulta SQL limpia en texto plano, sin markdown (sin ```sql) y sin punto y coma (;).\n"
                       . "5. Si es un saludo o no requiere base de datos, responde únicamente: GENERAL";

            $chSql = curl_init();
            curl_setopt_array($chSql, [
                CURLOPT_URL            => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode([
                    'model' => $modelName,
                    'messages' => [['role' => 'system', 'content' => $sqlPrompt], ['role' => 'user', 'content' => $userMessage]],
                    'temperature' => 0.0
                ]),
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4
            ]);

            $sqlResponse = curl_exec($chSql);

            if (curl_errno($chSql)) {
                throw new Exception("Error de comunicación cURL (Fase 1): " . curl_error($chSql));
            }

            $sqlData = json_decode($sqlResponse, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($sqlData['choices'][0]['message']['content'])) {
                throw new Exception("La API respondió un formato inesperado en la Fase 1.");
            }

            $generatedSql = trim($sqlData['choices'][0]['message']['content']);
            $generatedSql = str_replace(['```sql', '```', ';', '"', '`'], '', $generatedSql);
            $generatedSql = trim($generatedSql);
            curl_close($chSql);

            // Ejecutar la consulta SQL en tu MySQL de forma segura
            if ($generatedSql !== 'GENERAL' && stripos($generatedSql, 'SELECT') === 0) {
                $stmtData = $conexionActiva->query($generatedSql);
                $rows = $stmtData->fetchAll();
                if (!empty($rows)) { 
                    $dbContextData = json_encode($rows, JSON_UNESCAPED_UNICODE); 
                } else {
                    $dbContextData = "No se encontraron registros de este elemento en el inventario actual de la base de datos.";
                }
            }

            // FASE 2: Formatear la respuesta basada en el JSON real de la BD
            $finalPrompt = "Eres un asistente experto para el sistema industrial pasteleria_manager.\n"
                         . "Responde la pregunta del usuario utilizando única y exclusivamente los siguientes datos reales devueltos por MySQL:\n"
                         . "Datos obtenidos de MySQL: " . $dbContextData . "\n\n"
                         . "REGLAS DE NEGOCIO PARA EL CONTROL DE INVENTARIO:\n"
                         . "1. Informa al usuario únicamente los valores reales que existen en la tabla: el Stock Actual y el Stock Mínimo con su respectiva unidad de medida. JAMÁS inventes un stock máximo.\n"
                         . "2. Realiza el análisis de disponibilidad basado en reglas precisas:\n"
                         . "   - Si el stock_actual es MAYOR que el stock_minimo, reporta que está 'Disponible'.\n"
                         . "   - Si el stock_actual es MENOR o IGUAL que el stock_minimo, advierte que está 'Por agotarse' y requiere reorden.\n"
                         . "3. Habla en español de México de forma ejecutiva, concisa y profesional.\n"
                         . "4. Si los datos indican que no se encontraron registros, explica que ese artículo no está registrado en el inventario.";

            $chFinal = curl_init();
            curl_setopt_array($chFinal, [
                CURLOPT_URL            => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode([
                    'model' => $modelName,
                    'messages' => [['role' => 'system', 'content' => $finalPrompt], ['role' => 'user', 'content' => $userMessage]],
                    'temperature' => 0.2
                ]),
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4
            ]);

            $finalResponse = curl_exec($chFinal);
            if (curl_errno($chFinal)) {
                throw new Exception("Error de comunicación cURL (Fase 2): " . curl_error($chFinal));
            }
            
            $finalData = json_decode($finalResponse, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($finalData['choices'][0]['message']['content'])) {
                $aiReply = $finalData['choices'][0]['message']['content'];
            } else {
                throw new Exception("Error al decodificar la respuesta final de la IA.");
            }
            curl_close($chFinal);

        } catch (Exception $ex) {
            $errorMessage = $ex->getMessage();
            $success = 0;
        }
    }

    if (!$aiReply) {
        $aiReply = "Error interno del sistema. Detalle técnico: " . ($errorMessage ?? "No se recibió respuesta procesable de la base de datos.");
    }

    // =========================================================================
    // GUARDADO EN LA TABLA HISTÓRICA (Mapeado idéntico a tu CREATE TABLE)
    // =========================================================================
    if ($conexionActiva !== null) {
        try {
            $sqlLog = "INSERT INTO ai_queries (user_id, role_name, question, context_summary, response, query_type, success, error_message) 
                       VALUES (:user_id, :role_name, :question, :context_summary, :response, :query_type, :success, :error_message)";
            $stmtLog = $conexionActiva->prepare($sqlLog);
            $stmtLog->execute([
                ':user_id'         => $userId,
                ':role_name'       => $roleName,
                ':question'        => $userMessage,
                ':context_summary' => mb_strimwidth("SQL: " . $generatedSql . " | Contexto: " . $dbContextData, 0, 500, "..."), 
                ':response'        => $aiReply,
                ':query_type'      => $queryType, // Mapea directo con tu ENUM
                ':success'         => $success,   // Pasa 1 (éxito) o 0 (fallo) al TINYINT(1)
                ':error_message'   => $errorMessage ? mb_strimwidth($errorMessage, 0, 255, "...") : null // Límite exacto de tu VARCHAR(255)
            ]);
        } catch (PDOException $e) {}
    }

    echo json_encode(['reply' => $aiReply], JSON_UNESCAPED_UNICODE);
    exit; 
}

require_once __DIR__ . '/../partials/header.php';
?>

<section class="hero" style="padding: 20px; font-family: sans-serif;">
    <div class="card" style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); max-width: 800px; margin: 0 auto;">
        <span class="badge" style="background: #f0f0f0; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: bold;">Módulo Inteligente</span>
        <h1 style="font-size: 34px; margin-top: 14px; color: #333;">Consultas IA</h1>
        
        <div class="chat-container" style="border: 1px solid #e0e0e0; border-radius: 10px; padding: 15px; margin: 20px 0; background: #fafafa; height: 350px; overflow-y: auto;">
            <div id="chat-output" style="display: flex; flex-direction: column; gap: 12px;">
                <div class="ai-msg" style="background: #eef2f7; padding: 12px; border-radius: 8px; align-self: flex-start; max-width: 80%; color: #333;">
                    <strong>Asistente:</strong> ¡Hola! ¿Qué información de la producción, catálogo de pasteles o stock real necesitas consultar hoy?
                </div>
            </div>
        </div>

        <div class="chat-input-area" style="display: flex; gap: 10px; margin-bottom: 25px;">
            <input type="text" id="user-question" placeholder="Pregúntame algo sobre las materias primas o productos..." style="flex: 1; padding: 12px; border: 1px solid #ccc; border-radius: 8px; font-size: 15px;">
            <button id="btn-send" style="padding: 12px 24px; background: #7c5e43; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer;">Enviar</button>
        </div>

        <div class="grid two" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div class="stat" style="border: 1px solid #ddd; padding: 12px; border-radius: 8px; cursor: pointer; background: #fff;" onclick="setExample('¿Qué materias primas tenemos en el inventario?')">
                <strong>Ver Inventario</strong><br><span style="font-size: 13px; color: #555;">¿Qué materias primas tenemos en el inventario?</span>
            </div>
            <div class="stat" style="border: 1px solid #ddd; padding: 12px; border-radius: 8px; cursor: pointer; background: #fff;" onclick="setExample('¿Cuál es el stock y estado de los Empaques?')">
                <strong>Ver Disponibilidad</strong><br><span style="font-size: 13px; color: #555;">¿Cuál es el stock y estado de los Empaques?</span>
            </div>
        </div>
    </div>
</section>

<script>
function setExample(text) {
    document.getElementById('user-question').value = text;
}

document.getElementById('btn-send').addEventListener('click', async () => {
    const inputField = document.getElementById('user-question');
    const chatOutput = document.getElementById('chat-output');
    const question = inputField.value.trim();

    if (!question) return;

    chatOutput.innerHTML += `
        <div class="user-msg" style="background: #7c5e43; color: white; padding: 12px; border-radius: 8px; align-self: flex-end; max-width: 80%;">
            <strong>Tú:</strong> ${question}
        </div>
    `;
    inputField.value = '';

    const loadingId = 'load-' + Date.now();
    chatOutput.innerHTML += `
        <div id="${loadingId}" class="ai-msg" style="background: #eef2f7; padding: 12px; border-radius: 8px; align-self: flex-start; max-width: 80%; color: #333;">
            <strong>Asistente:</strong> Consultando base de datos real...
        </div>
    `;
    chatOutput.parentElement.scrollTop = chatOutput.parentElement.scrollHeight;

    try {
        const formData = new FormData();
        formData.append('action', 'ask_ai');
        formData.append('message', question);

        const currentPath = window.location.pathname;
        const targetUrl = currentPath.substring(0, currentPath.lastIndexOf('/')) + '/ai.php?t=' + Date.now();

        const response = await fetch(targetUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error('Fallo en la respuesta del servidor local. Código: ' + response.status);
        }

        const data = await response.json();
        const loadingElement = document.getElementById(loadingId);
        if (loadingElement) loadingElement.remove();

        if (data.reply) {
            chatOutput.innerHTML += `
                <div class="ai-msg" style="background: #eef2f7; padding: 12px; border-radius: 8px; align-self: flex-start; max-width: 80%; color: #333;">
                    <strong>Asistente:</strong> ${data.reply.replace(/\n/g, '<br>')}
                </div>
            `;
        }
    } catch (error) {
        const loadingElement = document.getElementById(loadingId);
        if (loadingElement) loadingElement.remove();
        chatOutput.innerHTML += `
            <div class="ai-msg" style="background: #eef2f7; padding: 12px; border-radius: 8px; align-self: flex-start; max-width: 80%; color: #333;">
                <strong>Asistente:</strong> Ocurrió un error en la decodificación de datos del servidor.
            </div>
        `;
        console.error("Detalle:", error);
    }

    chatOutput.parentElement.scrollTop = chatOutput.parentElement.scrollHeight;
});

document.getElementById('user-question').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('btn-send').click();
    }
});
</script>

<?php 
require_once __DIR__ . '/../partials/footer.php'; 
?>