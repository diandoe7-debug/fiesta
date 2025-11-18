<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'jurado') {
    header("Location: ../login.php");
    exit();
}

require_once 'C:/xampp/htdocs/votacion/src/config/database.php';

$database = new Database();
$db = $database->getConnection();

$evento = null;
$participantes = [];
$categorias = [];
$participantes_votados = [];
$error = '';
$success = '';

// Mostrar mensaje de éxito si existe
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Verificar que se haya proporcionado un evento_id
if (!isset($_GET['evento_id']) || empty($_GET['evento_id'])) {
    header("Location: dashboard.php");
    exit();
}

$evento_id = $_GET['evento_id'];
$jurado_id = $_SESSION['user_id'];

try {
    // Obtener información del evento
    $query = "SELECT id, nombre, fecha, estado FROM eventos WHERE id = :id AND estado = 'Activo'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $evento_id);
    $stmt->execute();
    $evento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$evento) {
        $error = "Evento no encontrado o no está activo.";
    } else {
        // Obtener participantes del evento
        $query = "SELECT id, nombre, representante, foto, descripcion FROM participantes WHERE evento_id = :evento_id ORDER BY nombre";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':evento_id', $evento_id);
        $stmt->execute();
        $participantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener categorías del evento
        $query = "SELECT id, nombre, puntaje_maximo FROM categorias WHERE evento_id = :evento_id ORDER BY nombre";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':evento_id', $evento_id);
        $stmt->execute();
        $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener participantes que YA han sido votados por este jurado
        $query = "SELECT DISTINCT participante_id FROM votos 
                 WHERE jurado_id = :jurado_id 
                 AND participante_id IN (SELECT id FROM participantes WHERE evento_id = :evento_id)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':jurado_id', $jurado_id);
        $stmt->bindParam(':evento_id', $evento_id);
        $stmt->execute();
        $participantes_votados = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    }
} catch (PDOException $e) {
    $error = "Error al cargar los datos: " . $e->getMessage();
}

