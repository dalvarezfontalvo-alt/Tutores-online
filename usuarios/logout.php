<?php
require_once __DIR__ . "/../config/sesion.php";

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $cookie = session_get_cookie_params();
    setcookie(session_name(), "", array(
        "expires" => time() - 42000,
        "path" => $cookie["path"],
        "domain" => $cookie["domain"],
        "secure" => $cookie["secure"],
        "httponly" => $cookie["httponly"],
        "samesite" => "Lax"
    ));
}

session_destroy();
header("Location: ../index.php");
exit;
