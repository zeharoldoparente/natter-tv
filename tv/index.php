<?php
include "../includes/db.php";

// Buscar todos os conteúdos ativos
$res = $conn->query("SELECT * FROM conteudos ORDER BY id ASC");
$conteudos = [];
while ($row = $res->fetch_assoc()) {
   $conteudos[] = $row;
}

// Se não há conteúdos, mostrar tela padrão
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
</head>

<body>
   <!-- Tela principal de conteúdo -->
   <div id="conteudo-principal">
      <div id="media-container"></div>

      <!-- Overlay com informações (opcional) -->
      <div id="overlay-info">
         <div class="empresa-logo">
            <i class="fas fa-building"></i>
            <span>TV Corporativa</span>
         </div>
         <div class="data-hora">
            <div id="horario"></div>
            <div id="data"></div>
         </div>
      </div>

      <!-- Loading indicator -->
      <div id="loading" class="hidden">
         <i class="fas fa-spinner fa-spin"></i>
         <p>Carregando conteúdo...</p>
      </div>
   </div>

   <!-- Tela de erro/sem conteúdo -->
   <div id="tela-sem-conteudo" class="hidden">
      <div class="sem-conteudo">
         <i class="fas fa-tv"></i>
         <h2>TV Corporativa</h2>
         <p>Aguardando conteúdo...</p>
         <div class="loading-dots">
            <span></span>
            <span></span>
            <span></span>
         </div>
      </div>
   </div>

   <script>
      // Configurações da TV
      const CONFIG = {
         // Verificar atualizações a cada 30 segundos
         updateInterval: 30000,
         // Mostrar overlay de informações
         showOverlay: true,
         // Transições suaves entre conteúdos
         fadeTransition: true,
         // Debug mode (define como false em produção)
         debug: false
      };

      // Dados dos conteúdos vindos do PHP
      let conteudos = <?php echo json_encode($conteudos); ?>;
      let currentIndex = 0;
      let isPlaying = false;
      let updateTimer = null;
      let contentTimer = null;

      // Inicializar TV quando página carregar
      document.addEventListener('DOMContentLoaded', function() {
         initializeTV();
      });

      /**
       * Inicializar sistema da TV
       */
      function initializeTV() {
         log('Inicializando TV Corporativa...');

         // Configurar relógio
         updateDateTime();
         setInterval(updateDateTime, 1000);

         // Verificar se há conteúdos
         if (conteudos.length === 0 || conteudos[0].id === 0) {
            showNoContentScreen();
         } else {
            // Iniciar reprodução
            startPlayback();
         }

         // Configurar verificação de atualizações
         setupUpdateChecker();

         // Configurar eventos de teclado para controle
         setupKeyboardControls();

         log('TV inicializada com sucesso');
      }

      /**
       * Iniciar reprodução de conteúdos
       */
      function startPlayback() {
         if (conteudos.length === 0) {
            showNoContentScreen();
            return;
         }

         hideNoContentScreen();
         showContent();
         isPlaying = true;
      }

      /**
       * Mostrar conteúdo atual
       */
      function showContent() {
         const content = conteudos[currentIndex];
         const container = document.getElementById('media-container');

         if (!content) {
            nextContent();
            return;
         }

         log(`Mostrando conteúdo: ${content.arquivo} (${content.tipo})`);

         // Limpar conteúdo anterior
         container.innerHTML = '';

         // Mostrar loading
         showLoading();

         if (content.tipo === 'imagem') {
            showImage(content);
         } else if (content.tipo === 'video') {
            showVideo(content);
         }
      }

      /**
       * Mostrar imagem
       */
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

               // Fade in
               setTimeout(() => {
                  img.style.opacity = '1';
               }, 100);
            } else {
               container.appendChild(img);
            }

            // Programar próximo conteúdo
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

      /**
       * Mostrar vídeo
       */
      function showVideo(content) {
         const video = document.createElement('video');
         video.src = `../uploads/${content.arquivo}`;
         video.autoplay = true;
         video.muted = true; // Importante para autoplay funcionar

         video.onloadeddata = function() {
            const container = document.getElementById('media-container');
            hideLoading();

            if (CONFIG.fadeTransition) {
               video.style.opacity = '0';
               container.appendChild(video);

               // Fade in
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

      /**
       * Avançar para próximo conteúdo
       */
      function nextContent() {
         // Limpar timer atual
         if (contentTimer) {
            clearTimeout(contentTimer);
            contentTimer = null;
         }

         // Avançar índice
         currentIndex = (currentIndex + 1) % conteudos.length;

         log(`Avançando para conteúdo ${currentIndex + 1} de ${conteudos.length}`);

         // Mostrar próximo conteúdo após pequeno delay
         setTimeout(showContent, 500);
      }

      /**
       * Configurar verificação de atualizações
       */
      function setupUpdateChecker() {
         updateTimer = setInterval(checkForUpdates, CONFIG.updateInterval);
         log(`Verificação de atualizações configurada para cada ${CONFIG.updateInterval/1000} segundos`);
      }

      /**
       * Verificar se há atualizações de conteúdo
       */
      function checkForUpdates() {
         log('Verificando atualizações...');

         // Verificar arquivo de sinal de update
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

                  // Recarregar página após 2 segundos
                  setTimeout(() => {
                     window.location.reload();
                  }, 2000);
               }
            })
            .catch(error => {
               // Arquivo não existe, fazer verificação por AJAX
               checkContentUpdates();
            });
      }

      /**
       * Verificar atualizações via AJAX
       */
      function checkContentUpdates() {
         fetch('get_contents.php', {
               cache: 'no-cache',
               headers: {
                  'Cache-Control': 'no-cache'
               }
            })
            .then(response => response.json())
            .then(newContents => {
               // Comparar com conteúdos atuais
               if (JSON.stringify(newContents) !== JSON.stringify(conteudos)) {
                  log('Novos conteúdos detectados! Atualizando...');
                  conteudos = newContents;

                  // Se não estava reproduzindo, iniciar
                  if (!isPlaying) {
                     startPlayback();
                  }
               }
            })
            .catch(error => {
               log('Erro ao verificar atualizações: ' + error.message);
            });
      }

      /**
       * Mostrar/esconder tela de loading
       */
      function showLoading() {
         document.getElementById('loading').classList.remove('hidden');
      }

      function hideLoading() {
         document.getElementById('loading').classList.add('hidden');
      }

      /**
       * Mostrar tela sem conteúdo
       */
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

      /**
       * Atualizar data e hora
       */
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

      /**
       * Configurar controles de teclado
       */
      function setupKeyboardControls() {
         document.addEventListener('keydown', function(e) {
            switch (e.key) {
               case 'ArrowRight':
               case ' ': // Espaço
                  nextContent();
                  break;
               case 'ArrowLeft':
                  // Voltar conteúdo anterior
                  currentIndex = currentIndex > 0 ? currentIndex - 1 : conteudos.length - 1;
                  showContent();
                  break;
               case 'r':
               case 'R':
                  // Recarregar
                  window.location.reload();
                  break;
               case 'f':
               case 'F':
                  // Fullscreen
                  if (document.fullscreenElement) {
                     document.exitFullscreen();
                  } else {
                     document.documentElement.requestFullscreen();
                  }
                  break;
            }
         });
      }

      /**
       * Função de log para debug
       */
      function log(message) {
         if (CONFIG.debug) {
            console.log(`[TV] ${new Date().toLocaleTimeString()}: ${message}`);
         }
      }

      // Configurar fullscreen automático (opcional)
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