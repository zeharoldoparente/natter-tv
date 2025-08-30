<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

include "../includes/db.php";
include "../includes/functions.php";

try {
   if (!isset($_GET['url']) || empty($_GET['url'])) {
      throw new Exception('URL do feed não fornecida');
   }

   $url = filter_var($_GET['url'], FILTER_VALIDATE_URL);
   if (!$url) {
      throw new Exception('URL inválida');
   }

   $context = stream_context_create([
      'http' => [
         'timeout' => 15,
         'user_agent' => 'NatterTV RSS Tester/1.0',
         'method' => 'GET',
         'header' => [
            'Accept: application/rss+xml, application/xml, text/xml',
            'Cache-Control: no-cache'
         ]
      ]
   ]);

   $rss_content = @file_get_contents($url, false, $context);

   if ($rss_content === false) {
      $error = error_get_last();
      throw new Exception('Erro ao acessar feed: ' . ($error['message'] ?? 'Conexão falhou'));
   }

   if (empty($rss_content)) {
      throw new Exception('Feed retornou conteúdo vazio');
   }

   $prev_setting = libxml_use_internal_errors(true);
   libxml_clear_errors();

   $xml = simplexml_load_string($rss_content);

   if ($xml === false) {
      $errors = libxml_get_errors();
      $error_message = "Erro no parse XML";
      if (!empty($errors)) {
         $error_message .= ": " . trim($errors[0]->message);
      }
      throw new Exception($error_message);
   }

   libxml_use_internal_errors($prev_setting);

   $feed_info = [
      'tipo' => 'desconhecido',
      'titulo' => '',
      'descricao' => '',
      'total_itens' => 0,
      'itens_amostra' => []
   ];

   if (isset($xml->channel)) {
      $feed_info['tipo'] = 'RSS 2.0';
      $feed_info['titulo'] = (string)$xml->channel->title;
      $feed_info['descricao'] = (string)$xml->channel->description;

      $items = $xml->channel->item;
      $feed_info['total_itens'] = count($items);

      $contador = 0;
      foreach ($items as $item) {
         if ($contador >= 3) break;

         $feed_info['itens_amostra'][] = [
            'titulo' => (string)$item->title,
            'descricao' => substr(strip_tags((string)$item->description), 0, 100) . '...',
            'data' => (string)$item->pubDate,
            'link' => (string)$item->link
         ];
         $contador++;
      }
   } elseif (isset($xml->entry)) {
      $feed_info['tipo'] = 'Atom';
      $feed_info['titulo'] = (string)$xml->title;
      $feed_info['descricao'] = (string)$xml->subtitle;

      $items = $xml->entry;
      $feed_info['total_itens'] = count($items);

      $contador = 0;
      foreach ($items as $item) {
         if ($contador >= 3) break;

         $link = isset($item->link['href']) ? (string)$item->link['href'] : (string)$item->id;
         $descricao = isset($item->summary) ? (string)$item->summary : (string)$item->content;

         $feed_info['itens_amostra'][] = [
            'titulo' => (string)$item->title,
            'descricao' => substr(strip_tags($descricao), 0, 100) . '...',
            'data' => isset($item->updated) ? (string)$item->updated : (string)$item->published,
            'link' => $link
         ];
         $contador++;
      }
   } else {
      throw new Exception('Formato de feed não reconhecido (não é RSS nem Atom)');
   }

   if (empty($feed_info['titulo'])) {
      throw new Exception('Feed não possui título válido');
   }

   if ($feed_info['total_itens'] == 0) {
      throw new Exception('Feed não contém itens');
   }

   echo json_encode([
      'success' => true,
      'message' => 'Feed RSS válido e acessível',
      'feed_info' => $feed_info,
      'url_testada' => $url,
      'timestamp' => date('Y-m-d H:i:s'),
      'tamanho_conteudo' => strlen($rss_content),
      'encoding' => mb_detect_encoding($rss_content)
   ]);
} catch (Exception $e) {
   http_response_code(400);
   echo json_encode([
      'success' => false,
      'message' => $e->getMessage(),
      'url_testada' => $_GET['url'] ?? '',
      'timestamp' => date('Y-m-d H:i:s')
   ]);
}
