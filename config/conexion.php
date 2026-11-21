<?php
/*
    Conexion a SQL Server.
    En local funciona sin configurar nada (localhost, autenticacion de Windows).
    En la nube se configura con variables de entorno:
      DB_SERVER  (ej. miservidor.database.windows.net)
      DB_NAME    (por defecto: tutoresonline)
      DB_USER, DB_PASS
      DB_ENCRYPT = 1   (obligatorio en Azure SQL)
*/

$servidor = getenv("DB_SERVER") ?: "localhost";

$opciones = array(
    "Database" => getenv("DB_NAME") ?: "tutoresonline",
    "Encrypt" => getenv("DB_ENCRYPT") === "1",
    "TrustServerCertificate" => getenv("DB_ENCRYPT") !== "1"
);

if (getenv("DB_USER") !== false && getenv("DB_USER") !== "") {
    $opciones["UID"] = getenv("DB_USER");
    $opciones["PWD"] = getenv("DB_PASS") ?: "";
}

$conexion = sqlsrv_connect($servidor, $opciones);

if ($conexion === false) {
    error_log(print_r(sqlsrv_errors(), true));
    die("Error al conectar con la base de datos.");
}
?>
