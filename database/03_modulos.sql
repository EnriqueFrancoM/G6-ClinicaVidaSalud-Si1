-- Módulos del panel de inicio. Cuelgan de paciente, médico, cita y especialidad.
-- La etapa logística no reemplaza el estado de la cita.

ALTER TABLE cita ADD COLUMN IF NOT EXISTS etapa VARCHAR(20) NOT NULL DEFAULT 'PROGRAMADO';

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ck_cita_etapa') THEN
        ALTER TABLE cita ADD CONSTRAINT ck_cita_etapa
            CHECK (etapa IN ('PROGRAMADO','SALA_ESPERA','EN_CONSULTA','FINALIZADO'));
    END IF;
END $$;

UPDATE cita SET etapa = 'FINALIZADO' WHERE estado = 'ATENDIDA';
UPDATE cita SET etapa = 'SALA_ESPERA' WHERE codigo = 'CM-0004';

CREATE TABLE IF NOT EXISTS cobro (
    id_cobro  SERIAL PRIMARY KEY,
    id_cita   INT            NOT NULL REFERENCES cita(id_cita) ON UPDATE CASCADE,
    concepto  VARCHAR(80)    NOT NULL,
    monto     NUMERIC(10,2)  NOT NULL CHECK (monto >= 0),
    estado    VARCHAR(15)    NOT NULL DEFAULT 'PENDIENTE' CHECK (estado IN ('PENDIENTE','PAGADO'))
);

CREATE TABLE IF NOT EXISTS historia_clinica (
    id_historia SERIAL PRIMARY KEY,
    id_cita     INT NOT NULL UNIQUE REFERENCES cita(id_cita) ON UPDATE CASCADE,
    anamnesis   TEXT,
    motivo      VARCHAR(200),
    subjetivo   TEXT,
    objetivo    TEXT,
    analisis    TEXT,
    plan        TEXT
);

CREATE TABLE IF NOT EXISTS receta (
    id_receta    SERIAL PRIMARY KEY,
    id_cita      INT          NOT NULL REFERENCES cita(id_cita) ON UPDATE CASCADE,
    id_paciente  INT          NOT NULL REFERENCES paciente(id_paciente) ON UPDATE CASCADE,
    id_medico    INT          NOT NULL REFERENCES medico(id_medico) ON UPDATE CASCADE,
    medicamento  VARCHAR(80)  NOT NULL,
    dosis        VARCHAR(80)  NOT NULL,
    indicacion   VARCHAR(200) NOT NULL,
    fecha        DATE         NOT NULL DEFAULT CURRENT_DATE
);

CREATE TABLE IF NOT EXISTS resultado (
    id_resultado SERIAL PRIMARY KEY,
    id_paciente  INT          NOT NULL REFERENCES paciente(id_paciente) ON UPDATE CASCADE,
    nombre       VARCHAR(80)  NOT NULL,
    fecha        DATE         NOT NULL,
    detalle      VARCHAR(200) NOT NULL
);

CREATE TABLE IF NOT EXISTS derivacion (
    id_derivacion   SERIAL PRIMARY KEY,
    id_paciente     INT          NOT NULL REFERENCES paciente(id_paciente) ON UPDATE CASCADE,
    id_medico       INT          NOT NULL REFERENCES medico(id_medico) ON UPDATE CASCADE,
    id_especialidad INT          NOT NULL REFERENCES especialidad(id_especialidad) ON UPDATE CASCADE,
    motivo          VARCHAR(200) NOT NULL,
    estado          VARCHAR(15)  NOT NULL DEFAULT 'SOLICITADA' CHECK (estado IN ('SOLICITADA','ACEPTADA'))
);

CREATE TABLE IF NOT EXISTS medicion_crecimiento (
    id_medicion   SERIAL PRIMARY KEY,
    id_paciente   INT           NOT NULL REFERENCES paciente(id_paciente) ON UPDATE CASCADE,
    fecha         DATE          NOT NULL,
    peso_kg       NUMERIC(5,2)  NOT NULL,
    talla_cm      NUMERIC(5,1)  NOT NULL,
    perimetro_cm  NUMERIC(4,1)
);

