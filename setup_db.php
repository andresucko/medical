<?php
// Script para configurar las tablas de la base de datos

include 'db_connect.php';

// Crear tabla de usuarios para login
$sql_users = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('doctor', 'admin') DEFAULT 'doctor',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Crear tabla de doctores
$sql_doctors = "CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    especialidad VARCHAR(100),
    email VARCHAR(100),
    FOREIGN KEY (user_id) REFERENCES users(id)
)";

// Crear tabla de pacientes
$sql_patients = "CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    telefono VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
)";

// Crear tabla de citas
$sql_appointments = "CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    doctor_id INT,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    motivo TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
)";

// Crear tabla de recetas
$sql_prescriptions = "CREATE TABLE IF NOT EXISTS prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    doctor_id INT,
    medicamento VARCHAR(100) NOT NULL,
    dosis VARCHAR(50),
    frecuencia VARCHAR(100),
    duracion INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
)";

// Crear tabla de notas de pacientes
$sql_notes = "CREATE TABLE IF NOT EXISTS patient_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    nota TEXT NOT NULL,
    fecha DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id)
)";

// Ejecutar las consultas
$queries = [$sql_users, $sql_doctors, $sql_patients, $sql_appointments, $sql_prescriptions, $sql_notes];

// Debug logging para identificar problemas de PowerShell
error_log("DEBUG: Starting database setup queries execution");
error_log("DEBUG: Number of queries to execute: " . count($queries));

foreach ($queries as $index => $query) {
    error_log("DEBUG: Executing query " . ($index + 1) . " of " . count($queries));
    error_log("DEBUG: Query: " . substr($query, 0, 100) . "...");

    if ($conn->query($query) === TRUE) {
        $success_message = "Tabla creada exitosamente<br>";
        error_log("DEBUG: Query executed successfully: " . $success_message);
        echo htmlspecialchars($success_message, ENT_NOQUOTES, 'UTF-8');
    } else {
        $error_message = "Error creando tabla: " . $conn->error . "<br>";
        error_log("DEBUG: Query failed: " . $error_message);
        echo htmlspecialchars($error_message, ENT_NOQUOTES, 'UTF-8');
    }
}

if (isset($conn)) {
    $conn->close();
}
?>