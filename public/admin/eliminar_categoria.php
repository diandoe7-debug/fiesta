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
    $categoria_id = $_GET['id'];
    
    try {
        // Verificar que la categoría existe y obtener info del evento
        $query = "SELECT c.nombre, c.evento_id, e.nombre as evento_nombre 
                 FROM categorias c 
                 JOIN eventos e ON c.evento_id = e.id 
                 WHERE c.id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $categoria_id);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            $nombre_categoria = $data['nombre'];
            $evento_id = $data['evento_id'];
            $evento_nombre = $data['evento_nombre'];
            
            // Verificar si hay votos asociados a esta categoría
            $query = "SELECT COUNT(*) as total_votos FROM votos WHERE categoria_id = :categoria_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':categoria_id', $categoria_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_votos = $result['total_votos'];
            
            if ($total_votos > 0) {
                $error = "No se puede eliminar la categoría '$nombre_categoria' porque tiene $total_votos votos asociados. Elimine primero los votos.";
            } else {
                // Eliminar la categoría
                $query = "DELETE FROM categorias WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $categoria_id);
                
                if ($stmt->execute()) {
                    $success = "Categoría '$nombre_categoria' eliminada exitosamente del evento '$evento_nombre'.";
                } else {
                    $error = "Error al eliminar la categoría.";
                }
            }
        } else {
            $error = "Categoría no encontrada.";
        }
    } catch (PDOException $e) {
        $error = "Error de base de datos: " . $e->getMessage();
    }
} else {
    $error = "ID de categoría no especificado.";
}

// Redirigir con mensaje
if (!empty($success)) {
    header("Location: gestionar_categorias.php?evento_id=$evento_id&success=3");
    exit();
} else {
    header("Location: gestionar_categorias.php?evento_id=$evento_id&error=" . urlencode($error));
    exit();
}
?>