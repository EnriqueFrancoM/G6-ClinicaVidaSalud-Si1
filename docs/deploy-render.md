# Despliegue en Render

El proyecto se publica como un Web Service de Docker y crea una base PostgreSQL
en la misma región. El Blueprint está en `render.yaml` y se encarga de enlazar
la URL privada de la base con la aplicación.

## Crear el despliegue

1. Suba estos cambios a la rama que se desplegará en GitHub.
2. En Render, seleccione **New > Blueprint** y conecte el repositorio.
3. Render detectará `render.yaml`. Confirme la creación de los dos recursos:
   `clinica-vida-salud` y `clinica-vida-salud-db`.
4. Cuando Render solicite `SEED_INITIAL_PASSWORD`, indique una contraseña
   temporal fuerte (mínimo 8 caracteres, con mayúscula, número y símbolo).
5. Espere el despliegue. Antes de iniciar Apache, el contenedor ejecuta
   `scripts/inicializar-db.sh`, que crea el esquema y carga `database/02_seed.sql`
   si la base todavía no está inicializada. Si la inicialización falla, el
   servicio no empieza a aceptar solicitudes.

Al terminar, ingrese con `carla.rojas` y la contraseña temporal elegida. La
aplicación obligará a cambiarla antes de abrir el panel. Las demás cuentas
semilla usan inicialmente la misma contraseña y también deben cambiarla en su
primer acceso.

## Variables de entorno

No agregue credenciales de PostgreSQL manualmente. Render genera y vincula
`DATABASE_URL` usando la conexión privada de `clinica-vida-salud-db`.

| Variable | Origen | Valor |
| --- | --- | --- |
| `DATABASE_URL` | `render.yaml` | Automática desde Render Postgres. |
| `SEED_INITIAL_PASSWORD` | Render solicita el secreto | Contraseña temporal fuerte para las cuentas semilla. |
| `APP_NAME` | `render.yaml` | `Clínica Vida Salud`. |
| `APP_DEBUG` | `render.yaml` | `false`. |
| `PORT` | Render | Automática; no crearla manualmente. |
| `RENDER_EXTERNAL_URL` | Render | Automática; se usa como URL base. |
| `APP_KEY` | `render.yaml` | Generada por Render. Firma los enlaces del recordatorio. |
| `BREVO_API_KEY`, `BREVO_SENDER_EMAIL` | Dashboard, secreto | API HTTPS de Brevo. Tiene prioridad: en Render el SMTP del puerto 587 se corta. El remitente debe estar verificado y el valor va sin comillas. |
| `BREVO_SMTP_USER`, `BREVO_SMTP_KEY` | Dashboard, secreto | Respaldo SMTP `smtp-relay.brevo.com:587` solo si no hay API. La clave es la SMTP (`xsmtpsib-`). |
| `MAIL_HOST`, `MAIL_USER`, `MAIL_PASS`, `MAIL_FROM` | Dashboard, secreto | SMTP propio. Solo se usa si no hay API de Brevo. |
| `BREVO_SENDER_NAME` | `render.yaml` | `Clínica Vida Salud`. |
| `RECORDATORIO_CANAL` | `render.yaml` | `auto`, `email` o `whatsapp`. |
| `WHATSAPP_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_TEMPLATE` | Dashboard, secreto | Cloud API. La plantilla cubre el aviso de 24 h. |
| `WHATSAPP_TEMPLATE_IDIOMA`, `WHATSAPP_TEMPLATE_MODO`, `WHATSAPP_PREFIJO` | `render.yaml` | `es`, `campos`, `591`. |
| `OPENROUTER_API_KEY` | Dashboard, secreto | Una clave para todas las cuentas. Vacía: el asistente no llama al modelo. |
| `OPENROUTER_MODEL` | `render.yaml` | `openrouter/free`. Elige un modelo gratuito disponible. |
| `GEMINI_API_KEY` | Dashboard, secreto | Respaldo si OpenRouter se queda sin cupo. Una clave para todas las cuentas. Vacía: no se llama a Gemini. |
| `GEMINI_MODEL` | `render.yaml` | `libre`. Recorre los modelos gratuitos y, si uno llega a su límite por minuto, sigue con el siguiente. |

La variable `SEED_INITIAL_PASSWORD` puede eliminarse después de inicializar la
base. En arranques posteriores el script detecta el esquema existente y no la
necesita. No elimine `DATABASE_URL`.

## Inicialización local opcional

Para cargar la misma semilla en una base local vacía:

```bash
export DATABASE_URL='postgresql://postgres:clave@127.0.0.1:5432/bd_vidasalud'
export SEED_INITIAL_PASSWORD='UnaClaveTemporal2026!'
./scripts/inicializar-db.sh
```

El script no vuelve a cargar datos si detecta que el esquema ya existe. Para
reiniciar una base de pruebas, cree una base nueva y ejecute el comando allí.
