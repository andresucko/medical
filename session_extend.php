<?php
// Script para extender la sesión

require_once 'session_manager.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (secureSessionStart() && isset($input['csrf_token']) && $input['csrf_token'] === $_SESSION['csrf_token']) {
        // Actualizar actividad de sesión
        $_SESSION['last_activity'] = time();
        $response['success'] = true;
        $response['message'] = 'Sesión extendida exitosamente';
    } else {
        $response['message'] = 'Token de seguridad inválido';
        http_response_code(403);
    }
} else {
    $response['message'] = 'Método no permitido';
    http_response_code(405);
}

echo json_encode($response);
?>