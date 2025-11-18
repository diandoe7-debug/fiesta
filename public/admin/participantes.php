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
        $success = "Participante creado exitosamente.";
    } elseif ($_GET['success'] == 2) {
        $success = "Participante actualizado exitosamente.";
    } elseif ($_GET['success'] == 3) {
        $success = "Participante eliminado exitosamente.";
    }
}

if ($db) {
    try {
        // SOLUCIÓN: Usar JOIN en una sola consulta para evitar el problema del bucle
        $query = "SELECT e.id, e.nombre, e.fecha, e.estado, 
                         COUNT(p.id) as total_participantes 
                  FROM eventos e 
                  LEFT JOIN participantes p ON e.id = p.evento_id 
                  GROUP BY e.id 
                  ORDER BY e.fecha DESC";
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
    <title>Gestión de Participantes - Sistema de Votación</title>
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
            max-width: 1200px;
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
        
        .btn-small {
            padding: 10px 20px;
            font-size: 0.9rem;
            border-radius: 8px;
        }
        
        .eventos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .evento-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-left: 5px solid #007bff;
        }
        
        .evento-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .evento-card h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-size: 1.4rem;
        }
        
        .evento-info {
            color: #6c757d;
            font-size: 0.95rem;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .evento-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            background: rgba(0,123,255,0.05);
            padding: 15px;
            border-radius: 10px;
        }
        
        .stat {
            text-align: center;
            flex: 1;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #007bff;
            display: block;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .evento-actions {
            display: flex;
            gap: 10px;
        }
        
        .estado-activo {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
            padding: 6px 12px;
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
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }
        
        .no-data .icon {
            font-size: 4rem;
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
        
        .footer-actions {
            text-align: center;
            margin-top: 30px;
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
            
            .eventos-grid {
                grid-template-columns: 1fr;
            }
            
            .evento-stats {
                flex-direction: column;
                gap: 10px;
            }
            
            .evento-actions {
                flex-direction: column;
            }
        }
        
        /* Animaciones */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .evento-card {
            animation: fadeInUp 0.6s ease-out;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👥 Gestión de Participantes por Evento</h1>
            <p>Seleccione un evento para gestionar sus concursantes</p>
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

        <div class="actions">
            <a href="dashboard.php" class="btn">
                ← Volver al Panel
            </a>
            <div class="total-counter">
                📊 Total: <?php echo count($eventos); ?> eventos
            </div>
        </div>

        <?php if (empty($eventos)): ?>
            <div class="no-data">
                <span class="icon">📝</span>
                <h3>No hay eventos creados en el sistema</h3>
                <p>Comienza creando el primer evento para agregar participantes</p>
                <a href="eventos.php" class="btn btn-success" style="margin-top: 20px;">
                    🎯 Crear Primer Evento
                </a>
            </div>
        <?php else: ?>
            <div class="eventos-grid">
                <?php foreach ($eventos as $evento): ?>
                <div class="evento-card">
                    <h3>🎯 <?php echo htmlspecialchars($evento['nombre']); ?></h3>
                    
                    <div class="evento-info">
                        <strong>📅 Fecha:</strong> <?php echo date('d/m/Y', strtotime($evento['fecha'])); ?><br>
                        <strong>🔄 Estado:</strong> 
                        <?php if ($evento['estado'] == 'Activo'): ?>
                            <span class="estado-activo">✅ <?php echo $evento['estado']; ?></span>
                        <?php else: ?>
                            <span class="estado-cerrado">🔒 <?php echo $evento['estado']; ?></span>
                        <?php endif; ?>
                        <br>
                        <strong>🆔 ID:</strong> #<?php echo $evento['id']; ?>
                    </div>

                    <div class="evento-stats">
                        <div class="stat">
                            <div class="stat-number"><?php echo $evento['total_participantes']; ?></div>
                            <div class="stat-label">Participantes</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">-</div>
                            <div class="stat-label">Categorías</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">#<?php echo $evento['id']; ?></div>
                            <div class="stat-label">ID Evento</div>
                        </div>
                    </div>

                    <div class="evento-actions">
                        <a href="gestionar_participantes.php?evento_id=<?php echo $evento['id']; ?>" class="btn btn-success btn-small">
                            👥 Gestionar Participantes
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="footer-actions">
            <a href="dashboard.php" class="btn btn-secondary">
                ← Volver al Panel de Administración
            </a>
        </div>
    </div>

    <script>
        // Efectos interactivos para las tarjetas
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.evento-card');
            
            cards.forEach((card, index) => {
                // Animación escalonada
                card.style.animationDelay = (index * 0.1) + 's';
                
                // Efecto hover mejorado
                card.addEventListener('mouseenter', function() {
                    this.style.borderLeftColor = '#28a745';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.borderLeftColor = '#007bff';
                });
            });

            // Efecto en botones
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>