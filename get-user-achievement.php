<?php
include 'db_config.php';
session_start();
include 'cors-config.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true && isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $userId = $_SESSION['user_id'];

        $selectQuery = 'SELECT level, current_points FROM user_level WHERE user_id = ?';
        $sth = $con->prepare($selectQuery);

        if (!$sth) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $sth->bind_param('i', $userId);

        if ($sth->execute()) {
            $result = $sth->get_result();
            $userAchievements = $result->fetch_assoc();

            if ($userAchievements) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'level' => (int)$userAchievements['level'],
                        'currentPoints' => (int)$userAchievements['current_points']
                    ]
                ]);
            } else {
                $insertQuery = 'INSERT INTO user_level (user_id, level, current_points) VALUES (?, 1, 0)';
                $insertSth = $con->prepare($insertQuery);
                $insertSth->bind_param('i', $userId);

                if ($insertSth->execute()) {
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
                $insertSth->close();
            }
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $sth->error
            ]);
        }

        $sth->close();
    }
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no loggeado'
    ]);
}
?>
