<?php ob_start(); ?>
<!--
CATEGORY: Tools
TAGS: OXPS, PDF, Converter, Edmonton, Privacy
DESCRIPTION: A secure, private OXPS to PDF converter tool for City of Edmonton staff. Processes files locally on the server to ensure data privacy and compliance with municipal data standards. Supports batch conversion and customizable render quality.
* Based on the gxps-9540-linux-x86_64 engine.
-->
<?php
/**
 * Robust Private OXPS to PDF Converter
 * Designed for City of Edmonton Data Privacy
 */

// 1. HANDLE API REQUESTS FIRST (Before any HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clear the metadata from the output buffer so it doesn't corrupt the JSON response
    ob_clean();
    
    $work_dir = __DIR__ . '/converter/work';
    
    // --- MANUAL CLEAR HANDLER ---
    if (isset($_POST['action']) && $_POST['action'] === 'clear_work') {
        header('Content-Type: application/json');
        if (is_dir($work_dir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($work_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                $todo($fileinfo->getRealPath());
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Directory not found.']);
        }
        exit;
    }

    // --- CONVERSION HANDLER ---
    if (isset($_FILES['files'])) {
        // Prevent timeouts for large files (like 600+ page documents)
        ini_set('max_execution_time', 0); 
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        
        header('Content-Type: application/json');
        
        $manual_binary_path = __DIR__ . '/converter/gxps';
        
        if (!is_dir($work_dir)) mkdir($work_dir, 0777, true);
        if (file_exists($manual_binary_path)) @chmod($manual_binary_path, 0755);

        $quality = isset($_POST['quality']) ? (int)$_POST['quality'] : 108;
        $bg = isset($_POST['background']) ? $_POST['background'] : 'white';
        $color_mode = isset($_POST['color_mode']) ? $_POST['color_mode'] : 'grayscale';
        $optimization = isset($_POST['optimization']) ? $_POST['optimization'] : 'default';
        $fit_page = isset($_POST['fit_page']) && $_POST['fit_page'] === 'true' ? true : false;
        $first_page = isset($_POST['first_page']) ? (int)$_POST['first_page'] : 0;
        $last_page = isset($_POST['last_page']) ? (int)$_POST['last_page'] : 0;
        
        $file_count = count($_FILES['files']['name']);
        $batch_id = bin2hex(random_bytes(8));
        $batch_dir = $work_dir . '/' . $batch_id;
        mkdir($batch_dir, 0777, true);

        $converted_files = [];
        $errors = [];

        for ($i = 0; $i < $file_count; $i++) {
            $original_name = $_FILES['files']['name'][$i];
            $tmp_path = $_FILES['files']['tmp_name'][$i];
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

            if (!in_array($ext, ['oxps', 'xps'])) {
                $errors[] = "$original_name: Invalid format.";
                continue;
            }

            $base_name = pathinfo($original_name, PATHINFO_FILENAME);
            $output_pdf_name = $base_name . '.pdf';
            $input_path = $batch_dir . '/' . $original_name;
            $output_path = $batch_dir . '/' . $output_pdf_name;

            if (move_uploaded_file($tmp_path, $input_path)) {
                // Build Advanced Command
                $args = [];
                $args[] = "-sDEVICE=pdfwrite";
                $args[] = "-r" . $quality;
                $args[] = "-dNOPAUSE -dBATCH";
                
                // Background
                if ($bg === 'transparent') {
                    $args[] = "-dUseCIEColor -dBackgroundColor=16#00000000";
                }
                
                // Color Mode
                if ($color_mode === 'grayscale') {
                    $args[] = "-sColorConversionStrategy=Gray -dProcessColorModel=/DeviceGray";
                }
                
                // Optimization
                if ($optimization !== 'default') {
                    $args[] = "-dPDFSETTINGS=/" . $optimization;
                }
                
                // Fit Page
                if ($fit_page) {
                    $args[] = "-dFitPage";
                }
                
                // Page Range
                if ($first_page > 0) $args[] = "-dFirstPage=" . $first_page;
                if ($last_page > 0) $args[] = "-dLastPage=" . $last_page;
                
                $args[] = "-sOutputFile=" . escapeshellarg($output_path);
                $args[] = escapeshellarg($input_path);
                
                $cmd = escapeshellarg($manual_binary_path) . " " . implode(" ", $args) . " 2>&1";
                
                shell_exec($cmd);
                @unlink($input_path);

                if (file_exists($output_path) && filesize($output_path) > 0) {
                    $converted_files[] = $output_pdf_name;
                } else {
                    $errors[] = "$original_name: Conversion failed.";
                }
            }
        }

        if (count($converted_files) === 0) {
            rmdir($batch_dir);
            echo json_encode(['error' => 'No files were converted.', 'details' => $errors]);
            exit;
        }

        if (count($converted_files) > 1) {
            $zip_path = $batch_dir . '/Batch_Conversion.zip';
            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
                foreach ($converted_files as $f) {
                    $zip->addFile($batch_dir . '/' . $f, $f);
                }
                $zip->close();
                echo json_encode(['success' => true, 'id' => $batch_id, 'type' => 'zip', 'count' => count($converted_files)]);
            } else {
                echo json_encode(['error' => 'Failed to create ZIP archive.']);
            }
        } else {
            echo json_encode(['success' => true, 'id' => $batch_id, 'type' => 'pdf', 'filename' => $converted_files[0]]);
        }
        exit;
    }
}

