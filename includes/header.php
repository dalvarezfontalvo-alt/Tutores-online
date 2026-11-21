<!-- Encabezado -->
<header class="encabezado">
    <div class="contenedor encabezado-contenido">

        <!-- Logotipo (se escribe con PHP usando la variable de arriba) -->
        <a href="<?php echo $ruta_base; ?>index.php" class="logotipo"><?php echo $nombre_sitio; ?></a>

        <!-- Menú de navegación: cada enlace lleva a una sección de esta página -->
        <!--
            JS (futuro) - MENÚ DE NAVEGACIÓN
            Qué hará: resaltar el enlace de la sección que el usuario está viendo.
            Cómo conectarlo: agregar un id a la etiqueta <nav>
            (sugerido: id="menu-navegacion") y recorrer sus enlaces <a> con JavaScript.
        -->
        <nav class="menu">
            <a href="<?php echo $ruta_secciones; ?>#inicio">Inicio</a>
            <a href="<?php echo $ruta_secciones; ?>#tutores">Tutores</a>
            <a href="<?php echo $ruta_secciones; ?>#como-funciona">Cómo funciona</a>
            <a href="<?php echo $ruta_secciones; ?>#contacto">Contacto</a>
        </nav>

        <div class="acceso">
            <?php if (isset($_SESSION["usuario"]["nombre"])): ?>
                <span>Hola, <?php echo htmlspecialchars($_SESSION["usuario"]["nombre"], ENT_QUOTES, "UTF-8"); ?></span>
                <?php if (isset($_SESSION["usuario"]["rol"])): ?>
                    <?php
                    $ruta_panel = $_SESSION["usuario"]["rol"] === "tutor"
                        ? "tutores/panel.php"
                        : "usuarios/panel_estudiante.php";
                    ?>
                    <a href="<?php echo $ruta_base . $ruta_panel; ?>" class="boton-sesion">Mi panel</a>
                <?php endif; ?>
                <a href="<?php echo $ruta_base; ?>usuarios/logout.php" class="boton-sesion">Cerrar sesión</a>
            <?php else: ?>
                <a href="<?php echo $ruta_base; ?>usuarios/login.php" class="boton-sesion">Iniciar sesión</a>
                <a href="<?php echo $ruta_base; ?>usuarios/registro.php" class="boton-registro">Registrarse</a>
            <?php endif; ?>
        </div>

    </div>
</header>
