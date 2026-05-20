# Guía para usar la base de datos online desde la PC del equipo

El código ya está preparado para conectarse a una base de datos MySQL remota. Lo importante es configurar bien la PC que va a estar encendida 24/7.

## Opción recomendada: Tailscale

Es mejor usar Tailscale porque crea una red privada entre las computadoras del equipo. Así no tienes que abrir el puerto 3306 de MySQL a todo internet.

### En la PC que tendrá la base de datos

1. Instalar XAMPP.
2. Iniciar MySQL.
3. Instalar Tailscale.
4. Iniciar sesión en Tailscale.
5. Copiar la IP de Tailscale de esa PC. Normalmente empieza con `100.`.

### Crear usuario MySQL para el equipo

En phpMyAdmin o consola MySQL, ejecutar algo parecido a esto:

```sql
CREATE DATABASE IF NOT EXISTS pasteleria_manager
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'equipo_pasteleria'@'%' IDENTIFIED BY 'CAMBIA_ESTA_CONTRASENA';
GRANT ALL PRIVILEGES ON pasteleria_manager.* TO 'equipo_pasteleria'@'%';
FLUSH PRIVILEGES;
```

En un proyecto real, la contraseña debe ser más fuerte y no se debe subir a GitHub.

### Permitir conexiones remotas en XAMPP

En algunas instalaciones hay que editar el archivo de configuración de MySQL/MariaDB.

Busca algo como:

```txt
bind-address = 127.0.0.1
```

y cámbialo a:

```txt
bind-address = 0.0.0.0
```

Después reinicia MySQL desde XAMPP.

Si no aparece esa línea, puede que tu instalación ya permita conexiones o que tengas que agregarla en la sección correcta del archivo de configuración.

### En las computadoras de los colaboradores

Cada colaborador debe crear este archivo:

```txt
app/config/database.local.php
```

con este contenido:

```php
<?php
return [
    'host' => '100.XX.XX.XX',
    'port' => 3306,
    'database' => 'pasteleria_manager',
    'username' => 'equipo_pasteleria',
    'password' => 'CAMBIA_ESTA_CONTRASENA',
];
```

La IP debe ser la IP de Tailscale de la PC donde está MySQL.

---

## Opción no recomendada: abrir el puerto 3306 en el router

También podrías abrir el puerto 3306 en el router, pero no es recomendable porque MySQL quedaría expuesto a internet.

Si aun así lo hacen para una práctica escolar, mínimo deberían:

- Usar contraseña fuerte.
- No usar el usuario `root` para conexiones remotas.
- Crear un usuario limitado para el proyecto.
- Abrir solo el puerto necesario.
- Cerrar el puerto después de la entrega.

---

## Prueba rápida de conexión

Cuando esté configurado, abre:

```txt
http://localhost/pasteleria_manager_alumno1/public/setup.php
```

Si todo está bien, debe mostrar que la conexión fue exitosa y que las tablas existen.
