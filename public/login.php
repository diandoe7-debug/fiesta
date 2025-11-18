<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] == 'admin') {
        header("Location: admin/dashboard.php");
        exit();
    } else {
        header("Location: jurado/dashboard.php");
        exit();
    }
}

require_once '../src/config/database.php';

$error = '';
$correo_value = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $correo = trim($_POST['correo']);
    $contrasena = $_POST['contrasena'];
    $correo_value = htmlspecialchars($correo);
    
    if (empty($correo) || empty($contrasena)) {
        $error = "Por favor, complete todos los campos.";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        if ($db) {
            try {
                $query = "SELECT id, nombre, correo, contraseña, rol FROM usuarios WHERE correo = :correo";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':correo', $correo);
                $stmt->execute();
                
                if ($stmt->rowCount() == 1) {
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($contrasena == $user['contraseña']) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_role'] = $user['rol'];
                        $_SESSION['user_name'] = $user['nombre'];
                        
                        if ($user['rol'] == 'admin') {
                            header("Location: admin/dashboard.php");
                        } else {
                            header("Location: jurado/dashboard.php");
                        }
                        exit();
                    } else {
                        $error = "Contraseña incorrecta.";
                    }
                } else {
                    $error = "Usuario no encontrado.";
                }
            } catch (PDOException $e) {
                $error = "Error de base de datos: " . $e->getMessage();
            }
        } else {
            $error = "Error de conexión a la base de datos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Votación</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 1.8rem;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #007bff;
        }
        
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
        }
        
        .system-info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: #333;
            border-left: 4px solid #007bff;
        }
        
        .system-info strong {
            color: #0056b3;
        }
        
        .btn-back {
            display: block;
            text-align: center;
            padding: 12px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .btn-back:hover {
            background: #545b62;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>🔐 Iniciar Sesión</h2>
        
        <?php if ($error != '') { ?>
            <div class="error"><?php echo $error; ?></div>
        <?php } ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="correo">Correo Electrónico:</label>
                <input type="email" id="correo" name="correo" value="<?php echo $correo_value; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="contrasena">Contraseña:</label>
                <input type="password" id="contrasena" name="contrasena" required>
            </div>
            
            <button type="submit">🚀 Ingresar al Sistema</button>
        </form>
        
        <div class="system-info">
            <strong>Credenciales de prueba:</strong><br>
            Admin: admin@test.com / admin123<br>
            Jurado: jurado@test.com / jurado123
        </div>
        
        <a href="index.php" class="btn-back">← Volver al Inicio</a>
    </div>
</body>
</html>