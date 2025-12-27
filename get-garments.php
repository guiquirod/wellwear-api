<?php
include 'db_config.php';
session_start();
include 'cors-config.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true && isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $userId = $_SESSION['user_id'];

        $getGarmentsQuery = 'SELECT id, type, sup_type, fabric_type, sleeve, seasons, picture, main_color, pattern, is_second_hand, worn, outfited FROM garment WHERE user_id = ? ORDER BY FIELD(sup_type, "superior", "inferior")';
        $getGarmentsSth = $con->prepare($getGarmentsQuery);

        if (!$getGarmentsSth) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $getGarmentsSth->bind_param('i', $userId);

        if ($getGarmentsSth->execute()) {
            $garmentsResult = $getGarmentsSth->get_result();
            $garments = [];

            while ($row = $garmentsResult->fetch_assoc()) {
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
            echo json_encode([
                'success' => true,
                'data' => $garments
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $getGarmentsSth->error
            ]);
        }
        $getGarmentsSth->close();
    }
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no logueado'
    ]);
}
?>
