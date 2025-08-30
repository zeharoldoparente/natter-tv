document.addEventListener("DOMContentLoaded", function () {
   initializeUpload();
});

function initializeUpload() {
   const dropZone = document.getElementById("dropZone");
   const fileInput = document.getElementById("arquivo");
   const uploadForm = document.getElementById("uploadForm");
   const tipoConteudo = document.getElementById("tipo_conteudo");

   setupDragAndDrop(dropZone, fileInput);

   if (tipoConteudo) {
      tipoConteudo.addEventListener("change", toggleFields);
      toggleFields();
   }

   fileInput.addEventListener("change", handleFileSelect);
   uploadForm.addEventListener("submit", handleFormSubmit);
}

function setupDragAndDrop(dropZone, fileInput) {
   ["dragenter", "dragover", "dragleave", "drop"].forEach((eventName) => {
      dropZone.addEventListener(eventName, preventDefaults, false);
      document.body.addEventListener(eventName, preventDefaults, false);
   });

   ["dragenter", "dragover"].forEach((eventName) => {
      dropZone.addEventListener(eventName, highlight, false);
   });

   ["dragleave", "drop"].forEach((eventName) => {
      dropZone.addEventListener(eventName, unhighlight, false);
   });

   dropZone.addEventListener("drop", handleDrop, false);

   function preventDefaults(e) {
      e.preventDefault();
      e.stopPropagation();
   }

   function highlight(e) {
      dropZone.classList.add("dragover");
   }

   function unhighlight(e) {
      dropZone.classList.remove("dragover");
   }

   function handleDrop(e) {
      const dt = e.dataTransfer;
      const files = dt.files;

      if (files.length > 0) {
         fileInput.files = files;
         handleFileSelect({ target: { files: files } });
      }
   }
}

function handleFileSelect(e) {
   const file = e.target.files[0];

   if (!file) {
      hideFilePreview();
      return;
   }

   if (!validateFile(file)) {
      return;
   }

   showFilePreview(file);
   adjustFormFields(file);

   if (file.type.startsWith("video/")) {
      detectVideoDuration(file);
   } else {
      hideVideoDurationInfo();
   }
}

function validateFile(file) {
   const maxSize = 83886080;
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

   if (file.size > maxSize) {
      showAlert("Arquivo muito grande! Máximo permitido: 80MB", "error");
      resetFileInput();
      return false;
   }

   if (!allowedTypes.includes(file.type)) {
      showAlert("Tipo de arquivo não permitido!", "error");
      resetFileInput();
      return false;
   }

   return true;
}

function showFilePreview(file) {
   const preview = document.getElementById("filePreview");
   const mediaContainer = preview.querySelector(".preview-media");
   const fileName = document.getElementById("fileName");
   const fileSize = document.getElementById("fileSize");
   const fileType = document.getElementById("fileType");

   mediaContainer.innerHTML = "";
   fileName.textContent = file.name;
   fileSize.textContent = formatFileSize(file.size);
   fileType.textContent = getFileTypeLabel(file.type);

   const reader = new FileReader();

   reader.onload = function (e) {
      let mediaElement;

      if (file.type.startsWith("image/")) {
         mediaElement = document.createElement("img");
         mediaElement.src = e.target.result;
      } else if (file.type.startsWith("video/")) {
         mediaElement = document.createElement("video");
         mediaElement.src = e.target.result;
         mediaElement.controls = true;
         mediaElement.muted = true;
      }

      if (mediaElement) {
         mediaContainer.appendChild(mediaElement);
      }
   };

   reader.readAsDataURL(file);
   preview.classList.remove("hidden");
}

function hideFilePreview() {
   const preview = document.getElementById("filePreview");
   preview.classList.add("hidden");
}

function toggleFields() {
   const tipo = document.getElementById("tipo_conteudo")?.value;
   const canalGroup = document.getElementById("canalGroup");
   const codigoInput = document.getElementById("codigo_canal");
   const durationGroup = document.getElementById("durationGroup");

   if (tipo === "lateral") {
      if (canalGroup) canalGroup.style.display = "none";
      if (durationGroup) durationGroup.style.display = "none";
      if (codigoInput) codigoInput.removeAttribute("required");
   } else {
      if (canalGroup) canalGroup.style.display = "block";
      if (durationGroup) durationGroup.style.display = "flex";
      if (codigoInput) codigoInput.setAttribute("required", "required");
   }
}

