// Elements
const audioInput = document.getElementById('audioInput');
const coverInput = document.getElementById('coverInput');
const lyricsInput = document.getElementById('lyricsInput');
const songTitleInput = document.getElementById('songTitle');
const artistNameInput = document.getElementById('artistName');
const visModeSelect = document.getElementById('visMode');
const canvas = document.getElementById('visualizer');
const ctx = canvas.getContext('2d');
const playPauseBtn = document.getElementById('playPauseBtn');
const progressBar = document.getElementById('progressBar');
const currentTimeDisplay = document.getElementById('currentTimeDisplay');
const totalTimeDisplay = document.getElementById('totalTimeDisplay');
const recordBtn = document.getElementById('recordBtn');

// State
let audioCtx;
let analyser;
let gainNode;
let sourceNode;
let audioBuffer;
let destNode; // For recording

let isPlaying = false;
let startTime = 0;
let pauseTime = 0;
let animationId;
let coverImage = null;
let lyrics = [];
let duration = 0;

let mediaRecorder;
let recordedChunks = [];
let isRecording = false;

// --- Helper Functions ---

function formatTime(seconds) {
    if (!seconds || isNaN(seconds)) return "00:00.00";
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    const ms = Math.floor((seconds % 1) * 100);
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}.${ms.toString().padStart(2, '0')}`;
}

function parseLyrics(text) {
    const lines = text.split('\n');
    const parsed = [];
    const timeRegex = /\[(\d{2}):(\d{2}\.\d{2})\]/;
    
    lines.forEach(line => {
        const match = line.match(timeRegex);
        if (match) {
            const minutes = parseFloat(match[1]);
            const seconds = parseFloat(match[2]);
            const time = minutes * 60 + seconds;
            const content = line.replace(timeRegex, '').trim();
            parsed.push({ time, text: content });
        }
    });
    return parsed.sort((a, b) => a.time - b.time);
}

// --- Audio Logic ---

async function initAudio(file) {
    if (isPlaying) stopAudio();
    
    // Reset Context if needed or reuse
    if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    } else if (audioCtx.state === 'suspended') {
        await audioCtx.resume();
    }

    const arrayBuffer = await file.arrayBuffer();
    audioBuffer = await audioCtx.decodeAudioData(arrayBuffer);
    duration = audioBuffer.duration;
    
    // UI Updates
    progressBar.max = duration;
    progressBar.disabled = false;
    playPauseBtn.disabled = false;
    recordBtn.disabled = false;
    totalTimeDisplay.textContent = formatTime(duration);
    
    // Guess title
    songTitleInput.value = file.name.replace(/\.[^/.]+$/, "");
    
    // Setup Graph
    analyser = audioCtx.createAnalyser();
    analyser.fftSize = 2048;
    
    gainNode = audioCtx.createGain();
    gainNode.connect(audioCtx.destination);
    
    destNode = audioCtx.createMediaStreamDestination();
    
    // Initial Draw
    draw();
}

function playAudio() {
    if (!audioBuffer) return;
    
    sourceNode = audioCtx.createBufferSource();
    sourceNode.buffer = audioBuffer;
    
    // Connect: Source -> Analyser -> Gain -> Speakers
    sourceNode.connect(analyser);
    analyser.connect(gainNode);
    
    // Connect for recording
    analyser.connect(destNode);
    
    const offset = pauseTime;
    sourceNode.start(0, offset);
    startTime = audioCtx.currentTime - offset;
    
    isPlaying = true;
    updatePlayBtn();
    loop();
    
    sourceNode.onended = () => {
        if(isPlaying) { // Natural end
            isPlaying = false;
            pauseTime = 0;
            startTime = 0;
            updatePlayBtn();
            if(isRecording) stopRecording();
        }
    };
}

function pauseAudio() {
    if (sourceNode) {
        sourceNode.stop();
        sourceNode = null;
    }
    pauseTime = audioCtx.currentTime - startTime;
    isPlaying = false;
    updatePlayBtn();
    cancelAnimationFrame(animationId);
}

function stopAudio() {
    if (sourceNode) {
        sourceNode.stop();
        sourceNode = null;
    }
    pauseTime = 0;
    startTime = 0;
    isPlaying = false;
    updatePlayBtn();
    cancelAnimationFrame(animationId);
    progressBar.value = 0;
    currentTimeDisplay.textContent = formatTime(0);
    draw(); // Draw empty state
}

function updatePlayBtn() {
    const icon = playPauseBtn.querySelector('i');
    if (isPlaying) {
        // Pause icon logic handled by lucide class replacement implies re-render, 
        // simpler for vanilla: replace innerHTML
        playPauseBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-pause fill-current w-4 h-4"><rect width="4" height="16" x="6" y="4"/><rect width="4" height="16" x="14" y="4"/></svg>`;
    } else {
        playPauseBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-play fill-current w-4 h-4 ml-1"><polygon points="6 3 20 12 6 21 6 3"/></svg>`;
    }
}

