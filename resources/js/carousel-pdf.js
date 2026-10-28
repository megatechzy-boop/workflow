import { jsPDF } from 'jspdf';

/**
 * Carousel slides => ek PDF (LinkedIn document post). Har slide ek page, apne size mein.
 * Browser mein banta hai, server par kuch nahi lagta.
 */
export default async function carouselPdf(urls, filename, { save = true } = {}) {
    let doc = null;

    for (const url of urls) {
        const blob = await (await fetch(url, { credentials: 'same-origin' })).blob();
        const bitmap = await createImageBitmap(blob);
        const { width, height } = bitmap;

        // webp/png sab ko JPEG mein: PDF chhoti aur har jagah chalti hai
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
