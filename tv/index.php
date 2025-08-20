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
   <link rel="stylesheet" href="../assets/css/tv-style.css">
   <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
   <link rel="shortcut icon" href="../assets/images/favicon.png" type="image/x-icon">
</head>

<body>
   <div id="conteudo-principal">
      <div id="media-container"></div>

      <!-- RSS Container Topo -->
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
         <!-- RSS Container Rodapé -->
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
         rssUpdateInterval: 300000, // 5 minutos
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

      document.addEventListener('DOMContentLoaded', function() {
         initializeTV();
      });

      function initializeTV() {
         log('Inicializando TV Corporativa - Canal: ' + CONFIG.canalAtual);

         updateDateTime();
         setInterval(updateDateTime, 1000);

         // Inicializar RSS imediatamente
         initializeRSS();

         if (conteudos.length === 0 || conteudos[0].id === 0) {
            showNoContentScreen();
         } else {
            startPlayback();
         }

         setupUpdateChecker();
         setupKeyboardControls();

         log('TV inicializada com sucesso para o canal ' + CONFIG.canalAtual);
      }

      function initializeRSS() {
         // Carregar RSS imediatamente ao inicializar
         updateRSSContent();

         // Configurar atualização periódica
         if (rssTimer) {
            clearInterval(rssTimer);
         }
         rssTimer = setInterval(updateRSSContent, CONFIG.rssUpdateInterval);
         log('Sistema RSS inicializado e carregado');
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
         // Configurar RSS do rodapé
         if (rssData.rodape && rssData.rodape.length > 0) {
            const rodapeContainer = document.getElementById('rss-rodape');
            const rodapeTicker = document.getElementById('rss-ticker-rodape');

            setupRSSPosition(rssData.rodape, rodapeTicker, rodapeContainer);
            rodapeContainer.classList.remove('hidden');
         } else {
            document.getElementById('rss-rodape').classList.add('hidden');
         }

         // Configurar RSS do topo
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

         // Usar configuração do primeiro item
         const config = items[0].configuracao;

         // Aplicar cores
         container.style.backgroundColor = config.cor_fundo;
         container.style.color = config.cor_texto;

         // Criar conteúdo HTML com separadores limpos
         let tickerHTML = '';
         items.forEach((item, index) => {
            // Adicionar apenas o texto da notícia, sem o nome do feed
            tickerHTML += `<span class="rss-item">${escapeHtml(item.texto)}</span>`;

            // Adicionar separador entre notícias (exceto depois da última)
            if (index < items.length - 1) {
               tickerHTML += `<span class="rss-separator"> | </span>`;
            }
         });

         // Duplicar o conteúdo para criar loop contínuo
         tickerHTML = tickerHTML + `<span class="rss-separator"> | </span>` + tickerHTML;

         ticker.innerHTML = tickerHTML;

         // CORREÇÃO DA VELOCIDADE: Limpar animações anteriores
         ticker.style.animation = 'none';
         ticker.style.transform = 'translateX(0)';

         // Aguardar o DOM atualizar para calcular larguras
         setTimeout(() => {
            const fullWidth = ticker.scrollWidth;
            const contentWidth = fullWidth / 2; // largura original antes da duplicação
            const containerWidth = container.offsetWidth;

            if (contentWidth <= containerWidth) {
               ticker.style.animation = "none";
               ticker.style.transform = "none";
               return;
            }

            // CORREÇÃO: Forçar recálculo e aplicar nova animação
            ticker.offsetHeight; // Força reflow

            // Calcular duração baseada na velocidade configurada (px/s)
            const velocidade = Math.max(config.velocidade_scroll, 10); // Mínimo 10px/s
            const duration = contentWidth / velocidade;

            // CORREÇÃO: Criar animação única para cada ticker
            const animationName = `scroll-horizontal-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;

            // Remover animação anterior se existir
            const existingStyle = document.getElementById(`rss-animation-${container.id}`);
            if (existingStyle) {
               existingStyle.remove();
            }

            // Criar nova animação CSS
            const style = document.createElement('style');
            style.id = `rss-animation-${container.id}`;
            style.textContent = `
               @keyframes ${animationName} {
                  0% {
                     transform: translateX(0);
                  }
                  100% {
                     transform: translateX(-${contentWidth}px);
                  }
               }
            `;
            document.head.appendChild(style);

            // Aplicar nova animação
            ticker.style.animation = `${animationName} ${duration}s linear infinite`;

            log(`RSS configurado: ${items.length} itens, velocidade: ${velocidade}px/s, duração: ${duration.toFixed(2)}s, largura: ${contentWidth}px`);
         }, 100);
      }

      function escapeHtml(text) {
         const div = document.createElement('div');
         div.textContent = text;
         return div.innerHTML;
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
                  }, 2000);
               }
            })
            .catch(error => {
               checkContentUpdates();
            });
      }

      function checkContentUpdates() {
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

         log('Exibindo tela de sem conteúdo para canal ' + CONFIG.canalAtual);
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
         const horarioElements = document.querySelectorAll('#horario');
         const dataElements = document.querySelectorAll('#data');

         horarioElements.forEach(element => {
            element.textContent = now.toLocaleTimeString('pt-BR', timeOptions);
         });

         dataElements.forEach(element => {
            element.textContent = now.toLocaleDateString('pt-BR', dateOptions);
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

      function log(message) {
         if (CONFIG.debug) {
            console.log(`[TV-${CONFIG.canalAtual}] ${new Date().toLocaleTimeString()}: ${message}`);
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