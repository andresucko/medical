<?php
// Sistema avanzado de manejo de errores y logging

// Configuración de logging
define('LOG_LEVELS', [
    'DEBUG' => 0,
    'INFO' => 1,
    'WARNING' => 2,
    'ERROR' => 3,
    'CRITICAL' => 4
]);

define('LOG_DIR', __DIR__ . '/logs');
define('MAX_LOG_FILES', 30); // Mantener máximo 30 archivos de log
define('MAX_LOG_SIZE', 10 * 1024 * 1024); // 10MB por archivo

class ErrorHandler {
    private static $instance = null;
    private $logLevel;
    private $logToFile;
    private $logToDatabase;
    private $displayErrors;

    private function __construct() {
        $this->logLevel = LOG_LEVELS[defined('LOG_LEVEL') ? LOG_LEVEL : 'ERROR'];
        $this->logToFile = true;
        $this->logToDatabase = false; // Cambiar a true si se quiere log en DB
        $this->displayErrors = defined('DEBUG_MODE') && DEBUG_MODE;

        $this->initializeLogging();
        $this->registerHandlers();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeLogging() {
        if (!file_exists(LOG_DIR)) {
            mkdir(LOG_DIR, 0755, true);
        }

        // Rotar logs si es necesario
        $this->rotateLogs();
    }

    private function registerHandlers() {
        // Manejar errores PHP
        set_error_handler([$this, 'handleError']);

        // Manejar excepciones no capturadas
        set_exception_handler([$this, 'handleException']);

        // Manejar errores fatales
        register_shutdown_function([$this, 'handleShutdown']);

        // Manejar errores de assertion
        assert_options(ASSERT_ACTIVE, 1);
        assert_options(ASSERT_WARNING, 0);
        assert_options(ASSERT_QUIET_EVAL, 1);
        assert_options(ASSERT_CALLBACK, [$this, 'handleAssertion']);
    }

    public function handleError($errno, $errstr, $errfile, $errline, $errcontext = []) {
        $errorType = $this->getErrorType($errno);

        if ($this->shouldLogError($errno)) {
            $logData = [
                'level' => 'ERROR',
                'type' => $errorType,
                'message' => $errstr,
                'file' => $errfile,
                'line' => $errline,
                'context' => $this->sanitizeContext($errcontext),
                'timestamp' => date('Y-m-d H:i:s'),
                'user_id' => $_SESSION['user_id'] ?? null,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'session_id' => session_id()
            ];

            $this->log($logData);

            // Mostrar error si está en modo debug
            if ($this->displayErrors) {
                $this->displayError($logData);
            }
        }

        // No ejecutar el manejador de errores interno de PHP
        return true;
    }

    public function handleException($exception) {
        $logData = [
            'level' => 'CRITICAL',
            'type' => 'Exception',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'] ?? null,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'session_id' => session_id()
        ];

        $this->log($logData);

        // Mostrar página de error amigable
        $this->displayExceptionPage($logData);
    }

    public function handleShutdown() {
        $error = error_get_last();
        if ($error && $this->shouldLogError($error['type'])) {
            $logData = [
                'level' => 'CRITICAL',
                'type' => 'Fatal Error',
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'timestamp' => date('Y-m-d H:i:s'),
                'user_id' => $_SESSION['user_id'] ?? null,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'session_id' => session_id()
            ];

            $this->log($logData);
        }
    }

    public function handleAssertion($file, $line, $code, $desc = null) {
        $logData = [
            'level' => 'WARNING',
            'type' => 'Assertion Failed',
            'message' => $desc ?: 'Assertion failed',
            'file' => $file,
            'line' => $line,
            'code' => $code,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->log($logData);
    }

    private function log($data) {
        $logEntry = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        // Log to file
        if ($this->logToFile) {
            $date = date('Y-m-d');
            $logFile = LOG_DIR . "/error_log_{$date}.json";

            if (file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
                error_log("Failed to write to log file: $logFile");
            }
        }

        // Log to database (si está configurado)
        if ($this->logToDatabase) {
            $this->logToDatabase($data);
        }

        // Log to system log for critical errors
        if ($data['level'] === 'CRITICAL') {
            error_log("CRITICAL ERROR: {$data['message']} in {$data['file']}:{$data['line']}");
        }
    }

    private function logToDatabase($data) {
        // Implementar logging a base de datos si es necesario
        // $conn = getDatabaseConnection();
        // $stmt = $conn->prepare("INSERT INTO error_logs (level, type, message, file, line, context, timestamp, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        // $stmt->execute([$data['level'], $data['type'], $data['message'], $data['file'], $data['line'], json_encode($data['context']), $data['timestamp'], $data['user_id']]);
    }

    private function shouldLogError($errno) {
        return ($errno & error_reporting()) === $errno;
    }

    private function getErrorType($errno) {
        switch ($errno) {
            case E_ERROR: return 'E_ERROR';
            case E_WARNING: return 'E_WARNING';
            case E_PARSE: return 'E_PARSE';
            case E_NOTICE: return 'E_NOTICE';
            case E_CORE_ERROR: return 'E_CORE_ERROR';
            case E_CORE_WARNING: return 'E_CORE_WARNING';
            case E_COMPILE_ERROR: return 'E_COMPILE_ERROR';
            case E_COMPILE_WARNING: return 'E_COMPILE_WARNING';
            case E_USER_ERROR: return 'E_USER_ERROR';
            case E_USER_WARNING: return 'E_USER_WARNING';
            case E_USER_NOTICE: return 'E_USER_NOTICE';
            case E_STRICT: return 'E_STRICT';
            case E_RECOVERABLE_ERROR: return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED: return 'E_DEPRECATED';
            case E_USER_DEPRECATED: return 'E_USER_DEPRECATED';
            default: return 'UNKNOWN';
        }
    }

    private function sanitizeContext($context) {
        // Remover información sensible del contexto
        $safeContext = [];
        foreach ($context as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $safeContext[$key] = '[COMPLEX DATA]';
            } elseif (strpos(strtolower($key), 'password') !== false ||
                      strpos(strtolower($key), 'token') !== false ||
                      strpos(strtolower($key), 'secret') !== false) {
                $safeContext[$key] = '[REDACTED]';
            } else {
                $safeContext[$key] = $value;
            }
        }
        return $safeContext;
    }

    private function displayError($data) {
        echo "<div style='background: #fee; border: 1px solid #fcc; padding: 10px; margin: 10px; border-radius: 5px;'>";
        echo "<h3 style='color: #c33;'>Error: {$data['type']}</h3>";
        echo "<p><strong>File:</strong> {$data['file']}:{$data['line']}</p>";
        echo "<p><strong>Message:</strong> {$data['message']}</p>";
        echo "<p><strong>Time:</strong> {$data['timestamp']}</p>";
        echo "</div>";
    }

    private function displayExceptionPage($data) {
        http_response_code(500);

        if ($this->displayErrors) {
            echo "<!DOCTYPE html><html><head><title>Error 500</title></head><body>";
            echo "<h1>Error 500 - Internal Server Error</h1>";
            echo "<p><strong>Message:</strong> {$data['message']}</p>";
            echo "<p><strong>File:</strong> {$data['file']}:{$data['line']}</p>";
            echo "<p><strong>Time:</strong> {$data['timestamp']}</p>";
            echo "<h3>Stack Trace:</h3>";
            echo "<pre>{$data['trace']}</pre>";
            echo "</body></html>";
        } else {
            // Mostrar página de error amigable
            echo "<!DOCTYPE html><html><head><title>Error del Sistema</title></head><body>";
            echo "<h1>Lo sentimos, ha ocurrido un error</h1>";
            echo "<p>El error ha sido registrado y será revisado por nuestro equipo técnico.</p>";
            echo "<p>Puede intentar nuevamente o <a href='/'>volver al inicio</a>.</p>";
            echo "</body></html>";
        }
    }

    private function rotateLogs() {
        $logFiles = glob(LOG_DIR . '/error_log_*.json');

        if (count($logFiles) > MAX_LOG_FILES) {
            // Ordenar por fecha de modificación
            usort($logFiles, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            // Eliminar archivos más antiguos
            $filesToDelete = array_slice($logFiles, 0, count($logFiles) - MAX_LOG_FILES);
            foreach ($filesToDelete as $file) {
                unlink($file);
            }
        }

        // Verificar tamaño de archivos actuales
        foreach ($logFiles as $file) {
            if (filesize($file) > MAX_LOG_SIZE) {
                // Rotar archivo actual
                $backupFile = $file . '.bak';
                rename($file, $backupFile);
            }
        }
    }

    // Métodos públicos para logging manual
    public function debug($message, $context = []) {
        if ($this->logLevel <= LOG_LEVELS['DEBUG']) {
            $this->log([
                'level' => 'DEBUG',
                'message' => $message,
                'context' => $context,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    }

    public function info($message, $context = []) {
        if ($this->logLevel <= LOG_LEVELS['INFO']) {
            $this->log([
                'level' => 'INFO',
                'message' => $message,
                'context' => $context,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    }

    public function warning($message, $context = []) {
        if ($this->logLevel <= LOG_LEVELS['WARNING']) {
            $this->log([
                'level' => 'WARNING',
                'message' => $message,
                'context' => $context,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    }

    public function error($message, $context = []) {
        if ($this->logLevel <= LOG_LEVELS['ERROR']) {
            $this->log([
                'level' => 'ERROR',
                'message' => $message,
                'context' => $context,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    }

    public function critical($message, $context = []) {
        if ($this->logLevel <= LOG_LEVELS['CRITICAL']) {
            $this->log([
                'level' => 'CRITICAL',
                'message' => $message,
                'context' => $context,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    }
}

// Inicializar el manejador de errores
ErrorHandler::getInstance();

// Función helper para logging rápido
function log_debug($message, $context = []) {
    ErrorHandler::getInstance()->debug($message, $context);
}

function log_info($message, $context = []) {
    ErrorHandler::getInstance()->info($message, $context);
}

function log_warning($message, $context = []) {
    ErrorHandler::getInstance()->warning($message, $context);
}

function log_error($message, $context = []) {
    ErrorHandler::getInstance()->error($message, $context);
}

function log_critical($message, $context = []) {
    ErrorHandler::getInstance()->critical($message, $context);
}
?>