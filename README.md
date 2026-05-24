# Manager Inteligente para Administración de Producción

## Pastelería Industrial

Aplicación web desarrollada en **PHP + MySQL** para administrar información de una empresa de manufactura enfocada en una **pastelería industrial**.

El sistema permite gestionar usuarios, roles, permisos, inventario de materia prima, productos, producción diaria, movimientos de inventario, recetas de productos, dashboards y consultas mediante Inteligencia Artificial.

---

## Descripción del proyecto

Este proyecto simula el funcionamiento de un sistema inteligente de apoyo a la toma de decisiones dentro de una empresa de producción.

La empresa elegida es una **pastelería industrial**, dedicada a la elaboración de:

- Pasteles.
- Cupcakes.
- Galletas.
- Postres empaquetados.
- Brownies.
- Cheesecakes.

La aplicación permite administrar información relacionada con producción, inventario y análisis mediante IA.

---

## Empresa simulada

```text
Empresa: Pastelería industrial

Producto principal: Pasteles personalizados y postres empaquetados

Materia prima: Harina, azúcar, huevos, leche, mantequilla, crema, chocolate, frutas, queso crema, vainilla, empaques, cajas y capacillos

Producción: Preparación, horneado, decoración y empaquetado diario de pasteles, cupcakes, galletas y postres
```

---

## Funcionamiento general del sistema

El sistema funciona mediante un login con roles de usuario.

Existen dos tipos principales de usuario:

### Admin

El administrador tiene acceso completo al sistema.

Puede:

- Consultar dashboard general.
- Administrar usuarios.
- Administrar permisos.
- Ver estado de PCs conectadas.
- Administrar inventario.
- Registrar productos.
- Registrar producción diaria.
- Consultar movimientos de inventario.
- Administrar recetas de productos.
- Consultar reportes.
- Usar consultas con IA.
- Modificar apariencia del sistema.

### User

El usuario tiene acceso limitado.

Puede:

- Consultar información general.
- Ver módulos permitidos.
- Registrar producción si tiene permiso.
- Consultar datos de inventario, productos o reportes según permisos asignados.
- Realizar consultas básicas a la IA.

---

## Módulos principales

### Dashboard

Muestra información general del sistema y accesos rápidos a los módulos disponibles.

### Estado de PCs

Permite revisar equipos que han abierto el sistema, su conexión y estado de sincronización.

### Inventario de materia prima

Permite consultar, registrar y actualizar materia prima de la pastelería.

Ejemplos:

- Harina.
- Azúcar.
- Huevos.
- Leche.
- Chocolate.
- Fresas.
- Empaques.

También muestra si una materia prima está disponible o en bajo stock.

### Productos

Permite registrar y consultar productos fabricados por la pastelería.

Ejemplos:

- Pastel de chocolate empaquetado.
- Pastel de tres leches.
- Cupcake de vainilla.
- Galleta con chispas de chocolate.
- Cheesecake de fresa.
- Brownie empaquetado.

### Registro de producción

Permite registrar producción diaria indicando:

- Fecha.
- Producto fabricado.
- Cantidad producida.
- Línea de producción.

Al registrar producción, el sistema puede descontar automáticamente materia prima si el producto tiene una receta configurada.

### Movimientos de inventario

Muestra el historial de entradas, salidas y ajustes de materia prima.

Permite registrar movimientos como:

- Compra de materia prima.
- Salida por producción.
- Ajuste manual de stock.

### Recetas de productos

Permite relacionar productos con las materias primas necesarias para fabricarlos.

Ejemplo:

```text
Pastel de chocolate empaquetado:
- Harina: 0.50 kg
- Azúcar: 0.30 kg
- Huevos: 4 piezas
- Chocolate: 0.25 kg
```

Esta relación permite calcular consumo de materia prima al registrar producción.

### Dashboards y reportes

Muestra indicadores de producción, inventario y desempeño del sistema.

### Consultas IA

Permite hacer preguntas relacionadas con la producción, inventario y productos de la pastelería.

Ejemplos:

```text
¿Qué materias primas tenemos en inventario?
¿Cuál es el producto más fabricado?
¿Qué materiales podrían agotarse pronto?
¿Qué recomendaciones hay para mejorar la producción?
```

