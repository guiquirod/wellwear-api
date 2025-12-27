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

        $type = $_POST['type'];
        $supType = $_POST['supType'];
        $fabricType = $_POST['fabricType'];
        $mainColor = $_POST['mainColor'];
        $sleeve = $_POST['sleeve'];
        $seasons = $_POST['seasons'];
        $pattern = $_POST['pattern'] === 'true' ? 1 : 0;
        $isSecondHand = $_POST['isSecondHand'] === 'true' ? 1 : 0;

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
        }

        $updateQuery = 'UPDATE garment SET type = ?, sup_type = ?, fabric_type = ?, main_color = ?, sleeve = ?, seasons = ?, pattern = ?, is_second_hand = ?';
        $queryParams = [$type, $supType, $fabricType, $mainColor, $sleeve, $seasons, $pattern, $isSecondHand];
        $variableTypes = 'ssssssii';

        if ($newPictureUrl) {
            $updateQuery .= ', picture = ?';
            $queryParams[] = $newPictureUrl;
            $variableTypes .= 's';
        }

        $updateQuery .= ' WHERE id = ? AND user_id = ?';
        $queryParams[] = $garmentId;
        $queryParams[] = $userId;
        $variableTypes .= 'ii';

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
        'message' => 'Usuario no logueado'
    ]);
}
?>
