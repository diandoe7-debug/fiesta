<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once 'C:/xampp/htdocs/votacion/src/config/database.php';

$database = new Database();
$db = $database->getConnection();

$participantes = [];
$evento = null;
$error = '';

// Obtener información del evento
if (isset($_GET['evento_id'])) {
    $evento_id = $_GET['evento_id'];
    
    try {
        // Obtener datos del evento
        $query = "SELECT id, nombre, fecha FROM eventos WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $evento_id);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $evento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Obtener participantes del evento
            $query = "SELECT id, nombre, representante, edad, foto, descripcion FROM participantes WHERE evento_id = :evento_id ORDER BY nombre";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':evento_id', $evento_id);
            $stmt->execute();
            $participantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error = "Evento no encontrado.";
        }
    } catch (PDOException $e) {
        $error = "Error al cargar los participantes: " . $e->getMessage();
    }
} else {
    $error = "ID de evento no especificado.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Participantes del Evento - Sistema de Votación</title>
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
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            box-shadow: 0 4px 15px rgba(108,117,125,0.3);
        }
        
        .btn-secondary:hover {
            box-shadow: 0 6px 20px rgba(108,117,125,0.4);
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
        
        .edad-badge {
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
        
        .participante-id {
            font-weight: 600;
            color: #007bff;
            background: rgba(0,123,255,0.1);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
        }
        
        .participante-nombre {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.05rem;
        }
        
        .foto-container {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border: 2px solid rgba(0,123,255,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .foto-preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .foto-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #e9ecef, #dee2e6);
            color: #6c757d;
            font-size: 1.5rem;
        }
        
        .foto-info {
            font-size: 0.75rem;
            color: #6c757d;
            text-align: center;
            margin-top: 8px;
            font-weight: 500;
        }
        
        .participante-foto:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(0,123,255,0.2);
            border-color: rgba(0,123,255,0.3);
        }
        
        .descripcion-text {
            max-width: 300px;
            line-height: 1.4;
        }
        
        .stats-summary {
            background: rgba(255,255,255,0.9);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: rgba(0,123,255,0.05);
            border-radius: 10px;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #007bff;
            display: block;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
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
            
            .evento-details {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .foto-container {
                width: 50px;
                height: 50px;
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
        
        /* Modal para fotos */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }
        
        .modal-content {
            max-width: 90%;
            max-height: 90%;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        
        .modal-title {
            color: white;
            text-align: center;
            margin-top: 20px;
            font-size: 1.3rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👥 Participantes del Evento</h1>
            <p>Lista de concursantes inscritos en este evento</p>
        </div>

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
                        <div class="detail-label">🆔 ID del Evento</div>
                        <div class="detail-value">#<?php echo $evento['id']; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">👥 Total Participantes</div>
                        <div class="detail-value"><?php echo count($participantes); ?></div>
                    </div>
                </div>
            </div>

            <?php if (!empty($participantes)): ?>
                <div class="stats-summary">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo count($participantes); ?></span>
                            <span class="stat-label">Total Participantes</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">
                                <?php 
                                    $edades = array_column($participantes, 'edad');
                                    echo count($edades) > 0 ? round(array_sum($edades) / count($edades), 1) : 0;
                                ?>
                            </span>
                            <span class="stat-label">Edad Promedio</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">
                                <?php 
                                    $conFoto = array_filter($participantes, function($p) { return !empty($p['foto']); });
                                    echo count($conFoto);
                                ?>
                            </span>
                            <span class="stat-label">Con Foto</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">
                                <?php 
                                    $sinFoto = array_filter($participantes, function($p) { return empty($p['foto']); });
                                    echo count($sinFoto);
                                ?>
                            </span>
                            <span class="stat-label">Sin Foto</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="actions">
                <a href="eventos.php" class="btn">
                    ← Volver a Eventos
                </a>
                <a href="participantes.php" class="btn btn-success">
                    ⚙️ Gestionar Participantes
                </a>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Foto</th>
                            <th>Nombre</th>
                            <th>Representa</th>
                            <th>Edad</th>
                            <th>Descripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($participantes)): ?>
                            <tr>
                                <td colspan="6" class="no-data">
                                    <span class="icon">📝</span>
                                    <h3>Este evento no tiene participantes</h3>
                                    <p>Inscribe los primeros participantes para este evento</p>
                                    <a href="participantes.php" class="btn btn-success" style="margin-top: 15px;">
                                        👥 Agregar Participantes
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($participantes as $participante): ?>
                            <tr>
                                <td>
                                    <span class="participante-id">#<?php echo $participante['id']; ?></span>
                                </td>
                                <td>
                                    <div class="foto-container participante-foto" 
                                         data-foto="<?php echo !empty($participante['foto']) ? '../uploads/fotos/' . $participante['foto'] : ''; ?>"
                                         data-nombre="<?php echo htmlspecialchars($participante['nombre']); ?>">
                                        <?php if (!empty($participante['foto']) && file_exists('../uploads/fotos/' . $participante['foto'])): ?>
                                            <img src="../uploads/fotos/<?php echo $participante['foto']; ?>" 
                                                 alt="<?php echo htmlspecialchars($participante['nombre']); ?>"
                                                 class="foto-preview">
                                        <?php else: ?>
                                            <div class="foto-placeholder" title="Sin foto">
                                                👤
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($participante['foto'])): ?>
                                        <div class="foto-info">✅ Con foto</div>
                                    <?php else: ?>
                                        <div class="foto-info">❌ Sin foto</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="participante-nombre">
                                        <?php echo htmlspecialchars($participante['nombre']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($participante['representante']); ?>
                                </td>
                                <td>
                                    <span class="edad-badge">
                                        🎂 <?php echo $participante['edad']; ?> años
                                    </span>
                                </td>
                                <td>
                                    <div class="descripcion-text" title="<?php echo htmlspecialchars($participante['descripcion']); ?>">
                                        <?php 
                                        $descripcion = $participante['descripcion'];
                                        if (strlen($descripcion) > 60) {
                                            echo htmlspecialchars(substr($descripcion, 0, 60)) . '...';
                                        } else {
                                            echo htmlspecialchars($descripcion);
                                        }
                                        ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="footer-actions">
                <a href="eventos.php" class="btn btn-secondary">
                    ← Volver a la Lista de Eventos
                </a>
            </div>

        <?php else: ?>
            <div style="text-align: center; margin-top: 25px;">
                <a href="eventos.php" class="btn">
                    ← Volver a Eventos
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal para ver foto en grande -->
    <div id="fotoModal" class="modal">
        <div style="text-align: center;">
            <img id="modalFoto" src="" alt="" class="modal-content">
            <div id="modalTitle" class="modal-title"></div>
        </div>
    </div>

    <script>
        // Modal para fotos
        const modal = document.getElementById('fotoModal');
        const modalFoto = document.getElementById('modalFoto');
        const modalTitle = document.getElementById('modalTitle');
        const fotoContainers = document.querySelectorAll('.participante-foto');

        fotoContainers.forEach(container => {
            container.addEventListener('click', function() {
                const fotoSrc = this.getAttribute('data-foto');
                const nombre = this.getAttribute('data-nombre');
                
                if (fotoSrc) {
                    modalFoto.src = fotoSrc;
                    modalTitle.textContent = nombre;
                    modal.style.display = 'flex';
                }
            });
        });

        // Cerrar modal al hacer click
        modal.addEventListener('click', function() {
            modal.style.display = 'none';
        });

        // Efectos adicionales para la tabla
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach((row, index) => {
                // Animación escalonada para las filas
                if (row.cells.length > 1) { // Solo filas con datos
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
                }
            });

            // Efecto en badges de edad
            const edadBadges = document.querySelectorAll('.edad-badge');
            edadBadges.forEach(badge => {
                badge.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.1)';
                });
                
                badge.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>
</html>