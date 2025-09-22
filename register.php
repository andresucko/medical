<?php
// Script para manejar el registro de nuevos usuarios - Versión segura

require_once 'db_connect.php';
require_once 'session_manager.php';

// Función para validar contraseña fuerte
function validatePassword($password) {
    return strlen($password) >= 8 &&
           preg_match('/[A-Z]/', $password) &&
           preg_match('/[a-z]/', $password) &&
           preg_match('/[0-9]/', $password) &&
           preg_match('/[^A-Za-z0-9]/', $password);
}

// Función para verificar si el usuario ya existe
function checkUserExists($conn, $username, $email) {
    $stmt = $conn->prepare("SELECT id FROM u279456972_saas_medic_doctors WHERE name = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token de seguridad inválido";
    } else {
        $username = validateString($_POST['username'] ?? '');
        $email = validateString($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $nombre = validateString($_POST['nombre'] ?? '');
        $especialidad = validateString($_POST['especialidad'] ?? '');

        // Validaciones
        if (empty($username) || empty($email) || empty($password) || empty($nombre)) {
            $error = "Todos los campos obligatorios deben ser completados";
        } elseif (!validateEmail($email)) {
            $error = "El formato del email no es válido";
        } elseif (!validatePassword($password)) {
            $error = "La contraseña debe tener al menos 8 caracteres, incluir mayúsculas, minúsculas, números y símbolos";
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $error = "El nombre de usuario debe tener entre 3 y 50 caracteres";
        } elseif (strlen($nombre) < 2) {
            $error = "El nombre debe tener al menos 2 caracteres";
        } elseif (checkUserExists($conn, $username, $email)) {
            $error = "El usuario o email ya están registrados";
        } else {
            try {
                // Insertar doctor directamente en la tabla existente
                $hashedPassword = password_hash($password, PASSWORD_ARGON2ID);
                $stmt = $conn->prepare("INSERT INTO u279456972_saas_medic_doctors (name, email, password, specialization, license) VALUES (?, ?, ?, ?, '')");
                $stmt->bind_param("ssss", $username, $email, $hashedPassword, $especialidad);

                if ($stmt->execute()) {
                    $success = true;
                    // Redirigir después de un breve delay
                    header("Refresh: 3; URL=login.html");
                } else {
                    $error = "Error al crear la cuenta de doctor";
                }
                $stmt->close();
            } catch (Exception $e) {
                error_log("Registration error: " . $e->getMessage());
                $error = "Error interno del servidor";
            }
        }
    }
}

// Generar token CSRF si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($conn)) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $success ? 'Registro Exitoso' : 'Registro'; ?> - Panel Médico</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <meta name="robots" content="noindex, nofollow">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <?php if ($success): ?>
            <div class="text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">¡Registro Exitoso!</h1>
                <p class="text-gray-600 mb-4">Tu cuenta ha sido creada correctamente. Redirigiendo al inicio de sesión...</p>
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
            </div>
        <?php else: ?>
            <div class="text-center">
                <h1 class="text-2xl font-bold text-center mb-6">Registro</h1>

                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form action="register.php" method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">Usuario</label>
                        <input type="text" id="username" name="username" required
                               class="mt-1 w-full p-2 border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
                               maxlength="50" pattern="[a-zA-Z0-9_]{3,50}" title="Solo letras, números y guiones bajos (3-50 caracteres)">
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" id="email" name="email" required
                               class="mt-1 w-full p-2 border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                        <input type="password" id="password" name="password" required
                               class="mt-1 w-full p-2 border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
                               minlength="8" title="Mínimo 8 caracteres con mayúsculas, minúsculas, números y símbolos">
                    </div>

                    <div>
                        <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre</label>
                        <input type="text" id="nombre" name="nombre" required
                               class="mt-1 w-full p-2 border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
                               maxlength="50" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]{2,50}" title="Solo letras y espacios (2-50 caracteres)">
                    </div>

                    <div>
                        <label for="apellido" class="block text-sm font-medium text-gray-700">Apellido</label>
                        <input type="text" id="apellido" name="apellido" required
                               class="mt-1 w-full p-2 border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
                               maxlength="50" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]{2,50}" title="Solo letras y espacios (2-50 caracteres)">
                    </div>

                    <div>
                        <label for="especialidad" class="block text-sm font-medium text-gray-700">Especialidad</label>
                        <input type="text" id="especialidad" name="especialidad"
                               class="mt-1 w-full p-2 border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
                               maxlength="100" placeholder="Opcional">
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                        Registrarse
                    </button>
                </form>

                <p class="text-center mt-4">
                    ¿Ya tienes cuenta? <a href="login.html" class="text-indigo-600 hover:text-indigo-800">Inicia Sesión</a>
                </p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>