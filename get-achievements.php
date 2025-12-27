<?php
include 'db_config.php';
session_start();
include 'cors-config.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true && isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $userId = $_SESSION['user_id'];

        $getAchievementsQuery = 'SELECT achievement.id, achievement.title, achievement.type, achievement.points, uap.completed_at
                        FROM achievement
                        LEFT JOIN user_achievement_progress uap ON achievement.id = uap.achievement_id AND uap.user_id = ?
                        ORDER BY achievement.id';

        $getAchievementsSth = $con->prepare($getAchievementsQuery);

        if (!$getAchievementsSth) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $getAchievementsSth->bind_param('i', $userId);

        if ($getAchievementsSth->execute()) {
            $achievementsResult = $getAchievementsSth->get_result();
            $achievements = [];

            while ($row = $achievementsResult->fetch_assoc()) {
                $completedAt = $row['completed_at'];
                $completed = false;

                if ($completedAt !== null) {
                    $completedTimestamp = strtotime($completedAt);
                    $currentTimestamp = time();

                    if ($row['type'] === 'automatic') {
                        $completed = true;
                    } else if ($row['type'] === 'daily') {
                        $completedDate = date('Y-m-d', $completedTimestamp);
                        $currentDate = date('Y-m-d', $currentTimestamp);
                        $completed = $completedDate >= $currentDate;
                    } else if ($row['type'] === 'weekly') {
                        $completedWeekStart = strtotime('monday this week', $completedTimestamp);
                        $currentWeekStart = strtotime('monday this week', $currentTimestamp);
                        $completed = $completedWeekStart >= $currentWeekStart;
                    } else if ($row['type'] === 'monthly') {
                        $completedYearMonth = date('Y-m', $completedTimestamp);
                        $currentYearMonth = date('Y-m', $currentTimestamp);
                        $completed = $completedYearMonth >= $currentYearMonth;
                    }
                }

                $achievements[] = [
                    'id' => (int)$row['id'],
                    'title' => $row['title'],
                    'type' => $row['type'],
                    'points' => (int)$row['points'],
                    'completed' => $completed,
                    'completedAt' => $completedAt
                ];
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $achievements
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $getAchievementsSth->error
            ]);
        }

        $getAchievementsSth->close();
    }
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no logueado'
    ]);
}
?>
