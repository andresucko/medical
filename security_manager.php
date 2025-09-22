<?php
// Sistema avanzado de gestión de seguridad

class SecurityManager {
    private static $instance = null;
    private $csrfTokens = [];

    private function __construct() {
        $this->initializeSecurity();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeSecurity() {
        // Configurar headers de seguridad
        $this->setSecurityHeaders();

        // Inicializar tokens CSRF
        $this->initializeCSRF();

        // Configurar políticas de contenido
        $this->setContentSecurityPolicy();
    }

    // Configurar headers de seguridad
    public function setSecurityHeaders() {
        $headers = [
            // Prevenir MIME type sniffing
            'X-Content-Type-Options' => 'nosniff',

            // Habilitar XSS filtering
            'X-XSS-Protection' => '1; mode=block',

            // Prevenir clickjacking
            'X-Frame-Options' => 'DENY',

            // Política de referrer
            'Referrer-Policy' => 'strict-origin-when-cross-origin',

            // Prevenir ataques de timing
            'X-DNS-Prefetch-Control' => 'off',

            // Deshabilitar algunos features para seguridad
            'Feature-Policy' => 'camera=(), microphone=(), geolocation=()',

            // Headers adicionales de seguridad
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'Cross-Origin-Embedder-Policy' => 'require-corp',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin'
        ];

        foreach ($headers as $header => $value) {
            if (!headers_sent()) {
                header("{$header}: {$value}");
            }
        }
    }

    // Inicializar sistema CSRF
    private function initializeCSRF() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Generar token CSRF si no existe
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = $this->generateCSRFToken();
        }