// 2. DOWNLOAD & PREVIEW HANDLERS
$work_dir = __DIR__ . '/converter/work';
if (isset($_GET['download'])) {
    $id = preg_replace('/[^a-f0-9]/', '', $_GET['download']);
    $batch_path = $work_dir . '/' . $id;
    if (is_dir($batch_path)) {
        if (file_exists($batch_path . '/Batch_Conversion.zip')) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="City_Documents_Batch.zip"');
            readfile($batch_path . '/Batch_Conversion.zip');
            exit;
        } 
        $files = array_diff(scandir($batch_path), array('.', '..'));
        foreach ($files as $f) {
            if (pathinfo($f, PATHINFO_EXTENSION) === 'pdf') {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $f . '"');
                readfile($batch_path . '/' . $f);
                exit;
            }
        }
    }
}

if (isset($_GET['preview'])) {
    $id = preg_replace('/[^a-f0-9]/', '', $_GET['preview']);
    $batch_path = $work_dir . '/' . $id;
    if (is_dir($batch_path)) {
        $files = array_diff(scandir($batch_path), array('.', '..'));
        foreach ($files as $f) {
            if (pathinfo($f, PATHINFO_EXTENSION) === 'pdf') {
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="preview.pdf"');
                readfile($batch_path . '/' . $f);
                exit;
            }
        }
    }
}

