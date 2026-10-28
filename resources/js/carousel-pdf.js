import { jsPDF } from 'jspdf';

/**
 * Carousel slides => one PDF (LinkedIn document post). One page per slide, at its own size.
 * Built in the browser; nothing runs on the server.
 */
export default async function carouselPdf(urls, filename, { save = true } = {}) {
    let doc = null;

    for (const url of urls) {
        const blob = await (await fetch(url, { credentials: 'same-origin' })).blob();
        const bitmap = await createImageBitmap(blob);
        const { width, height } = bitmap;

        // convert everything to JPEG: smaller PDF that works everywhere
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, width, height);
        ctx.drawImage(bitmap, 0, 0);
        const jpeg = canvas.toDataURL('image/jpeg', 0.92);

        const orientation = width > height ? 'landscape' : 'portrait';
        if (!doc) {
            doc = new jsPDF({ orientation, unit: 'px', format: [width, height], hotfixes: ['px_scaling'] });
        } else {
            doc.addPage([width, height], orientation);
        }
        doc.addImage(jpeg, 'JPEG', 0, 0, width, height);
    }

    if (save) {
        doc.save(filename);
    }

    return doc;
}
