<!DOCTYPE html>
<html lang="pt-BR">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>TV Corporativa - Selecionar Canal</title>
   <link rel="stylesheet" href="../assets/css/tv-style.css">
   <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
   <link rel="shortcut icon" href="../assets/images/favicon.png" type="image/x-icon">
   <style>
      .channel-selector {
         position: fixed;
         top: 0;
         left: 0;
         width: 100vw;
         height: 100vh;
         background: linear-gradient(135deg, #166353 0%, #0d4a3a 100%);
         display: flex;
         align-items: center;
         justify-content: center;
         color: white;
         font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      }

      .selector-container {
         text-align: center;
         max-width: 600px;
         padding: 40px;
         background: rgba(255, 255, 255, 0.1);
         border-radius: 20px;
         backdrop-filter: blur(10px);
         box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
      }

      .selector-header {
         margin-bottom: 40px;
      }

      .selector-header i {
         font-size: 4rem;
         color: #f39c12;
         margin-bottom: 20px;
      }

      .selector-header h1 {
         font-size: 2.5rem;
         margin-bottom: 10px;
         font-weight: 300;
      }

      .selector-header p {
         font-size: 1.2rem;
         opacity: 0.8;
         margin-bottom: 30px;
      }

      .channel-input-container {
         margin-bottom: 30px;
      }

      .channel-input {
         width: 300px;
         padding: 20px;
         font-size: 1.5rem;
         text-align: center;
         border: 3px solid rgba(255, 255, 255, 0.3);
         border-radius: 15px;
         background: rgba(255, 255, 255, 0.1);
         color: white;
         font-weight: 600;
         font-family: 'Courier New', monospace;
         text-transform: uppercase;
         letter-spacing: 2px;
      }

      .channel-input::placeholder {
         color: rgba(255, 255, 255, 0.5);
         font-weight: normal;
      }

      .channel-input:focus {
         outline: none;
         border-color: #f39c12;
         background: rgba(255, 255, 255, 0.2);
         box-shadow: 0 0 20px rgba(243, 156, 18, 0.3);
      }

      .channel-button {
         background: linear-gradient(135deg, #f39c12, #e67e22);
         color: white;
         border: none;
         padding: 15px 40px;
         font-size: 1.2rem;
         border-radius: 10px;
         cursor: pointer;
         margin: 10px;
         transition: all 0.3s ease;
         font-weight: 600;
      }

      .channel-button:hover {
         transform: translateY(-2px);
         box-shadow: 0 8px 25px rgba(243, 156, 18, 0.4);
      }

      .channel-button:active {
         transform: translateY(0);
      }

      .available-channels {
         margin-top: 40px;
      }

      .available-channels h3 {
         font-size: 1.3rem;
         margin-bottom: 20px;
         opacity: 0.9;
      }

      .channels-grid {
         display: grid;
         grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
         gap: 15px;
         max-width: 500px;
         margin: 0 auto;
      }

      .channel-card {
         background: rgba(255, 255, 255, 0.1);
         border: 2px solid rgba(255, 255, 255, 0.2);
         border-radius: 10px;
         padding: 20px;
         cursor: pointer;
         transition: all 0.3s ease;
      }

      .channel-card:hover {
         background: rgba(255, 255, 255, 0.2);
         border-color: #f39c12;
         transform: translateY(-2px);
      }

      .channel-code {
         font-size: 1.5rem;
         font-weight: 600;
         font-family: 'Courier New', monospace;
         color: #f39c12;
         margin-bottom: 8px;
      }

      .channel-content-count {
         font-size: 0.9rem;
         opacity: 0.7;
      }

      .instructions {
         margin-top: 30px;
         opacity: 0.6;
         font-size: 0.9rem;
      }

      .floating-shapes {
         position: absolute;
         top: 0;
         left: 0;
         width: 100%;
         height: 100%;
         overflow: hidden;
         z-index: -1;
      }

      .shape {
         position: absolute;
         opacity: 0.1;
         animation: float 15s infinite ease-in-out;
      }

      .shape:nth-child(1) {
         top: 10%;
         left: 10%;
         width: 100px;
         height: 100px;
         background: #f39c12;
         border-radius: 50%;
         animation-delay: 0s;
      }

      .shape:nth-child(2) {
         top: 60%;
         right: 15%;
         width: 150px;
         height: 150px;
         background: #e67e22;
         border-radius: 30%;
         animation-delay: 5s;
      }

      .shape:nth-child(3) {
         bottom: 20%;
         left: 20%;
         width: 80px;
         height: 80px;
         background: #d35400;
         border-radius: 20%;
         animation-delay: 10s;
      }

      @keyframes float {

         0%,
         100% {
            transform: translateY(0) rotate(0deg);
         }

         50% {
            transform: translateY(-20px) rotate(180deg);
         }
      }
   </style>
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

         // Formatar entrada automaticamente
         channelInput.addEventListener('input', function(e) {
            // Converter para maiúsculo e remover caracteres especiais
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
         });

         // Submeter ao pressionar Enter
         channelInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
               e.preventDefault();
               if (this.value.trim()) {
                  channelForm.submit();
               }
            }
         });

         // Submeter formulário
         channelForm.addEventListener('submit', function(e) {
            const canal = channelInput.value.trim();
            if (!canal) {
               e.preventDefault();
               alert('Digite um código de canal válido');
               channelInput.focus();
            }
         });

         // Auto fullscreen
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

      // Controles de teclado
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