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

Alpine.start();
