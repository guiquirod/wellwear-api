<?php
include 'db_config.php';
session_start();
include 'cors-config.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true && isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        $userId = $_SESSION['user_id'];
        $garmentsIds = $data['garmentIds'] ?? null;

        if (empty($garmentsIds)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Faltan prendas'
            ]);
            exit();
        }

        $con->begin_transaction();

        $insertOutfitQuery = 'INSERT INTO outfit (user_id) VALUES (?)';
        $insertUpdateSth = $con->prepare($insertOutfitQuery);

        if (!$insertUpdateSth) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $insertUpdateSth->bind_param('i', $userId);

        if (!$insertUpdateSth->execute()) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $insertUpdateSth->error
            ]);
            exit();
        }

        $outfitId = $con->insert_id;
        $insertUpdateSth->close();

        $insertGarmentQuery = 'INSERT INTO outfit_garment (outfit_id, garment_id) VALUES (?, ?)';
        $insertGarmentSth = $con->prepare($insertGarmentQuery);

        if (!$insertGarmentSth) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $con->error
            ]);
            exit();
        }

        foreach ($garmentsIds as $garmentId) {
            $insertGarmentSth->bind_param('ii', $outfitId, $garmentId);
            if (!$insertGarmentSth->execute()) {
                $con->rollback();
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => $insertGarmentSth->error
                ]);
                exit();
            }
        }

        $insertGarmentSth->close();

        $updateOutfitedQuery = 'UPDATE garment SET outfited = outfited + 1 WHERE id = ?';
        $updateGarmentSth = $con->prepare($updateOutfitedQuery);

        if (!$updateGarmentSth) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $con->error
            ]);
            exit();
        }

        foreach ($garmentsIds as $garmentId) {
            $updateGarmentSth->bind_param('i', $garmentId);
            if (!$updateGarmentSth->execute()) {
                $con->rollback();
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => $updateGarmentSth->error
                ]);
                exit();
            }
        }

        $updateGarmentSth->close();
        $con->commit();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Outfit creado satisfactoriamente',
            'data' => [
                'id' => $outfitId,
                'garmentIds' => $garmentsIds
            ]
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
