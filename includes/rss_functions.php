<?php
function atualizarFeedRSS($feed_id)
{
   global $conn;

   try {
      $stmt = $conn->prepare("SELECT * FROM feeds_rss WHERE id = ? AND ativo = 1");
      $stmt->bind_param("i", $feed_id);
      $stmt->execute();
      $result = $stmt->get_result();

      if (!$feed = $result->fetch_assoc()) {
         throw new Exception("Feed não encontrado");
      }
      $stmt->close();

      echo "Atualizando feed: " . $feed['nome'] . " - URL: " . $feed['url_feed'] . "\n";
      $context = stream_context_create([
         'http' => [
            'timeout' => 30,
            'user_agent' => 'NatterTV RSS Reader/1.0',
            'method' => 'GET',
            'header' => [
               'Accept: application/rss+xml, application/xml, text/xml, application/atom+xml',
               'Cache-Control: no-cache'
            ]
         ]
      ]);
      $rss_content = @file_get_contents($feed['url_feed'], false, $context);

      if ($rss_content === false) {
         $error = error_get_last();
         throw new Exception("Erro ao acessar feed RSS: " . ($error['message'] ?? 'Conexão falhou'));
      }

      if (empty($rss_content)) {
         throw new Exception("Feed retornou conteúdo vazio");
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
      $itens_processados = 0;
      $max_items = 50;

      if (isset($xml->channel)) {
         $items = $xml->channel->item;
         echo "Tipo: RSS 2.0 - Items encontrados: " . count($items) . "\n";
      } elseif (isset($xml->entry)) {
         $items = $xml->entry;
         echo "Tipo: Atom - Entries encontrados: " . count($items) . "\n";
      } else {
         throw new Exception("Formato de feed não suportado");
      }

      foreach ($items as $item) {
         if ($itens_processados >= $max_items) break;

         try {
            if (isset($xml->channel)) {
               $titulo = (string)$item->title;
               $link = (string)$item->link;
               $descricao = (string)$item->description;
               $data_pub = (string)$item->pubDate;
               $guid = isset($item->guid) ? (string)$item->guid : $link;
            } else {
               $titulo = (string)$item->title;
               $link = isset($item->link['href']) ? (string)$item->link['href'] : (string)$item->id;
               $descricao = isset($item->summary) ? (string)$item->summary : (string)$item->content;
               $data_pub = isset($item->updated) ? (string)$item->updated : (string)$item->published;
               $guid = (string)$item->id;
            }
            $titulo = trim(html_entity_decode(strip_tags($titulo), ENT_QUOTES, 'UTF-8'));
            $descricao = trim(html_entity_decode(strip_tags($descricao), ENT_QUOTES, 'UTF-8'));

            if (empty($titulo)) {
               echo "Item sem título, pulando...\n";
               continue;
            }
            $data_mysql = null;
            if (!empty($data_pub)) {
               $timestamp = strtotime($data_pub);
               if ($timestamp !== false) {
                  $data_mysql = date('Y-m-d H:i:s', $timestamp);
               }
            }
            if (empty($guid)) {
               $guid = md5($titulo . $link);
            }

            echo "Processando: " . substr($titulo, 0, 50) . "...\n";
            $stmt = $conn->prepare("
                    INSERT INTO cache_rss (feed_id, titulo, link, descricao, data_publicacao, guid, data_cache)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                    titulo = VALUES(titulo),
                    link = VALUES(link),
                    descricao = VALUES(descricao),
                    data_publicacao = VALUES(data_publicacao),
                    data_cache = NOW()
                ");

            $stmt->bind_param("isssss", $feed_id, $titulo, $link, $descricao, $data_mysql, $guid);

            if ($stmt->execute()) {
               $itens_processados++;
            } else {
               echo "Erro ao inserir item: " . $stmt->error . "\n";
            }
            $stmt->close();
         } catch (Exception $e) {
            echo "Erro ao processar item: " . $e->getMessage() . "\n";
            continue;
         }
      }
      $stmt = $conn->prepare("UPDATE feeds_rss SET ultima_atualizacao = NOW() WHERE id = ?");
      $stmt->bind_param("i", $feed_id);
      $stmt->execute();
      $stmt->close();
      $stmt = $conn->prepare("
            DELETE FROM cache_rss 
            WHERE feed_id = ? 
            AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM cache_rss 
                    WHERE feed_id = ? 
                    ORDER BY data_publicacao DESC, data_cache DESC 
                    LIMIT 100
                ) as keep_items
            )
        ");
      $stmt->bind_param("ii", $feed_id, $feed_id);
      $stmt->execute();
      $stmt->close();

      if (function_exists('registrarLog')) {
         registrarLog('rss_update', "Feed RSS atualizado: {$feed['nome']} ({$itens_processados} itens)");
      }

      echo "Feed atualizado com sucesso: {$itens_processados} itens processados\n";
      return $itens_processados;
   } catch (Exception $e) {
      echo "ERRO: " . $e->getMessage() . "\n";
      if (function_exists('registrarLog')) {
         registrarLog('rss_error', "Erro ao atualizar feed RSS {$feed_id}: " . $e->getMessage());
      }
      throw $e;
   }
}

function atualizarTodosFeedsRSS()
{
   global $conn;

   $feeds_atualizados = 0;
   $total_itens = 0;

   $result = $conn->query("SELECT id FROM feeds_rss WHERE ativo = 1");

   while ($row = $result->fetch_assoc()) {
      try {
         $itens = atualizarFeedRSS($row['id']);
         $feeds_atualizados++;
         $total_itens += $itens;
      } catch (Exception $e) {
         continue;
      }
   }

   registrarLog('rss_update_all', "Atualização completa: {$feeds_atualizados} feeds, {$total_itens} itens");
   return ['feeds' => $feeds_atualizados, 'itens' => $total_itens];
}

function buscarItensRSSParaCanal($codigo_canal)
{
   global $conn;

   $sql = "
        SELECT c.*, f.nome as feed_nome, f.velocidade_scroll, f.cor_texto, f.cor_fundo, f.posicao
        FROM cache_rss c
        INNER JOIN feeds_rss f ON c.feed_id = f.id
        WHERE f.ativo = 1 AND (f.codigo_canal = ? OR f.codigo_canal = 'TODOS')
        ORDER BY c.data_publicacao DESC
        LIMIT 100
    ";

   $stmt = $conn->prepare($sql);
   $stmt->bind_param("s", $codigo_canal);
   $stmt->execute();
   $result = $stmt->get_result();

   $itens = [];
   while ($row = $result->fetch_assoc()) {
      $itens[] = $row;
   }

   $stmt->close();
   return $itens;
}

function formatarTextoRSS($titulo, $descricao = '', $max_length = 200)
{
   $texto = $titulo;

   if (!empty($descricao) && strlen($titulo) < 100) {
      $texto .= " - " . $descricao;
   }
   if (strlen($texto) > $max_length) {
      $texto = substr($texto, 0, $max_length - 3) . '...';
   }

   return $texto;
}

function agendarAtualizacaoRSS()
{
   try {
      atualizarTodosFeedsRSS();
      echo "RSS feeds atualizados com sucesso\n";
   } catch (Exception $e) {
      echo "Erro na atualização RSS: " . $e->getMessage() . "\n";
   }
}
