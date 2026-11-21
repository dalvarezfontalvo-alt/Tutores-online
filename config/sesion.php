<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";

    session_set_cookie_params(array(
        "lifetime" => 0,
        "path" => "/",
        "secure" => $https,
        "httponly" => true,
        "samesite" => "Lax"
    ));

    session_start();
}