CREATE TABLE IF NOT EXISTS vacuna (
    id_vacuna  SERIAL PRIMARY KEY,
    nombre     VARCHAR(60) NOT NULL UNIQUE,
    edad_meses SMALLINT    NOT NULL CHECK (edad_meses >= 0)
);

CREATE TABLE IF NOT EXISTS vacuna_paciente (
    id_aplicacion    SERIAL PRIMARY KEY,
    id_paciente      INT         NOT NULL REFERENCES paciente(id_paciente) ON UPDATE CASCADE,
    id_vacuna        INT         NOT NULL REFERENCES vacuna(id_vacuna) ON UPDATE CASCADE,
    fecha_aplicacion DATE,
    estado           VARCHAR(15) NOT NULL CHECK (estado IN ('APLICADA','PENDIENTE')),
    UNIQUE (id_paciente, id_vacuna)
);

CREATE TABLE IF NOT EXISTS antecedente_perinatal (
    id_paciente    INT PRIMARY KEY REFERENCES paciente(id_paciente) ON UPDATE CASCADE,
    apgar          SMALLINT NOT NULL CHECK (apgar BETWEEN 0 AND 10),
    peso_nacer_g   INT      NOT NULL CHECK (peso_nacer_g > 0),
    semanas        SMALLINT NOT NULL CHECK (semanas BETWEEN 20 AND 45)
);

CREATE TABLE IF NOT EXISTS embarazo (
    id_embarazo SERIAL PRIMARY KEY,
    id_paciente INT  NOT NULL REFERENCES paciente(id_paciente) ON UPDATE CASCADE,
    fum         DATE NOT NULL,
    estado      VARCHAR(15) NOT NULL DEFAULT 'EN_CURSO' CHECK (estado IN ('EN_CURSO','FINALIZADO'))
);

CREATE TABLE IF NOT EXISTS historial_obstetrico (
    id_paciente INT PRIMARY KEY REFERENCES paciente(id_paciente) ON UPDATE CASCADE,
    gestas      SMALLINT NOT NULL CHECK (gestas >= 0),
    partos      SMALLINT NOT NULL CHECK (partos >= 0),
    abortos     SMALLINT NOT NULL CHECK (abortos >= 0),
    cesareas    SMALLINT NOT NULL CHECK (cesareas >= 0)
);

CREATE TABLE IF NOT EXISTS examen_preventivo (
    id_examen   SERIAL PRIMARY KEY,
    id_paciente INT          NOT NULL REFERENCES paciente(id_paciente) ON UPDATE CASCADE,
    tipo        VARCHAR(20)  NOT NULL CHECK (tipo IN ('PAPANICOLAOU','MAMOGRAFIA','COLPOSCOPIA')),
    fecha       DATE,
    resultado   VARCHAR(120) NOT NULL
);

CREATE TABLE IF NOT EXISTS pieza_dental (
    id_pieza    SERIAL PRIMARY KEY,
    id_paciente INT         NOT NULL REFERENCES paciente(id_paciente) ON UPDATE CASCADE,
    diente      SMALLINT    NOT NULL CHECK (diente BETWEEN 11 AND 85),
    condicion   VARCHAR(20) NOT NULL CHECK (condicion IN ('SANO','CARIES','EXTRACCION','IMPLANTE','TRATAMIENTO')),
    UNIQUE (id_paciente, diente)
);

CREATE TABLE IF NOT EXISTS periodonto (
    id_periodonto   SERIAL PRIMARY KEY,
    id_paciente     INT          NOT NULL REFERENCES paciente(id_paciente) ON UPDATE CASCADE,
    diente          SMALLINT     NOT NULL,
    profundidad_mm  NUMERIC(3,1) NOT NULL CHECK (profundidad_mm >= 0),
    UNIQUE (id_paciente, diente)
);

