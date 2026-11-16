// CRIXOOFER 3D visualizer (Three.js, loaded from assets/js/three.min.js next to this file)
const __crixBase = (document.currentScript && document.currentScript.src)
    ? document.currentScript.src.replace(/[^\/]*$/, '') : 'assets/js/';

class CrixooferVisuals {
    constructor(canvasId, engine) {
        this.canvas = document.getElementById(canvasId);   // becomes the 2D overlay (spectrum / messages)
        this.ctx = this.canvas.getContext('2d');
        this.engine = engine;
        this.disp = 0; this.env = 0; this.prevEnv = 0;
        this.shake = 0; this.clip = 0;
        this.rings = [];
        this.peaks = new Float32Array(96);
        this.ambient = '155,93,229';
        this.watts = 0; this.dispWatts = 0;
        this.drops = 0; this.lastDrop = 0;
        this.frame = 0;
        // CRIXOOFER upgrade controls + live measurements
        this.sensitivity = 1;
        this.visualMode = 'balanced';
        this.autoRotate = true;
        this.showMetrics = true;
        this.ledColor = '#9b5de5';
        this.metrics = { rms: 0, peak: 0, bass: 0, mid: 0, treble: 0, excursion: 0 };
        this.peakHold = 0;
        this.peakHoldAt = 0;
        this.ready = false; this.failed = false;
        // orbit camera state
        this.o = { theta: -0.5, phi: 0.16, zoom: 1 };
        this.dragging = false; this.lastInput = 0;

        const cs = this.canvas.style;
        cs.position = 'absolute'; cs.left = '0'; cs.top = '0'; cs.zIndex = '2';
        cs.touchAction = 'none'; cs.cursor = 'grab';
        this.bindInput();

        this.resize();
        window.addEventListener('resize', () => this.resize());
        this.loadThree().then(() => this.init3d()).catch(e => { console.error(e); this.failed = true; });
        this.animate();
    }

    setAmbient(rgbStr) { this.ambient = rgbStr; }

