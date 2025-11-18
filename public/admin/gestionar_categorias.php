<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once 'C:/xampp/htdocs/votacion/src/config/database.php';

$database = new Database();
$db = $database->getConnection();

$categorias = [];
$evento = null;
$error = '';
$success = '';

// Mostrar mensajes de éxito/error
if (isset($_GET['success'])) {
    if ($_GET['success'] == 3) {
        $success = "Categoría eliminada exitosamente.";
    }
}

if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// Obtener información del evento
if (isset($_GET['evento_id'])) {
    $evento_id = $_GET['evento_id'];
    
    try {
        // Obtener datos del evento
        $query = "SELECT id, nombre, fecha, estado FROM eventos WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $evento_id);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $evento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Obtener categorías del evento
            $query = "SELECT id, nombre, puntaje_maximo FROM categorias WHERE evento_id = :evento_id ORDER BY nombre";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':evento_id', $evento_id);
            $stmt->execute();
            $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error = "Evento no encontrado.";
        }
    } catch (PDOException $e) {
        $error = "Error al cargar las categorías: " . $e->getMessage();
    }
} else {
    $error = "ID de evento no especificado.";
}

// Procesar agregar categoría
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_categoria']) && $evento) {
    $nombre = trim($_POST['nombre']);
    $puntaje_maximo = 10; // Siempre 10 puntos

    if (empty($nombre)) {
        $error = "El nombre de la categoría es obligatorio.";
    } else {
        try {
            // Verificar si ya existe esta categoría en el evento
            $query = "SELECT id FROM categorias WHERE nombre = :nombre AND evento_id = :evento_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':evento_id', $evento['id']);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $error = "Ya existe una categoría con ese nombre en este evento.";
            } else {
                // Insertar nueva categoría
                $query = "INSERT INTO categorias (nombre, puntaje_maximo, evento_id) VALUES (:nombre, :puntaje_maximo, :evento_id)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':puntaje_maximo', $puntaje_maximo);
                $stmt->bindParam(':evento_id', $evento['id']);
                
                if ($stmt->execute()) {
                    $success = "Categoría '$nombre' agregada exitosamente.";
                    // Recargar categorías
                    $query = "SELECT id, nombre, puntaje_maximo FROM categorias WHERE evento_id = :evento_id ORDER BY nombre";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':evento_id', $evento['id']);
                    $stmt->execute();
                    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $error = "Error al agregar la categoría.";
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
    <title>Gestionar Categorías - Sistema de Votación</title>
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
            max-width: 1000px;
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
        }
        
        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 2.5rem;
            background: linear-gradient(135deg, #007bff, #28a745);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .header p {
            color: #7f8c8d;
            font-size: 1.1rem;
        }
        
        .evento-info {
            background: linear-gradient(135deg, #e7f3ff, #d1ecf1);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            border-left: 5px solid #007bff;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }
        
        .evento-info h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.4rem;
        }
        
        .evento-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .detail-item {
            background: rgba(255,255,255,0.8);
            padding: 12px;
            border-radius: 10px;
            text-align: center;
        }
        
        .detail-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
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
        
        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #212529;
            box-shadow: 0 4px 15px rgba(255,193,7,0.3);
        }
        
        .btn-warning:hover {
            box-shadow: 0 6px 20px rgba(255,193,7,0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            box-shadow: 0 4px 15px rgba(220,53,69,0.3);
        }
        
        .btn-danger:hover {
            box-shadow: 0 6px 20px rgba(220,53,69,0.4);
        }
        
        .btn-small {
            padding: 8px 16px;
            font-size: 0.85rem;
            border-radius: 8px;
        }
        
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .form-container h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.3rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 20px;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 12px 15px;
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
        }
        
        .puntaje-fijo {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            padding: 12px 15px;
            border-radius: 10px;
            text-align: center;
            font-weight: 600;
        }
        
        .table-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 18px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        td {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            color: #2c3e50;
        }
        
        tr:hover {
            background: rgba(0,123,255,0.03);
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }
        
        .no-data .icon {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
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
        
        .puntaje-badge {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .categoria-id {
            font-weight: 600;
            color: #007bff;
            background: rgba(0,123,255,0.1);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
        }
        
        .categoria-nombre {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.05rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .footer-actions {
            text-align: center;
            margin-top: 20px;
        }
        
        .total-counter {
            background: rgba(255,255,255,0.9);
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            color: #2c3e50;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        @media (max-width: 768px) {
            .actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn {
                justify-content: center;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            th, td {
                padding: 12px 15px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
        
        /* Animaciones */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .table-container, .form-container {
            animation: fadeIn 0.6s ease-out;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ Gestionar Categorías del Evento</h1>
            <p>Agregue, edite o elimine criterios de evaluación</p>
        </div>

        <?php if (!empty($success)): ?>
            <div class="success">
                ✅ <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="error">
                ❌ <strong>Error:</strong> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($evento): ?>
            <div class="evento-info">
                <h3>🎯 <?php echo htmlspecialchars($evento['nombre']); ?></h3>
                <div class="evento-details">
                    <div class="detail-item">
                        <div class="detail-label">📅 Fecha del Evento</div>
                        <div class="detail-value"><?php echo date('d/m/Y', strtotime($evento['fecha'])); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">🔄 Estado</div>
                        <div class="detail-value"><?php echo $evento['estado']; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">🆔 ID del Evento</div>
                        <div class="detail-value">#<?php echo $evento['id']; ?></div>
                    </div>
                </div>
            </div>

            <div class="actions">
                <a href="categorias.php" class="btn">
                    ← Volver a Eventos
                </a>
                <div class="total-counter">
                    📊 <?php echo count($categorias); ?> categorías configuradas
                </div>
            </div>

            <!-- Formulario para agregar categoría -->
            <div class="form-container">
                <h3>➕ Agregar Nueva Categoría</h3>
                <form method="POST" action="">
                    <input type="hidden" name="agregar_categoria" value="1">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nombre">Nombre de la Categoría</label>
                            <input type="text" id="nombre" name="nombre" required 
                                   placeholder="Ej: Elegancia, Simpatía, Carisma">
                        </div>
                        <div class="form-group">
                            <label>Puntaje Máximo</label>
                            <div class="puntaje-fijo">
                                ⭐ 10 puntos
                            </div>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-success" style="height: 46px;">
                                ✅ Agregar Categoría
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Lista de categorías existentes -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre de la Categoría</th>
                            <th>Puntaje Máximo</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categorias)): ?>
                            <tr>
                                <td colspan="4" class="no-data">
                                    <span class="icon">📝</span>
                                    <h3>Este evento no tiene categorías</h3>
                                    <p>¡Use el formulario superior para crear la primera categoría!</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categorias as $categoria): ?>
                            <tr>
                                <td>
                                    <span class="categoria-id">#<?php echo $categoria['id']; ?></span>
                                </td>
                                <td>
                                    <div class="categoria-nombre">
                                        <?php echo htmlspecialchars($categoria['nombre']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="puntaje-badge">
                                        ⭐ <?php echo $categoria['puntaje_maximo']; ?> puntos
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="editar_categoria.php?id=<?php echo $categoria['id']; ?>" class="btn btn-warning btn-small">
                                            ✏️ Editar
                                        </a>
                                        <a href="eliminar_categoria.php?id=<?php echo $categoria['id']; ?>" class="btn btn-danger btn-small" 
                                           onclick="return confirm('¿Estás seguro de eliminar la categoría \'<?php echo htmlspecialchars($categoria['nombre']); ?>\'?')">
                                            🗑️ Eliminar
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="footer-actions">
                <a href="categorias.php" class="btn btn-secondary">
                    ← Volver a la Lista de Eventos
                </a>
            </div>

        <?php else: ?>
            <div style="text-align: center; margin-top: 25px;">
                <a href="categorias.php" class="btn">
                    ← Volver a Eventos
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Efectos interactivos
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach((row, index) => {
                if (row.cells.length > 1) {
                    row.style.animationDelay = (index * 0.1) + 's';
                    row.style.animation = 'fadeIn 0.5s ease-out forwards';
                    row.style.opacity = '0';
                    
                    row.addEventListener('mouseenter', function() {
                        this.style.transform = 'scale(1.01)';
                        this.style.boxShadow = '0 5px 15px rgba(0,0,0,0.1)';
                    });
                    
                    row.addEventListener('mouseleave', function() {
                        this.style.transform = 'scale(1)';
                        this.style.boxShadow = 'none';
                    });
                }
            });

            // Efecto en el input del formulario
            const nombreInput = document.getElementById('nombre');
            if (nombreInput) {
                nombreInput.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'translateY(-2px)';
                });
                
                nombreInput.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'translateY(0)';
                });
            }
        });
    </script>
</body>
</html>