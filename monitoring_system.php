<?php
// Sistema de monitoreo y logging avanzado

class MonitoringSystem {
    private static $instance = null;
    private $logDir;
    private $metrics = [];
    private $alerts = [];
    private $performanceThresholds = [];

    private function __construct() {
        $this->logDir = __DIR__ . '/logs';
        $this->initializeMonitoring();
        $this->setDefaultThresholds();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeMonitoring() {
        if (!file_exists($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }

        // Crear archivos de log si no existen
        $logFiles = ['performance', 'security', 'errors', 'access', 'api'];
        foreach ($logFiles as $file) {
            $logFile = $this->logDir . "/{$file}_log.json";
            if (!file_exists($logFile)) {
                file_put_contents($logFile, json_encode(['created' => date('Y-m-d H:i:s')]) . PHP_EOL);
            }
        }
    }

    private function setDefaultThresholds() {
        $this->performanceThresholds = [
            'max_execution_time' => 5.0, // segundos
            'max_memory_usage' => 50 * 1024 * 1024, // 50MB
            'max_db_queries' => 100,
            'max_error_rate' => 0.05, // 5%
            'min_response_time' => 0.1 // 100ms
        ];
    }

    // Logging de métricas de rendimiento
    public function logPerformance($endpoint, $executionTime, $memoryUsage, $dbQueries = 0) {
        $metric = [
            'timestamp' => date('Y-m-d H:i:s'),
            'endpoint' => $endpoint,
            'execution_time' => round($executionTime, 4),
            'memory_usage' => $memoryUsage,
            'db_queries' => $dbQueries,
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];

        $this->metrics[] = $metric;
        $this->writeLog('performance', $metric);

        // Verificar umbrales
        $this->checkPerformanceThresholds($metric);

        return $metric;
    }

    // Logging de eventos de seguridad
    public function logSecurityEvent($event, $details = []) {
        $securityEvent = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'severity' => $details['severity'] ?? 'medium',
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'details' => $details
        ];

        $this->writeLog('security', $securityEvent);

        // Verificar si requiere alerta
        if ($securityEvent['severity'] === 'high' || $securityEvent['severity'] === 'critical') {
            $this->triggerAlert('security', $securityEvent);
        }

        return $securityEvent;
    }

    // Logging de acceso a la aplicación
    public function logAccess($endpoint, $method = 'GET', $responseCode = 200) {
        $accessLog = [
            'timestamp' => date('Y-m-d H:i:s'),
            'endpoint' => $endpoint,
            'method' => $method,
            'response_code' => $responseCode,
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'session_id' => session_id()
        ];

        $this->writeLog('access', $accessLog);
        return $accessLog;
    }

    // Logging de llamadas a API
    public function logAPI($endpoint, $method, $requestData, $responseData, $executionTime) {
        $apiLog = [
            'timestamp' => date('Y-m-d H:i:s'),
            'endpoint' => $endpoint,
            'method' => $method,
            'execution_time' => round($executionTime, 4),
            'request_size' => strlen(json_encode($requestData)),
            'response_size' => strlen(json_encode($responseData)),
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'success' => !isset($responseData['error'])
        ];

        $this->writeLog('api', $apiLog);
        return $apiLog;
    }

    // Logging de errores personalizados
    public function logCustom($category, $message, $data = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'category' => $category,
            'message' => $message,
            'data' => $data,
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ];

        $this->writeLog($category, $logEntry);
        return $logEntry;
    }

    // Verificar umbrales de rendimiento
    private function checkPerformanceThresholds($metric) {
        $alerts = [];

        if ($metric['execution_time'] > $this->performanceThresholds['max_execution_time']) {
            $alerts[] = "Tiempo de ejecución alto: {$metric['execution_time']}s";
        }

        if ($metric['memory_usage'] > $this->performanceThresholds['max_memory_usage']) {
            $alerts[] = "Uso de memoria alto: " . round($metric['memory_usage'] / 1024 / 1024, 2) . "MB";
        }

        if ($metric['db_queries'] > $this->performanceThresholds['max_db_queries']) {
            $alerts[] = "Número alto de consultas DB: {$metric['db_queries']}";
        }

        foreach ($alerts as $alert) {
            $this->triggerAlert('performance', [
                'message' => $alert,
                'metric' => $metric
            ]);
        }
    }

