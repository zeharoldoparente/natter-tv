<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

include "../includes/db.php";
include "../includes/functions.php";

try {
   $codigo_canal = '';
   if (isset($_GET['canal']) && !empty($_GET['canal'])) {
      $codigo_canal = strtoupper(trim($_GET['canal']));
      if (!preg_match('/^[A-Z0-9]{1,10}$/', $codigo_canal)) {
         throw new Exception('Código de canal inválido');
      }
   }
   if (empty($codigo_canal)) {
      throw new Exception('Código de canal não especificado');
   }

   $stmt = $conn->prepare("
      SELECT * FROM conteudos 
      WHERE codigo_canal = ? AND ativo = 1 
      ORDER BY ordem_exibicao ASC, id ASC
   ");

   $stmt->bind_param("s", $codigo_canal);
   $stmt->execute();
   $result = $stmt->get_result();

   $conteudos = [];
   while ($row = $result->fetch_assoc()) {
      $conteudos[] = $row;
   }
   $stmt->close();

   if (empty($conteudos)) {
      echo json_encode([]);
      exit;
   }
   $conteudosTV = [];

   foreach ($conteudos as $conteudo) {
      $caminhoArquivo = "../uploads/" . $conteudo['arquivo'];

      if (file_exists($caminhoArquivo)) {
         $conteudosTV[] = [
            'id' => (int)$conteudo['id'],
            'arquivo' => $conteudo['arquivo'],
            'tipo' => $conteudo['tipo'],
            'duracao' => (int)$conteudo['duracao'],
            'codigo_canal' => $conteudo['codigo_canal'],
            'data_upload' => $conteudo['data_upload'],
            'ordem' => (int)$conteudo['ordem_exibicao']
         ];
      } else {
         $stmtUpdate = $conn->prepare("UPDATE conteudos SET ativo = 0 WHERE id = ?");
         $stmtUpdate->bind_param("i", $conteudo['id']);
         $stmtUpdate->execute();
         $stmtUpdate->close();

         registrarLog('file_missing', "Arquivo não encontrado: " . $conteudo['arquivo'] . " - Canal: " . $codigo_canal);
      }
   }

   registrarLog('api_request', "Conteúdos solicitados para canal: " . $codigo_canal . " (" . count($conteudosTV) . " arquivos)");

   echo json_encode($conteudosTV);
} catch (Exception $e) {
   http_response_code(400);
   echo json_encode([
      'error' => true,
      'message' => $e->getMessage()
   ]);

   if (function_exists('registrarLog')) {
      registrarLog('api_error', $e->getMessage());
   }
}
