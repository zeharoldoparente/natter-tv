<?php
session_start();
if (!isset($_SESSION['logado'])) {
   header("Location: index.php");
   exit;
}

include "../includes/db.php";
include "../includes/functions.php";

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo'])) {
   try {
      if (!isset($_POST['csrf_token']) || !verificarTokenCSRF($_POST['csrf_token'])) {
         throw new Exception('Token de segurança inválido');
      }

      $duracao = (int)$_POST['duracao'] ?? 5;
      $duracao = max(1, min(300, $duracao));
      $codigo_canal = sanitizarEntrada($_POST['codigo_canal'] ?? '0000');

      if (empty($codigo_canal)) {
         $codigo_canal = '0000';
      }

      $conteudoId = processarUpload($_FILES['arquivo'], $duracao, $codigo_canal);
      if ($conteudoId) {
         $stmt = $conn->prepare("SELECT tipo, duracao FROM conteudos WHERE id = ?");
         $stmt->bind_param("i", $conteudoId);
         $stmt->execute();
         $conteudo_info = $stmt->get_result()->fetch_assoc();
         $stmt->close();

         $duracao_formatada = formatarDuracao($conteudo_info['duracao'], $conteudo_info['tipo']);
         $tipo_texto = $conteudo_info['tipo'] === 'video' ? 'vídeo' : 'imagem';

         $mensagem = "Arquivo enviado com sucesso para o canal {$codigo_canal}! Duração: {$duracao_formatada} ({$tipo_texto})";
         sinalizarAtualizacaoTV();
      }
   } catch (Exception $e) {
      $erro = $e->getMessage();
   }
}

