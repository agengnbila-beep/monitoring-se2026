// =========================
// DIALOG APLIKASI — pengganti prompt()/confirm() bawaan browser.
// Mengikuti tema, bisa dipakai dengan keyboard (Enter/Esc/Tab), fokus
// dikembalikan ke elemen sebelumnya setelah dialog ditutup.
// =========================

const make = (tag, props = {}, children = []) => {
    const node = Object.assign(document.createElement(tag), props);
    node.append(...children);
    return node;
};

let counter = 0;

/**
 * @param {{ title: string, body: Node[], confirmLabel: string, cancelLabel?: string, danger?: boolean, onConfirm: () => any }} options
 *        onConfirm mengembalikan nilai hasil, atau `undefined` untuk menahan dialog tetap terbuka.
 */
function openDialog({ title, body, confirmLabel, cancelLabel = "Batal", danger = false, onConfirm }) {
    return new Promise((resolve) => {
        const previousFocus = document.activeElement;
        const titleId = `dialog-title-${++counter}`;

        const confirmButton = make("button", { type: "submit", className: danger ? "danger-button" : "", textContent: confirmLabel });
        const cancelButton = make("button", { type: "button", className: "outline-button", textContent: cancelLabel });

        const form = make("form", { className: "dialog", method: "dialog" }, [
            make("h2", { className: "dialog-title", id: titleId, textContent: title }),
            make("div", { className: "dialog-body" }, body),
            make("div", { className: "dialog-actions" }, [cancelButton, confirmButton]),
        ]);
        form.setAttribute("role", "dialog");
        form.setAttribute("aria-modal", "true");
        form.setAttribute("aria-labelledby", titleId);

        const backdrop = make("div", { className: "dialog-backdrop" }, [form]);

        const close = (value) => {
            document.removeEventListener("keydown", onKeydown, true);
            backdrop.remove();
            previousFocus?.focus?.();
            resolve(value);
        };

        const onKeydown = (event) => {
            if (event.key === "Escape") {
                event.preventDefault();
                close(null);
            } else if (event.key === "Tab") {
                // Fokus tetap di dalam dialog.
                const focusable = [...form.querySelectorAll("input, button, select, textarea")].filter((el) => !el.disabled);
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        };

        form.addEventListener("submit", (event) => {
            event.preventDefault();
            const result = onConfirm();
            if (result !== undefined) {
                close(result);
            }
        });
        cancelButton.addEventListener("click", () => close(null));
        backdrop.addEventListener("mousedown", (event) => {
            if (event.target === backdrop) {
                close(null);
            }
        });
        document.addEventListener("keydown", onKeydown, true);

        document.body.append(backdrop);
        (form.querySelector("input") ?? (danger ? cancelButton : confirmButton)).focus();
    });
}

/**
 * Konfirmasi ya/tidak. Mengembalikan `true` bila dikonfirmasi.
 *
 * @param {{ title: string, message: string, confirmLabel?: string, danger?: boolean }} options
 * @returns {Promise<boolean>}
 */
export async function confirmDialog({ title, message, confirmLabel = "Ya", danger = false }) {
    const result = await openDialog({
        title,
        body: [make("p", { className: "dialog-message", textContent: message })],
        confirmLabel,
        danger,
        onConfirm: () => true,
    });

    return result === true;
}

/**
 * Isian teks satu baris. Mengembalikan teks (sudah di-trim) atau `null` bila dibatalkan.
 *
 * @param {{ title: string, label: string, value?: string, placeholder?: string, confirmLabel?: string, maxLength?: number }} options
 * @returns {Promise<string|null>}
 */
export function promptDialog({ title, label, value = "", placeholder = "", confirmLabel = "Simpan", maxLength = 100 }) {
    const inputId = `dialog-input-${++counter}`;
    const input = make("input", { type: "text", id: inputId, className: "qb-input dialog-input", value, placeholder, maxLength });
    const error = make("p", { className: "form-error", hidden: true, textContent: `${label} wajib diisi.` });
    input.addEventListener("input", () => (error.hidden = true));

    const promise = openDialog({
        title,
        body: [make("label", { className: "dialog-label", htmlFor: inputId, textContent: label }), input, error],
        confirmLabel,
        onConfirm: () => {
            const text = input.value.trim();
            if (text === "") {
                error.hidden = false;
                input.focus();
                return undefined;
            }
            return text;
        },
    });

    input.select();

    return promise;
}

/**
 * Form dengan atribut `data-confirm` meminta konfirmasi lewat dialog aplikasi
 * sebelum dikirim (pengganti `onsubmit="return confirm(...)"`).
 */
export function enableConfirmForms() {
    document.addEventListener("submit", async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) {
            return;
        }

        event.preventDefault();

        const ok = await confirmDialog({
            title: form.dataset.confirmTitle || "Konfirmasi",
            message: form.dataset.confirm,
            confirmLabel: form.dataset.confirmLabel || "Ya",
            danger: true,
        });

        if (ok) {
            form.submit();
        }
    });
}
