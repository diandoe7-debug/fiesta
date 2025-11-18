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
$evento = null;

// Primero obtener información del evento para mostrar en la confirmación
if (isset($_GET['id'])) {
    $evento_id = $_GET['id'];
    
    try {
        // Obtener datos del evento para mostrar
        $query = "SELECT id, nombre, fecha FROM eventos WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $evento_id);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $evento = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = "Evento no encontrado.";
        }
    } catch (PDOException $e) {
        $error = "Error al cargar el evento: " . $e->getMessage();
    }
} else {
    $error = "ID de evento no especificado.";
}

// Procesar eliminación si se confirma
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar'])) {
    $evento_id = $_POST['evento_id'];
    
    try {
        // Verificar que el evento existe
        $query = "SELECT nombre FROM eventos WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $evento_id);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $evento_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $nombre_evento = $evento_data['nombre'];
            
            // Eliminar el evento (las categorías y participantes se eliminarán en cascada)
            $query = "DELETE FROM eventos WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $evento_id);
            
            if ($stmt->execute()) {
                header("Location: eventos.php?success=3");
                exit();
            } else {
                $error = "Error al eliminar el evento.";
            }
        } else {
            $error = "Evento no encontrado.";
        }
    } catch (PDOException $e) {
        $error = "Error de base de datos: " . $e->getMessage();
    }
}

// Si hay error al cargar, redirigir
if (!empty($error) && !$evento) {
    header("Location: eventos.php?error=" . urlencode($error));
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Eliminación - Sistema de Votación</title>
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
            max-width: 500px;
            width: 100%;
        }
        
        .confirmation-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            text-align: center;
        }
        
        .warning-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            display: block;
        }
        
        .confirmation-card h1 {
            color: #dc3545;
            margin-bottom: 15px;
            font-size: 1.8rem;
        }
        
        .confirmation-card p {
            color: #6c757d;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .evento-info {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 4px solid #ffc107;
        }
        
        .evento-nombre {
            font-size: 1.3rem;
            font-weight: bold;
            color: #856404;
            margin-bottom: 8px;
        }
        
        .evento-details {
            display: flex;
            justify-content: space-around;
            margin-top: 15px;
        }
        
        .detail-item {
            text-align: center;
        }
        
        .detail-label {
            font-size: 0.8rem;
            color: #856404;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-weight: 600;
            color: #856404;
        }
        
        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            box-shadow: 0 4px 15px rgba(220,53,69,0.3);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220,53,69,0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
            box-shadow: 0 4px 15px rgba(108,117,125,0.3);
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108,117,125,0.4);
        }
        
        .error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 5px solid #dc3545;
        }
        
        .consequences {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: left;
        }
        
        .consequences h4 {
            color: #dc3545;
            margin-bottom: 10px;
        }
        
        .consequences ul {
            color: #6c757d;
            padding-left: 20px;
        }
        
        .consequences li {
            margin-bottom: 5px;
        }
        
        @media (max-width: 480px) {
            .confirmation-card {
                padding: 30px 20px;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!empty($error)): ?>
            <div class="confirmation-card">
                <div class="error">
                    ❌ <strong>Error:</strong> <?php echo $error; ?>
                </div>
                <div style="margin-top: 20px;">
                    <a href="eventos.php" class="btn btn-secondary">
                        ← Volver a Eventos
                    </a>
                </div>
            </div>
        <?php elseif ($evento): ?>
            <div class="confirmation-card">
                <span class="warning-icon">⚠️</span>
                <h1>Confirmar Eliminación</h1>
                <p>¿Estás seguro de que deseas eliminar este evento? Esta acción no se puede deshacer.</p>
                
                <div class="evento-info">
                    <div class="evento-nombre"><?php echo htmlspecialchars($evento['nombre']); ?></div>
                    <div class="evento-details">
                        <div class="detail-item">
                            <div class="detail-label">📅 Fecha</div>
                            <div class="detail-value"><?php echo date('d/m/Y', strtotime($evento['fecha'])); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">🆔 ID</div>
                            <div class="detail-value">#<?php echo $evento['id']; ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="consequences">
                    <h4>⚠️ Esta acción eliminará también:</h4>
                    <ul>
                        <li>Todas las categorías asociadas al evento</li>
                        <li>Todos los participantes inscritos</li>
                        <li>Todos los registros de votación</li>
                        <li>Todos los resultados y estadísticas</li>
                    </ul>
                </div>
                
                <form method="POST" class="actions">
                    <input type="hidden" name="evento_id" value="<?php echo $evento['id']; ?>">
                    <button type="submit" name="confirmar" value="1" class="btn btn-danger">
                        🗑️ Sí, Eliminar Evento
                    </button>
                    <a href="eventos.php" class="btn btn-secondary">
                        ✋ Cancelar
                    </a>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>