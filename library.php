<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library | Crixoofer</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php $PAGE_TITLE='Library'; $PAGE_ACTIVE='library'; require_once __DIR__.'/inc/header.php'; ?>

<main style="align-items:flex-start;">
    <div class="page-head">
        <div>
            <h1>Your Library</h1>
            <p class="page-sub" id="libSub">Loading...</p>
        </div>
        <div class="page-actions">
            <input type="text" id="searchInput" class="global-search-input" placeholder="Filter..." style="background:var(--glass);border:1px solid var(--glass-border);border-radius:10px;padding:.5rem 1rem;color:#fff;width:180px;outline:none;backdrop-filter:blur(12px);" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
            <button class="view-btn" id="gridBtn" title="Grid view">▦</button>
            <button class="view-btn" id="listBtn" title="List view">☰</button>
        </div>
    </div>
    <div id="libContainer" style="width:100%;max-width:1100px;"></div>
</main>
</div>

<script>
    const PLACEHOLDER = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='200' height='200'><rect width='200' height='200' rx='10' fill='%23181820'/><text x='100' y='118' font-size='64' text-anchor='middle' fill='%239b5de5'>♪</text></svg>";
    const container = document.getElementById('libContainer');
    const searchEl  = document.getElementById('searchInput');
    let allTracks = [];
    let mode = localStorage.getItem('crixoofer_libview') || 'grid';

    function esc(s) { return (s || '').replace(/'/g, "\\'").replace(/"/g, '&quot;'); }

    async function loadTracks() {
        const res = await fetch('api/tracks.php?action=list');
        allTracks = await res.json();
        const artists = new Set(allTracks.map(t => t.artist)).size;
        document.getElementById('libSub').textContent = `${allTracks.length} tracks • ${artists} artists`;
        render(allTracks);
    }

    function render(tracks) {
        document.getElementById('gridBtn').classList.toggle('on', mode === 'grid');
        document.getElementById('listBtn').classList.toggle('on', mode === 'list');
        if (!tracks.length) { container.innerHTML = '<p style="color:#556;padding:2rem;">No tracks found — upload some heat 🔥</p>'; return; }

        if (mode === 'grid') {
            container.className = '';
            container.innerHTML = `<div class="lib-grid">` + tracks.map(t => `
                <div class="track-card" onclick="queueTrack(${t.id})">
                    <div class="cover-wrap">
                        <img src="${t.cover ? 'assets/covers/'+t.cover : PLACEHOLDER}" loading="lazy">
                        <button class="card-play">▶</button>
                    </div>
                    <h4>${t.title}</h4>
                    <span>${t.artist}</span>
                </div>`).join('') + `</div>`;
        } else {
            container.className = '';
            container.innerHTML = `<div class="track-list">` + tracks.map(t => `
                <div class="track-item" onclick="queueTrack(${t.id})">
                    <img class="cover-thumb" src="${t.cover ? 'assets/covers/'+t.cover : PLACEHOLDER}">
                    <div class="track-meta">
                        <h4>${t.title}</h4>
                        <span>${t.artist}${t.album ? ' • '+t.album : ''}</span>
                    </div>
                    <div class="play-icon">▶</div>
                </div>`).join('') + `</div>`;
        }
    }

    function queueTrack(id) {
        const t = allTracks.find(x => x.id == id);
        localStorage.setItem('crixoofer_current', JSON.stringify(t));
        window.location.href = 'index.php';
    }

    searchEl.addEventListener('input', e => {
        const q = e.target.value.toLowerCase();
        render(allTracks.filter(t =>
            t.title.toLowerCase().includes(q) ||
            t.artist.toLowerCase().includes(q) ||
            (t.album || '').toLowerCase().includes(q)));
    });
    document.getElementById('gridBtn').onclick = () => { mode = 'grid'; localStorage.setItem('crixoofer_libview', mode); render(allTracks); };
    document.getElementById('listBtn').onclick = () => { mode = 'list'; localStorage.setItem('crixoofer_libview', mode); render(allTracks); };

    loadTracks();
</script>
</body>
</html>