<?php
// Script para manejar el cierre de sesión seguro

require_once 'session_manager.php';

// Iniciar sesión para poder destruirla
secureSessionStart();

// Registrar logout en los logs
if (isset($_SESSION['user_id'])) {
    error_log("User {$_SESSION['username']} logged out at " . date('Y-m-d H:i:s'));
}

// Cerrar sesión de forma segura
secureLogout();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cerrando Sesión - Panel Médico</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <meta name="robots" content="noindex, nofollow">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md text-center">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Sesión Cerrada</h1>
        <p class="text-gray-600 mb-4">Has cerrado sesión exitosamente. Redirigiendo...</p>
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>

    <script>
        // Redirigir después de 2 segundos
        setTimeout(function() {
            window.location.href = 'login.html';
        }, 2000);
    </script>
</body>
</html>