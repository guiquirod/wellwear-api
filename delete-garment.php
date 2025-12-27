<?php
include 'db_config.php';
session_start();
include 'cors-config.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true && isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);

        $userId = $_SESSION['user_id'];
        $garmentId = $data['garmentId'] ?? null;

        if (!$garmentId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Falta el ID de la prenda'
            ]);
            exit();
        }

        $existsQuery = 'SELECT id, picture FROM garment WHERE id = ? AND user_id = ?';
        $existsSth = $con->prepare($existsQuery);
        $existsSth->bind_param('ii', $garmentId, $userId);
        $existsSth->execute();
        $result = $existsSth->get_result();

        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Prenda no encontrada'
            ]);
            exit();
        }

        $garment = $result->fetch_assoc();
        $picturePath = $garment['picture'];
        $existsSth->close();

        $con->begin_transaction();

        $affectedOutfitsQuery = 'SELECT outfit_id FROM outfit_garment WHERE garment_id = ?';
        $affectedSth = $con->prepare($affectedOutfitsQuery);
        $affectedSth->bind_param('i', $garmentId);
        $affectedSth->execute();
        $affectedResult = $affectedSth->get_result();

        $affectedOutfits = [];
        while ($row = $affectedResult->fetch_assoc()) {
            $affectedOutfits[] = $row['outfit_id'];
        }
        $affectedSth->close();

        $deleteGarmentRelationsQuery = 'DELETE FROM outfit_garment WHERE garment_id = ?';
        $deleteSth = $con->prepare($deleteGarmentRelationsQuery);

        if (!$deleteSth) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $deleteSth->bind_param('i', $garmentId);

        if (!$deleteSth->execute()) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $deleteSth->error
            ]);
            exit();
        }
        $deleteSth->close();

        foreach ($affectedOutfits as $outfitId) {
            $checkOutfitQuery = 'SELECT COUNT(*) as garment_count FROM outfit_garment WHERE outfit_id = ?';
            $garmentAmountSth = $con->prepare($checkOutfitQuery);
            $garmentAmountSth->bind_param('i', $outfitId);
            $garmentAmountSth->execute();
            $result = $garmentAmountSth->get_result();
            $row = $result->fetch_assoc();
            $garmentCount = $row['garment_count'];
            $garmentAmountSth->close();

            if ($garmentCount === 0) {
                $deleteCalendarQuery = 'DELETE FROM outfit_calendar WHERE outfit_id = ?';
                $deleteOutfitSth = $con->prepare($deleteCalendarQuery);

                if (!$deleteOutfitSth) {
                    $con->rollback();
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'message' => $con->error
                    ]);
                    exit();
                }

                $deleteOutfitSth->bind_param('i', $outfitId);
                if (!$deleteOutfitSth->execute()) {
                    $con->rollback();
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'message' => $deleteOutfitSth->error
                    ]);
                    exit();
                }
                $deleteOutfitSth->close();

                $deleteEmptyOutfitQuery = 'DELETE FROM outfit WHERE id = ?';
                $deletOutfitSth = $con->prepare($deleteEmptyOutfitQuery);

                if (!$deletOutfitSth) {
                    $con->rollback();
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'message' => $con->error
                    ]);
                    exit();
                }

                $deletOutfitSth->bind_param('i', $outfitId);

                if (!$deletOutfitSth->execute()) {
                    $con->rollback();
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'message' => $deletOutfitSth->error
                    ]);
                    exit();
                }
                $deletOutfitSth->close();
            }
        }

        $deleteGarmentQuery = 'DELETE FROM garment WHERE id = ?';
        $deleteGarmentSth = $con->prepare($deleteGarmentQuery);
        $deleteGarmentSth->bind_param('i', $garmentId);

        if (!$deleteGarmentSth->execute()) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $deleteGarmentSth->error
            ]);
            exit();
        }

        $deleteGarmentSth->close();
        $con->commit();

        if ($picturePath && file_exists(__DIR__ . '/' . $picturePath)) {
            unlink(__DIR__ . '/' . $picturePath);
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Prenda eliminada satisfactoriamente'
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no logueado'
    ]);
}
?>
