document.addEventListener("DOMContentLoaded", function () {
    // =========================
    // ELEMENT
    // =========================

    const openButton = document.getElementById("openUploadModal");

    const closeButton = document.getElementById("closeUploadModal");

    const cancelButton = document.getElementById("cancelUploadModal");

    const modal = document.getElementById("uploadModal");

    const fileInput = document.getElementById("datasetFile");

    const fileLabel = document.getElementById("fileLabel");

    // =========================
    // OPEN MODAL
    // =========================

    if (openButton && modal) {
        openButton.addEventListener("click", function () {
            modal.classList.add("active");
        });
    }

    // =========================
    // CLOSE MODAL - X
    // =========================

    if (closeButton && modal) {
        closeButton.addEventListener("click", function () {
            modal.classList.remove("active");
        });
    }

    // =========================
    // CLOSE MODAL - BATAL
    // =========================

    if (cancelButton && modal) {
        cancelButton.addEventListener("click", function () {
            modal.classList.remove("active");
        });
    }

    // =========================
    // FILE INPUT
    // =========================

    if (fileInput && fileLabel) {
        fileInput.addEventListener("change", function () {
            if (this.files.length > 0) {
                const file = this.files[0];

                fileLabel.textContent = "📄 " + file.name;
            } else {
                fileLabel.textContent = "📄 Pilih File XLSX";
            }
        });
    }
});
