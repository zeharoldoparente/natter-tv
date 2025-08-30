<?php
session_start();
if (!isset($_SESSION['logado'])) {
   header("Location: index.php");
   exit;
}
include "../includes/db.php";
include "../includes/functions.php";
include "../includes/rss_functions.php";

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   try {
      if (!verificarTokenCSRF($_POST['csrf_token'])) {
         throw new Exception('Token de segurança inválido');
      }

      if (isset($_POST['adicionar_feed'])) {
         $nome = sanitizarEntrada($_POST['nome']);
         $url_feed = sanitizarEntrada($_POST['url_feed'], 'url');
         $codigo_canal = strtoupper(sanitizarEntrada($_POST['codigo_canal']));
         $velocidade = (int)$_POST['velocidade'];
         $cor_texto = sanitizarEntrada($_POST['cor_texto']);
         $cor_fundo = sanitizarEntrada($_POST['cor_fundo']);
         $posicao = sanitizarEntrada($_POST['posicao']);

         if (empty($nome) || empty($url_feed)) {
            throw new Exception('Nome e URL do feed são obrigatórios');
         }

         if (!filter_var($url_feed, FILTER_VALIDATE_URL)) {
            throw new Exception('URL do feed inválida');
         }

         if (empty($codigo_canal)) {
            $codigo_canal = 'TODOS';
         }

         $usuario_id = $_SESSION['usuario_id'] ?? 1;

         $stmt = $conn->prepare("
        INSERT INTO feeds_rss (nome, url_feed, codigo_canal, velocidade_scroll, cor_texto, cor_fundo, posicao, usuario_upload) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

         $stmt->bind_param("sssisssi", $nome, $url_feed, $codigo_canal, $velocidade, $cor_texto, $cor_fundo, $posicao, $usuario_id);

         if ($stmt->execute()) {
            $feed_id = $conn->insert_id;
            ob_start();
            try {
               atualizarFeedRSS($feed_id);
            } finally {
               ob_end_clean();
            }
            $mensagem = "Feed RSS adicionado com sucesso!";
            sinalizarAtualizacaoTV();
         } else {
            throw new Exception('Erro ao adicionar feed RSS: ' . $stmt->error);
         }
         $stmt->close();
      }

      if (isset($_POST['atualizar_feed'])) {
         $id = (int)$_POST['feed_id'];
         $nome = sanitizarEntrada($_POST['nome']);
         $url_feed = sanitizarEntrada($_POST['url_feed'], 'url');
         $codigo_canal = strtoupper(sanitizarEntrada($_POST['codigo_canal']));
         $velocidade = (int)$_POST['velocidade'];
         $cor_texto = sanitizarEntrada($_POST['cor_texto']);
         $cor_fundo = sanitizarEntrada($_POST['cor_fundo']);
         $posicao = sanitizarEntrada($_POST['posicao']);
         $ativo = isset($_POST['ativo']) ? 1 : 0;

         $stmt = $conn->prepare("
                UPDATE feeds_rss 
                SET nome = ?, url_feed = ?, codigo_canal = ?, velocidade_scroll = ?, cor_texto = ?, cor_fundo = ?, posicao = ?, ativo = ?
                WHERE id = ?
            ");

         $stmt->bind_param("sssssssii", $nome, $url_feed, $codigo_canal, $velocidade, $cor_texto, $cor_fundo, $posicao, $ativo, $id);

         if ($stmt->execute()) {
            ob_start();
            try {
               atualizarFeedRSS($id);
            } finally {
               ob_end_clean();
            }
            $mensagem = "Feed RSS atualizado com sucesso!";
            sinalizarAtualizacaoTV();
         }
         $stmt->close();
      }

      if (isset($_POST['atualizar_todos_feeds'])) {
         ob_start();
         try {
            atualizarTodosFeedsRSS();
         } finally {
            ob_end_clean();
         }
         $mensagem = "Todos os feeds RSS foram atualizados!";
      }
   } catch (Exception $e) {
      $erro = $e->getMessage();
   }
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
   $id = (int)$_GET['delete'];

   $stmt = $conn->prepare("DELETE FROM feeds_rss WHERE id = ?");
   $stmt->bind_param("i", $id);

   if ($stmt->execute()) {
      $mensagem = "Feed RSS excluído com sucesso!";
      sinalizarAtualizacaoTV();
   }
   $stmt->close();
}

$feeds = [];
$res = $conn->query("
    SELECT f.*, COUNT(c.id) as total_itens,
           MAX(c.data_publicacao) as ultima_atualizacao
    FROM feeds_rss f 
    LEFT JOIN cache_rss c ON f.id = c.feed_id 
    GROUP BY f.id 
    ORDER BY f.codigo_canal, f.nome
");

if ($res) {
   while ($row = $res->fetch_assoc()) {
      $feeds[] = $row;
   }
}

$canais = [];
$res = $conn->query("SELECT DISTINCT codigo_canal FROM conteudos WHERE ativo = 1 ORDER BY codigo_canal");
if ($res) {
   while ($row = $res->fetch_assoc()) {
      $canais[] = $row['codigo_canal'];
   }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Gerenciar RSS - NatterTV</title>
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
         <li class="active"><a href="rss.php"><i class="fas fa-rss"></i> RSS Feeds</a></li>
         <li><a href="sidebar.php"><i class="fas fa-th-large"></i> Conteúdo Lateral</a></li>
         <li><a href="../tv/index.php" target="_blank"><i class="fas fa-external-link-alt"></i> Ver TV</a></li>
         <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
      </ul>
   </nav>

   <main class="main-content">
      <header class="topbar">
         <h1><i class="fas fa-rss"></i> Gerenciar RSS Feeds</h1>
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

         <!-- Controles Gerais -->
         <div class="card">
            <div class="card-header">
               <h3><i class="fas fa-sync"></i> Controles RSS</h3>
            </div>
            <div class="card-body">
               <form method="POST" style="display: inline-block;">
                  <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">
                  <button type="submit" name="atualizar_todos_feeds" class="btn btn-primary">
                     <i class="fas fa-sync"></i> Atualizar Todos os Feeds
                  </button>
               </form>
               <a href="../tv/index.php" target="_blank" class="btn btn-secondary">
                  <i class="fas fa-eye"></i> Visualizar TV
               </a>
            </div>
         </div>

         <!-- Adicionar Novo Feed -->
         <div class="card">
            <div class="card-header">
               <h3><i class="fas fa-plus"></i> Adicionar Feed RSS</h3>
            </div>
            <div class="card-body">
               <form method="POST" class="rss-form">
                  <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

                  <div class="form-row">
                     <div class="form-group">
                        <label for="nome">
                           <i class="fas fa-tag"></i> Nome do Feed
                        </label>
                        <input type="text" name="nome" id="nome" required placeholder="Ex: Notícias Globo">
                     </div>

                     <div class="form-group">
                        <label for="url_feed">
                           <i class="fas fa-link"></i> URL do Feed RSS
                        </label>
                        <input type="url" name="url_feed" id="url_feed" required placeholder="https://example.com/rss.xml">
                     </div>
                  </div>

                  <div class="form-row">
                     <div class="form-group">
                        <label for="codigo_canal">
                           <i class="fas fa-tv"></i> Canal
                        </label>
                        <select name="codigo_canal" id="codigo_canal">
                           <option value="TODOS">Todos os Canais</option>
                           <?php foreach ($canais as $canal): ?>
                              <option value="<?php echo htmlspecialchars($canal); ?>">
                                 Canal <?php echo htmlspecialchars($canal); ?>
                              </option>
                           <?php endforeach; ?>
                        </select>
                     </div>

                     <div class="form-group">
                        <label for="velocidade">
                           <i class="fas fa-tachometer-alt"></i> Velocidade do Scroll (px/s)
                        </label>
                        <input type="number" name="velocidade" id="velocidade" value="50" min="10" max="1000">
                     </div>
                  </div>

                  <div class="form-row">
                     <div class="form-group">
                        <label for="cor_texto">
                           <i class="fas fa-palette"></i> Cor do Texto
                        </label>
                        <input type="color" name="cor_texto" id="cor_texto" value="#FFFFFF">
                     </div>

                     <div class="form-group">
                        <label for="cor_fundo">
                           <i class="fas fa-fill-drip"></i> Cor do Fundo
                        </label>
                        <input type="color" name="cor_fundo" id="cor_fundo" value="#000000">
                     </div>

                     <div class="form-group">
                        <label for="posicao">
                           <i class="fas fa-arrows-alt-v"></i> Posição
                        </label>
                        <select name="posicao" id="posicao">
                           <option value="rodape">Rodapé</option>
                           <option value="topo">Topo</option>
                        </select>
                     </div>
                  </div>

                  <div class="form-actions">
                     <button type="submit" name="adicionar_feed" class="btn btn-success">
                        <i class="fas fa-plus"></i> Adicionar Feed
                     </button>
                  </div>
               </form>
            </div>
         </div>

         <!-- Lista de Feeds -->
         <div class="card">
            <div class="card-header">
               <h3><i class="fas fa-list"></i> Feeds RSS Cadastrados</h3>
            </div>
            <div class="card-body">
               <?php if (empty($feeds)): ?>
                  <p class="text-center">Nenhum feed RSS cadastrado ainda.</p>
               <?php else: ?>
                  <div class="table-responsive">
                     <table class="content-table">
                        <thead>
                           <tr>
                              <th>Nome</th>
                              <th>Canal</th>
                              <th>URL</th>
                              <th>Posição</th>
                              <th>Itens</th>
                              <th>Última Atualização</th>
                              <th>Status</th>
                              <th>Ações</th>
                           </tr>
                        </thead>
                        <tbody>
                           <?php foreach ($feeds as $feed): ?>
                              <tr>
                                 <td><strong><?php echo htmlspecialchars($feed['nome']); ?></strong></td>
                                 <td>
                                    <span class="channel-badge">
                                       <?php echo htmlspecialchars($feed['codigo_canal']); ?>
                                    </span>
                                 </td>
                                 <td>
                                    <a href="<?php echo htmlspecialchars($feed['url_feed']); ?>"
                                       target="_blank" class="feed-url">
                                       <?php echo substr($feed['url_feed'], 0, 50); ?>...
                                    </a>
                                 </td>
                                 <td>
                                    <span class="badge <?php echo $feed['posicao'] == 'rodape' ? 'badge-info' : 'badge-warning'; ?>">
                                       <?php echo ucfirst($feed['posicao']); ?>
                                    </span>
                                 </td>
                                 <td><?php echo $feed['total_itens']; ?> itens</td>
                                 <td>
                                    <?php
                                    echo $feed['ultima_atualizacao']
                                       ? date('d/m/Y H:i', strtotime($feed['ultima_atualizacao']))
                                       : 'Nunca';
                                    ?>
                                 </td>
                                 <td>
                                    <span class="badge <?php echo $feed['ativo'] ? 'badge-success' : 'badge-danger'; ?>">
                                       <?php echo $feed['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                 </td>
                                 <td class="actions">
                                    <button class="btn btn-info btn-sm" onclick="editarFeed(<?php echo $feed['id']; ?>)">
                                       <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?delete=<?php echo $feed['id']; ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Tem certeza que deseja excluir este feed?')">
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
   <!-- Modal de edição de Feed -->
   <div id="edit-modal" class="modal">
      <div class="modal-content">
         <span class="close" onclick="fecharModal()">&times;</span>
         <h3><i class="fas fa-edit"></i> Editar Feed RSS</h3>
         <form method="POST" class="rss-form">
            <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">
            <input type="hidden" name="feed_id" id="edit-feed-id">

            <div class="form-row">
               <div class="form-group">
                  <label for="edit-nome"><i class="fas fa-tag"></i> Nome do Feed</label>
                  <input type="text" name="nome" id="edit-nome" required>
               </div>

               <div class="form-group">
                  <label for="edit-url_feed"><i class="fas fa-link"></i> URL do Feed RSS</label>
                  <input type="url" name="url_feed" id="edit-url_feed" required>
               </div>
            </div>

            <div class="form-row">
               <div class="form-group">
                  <label for="edit-codigo_canal"><i class="fas fa-tv"></i> Canal</label>
                  <select name="codigo_canal" id="edit-codigo_canal">
                     <option value="TODOS">Todos os Canais</option>
                     <?php foreach ($canais as $canal): ?>
                        <option value="<?php echo htmlspecialchars($canal); ?>">Canal <?php echo htmlspecialchars($canal); ?></option>
                     <?php endforeach; ?>
                  </select>
               </div>

               <div class="form-group">
                  <label for="edit-velocidade"><i class="fas fa-tachometer-alt"></i> Velocidade do Scroll (px/s)</label>
                  <input type="number" name="velocidade" id="edit-velocidade" min="10" max="1000">
               </div>
            </div>

            <div class="form-row">
               <div class="form-group">
                  <label for="edit-cor_texto"><i class="fas fa-palette"></i> Cor do Texto</label>
                  <input type="color" name="cor_texto" id="edit-cor_texto">
               </div>

               <div class="form-group">
                  <label for="edit-cor_fundo"><i class="fas fa-fill-drip"></i> Cor do Fundo</label>
                  <input type="color" name="cor_fundo" id="edit-cor_fundo">
               </div>

               <div class="form-group">
                  <label for="edit-posicao"><i class="fas fa-arrows-alt-v"></i> Posição</label>
                  <select name="posicao" id="edit-posicao">
                     <option value="rodape">Rodapé</option>
                     <option value="topo">Topo</option>
                  </select>
               </div>
            </div>

            <div class="form-row">
               <div class="form-group">
                  <label><input type="checkbox" name="ativo" id="edit-ativo"> Ativo</label>
               </div>
            </div>

            <div class="form-actions">
               <button type="submit" name="atualizar_feed" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
               <button type="button" class="btn btn-secondary" onclick="fecharModal()">Cancelar</button>
            </div>
         </form>
      </div>
   </div>

   <script src="../assets/js/admin.js"></script>
   <script>
      const feedsData = <?php echo json_encode($feeds); ?>;

      function editarFeed(id) {
         const feed = feedsData.find((f) => f.id == id);
         if (!feed) return;

         document.getElementById('edit-feed-id').value = feed.id;
         document.getElementById('edit-nome').value = feed.nome;
         document.getElementById('edit-url_feed').value = feed.url_feed;
         document.getElementById('edit-codigo_canal').value = feed.codigo_canal;
         document.getElementById('edit-velocidade').value = feed.velocidade_scroll;
         document.getElementById('edit-cor_texto').value = feed.cor_texto;
         document.getElementById('edit-cor_fundo').value = feed.cor_fundo;
         document.getElementById('edit-posicao').value = feed.posicao;
         document.getElementById('edit-ativo').checked = feed.ativo == 1;

         const modal = document.getElementById('edit-modal');
         modal.classList.add('visible');
      }

      function fecharModal() {
         document.getElementById('edit-modal').classList.remove('visible');
      }
   </script>
</body>

</html>