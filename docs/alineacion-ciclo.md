# Alineación del parcial — Clínica Vida Salud

Nota local para corregir el documento a mano. No modifica el PDF ni el Google Doc.
Los prototipos del documento se dejan como están. Las pantallas actuales de la
aplicación se mantienen: no se rediseñaron para copiar los wireframes del ciclo 2.

Fecha de la evidencia en base local `bd_vidasalud`: 2026-11-07.
Esquema medido: 16 tablas base, 24 claves foráneas, `usuario.bloqueo_hasta`
presente, `solicitud_espera` presente. Citas de prueba `ZZ-%`: 0.

## Prototipos

| Documento | Qué es | Decisión |
| --- | --- | --- |
| Ciclo 1, figuras de la sección de interfaces | Capturas de la interfaz ya implementada | Se mantienen. No se redibujan. |
| Ciclo 2, láminas rotuladas «ui CUxx · Prototipo propuesto» | Wireframe. El propio pie dice que no es una pantalla implementada | No se editan y no son meta de rediseño. |

Sobre esas pantallas actuales sí se ajustó el uso, sin cambiar nombres de clases
ni de métodos:

- El menú agrupa Atención, Gestión clínica y Administración. Los grupos vacíos no se muestran.
- Para recepción (tiene `CIT_GESTIONAR` y no tiene `USU_GESTIONAR`, `MED_GESTIONAR` ni `REP_GENERAR`) se destacan Programar cita y Agenda.
- La agenda de todos los médicos (`id_medico = 0`) solo aparece si el usuario puede gestionar citas y no está forzado a su propio médico. El médico sigue viendo solo su agenda.
- Programar, reprogramar y reservar no marcan el primer horario. Hay un resumen antes de confirmar.
- Si no hay cupos, la pantalla dice la causa (no atiende, bloqueo, ocupados o ya pasaron) y ofrece otras fechas. En programar también ofrece otros médicos de la especialidad y el alta en lista de espera, cuando esos datos existen.
- El error de un campo queda junto al campo (`error_detalle` / `campo_error`). No depende del aviso que desaparece.

## Matriz CU01–CU22

La columna Prototipo remite a la lámina del documento. La pantalla en código es la actual.
«Prueba» distingue lo ejecutado en este entorno de lo que no tiene evidencia.