$stats = [];
try {
   $statsQuery = $conn->query("
        SELECT 
            codigo_canal,
            COUNT(*) as total_arquivos,
            SUM(CASE WHEN tipo = 'imagem' THEN 1 ELSE 0 END) as total_imagens,
            SUM(CASE WHEN tipo = 'video' THEN 1 ELSE 0 END) as total_videos,
            SUM(tamanho) as espaco_usado
        FROM conteudos 
        WHERE ativo = 1
        GROUP BY codigo_canal
        ORDER BY codigo_canal
    ");

   if ($statsQuery) {
      while ($row = $statsQuery->fetch_assoc()) {
         $stats[] = $row;
      }
   }
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Upload de Conteúdo Principal - NatterTV</title>
   <link rel="stylesheet" href="../assets/css/admin-style.css">
   <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
   <link rel="shortcut icon" href="../assets/images/favicon.png" type="image/x-icon">
</head>

<body>
   <nav class="sidebar">
      <div class="sidebar-header">
         <img class="img-sync" src="../assets/images/Natter Logo.PNG" alt="">
         <h2>NatterTV</h2>
      </div>
      <ul class="sidebar-menu">
         <li><a href="dashboard.php"><i class="fas fa-dashboard"></i> Dashboard</a></li>
         <li class="active"><a href="upload.php"><i class="fas fa-upload"></i> Upload</a></li>
         <li><a href="rss.php"><i class="fas fa-rss"></i> RSS Feeds</a></li>
         <li><a href="sidebar.php"><i class="fas fa-th-large"></i> Conteúdo Lateral</a></li>
         <li><a href="../tv/index.php" target="_blank"><i class="fas fa-external-link-alt"></i> Ver TV</a></li>
         <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
      </ul>
   </nav>

   <main class="main-content">
      <header class="topbar">
         <h1><i class="fas fa-upload"></i> Upload de Conteúdo Principal</h1>
         <div class="user-info">
            <span>Bem-vindo, <?php echo $_SESSION['nome'] ?? 'Admin'; ?>!</span>
         </div>
      </header>

      <div class="content">
         <?php if ($mensagem): ?>
            <div class="alert alert-success">
               <i class="fas fa-check-circle"></i> <?php echo $mensagem; ?>
            </div>
         <?php endif; ?>

         <?php if ($erro): ?>
            <div class="alert alert-error">
               <i class="fas fa-exclamation-circle"></i> <?php echo $erro; ?>
            </div>
         <?php endif; ?>

         <!-- Estatísticas por Canal -->
         <?php if (!empty($stats)): ?>
            <div class="card">
               <div class="card-header">
                  <h3><i class="fas fa-chart-bar"></i> Estatísticas por Canal</h3>
               </div>
               <div class="card-body">
                  <div class="channels-stats">
                     <?php foreach ($stats as $stat): ?>
                        <div class="channel-stat">
                           <div class="channel-header">
                              <h4>Canal: <?php echo $stat['codigo_canal']; ?></h4>
                           </div>
                           <div class="channel-info">
                              <span><i class="fas fa-file"></i> <?php echo $stat['total_arquivos']; ?> arquivos</span>
                              <span><i class="fas fa-image"></i> <?php echo $stat['total_imagens']; ?> imagens</span>
                              <span><i class="fas fa-video"></i> <?php echo $stat['total_videos']; ?> vídeos</span>
                              <span><i class="fas fa-hdd"></i> <?php echo formatFileSize($stat['espaco_usado']); ?></span>
                           </div>
                        </div>
                     <?php endforeach; ?>
                  </div>
               </div>
            </div>
         <?php endif; ?>

         <div class="card">
            <div class="card-header">
               <h3><i class="fas fa-cloud-upload-alt"></i> Enviar Novo Conteúdo</h3>
            </div>
            <div class="card-body">
               <form method="POST" enctype="multipart/form-data" class="upload-form" id="uploadForm">
                  <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

                  <!-- Campo para código do canal -->
                  <div class="form-group">
                     <label for="codigo_canal">
                        <i class="fas fa-tv"></i>
                        Código do Canal
                     </label>
                     <input type="text" name="codigo_canal" id="codigo_canal"
                        placeholder="Ex: 1234" maxlength="10" required
                        pattern="[A-Za-z0-9]{1,10}" title="Use apenas letras e números">
                     <small>Digite um código para identificar o canal (ex: 1234, LOJA1, TV01, etc.)</small>
                  </div>

                  <div class="drop-zone" id="dropZone">
                     <div class="drop-zone-content">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <h4>Arraste arquivos aqui ou clique para selecionar</h4>
                        <p>Formatos aceitos: JPG, PNG, GIF, MP4, AVI, MOV</p>
                        <p>Tamanho máximo: <?php echo formatFileSize(MAX_FILE_SIZE); ?></p>
                     </div>
                     <input type="file" name="arquivo" id="arquivo" accept="image/*,video/*" required>
                  </div>

                  <div id="filePreview" class="file-preview hidden">
                     <div class="preview-content">
                        <div class="preview-media"></div>
                        <div class="preview-info">
                           <h5 id="fileName"></h5>
                           <p id="fileSize"></p>
                           <p id="fileType"></p>
                           <p id="fileDuration" class="file-duration hidden"></p>
                        </div>
                        <button type="button" class="btn-remove-file" onclick="removeFile()">
                           <i class="fas fa-times"></i>
                        </button>
                     </div>
                  </div>

                  <div class="form-group" id="durationGroup">
                     <label for="duracao">
                        <i class="fas fa-clock"></i>
                        Duração da Exibição (segundos)
                     </label>
                     <input type="number" name="duracao" id="duracao" value="5" min="1" max="300">
                     <small id="durationHelp">Apenas para imagens. Vídeos usam duração natural.</small>
                  </div>

                  <div class="form-actions">
                     <button type="submit" class="btn btn-success" id="submitBtn">
                        <i class="fas fa-upload"></i> Enviar Arquivo
                     </button>
                     <button type="button" class="btn btn-secondary" onclick="resetForm()">
                        <i class="fas fa-undo"></i> Limpar
                     </button>
                     <a href="dashboard.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Voltar ao Dashboard
                     </a>
                  </div>

                  <div class="progress-container hidden" id="progressContainer">
                     <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                     </div>
                     <div class="progress-text" id="progressText">0%</div>
                  </div>
               </form>
            </div>
         </div>

         <div class="card">
            <div class="card-header">
               <h3><i class="fas fa-info-circle"></i> Instruções de Uso</h3>
            </div>
            <div class="card-body">
               <div class="instructions">
                  <div class="instruction-item">
                     <i class="fas fa-tv text-success"></i>
                     <div>
                        <h5>Códigos de Canal</h5>
                        <p>Cada conteúdo é vinculado a um código de canal. Use códigos como: 1234, LOJA1, TV01, etc.</p>
                     </div>
                  </div>

                  <div class="instruction-item">
                     <i class="fas fa-image text-info"></i>
                     <div>
                        <h5>Imagens</h5>
                        <p>JPG, PNG, GIF - Defina o tempo de exibição em segundos (1-300s). Aparecerá como 00:05 para 5 segundos.</p>
                     </div>
                  </div>

                  <div class="instruction-item">
                     <i class="fas fa-video text-warning"></i>
                     <div>
                        <h5>Vídeos</h5>
                        <p>MP4, AVI, MOV - A duração real será detectada automaticamente e exibida como 5:30 para 5min30s.</p>
                     </div>
                  </div>

                  <div class="instruction-item">
                     <i class="fas fa-desktop text-primary"></i>
                     <div>
                        <h5>Visualização na TV</h5>
                        <p>Acesse a TV e digite o código do canal para ver apenas o conteúdo daquele canal</p>
                     </div>
                  </div>

                  <div class="instruction-item">
                     <i class="fas fa-th-large text-warning"></i>
                     <div>
                        <h5>Conteúdo Lateral</h5>
                        <p>Para gerenciar propagandas e conteúdo da sidebar, use a página <a href="sidebar.php">Conteúdo Lateral</a></p>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </main>

   <script src="../assets/js/upload.js"></script>
   <script>
      document.getElementById('codigo_canal').addEventListener('input', function(e) {
         this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
      });

      const originalHandleFileSelect = window.handleFileSelect;

      window.handleFileSelect = function(e) {
         const file = e.target.files[0];

         if (!file) {
            hideFilePreview();
            return;
         }

         if (!validateFile(file)) {
            return;
         }

         showFilePreview(file);
         adjustFormFields(file);

         if (file.type.startsWith('video/')) {
            detectVideoDuration(file);
         }
      };

      function detectVideoDuration(file) {
         const fileDurationElement = document.getElementById('fileDuration');
         const durationHelp = document.getElementById('durationHelp');

         const video = document.createElement('video');
         video.preload = 'metadata';

         video.onloadedmetadata = function() {
            const duration = Math.round(video.duration);
            const formattedDuration = formatDuration(duration, 'video');

            fileDurationElement.innerHTML = `<i class="fas fa-clock" style="color: var(--warning-color);"></i> Duração detectada: <strong>${formattedDuration}</strong>`;
            fileDurationElement.classList.remove('hidden');

            durationHelp.innerHTML = `<span style="color: var(--warning-color);">🎬 Vídeo detectado - duração será ${formattedDuration}</span>`;

            URL.revokeObjectURL(video.src);
         };

         video.onerror = function() {
            fileDurationElement.innerHTML = `<i class="fas fa-exclamation-triangle" style="color: var(--warning-color);"></i> Não foi possível detectar a duração do vídeo`;
            fileDurationElement.classList.remove('hidden');

            URL.revokeObjectURL(video.src);
         };

         video.src = URL.createObjectURL(file);
      }

      function formatDuration(seconds, type) {
         if (type === 'imagem') {
            return String(seconds).padStart(2, '0') + ':00';
         }

         const minutes = Math.floor(seconds / 60);
         const remainingSeconds = seconds % 60;
         return minutes + ':' + String(remainingSeconds).padStart(2, '0');
      }

      const originalRemoveFile = window.removeFile;

      window.removeFile = function() {
         if (originalRemoveFile) {
            originalRemoveFile();
         }

         const fileDurationElement = document.getElementById('fileDuration');
         const durationHelp = document.getElementById('durationHelp');

         fileDurationElement.classList.add('hidden');
         durationHelp.innerHTML = 'Apenas para imagens. Vídeos usam duração natural.';
      };
   </script>

   <style>
      .file-duration {
         padding: 8px 12px;
         background: rgba(243, 156, 18, 0.1);
         border-radius: 6px;
         border-left: 3px solid var(--warning-color);
         font-weight: 500;
         color: var(--warning-color);
         margin-top: 5px;
      }

      .file-duration i {
         margin-right: 6px;
      }

      .form-group small {
         transition: color 0.3s ease;
      }

      .duration-info {
         display: flex;
         align-items: center;
         gap: 10px;
         margin-top: 10px;
         padding: 8px 12px;
         background: #f8f9fa;
         border-radius: 6px;
         border-left: 3px solid var(--info-color);
      }

      .duration-info i {
         color: var(--info-color);
      }

      #durationGroup {
         transition: opacity 0.3s ease;
      }

      #durationGroup.disabled {
         opacity: 0.5;
      }

      #duracao:disabled {
         background-color: #f8f9fa;
         color: #6c757d;
         cursor: not-allowed;
      }
   </style>
</body>

</html>