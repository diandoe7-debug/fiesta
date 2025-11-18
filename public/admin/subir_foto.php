<?php
// Procesar subida de foto
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['foto'])) {
    $participante_id = $_POST['participante_id'];
    
    // Configuración
    $directorio_originales = '../uploads/fotos/originales/';
    $directorio_thumbnails = '../uploads/fotos/thumbnails/';
    $directorio_optimized = '../uploads/fotos/optimized/';
    
    // Crear directorios si no existen
    if (!is_dir($directorio_originales)) mkdir($directorio_originales, 0777, true);
    if (!is_dir($directorio_thumbnails)) mkdir($directorio_thumbnails, 0777, true);
    if (!is_dir($directorio_optimized)) mkdir($directorio_optimized, 0777, true);
    
    $archivo = $_FILES['foto'];
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $nombre_archivo = "participante_{$participante_id}_" . time() . ".{$extension}";
    
    // Validar tipo de archivo
    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
    if (in_array($extension, $extensiones_permitidas)) {
        
        // Mover archivo original
        if (move_uploaded_file($archivo['tmp_name'], $directorio_originales . $nombre_archivo)) {
            
            // Crear miniatura (150x150px)
            crear_miniatura($directorio_originales . $nombre_archivo, $directorio_thumbnails . $nombre_archivo, 150);
            
            // Crear versión optimizada (800x600px)
            crear_miniatura($directorio_originales . $nombre_archivo, $directorio_optimized . $nombre_archivo, 800);
            
            // Actualizar base de datos
            $query = "UPDATE participantes SET foto = :foto WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':foto', $nombre_archivo);
            $stmt->bindParam(':id', $participante_id);
            $stmt->execute();
            
            $success = "Foto actualizada exitosamente.";
        }
    }
}

function crear_miniatura($origen, $destino, $ancho_maximo) {
    list($ancho_orig, $alto_orig, $tipo) = getimagesize($origen);
    
    switch ($tipo) {
        case IMAGETYPE_JPEG: $imagen = imagecreatefromjpeg($origen); break;
        case IMAGETYPE_PNG: $imagen = imagecreatefrompng($origen); break;
        case IMAGETYPE_GIF: $imagen = imagecreatefromgif($origen); break;
        default: return false;
    }
    
    $ratio = $ancho_orig / $alto_orig;
    $alto_maximo = $ancho_maximo / $ratio;
    
    $miniatura = imagecreatetruecolor($ancho_maximo, $alto_maximo);
    imagecopyresampled($miniatura, $imagen, 0, 0, 0, 0, $ancho_maximo, $alto_maximo, $ancho_orig, $alto_orig);
    
    switch ($tipo) {
        case IMAGETYPE_JPEG: imagejpeg($miniatura, $destino, 85); break;
        case IMAGETYPE_PNG: imagepng($miniatura, $destino, 8); break;
        case IMAGETYPE_GIF: imagegif($miniatura, $destino); break;
    }
    
    imagedestroy($imagen);
    imagedestroy($miniatura);
    return true;
}
?>