# Portal del paciente

Cambio limitado al inicio del paciente en `dev-EF`. La reserva de CU18, el perfil de CU17, los permisos y los datos de la base no se rehicieron. Recepción, médico y administración siguen con su panel.

## Inicio

Con el rol Paciente, `/dashboard` deja de mostrar el panel de citas del día. Ahora tiene tres secciones:

| Sección | Qué muestra | De dónde sale |
| --- | --- | --- |
| Próxima cita | La cita `PROGRAMADA` o `CONFIRMADA` más cercana cuyo día y hora todavía no pasaron | `Cita::proximaDelPaciente` |
| Reservar cita | Especialidades del catálogo. Al abrir una, sus médicos y los cupos libres de cada uno | `CtrlReserva::directorioPorEspecialidad`, `Medico::directorioConHorario`, `HorarioAtencion::proximosCuposLibres` |
| Mis citas | Pendientes y confirmadas del titular, con enlace a la lista completa | `CtrlReserva::misCitas` |

En `bd_vidasalud`, María López (`PAC-00001`) no tiene cita hoy, 2026-11-07. Su próxima cita mostrada es `CM-0009`, lunes 09/11/2026 a las 17:00, con Luis Áñez. Después siguen `CM-0008` el mismo día a las 18:00 y `CM-0006` el 11/11/2026. El panel ya no se queda en “hoy”.

El paciente no ve conteos de la clínica, barras por estado ni accesos de administración. Su menú queda en Inicio, Mis citas y Mi perfil.

## Reserva que se conserva

La reserva filtra por especialidad y fecha. Ver horarios lista los cupos libres de esa especialidad con la hora y el nombre del médico. El paciente elige uno. Ningún horario viene marcado. El formulario pide confirmación, muestra el resumen y, si no hay cupo, la causa y otras fechas. `UI_MisCitas` y `CtrlReserva::reservar` validan titular, RN16, cupo libre y solapamiento.

## Traumatología

La especialidad está activa y es la sexta del catálogo. No tiene fila en `medico_especialidad` ni horario. RN15 y la poscondición de CU07 dicen que solo se programa si hay un médico activo con horario.

No se creó un médico ni un horario, porque eso duplicaría datos o publicaría cupos que la clínica no tiene. En Reservar cita aparece el aviso de que no hay cupos. No entra al selector de especialidades. Si se fuerza un médico de otra especialidad, `CtrlCita::validarReglas` responde que no atiende Traumatología.

## Correspondencia

| Pantalla | Ruta | Frontera | Control | Modelos | Permiso |
| --- | --- | --- | --- | --- | --- |
| Inicio del paciente | `GET /dashboard` | `UI_PanelPrincipal` elige la vista si el rol es Paciente | `CtrlReserva::datosInicio` | `Cita`, `Medico`, `HorarioAtencion`, `Especialidad`, `Recordatorio`, `Paciente` | `auth` y rol Paciente |
| Reservar y seguir | `GET/POST /portal/citas` | `UI_MisCitas` | `CtrlReserva::reservar`, `misCitas`, `responderCita` | `Cita`, `HorarioAtencion`, `Recordatorio`, `Paciente` | `POR_PACIENTE` |
| Perfil | `GET/POST /portal/perfil` y `/registro` | `UI_PerfilPaciente` | `CtrlPerfil::guardarPerfil` | `Paciente`, `Usuario`, `ContactoEmergencia` | perfil `POR_PACIENTE`; registro público |
| Comprobante | `GET /citas/comprobante` | `UI_Comprobante` | — | `Cita`, `Paciente`; `GeneradorQR` | `auth`; solo la cita propia |

`UI_InicioPaciente` es la vista. No se agregó una clase de control nueva ni un permiso nuevo.

## Requisito y mejora

| Tema | Clase |
| --- | --- |
| CU17 perfil del titular, CU18 reserva con filtros, horario explícito, resumen y alternativas | Requisito ya implementado; se mantiene |
| Privacidad: Mis citas y la próxima cita solo del titular. El comprobante de `CM-0004` responde 404 a María | Requisito comprobado |
| Seis especialidades. Pediatría de 0 a 14 años: María, nacida en 1990, es rechazada | Requisito comprobado, sin insertar cita |
| Horario activo, bloqueo y solapamiento dentro de `reservar` | Requisito existente; esta pasada no creó citas |
| Recordatorio de `CM-0006`: fila real `PENDIENTE`, canal según el correo. `MAIL_HOST` está vacío, así que no se envió correo ni se simuló un proveedor | Requisito comprobado sin integración ficticia |
| QR del comprobante propio: SVG generado por `GeneradorQR`. La URL es `/citas/llegada?t=` con un token opaco, no el código de la cita | Requisito comprobado |
| Reportes, indicadores y usuarios | Fuera del paciente. Con solo `POR_PACIENTE`, `/reportes` renderiza 403 |
| Tarjetas con iniciales y próximos horarios reales | Mejora de uso. No usa marca, fotos, reseñas ni precios de otro directorio |

## Evidencia

Render del portal con la sesión de `maria.lopez` y solo el permiso `POR_PACIENTE`. No se insertó ni se editó ninguna fila: antes y después coincidieron los conteos de `cita`, `especialidad`, `medico_especialidad`, `horario_atencion` y `recordatorio`.

- Escritorio, ventana 1280×900: `docs/evidencia-portal/portal-escritorio-1280.png`
- Móvil, ventana 390×1600 y la hoja de estilo servida por `127.0.0.1:8080`: `docs/evidencia-portal/portal-movil-390.png`

En el HTML del inicio hay 18 controles dentro del portal. Son enlaces con `href`. Ninguno usa un `tabindex` positivo. El CSS pinta el foco con `focus-visible`.

## No ejecutado

- Recorrido pulsando Tab en el navegador.
- Un teléfono físico. La captura móvil es Chromium en 390 px, no el aparato.
- Envío real de correo o WhatsApp.
- Alta de una reserva nueva. Habría escrito una cita en la base.
