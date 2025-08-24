<?php
$sidebarEnabled = true;
try {
   if (function_exists('buscarConfiguracao')) {
      $sidebarEnabled = buscarConfiguracao('sidebar_enabled', true);
   }
} catch (Exception $e) {
   $sidebarEnabled = true;
}

if (!$sidebarEnabled) {
   echo '<img src="../assets/images/propaganda.png" alt="Propaganda">';
   return;
}

$conteudoAtivo = null;
$usingDatabase = false;

try {
   $tableCheck = $conn->query("SHOW TABLES LIKE 'conteudos_laterais'");
   if ($tableCheck && $tableCheck->num_rows > 0) {
      if (function_exists('buscarConteudoLateralAtivo')) {
         $conteudoAtivo = buscarConteudoLateralAtivo();
         $usingDatabase = true;
      }
   }
} catch (Exception $e) {
   $conteudoAtivo = null;
   $usingDatabase = false;
}

if ($usingDatabase && $conteudoAtivo) {
   $caminhoArquivo = SIDEBAR_PATH . $conteudoAtivo['arquivo'];

   if (file_exists($caminhoArquivo)) {
      $src = SIDEBAR_WEB_PATH . $conteudoAtivo['arquivo'];
      $tipo = $conteudoAtivo['tipo'];
      $alt = htmlspecialchars($conteudoAtivo['descricao'] ?: $conteudoAtivo['nome_original']);

      if ($tipo === 'video') {
         echo '<video 
                  src="' . $src . '" 
                  autoplay 
                  muted 
                  loop 
                  playsinline 
                  webkit-playsinline 
                  preload="auto"
                  controls="false"
                  disablePictureInPicture
                  controlslist="nodownload nofullscreen noremoteplayback"
                  style="width: 100%; height: 100%; object-fit: cover; background: #000;"
                  title="' . $alt . '"
                  onloadstart="console.log(\'Video loading started\')"
                  oncanplay="console.log(\'Video can start playing\')"
                  onerror="console.error(\'Video error:\', this.error); this.style.display=\'none\'; this.parentNode.innerHTML=\'<img src=&quot;../assets/images/propaganda.png&quot; alt=&quot;Propaganda&quot; style=&quot;width:100%;height:100%;object-fit:cover;&quot;>\';"
                  onended="this.currentTime = 0; this.play();">
                  Seu navegador não suporta vídeos HTML5.
               </video>';
      } else {
         $ext = strtolower(pathinfo($conteudoAtivo['arquivo'], PATHINFO_EXTENSION));

         if ($ext === 'webp' && (!isset($_SERVER['HTTP_ACCEPT']) || strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') === false)) {
            if (function_exists('imagecreatefromwebp')) {
               $jpegPath = SIDEBAR_PATH . pathinfo($conteudoAtivo['arquivo'], PATHINFO_FILENAME) . '.jpg';
               if (!file_exists($jpegPath)) {
                  $img = imagecreatefromwebp($caminhoArquivo);
                  imagejpeg($img, $jpegPath, 90);
                  imagedestroy($img);
               }
               $jpegSrc = SIDEBAR_WEB_PATH . basename($jpegPath);
               echo '<img src="' . $jpegSrc . '" alt="' . $alt . '" style="width: 100%; height: 100%; object-fit: cover;">';
            } else {
               echo '<img src="../assets/images/propaganda.png" alt="Propaganda" style="width: 100%; height: 100%; object-fit: cover;">';
            }
         } else {
            echo '<img src="' . $src . '" alt="' . $alt . '" style="width: 100%; height: 100%; object-fit: cover;">';
         }
      }
   } else {
      $usingDatabase = false;
   }
}

if (!$usingDatabase) {
   $allowed = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv'];
   $files = [];

   if (defined('SIDEBAR_PATH') && is_dir(SIDEBAR_PATH)) {
      $sidebarPath = SIDEBAR_PATH;
   } else {
      $sidebarPath = "../sidebar/";
   }

   if (is_dir($sidebarPath)) {
      $files = array_values(array_filter(scandir($sidebarPath), function ($f) use ($allowed, $sidebarPath) {
         $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
         return !is_dir($sidebarPath . $f) && in_array($ext, $allowed);
      }));
   }

   if (!empty($files)) {
      $file = $files[0];
      $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

      if (defined('SIDEBAR_WEB_PATH')) {
         $src = SIDEBAR_WEB_PATH . $file;
      } else {
         $src = "../sidebar/" . $file;
      }

      if (in_array($ext, ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv'])) {
         echo '<video 
                  src="' . $src . '" 
                  autoplay 
                  muted 
                  loop 
                  playsinline 
                  webkit-playsinline 
                  preload="auto"
                  controls="false"
                  disablePictureInPicture
                  controlslist="nodownload nofullscreen noremoteplayback"
                  style="width: 100%; height: 100%; object-fit: cover; background: #000;"
                  onloadstart="console.log(\'Sidebar video loading started\')"
                  oncanplay="console.log(\'Sidebar video can start playing\')"
                  onerror="console.error(\'Sidebar video error:\', this.error); this.style.display=\'none\'; this.parentNode.innerHTML=\'<img src=&quot;../assets/images/propaganda.png&quot; alt=&quot;Propaganda&quot; style=&quot;width:100%;height:100%;object-fit:cover;&quot;>\';"
                  onended="this.currentTime = 0; this.play();">
                  Seu navegador não suporta vídeos HTML5.
               </video>';
      } else {
         if ($ext === 'webp' && (!isset($_SERVER['HTTP_ACCEPT']) || strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') === false)) {
            $originalPath = $sidebarPath . $file;
            if (function_exists('imagecreatefromwebp')) {
               $jpegPath = $sidebarPath . pathinfo($file, PATHINFO_FILENAME) . '.jpg';
               if (!file_exists($jpegPath)) {
                  $img = imagecreatefromwebp($originalPath);
                  imagejpeg($img, $jpegPath, 90);
                  imagedestroy($img);
               }
               $jpegSrc = (defined('SIDEBAR_WEB_PATH') ? SIDEBAR_WEB_PATH : "../sidebar/") . basename($jpegPath);
               echo '<img src="' . $jpegSrc . '" alt="Propaganda" style="width: 100%; height: 100%; object-fit: cover;">';
            } else {
               echo '<img src="../assets/images/propaganda.png" alt="Propaganda" style="width: 100%; height: 100%; object-fit: cover;">';
            }
         } else {
            echo '<img src="' . $src . '" alt="Propaganda" style="width: 100%; height: 100%; object-fit: cover;">';
         }
      }
   } else {
      echo '<img src="../assets/images/propaganda.png" alt="Propaganda" style="width: 100%; height: 100%; object-fit: cover;">';
   }
}
