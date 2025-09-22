<?php
// Gestor de sesiones seguro

// Configuración de seguridad de sesiones
ini_set('session.cookie_httponly', 1);
// Only enable secure cookies if HTTPS is being used
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
} else {
    ini_set('session.cookie_secure', 0); // Allow HTTP for development
}
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_lifetime', 0);
ini_set('session.gc_maxlifetime', 1800); // 30 minutos

// Función para iniciar sesión de forma segura
function secureSessionStart() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();

        // Verificar si la sesión es válida
        if (isset($_SESSION['user_id']) && isset($_SESSION['user_agent'])) {
            if ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
                // Posible session hijacking
                secureLogout();
                return false;
            }
        }

        // Establecer user agent si no existe
        if (!isset($_SESSION['user_agent']) && isset($_SESSION['user_id'])) {
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        }
    }
    return true;
}

// Función para verificar timeout de sesión
function checkSessionTimeout() {
    $timeout = 1800; // 30 minutos

    if (isset($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity'] > $timeout)) {
        secureLogout();
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}

// Función para renovar ID de sesión periódicamente
function renewSessionId() {
    if (session_status() == PHP_SESSION_ACTIVE) {
        // Renovar ID cada 5 minutos para prevenir fixation
        if (!isset($_SESSION['last_session_renewal'])) {
            $_SESSION['last_session_renewal'] = time();
        }

        if (time() - $_SESSION['last_session_renewal'] > 300) { // 5 minutos
            session_regenerate_id(true);
            $_SESSION['last_session_renewal'] = time();
        }
    }
}

// Función para validar IP (opcional, puede ser muy restrictivo)
function validateSessionIP() {
    if (isset($_SESSION['user_id']) && isset($_SESSION['ip_address'])) {
        if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
            // IP cambió, podría ser sospechoso
            error_log("Session IP mismatch for user {$_SESSION['user_id']}");
            // Opcional: cerrar sesión automáticamente
            // secureLogout();
        }
    } else {
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
    }
}

// Middleware de autenticación para páginas protegidas
function requireSecureAuth() {
    secureSessionStart();

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.html');
        exit();
    }

    if (!checkSessionTimeout()) {
        header('Location: login.html?timeout=1');
        exit();
    }

    renewSessionId();
    validateSessionIP();
}

// Función para obtener información de sesión
function getSessionInfo() {
    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'last_activity' => $_SESSION['last_activity'] ?? null,
        'login_time' => $_SESSION['login_time'] ?? null,
        'session_age' => isset($_SESSION['login_time']) ? time() - $_SESSION['login_time'] : null
    ];
}
?>