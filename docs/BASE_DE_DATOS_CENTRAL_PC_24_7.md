# Base de datos central en mi PC 24/7

El proyecto ya está preparado para que todos los colaboradores usen una sola base de datos MySQL. La idea correcta es esta:

- Mi PC se queda encendida con MySQL/XAMPP.
- Mis compañeros descargan el código de Git.
- El archivo `app/config/database.php` debe apuntar al host de mi PC.
- Todos trabajan contra la misma base `pasteleria_manager`.

## Error lógico importante

`127.0.0.1` o `localhost` no sirve para los colaboradores si ellos ejecutan el proyecto en su propia PC. Para ellos, `127.0.0.1` significa su propia computadora, no la mía.

Por eso, antes de subir el proyecto final a Git, debo cambiar `app/config/database.php`:

```php
'host' => 'MI_IP_PUBLICA_O_DOMINIO_DDNS',
'port' => 3306,
'database' => 'pasteleria_manager',
'username' => 'pasteleria_remote',
'password' => 'UNA_PASSWORD_SEGURA',
```

También puedo usar una IP de VPN privada, por ejemplo Tailscale o ZeroTier. Esa opción suele ser más segura que abrir MySQL directo a internet.

## Configuración recomendada en MySQL

Crear un usuario exclusivo para el proyecto, no usar `root` para conexiones externas:

```sql
CREATE USER 'pasteleria_remote'@'%' IDENTIFIED BY 'CAMBIA_ESTA_PASSWORD_SEGURA';
GRANT ALL PRIVILEGES ON pasteleria_manager.* TO 'pasteleria_remote'@'%';
FLUSH PRIVILEGES;
```

## Permitir conexión externa

En la PC que tendrá la base:

1. Mantener MySQL encendido en XAMPP.
2. Permitir el puerto 3306 en el Firewall de Windows.
3. Si se usa internet normal, abrir/reenviar el puerto 3306 en el módem hacia mi PC.
4. Si se usa VPN tipo Tailscale/ZeroTier, no es necesario abrir el puerto al público; se usa la IP privada de la VPN.

## Módulo Estado de PCs

El sistema incluye el módulo `Estado de PCs`, visible para Admin y para usuarios a los que el Admin les dé permiso.

Este módulo registra:

- qué equipo/navegador abrió el sistema;
- qué usuario lo abrió;
- cuándo fue la última actividad;
- si pudo escribir estado en la base central;
- la ruta donde está corriendo el proyecto;
- host de PHP, IP del navegador y host de base de datos usado.

## Qué sí puede sincronizar el sistema

El botón `Sincronizar` confirma que la PC actual puede conectarse y escribir en la base central.

## Qué no puede hacer una página web

Una página web no puede obligar a otra computadora a hacer `git pull`, subir commits o sincronizar archivos locales. Eso debe hacerlo cada colaborador desde GitHub/VS Code.

Por eso, el módulo no promete sincronizar código. Solo confirma conexión y actividad contra la base de datos central.
