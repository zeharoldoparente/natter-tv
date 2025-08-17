<?php

// Definir que é execução via CLI
if (php_sapi_name() !== 'cli') {
   die('Este script deve ser executado via linha de comando');
}

// Incluir arquivos necessários
$base_path = dirname(__DIR__);
require_once $base_path . '/includes/db.php';
require_once $base_path . '/includes/functions.php';
require_once $base_path . '/includes/rss_functions.php';

echo "[" . date('Y-m-d H:i:s') . "] Iniciando atualização automática de RSS\n";

try {
   // Verificar se há feeds para atualizar
   $stmt = $conn->prepare("SELECT COUNT(*) as total FROM feeds_rss WHERE ativo = 1");
   $stmt->execute();
   $result = $stmt->get_result();
   $total_feeds = $result->fetch_assoc()['total'];
   $stmt->close();

   if ($total_feeds == 0) {
      echo "[" . date('Y-m-d H:i:s') . "] Nenhum feed RSS ativo encontrado\n";
      exit(0);
   }

   echo "[" . date('Y-m-d H:i:s') . "] Encontrados {$total_feeds} feeds ativos\n";

   // Atualizar todos os feeds
   $resultado = atualizarTodosFeedsRSS();

   echo "[" . date('Y-m-d H:i:s') . "] Atualização concluída:\n";
   echo "  - Feeds atualizados: {$resultado['feeds']}\n";
   echo "  - Total de itens: {$resultado['itens']}\n";

   // Limpar cache antigo
   $cache_duration = buscarConfiguracao('rss_cache_duration', 3600);
   $stmt = $conn->prepare("
        DELETE FROM cache_rss 
        WHERE data_cache < DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
   $stmt->bind_param("i", $cache_duration);
   $stmt->execute();
   $itens_removidos = $stmt->affected_rows;
   $stmt->close();

   if ($itens_removidos > 0) {
      echo "  - Itens de cache removidos: {$itens_removidos}\n";
   }

   // Sinalizar atualização para as TVs
   sinalizarAtualizacaoTV();
   echo "  - Sinal de atualização enviado para as TVs\n";

   echo "[" . date('Y-m-d H:i:s') . "] Processo finalizado com sucesso\n";
} catch (Exception $e) {
   echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
   exit(1);
}

// Estatísticas finais
try {
   $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT f.id) as feeds_ativos,
            COUNT(c.id) as total_itens_cache,
            MAX(c.data_cache) as ultima_atualizacao
        FROM feeds_rss f
        LEFT JOIN cache_rss c ON f.id = c.feed_id
        WHERE f.ativo = 1
    ");
   $stmt->execute();
   $stats = $stmt->get_result()->fetch_assoc();
   $stmt->close();

   echo "\n=== ESTATÍSTICAS ===\n";
   echo "Feeds ativos: {$stats['feeds_ativos']}\n";
   echo "Itens em cache: {$stats['total_itens_cache']}\n";
   echo "Última atualização: {$stats['ultima_atualizacao']}\n";
   echo "==================\n\n";
} catch (Exception $e) {
   echo "Erro ao obter estatísticas: " . $e->getMessage() . "\n";
}
