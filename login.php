<?php
session_start();
include 'db_config.php';
include 'cors-config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $emailForm = $data['email'] ?? $_REQUEST['email'] ?? null;
    $passwordForm = $data['password'] ?? $_REQUEST['password'] ?? null;

    if ($emailForm && $passwordForm) {
        $loginQuery = 'SELECT id, email, password, name FROM users WHERE email = ?';
        $loginSth = $con->prepare($loginQuery);
        $loginSth->bind_param('s', $emailForm);
        $loginSth->execute();
        $loginResult = $loginSth->get_result();
        $data = $loginResult->fetch_assoc();

        if ($data && password_verify($passwordForm, $data['password'])) {
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $data['id'];
            $_SESSION['email'] = $data['email'];
            $_SESSION['name'] = $data['name'];

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Inicio satisfactorio',
                'data' => [
                    'email' => $data['email'],
                    'name' => $data['name']
                ]
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Email o contraseña no válidos'
            ]);
        }
        $loginSth->close();
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Email y contraseña requeridos'
        ]);
    }
}
?>
