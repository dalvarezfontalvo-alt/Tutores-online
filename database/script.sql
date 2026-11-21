/*
    TutoresOn-Line - Script completo de base de datos (SQL Server / Azure SQL)

    COMO USARLO
    1. Crea una base de datos vacia llamada  tutoresonline  (en Azure SQL: desde el portal).
    2. Conectate a ESA base en SSMS / Azure Data Studio y ejecuta este archivo completo.
    3. Se puede ejecutar mas de una vez: no borra nada y no duplica datos.

    Usuarios de prueba (todos con contrasena  Prueba123* ):
      Tutores:     ana.lopez@demo.com   carlos.ruiz@demo.com   laura.mejia@demo.com
      Estudiantes: sofia.gomez@demo.com   pedro.diaz@demo.com
*/

-- ============ TABLAS ============

IF OBJECT_ID(N'dbo.roles', N'U') IS NULL
CREATE TABLE dbo.roles (
    id_rol INT IDENTITY(1,1) PRIMARY KEY,
    nombre NVARCHAR(30) NOT NULL UNIQUE
);
GO

IF OBJECT_ID(N'dbo.usuarios', N'U') IS NULL
CREATE TABLE dbo.usuarios (
    id_usuario INT IDENTITY(1,1) PRIMARY KEY,
    nombre NVARCHAR(100) NOT NULL,
    apellido NVARCHAR(100) NOT NULL DEFAULT N'',
    correo NVARCHAR(150) NOT NULL UNIQUE,
    contrasena NVARCHAR(255) NOT NULL,          -- hash de password_hash()
    id_rol INT NOT NULL REFERENCES dbo.roles(id_rol),
    estado BIT NOT NULL DEFAULT 1,
    fecha_registro DATETIME2(0) NOT NULL DEFAULT SYSDATETIME()
);
GO

IF OBJECT_ID(N'dbo.estudiantes', N'U') IS NULL
CREATE TABLE dbo.estudiantes (
    id_estudiante INT IDENTITY(1,1) PRIMARY KEY,
    id_usuario INT NOT NULL UNIQUE REFERENCES dbo.usuarios(id_usuario)
);
GO

IF OBJECT_ID(N'dbo.tutores', N'U') IS NULL
CREATE TABLE dbo.tutores (
    id_tutor INT IDENTITY(1,1) PRIMARY KEY,
    id_usuario INT NOT NULL UNIQUE REFERENCES dbo.usuarios(id_usuario),
    estado BIT NOT NULL DEFAULT 1,
    precio_hora DECIMAL(10,2) NULL,
    modalidad NVARCHAR(20) NULL,
    CONSTRAINT ck_tutores_precio_hora CHECK (precio_hora IS NULL OR precio_hora >= 0),
    CONSTRAINT ck_tutores_modalidad CHECK (modalidad IS NULL OR modalidad IN (N'online', N'presencial', N'ambas'))
);
GO

IF OBJECT_ID(N'dbo.materias', N'U') IS NULL
CREATE TABLE dbo.materias (
    id_materia INT IDENTITY(1,1) PRIMARY KEY,
    nombre NVARCHAR(120) NOT NULL UNIQUE,
    estado BIT NOT NULL DEFAULT 1
);
GO

IF OBJECT_ID(N'dbo.tutor_materia', N'U') IS NULL
CREATE TABLE dbo.tutor_materia (
    id_tutor INT NOT NULL REFERENCES dbo.tutores(id_tutor),
    id_materia INT NOT NULL REFERENCES dbo.materias(id_materia),
    PRIMARY KEY (id_tutor, id_materia)
);
GO

