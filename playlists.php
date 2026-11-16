<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Playlists | Crixoofer</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php $PAGE_TITLE='Playlists'; $PAGE_ACTIVE='playlists'; require_once __DIR__.'/inc/header.php'; ?>

<main style="align-items:flex-start;">
    <div class="page-head">
        <div>
            <h1>Playlists</h1>
            <p class="page-sub">Curate your rotations</p>
        </div>
    </div>
    <div class="pl-container">
        <div class="panel glass-card">
            <h3>YOUR PLAYLISTS</h3>
            <div class="create-row">
                <input type="text" id="newPlName" placeholder="New playlist name...">
                <button id="createPlBtn">+</button>
            </div>
            <div class="scroll-list" id="plList"></div>
        </div>
        <div class="panel glass-card">
            <h3 id="rightPanelTitle">Select a playlist</h3>
            <div class="scroll-list" id="rightList"></div>
        </div>
    </div>
</main>
</div>

<script>
    const PLACEHOLDER = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='34' height='34'><rect width='34' height='34' rx='6' fill='%23181820'/><text x='17' y='24' font-size='15' text-anchor='middle' fill='%239b5de5'>♪</text></svg>";
    let playlists = [], tracks = [], activePlId = null, plCounts = {};

    async function loadData() {
        const [plRes, trRes] = await Promise.all([
            fetch('api/playlists.php?action=list'),
            fetch('api/tracks.php?action=list')
        ]);
        playlists = await plRes.json();
        tracks = await trRes.json();
        await Promise.all(playlists.map(async p => {
            const r = await fetch(`api/playlists.php?action=tracks&id=${p.id}`);
            plCounts[p.id] = (await r.json()).length;
        }));
        renderPlaylists();
    }

    function renderPlaylists() {
        document.getElementById('plList').innerHTML = playlists.map((p, i) => `
            <div class="pl-item ${activePlId==p.id?'active':''}" onclick="selectPlaylist(${p.id},'${p.name.replace(/'/g,"\\'")}')">
                <div class="pl-num">${i+1}</div>
                <span style="flex:1;font-weight:600;">${p.name}</span>
                <span class="pl-count">${plCounts[p.id]||0} tracks</span>
                <span class="delete-x" onclick="event.stopPropagation();deletePlaylist(${p.id})">✕</span>
            </div>
        `).join('') || '<p style="color:#556;padding:1rem;">No playlists yet — create one above</p>';
    }

    async function selectPlaylist(id, name) {
        activePlId = id;
        document.getElementById('rightPanelTitle').textContent = name.toUpperCase();
        renderPlaylists();
        const res = await fetch(`api/playlists.php?action=tracks&id=${id}`);
        const plTracks = await res.json();
        const ids = plTracks.map(t => t.id);

        let html = plTracks.map(t => `
            <div class="add-track-item">
                <img src="${t.cover ? 'assets/covers/'+t.cover : PLACEHOLDER}">
                <span style="flex:1;"><b>${t.title}</b> <span style="color:#889;font-size:.8rem;">— ${t.artist}</span></span>
                <span class="delete-x" onclick="removeFromPlaylist(${t.id})">✕</span>
            </div>`).join('');

        const available = tracks.filter(t => !ids.includes(t.id));
        if (available.length) {
            html += '<div style="padding:.9rem .6rem .4rem;color:#556;font-size:.68rem;letter-spacing:1.5px;font-weight:700;">+ ADD FROM LIBRARY</div>';
            html += available.map(t => `
                <div class="add-track-item" onclick="addToPlaylist(${t.id})">
                    <img src="${t.cover ? 'assets/covers/'+t.cover : PLACEHOLDER}">
                    <span style="flex:1;">${t.title} <span style="color:#889;font-size:.8rem;">— ${t.artist}</span></span>
                    <span style="color:rgb(var(--ambient));font-weight:800;">+</span>
                </div>`).join('');
        }
        document.getElementById('rightList').innerHTML = html || '<p style="color:#556;padding:1rem;">Empty playlist</p>';
    }

    document.getElementById('createPlBtn').addEventListener('click', async () => {
        const name = document.getElementById('newPlName').value.trim();
        if (!name) return;
        await fetch('api/playlists.php?action=create', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`name=${encodeURIComponent(name)}` });
        document.getElementById('newPlName').value = '';
        loadData();
    });

    async function deletePlaylist(id) {
        if (!confirm('Delete this playlist?')) return;
        await fetch('api/playlists.php?action=delete', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`id=${id}` });
        if (activePlId == id) { activePlId = null; document.getElementById('rightPanelTitle').textContent = 'Select a playlist'; document.getElementById('rightList').innerHTML = ''; }
        loadData();
    }
    async function addToPlaylist(trackId) {
        await fetch('api/playlists.php?action=add_track', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`playlist_id=${activePlId}&track_id=${trackId}` });
        selectPlaylist(activePlId, document.getElementById('rightPanelTitle').textContent);
        loadData();
    }
    async function removeFromPlaylist(trackId) {
        await fetch('api/playlists.php?action=remove_track', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`playlist_id=${activePlId}&track_id=${trackId}` });
        selectPlaylist(activePlId, document.getElementById('rightPanelTitle').textContent);
        loadData();
    }

    loadData();
</script>
</body>
</html>