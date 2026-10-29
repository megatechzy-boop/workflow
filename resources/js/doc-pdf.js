import { jsPDF } from 'jspdf';
import html2canvas from 'html2canvas';

/**
 * Invoice / receipt page => A4 PDF, built in the browser. The page is drawn as an image
 * (standard PDF fonts have no ₹ sign); a long document is split over several pages.
 */
export default async function docPdf(element, filename) {
    const canvas = await html2canvas(element, { scale: 2, backgroundColor: '#ffffff' });
    const doc = new jsPDF({ unit: 'pt', format: 'a4' });
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const sliceHeight = Math.floor(canvas.width * (pageHeight / pageWidth)); // canvas px per PDF page

    for (let y = 0; y < canvas.height; y += sliceHeight) {
        const slice = document.createElement('canvas');
        slice.width = canvas.width;
        slice.height = Math.min(sliceHeight, canvas.height - y);
        slice.getContext('2d').drawImage(canvas, 0, y, canvas.width, slice.height, 0, 0, canvas.width, slice.height);
        if (y > 0) {
            doc.addPage();
        }
        doc.addImage(slice.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, pageWidth, slice.height * (pageWidth / canvas.width));
    }
    doc.save(filename);
}