IF OBJECT_ID(N'dbo.tutorias', N'U') IS NULL
CREATE TABLE dbo.tutorias (
    id_tutoria INT IDENTITY(1,1) PRIMARY KEY,
    id_estudiante INT NOT NULL REFERENCES dbo.estudiantes(id_estudiante),
    id_tutor INT NOT NULL REFERENCES dbo.tutores(id_tutor),
    id_materia INT NOT NULL REFERENCES dbo.materias(id_materia),
    fecha DATE NOT NULL,
    hora_inicio TIME(0) NOT NULL,
    estado NVARCHAR(20) NOT NULL DEFAULT N'Pendiente',
    CONSTRAINT ck_tutorias_estado CHECK (estado IN (N'Pendiente', N'Confirmada', N'Cancelada', N'Completada'))
);
GO

IF OBJECT_ID(N'dbo.resenas', N'U') IS NULL
CREATE TABLE dbo.resenas (
    id_resena INT IDENTITY(1,1) PRIMARY KEY,
    id_tutoria INT NOT NULL UNIQUE REFERENCES dbo.tutorias(id_tutoria),
    calificacion TINYINT NOT NULL CHECK (calificacion BETWEEN 1 AND 5),
    comentario NVARCHAR(500) NULL,
    fecha DATETIME2(0) NOT NULL DEFAULT SYSDATETIME()
);
GO

-- ============ DATOS INICIALES ============

IF NOT EXISTS (SELECT 1 FROM dbo.roles WHERE nombre = N'estudiante') INSERT INTO dbo.roles (nombre) VALUES (N'estudiante');
IF NOT EXISTS (SELECT 1 FROM dbo.roles WHERE nombre = N'tutor')      INSERT INTO dbo.roles (nombre) VALUES (N'tutor');
GO

-- Usuarios de prueba (contrasena: Prueba123*)
DECLARE @hash NVARCHAR(255) = N'$2b$10$JUU4Rvg7JgkDHxHXnbXFauIxkFsjiydmV5TIKtNW61bIWnbm3seqS';
DECLARE @rolTutor INT = (SELECT id_rol FROM dbo.roles WHERE nombre = N'tutor');
DECLARE @rolEst   INT = (SELECT id_rol FROM dbo.roles WHERE nombre = N'estudiante');

INSERT INTO dbo.usuarios (nombre, apellido, correo, contrasena, id_rol)
SELECT v.nombre, v.apellido, v.correo, @hash, v.rol
FROM (VALUES
    (N'Ana',    N'López',  N'ana.lopez@demo.com',   @rolTutor),
    (N'Carlos', N'Ruiz',   N'carlos.ruiz@demo.com', @rolTutor),
    (N'Laura',  N'Mejía',  N'laura.mejia@demo.com', @rolTutor),
    (N'Sofía',  N'Gómez',  N'sofia.gomez@demo.com', @rolEst),
    (N'Pedro',  N'Díaz',   N'pedro.diaz@demo.com',  @rolEst)
) AS v(nombre, apellido, correo, rol)
WHERE NOT EXISTS (SELECT 1 FROM dbo.usuarios u WHERE u.correo = v.correo);

INSERT INTO dbo.tutores (id_usuario, precio_hora, modalidad)
SELECT u.id_usuario, v.precio, v.modalidad
FROM (VALUES
    (N'ana.lopez@demo.com',   35000.00, N'ambas'),
    (N'carlos.ruiz@demo.com', 28000.00, N'online'),
    (N'laura.mejia@demo.com', 40000.00, N'presencial')
) AS v(correo, precio, modalidad)
JOIN dbo.usuarios u ON u.correo = v.correo
WHERE NOT EXISTS (SELECT 1 FROM dbo.tutores t WHERE t.id_usuario = u.id_usuario);

INSERT INTO dbo.estudiantes (id_usuario)
SELECT u.id_usuario FROM dbo.usuarios u
WHERE u.correo IN (N'sofia.gomez@demo.com', N'pedro.diaz@demo.com')
  AND NOT EXISTS (SELECT 1 FROM dbo.estudiantes e WHERE e.id_usuario = u.id_usuario);

INSERT INTO dbo.materias (nombre)
SELECT v.nombre FROM (VALUES (N'Matemáticas'), (N'Inglés'), (N'Programación'), (N'Física')) AS v(nombre)
WHERE NOT EXISTS (SELECT 1 FROM dbo.materias m WHERE m.nombre = v.nombre);

