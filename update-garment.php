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
                'result' => false,
                'message' => 'ID de prenda requerido'
            ]);
            exit();
        }

        $checkQuery = 'SELECT id, picture FROM garment WHERE id = ? AND user_id = ?';
        $sth = $con->prepare($checkQuery);

        if (!$sth) {
            http_response_code(500);
            echo json_encode([
                'result' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $sth->bind_param('ii', $garmentId, $userId);
        $sth->execute();
        $result = $sth->get_result();

        if ($result->num_rows === 0) {
            $sth->close();
            http_response_code(404);
            echo json_encode([
                'result' => false,
                'message' => 'Prenda no encontrada'
            ]);
            exit();
        }

        $existingGarment = $result->fetch_assoc();
        $oldPicture = $existingGarment['picture'];
        $sth->close();

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
                    'result' => false,
                    'message' => 'Imagen no soportada o demasiado grande (máximo 3MB)'
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
                    'result' => false,
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
                'result' => false,
                'message' => 'No hay campos para actualizar'
            ]);
            exit();
        }

        $queryParams[] = $garmentId;
        $queryParams[] = $userId;
        $variableTypes .= 'ii';

        $updateQuery .= " WHERE id = ? AND user_id = ?";

        $sth = $con->prepare($updateQuery);

        if (!$sth) {
            http_response_code(500);
            echo json_encode([
                'result' => false,
                'message' => $con->error
            ]);
            exit();
        }

        $sth->bind_param($variableTypes, ...$queryParams);

        if ($sth->execute()) {
            if ($newPictureUrl && $oldPicture) {
                $oldPicturePath = __DIR__ . '/' . $oldPicture;
                if (file_exists($oldPicturePath)) {
                    unlink($oldPicturePath);
                }
            }

            $selectQuery = 'SELECT id, type, sup_type, fabric_type, main_color, sleeve, seasons, picture, pattern, is_second_hand FROM garment WHERE id = ?';
            $selectSth = $con->prepare($selectQuery);
            $selectSth->bind_param('i', $garmentId);
            $selectSth->execute();
            $result = $selectSth->get_result();
            $garment = $result->fetch_assoc();
            $selectSth->close();

            http_response_code(200);
            echo json_encode([
                'result' => true,
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
                'result' => false,
                'message' => $sth->error
            ]);
        }

        $sth->close();
    }
}
?>
