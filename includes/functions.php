<?php
function iniciarSessao()
{
   if (session_status() === PHP_SESSION_NONE) {
      ini_set('session.cookie_httponly', 1);
      ini_set('session.use_only_cookies', 1);
      ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));

      session_start();
   }
}
function verificarLogin()
{
   iniciarSessao();

   if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
      header("Location: index.php");
      exit;
   }
   if (isset($_SESSION['ultimo_acesso'])) {
      $timeout = 2 * 60 * 60;

      if (time() - $_SESSION['ultimo_acesso'] > $timeout) {
         session_destroy();
         header("Location: index.php?timeout=1");
         exit;
      }
   }

   $_SESSION['ultimo_acesso'] = time();
}
function fazerLogin($usuario, $senha)
{
   global $conn;
   $stmt = $conn->prepare("SELECT id, nome, usuario, senha, nivel, ativo FROM usuarios WHERE usuario = ? AND ativo = 1");
   $stmt->bind_param("s", $usuario);
   $stmt->execute();
   $result = $stmt->get_result();

   if ($row = $result->fetch_assoc()) {
      if (md5($senha) === $row['senha']) {
         iniciarSessao();

         $_SESSION['logado'] = true;
         $_SESSION['usuario_id'] = $row['id'];
         $_SESSION['nome'] = $row['nome'];
         $_SESSION['usuario'] = $row['usuario'];
         $_SESSION['nivel'] = $row['nivel'];
         $_SESSION['ultimo_acesso'] = time();
         $updateLogin = $conn->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
         $updateLogin->bind_param("i", $row['id']);
         $updateLogin->execute();
         $updateLogin->close();
         registrarLog('login', "Usuário {$row['usuario']} fez login");

         $stmt->close();
         return true;
      }
   }

   $stmt->close();
   return false;
}
function fazerLogout()
{
   iniciarSessao();

   if (isset($_SESSION['usuario'])) {
      registrarLog('logout', "Usuário {$_SESSION['usuario']} fez logout");
   }

   session_destroy();
   header("Location: index.php");
   exit;
}
function processarUpload($arquivo, $duracao = 5, $codigo_canal = '0000')
{
   global $conn;

   if ($arquivo['error'] !== UPLOAD_ERR_OK) {
      throw new Exception('Erro no upload do arquivo: ' . $arquivo['error']);
   }
   if ($arquivo['size'] > MAX_FILE_SIZE) {
      throw new Exception('Arquivo muito grande. Máximo: ' . formatFileSize(MAX_FILE_SIZE));
   }
   $tipo = getFileType($arquivo['name']);
   if ($tipo === 'desconhecido') {
      throw new Exception('Tipo de arquivo não permitido');
   }

   // Validar e limpar código do canal
   $codigo_canal = strtoupper(trim($codigo_canal));
   if (empty($codigo_canal) || !preg_match('/^[A-Z0-9]{1,10}$/', $codigo_canal)) {
      $codigo_canal = '0000'; // Código padrão se inválido
   }

   $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
   $nomeArquivo = time() . '_' . uniqid() . '.' . $extensao;
   $caminhoCompleto = UPLOAD_PATH . $nomeArquivo;

   if (!move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
      throw new Exception('Erro ao salvar arquivo');
   }

   $dimensoes = '';
   if ($tipo === 'imagem') {
      $info = getimagesize($caminhoCompleto);
      if ($info) {
         $dimensoes = $info[0] . 'x' . $info[1];
      }
   }

   $usuarioId = $_SESSION['usuario_id'] ?? null;

   // MODIFICADO: Incluir código_canal na inserção
   $stmt = $conn->prepare("
        INSERT INTO conteudos 
        (arquivo, nome_original, tipo, codigo_canal, duracao, tamanho, dimensoes, usuario_upload) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

   $stmt->bind_param(
      "ssssisis",
      $nomeArquivo,
      $arquivo['name'],
      $tipo,
      $codigo_canal,
      $duracao,
      $arquivo['size'],
      $dimensoes,
      $usuarioId
   );

   if (!$stmt->execute()) {
      unlink($caminhoCompleto);
      throw new Exception('Erro ao salvar informações no banco');
   }

   $conteudoId = $conn->insert_id;
   $stmt->close();

   registrarLog('upload', "Upload do arquivo: {$arquivo['name']} ({$tipo}) - Canal: {$codigo_canal}");

   return $conteudoId;
}
function excluirConteudo($id)
{
   global $conn;
   $stmt = $conn->prepare("SELECT arquivo, nome_original FROM conteudos WHERE id = ?");
   $stmt->bind_param("i", $id);
   $stmt->execute();
   $result = $stmt->get_result();

   if ($row = $result->fetch_assoc()) {
      $arquivo = $row['arquivo'];
      $nomeOriginal = $row['nome_original'];
      $caminhoArquivo = UPLOAD_PATH . $arquivo;
      if (file_exists($caminhoArquivo)) {
         unlink($caminhoArquivo);
      }
      $deleteStmt = $conn->prepare("DELETE FROM conteudos WHERE id = ?");
      $deleteStmt->bind_param("i", $id);
      $deleteStmt->execute();
      $deleteStmt->close();
      registrarLog('delete', "Arquivo excluído: {$nomeOriginal}");

      $stmt->close();
      return true;
   }

   $stmt->close();
   return false;
}
function buscarConteudos($apenasAtivos = true)
{
   global $conn;

   $sql = "SELECT * FROM conteudos";
   if ($apenasAtivos) {
      $sql .= " WHERE ativo = 1";
   }
   $sql .= " ORDER BY ordem_exibicao ASC, id ASC";

   $result = $conn->query($sql);

   $conteudos = [];
   while ($row = $result->fetch_assoc()) {
      $conteudos[] = $row;
   }

   return $conteudos;
}
function sinalizarAtualizacaoTV()
{
   $arquivo = TEMP_PATH . 'tv_update.txt';
   file_put_contents($arquivo, time());

   registrarLog('tv_update', 'Sinal de atualização enviado para TVs');

   return true;
}
function registrarLog($acao, $detalhes = '')
{
   global $conn;

   $usuarioId = $_SESSION['usuario_id'] ?? null;
   $ip = $_SERVER['REMOTE_ADDR'] ?? '';
   $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

   $stmt = $conn->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?)
    ");

   $stmt->bind_param("issss", $usuarioId, $acao, $detalhes, $ip, $userAgent);
   $stmt->execute();
   $stmt->close();
}
function buscarConfiguracao($chave, $valorPadrao = null)
{
   global $conn;

   $stmt = $conn->prepare("SELECT valor, tipo FROM configuracoes WHERE chave = ?");
   $stmt->bind_param("s", $chave);
   $stmt->execute();
   $result = $stmt->get_result();

   if ($row = $result->fetch_assoc()) {
      $valor = $row['valor'];
      switch ($row['tipo']) {
         case 'number':
            return is_numeric($valor) ? (float)$valor : $valorPadrao;
         case 'boolean':
            return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
         case 'json':
            return json_decode($valor, true) ?: $valorPadrao;
         default:
            return $valor;
      }
   }

   $stmt->close();
   return $valorPadrao;
}
function salvarConfiguracao($chave, $valor, $tipo = 'string')
{
   global $conn;
   switch ($tipo) {
      case 'boolean':
         $valor = $valor ? '1' : '0';
         break;
      case 'json':
         $valor = json_encode($valor);
         break;
      default:
         $valor = (string)$valor;
   }

   $stmt = $conn->prepare("
        INSERT INTO configuracoes (chave, valor, tipo) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE valor = ?, tipo = ?
    ");

   $stmt->bind_param("sssss", $chave, $valor, $tipo, $valor, $tipo);
   $stmt->execute();
   $stmt->close();

   return true;
}
function limparLogsAntigos()
{
   global $conn;

   $sql = "DELETE FROM logs_sistema WHERE data_log < DATE_SUB(NOW(), INTERVAL 30 DAY)";
   $result = $conn->query($sql);

   if ($result) {
      $deletados = $conn->affected_rows;
      registrarLog('maintenance', "Limpeza de logs: {$deletados} registros removidos");
      return $deletados;
   }

   return 0;
}
function sanitizarEntrada($dado, $tipo = 'string')
{
   switch ($tipo) {
      case 'email':
         return filter_var($dado, FILTER_SANITIZE_EMAIL);
      case 'int':
         return filter_var($dado, FILTER_SANITIZE_NUMBER_INT);
      case 'float':
         return filter_var($dado, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
      case 'url':
         return filter_var($dado, FILTER_SANITIZE_URL);
      default:
         return htmlspecialchars(trim($dado), ENT_QUOTES, 'UTF-8');
   }
}
function gerarTokenCSRF()
{
   iniciarSessao();

   if (!isset($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
   }

   return $_SESSION['csrf_token'];
}
function verificarTokenCSRF($token)
{
   iniciarSessao();

   return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
