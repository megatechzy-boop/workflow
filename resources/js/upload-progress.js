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
            if (pct >= 100) q('[data-title]').textContent = q('[data-title]').textContent.replace('Uploading', 'Saving');
        },
        step(n, total, name) {
            q('[data-title]').textContent = 'Uploading ' + n + ' of ' + total + '…';
            q('[data-file]').textContent = name;
            q('[data-bar]').style.width = '0%';
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

const failMessage = (status) => (status === 413 ? 'File is too big for the server (max 128 MB each).'
    : status === 419 ? 'Page expired. Reload the page and try again.'
    : status >= 400 ? 'Upload failed (' + status + '). Please try again.'
    : 'Uploaded. Reload the page to see it.');

document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (e.defaultPrevented || form.enctype !== 'multipart/form-data' || 'noProgress' in form.dataset) return;
    const inputs = [...form.querySelectorAll('input[type=file]')].filter((i) => i.files.length);
    if (!inputs.length) return;
    e.preventDefault();

    // Forms marked data-split-upload (assets, customer row files): one request per file, so the server's
    // per-request size limit counts each video alone. Others (a carousel's slides) go in one request.
    const split = 'splitUpload' in form.dataset && inputs.length === 1 && inputs[0].files.length > 1;
    const files = split ? [...inputs[0].files] : [null];
    const base = new FormData(form, e.submitter);

    const allFiles = inputs.flatMap((i) => [...i.files]);
    const ui = overlay(allFiles.length === 1 ? allFiles[0].name : allFiles.length + ' files');
    const buttons = [...form.querySelectorAll('button, [type=submit]')];
    buttons.forEach((b) => (b.disabled = true));
    let request = null, cancelled = false, finished = false;
    const reset = () => { buttons.forEach((b) => (b.disabled = false)); ui.remove(); };
    ui.onCancel(() => { if (finished) { reset(); return; } cancelled = true; request?.abort(); });
    const stop = (message) => { finished = true; buttons.forEach((b) => (b.disabled = false)); ui.fail(message); };

    let redirect = null;
    for (const [index, file] of files.entries()) {
        const data = new FormData();
        base.forEach((value, key) => { if (!(split && key === inputs[0].name)) data.append(key, value); });
        if (split) {
            data.append(inputs[0].name, file);
            ui.step(index + 1, files.length, file.name);
        }
        const headers = { 'X-Upload-Progress': '1', Accept: 'text/html, application/json' };
        if (split) headers['X-Upload-Part'] = (index + 1) + '/' + files.length;

        let xhr;
        try {
            request = sendWithProgress(form.action, data, { headers, onProgress: (l, t) => ui.progress(l, t) });
            xhr = await request;
        } catch (err) {
            if (cancelled) {
                if (redirect) { window.location.href = redirect; return; } // show the files already sent
                reset();
                return;
            }
            stop('Network problem. Check the internet and try again.');
            return;
        }

        let json = null;
        try { json = JSON.parse(xhr.responseText); } catch (err) { /* HTML page */ }
        if (!json?.redirect) {
            stop((split ? 'Video ' + (index + 1) + ': ' : '') + failMessage(xhr.status));
            return;
        }
        redirect = json.redirect;
        if (json.errors) break; // validation message waits on that page
    }
    window.location.href = redirect;
});
