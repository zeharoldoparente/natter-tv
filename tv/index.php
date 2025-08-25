<?php
include "../includes/db.php";

$codigo_canal = '';
if (isset($_GET['canal']) && !empty($_GET['canal'])) {
   $codigo_canal = strtoupper(trim($_GET['canal']));
   if (!preg_match('/^[A-Z0-9]{1,10}$/', $codigo_canal)) {
      $codigo_canal = '';
   }
}

if (empty($codigo_canal)) {
   $canais_disponiveis = [];
   $res = $conn->query("SELECT DISTINCT codigo_canal, COUNT(*) as total_conteudos FROM conteudos WHERE ativo = 1 GROUP BY codigo_canal ORDER BY codigo_canal");
   if ($res) {
      while ($row = $res->fetch_assoc()) {
         $canais_disponiveis[] = $row;
      }
   }
   include 'selecionar_canal.php';
   exit;
}

$stmt = $conn->prepare("SELECT * FROM conteudos WHERE codigo_canal = ? AND ativo = 1 ORDER BY ordem_exibicao ASC, id ASC");
$stmt->bind_param("s", $codigo_canal);
$stmt->execute();
$result = $stmt->get_result();

$conteudos = [];
while ($row = $result->fetch_assoc()) {
   $conteudos[] = $row;
}
$stmt->close();
if (empty($conteudos)) {
   $conteudos = [
      [
         'id' => 0,
         'arquivo' => 'default.jpg',
         'tipo' => 'imagem',
         'duracao' => 10,
         'codigo_canal' => $codigo_canal,
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
   <title>TV Corporativa - Canal <?php echo htmlspecialchars($codigo_canal); ?></title>
   <link rel="stylesheet" href="../assets/css/base.css">
   <link rel="stylesheet" href="../assets/css/tv-style.css">
   <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
   <link rel="shortcut icon" href="../assets/images/favicon.png" type="image/x-icon">
</head>

<body>
   <div id="conteudo-principal">
      <div id="media-container"></div>
      <div id="rss-topo" class="rss-container topo hidden">
         <div class="rss-ticker" id="rss-ticker-topo"></div>
      </div>
      <div id="sidebar-direita">
         <div class="propaganda">
            <?php include "../includes/sidebar_content.php"; ?>
         </div>
         <div class="info-branca">
            <div class="logo-tv">
               <img src="../assets/images/TV Corporativa - Natter.png" alt="Logo NatterTV">
               <div class="canal-nome">Canal <?php echo htmlspecialchars($codigo_canal); ?></div>
            </div>
            <div class="data-hora-container">
               <div id="horario"></div>
               <div id="data"></div>
            </div>
         </div>
      </div>
      <div id="rodape-bar">
         <div class="rodape-logo">
            <img src="../assets/images/tt Logo.png" alt="Logo">
         </div>
         <div id="rss-rodape" class="rss-container rodape hidden">
            <div class="rss-ticker" id="rss-ticker-rodape"></div>
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
         <h2>NatterTV - Canal <?php echo htmlspecialchars($codigo_canal); ?></h2>
         <p>Aguardando conteúdo para este canal...</p>
         <div class="loading-dots">
            <span></span>
            <span></span>
            <span></span>
         </div>
         <div class="canal-info-footer">
            <p><small>Pressione 'C' para trocar de canal</small></p>
         </div>
      </div>
   </div>

   <script>
      const CONFIG = {
         updateInterval: 30000,
         rssUpdateInterval: 300000,
         showOverlay: false,
         fadeTransition: true,
         debug: false,
         canalAtual: '<?php echo $codigo_canal; ?>'
      };
      let conteudos = <?php echo json_encode($conteudos); ?>;
      let currentIndex = 0;
      let isPlaying = false;
      let updateTimer = null;
      let contentTimer = null;
      let rssTimer = null;
      let rssData = {
         rodape: [],
         topo: []
      };
      let isFirstLoad = true;
      document.addEventListener('DOMContentLoaded', function() {
         initializeTV();
      });

      function initializeTV() {
         log('Inicializando TV Corporativa - Canal: ' + CONFIG.canalAtual);
         requestFullscreenSilent();
         updateDateTime();
         setInterval(updateDateTime, 1000);
         initializeRSS();
         if (conteudos.length === 0 || conteudos[0].id === 0) {
            showNoContentScreen();
         } else {
            startPlayback();
         }

         setTimeout(() => {
            setupUpdateChecker();
            isFirstLoad = false;
         }, 5000);

         setupKeyboardControls();

         log('TV inicializada com sucesso para o canal ' + CONFIG.canalAtual);
      }

      function requestFullscreenSilent() {
         if (document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen().catch(() => {
               log('Fullscreen automático não permitido pelo navegador');
            });
         }
         document.addEventListener('click', function(e) {
            if (!document.fullscreenElement) {
               document.documentElement.requestFullscreen().catch(() => {});
            }
         }, {
            once: false
         });
      }

      function initializeRSS() {
         updateRSSContent();
         if (rssTimer) {
            clearInterval(rssTimer);
         }
         rssTimer = setInterval(updateRSSContent, CONFIG.rssUpdateInterval);
         log('Sistema RSS inicializado');
      }

      function updateRSSContent() {
         log('Atualizando conteúdo RSS para canal ' + CONFIG.canalAtual + '...');

         fetch(`get_rss.php?canal=${CONFIG.canalAtual}`, {
               cache: 'no-cache',
               headers: {
                  'Cache-Control': 'no-cache'
               }
            })
            .then(response => response.json())
            .then(data => {
               if (data.error) {
                  log('Erro na API RSS: ' + data.message);
                  return;
               }

               rssData = data;
               setupRSSTickers();
               log(`RSS atualizado: ${data.total_itens} itens (${data.rodape.length} rodapé, ${data.topo.length} topo)`);
            })
            .catch(error => {
               log('Erro ao buscar RSS: ' + error.message);
            });
      }

      function setupRSSTickers() {
         if (rssData.rodape && rssData.rodape.length > 0) {
            const rodapeContainer = document.getElementById('rss-rodape');
            const rodapeTicker = document.getElementById('rss-ticker-rodape');
            setupRSSPosition(rssData.rodape, rodapeTicker, rodapeContainer);
            rodapeContainer.classList.remove('hidden');
         } else {
            document.getElementById('rss-rodape').classList.add('hidden');
         }
         if (rssData.topo && rssData.topo.length > 0) {
            const topoContainer = document.getElementById('rss-topo');
            const topoTicker = document.getElementById('rss-ticker-topo');
            setupRSSPosition(rssData.topo, topoTicker, topoContainer);
            topoContainer.classList.remove('hidden');
         } else {
            document.getElementById('rss-topo').classList.add('hidden');
         }
      }

      function setupRSSPosition(items, ticker, container) {
         if (!items || items.length === 0) return;

         const config = items[0].configuracao;
         container.style.backgroundColor = config.cor_fundo;
         container.style.color = config.cor_texto;
         let tickerHTML = '';
         items.forEach((item) => {
            tickerHTML += `<span class="rss-item">${escapeHtml(item.texto)}</span>`;
         });
         ticker.innerHTML = tickerHTML + tickerHTML;
         ticker.style.animation = 'none';
         ticker.style.transform = 'translateX(0)';

         setTimeout(() => {
            const fullWidth = ticker.scrollWidth;
            const contentWidth = fullWidth / 2;
            const containerWidth = container.offsetWidth;

            if (contentWidth <= containerWidth) {
               ticker.style.animation = "none";
               ticker.style.transform = "none";
               return;
            }

            const velocidade = Math.max(config.velocidade_scroll, 10);
            const duration = contentWidth / velocidade;

            const animationName = `scroll-horizontal-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
            const existingStyle = document.getElementById(`rss-animation-${container.id}`);
            if (existingStyle) {
               existingStyle.remove();
            }
            const style = document.createElement('style');
            style.id = `rss-animation-${container.id}`;
            style.textContent = `
               @keyframes ${animationName} {
                  0% { transform: translateX(0); }
                  100% { transform: translateX(-${contentWidth}px); }
               }
            `;
            document.head.appendChild(style);

            ticker.style.animation = `${animationName} ${duration}s linear infinite`;

            log(`RSS configurado: ${items.length} itens, velocidade: ${velocidade}px/s, duração: ${duration.toFixed(2)}s`);
         }, 100);
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

         log(`Mostrando conteúdo: ${content.arquivo} (${content.tipo}) - Canal: ${CONFIG.canalAtual}`);

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
         video.muted = false;

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
         if (isFirstLoad) {
            return;
         }

         log('Verificando atualizações para canal ' + CONFIG.canalAtual + '...');

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
               const lastUpdate = localStorage.getItem('last_tv_update_' + CONFIG.canalAtual) || '0';

               if (timestamp !== lastUpdate) {
                  log('Atualização detectada! Recarregando...');
                  localStorage.setItem('last_tv_update_' + CONFIG.canalAtual, timestamp);
                  setTimeout(() => {
                     window.location.reload();
                  }, 3000);
               }
            })
            .catch(error => {
               checkContentUpdates();
            });
      }

      function checkContentUpdates() {
         if (isFirstLoad) {
            return;
         }

         fetch(`get_contents.php?canal=${CONFIG.canalAtual}`, {
               cache: 'no-cache',
               headers: {
                  'Cache-Control': 'no-cache'
               }
            })
            .then(response => response.json())
            .then(newContents => {
               if (JSON.stringify(newContents) !== JSON.stringify(conteudos)) {
                  log('Novos conteúdos detectados para canal ' + CONFIG.canalAtual + '! Atualizando...');
                  conteudos = newContents;

                  if (!isPlaying && newContents.length > 0) {
                     startPlayback();
                  }
               }
            })
            .catch(error => {
               log('Erro ao verificar atualizações: ' + error.message);
            });
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
               case 'c':
               case 'C':
                  window.location.href = 'index.php';
                  break;
               case 'f':
               case 'F':
                  if (document.fullscreenElement) {
                     document.exitFullscreen();
                  } else {
                     document.documentElement.requestFullscreen();
                  }
                  break;
               case 'u':
               case 'U':
                  updateRSSContent();
                  break;
            }
         });
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

         const horarioElements = document.querySelectorAll('#horario');
         const dataElements = document.querySelectorAll('#data');

         horarioElements.forEach(element => {
            element.textContent = now.toLocaleTimeString('pt-BR', timeOptions);
         });

         dataElements.forEach(element => {
            element.textContent = now.toLocaleDateString('pt-BR', dateOptions);
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
         log('Exibindo tela de sem conteúdo para canal ' + CONFIG.canalAtual);
      }

      function hideNoContentScreen() {
         document.getElementById('conteudo-principal').classList.remove('hidden');
         document.getElementById('tela-sem-conteudo').classList.add('hidden');
      }

      function escapeHtml(text) {
         const div = document.createElement('div');
         div.textContent = text;
         return div.innerHTML;
      }

      function log(message) {
         if (CONFIG.debug) {
            console.log(`[TV-${CONFIG.canalAtual}] ${new Date().toLocaleTimeString()}: ${message}`);
         }
      }

      function initializeSidebarVideo() {
         const sidebarVideos = document.querySelectorAll('#sidebar-direita video');

         sidebarVideos.forEach((video, index) => {
            console.log(`Inicializando vídeo da sidebar ${index + 1}`);
            video.setAttribute('playsinline', 'true');
            video.setAttribute('webkit-playsinline', 'true');
            video.setAttribute('x-webkit-airplay', 'deny');
            video.setAttribute('disablePictureInPicture', 'true');
            video.muted = true;
            video.loop = true;
            video.autoplay = true;
            video.addEventListener('loadedmetadata', function() {
               console.log('Sidebar video metadata loaded');
               this.currentTime = 0;
               this.play().catch(e => {
                  console.error('Erro ao reproduzir vídeo da sidebar:', e);
                  replaceVideoWithImage(this);
               });
            });

            video.addEventListener('canplay', function() {
               console.log('Sidebar video can play');
               this.play().catch(e => {
                  console.error('Erro ao reproduzir vídeo da sidebar (canplay):', e);
                  replaceVideoWithImage(this);
               });
            });

            video.addEventListener('ended', function() {
               console.log('Sidebar video ended, restarting...');
               this.currentTime = 0;
               this.play().catch(e => {
                  console.error('Erro ao reiniciar vídeo da sidebar:', e);
               });
            });

            video.addEventListener('error', function(e) {
               console.error('Erro no vídeo da sidebar:', e, this.error);
               replaceVideoWithImage(this);
            });

            video.addEventListener('stalled', function() {
               console.warn('Vídeo da sidebar travado, tentando reiniciar...');
               this.load();
               setTimeout(() => {
                  this.play().catch(e => {
                     console.error('Erro ao reiniciar vídeo travado:', e);
                     replaceVideoWithImage(this);
                  });
               }, 1000);
            });
            setTimeout(() => {
               if (video.paused || video.currentTime === 0) {
                  console.warn('Vídeo da sidebar não está reproduzindo, forçando...');
                  video.load();
                  video.play().catch(e => {
                     console.error('Falha final ao reproduzir vídeo da sidebar:', e);
                     replaceVideoWithImage(video);
                  });
               }
            }, 3000);
            let lastTime = video.currentTime;
            setInterval(() => {
               if (!video.paused && video.currentTime === lastTime && video.readyState > 0) {
                  console.warn('Vídeo da sidebar pode estar travado');
                  video.currentTime = video.currentTime + 0.1;
               }
               lastTime = video.currentTime;
            }, 5000);
         });
      }

      function replaceVideoWithImage(videoElement) {
         console.log('Substituindo vídeo por imagem de fallback');
         const img = document.createElement('img');
         img.src = '../assets/images/propaganda.png';
         img.alt = 'Propaganda';

         if (videoElement.parentNode) {
            videoElement.parentNode.replaceChild(img, videoElement);
         }
      }

      function detectDevice() {
         const userAgent = navigator.userAgent.toLowerCase();

         if (userAgent.includes('tizen')) {
            console.log('Samsung Smart TV detectada');
            return 'samsung';
         } else if (userAgent.includes('webos')) {
            console.log('LG Smart TV detectada');
            return 'lg';
         } else if (userAgent.includes('android tv')) {
            console.log('Android TV detectada');
            return 'android';
         } else if (userAgent.includes('hbbtv')) {
            console.log('HbbTV detectada');
            return 'hbbtv';
         }

         return 'desktop';
      }

      function applyDeviceSpecificSettings() {
         const device = detectDevice();

         switch (device) {
            case 'samsung':
               document.querySelectorAll('#sidebar-direita video').forEach(video => {
                  video.setAttribute('webkit-playsinline', 'true');
                  video.preload = 'auto';
               });
               break;

            case 'lg':
               document.querySelectorAll('#sidebar-direita video').forEach(video => {
                  video.setAttribute('playsinline', 'true');
                  video.muted = true;
               });
               break;

            case 'android':
               document.querySelectorAll('#sidebar-direita video').forEach(video => {
                  video.controls = false;
                  video.setAttribute('playsinline', 'true');
               });
               break;
         }
      }
      const sidebarObserver = new MutationObserver(function(mutations) {
         mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
               if (node.nodeType === Node.ELEMENT_NODE) {
                  const videos = node.querySelectorAll ? node.querySelectorAll('video') : [];
                  if (videos.length > 0 || node.tagName === 'VIDEO') {
                     console.log('Novo vídeo detectado na sidebar');
                     setTimeout(initializeSidebarVideo, 500);
                  }
               }
            });
         });
      });
      document.addEventListener('DOMContentLoaded', function() {
         console.log('Inicializando sistema de vídeo da sidebar...');

         applyDeviceSpecificSettings();
         const sidebar = document.getElementById('sidebar-direita');
         if (sidebar) {
            sidebarObserver.observe(sidebar, {
               childList: true,
               subtree: true
            });
         }

         setTimeout(initializeSidebarVideo, 1000);

         setInterval(() => {
            const videos = document.querySelectorAll('#sidebar-direita video');
            if (videos.length > 0) {
               videos.forEach(video => {
                  if (video.paused && video.readyState >= 3) {
                     console.log('Re-iniciando vídeo pausado da sidebar');
                     video.play().catch(e => {
                        console.error('Erro ao re-iniciar vídeo:', e);
                     });
                  }
               });
            }
         }, 10000);
      });
   </script>
</body>

</html>