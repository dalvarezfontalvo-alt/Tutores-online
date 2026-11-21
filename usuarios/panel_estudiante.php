<?php
require_once __DIR__ . "/../config/sesion.php";

if (!isset($_SESSION["usuario"]["rol"]) || $_SESSION["usuario"]["rol"] !== "estudiante") {
    if (isset($_SESSION["usuario"]["rol"]) && $_SESSION["usuario"]["rol"] === "tutor") {
        header("Location: ../tutores/panel.php");
    } else {
        header("Location: login.php");
    }
    exit;
}

if (!isset($_SESSION["usuario"]["id"]) || (int) $_SESSION["usuario"]["id"] < 1) {
    header("Location: login.php");
    exit;
}

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

function obtenerFiltro($nombre)
{
    return isset($_GET[$nombre]) && is_string($_GET[$nombre]) ? trim($_GET[$nombre]) : "";
}

function obtenerFilas($conexion, $sql, $parametros, &$error)
{
    $stmt = sqlsrv_query($conexion, $sql, $parametros);
    if ($stmt === false) {
        error_log(print_r(sqlsrv_errors(), true));
        $error = true;
        return array();
    }

    $filas = array();
    while (($fila = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) !== null) {
        if ($fila === false) {
            error_log(print_r(sqlsrv_errors(), true));
            $error = true;
            break;
        }
        $filas[] = $fila;
    }
    sqlsrv_free_stmt($stmt);

    return $filas;
}

$nombreEstudiante = $_SESSION["usuario"]["nombre"] ?? "Estudiante";
$idEstudiante = (int) $_SESSION["usuario"]["id"];
$correoEstudiante = $_SESSION["usuario"]["correo"] ?? "";
$filtroNombre = obtenerFiltro("nombre");
$filtroMateria = obtenerFiltro("materia");
$filtroPrecio = obtenerFiltro("precio");
$filtroCalificacion = obtenerFiltro("calificacion");
$filtroModalidad = obtenerFiltro("modalidad");
$errorBaseDatos = false;
$tablasDisponibles = false;
$reservas = array();
$proximasTutorias = array();
$solicitudesPendientes = array();
$historial = array();
$calificaciones = array();
$tutores = array();
$busquedaRealizada = isset($_GET["buscar"]);

require_once __DIR__ . "/../config/conexion.php";

$idUsuarioEstudiante = $idEstudiante;
$estudianteActual = obtenerFilas(
    $conexion,
    "SELECT id_estudiante FROM dbo.estudiantes WHERE id_usuario = ?",
    array($idUsuarioEstudiante),
    $errorBaseDatos
);
if (isset($estudianteActual[0])) {
    $idEstudiante = (int) $estudianteActual[0]["id_estudiante"];
} else {
    $errorBaseDatos = true;
}

$estadoTablas = obtenerFilas(
    $conexion,
    "SELECT
        CASE WHEN OBJECT_ID(N'dbo.tutorias', N'U') IS NOT NULL THEN 1 ELSE 0 END AS reservas,
        CASE WHEN OBJECT_ID(N'dbo.resenas', N'U') IS NOT NULL THEN 1 ELSE 0 END AS calificaciones,
        CASE WHEN OBJECT_ID(N'dbo.tutores', N'U') IS NOT NULL THEN 1 ELSE 0 END AS tutores,
        CASE WHEN OBJECT_ID(N'dbo.tutor_materia', N'U') IS NOT NULL THEN 1 ELSE 0 END AS tutor_materias,
        CASE WHEN OBJECT_ID(N'dbo.estudiantes', N'U') IS NOT NULL THEN 1 ELSE 0 END AS estudiantes,
        CASE WHEN OBJECT_ID(N'dbo.materias', N'U') IS NOT NULL THEN 1 ELSE 0 END AS materias,
        CASE WHEN COL_LENGTH(N'dbo.tutores', N'precio_hora') IS NOT NULL
                   AND COL_LENGTH(N'dbo.tutores', N'modalidad') IS NOT NULL
             THEN 1 ELSE 0 END AS perfil_completo",
    array(),
    $errorBaseDatos
);

