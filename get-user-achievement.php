<?php
include 'db_config.php';
session_start();
include 'cors-config.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true && isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $userId = $_SESSION['user_id'];

        $getUserAchievementsQuery = 'SELECT level, current_points FROM user_level WHERE user_id = ?';
        $getUserAchievementsSth = $con->prepare($getUserAchievementsQuery);

        if (!$getUserAchievementsSth) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $getUserAchievementsSth->bind_param('i', $userId);

        if ($getUserAchievementsSth->execute()) {
            $userAchievementsResult = $getUserAchievementsSth->get_result();
            $userAchievements = $userAchievementsResult->fetch_assoc();

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
                'message' => $getUserAchievementsSth->error
            ]);
        }

        $getUserAchievementsSth->close();
    }
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no loggeado'
    ]);
}
?>
