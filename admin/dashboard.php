<?php
session_start();
if (!isset($_SESSION['logado'])) {
   header("Location: index.php");
   exit;
}
include "../includes/db.php";
include "../includes/functions.php";

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
   $id = (int)$_GET['delete'];

   $stmt = $conn->prepare("SELECT arquivo FROM conteudos WHERE id = ?");
   $stmt->bind_param("i", $id);
   $stmt->execute();
   $result = $stmt->get_result();

   if ($row = $result->fetch_assoc()) {
      $arquivo_path = "../uploads/" . $row['arquivo'];
      if (file_exists($arquivo_path)) {
         unlink($arquivo_path);
      }

      $stmt = $conn->prepare("DELETE FROM conteudos WHERE id = ?");
      $stmt->bind_param("i", $id);
      $stmt->execute();

      $mensagem = "Arquivo excluído com sucesso!";
   }
}

if (isset($_POST['atualizar_tv'])) {
   file_put_contents("../temp/tv_update.txt", time());
   $mensagem = "Sinal de atualização enviado para as TVs!";
}

// Filtro por canal
$canal_filtro = isset($_GET['canal']) ? strtoupper(trim($_GET['canal'])) : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Painel Administrativo - NatterTV</title>
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

         <div class="card">
            <div class="card-header">
               <h3><i class="fas fa-chart-bar"></i> Resumo por Canal</h3>
            </div>
            <div class="card-body">
               <?php
               $stats_query = $conn->query("
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
               ?>

               <div class="channels-overview">
                  <?php while ($stat = $stats_query->fetch_assoc()): ?>
                     <div class="channel-overview-card">
                        <div class="channel-header">
                           <h4><i class="fas fa-tv"></i> Canal: <?php echo htmlspecialchars($stat['codigo_canal']); ?></h4>
                           <a href="?canal=<?php echo urlencode($stat['codigo_canal']); ?>" class="btn-filter">
                              <i class="fas fa-filter"></i> Filtrar
                           </a>
                        </div>
                        <div class="channel-stats">
                           <span><i class="fas fa-file"></i> <?php echo $stat['total_arquivos']; ?> arquivos</span>
                           <span><i class="fas fa-image"></i> <?php echo $stat['total_imagens']; ?> imagens</span>
                           <span><i class="fas fa-video"></i> <?php echo $stat['total_videos']; ?> vídeos</span>
                           <span><i class="fas fa-hdd"></i> <?php echo formatFileSize($stat['espaco_usado']); ?></span>
                        </div>
                        <div class="channel-actions">
                           <a href="../tv/index.php?canal=<?php echo urlencode($stat['codigo_canal']); ?>"
                              target="_blank" class="btn btn-sm btn-secondary">
                              <i class="fas fa-eye"></i> Ver Canal
                           </a>
                        </div>
                     </div>
                  <?php endwhile; ?>
               </div>
            </div>
         </div>
         <div class="card">
            <div class="card-header">
               <h3><i class="fas fa-list"></i> Conteúdos Ativos</h3>
               <?php if ($canal_filtro): ?>
                  <div class="filter-info">
                     <span>Filtrado por canal: <strong><?php echo htmlspecialchars($canal_filtro); ?></strong></span>
                     <a href="dashboard.php" class="btn-clear-filter">
                        <i class="fas fa-times"></i> Limpar Filtro
                     </a>
                  </div>
               <?php endif; ?>
            </div>
            <div class="card-body">
               <!-- Filtro por canal -->
               <div class="filter-section">
                  <form method="GET" class="filter-form">
                     <div class="filter-group">
                        <label for="canal_filter">Filtrar por Canal:</label>
                        <input type="text" name="canal" id="canal_filter"
                           placeholder="Digite o código do canal"
                           value="<?php echo htmlspecialchars($canal_filtro); ?>">
                        <button type="submit" class="btn btn-sm btn-primary">
                           <i class="fas fa-search"></i> Filtrar
                        </button>
                        <?php if ($canal_filtro): ?>
                           <a href="dashboard.php" class="btn btn-sm btn-secondary">
                              <i class="fas fa-times"></i> Limpar
                           </a>
                        <?php endif; ?>
                     </div>
                  </form>
               </div>

               <div class="table-responsive">
                  <table class="content-table">
                     <thead>
                        <tr>
                           <th>Preview</th>
                           <th>Arquivo</th>
                           <th>Canal</th>
                           <th>Tipo</th>
                           <th>Duração</th>
                           <th>Data Upload</th>
                           <th>Ações</th>
                        </tr>
                     </thead>
                     <tbody>
                        <?php
                        // Query base
                        $sql = "SELECT * FROM conteudos WHERE 1=1";
                        $params = [];
                        $types = "";

                        // Adicionar filtro por canal se especificado
                        if ($canal_filtro) {
                           $sql .= " AND codigo_canal = ?";
                           $params[] = $canal_filtro;
                           $types .= "s";
                        }

                        $sql .= " ORDER BY codigo_canal ASC, id DESC";

                        if ($params) {
                           $stmt = $conn->prepare($sql);
                           $stmt->bind_param($types, ...$params);
                           $stmt->execute();
                           $res = $stmt->get_result();
                        } else {
                           $res = $conn->query($sql);
                        }

                        if ($res->num_rows == 0) {
                           $colspan = $canal_filtro ? 7 : 7;
                           echo "<tr><td colspan='{$colspan}' class='text-center'>Nenhum conteúdo encontrado</td></tr>";
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
                                            <td><span class='channel-badge'>{$row['codigo_canal']}</span></td>
                                            <td><span class='badge badge-" . ($row['tipo'] == 'imagem' ? 'info' : 'warning') . "'>{$row['tipo']}</span></td>
                                            <td>{$row['duracao']}s</td>
                                            <td>" . date('d/m/Y H:i', strtotime($row['data_upload'])) . "</td>
                                            <td class='actions'>
                                                <a href='../tv/index.php?canal={$row['codigo_canal']}' target='_blank' 
                                                   class='btn btn-info btn-sm' title='Ver canal na TV'>
                                                    <i class='fas fa-tv'></i>
                                                </a>
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

   <style>
      .channels-overview {
         display: grid;
         grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
         gap: 20px;
         margin-bottom: 20px;
      }

      .channel-overview-card {
         background: #f8f9fa;
         border-radius: 10px;
         padding: 20px;
         border-left: 4px solid var(--green-color);
         transition: transform 0.2s ease;
      }

      .channel-overview-card:hover {
         transform: translateY(-2px);
         box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      }

      .channel-header {
         display: flex;
         justify-content: space-between;
         align-items: center;
         margin-bottom: 15px;
      }

      .channel-header h4 {
         margin: 0;
         color: var(--green-color);
         font-size: 1.1rem;
      }

      .btn-filter {
         background: var(--primary-color);
         color: white;
         padding: 6px 12px;
         border-radius: 4px;
         text-decoration: none;
         font-size: 0.8rem;
         transition: background 0.3s ease;
      }

      .btn-filter:hover {
         background: var(--warning-color);
      }

      .channel-stats {
         display: flex;
         flex-wrap: wrap;
         gap: 15px;
         margin-bottom: 15px;
      }

      .channel-stats span {
         display: flex;
         align-items: center;
         gap: 6px;
         font-size: 0.9rem;
         color: #666;
      }

      .channel-stats i {
         color: var(--primary-color);
         width: 16px;
      }

      .channel-actions {
         text-align: right;
      }

      .filter-section {
         background: #f8f9fa;
         padding: 15px;
         border-radius: 8px;
         margin-bottom: 20px;
      }

      .filter-form {
         display: flex;
         align-items: center;
         gap: 15px;
         flex-wrap: wrap;
      }

      .filter-group {
         display: flex;
         align-items: center;
         gap: 10px;
      }

      .filter-group label {
         font-weight: 500;
         color: var(--primary-color);
         white-space: nowrap;
      }

      .filter-group input {
         padding: 8px 12px;
         border: 2px solid #e1e8ed;
         border-radius: 6px;
         font-size: 0.9rem;
         text-transform: uppercase;
         font-weight: 600;
         font-family: 'Courier New', monospace;
      }

      .filter-group input:focus {
         outline: none;
         border-color: var(--secondary-color);
      }

      .filter-info {
         color: white;
         font-size: 0.9rem;
         display: flex;
         align-items: center;
         gap: 15px;
      }

      .btn-clear-filter {
         background: rgba(255, 255, 255, 0.2);
         color: white;
         padding: 4px 8px;
         border-radius: 4px;
         text-decoration: none;
         font-size: 0.8rem;
      }

      .btn-clear-filter:hover {
         background: rgba(255, 255, 255, 0.3);
      }

      .channel-badge {
         background: var(--green-color);
         color: white;
         padding: 4px 8px;
         border-radius: 4px;
         font-size: 0.8rem;
         font-weight: 600;
         font-family: 'Courier New', monospace;
      }

      .btn-info {
         background: var(--info-color);
         color: white;
      }

      .btn-info:hover {
         background: #2980b9;
      }

      /* Formatação automática do código do canal */
      #codigo_canal,
      #canal_filter {
         text-transform: uppercase !important;
      }
   </style>

   <script src="../assets/js/admin.js"></script>
   <script>
      // Formatação automática dos campos de código
      document.addEventListener('DOMContentLoaded', function() {
         const canalInputs = document.querySelectorAll('#codigo_canal, #canal_filter');

         canalInputs.forEach(input => {
            input.addEventListener('input', function(e) {
               this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            });
         });
      });
   </script>
</body>

</html>