document.addEventListener("DOMContentLoaded", function () {
   initializeUpload();
});

function initializeUpload() {
   const dropZone = document.getElementById("dropZone");
   const fileInput = document.getElementById("arquivo");
   const uploadForm = document.getElementById("uploadForm");
   const tipoConteudo = document.getElementById("tipo_conteudo");

   if (!dropZone || !fileInput || !uploadForm) {
      return;
   }

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
}

function validateFile(file) {
   const maxSize = 52428800;
   const allowedTypes = [
      "image/jpeg",
      "image/jpg",
      "image/png",
      "image/gif",
      "image/webp",
      "image/bmp",
      "video/mp4",
      "video/avi",
      "video/mov",
      "video/wmv",
      "video/flv",
      "video/webm",
      "video/mkv",
   ];

   if (file.size > maxSize) {
      showAlert("Arquivo muito grande! Máximo permitido: 50MB", "error");
      resetFileInput();
      return false;
   }

   if (!allowedTypes.includes(file.type)) {
      showAlert(`Tipo de arquivo não permitido: ${file.type}`, "error");
      resetFileInput();
      return false;
   }

   return true;
}

function showFilePreview(file) {
   console.log("Iniciando preview do arquivo:", file.name, file.type);
   let preview = document.getElementById("filePreview");
   if (!preview) {
      preview = createPreviewContainer();
   }

   const mediaContainer = preview.querySelector(".preview-media");
   const fileName =
      preview.querySelector("#fileName") || preview.querySelector(".file-name");
   const fileSize =
      preview.querySelector("#fileSize") || preview.querySelector(".file-size");
   const fileType =
      preview.querySelector("#fileType") || preview.querySelector(".file-type");
   if (mediaContainer) {
      mediaContainer.innerHTML = "";
   }
   if (fileName) fileName.textContent = file.name;
   if (fileSize) fileSize.textContent = formatFileSize(file.size);
   if (fileType) fileType.textContent = getFileTypeLabel(file.type);
   const reader = new FileReader();

   reader.onload = function (e) {
      console.log("Arquivo carregado para preview");

      let mediaElement;
      const dataUrl = e.target.result;

      if (file.type.startsWith("image/")) {
         mediaElement = document.createElement("img");
         mediaElement.src = dataUrl;
         mediaElement.alt = file.name;
         mediaElement.style.cssText = `
            max-width: 150px;
            max-height: 120px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #ddd;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
         `;
      } else if (file.type.startsWith("video/")) {
         mediaElement = document.createElement("video");
         mediaElement.src = dataUrl;
         mediaElement.controls = true;
         mediaElement.muted = true;
         mediaElement.preload = "metadata";
         mediaElement.style.cssText = `
            max-width: 150px;
            max-height: 120px;
            border-radius: 8px;
            border: 2px solid #ddd;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
         `;
      }

      if (mediaElement && mediaContainer) {
         mediaContainer.appendChild(mediaElement);
         console.log("Elemento de mídia adicionado ao container");
      }
   };

   reader.onerror = function () {
      console.error("Erro ao carregar arquivo para preview");
      showAlert("Erro ao carregar preview do arquivo", "error");
   };
   reader.readAsDataURL(file);
   preview.classList.remove("hidden");
   console.log("Preview container mostrado");
}

function createPreviewContainer() {
   console.log("Criando container de preview");

   const preview = document.createElement("div");
   preview.id = "filePreview";
   preview.className = "file-preview";
   preview.innerHTML = `
      <div class="preview-content">
         <div class="preview-media"></div>
         <div class="preview-info">
            <h5 class="file-name" id="fileName"></h5>
            <p class="file-size" id="fileSize"></p>
            <p class="file-type" id="fileType"></p>
         </div>
         <button type="button" class="btn-remove-file" onclick="removeFile()">
            <i class="fas fa-times"></i>
         </button>
      </div>
   `;
   if (!document.getElementById("preview-styles")) {
      const style = document.createElement("style");
      style.id = "preview-styles";
      style.textContent = `
         .file-preview {
            margin: 20px 0;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            padding: 20px;
            background: #f8f9fa;
            animation: slideDown 0.3s ease-out;
         }
         
         .preview-content {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
         }
         
         .preview-media {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 150px;
            min-height: 120px;
            background: #fff;
            border-radius: 8px;
            border: 2px dashed #ddd;
         }
         
         .preview-info {
            flex-grow: 1;
         }
         
         .preview-info h5 {
            margin: 0 0 8px 0;
            color: #166353;
            font-size: 1rem;
            word-break: break-word;
         }
         
         .preview-info p {
            margin: 4px 0;
            color: #666;
            font-size: 0.9rem;
         }
         
         .btn-remove-file {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e74c3c;
            color: white;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: all 0.3s ease;
            z-index: 10;
         }
         
         .btn-remove-file:hover {
            background: #c0392b;
            transform: scale(1.1);
         }
         
         .hidden {
            display: none !important;
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
      `;
      document.head.appendChild(style);
   }
   const dropZone = document.getElementById("dropZone");
   if (dropZone && dropZone.parentNode) {
      dropZone.parentNode.insertBefore(preview, dropZone.nextSibling);
   } else {
      const form = document.querySelector(".upload-form");
      if (form) {
         form.appendChild(preview);
      }
   }

   return preview;
}

