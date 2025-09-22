<?php
// Simple PHP test
echo "PHP is working!\n";
echo "Current time: " . date('Y-m-d H:i:s') . "\n";
echo "Server: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "PHP Version: " . phpversion() . "\n";

// Test session
session_start();
$_SESSION['test'] = 'session_works';
echo "Session test: " . $_SESSION['test'] . "\n";

// Test database connection without global connection
try {
    $config = [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'dbname' => getenv('DB_NAME') ?: 'u279456972_saas_medic',
        'username' => getenv('DB_USER') ?: 'u279456972_saas_admin',
        'password' => getenv('DB_PASS') ?: 'P&AD3signs'
    ];

    $conn = new mysqli($config['host'], $config['username'], $config['password'], $config['dbname']);

    if ($conn->connect_error) {
        echo "Database connection failed: " . $conn->connect_error . "\n";
    } else {
        echo "Database connection successful!\n";
        if (isset($conn)) {
            $conn->close();
        }
    }
} catch (Exception $e) {
    echo "Database exception: " . $e->getMessage() . "\n";
}

echo "Test completed!\n";
?>