<?php
$nombre_sitio = "TutoresOn-Line";
$ruta_base = "../";
$ruta_secciones = "../index.php";
require_once __DIR__ . "/../config/sesion.php";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro - TutoresOn-Line</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <?php require_once __DIR__ . "/../includes/header.php"; ?>

    <div class="registro">
        <h1>¡Inicia sesión!</h1>

        <form action="../Registro/procesar_login.php" method="POST">
            <input type="email" name="email"
                   placeholder="Correo electrónico" autocomplete="email" required>

            <input type="password" name="password"
                   placeholder="Contraseña" autocomplete="current-password" required>

            <button type="submit">
                Iniciar sesión
            </button>
        </form>
    </div>

</body>
</html>
