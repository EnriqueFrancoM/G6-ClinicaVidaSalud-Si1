-- =========================================================
-- Datos iniciales (documento G6, sección 6.3.2 - Insert())
-- NOTA: los password_hash del documento son ilustrativos y NO permiten iniciar sesión.
-- Genera uno real con:  php -r "echo password_hash('TuClave', PASSWORD_DEFAULT);"
-- y aplícalo con:       UPDATE usuario SET password_hash = '<hash>' WHERE username = 'carla.rojas';
-- =========================================================
INSERT INTO rol (nombre, descripcion) VALUES
 ('Administrador','Configura y supervisa el sistema'),
 ('Recepcionista','Gestiona pacientes y citas'),
 ('Médico','Consulta su agenda y registra atenciones'),
 ('Paciente','Gestiona su perfil y sus citas en línea');

INSERT INTO permiso (codigo, nombre, modulo) VALUES
 ('USU_GESTIONAR','Gestionar usuarios','Seguridad'),
 ('ROL_GESTIONAR','Gestionar roles y permisos','Seguridad'),
 ('BIT_CONSULTAR','Consultar bitácora','Seguridad'),
 ('ESP_GESTIONAR','Gestionar especialidades','Gestión clínica'),
 ('MED_GESTIONAR','Gestionar médicos','Gestión clínica'),
 ('HOR_GESTIONAR','Gestionar horarios','Gestión clínica'),
 ('PAC_GESTIONAR','Gestionar pacientes','Gestión clínica'),
 ('CIT_GESTIONAR','Programar, reprogramar y cancelar citas','Citas'),
 ('AGE_CONSULTAR','Consultar agenda médica','Citas'),
 ('CIT_ATENDER','Marcar cita como atendida','Citas'),
 ('REP_GENERAR','Generar reportes','Reportes'),
 ('POR_PACIENTE','Usar portal del paciente','Portal');

-- Administrador: todos los permisos excepto el portal del paciente
INSERT INTO rol_permiso (id_rol, id_permiso) SELECT 1, id_permiso FROM permiso WHERE codigo <> 'POR_PACIENTE';
INSERT INTO rol_permiso (id_rol, id_permiso) SELECT 2, id_permiso FROM permiso WHERE codigo IN ('PAC_GESTIONAR','CIT_GESTIONAR','AGE_CONSULTAR');
INSERT INTO rol_permiso (id_rol, id_permiso) SELECT 3, id_permiso FROM permiso WHERE codigo IN ('AGE_CONSULTAR','CIT_ATENDER');
INSERT INTO rol_permiso (id_rol, id_permiso) SELECT 4, id_permiso FROM permiso WHERE codigo = 'POR_PACIENTE';

INSERT INTO usuario (id_rol, username, email, password_hash, primer_ingreso, ultimo_acceso) VALUES
 (1,'carla.rojas','carla.rojas@vidasalud.bo','$2y$10$Qe7n1kq8wJ0yYzS3QxS8Me1u5b7c9dXo2V4l6TzP8rK0aB1cD2eF3',FALSE,'2026-10-21 08:20:00'),
 (2,'recepcion1','recepcion1@vidasalud.bo','$2y$10$Hk2m9Lw4xT6pQ1rS5uV7WeY3z8A0bC2dE4fG6hI8jK0lM2nO4pQ6r',FALSE,'2026-10-21 08:01:12'),
 (2,'recepcion2','recepcion2@vidasalud.bo','$2y$10$Zx9c8V7b6N5m4L3k2J1hGfD0sA9pO8iU7yT6rE5wQ4aS3dF2gH1jK',TRUE,NULL),
 (3,'dr.jperez','jperez@vidasalud.bo','$2y$10$Pq1W2e3R4t5Y6u7I8o9P0aS1d2F3g4H5j6K7l8Z9x0C1v2B3n4M5q',FALSE,'2026-10-21 08:31:40'),
 (3,'dra.agutierrez','agutierrez@vidasalud.bo','$2y$10$Lm0Nb9Vc8Xz7As6Sd5Df4Fg3Gh2Hj1Jk0Kl9Lq8Qw7We6Er5Rt4Ty',FALSE,'2026-10-20 14:02:00'),
 (4,'maria.lopez','maria.lopez@gmail.com','$2y$10$Aa1Bb2Cc3Dd4Ee5Ff6Gg7Hh8Ii9Jj0Kk1Ll2Mm3Nn4Oo5Pp6Qq7Rr',FALSE,'2026-10-19 21:15:00');

INSERT INTO especialidad (nombre, descripcion) VALUES
 ('Medicina general','Atención primaria y consulta general'),
 ('Pediatría','Atención de niños de 0 a 14 años'),
 ('Ginecología','Salud integral de la mujer'),
 ('Odontología','Salud bucal'),
 ('Cardiología','Diagnóstico y tratamiento de enfermedades del corazón');

INSERT INTO medico (id_usuario, codigo, nro_registro_prof, nombres, apellidos, telefono, email) VALUES
 (4,'MED-001','CMB-12345','Juan','Pérez Rojas','71011223','jperez@vidasalud.bo'),
 (5,'MED-002','CMB-20811','Ana','Gutiérrez Suárez','72033445','agutierrez@vidasalud.bo'),
 (NULL,'MED-003','CMB-18764','Luis','Áñez Justiniano','73055667','lanez@vidasalud.bo'),
 (NULL,'MED-004','COB-05522','Rosa','Vaca Díez','74077889','rvaca@vidasalud.bo'),
 (NULL,'MED-005','CMB-09931','Marco','Antelo Saucedo','75099001','mantelo@vidasalud.bo');