CREATE TABLE IF NOT EXISTS fase_tratamiento (
    id_fase      SERIAL PRIMARY KEY,
    id_paciente  INT           NOT NULL REFERENCES paciente(id_paciente) ON UPDATE CASCADE,
    id_medico    INT           NOT NULL REFERENCES medico(id_medico) ON UPDATE CASCADE,
    nombre       VARCHAR(80)   NOT NULL,
    orden        SMALLINT      NOT NULL,
    presupuesto  NUMERIC(10,2) NOT NULL CHECK (presupuesto >= 0),
    pagado       NUMERIC(10,2) NOT NULL DEFAULT 0 CHECK (pagado >= 0),
    realizado    BOOLEAN       NOT NULL DEFAULT FALSE
);

CREATE TABLE IF NOT EXISTS signo_vital (
    id_signo    SERIAL PRIMARY KEY,
    id_paciente INT      NOT NULL REFERENCES paciente(id_paciente) ON UPDATE CASCADE,
    fecha       DATE     NOT NULL,
    sistolica   SMALLINT NOT NULL,
    diastolica  SMALLINT NOT NULL,
    frecuencia  SMALLINT NOT NULL,
    colesterol  SMALLINT,
    hdl         SMALLINT
);

CREATE TABLE IF NOT EXISTS estudio (
    id_estudio  SERIAL PRIMARY KEY,
    id_paciente INT         NOT NULL REFERENCES paciente(id_paciente) ON UPDATE CASCADE,
    id_medico   INT         NOT NULL REFERENCES medico(id_medico) ON UPDATE CASCADE,
    tipo        VARCHAR(20) NOT NULL CHECK (tipo IN ('ECG','HOLTER','ECOCARDIOGRAMA')),
    fecha       DATE        NOT NULL,
    lectura     TEXT        NOT NULL
);

INSERT INTO paciente (codigo, ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, direccion, tipo_sangre)
SELECT 'PAC-00006','5566778','Camila','Ferrufino Rojas','1994-05-02','F','70112233','Las Palmas','O+'
 WHERE NOT EXISTS (SELECT 1 FROM paciente WHERE codigo = 'PAC-00006');

INSERT INTO cobro (id_cita, concepto, monto, estado)
SELECT c.id_cita, v.concepto, v.monto, v.estado
  FROM (VALUES
        ('CM-0001','Copago consulta', 50.00, 'PAGADO'),
        ('CM-0004','Copago cardiología', 80.00, 'PENDIENTE'),
        ('CM-0005','Copago odontología', 150.00, 'PENDIENTE')
       ) AS v(codigo, concepto, monto, estado)
  JOIN cita c ON c.codigo = v.codigo
 WHERE NOT EXISTS (SELECT 1 FROM cobro k WHERE k.id_cita = c.id_cita AND k.concepto = v.concepto);

INSERT INTO historia_clinica (id_cita, anamnesis, motivo, subjetivo, objetivo, analisis, plan)
SELECT c.id_cita,
       'Sin antecedentes quirúrgicos. Alergia a penicilina.',
       'Consulta general',
       'Cefalea de dos días, sin fiebre.',
       'PA 118/76. FC 74. Afebril.',
       'Cefalea tensional.',
       'Reposo, hidratación y control si persiste.'
  FROM cita c
 WHERE c.codigo = 'CM-0001'
   AND NOT EXISTS (SELECT 1 FROM historia_clinica h WHERE h.id_cita = c.id_cita);

INSERT INTO receta (id_cita, id_paciente, id_medico, medicamento, dosis, indicacion, fecha)
SELECT c.id_cita, c.id_paciente, c.id_medico, 'Loratadina', '10 mg cada 24 h por 5 días', 'No usar penicilina.', c.fecha
  FROM cita c
 WHERE c.codigo = 'CM-0001'
   AND NOT EXISTS (SELECT 1 FROM receta r WHERE r.id_cita = c.id_cita);

INSERT INTO resultado (id_paciente, nombre, fecha, detalle)
SELECT p.id_paciente, 'Hemograma', DATE '2026-10-19', 'Hemoglobina 13.4 g/dL. Leucocitos normales.'
  FROM paciente p
 WHERE p.codigo = 'PAC-00001'
   AND NOT EXISTS (SELECT 1 FROM resultado r WHERE r.id_paciente = p.id_paciente AND r.nombre = 'Hemograma');

