# Manager Inteligente para Administración de Producción
## Pastelería industrial — Alumno 1: Backend, Login, Roles, Permisos y Apariencia

Este proyecto contiene la parte del **Alumno 1** del proyecto final:

- Estructura inicial del sistema.
- Conexión general a base de datos MySQL.
- Login de usuarios.
- Registro público como **User**.
- Creación de usuarios desde el panel **Admin**.
- Roles de usuario: **Admin** y **User**.
- Protección de páginas por rol.
- Protección de módulos por permisos.
- Matriz de permisos con casillas: Ver, Registrar, Editar, Eliminar y Administrar.
- Cierre de sesión.
- Redirección automática según tipo de cuenta.
- Validación para evitar usuarios repetidos.
- Contraseñas guardadas con `password_hash()`.
- Sección **solo Admin** para cambiar color primario y secundario de forma permanente.
- Contraste automático de texto según el color elegido.

La empresa simulada es una **pastelería industrial** dedicada a la producción diaria de pasteles, cupcakes, galletas y postres empaquetados.

---

## Requisitos

- XAMPP, WAMP, Laragon o servidor con PHP 8+.
- MySQL/MariaDB.
- Navegador web.
- Git, si trabajarán en equipo.

No requiere Composer ni librerías externas.

---

## Instalación rápida en XAMPP

1. Copia la carpeta del proyecto dentro de:

```txt
C:\xampp\htdocs\pasteleria_manager_alumno1
```

2. Inicia **Apache** y **MySQL** en XAMPP.

3. Abre en el navegador:

```txt
http://localhost/pasteleria_manager_alumno1/public/
```

4. La base de datos y las tablas se crean automáticamente la primera vez que se abre el sistema.

---

## Usuarios de prueba

### Administrador

```txt
Correo: admin@pasteleria.local
Usuario: admin
Contraseña: Admin123*
Rol: Admin
```

### Usuario normal

```txt
Correo: user@pasteleria.local
Usuario: user
Contraseña: User123*
Rol: User
```

---

## Módulos y permisos

El administrador puede entrar a:

```txt
/public/admin/users.php
```

Desde ahí puede:

- Crear usuarios.
- Editar usuarios existentes.
- Cambiar rol Admin/User.
- Activar o desactivar cuentas.
- Asignar permisos por módulo usando casillas.

Acciones disponibles por módulo:

- Ver.
- Registrar.
- Editar.
- Eliminar.
- Administrar.

Los módulos administrativos como **Usuarios y permisos** y **Apariencia** son solo para Admin. Aunque aparezcan en la matriz, el sistema no permite asignarlos a usuarios normales.

---

## Apariencia del sistema

La sección de apariencia está en:

```txt
/public/admin/appearance.php
```

Solo puede entrar un Admin. Permite cambiar:

- Color primario.
- Color secundario.

Los colores se guardan en la tabla `app_settings`, así que el cambio queda permanente. El sistema calcula automáticamente si el texto debe ser blanco u oscuro para que tenga contraste con el color de fondo.

---

## Configuración de base de datos

El archivo principal de configuración está en:

```txt
app/config/database.php
```

Por defecto usa:

```txt
Host: 127.0.0.1
Puerto: 3306
Base de datos: pasteleria_manager
Usuario: root
Contraseña: vacía
```

Para cada computadora, lo correcto es crear una copia del archivo:

```txt
app/config/database.local.example.php
```

y renombrarla como:

```txt
app/config/database.local.php
```

Ese archivo no se debe subir a GitHub porque puede contener contraseñas o IPs privadas.

---

## Cómo usar una base de datos “siempre online” desde tu PC 24/7

Este proyecto ya soporta una base MySQL remota. Para que tus colaboradores usen la misma base de datos:

1. Tu PC debe estar encendida y conectada a internet.
2. MySQL debe estar activo en XAMPP.
3. Debes permitir conexiones remotas a MySQL.
4. Tus compañeros deben poner la IP o dirección de tu PC en `database.local.php`.

La opción más segura para estudiantes es usar **Tailscale** o una VPN similar, porque así no expones MySQL directamente a internet.

Ejemplo para colaboradores:

