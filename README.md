# Clínica Vida Salud – Sistema Web de Gestión de Citas Médicas

Grupo #06 · INF342 SC · UAGRM – FICCT · Gestión 2-2026

- **Backend y frontend:** PHP 8.2 (vistas PHP + HTML5/CSS3/JavaScript)
- **Base de datos:** PostgreSQL 16 (PDO `pdo_pgsql`)
- **Servidor:** Apache 2.4 (XAMPP)
- **Patrón:** MVC con middleware de sesión y permisos (doc. sección 6.1.2)

## Estructura

```
├── app/
│   ├── Core/                 # Núcleo MVC: App, Router, Controller, Model, View, Database, Session
│   ├── Middleware/           # AuthMiddleware: verifica sesión y permisos del rol
│   ├── Controllers/          # Un subpaquete por paquete del análisis
│   │   ├── Seguridad/        # P1: CU01–CU06 (login, usuarios, roles, contraseña, bitácora)
│   │   ├── GestionClinica/   # P2: CU07–CU10 (especialidades, médicos, horarios, pacientes)
│   │   ├── Citas/            # P3: CU11–CU16 (Ciclo 2)
│   │   ├── Portal/           # P4: CU17–CU18 (Ciclo 2)
│   │   └── Reportes/         # P5: CU19–CU20 (Ciclo 2)
│   ├── Models/               # Acceso a datos, una clase por tabla principal
│   ├── Services/             # Lógica reutilizable (p. ej. correo con PHPMailer, Ciclo 2)
│   └── Views/                # Vistas PHP agrupadas por paquete + layouts
├── config/
│   ├── config.php            # Lee el archivo .env
│   └── routes.php            # Rutas y permiso requerido de cada una
├── database/
│   ├── 01_schema.sql         # Tablas (doc. 6.3.2)
│   └── 02_seed.sql           # Datos iniciales
├── public/                   # Única carpeta expuesta por Apache
│   ├── index.php             # Front controller
│   ├── .htaccess
│   └── assets/ (css, js, img)
├── storage/logs/
└── docs/                     # Documento y diagramas del proyecto
```

### Flujo de una petición

`Navegador → public/index.php → Router → AuthMiddleware (sesión + permiso) → Controller → Model (PDO) → PostgreSQL`, y el controlador responde con una `View` dentro de un layout.

## Instalación local (XAMPP)

1. Clonar el repositorio dentro de `C:\xampp\htdocs\` (o crear un VirtualHost apuntando a `public/`).
2. En `php.ini` habilitar `extension=pdo_pgsql` y `extension=pgsql`; reiniciar Apache. Activar `mod_rewrite`.
3. Crear la base de datos y cargar los scripts:
   ```bash
   createdb -U postgres bd_vidasalud
   psql -U postgres -d bd_vidasalud -f database/01_schema.sql
   psql -U postgres -d bd_vidasalud -f database/02_seed.sql
   ```
4. Copiar `.env.example` a `.env` y completar los datos de conexión.
5. Los hashes del seed son ilustrativos: generar una contraseña real para probar (ver comentario en `02_seed.sql`).
6. Abrir `http://localhost/G6-ClinicaVidaSalud-Si1/public`.

## Cómo agregar un caso de uso

1. Modelo en `app/Models/` (extiende `App\Core\Model`).
2. Controlador en `app/Controllers/<Paquete>/` (extiende `App\Core\Controller`).
3. Vistas en `app/Views/<paquete>/`.
4. Registrar la ruta y su permiso en `config/routes.php`.
 php -S 127.0.0.1:8080 -t public public/router.php   