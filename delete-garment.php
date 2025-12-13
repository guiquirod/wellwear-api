<?php
include 'db_config.php';
session_start();
include 'cors-config.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true && isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
        $json = file_get_contents('php://input');
        $params = json_decode($json, true);

        $userId = $_SESSION['user_id'];
        $garmentId = $params['garmentId'] ?? null;

        if (!$garmentId) {
            http_response_code(400);
            echo json_encode([
                'result' => false,
                'message' => 'Falta el ID de la prenda'
            ]);
            exit();
        }

        $existsQuery = 'SELECT id, picture FROM garment WHERE id = ? AND user_id = ?';
        $sth = $con->prepare($existsQuery);
        $sth->bind_param('ii', $garmentId, $userId);
        $sth->execute();
        $result = $sth->get_result();

        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode([
                'result' => false,
                'message' => 'Prenda no encontrada'
            ]);
            exit();
        }

        $garment = $result->fetch_assoc();
        $picturePath = $garment['picture'];
        $sth->close();

        $con->begin_transaction();

        $affectedOutfitsQuery = 'SELECT DISTINCT outfit_id FROM outfit_garment WHERE garment_id = ?';
        $sth = $con->prepare($affectedOutfitsQuery);
        $sth->bind_param('i', $garmentId);
        $sth->execute();
        $result = $sth->get_result();

        $affectedOutfits = [];
        while ($row = $result->fetch_assoc()) {
            $affectedOutfits[] = $row['outfit_id'];
        }
        $sth->close();

        $deleteGarmentRelationsQuery = 'DELETE FROM outfit_garment WHERE garment_id = ?';
        $sth = $con->prepare($deleteGarmentRelationsQuery);

        if (!$sth) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'result' => false,
                'message' => $con->error
            ]);
            exit();
        }

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
        $sth->close();

        foreach ($affectedOutfits as $outfitId) {
            $checkOutfitQuery = 'SELECT COUNT(*) as garment_count FROM outfit_garment WHERE outfit_id = ?';
            $sth = $con->prepare($checkOutfitQuery);
            $sth->bind_param('i', $outfitId);
            $sth->execute();
            $result = $sth->get_result();
            $row = $result->fetch_assoc();
            $garmentCount = $row['garment_count'];
            $sth->close();

            if ($garmentCount === 0) {
                $deleteCalendarQuery = 'DELETE FROM outfit_calendar WHERE outfit_id = ?';
                $sth = $con->prepare($deleteCalendarQuery);

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

                $deleteEmptyOutfitQuery = 'DELETE FROM outfit WHERE id = ?';
                $sth = $con->prepare($deleteEmptyOutfitQuery);

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
            }
        }

        $deleteGarmentQuery = 'DELETE FROM garment WHERE id = ?';
        $sth = $con->prepare($deleteGarmentQuery);
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

        $sth->close();
        $con->commit();

        if ($picturePath && file_exists(__DIR__ . '/' . $picturePath)) {
            unlink(__DIR__ . '/' . $picturePath);
        }

        http_response_code(200);
        echo json_encode([
            'result' => true,
            'message' => 'Prenda eliminada satisfactoriamente'
        ]);
    }
}

$con->close();
?>
