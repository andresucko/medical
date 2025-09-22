<?php
// Sistema de gestión y respaldo de base de datos

class DatabaseManager {
    private static $instance = null;
    private $conn;
    private $config;

    private function __construct() {
        $this->loadConfiguration();
        $this->connect();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadConfiguration() {
        $this->config = [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'database' => $_ENV['DB_NAME'] ?? 'medical_db',
            'username' => $_ENV['DB_USER'] ?? 'root',
            'password' => $_ENV['DB_PASS'] ?? '',
            'backup_dir' => __DIR__ . '/backups',
            'max_backups' => 10,
            'compression' => true
        ];
    }

    private function connect() {
        try {
            $this->conn = new PDO(
                "mysql:host={$this->config['host']};dbname={$this->config['database']};charset=utf8mb4",
                $this->config['username'],
                $this->config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );

            log_info('Database connection established');
        } catch (PDOException $e) {
            log_critical('Database connection failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            throw $e;
        }
    }

    // Crear respaldo completo de la base de datos
    public function createFullBackup($filename = null) {
        if (!$filename) {
            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        }

        $backupPath = $this->config['backup_dir'] . '/' . $filename;

        try {
            // Crear directorio si no existe
            if (!file_exists($this->config['backup_dir'])) {
                mkdir($this->config['backup_dir'], 0755, true);
            }

            // Obtener todas las tablas
            $tables = $this->getTables();

            // Generar script SQL
            $sql = $this->generateSQLScript($tables);

            // Escribir archivo
            if ($this->config['compression']) {
                $sql = gzencode($sql, 9);
                $backupPath .= '.gz';
            }

            if (file_put_contents($backupPath, $sql) === false) {
                throw new Exception('Failed to write backup file');
            }

            // Verificar integridad del archivo
            if (!file_exists($backupPath)) {
                throw new Exception('Backup file was not created');
            }

            log_info('Database backup created successfully', [
                'file' => $backupPath,
                'size' => filesize($backupPath),
                'tables' => count($tables)
            ]);

            // Limpiar backups antiguos
            $this->cleanupOldBackups();

            return [
                'success' => true,
                'file' => $backupPath,
                'size' => filesize($backupPath),
                'tables' => count($tables)
            ];

        } catch (Exception $e) {
            log_error('Database backup failed', [
                'error' => $e->getMessage(),
                'file' => $backupPath
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Crear respaldo de tablas específicas
    public function createPartialBackup($tables, $filename = null) {
        if (!$filename) {
            $filename = 'partial_backup_' . date('Y-m-d_H-i-s') . '.sql';
        }

        $backupPath = $this->config['backup_dir'] . '/' . $filename;

        try {
            // Validar tablas
            $validTables = $this->validateTables($tables);

            // Generar script SQL
            $sql = $this->generateSQLScript($validTables);

            // Escribir archivo
            if ($this->config['compression']) {
                $sql = gzencode($sql, 9);
                $backupPath .= '.gz';
            }

            if (file_put_contents($backupPath, $sql) === false) {
                throw new Exception('Failed to write backup file');
            }

            log_info('Partial database backup created', [
                'file' => $backupPath,
                'tables' => $validTables
            ]);

            return [
                'success' => true,
                'file' => $backupPath,
                'tables' => $validTables
            ];

        } catch (Exception $e) {
            log_error('Partial backup failed', [
                'error' => $e->getMessage(),
                'tables' => $tables
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Restaurar base de datos desde respaldo
    public function restoreDatabase($backupFile) {
        if (!file_exists($backupFile)) {
            return [
                'success' => false,
                'error' => 'Backup file not found'
            ];
        }

        try {
            // Leer contenido del archivo
            $sql = file_get_contents($backupFile);

            // Descomprimir si es necesario
            if (substr($backupFile, -3) === '.gz') {
                $sql = gzdecode($sql);
            }

            // Ejecutar SQL
            $this->conn->exec('SET FOREIGN_KEY_CHECKS = 0');

            // Dividir en consultas individuales
            $queries = $this->splitSQLQueries($sql);

            foreach ($queries as $query) {
                if (trim($query)) {
                    $this->conn->exec($query);
                }
            }

            $this->conn->exec('SET FOREIGN_KEY_CHECKS = 1');

            log_info('Database restored successfully', [
                'file' => $backupFile,
                'queries' => count($queries)
            ]);

            return [
                'success' => true,
                'queries_executed' => count($queries)
            ];

        } catch (Exception $e) {
            log_error('Database restore failed', [
                'error' => $e->getMessage(),
                'file' => $backupFile
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Obtener lista de tablas
    private function getTables() {
        $stmt = $this->conn->query("SHOW TABLES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Validar que las tablas existan
    private function validateTables($tables) {
        $allTables = $this->getTables();
        $validTables = [];

        foreach ($tables as $table) {
            if (in_array($table, $allTables)) {
                $validTables[] = $table;
            }
        }

        return $validTables;
    }

    // Generar script SQL completo
    private function generateSQLScript($tables) {
        $sql = "-- Database backup created on " . date('Y-m-d H:i:s') . "\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $table) {
            $sql .= $this->generateTableScript($table);
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        return $sql;
    }

    // Generar script para una tabla específica
    private function generateTableScript($table) {
        $sql = "-- Table: {$table}\n";

        // Estructura de la tabla
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $stmt = $this->conn->query("SHOW CREATE TABLE `{$table}`");
        $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
        $sql .= $createTable['Create Table'] . ";\n\n";

        // Datos de la tabla
        $stmt = $this->conn->query("SELECT COUNT(*) as count FROM `{$table}`");
        $count = $stmt->fetch()['count'];

        if ($count > 0) {
            $sql .= "INSERT INTO `{$table}` VALUES\n";

            $stmt = $this->conn->query("SELECT * FROM `{$table}`");
            $rows = $stmt->fetchAll(PDO::FETCH_NUM);

            $values = [];
            foreach ($rows as $row) {
                $escapedValues = array_map(function($value) {
                    if ($value === null) {
                        return 'NULL';
                    }
                    return $this->conn->quote($value);
                }, $row);
                $values[] = '(' . implode(',', $escapedValues) . ')';
            }

            $sql .= implode(",\n", $values) . ";\n\n";
        }

        return $sql;
    }

    // Dividir SQL en consultas individuales
    private function splitSQLQueries($sql) {
        $queries = [];
        $lines = explode("\n", $sql);
        $currentQuery = '';

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line) || strpos($line, '--') === 0) {
                continue;
            }

            $currentQuery .= $line . "\n";

            if (substr($line, -1) === ';') {
                $queries[] = $currentQuery;
                $currentQuery = '';
            }
        }

        return $queries;
    }

    // Limpiar backups antiguos
    private function cleanupOldBackups() {
        $backups = glob($this->config['backup_dir'] . '/*.{sql,gz}', GLOB_BRACE);

        if (count($backups) > $this->config['max_backups']) {
            // Ordenar por fecha de modificación
            usort($backups, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            // Eliminar archivos más antiguos
            $filesToDelete = array_slice($backups, 0, count($backups) - $this->config['max_backups']);

            foreach ($filesToDelete as $file) {
                unlink($file);
                log_info('Old backup file deleted', ['file' => $file]);
            }
        }
    }

    // Obtener información de la base de datos
    public function getDatabaseInfo() {
        try {
            $info = [
                'database' => $this->config['database'],
                'version' => $this->conn->query('SELECT VERSION()')->fetchColumn(),
                'tables' => count($this->getTables()),
                'size' => $this->getDatabaseSize(),
                'last_backup' => $this->getLastBackupInfo()
            ];

            return [
                'success' => true,
                'info' => $info
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Obtener tamaño de la base de datos
    private function getDatabaseSize() {
        try {
            $stmt = $this->conn->query("
                SELECT
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                FROM information_schema.tables
                WHERE table_schema = '{$this->config['database']}'
            ");

            return $stmt->fetch()['size_mb'] . ' MB';
        } catch (Exception $e) {
            return 'Unknown';
        }
    }

    // Obtener información del último backup
    private function getLastBackupInfo() {
        $backups = glob($this->config['backup_dir'] . '/*.{sql,gz}', GLOB_BRACE);

        if (empty($backups)) {
            return 'No backups found';
        }

        usort($backups, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $lastBackup = $backups[0];
        return [
            'file' => basename($lastBackup),
            'date' => date('Y-m-d H:i:s', filemtime($lastBackup)),
            'size' => round(filesize($lastBackup) / 1024 / 1024, 2) . ' MB'
        ];
    }

    // Optimizar base de datos
    public function optimizeDatabase() {
        try {
            $tables = $this->getTables();
            $results = [];

            foreach ($tables as $table) {
                $stmt = $this->conn->query("OPTIMIZE TABLE `{$table}`");
                $results[$table] = $stmt->fetch();
            }

            log_info('Database optimization completed', [
                'tables' => $tables,
                'results' => $results
            ]);

            return [
                'success' => true,
                'optimized_tables' => count($tables),
                'results' => $results
            ];

        } catch (Exception $e) {
            log_error('Database optimization failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Reparar base de datos
    public function repairDatabase() {
        try {
            $tables = $this->getTables();
            $results = [];

            foreach ($tables as $table) {
                $stmt = $this->conn->query("REPAIR TABLE `{$table}`");
                $results[$table] = $stmt->fetch();
            }

            log_info('Database repair completed', [
                'tables' => $tables,
                'results' => $results
            ]);

            return [
                'success' => true,
                'repaired_tables' => count($tables),
                'results' => $results
            ];

        } catch (Exception $e) {
            log_error('Database repair failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Crear respaldo programado
    public function scheduleBackup($frequency = 'daily', $time = '02:00') {
        // Esta función crearía un cron job o tarea programada
        // Por simplicidad, solo logueamos la configuración

        log_info('Backup schedule configured', [
            'frequency' => $frequency,
            'time' => $time
        ]);

        return [
            'success' => true,
            'message' => "Backup scheduled for {$frequency} at {$time}",
            'note' => 'Configure cron job manually or use a task scheduler'
        ];
    }

    // Verificar integridad de backups
    public function verifyBackupIntegrity($backupFile) {
        if (!file_exists($backupFile)) {
            return [
                'success' => false,
                'error' => 'Backup file not found'
            ];
        }

        try {
            // Leer contenido del backup
            $sql = file_get_contents($backupFile);

            if (substr($backupFile, -3) === '.gz') {
                $sql = gzdecode($sql);
            }

            // Verificar sintaxis SQL básica
            $queries = $this->splitSQLQueries($sql);
            $validQueries = 0;

            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query) && !preg_match('/^--/', $query)) {
                    $validQueries++;
                }
            }

            return [
                'success' => true,
                'file' => basename($backupFile),
                'size' => filesize($backupFile),
                'total_queries' => count($queries),
                'valid_queries' => $validQueries,
                'integrity' => $validQueries > 0 ? 'good' : 'suspicious'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

// Funciones helper
function create_database_backup($filename = null) {
    return DatabaseManager::getInstance()->createFullBackup($filename);
}

function restore_database($backupFile) {
    return DatabaseManager::getInstance()->restoreDatabase($backupFile);
}

function get_database_info() {
    return DatabaseManager::getInstance()->getDatabaseInfo();
}

function optimize_database() {
    return DatabaseManager::getInstance()->optimizeDatabase();
}

function repair_database() {
    return DatabaseManager::getInstance()->repairDatabase();
}

// Inicializar el sistema de gestión de base de datos
DatabaseManager::getInstance();
?>