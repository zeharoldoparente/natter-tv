<!DOCTYPE html>
<html lang="pt-BR">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>TV Corporativa - Selecionar Canal</title>
   <link rel="stylesheet" href="../assets/css/base.css">
   <link rel="stylesheet" href="../assets/css/tv-style.css">
   <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
   <link rel="shortcut icon" href="../assets/images/favicon.png" type="image/x-icon">
</head>

<body>
   <div class="channel-selector">
      <div class="floating-shapes">
         <div class="shape"></div>
         <div class="shape"></div>
         <div class="shape"></div>
      </div>

      <div class="selector-container">
         <div class="selector-header">
            <i class="fas fa-tv"></i>
            <h1>NatterTV</h1>
            <p>Selecione ou digite o código do canal</p>
         </div>

         <form method="GET" action="index.php" id="channelForm">
            <div class="channel-input-container">
               <input type="text"
                  name="canal"
                  id="channelInput"
                  class="channel-input"
                  placeholder="Ex: 1234"
                  maxlength="10"
                  pattern="[A-Za-z0-9]{1,10}"
                  autocomplete="off"
                  autofocus>
            </div>

            <button type="submit" class="channel-button">
               <i class="fas fa-play"></i> Acessar Canal
            </button>
         </form>

         <?php if (!empty($canais_disponiveis)): ?>
            <div class="available-channels">
               <h3><i class="fas fa-list"></i> Canais Disponíveis</h3>
               <div class="channels-grid">
                  <?php foreach ($canais_disponiveis as $canal): ?>
                     <div class="channel-card" onclick="selectChannel('<?php echo htmlspecialchars($canal['codigo_canal']); ?>')">
                        <div class="channel-code"><?php echo htmlspecialchars($canal['codigo_canal']); ?></div>
                        <div class="channel-content-count">
                           <?php echo $canal['total_conteudos']; ?> conteúdo<?php echo $canal['total_conteudos'] != 1 ? 's' : ''; ?>
                        </div>
                     </div>
                  <?php endforeach; ?>
               </div>
            </div>
         <?php endif; ?>

         <div class="instructions">
            <p><i class="fas fa-keyboard"></i> Digite o código e pressione Enter</p>
            <p><i class="fas fa-mouse"></i> Ou clique em um canal disponível</p>
         </div>
      </div>
   </div>

   <script>
      document.addEventListener('DOMContentLoaded', function() {
         const channelInput = document.getElementById('channelInput');
         const channelForm = document.getElementById('channelForm');
         channelInput.addEventListener('input', function(e) {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
         });
         channelInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
               e.preventDefault();
               if (this.value.trim()) {
                  channelForm.submit();
               }
            }
         });
         channelForm.addEventListener('submit', function(e) {
            const canal = channelInput.value.trim();
            if (!canal) {
               e.preventDefault();
               alert('Digite um código de canal válido');
               channelInput.focus();
            }
         });
         document.addEventListener('click', function() {
            if (!document.fullscreenElement) {
               document.documentElement.requestFullscreen().catch(err => {
                  console.log('Erro ao entrar em fullscreen:', err);
               });
            }
         });
      });

      function selectChannel(channelCode) {
         document.getElementById('channelInput').value = channelCode;
         document.getElementById('channelForm').submit();
      }
      document.addEventListener('keydown', function(e) {
         switch (e.key) {
            case 'Escape':
               if (document.fullscreenElement) {
                  document.exitFullscreen();
               }
               break;
            case 'F11':
               e.preventDefault();
               if (document.fullscreenElement) {
                  document.exitFullscreen();
               } else {
                  document.documentElement.requestFullscreen();
               }
               break;
         }
      });
   </script>
</body>

</html>