function adjustFormFields(file) {
   const durationGroup = document.getElementById("durationGroup");
   const durationInput = document.getElementById("duracao");
   const durationHelp = document.getElementById("durationHelp");

   const tipo = document.getElementById("tipo_conteudo")?.value;
   if (tipo === "lateral") {
      durationGroup.style.display = "none";
      durationInput.value = 0;
      durationInput.removeAttribute("required");
      durationInput.disabled = true;
      return;
   }

   if (file.type.startsWith("video/")) {
      durationGroup.classList.add("disabled");
      durationInput.value = 0;
      durationInput.removeAttribute("required");
      durationInput.disabled = true;
      durationHelp.innerHTML =
         '<span style="color: var(--warning-color);"><i class="fas fa-video"></i> Vídeo detectado - duração será calculada automaticamente</span>';
   } else {
      durationGroup.classList.remove("disabled");
      durationInput.disabled = false;
      durationInput.setAttribute("required", "required");
      if (durationInput.value == 0) {
         durationInput.value = 5;
      }
      durationHelp.innerHTML =
         '<i class="fas fa-image"></i> Defina quantos segundos a imagem ficará na tela';
   }
}

function detectVideoDuration(file) {
   const fileDurationElement = document.getElementById("fileDuration");

   if (!fileDurationElement) return;

   fileDurationElement.innerHTML =
      '<i class="fas fa-spinner fa-spin" style="color: var(--warning-color);"></i> Detectando duração do vídeo...';
   fileDurationElement.classList.remove("hidden");

   const video = document.createElement("video");
   video.preload = "metadata";

   video.onloadedmetadata = function () {
      const duration = Math.round(video.duration);
      const formattedDuration = formatDurationDisplay(duration, "video");

      fileDurationElement.innerHTML = `
         <i class="fas fa-clock" style="color: var(--warning-color);"></i> 
         Duração detectada: <strong style="color: var(--warning-color);">${formattedDuration}</strong>
         <small style="display: block; margin-top: 4px; color: #666;">
            (${duration} segundos total)
         </small>
      `;

      URL.revokeObjectURL(video.src);
   };

   video.onerror = function () {
      fileDurationElement.innerHTML = `
         <i class="fas fa-exclamation-triangle" style="color: var(--warning-color);"></i> 
         Não foi possível detectar a duração
         <small style="display: block; margin-top: 4px; color: #666;">
            A duração será detectada no servidor
         </small>
      `;

      URL.revokeObjectURL(video.src);
   };

   video.src = URL.createObjectURL(file);
}

function hideVideoDurationInfo() {
   const fileDurationElement = document.getElementById("fileDuration");
   if (fileDurationElement) {
      fileDurationElement.classList.add("hidden");
   }
}

function formatDurationDisplay(seconds, type) {
   if (type === "imagem") {
      return `00:${String(seconds).padStart(2, "0")}`;
   }

   const minutes = Math.floor(seconds / 60);
   const remainingSeconds = seconds % 60;
   return `${minutes}:${String(remainingSeconds).padStart(2, "0")}`;
}

