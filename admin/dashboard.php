<?php
session_start();
if (!isset($_SESSION['logado'])) {
   header("Location: index.php");
   exit;
}
include "../includes/db.php";
include "../includes/functions.php";

// Processar exclusão de arquivo se requisitado
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
   $id = (int)$_GET['delete'];

   // Buscar o arquivo para deletar fisicamente
   $stmt = $conn->prepare("SELECT arquivo FROM conteudos WHERE id = ?");
   $stmt->bind_param("i", $id);
   $stmt->execute();
   $result = $stmt->get_result();

   if ($row = $result->fetch_assoc()) {
      $arquivo_path = "../uploads/" . $row['arquivo'];
      if (file_exists($arquivo_path)) {
         unlink($arquivo_path); // Deleta o arquivo físico
      }

      // Deleta o registro do banco
      $stmt = $conn->prepare("DELETE FROM conteudos WHERE id = ?");
      $stmt->bind_param("i", $id);
      $stmt->execute();

      $mensagem = "Arquivo excluído com sucesso!";
   }
}

// Atualizar TV (força refresh na página da TV)
if (isset($_POST['atualizar_tv'])) {
   // Cria um arquivo de sinal para a TV detectar
   file_put_contents("../temp/tv_update.txt", time());
   $mensagem = "Sinal de atualização enviado para as TVs!";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Painel Administrativo - TV Corporativa</title>
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
         <li class="active"><a href="dashboard.php"><i class="fas fa-dashboard"></i> Dashboard</a></li>
         <li><a href="upload.php"><i class="fas fa-upload"></i> Upload</a></li>
         <li><a href="../tv/index.php" target="_blank"><i class="fas fa-external-link-alt"></i> Ver TV</a></li>
         <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
      </ul>
   </nav>

   <main class="main-content">
      <header class="topbar">
         <h1><i class="fas fa-dashboard"></i> Painel de Controle</h1>
         <div class="user-info">
            <span>Bem-vindo, <?php echo $_SESSION['nome'] ?? 'Admin'; ?>!</span>
         </div>
      </header>

      <div class="content">
         <?php if (isset($mensagem)): ?>
            <div class="alert alert-success">
               <i class="fas fa-check-circle"></i> <?php echo $mensagem; ?>
            </div>
         <?php endif; ?>

         <!-- Controles da TV -->
         <div class="card">
            <div class="card-header">
               <h3><i class="fas fa-broadcast-tower"></i> Controle da TV</h3>
            </div>
            <div class="card-body">
               <form method="POST" style="display: inline-block;">
                  <button type="submit" name="atualizar_tv" class="btn btn-primary">
                     <i class="fas fa-sync"></i> Atualizar TV Agora
                  </button>
               </form>
               <a href="../tv/index.php" target="_blank" class="btn btn-secondary">
                  <i class="fas fa-eye"></i> Visualizar TV
               </a>
            </div>
         </div>

         <!-- Upload de novos arquivos -->
         <div class="card">
            <div class="card-header">
               <h3><i class="fas fa-cloud-upload-alt"></i> Enviar Novo Conteúdo</h3>
            </div>
            <div class="card-body">
               <form action="upload.php" method="POST" enctype="multipart/form-data" class="upload-form">
                  <div class="form-group">
                     <label for="arquivo"><i class="fas fa-file"></i> Selecionar Arquivo</label>
                     <input type="file" name="arquivo" id="arquivo" required accept="image/*,video/*">
                     <small>Formatos aceitos: JPG, PNG, GIF, MP4, AVI, MOV</small>
                  </div>
                  <div class="form-group">
                     <label for="duracao"><i class="fas fa-clock"></i> Duração (segundos - apenas para imagens)</label>
                     <input type="number" name="duracao" id="duracao" value="5" min="1" max="60">
                  </div>
                  <button type="submit" class="btn btn-success">
                     <i class="fas fa-upload"></i> Enviar Arquivo
                  </button>
               </form>
            </div>
         </div>

         <!-- Lista de conteúdos -->
         <div class="card">
            <div class="card-header">
               <h3><i class="fas fa-list"></i> Conteúdos Ativos</h3>
            </div>
            <div class="card-body">
               <div class="table-responsive">
                  <table class="content-table">
                     <thead>
                        <tr>
                           <th>Preview</th>
                           <th>Arquivo</th>
                           <th>Tipo</th>
                           <th>Duração</th>
                           <th>Data Upload</th>
                           <th>Ações</th>
                        </tr>
                     </thead>
                     <tbody>
                        <?php
                        $res = $conn->query("SELECT * FROM conteudos ORDER BY id DESC");
                        if ($res->num_rows == 0) {
                           echo "<tr><td colspan='6' class='text-center'>Nenhum conteúdo encontrado</td></tr>";
                        } else {
                           while ($row = $res->fetch_assoc()) {
                              $preview = '';
                              if ($row['tipo'] == 'imagem') {
                                 $preview = "<img src='../uploads/{$row['arquivo']}' class='preview-img'>";
                              } else {
                                 $preview = "<video src='../uploads/{$row['arquivo']}' class='preview-video' muted></video>";
                              }

                              echo "<tr>
                                            <td class='preview-cell'>{$preview}</td>
                                            <td class='filename'>{$row['arquivo']}</td>
                                            <td><span class='badge badge-" . ($row['tipo'] == 'imagem' ? 'info' : 'warning') . "'>{$row['tipo']}</span></td>
                                            <td>{$row['duracao']}s</td>
                                            <td>" . date('d/m/Y H:i', strtotime($row['data_upload'])) . "</td>
                                            <td class='actions'>
                                                <a href='?delete={$row['id']}' class='btn btn-danger btn-sm' 
                                                   onclick='return confirm(\"Tem certeza que deseja excluir este arquivo?\")'>
                                                    <i class='fas fa-trash'></i>
                                                </a>
                                            </td>
                                        </tr>";
                           }
                        }
                        ?>
                     </tbody>
                  </table>
               </div>
            </div>
         </div>
      </div>
   </main>

   <script src="../assets/js/admin.js"></script>
</body>

</html>