<?php
require_once __DIR__ . "/../config/sesion.php";

if (!isset($_SESSION["usuario"]["rol"]) || $_SESSION["usuario"]["rol"] !== "tutor") {
    if (isset($_SESSION["usuario"]["rol"]) && $_SESSION["usuario"]["rol"] === "estudiante") {
        header("Location: ../usuarios/panel_estudiante.php");
    } else {
        header("Location: ../usuarios/login.php");
    }
    exit;
}

if (
    !isset($_SESSION["usuario"]["id"]) ||
    (int) $_SESSION["usuario"]["id"] < 1
) {
    header("Location: ../usuarios/login.php");
    exit;
}

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

function consultarFilas($conexion, $sql, $parametros = array())
{
    $stmt = sqlsrv_query($conexion, $sql, $parametros);
    if ($stmt === false) {
        error_log(print_r(sqlsrv_errors(), true));
        throw new RuntimeException("No se pudieron consultar los datos. Intenta de nuevo más tarde.");
    }

    $filas = array();
    while (($fila = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) !== null) {
        if ($fila === false) {
            error_log(print_r(sqlsrv_errors(), true));
            sqlsrv_free_stmt($stmt);
            throw new RuntimeException("No se pudieron leer los datos. Intenta de nuevo más tarde.");
        }
        $filas[] = $fila;
    }
    sqlsrv_free_stmt($stmt);

    return $filas;
}

$idUsuarioTutor = (int) $_SESSION["usuario"]["id"];
$idTutor = 0;
$nombreTutor = $_SESSION["usuario"]["nombre"] ?? "Tutor";
$mensaje = $_SESSION["mensaje_dashboard_tutor"] ?? "";
$tipoMensaje = $_SESSION["tipo_mensaje_dashboard_tutor"] ?? "success";
unset($_SESSION["mensaje_dashboard_tutor"], $_SESSION["tipo_mensaje_dashboard_tutor"]);

if (empty($_SESSION["csrf_dashboard_tutor"])) {
    $_SESSION["csrf_dashboard_tutor"] = bin2hex(random_bytes(32));
}
$tokenCsrf = $_SESSION["csrf_dashboard_tutor"];

require_once __DIR__ . "/../config/conexion.php";

