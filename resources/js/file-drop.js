/**
 * Drag & drop for every form with a file input (content, assets, calendar rows, chat).
 * Drop on the form, or anywhere on the page when it has only one such form. Each file goes
 * to the input whose `accept` fits it (video => video box, photo => cover/images box).
 */

const HIGHLIGHT = ['ring-2', 'ring-brand-500', 'ring-offset-2', 'rounded-2xl'];
const uploadForms = () => [...document.querySelectorAll('form')].filter((f) => f.querySelector('input[type=file]:not([disabled])'));

function targetForm(el) {
    const own = el instanceof Element ? el.closest('form') : null;
    if (own?.querySelector('input[type=file]')) return own;
    const all = uploadForms();
    return all.length === 1 ? all[0] : null;
}

function accepts(input, file) {
    const accept = (input.accept || '').split(',').map((s) => s.trim().toLowerCase()).filter(Boolean);
    if (!accept.length) return true;
    const type = (file.type || '').toLowerCase();
    const name = file.name.toLowerCase();
    return accept.some((a) => (a.startsWith('.') ? name.endsWith(a) : a.endsWith('/*') ? type.startsWith(a.slice(0, -1)) : type === a));
}

let current = null;
const mark = (form) => {
    if (current === form) return;
    current?.classList.remove(...HIGHLIGHT);
    current = form;
    current?.classList.add(...HIGHLIGHT);
};

const hasFiles = (e) => [...(e.dataTransfer?.types || [])].includes('Files');

document.addEventListener('dragover', (e) => {
    if (!hasFiles(e)) return;
    e.preventDefault(); // never let the browser open the file instead of the page
    const form = targetForm(e.target);
    e.dataTransfer.dropEffect = form ? 'copy' : 'none';
    mark(form);
});

document.addEventListener('dragleave', (e) => {
    if (e.relatedTarget === null) mark(null); // pointer left the window
});

document.addEventListener('drop', (e) => {
    if (!hasFiles(e)) return;
    e.preventDefault();
    const form = targetForm(e.target);
    mark(null);
    if (!form) return;

    const inputs = [...form.querySelectorAll('input[type=file]:not([disabled])')];
    const picked = new Map(); // input => files
    for (const file of e.dataTransfer.files) {
        const input = inputs.find((i) => accepts(i, file) && (i.multiple || !picked.has(i)));
        if (input) picked.set(input, [...(picked.get(input) || []), file]);
    }
    if (!picked.size) {
        window.alert?.('This file type is not allowed here.');
        return;
    }
    picked.forEach((files, input) => {
        const dt = new DataTransfer();
        files.forEach((f) => dt.items.add(f));
        input.files = dt.files;
        input.dispatchEvent(new Event('change', { bubbles: true })); // chat chip, calendar row auto-send
    });
});

// Small hint under visible file boxes
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form input[type=file]:not(.sr-only)').forEach((input) => {
        const hint = document.createElement('p');
        hint.className = 'mt-1 text-xs text-gray-500';
        hint.textContent = 'or drag & drop files here';
        input.after(hint);
    });
});
