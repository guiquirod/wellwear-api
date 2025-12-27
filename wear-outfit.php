<?php
include 'db_config.php';
session_start();
include 'cors-config.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true && isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        $userId = $_SESSION['user_id'];
        $outfitId = $data['outfitId'] ?? null;
        $wornDate = $data['wornDate'] ?? $data['date'];

        if (!$outfitId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Falta el ID del outfit'
            ]);
            exit();
        }

        $checkOutfitQuery = 'SELECT id FROM outfit WHERE id = ? AND user_id = ?';
        $checkOutfitSth = $con->prepare($checkOutfitQuery);
        $checkOutfitSth->bind_param('ii', $outfitId, $userId);
        $checkOutfitSth->execute();
        $outfitResult = $checkOutfitSth->get_result();

        if ($outfitResult->num_rows === 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Outfit no encontrado'
            ]);
            exit();
        }
        $checkOutfitSth->close();

        $checkOutfitCalendarQuery = 'SELECT id FROM outfit_calendar WHERE outfit_id = ? AND date_worn = ?';
        $checkOutfitCalendarSth = $con->prepare($checkOutfitCalendarQuery);
        $checkOutfitCalendarSth->bind_param('is', $outfitId, $wornDate);
        $checkOutfitCalendarSth->execute();
        $outfitCalendarResult = $checkOutfitCalendarSth->get_result();

        if ($outfitCalendarResult->num_rows > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Este outfit ya está asignado a esta fecha'
            ]);
            exit();
        }
        $checkOutfitCalendarSth->close();

        $con->begin_transaction();

        $insertOutfitCalendarQuery = 'INSERT INTO outfit_calendar (outfit_id, date_worn) VALUES (?, ?)';
        $insertOutfitCalendarSth = $con->prepare($insertOutfitCalendarQuery);

        if (!$insertOutfitCalendarSth) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $insertOutfitCalendarSth->bind_param('is', $outfitId, $wornDate);

        if (!$insertOutfitCalendarSth->execute()) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al registrar uso: ' . $insertOutfitCalendarSth->error
            ]);
            exit();
        }

        $calendarId = $con->insert_id;
        $insertOutfitCalendarSth->close();

        $updateUsageQuery = 'UPDATE garment
                            JOIN outfit_garment ON garment.id = outfit_garment.garment_id
                            SET garment.worn = garment.worn + 1
                            WHERE outfit_garment.outfit_id = ?';
        $updateUsageSth = $con->prepare($updateUsageQuery);

        if (!$updateUsageSth) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $updateUsageSth->bind_param('i', $outfitId);

        if (!$updateUsageSth->execute()) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $updateUsageSth->error
            ]);
            exit();
        }

        $updateUsageSth->close();

        $con->commit();
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Uso de outfit registrado satisfactoriamente',
            'data' => [
                'id' => $calendarId,
                'outfitId' => $outfitId,
                'wornDate' => $wornDate
            ]
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);

        $userId = $_SESSION['user_id'];
        $outfitId = $data['outfitId'] ?? null;
        $wornDate = $data['wornDate'] ?? $data['date'];

        if (!$outfitId || !$wornDate) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Faltan el ID del outfit o la fecha'
            ]);
            exit();
        }

        $checkOutfitQuery = 'SELECT id FROM outfit WHERE id = ? AND user_id = ?';
        $checkOutfitSth = $con->prepare($checkOutfitQuery);
        $checkOutfitSth->bind_param('ii', $outfitId, $userId);
        $checkOutfitSth->execute();
        $outfitResult = $checkOutfitSth->get_result();

        if ($outfitResult->num_rows === 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Outfit no encontrado'
            ]);
            exit();
        }
        $checkOutfitSth->close();

        $con->begin_transaction();

        $deleteCalendarQuery = 'DELETE FROM outfit_calendar WHERE outfit_id = ? AND date_worn = ?';
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

        $deleteCalendarSth->bind_param('is', $outfitId, $wornDate);

        if (!$deleteCalendarSth->execute()) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $deleteCalendarSth->error
            ]);
            exit();
        }

        if ($deleteCalendarSth->affected_rows === 0) {
            $deleteCalendarSth->close();
            $con->rollback();
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'El outfit no está registrado en esta fecha'
            ]);
            exit();
        }
        $deleteCalendarSth->close();

        $decrementWornQuery = 'UPDATE garment
                              JOIN outfit_garment ON garment.id = outfit_garment.garment_id
                              SET garment.worn = garment.worn - 1
                              WHERE outfit_garment.outfit_id = ? AND garment.worn > 0';
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

        $decrementWornSth->bind_param('i', $outfitId);

        if (!$decrementWornSth->execute()) {
            $con->rollback();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $decrementWornSth->error
            ]);
            exit();
        }
        $decrementWornSth->close();

        $con->commit();
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Registro de uso eliminado satisfactoriamente',
            'data' => [
                'outfitId' => $outfitId,
                'wornDate' => $wornDate
            ]
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
