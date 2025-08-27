<?php
session_start();
if (!isset($_SESSION['logado']) || $_SESSION['nivel'] !== 'admin') {
   header("Location: dashboard.php");
   exit;
}
include "../includes/db.php";
include "../includes/functions.php";

$mensagem = '';
$erro = '';
$csrf_token = gerarTokenCSRF();

$editando = false;
$usuarioEditar = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
   $usuarioEditar = buscarUsuarioPorId((int)$_GET['editar']);
   if ($usuarioEditar) {
      $editando = true;
   }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   try {
      if (!verificarTokenCSRF($_POST['csrf_token'])) {
         throw new Exception('Token de segurança inválido');
      }

      if ($_POST['acao'] === 'criar') {
         $nome = sanitizarEntrada($_POST['nome']);
         $usuario = sanitizarEntrada($_POST['usuario']);
         $senha = $_POST['senha'];
         $nivel = $_POST['nivel'] === 'admin' ? 'admin' : 'operador';
         $codigo_canal = strtoupper(sanitizarEntrada($_POST['codigo_canal'] ?? 'TODOS'));
         if ($nivel === 'admin') {
            $codigo_canal = 'TODOS';
         }
         if (criarUsuario($nome, $usuario, $senha, $nivel, $codigo_canal)) {
            $mensagem = 'Usuário criado com sucesso!';
         } else {
            $erro = 'Erro ao criar usuário.';
         }
      } elseif ($_POST['acao'] === 'editar') {
         $id = (int)$_POST['id'];
         $nome = sanitizarEntrada($_POST['nome']);
         $usuario = sanitizarEntrada($_POST['usuario']);
         $nivel = $_POST['nivel'] === 'admin' ? 'admin' : 'operador';
         $senha = trim($_POST['senha']);

         $codigo_canal = strtoupper(sanitizarEntrada($_POST['codigo_canal'] ?? 'TODOS'));
         if ($nivel === 'admin') {
            $codigo_canal = 'TODOS';
         }

         if (atualizarUsuario($id, $nome, $usuario, $nivel, $codigo_canal, $senha ? $senha : null)) {
            $mensagem = 'Usuário atualizado com sucesso!';
         } else {
            $erro = 'Erro ao atualizar usuário.';
         }
      } elseif ($_POST['acao'] === 'status') {
         $id = (int)$_POST['id'];
         $novoStatus = (int)$_POST['novo_status'];

         if (alterarStatusUsuario($id, $novoStatus)) {
            $mensagem = $novoStatus ? 'Usuário reativado com sucesso!' : 'Usuário desativado com sucesso!';
         } else {
            $erro = 'Erro ao alterar status do usuário.';
         }
      }
   } catch (Exception $e) {
      $erro = $e->getMessage();
   }
}

