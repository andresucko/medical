<?php
// API para obtener información del usuario actual

require_once 'session_manager.php';
require_once 'db_connect.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $user = getCurrentUser();

    if ($user) {
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'nombre' => $user['nombre'] ?? '',
                'apellido' => $user['apellido'] ?? '',
                'especialidad' => $user['especialidad'] ?? '',
                'email' => $user['email'] ?? ''
            ]
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Usuario no encontrado']);
    }

} catch (Exception $e) {
    error_log("API User error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}

if (isset($conn)) {
    $conn->close();
}
?>