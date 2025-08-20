<?php
// Exibe conteúdo lateral (imagem ou vídeo) enviado via upload
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv'];
$files = [];
if (is_dir(SIDEBAR_PATH)) {
   $files = array_values(array_filter(scandir(SIDEBAR_PATH), function ($f) use ($allowed) {
      $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
      return !is_dir($f) && in_array($ext, $allowed);
   }));
}
if (!empty($files)) {
   $file = $files[0];
   $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
   $src = SIDEBAR_WEB_PATH . $file;
   if (in_array($ext, ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv'])) {
      echo '<video src="' . $src . '" autoplay muted loop></video>';
   } else {
      echo '<img src="' . $src . '" alt="Propaganda">';
   }
} else {
   echo '<img src="../assets/images/propaganda.png" alt="Propaganda">';
}
