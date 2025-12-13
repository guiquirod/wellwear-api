<?php
include 'db_config.php';
session_start();
include 'cors-config.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true && isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $input = file_get_contents('php://input');
        $params = json_decode($input, true);

        $userId = $_SESSION['user_id'];
        $outfitId = $params['outfitId'] ?? null;
        $wornDate = $params['wornDate'] ?? $params['date'];

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
                'message' => 'Outfit no encontrado'
            ]);
            exit();
        }
        $sth->close();

        $checkQuery = 'SELECT id FROM outfit_calendar WHERE outfit_id = ? AND date_worn = ?';
        $sth = $con->prepare($checkQuery);
        $sth->bind_param('is', $outfitId, $wornDate);
        $sth->execute();
        $result = $sth->get_result();

        if ($result->num_rows > 0) {
            http_response_code(400);
            echo json_encode([
                'result' => false,
                'message' => 'Este outfit ya está asignado a esta fecha'
            ]);
            exit();
        }
        $sth->close();

        $con->begin_transaction();

        $insertCalendar = 'INSERT INTO outfit_calendar (outfit_id, date_worn) VALUES (?, ?)';
        $sth = $con->prepare($insertCalendar);

        if (!$sth) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'result' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $sth->bind_param('is', $outfitId, $wornDate);

        if (!$sth->execute()) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'result' => false,
                'message' => 'Error al registrar uso: ' . $sth->error
            ]);
            exit();
        }

        $calendarId = $con->insert_id;
        $sth->close();

        $updateUsageQuery = 'UPDATE garment
                            JOIN outfit_garment ON garment.id = outfit_garment.garment_id
                            SET garment.worn = garment.worn + 1
                            WHERE outfit_garment.outfit_id = ?';
        $sth = $con->prepare($updateUsageQuery);

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
            'message' => 'Uso de outfit registrado satisfactoriamente',
            'data' => [
                'id' => $calendarId,
                'outfitId' => $outfitId,
                'wornDate' => $wornDate
            ]
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
        $input = file_get_contents('php://input');
        $params = json_decode($input, true);

        $userId = $_SESSION['user_id'];
        $outfitId = $params['outfitId'] ?? null;
        $wornDate = $params['wornDate'] ?? $params['date'] ?? null;

        if (!$outfitId || !$wornDate) {
            http_response_code(400);
            echo json_encode([
                'result' => false,
                'message' => 'Faltan el ID del outfit o la fecha'
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
                'message' => 'Outfit no encontrado'
            ]);
            exit();
        }
        $sth->close();

        $con->begin_transaction();

        $deleteCalendarQuery = 'DELETE FROM outfit_calendar WHERE outfit_id = ? AND date_worn = ?';
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

        $sth->bind_param('is', $outfitId, $wornDate);

        if (!$sth->execute()) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'result' => false,
                'message' => $sth->error
            ]);
            exit();
        }

        if ($sth->affected_rows === 0) {
            $sth->close();
            $con->rollback();
            http_response_code(404);
            echo json_encode([
                'result' => false,
                'message' => 'El outfit no está registrado en esta fecha'
            ]);
            exit();
        }
        $sth->close();

        $decrementWornQuery = 'UPDATE garment
                              JOIN outfit_garment ON garment.id = outfit_garment.garment_id
                              SET garment.worn = garment.worn - 1
                              WHERE outfit_garment.outfit_id = ? AND garment.worn > 0';
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
            'message' => 'Registro de uso eliminado satisfactoriamente',
            'data' => [
                'outfitId' => $outfitId,
                'wornDate' => $wornDate
            ]
        ]);
    }
}

$con->close();
?>
