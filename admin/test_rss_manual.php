<?php
// Crie este arquivo como test_rss_manual.php na pasta admin para testar

session_start();
include "../includes/db.php";
include "../includes/functions.php";
include "../includes/rss_functions.php";

echo "<h2>Teste Manual do Sistema RSS</h2>";

// URL do seu feed RSS
$feed_url = "https://rss.app/feeds/ZRuKQ29wRI1J1RnT.xml";

echo "<h3>1. Testando conexão com o feed:</h3>";
echo "URL: " . $feed_url . "<br>";

// Teste de conexão
$context = stream_context_create([
   'http' => [
      'timeout' => 30,
      'user_agent' => 'NatterTV RSS Reader/1.0'
   ]
]);

$content = @file_get_contents($feed_url, false, $context);

if ($content === false) {
   echo "<span style='color: red;'>❌ Erro ao acessar o feed</span><br>";
   $error = error_get_last();
   echo "Erro: " . ($error['message'] ?? 'Desconhecido') . "<br>";
} else {
   echo "<span style='color: green;'>✅ Feed acessado com sucesso</span><br>";
   echo "Tamanho do conteúdo: " . strlen($content) . " bytes<br>";

   // Teste de parse XML
   echo "<h3>2. Testando parse do XML:</h3>";
   $xml = simplexml_load_string($content);

   if ($xml === false) {
      echo "<span style='color: red;'>❌ Erro no parse XML</span><br>";
      $errors = libxml_get_errors();
      foreach ($errors as $error) {
         echo "Erro: " . trim($error->message) . "<br>";
      }
   } else {
      echo "<span style='color: green;'>✅ XML válido</span><br>";

      if (isset($xml->channel)) {
         echo "Tipo: RSS 2.0<br>";
         echo "Título do feed: " . (string)$xml->channel->title . "<br>";
         echo "Total de itens: " . count($xml->channel->item) . "<br>";

         echo "<h3>3. Primeiros 3 itens:</h3>";
         $count = 0;
         foreach ($xml->channel->item as $item) {
            if ($count >= 3) break;
            echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 5px;'>";
            echo "<strong>" . (string)$item->title . "</strong><br>";
            echo "Data: " . (string)$item->pubDate . "<br>";
            echo "Link: " . (string)$item->link . "<br>";
            echo "Descrição: " . substr(strip_tags((string)$item->description), 0, 100) . "...<br>";
            echo "</div>";
            $count++;
         }
      } elseif (isset($xml->entry)) {
         echo "Tipo: Atom<br>";
         echo "Título do feed: " . (string)$xml->title . "<br>";
         echo "Total de itens: " . count($xml->entry) . "<br>";
      }
   }
}

echo "<h3>4. Inserindo feed no banco (teste):</h3>";

try {
   // Verificar se já existe
   $check = $conn->prepare("SELECT id FROM feeds_rss WHERE url_feed = ?");
   $check->bind_param("s", $feed_url);
   $check->execute();
   $result = $check->get_result();

   if ($existing = $result->fetch_assoc()) {
      echo "Feed já existe com ID: " . $existing['id'] . "<br>";
      $feed_id = $existing['id'];
   } else {
      // Inserir novo feed
      $stmt = $conn->prepare("
            INSERT INTO feeds_rss (nome, url_feed, codigo_canal, velocidade_scroll, cor_texto, cor_fundo, posicao, usuario_upload) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

      $nome = "Teste RSS";
      $canal = "5"; // Seu canal
      $velocidade = 50;
      $cor_texto = "#FFFFFF";
      $cor_fundo = "#000000";
      $posicao = "rodape";
      $usuario = 1;

      $stmt->bind_param("sssisssi", $nome, $feed_url, $canal, $velocidade, $cor_texto, $cor_fundo, $posicao, $usuario);

      if ($stmt->execute()) {
         $feed_id = $conn->insert_id;
         echo "<span style='color: green;'>✅ Feed inserido com sucesso! ID: " . $feed_id . "</span><br>";
      } else {
         throw new Exception("Erro ao inserir: " . $stmt->error);
      }
      $stmt->close();
   }
   $check->close();

   echo "<h3>5. Atualizando feed:</h3>";
   $itens = atualizarFeedRSS($feed_id);
   echo "<span style='color: green;'>✅ Feed atualizado! Itens processados: " . $itens . "</span><br>";

   echo "<h3>6. Verificando itens no cache:</h3>";
   $cache_check = $conn->prepare("SELECT COUNT(*) as total FROM cache_rss WHERE feed_id = ?");
   $cache_check->bind_param("i", $feed_id);
   $cache_check->execute();
   $cache_result = $cache_check->get_result();
   $cache_count = $cache_result->fetch_assoc()['total'];
   $cache_check->close();

   echo "Itens no cache: " . $cache_count . "<br>";

   if ($cache_count > 0) {
      echo "<h3>7. Testando API get_rss.php:</h3>";
      echo "Acesse: <a href='get_rss.php?canal=5' target='_blank'>get_rss.php?canal=5</a><br>";
   }
} catch (Exception $e) {
   echo "<span style='color: red;'>❌ Erro: " . $e->getMessage() . "</span><br>";
}

echo "<hr>";
echo "<h3>Status das tabelas:</h3>";

// Verificar tabelas
$tables = ['feeds_rss', 'cache_rss'];
foreach ($tables as $table) {
   $check = $conn->query("SHOW TABLES LIKE '$table'");
   if ($check->num_rows > 0) {
      echo "<span style='color: green;'>✅ Tabela $table existe</span><br>";

      $count = $conn->query("SELECT COUNT(*) as total FROM $table")->fetch_assoc()['total'];
      echo "   Registros: $count<br>";
   } else {
      echo "<span style='color: red;'>❌ Tabela $table não existe</span><br>";
   }
}