$tutorActual = sqlsrv_query(
    $conexion,
    "SELECT id_tutor FROM dbo.tutores WHERE id_usuario = ?",
    array($idUsuarioTutor)
);
if ($tutorActual === false) {
    error_log(print_r(sqlsrv_errors(), true));
    exit("No se pudo cargar el perfil del tutor.");
}
$filaTutor = sqlsrv_fetch_array($tutorActual, SQLSRV_FETCH_ASSOC);
if ($filaTutor === false) {
    error_log(print_r(sqlsrv_errors(), true));
    sqlsrv_free_stmt($tutorActual);
    exit("No se encontró el perfil de tutor para esta cuenta.");
}
if ($filaTutor === null) {
    sqlsrv_free_stmt($tutorActual);
    exit("No se encontró el perfil de tutor para esta cuenta.");
}
$idTutor = (int) $filaTutor["id_tutor"];
sqlsrv_free_stmt($tutorActual);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // El token CSRF evita que otro sitio envíe acciones usando la sesión del tutor.
    $tokenEnviado = isset($_POST["csrf_token"]) && is_string($_POST["csrf_token"])
        ? $_POST["csrf_token"]
        : "";

    if (!hash_equals($tokenCsrf, $tokenEnviado)) {
        $_SESSION["mensaje_dashboard_tutor"] = "La solicitud expiró o no es válida. Vuelve a intentarlo.";
        $_SESSION["tipo_mensaje_dashboard_tutor"] = "error";
        header("Location: panel.php");
        exit;
    }

    $accion = isset($_POST["accion"]) && is_string($_POST["accion"]) ? $_POST["accion"] : "";

    if ($accion === "guardar_perfil") {
        $materia = isset($_POST["materia"]) && is_string($_POST["materia"])
            ? trim($_POST["materia"])
            : "";
        $precioTexto = isset($_POST["precio_hora"]) && is_string($_POST["precio_hora"])
            ? trim($_POST["precio_hora"])
            : "";
        $modalidad = isset($_POST["modalidad"]) && is_string($_POST["modalidad"])
            ? $_POST["modalidad"]
            : "";

        if (
            $materia === "" ||
            mb_strlen($materia, "UTF-8") > 100 ||
            $precioTexto === "" ||
            !is_numeric($precioTexto) ||
            (float) $precioTexto < 0 ||
            (float) $precioTexto > 99999999 ||
            !in_array($modalidad, array("online", "presencial", "ambas"), true)
        ) {
            $_SESSION["mensaje_dashboard_tutor"] = "Revisa la materia, el precio y la modalidad.";
            $_SESSION["tipo_mensaje_dashboard_tutor"] = "error";
        } else {
            $sql = "SET NOCOUNT ON;
                    SET XACT_ABORT ON;
                    BEGIN TRY
                        BEGIN TRANSACTION;

                        DECLARE @tutor_id INT = ?;
                        DECLARE @materia NVARCHAR(120) = ?;
                        DECLARE @precio_hora DECIMAL(10, 2) = ?;
                        DECLARE @modalidad NVARCHAR(20) = ?;
                        DECLARE @materia_id INT;

                        SELECT @materia_id = id_materia
                        FROM dbo.Materias WITH (UPDLOCK, HOLDLOCK)
                        WHERE nombre = @materia;

                        IF @materia_id IS NULL
                        BEGIN
                            INSERT INTO dbo.Materias (nombre) VALUES (@materia);
                            SET @materia_id = CONVERT(INT, SCOPE_IDENTITY());
                        END;

                        UPDATE dbo.tutores WITH (UPDLOCK, SERIALIZABLE)
                        SET precio_hora = @precio_hora, modalidad = @modalidad
                        WHERE id_tutor = @tutor_id;

                        IF NOT EXISTS (
                            SELECT 1 FROM dbo.tutor_materia
                            WHERE id_tutor = @tutor_id AND id_materia = @materia_id
                        )
                            INSERT INTO dbo.tutor_materia (id_tutor, id_materia)
                            VALUES (@tutor_id, @materia_id);

                        COMMIT TRANSACTION;
                    END TRY
                    BEGIN CATCH
                        IF XACT_STATE() <> 0 ROLLBACK TRANSACTION;
                        THROW;
                    END CATCH";
            $parametros = array(
                $idTutor,
                $materia,
                (float) $precioTexto,
                $modalidad
            );
            $stmt = sqlsrv_query($conexion, $sql, $parametros);

            if ($stmt === false) {
                error_log(print_r(sqlsrv_errors(), true));
                $_SESSION["mensaje_dashboard_tutor"] = "No se pudo guardar tu perfil. Verifica la conexión e inténtalo de nuevo.";
                $_SESSION["tipo_mensaje_dashboard_tutor"] = "error";
            } else {
                sqlsrv_free_stmt($stmt);
                $_SESSION["mensaje_dashboard_tutor"] = "Tu perfil de tutor se guardó correctamente.";
                $_SESSION["tipo_mensaje_dashboard_tutor"] = "success";
            }
        }
    } elseif ($accion === "actualizar_reserva") {
        $idReserva = isset($_POST["reserva_id"]) && is_string($_POST["reserva_id"])
            ? filter_var($_POST["reserva_id"], FILTER_VALIDATE_INT, array("options" => array("min_range" => 1)))
            : false;
        $nuevoEstado = isset($_POST["estado"]) && is_string($_POST["estado"])
            ? $_POST["estado"]
            : "";

        if ($idReserva === false || !in_array($nuevoEstado, array("aceptada", "rechazada"), true)) {
            $_SESSION["mensaje_dashboard_tutor"] = "La solicitud seleccionada no es válida.";
            $_SESSION["tipo_mensaje_dashboard_tutor"] = "error";
        } else {
            // El tutor solo puede cambiar el estado de sus propias solicitudes pendientes.
            $stmt = sqlsrv_query(
                $conexion,
                "UPDATE dbo.tutorias
                 SET estado = CASE WHEN ? = 'aceptada' THEN N'Confirmada' ELSE N'Cancelada' END
                 WHERE id_tutoria = ?
                   AND id_tutor = ?
                   AND estado = N'Pendiente'",
                array($nuevoEstado, $idReserva, $idTutor)
            );

            if ($stmt === false) {
                error_log(print_r(sqlsrv_errors(), true));
                $_SESSION["mensaje_dashboard_tutor"] = "No se pudo actualizar la solicitud.";
                $_SESSION["tipo_mensaje_dashboard_tutor"] = "error";
            } else {
                $filasAfectadas = sqlsrv_rows_affected($stmt);
                sqlsrv_free_stmt($stmt);
                if ($filasAfectadas === 0) {
                    $_SESSION["mensaje_dashboard_tutor"] = "La solicitud ya fue atendida o no pertenece a tu cuenta.";
                    $_SESSION["tipo_mensaje_dashboard_tutor"] = "error";
                } else {
                    $_SESSION["mensaje_dashboard_tutor"] = $nuevoEstado === "aceptada"
                        ? "Solicitud aceptada."
                        : "Solicitud rechazada.";
                    $_SESSION["tipo_mensaje_dashboard_tutor"] = "success";
                }
            }
        }
    } else {
        $_SESSION["mensaje_dashboard_tutor"] = "La acción solicitada no es válida.";
        $_SESSION["tipo_mensaje_dashboard_tutor"] = "error";
    }

    header("Location: panel.php");
    exit;
}