// Calcular progreso de votación
$total_participantes = count($participantes);
$participantes_votados_count = count($participantes_votados);
$progreso = $total_participantes > 0 ? round(($participantes_votados_count / $total_participantes) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Votación - Jurado</title>
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
            max-width: 1400px;
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
            margin-bottom: 15px;
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

        .evento-info {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            border-left: 5px solid #28a745;
            box-shadow: 0 5px 20px rgba(40, 167, 69, 0.2);
        }

        .evento-info h3 {
            color: #155724;
            margin-bottom: 10px;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .participantes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .participante-card {
            background: linear-gradient(135deg, #ffffff, #f8f9fa);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .participante-card:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #74b9ff, #0984e3);
        }

        .participante-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .participante-card.votado {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border-color: #28a745;
        }

        .participante-card.votado:before {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        .foto-participante {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 20px;
            border: 4px solid #74b9ff;
            box-shadow: 0 5px 15px rgba(116, 185, 255, 0.3);
            transition: all 0.3s ease;
        }

        .participante-card:hover .foto-participante {
            border-color: #0984e3;
            transform: scale(1.05);
        }

        .foto-placeholder {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: linear-gradient(135deg, #74b9ff, #0984e3);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            border: 4px solid #74b9ff;
            box-shadow: 0 5px 15px rgba(116, 185, 255, 0.3);
            font-size: 3rem;
            color: white;
            transition: all 0.3s ease;
        }

        .participante-card:hover .foto-placeholder {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(116, 185, 255, 0.4);
        }

        .btn {
            padding: 14px 28px;
            background: linear-gradient(135deg, #00b894, #00cec9);
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 184, 148, 0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 184, 148, 0.4);
            background: linear-gradient(135deg, #00a085, #00b7b3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268, #495057);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #1e7e34, #199d74);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
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

        .success {
            background: linear-gradient(135deg, #55efc4, #00b894);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 184, 148, 0.3);
            text-align: center;
        }

        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .resumen-votos {
            background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(255,255,255,0.85));
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .error-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .participante-info {
            margin-bottom: 20px;
        }

        .participante-info h3 {
            color: #2d3436;
            margin-bottom: 10px;
            font-size: 1.3rem;
        }

        .categorias-count {
            background: linear-gradient(135deg, #74b9ff, #0984e3);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(116, 185, 255, 0.3);
        }

        .votado-badge {
            background: linear-gradient(135deg, #00b894, #00cec9);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0, 184, 148, 0.3);
        }

        .progreso-container {
            background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(255,255,255,0.85));
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .barra-progreso {
            background: #e9ecef;
            border-radius: 15px;
            height: 25px;
            margin: 15px 0;
            overflow: hidden;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.1);
        }

        .progreso {
            background: linear-gradient(135deg, #00b894, #00cec9);
            height: 100%;
            border-radius: 15px;
            transition: width 0.5s ease;
            text-align: center;
            color: white;
            font-size: 0.9rem;
            line-height: 25px;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0, 184, 148, 0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(255,255,255,0.85));
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 0.95rem;
            color: #666;
            font-weight: 600;
        }

        .section-title {
            text-align: center;
            margin-bottom: 25px;
            font-size: 1.8rem;
            color: #2d3436;
            position: relative;
        }

        .section-title:after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            margin: 10px auto;
            border-radius: 2px;
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
            
            .participantes-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .actions {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        .floating-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
            text-align: center;
        }

        .participante-descripcion {
            background: rgba(116, 185, 255, 0.1);
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
            font-size: 0.9rem;
            color: #555;
            border-left: 3px solid #74b9ff;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="floating-icon">🗳️</div>
            <h1>Sistema de Votación - Panel del Jurado</h1>
            
            <div class="user-info">
                <div class="user-card">
                    <span>👤 <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
                </div>
                <div class="user-card">
                    <span>🎯 <strong>Rol:</strong> Jurado</span>
                </div>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error">
                ❌ <strong>Error:</strong><br>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success">
                ✅ <strong>Éxito:</strong><br>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($evento): ?>
            <div class="evento-info">
                <h3>🎯 <?php echo htmlspecialchars($evento['nombre']); ?></h3>
                <p><strong>📅 Fecha:</strong> <?php echo date('d/m/Y', strtotime($evento['fecha'])); ?> | 
                   <strong>🔄 Estado:</strong> <?php echo $evento['estado']; ?></p>
            </div>

            <?php if (empty($participantes) || empty($categorias)): ?>
                <div class="error">
                    ❌ <strong>No se puede iniciar la votación</strong><br><br>
                    <?php if (empty($participantes)): ?>
                        📝 No hay participantes inscritos en este evento.<br>
                    <?php endif; ?>
                    <?php if (empty($categorias)): ?>
                        🏷️ No hay categorías configuradas para este evento.
                    <?php endif; ?>
                </div>
                <div class="error-actions">
                    <a href="dashboard.php" class="btn btn-secondary">← Volver al Panel del Jurado</a>
                </div>
            <?php else: ?>
                <!-- Estadísticas rápidas -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_participantes; ?></div>
                        <div class="stat-label">👥 Total Participantes</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $participantes_votados_count; ?></div>
                        <div class="stat-label">✅ Ya Votados</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_participantes - $participantes_votados_count; ?></div>
                        <div class="stat-label">⏳ Pendientes</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($categorias); ?></div>
                        <div class="stat-label">🏷️ Categorías</div>
                    </div>
                </div>

                <!-- Progreso de votación -->
                <div class="progreso-container">
                    <h3 class="section-title">📊 Progreso de Tu Votación</h3>
                    <p><strong><?php echo $participantes_votados_count; ?> de <?php echo $total_participantes; ?></strong> participantes votados</p>
                    <div class="barra-progreso">
                        <div class="progreso" style="width: <?php echo $progreso; ?>%">
                            <?php echo $progreso; ?>%
                        </div>
                    </div>
                    <p style="text-align: center; margin-top: 15px; font-size: 1.1rem;">
                        <?php if ($progreso == 0): ?>
                            🚀 <strong>Comienza a votar</strong> seleccionando un participante
                        <?php elseif ($progreso == 100): ?>
                            ✅ <strong>¡Felicidades!</strong> Has votado a todos los participantes
                        <?php else: ?>
                            ⏳ <strong>Continúa votando</strong> a los participantes restantes
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Resumen de votos -->
                <div class="resumen-votos">
                    <strong>📋 Resumen de Votación:</strong> 
                    <?php echo count($participantes); ?> participantes × 
                    <?php echo count($categorias); ?> categorías = 
                    <strong style="color: #667eea;"><?php echo count($participantes) * count($categorias); ?> votos posibles</strong>
                    <br>
                    <small style="color: #666; margin-top: 10px; display: block;">
                        Selecciona un participante para comenzar a votar en todas las categorías
                    </small>
                </div>

                <!-- Lista de participantes -->
                <h3 class="section-title">🎭 Lista de Participantes</h3>
                <div class="participantes-grid">
                    <?php foreach ($participantes as $participante): ?>
                        <?php $ya_votado = in_array($participante['id'], $participantes_votados); ?>
                        <div class="participante-card <?php echo $ya_votado ? 'votado' : ''; ?>">
                            <?php if (!empty($participante['foto']) && file_exists('../uploads/fotos/' . $participante['foto'])): ?>
                                <img src="../uploads/fotos/<?php echo $participante['foto']; ?>" 
                                     alt="<?php echo htmlspecialchars($participante['nombre']); ?>"
                                     class="foto-participante">
                            <?php else: ?>
                                <div class="foto-placeholder">👤</div>
                            <?php endif; ?>
                            
                            <div class="participante-info">
                                <h3><?php echo htmlspecialchars($participante['nombre']); ?></h3>
                                <p><strong>🏢 Representa:</strong> <?php echo htmlspecialchars($participante['representante']); ?></p>
                                
                                <?php if (!empty($participante['descripcion'])): ?>
                                    <div class="participante-descripcion">
                                        <?php echo htmlspecialchars($participante['descripcion']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <span class="categorias-count"><?php echo count($categorias); ?> categorías</span>
                                <?php if ($ya_votado): ?>
                                    <div class="votado-badge" style="margin-top: 10px;">✅ Ya votado</div>
                                <?php endif; ?>
                            </div>

                            <div style="margin-top: 20px;">
                                <?php if ($ya_votado): ?>
                                    <a href="votar_participante.php?evento_id=<?php echo $evento_id; ?>&participante_id=<?php echo $participante['id']; ?>" class="btn btn-secondary">
                                        ✏️ Editar Voto
                                    </a>
                                <?php else: ?>
                                    <a href="votar_participante.php?evento_id=<?php echo $evento_id; ?>&participante_id=<?php echo $participante['id']; ?>" class="btn">
                                        🗳️ Votar Participante
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="actions">
                    <?php if ($progreso == 100): ?>
                        <a href="ver_resultados.php?evento_id=<?php echo $evento_id; ?>" class="btn btn-success">📊 Ver Resultados Finales</a>
                    <?php else: ?>
                        <a href="ver_resultados.php?evento_id=<?php echo $evento_id; ?>" class="btn btn-secondary">📊 Ver Progreso General</a>
                    <?php endif; ?>
                    <a href="dashboard.php" class="btn btn-secondary">← Volver al Panel</a>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="error">
                ❌ <strong>Evento no disponible para votación</strong>
                <div class="error-actions">
                    <a href="dashboard.php" class="btn btn-secondary">← Volver al Panel del Jurado</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Efectos de interacción suaves
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.participante-card, .stat-card');
            
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>