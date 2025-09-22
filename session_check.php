<?php
// Script para verificar el estado de la sesión

require_once 'session_manager.php';

header('Content-Type: application/json');

$response = ['timeout' => false, 'time_remaining' => null];

if (secureSessionStart()) {
    $sessionInfo = getSessionInfo();

    if ($sessionInfo['user_id']) {
        $timeRemaining = 1800 - (time() - $sessionInfo['last_activity']);

        if ($timeRemaining <= 300) { // 5 minutos o menos
            $response['timeout'] = true;
            $response['time_remaining'] = $timeRemaining;
        } else {
            $response['time_remaining'] = $timeRemaining;
        }
    } else {
        $response['timeout'] = true;
    }
} else {
    $response['timeout'] = true;
}

echo json_encode($response);
?>