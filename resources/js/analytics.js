// =========================
// ANALITIK — kelola folder dan file analitik (Filter Data / Query SQL).
// =========================

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
        const name = prompt("Nama folder baru:");
        if (!name) {
            return;
        }
        reloadOr(await request(root.dataset.foldersUrl, "POST", { name: name.trim(), parent_id: Number(root.dataset.parentId) || null }));
    });

    root.querySelectorAll("[data-rename-folder]").forEach((button) =>
        button.addEventListener("click", async () => {
            const name = prompt("Nama baru folder:", button.dataset.name);
            if (!name) {
                return;
            }
            reloadOr(
                await request(url("folderUrl", button.dataset.renameFolder), "PUT", {
                    name: name.trim(),
                    parent_id: Number(button.dataset.parent) || null,
                }),
            );
        }),
    );

    root.querySelectorAll("[data-delete-folder]").forEach((button) =>
        button.addEventListener("click", async () => {
            if (!confirm(`Hapus folder "${button.dataset.name}"?`)) {
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
            if (!confirm(`Hapus file "${button.dataset.name}"?`)) {
                return;
            }
            reloadOr(await request(url("fileUrl", button.dataset.deleteFile), "DELETE"));
        }),
    );
}
