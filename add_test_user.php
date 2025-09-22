<?php
// Script to add a test user to the database for testing purposes

require_once 'db_connect.php';

// Test user credentials
$testUsername = 'testdoctor';
$testEmail = 'test@doctor.com';
$testPassword = 'TestPass123!';
$testName = 'Dr. Test User';
$testSpecialization = 'Medicina General';

// Hash the password using the same method as registration
$hashedPassword = password_hash($testPassword, PASSWORD_ARGON2ID);

// Check if user already exists
$stmt = $conn->prepare("SELECT id FROM u279456972_saas_medic_doctors WHERE name = ? OR email = ?");
$stmt->bind_param("ss", $testUsername, $testEmail);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "Test user already exists!\n";
    echo "Username: $testUsername\n";
    echo "Email: $testEmail\n";
    echo "Password: $testPassword\n";
    $stmt->close();
    if (isset($conn)) {
        $conn->close();
    }
    exit();
}

$stmt->close();

// Insert the test user
$stmt = $conn->prepare("INSERT INTO u279456972_saas_medic_doctors (name, email, password, specialization, license) VALUES (?, ?, ?, ?, '')");
$stmt->bind_param("ssss", $testUsername, $testEmail, $hashedPassword, $testSpecialization);

if ($stmt->execute()) {
    echo "Test user created successfully!\n";
    echo "Username: $testUsername\n";
    echo "Email: $testEmail\n";
    echo "Password: $testPassword\n";
    echo "Specialization: $testSpecialization\n";
    echo "\nYou can now login at login.html with these credentials.\n";
} else {
    echo "Error creating test user: " . $stmt->error . "\n";
}

$stmt->close();
if (isset($conn)) {
    $conn->close();
}
?>