<?php
session_start();
include 'db_config.php';
include 'cors-config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $_SESSION = [];

    session_destroy();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Se cerró sesión correctamente'
    ]);
}
?>
