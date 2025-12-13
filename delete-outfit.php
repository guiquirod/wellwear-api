<?php
include 'db_config.php';
session_start();
include 'cors-config.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true && isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
        $json = file_get_contents('php://input');
        $params = json_decode($json, true);

        $userId = $_SESSION['user_id'];
        $outfitId = $params['outfitId'] ?? null;

        if (!$outfitId) {
            http_response_code(400);
            echo json_encode([
                'result' => false,
                'message' => 'Falta el ID del outfit'
            ]);
            exit();
        }

        $verifyQuery = 'SELECT id FROM outfit WHERE id = ? AND user_id = ?';
        $sth = $con->prepare($verifyQuery);
        $sth->bind_param('ii', $outfitId, $userId);
        $sth->execute();
        $result = $sth->get_result();

        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode([
                'result' => false,
                'message' => 'Conjunto no encontrado'
            ]);
            exit();
        }
        $sth->close();

        $con->begin_transaction();

        $getGarmentsQuery = 'SELECT garment_id FROM outfit_garment WHERE outfit_id = ?';
        $sth = $con->prepare($getGarmentsQuery);

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
        $garmentIds = [];
        while ($row = $result->fetch_assoc()) {
            $garmentIds[] = $row['garment_id'];
        }
        $sth->close();

        if (!empty($garmentIds)) {
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

            foreach ($garmentIds as $gId) {
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

        $countCalendarQuery = 'SELECT COUNT(*) as count FROM outfit_calendar WHERE outfit_id = ?';
        $sth = $con->prepare($countCalendarQuery);

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
        $calendarCount = $result->fetch_assoc()['count'];
        $sth->close();

        if ($calendarCount > 0 && !empty($garmentIds)) {
            $decrementWornQuery = 'UPDATE garment SET worn = worn - 1 WHERE id = ? AND worn > 0';
            $sth = $con->prepare($decrementWornQuery);

            if (!$sth) {
                $con->rollback();
                http_response_code(500);
                echo json_encode([
                    'result' => false,
                    'message' => $con->error
                ]);
                exit();
            }

            for ($i = 0; $i < $calendarCount; $i++) {
                foreach ($garmentIds as $gId) {
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
            }
            $sth->close();
        }

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

        $deleteGarmentRelationsQuery = 'DELETE FROM outfit_garment WHERE outfit_id = ?';
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

        $deleteOutfitQuery = 'DELETE FROM outfit WHERE id = ?';
        $sth = $con->prepare($deleteOutfitQuery);

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
        $con->commit();

        http_response_code(200);
        echo json_encode([
            'result' => true,
            'message' => 'Outfit eliminado satisfactoriamente'
        ]);
    }
}

$con->close();
?>