function hideFilePreview() {
   const preview = document.getElementById("filePreview");
   if (preview) {
      preview.classList.add("hidden");
   }
}

function toggleFields() {
   const tipo = document.getElementById("tipo_conteudo");
   if (!tipo) return;

   const canalGroup = document.getElementById("canalGroup");
   const codigoInput = document.getElementById("codigo_canal");
   const durationGroup = document.getElementById("durationGroup");

   if (tipo.value === "lateral") {
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

   const tipo = document.getElementById("tipo_conteudo");
   if (tipo && tipo.value === "lateral") {
      if (durationGroup) durationGroup.style.display = "none";
      if (durationInput) {
         durationInput.value = 0;
         durationInput.removeAttribute("required");
         durationInput.disabled = true;
      }
      return;
   }

   if (file.type.startsWith("video/")) {
      if (durationGroup) durationGroup.style.display = "none";
      if (durationInput) {
         durationInput.value = 0;
         durationInput.removeAttribute("required");
         durationInput.disabled = true;
      }
   } else {
      if (durationGroup) durationGroup.style.display = "flex";
      if (durationInput) {
         durationInput.disabled = false;
         if (durationInput.value == 0) {
            durationInput.value = 5;
         }
      }
   }
}

function handleFormSubmit(e) {
   e.preventDefault();

   const fileInput = document.getElementById("arquivo");
   const submitBtn = document.getElementById("submitBtn");
   const progressContainer = document.getElementById("progressContainer");

   if (!fileInput.files[0]) {
      showAlert("Por favor, selecione um arquivo", "error");
      return;
   }

   const formData = new FormData(e.target);
   if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML =
         '<i class="fas fa-spinner fa-spin"></i> Enviando...';
   }

   if (progressContainer) {
      progressContainer.classList.remove("hidden");
   }

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

   xhr.open("POST", window.location.pathname);
   xhr.send(formData);
}

function updateProgress(percent) {
   const progressFill = document.getElementById("progressFill");
   const progressText = document.getElementById("progressText");

   if (progressFill) {
      progressFill.style.width = percent + "%";
   }
   if (progressText) {
      progressText.textContent = Math.round(percent) + "%";
   }
}

function resetUploadState() {
   const submitBtn = document.getElementById("submitBtn");
   const progressContainer = document.getElementById("progressContainer");

   if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="fas fa-upload"></i> Enviar Arquivo';
   }

   if (progressContainer) {
      progressContainer.classList.add("hidden");
   }

   updateProgress(0);
}

function removeFile() {
   console.log("Removendo arquivo selecionado");
   resetFileInput();
   hideFilePreview();
   const durationGroup = document.getElementById("durationGroup");
   const durationInput = document.getElementById("duracao");

   if (durationGroup) durationGroup.style.display = "flex";
   if (durationInput) {
      durationInput.disabled = false;
      durationInput.setAttribute("required", "required");
      durationInput.value = 5;
   }
}

function resetFileInput() {
   const fileInput = document.getElementById("arquivo");
   if (fileInput) {
      fileInput.value = "";
   }
}

function resetForm() {
   const form = document.getElementById("uploadForm");
   if (form) {
      form.reset();
   }

   hideFilePreview();
   resetFileInput();
   const durationGroup = document.getElementById("durationGroup");
   const durationInput = document.getElementById("duracao");

   if (durationGroup) durationGroup.style.display = "flex";
   if (durationInput) {
      durationInput.disabled = false;
      durationInput.setAttribute("required", "required");
      durationInput.value = 5;
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

   const content = document.querySelector(".content") || document.body;
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
