<?php
session_start();
if (!isset($_SESSION['logado'])) {
   header("Location: index.php");
   exit;
}
include "../includes/db.php";
include "../includes/functions.php";

$sidebar_file = null;
if (isset($_GET['delete_sidebar'])) {
   foreach (glob(SIDEBAR_PATH . '*') as $f) {
      if (is_file($f)) unlink($f);
   }
   $mensagem = "Conteúdo lateral excluído com sucesso!";
}
$sidebar_files = array_filter(is_dir(SIDEBAR_PATH) ? scandir(SIDEBAR_PATH) : [], function ($f) {
   return !in_array($f, ['.', '..']);
});
if (!empty($sidebar_files)) {
   $sidebar_file = array_values($sidebar_files)[0];
}

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

      if ($isOperadorRestrito) {
         $stmt = $conn->prepare("DELETE FROM conteudos WHERE id = ? AND codigo_canal = ?");
         $stmt->bind_param("is", $id, $canal_usuario);
      } else {
         $stmt = $conn->prepare("DELETE FROM conteudos WHERE id = ?");
         $stmt->bind_param("i", $id);
      }
      $stmt->execute();

      $mensagem = "Arquivo excluído com sucesso!";
   }
}

if (isset($_POST['atualizar_tv'])) {
   file_put_contents("../temp/tv_update.txt", time());
   $mensagem = "Sinal de atualização enviado para as TVs!";
}

