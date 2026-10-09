// =========================
// ANALITIK — kelola folder dan file analitik (Filter Data / Query SQL).
// =========================

import { confirmDialog, promptDialog } from "./dialog";
import { errorMessages, request } from "./http";

export function initAnalytics(root) {
    const url = (key, id) => root.dataset[key].replace("__ID__", id);
    const errorBox = document.getElementById("anError");

    const fail = (response) => {
        errorBox.textContent = errorMessages(response).join(" ");
        errorBox.hidden = false;
        errorBox.scrollIntoView({ block: "nearest" });
    };

    const reloadOr = (response) => (response.ok ? window.location.reload() : fail(response));

    document.getElementById("anNewFolder")?.addEventListener("click", async () => {
        const name = await promptDialog({ title: "Folder baru", label: "Nama folder", placeholder: "mis. Monitoring Kecamatan", confirmLabel: "Buat folder" });
        if (!name) {
            return;
        }
        reloadOr(await request(root.dataset.foldersUrl, "POST", { name, parent_id: Number(root.dataset.parentId) || null }));
    });

    root.querySelectorAll("[data-rename-folder]").forEach((button) =>
        button.addEventListener("click", async () => {
            const name = await promptDialog({ title: "Ganti nama folder", label: "Nama folder", value: button.dataset.name });
            if (!name) {
                return;
            }
            reloadOr(
                await request(url("folderUrl", button.dataset.renameFolder), "PUT", {
                    name,
                    parent_id: Number(button.dataset.parent) || null,
                }),
            );
        }),
    );

    root.querySelectorAll("[data-delete-folder]").forEach((button) =>
        button.addEventListener("click", async () => {
            if (!(await confirmDialog({ title: "Hapus folder", message: `Folder "${button.dataset.name}" akan dihapus.`, confirmLabel: "Hapus", danger: true }))) {
                return;
            }
            const response = await request(url("folderUrl", button.dataset.deleteFolder), "DELETE");
            if (!response.ok) {
                fail(response);
                return;
            }
            // Folder yang sedang dibuka dihapus: kembali ke folder induknya.
            const isCurrent = button.dataset.deleteFolder === root.dataset.parentId;
            isCurrent ? (window.location.href = root.dataset.upUrl) : window.location.reload();
        }),
    );

    root.querySelectorAll("[data-move-file]").forEach((select) =>
        select.addEventListener("change", async () => {
            reloadOr(await request(url("moveUrl", select.dataset.moveFile), "PUT", { folder_id: Number(select.value) || null }));
        }),
    );

    root.querySelectorAll("[data-delete-file]").forEach((button) =>
        button.addEventListener("click", async () => {
            if (!(await confirmDialog({ title: "Hapus file", message: `File "${button.dataset.name}" akan dihapus permanen.`, confirmLabel: "Hapus", danger: true }))) {
                return;
            }
            reloadOr(await request(url("fileUrl", button.dataset.deleteFile), "DELETE"));
        }),
    );
}