$perfil = null;
$solicitudes = array();
$clasesProximas = array();
$clasesCompletadas = array();
$calificaciones = array();
$promedio = null;
$totalPendientes = 0;
$totalProximas = 0;
$totalCalificaciones = 0;
$errorConsulta = "";

try {
    $perfiles = consultarFilas(
        $conexion,
        "SELECT m.nombre AS materia, t.precio_hora, t.modalidad
         FROM dbo.tutores AS t
         INNER JOIN dbo.tutor_materia AS tm ON tm.id_tutor = t.id_tutor
         INNER JOIN dbo.materias AS m ON m.id_materia = tm.id_materia
         WHERE t.id_tutor = ?",
        array($idTutor)
    );
    $perfil = isset($perfiles[0]) ? $perfiles[0] : null;

    $filasPendientes = consultarFilas(
        $conexion,
        "SELECT COUNT(*) AS total
         FROM dbo.tutorias
         WHERE id_tutor = ? AND estado = N'Pendiente'",
        array($idTutor)
    );
    $totalPendientes = isset($filasPendientes[0]) ? (int) $filasPendientes[0]["total"] : 0;

    $solicitudes = consultarFilas(
        $conexion,
        "SELECT TOP (50)
            r.id_tutoria AS reserva_id,
            u.correo AS estudiante_correo,
            LTRIM(RTRIM(u.nombre + N' ' + u.apellido)) AS nombre_estudiante,
            m.nombre AS materia,
            CONVERT(varchar(16), DATEADD(SECOND, DATEDIFF(SECOND, CONVERT(time, '00:00:00'), r.hora_inicio), CONVERT(datetime2(0), r.fecha)), 120) AS fecha_inicio,
            LOWER(r.estado) AS estado
         FROM dbo.tutorias AS r
         INNER JOIN dbo.estudiantes AS e ON e.id_estudiante = r.id_estudiante
         INNER JOIN dbo.usuarios AS u ON u.id_usuario = e.id_usuario
         INNER JOIN dbo.materias AS m ON m.id_materia = r.id_materia
         WHERE r.id_tutor = ?
         ORDER BY r.fecha DESC, r.hora_inicio DESC",
        array($idTutor)
    );

    $filasClasesProximas = consultarFilas(
        $conexion,
        "SELECT COUNT(*) AS total
         FROM dbo.tutorias
         WHERE id_tutor = ?
           AND LOWER(estado) = N'confirmada'
           AND DATEADD(SECOND, DATEDIFF(SECOND, CONVERT(time, '00:00:00'), hora_inicio), CONVERT(datetime2(0), fecha)) >= SYSDATETIME()",
        array($idTutor)
    );
    $totalProximas = isset($filasClasesProximas[0]) ? (int) $filasClasesProximas[0]["total"] : 0;

    $clasesProximas = consultarFilas(
        $conexion,
        "SELECT TOP (50)
            r.id_tutoria AS reserva_id,
            u.correo AS estudiante_correo,
            LTRIM(RTRIM(u.nombre + N' ' + u.apellido)) AS nombre_estudiante,
            m.nombre AS materia,
            CONVERT(varchar(16), DATEADD(SECOND, DATEDIFF(SECOND, CONVERT(time, '00:00:00'), r.hora_inicio), CONVERT(datetime2(0), r.fecha)), 120) AS fecha_inicio,
            LOWER(r.estado) AS estado
         FROM dbo.tutorias AS r
         INNER JOIN dbo.estudiantes AS e ON e.id_estudiante = r.id_estudiante
         INNER JOIN dbo.usuarios AS u ON u.id_usuario = e.id_usuario
         INNER JOIN dbo.materias AS m ON m.id_materia = r.id_materia
         WHERE r.id_tutor = ?
           AND LOWER(r.estado) = N'confirmada'
           AND DATEADD(SECOND, DATEDIFF(SECOND, CONVERT(time, '00:00:00'), r.hora_inicio), CONVERT(datetime2(0), r.fecha)) >= SYSDATETIME()
         ORDER BY r.fecha ASC, r.hora_inicio ASC",
        array($idTutor)
    );

    $clasesCompletadas = consultarFilas(
        $conexion,
        "SELECT TOP (100)
            r.id_tutoria AS reserva_id,
            u.correo AS estudiante_correo,
            LTRIM(RTRIM(u.nombre + N' ' + u.apellido)) AS nombre_estudiante,
            m.nombre AS materia,
            CONVERT(varchar(16), DATEADD(SECOND, DATEDIFF(SECOND, CONVERT(time, '00:00:00'), r.hora_inicio), CONVERT(datetime2(0), r.fecha)), 120) AS fecha_inicio,
            LOWER(r.estado) AS estado
         FROM dbo.tutorias AS r
         INNER JOIN dbo.estudiantes AS e ON e.id_estudiante = r.id_estudiante
         INNER JOIN dbo.usuarios AS u ON u.id_usuario = e.id_usuario
         INNER JOIN dbo.materias AS m ON m.id_materia = r.id_materia
         WHERE r.id_tutor = ?
           AND LOWER(r.estado) = N'completada'
         ORDER BY r.fecha DESC, r.hora_inicio DESC",
        array($idTutor)
    );

    $filasPromedio = consultarFilas(
        $conexion,
        "SELECT AVG(CAST(c.calificacion AS decimal(5,2))) AS promedio, COUNT(*) AS total
         FROM dbo.resenas AS c
         INNER JOIN dbo.tutorias AS r ON r.id_tutoria = c.id_tutoria
         WHERE r.id_tutor = ?",
        array($idTutor)
    );
    if (isset($filasPromedio[0]) && (int) $filasPromedio[0]["total"] > 0) {
        $promedio = (float) $filasPromedio[0]["promedio"];
        $totalCalificaciones = (int) $filasPromedio[0]["total"];
    }

    $calificaciones = consultarFilas(
        $conexion,
        "SELECT TOP (100)
            c.calificacion AS puntuacion,
            c.comentario,
            CONVERT(varchar(10), c.fecha, 23) AS fecha,
            u.correo AS estudiante_correo,
            LTRIM(RTRIM(u.nombre + N' ' + u.apellido)) AS nombre_estudiante
         FROM dbo.resenas AS c
         INNER JOIN dbo.tutorias AS r ON r.id_tutoria = c.id_tutoria
         INNER JOIN dbo.estudiantes AS e ON e.id_estudiante = r.id_estudiante
         INNER JOIN dbo.usuarios AS u ON u.id_usuario = e.id_usuario
         WHERE r.id_tutor = ?
         ORDER BY c.fecha DESC",
        array($idTutor)
    );

} catch (RuntimeException $excepcion) {
    $errorConsulta = $excepcion->getMessage();
}