INSERT INTO medico_especialidad VALUES (1,1),(2,2),(3,3),(4,4),(5,5),(5,1);

INSERT INTO horario_atencion (id_medico, dia_semana, hora_inicio, hora_fin, duracion_cita_min) VALUES
 (1,1,'08:00','12:00',30),(1,3,'14:00','18:00',30),(1,5,'08:00','11:00',30),
 (2,2,'09:00','13:00',30),(2,4,'09:00','13:00',30),
 (3,1,'15:00','19:00',30),(4,2,'08:00','12:00',30),(4,6,'08:00','12:00',30),
 (5,3,'08:00','12:00',30);

INSERT INTO bloqueo_agenda (id_medico, fecha, motivo) VALUES
 (NULL,'2026-11-02','Feriado: Día de Todos los Difuntos'),
 (3,'2026-11-16','Congreso de ginecología');

INSERT INTO paciente (id_usuario, codigo, ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, email, direccion, tipo_sangre, alergias, enfermedades_cronicas) VALUES
 (6,'PAC-00001','6321458','María','López Fernández','1990-03-12','F','71588420','maria.lopez@gmail.com','Equipetrol, calle 7 Este #15','O+','Penicilina',NULL),
 (NULL,'PAC-00002','8812345','Carlos','Justiniano Vaca','1984-07-25','M','76012345',NULL,'Av. Banzer 4to anillo','A+',NULL,'Hipertensión'),
 (NULL,'PAC-00003','12765432','Valentina','Rojas Áñez','2016-11-04','F','70845123',NULL,'Plan 3000, UV 160','B+',NULL,NULL),
 (NULL,'PAC-00004','4512398','Pedro','Suárez Méndez','1958-01-30','M','72133001',NULL,'Barrio Hamacas','O-','Sulfas','Diabetes tipo 2'),
 (NULL,'PAC-00005','9034567','Lucía','Montero Paz','1999-09-18','F','77788990','lucia.mp@gmail.com','Villa 1ro de Mayo','A-',NULL,NULL);

INSERT INTO contacto_emergencia (id_paciente, nombre, parentesco, telefono) VALUES
 (1,'José López','Padre','70011223'),(3,'Carmen Áñez','Madre','70845100'),(4,'Rosa Méndez','Esposa','72133002');

INSERT INTO cita (codigo, id_paciente, id_medico, id_especialidad, id_usuario_registro, fecha, hora_inicio, hora_fin, motivo, estado) VALUES
 ('CM-0001',1,1,1,2,'2026-10-19','08:00','08:30','Consulta general','ATENDIDA'),
 ('CM-0002',2,1,1,2,'2026-10-19','08:30','09:00','Control de presión arterial','INASISTENCIA'),
 ('CM-0003',3,2,2,2,'2026-10-20','09:00','09:30','Control de niño sano','ATENDIDA'),
 ('CM-0004',4,5,5,3,'2026-10-21','08:00','08:30','Dolor torácico leve','CONFIRMADA'),
 ('CM-0005',5,4,4,2,'2026-10-27','08:00','08:30','Limpieza dental','PROGRAMADA'),
 ('CM-0006',1,1,1,6,'2026-11-11','14:00','14:30','Resultados de laboratorio','PROGRAMADA');
INSERT INTO cita (codigo, id_paciente, id_medico, id_especialidad, id_usuario_registro, fecha, hora_inicio, hora_fin, motivo, estado, motivo_cancelacion) VALUES
 ('CM-0007',2,1,1,2,'2026-11-11','14:00','14:30','Control','CANCELADA','Paciente viaja fuera de la ciudad');

INSERT INTO historial_estado_cita (id_cita, id_usuario, estado_anterior, estado_nuevo, observacion) VALUES
 (1,2,NULL,'PROGRAMADA','Registro inicial'),(1,4,'PROGRAMADA','ATENDIDA',NULL),
 (2,2,'PROGRAMADA','INASISTENCIA','No se presentó'),(7,2,'PROGRAMADA','CANCELADA','Paciente viaja fuera de la ciudad');

INSERT INTO recordatorio (id_cita, canal, fecha_programada) VALUES
 (5,'EMAIL','2026-10-26 08:00'),(6,'EMAIL','2026-11-10 14:00');

INSERT INTO bitacora (id_usuario, accion, modulo, detalle, ip, fecha_hora) VALUES
 (2,'LOGIN','Seguridad',NULL,'192.168.1.21','2026-10-21 08:01:12'),
 (2,'REGISTRAR','Pacientes','PAC-00005','192.168.1.21','2026-10-21 08:05:40'),
 (1,'MODIFICAR','Médicos','MED-005','192.168.1.10','2026-10-21 08:20:03'),
 (4,'LOGIN_FALLIDO','Seguridad','Contraseña incorrecta','192.168.1.35','2026-10-21 08:31:17'),
 (4,'LOGIN','Seguridad',NULL,'192.168.1.35','2026-10-21 08:31:40'),
 (2,'LOGOUT','Seguridad',NULL,'192.168.1.21','2026-10-21 12:02:09');