function handleFormSubmit(e) {
   e.preventDefault();

   const fileInput = document.getElementById("arquivo");
   const codigoCanalInput = document.getElementById("codigo_canal");
   const submitBtn = document.getElementById("submitBtn");
   const progressContainer = document.getElementById("progressContainer");

   if (!fileInput.files[0]) {
      showAlert("Por favor, selecione um arquivo", "error");
      return;
   }

   if (codigoCanalInput && !codigoCanalInput.value.trim()) {
      showAlert("Por favor, digite o código do canal", "error");
      codigoCanalInput.focus();
      return;
   }

   const formData = new FormData(e.target);

   submitBtn.disabled = true;
   submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
   progressContainer.classList.remove("hidden");

   const xhr = new XMLHttpRequest();

   xhr.upload.addEventListener("progress", function (e) {
      if (e.lengthComputable) {
         const percentComplete = (e.loaded / e.total) * 100;
         updateProgress(percentComplete);
      }
   });

   xhr.addEventListener("load", function () {
      if (xhr.status === 200) {
         showAlert("Arquivo enviado com sucesso!", "success");
         resetForm();
         setTimeout(() => {
            window.location.reload();
         }, 2000);
      } else {
         showAlert("Erro ao enviar arquivo", "error");
      }
      resetUploadState();
   });

   xhr.addEventListener("error", function () {
      showAlert("Erro de conexão ao enviar arquivo", "error");
      resetUploadState();
   });

   xhr.open("POST", "upload.php");
   xhr.send(formData);
}

function updateProgress(percent) {
   const progressFill = document.getElementById("progressFill");
   const progressText = document.getElementById("progressText");

   progressFill.style.width = percent + "%";
   progressText.textContent = Math.round(percent) + "%";
}

function resetUploadState() {
   const submitBtn = document.getElementById("submitBtn");
   const progressContainer = document.getElementById("progressContainer");

   submitBtn.innerHTML = '<i class="fas fa-upload"></i> Enviar Arquivo';
   progressContainer.classList.add("hidden");

   updateProgress(0);
}

function removeFile() {
   resetFileInput();
   hideFilePreview();
   hideVideoDurationInfo();

   const durationGroup = document.getElementById("durationGroup");
   const durationInput = document.getElementById("duracao");
   const durationHelp = document.getElementById("durationHelp");

   if (durationGroup) {
      durationGroup.style.display = "flex";
      durationGroup.classList.remove("disabled");
   }

   if (durationInput) {
      durationInput.disabled = false;
      durationInput.setAttribute("required", "required");
      durationInput.value = 5;
   }

   if (durationHelp) {
      durationHelp.innerHTML =
         "Apenas para imagens. Vídeos usam duração natural.";
   }
}

function resetFileInput() {
   const fileInput = document.getElementById("arquivo");
   fileInput.value = "";
}

function resetForm() {
   document.getElementById("uploadForm").reset();
   hideFilePreview();
   hideVideoDurationInfo();
   resetFileInput();

   const durationGroup = document.getElementById("durationGroup");
   const durationInput = document.getElementById("duracao");
   const durationHelp = document.getElementById("durationHelp");

   if (durationGroup) {
      durationGroup.style.display = "flex";
      durationGroup.classList.remove("disabled");
   }

   if (durationInput) {
      durationInput.disabled = false;
      durationInput.setAttribute("required", "required");
      durationInput.value = 5;
   }

   if (durationHelp) {
      durationHelp.innerHTML =
         "Apenas para imagens. Vídeos usam duração natural.";
   }
}

