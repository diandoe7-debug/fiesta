<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'jurado') {
    header("Location: ../login.php");
    exit();
}

require_once 'C:/xampp/htdocs/votacion/src/config/database.php';

$database = new Database();
$db = $database->getConnection();

$eventos = [];
$error = '';

// Obtener eventos activos para el jurado
if ($db) {
    try {
        $query = "SELECT id, nombre, fecha, descripcion, estado FROM eventos WHERE estado = 'Activo' ORDER BY fecha DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $error = "Error al cargar los eventos: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel del Jurado</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            padding: 0; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header { 
            background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(255,255,255,0.85));
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .header h1 {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }

        .user-info {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        .user-card {
            background: linear-gradient(135deg, #ffeaa7, #fdcb6e);
            padding: 15px 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(253, 203, 110, 0.3);
            border: 2px solid rgba(255,255,255,0.3);
        }

        .user-card strong {
            color: #2d3436;
            font-size: 1.1rem;
        }

        .eventos { 
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .section-title {
            text-align: center;
            margin-bottom: 30px;
            font-size: 2rem;
            color: #2d3436;
            position: relative;
        }

        .section-title:after {
            content: '';
            display: block;
            width: 100px;
            height: 4px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            margin: 10px auto;
            border-radius: 2px;
        }

        .eventos-grid {
            display: grid;
            gap: 25px;
        }

        .evento { 
            background: linear-gradient(135deg, #ffffff, #f8f9fa);
            padding: 25px;
            border-radius: 15px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .evento:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #00b894, #00cec9);
        }

        .evento.activo { 
            background: linear-gradient(135deg, #e8f5e8, #d4edda);
            border-color: #28a745;
            box-shadow: 0 5px 20px rgba(40, 167, 69, 0.2);
        }

        .evento.activo:before {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        .evento:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .evento h3 {
            margin: 0 0 15px 0;
            color: #2d3436;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .evento-info {
            color: #666;
            margin-bottom: 20px;
        }

        .evento-descripcion {
            background: rgba(255,255,255,0.8);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            border-left: 4px solid #74b9ff;
            box-shadow: 0 2px 8px rgba(116, 185, 255, 0.1);
        }

        .btn { 
            padding: 12px 30px; 
            background: linear-gradient(135deg, #00b894, #00cec9);
            color: white; 
            text-decoration: none; 
            border-radius: 10px; 
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 184, 148, 0.3);
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 184, 148, 0.4);
            background: linear-gradient(135deg, #00a085, #00b7b3);
        }

        .logout { 
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 25px; 
            background: linear-gradient(135deg, #e17055, #d63031);
            color: white; 
            text-decoration: none; 
            border-radius: 10px; 
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(231, 112, 85, 0.3);
            font-weight: 600;
        }

        .logout:hover {
            background: linear-gradient(135deg, #d63031, #c23616);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(214, 48, 49, 0.4);
        }

        .no-events {
            text-align: center;
            padding: 60px 40px;
            color: #666;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            border: 2px dashed #dee2e6;
        }

        .no-events h3 {
            color: #6c757d;
            margin-bottom: 15px;
            font-size: 1.5rem;
        }

        .error {
            background: linear-gradient(135deg, #fab1a0, #e17055);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: none;
            box-shadow: 0 5px 15px rgba(231, 112, 85, 0.3);
            text-align: center;
        }

        .badge {
            background: linear-gradient(135deg, #00b894, #00cec9);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(0, 184, 148, 0.3);
        }

        .evento-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .stat {
            background: rgba(255,255,255,0.9);
            padding: 8px 15px;
            border-radius: 8px;
            border: 1px solid rgba(0,0,0,0.1);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .actions {
            text-align: center;
            margin-top: 20px;
        }

        .welcome-message {
            font-size: 1.1rem;
            color: #666;
            margin-bottom: 10px;
        }

        .role-badge {
            background: linear-gradient(135deg, #0984e3, #74b9ff);
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .header {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .user-info {
                flex-direction: column;
                gap: 15px;
            }
            
            .evento-stats {
                flex-direction: column;
                gap: 10px;
            }
            
            .evento {
                padding: 20px;
            }
        }

        .floating-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
            text-align: center;
        }

        .evento-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .evento-title {
            flex: 1;
            min-width: 250px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="floating-icon">🧑‍⚖️</div>
            <h1>Panel del Jurado</h1>
            <p class="welcome-message">Bienvenido al sistema de votación certificado</p>
            
            <div class="user-info">
                <div class="user-card">
                    <span>👤 <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
                </div>
                <div class="user-card">
                    <span>🎯 <strong>Rol: </strong><span class="role-badge"><?php echo $_SESSION['user_role']; ?></span></span>
                </div>
            </div>
            
            <p style="color: #666; margin-top: 15px; font-size: 1rem;">
                Seleccione un evento activo para comenzar a votar
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error">
                ❌ <strong>Error:</strong><br>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="eventos">
            <h2 class="section-title">🎯 Eventos Activos para Votación</h2>
            
            <?php if (empty($eventos)): ?>
                <div class="no-events">
                    <div style="font-size: 4rem; margin-bottom: 20px;">📝</div>
                    <h3>No hay eventos activos</h3>
                    <p>Actualmente no hay eventos disponibles para votación.</p>
                    <p style="margin-top: 10px; color: #888;">
                        Los eventos aparecerán aquí cuando estén activos y listos para recibir votos.
                    </p>
                </div>
            <?php else: ?>
                <div class="eventos-grid">
                    <?php foreach ($eventos as $evento): ?>
                    <div class="evento activo">
                        <div class="evento-header">
                            <div class="evento-title">
                                <h3>🎯 <?php echo htmlspecialchars($evento['nombre']); ?></h3>
                            </div>
                            <div class="badge">✅ ACTIVO</div>
                        </div>
                        
                        <div class="evento-stats">
                            <div class="stat">
                                <strong>📅</strong> <?php echo date('d/m/Y', strtotime($evento['fecha'])); ?>
                            </div>
                            <div class="stat">
                                <strong>🆔</strong> #<?php echo $evento['id']; ?>
                            </div>
                            <div class="stat">
                                <strong>⚡</strong> Listo para votar
                            </div>
                        </div>

                        <?php if (!empty($evento['descripcion'])): ?>
                            <div class="evento-descripcion">
                                <strong>📋 Descripción del Evento:</strong><br>
                                <?php echo htmlspecialchars($evento['descripcion']); ?>
                            </div>
                        <?php endif; ?>

                        <div class="actions">
                            <a href="votacion.php?evento_id=<?php echo $evento['id']; ?>" class="btn">
                                🗳️ Ingresar a Votar
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="../logout.php" class="logout">
                🚪 Cerrar Sesión
            </a>
        </div>
    </div>

    <script>
        // Efectos de interacción suaves
        document.addEventListener('DOMContentLoaded', function() {
            const eventos = document.querySelectorAll('.evento');
            
            eventos.forEach(evento => {
                evento.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                evento.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>