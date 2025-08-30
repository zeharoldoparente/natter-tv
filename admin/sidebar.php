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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   try {
      if (!verificarTokenCSRF($_POST['csrf_token'])) {
         throw new Exception('Token de segurança inválido');
      }

      if (isset($_POST['upload_lateral'])) {
         if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Nenhum arquivo foi selecionado ou erro no upload');
         }

         $descricao = sanitizarEntrada($_POST['descricao'] ?? '');
         $ativarImediatamente = isset($_POST['ativar_imediatamente']);

         $conteudoId = processarUploadLateral($_FILES['arquivo'], $descricao);

         if ($ativarImediatamente) {
            ativarConteudoLateral($conteudoId);
            $mensagem = "Conteúdo lateral enviado e ativado com sucesso!";
         } else {
            $mensagem = "Conteúdo lateral enviado com sucesso! Clique em 'Ativar' para usar.";
         }
      }

      if (isset($_POST['ativar_conteudo'])) {
         $id = (int)$_POST['conteudo_id'];
         ativarConteudoLateral($id);
         $mensagem = "Conteúdo lateral ativado com sucesso!";
      }

      if (isset($_POST['desativar_todos'])) {
         desativarTodosConteudosLaterais();
         $mensagem = "Todos os conteúdos laterais foram desativados!";
      }

      if (isset($_POST['atualizar_descricao'])) {
         $id = (int)$_POST['conteudo_id'];
         $descricao = sanitizarEntrada($_POST['nova_descricao']);
         atualizarDescricaoLateral($id, $descricao);
         $mensagem = "Descrição atualizada com sucesso!";
      }
   } catch (Exception $e) {
      $erro = $e->getMessage();
   }
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
   try {
      $id = (int)$_GET['delete'];
      if (excluirConteudoLateral($id)) {
         $mensagem = "Conteúdo lateral excluído com sucesso!";
      } else {
         $erro = "Erro ao excluir conteúdo lateral";
      }
   } catch (Exception $e) {
      $erro = $e->getMessage();
   }
}
$conteudos = buscarConteudosLaterais();
$conteudoAtivo = buscarConteudoLateralAtivo();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Gerenciar Conteúdo Lateral - NatterTV</title>
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
         <li><a href="upload.php"><i class="fas fa-upload"></i> Upload</a></li>
         <li><a href="rss.php"><i class="fas fa-rss"></i> RSS Feeds</a></li>
         <li class="active"><a href="sidebar.php"><i class="fas fa-th-large"></i> Conteúdo Lateral</a></li>
         <li><a href="../tv/index.php" target="_blank"><i class="fas fa-external-link-alt"></i> Ver TV</a></li>
         <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
      </ul>
   </nav>

   <main class="main-content">
      <header class="topbar">
         <h1><i class="fas fa-rectangle-wide"></i> Gerenciar Conteúdo Lateral</h1>
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

         <!-- Status Atual -->
         <div class="card">
            <div class="card-header">
               <h3><i class="fas fa-info-circle"></i> Status Atual</h3>
            </div>
            <div class="card-body">
               <?php if ($conteudoAtivo): ?>
                  <div class="current-sidebar-content">
                     <div class="sidebar-preview">
                        <?php
                        $src = SIDEBAR_WEB_PATH . $conteudoAtivo['arquivo'];
                        if ($conteudoAtivo['tipo'] === 'video'):
                        ?>
                           <video src="<?php echo $src; ?>" class="sidebar-media" controls>
                              Seu navegador não suporta vídeos.
                           </video>
                        <?php else: ?>
                           <img src="<?php echo $src; ?>" class="sidebar-media" alt="Conteúdo lateral ativo">
                        <?php endif; ?>
                     </div>
                     <div class="sidebar-info">
                        <h4>✅ Conteúdo Ativo: <?php echo htmlspecialchars($conteudoAtivo['nome_original']); ?></h4>
                        <p><strong>Tipo:</strong> <?php echo ucfirst($conteudoAtivo['tipo']); ?></p>
                        <p><strong>Tamanho:</strong> <?php echo formatFileSize($conteudoAtivo['tamanho']); ?></p>
                        <p><strong>Ativado em:</strong> <?php echo date('d/m/Y H:i', strtotime($conteudoAtivo['data_ativacao'])); ?></p>
                        <?php if ($conteudoAtivo['descricao']): ?>
                           <p><strong>Descrição:</strong> <?php echo htmlspecialchars($conteudoAtivo['descricao']); ?></p>
                        <?php endif; ?>

                        <div class="actions" style="margin-top: 15px;">
                           <form method="POST" style="display: inline-block;">
                              <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">
                              <button type="submit" name="desativar_todos" class="btn btn-warning btn-sm"
                                 onclick="return confirm('Desativar conteúdo lateral atual?')">
                                 <i class="fas fa-eye-slash"></i> Desativar
                              </button>
                           </form>
                           <a href="../tv/index.php" target="_blank" class="btn btn-secondary btn-sm">
                              <i class="fas fa-eye"></i> Ver na TV
                           </a>
                        </div>
                     </div>
                  </div>
               <?php else: ?>
                  <div class="no-content">
                     <i class="fas fa-eye-slash" style="font-size: 3rem; color: #ccc; margin-bottom: 15px;"></i>
                     <h4>Nenhum conteúdo lateral ativo</h4>
                     <p>Envie um novo conteúdo ou ative um existente para aparecer na sidebar da TV.</p>
                  </div>
               <?php endif; ?>
            </div>
         </div>

         <!-- Upload de Novo Conteúdo -->
         <div class="card">
            <div class="card-header">
               <h3><i class="fas fa-cloud-upload-alt"></i> Enviar Novo Conteúdo Lateral</h3>
            </div>
            <div class="card-body">
               <form method="POST" enctype="multipart/form-data" class="upload-form">
                  <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

                  <div class="form-group">
                     <label for="arquivo">
                        <i class="fas fa-file"></i> Arquivo
                     </label>
                     <div class="drop-zone" id="dropZone">
                        <div class="drop-zone-content">
                           <i class="fas fa-cloud-upload-alt"></i>
                           <h4>Arraste o arquivo aqui ou clique para selecionar</h4>
                           <p>Formatos aceitos: JPG, PNG, GIF, MP4, AVI, MOV</p>
                           <p>Tamanho máximo: <?php echo formatFileSize(MAX_FILE_SIZE); ?></p>
                        </div>
                        <input type="file" name="arquivo" id="arquivo" accept="image/*,video/*" required>
                     </div>
                  </div>

                  <div class="form-group">
                     <label for="descricao">
                        <i class="fas fa-comment"></i> Descrição (Opcional)
                     </label>
                     <textarea name="descricao" id="descricao" rows="3"
                        placeholder="Descrição ou observações sobre este conteúdo"></textarea>
                  </div>

                  <div class="form-group">
                     <label>
                        <input type="checkbox" name="ativar_imediatamente" id="ativar_imediatamente" checked>
                        Ativar imediatamente após upload
                     </label>
                  </div>

                  <div class="form-actions">
                     <button type="submit" name="upload_lateral" class="btn btn-success">
                        <i class="fas fa-upload"></i> Enviar Conteúdo
                     </button>
                  </div>
               </form>
            </div>
         </div>

         <!-- Lista de Conteúdos -->
         <div class="card">
            <div class="card-header">
               <h3><i class="fas fa-list"></i> Histórico de Conteúdos Laterais</h3>
            </div>
            <div class="card-body">
               <?php if (empty($conteudos)): ?>
                  <p class="text-center">Nenhum conteúdo lateral encontrado.</p>
               <?php else: ?>
                  <div class="table-responsive">
                     <table class="content-table">
                        <thead>
                           <tr>
                              <th>Preview</th>
                              <th>Arquivo</th>
                              <th>Tipo</th>
                              <th>Status</th>
                              <th>Upload</th>
                              <th>Descrição</th>
                              <th>Ações</th>
                           </tr>
                        </thead>
                        <tbody>
                           <?php foreach ($conteudos as $conteudo): ?>
                              <tr class="<?php echo $conteudo['ativo'] ? 'active-row' : ''; ?>">
                                 <td class="preview-cell">
                                    <?php
                                    $src = SIDEBAR_WEB_PATH . $conteudo['arquivo'];
                                    if ($conteudo['tipo'] === 'video'):
                                    ?>
                                       <video src="<?php echo $src; ?>" class="preview-video" muted></video>
                                    <?php else: ?>
                                       <img src="<?php echo $src; ?>" class="preview-img">
                                    <?php endif; ?>
                                 </td>
                                 <td class="filename"><?php echo htmlspecialchars($conteudo['nome_original']); ?></td>
                                 <td>
                                    <span class="badge badge-<?php echo $conteudo['tipo'] === 'imagem' ? 'info' : 'warning'; ?>">
                                       <?php echo ucfirst($conteudo['tipo']); ?>
                                    </span>
                                 </td>
                                 <td>
                                    <?php if ($conteudo['ativo']): ?>
                                       <span class="badge badge-success">
                                          <i class="fas fa-eye"></i> Ativo
                                       </span>
                                    <?php else: ?>
                                       <span class="badge badge-secondary">Inativo</span>
                                    <?php endif; ?>
                                 </td>
                                 <td>
                                    <?php echo date('d/m/Y H:i', strtotime($conteudo['data_upload'])); ?><br>
                                    <small><?php echo htmlspecialchars($conteudo['usuario_nome'] ?? 'Usuário'); ?></small>
                                 </td>
                                 <td>
                                    <div class="description-cell">
                                       <span class="description-text">
                                          <?php echo htmlspecialchars($conteudo['descricao'] ?: 'Sem descrição'); ?>
                                       </span>
                                       <button class="btn btn-sm" onclick="editDescription(<?php echo $conteudo['id']; ?>, '<?php echo htmlspecialchars($conteudo['descricao'], ENT_QUOTES); ?>')">
                                          <i class="fas fa-edit"></i>
                                       </button>
                                    </div>
                                 </td>
                                 <td class="actions">
                                    <?php if (!$conteudo['ativo']): ?>
                                       <form method="POST" style="display: inline-block;">
                                          <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">
                                          <input type="hidden" name="conteudo_id" value="<?php echo $conteudo['id']; ?>">
                                          <button type="submit" name="ativar_conteudo" class="btn btn-success btn-sm" title="Ativar este conteúdo">
                                             <i class="fas fa-eye"></i>
                                          </button>
                                       </form>
                                    <?php endif; ?>

                                    <a href="?delete=<?php echo $conteudo['id']; ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Tem certeza que deseja excluir este conteúdo lateral?')"
                                       title="Excluir">
                                       <i class="fas fa-trash"></i>
                                    </a>
                                 </td>
                              </tr>
                           <?php endforeach; ?>
                        </tbody>
                     </table>
                  </div>
               <?php endif; ?>
            </div>
         </div>
      </div>
   </main>

   <!-- Modal para editar descrição -->
   <div id="edit-description-modal" class="modal">
      <div class="modal-content">
         <span class="close" onclick="closeDescriptionModal()">&times;</span>
         <h3><i class="fas fa-edit"></i> Editar Descrição</h3>
         <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">
            <input type="hidden" name="conteudo_id" id="edit-content-id">

            <div class="form-group">
               <label for="nova_descricao">Descrição:</label>
               <textarea name="nova_descricao" id="nova_descricao" rows="3"
                  placeholder="Digite a nova descrição"></textarea>
            </div>

            <div class="form-actions">
               <button type="submit" name="atualizar_descricao" class="btn btn-primary">
                  <i class="fas fa-save"></i> Salvar
               </button>
               <button type="button" class="btn btn-secondary" onclick="closeDescriptionModal()">
                  Cancelar
               </button>
            </div>
         </form>
      </div>
   </div>

   <script src="../assets/js/admin.js"></script>
   <script>
      function editDescription(id, currentDescription) {
         document.getElementById('edit-content-id').value = id;
         document.getElementById('nova_descricao').value = currentDescription;
         document.getElementById('edit-description-modal').classList.add('visible');
      }

      function closeDescriptionModal() {
         document.getElementById('edit-description-modal').classList.remove('visible');
      }
      const dropZone = document.getElementById('dropZone');
      const fileInput = document.getElementById('arquivo');

      ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
         dropZone.addEventListener(eventName, preventDefaults, false);
      });

      function preventDefaults(e) {
         e.preventDefault();
         e.stopPropagation();
      }

      ['dragenter', 'dragover'].forEach(eventName => {
         dropZone.addEventListener(eventName, highlight, false);
      });

      ['dragleave', 'drop'].forEach(eventName => {
         dropZone.addEventListener(eventName, unhighlight, false);
      });

      function highlight(e) {
         dropZone.classList.add('dragover');
      }

      function unhighlight(e) {
         dropZone.classList.remove('dragover');
      }

      dropZone.addEventListener('drop', handleDrop, false);

      function handleDrop(e) {
         const dt = e.dataTransfer;
         const files = dt.files;

         if (files.length > 0) {
            fileInput.files = files;
            showFilePreview(files[0]);
         }
      }
      fileInput.addEventListener('change', function(e) {
         if (e.target.files.length > 0) {
            showFilePreview(e.target.files[0]);
         }
      });

      function showFilePreview(file) {
         const preview = document.createElement('div');
         preview.className = 'file-preview';
         preview.innerHTML = `
                <div class="preview-content">
                    <div class="preview-media">
                        ${file.type.startsWith('video/') ? 
                            `<video src="${URL.createObjectURL(file)}" controls style="max-width: 100px; max-height: 80px;"></video>` :
                            `<img src="${URL.createObjectURL(file)}" style="max-width: 100px; max-height: 80px; object-fit: cover;">`
                        }
                    </div>
                    <div class="preview-info">
                        <h5>${file.name}</h5>
                        <p>Tamanho: ${formatFileSize(file.size)}</p>
                        <p>Tipo: ${file.type.startsWith('video/') ? 'Vídeo' : 'Imagem'}</p>
                    </div>
                    <button type="button" class="btn-remove-file" onclick="removeFilePreview()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
         const existingPreview = document.querySelector('.file-preview');
         if (existingPreview) {
            existingPreview.remove();
         }
         dropZone.parentNode.insertBefore(preview, dropZone.nextSibling);
      }

      function removeFilePreview() {
         const preview = document.querySelector('.file-preview');
         if (preview) {
            preview.remove();
         }
         fileInput.value = '';
      }

      function formatFileSize(bytes) {
         if (bytes === 0) return '0 Bytes';
         const k = 1024;
         const sizes = ['Bytes', 'KB', 'MB', 'GB'];
         const i = Math.floor(Math.log(bytes) / Math.log(k));
         return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
      }
      const style = document.createElement('style');
      style.textContent = `
            .active-row {
                background-color: #e8f5e8 !important;
                border-left: 4px solid #27ae60;
            }
            
            .current-sidebar-content {
                display: flex;
                gap: 20px;
                align-items: center;
            }
            
            .sidebar-preview {
                flex-shrink: 0;
            }
            
            .sidebar-media {
                max-width: 200px;
                max-height: 150px;
                border-radius: 8px;
                border: 2px solid #ddd;
                object-fit: cover;
            }
            
            .sidebar-info {
                flex-grow: 1;
            }
            
            .no-content {
                text-align: center;
                padding: 40px 20px;
                color: #666;
            }
            
            .description-cell {
                max-width: 200px;
            }
            
            .description-text {
                display: block;
                margin-bottom: 5px;
                word-break: break-word;
            }
            
            .file-preview {
                margin-top: 15px;
                border: 2px solid #e1e8ed;
                border-radius: 10px;
                padding: 15px;
            }
            
            .preview-content {
                display: flex;
                align-items: center;
                gap: 15px;
                position: relative;
            }
            
            .btn-remove-file {
                position: absolute;
                top: -10px;
                right: -10px;
                width: 25px;
                height: 25px;
                border-radius: 50%;
                background: #e74c3c;
                color: white;
                border: none;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
            }
        `;
      document.head.appendChild(style);
   </script>
</body>

</html>