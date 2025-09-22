<?php
// Simple database connection test
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing database connection...\n";

try {
    $config = [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'dbname' => getenv('DB_NAME') ?: 'u279456972_saas_medic',
        'username' => getenv('DB_USER') ?: 'u279456972_saas_admin',
        'password' => getenv('DB_PASS') ?: 'P&AD3signs'
    ];

    echo "Configuration:\n";
    echo "Host: " . $config['host'] . "\n";
    echo "Database: " . $config['dbname'] . "\n";
    echo "Username: " . $config['username'] . "\n";
    echo "Password: " . (empty($config['password']) ? 'EMPTY' : 'SET') . "\n";

    $conn = new mysqli($config['host'], $config['username'], $config['password'], $config['dbname']);

    if ($conn->connect_error) {
        echo "Connection failed: " . $conn->connect_error . "\n";
        exit(1);
    }

    echo "Connection successful!\n";

    // Test a simple query
    $result = $conn->query("SELECT 1 as test");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "Query test successful: " . $row['test'] . "\n";
    } else {
        echo "Query test failed: " . $conn->error . "\n";
    }

    if (isset($conn)) {
        $conn->close();
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Test completed successfully!\n";
?>