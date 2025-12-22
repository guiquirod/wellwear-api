<?php
include 'db_config.php';
session_start();
include 'cors-config.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true && isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $userId = $_SESSION['user_id'];
        $data = json_decode(file_get_contents('php://input'), true);
        $achievementId = isset($data['achievementId']) ? (int)$data['achievementId'] : null;

        if (!$achievementId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Achievement ID required'
            ]);
            exit();
        }

        $selectAchievementQuery = 'SELECT achievement.type, achievement.points, uap.completed_at
                                   FROM achievement
                                   LEFT JOIN user_achievement_progress uap ON achievement.id = uap.achievement_id AND uap.user_id = ?
                                   WHERE achievement.id = ?';

        $sth = $con->prepare($selectAchievementQuery);
        $sth->bind_param('ii', $userId, $achievementId);

        if ($sth->execute()) {
            $result = $sth->get_result();
            $achievement = $result->fetch_assoc();

            if (!$achievement) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Reto no encontrado'
                ]);
                exit();
            }

            $completedAt = $achievement['completed_at'];
            $canComplete = true;

            if ($completedAt !== null) {
                $completedTimestamp = strtotime($completedAt);
                $currentTimestamp = time();

                if ($achievement['type'] === 'daily') {
                    $completedDate = date('Y-m-d', $completedTimestamp);
                    $currentDate = date('Y-m-d', $currentTimestamp);
                    $canComplete = $completedDate < $currentDate;
                } else if ($achievement['type'] === 'weekly') {
                    $completedWeekStart = strtotime('monday this week', $completedTimestamp);
                    $currentWeekStart = strtotime('monday this week', $currentTimestamp);
                    $canComplete = $completedWeekStart < $currentWeekStart;
                } else if ($achievement['type'] === 'monthly') {
                    $completionYearMonth = date('Y-m', $completedTimestamp);
                    $currentYearMonth = date('Y-m', $currentTimestamp);
                    $canComplete = $completionYearMonth < $currentYearMonth;
                }
            }

            if (!$canComplete) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Reto ya cumplido'
                ]);
                exit();
            }

            $insertOrUpdateQuery = 'INSERT INTO user_achievement_progress (user_id, achievement_id, completed_at)
                                    VALUES (?, ?, NOW())
                                    ON DUPLICATE KEY UPDATE completed_at = NOW()';

            $insertUpdateSth = $con->prepare($insertOrUpdateQuery);
            $insertUpdateSth->bind_param('ii', $userId, $achievementId);

            if ($insertUpdateSth->execute()) {
                $selectUserAchievementQuery = 'SELECT level, current_points FROM user_level WHERE user_id = ?';
                $userAchievementSth = $con->prepare($selectUserAchievementQuery);

                if (!$userAchievementSth) {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'message' => $con->error
                    ]);
                    exit();
                }

                $userAchievementSth->bind_param('i', $userId);

                if (!$userAchievementSth->execute()) {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'message' => $userAchievementSth->error
                    ]);
                    $userAchievementSth->close();
                    exit();
                }

                $userAchievementResult = $userAchievementSth->get_result();
                $userAchievement = $userAchievementResult->fetch_assoc();

                if (!$userAchievement) {
                    $insertUserAchievementQuery = 'INSERT INTO user_level (user_id, level, current_points) VALUES (?, 1, 0)';
                    $insertUserSth = $con->prepare($insertUserAchievementQuery);
                    $insertUserSth->bind_param('i', $userId);
                    $insertUserSth->execute();
                    $insertUserSth->close();
                    $userAchievement = ['level' => 1, 'current_points' => 0];
                }

                $currentLevel = (int)$userAchievement['level'];
                $currentPoints = (int)$userAchievement['current_points'];
                $pointsToAdd = (int)$achievement['points'];
                $newPoints = $currentPoints + $pointsToAdd;

                if ($newPoints >= 100) {
                    $currentLevel++;
                    $newPoints = $newPoints - 100;
                }

                $updatePointsQuery = 'UPDATE user_level SET level = ?, current_points = ? WHERE user_id = ?';
                $updatePointsSth = $con->prepare($updatePointsQuery);

                if (!$updatePointsSth) {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'message' => $con->error
                    ]);
                    $userAchievementSth->close();
                    exit();
                }

                $updatePointsSth->bind_param('iii', $currentLevel, $newPoints, $userId);

                if (!$updatePointsSth->execute()) {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'message' => $updatePointsSth->error
                    ]);
                    $updatePointsSth->close();
                    $userAchievementSth->close();
                    exit();
                }

                $updatePointsSth->close();

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'level' => $currentLevel,
                        'currentPoints' => $newPoints
                    ]
                ]);

                $userAchievementSth->close();
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => $insertUpdateSth->error
                ]);
            }

            $insertUpdateSth->close();
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
