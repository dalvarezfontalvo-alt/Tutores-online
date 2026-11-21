
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
        <h1>Crear cuenta</h1>

       <form action="../Registro/procesar_registro.php" method="POST">
            <input type="text" name="nombre"
                   placeholder="Nombre completo" required>

            <input type="email" name="email"
                  placeholder="Correo electrónico" autocomplete="email" required>

            <input type="password" name="password"
                  placeholder="Contraseña" autocomplete="new-password" required>

           <label for="rol">Quiero registrarme como:</label>
           <select id="rol" name="rol" required>
               <option value="">Selecciona una opción</option>
               <option value="estudiante">Estudiante</option>
               <option value="tutor">Tutor</option>
           </select>

           <button type="submit" name="registrar">
               Registrarme
            </button>
        </form>
    </div>
    

</body>
</html>
