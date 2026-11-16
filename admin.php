<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Crixoofer</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php $PAGE_TITLE='Admin'; $PAGE_ACTIVE='admin'; require_once __DIR__.'/inc/header.php'; ?>

<main style="align-items:flex-start;">
    <div class="page-head">
        <div>
            <h1>Admin</h1>
            <p class="page-sub">Library management & stats</p>
        </div>
    </div>
    <div class="stat-bar" id="stats"></div>
    <div class="glass-card" style="width:100%;max-width:1100px;overflow:hidden;">
        <table class="admin-table">
            <thead><tr><th>Cover</th><th>Title</th><th>Artist</th><th>Album</th><th>Uploaded</th><th>Action</th></tr></thead>
            <tbody id="adminBody"></tbody>
        </table>
    </div>
</main>
</div>

<script>
    const PLACEHOLDER = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='44' height='44'><rect width='44' height='44' rx='6' fill='%23181820'/><text x='22' y='30' font-size='18' text-anchor='middle' fill='%239b5de5'>♪</text></svg>";

    async function loadAdmin() {
        const res = await fetch('api/tracks.php?action=list');
        const tracks = await res.json();
        const withCovers = tracks.filter(t => t.cover).length;

        document.getElementById('stats').innerHTML = `
            <div class="stat-card glass-card"><div class="num">${tracks.length}</div><div class="label">Total Tracks</div></div>
            <div class="stat-card glass-card"><div class="num">${new Set(tracks.map(t=>t.artist)).size}</div><div class="label">Artists</div></div>
            <div class="stat-card glass-card"><div class="num">${withCovers}</div><div class="label">With Cover Art</div></div>
        `;

        document.getElementById('adminBody').innerHTML = tracks.map(t => `
            <tr>
                <td><img class="cover-thumb" src="${t.cover ? 'assets/covers/'+t.cover : PLACEHOLDER}"></td>
                <td><b>${t.title}</b></td>
                <td style="color:#99a">${t.artist}</td>
                <td style="color:#99a">${t.album || '—'}</td>
                <td style="color:#778;font-size:.8rem;">${new Date(t.uploaded_at).toLocaleString()}</td>
                <td><button class="del-btn" onclick="deleteTrack(${t.id})">Delete</button></td>
            </tr>
        `).join('') || '<tr><td colspan="6" style="text-align:center;color:#556;padding:2.5rem;">No tracks uploaded yet</td></tr>';
    }

    async function deleteTrack(id) {
        if (!confirm('Delete this track permanently? File will be removed from server.')) return;
        const formData = new URLSearchParams(); formData.append('id', id); formData.append('action', 'delete');
        await fetch('api/tracks.php', { method:'POST', body:formData });
        loadAdmin();
    }

    loadAdmin();
</script>
</body>
</html>