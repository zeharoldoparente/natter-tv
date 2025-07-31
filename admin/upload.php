<?php
session_start();
if (!isset($_SESSION['logado'])) {
   header("Location: index.php");
   exit;
}
include "../db.php";

$duracao = (int)$_POST['duracao'];
$arquivo = $_FILES['arquivo'];

if ($arquivo['error'] == 0) {
   $ext = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
   $novoNome = time() . "." . $ext;
   move_uploaded_file($arquivo['tmp_name'], "../uploads/" . $novoNome);

   $tipo = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'imagem' : 'video';

   $stmt = $conn->prepare("INSERT INTO conteudos (arquivo, tipo, duracao) VALUES (?, ?, ?)");
   $stmt->bind_param("ssi", $novoNome, $tipo, $duracao);
   $stmt->execute();
}
header("Location: dashboard.php");
