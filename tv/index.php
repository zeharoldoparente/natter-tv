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
   <title>NatterTV - Canal <?php echo htmlspecialchars($codigo_canal); ?></title>
   <link rel="stylesheet" href="../assets/css/base.css">
   <link rel="stylesheet" href="../assets/css/tv-style.css">
   <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
   <link rel="shortcut icon" href="../assets/images/favicon.png" type="image/x-icon">
   <meta name="theme-color" content="#166353">
   <meta name="description" content="NatterTV - Sistema Corporativo de TV Digital">
</head>

<body>
   <div id="conteudo-principal">
      <div id="media-container"></div>

      <!-- RSS Topo -->
      <div id="rss-topo" class="rss-container topo hidden">
         <div class="rss-ticker" id="rss-ticker-topo"></div>
      </div>

      <!-- Sidebar Direita -->
      <div id="sidebar-direita">
         <div class="propaganda">
            <?php include "../includes/sidebar_content.php"; ?>
         </div>

         <div class="info-branca">
            <div class="logo-tv">
               <img src="../assets/images/TV Corporativa - Natter.png" alt="NatterTV" loading="lazy">
               <div class="canal-nome">Canal <?php echo htmlspecialchars($codigo_canal); ?></div>
            </div>

            <div class="data-hora-container">
               <div id="horario">--:--:--</div>
               <div id="data">Carregando...</div>
            </div>
         </div>
      </div>

      <!-- Rodapé com RSS -->
      <div id="rodape-bar">
         <div class="rodape-logo">
            <img src="../assets/images/tt Logo.png" alt="Logo" loading="lazy">
         </div>
         <div id="rss-rodape" class="rss-container rodape hidden">
            <div class="rss-ticker" id="rss-ticker-rodape"></div>
         </div>
      </div>

      <!-- Loading -->
      <div id="loading" class="hidden">
         <i class="fas fa-spinner fa-spin"></i>
         <p>Carregando conteúdo...</p>
      </div>

      <!-- Connection Indicator -->
      <div class="connection-indicator" id="connectionIndicator"></div>
   </div>

   <!-- Tela Sem Conteúdo -->
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
            <p>
               <i class="fas fa-keyboard"></i>
               Pressione 'C' para trocar de canal • 'R' para recarregar
            </p>
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
         canalAtual: '<?php echo $codigo_canal; ?>',
         enableAnimations: true
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
      let connectionStatus = 'online';

      document.addEventListener('DOMContentLoaded', function() {
         initializeTV();
      });

      function initializeTV() {
         log('🚀 Inicializando NatterTV - Canal: ' + CONFIG.canalAtual);

         // Configurações iniciais
         requestFullscreenSilent();
         updateDateTime();
         setInterval(updateDateTime, 1000);

         // Sistema RSS
         initializeRSS();

         // Verificar conteúdo
         if (conteudos.length === 0 || conteudos[0].id === 0) {
            showNoContentScreen();
         } else {
            startPlayback();
         }

         // Configurar atualizações após carregamento inicial
         setTimeout(() => {
            setupUpdateChecker();
            isFirstLoad = false;
            updateConnectionStatus('online');
         }, 5000);

         // Controles do teclado
         setupKeyboardControls();

         // Inicializar vídeos da sidebar
         setTimeout(initializeSidebarVideo, 1000);

         log('✅ NatterTV inicializada com sucesso para o canal ' + CONFIG.canalAtual);
      }

      function requestFullscreenSilent() {
         // Tentar fullscreen automático
         if (document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen().catch(() => {
               log('📱 Fullscreen automático não permitido pelo navegador');
            });
         }

         // Fallback para clique
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
         log('📡 Sistema RSS inicializado');
      }

      function updateRSSContent() {
         log('🔄 Atualizando conteúdo RSS para canal ' + CONFIG.canalAtual + '...');

         fetch(`get_rss.php?canal=${CONFIG.canalAtual}`, {
               cache: 'no-cache',
               headers: {
                  'Cache-Control': 'no-cache'
               }
            })
            .then(response => response.json())
            .then(data => {
               if (data.error) {
                  log('❌ Erro na API RSS: ' + data.message);
                  return;
               }

               rssData = data;
               setupRSSTickers();
               log(`📰 RSS atualizado: ${data.total_itens} itens (${data.rodape.length} rodapé, ${data.topo.length} topo)`);
            })
            .catch(error => {
               log('🚨 Erro ao buscar RSS: ' + error.message);
               updateConnectionStatus('offline');
            });
      }

      function setupRSSTickers() {
         // RSS Rodapé
         if (rssData.rodape && rssData.rodape.length > 0) {
            const rodapeContainer = document.getElementById('rss-rodape');
            const rodapeTicker = document.getElementById('rss-ticker-rodape');
            setupRSSPosition(rssData.rodape, rodapeTicker, rodapeContainer);
            rodapeContainer.classList.remove('hidden');
         } else {
            document.getElementById('rss-rodape').classList.add('hidden');
         }

         // RSS Topo
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

         // Aplicar estilos do feed
         container.style.background = `linear-gradient(135deg, ${config.cor_fundo}ee 0%, ${config.cor_fundo}dd 100%)`;
         container.style.color = config.cor_texto;

         // Gerar HTML do ticker
         let tickerHTML = '';
         items.forEach((item) => {
            tickerHTML += `<span class="rss-item" title="${escapeHtml(item.titulo)}">
               <span class="rss-feed-label">${escapeHtml(item.feed_nome)}</span>
               ${escapeHtml(item.texto)}
            </span>`;
         });

         // Duplicar para scroll infinito
         ticker.innerHTML = tickerHTML + tickerHTML;

         // Reset animation
         ticker.style.animation = 'none';
         ticker.style.transform = 'translateX(0)';

         // Configurar animação após um frame
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

            ticker.style.animation = `scroll-horizontal ${duration}s linear infinite`;

            log(`📺 RSS configurado: ${items.length} itens, velocidade: ${velocidade}px/s, duração: ${duration.toFixed(2)}s`);
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

         log(`🎬 Mostrando: ${content.arquivo} (${content.tipo}) - Canal: ${CONFIG.canalAtual}`);

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
         img.loading = 'eager';

         img.onload = function() {
            const container = document.getElementById('media-container');
            hideLoading();

            if (CONFIG.fadeTransition) {
               img.style.opacity = '0';
               img.style.transform = 'scale(1.02)';
               container.appendChild(img);

               requestAnimationFrame(() => {
                  img.style.transition = 'opacity 0.6s ease-in-out, transform 0.6s ease-in-out';
                  img.style.opacity = '1';
                  img.style.transform = 'scale(1)';
               });
            } else {
               container.appendChild(img);
            }

            const duration = content.duracao * 1000;
            contentTimer = setTimeout(nextContent, duration);
            log(`🖼️ Imagem será exibida por ${content.duracao} segundos`);
         };

         img.onerror = function() {
            log(`❌ Erro ao carregar imagem: ${content.arquivo}`);
            hideLoading();
            nextContent();
         };
      }

      function showVideo(content) {
         const video = document.createElement('video');
         video.src = `../uploads/${content.arquivo}`;
         video.autoplay = true;
         video.muted = false;
         video.preload = 'auto';
         video.playsInline = true;

         video.onloadeddata = function() {
            const container = document.getElementById('media-container');
            hideLoading();

            if (CONFIG.fadeTransition) {
               video.style.opacity = '0';
               video.style.transform = 'scale(1.02)';
               container.appendChild(video);

               requestAnimationFrame(() => {
                  video.style.transition = 'opacity 0.6s ease-in-out, transform 0.6s ease-in-out';
                  video.style.opacity = '1';
                  video.style.transform = 'scale(1)';
               });
            } else {
               container.appendChild(video);
            }

            log(`🎥 Vídeo iniciado: ${content.arquivo} (duração: ${video.duration}s)`);
         };

         video.onended = function() {
            log(`✅ Vídeo finalizado: ${content.arquivo}`);
            nextContent();
         };

         video.onerror = function() {
            log(`❌ Erro ao carregar vídeo: ${content.arquivo}`);
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
         log(`⏭️ Avançando para conteúdo ${currentIndex + 1} de ${conteudos.length}`);

         // Pequeno delay para transição suave
         setTimeout(showContent, CONFIG.fadeTransition ? 500 : 100);
      }

      function setupUpdateChecker() {
         updateTimer = setInterval(checkForUpdates, CONFIG.updateInterval);
         log(`🔄 Verificação de atualizações configurada para cada ${CONFIG.updateInterval/1000} segundos`);
      }

      function checkForUpdates() {
         if (isFirstLoad) return;

         log('🔍 Verificando atualizações para canal ' + CONFIG.canalAtual + '...');

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
                  log('🚀 Atualização detectada! Recarregando...');
                  localStorage.setItem('last_tv_update_' + CONFIG.canalAtual, timestamp);
                  setTimeout(() => window.location.reload(), 3000);
               }
               updateConnectionStatus('online');
            })
            .catch(error => {
               updateConnectionStatus('offline');
               checkContentUpdates();
            });
      }

      function checkContentUpdates() {
         if (isFirstLoad) return;

         fetch(`get_contents.php?canal=${CONFIG.canalAtual}`, {
               cache: 'no-cache',
               headers: {
                  'Cache-Control': 'no-cache'
               }
            })
            .then(response => response.json())
            .then(newContents => {
               if (JSON.stringify(newContents) !== JSON.stringify(conteudos)) {
                  log('🆕 Novos conteúdos detectados para canal ' + CONFIG.canalAtual + '! Atualizando...');
                  conteudos = newContents;

                  if (!isPlaying && newContents.length > 0) {
                     startPlayback();
                  }
               }
               updateConnectionStatus('online');
            })
            .catch(error => {
               log('❌ Erro ao verificar atualizações: ' + error.message);
               updateConnectionStatus('offline');
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
                  log('🔄 Recarregando página...');
                  window.location.reload();
                  break;
               case 'c':
               case 'C':
                  log('📺 Redirecionando para seleção de canal...');
                  window.location.href = 'index.php';
                  break;
               case 'f':
               case 'F':
                  toggleFullscreen();
                  break;
               case 'u':
               case 'U':
                  log('📡 Forçando atualização RSS...');
                  updateRSSContent();
                  break;
            }
         });
      }

      function toggleFullscreen() {
         if (document.fullscreenElement) {
            document.exitFullscreen();
         } else {
            document.documentElement.requestFullscreen();
         }
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
         log('📺 Exibindo tela de sem conteúdo para canal ' + CONFIG.canalAtual);
      }

      function hideNoContentScreen() {
         document.getElementById('conteudo-principal').classList.remove('hidden');
         document.getElementById('tela-sem-conteudo').classList.add('hidden');
      }

      function updateConnectionStatus(status) {
         const indicator = document.getElementById('connectionIndicator');
         if (connectionStatus !== status) {
            connectionStatus = status;
            indicator.className = status === 'online' ? 'connection-indicator' : 'connection-indicator offline';
            log(status === 'online' ? '🟢 Conexão online' : '🔴 Conexão offline');
         }
      }

      function escapeHtml(text) {
         const div = document.createElement('div');
         div.textContent = text;
         return div.innerHTML;
      }

      function log(message) {
         if (CONFIG.debug) {
            console.log(`[NatterTV-${CONFIG.canalAtual}] ${new Date().toLocaleTimeString()}: ${message}`);
         }
      }

      // Funções para vídeos da sidebar (mantidas para compatibilidade)
      function initializeSidebarVideo() {
         const sidebarVideos = document.querySelectorAll('#sidebar-direita video');

         sidebarVideos.forEach((video, index) => {
            log(`🎬 Inicializando vídeo da sidebar ${index + 1}`);

            // Configurações do vídeo
            video.setAttribute('playsinline', 'true');
            video.setAttribute('webkit-playsinline', 'true');
            video.setAttribute('x-webkit-airplay', 'deny');
            video.setAttribute('disablePictureInPicture', 'true');
            video.muted = true;
            video.loop = true;
            video.autoplay = true;

            // Event listeners
            video.addEventListener('loadedmetadata', function() {
               this.currentTime = 0;
               this.play().catch(e => {
                  log('❌ Erro ao reproduzir vídeo da sidebar: ' + e.message);
                  replaceVideoWithImage(this);
               });
            });

            video.addEventListener('canplay', function() {
               this.play().catch(e => {
                  log('❌ Erro ao reproduzir vídeo da sidebar (canplay): ' + e.message);
                  replaceVideoWithImage(this);
               });
            });

            video.addEventListener('ended', function() {
               this.currentTime = 0;
               this.play().catch(e => {
                  log('❌ Erro ao reiniciar vídeo da sidebar: ' + e.message);
               });
            });

            video.addEventListener('error', function(e) {
               log('❌ Erro no vídeo da sidebar: ' + e.message);
               replaceVideoWithImage(this);
            });

            video.addEventListener('stalled', function() {
               log('⚠️ Vídeo da sidebar travado, tentando reiniciar...');
               this.load();
               setTimeout(() => {
                  this.play().catch(e => {
                     log('❌ Erro ao reiniciar vídeo travado: ' + e.message);
                     replaceVideoWithImage(this);
                  });
               }, 1000);
            });

            // Verificação de reprodução
            setTimeout(() => {
               if (video.paused || video.currentTime === 0) {
                  log('⚠️ Vídeo da sidebar não está reproduzindo, forçando...');
                  video.load();
                  video.play().catch(e => {
                     log('❌ Falha final ao reproduzir vídeo da sidebar: ' + e.message);
                     replaceVideoWithImage(video);
                  });
               }
            }, 3000);

            // Monitor de travamento
            let lastTime = video.currentTime;
            setInterval(() => {
               if (!video.paused && video.currentTime === lastTime && video.readyState > 0) {
                  log('⚠️ Vídeo da sidebar pode estar travado');
                  video.currentTime = video.currentTime + 0.1;
               }
               lastTime = video.currentTime;
            }, 5000);
         });
      }

      function replaceVideoWithImage(videoElement) {
         log('🖼️ Substituindo vídeo por imagem de fallback');
         const img = document.createElement('img');
         img.src = '../assets/images/propaganda.png';
         img.alt = 'Propaganda';
         img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';

         if (videoElement.parentNode) {
            videoElement.parentNode.replaceChild(img, videoElement);
         }
      }

      function detectDevice() {
         const userAgent = navigator.userAgent.toLowerCase();

         if (userAgent.includes('tizen')) {
            log('📱 Samsung Smart TV detectada');
            return 'samsung';
         } else if (userAgent.includes('webos')) {
            log('📱 LG Smart TV detectada');
            return 'lg';
         } else if (userAgent.includes('android tv')) {
            log('📱 Android TV detectada');
            return 'android';
         } else if (userAgent.includes('hbbtv')) {
            log('📱 HbbTV detectada');
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

      // Observer para novos vídeos na sidebar
      const sidebarObserver = new MutationObserver(function(mutations) {
         mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
               if (node.nodeType === Node.ELEMENT_NODE) {
                  const videos = node.querySelectorAll ? node.querySelectorAll('video') : [];
                  if (videos.length > 0 || node.tagName === 'VIDEO') {
                     log('🆕 Novo vídeo detectado na sidebar');
                     setTimeout(initializeSidebarVideo, 500);
                  }
               }
            });
         });
      });

      // Inicialização dos vídeos da sidebar
      document.addEventListener('DOMContentLoaded', function() {
         log('🎬 Inicializando sistema de vídeo da sidebar...');

         applyDeviceSpecificSettings();

         const sidebar = document.getElementById('sidebar-direita');
         if (sidebar) {
            sidebarObserver.observe(sidebar, {
               childList: true,
               subtree: true
            });
         }

         // Monitor de vídeos pausados
         setInterval(() => {
            const videos = document.querySelectorAll('#sidebar-direita video');
            if (videos.length > 0) {
               videos.forEach(video => {
                  if (video.paused && video.readyState >= 3) {
                     log('▶️ Re-iniciando vídeo pausado da sidebar');
                     video.play().catch(e => {
                        log('❌ Erro ao re-iniciar vídeo: ' + e.message);
                     });
                  }
               });
            }
         }, 10000);
      });
   </script>
</body>

</html>