<?php
session_start();
include 'db_config.php';
include 'cors-config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $emailForm = $data['email'] ?? $_REQUEST['email'] ?? null;
    $passwordForm = $data['password'] ?? $_REQUEST['password'] ?? null;

    if ($emailForm && $passwordForm) {
        $query = 'SELECT id, email, password, name FROM users WHERE email = ?';
        $sth = $con->prepare($query);
        $sth->bind_param('s', $emailForm);
        $sth->execute();
        $result = $sth->get_result();
        $data = $result->fetch_assoc();

        if ($data && password_verify($passwordForm, $data['password'])) {
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $data['id'];
            $_SESSION['email'] = $data['email'];
            $_SESSION['name'] = $data['name'];

            http_response_code(200);
            echo json_encode([
                'user_id' => (string)$data['id'],
                'email' => $data['email'],
                'name' => $data['name']
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'error' => 'Email o contraseña no válidos'
            ]);
        }
        $sth->close();
    } else {
        http_response_code(400);
        echo json_encode([
            'error' => 'Email y contraseña requeridos'
        ]);
    }
}
?>
