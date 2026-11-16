<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crixoofer | Bass Engine</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="player-page">
<?php $PAGE_TITLE='Player'; $PAGE_ACTIVE='index'; require_once __DIR__.'/inc/header.php'; ?>

<main class="player">
    <div class="player-left">
        <div class="controls">
            <div class="track-info now-playing">
                <img id="trackCover" class="cover-art" src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='80' height='80'><rect width='80' height='80' rx='8' fill='%23181820'/><text x='40' y='52' font-size='28' text-anchor='middle' fill='%239b5de5'>♪</text></svg>" alt="">
                <div>
                    <h2 id="trackTitle">Select a Track</h2>
                    <p id="trackArtist">From Library</p>
                    <p id="trackAlbum" class="album-line"></p>
                </div>
            </div>

            <div class="seek-row">
                <span id="curTime">0:00</span>
                <input type="range" id="seek" min="0" max="100" value="0">
                <span id="durTime">0:00</span>
            </div>

            <div class="transport">
                <button class="t-btn" id="shuffleBtn" title="Shuffle"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3h5v5"/><path d="M4 20L21 3"/><path d="M21 16v5h-5"/><path d="M15 15l6 6"/><path d="M4 4l5 5"/></svg></button>
                <button class="t-btn" id="prevBtn" title="Previous"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="19 20 9 12 19 4 19 20"/><line x1="5" y1="19" x2="5" y2="5"/></svg></button>
                <button class="t-btn btn-play" id="playBtn">▶</button>
                <button class="t-btn" id="nextBtn" title="Next"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19"/></svg></button>
                <button class="t-btn" id="repeatBtn" title="Repeat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg></button>
                <button class="t-btn" id="fsBtn" title="Fullscreen (F)"></button>
            </div>

            <div class="dsp-panel">
                <div class="knob"><label>Bass Boost</label><input type="range" id="boostSlider" min="0" max="24" value="12"></div>
                <div class="knob"><label>Crossover Hz</label><input type="range" id="crossSlider" min="40" max="200" value="90"></div>
                <div class="knob"><label>Sub Level</label><input type="range" id="subSlider" min="0" max="150" value="120"></div>
                <div class="knob"><label>Master Vol</label><input type="range" id="masterSlider" min="0" max="100" value="80"></div>
            </div>

            <div class="presets">
                <button class="preset" data-preset="flat">FLAT</button>
                <button class="preset" data-preset="car">CAR BASS</button>
                <button class="preset" data-preset="club">CLUB</button>
                <button class="preset" data-preset="phone">HEADPHONE</button>
                <button class="preset" data-preset="quake">EARTHQUAKE 💀</button>
            </div>
        </div>
    </div>

    <div id="viz-container">
        <canvas id="wooferCanvas"></canvas>
        <div class="viz-hud">
            <div class="hud-chip"><span class="hud-icon">⚡</span><span id="wattReadout">0W</span></div>
            <div class="hud-chip"><span class="hud-icon">💥</span><span id="dropCount">0</span><span class="hud-label">DROPS</span></div>
        </div>
    </div>
</main>
</div>

<audio id="audioEl" crossorigin="anonymous"></audio>

