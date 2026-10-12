import { enableConfirmForms } from "./dialog";
import { initQueryBuilder } from "./query-builder";

document.addEventListener("DOMContentLoaded", function () {
    enableConfirmForms();

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
                fileLabel.textContent = "📄 Pilih file CSV, XLSX, atau JSON";
            }
        });
    }

    // =========================
    // UPLOAD DENGAN PROGRESS
    // =========================

    const uploadForm = document.getElementById("uploadForm");

    if (uploadForm) {
        const submitButton = document.getElementById("uploadSubmit");
        const progress = document.getElementById("uploadProgress");
        const bar = document.getElementById("uploadBar");
        const percent = document.getElementById("uploadPercent");
        const errorText = document.getElementById("uploadError");

        const showError = function (message) {
            progress.classList.remove("active");
            submitButton.disabled = false;
            errorText.textContent = message;
            errorText.hidden = false;
        };

        uploadForm.addEventListener("submit", function (event) {
            event.preventDefault();

            const xhr = new XMLHttpRequest();

            xhr.open("POST", uploadForm.action);
            xhr.setRequestHeader("Accept", "application/json");
            xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");

            xhr.upload.addEventListener("progress", function (e) {
                if (e.lengthComputable) {
                    const value = Math.round((e.loaded / e.total) * 100) + "%";
                    bar.style.width = value;
                    percent.textContent = value;
                }
            });

            xhr.addEventListener("load", function () {
                if (xhr.status === 201) {
                    window.location.href = JSON.parse(xhr.responseText).redirect;
                } else if (xhr.status === 422) {
                    const errors = JSON.parse(xhr.responseText).errors || {};
                    showError(Object.values(errors).flat()[0] || "Upload tidak valid.");
                } else if (xhr.status === 413) {
                    showError("File terlalu besar untuk batas server.");
                } else if (xhr.status === 419) {
                    showError("Sesi habis. Muat ulang halaman lalu coba lagi.");
                } else {
                    showError("Upload gagal (HTTP " + xhr.status + ").");
                }
            });

            xhr.addEventListener("error", function () {
                showError("Koneksi terputus saat mengupload.");
            });

            errorText.hidden = true;
            submitButton.disabled = true;
            bar.style.width = "0%";
            percent.textContent = "0%";
            progress.classList.add("active");

            xhr.send(new FormData(uploadForm));
        });
    }

    // =========================
    // POLLING STATUS IMPORT
    // =========================

    const datasetList = document.getElementById("datasetList");

    const busySelector = '[data-status="pending"], [data-status="processing"], [data-update-status="pending"], [data-update-status="processing"]';

    if (datasetList && datasetList.querySelector(busySelector)) {
        const poll = function () {
            fetch(datasetList.dataset.pollUrl, { headers: { Accept: "application/json" } })
                .then(function (response) {
                    return response.ok ? response.json() : Promise.reject(response.status);
                })
                .then(function (body) {
                    const changed = body.data.some(function (dataset) {
                        const card = datasetList.querySelector('[data-dataset-id="' + dataset.id + '"]');

                        return card && (card.dataset.status !== dataset.status || (card.dataset.updateStatus || null) !== (dataset.update_status ?? null));
                    });

                    if (changed) {
                        window.location.reload();
                    } else {
                        setTimeout(poll, 5000);
                    }
                })
                .catch(function () {
                    setTimeout(poll, 15000);
                });
        };

        setTimeout(poll, 5000);
    }

    // =========================
    // QUERY BUILDER
    // =========================

    const queryBuilder = document.getElementById("queryBuilder");

    if (queryBuilder) {
        initQueryBuilder(queryBuilder);
    }

    // =========================
    // DASHBOARD
    // =========================

    const dashboardRoot = document.getElementById("dashboardRoot");

    // Dimuat terpisah agar ECharts (±550 KB) hanya diunduh di laman dashboard.
    if (dashboardRoot) {
        import("./dashboard").then(({ initDashboard }) => {
            const view = initDashboard(dashboardRoot);

            if (dashboardRoot.dataset.canEdit) {
                import("./dashboard-editor").then(({ initDashboardEditor }) => initDashboardEditor(dashboardRoot, view));
            }
        });
    }

    const dashCreate = document.getElementById("dashCreate");

    if (dashCreate) {
        import("./dashboard-editor").then(({ initDashboardCreate }) => initDashboardCreate(dashCreate));
    }

    // =========================
    // MODE SQL
    // =========================

    const sqlApp = document.getElementById("sqlApp");

    if (sqlApp) {
        import("./sql-editor").then(({ initSqlEditor }) => initSqlEditor(sqlApp));
    }

    // =========================
    // ANALITIK
    // =========================

    const analyticsRoot = document.getElementById("analyticsRoot");

    if (analyticsRoot) {
        import("./analytics").then(({ initAnalytics }) => initAnalytics(analyticsRoot));
    }
});
