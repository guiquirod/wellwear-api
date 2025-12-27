<?php
include 'db_config.php';
session_start();
include 'cors-config.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true && isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);

        $userId = $_SESSION['user_id'];
        $outfitId = $data['outfitId'] ?? null;

        if (!$outfitId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Falta el ID del outfit'
            ]);
            exit();
        }

        $verifyQuery = 'SELECT id FROM outfit WHERE id = ? AND user_id = ?';
        $verifySth = $con->prepare($verifyQuery);
        $verifySth->bind_param('ii', $outfitId, $userId);
        $verifySth->execute();
        $result = $verifySth->get_result();

        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Conjunto no encontrado'
            ]);
            exit();
        }
        $verifySth->close();

        $con->begin_transaction();

        $getGarmentsQuery = 'SELECT garment_id FROM outfit_garment WHERE outfit_id = ?';
        $getGarmentsSth = $con->prepare($getGarmentsQuery);

        if (!$getGarmentsSth) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $getGarmentsSth->bind_param('i', $outfitId);
        $getGarmentsSth->execute();
        $garmentsResult = $getGarmentsSth->get_result();
        $garmentIds = [];
        while ($row = $garmentsResult->fetch_assoc()) {
            $garmentIds[] = $row['garment_id'];
        }
        $getGarmentsSth->close();

        if (!empty($garmentIds)) {
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

            foreach ($garmentIds as $garmentId) {
                $decrementSth->bind_param('i', $garmentId);
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

        $countCalendarQuery = 'SELECT COUNT(*) as count FROM outfit_calendar WHERE outfit_id = ?';
        $countCalendarSth = $con->prepare($countCalendarQuery);

        if (!$countCalendarSth) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $countCalendarSth->bind_param('i', $outfitId);
        $countCalendarSth->execute();
        $countCalendarResult = $countCalendarSth->get_result();
        $calendarCount = $countCalendarResult->fetch_assoc()['count'];
        $countCalendarSth->close();

        if ($calendarCount > 0 && !empty($garmentIds)) {
            $decrementWornQuery = 'UPDATE garment SET worn = worn - 1 WHERE id = ? AND worn > 0';
            $decrementWornSth = $con->prepare($decrementWornQuery);

            if (!$decrementWornSth) {
                $con->rollback();
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => $con->error
                ]);
                exit();
            }

            for ($i = 0; $i < $calendarCount; $i++) {
                foreach ($garmentIds as $garmentId) {
                    $decrementWornSth->bind_param('i', $garmentId);
                    if (!$decrementWornSth->execute()) {
                        $con->rollback();
                        http_response_code(500);
                        echo json_encode([
                            'success' => false,
                            'message' => $decrementWornSth->error
                        ]);
                        exit();
                    }
                }
            }
            $decrementWornSth->close();
        }

        $deleteCalendarQuery = 'DELETE FROM outfit_calendar WHERE outfit_id = ?';
        $deleteCalendarSth = $con->prepare($deleteCalendarQuery);

        if (!$deleteCalendarSth) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $deleteCalendarSth->bind_param('i', $outfitId);

        if (!$deleteCalendarSth->execute()) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $deleteCalendarSth->error
            ]);
            exit();
        }
        $deleteCalendarSth->close();

        $deleteGarmentRelationsQuery = 'DELETE FROM outfit_garment WHERE outfit_id = ?';
        $deleteGarmentRelationsSth = $con->prepare($deleteGarmentRelationsQuery);

        if (!$deleteGarmentRelationsSth) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $deleteGarmentRelationsSth->bind_param('i', $outfitId);

        if (!$deleteGarmentRelationsSth->execute()) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $deleteGarmentRelationsSth->error
            ]);
            exit();
        }
        $deleteGarmentRelationsSth->close();

        $deleteOutfitQuery = 'DELETE FROM outfit WHERE id = ?';
        $deleteOutfitSth = $con->prepare($deleteOutfitQuery);

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
        $con->commit();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Outfit eliminado satisfactoriamente'
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
