<?php
session_start();
include 'db_config.php';
include 'cors-config.php';

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (isset($_SESSION['user_id']) && isset($_SESSION['email']) && isset($_SESSION['name'])) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Sesión activa',
            'data' => [
                'email' => $_SESSION['email'],
                'name' => $_SESSION['name']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'No hay sesión activa'
        ]);
    }
}
?>
