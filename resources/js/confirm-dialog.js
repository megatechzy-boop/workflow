/**
 * The app's own centered dialog instead of the browser's confirm() / alert() boxes.
 *   <form data-confirm="Delete this file?">            asks before the form is sent
 *   <button data-confirm="Reject this content?">       asks before this button sends its form
 *   appAlert('Tick at least one row.')                  a message with one OK button
 * The OK button is red and says the action ("Delete", "Remove", "Reject") when the question starts with it.
 */

const DANGER = /^(delete|remove|reject)\b/i;

function dialog(message, { cancel = true } = {}) {
    return new Promise((resolve) => {
        const verb = message.match(DANGER)?.[1];
        const box = document.createElement('div');
        box.className = 'fixed inset-0 z-[70] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm';
        box.setAttribute('role', cancel ? 'alertdialog' : 'dialog');
        box.setAttribute('aria-modal', 'true');
        box.innerHTML = `
            <div class="card w-full max-w-md p-6 shadow-2xl text-center space-y-5" data-panel>
                <div class="mx-auto w-12 h-12 rounded-full flex items-center justify-center ${verb ? 'bg-red-50 text-red-600' : 'bg-brand-50 text-brand-600'}">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="${verb
                            ? 'M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z'
                            : 'M12 16v-4m0-4h.01M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0z'}"/>
                    </svg>
                </div>
                <p class="text-base text-gray-800 leading-relaxed" data-message></p>
                <div class="flex flex-col-reverse sm:flex-row sm:justify-center gap-2">
                    ${cancel ? '<button type="button" data-no class="px-5 min-h-[44px] rounded-xl border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</button>' : ''}
                    <button type="button" data-yes class="px-5 min-h-[44px] rounded-xl text-sm font-semibold text-white ${verb ? 'bg-red-600 hover:bg-red-500' : 'bg-brand-600 hover:bg-brand-700'}"></button>
                </div>
            </div>`;
        box.querySelector('[data-message]').textContent = message; // text, never HTML
        box.querySelector('[data-yes]').textContent = !cancel ? 'OK' : verb ? verb[0].toUpperCase() + verb.slice(1).toLowerCase() : 'Yes, continue';

        const back = document.activeElement;
        const close = (answer) => {
            document.removeEventListener('keydown', onKey, true);
            box.remove();
            back?.focus?.();
            resolve(answer);
        };
        const onKey = (e) => {
            if (e.key === 'Escape') { e.preventDefault(); close(false); }
            if (e.key === 'Tab') { // keep focus inside the dialog
                const buttons = [...box.querySelectorAll('button')];
                const i = buttons.indexOf(document.activeElement);
                e.preventDefault();
                buttons[(i + (e.shiftKey ? -1 : 1) + buttons.length) % buttons.length].focus();
            }
        };
        box.querySelector('[data-yes]').addEventListener('click', () => close(true));
        box.querySelector('[data-no]')?.addEventListener('click', () => close(false));
        box.addEventListener('click', (e) => e.target === box && close(false)); // tap outside = Cancel
        document.addEventListener('keydown', onKey, true);
        document.body.append(box);
        // Danger: focus Cancel, so Enter does not delete by mistake
        (verb && cancel ? box.querySelector('[data-no]') : box.querySelector('[data-yes]')).focus();
    });
}

export const appConfirm = (message) => dialog(message);
export const appAlert = (message) => dialog(message, { cancel: false });

// Capture phase: runs before the form's own handlers, the no-double-submit guard and the upload progress
document.addEventListener('submit', (e) => {
    const form = e.target;
    const submitter = e.submitter;
    const message = submitter?.dataset.confirm ?? form.dataset.confirm;
    if (!message || form.dataset.confirmed === '1') {
        delete form.dataset.confirmed;
        return;
    }
    e.preventDefault();
    e.stopImmediatePropagation();
    appConfirm(message).then((yes) => {
        if (!yes) return;
        form.dataset.confirmed = '1';
        form.requestSubmit(submitter || undefined); // same button, so its name / value are sent
    });
}, true);
