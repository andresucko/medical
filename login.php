<?php
// Script para manejar el inicio de sesión - Versión segura

// Incluir configuración de seguridad
require_once 'db_connect.php';
require_once 'session_manager.php';

// Función para verificar rate limiting
function checkRateLimit($username) {
    $file = 'logs/login_attempts.log';
    $maxAttempts = 5;
    $timeWindow = 15 * 60; // 15 minutos

    if (!file_exists('logs')) {
        mkdir('logs', 0755, true);
    }

    $attempts = [];
    if (file_exists($file)) {
        $attempts = json_decode(file_get_contents($file), true) ?: [];
    }

    $now = time();
    $userAttempts = array_filter($attempts, function($attempt) use ($username, $now, $timeWindow) {
        return $attempt['username'] === $username && ($now - $attempt['time']) < $timeWindow;
    });

    return count($userAttempts) >= $maxAttempts;
}

// Función para registrar intento de login
function logLoginAttempt($username, $success) {
    $file = 'logs/login_attempts.log';
    $attempt = [
        'username' => $username,
        'time' => time(),
        'success' => $success,
        'ip' => $_SERVER['REMOTE_ADDR']
    ];

    if (!file_exists('logs')) {
        mkdir('logs', 0755, true);
    }

    $attempts = [];
    if (file_exists($file)) {
        $attempts = json_decode(file_get_contents($file), true) ?: [];
    }

    $attempts[] = $attempt;

    // Mantener solo los últimos 1000 intentos
    if (count($attempts) > 1000) {
        $attempts = array_slice($attempts, -1000);
    }

    file_put_contents($file, json_encode($attempts));
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token de seguridad inválido";
        logLoginAttempt($_POST['username'] ?? 'unknown', false);
    } else {
        $username = validateString($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validaciones básicas
        if (empty($username) || empty($password)) {
            $error = "Todos los campos son obligatorios";
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $error = "El nombre de usuario debe tener entre 3 y 50 caracteres";
        } elseif (strlen($password) < 6) {
            $error = "La contraseña debe tener al menos 6 caracteres";
        } elseif (checkRateLimit($username)) {
            $error = "Demasiados intentos fallidos. Intente nuevamente en 15 minutos";
        } else {
            try {
                // Buscar usuario en la base de datos con prepared statement
                $stmt = $conn->prepare("SELECT id, password, email, specialization FROM u279456972_saas_medic_doctors WHERE name = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password, $user['password'])) {
                        // Inicio de sesión exitoso
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $username;
                        $_SESSION['role'] = 'doctor'; // Set default role since it doesn't exist in the table
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['specialization'] = $user['specialization'];
                        $_SESSION['last_activity'] = time();
                        $_SESSION['login_time'] = time();

                        // Regenerar ID de sesión por seguridad
                        session_regenerate_id(true);

                        logLoginAttempt($username, true);
                        $success = true;

                        // Redirigir después de un breve delay para mostrar mensaje de éxito
                        header("Refresh: 2; URL=index.html");
                    } else {
                        $error = "Contraseña incorrecta";
                        logLoginAttempt($username, false);
                    }
                } else {
                    $error = "Usuario no encontrado";
                    logLoginAttempt($username, false);
                }
                $stmt->close();
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $error = "Error interno del servidor";
            }
        }
    }
}

// Generar token CSRF si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($conn)) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $success ? 'Inicio de Sesión Exitoso' : 'Error de Inicio de Sesión'; ?> - Panel Médico</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <meta name="robots" content="noindex, nofollow">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <?php if ($success): ?>
            <div class="text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">¡Bienvenido!</h1>
                <p class="text-gray-600 mb-4">Inicio de sesión exitoso. Redirigiendo...</p>
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
            </div>
        <?php else: ?>
            <div class="text-center">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Error de Inicio de Sesión</h1>
                <p class="text-red-600 mb-4"><?php echo htmlspecialchars($error); ?></p>
                <a href="login.html" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                    Volver al inicio de sesión
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>