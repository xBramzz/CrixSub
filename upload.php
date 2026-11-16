<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload | Crixoofer</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .upload-zone { width:100%; max-width:600px; min-height:300px; border:2px dashed #2a2a35; border-radius:16px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:1rem; transition:0.3s; cursor:pointer; position:relative; }
        .upload-zone.dragover { border-color:var(--purple); background:rgba(155,93,229,0.05); }
        .upload-zone input { position:absolute; inset:0; opacity:0; cursor:pointer; }
        .upload-icon { font-size:3rem; color:var(--purple); }
        .form-fields { width:100%; max-width:600px; display:flex; flex-direction:column; gap:1rem; margin-top:2rem; }
        .form-fields input { background:var(--surface); border:1px solid #2a2a35; padding:1rem; border-radius:8px; color:#fff; outline:none; }
        .form-fields input:focus { border-color:var(--purple); }
        .upload-btn { background:linear-gradient(to right, var(--purple), var(--pink)); border:none; padding:1rem; border-radius:8px; color:#fff; font-weight:700; font-size:1rem; cursor:pointer; transition:0.2s; }
        .upload-btn:hover { opacity:0.9; box-shadow:0 0 20px rgba(155,93,229,0.4); }
        .status { margin-top:1rem; font-size:0.9rem; color:#888; min-height:1.5rem; }
        .status.success { color:#00ff88; }
        .status.error { color:#ff4466; }
    </style>
</head>
<body>
<?php $PAGE_TITLE='Upload'; $PAGE_ACTIVE='upload'; require_once __DIR__.'/inc/header.php'; ?>

<main>
    <div class="upload-zone" id="dropZone">
        <input type="file" id="fileInput" accept=".mp3,.wav,.ogg">
        <div class="upload-icon">📁</div>
        <p>Drop MP3/WAV/OGG here or click to browse</p>
        <p id="fileName" style="color:var(--pink);font-weight:600;"></p>
    </div>
    <div class="form-fields">
        <input type="text" id="titleInput" placeholder="Track Title (optional)">
        <input type="text" id="artistInput" placeholder="Artist Name (optional)">
        <button class="upload-btn" id="uploadBtn">Upload to Crixoofer</button>
    </div>
    <div class="status" id="statusMsg"></div>
</main>
</div>

<script>
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const fileNameEl = document.getElementById('fileName');
    const uploadBtn = document.getElementById('uploadBtn');
    const statusMsg = document.getElementById('statusMsg');
    let selectedFile = null;

    ['dragenter','dragover'].forEach(e => dropZone.addEventListener(e, (ev) => { ev.preventDefault(); dropZone.classList.add('dragover'); }));
    ['dragleave','drop'].forEach(e => dropZone.addEventListener(e, (ev) => { ev.preventDefault(); dropZone.classList.remove('dragover'); }));
    
    dropZone.addEventListener('drop', (e) => { if(e.dataTransfer.files.length) setFile(e.dataTransfer.files[0]); });
    fileInput.addEventListener('change', () => { if(fileInput.files.length) setFile(fileInput.files[0]); });

    function setFile(file) {
        selectedFile = file;
        fileNameEl.textContent = file.name;
        statusMsg.textContent = '';
    }

    uploadBtn.addEventListener('click', async () => {
        if(!selectedFile) { statusMsg.textContent = 'Select a file first'; statusMsg.className='status error'; return; }
        
        const formData = new FormData();
        formData.append('audio', selectedFile);
        formData.append('title', document.getElementById('titleInput').value);
        formData.append('artist', document.getElementById('artistInput').value);

        statusMsg.textContent = 'Uploading...'; statusMsg.className='status';
        try {
            const res = await fetch('api/upload.php', { method:'POST', body:formData });
            const data = await res.json();
            if(data.success) {
                statusMsg.textContent = `✅ ${data.title} — ${data.artist}${data.album ? ' ('+data.album+')' : ''}${data.cover ? ' 🖼️ cover found' : ''}`;
                statusMsg.className='status success';
                selectedFile = null; fileNameEl.textContent = ''; fileInput.value = '';
            } else {
                statusMsg.textContent = '❌ ' + (data.error || 'Upload failed'); statusMsg.className='status error';
            }
        } catch(err) {
            statusMsg.textContent = '❌ Network error'; statusMsg.className='status error';
        }
    });
</script>
</body>
</html>