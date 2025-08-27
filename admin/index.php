<?php
session_start();
include "../includes/db.php";
include "../includes/functions.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
   $usuario = $_POST['usuario'];
   $senha = $_POST['senha'];

   if (fazerLogin($usuario, $senha)) {
      header("Location: dashboard.php");
      exit;
   } else {
      $erro = "Usuário ou senha inválidos";
   }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Login - NatterTV</title>
   <link rel="stylesheet" href="../assets/css/base.css">
   <link rel="stylesheet" href="../assets/css/login-style.css">
   <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
   <link rel="shortcut icon" href="../assets/images/favicon.png" type="image/x-icon">
</head>

<body>
   <div class="floating-elements"></div>

   <div class="login-container">
      <div class="login-header">
         <img src="../assets/images/TV Corporativa - Natter.png" alt="NatterTV">
         <p>Sistema de Gerenciamento de Conteúdo</p>
      </div>

      <?php if (!empty($erro)): ?>
         <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $erro; ?>
         </div>
      <?php endif; ?>

      <form method="POST" class="login-form" id="loginForm">
         <div class="input-group">
            <input type="text" name="usuario" placeholder="Nome de usuário" required>
            <i class="fas fa-user"></i>
         </div>

         <div class="input-group">
            <input type="password" name="senha" placeholder="Senha" required>
            <i class="fas fa-lock"></i>
         </div>

         <button type="submit" class="login-btn" id="loginBtn">
            <i class="fas fa-sign-in-alt"></i>
            Entrar
         </button>
      </form>

      <div class="login-footer">
         <p>&copy; 2024 NatterTV - Todos os direitos reservados</p>
         <div class="version">Versão 2.0</div>
      </div>
   </div>

   <script>
      document.addEventListener('DOMContentLoaded', function() {
         const form = document.getElementById('loginForm');
         const loginBtn = document.getElementById('loginBtn');
         const inputs = document.querySelectorAll('input');

         inputs.forEach(input => {
            input.addEventListener('focus', function() {
               this.parentElement.style.transform = 'translateY(-2px)';
            });

            input.addEventListener('blur', function() {
               this.parentElement.style.transform = 'translateY(0)';
            });

            input.addEventListener('keypress', function(e) {
               if (e.key === 'Enter') {
                  form.submit();
               }
            });
         });

         form.addEventListener('submit', function() {
            loginBtn.classList.add('loading');
            loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Entrando...';
            loginBtn.disabled = true;
         });
      });
   </script>
</body>

</html>