Las consultas realizadas a la IA se almacenan en la tabla `ai_queries`.

---

## Base de datos

La base de datos utilizada es:

```text
pasteleria_manager
```

El sistema crea automáticamente la base de datos y sus tablas al abrir la aplicación, siempre que `auto_migrate` esté activado.

También se incluye el archivo:

```text
database/schema.sql
```

como respaldo y documentación formal de la estructura de la base de datos.

---

## Conexión con XAMPP

El sistema está preparado para funcionar con MySQL en los puertos:

```text
3306
3307
```

Esto permite que el proyecto funcione en diferentes configuraciones de XAMPP sin que cada integrante tenga que modificar manualmente el puerto.

La configuración se encuentra en:

```text
app/config/database.php
app/core/Database.php
```

---

## Tablas principales

### roles

Guarda los roles del sistema.

```text
admin
user
```

### users

Guarda los usuarios registrados, sus datos, rol y contraseña protegida.

### login_logs

Guarda historial de intentos de inicio de sesión.

### materias_primas

Guarda el inventario de materia prima.

### productos

Guarda los productos fabricados por la pastelería.

### producto_materias_primas

Relaciona productos con materias primas.  
Funciona como receta técnica del producto.

### produccion_diaria

Guarda los registros de producción diaria.

### movimientos_inventario

Guarda entradas, salidas y ajustes de inventario.

### modules

Guarda los módulos disponibles del sistema.

### user_module_permissions

Guarda permisos específicos por usuario y módulo.

### workstation_status

Guarda información de los equipos que han abierto el sistema.

### ai_queries

Guarda el historial de consultas realizadas a la IA.

---

## Usuarios de prueba

El sistema crea usuarios iniciales para realizar pruebas.

```text
Usuario Admin:
admin

Contraseña:
Admin123*
```

```text
Usuario User:
user

Contraseña:
User123*
```

---

## Uso local con XAMPP

1. Copiar o clonar el proyecto dentro de:

```text
C:\xampp\htdocs
```

2. Iniciar en XAMPP:

```text
Apache
MySQL
```

3. Abrir el proyecto en el navegador:

```text
http://localhost/Proyecto-Final--Interfaz-Pasteler-a-/public/
```

4. Iniciar sesión con el usuario de prueba.

---

## Estructura general del proyecto

```text
Proyecto-Final--Interfaz-Pasteler-a-/
│
├── app/
│   ├── config/
│   │   └── database.php
│   │
│   ├── core/
│   │   └── Database.php
│   │
│   └── ...
│
├── database/
│   └── schema.sql
│
├── public/
│   ├── admin/
│   ├── modules/
│   │   ├── ai.php
│   │   ├── inventory.php
│   │   ├── inventory_movements.php
│   │   ├── product_recipes.php
│   │   ├── products.php
│   │   ├── production.php
│   │   ├── reports.php
│   │   └── workstations.php
│   │
│   └── index.php
│
├── README.md
└── index.php
```

---

## Aportaciones por alumno

## Alumno 1: Backend, login, roles, permisos y estructura base

El Alumno 1 desarrolló la base principal del sistema.

Aportaciones:

- Creación de la estructura inicial del proyecto.
- Configuración general de la aplicación.
- Creación del login.
- Manejo de sesiones.
- Creación de roles de usuario.
- Protección de rutas según permisos.
- Administración de usuarios.
- Administración de permisos por módulo.
- Configuración inicial de módulos.
- Diseño base de navegación.
- Configuración inicial de apariencia.
- Creación de usuarios de prueba.
- Creación de la estructura inicial para dashboards y módulos.

Archivos relacionados:

```text
app/
public/admin/
public/index.php
public/modules/
app/core/
app/config/
```

---

## Alumno 2: Base de datos, inventario y producción

El Alumno 2 se encargó de complementar la parte de base de datos y los módulos relacionados con la administración de producción.

Aportaciones:

