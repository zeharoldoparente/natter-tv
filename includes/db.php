<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tv_corporativa');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('TEMP_PATH', __DIR__ . '/../temp/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024);
define('SIDEBAR_PATH', __DIR__ . '/../uploads/sidebar/');
define('SIDEBAR_WEB_PATH', '../uploads/sidebar/');
if (!is_dir(SIDEBAR_PATH)) {
   mkdir(SIDEBAR_PATH, 0755, true);
}
if (!is_dir(UPLOAD_PATH)) {
   mkdir(UPLOAD_PATH, 0755, true);
}

if (!is_dir(TEMP_PATH)) {
   mkdir(TEMP_PATH, 0755, true);
}

try {
   $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
   $conn->set_charset("utf8");
   if ($conn->connect_error) {
      throw new Exception("Erro na conexão: " . $conn->connect_error);
   }
} catch (Exception $e) {
   try {
      $conn_temp = new mysqli(DB_HOST, DB_USER, DB_PASS);

      if ($conn_temp->connect_error) {
         die("Erro crítico de conexão: " . $conn_temp->connect_error);
      }
      $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8 COLLATE utf8_general_ci";
      if ($conn_temp->query($sql)) {
         $conn_temp->close();
         $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
         $conn->set_charset("utf8");

         if ($conn->connect_error) {
            die("Erro após criar banco: " . $conn->connect_error);
         }
         include_once __DIR__ . '/install_tables.php';
      } else {
         die("Erro ao criar banco de dados: " . $conn_temp->error);
      }
   } catch (Exception $e2) {
      die("Erro crítico: " . $e2->getMessage());
   }
}
function executeQuery($query, $params = [], $types = '')
{
   global $conn;

   $stmt = $conn->prepare($query);

   if (!$stmt) {
      throw new Exception("Erro ao preparar query: " . $conn->error);
   }

   if (!empty($params)) {
      $stmt->bind_param($types, ...$params);
   }

   if (!$stmt->execute()) {
      throw new Exception("Erro ao executar query: " . $stmt->error);
   }

   return $stmt;
}
function fetchData($query, $params = [], $types = '')
{
   $stmt = executeQuery($query, $params, $types);
   $result = $stmt->get_result();

   $data = [];
   while ($row = $result->fetch_assoc()) {
      $data[] = $row;
   }

   $stmt->close();
   return $data;
}
function fetchSingle($query, $params = [], $types = '')
{
   $stmt = executeQuery($query, $params, $types);
   $result = $stmt->get_result();

   $data = $result->fetch_assoc();
   $stmt->close();

   return $data;
}
function insertData($query, $params = [], $types = '')
{
   global $conn;

   $stmt = executeQuery($query, $params, $types);
   $insertId = $conn->insert_id;
   $stmt->close();

   return $insertId;
}
function sanitizeFilename($filename)
{
   $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
   $filename = preg_replace('/_+/', '_', $filename);
   $filename = trim($filename, '_');

   return $filename;
}
function getFileType($filename)
{
   $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

   $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
   $videoTypes = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv'];

   if (in_array($ext, $imageTypes)) {
      return 'imagem';
   } elseif (in_array($ext, $videoTypes)) {
      return 'video';
   }

   return 'desconhecido';
}
function formatFileSize($bytes)
{
   if ($bytes >= 1073741824) {
      return number_format($bytes / 1073741824, 2) . ' GB';
   } elseif ($bytes >= 1048576) {
      return number_format($bytes / 1048576, 2) . ' MB';
   } elseif ($bytes >= 1024) {
      return number_format($bytes / 1024, 2) . ' KB';
   } else {
      return $bytes . ' bytes';
   }
}
