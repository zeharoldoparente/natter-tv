<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "tv_corporativa";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
   die("Erro na conexão: " . $conn->connect_error);
}
