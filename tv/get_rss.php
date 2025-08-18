<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

include "../includes/db.php";
include "../includes/functions.php";

// Função para limpar texto RSS de caracteres especiais
function limparTextoRSS($texto)
{
   // Remove caracteres especiais comuns em RSS
   $texto = str_replace(['•', '●', '◦', '▪', '▫', '‣'], '', $texto);

   // Remove múltiplos espaços e quebras de linha
   $texto = preg_replace('/\s+/', ' ', $texto);

   // Remove caracteres de controle
   $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $texto);

   // Limpa HTML entities
   $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');

   // Remove tags HTML restantes
   $texto = strip_tags($texto);

   return trim($texto);
}

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

   // Debug - verificar se existem feeds
   $debug_stmt = $conn->prepare("SELECT COUNT(*) as total FROM feeds_rss WHERE ativo = 1");
   $debug_stmt->execute();
   $debug_result = $debug_stmt->get_result();
   $total_feeds = $debug_result->fetch_assoc()['total'];
   $debug_stmt->close();

   error_log("Total feeds ativos: " . $total_feeds);

   // Buscar feeds para o canal
   $sql = "
        SELECT f.*, COUNT(c.id) as total_itens
        FROM feeds_rss f
        LEFT JOIN cache_rss c ON f.id = c.feed_id
        WHERE f.ativo = 1 AND (f.codigo_canal = ? OR f.codigo_canal = 'TODOS')
        GROUP BY f.id
    ";

   $stmt = $conn->prepare($sql);
   $stmt->bind_param("s", $codigo_canal);
   $stmt->execute();
   $feeds_result = $stmt->get_result();

   $feeds_encontrados = [];
   while ($row = $feeds_result->fetch_assoc()) {
      $feeds_encontrados[] = $row;
   }
   $stmt->close();

   error_log("Feeds encontrados para canal $codigo_canal: " . count($feeds_encontrados));

   // Buscar itens RSS para o canal
   $sql_itens = "
        SELECT c.*, f.nome as feed_nome, f.velocidade_scroll, f.cor_texto, f.cor_fundo, f.posicao
        FROM cache_rss c
        INNER JOIN feeds_rss f ON c.feed_id = f.id
        WHERE f.ativo = 1 AND (f.codigo_canal = ? OR f.codigo_canal = 'TODOS')
        ORDER BY c.data_publicacao DESC, c.data_cache DESC
        LIMIT 50
    ";

   $stmt = $conn->prepare($sql_itens);
   $stmt->bind_param("s", $codigo_canal);
   $stmt->execute();
   $result = $stmt->get_result();

   $rss_data = [];
   while ($row = $result->fetch_assoc()) {
      // Limpar e formatar texto
      $titulo_limpo = limparTextoRSS($row['titulo']);
      $descricao_limpa = limparTextoRSS($row['descricao']);

      // Formatar texto para exibição
      $texto_formatado = $titulo_limpo;
      if (!empty($descricao_limpa) && strlen($titulo_limpo) < 100) {
         $texto_formatado .= " - " . $descricao_limpa;
      }

      // Limitar tamanho do texto
      if (strlen($texto_formatado) > 300) {
         $texto_formatado = substr($texto_formatado, 0, 297) . '...';
      }

      $rss_data[] = [
         'id' => (int)$row['id'],
         'feed_nome' => limparTextoRSS($row['feed_nome']),
         'titulo' => $titulo_limpo,
         'texto' => $texto_formatado,
         'link' => $row['link'],
         'data_publicacao' => $row['data_publicacao'],
         'configuracao' => [
            'velocidade_scroll' => (int)$row['velocidade_scroll'],
            'cor_texto' => $row['cor_texto'],
            'cor_fundo' => $row['cor_fundo'],
            'posicao' => $row['posicao']
         ]
      ];
   }
   $stmt->close();

   error_log("Itens RSS encontrados: " . count($rss_data));

   // Agrupar por posição
   $response = [
      'canal' => $codigo_canal,
      'total_itens' => count($rss_data),
      'rodape' => [],
      'topo' => [],
      'timestamp' => time(),
      'debug' => [
         'total_feeds_sistema' => $total_feeds,
         'feeds_canal' => count($feeds_encontrados),
         'itens_encontrados' => count($rss_data)
      ]
   ];

   foreach ($rss_data as $item) {
      $posicao = $item['configuracao']['posicao'];
      if ($posicao === 'rodape' || $posicao === 'topo') {
         $response[$posicao][] = $item;
      }
   }

   if (function_exists('registrarLog')) {
      registrarLog('rss_api_request', "RSS solicitado para canal: " . $codigo_canal . " (" . count($rss_data) . " itens)");
   }

   echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
   http_response_code(400);
   echo json_encode([
      'error' => true,
      'message' => $e->getMessage(),
      'canal' => $codigo_canal ?? '',
      'rodape' => [],
      'topo' => [],
      'debug' => [
         'error_details' => $e->getTraceAsString()
      ]
   ], JSON_UNESCAPED_UNICODE);

   if (function_exists('registrarLog')) {
      registrarLog('rss_api_error', $e->getMessage());
   }
}