INSERT INTO derivacion (id_paciente, id_medico, id_especialidad, motivo, estado)
SELECT p.id_paciente, m.id_medico, e.id_especialidad, 'Hipertensión no controlada. Solicita valoración.', 'SOLICITADA'
  FROM paciente p
  JOIN medico m ON m.codigo = 'MED-001'
  JOIN especialidad e ON e.nombre = 'Cardiología'
 WHERE p.codigo = 'PAC-00002'
   AND NOT EXISTS (
        SELECT 1 FROM derivacion d
         WHERE d.id_paciente = p.id_paciente AND d.id_especialidad = e.id_especialidad
   );

INSERT INTO medicion_crecimiento (id_paciente, fecha, peso_kg, talla_cm, perimetro_cm)
SELECT p.id_paciente, v.fecha, v.peso, v.talla, v.perimetro
  FROM paciente p
  JOIN (VALUES
        (DATE '2017-11-04', 9.10, 74.0, 45.0),
        (DATE '2019-11-04', 14.20, 95.0, 48.5),
        (DATE '2022-11-04', 21.00, 115.0, NULL),
        (DATE '2026-10-20', 28.40, 132.0, NULL)
       ) AS v(fecha, peso, talla, perimetro) ON TRUE
 WHERE p.codigo = 'PAC-00003'
   AND NOT EXISTS (
        SELECT 1 FROM medicion_crecimiento m
         WHERE m.id_paciente = p.id_paciente AND m.fecha = v.fecha
   );

INSERT INTO vacuna (nombre, edad_meses) VALUES
 ('BCG', 0),
 ('Hepatitis B', 0),
 ('Pentavalente 1', 2),
 ('Pentavalente 2', 4),
 ('Pentavalente 3', 6),
 ('SRP', 12),
 ('dTpa', 48)
ON CONFLICT (nombre) DO NOTHING;

INSERT INTO vacuna_paciente (id_paciente, id_vacuna, fecha_aplicacion, estado)
SELECT p.id_paciente, v.id_vacuna, a.fecha, a.estado
  FROM paciente p
  JOIN (VALUES
        ('BCG', DATE '2016-11-04', 'APLICADA'),
        ('Hepatitis B', DATE '2016-11-04', 'APLICADA'),
        ('Pentavalente 1', DATE '2017-01-04', 'APLICADA'),
        ('Pentavalente 2', DATE '2017-03-04', 'APLICADA'),
        ('Pentavalente 3', DATE '2017-05-04', 'APLICADA'),
        ('SRP', DATE '2017-11-04', 'APLICADA'),
        ('dTpa', NULL::date, 'PENDIENTE')
       ) AS a(nombre, fecha, estado) ON TRUE
  JOIN vacuna v ON v.nombre = a.nombre
 WHERE p.codigo = 'PAC-00003'
ON CONFLICT (id_paciente, id_vacuna) DO NOTHING;

INSERT INTO antecedente_perinatal (id_paciente, apgar, peso_nacer_g, semanas)
SELECT id_paciente, 9, 3200, 39 FROM paciente WHERE codigo = 'PAC-00003'
ON CONFLICT (id_paciente) DO NOTHING;

INSERT INTO embarazo (id_paciente, fum, estado)
SELECT id_paciente, DATE '2026-06-08', 'EN_CURSO' FROM paciente WHERE codigo = 'PAC-00006'
 AND NOT EXISTS (
    SELECT 1 FROM embarazo e
     WHERE e.id_paciente = (SELECT id_paciente FROM paciente WHERE codigo = 'PAC-00006')
       AND e.estado = 'EN_CURSO'
 );

INSERT INTO historial_obstetrico (id_paciente, gestas, partos, abortos, cesareas)
SELECT id_paciente, 2, 1, 0, 0 FROM paciente WHERE codigo = 'PAC-00006'
ON CONFLICT (id_paciente) DO NOTHING;

