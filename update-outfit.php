<?php
include 'db_config.php';
session_start();
include 'cors-config.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true && isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);

        $userId = $_SESSION['user_id'];
        $outfitId = $data['outfitId'] ?? null;
        $garmentId = $data['garmentId'] ?? null;

        if (!$outfitId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID de outfit requerido'
            ]);
            exit();
        }

        $checkOutfitQuery = 'SELECT id FROM outfit WHERE id = ? AND user_id = ?';
        $checkOutfitSth = $con->prepare($checkOutfitQuery);

        if (!$checkOutfitSth) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $checkOutfitSth->bind_param('ii', $outfitId, $userId);
        $checkOutfitSth->execute();
        $outfitResult = $checkOutfitSth->get_result();

        if ($outfitResult->num_rows === 0) {
            $checkOutfitSth->close();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Conjunto no encontrado'
            ]);
            exit();
        }
        $checkOutfitSth->close();

        $con->begin_transaction();

        if ($garmentId !== null) {
            $getCurrentGarmentsQuery = 'SELECT garment_id FROM outfit_garment WHERE outfit_id = ?';
            $getCurrentGarmentsSth = $con->prepare($getCurrentGarmentsQuery);

            if (!$getCurrentGarmentsSth) {
                $con->rollback();
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => $con->error
                ]);
                exit();
            }

            $getCurrentGarmentsSth->bind_param('i', $outfitId);
            $getCurrentGarmentsSth->execute();
            $oldGarmentsResult = $getCurrentGarmentsSth->get_result();
            $oldGarmentIds = [];
            while ($row = $oldGarmentsResult->fetch_assoc()) {
                $oldGarmentIds[] = $row['garment_id'];
            }
            $getCurrentGarmentsSth->close();

            $removedGarments = array_diff($oldGarmentIds, $garmentId);
            if (!empty($removedGarments)) {
                $decrementQuery = 'UPDATE garment SET outfited = outfited - 1 WHERE id = ? AND outfited > 0';
                $decrementSth = $con->prepare($decrementQuery);

                if (!$decrementSth) {
                    $con->rollback();
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'message' => $con->error
                    ]);
                    exit();
                }

                foreach ($removedGarments as $removedId) {
                    $decrementSth->bind_param('i', $removedId);
                    if (!$decrementSth->execute()) {
                        $con->rollback();
                        http_response_code(500);
                        echo json_encode([
                            'success' => false,
                            'message' => $decrementSth->error
                        ]);
                        exit();
                    }
                }
                $decrementSth->close();
            }

            $addedGarments = array_diff($garmentId, $oldGarmentIds);
            if (!empty($addedGarments)) {
                $incrementQuery = 'UPDATE garment SET outfited = outfited + 1 WHERE id = ?';
                $incrementSth = $con->prepare($incrementQuery);

                if (!$incrementSth) {
                    $con->rollback();
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'message' => $con->error
                    ]);
                    exit();
                }

                foreach ($addedGarments as $addedId) {
                    $incrementSth->bind_param('i', $addedId);
                    if (!$incrementSth->execute()) {
                        $con->rollback();
                        http_response_code(500);
                        echo json_encode([
                            'success' => false,
                            'message' => $incrementSth->error
                        ]);
                        exit();
                    }
                }
                $incrementSth->close();
            }

            $deleteGarmentsQuery = 'DELETE FROM outfit_garment WHERE outfit_id = ?';
            $deleteGarmentSth = $con->prepare($deleteGarmentsQuery);

            if (!$deleteGarmentSth) {
                $con->rollback();
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => $con->error
                ]);
                exit();
            }

            $deleteGarmentSth->bind_param('i', $outfitId);

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

            foreach ($garmentId as $gId) {
                $insertGarmentSth->bind_param('ii', $outfitId, $gId);

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
        }

        $con->commit();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Outfit actualizado satisfactoriamente',
            'data' => [
                'id' => $outfitId,
                'garmentIds' => $garmentId
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
