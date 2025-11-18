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
$participante = null;
$categorias = [];
$votos_existentes = [];
$error = '';
$success = '';

// Verificar parámetros
if (!isset($_GET['evento_id']) || !isset($_GET['participante_id'])) {
    header("Location: dashboard.php");
    exit();
}

$evento_id = $_GET['evento_id'];
$participante_id = $_GET['participante_id'];
$jurado_id = $_SESSION['user_id'];

try {
    // Obtener información del evento
    $query = "SELECT id, nombre FROM eventos WHERE id = :id AND estado = 'Activo'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $evento_id);
    $stmt->execute();
    $evento = $stmt->fetch(PDO::FETCH_ASSOC);

    // Obtener información del participante
    $query = "SELECT id, nombre, representante, foto, descripcion FROM participantes WHERE id = :id AND evento_id = :evento_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $participante_id);
    $stmt->bindParam(':evento_id', $evento_id);
    $stmt->execute();
    $participante = $stmt->fetch(PDO::FETCH_ASSOC);

    // Obtener categorías del evento
    $query = "SELECT id, nombre, puntaje_maximo FROM categorias WHERE evento_id = :evento_id ORDER BY nombre";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':evento_id', $evento_id);
    $stmt->execute();
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener votos existentes del jurado para este participante
    $query = "SELECT categoria_id, puntaje FROM votos 
              WHERE jurado_id = :jurado_id 
              AND participante_id = :participante_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':jurado_id', $jurado_id);
    $stmt->bindParam(':participante_id', $participante_id);
    $stmt->execute();
    $votos_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organizar votos existentes
    foreach ($votos_raw as $voto) {
        $votos_existentes[$voto['categoria_id']] = $voto['puntaje'];
    }

} catch (PDOException $e) {
    $error = "Error al cargar los datos: " . $e->getMessage();
}

// Procesar el formulario de votación
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_votos'])) {
    try {
        $db->beginTransaction();
        $votos_procesados = 0;

        foreach ($categorias as $categoria) {
            $input_name = "puntaje_{$categoria['id']}";
            
            if (isset($_POST[$input_name]) && !empty($_POST[$input_name])) {
                $puntaje = intval($_POST[$input_name]);
                
                // Validar puntaje
                if ($puntaje >= 1 && $puntaje <= $categoria['puntaje_maximo']) {
                    
                    // Verificar si ya existe un voto
                    $query = "SELECT id FROM votos 
                             WHERE jurado_id = :jurado_id 
                             AND participante_id = :participante_id 
                             AND categoria_id = :categoria_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':jurado_id', $jurado_id);
                    $stmt->bindParam(':participante_id', $participante_id);
                    $stmt->bindParam(':categoria_id', $categoria['id']);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        // Actualizar voto existente
                        $query = "UPDATE votos SET puntaje = :puntaje WHERE jurado_id = :jurado_id AND participante_id = :participante_id AND categoria_id = :categoria_id";
                    } else {
                        // Insertar nuevo voto
                        $query = "INSERT INTO votos (jurado_id, participante_id, categoria_id, puntaje) 
                                 VALUES (:jurado_id, :participante_id, :categoria_id, :puntaje)";
                    }
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':jurado_id', $jurado_id);
                    $stmt->bindParam(':participante_id', $participante_id);
                    $stmt->bindParam(':categoria_id', $categoria['id']);
                    $stmt->bindParam(':puntaje', $puntaje);
                    $stmt->execute();
                    
                    $votos_procesados++;
                }
            }
        }
        
        $db->commit();
        
        // ✅ REDIRECCIÓN AUTOMÁTICA DESPUÉS DE GUARDAR
        $_SESSION['success'] = "✅ Votos guardados exitosamente para {$participante['nombre']}.";
        header("Location: votacion.php?evento_id=" . $evento_id);
        exit();
        
    } catch (PDOException $e) {
        $db->rollBack();
        $error = "Error al guardar los votos: " . $e->getMessage();
    }
}

