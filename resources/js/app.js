import './bootstrap';

import Alpine from 'alpinejs';
import { sendWithProgress } from './upload-progress';
import './file-drop';

window.Alpine = Alpine;
window.sendWithProgress = sendWithProgress; // chat file / voice note

// Carousel => PDF (LinkedIn). jsPDF loads only on click.
window.downloadCarouselPdf = (...args) => import('./carousel-pdf').then((m) => m.default(...args));
// Invoice UPI QR: the library loads only on pages that show one
window.renderQr = (img, text) => import('qrcode').then((m) => m.toDataURL(text, { margin: 1, width: 260 })).then((url) => { img.src = url; });
// Invoice / receipt => A4 PDF
window.downloadDocPdf = (...args) => import('./doc-pdf').then((m) => m.default(...args));

// No double submit: a second tap on Approve / Revision / Save used to send the form twice
// (the second one then failed). Buttons are disabled after the browser has read the clicked one.
document.addEventListener('submit', (e) => {
    if (e.defaultPrevented || (e.target.method || '').toLowerCase() !== 'post') return;
    setTimeout(() => e.target.querySelectorAll('button:not([disabled]), [type=submit]:not([disabled])').forEach((b) => {
        b.disabled = true;
        b.dataset.sentOnce = '1';
    }));
});
// Back button restores the page from cache: enable the buttons this code disabled
window.addEventListener('pageshow', (e) => e.persisted && document.querySelectorAll('[data-sent-once]').forEach((b) => {
    b.disabled = false;
    delete b.dataset.sentOnce;
}));

Alpine.start();
