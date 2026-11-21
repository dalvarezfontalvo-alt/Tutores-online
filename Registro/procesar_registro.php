
<?php

require_once __DIR__ . "/../config/sesion.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Método no permitido.");
}

if (
    !isset($_POST["nombre"]) ||
    !isset($_POST["email"]) ||
    !isset($_POST["password"]) ||
    !isset($_POST["rol"]) ||
    !is_string($_POST["nombre"]) ||
    !is_string($_POST["email"]) ||
    !is_string($_POST["password"]) ||
    !is_string($_POST["rol"])
) {
    exit("Por favor, complete todos los campos.");
}

$nombre = trim($_POST["nombre"]);
$email = trim($_POST["email"]);
$password = $_POST["password"];
$rol = $_POST["rol"];

if ($nombre === "" || $email === "" || $password === "" || $rol === "") {
    exit("Por favor, complete todos los campos.");
}

if (!in_array($rol, array("estudiante", "tutor"), true)) {
    exit("El rol seleccionado no es válido.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("El correo electrónico no es válido.");
}

if (mb_strlen($nombre, "UTF-8") > 201 || mb_strlen($email, "UTF-8") > 150) {
    exit("El nombre o el correo exceden la longitud permitida.");
}

$partesNombre = preg_split('/\s+/u', $nombre, 2);
if ($partesNombre === false) {
    exit("El nombre no tiene un formato válido.");
}
$nombrePila = $partesNombre[0];
$apellido = $partesNombre[1] ?? "";

if (
    mb_strlen($nombrePila, "UTF-8") > 100 ||
    mb_strlen($apellido, "UTF-8") > 100
) {
    exit("El nombre o el apellido exceden la longitud permitida.");
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
if ($passwordHash === false) {
    error_log("No se pudo generar el hash de contraseña para el registro.");
    exit("No se pudo completar el registro.");
}

require_once __DIR__ . "/../config/conexion.php";

$sql = "SET NOCOUNT ON;
        SET XACT_ABORT ON;
        BEGIN TRY
            BEGIN TRANSACTION;

            DECLARE @rol VARCHAR(20) = ?;
            DECLARE @rol_id INT;
            SELECT @rol_id = id_rol
            FROM dbo.roles
            WHERE LOWER(nombre) = @rol;

            IF @rol_id IS NULL
                THROW 50003, 'El rol solicitado no existe.', 1;

            INSERT INTO dbo.usuarios (nombre, apellido, correo, contrasena, id_rol)
            VALUES (?, ?, ?, ?, @rol_id);
            DECLARE @usuario_id INT = CONVERT(INT, SCOPE_IDENTITY());

            IF @rol = 'tutor'
                INSERT INTO dbo.tutores (id_usuario) VALUES (@usuario_id);
            ELSE
                INSERT INTO dbo.estudiantes (id_usuario) VALUES (@usuario_id);

            COMMIT TRANSACTION;
            SELECT @usuario_id AS id_usuario;
        END TRY
        BEGIN CATCH
            IF XACT_STATE() <> 0 ROLLBACK TRANSACTION;
            THROW;
        END CATCH";
$parametros = array($rol, $nombrePila, $apellido, $email, $passwordHash);

$stmt = sqlsrv_query($conexion, $sql, $parametros);

if ($stmt === false) {
    $errores = sqlsrv_errors();
    $codigo = $errores[0]["code"] ?? 0;

    if ($codigo == 2601 || $codigo == 2627) {
        exit("Este correo ya está registrado.");
    }

    error_log(print_r($errores, true));
    exit("Error al registrar el usuario.");
}

$filaUsuario = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if ($filaUsuario === false || $filaUsuario === null) {
    error_log(print_r(sqlsrv_errors(), true));
    sqlsrv_free_stmt($stmt);
    exit("No se pudo completar el registro.");
}
sqlsrv_free_stmt($stmt);

session_regenerate_id(true);
$_SESSION["usuario"] = array(
    "id" => (int) $filaUsuario["id_usuario"],
    "nombre" => $nombre,
    "correo" => $email,
    "rol" => $rol
);

if ($rol === "tutor") {
    header("Location: ../tutores/panel.php");
} else {
    header("Location: ../usuarios/panel_estudiante.php");
}
exit;
?>
