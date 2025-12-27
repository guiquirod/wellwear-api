<?php
include 'db_config.php';
session_start();
include 'cors-config.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true && isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $userId = $_SESSION['user_id'];

        $getUserLevelQuery = 'SELECT level, current_points FROM user_level WHERE user_id = ?';
        $getUserLevelSth = $con->prepare($getUserLevelQuery);

        if (!$getUserLevelSth) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $getUserLevelSth->bind_param('i', $userId);

        if ($getUserLevelSth->execute()) {
            $userLevelResult = $getUserLevelSth->get_result();
            $userLevel = $userLevelResult->fetch_assoc();

            if ($userLevel) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'level' => (int)$userLevel['level'],
                        'currentPoints' => (int)$userLevel['current_points']
                    ]
                ]);
            } else {
                $insertPointsQuery = 'INSERT INTO user_level (user_id, level, current_points) VALUES (?, 1, 0)';
                $insertPointsSth = $con->prepare($insertPointsQuery);
                $insertPointsSth->bind_param('i', $userId);

                if ($insertPointsSth->execute()) {
                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'level' => 1,
                            'currentPoints' => 0
                        ]
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error en el registo del reto'
                    ]);
                }
                $insertPointsSth->close();
            }
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $getUserLevelSth->error
            ]);
        }

        $getUserLevelSth->close();
    }
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no logueado'
    ]);
}
?>