function showAlert(message, type = "info") {
   const existingAlerts = document.querySelectorAll(".alert-dynamic");
   existingAlerts.forEach((alert) => alert.remove());

   const alert = document.createElement("div");
   alert.className = `alert alert-${type} alert-dynamic`;

   const icon =
      type === "success"
         ? "check-circle"
         : type === "error"
         ? "exclamation-circle"
         : "info-circle";

   alert.innerHTML = `
        <i class="fas fa-${icon}"></i> ${message}
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;

   const content = document.querySelector(".content");
   content.insertBefore(alert, content.firstChild);

   setTimeout(() => {
      if (alert.parentNode) {
         alert.remove();
      }
   }, 5000);
}

function formatFileSize(bytes) {
   if (bytes === 0) return "0 Bytes";

   const k = 1024;
   const sizes = ["Bytes", "KB", "MB", "GB"];
   const i = Math.floor(Math.log(bytes) / Math.log(k));

   return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
}

function getFileTypeLabel(mimeType) {
   if (mimeType.startsWith("image/")) {
      return "Imagem";
   } else if (mimeType.startsWith("video/")) {
      return "Vídeo";
   }

   return "Arquivo";
}

function formatDurationDisplay(seconds, type) {
   if (type === "imagem" || type === "image") {
      return `00:${String(seconds).padStart(2, "0")}`;
   }

   const hours = Math.floor(seconds / 3600);
   const minutes = Math.floor((seconds % 3600) / 60);
   const remainingSeconds = seconds % 60;

   if (hours > 0) {
      return `${hours}:${String(minutes).padStart(2, "0")}:${String(
         remainingSeconds
      ).padStart(2, "0")}`;
   } else {
      return `${minutes}:${String(remainingSeconds).padStart(2, "0")}`;
   }
}

function detectVideoDuration(file) {
   const fileDurationElement = document.getElementById("fileDuration");
   const durationHelp = document.getElementById("durationHelp");

   if (!fileDurationElement) return;

   fileDurationElement.innerHTML = `
      <i class="fas fa-spinner fa-spin" style="color: var(--warning-color);"></i> 
      Analisando vídeo...
   `;
   fileDurationElement.classList.remove("hidden");

   const video = document.createElement("video");
   video.preload = "metadata";
   video.muted = true;

   video.onloadedmetadata = function () {
      const duration = Math.round(video.duration);

      if (duration > 0) {
         const formattedDuration = formatDurationDisplay(duration, "video");

         fileDurationElement.innerHTML = `
            <i class="fas fa-video" style="color: var(--warning-color);"></i> 
            <strong style="color: var(--warning-color);">${formattedDuration}</strong>
            <small style="display: block; margin-top: 4px; color: #666; font-size: 0.8rem;">
               Duração detectada: ${duration} segundos
            </small>
         `;

         if (durationHelp) {
            durationHelp.innerHTML = `
               <span style="color: var(--warning-color);">
                  <i class="fas fa-video"></i> Vídeo detectado - será exibido por ${formattedDuration}
               </span>
            `;
         }
      } else {
         fileDurationElement.innerHTML = `
            <i class="fas fa-exclamation-triangle" style="color: var(--warning-color);"></i> 
            Duração não detectada
            <small style="display: block; margin-top: 4px; color: #666; font-size: 0.8rem;">
               Será calculada no servidor durante o upload
            </small>
         `;
      }

      URL.revokeObjectURL(video.src);
   };

   video.onerror = function () {
      fileDurationElement.innerHTML = `
         <i class="fas fa-exclamation-triangle" style="color: var(--warning-color);"></i> 
         Erro ao analisar vídeo
         <small style="display: block; margin-top: 4px; color: #666; font-size: 0.8rem;">
            A duração será detectada durante o upload
         </small>
      `;
      URL.revokeObjectURL(video.src);
   };

   video.src = URL.createObjectURL(file);
}

function hideVideoDurationInfo() {
   const fileDurationElement = document.getElementById("fileDuration");
   const durationHelp = document.getElementById("durationHelp");

   if (fileDurationElement) {
      fileDurationElement.classList.add("hidden");
   }

   if (durationHelp) {
      durationHelp.innerHTML =
         "Apenas para imagens. Vídeos usam duração natural.";
   }
}
const additionalCSS = `
.alert-dynamic {
    position: relative;
    margin-bottom: 20px;
    animation: slideDown 0.3s ease-out;
}

.alert-close {
    position: absolute;
    top: 15px;
    right: 15px;
    background: none;
    border: none;
    color: inherit;
    opacity: 0.7;
    cursor: pointer;
    font-size: 1.2rem;
}

.alert-close:hover {
    opacity: 1;
}

.file-duration {
    padding: 10px 15px;
    background: rgba(243, 156, 18, 0.1);
    border-radius: 8px;
    border-left: 4px solid var(--warning-color);
    font-weight: 500;
    color: var(--warning-color);
    margin-top: 8px;
    font-size: 0.9rem;
    line-height: 1.4;
    animation: slideDown 0.3s ease-out;
}

.file-duration i {
    margin-right: 8px;
}

.form-group.disabled {
    opacity: 0.6;
    pointer-events: none;
}

.form-group.disabled label {
    color: #999 !important;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

.detecting-duration {
    animation: pulse 1.5s infinite;
}
`;

const style = document.createElement("style");
style.textContent = additionalCSS;
document.head.appendChild(style);
