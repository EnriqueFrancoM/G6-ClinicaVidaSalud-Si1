-- =========================================================
-- Migración del Ciclo #2 para bases ya creadas (se puede ejecutar varias veces).
-- Las bases nuevas ya reciben todo desde 01_schema.sql y 02_seed.sql.
-- =========================================================
-- Este archivo está en UTF-8. Sin esta línea, psql en Windows lo lee como WIN1252.
SET client_encoding = 'UTF8';

-- CU01: espera progresiva tras intentos fallidos
ALTER TABLE usuario ADD COLUMN IF NOT EXISTS bloqueo_hasta TIMESTAMPTZ;

-- CU21 Gestionar lista de espera (ver 01_schema.sql)
CREATE TABLE IF NOT EXISTS solicitud_espera (
    id_solicitud        SERIAL PRIMARY KEY,
    id_paciente         INT          NOT NULL REFERENCES paciente(id_paciente) ON UPDATE CASCADE,
    id_especialidad     INT          NOT NULL REFERENCES especialidad(id_especialidad) ON UPDATE CASCADE,
    id_medico           INT          REFERENCES medico(id_medico) ON UPDATE CASCADE,
    fecha_desde         DATE,
    fecha_hasta         DATE,
    preferencias        VARCHAR(200),
    fecha_ingreso       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado              VARCHAR(15)  NOT NULL DEFAULT 'ACTIVA'
                        CHECK (estado IN ('ACTIVA','OFERTADA','ASIGNADA','CANCELADA')),
    oferta_id_medico    INT          REFERENCES medico(id_medico) ON UPDATE CASCADE,
    oferta_fecha        DATE,
    oferta_hora_inicio  TIME,
    oferta_hora_fin     TIME,
    oferta_hasta        TIMESTAMP,
    id_cita             INT          REFERENCES cita(id_cita) ON UPDATE CASCADE,
    id_usuario_registro INT          NOT NULL REFERENCES usuario(id_usuario) ON UPDATE CASCADE,
    CONSTRAINT ck_solicitud_rango CHECK (fecha_hasta IS NULL OR fecha_desde IS NULL OR fecha_hasta >= fecha_desde)
);
CREATE UNIQUE INDEX IF NOT EXISTS ux_solicitud_vigente ON solicitud_espera (id_paciente, id_especialidad)
    WHERE estado IN ('ACTIVA','OFERTADA');

-- RN15: la clínica ofrece seis especialidades
INSERT INTO especialidad (nombre, descripcion)
SELECT 'Traumatología', 'Lesiones y enfermedades del sistema musculoesquelético'
 WHERE NOT EXISTS (SELECT 1 FROM especialidad WHERE LOWER(TRIM(nombre)) = 'traumatología');
