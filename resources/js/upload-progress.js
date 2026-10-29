/**
 * Upload progress for every form that sends files (content, replace, assets, customer files).
 * The form is sent with XMLHttpRequest (fetch cannot report upload progress); the server
 * answers a redirect as {"redirect": url} (UploadRedirectAsJson) and the browser opens it.
 */

const mb = (bytes) => (bytes / 1048576).toFixed(bytes < 10485760 ? 1 : 0) + ' MB';

/** POST a FormData with progress. Resolves with the XHR (status, responseText). */
export function sendWithProgress(url, data, { method = 'POST', headers = {}, onProgress } = {}) {
    const xhr = new XMLHttpRequest();
    const promise = new Promise((resolve, reject) => {
        xhr.open(method, url);
        Object.entries(headers).forEach(([k, v]) => xhr.setRequestHeader(k, v));
        xhr.upload.onprogress = (e) => e.lengthComputable && onProgress?.(e.loaded, e.total);
        xhr.onload = () => resolve(xhr);
        xhr.onerror = () => reject(new Error('network'));
        xhr.onabort = () => reject(new Error('aborted'));
        xhr.send(data);
    });
    promise.abort = () => xhr.abort();
    return promise;
}

function overlay(fileLabel) {
    const box = document.createElement('div');
    box.className = 'fixed inset-x-0 bottom-0 z-50 p-4 pb-safe sm:inset-auto sm:right-6 sm:bottom-6 sm:w-96';
    box.setAttribute('role', 'status');
    box.innerHTML = `
        <div class="card p-4 shadow-xl space-y-3">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-semibold text-gray-900" data-title>Uploading…</div>
                    <div class="text-xs text-gray-500 truncate" data-file></div>
                </div>
                <button type="button" data-cancel class="px-3 py-1.5 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100">Cancel</button>
            </div>
            <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden"><div data-bar class="h-full w-0 bg-brand-600 transition-[width] duration-200"></div></div>
            <div class="flex justify-between text-xs text-gray-600 tabular-nums"><span data-size></span><span data-pct>0%</span></div>
        </div>`;
    box.querySelector('[data-file]').textContent = fileLabel; // file names stay text
    document.body.append(box);
    const q = (s) => box.querySelector(s);
    return {
        progress(loaded, total) {
            const pct = Math.round((loaded / total) * 100);
            q('[data-bar]').style.width = pct + '%';
            q('[data-pct]').textContent = pct + '%';
            q('[data-size]').textContent = mb(loaded) + ' of ' + mb(total);
            if (pct >= 100) q('[data-title]').textContent = 'Processing…';
        },
        fail(message) {
            q('[data-title]').textContent = message;
            q('[data-title]').classList.add('text-red-600');
            q('[data-cancel]').textContent = 'Close';
        },
        onCancel(fn) { q('[data-cancel]').addEventListener('click', fn); },
        remove: () => box.remove(),
    };
}

document.addEventListener('submit', (e) => {
    const form = e.target;
    if (e.defaultPrevented || form.enctype !== 'multipart/form-data' || 'noProgress' in form.dataset) return;
    const files = [...form.querySelectorAll('input[type=file]')].flatMap((i) => [...i.files]);
    if (!files.length) return;

    e.preventDefault();
    const label = files.length === 1 ? files[0].name : files.length + ' files';
    const ui = overlay(label);
    const buttons = [...form.querySelectorAll('button, [type=submit]')];
    buttons.forEach((b) => (b.disabled = true));
    let done = false;

    const request = sendWithProgress(form.action, new FormData(form, e.submitter), {
        headers: { 'X-Upload-Progress': '1', Accept: 'text/html, application/json' },
        onProgress: (l, t) => ui.progress(l, t),
    });
    const reset = () => { buttons.forEach((b) => (b.disabled = false)); ui.remove(); };
    ui.onCancel(() => (done ? reset() : request.abort()));

    request.then((xhr) => {
        let json = null;
        try { json = JSON.parse(xhr.responseText); } catch (err) { /* HTML page */ }
        if (json?.redirect) { window.location.href = json.redirect; return; }
        done = true;
        buttons.forEach((b) => (b.disabled = false));
        ui.fail(xhr.status === 413 ? 'File is too big for the server.'
            : xhr.status === 419 ? 'Page expired. Reload the page and try again.'
            : xhr.status >= 400 ? 'Upload failed (' + xhr.status + '). Please try again.'
            : 'Uploaded. Reload the page to see it.');
    }).catch((err) => {
        if (err.message === 'aborted') { reset(); return; }
        done = true;
        buttons.forEach((b) => (b.disabled = false));
        ui.fail('Network problem. Check the internet and try again.');
    });
});
