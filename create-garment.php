<?php
include 'db_config.php';
session_start();
include 'cors-config.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true && isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        $userId = $_SESSION['user_id'];
        $type = $_POST['type'] ?? null;
        $supType = $_POST['supType'] ?? null;
        $fabricTypes = $_POST['fabricType'] ?? null;
        $mainColor = $_POST['mainColor'] ?? null;
        $sleeve = $_POST['sleeve'] ?? null;
        $seasons = $_POST['seasons'] ?? null;
        $pattern = ($_POST['pattern'] ?? '') === 'true';
        $isSecondHand = ($_POST['isSecondHand'] ?? '') === 'true';

        if (!$type || !$supType || !$fabricTypes || !$mainColor || !$sleeve || !$seasons) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Faltan campos requeridos'
            ]);
            exit();
        }

        if (!isset($_FILES['picture'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Se requiere una imagen de la prenda'
            ]);
            exit();
        }

        $file = $_FILES['picture'];
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $maxFileSize = 3145728;

        if ($file['size'] > $maxFileSize || !in_array($file['type'], $allowedTypes)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Imagen no soportada o demasiado grande (máximo 3MB)'
            ]);
            exit();
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al subir la imagen'
            ]);
            exit();
        }

        $garmentsFolder = __DIR__ . '/garments_images';
        $filePathParts = pathinfo($file['name']);
        $fileName = uniqid('garment_') . '.' . $filePathParts['extension'];
        $fileFullPath = $garmentsFolder . '/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $fileFullPath)) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al guardar la imagen'
            ]);
            exit();
        }

        $pictureUrl = 'garments_images/' . $fileName;

        $fabricTypesDecoded = json_decode($fabricTypes, true);
        $seasonsDecoded = json_decode($seasons, true);

        if (empty($fabricTypesDecoded)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Se requiere al menos un tipo de tejido'
            ]);
            exit();
        }

        if (empty($seasonsDecoded)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Se requiere al menos una estacion del año'
            ]);
            exit();
        }

        $insertQuery = 'INSERT INTO garment (user_id, type, sup_type, fabric_type, sleeve, seasons, picture, main_color, pattern, is_second_hand) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $insertGarmentSth = $con->prepare($insertQuery);

        if (!$insertGarmentSth) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $insertGarmentSth->bind_param('isssssssii', $userId, $type, $supType, $fabricTypes, $sleeve, $seasons, $pictureUrl, $mainColor, $pattern, $isSecondHand);

        if ($insertGarmentSth->execute()) {
            $garmentId = $con->insert_id;
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Prenda creada satisfactoriamente',
                'data' => [
                    'id' => $garmentId,
                    'type' => $type,
                    'supType' => $supType,
                    'fabricType' => $fabricTypesDecoded,
                    'mainColor' => $mainColor,
                    'sleeve' => $sleeve,
                    'seasons' => $seasonsDecoded,
                    'picture' => $pictureUrl,
                    'pattern' => $pattern,
                    'isSecondHand' => $isSecondHand
                ]
            ]);
        } else {
            unlink($fileFullPath);
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $insertGarmentSth->error
            ]);
        }

        $insertGarmentSth->close();
    }
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no loggeado'
    ]);
}
?>