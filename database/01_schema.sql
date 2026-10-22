-- =========================================================
-- BD: bd_vidasalud | Sistema Web de Gestión de Citas Médicas
-- Clínica Vida Salud | PostgreSQL 16+
-- Fuente: documento G6 - Examen 2 Ciclo 1, sección 6.3.2
-- =========================================================
CREATE EXTENSION IF NOT EXISTS btree_gist;

-- ---------- P1 Seguridad y Acceso ----------
CREATE TABLE rol (
    id_rol      SERIAL PRIMARY KEY,
    nombre      VARCHAR(40)  NOT NULL UNIQUE,
    descripcion VARCHAR(150),
    estado      BOOLEAN      NOT NULL DEFAULT TRUE
);

CREATE TABLE permiso (
    id_permiso SERIAL PRIMARY KEY,
    codigo     VARCHAR(50) NOT NULL UNIQUE,
    nombre     VARCHAR(80) NOT NULL,
    modulo     VARCHAR(40) NOT NULL
);

CREATE TABLE rol_permiso (
    id_rol     INT NOT NULL REFERENCES rol(id_rol) ON UPDATE CASCADE ON DELETE CASCADE,
    id_permiso INT NOT NULL REFERENCES permiso(id_permiso) ON UPDATE CASCADE ON DELETE CASCADE,
    PRIMARY KEY (id_rol, id_permiso)
);

