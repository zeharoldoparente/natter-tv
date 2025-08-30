class RSSManager {
   constructor(canal) {
      this.canal = canal;
      this.updateInterval = 300000;
      this.timer = null;
      this.data = { rodape: [], topo: [] };
      this.debug = false;
   }

   init() {
      this.log("Inicializando gerenciador RSS para canal: " + this.canal);
      this.updateContent();
      this.startAutoUpdate();
   }

   startAutoUpdate() {
      if (this.timer) {
         clearInterval(this.timer);
      }

      this.timer = setInterval(() => {
         this.updateContent();
      }, this.updateInterval);

      this.log(
         `Auto-atualização RSS configurada para cada ${
            this.updateInterval / 1000
         } segundos`
      );
   }

   async updateContent() {
      try {
         this.log("Buscando conteúdo RSS...");

         const response = await fetch(`get_rss.php?canal=${this.canal}`, {
            cache: "no-cache",
            headers: {
               "Cache-Control": "no-cache",
            },
         });

         if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
         }

         const data = await response.json();

         if (data.error) {
            throw new Error(data.message);
         }

         this.data = data;
         this.renderTickers();

         this.log(
            `RSS atualizado: ${data.total_itens} itens (${data.rodape.length} rodapé, ${data.topo.length} topo)`
         );
      } catch (error) {
         this.log("Erro ao atualizar RSS: " + error.message);
      }
   }

   renderTickers() {
      this.renderPosition("rodape");
      this.renderPosition("topo");
   }

   renderPosition(position) {
      const items = this.data[position] || [];
      const container = document.getElementById(`rss-${position}`);
      const ticker = document.getElementById(`rss-ticker-${position}`);

      if (!container || !ticker) {
         this.log(`Containers RSS não encontrados para posição: ${position}`);
         return;
      }

      if (items.length === 0) {
         container.classList.add("hidden");
         this.log(`Nenhum item RSS para posição: ${position}`);
         return;
      }

      const config = items[0].configuracao;
      this.applyContainerStyles(container, config);
      const tickerHTML = this.generateTickerHTML(items);
      ticker.innerHTML = tickerHTML + tickerHTML;
      this.setupAnimation(ticker, container, config);
      container.classList.remove("hidden");

      this.log(`RSS renderizado para ${position}: ${items.length} itens`);
   }

   applyContainerStyles(container, config) {
      container.style.backgroundColor = config.cor_fundo;
      container.style.color = config.cor_texto;
      container.style.borderColor = this.adjustColorOpacity(
         config.cor_texto,
         0.3
      );
   }

   generateTickerHTML(items) {
      return items
         .map(
            (item) => `
            <div class="rss-item" title="${this.escapeHtml(item.titulo)}">
                <span class="rss-feed-label">${this.escapeHtml(
                   item.feed_nome
                )}</span>
                ${this.escapeHtml(item.texto)}
            </div>
        `
         )
         .join("");
   }

   setupAnimation(ticker, container, config) {
      setTimeout(() => {
         const fullWidth = ticker.scrollWidth;
         const contentWidth = fullWidth / 2;
         const containerWidth = container.offsetWidth;

         if (contentWidth <= containerWidth) {
            ticker.style.animation = "none";
            ticker.style.transform = "none";
            return;
         }

         const totalDistance = tickerWidth + containerWidth;
         const duration = contentWidth / config.velocidade_scroll;
         ticker.style.animationDuration = duration + "s";
         ticker.style.animationName = "scroll-horizontal";
         ticker.style.animationTimingFunction = "linear";
         ticker.style.animationIterationCount = "infinite";

         this.log(
            `Animação configurada: ${contentWidth}px, duração: ${duration}s, velocidade: ${config.velocidade_scroll}px/s`
         );
      }, 100);
   }

   adjustColorOpacity(color, opacity) {
      const hex = color.replace("#", "");
      const r = parseInt(hex.substr(0, 2), 16);
      const g = parseInt(hex.substr(2, 2), 16);
      const b = parseInt(hex.substr(4, 2), 16);
      return `rgba(${r}, ${g}, ${b}, ${opacity})`;
   }

   escapeHtml(text) {
      if (!text) return "";

      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
   }

   pause() {
      if (this.timer) {
         clearInterval(this.timer);
         this.timer = null;
         this.log("RSS pausado");
      }
   }

   resume() {
      if (!this.timer) {
         this.startAutoUpdate();
         this.log("RSS retomado");
      }
   }

   forceUpdate() {
      this.log("Forçando atualização RSS...");
      this.updateContent();
   }

   setDebug(enabled) {
      this.debug = enabled;
      this.log("Debug " + (enabled ? "ativado" : "desativado"));
   }

   setSpeed(position, speed) {
      const ticker = document.getElementById(`rss-ticker-${position}`);
      if (!ticker) return;

      const fullWidth = ticker.scrollWidth;
      const contentWidth = fullWidth / 2;
      const duration = contentWidth / speed;

      ticker.style.animation = `scroll-horizontal ${duration}s linear infinite`;
      this.log(`Velocidade alterada para ${position}: ${speed}px/s`);
   }

   setColors(position, textColor, backgroundColor) {
      const container = document.getElementById(`rss-${position}`);
      if (!container) return;

      container.style.color = textColor;
      container.style.backgroundColor = backgroundColor;
      container.style.borderColor = this.adjustColorOpacity(textColor, 0.3);

      this.log(
         `Cores alteradas para ${position}: ${textColor} / ${backgroundColor}`
      );
   }

   getStats() {
      return {
         canal: this.canal,
         total_itens: this.data.rodape.length + this.data.topo.length,
         rodape_itens: this.data.rodape.length,
         topo_itens: this.data.topo.length,
         update_interval: this.updateInterval,
         timer_active: this.timer !== null,
      };
   }

   log(message) {
      if (this.debug) {
         console.log(
            `[RSS-${this.canal}] ${new Date().toLocaleTimeString()}: ${message}`
         );
      }
   }

   destroy() {
      if (this.timer) {
         clearInterval(this.timer);
         this.timer = null;
      }

      ["rodape", "topo"].forEach((position) => {
         const container = document.getElementById(`rss-${position}`);
         if (container) {
            container.classList.add("hidden");
         }
      });

      this.log("RSS Manager destruído");
   }
}

window.RSSUtils = {
   validateFeedURL: function (url) {
      try {
         new URL(url);
         return (
            url.match(/\.(xml|rss|atom)$/i) ||
            url.includes("rss") ||
            url.includes("feed") ||
            url.includes("atom")
         );
      } catch {
         return false;
      }
   },

   formatDisplayText: function (title, description, maxLength = 200) {
      let text = title;

      if (description && title.length < 100) {
         text += " - " + description;
      }

      if (text.length > maxLength) {
         text = text.substring(0, maxLength - 3) + "...";
      }

      return text;
   },

   testFeed: async function (url) {
      try {
         const response = await fetch(
            `test_rss.php?url=${encodeURIComponent(url)}`
         );
         const result = await response.json();
         return result;
      } catch (error) {
         return { success: false, message: error.message };
      }
   },

   calculateOptimalSpeed: function (textLength, containerWidth) {
      const baseSpeed = 50;
      const adjustment = Math.min(textLength / 1000, 0.5);
      return Math.round(baseSpeed * (1 + adjustment));
   },
};

window.RSSManager = RSSManager;
