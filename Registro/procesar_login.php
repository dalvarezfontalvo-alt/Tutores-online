<?php
require_once __DIR__ . "/../config/sesion.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Método no permitido.");
}

if (
    !isset($_POST["email"], $_POST["password"]) ||
    !is_string($_POST["email"]) ||
    !is_string($_POST["password"])
) {
    exit("Por favor, complete todos los campos.");
}

$email = trim($_POST["email"]);
$password = $_POST["password"];

if ($email === "" || $password === "") {
    exit("Por favor, complete todos los campos.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("El correo electrónico no es válido.");
}

require_once __DIR__ . "/../config/conexion.php";

$sql = "SELECT u.id_usuario, u.nombre, u.apellido, u.correo,
               u.contrasena, LOWER(r.nombre) AS rol
        FROM dbo.usuarios AS u
        INNER JOIN dbo.roles AS r ON r.id_rol = u.id_rol
        WHERE u.correo = ?
          AND u.estado = 1";
$stmt = sqlsrv_query($conexion, $sql, array($email));
if ($stmt === false) {
    error_log(print_r(sqlsrv_errors(), true));
    exit("Error al iniciar sesión.");
}

$usuario = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if ($usuario === false) {
    error_log(print_r(sqlsrv_errors(), true));
    sqlsrv_free_stmt($stmt);
    exit("Error al iniciar sesión.");
}
sqlsrv_free_stmt($stmt);

if (
    $usuario === null ||
    !is_string($usuario["contrasena"]) ||
    !password_verify($password, $usuario["contrasena"])
) {
    exit("Correo o contraseña incorrectos.");
}

$rol = is_string($usuario["rol"]) ? trim($usuario["rol"]) : "";
if (!in_array($rol, array("estudiante", "tutor"), true)) {
    error_log("El usuario autenticado tiene un rol no válido.");
    exit("La cuenta tiene un rol no válido. Contacte al administrador.");
}

session_regenerate_id(true);

$_SESSION["usuario"] = array(
    "id" => (int) $usuario["id_usuario"],
    "nombre" => trim($usuario["nombre"] . " " . $usuario["apellido"]),
    "correo" => $usuario["correo"],
    "rol" => $rol
);

if ($rol === "tutor") {
    header("Location: ../tutores/panel.php");
} else {
    header("Location: ../usuarios/panel_estudiante.php");
}
exit;
?>