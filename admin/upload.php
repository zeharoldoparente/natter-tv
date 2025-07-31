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

      $conteudoId = processarUpload($_FILES['arquivo'], $duracao);

      if ($conteudoId) {
         $mensagem = "Arquivo enviado com sucesso!";

         sinalizarAtualizacaoTV();
      }
   } catch (Exception $e) {
      $erro = $e->getMessage();
   }
}

$stats = [
   'total_arquivos' => 0,
   'total_imagens' => 0,
   'total_videos' => 0,
   'espaco_usado' => 0
];

try {
   $statsQuery = $conn->query("
        SELECT 
            COUNT(*) as total_arquivos,
            SUM(CASE WHEN tipo = 'imagem' THEN 1 ELSE 0 END) as total_imagens,
            SUM(CASE WHEN tipo = 'video' THEN 1 ELSE 0 END) as total_videos,
            SUM(tamanho) as espaco_usado
        FROM conteudos 
        WHERE ativo = 1
    ");

   if ($statsQuery && $row = $statsQuery->fetch_assoc()) {
      $stats = $row;
   }
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Upload de Arquivos - TV Corporativa</title>
   <link rel="stylesheet" href="../assets/css/admin-style.css">
   <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body>
   <nav class="sidebar">
      <div class="sidebar-header">
         <i class="fas fa-tv"></i>
         <h2>TV Corporativa</h2>
      </div>
      <ul class="sidebar-menu">
         <li><a href="dashboard.php"><i class="fas fa-dashboard"></i> Dashboard</a></li>
         <li class="active"><a href="upload.php"><i class="fas fa-upload"></i> Upload</a></li>
         <li><a href="../tv/index.php" target="_blank"><i class="fas fa-external-link-alt"></i> Ver TV</a></li>
         <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
      </ul>
   </nav>

   <main class="main-content">
      <header class="topbar">
         <h1><i class="fas fa-upload"></i> Upload de Arquivos</h1>
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

         <div class="stats-grid">
            <div class="stat-card">
               <div class="stat-icon">
                  <i class="fas fa-file"></i>
               </div>
               <div class="stat-info">
                  <h3><?php echo number_format($stats['total_arquivos']); ?></h3>
                  <p>Total de Arquivos</p>
               </div>
            </div>

            <div class="stat-card">
               <div class="stat-icon">
                  <i class="fas fa-image"></i>
               </div>
               <div class="stat-info">
                  <h3><?php echo number_format($stats['total_imagens']); ?></h3>
                  <p>Imagens</p>
               </div>
            </div>

            <div class="stat-card">
               <div class="stat-icon">
                  <i class="fas fa-video"></i>
               </div>
               <div class="stat-info">
                  <h3><?php echo number_format($stats['total_videos']); ?></h3>
                  <p>Vídeos</p>
               </div>
            </div>

            <div class="stat-card">
               <div class="stat-icon">
                  <i class="fas fa-hdd"></i>
               </div>
               <div class="stat-info">
                  <h3><?php echo formatFileSize($stats['espaco_usado']); ?></h3>
                  <p>Espaço Usado</p>
               </div>
            </div>
         </div>

         <div class="card">
            <div class="card-header">
               <h3><i class="fas fa-cloud-upload-alt"></i> Enviar Novo Arquivo</h3>
            </div>
            <div class="card-body">
               <form method="POST" enctype="multipart/form-data" class="upload-form" id="uploadForm">
                  <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

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
                        </div>
                        <button type="button" class="btn-remove-file" onclick="removeFile()">
                           <i class="fas fa-times"></i>
                        </button>
                     </div>
                  </div>

                  <div class="form-row">
                     <div class="form-group" id="durationGroup">
                        <label for="duracao">
                           <i class="fas fa-clock"></i>
                           Duração da Exibição (segundos)
                        </label>
                        <input type="number" name="duracao" id="duracao" value="5" min="1" max="300">
                        <small>Apenas para imagens. Vídeos usam duração natural.</small>
                     </div>
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
                     <i class="fas fa-image text-info"></i>
                     <div>
                        <h5>Imagens</h5>
                        <p>JPG, PNG, GIF - Defina o tempo de exibição em segundos (1-300s)</p>
                     </div>
                  </div>

                  <div class="instruction-item">
                     <i class="fas fa-video text-warning"></i>
                     <div>
                        <h5>Vídeos</h5>
                        <p>MP4, AVI, MOV - Serão reproduzidos por completo automaticamente</p>
                     </div>
                  </div>

                  <div class="instruction-item">
                     <i class="fas fa-file-upload text-success"></i>
                     <div>
                        <h5>Upload</h5>
                        <p>Arraste e solte arquivos na área de upload ou clique para selecionar</p>
                     </div>
                  </div>

                  <div class="instruction-item">
                     <i class="fas fa-tv text-primary"></i>
                     <div>
                        <h5>Visualização</h5>
                        <p>Após o upload, os arquivos aparecerão automaticamente na TV</p>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </main>

   <style>
      .stats-grid {
         display: grid;
         grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
         gap: 20px;
         margin-bottom: 30px;
      }

      .stat-card {
         background: white;
         padding: 20px;
         border-radius: 10px;
         box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
         display: flex;
         align-items: center;
         gap: 15px;
         transition: transform 0.2s ease;
      }

      .stat-card:hover {
         transform: translateY(-2px);
         box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
      }

      .stat-icon {
         width: 50px;
         height: 50px;
         border-radius: 10px;
         display: flex;
         align-items: center;
         justify-content: center;
         background: var(--primary-color);
         color: white;
         font-size: 1.5rem;
      }

      .stat-info h3 {
         margin: 0;
         font-size: 1.8rem;
         font-weight: 600;
         color: var(--primary-color);
      }

      .stat-info p {
         margin: 5px 0 0 0;
         color: #666;
         font-size: 0.9rem;
      }

      .drop-zone {
         position: relative;
         border: 3px dashed #ddd;
         border-radius: 10px;
         padding: 40px 20px;
         text-align: center;
         transition: all 0.3s ease;
         margin-bottom: 20px;
         cursor: pointer;
      }

      .drop-zone:hover,
      .drop-zone.dragover {
         border-color: var(--secondary-color);
         background: rgba(52, 152, 219, 0.05);
      }

      .drop-zone input[type="file"] {
         position: absolute;
         top: 0;
         left: 0;
         width: 100%;
         height: 100%;
         opacity: 0;
         cursor: pointer;
      }

      .drop-zone-content i {
         font-size: 3rem;
         color: #ddd;
         margin-bottom: 15px;
      }

      .drop-zone-content h4 {
         margin-bottom: 10px;
         color: var(--primary-color);
      }

      .drop-zone-content p {
         margin: 5px 0;
         color: #666;
         font-size: 0.9rem;
      }

      .file-preview {
         border: 2px solid #e1e8ed;
         border-radius: 10px;
         padding: 20px;
         margin-bottom: 20px;
      }

      .preview-content {
         display: flex;
         align-items: center;
         gap: 20px;
         position: relative;
      }

      .preview-media {
         flex-shrink: 0;
      }

      .preview-media img,
      .preview-media video {
         width: 100px;
         height: 80px;
         object-fit: cover;
         border-radius: 6px;
         border: 2px solid #ddd;
      }

      .preview-info {
         flex-grow: 1;
      }

      .preview-info h5 {
         margin: 0 0 5px 0;
         color: var(--primary-color);
      }

      .preview-info p {
         margin: 2px 0;
         color: #666;
         font-size: 0.9rem;
      }

      .btn-remove-file {
         position: absolute;
         top: -10px;
         right: -10px;
         width: 30px;
         height: 30px;
         border-radius: 50%;
         background: var(--danger-color);
         color: white;
         border: none;
         cursor: pointer;
         display: flex;
         align-items: center;
         justify-content: center;
      }

      .form-row {
         display: grid;
         grid-template-columns: 1fr;
         gap: 20px;
      }

      .form-actions {
         display: flex;
         gap: 10px;
         flex-wrap: wrap;
         margin-top: 20px;
      }

      .btn-outline {
         background: transparent;
         border: 2px solid var(--primary-color);
         color: var(--primary-color);
      }

      .btn-outline:hover {
         background: var(--primary-color);
         color: white;
      }

      .progress-container {
         margin-top: 20px;
      }

      .progress-bar {
         width: 100%;
         height: 10px;
         background: #f0f0f0;
         border-radius: 5px;
         overflow: hidden;
      }

      .progress-fill {
         height: 100%;
         background: var(--success-color);
         width: 0%;
         transition: width 0.3s ease;
      }

      .progress-text {
         text-align: center;
         margin-top: 10px;
         font-weight: 600;
         color: var(--primary-color);
      }

      .instructions {
         display: grid;
         gap: 20px;
      }

      .instruction-item {
         display: flex;
         align-items: flex-start;
         gap: 15px;
         padding: 15px;
         background: #f8f9fa;
         border-radius: 8px;
      }

      .instruction-item i {
         font-size: 1.5rem;
         margin-top: 2px;
      }

      .instruction-item h5 {
         margin: 0 0 5px 0;
         color: var(--primary-color);
      }

      .instruction-item p {
         margin: 0;
         color: #666;
         font-size: 0.9rem;
      }

      .text-info {
         color: var(--info-color) !important;
      }

      .text-warning {
         color: var(--warning-color) !important;
      }

      .text-success {
         color: var(--success-color) !important;
      }

      .text-primary {
         color: var(--primary-color) !important;
      }

      .alert-error {
         background: #f8d7da;
         color: #721c24;
         border: 1px solid #f5c6cb;
      }
   </style>

   <script src="../assets/js/upload.js"></script>
</body>

</html>