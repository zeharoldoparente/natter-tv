document.addEventListener("DOMContentLoaded", function () {
   initializeUpload();
});

function initializeUpload() {
   const dropZone = document.getElementById("dropZone");
   const fileInput = document.getElementById("arquivo");
   const uploadForm = document.getElementById("uploadForm");
   setupDragAndDrop(dropZone, fileInput);
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
      "video/mp4",
      "video/avi",
      "video/mov",
      "video/wmv",
   ];

   if (file.size > maxSize) {
      showAlert("Arquivo muito grande! Máximo permitido: 50MB", "error");
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
         mediaElement.muted = false;
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

function adjustFormFields(file) {
   const durationGroup = document.getElementById("durationGroup");
   const durationInput = document.getElementById("duracao");

   if (file.type.startsWith("video/")) {
      durationGroup.style.display = "none";
      durationInput.value = 0;
      durationInput.removeAttribute("required");
      durationInput.disabled = true;
   } else {
      durationGroup.style.display = "flex";
      durationInput.disabled = false;
      if (durationInput.value == 0) {
         durationInput.value = 5;
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

   submitBtn.disabled = false;
   submitBtn.innerHTML = '<i class="fas fa-upload"></i> Enviar Arquivo';
   progressContainer.classList.add("hidden");

   updateProgress(0);
}

function removeFile() {
   resetFileInput();
   hideFilePreview();

   const durationGroup = document.getElementById("durationGroup");
   const durationInput = document.getElementById("duracao");

   durationGroup.style.display = "flex";
   durationInput.disabled = false;
   durationInput.value = 5;
}

function resetFileInput() {
   const fileInput = document.getElementById("arquivo");
   fileInput.value = "";
}

function resetForm() {
   document.getElementById("uploadForm").reset();
   hideFilePreview();
   resetFileInput();

   const durationGroup = document.getElementById("durationGroup");
   const durationInput = document.getElementById("duracao");

   durationGroup.style.display = "flex";
   durationInput.disabled = false;
   durationInput.value = 5;
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

const style = document.createElement("style");
style.textContent = additionalCS;
