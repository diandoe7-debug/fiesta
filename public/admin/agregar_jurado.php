<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// RUTA ABSOLUTA
require_once 'C:/xampp/htdocs/votacion/src/config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $contrasena = $_POST['contrasena'];
    $confirmar_contrasena = $_POST['confirmar_contrasena'];

    // Validaciones
    if (empty($nombre) || empty($correo) || empty($contrasena)) {
        $error = "Todos los campos son obligatorios.";
    } elseif ($contrasena != $confirmar_contrasena) {
        $error = "Las contraseñas no coinciden.";
    } elseif (strlen($contrasena) < 4) {
        $error = "La contraseña debe tener al menos 4 caracteres.";
    } else {
        try {
            // Verificar si el correo ya existe
            $query = "SELECT id FROM usuarios WHERE correo = :correo";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':correo', $correo);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $error = "El correo electrónico ya está registrado.";
            } else {
                // Insertar nuevo jurado
                $query = "INSERT INTO usuarios (nombre, correo, contraseña, rol) VALUES (:nombre, :correo, :contrasena, 'jurado')";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':correo', $correo);
                $stmt->bindParam(':contrasena', $contrasena);
                
                if ($stmt->execute()) {
                    // Redirigir a la lista de jurados después de agregar
                    header("Location: jurados.php?success=1");
                    exit();
                } else {
                    $error = "Error al agregar el jurado.";
                }
            }
        } catch (PDOException $e) {
            $error = "Error de base de datos: " . $e->getMessage();
        }
    }
}

// Mostrar mensaje de éxito si viene de redirección
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = "Jurado agregado exitosamente.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Jurado - Sistema de Votación</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            text-align: center;
        }
        
        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 2.2rem;
            background: linear-gradient(135deg, #007bff, #28a745);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .header p {
            color: #7f8c8d;
            font-size: 1.1rem;
        }
        
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 1rem;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.8);
        }
        
        input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
            background: white;
            transform: translateY(-2px);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 24px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
            font-size: 1rem;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,123,255,0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            box-shadow: 0 4px 15px rgba(40,167,69,0.3);
        }
        
        .btn-success:hover {
            box-shadow: 0 6px 20px rgba(40,167,69,0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            box-shadow: 0 4px 15px rgba(108,117,125,0.3);
        }
        
        .btn-secondary:hover {
            box-shadow: 0 6px 20px rgba(108,117,125,0.4);
        }
        
        .error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 5px solid #dc3545;
            box-shadow: 0 4px 15px rgba(220,53,69,0.1);
        }
        
        .success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 5px solid #28a745;
            box-shadow: 0 4px 15px rgba(40,167,69,0.1);
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .left-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .right-actions {
            display: flex;
            gap: 10px;
        }
        
        .password-strength {
            margin-top: 8px;
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .form-note {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            border-left: 4px solid #007bff;
        }
        
        .form-note h4 {
            color: #0056b3;
            margin-bottom: 5px;
        }
        
        .form-note p {
            color: #495057;
            font-size: 0.9rem;
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .left-actions, .right-actions {
                justify-content: center;
                width: 100%;
            }
            
            .btn {
                flex: 1;
                justify-content: center;
                min-width: 140px;
            }
        }
        
        /* Animaciones */
        @keyframes slideIn {
            from { 
                opacity: 0; 
                transform: translateY(30px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        
        .form-container {
            animation: slideIn 0.6s ease-out;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧑‍⚖️ Agregar Nuevo Jurado</h1>
            <p>Complete los datos del nuevo usuario jurado del sistema</p>
        </div>

        <div class="form-container">
            <?php if (!empty($error)): ?>
                <div class="error">
                    ❌ <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="success">
                    ✅ <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="juradoForm">
                <div class="form-group">
                    <label for="nombre">👤 Nombre Completo:</label>
                    <input type="text" id="nombre" name="nombre" 
                           value="<?php echo isset($nombre) ? htmlspecialchars($nombre) : ''; ?>" 
                           placeholder="Ingrese el nombre completo del jurado" required>
                </div>

                <div class="form-group">
                    <label for="correo">📧 Correo Electrónico:</label>
                    <input type="email" id="correo" name="correo" 
                           value="<?php echo isset($correo) ? htmlspecialchars($correo) : ''; ?>" 
                           placeholder="correo@ejemplo.com" required>
                </div>

                <div class="form-group">
                    <label for="contrasena">🔒 Contraseña:</label>
                    <input type="password" id="contrasena" name="contrasena" 
                           placeholder="Mínimo 4 caracteres" required
                           oninput="checkPasswordStrength(this.value)">
                    <div class="password-strength" id="passwordStrength">
                        🔓 La contraseña debe tener al menos 4 caracteres
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirmar_contrasena">✅ Confirmar Contraseña:</label>
                    <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" 
                           placeholder="Repita la contraseña" required
                           oninput="checkPasswordMatch()">
                    <div class="password-strength" id="passwordMatch">
                        🔄 Las contraseñas deben coincidir
                    </div>
                </div>

                <div class="form-note">
                    <h4>📋 Información importante:</h4>
                    <p>• El jurado recibirá el rol automáticamente<br>
                       • Podrá acceder al sistema con su correo y contraseña<br>
                       • Podrá votar en los eventos activos asignados</p>
                </div>

                <div class="form-actions">
                    <div class="left-actions">
                        <a href="dashboard.php" class="btn btn-secondary">
                            🏠 Panel Principal
                        </a>
                        <a href="jurados.php" class="btn btn-secondary">
                            📋 Lista de Jurados
                        </a>
                    </div>
                    <div class="right-actions">
                        <button type="submit" class="btn btn-success">
                            ✅ Agregar Jurado
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function checkPasswordStrength(password) {
            const strengthElement = document.getElementById('passwordStrength');
            if (password.length === 0) {
                strengthElement.innerHTML = '🔓 La contraseña debe tener al menos 4 caracteres';
                strengthElement.style.color = '#6c757d';
            } else if (password.length < 4) {
                strengthElement.innerHTML = '❌ Muy corta (mínimo 4 caracteres)';
                strengthElement.style.color = '#dc3545';
            } else if (password.length < 6) {
                strengthElement.innerHTML = '⚠️ Contraseña aceptable';
                strengthElement.style.color = '#ffc107';
            } else {
                strengthElement.innerHTML = '✅ Contraseña segura';
                strengthElement.style.color = '#28a745';
            }
        }

        function checkPasswordMatch() {
            const password = document.getElementById('contrasena').value;
            const confirmPassword = document.getElementById('confirmar_contrasena').value;
            const matchElement = document.getElementById('passwordMatch');
            
            if (confirmPassword.length === 0) {
                matchElement.innerHTML = '🔄 Las contraseñas deben coincidir';
                matchElement.style.color = '#6c757d';
            } else if (password !== confirmPassword) {
                matchElement.innerHTML = '❌ Las contraseñas no coinciden';
                matchElement.style.color = '#dc3545';
            } else {
                matchElement.innerHTML = '✅ Las contraseñas coinciden';
                matchElement.style.color = '#28a745';
            }
        }

        // Validación del formulario antes de enviar
        document.getElementById('juradoForm').addEventListener('submit', function(e) {
            const password = document.getElementById('contrasena').value;
            const confirmPassword = document.getElementById('confirmar_contrasena').value;
            
            if (password.length < 4) {
                e.preventDefault();
                alert('❌ La contraseña debe tener al menos 4 caracteres');
                return false;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('❌ Las contraseñas no coinciden');
                return false;
            }
        });
    </script>
</body>
</html>