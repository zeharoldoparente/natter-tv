document.addEventListener("DOMContentLoaded", function () {
   initializeFileUpload();
   initializeTableFeatures();
   initializeAlerts();
   initializeTVControl();
});
function initializeFileUpload() {
   const fileInput = document.getElementById("arquivo");

   if (fileInput) {
      fileInput.addEventListener("change", function (e) {
         const file = e.target.files[0];

         if (file) {
            createFilePreview(file);
            adjustDurationField(file);
         }
      });
   }
}

function createFilePreview(file) {
   const previewContainer = document.getElementById("preview-container");

   if (!previewContainer) {
      const container = document.createElement("div");
      container.id = "preview-container";
      container.style.marginTop = "15px";
      container.style.textAlign = "center";

      const fileInput = document.getElementById("arquivo");
      fileInput.parentNode.appendChild(container);
   }

   const container = document.getElementById("preview-container");
   container.innerHTML = "";

   const reader = new FileReader();

   reader.onload = function (e) {
      let previewElement;

      if (file.type.startsWith("image/")) {
         previewElement = document.createElement("img");
         previewElement.src = e.target.result;
         previewElement.style.maxWidth = "200px";
         previewElement.style.maxHeight = "150px";
         previewElement.style.border = "2px solid #ddd";
         previewElement.style.borderRadius = "6px";
      } else if (file.type.startsWith("video/")) {
         previewElement = document.createElement("video");
         previewElement.src = e.target.result;
         previewElement.controls = true;
         previewElement.style.maxWidth = "200px";
         previewElement.style.maxHeight = "150px";
         previewElement.style.border = "2px solid #ddd";
         previewElement.style.borderRadius = "6px";
      }

      if (previewElement) {
         const label = document.createElement("p");
         label.textContent = `Preview: ${file.name}`;
         label.style.marginBottom = "10px";
         label.style.fontWeight = "bold";
         label.style.color = "#2c3e50";

         container.appendChild(label);
         container.appendChild(previewElement);
      }
   };

   reader.readAsDataURL(file);
}
function adjustDurationField(file) {
   const duracaoInput = document.getElementById("duracao");
   const duracaoGroup = duracaoInput.closest(".form-group");

   if (file.type.startsWith("video/")) {
      duracaoGroup.style.display = "none";
      duracaoInput.value = 0;
   } else {
      duracaoGroup.style.display = "flex";
      if (duracaoInput.value == 0) {
         duracaoInput.value = 5;
      }
   }
}

function initializeTableFeatures() {
   const previewVideos = document.querySelectorAll(".preview-video");

   previewVideos.forEach((video) => {
      video.addEventListener("mouseenter", function () {
         this.play();
      });

      video.addEventListener("mouseleave", function () {
         this.pause();
         this.currentTime = 0;
      });
   });
   const deleteButtons = document.querySelectorAll('a[href*="delete"]');

   deleteButtons.forEach((button) => {
      button.addEventListener("click", function (e) {
         e.preventDefault();

         const confirmDelete = confirm(
            "Tem certeza que deseja excluir este arquivo?\n\n" +
               "Esta ação não pode ser desfeita e o arquivo será removido permanentemente."
         );

         if (confirmDelete) {
            this.classList.add("loading");
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            setTimeout(() => {
               window.location.href = this.getAttribute("href");
            }, 500);
         }
      });
   });
}
function initializeAlerts() {
   const alerts = document.querySelectorAll(".alert");

   alerts.forEach((alert) => {
      const closeButton = document.createElement("button");
      closeButton.innerHTML = "&times;";
      closeButton.style.cssText = `
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            margin-left: auto;
            color: inherit;
            opacity: 0.7;
        `;

      closeButton.addEventListener("click", function () {
         alert.style.animation = "fadeOut 0.3s ease-out";
         setTimeout(() => alert.remove(), 300);
      });

      alert.appendChild(closeButton);
      setTimeout(() => {
         if (alert.parentNode) {
            alert.style.animation = "fadeOut 0.3s ease-out";
            setTimeout(() => alert.remove(), 300);
         }
      }, 5000);
   });
}
function initializeTVControl() {
   const updateTVButton = document.querySelector('button[name="atualizar_tv"]');

   if (updateTVButton) {
      updateTVButton.addEventListener("click", function (e) {
         e.preventDefault();
         this.classList.add("loading");
         this.disabled = true;
         fetch(window.location.href, {
            method: "POST",
            headers: {
               "Content-Type": "application/x-www-form-urlencoded",
            },
            body: "atualizar_tv=1",
         })
            .then((response) => response.text())
            .then((data) => {
               this.classList.remove("loading");
               this.disabled = false;
               showNotification(
                  "Sinal de atualização enviado para as TVs!",
                  "success"
               );
               setTimeout(() => {
                  window.location.reload();
               }, 1000);
            })
            .catch((error) => {
               console.error("Erro:", error);
               this.classList.remove("loading");
               this.disabled = false;
               showNotification("Erro ao enviar sinal de atualização", "error");
            });
      });
   }
}

function showNotification(message, type = "info") {
   const notification = document.createElement("div");
   notification.className = `notification notification-${type}`;
   notification.innerHTML = `
        <i class="fas fa-${
           type === "success"
              ? "check-circle"
              : type === "error"
              ? "exclamation-circle"
              : "info-circle"
        }"></i>
        ${message}
    `;

   notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 6px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        animation: slideInRight 0.3s ease-out;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        max-width: 300px;
    `;

   const colors = {
      success: "#27ae60",
      error: "#e74c3c",
      info: "#3498db",
      warning: "#f39c12",
   };

   notification.style.background = colors[type] || colors.info;

   document.body.appendChild(notification);

   setTimeout(() => {
      notification.style.animation = "slideOutRight 0.3s ease-out";
      setTimeout(() => {
         if (notification.parentNode) {
            notification.remove();
         }
      }, 300);
   }, 4000);
}

function validateUploadForm() {
   const form = document.querySelector(".upload-form");

   if (form) {
      form.addEventListener("submit", function (e) {
         const fileInput = document.getElementById("arquivo");
         const file = fileInput.files[0];

         if (!file) {
            e.preventDefault();
            showNotification("Por favor, selecione um arquivo", "error");
            return false;
         }

         const maxSize = 50 * 1024 * 1024;
         if (file.size > maxSize) {
            e.preventDefault();
            showNotification(
               "Arquivo muito grande! Máximo 50MB permitido",
               "error"
            );
            return false;
         }

         const allowedTypes = [
            "image/jpeg",
            "image/jpg",
            "image/png",
            "image/gif",
            "video/mp4",
            "video/avi",
            "video/mov",
            "video/wmv",
         ];

         if (!allowedTypes.includes(file.type)) {
            e.preventDefault();
            showNotification("Tipo de arquivo não permitido!", "error");
            return false;
         }

         const submitButton = form.querySelector('button[type="submit"]');
         submitButton.classList.add("loading");
         submitButton.disabled = true;
      });
   }
}

const additionalCSS = `
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

@keyframes fadeOut {
    from {
        opacity: 1;
    }
    to {
        opacity: 0;
    }
}
`;

const style = document.createElement("style");
style.textContent = additionalCSS;
document.head.appendChild(style);
