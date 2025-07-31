<?php

/**
 * API para buscar conteúdos - usado pela TV para verificar atualizações
 * TV Corporativa - Sistema profissionalizado
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Incluir arquivos necessários
include "../includes/db.php";
include "../includes/functions.php";

try {
   // Buscar conteúdos ativos
   $conteudos = buscarConteudos(true);

   // Se não há conteúdos, retornar array vazio
   if (empty($conteudos)) {
      echo json_encode([]);
      exit;
   }

   // Processar conteúdos para a TV
   $conteudosTV = [];

   foreach ($conteudos as $conteudo) {
      // Verificar se arquivo ainda existe
      $caminhoArquivo = "../uploads/" . $conteudo['arquivo'];

      if (file_exists($caminhoArquivo)) {
         $conteudosTV[] = [
            'id' => (int)$conteudo['id'],
            'arquivo' => $conteudo['arquivo'],
            'tipo' => $conteudo['tipo'],
            'duracao' => (int)$conteudo['duracao'],
            'data_upload' => $conteudo['data_upload'],
            'ordem' => (int)$conteudo['ordem_exibicao']
         ];
      } else {
         // Arquivo não existe, marcar como inativo no banco
         $stmt = $conn->prepare("UPDATE conteudos SET ativo = 0 WHERE id = ?");
         $stmt->bind_param("i", $conteudo['id']);
         $stmt->execute();
         $stmt->close();

         registrarLog('file_missing', "Arquivo não encontrado: " . $conteudo['arquivo']);
      }
   }

   // Retornar conteúdos em JSON
   echo json_encode($conteudosTV);
} catch (Exception $e) {
   // Em caso de erro, retornar erro em JSON
   http_response_code(500);
   echo json_encode([
      'error' => true,
      'message' => 'Erro interno do servidor'
   ]);

   // Log do erro (se possível)
   if (function_exists('registrarLog')) {
      registrarLog('api_error', $e->getMessage());
   }
}
