<?php
include 'db_config.php';
session_start();
include 'cors-config.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true && isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $userId = $_SESSION['user_id'];

        $deleteUserQuery = 'DELETE FROM users WHERE id = ?';
        $deleteUserSth = $con->prepare($deleteUserQuery);

        if (!$deleteUserSth) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $deleteUserSth->bind_param('i', $userId);

        if ($deleteUserSth->execute()) {
            $deleteUserSth->close();

            $_SESSION = [];
            session_destroy();

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Usuario eliminado correctamente'
            ]);
        } else {
            $deleteUserSth->close();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $deleteUserSth->error
            ]);
        }
    }
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no logueado'
    ]);
}
?>
