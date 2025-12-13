<?php

$origins = [
    'http://localhost:4200'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Content-Type: application/json, charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}
?>
