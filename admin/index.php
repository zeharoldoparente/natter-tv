<?php
session_start();
include "../db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
   $usuario = $_POST['usuario'];
   $senha = md5($_POST['senha']);

   $sql = "SELECT * FROM usuarios WHERE usuario='$usuario' AND senha='$senha'";
   $res = $conn->query($sql);

   if ($res->num_rows > 0) {
      $_SESSION['logado'] = true;
      header("Location: dashboard.php");
      exit;
   } else {
      $erro = "Usuário ou senha inválidos";
   }
}
?>
<!DOCTYPE html>
<html>

<head>
   <meta charset="UTF-8">
   <title>Login - TV Corporativa</title>
   <link rel="stylesheet" href="style.css">
</head>

<body>
   <div class="login-box">
      <h1>TV Corporativa</h1>
      <?php if (!empty($erro)) echo "<p style='color:red;'>$erro</p>"; ?>
      <form method="POST">
         <input type="text" name="usuario" placeholder="Usuário" required><br><br>
         <input type="password" name="senha" placeholder="Senha" required><br><br>
         <button type="submit">Entrar</button>
      </form>
   </div>
</body>

</html>