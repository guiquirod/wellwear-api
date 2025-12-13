<?php
include 'db_config.php';
session_start();
include 'cors-config.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true && isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $json = file_get_contents('php://input');
        $params = json_decode($json, true);

        $userId = $_SESSION['user_id'];
        $outfitId = $params['outfitId'] ?? null;
        $garmentId = $params['garmentId'] ?? null;

        if (!$outfitId) {
            http_response_code(400);
            echo json_encode([
                'result' => false,
                'message' => 'ID de outfit requerido'
            ]);
            exit();
        }

        $checkQuery = 'SELECT id FROM outfit WHERE id = ? AND user_id = ?';
        $sth = $con->prepare($checkQuery);

        if (!$sth) {
            http_response_code(500);
            echo json_encode([
                'result' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $sth->bind_param('ii', $outfitId, $userId);
        $sth->execute();
        $result = $sth->get_result();

        if ($result->num_rows === 0) {
            $sth->close();
            http_response_code(400);
            echo json_encode([
                'result' => false,
                'message' => 'Conjunto no encontrado'
            ]);
            exit();
        }
        $sth->close();

        $con->begin_transaction();

        if ($garmentId !== null) {
            $getCurrentGarmentsQuery = 'SELECT garment_id FROM outfit_garment WHERE outfit_id = ?';
            $sth = $con->prepare($getCurrentGarmentsQuery);

            if (!$sth) {
                $con->rollback();
                http_response_code(500);
                echo json_encode([
                    'result' => false,
                    'message' => $con->error
                ]);
                exit();
            }

            $sth->bind_param('i', $outfitId);
            $sth->execute();
            $result = $sth->get_result();
            $oldGarmentIds = [];
            while ($row = $result->fetch_assoc()) {
                $oldGarmentIds[] = $row['garment_id'];
            }
            $sth->close();

            $removedGarments = array_diff($oldGarmentIds, $garmentId);
            if (!empty($removedGarments)) {
                $decrementQuery = 'UPDATE garment SET outfited = outfited - 1 WHERE id = ? AND outfited > 0';
                $sth = $con->prepare($decrementQuery);

                if (!$sth) {
                    $con->rollback();
                    http_response_code(500);
                    echo json_encode([
                        'result' => false,
                        'message' => $con->error
                    ]);
                    exit();
                }

                foreach ($removedGarments as $removedId) {
                    $sth->bind_param('i', $removedId);
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
            }

            $addedGarments = array_diff($garmentId, $oldGarmentIds);
            if (!empty($addedGarments)) {
                $incrementQuery = 'UPDATE garment SET outfited = outfited + 1 WHERE id = ?';
                $sth = $con->prepare($incrementQuery);

                if (!$sth) {
                    $con->rollback();
                    http_response_code(500);
                    echo json_encode([
                        'result' => false,
                        'message' => $con->error
                    ]);
                    exit();
                }

                foreach ($addedGarments as $gId) {
                    $sth->bind_param('i', $gId);
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
            }

            $deleteGarmentsQuery = 'DELETE FROM outfit_garment WHERE outfit_id = ?';
            $sth = $con->prepare($deleteGarmentsQuery);

            if (!$sth) {
                $con->rollback();
                http_response_code(500);
                echo json_encode([
                    'result' => false,
                    'message' => $con->error
                ]);
                exit();
            }

            $sth->bind_param('i', $outfitId);

            if (!$sth->execute()) {
                $con->rollback();
                http_response_code(500);
                echo json_encode([
                    'result' => false,
                    'message' => $sth->error
                ]);
                exit();
            }
            $sth->close();

            $insertQuery = 'INSERT INTO outfit_garment (outfit_id, garment_id) VALUES (?, ?)';
            $sth = $con->prepare($insertQuery);

            if (!$sth) {
                $con->rollback();
                http_response_code(500);
                echo json_encode([
                    'result' => false,
                    'message' => $con->error
                ]);
                exit();
            }

            foreach ($garmentId as $gId) {
                $sth->bind_param('ii', $outfitId, $gId);

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
        }

        $con->commit();

        http_response_code(200);
        echo json_encode([
            'result' => true,
            'message' => 'Outfit actualizado satisfactoriamente',
            'data' => [
                'id' => $outfitId,
                'garmentId' => $garmentId
            ]
        ]);
    }
}

$con->close();
?>
