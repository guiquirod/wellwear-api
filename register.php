<?php
session_start();
include 'db_config.php';
include 'cors-config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $nameForm = $data['name'] ?? null;
    $emailForm = $data['email'] ?? null;
    $passwordForm = $data['password'] ?? null;

    if (!$nameForm || !$emailForm || !$passwordForm) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Datos de formulario incompletos'
        ]);
        exit();
    }

    if (!filter_var($emailForm, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Formato de email inválido'
        ]);
        exit();
    }

    if (strlen($nameForm) > 30) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El nombre no puede tener más de 30 carácteres'
        ]);
        exit();
    }

    if (strlen($passwordForm) < 6) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'La contraseña debe tener 6 carácteres mínimo'
        ]);
        exit();
    }

    if (strlen($passwordForm) > 16) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'La contraseña no puede tener más de 16 carácteres'
        ]);
        exit();
    }

    $checkEmailQuery = 'SELECT id FROM users WHERE email = ?';
    $checkEmailSth = $con->prepare($checkEmailQuery);

    if (!$checkEmailSth) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $con->error
        ]);
        exit();
    }

    $checkEmailSth->bind_param('s', $emailForm);
    $checkEmailSth->execute();
    $checkEmailResult = $checkEmailSth->get_result();

    if ($checkEmailResult->num_rows > 0) {
        $checkEmailSth->close();
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Email ya registrado'
        ]);
        exit();
    }
    $checkEmailSth->close();

    $hashedPassword = password_hash($passwordForm, PASSWORD_BCRYPT);
    $createUserQuery = 'INSERT INTO users (email, password, name) VALUES (?, ?, ?)';
    $createUserSth = $con->prepare($createUserQuery);

    if (!$createUserSth) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $con->error
        ]);
        exit();
    }

    $createUserSth->bind_param('sss', $emailForm, $hashedPassword, $nameForm);

    if ($createUserSth->execute()) {
        $userId = $createUserSth->insert_id;
        $createUserSth->close();

        $_SESSION['loggedin'] = true;
        $_SESSION['user_id'] = $userId;
        $_SESSION['email'] = $emailForm;
        $_SESSION['name'] = $nameForm;

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Registro exitoso',
            'data' => [
                'email' => $emailForm,
                'name' => $nameForm
            ]
        ]);
    } else {
        $createUserSth->close();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $createUserSth->error
        ]);
    }
}
?>
