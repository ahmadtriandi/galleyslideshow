<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pemutaran Otomatis</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html, body { height: 100%; background: #000; overflow: hidden; font-family: system-ui, sans-serif; }

  /* === Bagian ini IDENTIK dengan play-debug.php yang terbukti menampilkan video === */
  #player {
    position: fixed; inset: 0;
    width: 100%; height: 100%;
    object-fit: contain;
    background: #000;
  }
  /* ================================================================= */

  #startBtn {
    position: fixed; inset: 0; margin: auto;
    width: 240px; height: 70px; display: none; z-index: 60;
    font-size: 18px; background: #2563eb; color: #fff;
    border: none; border-radius: 10px; cursor: pointer;
  }

  .controls {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 50;
    padding: 18px 24px;
    background: linear-gradient(to top, rgba(0,0,0,.7), transparent);
    display: flex; gap: 10px; align-items: center; justify-content: center;
    opacity: 0; transition: opacity .3s; pointer-events: none;
  }
  .topbar {
    position: fixed; top: 0; left: 0; right: 0; z-index: 50;
    padding: 16px 24px;
    background: linear-gradient(to bottom, rgba(0,0,0,.7), transparent);
    color: #fff; display: flex; justify-content: space-between; align-items: center;
    opacity: 0; transition: opacity .3s; pointer-events: none;
  }
  .show { opacity: 1 !important; pointer-events: auto !important; }

  .title { font-size: 16px; font-weight: 600; word-break: break-all; }
  .counter { font-size: 13px; color: #cfd3dd; }
  button.ctrl {
    cursor: pointer; border: none; border-radius: 8px;
    padding: 9px 16px; font-weight: 600; font-size: 14px;
    background: rgba(255,255,255,.18); color: #fff;
  }
  button.ctrl:hover { background: rgba(255,255,255,.3); }
  button.ctrl.active { background: #2563eb; }
  a.back { color: #fff; text-decoration: none; font-size: 13px;
    background: rgba(255,255,255,.18); padding: 8px 14px; border-radius: 8px; }

  #message {
    position: fixed; inset: 0; display: none; z-index: 40;
    align-items: center; justify-content: center;
    color: #8b90a0; font-size: 18px; text-align: center;
  }
</style>
</head>
<body>
<video id="player" playsinline muted></video>
<button id="startBtn">&#9654; Mulai Putar</button>

<div id="message">Belum ada video di folder.<br>Tambahkan file .mp4 lalu halaman ini akan otomatis memutarnya.</div>

<div class="topbar" id="topbar">
  <span class="title" id="title">&mdash;</span>
  <span class="counter" id="counter"></span>
</div>

<div class="controls" id="controls">
  <a class="back" href="index.php">&larr; Galeri</a>
  <button class="ctrl" id="btnPrev">&#9198; Prev</button>
  <button class="ctrl" id="btnPlay">&#9208; Jeda</button>
  <button class="ctrl" id="btnNext">Next &#9197;</button>
  <button class="ctrl active" id="btnMute">&#128263; Bisu</button>
  <button class="ctrl active" id="btnLoop">&#128257; Loop</button>
  <button class="ctrl" id="btnFit">&#9638; Isi Layar</button>
  <button class="ctrl" id="btnFull">&#9638; Fullscreen</button>
</div>

<script>
const player  = document.getElementById('player');
const startBtn= document.getElementById('startBtn');
const message = document.getElementById('message');
const topbar  = document.getElementById('topbar');
const controls= document.getElementById('controls');
const titleEl = document.getElementById('title');
const counter = document.getElementById('counter');

let playlist = [];
let current  = 0;
let loop     = true;
let muted    = true;

function srcOf(name){ return 'api.php?action=stream&name=' + encodeURIComponent(name); }

async function fetchList() {
  try {
    const res = await fetch('api.php?action=videos');
    if (!res.ok) return [];
    const videos = await res.json();
    return Array.isArray(videos) ? videos.map(v => v.name) : [];
  } catch { return []; }
}

function updateInfo() {
  if (!playlist.length) return;
  titleEl.textContent = playlist[current];
  counter.textContent = (current + 1) + ' / ' + playlist.length;
}

function playIndex(i) {
  if (!playlist.length) return;
  current = (i + playlist.length) % playlist.length;
  player.src = srcOf(playlist[current]);
  player.muted = muted;
  player.load();
  const p = player.play();
  if (p && p.catch) p.catch(() => { startBtn.style.display = 'block'; });
  updateInfo();
}

function next() {
  if (current + 1 >= playlist.length && !loop) return;
  playIndex(current + 1);
}
function prev() { playIndex(current - 1); }

player.addEventListener('ended', next);
player.addEventListener('error', () => {
  message.innerHTML = 'Gagal memuat: ' + (playlist[current]||'') + '<br><small>Lanjut berikutnya...</small>';
  message.style.display = 'flex';
  setTimeout(() => { message.style.display = 'none'; next(); }, 1500);
});

async function init() {
  playlist = await fetchList();
  if (!playlist.length) { message.style.display = 'flex'; return; }
  message.style.display = 'none';
  playIndex(0);
}

setInterval(async () => {
  const fresh = await fetchList();
  const before = playlist.length;
  fresh.forEach(n => { if (!playlist.includes(n)) playlist.push(n); });
  const playing = playlist[current];
  playlist = playlist.filter(n => fresh.includes(n) || n === playing);
  if (!playlist.length) { message.style.display = 'flex'; }
  else if (before === 0) { message.style.display = 'none'; playIndex(0); }
  updateInfo();
}, 10000);

document.getElementById('btnNext').onclick = next;
document.getElementById('btnPrev').onclick = prev;

const btnPlay = document.getElementById('btnPlay');
btnPlay.onclick = () => {
  if (player.paused) { player.play(); btnPlay.innerHTML = '&#9208; Jeda'; }
  else { player.pause(); btnPlay.innerHTML = '&#9654; Putar'; }
};

const btnMute = document.getElementById('btnMute');
btnMute.onclick = () => {
  muted = !muted; player.muted = muted;
  btnMute.innerHTML = muted ? '&#128263; Bisu' : '&#128266; Suara';
  btnMute.classList.toggle('active', muted);
};

const btnLoop = document.getElementById('btnLoop');
btnLoop.onclick = () => { loop = !loop; btnLoop.classList.toggle('active', loop); };

const btnFit = document.getElementById('btnFit');
btnFit.onclick = () => {
  const cur = getComputedStyle(player).objectFit;
  player.style.objectFit = (cur === 'cover') ? 'contain' : 'cover';
  btnFit.classList.toggle('active');
};

document.getElementById('btnFull').onclick = () => {
  if (!document.fullscreenElement) document.documentElement.requestFullscreen();
  else document.exitFullscreen();
};

startBtn.onclick = () => { startBtn.style.display = 'none'; player.play().catch(()=>{}); };

document.addEventListener('keydown', e => {
  if (e.code === 'Space') { e.preventDefault(); btnPlay.click(); }
  if (e.code === 'ArrowRight') next();
  if (e.code === 'ArrowLeft') prev();
  if (e.key.toLowerCase() === 'f') document.getElementById('btnFull').click();
});

let hideTimer;
function showControls() {
  topbar.classList.add('show');
  controls.classList.add('show');
  clearTimeout(hideTimer);
  hideTimer = setTimeout(() => {
    topbar.classList.remove('show');
    controls.classList.remove('show');
  }, 3000);
}
document.addEventListener('mousemove', showControls);
document.addEventListener('touchstart', showControls);

init();
</script>
</body>
</html>
