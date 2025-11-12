<?php
$pdfFolder = __DIR__ . '/pdfs';
$cacheFile = __DIR__ . '/pdf_files.json';
$cacheTTL = 3600; // 1 hour cache

// Function to build PDF list
function getPdfFiles($folder) {
    $files = glob($folder . '/*.pdf');
    return array_map(fn($f) => ['name' => basename($f), 'mtime' => filemtime($f)], $files);
}

// If this is an AJAX request for updates, return JSON
if (isset($_GET['json'])) {
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
        $pdfFiles = json_decode(file_get_contents($cacheFile), true);
    } else {
        $pdfFiles = getPdfFiles($pdfFolder);
        file_put_contents($cacheFile, json_encode($pdfFiles));
    }

    header('Content-Type: application/json');
    echo json_encode($pdfFiles);
    exit;
}

// Otherwise, serve the HTML page
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    $pdfFiles = json_decode(file_get_contents($cacheFile), true);
} else {
    $pdfFiles = getPdfFiles($pdfFolder);
    file_put_contents($cacheFile, json_encode($pdfFiles));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Floor Info</title>
<style>
    body { font-family: Arial, sans-serif; text-align:center; background:#f0f0f0; margin:0; }
    #pdf-viewer { width: 100%; height: 100vh; display:flex; align-items:center; justify-content:center; background:#000; }
    canvas { max-width: 100%; max-height: 100%; }
</style>
</head>
<body>

<div id="pdf-viewer"></div>

<script type="module" src="pdfjs/pdf.mjs"></script>
<script type="module">
pdfjsLib.GlobalWorkerOptions.workerSrc = 'pdfjs/pdf.worker.mjs';

let pdfFiles = <?php echo json_encode($pdfFiles); ?>;
let pdfMtimes = Object.fromEntries(pdfFiles.map(f => [f.name, f.mtime]));

let currentIndex = 0;
let pdfDoc = null;
let currentPageNum = 1;

const viewer = document.getElementById('pdf-viewer');
const canvas = document.createElement('canvas');
const ctx = canvas.getContext('2d');
viewer.appendChild(canvas);

// Load PDF and render first page
function loadPDF(file) {
    // Use mtime instead of Date.now() to avoid unnecessary reloads
    const t = pdfMtimes[file] || 0;
    const url = 'pdfs/' + encodeURIComponent(file) + '?t=' + t;
    console.log('Loading:', file, '(mtime:', t, ')');

    pdfjsLib.getDocument(url).promise.then(pdf => {
        pdfDoc = pdf;
        currentPageNum = 1;
        renderPage(currentPageNum);
    }).catch(err => console.error('Failed to load PDF:', err));
}

// Render a page on the single canvas
function renderPage(num) {
    if (!pdfDoc) return;

    pdfDoc.getPage(num).then(page => {
        const viewport = page.getViewport({ scale: 1 });
        const scale = Math.min(viewer.clientWidth / viewport.width, viewer.clientHeight / viewport.height);
        const scaledViewport = page.getViewport({ scale });

        canvas.width = scaledViewport.width;
        canvas.height = scaledViewport.height;

        const renderContext = { canvasContext: ctx, viewport: scaledViewport };
        page.render(renderContext);
    });
}

// Move to next page or next PDF
function nextPage() {
    if (!pdfDoc) return;

    if (currentPageNum < pdfDoc.numPages) {
        currentPageNum++;
        renderPage(currentPageNum);
    } else {
        currentIndex = (currentIndex + 1) % pdfFiles.length;
        loadPDF(pdfFiles[currentIndex].name);
    }
}

// Start slideshow (30s per page)
function startSlideshow() {
    setInterval(nextPage, 30000);
}

// Check for updated PDFs every 10 minutes
async function checkForUpdates() {
    try {
        const response = await fetch('?json&_=' + Date.now());
        const latest = await response.json();

        latest.forEach(f => {
            if (!pdfMtimes[f.name] || pdfMtimes[f.name] !== f.mtime) {
                console.log('Updated PDF detected:', f.name);
                pdfMtimes[f.name] = f.mtime;
                // Reload only if the current PDF changed
                if (f.name === pdfFiles[currentIndex].name) {
                    loadPDF(f.name);
                }
            }
        });

        // Replace list if new files were added or removed
        if (latest.length !== pdfFiles.length) {
            pdfFiles = latest;
            pdfMtimes = Object.fromEntries(latest.map(f => [f.name, f.mtime]));
        }

    } catch (e) {
        console.error('Error checking PDF updates:', e);
    }
}

// Initialize
if (pdfFiles.length > 0) {
    loadPDF(pdfFiles[0].name);
    startSlideshow();
    setInterval(checkForUpdates, 10 * 60 * 1000); // 10 minutes
}
</script>

</body>
</html>
