<?php
// Sistema de optimización de rendimiento

class PerformanceOptimizer {
    private static $instance = null;
    private $cache = [];
    private $startTime;
    private $memoryStart;

    private function __construct() {
        $this->startTime = microtime(true);
        $this->memoryStart = memory_get_usage();
        $this->initializeCaching();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeCaching() {
        // Configurar diferentes tipos de caché
        if (extension_loaded('apcu')) {
            $this->cache['type'] = 'apcu';
        } elseif (extension_loaded('memcached')) {
            $this->cache['type'] = 'memcached';
        } elseif (extension_loaded('redis')) {
            $this->cache['type'] = 'redis';
        } else {
            $this->cache['type'] = 'file';
            $this->cache['dir'] = __DIR__ . '/cache';
            if (!file_exists($this->cache['dir'])) {
                mkdir($this->cache['dir'], 0755, true);
            }
        }
    }

    // Caché de consultas SQL
    public function cacheQuery($key, $query, $params = [], $ttl = 300) {
        $cacheKey = 'sql_' . md5($key . $query . serialize($params));

        if ($this->getFromCache($cacheKey)) {
            return $this->getFromCache($cacheKey);
        }

        try {
            $conn = getDatabaseConnection();
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->setCache($cacheKey, $result, $ttl);
            return $result;
        } catch (Exception $e) {
            log_error('Cache query error', ['query' => $query, 'error' => $e->getMessage()]);
            return false;
        }
    }

    // Caché de archivos estáticos
    public function cacheStaticFile($filePath, $contentType = 'text/html') {
        $cacheKey = 'static_' . md5($filePath);

        if ($this->getFromCache($cacheKey)) {
            $cached = $this->getFromCache($cacheKey);
            header('Content-Type: ' . $cached['content_type']);
            header('X-Cache: HIT');
            return $cached['content'];
        }

        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            $this->setCache($cacheKey, [
                'content' => $content,
                'content_type' => $contentType
            ], 3600); // 1 hora

            header('Content-Type: ' . $contentType);
            header('X-Cache: MISS');
            return $content;
        }

        return false;
    }

    // Caché de configuración
    public function cacheConfig($key, $callback, $ttl = 3600) {
        $cacheKey = 'config_' . $key;

        if ($this->getFromCache($cacheKey)) {
            return $this->getFromCache($cacheKey);
        }

        $data = call_user_func($callback);
        $this->setCache($cacheKey, $data, $ttl);
        return $data;
    }

    // Lazy loading de imágenes
    public function lazyLoadImage($src, $alt = '', $class = 'lazy', $placeholder = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PC9zdmc+') {
        return sprintf(
            '<img src="%s" data-src="%s" alt="%s" class="%s" loading="lazy" decoding="async">',
            $placeholder,
            htmlspecialchars($src),
            htmlspecialchars($alt),
            htmlspecialchars($class)
        );
    }

    // Optimización de CSS - Critical CSS
    public function getCriticalCSS($cssFiles) {
        $criticalCSS = '';

        foreach ($cssFiles as $file) {
            if (file_exists($file)) {
                $css = file_get_contents($file);
                // Extraer CSS crítico (simplificado)
                $criticalCSS .= $this->extractCriticalCSS($css);
            }
        }

        return $criticalCSS;
    }

    private function extractCriticalCSS($css) {
        // Extraer solo las reglas CSS críticas para above-the-fold
        $criticalSelectors = [
            'html', 'body', 'h1', 'h2', 'h3', 'p', 'a', 'button',
            'input', 'form', 'header', 'nav', 'main', 'footer',
            '.container', '.row', '.col-', '.btn', '.form-',
            '#header', '#nav', '#main', '#footer'
        ];

        $criticalCSS = '';
        $lines = explode("\n", $css);

        foreach ($lines as $line) {
            foreach ($criticalSelectors as $selector) {
                if (stripos($line, $selector) === 0) {
                    $criticalCSS .= $line . "\n";
                    break;
                }
            }
        }

        return $criticalCSS;
    }

    // Code splitting para JavaScript
    public function splitJavaScript($modules) {
        $output = [];

        foreach ($modules as $name => $files) {
            $output[$name] = [
                'files' => $files,
                'loaded' => false,
                'loading' => false
            ];
        }

        return $output;
    }

    // Preloading de recursos críticos
    public function generatePreloadHeaders($resources) {
        $headers = [];

        foreach ($resources as $resource) {
            $type = $resource['type'];
            $href = $resource['href'];
            $as = $resource['as'] ?? '';

            switch ($type) {
                case 'font':
                    $headers[] = "<link rel=\"preload\" href=\"$href\" as=\"font\" type=\"font/woff2\" crossorigin>";
                    break;
                case 'script':
                    $headers[] = "<link rel=\"preload\" href=\"$href\" as=\"script\">";
                    break;
                case 'style':
                    $headers[] = "<link rel=\"preload\" href=\"$href\" as=\"style\">";
                    break;
                case 'image':
                    $headers[] = "<link rel=\"preload\" href=\"$href\" as=\"image\">";
                    break;
            }
        }

        return implode("\n", $headers);
    }