INSERT INTO dbo.tutor_materia (id_tutor, id_materia)
SELECT t.id_tutor, m.id_materia
FROM (VALUES
    (N'ana.lopez@demo.com',   N'Matemáticas'),
    (N'carlos.ruiz@demo.com', N'Programación'),
    (N'laura.mejia@demo.com', N'Inglés')
) AS v(correo, materia)
JOIN dbo.usuarios u ON u.correo = v.correo
JOIN dbo.tutores t ON t.id_usuario = u.id_usuario
JOIN dbo.materias m ON m.nombre = v.materia
WHERE NOT EXISTS (SELECT 1 FROM dbo.tutor_materia x WHERE x.id_tutor = t.id_tutor AND x.id_materia = m.id_materia);

-- Tutorias de ejemplo (fechas relativas a hoy para que siempre haya "proximas")
IF NOT EXISTS (SELECT 1 FROM dbo.tutorias)
BEGIN
    DECLARE @sofia INT = (SELECT e.id_estudiante FROM dbo.estudiantes e JOIN dbo.usuarios u ON u.id_usuario = e.id_usuario WHERE u.correo = N'sofia.gomez@demo.com');
    DECLARE @pedro INT = (SELECT e.id_estudiante FROM dbo.estudiantes e JOIN dbo.usuarios u ON u.id_usuario = e.id_usuario WHERE u.correo = N'pedro.diaz@demo.com');
    DECLARE @ana   INT = (SELECT t.id_tutor FROM dbo.tutores t JOIN dbo.usuarios u ON u.id_usuario = t.id_usuario WHERE u.correo = N'ana.lopez@demo.com');
    DECLARE @carlos INT = (SELECT t.id_tutor FROM dbo.tutores t JOIN dbo.usuarios u ON u.id_usuario = t.id_usuario WHERE u.correo = N'carlos.ruiz@demo.com');
    DECLARE @laura INT = (SELECT t.id_tutor FROM dbo.tutores t JOIN dbo.usuarios u ON u.id_usuario = t.id_usuario WHERE u.correo = N'laura.mejia@demo.com');
    DECLARE @mat INT = (SELECT id_materia FROM dbo.materias WHERE nombre = N'Matemáticas');
    DECLARE @prog INT = (SELECT id_materia FROM dbo.materias WHERE nombre = N'Programación');
    DECLARE @ing INT = (SELECT id_materia FROM dbo.materias WHERE nombre = N'Inglés');

    INSERT INTO dbo.tutorias (id_estudiante, id_tutor, id_materia, fecha, hora_inicio, estado) VALUES
        (@sofia, @ana,    @mat,  DATEADD(DAY,  3, CAST(GETDATE() AS DATE)), '15:00', N'Pendiente'),
        (@sofia, @carlos, @prog, DATEADD(DAY,  5, CAST(GETDATE() AS DATE)), '10:00', N'Confirmada'),
        (@pedro, @ana,    @mat,  DATEADD(DAY,  2, CAST(GETDATE() AS DATE)), '16:30', N'Pendiente'),
        (@sofia, @laura,  @ing,  DATEADD(DAY, -7, CAST(GETDATE() AS DATE)), '09:00', N'Completada'),
        (@pedro, @carlos, @prog, DATEADD(DAY,-10, CAST(GETDATE() AS DATE)), '14:00', N'Completada');

    INSERT INTO dbo.resenas (id_tutoria, calificacion, comentario)
    SELECT t.id_tutoria, v.cal, v.coment
    FROM (VALUES
        (@sofia, @laura,  5, N'Excelente clase, muy clara y paciente.'),
        (@pedro, @carlos, 4, N'Buena explicación de los temas.')
    ) AS v(est, tut, cal, coment)
    JOIN dbo.tutorias t ON t.id_estudiante = v.est AND t.id_tutor = v.tut AND t.estado = N'Completada';
END
GO
