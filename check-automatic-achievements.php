<?php
include 'db_config.php';
session_start();
include 'cors-config.php';

$automaticAchievements = [
    17 => ['query' => 'SELECT COUNT(*) as count FROM garment WHERE user_id = ? AND is_second_hand = 1', 'minimum' => 5],
    19 => ['query' => 'SELECT COUNT(*) as count FROM outfit WHERE user_id = ?', 'minimum' => 5],
    20 => ['query' => 'SELECT COUNT(*) as count FROM garment WHERE user_id = ? AND outfited >= 5', 'minimum' => 1]
];

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true && isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $userId = $_SESSION['user_id'];
        $completedAchievementsIds = [];

        foreach ($automaticAchievements as $achievementId => $achievementQuery) {
            $achievementStateQuery = 'SELECT achievement.id FROM achievement
                                       LEFT JOIN user_achievement_progress uap ON achievement.id = uap.achievement_id AND uap.user_id = ?
                                       WHERE achievement.id = ? AND (uap.completed_at IS NULL OR uap.id IS NULL)';
            $checkAchievementSth = $con->prepare($achievementStateQuery);
            $checkAchievementSth->bind_param('ii', $userId, $achievementId);
            $checkAchievementSth->execute();
            $result = $checkAchievementSth->get_result();
            $achievementRow = $result->fetch_assoc();
            $checkAchievementSth->close();

            if (!$achievementRow) {
                continue;
            }

            $completionCountSth = $con->prepare($achievementQuery['query']);
            $completionCountSth->bind_param('i', $userId);
            $completionCountSth->execute();
            $countResult = $completionCountSth->get_result();
            $countRow = $countResult->fetch_assoc();
            $currentCount = (int)$countRow['count'];
            $completionCountSth->close();

            if ($currentCount >= $achievementQuery['minimum']) {
                $insertInProgressQuery = 'INSERT INTO user_achievement_progress (user_id, achievement_id, completed_at)
                                            VALUES (?, ?, NOW())
                                            ON DUPLICATE KEY UPDATE completed_at = NOW()';
                $insertInProgressSth = $con->prepare($insertInProgressQuery);
                $insertInProgressSth->bind_param('ii', $userId, $achievementId);
                $insertInProgressSth->execute();
                $insertInProgressSth->close();

                $getPointsQuery = 'SELECT points FROM achievement WHERE id = ?';
                $getPointsSth = $con->prepare($getPointsQuery);
                $getPointsSth->bind_param('i', $achievementId);
                $getPointsSth->execute();
                $pointsResult = $getPointsSth->get_result();
                $pointsRow = $pointsResult->fetch_assoc();
                $pointsToAdd = (int)$pointsRow['points'];
                $getPointsSth->close();

                $getUserLevelQuery = 'SELECT level, current_points FROM user_level WHERE user_id = ?';
                $userLevelSth = $con->prepare($getUserLevelQuery);
                $userLevelSth->bind_param('i', $userId);
                $userLevelSth->execute();
                $userLevelResult = $userLevelSth->get_result();
                $userLevel = $userLevelResult->fetch_assoc();
                $userLevelSth->close();

                $currentLevel = (int)$userLevel['level'];
                $currentPoints = (int)$userLevel['current_points'];
                $newPoints = $currentPoints + $pointsToAdd;

                if ($newPoints >= 100) {
                    $currentLevel++;
                    $newPoints = $newPoints - 100;
                }

                $updatePointsQuery = 'UPDATE user_level SET level = ?, current_points = ? WHERE user_id = ?';
                $updatePointsSth = $con->prepare($updatePointsQuery);
                $updatePointsSth->bind_param('iii', $currentLevel, $newPoints, $userId);
                $updatePointsSth->execute();
                $updatePointsSth->close();

                $completedAchievementsIds[] = $achievementId;
            }
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => ['completedAchievements' => $completedAchievementsIds]
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no loggeado'
    ]);
}
?>