$canal_filtro = isset($_GET['canal']) ? strtoupper(trim($_GET['canal'])) : '';
$canal_usuario = $_SESSION['codigo_canal'] ?? 'TODOS';
$isOperadorRestrito = (isset($_SESSION['nivel']) && $_SESSION['nivel'] === 'operador' && $canal_usuario !== 'TODOS');
if ($isOperadorRestrito) {
   $canal_filtro = $canal_usuario;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Dashboard - NatterTV</title>
   <link rel="stylesheet" href="../assets/css/base.css">
   <link rel="stylesheet" href="../assets/css/admin-style.css">
   <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
   <link rel="shortcut icon" href="../assets/images/favicon.png" type="image/x-icon">
</head>

<body>
   <nav class="sidebar">
      <div class="sidebar-header">
         <img src="../assets/images/Natter Logo.PNG" alt="NatterTV">
         <h2>NatterTV</h2>
      </div>
      <ul class="sidebar-menu">
         <li class="active"><a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
         <li><a href="upload.php"><i class="fas fa-cloud-upload-alt"></i> Upload</a></li>
         <li><a href="rss.php"><i class="fas fa-rss"></i> RSS Feeds</a></li>
         <li><a href="sidebar.php"><i class="fas fa-bullhorn"></i> Conteúdo Lateral</a></li>
         <?php if (isset($_SESSION['nivel']) && $_SESSION['nivel'] === 'admin'): ?>
            <li><a href="usuarios.php"><i class="fas fa-users"></i> Usuários</a></li>
         <?php endif; ?>
         <li><a href="../tv/index.php" target="_blank"><i class="fas fa-external-link-alt"></i> Ver TV</a></li>
         <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
      </ul>
   </nav>

   <main class="main-content">
      <header class="topbar">
         <h1><i class="fas fa-chart-line"></i> Painel de Controle</h1>
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
               <form method="POST" class="inline-block">
                  <button type="submit" name="atualizar_tv" class="btn btn-primary">
                     <i class="fas fa-sync-alt"></i> Atualizar TV Agora
                  </button>
               </form>
               <a href="../tv/index.php" target="_blank" class="btn btn-secondary">
                  <i class="fas fa-eye"></i> Visualizar TV
               </a>
               <a href="rss.php" class="btn btn-success">
                  <i class="fas fa-rss"></i> Gerenciar RSS
               </a>
            </div>
         </div>

         <div class="card">
            <div class="card-header">
               <h3><i class="fas fa-chart-pie"></i> Estatísticas do Sistema</h3>
            </div>
            <div class="card-body">
               <?php
               if ($isOperadorRestrito) {
                  $stmt = $conn->prepare("SELECT COUNT(*) as total_arquivos, SUM(CASE WHEN tipo = 'imagem' THEN 1 ELSE 0 END) as total_imagens, SUM(CASE WHEN tipo = 'video' THEN 1 ELSE 0 END) as total_videos, SUM(tamanho) as espaco_usado FROM conteudos WHERE ativo = 1 AND codigo_canal = ?");
                  $stmt->bind_param("s", $canal_usuario);
                  $stmt->execute();
                  $stats_conteudo = $stmt->get_result()->fetch_assoc();
                  $stmt->close();

                  $stmt = $conn->prepare("SELECT COUNT(*) as total_feeds, SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) as feeds_ativos, COUNT(DISTINCT codigo_canal) as canais_rss FROM feeds_rss WHERE codigo_canal = ?");
                  $stmt->bind_param("s", $canal_usuario);
                  $stmt->execute();
                  $stats_rss = $stmt->get_result()->fetch_assoc();
                  $stmt->close();

                  $stmt = $conn->prepare("SELECT COUNT(*) as total_items FROM cache_rss c INNER JOIN feeds_rss f ON c.feed_id = f.id WHERE f.ativo = 1 AND f.codigo_canal = ?");
                  $stmt->bind_param("s", $canal_usuario);
                  $stmt->execute();
                  $stats_rss_items = $stmt->get_result()->fetch_assoc();
                  $stmt->close();
               } else {
                  $stats_conteudo = $conn->query("SELECT COUNT(*) as total_arquivos, SUM(CASE WHEN tipo = 'imagem' THEN 1 ELSE 0 END) as total_imagens, SUM(CASE WHEN tipo = 'video' THEN 1 ELSE 0 END) as total_videos, SUM(tamanho) as espaco_usado FROM conteudos WHERE ativo = 1")->fetch_assoc();
                  $stats_rss = $conn->query("SELECT COUNT(*) as total_feeds, SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) as feeds_ativos, COUNT(DISTINCT codigo_canal) as canais_rss FROM feeds_rss")->fetch_assoc();
                  $stats_rss_items = $conn->query("SELECT COUNT(*) as total_items FROM cache_rss c INNER JOIN feeds_rss f ON c.feed_id = f.id WHERE f.ativo = 1")->fetch_assoc();
               }
               ?>

               <div class="stats-overview">
                  <div class="stat-card">
                     <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                     </div>
                     <div class="stat-info">
                        <h3><?php echo $stats_conteudo['total_arquivos']; ?></h3>
                        <p>Total de Arquivos</p>
                     </div>
                  </div>

                  <div class="stat-card">
                     <div class="stat-icon">
                        <i class="fas fa-rss"></i>
                     </div>
                     <div class="stat-info">
                        <h3><?php echo $stats_rss['feeds_ativos']; ?></h3>
                        <p>Feeds RSS Ativos</p>
                     </div>
                  </div>

                  <div class="stat-card">
                     <div class="stat-icon">
                        <i class="fas fa-newspaper"></i>
                     </div>
                     <div class="stat-info">
                        <h3><?php echo $stats_rss_items['total_items']; ?></h3>
                        <p>Notícias RSS</p>
                     </div>
                  </div>

                  <div class="stat-card">
                     <div class="stat-icon">
                        <i class="fas fa-hdd"></i>
                     </div>
                     <div class="stat-info">
                        <h3><?php echo formatFileSize($stats_conteudo['espaco_usado']); ?></h3>
                        <p>Espaço Utilizado</p>
                     </div>
                  </div>
               </div>
            </div>
         </div>

         <div class="card">
            <div class="card-header">
               <h3><i class="fas fa-chart-bar"></i> Resumo por Canal</h3>
            </div>
            <div class="card-body">
               <?php
               if ($isOperadorRestrito) {
                  $stmt = $conn->prepare("SELECT codigo_canal, COUNT(*) as total_arquivos, SUM(CASE WHEN tipo = 'imagem' THEN 1 ELSE 0 END) as total_imagens, SUM(CASE WHEN tipo = 'video' THEN 1 ELSE 0 END) as total_videos, SUM(tamanho) as espaco_usado FROM conteudos WHERE ativo = 1 AND codigo_canal = ? GROUP BY codigo_canal ORDER BY codigo_canal");
                  $stmt->bind_param("s", $canal_usuario);
                  $stmt->execute();
                  $stats_query = $stmt->get_result();
                  $stmt->close();

                  $rss_por_canal = [];
                  $stmt2 = $conn->prepare("SELECT codigo_canal, COUNT(*) as total_feeds FROM feeds_rss WHERE ativo = 1 AND codigo_canal = ? GROUP BY codigo_canal");
                  $stmt2->bind_param("s", $canal_usuario);
                  $stmt2->execute();
                  $rss_query = $stmt2->get_result();
                  while ($row = $rss_query->fetch_assoc()) {
                     $rss_por_canal[$row['codigo_canal']] = $row['total_feeds'];
                  }
                  $stmt2->close();
               } else {
                  $stats_query = $conn->query("SELECT codigo_canal, COUNT(*) as total_arquivos, SUM(CASE WHEN tipo = 'imagem' THEN 1 ELSE 0 END) as total_imagens, SUM(CASE WHEN tipo = 'video' THEN 1 ELSE 0 END) as total_videos, SUM(tamanho) as espaco_usado FROM conteudos WHERE ativo = 1 GROUP BY codigo_canal ORDER BY codigo_canal");
                  $rss_por_canal = [];
                  $rss_query = $conn->query("SELECT codigo_canal, COUNT(*) as total_feeds FROM feeds_rss WHERE ativo = 1 GROUP BY codigo_canal");
                  while ($row = $rss_query->fetch_assoc()) {
                     $rss_por_canal[$row['codigo_canal']] = $row['total_feeds'];
                  }
               }
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
                           <span><i class="fas fa-file-alt"></i> <?php echo $stat['total_arquivos']; ?> arquivos</span>
                           <span><i class="fas fa-image"></i> <?php echo $stat['total_imagens']; ?> imagens</span>
                           <span><i class="fas fa-video"></i> <?php echo $stat['total_videos']; ?> vídeos</span>
                           <span><i class="fas fa-rss"></i> <?php echo $rss_por_canal[$stat['codigo_canal']] ?? 0; ?> feeds RSS</span>
                           <span><i class="fas fa-hdd"></i> <?php echo formatFileSize($stat['espaco_usado']); ?></span>
                        </div>
                        <div class="channel-actions">
                           <a href="../tv/index.php?canal=<?php echo urlencode($stat['codigo_canal']); ?>"
                              target="_blank" class="btn btn-sm btn-secondary">
                              <i class="fas fa-eye"></i> Ver Canal
                           </a>
                           <a href="rss.php?canal=<?php echo urlencode($stat['codigo_canal']); ?>"
                              class="btn btn-sm btn-success">
                              <i class="fas fa-rss"></i> RSS
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
               <div class="filter-section">
                  <form method="GET" class="filter-form">
                     <div class="filter-group">
                        <label for="canal_filter">Filtrar por Canal:</label>
                        <input type="text" name="canal" id="canal_filter"
                           placeholder="Digite o código do canal"
                           value="<?php echo htmlspecialchars($canal_filtro); ?>" <?php echo $isOperadorRestrito ? 'readonly' : ''; ?>>
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
                        $sql = "SELECT * FROM conteudos WHERE 1=1";
                        $params = [];
                        $types = "";

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
                           $colspan = 7;
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
                                                    <i class='fas fa-trash-alt'></i>
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
   <script>
      document.addEventListener('DOMContentLoaded', function() {
         const canalInputs = document.querySelectorAll('#codigo_canal, #canal_filter');

         canalInputs.forEach(input => {
            input.addEventListener('input', function(e) {
               this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            });
         });

         // Add smooth animations
         const cards = document.querySelectorAll('.card');
         cards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
         });
      });
   </script>
</body>

</html>