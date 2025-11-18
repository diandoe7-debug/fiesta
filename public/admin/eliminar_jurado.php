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
    $jurado_id = $_GET['id'];
    
    try {
        // Verificar que el jurado existe
        $query = "SELECT nombre FROM usuarios WHERE id = :id AND rol = 'jurado'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $jurado_id);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $jurado = $stmt->fetch(PDO::FETCH_ASSOC);
            $nombre_jurado = $jurado['nombre'];
            
            // Eliminar el jurado
            $query = "DELETE FROM usuarios WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $jurado_id);
            
            if ($stmt->execute()) {
                $success = "Jurado '{$nombre_jurado}' eliminado exitosamente.";
            } else {
                $error = "Error al eliminar el jurado.";
            }
        } else {
            $error = "Jurado no encontrado.";
        }
    } catch (PDOException $e) {
        $error = "Error de base de datos: " . $e->getMessage();
    }
} else {
    $error = "ID de jurado no especificado.";
}

// Redirigir con mensaje
if (!empty($success)) {
    header("Location: jurados.php?success=3");
    exit();
} else {
    header("Location: jurados.php?error=" . urlencode($error));
    exit();
}
?>