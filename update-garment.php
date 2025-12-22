<?php
include 'db_config.php';
session_start();
include 'cors-config.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true && isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $userId = $_SESSION['user_id'];
        $garmentId = $_GET['id'] ?? null;

        if (!$garmentId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID de prenda requerido'
            ]);
            exit();
        }

        $checkExistingGarmentQuery = 'SELECT id, picture FROM garment WHERE id = ? AND user_id = ?';
        $checkExistingGarmentSth = $con->prepare($checkExistingGarmentQuery);

        if (!$checkExistingGarmentSth) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $checkExistingGarmentSth->bind_param('ii', $garmentId, $userId);
        $checkExistingGarmentSth->execute();
        $existingGarmentResult = $checkExistingGarmentSth->get_result();

        if ($existingGarmentResult->num_rows === 0) {
            $checkExistingGarmentSth->close();
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Prenda no encontrada'
            ]);
            exit();
        }

        $existingGarment = $existingGarmentResult->fetch_assoc();
        $oldPicture = $existingGarment['picture'];
        $checkExistingGarmentSth->close();

        $params = $_POST;

        $updateQuery = '';
        $queryParams = [];
        $variableTypes = '';

        if (isset($params['type'])) {
            $updateQuery .= (($updateQuery != '') ? (", type = ?") : ("UPDATE garment SET type = ?"));
            $queryParams[] = $params['type'];
            $variableTypes .= 's';
        }

        if (isset($params['supType'])) {
            $updateQuery .= (($updateQuery != '') ? (", sup_type = ?") : ("UPDATE garment SET sup_type = ?"));
            $queryParams[] = $params['supType'];
            $variableTypes .= 's';
        }

        if (isset($params['fabricType'])) {
            $fabricTypes = $params['fabricType'];
            $updateQuery .= (($updateQuery != '') ? (", fabric_type = ?") : ("UPDATE garment SET fabric_type = ?"));
            $queryParams[] = $fabricTypes;
            $variableTypes .= 's';
        }

        if (isset($params['mainColor'])) {
            $updateQuery .= (($updateQuery != '') ? (", main_color = ?") : ("UPDATE garment SET main_color = ?"));
            $queryParams[] = $params['mainColor'];
            $variableTypes .= 's';
        }

        if (isset($params['sleeve'])) {
            $updateQuery .= (($updateQuery != '') ? (", sleeve = ?") : ("UPDATE garment SET sleeve = ?"));
            $queryParams[] = $params['sleeve'];
            $variableTypes .= 's';
        }

        if (isset($params['seasons'])) {
            $seasons = $params['seasons'];
            $updateQuery .= (($updateQuery != '') ? (", seasons = ?") : ("UPDATE garment SET seasons = ?"));
            $queryParams[] = $seasons;
            $variableTypes .= 's';
        }

        if (isset($params['pattern'])) {
            $updateQuery .= (($updateQuery != '') ? (", pattern = ?") : ("UPDATE garment SET pattern = ?"));
            $queryParams[] = $params['pattern'] === 'true' ? 1 : 0;
            $variableTypes .= 'i';
        }

        if (isset($params['isSecondHand'])) {
            $updateQuery .= (($updateQuery != '') ? (", is_second_hand = ?") : ("UPDATE garment SET is_second_hand = ?"));
            $queryParams[] = $params['isSecondHand'] === 'true' ? 1 : 0;
            $variableTypes .= 'i';
        }

        $newPictureUrl = null;
        if (isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['picture'];
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            $maxFileSize = 3145728;

            if ($file['size'] > $maxFileSize || !in_array($file['type'], $allowedTypes)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Tipo de imagen no soportada o demasiado grande (máximo 3MB)'
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

            $newPictureUrl = 'garments_images/' . $fileName;
            $updateQuery .= (($updateQuery != '') ? (", picture = ?") : ("UPDATE garment SET picture = ?"));
            $queryParams[] = $newPictureUrl;
            $variableTypes .= 's';
        }

        if ($updateQuery == '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No hay campos para actualizar'
            ]);
            exit();
        }

        $queryParams[] = $garmentId;
        $queryParams[] = $userId;
        $variableTypes .= 'ii';

        $updateQuery .= " WHERE id = ? AND user_id = ?";

        $updateSth = $con->prepare($updateQuery);

        if (!$updateSth) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $updateSth->bind_param($variableTypes, ...$queryParams);

        if ($updateSth->execute()) {
            if ($newPictureUrl && $oldPicture) {
                $oldPicturePath = __DIR__ . '/' . $oldPicture;
                if (file_exists($oldPicturePath)) {
                    unlink($oldPicturePath);
                }
            }

            $updateGarmentQuery = 'SELECT id, type, sup_type, fabric_type, main_color, sleeve, seasons, picture, pattern, is_second_hand FROM garment WHERE id = ?';
            $updateGarmentSth = $con->prepare($updateGarmentQuery);
            $updateGarmentSth->bind_param('i', $garmentId);
            $updateGarmentSth->execute();
            $updateGarmentResult = $updateGarmentSth->get_result();
            $garment = $updateGarmentResult->fetch_assoc();
            $updateGarmentSth->close();

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Prenda actualizada satisfactoriamente',
                'data' => [
                    'id' => (int)$garment['id'],
                    'type' => $garment['type'],
                    'supType' => $garment['sup_type'],
                    'fabricType' => json_decode($garment['fabric_type'], true),
                    'mainColor' => $garment['main_color'],
                    'sleeve' => $garment['sleeve'],
                    'seasons' => json_decode($garment['seasons'], true),
                    'picture' => $garment['picture'],
                    'pattern' => (bool)$garment['pattern'],
                    'isSecondHand' => (bool)$garment['is_second_hand']
                ]
            ]);
        } else {
            if ($newPictureUrl) {
                $newPicturePath = __DIR__ . '/' . $newPictureUrl;
                if (file_exists($newPicturePath)) {
                    unlink($newPicturePath);
                }
            }
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $updateSth->error
            ]);
        }

        $updateSth->close();
    }
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no loggeado'
    ]);
}
?>