if (!$errorBaseDatos && isset($estadoTablas[0])) {
    $tablasDisponibles = (int) $estadoTablas[0]["reservas"] === 1
        && (int) $estadoTablas[0]["calificaciones"] === 1
        && (int) $estadoTablas[0]["tutores"] === 1
        && (int) $estadoTablas[0]["tutor_materias"] === 1
        && (int) $estadoTablas[0]["estudiantes"] === 1
        && (int) $estadoTablas[0]["materias"] === 1
        && (int) $estadoTablas[0]["perfil_completo"] === 1;
}

if ($tablasDisponibles && !$errorBaseDatos) {
    $reservas = obtenerFilas(
        $conexion,
        "SELECT TOP (100)
            r.id_tutoria AS reserva_id,
            m.nombre AS materia,
            CONVERT(varchar(16), DATEADD(SECOND, DATEDIFF(SECOND, CONVERT(time, '00:00:00'), r.hora_inicio), CONVERT(datetime2(0), r.fecha)), 120) AS fecha_inicio,
            LOWER(r.estado) AS estado,
            LTRIM(RTRIM(u.nombre + N' ' + u.apellido)) AS nombre_tutor,
            u.correo AS tutor_correo
        FROM dbo.tutorias AS r
        INNER JOIN dbo.tutores AS t ON t.id_tutor = r.id_tutor
        INNER JOIN dbo.usuarios AS u ON u.id_usuario = t.id_usuario
        INNER JOIN dbo.materias AS m ON m.id_materia = r.id_materia
        WHERE r.id_estudiante = ?
        ORDER BY r.fecha DESC, r.hora_inicio DESC",
        array($idEstudiante),
        $errorBaseDatos
    );

    foreach ($reservas as $reserva) {
        $estado = strtolower(trim((string) $reserva["estado"]));
        $fecha = strtotime((string) $reserva["fecha_inicio"]);

        if ($estado === "pendiente") {
            $solicitudesPendientes[] = $reserva;
        }
        if (in_array($estado, array("aceptada", "confirmada"), true) && $fecha !== false && $fecha >= time()) {
            $proximasTutorias[] = $reserva;
        }
        if (in_array($estado, array("completada", "completado"), true)) {
            $historial[] = $reserva;
        }
    }

    $calificaciones = obtenerFilas(
        $conexion,
        "SELECT TOP (100)
            c.calificacion AS puntuacion,
            c.comentario,
            CONVERT(varchar(10), c.fecha, 23) AS fecha,
            LTRIM(RTRIM(u.nombre + N' ' + u.apellido)) AS nombre_tutor,
            u.correo AS tutor_correo
        FROM dbo.resenas AS c
        INNER JOIN dbo.tutorias AS r ON r.id_tutoria = c.id_tutoria
        INNER JOIN dbo.tutores AS t ON t.id_tutor = r.id_tutor
        INNER JOIN dbo.usuarios AS u ON u.id_usuario = t.id_usuario
        WHERE r.id_estudiante = ?
        ORDER BY c.fecha DESC",
        array($idEstudiante),
        $errorBaseDatos
    );

    if ($busquedaRealizada) {
        $condiciones = array("t.estado = 1", "m.estado = 1");
        $parametros = array();

        if ($filtroNombre !== "") {
            $condiciones[] = "LTRIM(RTRIM(u.nombre + N' ' + u.apellido)) LIKE ?";
            $parametros[] = "%" . $filtroNombre . "%";
        }
        if ($filtroMateria !== "") {
            $condiciones[] = "m.nombre LIKE ?";
            $parametros[] = "%" . $filtroMateria . "%";
        }
        if ($filtroPrecio !== "" && is_numeric($filtroPrecio) && (float) $filtroPrecio >= 0) {
            $condiciones[] = "t.precio_hora <= ?";
            $parametros[] = (float) $filtroPrecio;
        }
        if ($filtroModalidad !== "" && in_array($filtroModalidad, array("online", "presencial"), true)) {
            $condiciones[] = "(t.modalidad = ? OR t.modalidad = N'ambas')";
            $parametros[] = $filtroModalidad;
        }

        $calificacionValida = $filtroCalificacion !== ""
            && is_numeric($filtroCalificacion)
            && (float) $filtroCalificacion >= 1
            && (float) $filtroCalificacion <= 5;

        $sqlTutores = "SELECT TOP (50)
                LTRIM(RTRIM(u.nombre + N' ' + u.apellido)) AS nombre,
                m.nombre AS materia,
                t.precio_hora,
                t.modalidad,
                resenas.calificacion,
                COALESCE(resenas.total_calificaciones, 0) AS total_calificaciones
            FROM dbo.tutores AS t
            INNER JOIN dbo.usuarios AS u ON u.id_usuario = t.id_usuario
            INNER JOIN dbo.tutor_materia AS tm ON tm.id_tutor = t.id_tutor
            INNER JOIN dbo.materias AS m ON m.id_materia = tm.id_materia
            LEFT JOIN (
                SELECT r.id_tutor,
                    AVG(CAST(c.calificacion AS decimal(5,2))) AS calificacion,
                    COUNT(c.id_resena) AS total_calificaciones
                FROM dbo.tutorias AS r
                INNER JOIN dbo.resenas AS c ON c.id_tutoria = r.id_tutoria
                GROUP BY r.id_tutor
            ) AS resenas ON resenas.id_tutor = t.id_tutor
            WHERE " . implode(" AND ", $condiciones);

        if ($calificacionValida) {
            $sqlTutores .= " AND resenas.calificacion >= ?";
            $parametros[] = (float) $filtroCalificacion;
        }
        $sqlTutores .= " ORDER BY resenas.calificacion DESC, u.nombre ASC";

        $tutores = obtenerFilas($conexion, $sqlTutores, $parametros, $errorBaseDatos);
    }
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
    <title>Panel de estudiante | <?php echo escapar($nombre_sitio); ?></title>
    <link rel="stylesheet" href="../css/dashboard-estudiante.css">
</head>
<body class="dashboard-body">
    <button class="dashboard-menu-toggle" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="menu-estudiante">
        <span></span><span></span><span></span>
    </button>
    <aside class="dashboard-sidebar" id="menu-estudiante">
        <a class="dashboard-brand" href="../index.php"><span class="brand-mark">T</span><span>Tutores<span class="brand-light">On-Line</span></span></a>
        <p class="sidebar-caption">MENÚ PRINCIPAL</p>
        <nav class="sidebar-nav" aria-label="Menú del estudiante">
            <a class="sidebar-link active" href="#inicio"><span class="nav-icon">⌂</span>Inicio</a>
            <a class="sidebar-link" href="#buscar-tutores"><span class="nav-icon">⌕</span>Buscar tutores</a>
            <a class="sidebar-link" href="#mis-reservas"><span class="nav-icon">▣</span>Mis reservas</a>
            <a class="sidebar-link" href="#historial"><span class="nav-icon">◷</span>Historial de clases</a>
            <a class="sidebar-link" href="#calificaciones"><span class="nav-icon">☆</span>Calificaciones</a>
            <a class="sidebar-link" href="#mi-perfil"><span class="nav-icon">♙</span>Mi perfil</a>
        </nav>
        <div class="sidebar-bottom">
            <div class="sidebar-user">
                <span class="user-avatar"><?php echo escapar(mb_strtoupper(mb_substr($nombreEstudiante, 0, 1, "UTF-8"), "UTF-8")); ?></span>
                <span class="sidebar-user-info"><strong><?php echo escapar($nombreEstudiante); ?></strong><small>Estudiante</small></span>
            </div>
            <a class="logout-link" href="logout.php"><span class="nav-icon">↪</span>Cerrar sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <header class="dashboard-topbar">
            <div><span class="eyebrow">ESPACIO DEL ESTUDIANTE</span><h1 id="inicio">Hola, <?php echo escapar($nombreEstudiante); ?> <span class="wave">✦</span></h1><p>Organiza tus clases y encuentra el tutor ideal para ti.</p></div>
            <a class="topbar-profile" href="#mi-perfil"><span class="user-avatar"><?php echo escapar(mb_strtoupper(mb_substr($nombreEstudiante, 0, 1, "UTF-8"), "UTF-8")); ?></span><span><strong><?php echo escapar($nombreEstudiante); ?></strong><small>Estudiante</small></span></a>
        </header>

        <?php if ($errorBaseDatos): ?>
            <div class="dashboard-alert error" role="alert">No se pudieron consultar algunos datos. Revisa la conexión SQL Server y los registros de error de PHP.</div>
        <?php elseif (!$tablasDisponibles): ?>
            <div class="dashboard-alert info" role="status">
                <strong>El panel está listo, pero faltan tablas para mostrar datos.</strong>
                Ejecuta <code>database/script.sql</code> en la base <code>tutoresonline</code>.
            </div>
        <?php endif; ?>

        <section class="stats-grid" aria-label="Resumen de actividad">
            <article class="stat-card"><span class="stat-icon blue">▣</span><div><p>Próximas tutorías</p><strong><?php echo count($proximasTutorias); ?></strong></div><a href="#mis-reservas" aria-label="Ver próximas tutorías">→</a></article>
            <article class="stat-card"><span class="stat-icon amber">◷</span><div><p>Solicitudes pendientes</p><strong><?php echo count($solicitudesPendientes); ?></strong></div><a href="#mis-reservas" aria-label="Ver solicitudes pendientes">→</a></article>
            <article class="stat-card"><span class="stat-icon green">✓</span><div><p>Clases completadas</p><strong><?php echo count($historial); ?></strong></div><a href="#historial" aria-label="Ver historial de clases">→</a></article>
        </section>

        <section class="dashboard-section" aria-labelledby="upcoming-title">
            <div class="section-heading"><div><span class="eyebrow">TU AGENDA</span><h2 id="upcoming-title">Próximas tutorías</h2></div><a class="text-link" href="#mis-reservas">Ver reservas <span>→</span></a></div>
            <?php if (!$tablasDisponibles): ?>
                <div class="empty-card"><span class="empty-icon">▣</span><h3>Agenda todavía no disponible</h3><p>Cuando estén instaladas las tablas de reservas, aquí aparecerán tus tutorías confirmadas.</p></div>
            <?php elseif (!$proximasTutorias): ?>
                <div class="empty-card"><span class="empty-icon">▣</span><h3>No tienes tutorías próximas</h3><p>Busca un tutor y consulta aquí las clases que se confirmen.</p><a class="button-primary" href="#buscar-tutores">Buscar tutores</a></div>
            <?php else: ?>
                <div class="appointment-list">
                    <?php foreach (array_slice($proximasTutorias, 0, 4) as $tutoria): ?>
                        <article class="appointment-card">
                            <div class="appointment-date"><strong><?php echo escapar(date("d", strtotime($tutoria["fecha_inicio"]))); ?></strong><span><?php echo escapar(date("M", strtotime($tutoria["fecha_inicio"]))); ?></span></div>
                            <div class="appointment-info"><h3><?php echo escapar($tutoria["materia"]); ?></h3><p>Con <?php echo escapar($tutoria["nombre_tutor"] ?: $tutoria["tutor_correo"]); ?></p></div>
                            <div class="appointment-time"><?php echo escapar(date("d/m/Y · H:i", strtotime($tutoria["fecha_inicio"]))); ?></div>
                            <span class="status-pill accepted">Confirmada</span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="dashboard-section" id="buscar-tutores" aria-labelledby="search-title">
            <div class="section-heading"><div><span class="eyebrow">ENCUENTRA TU PRÓXIMO TUTOR</span><h2 id="search-title">Buscar tutores</h2></div></div>
            <form class="search-panel" method="get" action="#buscar-tutores">
                <label>Nombre del tutor<input type="search" name="nombre" placeholder="Ej. Ana López" value="<?php echo escapar($filtroNombre); ?>"></label>
                <label>Materia<input type="search" name="materia" placeholder="Ej. Matemáticas" value="<?php echo escapar($filtroMateria); ?>"></label>
                <label>Precio máximo<input type="number" name="precio" min="0" step="0.01" placeholder="Sin límite" value="<?php echo escapar($filtroPrecio); ?>"></label>
                <label>Calificación mínima<select name="calificacion"><option value="">Cualquiera</option><?php for ($rating = 5; $rating >= 1; $rating--): ?><option value="<?php echo $rating; ?>" <?php echo $filtroCalificacion === (string) $rating ? "selected" : ""; ?>><?php echo $rating; ?> estrellas o más</option><?php endfor; ?></select></label>
                <label>Modalidad<select name="modalidad"><option value="">Todas</option><option value="online" <?php echo $filtroModalidad === "online" ? "selected" : ""; ?>>En línea</option><option value="presencial" <?php echo $filtroModalidad === "presencial" ? "selected" : ""; ?>>Presencial</option></select></label>
                <button class="button-primary search-submit" type="submit" name="buscar" value="1">⌕ <span>Buscar tutores</span></button>
            </form>
            <div class="results-area">
                <?php if (!$tablasDisponibles): ?>
                    <p class="muted-note">La búsqueda estará disponible al instalar la estructura SQL del dashboard y crear perfiles reales de tutor.</p>
                <?php elseif ($busquedaRealizada && !$errorBaseDatos): ?>
                    <h3 class="results-title"><?php echo count($tutores); ?> resultado<?php echo count($tutores) === 1 ? "" : "s"; ?></h3>
                    <?php if (!$tutores): ?><div class="empty-inline">No encontramos tutores que coincidan con esos filtros.</div><?php else: ?>
                        <div class="tutor-results">
                            <?php foreach ($tutores as $tutor): ?>
                                <article class="tutor-result">
                                    <span class="tutor-avatar"><?php echo escapar(mb_strtoupper(mb_substr((string) $tutor["nombre"], 0, 1, "UTF-8"), "UTF-8")); ?></span>
                                    <div class="tutor-result-info"><h3><?php echo escapar($tutor["nombre"]); ?></h3><p><?php echo escapar($tutor["materia"]); ?> · <?php echo escapar($tutor["modalidad"] ?: "Modalidad por definir"); ?></p></div>
                                    <div class="tutor-rating"><?php echo $tutor["calificacion"] !== null ? "★ " . escapar(number_format((float) $tutor["calificacion"], 1)) : "☆ Sin calificaciones"; ?><small><?php echo escapar($tutor["total_calificaciones"]); ?> opiniones</small></div>
                                    <strong class="tutor-price"><?php echo $tutor["precio_hora"] === null ? "Por definir" : escapar(number_format((float) $tutor["precio_hora"], 2)); ?><?php if ($tutor["precio_hora"] !== null): ?><small>por hora</small><?php endif; ?></strong>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="muted-note">Usa los filtros para consultar los perfiles de tutores disponibles.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="dashboard-section" id="mis-reservas" aria-labelledby="reservations-title">
            <div class="section-heading"><div><span class="eyebrow">SEGUIMIENTO</span><h2 id="reservations-title">Mis reservas</h2></div></div>
            <?php if (!$tablasDisponibles): ?>
                <div class="empty-card compact"><span class="empty-icon">◷</span><h3>Reservas todavía no disponibles</h3><p>La tabla necesaria aún no está creada. Tus reservas no se han alterado.</p></div>
            <?php elseif (!$reservas): ?>
                <div class="empty-card compact"><span class="empty-icon">◷</span><h3>Aún no tienes solicitudes</h3><p>Las solicitudes que realices aparecerán aquí con su estado actualizado.</p></div>
            <?php else: ?>
                <div class="table-wrap"><table><thead><tr><th>Materia</th><th>Tutor</th><th>Fecha</th><th>Estado</th></tr></thead><tbody>
                    <?php foreach ($reservas as $reserva): ?>
                        <tr><td><?php echo escapar($reserva["materia"]); ?></td><td><?php echo escapar($reserva["nombre_tutor"] ?: $reserva["tutor_correo"]); ?></td><td><?php echo escapar(date("d/m/Y H:i", strtotime($reserva["fecha_inicio"]))); ?></td><td><span class="status-pill <?php echo escapar(strtolower($reserva["estado"])); ?>"><?php echo escapar(ucfirst($reserva["estado"])); ?></span></td></tr>
                    <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
            <?php if ($tablasDisponibles && $solicitudesPendientes): ?><p class="pending-summary"><?php echo count($solicitudesPendientes); ?> solicitud<?php echo count($solicitudesPendientes) === 1 ? "" : "es"; ?> pendiente<?php echo count($solicitudesPendientes) === 1 ? "" : "s"; ?> de respuesta.</p><?php endif; ?>
        </section>

        <section class="dashboard-section" id="historial" aria-labelledby="history-title">
            <div class="section-heading"><div><span class="eyebrow">TU RECORRIDO</span><h2 id="history-title">Historial de clases</h2></div></div>
            <?php if (!$tablasDisponibles): ?>
                <div class="empty-inline">El historial se mostrará cuando esté disponible la tabla de reservas.</div>
            <?php elseif (!$historial): ?>
                <div class="empty-inline">Todavía no hay clases completadas asociadas a tu cuenta.</div>
            <?php else: ?>
                <div class="history-list"><?php foreach ($historial as $clase): ?><article class="history-item"><span class="history-check">✓</span><div><strong><?php echo escapar($clase["materia"]); ?></strong><p>Con <?php echo escapar($clase["nombre_tutor"] ?: $clase["tutor_correo"]); ?></p></div><time><?php echo escapar(date("d/m/Y", strtotime($clase["fecha_inicio"]))); ?></time></article><?php endforeach; ?></div>
            <?php endif; ?>
        </section>

        <section class="dashboard-section" id="calificaciones" aria-labelledby="ratings-title">
            <div class="section-heading"><div><span class="eyebrow">OPINIONES</span><h2 id="ratings-title">Calificaciones</h2></div></div>
            <?php if (!$tablasDisponibles): ?>
                <div class="empty-inline">Las calificaciones se mostrarán cuando esté disponible la estructura SQL.</div>
            <?php elseif (!$calificaciones): ?>
                <div class="empty-inline">Aún no has enviado calificaciones.</div>
            <?php else: ?>
                <div class="history-list"><?php foreach ($calificaciones as $calificacion): ?><article class="history-item rating-item"><span class="rating-stars"><?php echo str_repeat("★", max(0, min(5, (int) $calificacion["puntuacion"]))); ?></span><div><strong><?php echo escapar($calificacion["nombre_tutor"] ?: $calificacion["tutor_correo"]); ?></strong><p><?php echo escapar($calificacion["comentario"] ?: "Sin comentario"); ?></p></div><time><?php echo escapar($calificacion["fecha"]); ?></time></article><?php endforeach; ?></div>
            <?php endif; ?>
        </section>

        <section class="dashboard-section profile-section" id="mi-perfil" aria-labelledby="profile-title">
            <div class="section-heading"><div><span class="eyebrow">TU CUENTA</span><h2 id="profile-title">Mi perfil</h2></div></div>
            <div class="profile-card"><span class="profile-avatar"><?php echo escapar(mb_strtoupper(mb_substr($nombreEstudiante, 0, 1, "UTF-8"), "UTF-8")); ?></span><div><span class="profile-role">ESTUDIANTE</span><h3><?php echo escapar($nombreEstudiante); ?></h3><p><?php echo escapar($correoEstudiante); ?></p></div><a class="button-secondary" href="logout.php">Cerrar sesión</a></div>
        </section>
        <footer class="dashboard-footer">© <?php echo date("Y"); ?> TutoresOn-Line</footer>
    </main>
    <script src="../js/dashboard-estudiante.js"></script>
</body>
</html>
