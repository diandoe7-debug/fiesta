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

if (isset($_GET['id'])) {
    $participante_id = $_GET['id'];
    
    try {
        // Verificar que el participante existe y obtener info del evento
        $query = "SELECT p.nombre, p.evento_id, e.nombre as evento_nombre 
                 FROM participantes p 
                 JOIN eventos e ON p.evento_id = e.id 
                 WHERE p.id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $participante_id);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            $nombre_participante = $data['nombre'];
            $evento_id = $data['evento_id'];
            $evento_nombre = $data['evento_nombre'];
            
            // Verificar si hay votos asociados a este participante
            $query = "SELECT COUNT(*) as total_votos FROM votos WHERE participante_id = :participante_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':participante_id', $participante_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_votos = $result['total_votos'];
            
            if ($total_votos > 0) {
                $error = "No se puede eliminar al participante '$nombre_participante' porque tiene $total_votos votos asociados. Elimine primero los votos.";
            } else {
                // Eliminar el participante
                $query = "DELETE FROM participantes WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $participante_id);
                
                if ($stmt->execute()) {
                    $success = "Participante '$nombre_participante' eliminado exitosamente del evento '$evento_nombre'.";
                } else {
                    $error = "Error al eliminar el participante.";
                }
            }
        } else {
            $error = "Participante no encontrado.";
        }
    } catch (PDOException $e) {
        $error = "Error de base de datos: " . $e->getMessage();
    }
} else {
    $error = "ID de participante no especificado.";
}

// Redirigir con mensaje
if (!empty($success)) {
    header("Location: gestionar_participantes.php?evento_id=$evento_id&success=3");
    exit();
} else {
    header("Location: gestionar_participantes.php?evento_id=$evento_id&error=" . urlencode($error));
    exit();
}
?>