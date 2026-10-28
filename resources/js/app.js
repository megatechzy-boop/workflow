import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Carousel => PDF (LinkedIn). jsPDF loads only on click.
window.downloadCarouselPdf = (...args) => import('./carousel-pdf').then((m) => m.default(...args));

Alpine.start();
