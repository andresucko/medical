<?php
// Archivo de conexión a la base de datos MySQL - Versión segura

// Iniciar sesión para manejar sesiones de forma segura
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Configuración de seguridad básica
ini_set('display_errors', 0);
error_reporting(E_ERROR | E_PARSE);

// Headers de seguridad
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Función para obtener configuración de forma segura
function getDatabaseConfig() {
    // En producción, usar variables de entorno
    $config = [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'dbname' => getenv('DB_NAME') ?: 'u279456972_saas_medic',
        'username' => getenv('DB_USER') ?: 'u279456972_saas_admin',
        'password' => getenv('DB_PASS') ?: 'P&AD3signs'
    ];

    return $config;
}

// Función para crear conexión segura a la base de datos
function createSecureConnection() {
    $config = getDatabaseConfig();

    // Crear conexión con manejo de errores mejorado
    $conn = new mysqli($config['host'], $config['username'], $config['password'], $config['dbname']);

    // Verificar conexión
    if ($conn->connect_error) {
        error_log("Database connection error: " . $conn->connect_error);
        http_response_code(500);
        die("Error interno del servidor");
    }

    // Establecer charset para evitar problemas con caracteres especiales
    if (!$conn->set_charset("utf8mb4")) {
        error_log("Error setting charset: " . $conn->error);
    }

    return $conn;
}

// Función para sanitizar entrada de usuario
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Función para validar email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Función para validar y sanitizar strings
function validateString($string, $maxLength = 255) {
    $string = sanitizeInput($string);
    if (strlen($string) > $maxLength) {
        return substr($string, 0, $maxLength);
    }
    return $string;
}

// Crear conexión global (solo si no existe)
if (!isset($conn)) {
    $conn = createSecureConnection();
}

// Función para verificar si el usuario está autenticado
function isAuthenticated() {
    return isset($_SESSION['user_id']) && isset($_SESSION['last_activity']) &&
           (time() - $_SESSION['last_activity'] < 1800); // 30 minutos de inactividad
}

// Función para actualizar la actividad del usuario
function updateUserActivity() {
    $_SESSION['last_activity'] = time();
}

// Función para requerir autenticación
function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: login.html');
        exit();
    }
    updateUserActivity();
}

// Función para obtener información del usuario actual
function getCurrentUser() {
    if (!isAuthenticated()) {
        return null;
    }

    static $user = null;
    if ($user === null) {
        global $conn;
        $stmt = $conn->prepare("SELECT id, name as username, email, specialization as especialidad
                               FROM u279456972_saas_medic_doctors
                               WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
    }

    return $user;
}

// Función para logout seguro
function secureLogout() {
    // Limpiar todas las variables de sesión
    $_SESSION = array();

    // Destruir la cookie de sesión
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Destruir la sesión
    session_destroy();

    // Redirigir al login
    header('Location: login.html');
    exit();
}

// Función para verificar permisos de rol
function hasRole($requiredRole) {
    if (!isAuthenticated()) {
        return false;
    }

    $user = getCurrentUser();
    return $user && $user['role'] === $requiredRole;
}

// Función para verificar si es doctor
function isDoctor() {
    return hasRole('doctor');
}

// Función para verificar si es admin
function isAdmin() {
    return hasRole('admin');
}
?>