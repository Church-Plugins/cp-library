
/**
 * Working vrsion that uss pdf-parse
 *
 * This exports plain text only. It does not preserve the formatting.
 */
// const pdf = require('pdf-parse');

// async function pdfToHtml(filePath) {
//     let data = await pdf(filePath);

//     let htmlContent = `<html><head><title>PDF Content</title></head><body>`;

//     // You can extract more data from the PDF if required.
//     // For now, we'll just use the text.
//     htmlContent += `<p>${data.text}</p>`;
//     htmlContent += `</body></html>`;

//     return htmlContent;
// }

const pdfjsLib = require('pdfjs-dist/legacy/build/pdf');

async function pdfToHtml(filePath) {
    const loadingTask = pdfjsLib.getDocument(filePath);
    const pdf = await loadingTask.promise;
    const pageCount = pdf.numPages;

    let htmlOutput = `<html><head><title>PDF Content</title></head><body>`;

    for (let i = 1; i <= pageCount; i++) {
        const page = await pdf.getPage(i);
        const content = await page.getTextContent();

        for (const item of content.items) {
            const transform = item.transform;
            const fontSize = transform[0]; // Usually, the first item in the transform array is the font size.

            htmlOutput += `<span style="font-size:${fontSize}px">${item.str}</span> `;
        }
        htmlOutput += `<br>`;
    }

    htmlOutput += `</body></html>`;
    return htmlOutput;
}

module.exports = pdfToHtml;