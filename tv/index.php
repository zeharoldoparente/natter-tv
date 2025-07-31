<?php
include "../includes/db.php";

$res = $conn->query("SELECT * FROM conteudos ORDER BY id ASC");
$conteudos = [];
while ($row = $res->fetch_assoc()) {
   $conteudos[] = $row;
}

if (empty($conteudos)) {
   $conteudos = [
      [
         'id' => 0,
         'arquivo' => 'default.jpg',
         'tipo' => 'imagem',
         'duracao' => 10,
         'data_upload' => date('Y-m-d H:i:s')
      ]
   ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>TV Corporativa</title>
   <link rel="stylesheet" href="../assets/css/tv-style.css">
   <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
   <link rel="shortcut icon" href="../assets/images/favicon.png" type="image/x-icon">
</head>

<body>
   <div id="conteudo-principal">
      <div id="media-container"></div>

      <div id="overlay-info">
         <div class="empresa-logo">
            <img class="tt-logo" src="../assets/images/tt Logo.png" alt="">
            <span>NatterTV</span>
         </div>
         <div class="data-hora">
            <div id="horario"></div>
            <div id="data"></div>
         </div>
      </div>

      <div id="loading" class="hidden">
         <i class="fas fa-spinner fa-spin"></i>
         <p>Carregando conteúdo...</p>
      </div>
   </div>

   <div id="tela-sem-conteudo" class="hidden">
      <div class="sem-conteudo">
         <i class="fas fa-tv"></i>
         <h2>NatterTV</h2>
         <p>Aguardando conteúdo...</p>
         <div class="loading-dots">
            <span></span>
            <span></span>
            <span></span>
         </div>
      </div>
   </div>

   <script>
      const CONFIG = {
         updateInterval: 30000,
         showOverlay: true,
         fadeTransition: true,
         debug: false
      };

      let conteudos = <?php echo json_encode($conteudos); ?>;
      let currentIndex = 0;
      let isPlaying = false;
      let updateTimer = null;
      let contentTimer = null;

      document.addEventListener('DOMContentLoaded', function() {
         initializeTV();
      });

      function initializeTV() {
         log('Inicializando TV Corporativa...');

         updateDateTime();
         setInterval(updateDateTime, 1000);

         if (conteudos.length === 0 || conteudos[0].id === 0) {
            showNoContentScreen();
         } else {
            startPlayback();
         }

         setupUpdateChecker();

         setupKeyboardControls();

         log('TV inicializada com sucesso');
      }

      function startPlayback() {
         if (conteudos.length === 0) {
            showNoContentScreen();
            return;
         }

         hideNoContentScreen();
         showContent();
         isPlaying = true;
      }

      function showContent() {
         const content = conteudos[currentIndex];
         const container = document.getElementById('media-container');

         if (!content) {
            nextContent();
            return;
         }

         log(`Mostrando conteúdo: ${content.arquivo} (${content.tipo})`);

         container.innerHTML = '';
         showLoading();

         if (content.tipo === 'imagem') {
            showImage(content);
         } else if (content.tipo === 'video') {
            showVideo(content);
         }
      }

      function showImage(content) {
         const img = document.createElement('img');
         img.src = `../uploads/${content.arquivo}`;
         img.alt = 'Conteúdo corporativo';

         img.onload = function() {
            const container = document.getElementById('media-container');
            hideLoading();

            if (CONFIG.fadeTransition) {
               img.style.opacity = '0';
               container.appendChild(img);

               setTimeout(() => {
                  img.style.opacity = '1';
               }, 100);
            } else {
               container.appendChild(img);
            }
            const duration = content.duracao * 1000;
            contentTimer = setTimeout(nextContent, duration);

            log(`Imagem será exibida por ${content.duracao} segundos`);
         };

         img.onerror = function() {
            log(`Erro ao carregar imagem: ${content.arquivo}`);
            hideLoading();
            nextContent();
         };
      }

      function showVideo(content) {
         const video = document.createElement('video');
         video.src = `../uploads/${content.arquivo}`;
         video.autoplay = true;
         video.muted = true;

         video.onloadeddata = function() {
            const container = document.getElementById('media-container');
            hideLoading();

            if (CONFIG.fadeTransition) {
               video.style.opacity = '0';
               container.appendChild(video);
               setTimeout(() => {
                  video.style.opacity = '1';
               }, 100);
            } else {
               container.appendChild(video);
            }

            log(`Vídeo iniciado: ${content.arquivo} (duração: ${video.duration}s)`);
         };

         video.onended = function() {
            log(`Vídeo finalizado: ${content.arquivo}`);
            nextContent();
         };

         video.onerror = function() {
            log(`Erro ao carregar vídeo: ${content.arquivo}`);
            hideLoading();
            nextContent();
         };
      }

      function nextContent() {
         if (contentTimer) {
            clearTimeout(contentTimer);
            contentTimer = null;
         }
         currentIndex = (currentIndex + 1) % conteudos.length;

         log(`Avançando para conteúdo ${currentIndex + 1} de ${conteudos.length}`);

         setTimeout(showContent, 500);
      }

      function setupUpdateChecker() {
         updateTimer = setInterval(checkForUpdates, CONFIG.updateInterval);
         log(`Verificação de atualizações configurada para cada ${CONFIG.updateInterval/1000} segundos`);
      }

      function checkForUpdates() {
         log('Verificando atualizações...');
         fetch('../temp/tv_update.txt', {
               cache: 'no-cache',
               headers: {
                  'Cache-Control': 'no-cache'
               }
            })
            .then(response => {
               if (response.ok) {
                  return response.text();
               }
               throw new Error('Arquivo de update não encontrado');
            })
            .then(timestamp => {
               const lastUpdate = localStorage.getItem('last_tv_update') || '0';

               if (timestamp !== lastUpdate) {
                  log('Atualização detectada! Recarregando...');
                  localStorage.setItem('last_tv_update', timestamp);
                  setTimeout(() => {
                     window.location.reload();
                  }, 2000);
               }
            })
            .catch(error => {
               checkContentUpdates();
            });
      }

      function checkContentUpdates() {
         fetch('get_contents.php', {
               cache: 'no-cache',
               headers: {
                  'Cache-Control': 'no-cache'
               }
            })
            .then(response => response.json())
            .then(newContents => {
               if (JSON.stringify(newContents) !== JSON.stringify(conteudos)) {
                  log('Novos conteúdos detectados! Atualizando...');
                  conteudos = newContents;

                  if (!isPlaying) {
                     startPlayback();
                  }
               }
            })
            .catch(error => {
               log('Erro ao verificar atualizações: ' + error.message);
            });
      }

      function showLoading() {
         document.getElementById('loading').classList.remove('hidden');
      }

      function hideLoading() {
         document.getElementById('loading').classList.add('hidden');
      }

      function showNoContentScreen() {
         document.getElementById('conteudo-principal').classList.add('hidden');
         document.getElementById('tela-sem-conteudo').classList.remove('hidden');
         isPlaying = false;

         log('Exibindo tela de sem conteúdo');
      }

      function hideNoContentScreen() {
         document.getElementById('conteudo-principal').classList.remove('hidden');
         document.getElementById('tela-sem-conteudo').classList.add('hidden');
      }

      function updateDateTime() {
         const now = new Date();

         const timeOptions = {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
         };

         const dateOptions = {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
         };

         document.getElementById('horario').textContent =
            now.toLocaleTimeString('pt-BR', timeOptions);

         document.getElementById('data').textContent =
            now.toLocaleDateString('pt-BR', dateOptions);
      }

      function setupKeyboardControls() {
         document.addEventListener('keydown', function(e) {
            switch (e.key) {
               case 'ArrowRight':
               case ' ':
                  nextContent();
                  break;
               case 'ArrowLeft':
                  currentIndex = currentIndex > 0 ? currentIndex - 1 : conteudos.length - 1;
                  showContent();
                  break;
               case 'r':
               case 'R':
                  window.location.reload();
                  break;
               case 'f':
               case 'F':
                  if (document.fullscreenElement) {
                     document.exitFullscreen();
                  } else {
                     document.documentElement.requestFullscreen();
                  }
                  break;
            }
         });
      }

      function log(message) {
         if (CONFIG.debug) {
            console.log(`[TV] ${new Date().toLocaleTimeString()}: ${message}`);
         }
      }

      document.addEventListener('click', function() {
         if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
               log('Erro ao entrar em fullscreen: ' + err.message);
            });
         }
      });
   </script>
</body>

</html>