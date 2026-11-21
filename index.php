<?php
/*
    ARCHIVO: index.php
    PROYECTO: TutoresOn-Line
    DESCRIPCIÓN: Página principal de la plataforma.

    La página principal muestra perfiles de tutores guardados en SQL Server.
*/

// Nombre del sitio. Si algún día cambia, solo se modifica aquí.
$nombre_sitio = "TutoresOn-Line";
$ruta_base = "";
$ruta_secciones = "";
require_once __DIR__ . "/config/sesion.php";

$materiaBuscada = isset($_GET["materia"]) && is_string($_GET["materia"])
    ? trim($_GET["materia"])
    : "";
$tutoresDestacados = array();
$errorTutores = false;

require_once __DIR__ . "/config/conexion.php";

$sqlTutores = "SELECT TOP (6)
        LTRIM(RTRIM(u.nombre + N' ' + u.apellido)) AS nombre,
        m.nombre AS materia,
        t.precio_hora,
        t.modalidad,
        AVG(CAST(c.calificacion AS decimal(5,2))) AS calificacion,
        COUNT(c.id_resena) AS total_calificaciones
    FROM dbo.tutores AS t
    INNER JOIN dbo.usuarios AS u ON u.id_usuario = t.id_usuario
    INNER JOIN dbo.tutor_materia AS tm ON tm.id_tutor = t.id_tutor
    INNER JOIN dbo.materias AS m ON m.id_materia = tm.id_materia
    LEFT JOIN (
        SELECT r.id_tutor, c.id_resena, c.calificacion
        FROM dbo.resenas AS c
        INNER JOIN dbo.tutorias AS r ON r.id_tutoria = c.id_tutoria
    ) AS c
        ON c.id_tutor = t.id_tutor
    WHERE t.estado = 1
      AND m.estado = 1";
$parametrosTutores = array();

if ($materiaBuscada !== "") {
    $sqlTutores .= " AND m.nombre LIKE ?";
    $parametrosTutores[] = "%" . $materiaBuscada . "%";
}

$sqlTutores .= " GROUP BY u.nombre, u.apellido, m.nombre, t.precio_hora, t.modalidad
    ORDER BY AVG(CAST(c.calificacion AS decimal(5,2))) DESC, u.nombre ASC";

