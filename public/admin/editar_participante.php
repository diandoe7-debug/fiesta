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

// Obtener datos del participante a editar
$participante = null;
$evento = null;
if (isset($_GET['id'])) {
    try {
        $query = "SELECT p.*, e.nombre as evento_nombre, e.id as evento_id 
                 FROM participantes p 
                 JOIN eventos e ON p.evento_id = e.id 
                 WHERE p.id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $_GET['id']);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $participante = $stmt->fetch(PDO::FETCH_ASSOC);
            $evento = [
                'id' => $participante['evento_id'],
                'nombre' => $participante['evento_nombre']
            ];
        } else {
            $error = "Participante no encontrado.";
        }
    } catch (PDOException $e) {
        $error = "Error al cargar el participante: " . $e->getMessage();
    }
} else {
    $error = "ID de participante no especificado.";
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $participante) {
    $nombre = trim($_POST['nombre']);
    $representante = trim($_POST['representante']);
    $edad = $_POST['edad'];
    $descripcion = trim($_POST['descripcion']);
    $foto_nombre = $participante['foto']; // Mantener foto actual por defecto

    // Validaciones
    if (empty($nombre) || empty($representante) || empty($edad)) {
        $error = "Nombre, representante y edad son obligatorios.";
    } elseif ($edad < 1 || $edad > 100) {
        $error = "La edad debe ser entre 1 y 100 años.";
    } else {
        try {
            // Verificar si el nombre ya existe en otro participante del mismo evento
            $query = "SELECT id FROM participantes WHERE nombre = :nombre AND evento_id = :evento_id AND id != :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':evento_id', $participante['evento_id']);
            $stmt->bindParam(':id', $participante['id']);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $error = "Ya existe un participante con ese nombre en este evento.";
            } else {
                // Procesar nueva foto si se subió
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
                        // Crear nombre único para la nueva foto
                        $nueva_foto_nombre = 'participante_' . $participante['evento_id'] . '_' . time() . '.' . $extension;
                        $ruta_destino = '../uploads/fotos/' . $nueva_foto_nombre;
                        
                        // Crear directorio si no existe
                        if (!is_dir('../uploads/fotos/')) {
                            mkdir('../uploads/fotos/', 0777, true);
                        }
                        
                        // Mover archivo
                        if (move_uploaded_file($foto['tmp_name'], $ruta_destino)) {
                            // Eliminar foto anterior si existe
                            if (!empty($participante['foto']) && file_exists('../uploads/fotos/' . $participante['foto'])) {
                                unlink('../uploads/fotos/' . $participante['foto']);
                            }
                            $foto_nombre = $nueva_foto_nombre;
                        } else {
                            $error = "Error al subir la nueva imagen. Intente nuevamente.";
                        }
                    }
                } elseif ($_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
                    // Error en la subida (diferente a "no se seleccionó archivo")
                    $error = "Error al subir la imagen: " . getUploadError($_FILES['foto']['error']);
                }

                // Si no hay errores, actualizar el participante
                if (empty($error)) {
                    // Actualizar participante
                    $query = "UPDATE participantes SET nombre = :nombre, representante = :representante, edad = :edad, descripcion = :descripcion, foto = :foto WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':nombre', $nombre);
                    $stmt->bindParam(':representante', $representante);
                    $stmt->bindParam(':edad', $edad);
                    $stmt->bindParam(':descripcion', $descripcion);
                    $stmt->bindParam(':foto', $foto_nombre);
                    $stmt->bindParam(':id', $participante['id']);
                    
                    if ($stmt->execute()) {
                        // Actualizar datos locales
                        $participante['nombre'] = $nombre;
                        $participante['representante'] = $representante;
                        $participante['edad'] = $edad;
                        $participante['descripcion'] = $descripcion;
                        $participante['foto'] = $foto_nombre;
                        
                        // Redirigir para mostrar mensaje de éxito
                        header("Location: gestionar_participantes.php?evento_id=" . $evento['id'] . "&success=2");
                        exit();
                    } else {
                        $error = "Error al actualizar el participante.";
                        // Eliminar nueva foto si se subió pero falló la actualización
                        if (isset($nueva_foto_nombre) && file_exists('../uploads/fotos/' . $nueva_foto_nombre)) {
                            unlink('../uploads/fotos/' . $nueva_foto_nombre);
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Error de base de datos: " . $e->getMessage();
            // Eliminar nueva foto si se subió pero falló la actualización
            if (isset($nueva_foto_nombre) && file_exists('../uploads/fotos/' . $nueva_foto_nombre)) {
                unlink('../uploads/fotos/' . $nueva_foto_nombre);
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
    <title>Editar Participante - Sistema de Votación</title>
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
            max-width: 700px;
            width: 100%;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 20px 20px 0 0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-bottom: none;
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
        
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-top: none;
        }
        
        .evento-info {
            background: linear-gradient(135deg, #e7f3ff, #d1ecf1);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 5px solid #007bff;
        }
        
        .evento-info h3 {
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 1.3rem;
        }
        
        .current-info {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
            text-align: center;
            font-weight: 600;
            color: #856404;
        }
        
        .foto-section {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 2px dashed #dee2e6;
            text-align: center;
        }
        
        .foto-preview {
            width: 200px;
            height: 200px;
            margin: 0 auto 20px;
            border-radius: 12px;
            overflow: hidden;
            border: 4px solid #007bff;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            transition: all 0.3s ease;
        }
        
        .foto-preview:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 25px rgba(0,123,255,0.2);
        }
        
        .foto-preview img {
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
            font-size: 3rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group-full {
            grid-column: 1 / -1;
        }
        
        label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 1rem;
        }
        
        input[type="text"],
        input[type="number"],
        textarea,
        input[type="file"] {
            width: 100%;
            padding: 15px;
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
            padding: 12px;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
            font-size: 1rem;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,123,255,0.4);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #212529;
            box-shadow: 0 4px 15px rgba(255,193,7,0.3);
        }
        
        .btn-warning:hover {
            box-shadow: 0 6px 20px rgba(255,193,7,0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            box-shadow: 0 4px 15px rgba(108,117,125,0.3);
        }
        
        .btn-secondary:hover {
            box-shadow: 0 6px 20px rgba(108,117,125,0.4);
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
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        
        .left-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .right-actions {
            display: flex;
            gap: 10px;
        }
        
        .form-help {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 8px;
            font-style: italic;
        }
        
        .current-foto-info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
            text-align: center;
            font-weight: 500;
            color: #0c5460;
        }
        
        .foto-actions {
            margin-top: 15px;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .left-actions, .right-actions {
                width: 100%;
                justify-content: center;
            }
            
            .btn {
                flex: 1;
                justify-content: center;
            }
            
            .foto-preview {
                width: 150px;
                height: 150px;
            }
        }
        
        /* Animaciones */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-container {
            animation: fadeIn 0.6s ease-out;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✏️ Editar Participante</h1>
            <p>Modifique los datos del concursante</p>
        </div>

        <div class="form-container">
            <?php if (!empty($error)): ?>
                <div class="error">
                    ❌ <strong>Error:</strong> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="success">
                    ✅ <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if ($participante && $evento): ?>
                <div class="evento-info">
                    <h3>🎯 <?php echo htmlspecialchars($evento['nombre']); ?></h3>
                    <div style="color: #6c757d; font-size: 0.9rem;">
                        ID del Evento: #<?php echo $evento['id']; ?>
                    </div>
                </div>

                <div class="current-info">
                    📝 Editando participante: <strong>#<?php echo $participante['id']; ?></strong>
                </div>

                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="foto-section">
                        <label>📷 Foto del Participante</label>
                        
                        <div class="foto-preview" id="fotoPreviewContainer">
                            <?php if (!empty($participante['foto']) && file_exists('../uploads/fotos/' . $participante['foto'])): ?>
                                <img src="../uploads/fotos/<?php echo $participante['foto']; ?>" 
                                     alt="<?php echo htmlspecialchars($participante['nombre']); ?>"
                                     id="fotoPreview">
                            <?php else: ?>
                                <div class="foto-placeholder" id="fotoPlaceholder">
                                    👤
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($participante['foto'])): ?>
                            <div class="current-foto-info">
                                ✅ Foto actual cargada
                            </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <input type="file" id="foto" name="foto" accept="image/*" onchange="previewImage(this)">
                            <div class="form-help">Formatos: JPG, PNG, GIF (Máx. 2MB). Deje vacío para mantener la foto actual.</div>
                        </div>

                        <?php if (!empty($participante['foto'])): ?>
                            <div class="foto-actions">
                                <a href="eliminar_foto_participante.php?id=<?php echo $participante['id']; ?>" 
                                   class="btn btn-danger btn-small" 
                                   onclick="return confirm('¿Estás seguro de eliminar la foto de este participante?')">
                                    🗑️ Eliminar Foto Actual
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nombre">👤 Nombre Completo</label>
                            <input type="text" id="nombre" name="nombre" 
                                   value="<?php echo htmlspecialchars($participante['nombre']); ?>" 
                                   required 
                                   placeholder="Ej: María González">
                        </div>
                        <div class="form-group">
                            <label for="edad">🎂 Edad</label>
                            <input type="number" id="edad" name="edad" 
                                   value="<?php echo $participante['edad']; ?>" 
                                   required min="1" max="100" 
                                   placeholder="Ej: 17">
                        </div>
                        <div class="form-group form-group-full">
                            <label for="representante">🏢 Representa a</label>
                            <input type="text" id="representante" name="representante" 
                                   value="<?php echo htmlspecialchars($participante['representante']); ?>" 
                                   required 
                                   placeholder="Ej: 4°A, Barrio Centro, Institución">
                        </div>
                        <div class="form-group form-group-full">
                            <label for="descripcion">📝 Descripción/Información</label>
                            <textarea id="descripcion" name="descripcion" 
                                      placeholder="Información adicional sobre el participante..."><?php echo htmlspecialchars($participante['descripcion']); ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <div class="left-actions">
                            <a href="gestionar_participantes.php?evento_id=<?php echo $evento['id']; ?>" class="btn btn-secondary">
                                ← Volver
                            </a>
                            <a href="participantes.php" class="btn btn-secondary">
                                📋 Todos los Eventos
                            </a>
                        </div>
                        <div class="right-actions">
                            <button type="submit" class="btn btn-warning">
                                💾 Guardar Cambios
                            </button>
                        </div>
                    </div>
                </form>

            <?php else: ?>
                <div class="error">
                    ❌ No se puede cargar la información del participante.
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="participantes.php" class="btn">
                        📋 Volver a la gestión de participantes
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function previewImage(input) {
            const preview = document.getElementById('fotoPreview');
            const placeholder = document.getElementById('fotoPlaceholder');
            const container = document.getElementById('fotoPreviewContainer');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Ocultar placeholder si existe
                    if (placeholder) {
                        placeholder.style.display = 'none';
                    }
                    
                    // Crear o actualizar la imagen de preview
                    if (!preview) {
                        const newPreview = document.createElement('img');
                        newPreview.id = 'fotoPreview';
                        newPreview.src = e.target.result;
                        newPreview.style.width = '100%';
                        newPreview.style.height = '100%';
                        newPreview.style.objectFit = 'cover';
                        container.appendChild(newPreview);
                    } else {
                        preview.src = e.target.result;
                    }
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Efectos en inputs
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input, textarea');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 8px 25px rgba(0,123,255,0.15)';
                });
                
                input.addEventListener('blur', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = 'none';
                });
            });

            // Prevenir envío duplicado
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.innerHTML = '⏳ Guardando...';
                        submitBtn.disabled = true;
                    }
                });
            }
        });
    </script>
</body>
</html>