| CU | Requisito | Ruta | Frontera | Control | Datos / servicio | Permiso | Prueba |
| --- | --- | --- | --- | --- | --- | --- | --- |
| CU01 Iniciar sesión | Acceso con bloqueo progresivo | `GET/POST /login` | `UI_IniciarSesion` | `CtrlAutenticacion::autenticar` | `Usuario` (`bloqueo_hasta`) | pública | La columna existe y el login deja de fallar por ella. Recorrido en navegador: **NO EJECUTADA** |
| CU02 Cerrar sesión | Fin de sesión | `POST /logout` | `UI_PanelPrincipal` | `CtrlAutenticacion::cerrarSesion` | sesión | `auth` | **NO EJECUTADA** en navegador |
| CU03 Gestionar usuarios | Alta y modificación | `GET/POST /usuarios` | `UI_GestionarUsuarios` | `CtrlUsuario::guardarUsuario` | `Usuario`, `Rol` | `USU_GESTIONAR` | **NO EJECUTADA** de caja negra |
| CU04 Roles y permisos | Matriz de permisos | `GET/POST /roles` | `UI_RolesPermisos` | `CtrlRol::guardarRol` | `Rol`, `Permiso`, `RolPermiso` | `ROL_GESTIONAR` | **NO EJECUTADA** de caja negra |
| CU05 Cambiar contraseña | Cambio y primer ingreso | `GET/POST /cambiar-contrasena` | `UI_CambiarContrasena` | `CtrlContrasena` | `Usuario` | `auth` | **NO EJECUTADA** de caja negra |
| CU06 Bitácora | Consulta, filtro y exportación | `GET /bitacora`, `/bitacora/filtrar`, `/bitacora/exportar` | `UI_Bitacora` | `CtrlBitacora::consultar` | `Bitacora` | `BIT_CONSULTAR` | **NO EJECUTADA** de caja negra |
| CU07 Especialidades | Registro | `GET/POST /especialidades` | `UI_Especialidades` | `CtrlEspecialidad::guardar` | `Especialidad` | `ESP_GESTIONAR` | **NO EJECUTADA** de caja negra |
| CU08 Médicos | Registro | `GET/POST /medicos` | `UI_Medicos` | `CtrlMedico::guardarMedico` | `Medico`, `MedicoEspecialidad`, `Usuario` | `MED_GESTIONAR` | **NO EJECUTADA** de caja negra |
| CU09 Horarios | Horario y bloqueo | `GET/POST /horarios`, `POST /horarios/bloqueo` | `UI_Horarios` | `CtrlHorario` | `HorarioAtencion`, `BloqueoAgenda` | `HOR_GESTIONAR` | Domingo sin horario: causa «no atiende». Alta de horario en navegador: **NO EJECUTADA** |
| CU10 Pacientes | Búsqueda, ficha y registro | `GET/POST /pacientes`, `GET /pacientes/ficha` | `UI_Pacientes` | `CtrlPaciente::guardarPaciente` | `Paciente`, `ContactoEmergencia` | `PAC_GESTIONAR` | **NO EJECUTADA** de caja negra |
| CU11 Programar cita | Cupo, resumen y registro | `GET/POST /citas/programar` | `UI_ProgramarCita` | `CtrlCita::programar` | `Cita`, `HorarioAtencion`, `Paciente`, `Recordatorio` | `CIT_GESTIONAR` | Fechas `2026-02-31` y `31/02/2026` rechazadas. HTML sin horario premarcado, con resumen o causa, menú agrupado y destacado |
| CU12 Reprogramar | Nuevo horario y recordatorio | `GET/POST /citas/reprogramar` | `UI_ReprogramarCita` | `CtrlCita::reprogramar` | `Cita::actualizarHorario`, `Recordatorio::reprogramar` | `CIT_GESTIONAR` | Update solo si está `PROGRAMADA` o `CONFIRMADA`. Tras `CANCELADA` no cambia la fecha. Si el recordatorio ya estaba `ENVIADO`, queda uno `PENDIENTE` nuevo |
| CU13 Cancelar | Motivo y estado previo | `GET/POST /citas/cancelar` | `UI_CancelarCita` | `CtrlCita` | `Cita::cancelarConMotivo` | `CIT_GESTIONAR` | La cancelación concurrente bloquea la fila: la otra conexión espera el `FOR UPDATE` (timeout `57014`). Commit simultáneo de las dos operaciones: **NO EJECUTADA** |
| CU14 Asistencia y llegada | Estado y QR | `GET/POST /citas/estado`, `GET/POST /citas/llegada` | `UI_EstadoCita` | `CtrlEstadoCita::registrarLlegada` | `Cita`, `HistorialEstadoCita` | estado: `auth` y regla interna (`CIT_GESTIONAR` o `CIT_ATENDER`). Llegada: `CIT_GESTIONAR` | GET no cambia el estado. POST de una cita de hoy pasa a `CONFIRMADA`. Invitado sin sesión en HTTP: 404 del enrutador, no es la prueba del POST |
| CU15 Agenda | Día del médico o de todos | `GET /agenda` | `UI_AgendaMedica` | `CtrlAgenda::consultarAgenda` | `Cita::buscarAgendaDia`, `HorarioAtencion` | `AGE_CONSULTAR`. «Todos» además exige `CIT_GESTIONAR` y no ser médico propio | Médico con `id_medico=0` ve solo la suya. Recepción ve `todos`. Fecha `2026-02-31` rechazada |
| CU16 Historial de citas | Consulta por paciente | `GET /citas/historial` | `UI_HistorialCitas` | `CtrlHistorialCitas::consultarHistorial` | `Cita`, `Paciente` | `CIT_GESTIONAR` | **NO EJECUTADA** de caja negra |
| CU17 Perfil del paciente | Registro público y perfil | `GET/POST /registro`, `GET/POST /portal/perfil` | `UI_PerfilPaciente` | `CtrlPerfil::guardarPerfil` | `Paciente`, `Usuario`, `ContactoEmergencia` | registro público; perfil `POR_PACIENTE` | **NO EJECUTADA** de caja negra |
| CU18 Reservar en línea | Cupos y seguimiento | `GET/POST /portal/citas` | `UI_MisCitas` | `CtrlReserva` | `Cita`, `HorarioAtencion` | `POR_PACIENTE` | Misma regla de fecha inválida en código. Recorrido del paciente: **NO EJECUTADA** |
| CU19 Reportes | Vista, PDF y Excel | `GET /reportes` | `UI_Reportes` | `CtrlReporte::generarReporte` | `ExportadorReporte` | `REP_GENERAR` | Período 2026-10-01 a 2026-11-30 devuelve filas. PDF empieza con `%PDF`. XLSX empieza con `PK` y contiene `xl/worksheets/sheet1.xml`. Fecha `2026-02-31` rechazada. Clic de descarga en el navegador: **NO EJECUTADA**. La prueba no escribió bitácora porque no envió `formato` |
| CU20 Recordatorios | Envío y respuesta firmada | `GET/POST /recordatorios`, `GET/POST /recordatorio/responder` | `ServicioRecordatorios` | `CtrlRecordatorio` | `Recordatorio`, `ProveedorNotificaciones`, `CanalRecordatorio` | panel `REP_GENERAR`; el enlace es público y firmado | La firma cambia si cambian la acción o el horario. Un token de cancelar no confirma. Un token de otro horario responde «ya cambió». GET no aplica la respuesta; POST sí. Canal `EMAIL`, `WHATSAPP` o `SISTEMA`. Envío real por Brevo o WhatsApp: **NO EJECUTADA** |
| CU21 Lista de espera | Alta y aceptación de oferta | `GET/POST /lista-espera` | `UI_ListaEspera` | `CtrlListaEspera` | `SolicitudEspera`, `HorarioAtencion`, `Cita` | `CIT_GESTIONAR` | Aceptar revalida el cupo, deja la solicitud `ASIGNADA` y una sola cita. La segunda aceptación no crea otra |
| CU22 Indicadores | Panel del período | `GET /indicadores` | `UI_Indicadores` | `CtrlIndicador::calcularIndicadores` | consultas de `Cita` | `REP_GENERAR` | **NO EJECUTADA** de caja negra. La fecha inválida usa `App::fechaIso` |

