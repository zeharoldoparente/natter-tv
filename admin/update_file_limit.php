<?php

include "../includes/db.php";
include "../includes/functions.php";

try {
   echo "<h2>Atualizando Limite de Upload para 80MB</h2>";

   echo "<h3>1. Configurações PHP Atuais:</h3>";
   echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
   echo "post_max_size: " . ini_get('post_max_size') . "<br>";
   echo "memory_limit: " . ini_get('memory_limit') . "<br>";
   echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";

   echo "<h3>2. Atualizando Banco de Dados:</h3>";

   $novo_limite = 83886080;

   $stmt = $conn->prepare("
UPDATE configuracoes
SET valor = ?
WHERE chave = 'max_file_size'
");
   $stmt->bind_param("s", $novo_limite);

   if ($stmt->execute()) {
      echo "<span style='color: green;'>✓ Limite atualizado no banco de dados</span><br>";
   } else {
      echo "<span style='color: red;'>✗ Erro ao atualizar banco</span><br>";
   }
   $stmt->close();

   echo "<h3>3. Verificação da Constante:</h3>";
   echo "MAX_FILE_SIZE atual: " . formatFileSize(MAX_FILE_SIZE) . "<br>";

   if (MAX_FILE_SIZE == 83886080) {
      echo "<span style='color: green;'>✓ Constante MAX_FILE_SIZE atualizada corretamente</span><br>";
   } else {
      echo "<span style='color: orange;'>⚠ Você precisa alterar a constante MAX_FILE_SIZE em db.php</span><br>";
   }

   echo "<h3>4. Verificação de Espaço:</h3>";
   $espaco_livre = disk_free_space(__DIR__);
   $espaco_total = disk_total_space(__DIR__);

   echo "Espaço livre: " . formatFileSize($espaco_livre) . "<br>";
   echo "Espaço total: " . formatFileSize($espaco_total) . "<br>";

   if ($espaco_livre > (500 * 1024 * 1024)) { // 500MB livres
      echo "<span style='color: green;'>✓ Espaço em disco adequado</span><br>";
   } else {
      echo "<span style='color: red;'>⚠ Pouco espaço em disco disponível</span><br>";
   }

   echo "<h3>5. Recomendações:</h3>";
   echo "<ul>";
   echo "<li>Reinicie o servidor web após alterar o php.ini</li>";
   echo "<li>Teste com arquivos pequenos primeiro</li>";
   echo "<li>Monitore o uso de memória e CPU</li>";
   echo "<li>Configure um sistema de limpeza automática de arquivos antigos</li>";
   echo "</ul>";

   echo "
<hr>";
   echo "<h3 style='color: green;'>✅ Atualização Concluída!</h3>";
   echo "<p><a href='upload.php'>Testar Upload</a> | <a href='dashboard.php'>Voltar ao Dashboard</a></p>";
} catch (Exception $e) {
   echo "<h3 style='color: red;'>❌ Erro:</h3>";
   echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
