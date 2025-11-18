<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once 'C:/xampp/htdocs/votacion/src/config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Obtener datos de la categoría a editar
$categoria = null;
$evento = null;
if (isset($_GET['id'])) {
    try {
        $query = "SELECT c.id, c.nombre, c.puntaje_maximo, c.evento_id, e.nombre as evento_nombre 
                 FROM categorias c 
                 JOIN eventos e ON c.evento_id = e.id 
                 WHERE c.id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $_GET['id']);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
            $evento = [
                'id' => $categoria['evento_id'],
                'nombre' => $categoria['evento_nombre']
            ];
        } else {
            $error = "Categoría no encontrada.";
        }
    } catch (PDOException $e) {
        $error = "Error al cargar la categoría: " . $e->getMessage();
    }
} else {
    $error = "ID de categoría no especificado.";
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $categoria) {
    $nombre = trim($_POST['nombre']);
    $puntaje_maximo = 10; // Siempre 10 puntos

    // Validaciones
    if (empty($nombre)) {
        $error = "El nombre de la categoría es obligatorio.";
    } else {
        try {
            // Verificar si el nombre ya existe en otro evento
            $query = "SELECT id FROM categorias WHERE nombre = :nombre AND evento_id = :evento_id AND id != :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':evento_id', $categoria['evento_id']);
            $stmt->bindParam(':id', $categoria['id']);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $error = "Ya existe una categoría con ese nombre en este evento.";
            } else {
                // Actualizar categoría
                $query = "UPDATE categorias SET nombre = :nombre, puntaje_maximo = :puntaje_maximo WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':puntaje_maximo', $puntaje_maximo);
                $stmt->bindParam(':id', $categoria['id']);
                
                if ($stmt->execute()) {
                    $success = "Categoría actualizada exitosamente.";
                    // Actualizar datos locales
                    $categoria['nombre'] = $nombre;
                } else {
                    $error = "Error al actualizar la categoría.";
                }
            }
        } catch (PDOException $e) {
            $error = "Error de base de datos: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Categoría - Sistema de Votación</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            max-width: 600px;
            width: 100%;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 20px 20px 0 0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-bottom: none;
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
            border-radius: 0 0 20px 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-top: none;
        }
        
        .evento-info {
            background: linear-gradient(135deg, #e7f3ff, #d1ecf1);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 5px solid #007bff;
        }
        
        .evento-info h3 {
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 1.3rem;
        }
        
        .current-info {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
            text-align: center;
            font-weight: 600;
            color: #856404;
        }
        
        .puntaje-info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid #17a2b8;
            text-align: center;
            font-weight: 500;
            color: #0c5460;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 1rem;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.9);
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
            transform: translateY(-2px);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
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
        
        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #212529;
            box-shadow: 0 4px 15px rgba(255,193,7,0.3);
        }
        
        .btn-warning:hover {
            box-shadow: 0 6px 20px rgba(255,193,7,0.4);
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
            gap: 15px;
            justify-content: space-between;
            flex-wrap: wrap;
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
        
        .form-help {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 8px;
            font-style: italic;
        }
        
        .puntaje-fijo {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .left-actions, .right-actions {
                width: 100%;
                justify-content: center;
            }
            
            .btn {
                flex: 1;
                justify-content: center;
            }
        }
        
        /* Animaciones */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-container {
            animation: fadeIn 0.6s ease-out;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✏️ Editar Categoría</h1>
            <p>Modifique los datos de la categoría de evaluación</p>
        </div>

        <div class="form-container">
            <?php if (!empty($error)): ?>
                <div class="error">
                    ❌ <strong>Error:</strong> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="success">
                    ✅ <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if ($categoria && $evento): ?>
                <div class="evento-info">
                    <h3>🎯 <?php echo htmlspecialchars($evento['nombre']); ?></h3>
                    <div style="color: #6c757d; font-size: 0.9rem;">
                        ID del Evento: #<?php echo $evento['id']; ?>
                    </div>
                </div>

                <div class="current-info">
                    📝 Editando categoría: <strong>#<?php echo $categoria['id']; ?></strong>
                </div>

                <div class="puntaje-info">
                    ⚠️ <strong>Información:</strong> El puntaje máximo está configurado en <strong>10 puntos</strong> para todas las categorías.
                </div>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="nombre">🏷️ Nombre de la Categoría</label>
                        <input type="text" id="nombre" name="nombre" 
                               value="<?php echo htmlspecialchars($categoria['nombre']); ?>" 
                               required 
                               placeholder="Ej: Elegancia, Simpatía, Carisma">
                        <div class="form-help">Nombre descriptivo del criterio de evaluación</div>
                    </div>

                    <div class="form-group">
                        <label>⭐ Puntaje Máximo</label>
                        <div class="puntaje-fijo">
                            🎯 10 puntos (configuración fija del sistema)
                        </div>
                        <div class="form-help">Todas las categorías usan escala del 1 al 10</div>
                    </div>

                    <div class="form-actions">
                        <div class="left-actions">
                            <a href="gestionar_categorias.php?evento_id=<?php echo $evento['id']; ?>" class="btn btn-secondary">
                                ← Volver
                            </a>
                            <a href="categorias.php" class="btn btn-secondary">
                                📋 Todos los Eventos
                            </a>
                        </div>
                        <div class="right-actions">
                            <button type="submit" class="btn btn-warning">
                                💾 Guardar Cambios
                            </button>
                        </div>
                    </div>
                </form>

            <?php else: ?>
                <div class="error">
                    ❌ No se puede cargar la información de la categoría.
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="categorias.php" class="btn">
                        📋 Volver a la gestión de categorías
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Efectos interactivos
        document.addEventListener('DOMContentLoaded', function() {
            const nombreInput = document.getElementById('nombre');
            if (nombreInput) {
                nombreInput.addEventListener('focus', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 8px 25px rgba(0,123,255,0.15)';
                });
                
                nombreInput.addEventListener('blur', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = 'none';
                });
            }

            // Prevenir envío duplicado del formulario
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.innerHTML = '⏳ Guardando...';
                        submitBtn.disabled = true;
                    }
                });
            }
        });
    </script>
</body>
</html>