// --- Visualizer Logic ---

function draw() {
    // 1. Clear & Background
    ctx.fillStyle = '#0f0f13';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    const width = canvas.width;
    const height = canvas.height;
    const splitX = width / 2;

    // 2. Cover Art
    if (coverImage) {
        const imgSize = Math.min(splitX, height) * 0.8;
        const x = (splitX - imgSize) / 2;
        const y = (height - imgSize) / 2;
        
        ctx.save();
        ctx.shadowColor = 'rgba(0,0,0,0.5)';
        ctx.shadowBlur = 20;
        ctx.drawImage(coverImage, x, y, imgSize, imgSize);
        ctx.strokeStyle = '#333';
        ctx.lineWidth = 2;
        ctx.strokeRect(x, y, imgSize, imgSize);
        ctx.restore();
    } else {
        ctx.fillStyle = '#333';
        ctx.font = '24px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText("No Cover Art", splitX / 2, height / 2);
    }

    // 3. Metadata
    const title = songTitleInput.value;
    const artist = artistNameInput.value;
    if (title || artist) {
        ctx.save();
        const gradient = ctx.createLinearGradient(0, 0, 0, 100);
        gradient.addColorStop(0, 'rgba(0,0,0,0.8)');
        gradient.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, splitX * 0.9, 100);
        
        ctx.textAlign = 'left';
        ctx.textBaseline = 'top';
        ctx.shadowColor = 'rgba(0,0,0,0.9)';
        ctx.shadowBlur = 4;
        
        ctx.fillStyle = '#fff';
        ctx.font = 'bold 36px sans-serif';
        ctx.fillText(title || "Unknown Title", 40, 30);
        
        ctx.fillStyle = '#ddd';
        ctx.font = '24px sans-serif';
        ctx.fillText(artist || "Unknown Artist", 40, 75);
        ctx.restore();
    }

    // 4. Visualization
    ctx.fillStyle = '#18181b';
    ctx.fillRect(splitX, 0, splitX, height); // Right bg

    if (analyser) {
        const bufferLength = analyser.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);
        const mode = visModeSelect.value;

        if (mode === 'bars') {
            analyser.getByteFrequencyData(dataArray);
            const barWidth = (splitX / bufferLength) * 2.5;
            let barHeight;
            let x = splitX;

            for (let i = 0; i < bufferLength; i++) {
                barHeight = (dataArray[i] / 255) * (height * 0.8);
                const g = ctx.createLinearGradient(0, height, 0, height - barHeight);
                g.addColorStop(0, '#4f46e5');
                g.addColorStop(1, '#a855f7');
                ctx.fillStyle = g;
                ctx.fillRect(x, (height - barHeight) / 2, barWidth, barHeight);
                x += barWidth + 1;
                if (x > width) break;
            }
        } else if (mode === 'wave') {
            analyser.getByteTimeDomainData(dataArray);
            ctx.lineWidth = 2;
            ctx.strokeStyle = '#22d3ee';
            ctx.beginPath();
            const sliceWidth = splitX / bufferLength;
            let x = splitX;
            for(let i = 0; i < bufferLength; i++) {
                const v = dataArray[i] / 128.0;
                const y = v * height / 2;
                if(i===0) ctx.moveTo(x, y);
                else ctx.lineTo(x, y);
                x += sliceWidth;
            }
            ctx.lineTo(width, height/2);
            ctx.stroke();
        } else if (mode === 'circular') {
            analyser.getByteFrequencyData(dataArray);
            const centerX = splitX + splitX/2;
            const centerY = height/2;
            const radius = Math.min(splitX, height) * 0.3;
            ctx.save();
            ctx.translate(centerX, centerY);
            for(let i=0; i<bufferLength; i+=4) {
                const val = dataArray[i];
                const h = (val/255)*100;
                ctx.rotate((Math.PI * 2) / (bufferLength/4));
                ctx.fillStyle = `hsl(${(i/bufferLength)*360}, 100%, 50%)`;
                ctx.fillRect(0, radius, 4, h);
            }
            ctx.restore();
        }
    }

    // 5. Lyrics
    if (isPlaying) {
        const curr = audioCtx.currentTime - startTime;
        const currentLyric = lyrics.find((line, idx) => {
            const nextTime = lyrics[idx+1] ? lyrics[idx+1].time : Infinity;
            return curr >= line.time && curr < nextTime;
        });
        
        if (currentLyric) {
            ctx.save();
            ctx.fillStyle = '#fff';
            ctx.strokeStyle = 'rgba(0,0,0,0.8)';
            ctx.lineWidth = 4;
            ctx.font = 'bold 32px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            ctx.shadowColor = 'rgba(0,0,0,0.8)';
            ctx.shadowBlur = 8;
            ctx.strokeText(currentLyric.text, width/2, height - 40);
            ctx.fillText(currentLyric.text, width/2, height - 40);
            ctx.restore();
        }
    }
}

