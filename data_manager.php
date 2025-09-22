<?php
// Sistema de exportación e importación de datos

class DataManager {
    private static $instance = null;
    private $conn;
    private $exportFormats = ['csv', 'json', 'xml', 'pdf'];
    private $importFormats = ['csv', 'json', 'xml'];

    private function __construct() {
        $this->connect();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect() {
        try {
            $this->conn = new PDO(
                "mysql:host={$_ENV['DB_HOST'] ?? 'localhost'};dbname={$_ENV['DB_NAME'] ?? 'medical_db'};charset=utf8mb4",
                $_ENV['DB_USER'] ?? 'root',
                $_ENV['DB_PASS'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            log_critical('DataManager connection failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    // Exportar datos a diferentes formatos
    public function exportData($tables, $format = 'csv', $filename = null) {
        if (!in_array($format, $this->exportFormats)) {
            return [
                'success' => false,
                'error' => 'Formato no soportado: ' . $format
            ];
        }

        if (!$filename) {
            $filename = 'export_' . date('Y-m-d_H-i-s') . '.' . $format;
        }

        try {
            $data = $this->getExportData($tables);

            switch ($format) {
                case 'csv':
                    return $this->exportToCSV($data, $filename);
                case 'json':
                    return $this->exportToJSON($data, $filename);
                case 'xml':
                    return $this->exportToXML($data, $filename);
                case 'pdf':
                    return $this->exportToPDF($data, $filename);
                default:
                    return [
                        'success' => false,
                        'error' => 'Formato no implementado: ' . $format
                    ];
            }

        } catch (Exception $e) {
            log_error('Data export failed', [
                'error' => $e->getMessage(),
                'tables' => $tables,
                'format' => $format
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Obtener datos para exportación
    private function getExportData($tables) {
        $data = [];

        foreach ($tables as $table) {
            $stmt = $this->conn->query("SELECT * FROM `{$table}`");
            $rows = $stmt->fetchAll();

            $data[$table] = [
                'columns' => $stmt->fetch(PDO::FETCH_ASSOC) ? array_keys($stmt->fetch(PDO::FETCH_ASSOC)) : [],
                'rows' => $rows,
                'count' => count($rows)
            ];
        }

        return $data;
    }

    // Exportar a CSV
    private function exportToCSV($data, $filename) {
        $filepath = $this->getExportPath($filename);

        try {
            $handle = fopen($filepath, 'w');

            // Escribir headers
            $headers = [];
            foreach ($data as $tableName => $tableData) {
                $headers = array_merge($headers, array_map(function($col) use ($tableName) {
                    return $tableName . '_' . $col;
                }, $tableData['columns']));
            }
            fputcsv($handle, $headers);

            // Escribir datos
            $maxRows = max(array_column($data, 'count'));
            for ($i = 0; $i < $maxRows; $i++) {
                $row = [];
                foreach ($data as $tableData) {
                    if (isset($tableData['rows'][$i])) {
                        $row = array_merge($row, array_values($tableData['rows'][$i]));
                    } else {
                        $row = array_merge($row, array_fill(0, count($tableData['columns']), ''));
                    }
                }
                fputcsv($handle, $row);
            }

            fclose($handle);

            log_info('CSV export completed', [
                'file' => $filepath,
                'tables' => array_keys($data),
                'rows' => array_sum(array_column($data, 'count'))
            ]);

            return [
                'success' => true,
                'file' => $filepath,
                'format' => 'csv',
                'size' => filesize($filepath)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Exportar a JSON
    private function exportToJSON($data, $filename) {
        $filepath = $this->getExportPath($filename);

        try {
            $jsonData = [
                'export_info' => [
                    'date' => date('Y-m-d H:i:s'),
                    'tables' => array_keys($data),
                    'total_rows' => array_sum(array_column($data, 'count'))
                ],
                'data' => $data
            ];

            if (file_put_contents($filepath, json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
                throw new Exception('Failed to write JSON file');
            }

            log_info('JSON export completed', [
                'file' => $filepath,
                'tables' => array_keys($data)
            ]);

            return [
                'success' => true,
                'file' => $filepath,
                'format' => 'json',
                'size' => filesize($filepath)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Exportar a XML
    private function exportToXML($data, $filename) {
        $filepath = $this->getExportPath($filename);

        try {
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><medical_data></medical_data>');

            $xml->addAttribute('export_date', date('Y-m-d H:i:s'));
            $xml->addAttribute('total_tables', count($data));

            foreach ($data as $tableName => $tableData) {
                $tableElement = $xml->addChild($tableName);

                foreach ($tableData['rows'] as $row) {
                    $rowElement = $tableElement->addChild('row');
                    foreach ($row as $column => $value) {
                        $rowElement->addChild($column, htmlspecialchars($value));
                    }
                }
            }

            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->formatOutput = true;
            $dom->loadXML($xml->asXML());

            if (file_put_contents($filepath, $dom->saveXML()) === false) {
                throw new Exception('Failed to write XML file');
            }

            log_info('XML export completed', [
                'file' => $filepath,
                'tables' => array_keys($data)
            ]);

            return [
                'success' => true,
                'file' => $filepath,
                'format' => 'xml',
                'size' => filesize($filepath)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Exportar a PDF (requiere librería como TCPDF o similar)
    private function exportToPDF($data, $filename) {
        // Esta implementación requeriría una librería PDF como TCPDF
        // Por simplicidad, devolveremos un mensaje indicando que no está implementado

        return [
            'success' => false,
            'error' => 'PDF export requires additional library (TCPDF, FPDF, etc.)'
        ];
    }

    // Importar datos desde diferentes formatos
    public function importData($file, $format = 'csv') {
        if (!in_array($format, $this->importFormats)) {
            return [
                'success' => false,
                'error' => 'Formato no soportado: ' . $format
            ];
        }

        if (!file_exists($file)) {
            return [
                'success' => false,
                'error' => 'Archivo no encontrado'
            ];
        }

        try {
            switch ($format) {
                case 'csv':
                    return $this->importFromCSV($file);
                case 'json':
                    return $this->importFromJSON($file);
                case 'xml':
                    return $this->importFromXML($file);
                default:
                    return [
                        'success' => false,
                        'error' => 'Formato no implementado: ' . $format
                    ];
            }

        } catch (Exception $e) {
            log_error('Data import failed', [
                'error' => $e->getMessage(),
                'file' => $file,
                'format' => $format
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Importar desde CSV
    private function importFromCSV($file) {
        try {
            $handle = fopen($file, 'r');
            $headers = fgetcsv($handle);
            $data = [];

            while (($row = fgetcsv($handle)) !== false) {
                $data[] = array_combine($headers, $row);
            }

            fclose($handle);

            // Procesar datos importados
            $result = $this->processImportData($data);

            log_info('CSV import completed', [
                'file' => $file,
                'rows' => count($data),
                'result' => $result
            ]);

            return [
                'success' => true,
                'imported' => count($data),
                'result' => $result
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Importar desde JSON
    private function importFromJSON($file) {
        try {
            $jsonContent = file_get_contents($file);
            $data = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON format');
            }

            // Si es el formato de exportación, extraer datos
            if (isset($data['data'])) {
                $data = $data['data'];
            }

            $result = $this->processImportData($data);

            log_info('JSON import completed', [
                'file' => $file,
                'tables' => array_keys($data),
                'result' => $result
            ]);

            return [
                'success' => true,
                'tables' => array_keys($data),
                'result' => $result
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Importar desde XML
    private function importFromXML($file) {
        try {
            $xml = simplexml_load_file($file);

            if ($xml === false) {
                throw new Exception('Invalid XML format');
            }

            $data = [];
            foreach ($xml->children() as $table) {
                $tableName = $table->getName();
                $rows = [];

                foreach ($table->row as $row) {
                    $rowData = [];
                    foreach ($row->children() as $column) {
                        $rowData[$column->getName()] = (string) $column;
                    }
                    $rows[] = $rowData;
                }

                $data[$tableName] = $rows;
            }

            $result = $this->processImportData($data);

            log_info('XML import completed', [
                'file' => $file,
                'tables' => array_keys($data),
                'result' => $result
            ]);

            return [
                'success' => true,
                'tables' => array_keys($data),
                'result' => $result
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Procesar datos importados
    private function processImportData($data) {
        $results = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        foreach ($data as $tableName => $rows) {
            try {
                // Verificar que la tabla existe
                $stmt = $this->conn->query("SHOW TABLES LIKE '{$tableName}'");
                if ($stmt->rowCount() === 0) {
                    $results['errors'][] = "Table '{$tableName}' does not exist";
                    continue;
                }

                // Obtener columnas de la tabla
                $stmt = $this->conn->query("DESCRIBE `{$tableName}`");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($rows as $row) {
                    try {
                        // Filtrar columnas válidas
                        $validColumns = array_intersect(array_keys($row), $columns);
                        $validData = array_intersect_key($row, array_flip($validColumns));

                        if (empty($validData)) {
                            $results['skipped']++;
                            continue;
                        }

                        // Verificar si es actualización o inserción
                        $idColumn = $this->getPrimaryKey($tableName);
                        $isUpdate = false;

                        if ($idColumn && isset($row[$idColumn])) {
                            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM `{$tableName}` WHERE `{$idColumn}` = ?");
                            $stmt->execute([$row[$idColumn]]);
                            $isUpdate = $stmt->fetchColumn() > 0;
                        }

                        if ($isUpdate) {
                            // Actualizar
                            $updateSql = "UPDATE `{$tableName}` SET " .
                                implode(' = ?, ', $validColumns) . ' = ? WHERE ' .
                                $idColumn . ' = ?';

                            $updateValues = array_values($validData);
                            $updateValues[] = $row[$idColumn];

                            $stmt = $this->conn->prepare($updateSql);
                            $stmt->execute($updateValues);
                            $results['updated']++;
                        } else {
                            // Insertar
                            $insertSql = "INSERT INTO `{$tableName}` (" .
                                implode(', ', $validColumns) .
                                ") VALUES (" . str_repeat('?, ', count($validColumns) - 1) . "?)";

                            $stmt = $this->conn->prepare($insertSql);
                            $stmt->execute(array_values($validData));
                            $results['imported']++;
                        }

                    } catch (Exception $e) {
                        $results['errors'][] = "Error processing row in {$tableName}: " . $e->getMessage();
                        $results['skipped']++;
                    }
                }

            } catch (Exception $e) {
                $results['errors'][] = "Error processing table {$tableName}: " . $e->getMessage();
            }
        }

        return $results;
    }

    // Obtener clave primaria de una tabla
    private function getPrimaryKey($tableName) {
        $stmt = $this->conn->query("SHOW KEYS FROM `{$tableName}` WHERE Key_name = 'PRIMARY'");
        $primary = $stmt->fetch();

        return $primary ? $primary['Column_name'] : null;
    }

    // Obtener ruta para archivos de exportación
    private function getExportPath($filename) {
        $exportDir = __DIR__ . '/exports';

        if (!file_exists($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        return $exportDir . '/' . $filename;
    }

    // Validar archivo antes de importar
    public function validateImportFile($file, $format) {
        $errors = [];

        // Verificar tamaño del archivo
        if (filesize($file) > 10 * 1024 * 1024) { // 10MB
            $errors[] = 'El archivo es demasiado grande (máximo 10MB)';
        }

        // Verificar extensión
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($extension !== $format) {
            $errors[] = 'La extensión del archivo no coincide con el formato especificado';
        }

        // Validar contenido según formato
        switch ($format) {
            case 'csv':
                if (($handle = fopen($file, 'r')) === false) {
                    $errors[] = 'No se puede leer el archivo CSV';
                } else {
                    $headers = fgetcsv($handle);
                    if (empty($headers)) {
                        $errors[] = 'El archivo CSV está vacío o no tiene headers';
                    }
                    fclose($handle);
                }
                break;

            case 'json':
                $content = file_get_contents($file);
                if (!json_decode($content)) {
                    $errors[] = 'El archivo JSON no es válido';
                }
                break;

            case 'xml':
                if (!simplexml_load_file($file)) {
                    $errors[] = 'El archivo XML no es válido';
                }
                break;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    // Generar reporte de exportación
    public function generateExportReport($exportResult) {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'export',
            'format' => $exportResult['format'],
            'file' => basename($exportResult['file']),
            'size' => $this->formatBytes($exportResult['size']),
            'status' => $exportResult['success'] ? 'success' : 'failed'
        ];

        if (isset($exportResult['tables'])) {
            $report['tables'] = $exportResult['tables'];
        }

        if (isset($exportResult['error'])) {
            $report['error'] = $exportResult['error'];
        }

        return $report;
    }

    // Formatear bytes
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// Funciones helper
function export_data($tables, $format = 'csv', $filename = null) {
    return DataManager::getInstance()->exportData($tables, $format, $filename);
}

function import_data($file, $format = 'csv') {
    return DataManager::getInstance()->importData($file, $format);
}

function validate_import_file($file, $format) {
    return DataManager::getInstance()->validateImportFile($file, $format);
}

function generate_export_report($exportResult) {
    return DataManager::getInstance()->generateExportReport($exportResult);
}

// Inicializar el sistema de gestión de datos
DataManager::getInstance();
?>