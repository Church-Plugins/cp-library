#!/usr/bin/env node

const pdfToHtml = require('../src/pdf2html');
const fs = require('fs');

async function main() {
    const htmlContent = await pdfToHtml('./Sermon_Transcripts_01.pdf');
	// Write the content to an HTML file
    fs.writeFileSync('output.html', htmlContent, 'utf-8', (err) => {
        if (err) {
            console.error('Error writing to file', err);
        } else {
            console.log('File has been saved as output.html');
        }
    });
    // console.log(htmlContent); // or save it to a file
}

main();