function loop() {
    if (!isPlaying) return;
    draw();
    
    // Update progress
    const curr = audioCtx.currentTime - startTime;
    progressBar.value = curr;
    currentTimeDisplay.textContent = formatTime(curr);
    
    animationId = requestAnimationFrame(loop);
}

// --- Event Listeners ---

audioInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) initAudio(file);
});

playPauseBtn.addEventListener('click', () => {
    if (isPlaying) pauseAudio();
    else playAudio();
});

coverInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) {
        const img = new Image();
        img.onload = () => {
            coverImage = img;
            draw();
        };
        img.src = URL.createObjectURL(file);
    }
});

visModeSelect.addEventListener('change', draw);

// Simple Lyric Parsing on Change
lyricsInput.addEventListener('input', (e) => {
    lyrics = parseLyrics(e.target.value);
});

progressBar.addEventListener('input', (e) => {
    if (!audioBuffer) return;
    const time = parseFloat(e.target.value);
    pauseTime = time;
    startTime = audioCtx.currentTime - pauseTime;
    currentTimeDisplay.textContent = formatTime(time);
    
    if (isPlaying) {
        // Restart source for seek
        sourceNode.stop();
        sourceNode = audioCtx.createBufferSource();
        sourceNode.buffer = audioBuffer;
        sourceNode.connect(analyser);
        analyser.connect(gainNode);
        analyser.connect(destNode); // Reconnect for recording
        sourceNode.start(0, pauseTime);
    } else {
        draw(); // Update visuals for seek position
    }
});

// --- Recording ---

recordBtn.addEventListener('click', () => {
    if (isRecording) {
        // Stop
        mediaRecorder.stop();
        playPauseBtn.click(); // Pause playback
        recordBtn.innerHTML = `<div class="w-2 h-2 rounded-full bg-white animate-pulse"></div> Start Recording`;
        isRecording = false;
    } else {
        // Start
        // Restart audio from beginning
        stopAudio();
        
        recordedChunks = [];
        const canvasStream = canvas.captureStream(30);
        const audioStream = destNode.stream;
        const combined = new MediaStream([...canvasStream.getVideoTracks(), ...audioStream.getAudioTracks()]);
        
        const opts = { mimeType: 'video/webm;codecs=vp9', videoBitsPerSecond: 5000000 };
        if (!MediaRecorder.isTypeSupported(opts.mimeType)) {
            opts.mimeType = 'video/webm'; // Fallback
        }

        mediaRecorder = new MediaRecorder(combined, opts);
        
        mediaRecorder.ondataavailable = (e) => {
            if (e.data.size > 0) recordedChunks.push(e.data);
        };
        
        mediaRecorder.onstop = () => {
            const blob = new Blob(recordedChunks, { type: opts.mimeType });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `groovegen_vanilla.webm`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        };
        
        mediaRecorder.start();
        isRecording = true;
        recordBtn.innerHTML = `<div class="w-2 h-2 rounded-full bg-red-500"></div> Stop & Download`;
        
        setTimeout(() => {
            playAudio();
        }, 100);
    }
});

// Initial draw
draw();