// Mostrar mensaje de éxito si existe (para cuando se edita un voto sin redirección)
if (isset($_SESSION['success_edit'])) {
    $success = $_SESSION['success_edit'];
    unset($_SESSION['success_edit']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votar Participante - Jurado</title>
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

        .participante-info {
            background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(255,255,255,0.85));
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 30px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        /* ESTILOS MEJORADOS PARA LAS IMÁGENES */
        .foto-container {
            width: 200px;
            height: 200px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .foto-participante {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: all 0.3s ease;
        }

        .foto-container:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        }

        .foto-container:hover .foto-participante {
            transform: scale(1.1);
        }

        /* Placeholder transparente */
        .foto-placeholder-transparent {
            width: 200px;
            height: 200px;
            border-radius: 10px;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .placeholder-icon {
            font-size: 4rem;
            color: rgba(116, 185, 255, 0.5);
            transition: all 0.3s ease;
        }

        .foto-placeholder-transparent:hover .placeholder-icon {
            color: rgba(116, 185, 255, 0.8);
            transform: scale(1.1);
        }

        .participante-details h2 {
            color: #2d3436;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }

        .participante-details p {
            margin-bottom: 8px;
            font-size: 1.1rem;
        }

        .evento-badge {
            background: linear-gradient(135deg, #00b894, #00cec9);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 10px;
            box-shadow: 0 2px 8px rgba(0, 184, 148, 0.3);
        }

        .categorias-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .categoria-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(255,255,255,0.85));
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            position: relative;
            overflow: hidden;
        }

        .categoria-card:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #74b9ff, #0984e3);
        }

        .categoria-card.votado {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border-color: #28a745;
        }

        .categoria-card.votado:before {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        .categoria-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        input[type="number"] {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            text-align: center;
            font-size: 1.2rem;
            font-weight: bold;
            transition: all 0.3s ease;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        input[type="number"]:focus {
            outline: none;
            border-color: #74b9ff;
            box-shadow: 0 0 15px rgba(116, 185, 255, 0.3);
            transform: scale(1.02);
        }

        .btn {
            padding: 16px 32px;
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

        .puntaje-info {
            font-size: 0.9rem;
            color: #666;
            margin-top: 10px;
            text-align: center;
        }

        .votado-badge {
            background: linear-gradient(135deg, #00b894, #00cec9);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 10px;
            box-shadow: 0 2px 8px rgba(0, 184, 148, 0.3);
        }

        .info-box {
            background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(255,255,255,0.85));
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-left: 5px solid #74b9ff;
        }

        .categoria-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .categoria-header h3 {
            color: #2d3436;
            margin: 0;
            font-size: 1.3rem;
        }

        .floating-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
            text-align: center;
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
            
            .participante-info {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .foto-container,
            .foto-placeholder-transparent {
                width: 150px;
                height: 150px;
            }
            
            .categorias-grid {
                grid-template-columns: 1fr;
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

        .participante-descripcion {
            background: rgba(116, 185, 255, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
            font-size: 1rem;
            color: #555;
            border-left: 3px solid #74b9ff;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="floating-icon">🗳️</div>
            <h1>Votar Participante</h1>
            
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

        <?php if ($evento && $participante): ?>
            <div class="participante-info">
                <?php if (!empty($participante['foto']) && file_exists('../uploads/fotos/' . $participante['foto'])): ?>
                    <div class="foto-container">
                        <img src="../uploads/fotos/<?php echo $participante['foto']; ?>" 
                             alt="<?php echo htmlspecialchars($participante['nombre']); ?>"
                             class="foto-participante">
                    </div>
                <?php else: ?>
                    <div class="foto-placeholder-transparent">
                        <span class="placeholder-icon">👤</span>
                    </div>
                <?php endif; ?>
                
                <div class="participante-details">
                    <h2><?php echo htmlspecialchars($participante['nombre']); ?></h2>
                    <p><strong>🏢 Representa:</strong> <?php echo htmlspecialchars($participante['representante']); ?></p>
                    
                    <?php if (!empty($participante['descripcion'])): ?>
                        <div class="participante-descripcion">
                            <?php echo htmlspecialchars($participante['descripcion']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="evento-badge">
                        🎯 <?php echo htmlspecialchars($evento['nombre']); ?>
                    </div>
                </div>
            </div>

            <!-- Información sobre la votación -->
            <div class="info-box">
                <h4 style="margin: 0 0 15px 0; color: #2d3436; display: flex; align-items: center; gap: 10px;">
                    💡 Información importante
                </h4>
                <p style="margin: 0; font-size: 1rem; line-height: 1.6;">
                    Después de guardar los votos, serás redirigido automáticamente a la lista de participantes 
                    donde podrás continuar votando a los demás concursantes.
                </p>
            </div>

            <form method="POST" action="">
                <div class="categorias-grid">
                    <?php foreach ($categorias as $categoria): ?>
                        <?php $ya_votado = isset($votos_existentes[$categoria['id']]); ?>
                        <div class="categoria-card <?php echo $ya_votado ? 'votado' : ''; ?>">
                            <div class="categoria-header">
                                <h3><?php echo htmlspecialchars($categoria['nombre']); ?></h3>
                                <?php if ($ya_votado): ?>
                                    <span class="votado-badge">✅ Ya Votado</span>
                                <?php endif; ?>
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label for="puntaje_<?php echo $categoria['id']; ?>" style="font-weight: 600; color: #2d3436;">
                                    Puntaje (1-<?php echo $categoria['puntaje_maximo']; ?>):
                                </label>
                            </div>
                            
                            <input type="number" 
                                   id="puntaje_<?php echo $categoria['id']; ?>"
                                   name="puntaje_<?php echo $categoria['id']; ?>" 
                                   min="1" 
                                   max="<?php echo $categoria['puntaje_maximo']; ?>" 
                                   value="<?php echo $ya_votado ? $votos_existentes[$categoria['id']] : ''; ?>"
                                   placeholder="0"
                                   required>
                            
                            <div class="puntaje-info">
                                Escala: 1 (Mínimo) - <?php echo $categoria['puntaje_maximo']; ?> (Máximo)
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="actions">
                    <button type="submit" name="guardar_votos" class="btn">
                        💾 Guardar Votos y Continuar
                    </button>
                    <a href="votacion.php?evento_id=<?php echo $evento_id; ?>" class="btn btn-secondary">
                        ← Volver a la Lista
                    </a>
                    <a href="dashboard.php" class="btn btn-secondary">
                        🏠 Panel Principal
                    </a>
                </div>
            </form>

        <?php else: ?>
            <div class="error">
                ❌ <strong>No se pudo cargar la información del participante</strong>
                <div class="actions" style="margin-top: 20px;">
                    <a href="votacion.php?evento_id=<?php echo $evento_id; ?>" class="btn btn-secondary">← Volver a la Lista</a>
                    <a href="dashboard.php" class="btn btn-secondary">🏠 Panel Principal</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Confirmación antes de enviar el formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const confirmacion = confirm('¿Estás seguro de que quieres guardar los votos? Serás redirigido a la lista de participantes.');
            if (!confirmacion) {
                e.preventDefault();
            }
        });

        // Validación en tiempo real de los puntajes
        document.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('change', function() {
                const max = parseInt(this.max);
                const value = parseInt(this.value);
                
                if (value < 1) {
                    this.value = 1;
                    alert('El puntaje mínimo es 1');
                } else if (value > max) {
                    this.value = max;
                    alert(`El puntaje máximo permitido es ${max}`);
                }
            });

            // Efecto visual al enfocar
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });

            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });

        // Efectos de interacción suaves
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.categoria-card');
            
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