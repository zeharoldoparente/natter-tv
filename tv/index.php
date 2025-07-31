<?php
include "../db.php";
$res = $conn->query("SELECT * FROM conteudos ORDER BY id ASC");
$conteudos = [];
while ($row = $res->fetch_assoc()) {
   $conteudos[] = $row;
}
?>
<!DOCTYPE html>
<html>

<head>
   <meta charset="UTF-8">
   <title>TV Corporativa</title>
   <style>
      body {
         margin: 0;
         background: #000;
         display: flex;
         align-items: center;
         justify-content: center;
         height: 100vh;
      }

      img,
      video {
         max-width: 100%;
         max-height: 100%;
         border: 8px solid #f57c00;
         border-radius: 10px;
      }
   </style>
</head>

<body>
   <div id="conteudo"></div>
   <script>
      const conteudos = <?php echo json_encode($conteudos); ?>;
      let index = 0;

      function mostrar() {
         const item = conteudos[index];
         let html = '';

         if (item.tipo === 'imagem') {
            html = `<img src="../uploads/${item.arquivo}" />`;
            document.getElementById('conteudo').innerHTML = html;
            setTimeout(proximo, item.duracao * 1000);
         } else {
            html = `<video src="../uploads/${item.arquivo}" autoplay></video>`;
            document.getElementById('conteudo').innerHTML = html;
            document.querySelector('video').addEventListener('ended', proximo);
         }
      }

      function proximo() {
         index = (index + 1) % conteudos.length;
         mostrar();
      }

      mostrar();
   </script>
</body>

</html>