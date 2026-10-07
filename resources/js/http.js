// Permintaan JSON ke API dengan token CSRF dari <meta name="csrf-token">.

export async function request(url, method = "GET", body = undefined) {
    const response = await fetch(url, {
        method,
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    const data = response.status === 204 ? null : await response.json().catch(() => null);

    return { ok: response.ok, status: response.status, data };
}

export function errorMessages(response) {
    if (response.status === 422 && response.data?.errors) {
        return Object.values(response.data.errors).flat();
    }

    return [response.data?.message || `Permintaan gagal (HTTP ${response.status}).`];
}
