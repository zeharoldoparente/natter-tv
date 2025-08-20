<?php

/**
 * SUBSTITUIR o arquivo includes/sidebar_content.php por este código
 * Versão segura que funciona mesmo se o novo sistema não estiver instalado
 */

// Verificar se a sidebar está habilitada (com fallback)
$sidebarEnabled = true;
try {
   if (function_exists('buscarConfiguracao')) {
      $sidebarEnabled = buscarConfiguracao('sidebar_enabled', true);
   }
} catch (Exception $e) {
   // Se der erro, continuar com sistema antigo
   $sidebarEnabled = true;
}

if (!$sidebarEnabled) {
   echo '<img src="../assets/images/propaganda.png" alt="Propaganda">';
   return;
}

// Tentar buscar conteúdo lateral ativo no banco de dados
$conteudoAtivo = null;
$usingDatabase = false;

try {
   // Verificar se a tabela existe
   $tableCheck = $conn->query("SHOW TABLES LIKE 'conteudos_laterais'");
   if ($tableCheck && $tableCheck->num_rows > 0) {
      // Tabela existe, tentar buscar conteúdo
      if (function_exists('buscarConteudoLateralAtivo')) {
         $conteudoAtivo = buscarConteudoLateralAtivo();
         $usingDatabase = true;
      }
   }
} catch (Exception $e) {
   // Se der erro, usar sistema antigo
   $conteudoAtivo = null;
   $usingDatabase = false;
}

if ($usingDatabase && $conteudoAtivo) {
   // NOVO SISTEMA: Usar conteúdo do banco de dados
   $caminhoArquivo = SIDEBAR_PATH . $conteudoAtivo['arquivo'];

   if (file_exists($caminhoArquivo)) {
      $src = SIDEBAR_WEB_PATH . $conteudoAtivo['arquivo'];
      $tipo = $conteudoAtivo['tipo'];
      $alt = htmlspecialchars($conteudoAtivo['descricao'] ?: $conteudoAtivo['nome_original']);

      if ($tipo === 'video') {
         echo '<video src="' . $src . '" autoplay muted loop playsinline webkit-playsinline preload="auto" title="' . $alt . '"></video>';
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
               echo '<img src="' . $jpegSrc . '" alt="' . $alt . '">';
            } else {
               echo '<img src="../assets/images/propaganda.png" alt="Propaganda">';
            }
         } else {
            echo '<img src="' . $src . '" alt="' . $alt . '">';
         }
      }
   } else {
      // Arquivo não existe mais, usar sistema antigo como fallback
      $usingDatabase = false;
   }
}

if (!$usingDatabase) {
   // SISTEMA ANTIGO: Buscar arquivos na pasta sidebar diretamente
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
         echo '<video src="' . $src . '" autoplay muted loop playsinline webkit-playsinline preload="auto"></video>';
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
               echo '<img src="' . $jpegSrc . '" alt="Propaganda">';
            } else {
               echo '<img src="../assets/images/propaganda.png" alt="Propaganda">';
            }
         } else {
            echo '<img src="' . $src . '" alt="Propaganda">';
         }
      }
   } else {
      // Nenhum conteúdo encontrado, exibir imagem padrão
      echo '<img src="../assets/images/propaganda.png" alt="Propaganda">';
   }
}
