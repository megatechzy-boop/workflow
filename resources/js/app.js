import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Carousel => PDF (LinkedIn). jsPDF sirf click par load hota hai.
window.downloadCarouselPdf = (...args) => import('./carousel-pdf').then((m) => m.default(...args));

Alpine.start();