```php
return [
    'host' => '100.XX.XX.XX', // IP de Tailscale de la PC que tendrá MySQL 24/7
    'port' => 3306,
    'database' => 'pasteleria_manager',
    'username' => 'equipo_pasteleria',
    'password' => 'CAMBIA_ESTA_CONTRASENA',
];
```

También se incluye una guía más detallada en:

```txt
docs/CONFIGURAR_BASE_ONLINE.md
```

---

## Rutas principales

```txt
/public/index.php                 Inicio
/public/login.php                 Login
/public/register.php              Registro público como User
/public/logout.php                Cerrar sesión
/public/dashboard.php             Redirección por rol
/public/admin/dashboard.php       Panel Admin
/public/admin/users.php           Administración de usuarios, roles y permisos
/public/admin/user_create.php     Crear usuario desde Admin
/public/admin/user_edit.php       Editar usuario y permisos
/public/admin/appearance.php      Cambiar colores globales
/public/user/dashboard.php        Panel User
/public/modules/inventory.php     Inventario de materia prima
/public/modules/products.php      Productos
/public/modules/production.php    Registro de producción
/public/modules/reports.php       Dashboards y reportes
/public/modules/ai.php            Consultas IA
/public/403.php                   Acceso denegado
```

---

## Base de datos incluida

Tablas principales de la parte del Alumno 1:

- `roles`
- `users`
- `login_logs`
- `modules`
- `user_module_permissions`
- `app_settings`

Tablas base para que los demás compañeros puedan continuar el proyecto:

- `materias_primas`
- `productos`
- `produccion_diaria`

---

## Checklist de la repartición

| Requisito del Alumno 1 | Estado |
|---|---|
| Crear estructura inicial del proyecto | Hecho |
| Configurar conexión general del sistema | Hecho |
| Crear sistema de login | Hecho |
| Crear registro de usuarios | Hecho |
| Implementar roles Admin/User | Hecho |
| Proteger páginas según rol | Hecho |
| Crear cierre de sesión | Hecho |
| Redirigir al usuario según cuenta | Hecho |
| Validar que usuarios no se repitan | Hecho |
| Guardar contraseñas con hash | Hecho |
| Crear usuarios desde Admin | Hecho |
| Asignar permisos por módulo | Hecho |
| Cambiar colores desde Admin | Hecho |
| Guardar cambios de apariencia permanentemente | Hecho |
| Ajustar contraste automáticamente | Hecho |

---

## Nota para GitHub

Este proyecto incluye `.gitignore` para evitar subir configuraciones privadas.

Sí se puede subir:

- Código PHP.
- CSS/JS.
- README.
- Estructura de carpetas.
- `database.local.example.php`.

No se debe subir:

- `database.local.php`.
- Contraseñas reales.
- Datos privados de la empresa o equipo.

## Actualización: base central en la PC del dueño y Estado de PCs

Esta versión agrega un módulo llamado **Estado de PCs**. Sirve para que el Admin vea qué equipos/navegadores han abierto el sistema y si pudieron escribir su estado en la base de datos central.

El módulo es asignable desde **Usuarios y permisos**. Por defecto solo el Admin lo tiene activo, pero se puede marcar la casilla de `Ver` para otro usuario si se desea.

### Importante sobre la base online

Si el proyecto se sube a Git y todos deben conectarse a la base de datos de la PC principal, el archivo que manda es:

```txt
app/config/database.php
```

Ahí se debe poner la IP pública, dominio DDNS o IP de VPN de la PC donde estará MySQL encendido 24/7. No uses `127.0.0.1` para colaboradores, porque eso apunta a la PC de cada colaborador.

Guía completa:

```txt
docs/BASE_DE_DATOS_CENTRAL_PC_24_7.md
```

### Qué corrige esta versión

- Agrega tabla `workstation_status`.
- Registra equipos que abren el sistema.
- Agrega botón de sincronización manual para el equipo actual.
- Evita prometer sincronización falsa de Git: una web no puede forzar `git pull` en otras PCs.
- Ajusta la lógica de semillas para no reiniciar inventario/productos cada vez que se abre el sistema.
