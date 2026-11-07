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
│   │   ├── Citas/            # P3: CU11–CU16 y CU21 (Ciclo 2)
│   │   ├── Portal/           # P4: CU17–CU18 (Ciclo 2)
│   │   └── Reportes/         # P5: CU19, CU20 y CU22 (Ciclo 2)
│   ├── Models/               # Acceso a datos, una clase por tabla principal
│   ├── Services/             # ExportadorReporte (PDF/XLSX), ProveedorNotificaciones (SMTP), GeneradorQR
│   └── Views/                # Vistas PHP agrupadas por paquete + layouts
├── config/
│   ├── config.php            # Lee el archivo .env
│   └── routes.php            # Rutas y permiso requerido de cada una
├── database/
│   ├── 01_schema.sql         # Tablas (doc. 6.3.2)
│   ├── 02_seed.sql           # Datos iniciales
│   └── 03_ciclo2.sql         # Migración del Ciclo #2 para bases ya creadas
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
   createdb -U postgres -E UTF8 -T template0 bd_vidasalud
   export DATABASE_URL='postgresql://postgres:clave@127.0.0.1:5432/bd_vidasalud'
   export SEED_INITIAL_PASSWORD='UnaClaveTemporal2026!'
   ./scripts/inicializar-db.sh
   ```
   Todas las cuentas semilla utilizan inicialmente `SEED_INITIAL_PASSWORD` y se les exige cambiarla al ingresar.
4. Copiar `.env.example` a `.env` y completar los datos de conexión.
5. Los hashes del seed son ilustrativos: generar una contraseña real para probar (ver comentario en `02_seed.sql`).
6. Abrir `http://localhost/G6-ClinicaVidaSalud-Si1/public`.

Para publicar en Render, consulte [docs/deploy-render.md](docs/deploy-render.md). El archivo `render.yaml` crea y enlaza el Web Service y PostgreSQL automáticamente.

## Ciclo #2 (CU11–CU22)

| Paquete | Caso de uso | Ruta | Interfaz → Control |
|---|---|---|---|
| P3 | CU11 Programar cita | `/citas/programar` | `UI_ProgramarCita` → `CtrlCita.programar()` |
| P3 | CU12 Reprogramar cita | `/citas/reprogramar` | `UI_ReprogramarCita` → `CtrlCita.reprogramar()` |
| P3 | CU13 Cancelar cita | `/citas/cancelar` | `UI_CancelarCita` → `CtrlCita.cancelar()` |
| P3 | CU14 Registrar asistencia y estado | `/citas/estado` | `UI_EstadoCita` → `CtrlEstadoCita.cambiarEstado()` |
| P3 | CU15 Consultar agenda médica | `/agenda` | `UI_AgendaMedica` → `CtrlAgenda.consultarAgenda()` |
| P3 | CU16 Historial de citas del paciente | `/citas/historial` | `UI_HistorialCitas` → `CtrlHistorialCitas.consultarHistorial()` |
| P3 | CU21 Lista de espera | `/lista-espera` | `UI_ListaEspera` → `CtrlListaEspera.registrarSolicitud()` |
| P4 | CU17 Perfil del paciente (y registro) | `/portal/perfil`, `/registro` | `UI_PerfilPaciente` → `CtrlPerfil.guardarPerfil()` |
| P4 | CU18 Reservar y seguir citas | `/portal/citas` | `UI_MisCitas` → `CtrlReserva.reservar()` |
| P5 | CU19 Generar reportes (PDF/XLSX) | `/reportes` | `UI_Reportes` → `CtrlReporte.generarReporte()` |
| P5 | CU20 Enviar recordatorios | `/recordatorios` y cron | `ServicioRecordatorios` → `CtrlRecordatorio.procesarPendientes()` |
| P5 | CU22 Panel de indicadores | `/indicadores` | `UI_Indicadores` → `CtrlIndicador.calcularIndicadores()` |

Mejoras: comprobante con QR (`/citas/comprobante`, RF24), prioridad pediátrica en la agenda (RF23) y enlace firmado para confirmar o cancelar desde el recordatorio (`/recordatorio/responder`, RF21).

**Base existente:** aplicar `database/03_ciclo2.sql` (en Render lo hace `scripts/inicializar-db.sh` al arrancar).

**Recordatorios (actor Tiempo):** programar `php scripts/enviar-recordatorios.php` cada 15 minutos con cron (o el Programador de tareas de Windows). El canal lo define `RECORDATORIO_CANAL` (`auto`, `email` o `whatsapp`). `auto` usa WhatsApp si `WHATSAPP_TOKEN` y `WHATSAPP_PHONE_NUMBER_ID` están puestos y el paciente tiene teléfono; si no, correo; si no hay correo, el aviso queda en el portal. El correo sale por `MAIL_*` o, si `MAIL_HOST` está vacío, por el SMTP de Brevo (`BREVO_SMTP_USER` y `BREVO_SMTP_KEY`) o por la API (`BREVO_API_KEY` y `BREVO_SENDER_EMAIL`). Sin proveedor el envío queda FALLIDO: no se simula un éxito. La plantilla de WhatsApp (`WHATSAPP_TEMPLATE`) es necesaria para el aviso de 24 h. `APP_KEY` firma los enlaces de confirmar y cancelar. La lista completa está en `.env.example`. El paciente reserva sin pago.

## Cómo agregar un caso de uso

1. Modelo en `app/Models/` (extiende `App\Core\Model`).
2. Controlador en `app/Controllers/<Paquete>/` (extiende `App\Core\Controller`).
3. Vistas en `app/Views/<paquete>/`.
4. Registrar la ruta y su permiso en `config/routes.php`.
 php -S 127.0.0.1:8080 -t public public/router.php   
