<?php
include 'db_config.php';
session_start();
include 'cors-config.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true && isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $json = file_get_contents('php://input');
        $params = json_decode($json, true);

        $userId = $_SESSION['user_id'];
        $garmentsIds = $params['garmentIds'] ?? null;

        if (empty($garmentsIds)) {
            http_response_code(400);
            echo json_encode([
                'result' => false,
                'message' => 'Faltan campos requeridos'
            ]);
            exit();
        }

        $con->begin_transaction();

        $insertOutfitQuery = 'INSERT INTO outfit (user_id) VALUES (?)';
        $sth = $con->prepare($insertOutfitQuery);

        if (!$sth) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'result' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $sth->bind_param('i', $userId);

        if (!$sth->execute()) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'result' => false,
                'message' => $sth->error
            ]);
            exit();
        }

        $outfitId = $con->insert_id;
        $sth->close();

        $insertGarmentQuery = 'INSERT INTO outfit_garment (outfit_id, garment_id) VALUES (?, ?)';
        $sth = $con->prepare($insertGarmentQuery);

        if (!$sth) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'result' => false,
                'message' => $con->error
            ]);
            exit();
        }

        foreach ($garmentsIds as $garmentId) {
            $sth->bind_param('ii', $outfitId, $garmentId);
            if (!$sth->execute()) {
                $con->rollback();
                http_response_code(500);
                echo json_encode([
                    'result' => false,
                    'message' => $sth->error
                ]);
                exit();
            }
        }

        $sth->close();

        $updateOutfitedQuery = 'UPDATE garment SET outfited = outfited + 1 WHERE id = ?';
        $sth = $con->prepare($updateOutfitedQuery);

        if (!$sth) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'result' => false,
                'message' => $con->error
            ]);
            exit();
        }

        foreach ($garmentsIds as $garmentId) {
            $sth->bind_param('i', $garmentId);
            if (!$sth->execute()) {
                $con->rollback();
                http_response_code(500);
                echo json_encode([
                    'result' => false,
                    'message' => $sth->error
                ]);
                exit();
            }
        }

        $sth->close();
        $con->commit();

        http_response_code(200);
        echo json_encode([
            'result' => true,
            'message' => 'Outfit creado satisfactoriamente',
            'data' => [
                'id' => $outfitId,
                'garmentIds' => $garmentsIds
            ]
        ]);
    }
}
?>