- Configuración de conexión automática a MySQL en los puertos `3306` y `3307`.
- Actualización de la creación automática de la base de datos.
- Documentación de la estructura de base de datos en `database/schema.sql`.
- Creación y organización de tablas relacionadas con inventario, productos, producción, recetas y consultas IA.
- Agregado de datos iniciales para materias primas, productos y registros de producción.
- Implementación de formularios para administrar materia prima, productos y producción diaria.
- Implementación de movimientos de inventario para registrar entradas, salidas y ajustes.
- Implementación de recetas de productos para relacionar productos con las materias primas necesarias.
- Integración del descuento automático de inventario al registrar producción.
- Corrección de la conexión del módulo de IA para usar la conexión central del sistema.

Archivos relacionados:

```text
app/config/database.php
app/core/Database.php
database/schema.sql
public/modules/inventory.php
public/modules/products.php
public/modules/production.php
public/modules/inventory_movements.php
public/modules/product_recipes.php
public/modules/ai.php
```

---

## Alumno 3: Inteligencia Artificial

El Alumno 3 se encargó de la integración de consultas con Inteligencia Artificial.

Aportaciones:

- Creación del módulo de consultas IA.
- Implementación de interfaz tipo chat.
- Creación de preguntas sugeridas.
- Integración de respuestas relacionadas con producción e inventario.
- Preparación del módulo para responder preguntas de usuarios.
- Uso de información del sistema como contexto para la IA.
- Registro de consultas en la tabla `ai_queries`.

Archivos relacionados:

```text
public/modules/ai.php
app/services/
api/
```

Nota: la función de IA depende de la API externa configurada. Si la API no responde o la cuota se supera, el sistema puede mostrar un error relacionado con la consulta.

---

## Alumno 4: Frontend, dashboards y diseño visual

El Alumno 4 trabajó en la parte visual y experiencia de usuario del sistema.

Aportaciones:

- Diseño visual del sistema.
- Mejora de la interfaz principal.
- Organización del menú de navegación.
- Estilos generales de la aplicación.
- Diseño visual de módulos.
- Mejoras en formularios.
- Implementación o mejora de dashboards.
- Agregó una gráfica para visualizar información del sistema de forma más clara.
- Diseño de reportes visuales.
- Adaptación de interfaz para la temática de pastelería industrial.

Archivos relacionados:

```text
public/assets/
public/modules/reports.php
public/partials/
public/admin/dashboard.php
public/modules/
```

---

## Flujo de uso del sistema

1. El usuario entra al sistema.
2. Inicia sesión como Admin o User.
3. El sistema muestra los módulos disponibles según sus permisos.
4. El Admin puede administrar usuarios, permisos, inventario, productos y producción.
5. Se pueden registrar materias primas y productos.
6. Se pueden crear recetas relacionando productos con materia prima.
7. Se registra producción diaria.
8. El sistema descuenta inventario según la receta del producto.
9. Se guardan movimientos de inventario.
10. Se pueden consultar dashboards y reportes.
11. Se pueden hacer consultas a la IA.
12. Las consultas de IA se guardan en la base de datos.

---

## Control de versiones

El proyecto se trabaja con Git y GitHub.

Cada alumno debe trabajar en su propia rama y subir sus avances mediante commits y pull requests.

Ramas sugeridas:

```text
alumno1-backend
alumno2-base-datos
alumno3-ia
alumno4-frontend
```

---

## Notas importantes

- La base de datos se crea automáticamente desde `app/core/Database.php`.
- El archivo `database/schema.sql` sirve como respaldo y documentación.
- Los datos iniciales se insertan automáticamente al abrir el sistema.
- La conexión prueba automáticamente los puertos `3306` y `3307`.
- Las consultas IA se almacenan en `ai_queries`.
- Los movimientos de inventario se almacenan en `movimientos_inventario`.
- Las recetas de productos se almacenan en `producto_materias_primas`.

---

## Estado actual del proyecto

El sistema cuenta actualmente con:

- Login funcional.
- Roles Admin y User.
- Permisos por módulo.
- Base de datos automática.
- Inventario de materia prima.
- Catálogo de productos.
- Registro de producción.
- Movimientos de inventario.
- Recetas de productos.
- Dashboards y reportes.
- Consultas IA.
- Registro de historial de IA.
- Interfaz visual adaptada a pastelería industrial.