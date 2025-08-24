<?php

session_start();
if (!isset($_SESSION['logado']) || $_SESSION['nivel'] !== 'admin') {
   die('Acesso negado. Apenas administradores podem executar este script.');
}

include "../includes/db.php";

try {
   echo "<h2>Instalando Sistema de Gerenciamento de Conteúdo Lateral</h2>";
   echo "<p>1. Criando tabela conteudos_laterais...</p>";

   $sqlConteudosLaterais = "
        CREATE TABLE IF NOT EXISTS conteudos_laterais (
            id INT PRIMARY KEY AUTO_INCREMENT,
            arquivo VARCHAR(255) NOT NULL,
            nome_original VARCHAR(255) NOT NULL,
            tipo ENUM('imagem','video') NOT NULL,
            tamanho BIGINT DEFAULT 0,
            dimensoes VARCHAR(20),
            ativo TINYINT(1) DEFAULT 0,
            data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_ativacao TIMESTAMP NULL,
            data_desativacao TIMESTAMP NULL,
            usuario_upload INT,
            descricao TEXT,
            
            INDEX idx_ativo (ativo),
            INDEX idx_data_upload (data_upload),
            
            FOREIGN KEY (usuario_upload) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ";

   if ($conn->query($sqlConteudosLaterais)) {
      echo "<span style='color: green;'>✓ Tabela conteudos_laterais criada com sucesso!</span><br>";
   } else {
      throw new Exception("Erro ao criar tabela conteudos_laterais: " . $conn->error);
   }
   echo "<p>2. Adicionando configurações...</p>";

   $configSidebar = [
      ['sidebar_enabled', '1', 'Ativar exibição de conteúdo lateral', 'boolean'],
      ['sidebar_auto_activate', '1', 'Ativar automaticamente novos uploads de sidebar', 'boolean'],
      ['sidebar_max_files', '10', 'Máximo de arquivos de sidebar a manter no histórico', 'number']
   ];

   foreach ($configSidebar as $config) {
      $checkConfig = $conn->prepare("SELECT COUNT(*) as count FROM configuracoes WHERE chave = ?");
      $checkConfig->bind_param("s", $config[0]);
      $checkConfig->execute();
      $exists = $checkConfig->get_result()->fetch_assoc()['count'] > 0;
      $checkConfig->close();

      if (!$exists) {
         $insertConfig = $conn->prepare("
                INSERT INTO configuracoes (chave, valor, descricao, tipo) 
                VALUES (?, ?, ?, ?)
            ");
         $insertConfig->bind_param("ssss", $config[0], $config[1], $config[2], $config[3]);
         $insertConfig->execute();
         $insertConfig->close();

         echo "<span style='color: green;'>✓ Configuração '{$config[0]}' adicionada!</span><br>";
      } else {
         echo "<span style='color: orange;'>⚠ Configuração '{$config[0]}' já existe.</span><br>";
      }
   }
   echo "<p>3. Verificando arquivos de sidebar existentes...</p>";

   $sidebarPath = "../sidebar/";
   $allowed = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv'];

   if (is_dir($sidebarPath)) {
      $files = array_filter(scandir($sidebarPath), function ($f) use ($allowed, $sidebarPath) {
         $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
         return !is_dir($sidebarPath . $f) && in_array($ext, $allowed);
      });

      if (!empty($files)) {
         echo "<p>Encontrados " . count($files) . " arquivo(s) de sidebar. Migrando...</p>";

         foreach ($files as $file) {
            try {
               $caminhoCompleto = $sidebarPath . $file;
               $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
               $tipo = in_array($ext, ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv']) ? 'video' : 'imagem';
               $tamanho = filesize($caminhoCompleto);
               $dimensoes = '';
               if ($tipo === 'imagem') {
                  $info = getimagesize($caminhoCompleto);
                  if ($info) {
                     $dimensoes = $info[0] . 'x' . $info[1];
                  }
               }
               $checkMigrated = $conn->prepare("SELECT COUNT(*) as count FROM conteudos_laterais WHERE arquivo = ?");
               $checkMigrated->bind_param("s", $file);
               $checkMigrated->execute();
               $already_migrated = $checkMigrated->get_result()->fetch_assoc()['count'] > 0;
               $checkMigrated->close();

               if (!$already_migrated) {
                  $stmt = $conn->prepare("
                            INSERT INTO conteudos_laterais 
                            (arquivo, nome_original, tipo, tamanho, dimensoes, ativo, data_ativacao, descricao) 
                            VALUES (?, ?, ?, ?, ?, 1, NOW(), ?)
                        ");

                  $nomeOriginal = 'Migrado: ' . $file;
                  $descricao = 'Conteúdo migrado automaticamente do sistema anterior em ' . date('d/m/Y H:i:s');

                  $stmt->bind_param("sssiss", $file, $nomeOriginal, $tipo, $tamanho, $dimensoes, $descricao);

                  if ($stmt->execute()) {
                     echo "<span style='color: green;'>✓ Arquivo migrado: {$file}</span><br>";
                  } else {
                     echo "<span style='color: red;'>✗ Erro ao migrar: {$file}</span><br>";
                  }
                  $stmt->close();
               } else {
                  echo "<span style='color: orange;'>⚠ Já migrado: {$file}</span><br>";
               }
            } catch (Exception $e) {
               echo "<span style='color: red;'>✗ Erro ao migrar {$file}: " . $e->getMessage() . "</span><br>";
            }
         }
      } else {
         echo "<span style='color: blue;'>ℹ Nenhum arquivo de sidebar encontrado para migrar.</span><br>";
      }
   } else {
      echo "<span style='color: orange;'>⚠ Pasta de sidebar não encontrada.</span><br>";
   }
   echo "<p>4. Verificando funções necessárias...</p>";

   $required_functions = [
      'processarUploadLateral',
      'ativarConteudoLateral',
      'excluirConteudoLateral',
      'buscarConteudosLaterais',
      'buscarConteudoLateralAtivo'
   ];

   $missing_functions = [];
   foreach ($required_functions as $func) {
      if (!function_exists($func)) {
         $missing_functions[] = $func;
      }
   }

   if (empty($missing_functions)) {
      echo "<span style='color: green;'>✓ Todas as funções necessárias estão disponíveis!</span><br>";
   } else {
      echo "<span style='color: red;'>✗ Funções em falta:</span><br>";
      foreach ($missing_functions as $func) {
         echo "<span style='color: red;'>  - {$func}</span><br>";
      }
      echo "<p><strong>AÇÃO NECESSÁRIA:</strong> Adicione as funções fornecidas ao arquivo functions.php</p>";
   }

   echo "<hr>";
   echo "<h3 style='color: green;'>✅ Instalação concluída!</h3>";
   echo "<p><strong>Próximos passos:</strong></p>";
   echo "<ol>";
   echo "<li>Verifique se o arquivo <code>sidebar.php</code> foi criado na pasta admin/</li>";
   echo "<li>Atualize o arquivo <code>sidebar_content.php</code> com a nova versão</li>";
   echo "<li>Adicione as funções ao <code>functions.php</code> se ainda não foram adicionadas</li>";
   echo "<li>Atualize o menu das páginas administrativas para incluir o link do 'Conteúdo Lateral'</li>";
   echo "<li>Acesse <a href='sidebar.php'>sidebar.php</a> para começar a gerenciar o conteúdo lateral</li>";
   echo "</ol>";

   echo "<p><a href='dashboard.php' style='background: #27ae60; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Voltar ao Dashboard</a></p>";
} catch (Exception $e) {
   echo "<h3 style='color: red;'>❌ Erro na instalação:</h3>";
   echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
   echo "<p><a href='dashboard.php'>← Voltar ao Dashboard</a></p>";
}