Comprobante (`GET /citas/comprobante`, `UI_Comprobante`, sesión): recepción (`CIT_GESTIONAR`) ve cualquiera; el médico solo el suyo; el paciente solo el propio. `AGE_CONSULTAR` ya no abre todos. Probado por reflexión sobre `puedeVer`, no con captura. La reserva del portal se registra al confirmar, sin pasarela de pago.

Las fronteras muestran el resultado con `mostrarResultado()`. En el código no existe `presentarResultado()`.

## Correcciones para el documento

Aplicarlas en el documento. Este archivo no las escribe allí.

1. Esquema auditado en `bd_vidasalud`: 16 tablas y 24 relaciones (claves foráneas). Tablas: `bitacora`, `bloqueo_agenda`, `cita`, `contacto_emergencia`, `especialidad`, `historial_estado_cita`, `horario_atencion`, `medico`, `medico_especialidad`, `paciente`, `permiso`, `recordatorio`, `rol`, `rol_permiso`, `solicitud_espera`, `usuario`. Incluir `usuario.bloqueo_hasta`. No eliminar `solicitud_espera` para forzar un conteo viejo de 15 tablas y 18 relaciones. `recordatorio.canal` admite `EMAIL`, `SISTEMA` y `WHATSAPP`. Eso no agrega tabla ni clave foránea.
2. Los diagramas de secuencia deben decir `CtrlAutenticacion` y `CtrlUsuario`. En el código no están `AuthController` ni `UsuarioController`.
3. Donde el diagrama diga `presentarResultado()`, debe decir `mostrarResultado()`.
4. El modelo lógico y el físico son dos diagramas completos e integrados, no uno por paquete.
5. Componentes: un diagrama general y uno por paquete. Paquetes reales: Seguridad, Gestión clínica, Citas, Portal, Reportes.
   - Presentación: `app/Views/` y las clases `UI_*` (más `ServicioRecordatorios` como frontera de CU20).
   - Control: `CtrlAutenticacion`, `CtrlUsuario`, `CtrlRol`, `CtrlContrasena`, `CtrlBitacora`, `CtrlEspecialidad`, `CtrlMedico`, `CtrlHorario`, `CtrlPaciente`, `CtrlCita`, `CtrlEstadoCita`, `CtrlAgenda`, `CtrlHistorialCitas`, `CtrlListaEspera`, `CtrlPerfil`, `CtrlReserva`, `CtrlReporte`, `CtrlIndicador`, `CtrlRecordatorio`.
   - Datos: `app/Models/`.
   - Servicios: `app/Services/ExportadorReporte.php`, `GeneradorQR.php`, `ProveedorNotificaciones.php`.
