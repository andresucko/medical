<?php
// API para operaciones con recetas

require_once 'session_manager.php';
require_once 'db_connect.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Obtener recetas del doctor actual
            $user = getCurrentUser();
            if (!$user || !isset($user['id'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Usuario no encontrado']);
                exit();
            }

            $stmt = $conn->prepare("
                SELECT p.id, p.medicamento, p.dosis, p.frecuencia, p.duracion, p.created_at,
                       pat.nombre as paciente_nombre, pat.id as paciente_id
                FROM prescriptions p
                JOIN patients pat ON p.patient_id = pat.id
                WHERE p.doctor_id = ?
                ORDER BY p.created_at DESC
            ");

            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $result = $stmt->get_result();

            $prescriptions = [];
            while ($row = $result->fetch_assoc()) {
                $prescriptions[] = [
                    'id' => (int)$row['id'],
                    'paciente_id' => (int)$row['paciente_id'],
                    'paciente_nombre' => $row['paciente_nombre'],
                    'medicamento' => $row['medicamento'],
                    'dosis' => $row['dosis'],
                    'frecuencia' => $row['frecuencia'],
                    'duracion' => (int)$row['duracion'],
                    'created_at' => $row['created_at']
                ];
            }

            echo json_encode(['success' => true, 'prescriptions' => $prescriptions]);
            break;

        case 'POST':
            // Crear nueva receta
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
                http_response_code(403);
                echo json_encode(['error' => 'Token CSRF inválido']);
                exit();
            }

            $patientId = (int)($input['patient_id'] ?? 0);
            $medicamento = validateString($input['medicamento'] ?? '');
            $dosis = validateString($input['dosis'] ?? '');
            $frecuencia = validateString($input['frecuencia'] ?? '');
            $duracion = (int)($input['duracion'] ?? 0);

            if ($patientId <= 0 || empty($medicamento) || empty($dosis) || empty($frecuencia) || $duracion <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Todos los campos son obligatorios']);
                exit();
            }

            $user = getCurrentUser();

            // Verificar que el paciente pertenece al doctor
            $stmt = $conn->prepare("SELECT id FROM patients WHERE id = ? AND doctor_id = ?");
            $stmt->bind_param("ii", $patientId, $user['id']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Paciente no encontrado']);
                exit();
            }

            $stmt = $conn->prepare("INSERT INTO prescriptions (patient_id, doctor_id, medicamento, dosis, frecuencia, duracion) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssi", $patientId, $user['id'], $medicamento, $dosis, $frecuencia, $duracion);

            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Receta creada exitosamente',
                    'prescription_id' => $conn->insert_id
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error al crear receta']);
            }
            break;

        case 'PUT':
            // Actualizar receta
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
                http_response_code(403);
                echo json_encode(['error' => 'Token CSRF inválido']);
                exit();
            }

            $prescriptionId = (int)($input['id'] ?? 0);
            $patientId = (int)($input['patient_id'] ?? 0);
            $medicamento = validateString($input['medicamento'] ?? '');
            $dosis = validateString($input['dosis'] ?? '');
            $frecuencia = validateString($input['frecuencia'] ?? '');
            $duracion = (int)($input['duracion'] ?? 0);

            if ($prescriptionId <= 0 || $patientId <= 0 || empty($medicamento) || empty($dosis) || empty($frecuencia) || $duracion <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Datos inválidos']);
                exit();
            }

            $user = getCurrentUser();

            $stmt = $conn->prepare("UPDATE prescriptions SET patient_id = ?, medicamento = ?, dosis = ?, frecuencia = ?, duracion = ? WHERE id = ? AND doctor_id = ?");
            $stmt->bind_param("issssii", $patientId, $medicamento, $dosis, $frecuencia, $duracion, $prescriptionId, $user['id']);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Receta actualizada exitosamente']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error al actualizar receta']);
            }
            break;

        case 'DELETE':
            // Eliminar receta
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
                http_response_code(403);
                echo json_encode(['error' => 'Token CSRF inválido']);
                exit();
            }

            $prescriptionId = (int)($input['id'] ?? 0);

            if ($prescriptionId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de receta inválido']);
                exit();
            }

            $user = getCurrentUser();
            $stmt = $conn->prepare("DELETE FROM prescriptions WHERE id = ? AND doctor_id = ?");
            $stmt->bind_param("ii", $prescriptionId, $user['id']);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Receta eliminada exitosamente']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error al eliminar receta']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
    }

} catch (Exception $e) {
    error_log("API Prescriptions error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}

if (isset($conn)) {
    $conn->close();
}
?>