CREATE TABLE usuario (
    id_usuario        SERIAL PRIMARY KEY,
    id_rol            INT          NOT NULL REFERENCES rol(id_rol) ON UPDATE CASCADE,
    username          VARCHAR(40)  NOT NULL UNIQUE,
    email             VARCHAR(100) NOT NULL UNIQUE,
    password_hash     VARCHAR(255) NOT NULL,
    estado            BOOLEAN      NOT NULL DEFAULT TRUE,
    intentos_fallidos SMALLINT     NOT NULL DEFAULT 0 CHECK (intentos_fallidos >= 0),
    primer_ingreso    BOOLEAN      NOT NULL DEFAULT TRUE,
    ultimo_acceso     TIMESTAMP,
    fecha_creacion    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE bitacora (
    id_bitacora BIGSERIAL PRIMARY KEY,
    id_usuario  INT REFERENCES usuario(id_usuario) ON UPDATE CASCADE,
    fecha_hora  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    accion      VARCHAR(40) NOT NULL,
    modulo      VARCHAR(40) NOT NULL,
    detalle     TEXT,
    ip          VARCHAR(45)
);

-- ---------- P2 Gestión Clínica (Datos Maestros) ----------
CREATE TABLE especialidad (
    id_especialidad SERIAL PRIMARY KEY,
    nombre          VARCHAR(60) NOT NULL,
    descripcion     VARCHAR(200),
    estado          BOOLEAN     NOT NULL DEFAULT TRUE
);
CREATE UNIQUE INDEX ux_especialidad_nombre ON especialidad (LOWER(TRIM(nombre)));

CREATE TABLE medico (
    id_medico         SERIAL PRIMARY KEY,
    id_usuario        INT UNIQUE REFERENCES usuario(id_usuario) ON UPDATE CASCADE,
    codigo            VARCHAR(10)  NOT NULL UNIQUE,
    nro_registro_prof VARCHAR(20)  NOT NULL UNIQUE,
    nombres           VARCHAR(60)  NOT NULL,
    apellidos         VARCHAR(60)  NOT NULL,
    telefono          VARCHAR(15)  NOT NULL,
    email             VARCHAR(100),
    estado            BOOLEAN      NOT NULL DEFAULT TRUE
);

CREATE TABLE medico_especialidad (
    id_medico       INT NOT NULL REFERENCES medico(id_medico) ON UPDATE CASCADE ON DELETE CASCADE,
    id_especialidad INT NOT NULL REFERENCES especialidad(id_especialidad) ON UPDATE CASCADE,
    PRIMARY KEY (id_medico, id_especialidad)
);

CREATE TABLE horario_atencion (
    id_horario        SERIAL PRIMARY KEY,
    id_medico         INT      NOT NULL REFERENCES medico(id_medico) ON UPDATE CASCADE,
    dia_semana        SMALLINT NOT NULL CHECK (dia_semana BETWEEN 1 AND 7), -- 1 = lunes (ISO)
    hora_inicio       TIME     NOT NULL,
    hora_fin          TIME     NOT NULL,
    duracion_cita_min SMALLINT NOT NULL DEFAULT 30 CHECK (duracion_cita_min IN (15,20,30,45,60)),
    estado            BOOLEAN  NOT NULL DEFAULT TRUE,
    CONSTRAINT ck_horario_rango CHECK (hora_fin > hora_inicio)
);

CREATE TABLE bloqueo_agenda (
    id_bloqueo SERIAL PRIMARY KEY,
    id_medico  INT REFERENCES medico(id_medico) ON UPDATE CASCADE, -- NULL = feriado general
    fecha      DATE         NOT NULL,
    motivo     VARCHAR(120) NOT NULL
);

CREATE TABLE paciente (
    id_paciente           SERIAL PRIMARY KEY,
    id_usuario            INT UNIQUE REFERENCES usuario(id_usuario) ON UPDATE CASCADE,
    codigo                VARCHAR(10)  NOT NULL UNIQUE,
    ci                    VARCHAR(15)  UNIQUE,
    nombres               VARCHAR(60)  NOT NULL,
    apellidos             VARCHAR(60)  NOT NULL,
    fecha_nacimiento      DATE         NOT NULL CHECK (fecha_nacimiento <= CURRENT_DATE),
    sexo                  CHAR(1)      CHECK (sexo IN ('F','M','O')),
    telefono              VARCHAR(15)  NOT NULL,
    email                 VARCHAR(100),
    direccion             VARCHAR(150),
    tipo_sangre           VARCHAR(3)   CHECK (tipo_sangre IN ('A+','A-','B+','B-','AB+','AB-','O+','O-')),
    alergias              TEXT,
    enfermedades_cronicas TEXT,
    estado                BOOLEAN      NOT NULL DEFAULT TRUE,
    fecha_registro        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE contacto_emergencia (
    id_contacto SERIAL PRIMARY KEY,
    id_paciente INT          NOT NULL REFERENCES paciente(id_paciente) ON UPDATE CASCADE ON DELETE CASCADE,
    nombre      VARCHAR(100) NOT NULL,
    parentesco  VARCHAR(30)  NOT NULL,
    telefono    VARCHAR(15)  NOT NULL
);

-- ---------- P3 Gestión de Citas ----------
CREATE TABLE cita (
    id_cita             SERIAL PRIMARY KEY,
    codigo              VARCHAR(10) NOT NULL UNIQUE,
    id_paciente         INT         NOT NULL REFERENCES paciente(id_paciente) ON UPDATE CASCADE,
    id_medico           INT         NOT NULL REFERENCES medico(id_medico) ON UPDATE CASCADE,
    id_especialidad     INT         NOT NULL REFERENCES especialidad(id_especialidad) ON UPDATE CASCADE,
    id_usuario_registro INT         NOT NULL REFERENCES usuario(id_usuario) ON UPDATE CASCADE,
    fecha               DATE        NOT NULL,
    hora_inicio         TIME        NOT NULL,
    hora_fin            TIME        NOT NULL,
    motivo              VARCHAR(200),
    estado              VARCHAR(15) NOT NULL DEFAULT 'PROGRAMADA'
                        CHECK (estado IN ('PROGRAMADA','CONFIRMADA','ATENDIDA','CANCELADA','INASISTENCIA')),
    motivo_cancelacion  VARCHAR(200),
    fecha_creacion      TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT ck_cita_rango CHECK (hora_fin > hora_inicio),
    -- RN07: un médico no puede tener citas superpuestas (las canceladas no ocupan horario, RN09)
    CONSTRAINT ex_cita_sin_solape EXCLUDE USING gist (
        id_medico WITH =,
        tsrange(fecha + hora_inicio, fecha + hora_fin) WITH &&
    ) WHERE (estado <> 'CANCELADA')
);

CREATE TABLE historial_estado_cita (
    id_historial    SERIAL PRIMARY KEY,
    id_cita         INT         NOT NULL REFERENCES cita(id_cita) ON UPDATE CASCADE,
    id_usuario      INT         NOT NULL REFERENCES usuario(id_usuario) ON UPDATE CASCADE,
    estado_anterior VARCHAR(15),
    estado_nuevo    VARCHAR(15) NOT NULL,
    fecha_hora      TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observacion     VARCHAR(200)
);

-- ---------- P5 Reportes y Notificaciones ----------
CREATE TABLE recordatorio (
    id_recordatorio  SERIAL PRIMARY KEY,
    id_cita          INT         NOT NULL REFERENCES cita(id_cita) ON UPDATE CASCADE ON DELETE CASCADE,
    canal            VARCHAR(15) NOT NULL DEFAULT 'EMAIL' CHECK (canal IN ('EMAIL','SISTEMA')),
    fecha_programada TIMESTAMP   NOT NULL,
    estado_envio     VARCHAR(15) NOT NULL DEFAULT 'PENDIENTE' CHECK (estado_envio IN ('PENDIENTE','ENVIADO','FALLIDO'))
);

CREATE INDEX ix_cita_medico_fecha  ON cita (id_medico, fecha);
CREATE INDEX ix_cita_paciente      ON cita (id_paciente);
CREATE INDEX ix_paciente_apellidos ON paciente (apellidos, nombres);
CREATE INDEX ix_bitacora_fecha     ON bitacora (fecha_hora);