    // Compresión de respuesta
    public function compressOutput($content) {
        if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
            $content = gzencode($content, 9);
            header('Content-Encoding: gzip');
        }

        return $content;
    }

    // Minificación de HTML
    public function minifyHTML($html) {
        // Remover comentarios HTML
        $html = preg_replace('/<!--(?!<!)[^\[>].*?-->/s', '', $html);

        // Remover espacios en blanco múltiples
        $html = preg_replace('/\s+/', ' ', $html);

        // Remover espacios en blanco entre tags
        $html = preg_replace('/>\s+</', '><', $html);

        return trim($html);
    }

    // Minificación de CSS
    public function minifyCSS($css) {
        // Remover comentarios
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);

        // Remover espacios en blanco
        $css = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $css);

        // Remover espacios innecesarios
        $css = preg_replace('/\s*([{}|:;,>+~])\s*/', '$1', $css);

        return $css;
    }

    // Minificación de JavaScript
    public function minifyJS($js) {
        // Remover comentarios de una línea
        $js = preg_replace('/^\s*\/\/.*$/m', '', $js);

        // Remover comentarios multilínea
        $js = preg_replace('/\/\*.*?\*\//s', '', $js);

        // Remover espacios en blanco
        $js = preg_replace('/\s+/', ' ', $js);

        return trim($js);
    }

    // Database query optimization
    public function optimizeQuery($query, $params = []) {
        $optimized = $query;

        // Agregar EXPLAIN para análisis
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $conn = getDatabaseConnection();
            $stmt = $conn->prepare("EXPLAIN " . $query);
            $stmt->execute($params);
            $explain = $stmt->fetchAll();

            log_debug('Query Analysis', [
                'query' => $query,
                'params' => $params,
                'explain' => $explain
            ]);
        }

        return $optimized;
    }

    // Memory usage tracking
    public function getMemoryUsage() {
        return [
            'current' => memory_get_usage(),
            'peak' => memory_get_peak_usage(),
            'start' => $this->memoryStart,
            'used' => memory_get_usage() - $this->memoryStart
        ];
    }

    // Execution time tracking
    public function getExecutionTime() {
        return microtime(true) - $this->startTime;
    }

    // Performance metrics
    public function getPerformanceMetrics() {
        return [
            'execution_time' => $this->getExecutionTime(),
            'memory_usage' => $this->getMemoryUsage(),
            'cache_stats' => $this->getCacheStats(),
            'database_queries' => $this->getQueryCount()
        ];
    }

    // Cache management
    private function getFromCache($key) {
        switch ($this->cache['type']) {
            case 'apcu':
                return apcu_fetch($key);
            case 'memcached':
                $memcached = new Memcached();
                $memcached->addServer('localhost', 11211);
                return $memcached->get($key);
            case 'redis':
                $redis = new Redis();
                $redis->connect('127.0.0.1', 6379);
                return $redis->get($key);
            default:
                $file = $this->cache['dir'] . '/' . md5($key) . '.cache';
                if (file_exists($file)) {
                    $data = unserialize(file_get_contents($file));
                    if ($data['expires'] > time()) {
                        return $data['content'];
                    } else {
                        unlink($file);
                    }
                }
                return false;
        }
    }

    private function setCache($key, $value, $ttl = 300) {
        $expires = time() + $ttl;

        switch ($this->cache['type']) {
            case 'apcu':
                return apcu_store($key, $value, $ttl);
            case 'memcached':
                $memcached = new Memcached();
                $memcached->addServer('localhost', 11211);
                return $memcached->set($key, $value, $ttl);
            case 'redis':
                $redis = new Redis();
                $redis->connect('127.0.0.1', 6379);
                return $redis->setex($key, $ttl, serialize($value));
            default:
                $file = $this->cache['dir'] . '/' . md5($key) . '.cache';
                $data = ['content' => $value, 'expires' => $expires];
                return file_put_contents($file, serialize($data));
        }
    }

    private function getCacheStats() {
        // Implementar estadísticas de caché
        return [
            'type' => $this->cache['type'],
            'enabled' => true
        ];
    }

    private function getQueryCount() {
        // Implementar contador de consultas
        return 0;
    }

    // Cleanup method
    public function cleanup() {
        // Limpiar archivos de caché expirados
        if ($this->cache['type'] === 'file') {
            $files = glob($this->cache['dir'] . '/*.cache');
            foreach ($files as $file) {
                $data = unserialize(file_get_contents($file));
                if ($data['expires'] < time()) {
                    unlink($file);
                }
            }
        }
    }
}

// Funciones helper
function cache_query($key, $query, $params = [], $ttl = 300) {
    return PerformanceOptimizer::getInstance()->cacheQuery($key, $query, $params, $ttl);
}

function lazy_load_image($src, $alt = '', $class = 'lazy') {
    return PerformanceOptimizer::getInstance()->lazyLoadImage($src, $alt, $class);
}

function get_performance_metrics() {
    return PerformanceOptimizer::getInstance()->getPerformanceMetrics();
}

// Inicializar el optimizador
PerformanceOptimizer::getInstance();
?>