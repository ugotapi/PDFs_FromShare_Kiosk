<?php

$pdfFolder = __DIR__ . '/pdfs';
$cacheFile = __DIR__ . '/pdf_files.json';
$cacheTTL = 3600; // 1 hour cache

function getPdfFiles($folder) {
    $files = glob($folder . '/*.pdf');
    $result = [];
    foreach ($files as $f) {
        $result[] = [
            'name' => basename($f),
            'mtime' => filemtime($f)
        ];
    }
    return $result;
}

function regenCache($cacheFile, $pdfFolder) {
    $list = getPdfFiles($pdfFolder);
    file_put_contents($cacheFile, json_encode($list, JSON_PRETTY_PRINT));
    return $list;
}

$forceRegen = isset($argv) && in_array("--regen-cache", $argv);

// ------------ CLI REGEN MODE ------------
if ($forceRegen) {
    regenCache($cacheFile, $pdfFolder);
    exit;
}

// ------------ AJAX JSON MODE ------------
if (isset($_GET['json'])) {

    $current = getPdfFiles($pdfFolder);

    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);

        // If files differ → update cache
        if ($cached !== $current) {
            file_put_contents($cacheFile, json_encode($current));
            $cached = $current;
        }

        header('Content-Type: application/json');
        echo json_encode($cached);
        exit;
    }

    // No cache yet
    file_put_contents($cacheFile, json_encode($current));
    header('Content-Type: application/json');
    echo json_encode($current);
    exit;
}

// ------------ NORMAL PAGE LOAD ------------
$current = getPdfFiles($pdfFolder);

if (file_exists($cacheFile)) {
    $cached = json_decode(file_get_contents($cacheFile), true);

    // Regenerate cache if PDFs changed
    if ($cached !== $current) {
        file_put_contents($cacheFile, json_encode($current));
        $cached = $current;
    }

    $pdfFiles = $cached;
} else {
    $pdfFiles = $current;
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

function loadPDF(file) {
    const url = 'pdfs/' + encodeURIComponent(file) + '?t=' + Date.now();
    console.log('Loading:', file);

    pdfjsLib.getDocument(url).promise.then(pdf => {
        pdfDoc = pdf;
        currentPageNum = 1;
        renderPage(currentPageNum);
    });
}

function renderPage(num) {
    if (!pdfDoc) return;

    pdfDoc.getPage(num).then(page => {
        const viewport = page.getViewport({ scale: 1 });
        const scale = Math.min(viewer.clientWidth / viewport.width, viewer.clientHeight / viewport.height);
        const scaledViewport = page.getViewport({ scale });

        canvas.width = scaledViewport.width;
        canvas.height = scaledViewport.height;

        page.render({ canvasContext: ctx, viewport: scaledViewport });
    });
}

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

function startSlideshow() {
    setInterval(nextPage, 30000);
}

async function checkForUpdates() {
    try {
        const response = await fetch('?json&_=' + Date.now());
        const latest = await response.json();

        latest.forEach(f => {
            if (!pdfMtimes[f.name] || pdfMtimes[f.name] != f.mtime) {
                console.log("Updated PDF detected:", f.name);
                pdfMtimes[f.name] = f.mtime;

                // Reload only if it's the current one being viewed
                if (f.name === pdfFiles[currentIndex].name) {
                    loadPDF(f.name);
                }
            }
        });

        pdfFiles = latest;
    } catch (e) {
        console.error('Error checking PDF updates:', e);
    }
}

if (pdfFiles.length > 0) {
    loadPDF(pdfFiles[0].name);
    startSlideshow();
    setInterval(checkForUpdates, 10 * 60 * 1000);
}
</script>

</body>
</html>