    setLEDColor(hex) {
        if (!/^#[0-9a-f]{6}$/i.test(hex)) return;
        this.ledColor = hex;
        if (this.mats && this.mats.cone) this.mats.cone.emissive.set(hex);
        if (this.ledMat) this.ledMat.color.set(hex);
    }

    setVisualMode(mode) {
        if (['balanced', 'cinematic', 'pulse', 'minimal'].includes(mode)) this.visualMode = mode;
    }

    setCameraPreset(preset) {
        const presets = {
            front: { theta: 0, phi: 0.08, zoom: 1 },
            threeQuarter: { theta: -0.5, phi: 0.16, zoom: 1 },
            side: { theta: Math.PI / 2, phi: 0.08, zoom: 1 },
            rear: { theta: Math.PI, phi: 0.08, zoom: 1 }
        };
        if (presets[preset]) { Object.assign(this.o, presets[preset]); this.lastInput = performance.now(); }
    }

    createControls() {
        if (this.controls || !this.canvas.parentElement) return;
        const root = this.controls = document.createElement('div');
        root.className = 'crixoofer-controls';
        Object.assign(root.style, {
            position: 'absolute', right: '14px', top: '14px', zIndex: '5', display: 'flex',
            flexWrap: 'wrap', alignItems: 'center', justifyContent: 'flex-end', gap: '7px',
            maxWidth: 'min(560px, calc(100% - 28px))', padding: '8px', borderRadius: '12px',
            background: 'rgba(9,9,14,.72)', border: '1px solid rgba(255,255,255,.12)',
            backdropFilter: 'blur(14px)', font: '11px Segoe UI, sans-serif', color: '#eee'
        });
        const makeButton = (label, action) => {
            const b = document.createElement('button'); b.type = 'button'; b.textContent = label;
            Object.assign(b.style, { border: '1px solid rgba(255,255,255,.14)', borderRadius: '7px',
                padding: '6px 9px', color: '#eee', background: 'rgba(255,255,255,.07)', cursor: 'pointer' });
            b.addEventListener('click', action); root.appendChild(b); return b;
        };
        const mode = document.createElement('select');
        [['balanced','Balanced'],['cinematic','Cinematic'],['pulse','Pulse'],['minimal','Minimal']].forEach(([v,t]) => {
            const o = document.createElement('option'); o.value = v; o.textContent = t; mode.appendChild(o);
        });
        Object.assign(mode.style, { border: '1px solid rgba(255,255,255,.14)', borderRadius: '7px', padding: '6px',
            color: '#eee', background: '#17171f', cursor: 'pointer' });
        mode.addEventListener('change', () => this.setVisualMode(mode.value)); root.appendChild(mode);
        const camera = document.createElement('select');
        [['threeQuarter','Camera'],['front','Front'],['side','Side'],['rear','Rear']].forEach(([v,t]) => {
            const o = document.createElement('option'); o.value = v; o.textContent = t; camera.appendChild(o);
        });
        Object.assign(camera.style, { border: '1px solid rgba(255,255,255,.14)', borderRadius: '7px', padding: '6px',
            color: '#eee', background: '#17171f', cursor: 'pointer' });
        camera.addEventListener('change', () => this.setCameraPreset(camera.value)); root.appendChild(camera);
        makeButton('Auto rotate: ON', e => {
            this.autoRotate = !this.autoRotate; e.currentTarget.textContent = 'Auto rotate: ' + (this.autoRotate ? 'ON' : 'OFF');
        });
        makeButton('Metrics: ON', e => {
            this.showMetrics = !this.showMetrics; e.currentTarget.textContent = 'Metrics: ' + (this.showMetrics ? 'ON' : 'OFF');
        });
        const color = document.createElement('input'); color.type = 'color'; color.value = this.ledColor;
        color.title = 'LED colour'; Object.assign(color.style, { width: '28px', height: '28px', padding: '0', border: '0', background: 'transparent', cursor: 'pointer' });
        color.addEventListener('input', () => this.setLEDColor(color.value)); root.appendChild(color);
        const sensitivity = document.createElement('input'); sensitivity.type = 'range'; sensitivity.min = '0.4';
        sensitivity.max = '2.5'; sensitivity.step = '0.05'; sensitivity.value = String(this.sensitivity);
        sensitivity.title = 'Audio sensitivity'; sensitivity.setAttribute('aria-label', 'Audio sensitivity');
        sensitivity.style.width = '76px'; sensitivity.addEventListener('input', () => { this.sensitivity = Number(sensitivity.value); });
        root.appendChild(sensitivity);
        this.canvas.parentElement.appendChild(root);
    }

    loadThree() {
        if (window.THREE) return Promise.resolve();
        return new Promise((res, rej) => {
            const s = document.createElement('script');
            s.src = __crixBase + 'three.min.js';
            s.onload = res;
            s.onerror = () => rej(new Error('three.min.js not found at ' + s.src));
            document.head.appendChild(s);
        });
    }

    bindInput() {
        const c = this.canvas;
        let lx = 0, ly = 0;
        c.addEventListener('pointerdown', e => {
            this.dragging = true; lx = e.clientX; ly = e.clientY;
            c.setPointerCapture(e.pointerId); c.style.cursor = 'grabbing';
        });
        c.addEventListener('pointermove', e => {
            if (!this.dragging) return;
            this.o.theta -= (e.clientX - lx) * 0.008;
            this.o.phi = Math.max(-0.15, Math.min(1.25, this.o.phi + (e.clientY - ly) * 0.006));
            lx = e.clientX; ly = e.clientY; this.lastInput = performance.now();
        });
        const up = () => { this.dragging = false; c.style.cursor = 'grab'; this.lastInput = performance.now(); };
        c.addEventListener('pointerup', up); c.addEventListener('pointercancel', up);
        c.addEventListener('wheel', e => {
            e.preventDefault();
            this.o.zoom = Math.max(0.45, Math.min(1.6, this.o.zoom * (1 + e.deltaY * 0.001)));
            this.lastInput = performance.now();
        }, { passive: false });
        c.addEventListener('dblclick', () => { this.o.theta = -0.5; this.o.phi = 0.16; this.o.zoom = 1; });
    }

    resize() {
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        const r = this.canvas.parentElement.getBoundingClientRect();
        this.canvas.width  = Math.max(1, r.width  * dpr);
        this.canvas.height = Math.max(1, r.height * dpr);
        this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        this.w = r.width; this.h = r.height;
        if (this.ready) this.resize3d();
    }

    animate() {
        requestAnimationFrame(() => this.animate());
        this.step(this.engine.getSubWaveform());
        this.draw(this.engine.getFrequencyData());
        this.frame++;
        if (this.frame % 8 === 0) this.updateHud();
    }

    updateHud() {
        this.dispWatts += (this.watts - this.dispWatts) * 0.45;
        const wEl = document.getElementById('wattReadout');
        const dEl = document.getElementById('dropCount');
        if (wEl) {
            wEl.textContent = '≈ ' + Math.round(this.dispWatts).toLocaleString() + 'W';
            wEl.classList.toggle('hot', this.dispWatts > 1800);
        }
        if (dEl) dEl.textContent = this.drops;
    }

    step(wf) {
        const newest = (wf[wf.length - 1] - 128) / 128;
        let peak = 0, sum = 0, n = 0;
        for (let i = 0; i < wf.length; i += 4) {
            const d = (wf[i] - 128) / 128;
            const a = d < 0 ? -d : d;
            if (a > peak) peak = a;
            sum += d * d; n++;
        }
        const rms = Math.sqrt(sum / n);

        // ---- WATTAGE: sub energy x sub gain x master volume ----
        const gain   = Math.max(0.05, this.engine.subGain ? this.engine.subGain.gain.value : 1);
        const master = this.engine.audio ? this.engine.audio.volume : 1;
        this.watts = Math.min(9999, Math.round(Math.pow(rms, 2) * 12000 * gain * gain * (0.4 + master)));

        const target = newest * (0.55 + 0.45 * Math.min(1, peak * 1.4)) * this.sensitivity;
        if (Math.abs(target) > Math.abs(this.disp)) this.disp = target;
        else this.disp *= 0.78;

        const jump = rms - this.prevEnv;
        if (jump > 0.05 && rms > 0.10) {
            this.shake = Math.min(1, this.shake + jump * 3.5);
            this.clip  = Math.min(1, this.clip  + jump * 4);
            if (this.rings.length < 10) this.rings.push({ t: 0 });
            const now = performance.now();
            if (jump > 0.07 && now - this.lastDrop > 450) { this.drops++; this.lastDrop = now; }
        }
        this.shake *= 0.86;
        this.clip  *= 0.92;
        this.env = this.env * 0.7 + rms * 0.3;
        this.metrics.rms += (Math.min(1, rms) - this.metrics.rms) * 0.22;
        this.metrics.peak = Math.max(this.metrics.peak * 0.992, Math.min(1, peak));
        this.metrics.excursion = Math.min(1, Math.abs(this.disp));
        if (peak >= this.peakHold) { this.peakHold = peak; this.peakHoldAt = performance.now(); }
        else if (performance.now() - this.peakHoldAt > 1400) this.peakHold *= 0.997;
        this.prevEnv = rms;
    }

    // ======================================================================
    //  3D SCENE
    // ======================================================================
    init3d() {
        const THREE = window.THREE;
        const par = this.canvas.parentElement;
        let renderer;
        try {
            this.glCanvas = document.createElement('canvas');
            renderer = new THREE.WebGLRenderer({ canvas: this.glCanvas, antialias: true, alpha: true });
        } catch (e) { console.error(e); this.failed = true; return; }
        this.renderer = renderer;
        renderer.setClearColor(0x000000, 0);
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.3;

        const gs = this.glCanvas.style;
        gs.position = 'absolute'; gs.left = '0'; gs.top = '0'; gs.width = '100%';
        gs.borderRadius = '18px 18px 0 0';
        gs.background = 'radial-gradient(ellipse at 50% 45%, #1b1b26 0%, #0c0c12 65%, #07070a 100%)';
        par.insertBefore(this.glCanvas, this.canvas);

        this.col = new THREE.Color(0.61, 0.36, 0.9);
        this._lastA = '';
        const scene = this.scene = new THREE.Scene();
        this.camera = new THREE.PerspectiveCamera(32, 1, 0.1, 100);
        this.target = new THREE.Vector3(0, -0.2, -1.2);

        // ---- reflections: tiny studio made of soft boxes ----
        try {
            const envScene = new THREE.Scene();
            envScene.background = new THREE.Color(0x07070b);
            const panel = (w, h, p, k, c) => {
                const m = new THREE.Mesh(new THREE.PlaneGeometry(w, h),
                    new THREE.MeshBasicMaterial({ color: new THREE.Color(c).multiplyScalar(k), side: THREE.DoubleSide }));
                m.position.set(p[0], p[1], p[2]); m.lookAt(0, 0, 0); envScene.add(m);
            };
            panel(14, 6, [-8, 9, 8], 6, 0xffffff);
            panel(5, 12, [10, 2, 4], 3, 0xaab4ff);
            panel(12, 3, [0, 10, -6], 4, 0xffffff);
            panel(8, 8, [0, -3, 10], 0.8, 0x9b5de5);
            const pm = new THREE.PMREMGenerator(renderer);
            scene.environment = pm.fromScene(envScene, 0.02).texture;
            pm.dispose();
        } catch (e) { console.warn('env map skipped', e); }

        // ---- lights ----
        scene.add(new THREE.HemisphereLight(0x9a9aa8, 0x050508, 0.3));
        const key = new THREE.DirectionalLight(0xffffff, 1.0);
        key.position.set(-6, 9, 9); key.castShadow = true;
        key.shadow.mapSize.set(2048, 2048);
        Object.assign(key.shadow.camera, { left: -9, right: 9, top: 9, bottom: -9, near: 1, far: 40 });
        key.shadow.bias = -0.0004; key.shadow.normalBias = 0.03;
        scene.add(key);
        this.rim = new THREE.DirectionalLight(0xffffff, 0.55);
        this.rim.position.set(9, 3, -7); scene.add(this.rim);
        this.glow = new THREE.PointLight(0xffffff, 0, 16, 2);
        this.glow.position.set(0, 0.8, 3.6); scene.add(this.glow);

        // ---- dimensions ----
        const W = 6.8, H = 5.8, D = 3.2, BEV = 0.1, RECESS = 0.28;
        this.dim = { W, H, D };
        const layout = {
            main: { x: 0,    y: 0.85,  R: 1.3  },
            s1:   { x: -2.3, y: -1.65, R: 0.66 },
            s2:   { x:  2.3, y: -1.65, R: 0.66 }
        };
        const HOLE = 1.28;   // hole radius / driver radius

        // ---- cabinet body (rounded box with real driver holes) ----
        const shape = new THREE.Shape();
        const rw = W/2, rh = H/2, rad = 0.4;
        shape.moveTo(-rw + rad, -rh);
        shape.lineTo(rw - rad, -rh); shape.absarc(rw - rad, -rh + rad, rad, -Math.PI/2, 0, false);
        shape.lineTo(rw, rh - rad);  shape.absarc(rw - rad,  rh - rad, rad, 0, Math.PI/2, false);
        shape.lineTo(-rw + rad, rh); shape.absarc(-rw + rad, rh - rad, rad, Math.PI/2, Math.PI, false);
        shape.lineTo(-rw, -rh + rad); shape.absarc(-rw + rad, -rh + rad, rad, Math.PI, Math.PI*1.5, false);
        const backShape = shape.clone();     // outline only (no holes) for the back panel
        Object.values(layout).forEach(l => {
            const hole = new THREE.Path();
            hole.absarc(l.x, l.y, l.R * HOLE, 0, Math.PI * 2, true);
            shape.holes.push(hole);
        });
        const bodyGeo = new THREE.ExtrudeGeometry(shape, {
            depth: D, bevelEnabled: true, bevelThickness: BEV, bevelSize: BEV, bevelSegments: 5, curveSegments: 64
        });
        bodyGeo.translate(0, 0, -(D + BEV));            // front face sits at z = 0

        // procedural grain for the vinyl / MDF finish
        const nc = document.createElement('canvas'); nc.width = nc.height = 128;
        const ng = nc.getContext('2d'), nd = ng.createImageData(128, 128);
        for (let i = 0; i < nd.data.length; i += 4) {
            const v = 90 + Math.random() * 130;
            nd.data[i] = nd.data[i+1] = nd.data[i+2] = v; nd.data[i+3] = 255;
        }
        ng.putImageData(nd, 0, 0);
        const grain = new THREE.CanvasTexture(nc);
        grain.wrapS = grain.wrapT = THREE.RepeatWrapping; grain.repeat.set(1.6, 1.6);
        const cabMat = new THREE.MeshStandardMaterial({
            color: 0x14141a, roughness: 0.78, metalness: 0.08, bumpMap: grain, bumpScale: 0.12
        });

        const cab = this.cab = new THREE.Group();
        scene.add(cab);
        const body = new THREE.Mesh(bodyGeo, cabMat);
        body.castShadow = body.receiveShadow = true;
        cab.add(body);

        // ---- materials shared by the drivers ----
        const M = this.mats = {
            rubber:  new THREE.MeshStandardMaterial({ color: 0x0b0b0e, roughness: 0.55, metalness: 0.0, side: THREE.DoubleSide }),
            flange:  new THREE.MeshStandardMaterial({ color: 0x2a2a33, roughness: 0.42, metalness: 0.85 }),
            bolt:    new THREE.MeshStandardMaterial({ color: 0xb0b0bc, roughness: 0.28, metalness: 1.0 }),
            cone:    new THREE.MeshStandardMaterial({ color: 0x1e1e26, roughness: 0.38, metalness: 0.55, side: THREE.DoubleSide, emissive: this.col, emissiveIntensity: 0 }),
            cap:     new THREE.MeshStandardMaterial({ color: 0x3c3c48, roughness: 0.2,  metalness: 0.75 }),
        };

        // ---- build the 3 drivers ----
        this.drivers = [
            this.buildDriver(layout.main, HOLE, RECESS, 0.30, 1.0,  true),
            this.buildDriver(layout.s1,   HOLE, RECESS, 0.15, 0.85, false),
            this.buildDriver(layout.s2,   HOLE, RECESS, 0.15, 0.75, false),
        ];
        this.drivers.forEach(d => cab.add(d.group));
        this.mainPos = new THREE.Vector3(layout.main.x, layout.main.y, 0);

        // ---- front plaque between the small drivers ----
        const plaque = new THREE.Mesh(new THREE.BoxGeometry(1.5, 0.42, 0.07),
            new THREE.MeshStandardMaterial({ color: 0x4a4a56, roughness: 0.35, metalness: 0.95 }));
        plaque.position.set(0, -1.65, 0.035); plaque.castShadow = true; cab.add(plaque);
        const tc = document.createElement('canvas'); tc.width = 512; tc.height = 128;
        const tg = tc.getContext('2d');
        tg.fillStyle = '#0d0d12'; tg.font = '800 62px Segoe UI, Arial'; tg.textBaseline = 'middle'; tg.textAlign = 'center';
        tg.fillText('CRIXOOFER', 256, 68);
        const label = new THREE.Mesh(new THREE.PlaneGeometry(1.3, 0.325),
            new THREE.MeshBasicMaterial({ map: new THREE.CanvasTexture(tc), transparent: true }));
        label.position.set(0, -1.65, 0.075); cab.add(label);
        this.ledMat = new THREE.MeshBasicMaterial({ color: 0x9b5de5 });
        const led = new THREE.Mesh(new THREE.SphereGeometry(0.045, 16, 12), this.ledMat);
        led.position.set(0.62, -1.49, 0.08); cab.add(led);

        // ---- corner screws on the baffle ----
        [[-3.0, 2.5], [3.0, 2.5], [-3.0, -2.55], [3.0, -2.55]].forEach(([x, y]) => {
            const s = new THREE.Mesh(new THREE.CylinderGeometry(0.09, 0.09, 0.05, 20), M.bolt);
            s.rotation.x = Math.PI/2; s.position.set(x, y, 0.02); cab.add(s);
        });

        // ---- feet ----
        const footMat = new THREE.MeshStandardMaterial({ color: 0x050507, roughness: 0.9 });
        const footH = 0.28; const floorY = -H/2 - footH - BEV;
        [[-2.6, -0.8], [2.6, -0.8], [-2.6, -2.6], [2.6, -2.6]].forEach(([x, z]) => {
            const f = new THREE.Mesh(new THREE.CylinderGeometry(0.34, 0.4, footH, 28), footMat);
            f.position.set(x, -H/2 - BEV - footH/2, z); f.castShadow = true; cab.add(f);
        });

        // ---- solid back panel (holes go through the body, so close them off) ----
        const backZ0 = -(D + 2*BEV);
        const backGeo = new THREE.ExtrudeGeometry(backShape, { depth: 0.08, bevelEnabled: false, curveSegments: 64 });
        backGeo.translate(0, 0, backZ0 - 0.08);
        const backPanel = new THREE.Mesh(backGeo, cabMat);
        backPanel.castShadow = backPanel.receiveShadow = true; cab.add(backPanel);

        // ---- rear terminal plate (visible when you orbit round the back) ----
        const backZ = backZ0 - 0.08;
        const plate = new THREE.Mesh(new THREE.BoxGeometry(2.4, 1.5, 0.08),
            new THREE.MeshStandardMaterial({ color: 0x2a2a33, roughness: 0.4, metalness: 0.9 }));
        plate.position.set(0, 0.4, backZ - 0.04); plate.castShadow = true; cab.add(plate);
        [[-0.55, 0xd22b2b], [0.55, 0x15151a]].forEach(([x, c]) => {
            const post = new THREE.Mesh(new THREE.CylinderGeometry(0.16, 0.16, 0.3, 24),
                new THREE.MeshStandardMaterial({ color: c, roughness: 0.35, metalness: 0.4 }));
            post.rotation.x = Math.PI/2; post.position.set(x, 0.35, backZ - 0.2); cab.add(post);
            const cap = new THREE.Mesh(new THREE.CylinderGeometry(0.07, 0.07, 0.06, 16), M.bolt);
            cap.rotation.x = Math.PI/2; cap.position.set(x, 0.35, backZ - 0.37); cab.add(cap);
        });
        const bc = document.createElement('canvas'); bc.width = 512; bc.height = 128;
        const bg = bc.getContext('2d');
        bg.fillStyle = '#cfcfda'; bg.font = '700 44px Segoe UI, Arial'; bg.textAlign = 'center'; bg.textBaseline = 'middle';
        bg.fillText('CRIXOOFER  3×DRIVER', 256, 64);
        const bl = new THREE.Mesh(new THREE.PlaneGeometry(2.1, 0.52),
            new THREE.MeshBasicMaterial({ map: new THREE.CanvasTexture(bc), transparent: true }));
        bl.rotation.y = Math.PI; bl.position.set(0, 0.95, backZ - 0.085); cab.add(bl);

        // ---- floor (shadow catcher) ----
        const floor = new THREE.Mesh(new THREE.PlaneGeometry(60, 60), new THREE.ShadowMaterial({ opacity: 0.55 }));
        floor.rotation.x = -Math.PI/2; floor.position.y = floorY; floor.receiveShadow = true;
        scene.add(floor);

        // ---- shockwave rings (pool) ----
        this.ringPool = [];
        for (let i = 0; i < 10; i++) {
            const m = new THREE.Mesh(new THREE.RingGeometry(1, 1.04, 96),
                new THREE.MeshBasicMaterial({ color: this.col, transparent: true, opacity: 0, depthWrite: false, blending: THREE.AdditiveBlending, side: THREE.DoubleSide }));
            m.visible = false; m.position.copy(this.mainPos); scene.add(m); this.ringPool.push(m);
        }

        this.ready = true;
        this.createControls();
        this.resize3d();
    }

    buildDriver(l, HOLE, RECESS, depthK, k, main) {
        const THREE = window.THREE, M = this.mats, R = l.R;
        const group = new THREE.Group();
        group.position.set(l.x, l.y, -RECESS);
        const inner = new THREE.Group(); inner.rotation.x = Math.PI/2; group.add(inner);   // local +Y -> toward viewer
        const Rh = R * HOLE, Ro = R * 0.96, Ri = R * 0.72;
        const depth = R * depthK * 1.1;
        const r0 = R * 0.27;

        // mounting flange ring (static) + bolts
        const ring = new THREE.Mesh(new THREE.RingGeometry(R * 0.9, Rh * 1.01, 96), M.flange);
        ring.castShadow = ring.receiveShadow = true; group.add(ring);
        const nb = main ? 8 : 6, bR = (R * 0.9 + Rh) / 2, br = main ? 0.06 : 0.04;
        for (let i = 0; i < nb; i++) {
            const a = i / nb * Math.PI * 2 + 0.4;
            const b = new THREE.Mesh(new THREE.CylinderGeometry(br, br, 0.05, 6), M.bolt);
            b.rotation.x = Math.PI/2; b.position.set(Math.cos(a)*bR, Math.sin(a)*bR, 0.03);
            b.castShadow = true; group.add(b);
        }
        // glowing LED ring on the flange rim
        const ledMat = new THREE.MeshBasicMaterial({ color: this.col.clone() });
        const led = new THREE.Mesh(new THREE.TorusGeometry(Rh * 0.965, main ? 0.028 : 0.02, 8, 128), ledMat);
        led.position.z = 0.012; group.add(led);

        // half-roll rubber surround: lathe of a semicircle, vertices animated per frame
        const rm = (Ro + Ri) / 2, rr = (Ro - Ri) / 2, pts = [];
        for (let i = 0; i <= 20; i++) {
            const t = i / 20 * Math.PI;
            pts.push(new THREE.Vector2(rm + Math.cos(t) * rr, Math.sin(t) * rr * 0.95));
        }
        const sGeo = new THREE.LatheGeometry(pts, main ? 96 : 64);
        const surround = new THREE.Mesh(sGeo, M.rubber);
        surround.castShadow = surround.receiveShadow = true; surround.frustumCulled = false;
        inner.add(surround);
        const sPos = sGeo.attributes.position, sBase = Float32Array.from(sPos.array);

        // moving assembly: cone + dust cap
        const moving = new THREE.Group(); inner.add(moving);
        const cp = [];
        const N = 70;
        for (let i = 0; i <= N; i++) {
            const s = i / N;
            const r = Ri + (r0 - Ri) * s;
            const ridge = Math.sin(s * (main ? 46 : 30)) * R * 0.006 * Math.sin(s * Math.PI);
            cp.push(new THREE.Vector2(r, -depth * Math.pow(s, 1.7) + ridge));
        }
        const cone = new THREE.Mesh(new THREE.LatheGeometry(cp, main ? 96 : 64), M.cone.clone());
        cone.castShadow = cone.receiveShadow = true; cone.frustumCulled = false;
        moving.add(cone);

        const dust = new THREE.Mesh(new THREE.SphereGeometry(r0 * 1.04, 40, 20, 0, Math.PI*2, 0, Math.PI/2), M.cap);
        dust.scale.y = 0.6; dust.position.y = -depth; dust.castShadow = true;
        moving.add(dust);

        return { group, moving, cone, ledMat, sPos, sBase, Ro, Ri, amp: R * 0.2, k };
    }

    resize3d() {
        const specH = this.h * 0.16, glH = Math.max(1, this.h - specH);
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        this.renderer.setPixelRatio(dpr);
        this.renderer.setSize(this.w, glH, false);
        this.glCanvas.style.height = glH + 'px';
        this.camera.aspect = this.w / glH;
        this.camera.updateProjectionMatrix();
        const tan = Math.tan(this.camera.fov * Math.PI / 360);
        const fitV = (this.dim.H + 2.4) / 2 / tan;
        const fitH = (this.dim.W + 1.8) / 2 / (tan * this.camera.aspect);
        this.baseDist = Math.max(fitV, fitH);
    }

    update3d() {
        const THREE = window.THREE;
        const d = this.disp, A = this.ambient;
        if (A !== this._lastA) {
            const [r, g, b] = A.split(',').map(Number);
            this.col.setRGB(r/255, g/255, b/255);
            this.rim.color.copy(this.col); this.glow.color.copy(this.col);
            this._lastA = A;
        }

        // camera orbit (drag, wheel, dbl-click resets; gentle sway when idle)
        const now = performance.now();
        if (this.autoRotate && !this.dragging && now - this.lastInput > 4000) {
            this.o.theta += (-0.5 + Math.sin(now/2600) * 0.45 - this.o.theta) * 0.02;
            this.o.phi   += (0.16 + Math.sin(now/3300) * 0.05 - this.o.phi) * 0.02;
        }
        const dist = this.baseDist * this.o.zoom, { theta, phi } = this.o;
        this.camera.position.set(
            this.target.x + dist * Math.sin(theta) * Math.cos(phi),
            this.target.y + dist * Math.sin(phi),
            this.target.z + dist * Math.cos(theta) * Math.cos(phi));
        this.camera.lookAt(this.target);

        // cabinet shake on bass hits
        const modeGain = this.visualMode === 'cinematic' ? 1.35 : this.visualMode === 'minimal' ? 0.45 : 1;
        const s = this.shake * modeGain;
        this.cab.position.set((Math.random()*2-1)*s*0.07, (Math.random()*2-1)*s*0.05, 0);
        this.cab.rotation.z = (Math.random()*2-1)*s*0.006;

        // drivers
        const e = this.env;
        for (const dr of this.drivers) {
            const dd = d * dr.k, dy = dd * dr.amp;
            dr.moving.position.y = dy;
            const p = dr.sPos, b = dr.sBase, span = dr.Ro - dr.Ri;
            for (let i = 0; i < p.count; i++) {
                const x = b[i*3], z = b[i*3+2];
                let w = (dr.Ro - Math.hypot(x, z)) / span; w = w < 0 ? 0 : w > 1 ? 1 : w;
                p.setY(i, b[i*3+1] + dy * (w*w*(3 - 2*w)));
            }
            p.needsUpdate = true;
            dr.cone.material.emissive.copy(this.col);
            dr.cone.material.emissiveIntensity = (this.visualMode === 'minimal' ? 0.005 : 0.015) + Math.max(0, dd) * (this.visualMode === 'cinematic' ? 0.24 : 0.16) + e * 0.1;
            const lv = 0.25 + e * 1.8;
            dr.ledMat.color.setRGB(this.col.r * lv, this.col.g * lv, this.col.b * lv);
        }
        const lv = 0.3 + e * 2;
        this.ledMat.color.setRGB(this.col.r * lv, this.col.g * lv, this.col.b * lv);
        this.glow.intensity = this.visualMode === 'minimal' ? 0.08 : 0.1 + e * (this.visualMode === 'cinematic' ? 4.2 : 3);

        // shockwave rings
        this.rings = this.rings.filter(r => r.t < (this.visualMode === 'pulse' ? 18 : 12));
        this.rings.forEach(r => r.t++);
        this.ringPool.forEach((m, i) => {
            const r = this.rings[i];
            if (!r) { m.visible = false; return; }
            m.visible = true;
            m.scale.setScalar(1.6 + r.t * (this.visualMode === 'pulse' ? 0.22 : 0.16));
            m.position.z = 0.15 + r.t * 0.22;
            m.material.color.copy(this.col);
            m.material.opacity = this.visualMode === 'minimal' ? 0 : Math.max(0, (this.visualMode === 'pulse' ? 0.58 : 0.45) - r.t * (this.visualMode === 'pulse' ? 0.029 : 0.038));
        });

        this.renderer.render(this.scene, this.camera);
    }

    // ======================================================================
    //  2D OVERLAY: spectrum strip
    // ======================================================================
    draw(freq) {
        const { ctx, w, h } = this;
        if (!w || !h) return;
        const A = this.ambient;
        ctx.clearRect(0, 0, w, h);

        if (this.ready) this.update3d();
        else {
            ctx.fillStyle = `rgba(${A},.7)`; ctx.font = '600 13px Segoe UI'; ctx.textAlign = 'center';
            ctx.fillText(this.failed ? 'Could not load 3D (assets/js/three.min.js / WebGL)' : 'Loading 3D…', w/2, h*0.4);
            ctx.textAlign = 'left';
        }

        const specH = h * 0.16, y0 = h - specH;
        this.roundRect(10, y0, w-20, specH-8, 8);
        ctx.fillStyle = '#0d0d11'; ctx.fill();
        ctx.strokeStyle = '#1e1e26'; ctx.lineWidth = 1; ctx.stroke();
        const N = 96, bw = (w-44)/N;
        for (let i = 0; i < N; i++) {
            const bin = Math.min(freq.length-1, Math.floor(Math.pow((i+1)/N, 1.7) * freq.length * 0.75));
            const v = freq[bin]/255;
            this.peaks[i] = Math.max(this.peaks[i]-0.012, v);
            const bh = v*(specH-24);
            const x = 22 + i*bw;
            const y = y0 + specH - 12 - bh;
            ctx.fillStyle = v > 0.72 ? `rgba(241,91,181,${0.5+v*0.5})` : `rgba(${A},${0.4+v*0.6})`;
            ctx.fillRect(x, y, bw-1.5, bh);
            ctx.fillStyle = 'rgba(241,91,181,0.9)';
            ctx.fillRect(x, y0 + specH - 12 - this.peaks[i]*(specH-24) - 2, bw-1.5, 2);
        }
        this.metrics.bass = this.bandLevel(freq, 0.00, 0.12);
        this.metrics.mid = this.bandLevel(freq, 0.12, 0.48);
        this.metrics.treble = this.bandLevel(freq, 0.48, 0.95);
        if (this.showMetrics) this.drawMetrics(ctx, w, h);
    }

    bandLevel(freq, from, to) {
        if (!freq || !freq.length) return 0;
        const a = Math.max(0, Math.floor(freq.length * from));
        const b = Math.max(a + 1, Math.floor(freq.length * to));
        let sum = 0;
        for (let i = a; i < Math.min(freq.length, b); i++) sum += freq[i] / 255;
        return sum / Math.max(1, Math.min(freq.length, b) - a);
    }

    drawMetrics(ctx, w, h) {
        const x = 16, y = 16, boxW = Math.min(222, w - 32), boxH = 88;
        ctx.save();
        ctx.fillStyle = 'rgba(8,8,13,.72)'; ctx.strokeStyle = 'rgba(255,255,255,.12)'; ctx.lineWidth = 1;
        this.roundRect(x, y, boxW, boxH, 10); ctx.fill(); ctx.stroke();
        ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
        ctx.fillStyle = '#f2f0f8'; ctx.font = '700 10px Segoe UI, sans-serif';
        ctx.fillText('CRIXOOFER  /  LIVE TELEMETRY', x + 10, y + 14);
        const rows = [
            ['RMS', this.metrics.rms, '#9b5de5'],
            ['PEAK', this.metrics.peak, '#f15bb5'],
            ['BASS', this.metrics.bass, '#8bdbff']
        ];
        rows.forEach((r, i) => {
            const yy = y + 34 + i * 17;
            ctx.fillStyle = 'rgba(255,255,255,.12)'; this.roundRect(x + 48, yy - 4, boxW - 86, 7, 4); ctx.fill();
            ctx.fillStyle = r[2]; this.roundRect(x + 48, yy - 4, (boxW - 86) * Math.max(0, Math.min(1, r[1])), 7, 4); ctx.fill();
            ctx.fillStyle = '#d8d5df'; ctx.font = '600 9px Segoe UI, sans-serif'; ctx.fillText(r[0], x + 10, yy);
            ctx.textAlign = 'right'; ctx.fillText(Math.round(r[1] * 100) + '%', x + boxW - 9, yy); ctx.textAlign = 'left';
        });
        ctx.fillStyle = 'rgba(255,255,255,.55)'; ctx.font = '9px Segoe UI, sans-serif';
        ctx.fillText('EXCURSION', x + boxW + 10, y + 14);
        ctx.fillStyle = '#fff'; ctx.font = '700 16px Segoe UI, sans-serif';
        ctx.fillText(Math.round(this.metrics.excursion * 100) + '%', x + boxW + 10, y + 34);
        ctx.restore();
    }

    roundRect(x, y, w, h, r) {
        this.ctx.beginPath();
        this.ctx.moveTo(x + r, y);
        this.ctx.arcTo(x + w, y, x + w, y + h, r);
        this.ctx.arcTo(x + w, y + h, x, y + h, r);
        this.ctx.arcTo(x, y + h, x, y, r);
        this.ctx.arcTo(x, y, x + w, y, r);
        this.ctx.closePath();
    }
}