6. Los prototipos del ciclo 2 siguen como propuesta gráfica. La implementación de referencia es la pantalla actual.

## Cambios de código de esta pasada

- `App::url` arma la URL con el host de la petición cuando no coincide con `APP_URL`. Local y producción siguen usando `APP_URL` si el host coincide. No hay dominio de túnel escrito en el código.
- `App::fechaIso` exige una fecha real `Y-m-d`. Reemplaza los `createFromFormat(...)?->format`, que rompían con textos como `31/02/2026` porque `createFromFormat` devuelve `false` y `?->` no cubre `false`.
- Llegada y respuesta de recordatorio: GET muestra la confirmación; el cambio de estado es POST, con CSRF.
- Reprogramar no actualiza una cita que ya no está `PROGRAMADA` o `CONFIRMADA`. El recordatorio pendiente se mueve; si ya no hay pendiente, se programa uno nuevo.
- Aceptar una oferta bloquea la fila, vuelve a validar el cupo y solo marca `ASIGNADA` si la cita se creó.
- El comprobante no se abre con el solo permiso de consultar agenda.

## Evidencia ejecutada

Script fuera del repositorio, datos sintéticos, borrado al terminar. Resultado de la segunda corrida: `RESUMEN ok=37 fallo=0`. Después, `cita.codigo LIKE 'ZZ-%'` = 0.

Contra el servidor ya levantado en `127.0.0.1:8080` (no se reinició):

- Login local: la hoja de estilo sale como `http://127.0.0.1:8080/assets/css/app.css`.
- Misma ruta con `Host: tunel.example` y `X-Forwarded-Proto: https`: `https://tunel.example/assets/css/app.css`.
- `GET /citas/llegada` sin sesión: HTTP 404. No prueba el flujo autenticado; ese flujo se probó dentro del script.

## NO EJECUTADA

- Recorrido de teclado.
- Viewport móvil real. Un zoom de escritorio no cuenta.
- Capturas de caja negra de CU01–CU22.
- Clic de descarga del PDF o del Excel en el navegador.
- Envío de correo o WhatsApp.
- Commit simultáneo de cancelar y reprogramar en dos procesos que lleguen a confirmar. Sí se probó la espera sobre el bloqueo de fila y el rechazo cuando la cita ya está cancelada.
- Login visual con las cuentas semilla. Esas contraseñas locales ya no son las del seed y no se restablecieron.

## Contraste de entrega

| Pedido | Estado |
| --- | --- |
| Herramientas con ventajas y desventajas | Pendiente en el documento. El código no lo sustituye. |
| Componentes: presentación, control y datos, con archivos reales | Los archivos están. Falta dibujar el diagrama general y uno por paquete. |
| Interfaz adaptable y accesible | Hay ajustes de uso sobre la identidad actual. Teclado y móvil real: **NO EJECUTADA**. |
| PDF y Excel utilizables | Generados en proceso (`%PDF` y XLSX con hoja). Descarga en navegador: **NO EJECUTADA**. |
| Caja negra de los CU importantes, con capturas | **NO EJECUTADA**. |

Voz e IA siguen fuera de este parcial. Correo con Brevo y recordatorio por WhatsApp o correo quedan listos por variables de entorno. Sin claves el correo no se envía y WhatsApp no se elige. El paciente reserva sin pago. No hay pasarela de pago. Envío real: **NO EJECUTADA**.

## Pendiente manual

- Pasar al documento las cinco correcciones de la sección anterior.
- Dejar los prototipos del ciclo 2 sin cambios.
- Completar capturas de caja negra, teclado y un ancho móvil real antes de afirmar usabilidad o cumplimiento de un CU solo porque la pantalla existe.
