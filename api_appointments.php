<?php
// API para operaciones con citas

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
            // Obtener citas del doctor actual
            $user = getCurrentUser();
            if (!$user || !isset($user['id'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Usuario no encontrado']);
                exit();
            }

            $stmt = $conn->prepare("
                SELECT a.id, a.fecha, a.hora, a.motivo, p.nombre as paciente_nombre, p.id as paciente_id
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                WHERE a.doctor_id = ?
                ORDER BY a.fecha DESC, a.hora ASC
            ");

            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $result = $stmt->get_result();

            $appointments = [];
            while ($row = $result->fetch_assoc()) {
                $appointments[] = [
                    'id' => (int)$row['id'],
                    'fecha' => $row['fecha'],
                    'hora' => $row['hora'],
                    'motivo' => $row['motivo'],
                    'paciente_id' => (int)$row['paciente_id'],
                    'paciente_nombre' => $row['paciente_nombre']
                ];
            }

            echo json_encode(['success' => true, 'appointments' => $appointments]);
            break;

        case 'POST':
            // Crear nueva cita
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
                http_response_code(403);
                echo json_encode(['error' => 'Token CSRF inválido']);
                exit();
            }

            $patientId = (int)($input['patient_id'] ?? 0);
            $fecha = validateString($input['fecha'] ?? '');
            $hora = validateString($input['hora'] ?? '');
            $motivo = validateString($input['motivo'] ?? '');

            if ($patientId <= 0 || empty($fecha) || empty($hora) || empty($motivo)) {
                http_response_code(400);
                echo json_encode(['error' => 'Todos los campos son obligatorios']);
                exit();
            }

            // Validar formato de fecha y hora
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                http_response_code(400);
                echo json_encode(['error' => 'Formato de fecha inválido (YYYY-MM-DD)']);
                exit();
            }

            if (!preg_match('/^\d{2}:\d{2}$/', $hora)) {
                http_response_code(400);
                echo json_encode(['error' => 'Formato de hora inválido (HH:MM)']);
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

            $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, fecha, hora, motivo) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisss", $patientId, $user['id'], $fecha, $hora, $motivo);

            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Cita creada exitosamente',
                    'appointment_id' => $conn->insert_id
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error al crear cita']);
            }
            break;

        case 'PUT':
            // Actualizar cita
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
                http_response_code(403);
                echo json_encode(['error' => 'Token CSRF inválido']);
                exit();
            }

            $appointmentId = (int)($input['id'] ?? 0);
            $patientId = (int)($input['patient_id'] ?? 0);
            $fecha = validateString($input['fecha'] ?? '');
            $hora = validateString($input['hora'] ?? '');
            $motivo = validateString($input['motivo'] ?? '');

            if ($appointmentId <= 0 || $patientId <= 0 || empty($fecha) || empty($hora) || empty($motivo)) {
                http_response_code(400);
                echo json_encode(['error' => 'Datos inválidos']);
                exit();
            }

            $user = getCurrentUser();

            $stmt = $conn->prepare("UPDATE appointments SET patient_id = ?, fecha = ?, hora = ?, motivo = ? WHERE id = ? AND doctor_id = ?");
            $stmt->bind_param("isssii", $patientId, $fecha, $hora, $motivo, $appointmentId, $user['id']);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Cita actualizada exitosamente']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error al actualizar cita']);
            }
            break;

        case 'DELETE':
            // Eliminar cita
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
                http_response_code(403);
                echo json_encode(['error' => 'Token CSRF inválido']);
                exit();
            }

            $appointmentId = (int)($input['id'] ?? 0);

            if ($appointmentId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de cita inválido']);
                exit();
            }

            $user = getCurrentUser();
            $stmt = $conn->prepare("DELETE FROM appointments WHERE id = ? AND doctor_id = ?");
            $stmt->bind_param("ii", $appointmentId, $user['id']);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Cita eliminada exitosamente']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error al eliminar cita']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
    }

} catch (Exception $e) {
    error_log("API Appointments error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}

if (isset($conn)) {
    $conn->close();
}
?>