        // Generar token CSRF por formulario si es necesario
        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }
    }

    // Generar token CSRF
    public function generateCSRFToken($formName = 'default') {
        $token = bin2hex(random_bytes(32));
        $timestamp = time();

        $_SESSION['csrf_tokens'][$formName] = [
            'token' => $token,
            'timestamp' => $timestamp,
            'expires' => $timestamp + 3600 // 1 hora
        ];

        return $token;
    }

    // Validar token CSRF
    public function validateCSRFToken($token, $formName = 'default') {
        // Verificar si el token existe
        if (!isset($_SESSION['csrf_tokens'][$formName])) {
            log_security('CSRF token not found', [
                'form_name' => $formName,
                'severity' => 'high'
            ]);
            return false;
        }

        $storedToken = $_SESSION['csrf_tokens'][$formName];

        // Verificar expiración
        if (time() > $storedToken['expires']) {
            log_security('CSRF token expired', [
                'form_name' => $formName,
                'expires' => $storedToken['expires'],
                'severity' => 'medium'
            ]);
            return false;
        }

        // Verificar token
        if (!hash_equals($storedToken['token'], $token)) {
            log_security('CSRF token mismatch', [
                'form_name' => $formName,
                'severity' => 'high'
            ]);
            return false;
        }

        // Token válido, eliminarlo para prevenir reutilización
        unset($_SESSION['csrf_tokens'][$formName]);

        return true;
    }

    // Obtener token CSRF para formularios
    public function getCSRFToken($formName = 'default') {
        if (!isset($_SESSION['csrf_tokens'][$formName])) {
            $this->generateCSRFToken($formName);
        }

        return $_SESSION['csrf_tokens'][$formName]['token'];
    }

    // Configurar Content Security Policy
    public function setContentSecurityPolicy() {
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://fonts.googleapis.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: https:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
            "upgrade-insecure-requests"
        ];

        $cspHeader = 'Content-Security-Policy: ' . implode('; ', $csp);

        if (!headers_sent()) {
            header($cspHeader);
        }
    }

    // Sanitizar entrada de usuario
    public function sanitizeInput($data, $type = 'string') {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }

        $sanitized = $data;

        switch ($type) {
            case 'string':
                $sanitized = filter_var($sanitized, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
                break;
            case 'email':
                $sanitized = filter_var($sanitized, FILTER_SANITIZE_EMAIL);
                break;
            case 'url':
                $sanitized = filter_var($sanitized, FILTER_SANITIZE_URL);
                break;
            case 'int':
                $sanitized = filter_var($sanitized, FILTER_SANITIZE_NUMBER_INT);
                break;
            case 'float':
                $sanitized = filter_var($sanitized, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                break;
            case 'html':
                $sanitized = $this->sanitizeHTML($sanitized);
                break;
        }

        return $sanitized;
    }

    // Sanitizar HTML
    private function sanitizeHTML($html) {
        // Usar DOMPurify o similar si está disponible
        // Por simplicidad, usar strip_tags con excepciones
        $allowedTags = '<p><br><strong><em><u><ol><ul><li><h1><h2><h3><h4><h5><h6><blockquote><code><pre>';
        return strip_tags($html, $allowedTags);
    }

    // Validar y sanitizar datos de formulario
    public function sanitizeFormData($data, $rules = []) {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $rule = $rules[$key] ?? 'string';
            $sanitized[$key] = $this->sanitizeInput($value, $rule);
        }

        return $sanitized;
    }

    // Detectar ataques de inyección SQL
    public function detectSQLInjection($input) {
        $patterns = [
            '/(\b(union|select|insert|update|delete|drop|create|alter|exec|execute)\b)/i',
            '/(\b(script|javascript|vbscript|onload|onerror|onclick|onmouseover)\b)/i',
            '/(--|#|\/\*|\*\/)/',
            '/(\bor\b\s+\d+\s*=\s*\d+)/i',
            '/(\band\b\s+\d+\s*=\s*\d+)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                log_security('SQL injection attempt detected', [
                    'input' => $input,
                    'pattern' => $pattern,
                    'severity' => 'high'
                ]);
                return true;
            }
        }

        return false;
    }

    // Detectar ataques XSS
    public function detectXSS($input) {
        $patterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe\b[^>]*>/i',
            '/<object\b[^>]*>/i',
            '/<embed\b[^>]*>/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                log_security('XSS attempt detected', [
                    'input' => $input,
                    'pattern' => $pattern,
                    'severity' => 'high'
                ]);
                return true;
            }
        }

        return false;
    }

    // Validar origen de la petición
    public function validateRequestOrigin() {
        $allowedOrigins = ['https://yourdomain.com', 'https://www.yourdomain.com'];

        if (isset($_SERVER['HTTP_ORIGIN']) && !in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
            log_security('Invalid request origin', [
                'origin' => $_SERVER['HTTP_ORIGIN'],
                'severity' => 'medium'
            ]);
            return false;
        }

        return true;
    }

    // Rate limiting
    public function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 300) {
        $key = 'rate_limit_' . $identifier;

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'attempts' => 0,
                'first_attempt' => time()
            ];
        }

        $rateLimit = $_SESSION[$key];

        // Resetear contador si ha pasado el tiempo
        if (time() - $rateLimit['first_attempt'] > $timeWindow) {
            $rateLimit['attempts'] = 0;
            $rateLimit['first_attempt'] = time();
        }

        $rateLimit['attempts']++;

        if ($rateLimit['attempts'] > $maxAttempts) {
            log_security('Rate limit exceeded', [
                'identifier' => $identifier,
                'attempts' => $rateLimit['attempts'],
                'severity' => 'medium'
            ]);
            return false;
        }

        $_SESSION[$key] = $rateLimit;
        return true;
    }

    // Encriptar datos sensibles
    public function encryptSensitiveData($data) {
        $key = $_ENV['ENCRYPTION_KEY'] ?? 'your-secret-key-change-in-production';
        $iv = openssl_random_pseudo_bytes(16);

        $encrypted = openssl_encrypt(
            json_encode($data),
            'AES-256-CBC',
            $key,
            0,
            $iv
        );

        return base64_encode($iv . $encrypted);
    }

    // Desencriptar datos sensibles
    public function decryptSensitiveData($encryptedData) {
        $key = $_ENV['ENCRYPTION_KEY'] ?? 'your-secret-key-change-in-production';
        $data = base64_decode($encryptedData);

        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        $decrypted = openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $key,
            0,
            $iv
        );

        return json_decode($decrypted, true);
    }

    // Generar hash seguro para contraseñas
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    // Verificar contraseña
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    // Generar código de verificación
    public function generateVerificationCode($length = 6) {
        return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    // Sanitizar nombre de archivo
    public function sanitizeFilename($filename) {
        // Remover caracteres peligrosos
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);

        // Limitar longitud
        $filename = substr($filename, 0, 255);

        // Prevenir directory traversal
        $filename = str_replace(['../', '..\\'], '', $filename);

        return $filename;
    }

    // Validar tipo de archivo
    public function validateFileType($filename, $allowedTypes) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedTypes)) {
            return false;
        }

        // Verificar MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filename);
        finfo_close($finfo);

        $allowedMimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];

        return in_array($mimeType, array_values($allowedMimeTypes));
    }

    // Configurar HTTPS
    public function enforceHTTPS() {
        if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
            $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header('HTTP/1.1 301 Moved Permanently');
            header('Location: ' . $redirect);
            exit();
        }
    }

    // Generar headers de seguridad para API
    public function setAPIAuthHeaders() {
        header('Access-Control-Allow-Origin: https://yourdomain.com');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
        header('Access-Control-Allow-Credentials: true');
    }
}

// Funciones helper para uso rápido
function generate_csrf_token($formName = 'default') {
    return SecurityManager::getInstance()->getCSRFToken($formName);
}

function validate_csrf_token($token, $formName = 'default') {
    return SecurityManager::getInstance()->validateCSRFToken($token, $formName);
}

function sanitize_input($data, $type = 'string') {
    return SecurityManager::getInstance()->sanitizeInput($data, $type);
}

function sanitize_form_data($data, $rules = []) {
    return SecurityManager::getInstance()->sanitizeFormData($data, $rules);
}

function detect_sql_injection($input) {
    return SecurityManager::getInstance()->detectSQLInjection($input);
}

function detect_xss($input) {
    return SecurityManager::getInstance()->detectXSS($input);
}

function hash_password($password) {
    return SecurityManager::getInstance()->hashPassword($password);
}

function verify_password($password, $hash) {
    return SecurityManager::getInstance()->verifyPassword($password, $hash);
}

// Inicializar el sistema de seguridad
SecurityManager::getInstance();
?>