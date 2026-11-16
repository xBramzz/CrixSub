class CrixooferEngine {
    constructor(audioElement) {
        this.audio = audioElement;
        const AC = window.AudioContext || window.webkitAudioContext;
        this.ctx = new AC();
        this.source = this.ctx.createMediaElementSource(audioElement);

        this.master = this.ctx.createGain();
        this.master.gain.value = 1;
        this.master.connect(this.ctx.destination);

        // ---- MAIN PATH (mids + highs) ----
        this.highPass = this.ctx.createBiquadFilter();
        this.highPass.type = 'highpass';
        this.mainGain = this.ctx.createGain();
        this.mainGain.gain.value = 1;
        this.source.connect(this.highPass);
        this.highPass.connect(this.mainGain);
        this.mainGain.connect(this.master);

        // ---- SUB PATH (lows, 24dB/oct + boost + level) ----
        this.lowPass = this.ctx.createBiquadFilter();
        this.lowPass.type = 'lowpass'; this.lowPass.Q.value = 0.7;
        this.lowPass2 = this.ctx.createBiquadFilter();
        this.lowPass2.type = 'lowpass'; this.lowPass2.Q.value = 0.7;
        this.bassBoost = this.ctx.createBiquadFilter();
        this.bassBoost.type = 'lowshelf';
        this.subGain = this.ctx.createGain();
        this.subGain.gain.value = 1.2;   // ← explicit non-zero default
        this.source.connect(this.lowPass);
        this.lowPass.connect(this.lowPass2);
        this.lowPass2.connect(this.bassBoost);
        this.bassBoost.connect(this.subGain);
        this.subGain.connect(this.master);

        // ---- ANALYSERS ----
        this.fullAnalyser = this.ctx.createAnalyser();
        this.fullAnalyser.fftSize = 2048;
        this.fullAnalyser.smoothingTimeConstant = 0.82;
        this.source.connect(this.fullAnalyser);

        this.subAnalyser = this.ctx.createAnalyser();
        this.subAnalyser.fftSize = 2048;
        this.subGain.connect(this.subAnalyser);

        this.freqData = new Uint8Array(this.fullAnalyser.frequencyBinCount);
        this.subWave  = new Uint8Array(this.subAnalyser.fftSize);

        this.loadSettings();
    }

    resume() { if (this.ctx.state === 'suspended') this.ctx.resume(); }

    setBass(boostDb, crossoverHz, subVolume) {
        const t = this.ctx.currentTime;
        this.bassBoost.gain.setTargetAtTime(boostDb, t, 0.02);
        [this.lowPass, this.lowPass2, this.highPass].forEach(f =>
            f.frequency.setTargetAtTime(crossoverHz, t, 0.02));
        // floor at 0.05 so wattage never collapses to zero
        this.subGain.gain.setTargetAtTime(Math.max(0.05, subVolume), t, 0.02);
        localStorage.setItem('crixoofer_dsp', JSON.stringify({ boostDb, crossoverHz, subVolume }));
    }

    getFrequencyData() { this.fullAnalyser.getByteFrequencyData(this.freqData); return this.freqData; }
    getSubWaveform()   { this.subAnalyser.getByteTimeDomainData(this.subWave);  return this.subWave;  }

    loadSettings() {
        const s = JSON.parse(localStorage.getItem('crixoofer_dsp') || 'null');
        this.setBass(s?.boostDb ?? 12, s?.crossoverHz ?? 90, s?.subVolume ?? 1.2);
    }
}