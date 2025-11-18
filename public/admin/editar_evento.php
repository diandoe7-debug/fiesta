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

// Obtener datos del evento a editar
$evento = null;
if (isset($_GET['id'])) {
    try {
        $query = "SELECT id, nombre, fecha, descripcion, estado FROM eventos WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $_GET['id']);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $evento = $stmt->fetch(PDO::FETCH_ASSOC);
            // Formatear fecha para el input
            $evento['fecha'] = date('Y-m-d', strtotime($evento['fecha']));
        } else {
            $error = "Evento no encontrado.";
        }
    } catch (PDOException $e) {
        $error = "Error al cargar el evento: " . $e->getMessage();
    }
} else {
    $error = "ID de evento no especificado.";
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $evento) {
    $nombre = trim($_POST['nombre']);
    $fecha = $_POST['fecha'];
    $descripcion = trim($_POST['descripcion']);
    $estado = $_POST['estado'];

    // Validaciones
    if (empty($nombre) || empty($fecha) || empty($descripcion)) {
        $error = "Todos los campos son obligatorios.";
    } else {
        try {
            // Verificar si el nombre ya existe en otro evento
            $query = "SELECT id FROM eventos WHERE nombre = :nombre AND id != :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':id', $evento['id']);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $error = "Ya existe un evento con ese nombre.";
            } else {
                // Actualizar evento
                $query = "UPDATE eventos SET nombre = :nombre, fecha = :fecha, descripcion = :descripcion, estado = :estado WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':fecha', $fecha);
                $stmt->bindParam(':descripcion', $descripcion);
                $stmt->bindParam(':estado', $estado);
                $stmt->bindParam(':id', $evento['id']);
                
                if ($stmt->execute()) {
                    $success = "Evento actualizado exitosamente.";
                    // Actualizar datos locales
                    $evento['nombre'] = $nombre;
                    $evento['fecha'] = $fecha;
                    $evento['descripcion'] = $descripcion;
                    $evento['estado'] = $estado;
                } else {
                    $error = "Error al actualizar el evento.";
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
    <title>Editar Evento - Sistema de Votación</title>
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
            max-width: 700px;
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
            text-align: center;
        }
        
        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 2.2rem;
            background: linear-gradient(135deg, #007bff, #ffc107);
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
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .current-info {
            background: linear-gradient(135deg, #e7f3ff, #d1ecf1);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 5px solid #007bff;
            box-shadow: 0 4px 15px rgba(0,123,255,0.1);
        }
        
        .current-info strong {
            color: #0056b3;
            font-size: 1.1rem;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 1rem;
        }
        
        input[type="text"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.8);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
            background: white;
            transform: translateY(-2px);
        }
        
        textarea {
            resize: vertical;
            min-height: 120px;
            line-height: 1.5;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 24px;
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
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            flex-wrap: wrap;
            gap: 15px;
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
        
        .character-count {
            text-align: right;
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .character-count.warning {
            color: #ffc107;
        }
        
        .character-count.danger {
            color: #dc3545;
        }
        
        .form-note {
            background: #fff3cd;
            padding: 20px;
            border-radius: 12px;
            margin-top: 25px;
            border-left: 4px solid #ffc107;
        }
        
        .form-note h4 {
            color: #856404;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-note ul {
            color: #856404;
            margin-left: 20px;
            line-height: 1.6;
        }
        
        .form-note li {
            margin-bottom: 5px;
        }
        
        .evento-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        
        .detail-item {
            background: rgba(255,255,255,0.7);
            padding: 10px;
            border-radius: 8px;
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
        
        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .left-actions, .right-actions {
                justify-content: center;
                width: 100%;
            }
            
            .btn {
                flex: 1;
                justify-content: center;
                min-width: 140px;
            }
            
            .evento-details {
                grid-template-columns: 1fr;
            }
        }
        
        /* Animaciones */
        @keyframes slideIn {
            from { 
                opacity: 0; 
                transform: translateY(30px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        
        .form-container {
            animation: slideIn 0.6s ease-out;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✏️ Editar Evento</h1>
            <p>Modifique los detalles del evento de votación</p>
        </div>

        <div class="form-container">
            <?php if (!empty($error)): ?>
                <div class="error">
                    ❌ <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="success">
                    ✅ <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if ($evento): ?>
                <div class="current-info">
                    <strong>🎉 Editando Evento:</strong><br>
                    <span style="font-size: 1.2rem; font-weight: 600; color: #2c3e50;">
                        <?php echo htmlspecialchars($evento['nombre']); ?>
                    </span>
                    
                    <div class="evento-details">
                        <div class="detail-item">
                            <div class="detail-label">ID del Evento</div>
                            <div class="detail-value">#<?php echo $evento['id']; ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Estado Actual</div>
                            <div class="detail-value">
                                <?php if ($evento['estado'] == 'Activo'): ?>
                                    <span style="color: #28a745;">✅ Activo</span>
                                <?php else: ?>
                                    <span style="color: #6c757d;">🔒 Cerrado</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Fecha Original</div>
                            <div class="detail-value"><?php echo date('d/m/Y', strtotime($evento['fecha'])); ?></div>
                        </div>
                    </div>
                </div>

                <form method="POST" action="" id="editarEventoForm">
                    <div class="form-group">
                        <label for="nombre">📝 Nombre del Evento:</label>
                        <input type="text" id="nombre" name="nombre" 
                               value="<?php echo htmlspecialchars($evento['nombre']); ?>" 
                               required placeholder="Ej: Reina del Colegio 2024 - Elección Estudiantil">
                        <div class="form-help">Nombre descriptivo que identifique claramente el evento</div>
                    </div>

                    <div class="form-group">
                        <label for="fecha">📅 Fecha del Evento:</label>
                        <input type="date" id="fecha" name="fecha" 
                               value="<?php echo $evento['fecha']; ?>" required>
                        <div class="form-help">Fecha en la que se realizará o culminará el evento</div>
                    </div>

                    <div class="form-group">
                        <label for="descripcion">📋 Descripción del Evento:</label>
                        <textarea id="descripcion" name="descripcion" required 
                                  placeholder="Describa el propósito, reglas y detalles importantes del evento..."
                                  oninput="updateCharacterCount(this)"><?php echo htmlspecialchars($evento['descripcion']); ?></textarea>
                        <div class="character-count" id="charCount"><?php echo strlen($evento['descripcion']); ?>/500 caracteres</div>
                        <div class="form-help">Información detallada sobre el evento, criterios de evaluación, etc.</div>
                    </div>

                    <div class="form-group">
                        <label for="estado">⚡ Estado del Evento:</label>
                        <select id="estado" name="estado" required>
                            <option value="Activo" <?php echo ($evento['estado'] == 'Activo') ? 'selected' : ''; ?>>
                                ✅ Activo - Puede recibir votos
                            </option>
                            <option value="Cerrado" <?php echo ($evento['estado'] == 'Cerrado') ? 'selected' : ''; ?>>
                                🔒 Cerrado - No puede recibir votos
                            </option>
                        </select>
                        <div class="form-help">Los eventos "Activos" permiten a los jurados emitir votos</div>
                    </div>

                    <div class="form-note">
                        <h4>💡 Información importante:</h4>
                        <ul>
                            <li>Los cambios se aplicarán inmediatamente a todos los usuarios</li>
                            <li>Al cambiar el estado a "Cerrado", los jurados no podrán votar</li>
                            <li>Los resultados existentes se mantendrán intactos</li>
                            <li>Revise cuidadosamente los datos antes de guardar</li>
                        </ul>
                    </div>

                    <div class="form-actions">
                        <div class="left-actions">
                            <a href="dashboard.php" class="btn btn-secondary">
                                🏠 Panel Principal
                            </a>
                            <a href="eventos.php" class="btn btn-secondary">
                                📋 Lista de Eventos
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
                    ❌ No se puede cargar la información del evento.
                </div>
                <div style="text-align: center; margin-top: 25px;">
                    <a href="eventos.php" class="btn btn-secondary">
                        📋 Volver a la lista de eventos
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Contador de caracteres para la descripción
        function updateCharacterCount(textarea) {
            const charCount = textarea.value.length;
            const charCountElement = document.getElementById('charCount');
            charCountElement.textContent = `${charCount}/500 caracteres`;
            
            // Cambiar color según la cantidad de caracteres
            if (charCount > 400) {
                charCountElement.className = 'character-count danger';
            } else if (charCount > 300) {
                charCountElement.className = 'character-count warning';
            } else {
                charCountElement.className = 'character-count';
            }
        }

        // Inicializar contador de caracteres
        const descripcionTextarea = document.getElementById('descripcion');
        updateCharacterCount(descripcionTextarea);

        // Validación del formulario antes de enviar
        document.getElementById('editarEventoForm').addEventListener('submit', function(e) {
            const nombre = document.getElementById('nombre').value.trim();
            const descripcion = document.getElementById('descripcion').value.trim();
            
            if (nombre.length < 5) {
                e.preventDefault();
                alert('❌ El nombre del evento debe tener al menos 5 caracteres');
                document.getElementById('nombre').focus();
                return false;
            }
            
            if (descripcion.length < 10) {
                e.preventDefault();
                alert('❌ La descripción debe tener al menos 10 caracteres');
                document.getElementById('descripcion').focus();
                return false;
            }
            
            if (descripcion.length > 500) {
                e.preventDefault();
                alert('❌ La descripción no puede exceder los 500 caracteres');
                document.getElementById('descripcion').focus();
                return false;
            }
            
            // Confirmación antes de guardar cambios
            if (!confirm('¿Está seguro de que desea guardar los cambios en este evento?')) {
                e.preventDefault();
                return false;
            }
        });

        // Efecto de focus mejorado
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });

        // Validación en tiempo real del nombre
        const nombreInput = document.getElementById('nombre');
        nombreInput.addEventListener('input', function() {
            if (this.value.length >= 5) {
                this.style.borderColor = '#28a745';
            } else {
                this.style.borderColor = '#e9ecef';
            }
        });

        // Inicializar validación visual del nombre
        if (nombreInput.value.length >= 5) {
            nombreInput.style.borderColor = '#28a745';
        }
    </script>
</body>
</html>