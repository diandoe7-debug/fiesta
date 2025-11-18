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
$resultados = [];
$estado_votacion = '';
$error = '';
$success = '';

// Verificar que se haya proporcionado un evento_id
if (!isset($_GET['evento_id']) || empty($_GET['evento_id'])) {
    header("Location: dashboard.php");
    exit();
}

$evento_id = $_GET['evento_id'];
$jurado_id = $_SESSION['user_id'];

try {
    // Obtener información del evento
    $query = "SELECT id, nombre, fecha, estado FROM eventos WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $evento_id);
    $stmt->execute();
    $evento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$evento) {
        $error = "Evento no encontrado.";
    } else {
        // Obtener participantes del evento
        $query = "SELECT id, nombre, representante, foto FROM participantes WHERE evento_id = :evento_id ORDER BY nombre";
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

        // Obtener total de jurados
        $query = "SELECT COUNT(*) as total_jurados FROM usuarios WHERE rol = 'jurado'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $total_jurados = $stmt->fetch(PDO::FETCH_ASSOC)['total_jurados'];

        // ✅ CORRECCIÓN: Verificar jurado por jurado si completó TODOS los votos
        $query = "SELECT u.id, u.nombre,
                         (SELECT COUNT(DISTINCT participante_id) 
                          FROM votos 
                          WHERE jurado_id = u.id 
                          AND participante_id IN (SELECT id FROM participantes WHERE evento_id = :evento_id)
                         ) as participantes_votados
                  FROM usuarios u 
                  WHERE u.rol = 'jurado'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':evento_id', $evento_id);
        $stmt->execute();
        $jurados_info = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $jurados_votados = 0;
        $total_participantes = count($participantes);
        
        foreach ($jurados_info as $jurado) {
            if ($jurado['participantes_votados'] == $total_participantes) {
                $jurados_votados++;
            }
        }

        // Determinar estado de la votación
        if ($jurados_votados == 0) {
            $estado_votacion = 'sin_votos';
        } elseif ($jurados_votados < $total_jurados) {
            $estado_votacion = 'votando';
        } else {
            $estado_votacion = 'completado';
        }

        // Si la votación está completada, calcular resultados
        if ($estado_votacion == 'completado') {
            // Calcular resultados por categoría
            foreach ($categorias as $categoria) {
                $query = "SELECT p.id, p.nombre, p.representante, p.foto,
                                 AVG(v.puntaje) as promedio,
                                 COUNT(v.puntaje) as total_votos,
                                 SUM(v.puntaje) as puntaje_total
                          FROM participantes p
                          LEFT JOIN votos v ON p.id = v.participante_id AND v.categoria_id = :categoria_id
                          WHERE p.evento_id = :evento_id
                          GROUP BY p.id, p.nombre, p.representante, p.foto
                          ORDER BY promedio DESC";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':categoria_id', $categoria['id']);
                $stmt->bindParam(':evento_id', $evento_id);
                $stmt->execute();
                $resultados_categoria = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Calcular porcentajes
                $max_puntaje = $categoria['puntaje_maximo'];
                foreach ($resultados_categoria as &$resultado) {
                    if ($resultado['promedio']) {
                        $resultado['porcentaje'] = round(($resultado['promedio'] / $max_puntaje) * 100, 1);
                    } else {
                        $resultado['porcentaje'] = 0;
                        $resultado['promedio'] = 0;
                    }
                }

                $resultados[$categoria['id']] = [
                    'categoria_nombre' => $categoria['nombre'],
                    'puntaje_maximo' => $max_puntaje,
                    'participantes' => $resultados_categoria
                ];
            }

            // Calcular resultados generales (suma de todos los promedios)
            $query = "SELECT p.id, p.nombre, p.representante, p.foto,
                             AVG(v.puntaje) as promedio_general,
                             COUNT(v.puntaje) as total_votos
                      FROM participantes p
                      LEFT JOIN votos v ON p.id = v.participante_id
                      WHERE p.evento_id = :evento_id
                      GROUP BY p.id, p.nombre, p.representante, p.foto
                      ORDER BY promedio_general DESC";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':evento_id', $evento_id);
            $stmt->execute();
            $resultados_generales = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $resultados['general'] = [
                'categoria_nombre' => '🏆 CLASIFICACIÓN FINAL - RESULTADO GENERAL',
                'participantes' => $resultados_generales
            ];
        }

    }
} catch (PDOException $e) {
    $error = "Error al cargar los resultados: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados - Jurado</title>
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
            font-size: 2.8rem;
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
            background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(255,255,255,0.85));
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-left: 5px solid #00b894;
        }

        .evento-info h3 {
            color: #2d3436;
            margin-bottom: 10px;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .estado-votacion {
            background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(255,255,255,0.85));
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-left: 5px solid #fdcb6e;
        }

        .estado-completado {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border-left: 5px solid #00b894;
        }

        .resultados-container {
            background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(255,255,255,0.85));
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        /* Estilos para el acordeón */
        .acordeon-categoria {
            margin-bottom: 15px;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .acordeon-categoria:hover {
            border-color: #74b9ff;
            box-shadow: 0 8px 25px rgba(116, 185, 255, 0.2);
        }

        .acordeon-header {
            background: linear-gradient(135deg, #74b9ff, #0984e3);
            color: white;
            padding: 20px 25px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            font-size: 1.2rem;
            font-weight: 600;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }

        .acordeon-header:hover {
            background: linear-gradient(135deg, #0984e3, #0747a6);
        }

        .acordeon-header.active {
            background: linear-gradient(135deg, #0984e3, #0747a6);
        }

        .acordeon-icon {
            transition: transform 0.3s ease;
            font-size: 1.3rem;
        }

        .acordeon-header.active .acordeon-icon {
            transform: rotate(180deg);
        }

        .acordeon-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease, padding 0.3s ease;
            background: white;
        }

        .acordeon-content.open {
            max-height: 2000px;
            padding: 0;
        }

        .acordeon-general {
            border: 3px solid #00b894;
            margin-top: 30px;
        }

        .acordeon-general .acordeon-header {
            background: linear-gradient(135deg, #00b894, #00cec9);
            font-size: 1.4rem;
            text-align: center;
            justify-content: center;
            gap: 15px;
        }

        .acordeon-general .acordeon-header:hover {
            background: linear-gradient(135deg, #00a085, #00b7b3);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        th, td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        th {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            font-weight: 600;
            color: #2d3436;
            border-bottom: 2px solid #dee2e6;
        }

        tr:hover {
            background: rgba(116, 185, 255, 0.05);
        }

        .puesto-1 { 
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            font-weight: bold;
            border-left: 4px solid #f39c12;
        }
        .puesto-2 { 
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-left: 4px solid #95a5a6;
        }
        .puesto-3 { 
            background: linear-gradient(135deg, #ffeaa7, #fdcb6e);
            border-left: 4px solid #e67e22;
        }

        .medal { 
            font-size: 1.4em; 
            margin-right: 10px;
            display: inline-block;
            min-width: 40px;
        }

        .foto-participante {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            border: 3px solid #74b9ff;
            box-shadow: 0 3px 10px rgba(116, 185, 255, 0.3);
        }

        .foto-placeholder {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #74b9ff, #0984e3);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            border: 3px solid #74b9ff;
            color: white;
            font-size: 1.1rem;
            box-shadow: 0 3px 10px rgba(116, 185, 255, 0.3);
        }

        .barra-progreso {
            background: #e9ecef;
            border-radius: 12px;
            height: 20px;
            margin: 8px 0;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }

        .progreso {
            background: linear-gradient(135deg, #00b894, #00cec9);
            height: 100%;
            border-radius: 12px;
            transition: width 0.5s ease;
            box-shadow: 0 2px 8px rgba(0, 184, 148, 0.3);
        }

        .btn {
            padding: 14px 28px;
            background: linear-gradient(135deg, #00b894, #00cec9);
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(0, 184, 148, 0.3);
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 184, 148, 0.4);
            background: linear-gradient(135deg, #00a085, #00b7b3);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #1e7e34, #199d74);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.3);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268, #495057);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.4);
        }

        .error {
            background: linear-gradient(135deg, #fab1a0, #e17055);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: none;
            box-shadow: 0 5px 20px rgba(231, 112, 85, 0.3);
            text-align: center;
        }

        .success {
            background: linear-gradient(135deg, #55efc4, #00b894);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 184, 148, 0.3);
            text-align: center;
        }

        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .stats {
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
            transform: translateY(-5px);
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

        .info-detalle {
            font-size: 0.85rem;
            color: #888;
            margin-top: 5px;
        }

        .participant-info {
            display: flex;
            align-items: center;
        }

        .participant-details {
            display: flex;
            flex-direction: column;
        }

        .participant-name {
            font-weight: 600;
            color: #2d3436;
        }

        .participant-representante {
            font-size: 0.85rem;
            color: #666;
        }

        .score-highlight {
            font-size: 1.2rem;
            font-weight: bold;
            color: #00b894;
        }

        .percentage-highlight {
            font-size: 1.1rem;
            font-weight: bold;
            color: #0984e3;
        }

        .floating-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
            text-align: center;
        }

        .section-title {
            text-align: center;
            margin-bottom: 25px;
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

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .header {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 2.2rem;
            }
            
            .stats {
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
            
            table {
                font-size: 0.9rem;
            }
            
            th, td {
                padding: 10px 12px;
            }

            .acordeon-header {
                padding: 15px 20px;
                font-size: 1.1rem;
            }
        }

        .progress-container {
            min-width: 120px;
        }

        .winner-glow {
            animation: glow 2s ease-in-out infinite alternate;
        }

        @keyframes glow {
            from {
                box-shadow: 0 0 10px #00b894, 0 0 20px #00b894;
            }
            to {
                box-shadow: 0 0 15px #00cec9, 0 0 30px #00cec9;
            }
        }

        .acordeon-count {
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="floating-icon">📊</div>
            <h1>Resultados de la Votación</h1>
            
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
                   <strong>🔄 Estado del Evento:</strong> <?php echo $evento['estado']; ?></p>
            </div>

            <!-- Estadísticas de votación -->
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_jurados ?? 0; ?></div>
                    <div class="stat-label">🧑‍⚖️ Total Jurados</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $jurados_votados ?? 0; ?></div>
                    <div class="stat-label">✅ Jurados Completaron</div>
                    <div class="info-detalle">(todos los participantes)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($participantes); ?></div>
                    <div class="stat-label">👥 Participantes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($categorias); ?></div>
                    <div class="stat-label">🏷️ Categorías</div>
                </div>
            </div>

            <!-- Estado de la votación -->
            <?php if ($estado_votacion == 'sin_votos'): ?>
                <div class="estado-votacion">
                    <h3>⏳ Esperando Votos</h3>
                    <p>Ningún jurado ha completado la votación de todos los participantes.</p>
                    <p>Los resultados se mostrarán aquí cuando todos los jurados hayan votado.</p>
                    <div class="actions">
                        <a href="votacion.php?evento_id=<?php echo $evento_id; ?>" class="btn btn-success">🗳️ Comenzar a Votar</a>
                        <a href="dashboard.php" class="btn btn-secondary">← Volver al Panel</a>
                    </div>
                </div>

            <?php elseif ($estado_votacion == 'votando'): ?>
                <div class="estado-votacion">
                    <h3>📊 Votación en Progreso</h3>
                    <p><strong><?php echo $jurados_votados; ?> de <?php echo $total_jurados; ?></strong> jurados han completado la votación.</p>
                    <p>Esperando a que todos los jurados completen sus votaciones para mostrar los resultados finales.</p>
                    <div class="actions">
                        <a href="votacion.php?evento_id=<?php echo $evento_id; ?>" class="btn">✏️ Continuar Votando</a>
                        <a href="ver_resultados.php?evento_id=<?php echo $evento_id; ?>" class="btn btn-success">🔄 Actualizar</a>
                        <a href="dashboard.php" class="btn btn-secondary">← Volver al Panel</a>
                    </div>
                </div>

            <?php elseif ($estado_votacion == 'completado'): ?>
                <div class="estado-votacion estado-completado">
                    <h3>✅ Votación Completada</h3>
                    <p><strong>¡Todos los jurados han completado la votación!</strong> Resultados finales disponibles.</p>
                    <p><em>Haz clic en cada categoría para ver los resultados</em></p>
                </div>

                <!-- Resultados por categoría en acordeón -->
                <div class="resultados-container">
                    <h2 class="section-title">🏆 Resultados por Categoría</h2>
                    
                    <?php foreach ($resultados as $categoria_id => $categoria_data): ?>
                        <?php if ($categoria_id != 'general'): ?>
                            <div class="acordeon-categoria">
                                <div class="acordeon-header" onclick="toggleAcordeon(this)">
                                    <span>
                                        <?php echo htmlspecialchars($categoria_data['categoria_nombre']); ?> 
                                        <span class="acordeon-count">(Máximo: <?php echo $categoria_data['puntaje_maximo']; ?> puntos)</span>
                                    </span>
                                    <span class="acordeon-icon">▼</span>
                                </div>
                                <div class="acordeon-content">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Puesto</th>
                                                <th>Participante</th>
                                                <th>Promedio</th>
                                                <th>Porcentaje</th>
                                                <th>Total Votos</th>
                                                <th>Progreso</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categoria_data['participantes'] as $index => $participante): ?>
                                                <tr class="puesto-<?php echo min($index + 1, 3); ?>">
                                                    <td>
                                                        <?php if ($index == 0): ?>
                                                            <span class="medal">🥇</span>
                                                        <?php elseif ($index == 1): ?>
                                                            <span class="medal">🥈</span>
                                                        <?php elseif ($index == 2): ?>
                                                            <span class="medal">🥉</span>
                                                        <?php else: ?>
                                                            #<?php echo $index + 1; ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="participant-info">
                                                            <?php if (!empty($participante['foto']) && file_exists('../uploads/fotos/' . $participante['foto'])): ?>
                                                                <img src="../uploads/fotos/<?php echo $participante['foto']; ?>" 
                                                                     alt="<?php echo htmlspecialchars($participante['nombre']); ?>"
                                                                     class="foto-participante">
                                                            <?php else: ?>
                                                                <div class="foto-placeholder">👤</div>
                                                            <?php endif; ?>
                                                            <div class="participant-details">
                                                                <span class="participant-name"><?php echo htmlspecialchars($participante['nombre']); ?></span>
                                                                <span class="participant-representante"><?php echo htmlspecialchars($participante['representante']); ?></span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><span class="score-highlight"><?php echo number_format($participante['promedio'], 2); ?></span></td>
                                                    <td><span class="percentage-highlight"><?php echo $participante['porcentaje']; ?>%</span></td>
                                                    <td><?php echo $participante['total_votos']; ?></td>
                                                    <td class="progress-container">
                                                        <div class="barra-progreso">
                                                            <div class="progreso" style="width: <?php echo $participante['porcentaje']; ?>%"></div>
                                                        </div>
                                                        <small><?php echo $participante['porcentaje']; ?>%</small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <!-- ⭐ RESULTADO GENERAL AL FINAL EN ACORDEÓN ESPECIAL -->
                    <?php if (isset($resultados['general'])): ?>
                        <div class="acordeon-categoria acordeon-general winner-glow">
                            <div class="acordeon-header" onclick="toggleAcordeon(this)">
                                <span><?php echo $resultados['general']['categoria_nombre']; ?></span>
                                <span class="acordeon-icon">▼</span>
                            </div>
                            <div class="acordeon-content">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Puesto</th>
                                            <th>Participante</th>
                                            <th>Promedio General</th>
                                            <th>Total Votos</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($resultados['general']['participantes'] as $index => $participante): ?>
                                            <tr class="puesto-<?php echo min($index + 1, 3); ?>">
                                                <td>
                                                    <?php if ($index == 0): ?>
                                                        <span class="medal">🏆</span>
                                                    <?php elseif ($index == 1): ?>
                                                        <span class="medal">🥈</span>
                                                    <?php elseif ($index == 2): ?>
                                                        <span class="medal">🥉</span>
                                                    <?php else: ?>
                                                        #<?php echo $index + 1; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="participant-info">
                                                        <?php if (!empty($participante['foto']) && file_exists('../uploads/fotos/' . $participante['foto'])): ?>
                                                            <img src="../uploads/fotos/<?php echo $participante['foto']; ?>" 
                                                                 alt="<?php echo htmlspecialchars($participante['nombre']); ?>"
                                                                 class="foto-participante">
                                                        <?php else: ?>
                                                            <div class="foto-placeholder">👤</div>
                                                        <?php endif; ?>
                                                        <div class="participant-details">
                                                            <span class="participant-name"><?php echo htmlspecialchars($participante['nombre']); ?></span>
                                                            <span class="participant-representante"><?php echo htmlspecialchars($participante['representante']); ?></span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><span class="score-highlight" style="color: #e84393;"><?php echo number_format($participante['promedio_general'], 2); ?></span></td>
                                                <td><?php echo $participante['total_votos']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="actions">
                    <button onclick="expandAll()" class="btn">📖 Expandir Todos</button>
                    <button onclick="collapseAll()" class="btn btn-secondary">📕 Contraer Todos</button>
                    <a href="votacion.php?evento_id=<?php echo $evento_id; ?>" class="btn">✏️ Ver Lista de Participantes</a>
                    <a href="dashboard.php" class="btn btn-secondary">← Volver al Panel</a>
                </div>

            <?php endif; ?>

        <?php else: ?>
            <div class="error">
                ❌ <strong>Evento no encontrado</strong>
                <div class="actions">
                    <a href="dashboard.php" class="btn btn-secondary">← Volver al Panel del Jurado</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Función para alternar acordeones
        function toggleAcordeon(element) {
            const content = element.nextElementSibling;
            const isOpen = content.classList.contains('open');
            
            // Cerrar todos los acordeones primero (opcional)
            // closeAllAcordeons();
            
            // Alternar el acordeón actual
            if (isOpen) {
                content.classList.remove('open');
                element.classList.remove('active');
            } else {
                content.classList.add('open');
                element.classList.add('active');
            }
        }

        // Función para expandir todos los acordeones
        function expandAll() {
            const headers = document.querySelectorAll('.acordeon-header');
            headers.forEach(header => {
                const content = header.nextElementSibling;
                content.classList.add('open');
                header.classList.add('active');
            });
        }

        // Función para contraer todos los acordeones
        function collapseAll() {
            const headers = document.querySelectorAll('.acordeon-header');
            headers.forEach(header => {
                const content = header.nextElementSibling;
                content.classList.remove('open');
                header.classList.remove('active');
            });
        }

        // Función para cerrar todos los acordeones
        function closeAllAcordeons() {
            const openContents = document.querySelectorAll('.acordeon-content.open');
            const activeHeaders = document.querySelectorAll('.acordeon-header.active');
            
            openContents.forEach(content => content.classList.remove('open'));
            activeHeaders.forEach(header => header.classList.remove('active'));
        }

        // Efectos de interacción suaves
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card');
            
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Expandir automáticamente el resultado general
            const generalHeader = document.querySelector('.acordeon-general .acordeon-header');
            if (generalHeader) {
                setTimeout(() => {
                    toggleAcordeon(generalHeader);
                }, 500);
            }
        });

        // Cerrar acordeón al hacer clic fuera de él
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.acordeon-categoria')) {
                // closeAllAcordeons(); // Descomenta si quieres que se cierren al hacer clic fuera
            }
        });
    </script>
</body>
</html>