// 3. HOUSEKEEPING (Clean up old files)
if (is_dir($work_dir)) {
    $files = glob($work_dir . '/*');
    foreach ($files as $file) {
        if (is_file($file) && time() - filemtime($file) > 3600) {
            unlink($file);
        } elseif (is_dir($file) && time() - filemtime($file) > 3600) {
            array_map('unlink', glob("$file/*.*"));
            rmdir($file);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OXPS → PDF Converter | Internal Utility</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #0f172a; color: #f8fafc; }
        .panel { background-color: #1e293b; border: 1px solid #334155; }
        .accent-blue { background-color: #2563eb; }
        .accent-blue:hover { background-color: #3b82f6; }
        .sidebar-label { color: #94a3b8; text-transform: uppercase; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; display: block; }
        select, button, input { border-radius: 0.375rem; }
        input[type="number"]::-webkit-inner-spin-button { opacity: 1; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #1e293b; }
        ::-webkit-scrollbar-thumb { background: #475569; border-radius: 4px; }
        /* Custom Summary Arrow Fix */
        summary::-webkit-details-marker { display:none; }
    </style>
</head>
<body class="h-screen flex flex-col overflow-hidden font-sans text-white">

    <header class="p-4 border-b border-slate-800 flex items-center justify-between bg-slate-900/50">
        <div class="flex items-center gap-3">
            <div class="bg-blue-600 p-1.5 rounded shadow-lg shadow-blue-900/20">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
            </div>
            <div>
                <h1 class="text-lg font-bold leading-none tracking-tight text-white">OXPS → PDF Converter</h1>
                <p class="text-[10px] text-slate-400 mt-1 uppercase tracking-wider">Internal Document Utility — City of Edmonton</p>
            </div>
        </div>
        <div class="flex gap-2">
            <span class="text-[10px] bg-slate-800 text-blue-400 px-2 py-1 rounded border border-blue-900/50 font-mono text-white uppercase tracking-tighter">Secure Instance</span>
        </div>
    </header>

    <div class="flex-1 flex overflow-hidden">
        <aside class="w-80 p-6 flex flex-col gap-8 border-r border-slate-800 bg-slate-900/20 overflow-y-auto">
            <!-- Step 1: Select -->
            <section>
                <label class="sidebar-label mb-3">1 · SELECT FILES</label>
                <div id="drop-zone" class="border-2 border-dashed border-slate-700 rounded-xl p-6 text-center hover:border-blue-500 hover:bg-blue-500/5 transition-all cursor-pointer group relative">
                    <input type="file" id="file-input" class="absolute inset-0 opacity-0 cursor-pointer" accept=".oxps,.xps" multiple>
                    <svg class="w-8 h-8 text-slate-500 mx-auto group-hover:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    <p id="file-name-display" class="text-xs text-slate-400 mt-3 break-all">Drag & drop or <span class="text-blue-400 font-bold underline">Browse...</span></p>
                </div>
            </section>

            <!-- Step 2: Advanced Options (Collapsible) -->
            <section>
                <details class="group">
                    <summary class="sidebar-label cursor-pointer flex justify-between items-center list-none select-none hover:text-slate-200 transition-colors">
                        <span>2 · ADVANCED OPTIONS</span>
                        <svg class="w-3 h-3 transition-transform group-open:rotate-180 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </summary>
                    <div class="mt-5 flex flex-col gap-5">
                        <!-- Page Range -->
                        <div class="grid grid-cols-2 gap-2">
                            <div class="col-span-2 text-[11px] text-slate-300 mb-1">Page Range (0 = All)</div>
                            <div>
                                <input type="number" id="first_page" placeholder="First" min="0" value="0" class="w-full bg-slate-800 border border-slate-700 text-xs p-2.5 text-white outline-none focus:ring-1 focus:ring-blue-500">
                            </div>
                            <div>
                                <input type="number" id="last_page" placeholder="Last" min="0" value="0" class="w-full bg-slate-800 border border-slate-700 text-xs p-2.5 text-white outline-none focus:ring-1 focus:ring-blue-500">
                            </div>
                        </div>

                        <!-- Basic Options -->
                        <div class="space-y-4">
                            <div>
                                <span class="text-[11px] text-slate-300 block mb-1">Color Mode</span>
                                <select id="color_mode" class="w-full bg-slate-800 border border-slate-700 text-xs p-2.5 text-white outline-none focus:ring-1 focus:ring-blue-500">
                                    <option value="grayscale" selected>Grayscale (Smallest file)</option>
                                    <option value="color">Full Color</option>
                                </select>
                            </div>

                            <div>
                                <span class="text-[11px] text-slate-300 block mb-1">Optimization Presets</span>
                                <select id="optimization" class="w-full bg-slate-800 border border-slate-700 text-xs p-2.5 text-white outline-none focus:ring-1 focus:ring-blue-500">
                                    <option value="default" selected>Balanced (Standard)</option>
                                    <option value="screen">Screen (72 DPI - Low Res)</option>
                                    <option value="ebook">Ebook (150 DPI - Medium)</option>
                                    <option value="printer">Printer (300 DPI - High)</option>
                                    <option value="prepress">Prepress (Max quality)</option>
                                </select>
                            </div>

                            <div class="flex items-center justify-between py-1 px-1 bg-slate-800/40 rounded">
                                <span class="text-[11px] text-slate-300">Scale to Fit Page</span>
                                <input type="checkbox" id="fit_page" checked class="w-4 h-4 accent-blue-600">
                            </div>

                            <div>
                                <span class="text-[11px] text-slate-300 block mb-1">Render Quality (DPI)</span>
                                <select id="quality" class="w-full bg-slate-800 border border-slate-700 text-xs p-2.5 text-white outline-none focus:ring-1 focus:ring-blue-500">
                                    <option value="72">72 DPI — Draft</option>
                                    <option value="108" selected>108 DPI — Balanced</option>
                                    <option value="144">144 DPI — High</option>
                                    <option value="300">300 DPI — Print</option>
                                </select>
                            </div>

                            <div>
                                <span class="text-[11px] text-slate-300 block mb-1">Background</span>
                                <select id="background" class="w-full bg-slate-800 border border-slate-700 text-xs p-2.5 text-white outline-none focus:ring-1 focus:ring-blue-500">
                                    <option value="white" selected>White</option>
                                    <option value="transparent">Transparent</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </details>
            </section>

            <!-- Step 3: Convert -->
            <section>
                <label class="sidebar-label mb-3">3 · CONVERT</label>
                <button id="convert-btn" class="w-full py-3.5 px-4 bg-slate-700 text-slate-400 font-bold text-xs rounded transition-all flex items-center justify-center gap-2 cursor-not-allowed shadow-lg" disabled>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"></circle></svg>
                    <span>CONVERT TO PDF</span>
                </button>
            </section>

            <!-- Step 4: Download -->
            <section id="step-4" class="hidden transition-all duration-500 translate-y-2 opacity-0">
                <label class="sidebar-label mb-3 text-white">4 · DOWNLOAD</label>
                <a id="download-link" href="#" class="w-full py-4 px-4 bg-green-600 hover:bg-green-500 text-white font-black text-xs rounded transition-all flex items-center justify-center gap-2 shadow-lg shadow-green-900/20 active:scale-95 text-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    <span id="download-text">SAVE CONVERTED FILE</span>
                </a>
            </section>

            <section class="mt-auto pt-6 border-t border-slate-800">
                <label class="sidebar-label mb-3">System Maintenance</label>
                <button id="clear-work-btn" class="w-full py-2.5 px-3 bg-red-900/10 hover:bg-red-900/30 text-red-400 border border-red-900/40 text-[10px] font-bold rounded transition-all flex items-center justify-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    <span>CLEAR WORKING DIRECTORY</span>
                </button>
            </section>
        </aside>

        <main class="flex-1 bg-slate-900 p-6 flex flex-col gap-4 overflow-hidden relative">
            <div id="preview-container" class="flex-1 panel rounded-xl flex items-center justify-center relative overflow-hidden shadow-2xl shadow-black/50">
                <div id="initial-preview" class="text-center opacity-30">
                    <svg class="w-24 h-24 text-slate-600 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    <p class="text-slate-400 text-base font-medium">Your converted PDF will appear here</p>
                    <p id="batch-notice" class="text-[10px] text-slate-500 mt-2 hidden italic">Note: Only the first file is shown in preview.</p>
                </div>
                <div id="loader" class="hidden absolute inset-0 bg-slate-900/95 backdrop-blur-md z-20 flex flex-col items-center justify-center">
                    <div class="animate-spin rounded-full h-16 w-16 border-4 border-blue-500 border-t-transparent shadow-xl mb-6"></div>
                    <p class="text-blue-400 text-sm font-black tracking-widest uppercase animate-pulse">Processing Document...</p>
                    <p class="text-[10px] text-slate-500 mt-3">Large files may take several minutes. Please do not close this window.</p>
                </div>
                <iframe id="pdf-viewer" class="hidden w-full h-full border-none bg-slate-800" src=""></iframe>
            </div>
            <div class="flex justify-between items-center px-2 text-white">
                <span class="text-[9px] text-slate-500 uppercase tracking-widest font-mono">Backend Engine: GXPS v9.54 Build &nbsp;&nbsp;&nbsp; || &nbsp;&nbsp;&nbsp;100% Local Server Processing</span>
            </div>
        </main>
    </div>

    <script>
        const fileInput = document.getElementById('file-input');
        const fileNameDisplay = document.getElementById('file-name-display');
        const convertBtn = document.getElementById('convert-btn');
        const downloadLink = document.getElementById('download-link');
        const downloadText = document.getElementById('download-text');
        const step4 = document.getElementById('step-4');
        const loader = document.getElementById('loader');
        const pdfViewer = document.getElementById('pdf-viewer');
        const initialPreview = document.getElementById('initial-preview');
        const batchNotice = document.getElementById('batch-notice');
        const dropZone = document.getElementById('drop-zone');
        const clearWorkBtn = document.getElementById('clear-work-btn');

        // Options
        const firstPageInp = document.getElementById('first_page');
        const lastPageInp = document.getElementById('last_page');
        const colorModeSelect = document.getElementById('color_mode');
        const optimizationSelect = document.getElementById('optimization');
        const fitPageCheck = document.getElementById('fit_page');
        const qualitySelect = document.getElementById('quality');
        const bgSelect = document.getElementById('background');

        let selectedFiles = [];

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) handleFilesSelect(e.target.files);
        });

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, e => {
                e.preventDefault();
                e.stopPropagation();
                if (eventName === 'dragover') dropZone.classList.add('border-blue-500', 'bg-blue-500/10');
                if (eventName === 'dragleave' || eventName === 'drop') dropZone.classList.remove('border-blue-500', 'bg-blue-500/10');
            });
        });

        dropZone.addEventListener('drop', e => {
            if (e.dataTransfer.files.length > 0) handleFilesSelect(e.dataTransfer.files);
        });

        function handleFilesSelect(files) {
            selectedFiles = Array.from(files);
            const count = selectedFiles.length;
            if (count > 1) {
                fileNameDisplay.textContent = `${count} files selected`;
                batchNotice.classList.remove('hidden');
            } else {
                fileNameDisplay.textContent = selectedFiles[0].name;
                batchNotice.classList.add('hidden');
            }
            fileNameDisplay.classList.add('text-blue-400', 'font-bold');
            convertBtn.disabled = false;
            convertBtn.classList.remove('bg-slate-700', 'text-slate-400', 'cursor-not-allowed');
            convertBtn.classList.add('accent-blue', 'text-white', 'cursor-pointer');
            step4.classList.add('hidden');
            step4.classList.remove('opacity-100', 'translate-y-0');
            pdfViewer.classList.add('hidden');
            initialPreview.classList.remove('hidden');
        }

        convertBtn.addEventListener('click', () => {
            if (selectedFiles.length === 0) return;
            const formData = new FormData();
            selectedFiles.forEach(file => {
                formData.append('files[]', file);
            });
            
            // Collect advanced options
            formData.append('quality', qualitySelect.value);
            formData.append('background', bgSelect.value);
            formData.append('first_page', firstPageInp.value);
            formData.append('last_page', lastPageInp.value);
            formData.append('color_mode', colorModeSelect.value);
            formData.append('optimization', optimizationSelect.value);
            formData.append('fit_page', fitPageCheck.checked);

            loader.classList.remove('hidden');
            convertBtn.disabled = true;
            convertBtn.innerHTML = '<span>CONVERTING...</span>';

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const previewUrl = `?preview=${data.id}`;
                    const downloadUrl = `?download=${data.id}`;
                    pdfViewer.src = previewUrl;
                    pdfViewer.classList.remove('hidden');
                    initialPreview.classList.add('hidden');
                    downloadLink.href = downloadUrl;
                    downloadText.textContent = data.type === 'zip' ? `SAVE ALL (${data.count} FILES)` : 'SAVE CONVERTED PDF';
                    step4.classList.remove('hidden');
                    setTimeout(() => {
                        step4.classList.add('opacity-100', 'translate-y-0');
                    }, 100);
                } else {
                    alert('Error: ' + (data.error || 'Conversion engine failed.'));
                }
            })
            .catch(err => {
                alert('Connection error. Server might be timing out for large batches or very large files.');
                console.error(err);
            })
            .finally(() => {
                loader.classList.add('hidden');
                convertBtn.disabled = false;
                convertBtn.innerHTML = '<span>CONVERT TO PDF</span>';
            });
        });

        clearWorkBtn.addEventListener('click', () => {
            if (confirm('Are you sure you want to delete ALL files in the working directory? This action cannot be undone.')) {
                const formData = new FormData();
                formData.append('action', 'clear_work');
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('Working directory cleared successfully.');
                        location.reload();
                    } else {
                        alert('Error clearing directory: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(err => {
                    alert('Request failed. Check console for details.');
                    console.error(err);
                });
            }
        });
    </script>
    <a href="http://web-mage.ca/games/"><img src="WebMage-sm.webp" alt="WebMage Logo" style="position: fixed; right: 16px; bottom: 16px; height: 50px; z-index: 9999; pointer-events: auto;"></a>
</body>
</html><?php ob_end_flush(); ?>