<script src="assets/js/player.js"></script>
<script src="assets/js/visualizer.js"></script>
<script>
    const $ = id => document.getElementById(id);
    const audio = $('audioEl');
    const engine = new CrixooferEngine(audio);
    const visuals = new CrixooferVisuals('wooferCanvas', engine);

    const PLACEHOLDER = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='80' height='80'><rect width='80' height='80' rx='8' fill='%23181820'/><text x='40' y='52' font-size='28' text-anchor='middle' fill='%239b5de5'>♪</text></svg>";

    let queue = [], current = -1, shuffle = false, repeat = false;
    const playBtn=$('playBtn'), prevBtn=$('prevBtn'), nextBtn=$('nextBtn'),
          shufBtn=$('shuffleBtn'), repBtn=$('repeatBtn'), seek=$('seek'),
          boost=$('boostSlider'), cross=$('crossSlider'), sub=$('subSlider'), mast=$('masterSlider');

    // ---------- AMBIENT ART GLOW ----------
    const ambientCache = {};
    function applyAmbient(rgbStr) {
        document.documentElement.style.setProperty('--ambient', rgbStr);
        visuals.setAmbient(rgbStr);
    }
    function extractAmbient(url) {
        if (!url || url.startsWith('data:')) { applyAmbient('155,93,229'); return; }
        if (ambientCache[url]) { applyAmbient(ambientCache[url]); return; }
        const img = new Image();
        img.onload = () => {
            try {
                const c = document.createElement('canvas'); c.width = c.height = 32;
                const x = c.getContext('2d'); x.drawImage(img, 0, 0, 32, 32);
                const d = x.getImageData(0, 0, 32, 32).data;
                let r=0, g=0, b=0, n=0;
                for (let i = 0; i < d.length; i += 4) {
                    const R=d[i], G=d[i+1], B=d[i+2];
                    const mx=Math.max(R,G,B), mn=Math.min(R,G,B);
                    if (mx < 30 || mx - mn < 25) continue; // skip blacks & greys
                    r+=R; g+=G; b+=B; n++;
                }
                const col = n
                    ? `${Math.min(255,Math.round(r/n*1.5))},${Math.min(255,Math.round(g/n*1.5))},${Math.min(255,Math.round(b/n*1.5))}`
                    : '155,93,229';
                ambientCache[url] = col;
                applyAmbient(col);
            } catch (e) { applyAmbient('155,93,229'); }
        };
        img.src = url;
    }

    function applyDSP() {
        engine.setBass(+boost.value, +cross.value, +sub.value / 100);
        audio.volume = +mast.value / 100;
    }
    [boost, cross, sub, mast].forEach(el => el.addEventListener('input', applyDSP));
    mast.addEventListener('input', () => localStorage.setItem('crixoofer_vol', mast.value));

    const savedDSP = JSON.parse(localStorage.getItem('crixoofer_dsp') || 'null');
    if (savedDSP) {
        boost.value = savedDSP.boostDb ?? 12;
        cross.value = savedDSP.crossoverHz ?? 90;
        sub.value   = (savedDSP.subVolume ?? 1.2) * 100;
    }
    mast.value = localStorage.getItem('crixoofer_vol') ?? 80;
    applyDSP();

    const PRESETS = {
        flat:  { b: 0,  c: 80,  s: 100 },
        car:   { b: 12, c: 90,  s: 120 },
        club:  { b: 8,  c: 110, s: 110 },
        phone: { b: 6,  c: 70,  s: 90  },
        quake: { b: 20, c: 60,  s: 150 }
    };
    document.querySelectorAll('.preset').forEach(btn => btn.addEventListener('click', () => {
        const p = PRESETS[btn.dataset.preset];
        boost.value = p.b; cross.value = p.c; sub.value = p.s;
        applyDSP();
        document.querySelectorAll('.preset').forEach(b => b.classList.toggle('on', b === btn));
    }));

    function setCover(t) {
        const url = t.cover ? 'assets/covers/' + t.cover : null;
        $('trackCover').src = url || PLACEHOLDER;
        $('trackAlbum').textContent = t.album || '';
        extractAmbient(url);
    }

    function loadTrack(i, autoplay = true) {
        if (i < 0 || i >= queue.length) return;
        current = i;
        const t = queue[i];
        audio.src = `assets/uploads/${t.filename}`;
        $('trackTitle').textContent = t.title;
        $('trackArtist').textContent = t.artist;
        setCover(t);
        localStorage.setItem('crixoofer_last', JSON.stringify(t));
        if (autoplay) { engine.resume(); audio.play(); playBtn.textContent = '⏸'; }
    }

    function nextTrack() {
        if (!queue.length) return;
        if (shuffle) loadTrack(Math.floor(Math.random() * queue.length));
        else if (current + 1 < queue.length) loadTrack(current + 1);
        else if (repeat) loadTrack(0);
        else { audio.pause(); playBtn.textContent = '▶'; }
    }
    function prevTrack() {
        if (!queue.length) return;
        if (audio.currentTime > 3) { audio.currentTime = 0; return; }
        loadTrack(current - 1 >= 0 ? current - 1 : queue.length - 1);
    }

    playBtn.onclick = () => {
        engine.resume();
        if (audio.paused) { audio.play(); playBtn.textContent = '⏸'; }
        else { audio.pause(); playBtn.textContent = '▶'; }
    };
    nextBtn.onclick = nextTrack;
    prevBtn.onclick = prevTrack;
    shufBtn.onclick = () => { shuffle = !shuffle; shufBtn.classList.toggle('on', shuffle); };
    repBtn.onclick  = () => { repeat  = !repeat;  repBtn.classList.toggle('on', repeat);  };
    audio.addEventListener('ended', nextTrack);

    const FS_EXPAND   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>';
    const FS_COMPRESS = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3v3a2 2 0 0 1-2 2H3"/><path d="M21 8h-3a2 2 0 0 1-2-2V3"/><path d="M3 16h3a2 2 0 0 1 2 2v3"/><path d="M16 21v-3a2 2 0 0 1 2-2h3"/></svg>';
    const fsBtn = $('fsBtn');
    fsBtn.innerHTML = FS_EXPAND;
    fsBtn.onclick = () => {
        const el = document.querySelector('main.player');
        if (!document.fullscreenElement) (el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen).call(el);
        else document.exitFullscreen();
    };
    document.addEventListener('fullscreenchange', () => {
        fsBtn.innerHTML = document.fullscreenElement ? FS_COMPRESS : FS_EXPAND;
        setTimeout(() => visuals.resize(), 80);
    });

    const fmt = s => !isFinite(s) ? '0:00' : Math.floor(s/60) + ':' + String(Math.floor(s%60)).padStart(2,'0');
    audio.addEventListener('timeupdate', () => {
        if (!seek.matches(':active')) seek.value = (audio.currentTime / audio.duration * 100) || 0;
        $('curTime').textContent = fmt(audio.currentTime);
    });
    audio.addEventListener('loadedmetadata', () => $('durTime').textContent = fmt(audio.duration));
    seek.addEventListener('input', () => audio.currentTime = seek.value / 100 * audio.duration);

    document.addEventListener('keydown', e => {
        if (e.target.tagName === 'INPUT') return;
        if (e.code === 'Space')      { e.preventDefault(); playBtn.click(); }
        if (e.code === 'ArrowRight') nextTrack();
        if (e.code === 'ArrowLeft')  prevTrack();
        if (e.code === 'ArrowUp')    { e.preventDefault(); mast.value = Math.min(100, +mast.value + 5); applyDSP(); }
        if (e.code === 'ArrowDown')  { e.preventDefault(); mast.value = Math.max(0,  +mast.value - 5); applyDSP(); }
        if (e.code === 'KeyF')       fsBtn.click();
    });

    fetch('api/tracks.php?action=list').then(r => r.json()).then(tracks => {
        queue = tracks;
        const queued = JSON.parse(localStorage.getItem('crixoofer_current') || 'null');
        const last   = JSON.parse(localStorage.getItem('crixoofer_last') || 'null');
        localStorage.removeItem('crixoofer_current');
        const target = queued || last;
        if (target) {
            const idx = queue.findIndex(t => t.id == target.id);
            if (idx > -1) { loadTrack(idx, false); return; }
        }
        if (queue.length) loadTrack(0, false);
    });
</script>
</body>
</html>