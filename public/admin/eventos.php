<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once 'C:/xampp/htdocs/votacion/src/config/database.php';

$database = new Database();
$db = $database->getConnection();

$eventos = [];
$error = '';
$success = '';

// Mostrar mensajes de éxito
if (isset($_GET['success'])) {
    if ($_GET['success'] == 1) {
        $success = "Evento creado exitosamente.";
    } elseif ($_GET['success'] == 2) {
        $success = "Evento actualizado exitosamente.";
    } elseif ($_GET['success'] == 3) {
        $success = "Evento eliminado exitosamente.";
    }
}

// Mostrar mensajes de error
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

if ($db) {
    try {
        $query = "SELECT id, nombre, fecha, descripcion, estado FROM eventos ORDER BY fecha DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error al cargar los eventos: " . $e->getMessage();
    }
} else {
    $error = "No se pudo conectar a la base de datos.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Eventos - Sistema de Votación</title>
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
            max-width: 1400px;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #007bff;
            display: block;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.95rem;
            color: #7f8c8d;
            font-weight: 500;
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
            box-shadow: 0 4px 15px rgba(255,193,7,0.3);
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        
        .btn-info {
            background: linear-gradient(135deg, #17a2b8, #138496);
            box-shadow: 0 4px 15px rgba(23,162,184,0.3);
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            box-shadow: 0 4px 15px rgba(220,53,69,0.3);
            padding: 6px 12px;
            font-size: 0.85rem;
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
        
        .estado-activo {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .estado-cerrado {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .descripcion {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #6c757d;
        }
        
        .evento-nombre {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.05rem;
        }
        
        .evento-fecha {
            color: #007bff;
            font-weight: 500;
        }
        
        .actions-cell {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .footer-actions {
            text-align: center;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn {
                justify-content: center;
            }
            
            th, td {
                padding: 12px 15px;
            }
            
            .actions-cell {
                flex-direction: column;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            .descripcion {
                max-width: 150px;
            }
        }
        
        /* Animaciones */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .table-container {
            animation: fadeIn 0.6s ease-out;
        }
        
        .evento-id {
            font-weight: 600;
            color: #007bff;
            background: rgba(0,123,255,0.1);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Gestión de Eventos</h1>
            <p>Administra los concursos y eventos de votación del sistema</p>
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

        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number"><?php echo count($eventos); ?></span>
                <span class="stat-label">Total Eventos</span>
            </div>
            <div class="stat-card">
                <span class="stat-number">
                    <?php 
                        $activos = array_filter($eventos, function($evento) {
                            return $evento['estado'] == 'Activo';
                        });
                        echo count($activos);
                    ?>
                </span>
                <span class="stat-label">Eventos Activos</span>
            </div>
            <div class="stat-card">
                <span class="stat-number">
                    <?php 
                        $cerrados = array_filter($eventos, function($evento) {
                            return $evento['estado'] == 'Cerrado';
                        });
                        echo count($cerrados);
                    ?>
                </span>
                <span class="stat-label">Eventos Cerrados</span>
            </div>
            <div class="stat-card">
                <span class="stat-number">100%</span>
                <span class="stat-label">Sistema Listo</span>
            </div>
        </div>

        <div class="actions">
            <a href="dashboard.php" class="btn">
                ← Volver al Panel
            </a>
            <a href="agregar_evento.php" class="btn btn-success">
                ➕ Crear Nuevo Evento
            </a>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre del Evento</th>
                        <th>Fecha</th>
                        <th>Descripción</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($eventos) && empty($error)): ?>
                        <tr>
                            <td colspan="6" class="no-data">
                                <span class="icon">📅</span>
                                <h3>No hay eventos creados</h3>
                                <p>Comienza creando el primer evento del sistema</p>
                                <a href="agregar_evento.php" class="btn btn-success" style="margin-top: 15px;">
                                    ➕ Crear Primer Evento
                                </a>
                            </td>
                        </tr>
                    <?php elseif (!empty($error)): ?>
                        <tr>
                            <td colspan="6" class="no-data">
                                <span class="icon">❌</span>
                                <h3>Error de conexión</h3>
                                <p>No se pueden cargar los datos en este momento</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($eventos as $evento): ?>
                        <tr>
                            <td>
                                <span class="evento-id">#<?php echo $evento['id']; ?></span>
                            </td>
                            <td>
                                <div class="evento-nombre">
                                    <?php echo htmlspecialchars($evento['nombre']); ?>
                                </div>
                            </td>
                            <td>
                                <div class="evento-fecha">
                                    <?php echo date('d/m/Y', strtotime($evento['fecha'])); ?>
                                </div>
                            </td>
                            <td class="descripcion" title="<?php echo htmlspecialchars($evento['descripcion']); ?>">
                                <?php echo htmlspecialchars($evento['descripcion']); ?>
                            </td>
                            <td>
                                <?php if ($evento['estado'] == 'Activo'): ?>
                                    <span class="estado-activo">
                                        ✅ <?php echo $evento['estado']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="estado-cerrado">
                                        🔒 <?php echo $evento['estado']; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <a href="editar_evento.php?id=<?php echo $evento['id']; ?>" class="btn btn-warning" title="Editar evento">
                                        ✏️ Editar
                                    </a>
                                    <a href="categorias_evento.php?evento_id=<?php echo $evento['id']; ?>" class="btn btn-info" title="Gestionar categorías">
                                        🏷️ Categorías
                                    </a>
                                    <a href="participantes_evento.php?evento_id=<?php echo $evento['id']; ?>" class="btn" title="Gestionar participantes">
                                        👥 Participantes
                                    </a>
                                    <a href="eliminar_evento.php?id=<?php echo $evento['id']; ?>" class="btn btn-danger" 
                                       onclick="return confirm('¿Estás seguro de que quieres eliminar el evento \"<?php echo htmlspecialchars(addslashes($evento['nombre'])); ?>\"? Se eliminarán también sus categorías y participantes.')"
                                       title="Eliminar evento">
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
            <a href="dashboard.php" class="btn">
                ← Volver al Panel de Administración
            </a>
        </div>
    </div>

    <script>
        // Efectos adicionales para la tabla
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach((row, index) => {
                // Animación escalonada para las filas
                row.style.animationDelay = (index * 0.1) + 's';
                row.style.animation = 'fadeIn 0.5s ease-out forwards';
                row.style.opacity = '0';
                
                // Efecto hover mejorado
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.01)';
                    this.style.boxShadow = '0 5px 15px rgba(0,0,0,0.1)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                    this.style.boxShadow = 'none';
                });
            });

            // Tooltip para descripciones largas
            const descripciones = document.querySelectorAll('.descripcion');
            descripciones.forEach(desc => {
                desc.addEventListener('mouseenter', function() {
                    if (this.scrollWidth > this.clientWidth) {
                        // Mostrar tooltip personalizado si el texto está truncado
                        this.style.cursor = 'help';
                    }
                });
            });
        });
    </script>
</body>
</html>