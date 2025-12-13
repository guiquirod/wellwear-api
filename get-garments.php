<?php
session_start();
include 'db_config.php';
include 'cors-config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $userId = $_SESSION['user_id'];

    $selectQuery = 'SELECT id, type, sup_type, fabric_type, sleeve, seasons, picture, main_color, pattern, is_second_hand, worn, outfited FROM garment WHERE user_id = ? ORDER BY FIELD(sup_type, "superior", "inferior")';
    $sth = $con->prepare($selectQuery);

    if (!$sth) {
        http_response_code(500);
        echo json_encode([
            'error' => $con->error
        ]);
        exit();
    }

    $sth->bind_param('i', $userId);

    if ($sth->execute()) {
        $result = $sth->get_result();
        $garments = [];

        while ($row = $result->fetch_assoc()) {
            $garments[] = [
                'id' => $row['id'],
                'type' => $row['type'],
                'supType' => $row['sup_type'],
                'fabricType' => json_decode($row['fabric_type']),
                'sleeve' => $row['sleeve'],
                'seasons' => json_decode($row['seasons']),
                'picture' => $row['picture'],
                'mainColor' => $row['main_color'],
                'pattern' => (bool)$row['pattern'],
                'isSecondHand' => (bool)$row['is_second_hand'],
                'worn' => $row['worn'],
                'outfited' => $row['outfited']
            ];
        }

        http_response_code(200);
        echo json_encode($garments);
    } else {
        http_response_code(500);
        echo json_encode([
            'error' => $sth->error
        ]);
    }
    $sth->close();
}
?>