$usuarios = listarUsuarios();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Gerenciar Usuários - NatterTV</title>
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
         <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
         <li><a href="upload.php"><i class="fas fa-cloud-upload-alt"></i> Upload</a></li>
         <li><a href="rss.php"><i class="fas fa-rss"></i> RSS Feeds</a></li>
         <li><a href="sidebar.php"><i class="fas fa-bullhorn"></i> Conteúdo Lateral</a></li>
         <li class="active"><a href="usuarios.php"><i class="fas fa-users"></i> Usuários</a></li>
         <li><a href="../tv/index.php" target="_blank"><i class="fas fa-external-link-alt"></i> Ver TV</a></li>
         <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
      </ul>
   </nav>

   <main class="main-content">
      <header class="topbar">
         <h1><i class="fas fa-users"></i> Gerenciar Usuários</h1>
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

         <div class="card">
            <div class="card-header">
               <h3><i class="fas fa-list"></i> Usuários Cadastrados</h3>
            </div>
            <div class="card-body">
               <div class="table-responsive">
                  <table class="content-table">
                     <thead>
                        <tr>
                           <th>Nome</th>
                           <th>Usuário</th>
                           <th>Nível</th>
                           <th>Canal</th>
                           <th>Status</th>
                           <th>Ações</th>
                        </tr>
                     </thead>
                     <tbody>
                        <?php foreach ($usuarios as $u): ?>
                           <tr>
                              <td><?php echo htmlspecialchars($u['nome']); ?></td>
                              <td><?php echo htmlspecialchars($u['usuario']); ?></td>
                              <td><?php echo htmlspecialchars(ucfirst($u['nivel'])); ?></td>
                              <td><?php echo htmlspecialchars($u['codigo_canal']); ?></td>
                              <td><?php echo $u['ativo'] ? 'Ativo' : 'Inativo'; ?></td>
                              <td>
                                 <a href="usuarios.php?editar=<?php echo $u['id']; ?>" class="btn btn-sm"><i class="fas fa-edit"></i> Editar</a>
                                 <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="acao" value="status">
                                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                    <input type="hidden" name="novo_status" value="<?php echo $u['ativo'] ? 0 : 1; ?>">
                                    <button type="submit" class="btn btn-sm <?php echo $u['ativo'] ? 'btn-danger' : 'btn-success'; ?>">
                                       <i class="fas fa-<?php echo $u['ativo'] ? 'ban' : 'check'; ?>"></i> <?php echo $u['ativo'] ? 'Desativar' : 'Ativar'; ?>
                                    </button>
                                 </form>
                              </td>
                           </tr>
                        <?php endforeach; ?>
                     </tbody>
                  </table>
               </div>
            </div>
         </div>

         <div class="card">
            <div class="card-header">
               <h3><i class="fas <?php echo $editando ? 'fa-user-edit' : 'fa-user-plus'; ?>"></i> <?php echo $editando ? 'Editar Usuário' : 'Novo Usuário'; ?></h3>
            </div>
            <div class="card-body">
               <form method="POST">
                  <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                  <?php if ($editando): ?>
                     <input type="hidden" name="acao" value="editar">
                     <input type="hidden" name="id" value="<?php echo $usuarioEditar['id']; ?>">
                  <?php else: ?>
                     <input type="hidden" name="acao" value="criar">
                  <?php endif; ?>

                  <div class="form-group">
                     <label for="nome"><i class="fas fa-id-card"></i> Nome</label>
                     <input type="text" name="nome" id="nome" required value="<?php echo $usuarioEditar['nome'] ?? ''; ?>">
                  </div>

                  <div class="form-group">
                     <label for="usuario"><i class="fas fa-user"></i> Usuário</label>
                     <input type="text" name="usuario" id="usuario" required value="<?php echo $usuarioEditar['usuario'] ?? ''; ?>">
                  </div>

                  <div class="form-group">
                     <label for="senha"><i class="fas fa-lock"></i> Senha <?php echo $editando ? '(deixe em branco para manter)' : ''; ?></label>
                     <input type="password" name="senha" id="senha" <?php echo $editando ? '' : 'required'; ?>>
                  </div>

                  <div class="form-group">
                     <label for="nivel"><i class="fas fa-user-shield"></i> Nível</label>
                     <select name="nivel" id="nivel">
                        <option value="operador" <?php echo (isset($usuarioEditar['nivel']) && $usuarioEditar['nivel'] === 'operador') ? 'selected' : ''; ?>>Operador</option>
                        <option value="admin" <?php echo (isset($usuarioEditar['nivel']) && $usuarioEditar['nivel'] === 'admin') ? 'selected' : ''; ?>>Administrador</option>
                     </select>
                  </div>
                  <div class="form-group">
                     <label for="codigo_canal"><i class="fas fa-tv"></i> Canal</label>
                     <input type="text" name="codigo_canal" id="codigo_canal" maxlength="10" required pattern="[A-Za-z0-9]{1,10}" value="<?php echo $usuarioEditar['codigo_canal'] ?? 'TODOS'; ?>">
                  </div>
                  <button type="submit" class="btn btn-primary">
                     <i class="fas fa-save"></i> <?php echo $editando ? 'Salvar Alterações' : 'Criar Usuário'; ?>
                  </button>
                  <?php if ($editando): ?>
                     <a href="usuarios.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                     </a>
                  <?php endif; ?>
               </form>
            </div>
         </div>
      </div>
   </main>
   <script>
      document.addEventListener('DOMContentLoaded', function() {
         const canalInput = document.getElementById('codigo_canal');
         if (canalInput) {
            canalInput.addEventListener('input', function(e) {
               this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            });
         }
      });
   </script>
</body>

</html>