    // Generar alertas
    private function triggerAlert($type, $data) {
        $alert = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $type,
            'data' => $data,
            'resolved' => false
        ];

        $this->alerts[] = $alert;

        // Enviar notificación por email (si está configurado)
        $this->sendAlertNotification($alert);

        // Log de la alerta
        $this->writeLog('alerts', $alert);
    }

    // Enviar notificación de alerta
    private function sendAlertNotification($alert) {
        // Implementar envío de email o notificaciones push
        // Por ejemplo, usando PHPMailer o similar

        if (function_exists('mail')) {
            $subject = "ALERTA: {$alert['type']} - Sistema Médico";
            $message = json_encode($alert, JSON_PRETTY_PRINT);

            // mail('admin@domain.com', $subject, $message);
        }
    }

    // Obtener estadísticas de rendimiento
    public function getPerformanceStats($timeframe = '1 hour') {
        $timeframeSeconds = $this->parseTimeframe($timeframe);
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$timeframe}"));

        $performanceLogs = $this->readLogs('performance', $cutoffTime);

        $stats = [
            'total_requests' => count($performanceLogs),
            'avg_execution_time' => 0,
            'avg_memory_usage' => 0,
            'max_execution_time' => 0,
            'max_memory_usage' => 0,
            'slow_requests' => 0,
            'error_rate' => 0
        ];

        if (count($performanceLogs) > 0) {
            $executionTimes = array_column($performanceLogs, 'execution_time');
            $memoryUsages = array_column($performanceLogs, 'memory_usage');

            $stats['avg_execution_time'] = round(array_sum($executionTimes) / count($executionTimes), 4);
            $stats['avg_memory_usage'] = round(array_sum($memoryUsages) / count($memoryUsages));
            $stats['max_execution_time'] = round(max($executionTimes), 4);
            $stats['max_memory_usage'] = max($memoryUsages);
            $stats['slow_requests'] = count(array_filter($executionTimes, function($time) {
                return $time > $this->performanceThresholds['max_execution_time'];
            }));
        }

        return $stats;
    }

    // Obtener estadísticas de seguridad
    public function getSecurityStats($timeframe = '24 hours') {
        $timeframeSeconds = $this->parseTimeframe($timeframe);
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$timeframe}"));

        $securityLogs = $this->readLogs('security', $cutoffTime);

        $stats = [
            'total_events' => count($securityLogs),
            'high_severity' => 0,
            'medium_severity' => 0,
            'low_severity' => 0,
            'failed_logins' => 0,
            'suspicious_activity' => 0
        ];

        foreach ($securityLogs as $log) {
            $severity = $log['severity'] ?? 'medium';
            $stats[$severity . '_severity']++;

            if (strpos($log['event'], 'login') !== false && isset($log['details']['success']) && !$log['details']['success']) {
                $stats['failed_logins']++;
            }

            if (in_array($log['event'], ['suspicious_activity', 'brute_force', 'sql_injection'])) {
                $stats['suspicious_activity']++;
            }
        }

        return $stats;
    }

    // Generar reporte de salud del sistema
    public function generateHealthReport() {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'system_health' => 'good',
            'performance' => $this->getPerformanceStats('1 hour'),
            'security' => $this->getSecurityStats('24 hours'),
            'active_alerts' => count(array_filter($this->alerts, function($alert) {
                return !$alert['resolved'];
            })),
            'disk_usage' => $this->getDiskUsage(),
            'database_status' => $this->checkDatabaseStatus()
        ];

        // Determinar salud general del sistema
        if ($report['active_alerts'] > 5 || $report['security']['high_severity'] > 0) {
            $report['system_health'] = 'critical';
        } elseif ($report['active_alerts'] > 2 || $report['performance']['slow_requests'] > 10) {
            $report['system_health'] = 'warning';
        }

        return $report;
    }

    // Obtener uso de disco
    private function getDiskUsage() {
        $diskUsage = [
            'log_directory' => $this->getDirectorySize($this->logDir),
            'total_space' => disk_total_space(__DIR__),
            'free_space' => disk_free_space(__DIR__)
        ];

        $diskUsage['used_percentage'] = round(
            (($diskUsage['total_space'] - $diskUsage['free_space']) / $diskUsage['total_space']) * 100,
            2
        );

        return $diskUsage;
    }

    // Verificar estado de la base de datos
    private function checkDatabaseStatus() {
        try {
            $conn = getDatabaseConnection();
            $stmt = $conn->query("SELECT 1");
            return $stmt ? 'connected' : 'error';
        } catch (Exception $e) {
            return 'error: ' . $e->getMessage();
        }
    }

    // Obtener tamaño de directorio
    private function getDirectorySize($dir) {
        $size = 0;
        foreach (glob(rtrim($dir, '/') . '/*', GLOB_NOSORT) as $file) {
            $size += is_file($file) ? filesize($file) : $this->getDirectorySize($file);
        }
        return $size;
    }

    // Parsear timeframe
    private function parseTimeframe($timeframe) {
        $unit = strtolower(substr($timeframe, -1));
        $value = (int) $timeframe;

        switch ($unit) {
            case 'h': return $value * 3600;
            case 'd': return $value * 86400;
            case 'w': return $value * 604800;
            case 'm': return $value * 2592000;
            default: return $value;
        }
    }

    // Leer logs
    private function readLogs($type, $cutoffTime = null) {
        $logFile = $this->logDir . "/{$type}_log.json";
        $logs = [];

        if (file_exists($logFile)) {
            $handle = fopen($logFile, 'r');
            while (($line = fgets($handle)) !== false) {
                $log = json_decode($line, true);
                if ($log && (!$cutoffTime || $log['timestamp'] >= $cutoffTime)) {
                    $logs[] = $log;
                }
            }
            fclose($handle);
        }

        return $logs;
    }

    // Escribir log
    private function writeLog($type, $data) {
        $logFile = $this->logDir . "/{$type}_log.json";
        $logEntry = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        if (file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
            error_log("Failed to write to {$type} log file: $logFile");
        }
    }

    // Limpiar logs antiguos
    public function cleanupOldLogs($days = 30) {
        $cutoffDate = date('Y-m-d', strtotime("-{$days} days"));

        $logFiles = ['performance', 'security', 'errors', 'access', 'api'];
        foreach ($logFiles as $type) {
            $logFile = $this->logDir . "/{$type}_log.json";
            if (file_exists($logFile)) {
                $tempFile = $logFile . '.tmp';
                $handle = fopen($logFile, 'r');
                $tempHandle = fopen($tempFile, 'w');

                while (($line = fgets($handle)) !== false) {
                    $log = json_decode($line, true);
                    if ($log && $log['timestamp'] >= $cutoffDate) {
                        fwrite($tempHandle, $line);
                    }
                }

                fclose($handle);
                fclose($tempHandle);

                rename($tempFile, $logFile);
            }
        }
    }
}

// Funciones helper para logging rápido
function log_performance($endpoint, $executionTime, $memoryUsage, $dbQueries = 0) {
    return MonitoringSystem::getInstance()->logPerformance($endpoint, $executionTime, $memoryUsage, $dbQueries);
}

function log_security($event, $details = []) {
    return MonitoringSystem::getInstance()->logSecurityEvent($event, $details);
}

function log_access($endpoint, $method = 'GET', $responseCode = 200) {
    return MonitoringSystem::getInstance()->logAccess($endpoint, $method, $responseCode);
}

function log_api($endpoint, $method, $requestData, $responseData, $executionTime) {
    return MonitoringSystem::getInstance()->logAPI($endpoint, $method, $requestData, $responseData, $executionTime);
}

function log_custom($category, $message, $data = []) {
    return MonitoringSystem::getInstance()->logCustom($category, $message, $data);
}

function get_health_report() {
    return MonitoringSystem::getInstance()->generateHealthReport();
}

// Inicializar el sistema de monitoreo
MonitoringSystem::getInstance();
?>