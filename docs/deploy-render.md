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
5. Espere el primer despliegue. El hook `scripts/inicializar-db.sh` crea el
   esquema y carga `database/02_seed.sql` una sola vez.

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

Tras comprobar el primer inicio de sesión, elimine `SEED_INITIAL_PASSWORD` del
panel de Render: solo se necesita durante la inicialización. No elimine
`DATABASE_URL`.

## Inicialización local opcional

Para cargar la misma semilla en una base local vacía:

```bash
export DATABASE_URL='postgresql://postgres:clave@127.0.0.1:5432/bd_vidasalud'
export SEED_INITIAL_PASSWORD='UnaClaveTemporal2026!'
./scripts/inicializar-db.sh
```

El script no vuelve a cargar datos si detecta que el esquema ya existe. Para
reiniciar una base de pruebas, cree una base nueva y ejecute el comando allí.
