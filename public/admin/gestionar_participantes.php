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
$success = '';

// Mostrar mensajes de éxito/error
if (isset($_GET['success'])) {
    if ($_GET['success'] == 1) {
        $success = "Participante creado exitosamente.";
    } elseif ($_GET['success'] == 2) {
        $success = "Participante actualizado exitosamente.";
    } elseif ($_GET['success'] == 3) {
        $success = "Participante eliminado exitosamente.";
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

// Procesar agregar participante
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_participante']) && $evento) {
    $nombre = trim($_POST['nombre']);
    $representante = trim($_POST['representante']);
    $edad = $_POST['edad'];
    $descripcion = trim($_POST['descripcion']);
    $foto_nombre = null;

    // Validaciones básicas
    if (empty($nombre) || empty($representante) || empty($edad)) {
        $error = "Nombre, representante y edad son obligatorios.";
    } elseif ($edad < 1 || $edad > 100) {
        $error = "La edad debe ser entre 1 y 100 años.";
    } else {
        try {
            // Verificar si ya existe este participante en el evento
            $query = "SELECT id FROM participantes WHERE nombre = :nombre AND evento_id = :evento_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':evento_id', $evento['id']);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $error = "Ya existe un participante con ese nombre en este evento.";
            } else {
                // Procesar foto si se subió
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    $foto = $_FILES['foto'];
                    
                    // Validar tipo de archivo
                    $extension = strtolower(pathinfo($foto['name'], PATHINFO_EXTENSION));
                    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
                    $tipos_permitidos = ['image/jpeg', 'image/png', 'image/gif'];
                    
                    // Verificar extensión y tipo MIME
                    if (!in_array($extension, $extensiones_permitidas) || 
                        !in_array($foto['type'], $tipos_permitidos)) {
                        $error = "Formato de imagen no permitido. Use JPG, PNG o GIF.";
                    } 
                    // Verificar tamaño (máximo 2MB)
                    elseif ($foto['size'] > 2 * 1024 * 1024) {
                        $error = "La imagen es demasiado grande. Máximo 2MB permitido.";
                    } else {
                        // Crear nombre único para la foto
                        $foto_nombre = 'participante_' . $evento['id'] . '_' . time() . '.' . $extension;
                        $ruta_destino = '../uploads/fotos/' . $foto_nombre;
                        
                        // Crear directorio si no existe
                        if (!is_dir('../uploads/fotos/')) {
                            mkdir('../uploads/fotos/', 0777, true);
                        }
                        
                        // Mover archivo
                        if (!move_uploaded_file($foto['tmp_name'], $ruta_destino)) {
                            $error = "Error al subir la imagen. Intente nuevamente.";
                            $foto_nombre = null;
                        }
                    }
                } elseif ($_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
                    // Error en la subida (diferente a "no se seleccionó archivo")
                    $error = "Error al subir la imagen: " . getUploadError($_FILES['foto']['error']);
                }

                // Si no hay errores, insertar el participante
                if (empty($error)) {
                    // Insertar nuevo participante
                    $query = "INSERT INTO participantes (nombre, representante, edad, descripcion, evento_id, foto) VALUES (:nombre, :representante, :edad, :descripcion, :evento_id, :foto)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':nombre', $nombre);
                    $stmt->bindParam(':representante', $representante);
                    $stmt->bindParam(':edad', $edad);
                    $stmt->bindParam(':descripcion', $descripcion);
                    $stmt->bindParam(':evento_id', $evento['id']);
                    $stmt->bindParam(':foto', $foto_nombre);
                    
                    if ($stmt->execute()) {
                        // Redirigir para mostrar mensaje de éxito
                        header("Location: gestionar_participantes.php?evento_id=" . $evento['id'] . "&success=1");
                        exit();
                    } else {
                        $error = "Error al agregar el participante.";
                        // Eliminar foto si se subió pero falló la inserción
                        if ($foto_nombre && file_exists('../uploads/fotos/' . $foto_nombre)) {
                            unlink('../uploads/fotos/' . $foto_nombre);
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Error de base de datos: " . $e->getMessage();
            // Eliminar foto si se subió pero falló la inserción
            if ($foto_nombre && file_exists('../uploads/fotos/' . $foto_nombre)) {
                unlink('../uploads/fotos/' . $foto_nombre);
            }
        }
    }
}

// Función para obtener mensajes de error de subida
function getUploadError($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "El archivo es demasiado grande.";
        case UPLOAD_ERR_PARTIAL:
            return "El archivo se subió parcialmente.";
        case UPLOAD_ERR_NO_FILE:
            return "No se seleccionó ningún archivo.";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Error del servidor: No existe directorio temporal.";
        case UPLOAD_ERR_CANT_WRITE:
            return "Error del servidor: No se pudo guardar el archivo.";
        case UPLOAD_ERR_EXTENSION:
            return "Extensión de archivo no permitida.";
        default:
            return "Error desconocido al subir el archivo.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Participantes - Sistema de Votación</title>
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
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group-full {
            grid-column: 1 / -1;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        input[type="text"],
        input[type="number"],
        textarea,
        input[type="file"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.9);
        }
        
        input[type="text"]:focus,
        input[type="number"]:focus,
        textarea:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
            transform: translateY(-2px);
        }
        
        input[type="file"] {
            padding: 10px;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
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
            width: 60px;
            height: 60px;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border: 2px solid rgba(0,123,255,0.1);
            transition: all 0.3s ease;
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
            font-size: 1.2rem;
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
        
        .form-help {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 8px;
            font-style: italic;
        }
        
        @media (max-width: 768px) {
            .actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn {
                justify-content: center;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
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
            <h1>👥 Gestionar Participantes del Evento</h1>
            <p>Agregue, edite o elimine concursantes</p>
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
                <a href="participantes.php" class="btn">
                    ← Volver a Eventos
                </a>
                <div class="total-counter">
                    📊 <?php echo count($participantes); ?> participantes inscritos
                </div>
            </div>

            <!-- Formulario para agregar participante -->
            <div class="form-container">
                <h3>➕ Agregar Nuevo Participante</h3>
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="agregar_participante" value="1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nombre">👤 Nombre Completo</label>
                            <input type="text" id="nombre" name="nombre" required 
                                   placeholder="Ej: María González">
                        </div>
                        <div class="form-group">
                            <label for="representante">🏢 Representa a</label>
                            <input type="text" id="representante" name="representante" required 
                                   placeholder="Ej: 4°A, Barrio Centro, Institución">
                        </div>
                        <div class="form-group">
                            <label for="edad">🎂 Edad</label>
                            <input type="number" id="edad" name="edad" required min="1" max="100" 
                                   placeholder="Ej: 17">
                        </div>
                        <div class="form-group">
                            <label for="foto">📷 Foto del Participante</label>
                            <input type="file" id="foto" name="foto" accept="image/*">
                            <div class="form-help">Formatos: JPG, PNG, GIF (Máx. 2MB)</div>
                        </div>
                        <div class="form-group form-group-full">
                            <label for="descripcion">📝 Descripción/Información</label>
                            <textarea id="descripcion" name="descripcion" 
                                      placeholder="Información adicional sobre el participante..."></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success">
                        ✅ Agregar Participante
                    </button>
                </form>
            </div>

            <!-- Lista de participantes existentes -->
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
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($participantes)): ?>
                            <tr>
                                <td colspan="7" class="no-data">
                                    <span class="icon">📝</span>
                                    <h3>Este evento no tiene participantes</h3>
                                    <p>¡Use el formulario superior para inscribir el primer participante!</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($participantes as $participante): ?>
                            <tr>
                                <td>
                                    <span class="participante-id">#<?php echo $participante['id']; ?></span>
                                </td>
                                <td>
                                    <div class="foto-container">
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
                                    <div title="<?php echo htmlspecialchars($participante['descripcion']); ?>">
                                        <?php 
                                        $descripcion = $participante['descripcion'];
                                        if (strlen($descripcion) > 50) {
                                            echo htmlspecialchars(substr($descripcion, 0, 50)) . '...';
                                        } else {
                                            echo htmlspecialchars($descripcion);
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="editar_participante.php?id=<?php echo $participante['id']; ?>" class="btn btn-warning btn-small">
                                            ✏️ Editar
                                        </a>
                                        <a href="eliminar_participante.php?id=<?php echo $participante['id']; ?>" class="btn btn-danger btn-small" 
                                           onclick="return confirm('¿Estás seguro de eliminar al participante \'<?php echo htmlspecialchars($participante['nombre']); ?>\'?')">
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
                <a href="participantes.php" class="btn btn-secondary">
                    ← Volver a la Lista de Eventos
                </a>
            </div>

        <?php else: ?>
            <div style="text-align: center; margin-top: 25px;">
                <a href="participantes.php" class="btn">
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

            // Efecto en inputs del formulario
            const inputs = document.querySelectorAll('input, textarea');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                input.addEventListener('blur', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>