$stmtTutores = sqlsrv_query($conexion, $sqlTutores, $parametrosTutores);
if ($stmtTutores === false) {
    error_log(print_r(sqlsrv_errors(), true));
    $errorTutores = true;
} else {
    while (($tutor = sqlsrv_fetch_array($stmtTutores, SQLSRV_FETCH_ASSOC)) !== null) {
        if ($tutor === false) {
            error_log(print_r(sqlsrv_errors(), true));
            $errorTutores = true;
            break;
        }
        $tutoresDestacados[] = $tutor;
    }
    sqlsrv_free_stmt($stmtTutores);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $nombre_sitio; ?> | Encuentra tu tutor</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Hoja de estilos del proyecto -->
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <?php require_once __DIR__ . "/includes/header.php"; ?>


    <main>

        <!-- Sección principal -->
        <section class="principal" id="inicio">
            <div class="contenedor principal-contenido">
                <div class="principal-texto-contenedor">
                    <h1>Aprende con un tutor que se ajuste a lo que necesitas</h1>
                    <p class="principal-texto">
                        En <?php echo $nombre_sitio; ?> conectamos estudiantes con tutores de distintas materias.
                        Elige entre clases virtuales o presenciales y reserva tu sesión.
                    </p>
                </div>

                <div class="principal-imagen-contenedor">
                    <img src="img/profesor.png" alt="Profesor dando una clase en línea" class="principal-imagen">
                </div>
            </div>
        </section>


        <!-- Buscador de tutores -->
        <section class="buscador" id="buscador">
            <div class="contenedor">

                <!-- Por ahora el formulario no busca nada; solo es la parte visual -->
                <form class="buscador-formulario" action="index.php" method="get">
                    <label for="materia">¿Qué materia necesitas?</label>

                    <div class="buscador-campos">
                        <input
                            type="text"
                            id="materia"
                            name="materia"
                            placeholder="Ejemplo: Matemáticas, Inglés, Programación"
                            value="<?php echo htmlspecialchars($materiaBuscada, ENT_QUOTES, "UTF-8"); ?>"
                        >
                        <button type="submit">Buscar tutor</button>
                    </div>
                </form>

            </div>
        </section>


        <!-- Tutores destacados -->
        <section class="tutores" id="tutores">
            <div class="contenedor">

                <h2>Tutores destacados</h2>
                <p class="seccion-subtitulo">Algunos de los tutores que puedes encontrar en la plataforma.</p>

                <div class="tutores-lista">
                    <?php if ($errorTutores): ?>
                        <p class="tutores-mensaje" role="status">No se pudieron cargar los tutores. Inténtalo de nuevo más tarde.</p>
                    <?php elseif (!$tutoresDestacados): ?>
                        <p class="tutores-mensaje" role="status">
                            <?php echo $materiaBuscada !== ""
                                ? "No encontramos tutores para esa materia."
                                : "Todavía no hay perfiles de tutor publicados."; ?>
                        </p>
                    <?php else: ?>
                        <?php foreach ($tutoresDestacados as $tutor): ?>
                            <?php
                            $nombreTutor = (string) $tutor["nombre"];
                            $modalidades = array(
                                "online" => "En línea",
                                "presencial" => "Presencial",
                                "ambas" => "En línea y presencial"
                            );
                            $modalidadTutor = $tutor["modalidad"] === null
                                ? "Modalidad por definir"
                                : ($modalidades[$tutor["modalidad"]] ?? $tutor["modalidad"]);
                            ?>
                            <article class="tarjeta-tutor">
                                <div class="tarjeta-inicial" aria-hidden="true">
                                    <?php echo htmlspecialchars(mb_strtoupper(mb_substr($nombreTutor, 0, 1, "UTF-8"), "UTF-8"), ENT_QUOTES, "UTF-8"); ?>
                                </div>
                                <h3><?php echo htmlspecialchars($nombreTutor, ENT_QUOTES, "UTF-8"); ?></h3>
                                <p class="tarjeta-materia"><?php echo htmlspecialchars($tutor["materia"], ENT_QUOTES, "UTF-8"); ?></p>
                                <p class="tarjeta-descripcion">
                                    <?php if ($tutor["calificacion"] !== null): ?>
                                        <span aria-label="<?php echo htmlspecialchars(number_format((float) $tutor["calificacion"], 1), ENT_QUOTES, "UTF-8"); ?> de 5 estrellas">
                                            ★ <?php echo htmlspecialchars(number_format((float) $tutor["calificacion"], 1), ENT_QUOTES, "UTF-8"); ?>/5
                                        </span>
                                        (<?php echo (int) $tutor["total_calificaciones"]; ?>
                                        <?php echo (int) $tutor["total_calificaciones"] === 1 ? "calificación" : "calificaciones"; ?>)
                                    <?php else: ?>
                                        Aún no tiene calificaciones.
                                    <?php endif; ?>
                                </p>
                                <span class="tarjeta-modalidad"><?php echo htmlspecialchars((string) $modalidadTutor, ENT_QUOTES, "UTF-8"); ?></span>
                                <p class="tarjeta-precio">
                                    <?php if ($tutor["precio_hora"] === null): ?>
                                        Precio por definir
                                    <?php else: ?>
                                        <?php echo htmlspecialchars(number_format((float) $tutor["precio_hora"], 2), ENT_QUOTES, "UTF-8"); ?>
                                        <span>por hora</span>
                                    <?php endif; ?>
                                </p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </section>


        <!-- Cómo funciona -->
        <section class="como-funciona" id="como-funciona">
            <div class="contenedor">

                <h2>Cómo funciona</h2>

                <!--
                    JS (futuro) - PASOS DE "CÓMO FUNCIONA" (opcional)
                    Qué hará: resaltar cada paso al pasar el mouse (efecto visual).
                    Cómo conectarlo: usar la clase "paso" que ya tiene cada paso,
                    o agregar un id a este <div> (sugerido: id="lista-pasos").
                -->
                <div class="pasos-lista">

                    <div class="paso">
                        <span class="paso-numero">1</span>
                        <h3>Busca</h3>
                        <p>Escribe la materia que necesitas y mira los tutores disponibles.</p>
                    </div>

                    <div class="paso">
                        <span class="paso-numero">2</span>
                        <h3>Elige</h3>
                        <p>Revisa la información del tutor y si da clases virtuales o presenciales.</p>
                    </div>

                    <div class="paso">
                        <span class="paso-numero">3</span>
                        <h3>Reserva</h3>
                        <p>Solicita tu sesión en el horario que el tutor tenga disponible.</p>
                    </div>

                </div>

            </div>
        </section>

    </main>


    <?php require_once __DIR__ . "/includes/footer.php"; ?>

    <!--
        JS (futuro) - AQUÍ SE CONECTA EL SCRIPT
        Cuando crees tu archivo de JavaScript, se enlaza justo aquí,
        antes de cerrar el body, para que la página ya esté cargada
        cuando el código busque los elementos.
        Ejemplo: <script src="js/script.js"></script>
    -->

</body>

</html>