$nombre_sitio = "TutoresOn-Line";
$ruta_base = "../";
$ruta_secciones = "../index.php";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de tutor | <?php echo escapar($nombre_sitio); ?></title>
    <link rel="stylesheet" href="../css/dashboard-estudiante.css">
    <link rel="stylesheet" href="../css/dashboard-tutor.css">
</head>
<body class="dashboard-body tutor-dashboard">
    <button class="dashboard-menu-toggle" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="menu-tutor">
        <span></span><span></span><span></span>
    </button>
    <aside class="dashboard-sidebar" id="menu-tutor">
        <a class="dashboard-brand" href="../index.php"><span class="brand-mark">T</span><span>Tutores<span class="brand-light">On-Line</span></span></a>
        <p class="sidebar-caption">MENÚ PRINCIPAL</p>
        <nav class="sidebar-nav" aria-label="Menú del tutor">
            <a class="sidebar-link active" href="#inicio"><span class="nav-icon">⌂</span>Inicio</a>
            <a class="sidebar-link" href="#mi-perfil"><span class="nav-icon">♙</span>Mi perfil de tutor</a>
            <a class="sidebar-link" href="#mis-solicitudes"><span class="nav-icon">▣</span>Mis solicitudes</a>
            <a class="sidebar-link" href="#mis-clases"><span class="nav-icon">◷</span>Mis clases</a>
            <a class="sidebar-link" href="#calificaciones"><span class="nav-icon">☆</span>Calificaciones</a>
        </nav>
        <div class="sidebar-bottom">
            <div class="sidebar-user">
                <span class="user-avatar"><?php echo escapar(mb_strtoupper(mb_substr($nombreTutor, 0, 1, "UTF-8"), "UTF-8")); ?></span>
                <span class="sidebar-user-info"><strong><?php echo escapar($nombreTutor); ?></strong><small>Tutor</small></span>
            </div>
            <a class="logout-link" href="../usuarios/logout.php"><span class="nav-icon">↪</span>Cerrar sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <header class="dashboard-topbar" id="inicio">
            <div><span class="eyebrow">ESPACIO DEL TUTOR</span><h1>Hola, <?php echo escapar($nombreTutor); ?> <span class="wave">✦</span></h1><p>Este es el resumen de tu actividad y tus clases.</p></div>
            <a class="topbar-profile" href="#mi-perfil"><span class="user-avatar"><?php echo escapar(mb_strtoupper(mb_substr($nombreTutor, 0, 1, "UTF-8"), "UTF-8")); ?></span><span><strong><?php echo escapar($nombreTutor); ?></strong><small>Tutor</small></span></a>
        </header>

        <?php if ($mensaje !== ""): ?>
            <div class="dashboard-alert <?php echo escapar($tipoMensaje); ?>" role="status"><?php echo escapar($mensaje); ?></div>
        <?php endif; ?>
        <?php if ($errorConsulta !== ""): ?>
            <div class="dashboard-alert error" role="alert"><?php echo escapar($errorConsulta); ?> Verifica el esquema de <code>tutoresonline</code> y ejecuta <code>database/adaptar_tutoresonline_para_aplicacion.sql</code> desde SSMS.</div>
        <?php endif; ?>

        <section class="stats-grid" aria-label="Resumen del tutor">
            <article class="stat-card"><span class="stat-icon amber">◷</span><div><p>Solicitudes pendientes</p><strong><?php echo $errorConsulta === "" ? $totalPendientes : "—"; ?></strong></div><a href="#mis-solicitudes" aria-label="Ver solicitudes">→</a></article>
            <article class="stat-card"><span class="stat-icon blue">▣</span><div><p>Clases próximas</p><strong><?php echo $errorConsulta === "" ? $totalProximas : "—"; ?></strong></div><a href="#mis-clases" aria-label="Ver clases">→</a></article>
            <article class="stat-card"><span class="stat-icon green">★</span><div><p>Calificación promedio</p><strong><?php echo $promedio !== null ? escapar(number_format($promedio, 1)) . " / 5" : "—"; ?></strong></div><a href="#calificaciones" aria-label="Ver calificaciones">→</a></article>
        </section>

        <section class="dashboard-section" aria-labelledby="recent-title">
            <div class="section-heading"><div><span class="eyebrow">NUEVAS O ACTUALIZADAS</span><h2 id="recent-title">Solicitudes recientes</h2></div><a class="text-link" href="#mis-solicitudes">Ver todas <span>→</span></a></div>
            <?php if ($errorConsulta !== ""): ?>
                <div class="empty-inline">No se pudieron cargar las solicitudes.</div>
            <?php elseif (!$solicitudes): ?>
                <div class="empty-card compact"><span class="empty-icon">▣</span><h3>Aún no tienes solicitudes</h3><p>Cuando un estudiante solicite una clase, aparecerá aquí.</p></div>
            <?php else: ?>
                <div class="request-list">
                    <?php foreach (array_slice($solicitudes, 0, 5) as $solicitud): ?>
                        <?php $estadoSolicitud = strtolower(trim((string) $solicitud["estado"])); ?>
                        <article class="request-card">
                            <span class="request-avatar"><?php echo escapar(mb_strtoupper(mb_substr((string) ($solicitud["nombre_estudiante"] ?: $solicitud["estudiante_correo"]), 0, 1, "UTF-8"), "UTF-8")); ?></span>
                            <div class="request-info"><h3><?php echo escapar($solicitud["nombre_estudiante"] ?: "Estudiante"); ?></h3><p><?php echo escapar($solicitud["materia"]); ?> · <?php echo escapar(date("d/m/Y H:i", strtotime($solicitud["fecha_inicio"]))); ?></p><small><?php echo escapar($solicitud["estudiante_correo"]); ?></small></div>
                            <span class="status-pill <?php echo escapar($estadoSolicitud); ?>"><?php echo escapar(ucfirst($estadoSolicitud)); ?></span>
                            <?php if ($estadoSolicitud === "pendiente"): ?>
                                <div class="request-actions">
                                    <form method="post" action="panel.php">
                                        <input type="hidden" name="csrf_token" value="<?php echo escapar($tokenCsrf); ?>">
                                        <input type="hidden" name="accion" value="actualizar_reserva">
                                        <input type="hidden" name="reserva_id" value="<?php echo escapar($solicitud["reserva_id"]); ?>">
                                        <input type="hidden" name="estado" value="aceptada">
                                        <button class="small-button accept-button" type="submit">Aceptar</button>
                                    </form>
                                    <form method="post" action="panel.php">
                                        <input type="hidden" name="csrf_token" value="<?php echo escapar($tokenCsrf); ?>">
                                        <input type="hidden" name="accion" value="actualizar_reserva">
                                        <input type="hidden" name="reserva_id" value="<?php echo escapar($solicitud["reserva_id"]); ?>">
                                        <input type="hidden" name="estado" value="rechazada">
                                        <button class="small-button reject-button" type="submit">Rechazar</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="dashboard-section" id="mi-perfil" aria-labelledby="profile-title">
            <div class="section-heading"><div><span class="eyebrow">PRESENTA TUS SERVICIOS</span><h2 id="profile-title">Mi perfil de tutor</h2></div></div>
            <?php if ($errorConsulta !== ""): ?>
                <div class="empty-inline">No fue posible cargar el perfil. Inténtalo de nuevo más tarde.</div>
            <?php else: ?>
                <form class="tutor-profile-form" method="post" action="panel.php">
                    <input type="hidden" name="csrf_token" value="<?php echo escapar($tokenCsrf); ?>">
                    <input type="hidden" name="accion" value="guardar_perfil">
                    <label>Materia que enseñas<input type="text" name="materia" maxlength="120" placeholder="Ej. Matemáticas" value="<?php echo escapar($perfil["materia"] ?? ""); ?>" required></label>
                    <label>Precio por hora<input type="number" name="precio_hora" min="0" max="99999999" step="0.01" placeholder="Ej. 250.00" value="<?php echo escapar($perfil["precio_hora"] ?? ""); ?>" required></label>
                    <label>Modalidad<select name="modalidad" required><option value="">Selecciona una modalidad</option><option value="online" <?php echo (($perfil["modalidad"] ?? "") === "online") ? "selected" : ""; ?>>En línea</option><option value="presencial" <?php echo (($perfil["modalidad"] ?? "") === "presencial") ? "selected" : ""; ?>>Presencial</option><option value="ambas" <?php echo (($perfil["modalidad"] ?? "") === "ambas") ? "selected" : ""; ?>>Ambas</option></select></label>
                    <button class="button-primary profile-save" type="submit">Guardar perfil</button>
                </form>
            <?php endif; ?>
        </section>

        <section class="dashboard-section" id="mis-solicitudes" aria-labelledby="requests-title">
            <div class="section-heading"><div><span class="eyebrow">GESTIONA TUS PETICIONES</span><h2 id="requests-title">Mis solicitudes</h2></div></div>
            <?php if ($errorConsulta !== ""): ?>
                <div class="empty-inline">No se pudieron consultar las solicitudes.</div>
            <?php elseif (!$solicitudes): ?>
                <div class="empty-inline">No hay solicitudes asociadas a tu cuenta de tutor.</div>
            <?php else: ?>
                <div class="table-wrap"><table><thead><tr><th>Estudiante</th><th>Correo</th><th>Materia</th><th>Fecha</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
                    <?php foreach ($solicitudes as $solicitud): ?>
                        <?php $estadoSolicitud = strtolower(trim((string) $solicitud["estado"])); ?>
                        <tr>
                            <td><?php echo escapar($solicitud["nombre_estudiante"] ?: "Estudiante"); ?></td>
                            <td><?php echo escapar($solicitud["estudiante_correo"]); ?></td>
                            <td><?php echo escapar($solicitud["materia"]); ?></td>
                            <td><?php echo escapar(date("d/m/Y H:i", strtotime($solicitud["fecha_inicio"]))); ?></td>
                            <td><span class="status-pill <?php echo escapar($estadoSolicitud); ?>"><?php echo escapar(ucfirst($estadoSolicitud)); ?></span></td>
                            <td>
                                <?php if ($estadoSolicitud === "pendiente"): ?>
                                    <div class="table-actions">
                                        <form method="post" action="panel.php"><input type="hidden" name="csrf_token" value="<?php echo escapar($tokenCsrf); ?>"><input type="hidden" name="accion" value="actualizar_reserva"><input type="hidden" name="reserva_id" value="<?php echo escapar($solicitud["reserva_id"]); ?>"><input type="hidden" name="estado" value="aceptada"><button class="small-button accept-button" type="submit">Aceptar</button></form>
                                        <form method="post" action="panel.php"><input type="hidden" name="csrf_token" value="<?php echo escapar($tokenCsrf); ?>"><input type="hidden" name="accion" value="actualizar_reserva"><input type="hidden" name="reserva_id" value="<?php echo escapar($solicitud["reserva_id"]); ?>"><input type="hidden" name="estado" value="rechazada"><button class="small-button reject-button" type="submit">Rechazar</button></form>
                                    </div>
                                <?php else: ?><span class="muted-note">—</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </section>

        <section class="dashboard-section" id="mis-clases" aria-labelledby="classes-title">
            <div class="section-heading"><div><span class="eyebrow">AGENDA Y ACTIVIDAD</span><h2 id="classes-title">Mis clases</h2></div></div>
            <h3 class="subsection-title">Próximas clases</h3>
            <?php if ($errorConsulta !== ""): ?>
                <div class="empty-inline">No se pudieron consultar tus clases.</div>
            <?php elseif (!$clasesProximas): ?>
                <div class="empty-inline">No tienes clases aceptadas o confirmadas próximas.</div>
            <?php else: ?>
                <div class="table-wrap"><table><thead><tr><th>Estudiante</th><th>Correo</th><th>Materia</th><th>Fecha</th><th>Estado</th></tr></thead><tbody>
                    <?php foreach ($clasesProximas as $clase): ?><tr><td><?php echo escapar($clase["nombre_estudiante"] ?: "Estudiante"); ?></td><td><?php echo escapar($clase["estudiante_correo"]); ?></td><td><?php echo escapar($clase["materia"]); ?></td><td><?php echo escapar(date("d/m/Y H:i", strtotime($clase["fecha_inicio"]))); ?></td><td><span class="status-pill <?php echo escapar(strtolower($clase["estado"])); ?>"><?php echo escapar(ucfirst($clase["estado"])); ?></span></td></tr><?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
            <h3 class="subsection-title completed-title">Clases completadas</h3>
            <?php if ($errorConsulta !== ""): ?>
                <div class="empty-inline">No se pudo cargar el historial de clases.</div>
            <?php elseif (!$clasesCompletadas): ?>
                <div class="empty-inline">Todavía no tienes clases completadas.</div>
            <?php else: ?>
                <div class="table-wrap"><table><thead><tr><th>Estudiante</th><th>Correo</th><th>Materia</th><th>Fecha</th><th>Estado</th></tr></thead><tbody>
                    <?php foreach ($clasesCompletadas as $clase): ?><tr><td><?php echo escapar($clase["nombre_estudiante"] ?: "Estudiante"); ?></td><td><?php echo escapar($clase["estudiante_correo"]); ?></td><td><?php echo escapar($clase["materia"]); ?></td><td><?php echo escapar(date("d/m/Y H:i", strtotime($clase["fecha_inicio"]))); ?></td><td><span class="status-pill completada"><?php echo escapar(ucfirst($clase["estado"])); ?></span></td></tr><?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </section>

        <section class="dashboard-section" id="calificaciones" aria-labelledby="ratings-title">
            <div class="section-heading"><div><span class="eyebrow">OPINIONES DE ESTUDIANTES</span><h2 id="ratings-title">Calificaciones</h2></div><?php if ($promedio !== null): ?><span class="average-badge">★ <?php echo escapar(number_format($promedio, 1)); ?> / 5</span><?php endif; ?></div>
            <?php if ($errorConsulta !== ""): ?>
                <div class="empty-inline">No se pudieron consultar tus calificaciones.</div>
            <?php elseif (!$calificaciones): ?>
                <div class="empty-card compact"><span class="empty-icon">☆</span><h3>Aún no has recibido calificaciones</h3><p>Cuando tus estudiantes envíen una valoración, aparecerá aquí.</p></div>
            <?php else: ?>
                <p class="rating-count"><?php echo escapar($totalCalificaciones); ?> valoración<?php echo $totalCalificaciones === 1 ? "" : "es"; ?></p>
                <div class="review-list">
                    <?php foreach ($calificaciones as $calificacion): ?>
                        <article class="review-card">
                            <div class="review-heading"><div><strong><?php echo escapar($calificacion["nombre_estudiante"] ?: "Estudiante"); ?></strong><small><?php echo escapar($calificacion["fecha"]); ?></small></div><span class="review-score"><?php echo str_repeat("★", max(0, min(5, (int) $calificacion["puntuacion"]))); ?><small><?php echo escapar($calificacion["puntuacion"]); ?>/5</small></span></div>
                            <p><?php echo escapar($calificacion["comentario"] ?: "El estudiante no dejó un comentario."); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <footer class="dashboard-footer">© <?php echo date("Y"); ?> TutoresOn-Line</footer>
    </main>
    <script src="../js/dashboard-tutor.js"></script>
</body>
</html>