INSERT INTO examen_preventivo (id_paciente, tipo, fecha, resultado)
SELECT p.id_paciente, v.tipo, v.fecha, v.resultado
  FROM paciente p
  JOIN (VALUES
        ('PAPANICOLAOU', DATE '2025-08-12', 'Negativo'),
        ('MAMOGRAFIA', NULL::date, 'Pendiente'),
        ('COLPOSCOPIA', DATE '2025-09-02', 'Sin lesiones')
       ) AS v(tipo, fecha, resultado) ON TRUE
 WHERE p.codigo = 'PAC-00006'
   AND NOT EXISTS (
        SELECT 1 FROM examen_preventivo x
         WHERE x.id_paciente = p.id_paciente AND x.tipo = v.tipo
   );

INSERT INTO pieza_dental (id_paciente, diente, condicion)
SELECT p.id_paciente, v.diente, v.condicion
  FROM paciente p
  JOIN (VALUES
        (16, 'CARIES'),
        (26, 'CARIES'),
        (18, 'EXTRACCION'),
        (36, 'TRATAMIENTO'),
        (46, 'IMPLANTE')
       ) AS v(diente, condicion) ON TRUE
 WHERE p.codigo = 'PAC-00005'
ON CONFLICT (id_paciente, diente) DO NOTHING;

INSERT INTO periodonto (id_paciente, diente, profundidad_mm)
SELECT p.id_paciente, v.diente, v.mm
  FROM paciente p
  JOIN (VALUES (16, 3.0), (26, 4.0), (36, 2.5), (46, 3.5)) AS v(diente, mm) ON TRUE
 WHERE p.codigo = 'PAC-00005'
ON CONFLICT (id_paciente, diente) DO NOTHING;

INSERT INTO fase_tratamiento (id_paciente, id_medico, nombre, orden, presupuesto, pagado, realizado)
SELECT p.id_paciente, m.id_medico, v.nombre, v.orden, v.presupuesto, v.pagado, v.realizado
  FROM paciente p
  JOIN medico m ON m.codigo = 'MED-004'
  JOIN (VALUES
        ('Limpieza', 1, 350.00, 350.00, TRUE),
        ('Resina pieza 16', 2, 400.00, 0.00, FALSE),
        ('Control de implante 46', 3, 2800.00, 1500.00, TRUE)
       ) AS v(nombre, orden, presupuesto, pagado, realizado) ON TRUE
 WHERE p.codigo = 'PAC-00005'
   AND NOT EXISTS (
        SELECT 1 FROM fase_tratamiento f
         WHERE f.id_paciente = p.id_paciente AND f.nombre = v.nombre
   );

INSERT INTO signo_vital (id_paciente, fecha, sistolica, diastolica, frecuencia, colesterol, hdl)
SELECT p.id_paciente, v.fecha, v.sis, v.dia, v.fc, v.col, v.hdl
  FROM paciente p
  JOIN (VALUES
        (DATE '2026-04-21', 148, 92, 78, 240, 42),
        (DATE '2026-07-15', 152, 94, 80, NULL, NULL),
        (DATE '2026-10-21', 158, 96, 84, 246, 40)
       ) AS v(fecha, sis, dia, fc, col, hdl) ON TRUE
 WHERE p.codigo = 'PAC-00004'
   AND NOT EXISTS (
        SELECT 1 FROM signo_vital s
         WHERE s.id_paciente = p.id_paciente AND s.fecha = v.fecha
   );

INSERT INTO estudio (id_paciente, id_medico, tipo, fecha, lectura)
SELECT p.id_paciente, m.id_medico, 'ECG', DATE '2026-10-21', 'Ritmo sinusal. Sin signos de isquemia aguda.'
  FROM paciente p
  JOIN medico m ON m.codigo = 'MED-005'
 WHERE p.codigo = 'PAC-00004'
   AND NOT EXISTS (
        SELECT 1 FROM estudio e
         WHERE e.id_paciente = p.id_paciente AND e.tipo = 'ECG' AND e.fecha = DATE '2026-10-21'
   );
