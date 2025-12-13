<?php
session_start();
include 'db_config.php';
include 'cors-config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $nameForm = $data['name'] ?? null;
    $emailForm = $data['email'] ?? null;
    $passwordForm = $data['password'] ?? null;

    if (!$nameForm || !$emailForm || !$passwordForm) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Datos de formulario incompletos'
        ]);
        exit();
    }

    if (!filter_var($emailForm, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Formato de email inválido'
        ]);
        exit();
    }

    if (strlen($nameForm) > 30) {
        http_response_code(400);
        echo json_encode([
            'error' => 'El nombre no puede tener más de 30 carácteres'
        ]);
        exit();
    }

    if (strlen($passwordForm) < 6) {
        http_response_code(400);
        echo json_encode([
            'error' => 'La contraseña debe tener 6 carácteres mínimo'
        ]);
        exit();
    }

    if (strlen($passwordForm) > 16) {
        http_response_code(400);
        echo json_encode([
            'error' => 'La contraseña no puede tener más de 16 carácteres'
        ]);
        exit();
    }

    $checkQuery = 'SELECT id FROM users WHERE email = ?';
    $sth = $con->prepare($checkQuery);

    if (!$sth) {
        http_response_code(500);
        echo json_encode([
            'error' => $con->error
        ]);
        exit();
    }

    $sth->bind_param('s', $emailForm);
    $sth->execute();
    $result = $sth->get_result();

    if ($result->num_rows > 0) {
        $sth->close();
        http_response_code(409);
        echo json_encode([
            'error' => 'Email ya registrado'
        ]);
        exit();
    }
    $sth->close();

    $hashedPassword = password_hash($passwordForm, PASSWORD_BCRYPT);
    $insertQuery = 'INSERT INTO users (email, password, name) VALUES (?, ?, ?)';
    $sth = $con->prepare($insertQuery);

    if (!$sth) {
        http_response_code(500);
        echo json_encode([
            'error' => $con->error
        ]);
        exit();
    }

    $sth->bind_param('sss', $emailForm, $hashedPassword, $nameForm);

    if ($sth->execute()) {
        $userId = $sth->insert_id;
        $sth->close();

        $_SESSION['loggedin'] = true;
        $_SESSION['user_id'] = $userId;
        $_SESSION['email'] = $emailForm;
        $_SESSION['name'] = $nameForm;

        http_response_code(200);
        echo json_encode([
            'user_id' => (string)$userId,
            'email' => $emailForm,
            'name' => $nameForm
        ]);
    } else {
        $sth->close();
        http_response_code(500);
        echo json_encode([
            'error' => $sth->error
        ]);
    }
}
?>
