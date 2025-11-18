<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once 'C:/xampp/htdocs/votacion/src/config/database.php';

$database = new Database();
$db = $database->getConnection();

// Verificar si se proporcionó un ID de evento
if (!isset($_GET['evento_id']) || empty($_GET['evento_id'])) {
    die("Error: No se especificó el evento.");
}

$evento_id = $_GET['evento_id'];

try {
    // Obtener información del evento
    $query = "SELECT id, nombre, fecha, estado FROM eventos WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $evento_id);
    $stmt->execute();
    $evento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$evento) {
        die("Error: Evento no encontrado.");
    }
    
    // Obtener estadísticas del evento
    $estadisticas = [];
    
    // Total de participantes
    $query = "SELECT COUNT(*) as total FROM participantes WHERE evento_id = :evento_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':evento_id', $evento_id);
    $stmt->execute();
    $estadisticas['total_participantes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de categorías
    $query = "SELECT COUNT(*) as total FROM categorias WHERE evento_id = :evento_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':evento_id', $evento_id);
    $stmt->execute();
    $estadisticas['total_categorias'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de jurados
    $query = "SELECT COUNT(*) as total FROM usuarios WHERE rol = 'jurado'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $estadisticas['total_jurados'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de votos
    $query = "SELECT COUNT(*) as total 
              FROM votos v 
              JOIN participantes p ON v.participante_id = p.id 
              WHERE p.evento_id = :evento_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':evento_id', $evento_id);
    $stmt->execute();
    $estadisticas['total_votos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener información de jurados que han completado la votación
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

    $estadisticas['jurados_completados'] = 0;
    $total_participantes = $estadisticas['total_participantes'];
    
    foreach ($jurados_info as $jurado) {
        if ($jurado['participantes_votados'] == $total_participantes) {
            $estadisticas['jurados_completados']++;
        }
    }

    // Determinar estado de la votación
    if ($estadisticas['jurados_completados'] == 0) {
        $estadisticas['estado_votacion'] = 'sin_votos';
    } elseif ($estadisticas['jurados_completados'] < $estadisticas['total_jurados']) {
        $estadisticas['estado_votacion'] = 'votando';
    } else {
        $estadisticas['estado_votacion'] = 'completado';
    }

    // Obtener ranking de participantes (puntaje total)
    $query = "SELECT p.id, p.nombre, p.representante, 
                     COALESCE(SUM(v.puntaje), 0) as puntaje_total,
                     COUNT(v.id) as votos_recibidos
              FROM participantes p 
              LEFT JOIN votos v ON p.id = v.participante_id 
              WHERE p.evento_id = :evento_id 
              GROUP BY p.id, p.nombre, p.representante
              ORDER BY puntaje_total DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':evento_id', $evento_id);
    $stmt->execute();
    $ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener resultados detallados por categoría si hay votos
    $resultados_detallados = [];
    if ($estadisticas['total_votos'] > 0) {
        // Obtener categorías del evento
        $query = "SELECT id, nombre, puntaje_maximo FROM categorias WHERE evento_id = :evento_id ORDER BY nombre";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':evento_id', $evento_id);
        $stmt->execute();
        $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcular resultados por categoría
        foreach ($categorias as $categoria) {
            $query = "SELECT p.id, p.nombre, p.representante,
                             AVG(v.puntaje) as promedio,
                             COUNT(v.puntaje) as total_votos,
                             SUM(v.puntaje) as puntaje_total
                      FROM participantes p
                      LEFT JOIN votos v ON p.id = v.participante_id AND v.categoria_id = :categoria_id
                      WHERE p.evento_id = :evento_id
                      GROUP BY p.id, p.nombre, p.representante
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

            $resultados_detallados[$categoria['id']] = [
                'categoria_nombre' => $categoria['nombre'],
                'puntaje_maximo' => $max_puntaje,
                'participantes' => $resultados_categoria
            ];
        }

        // Calcular resultados generales (promedio de todos los votos)
        $query = "SELECT p.id, p.nombre, p.representante,
                         AVG(v.puntaje) as promedio_general,
                         COUNT(v.puntaje) as total_votos
                  FROM participantes p
                  LEFT JOIN votos v ON p.id = v.participante_id
                  WHERE p.evento_id = :evento_id
                  GROUP BY p.id, p.nombre, p.representante
                  ORDER BY promedio_general DESC";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':evento_id', $evento_id);
        $stmt->execute();
        $resultados_generales = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultados_detallados['general'] = [
            'categoria_nombre' => 'Resultado General',
            'participantes' => $resultados_generales
        ];
    }

} catch (PDOException $e) {
    die("Error al obtener datos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte - <?php echo htmlspecialchars($evento['nombre']); ?></title>
    <style>
        /* Estilos optimizados para impresión PDF */
        @media print {
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Arial', 'Helvetica', sans-serif;
                font-size: 12px;
                line-height: 1.4;
                color: #000;
                background: #fff;
                margin: 0;
                padding: 15px;
            }
            
            .no-print {
                display: none !important;
            }
            
            .page-break {
                page-break-after: always;
                margin-top: 20px;
                padding-top: 20px;
                border-top: 2px dashed #ccc;
            }
            
            .container {
                max-width: 100%;
                margin: 0;
                padding: 0;
            }
            
            .header {
                text-align: center;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 3px double #333;
            }
            
            .header h1 {
                font-size: 24px;
                color: #2c3e50;
                margin-bottom: 5px;
            }
            
            .header p {
                font-size: 14px;
                color: #7f8c8d;
                font-style: italic;
            }
            
            .section {
                margin-bottom: 25px;
                page-break-inside: avoid;
            }
            
            h2 {
                font-size: 18px;
                color: #2c3e50;
                margin-bottom: 15px;
                padding-bottom: 8px;
                border-bottom: 2px solid #3498db;
                page-break-after: avoid;
            }
            
            h3 {
                font-size: 16px;
                color: #34495e;
                margin-bottom: 12px;
                page-break-after: avoid;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 12px 0;
                font-size: 11px;
                page-break-inside: avoid;
            }
            
            th {
                background: #34495e !important;
                color: white !important;
                font-weight: bold;
                padding: 8px 6px;
                border: 1px solid #2c3e50;
                text-align: left;
            }
            
            td {
                padding: 6px;
                border: 1px solid #bdc3c7;
                text-align: left;
                vertical-align: top;
            }
            
            .puesto-1 {
                background-color: #fff3cd !important;
                font-weight: bold;
            }
            
            .puesto-2 {
                background-color: #f8f9fa !important;
            }
            
            .puesto-3 {
                background-color: #e9ecef !important;
            }
            
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
                margin: 15px 0;
                page-break-inside: avoid;
            }
            
            .stat-item {
                background: #ecf0f1;
                padding: 12px 8px;
                border: 1px solid #bdc3c7;
                border-radius: 6px;
                text-align: center;
                page-break-inside: avoid;
            }
            
            .stat-number {
                font-size: 20px;
                font-weight: bold;
                color: #2c3e50;
                display: block;
                margin-bottom: 4px;
            }
            
            .stat-label {
                font-size: 10px;
                color: #7f8c8d;
                text-transform: uppercase;
                font-weight: 600;
            }
            
            .evento-info {
                background: #d5edda;
                padding: 15px;
                border: 1px solid #c3e6cb;
                border-radius: 8px;
                margin-bottom: 20px;
                page-break-inside: avoid;
            }
            
            .estado-votacion {
                background: #fff3cd;
                padding: 15px;
                border: 1px solid #ffeaa7;
                border-radius: 8px;
                margin-bottom: 20px;
                text-align: center;
                page-break-inside: avoid;
            }
            
            .estado-completado {
                background: #d4edda;
                border-color: #c3e6cb;
            }
            
            .barra-progreso {
                background: #ecf0f1;
                border-radius: 4px;
                height: 12px;
                margin: 3px 0;
                overflow: hidden;
            }
            
            .progreso {
                background: #27ae60;
                height: 100%;
                border-radius: 4px;
            }
            
            .categoria-resultados {
                margin-bottom: 25px;
                page-break-inside: avoid;
            }
            
            .categoria-header {
                background: #3498db;
                color: white;
                padding: 12px 15px;
                margin-bottom: 0;
                font-size: 14px;
                font-weight: bold;
                page-break-after: avoid;
            }
            
            .timestamp {
                text-align: center;
                font-style: italic;
                color: #7f8c8d;
                margin-top: 25px;
                padding-top: 15px;
                border-top: 1px solid #bdc3c7;
                font-size: 10px;
                page-break-before: avoid;
            }
            
            .medal {
                font-size: 12px;
                margin-right: 4px;
            }
            
            /* Evitar que las tablas se dividan entre páginas */
            table {
                page-break-inside: avoid;
            }
            
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            
            /* Mejorar la legibilidad en PDF */
            .text-center {
                text-align: center;
            }
            
            .text-right {
                text-align: right;
            }
            
            .font-bold {
                font-weight: bold;
            }
        }

        /* Estilos para vista previa en pantalla */
        @media screen {
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
                background: rgba(255, 255, 255, 0.95);
                padding: 30px;
                border-radius: 15px;
                box-shadow: 0 15px 35px rgba(0,0,0,0.1);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255,255,255,0.2);
            }
            
            .header {
                text-align: center;
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 3px solid #007bff;
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
            
            .preview-actions {
                text-align: center;
                margin: 25px 0;
                padding: 20px;
                background: linear-gradient(135deg, #e7f3ff, #d1ecf1);
                border-radius: 12px;
                border-left: 5px solid #007bff;
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
                margin: 0 10px;
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
        }

        /* Estilos comunes */
        .evento-info h3 {
            color: #155724;
            margin-bottom: 10px;
        }
        
        .estado-votacion h3 {
            color: #856404;
            margin-bottom: 8px;
        }
        
        .estado-completado h3 {
            color: #155724;
        }
        
        .instructions {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #ffc107;
            font-size: 0.9rem;
        }
        
        .instructions ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .instructions li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Acciones de vista previa (solo en pantalla) -->
        <div class="preview-actions no-print">
            <h3>📄 Vista Previa del Reporte</h3>
            <p>Este documento está optimizado para impresión y exportación a PDF.</p>
            
            <div style="margin: 20px 0;">
                <button class="btn btn-success" onclick="window.print()">
                    🖨️ Imprimir / Guardar como PDF
                </button>
                <a href="resultados.php?evento_id=<?php echo $evento_id; ?>" class="btn btn-secondary">
                    ← Volver a Resultados
                </a>
            </div>
            
            <div class="instructions">
                <strong>💡 Instrucciones para exportar:</strong>
                <ul>
                    <li>Haz clic en "Imprimir / Guardar como PDF"</li>
                    <li>En la ventana de impresión, selecciona "Guardar como PDF" como destino</li>
                    <li>Ajusta la configuración si es necesario (márgenes, orientación)</li>
                    <li>Haz clic en "Guardar" para descargar el reporte</li>
                </ul>
            </div>
        </div>

        <!-- Encabezado del reporte -->
        <div class="header">
            <h1>REPORTE OFICIAL DE RESULTADOS</h1>
            <p>Sistema de Votación - Documento Certificado</p>
        </div>

        <!-- Información del evento -->
        <div class="section">
            <h2>INFORMACIÓN DEL EVENTO</h2>
            <div class="evento-info">
                <h3><?php echo htmlspecialchars($evento['nombre']); ?></h3>
                <p><strong>Fecha del Evento:</strong> <?php echo date('d/m/Y', strtotime($evento['fecha'])); ?></p>
                <p><strong>Estado:</strong> <?php echo $evento['estado']; ?></p>
                <p><strong>ID de Referencia:</strong> #<?php echo $evento['id']; ?></p>
            </div>
        </div>

        <!-- Estado de la votación -->
        <div class="estado-votacion <?php echo $estadisticas['estado_votacion'] == 'completado' ? 'estado-completado' : ''; ?>">
            <?php if ($estadisticas['estado_votacion'] == 'sin_votos'): ?>
                <h3>⏳ VOTACIÓN PENDIENTE</h3>
                <p>Ningún jurado ha completado la votación de todos los participantes.</p>
            <?php elseif ($estadisticas['estado_votacion'] == 'votando'): ?>
                <h3>📊 VOTACIÓN EN PROCESO</h3>
                <p><strong><?php echo $estadisticas['jurados_completados']; ?> de <?php echo $estadisticas['total_jurados']; ?></strong> jurados han completado la votación.</p>
                <p><em>Este es un reporte parcial de los resultados hasta el momento.</em></p>
            <?php elseif ($estadisticas['estado_votacion'] == 'completado'): ?>
                <h3>✅ VOTACIÓN COMPLETADA</h3>
                <p><strong>Todos los jurados han completado la votación.</strong></p>
                <p><em>Reporte final con resultados definitivos.</em></p>
            <?php endif; ?>
        </div>

        <!-- Estadísticas generales -->
        <div class="section">
            <h2>ESTADÍSTICAS GENERALES</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number"><?php echo $estadisticas['total_participantes']; ?></span>
                    <span class="stat-label">Participantes</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $estadisticas['total_categorias']; ?></span>
                    <span class="stat-label">Categorías</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $estadisticas['total_jurados']; ?></span>
                    <span class="stat-label">Jurados Totales</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $estadisticas['jurados_completados']; ?></span>
                    <span class="stat-label">Jurados Completaron</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $estadisticas['total_votos']; ?></span>
                    <span class="stat-label">Votos Emitidos</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">
                        <?php if ($estadisticas['total_participantes'] > 0 && $estadisticas['total_jurados'] > 0): ?>
                            <?php echo round(($estadisticas['total_votos'] / ($estadisticas['total_participantes'] * $estadisticas['total_jurados'])) * 100, 1); ?>%
                        <?php else: ?>
                            0%
                        <?php endif; ?>
                    </span>
                    <span class="stat-label">Progreso Total</span>
                </div>
            </div>
        </div>

        <!-- Ranking general de participantes -->
        <div class="section">
            <h2>RANKING GENERAL - CLASIFICACIÓN FINAL</h2>
            <?php if (empty($ranking) || $estadisticas['total_votos'] == 0): ?>
                <p class="text-center"><em>No hay votos registrados para este evento.</em></p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th width="15%">Puesto</th>
                            <th width="25%">Participante</th>
                            <th width="30%">Representa</th>
                            <th width="15%">Puntaje Total</th>
                            <th width="15%">Votos Recibidos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ranking as $index => $participante): ?>
                            <tr class="puesto-<?php echo min($index + 1, 3); ?>">
                                <td class="font-bold">
                                    <?php if ($index == 0): ?>
                                        <span class="medal">🥇</span> PRIMER PUESTO
                                    <?php elseif ($index == 1): ?>
                                        <span class="medal">🥈</span> SEGUNDO PUESTO
                                    <?php elseif ($index == 2): ?>
                                        <span class="medal">🥉</span> TERCER PUESTO
                                    <?php else: ?>
                                        #<?php echo $index + 1; ?>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($participante['nombre']); ?></strong></td>
                                <td><?php echo htmlspecialchars($participante['representante']); ?></td>
                                <td class="text-center"><strong><?php echo $participante['puntaje_total']; ?> pts</strong></td>
                                <td class="text-center"><?php echo $participante['votos_recibidos']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Resultados detallados por categoría -->
        <?php if ($estadisticas['total_votos'] > 0 && !empty($resultados_detallados)): ?>
            <div class="section">
                <h2>RESULTADOS DETALLADOS POR CATEGORÍA</h2>
                
                <?php foreach ($resultados_detallados as $categoria_id => $categoria_data): ?>
                    <?php if ($categoria_id != 'general'): ?>
                        <div class="categoria-resultados">
                            <div class="categoria-header">
                                CATEGORÍA: <?php echo htmlspecialchars($categoria_data['categoria_nombre']); ?> 
                                <small>(Puntaje máximo: <?php echo $categoria_data['puntaje_maximo']; ?> puntos)</small>
                            </div>
                            <table>
                                <thead>
                                    <tr>
                                        <th width="12%">Puesto</th>
                                        <th width="28%">Participante</th>
                                        <th width="15%">Promedio</th>
                                        <th width="15%">Porcentaje</th>
                                        <th width="15%">Total Votos</th>
                                        <th width="15%">Progreso</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categoria_data['participantes'] as $index => $participante): ?>
                                        <tr class="puesto-<?php echo min($index + 1, 3); ?>">
                                            <td>
                                                <?php if ($index == 0): ?>
                                                    <span class="medal">🥇</span> 1°
                                                <?php elseif ($index == 1): ?>
                                                    <span class="medal">🥈</span> 2°
                                                <?php elseif ($index == 2): ?>
                                                    <span class="medal">🥉</span> 3°
                                                <?php else: ?>
                                                    #<?php echo $index + 1; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($participante['nombre']); ?></strong></td>
                                            <td class="text-center"><strong><?php echo number_format($participante['promedio'], 2); ?></strong></td>
                                            <td class="text-center"><strong><?php echo $participante['porcentaje']; ?>%</strong></td>
                                            <td class="text-center"><?php echo $participante['total_votos']; ?></td>
                                            <td>
                                                <div class="barra-progreso">
                                                    <div class="progreso" style="width: <?php echo $participante['porcentaje']; ?>%"></div>
                                                </div>
                                                <small class="text-center"><?php echo $participante['porcentaje']; ?>%</small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <!-- Resultados generales -->
                <?php if (isset($resultados_detallados['general'])): ?>
                    <div class="page-break"></div>
                    <div class="categoria-resultados">
                        <div class="categoria-header">
                            RESULTADO GENERAL - PROMEDIO FINAL
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th width="15%">Puesto</th>
                                    <th width="45%">Participante</th>
                                    <th width="20%">Promedio General</th>
                                    <th width="20%">Total Votos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resultados_detallados['general']['participantes'] as $index => $participante): ?>
                                    <tr class="puesto-<?php echo min($index + 1, 3); ?>">
                                        <td class="font-bold">
                                            <?php if ($index == 0): ?>
                                                <span class="medal">🥇</span> 1°
                                            <?php elseif ($index == 1): ?>
                                                <span class="medal">🥈</span> 2°
                                            <?php elseif ($index == 2): ?>
                                                <span class="medal">🥉</span> 3°
                                            <?php else: ?>
                                                #<?php echo $index + 1; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($participante['nombre']); ?></strong></td>
                                        <td class="text-center"><strong><?php echo number_format($participante['promedio_general'], 2); ?></strong></td>
                                        <td class="text-center"><?php echo $participante['total_votos']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Pie de página con timestamp -->
        <div class="timestamp">
            <p><strong>Documento generado electrónicamente por el Sistema de Votación</strong></p>
            <p>Fecha y hora de generación: <?php echo date('d/m/Y \a \l\a\s H:i:s'); ?></p>
            <p>Este documento es una representación oficial de los resultados del evento.</p>
        </div>

        <!-- Mensaje final solo en pantalla -->
        <div class="no-print" style="text-align: center; margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <p>✅ <strong>Reporte listo para imprimir o guardar como PDF</strong></p>
            <p>Usa el botón "Imprimir / Guardar como PDF" en la parte superior para descargar este reporte.</p>
        </div>
    </div>

    <script>
        // Auto-imprimir opcional (descomenta la siguiente línea si quieres impresión automática)
        // window.onload = function() { setTimeout(() => window.print(), 1000); };
        
        // Mejorar la experiencia de impresión
        window.addEventListener('beforeprint', function() {
            document.title = "Reporte_<?php echo htmlspecialchars($evento['nombre']); ?>_<?php echo date('Y-m-d'); ?>";
        });
    </script>
</body>
</html>