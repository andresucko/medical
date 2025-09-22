<?php
// API para operaciones con pacientes

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
            // Obtener todos los pacientes del doctor actual
            $user = getCurrentUser();
            if (!$user || !isset($user['id'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Usuario no encontrado']);
                exit();
            }

            $stmt = $conn->prepare("
                SELECT p.id, p.nombre, p.email, p.telefono, p.created_at,
                       GROUP_CONCAT(pn.nota SEPARATOR '||') as notas,
                       GROUP_CONCAT(pn.fecha SEPARATOR '||') as fechas_notas
                FROM patients p
                LEFT JOIN patient_notes pn ON p.id = pn.patient_id
                WHERE p.doctor_id = ?
                GROUP BY p.id, p.nombre, p.email, p.telefono, p.created_at
                ORDER BY p.created_at DESC
            ");

            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $result = $stmt->get_result();

            $patients = [];
            while ($row = $result->fetch_assoc()) {
                $notas = [];
                if ($row['notas']) {
                    $notas_text = explode('||', $row['notas']);
                    $fechas = explode('||', $row['fechas_notas']);
                    for ($i = 0; $i < count($notas_text); $i++) {
                        $notas[] = [
                            'texto' => $notas_text[$i],
                            'fecha' => $fechas[$i] ?? date('Y-m-d')
                        ];
                    }
                }

                $patients[] = [
                    'id' => (int)$row['id'],
                    'nombre' => $row['nombre'],
                    'email' => $row['email'],
                    'telefono' => $row['telefono'],
                    'notas' => $notas
                ];
            }

            echo json_encode(['success' => true, 'patients' => $patients]);
            break;

        case 'POST':
            // Crear nuevo paciente
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
                http_response_code(403);
                echo json_encode(['error' => 'Token CSRF inválido']);
                exit();
            }

            $nombre = validateString($input['nombre'] ?? '');
            $email = validateString($input['email'] ?? '');
            $telefono = validateString($input['telefono'] ?? '');

            if (empty($nombre) || empty($email)) {
                http_response_code(400);
                echo json_encode(['error' => 'Nombre y email son obligatorios']);
                exit();
            }

            if (!validateEmail($email)) {
                http_response_code(400);
                echo json_encode(['error' => 'Email inválido']);
                exit();
            }

            $user = getCurrentUser();
            $stmt = $conn->prepare("INSERT INTO patients (doctor_id, nombre, email, telefono) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $user['id'], $nombre, $email, $telefono);

            if ($stmt->execute()) {
                $patientId = $conn->insert_id;
                echo json_encode([
                    'success' => true,
                    'message' => 'Paciente creado exitosamente',
                    'patient_id' => $patientId
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error al crear paciente']);
            }
            break;

        case 'PUT':
            // Actualizar paciente
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
                http_response_code(403);
                echo json_encode(['error' => 'Token CSRF inválido']);
                exit();
            }

            $patientId = (int)($input['id'] ?? 0);
            $nombre = validateString($input['nombre'] ?? '');
            $email = validateString($input['email'] ?? '');
            $telefono = validateString($input['telefono'] ?? '');

            if ($patientId <= 0 || empty($nombre) || empty($email)) {
                http_response_code(400);
                echo json_encode(['error' => 'Datos inválidos']);
                exit();
            }

            $user = getCurrentUser();
            $stmt = $conn->prepare("UPDATE patients SET nombre = ?, email = ?, telefono = ? WHERE id = ? AND doctor_id = ?");
            $stmt->bind_param("sssii", $nombre, $email, $telefono, $patientId, $user['id']);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Paciente actualizado exitosamente']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error al actualizar paciente']);
            }
            break;

        case 'DELETE':
            // Eliminar paciente
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
                http_response_code(403);
                echo json_encode(['error' => 'Token CSRF inválido']);
                exit();
            }

            $patientId = (int)($input['id'] ?? 0);

            if ($patientId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de paciente inválido']);
                exit();
            }

            $user = getCurrentUser();
            $conn->begin_transaction();

            try {
                // Eliminar notas del paciente
                $stmt = $conn->prepare("DELETE FROM patient_notes WHERE patient_id = ?");
                $stmt->bind_param("i", $patientId);
                $stmt->execute();

                // Eliminar citas del paciente
                $stmt = $conn->prepare("DELETE FROM appointments WHERE patient_id = ?");
                $stmt->bind_param("i", $patientId);
                $stmt->execute();

                // Eliminar recetas del paciente
                $stmt = $conn->prepare("DELETE FROM prescriptions WHERE patient_id = ?");
                $stmt->bind_param("i", $patientId);
                $stmt->execute();

                // Eliminar paciente
                $stmt = $conn->prepare("DELETE FROM patients WHERE id = ? AND doctor_id = ?");
                $stmt->bind_param("ii", $patientId, $user['id']);

                if ($stmt->execute()) {
                    $conn->commit();
                    echo json_encode(['success' => true, 'message' => 'Paciente eliminado exitosamente']);
                } else {
                    throw new Exception('Error al eliminar paciente');
                }
            } catch (Exception $e) {
                $conn->rollback();
                http_response_code(500);
                echo json_encode(['error' => 'Error al eliminar paciente']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
    }

} catch (Exception $e) {
    error_log("API Patients error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}

if (isset($conn)) {
    $conn->close();
}
?>