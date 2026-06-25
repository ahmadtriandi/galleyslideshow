<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pemutaran Otomatis (Diagnostik)</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html, body { height: 100%; background: #000; overflow: hidden; font-family: system-ui, sans-serif; }

  #player {
    position: fixed; inset: 0;
    width: 100%; height: 100%;
    object-fit: contain;
    background: #000;
  }

  /* Panel status selalu terlihat di pojok, agar tidak pernah "hitam diam" */
  #log {
    position: fixed; top: 10px; left: 10px; z-index: 50;
    max-width: 90vw; max-height: 50vh; overflow: auto;
    background: rgba(0,0,0,.75); color: #0f0;
    font-family: monospace; font-size: 13px; line-height: 1.5;
    padding: 12px 14px; border-radius: 8px; border: 1px solid #0f0;
    white-space: pre-wrap;
  }
  #log .err { color: #ff5555; }
  #log .warn { color: #ffcc00; }

  #startBtn {
    position: fixed; inset: 0; margin: auto;
    width: 240px; height: 70px; display: none; z-index: 60;
    font-size: 18px; background: #2563eb; color: #fff;
    border: none; border-radius: 10px; cursor: pointer;
  }

  .controls {
    position: fixed; bottom: 16px; left: 50%; transform: translateX(-50%);
    z-index: 50; display: flex; gap: 8px;
  }
  .controls button {
    cursor: pointer; border: none; border-radius: 8px;
    padding: 8px 14px; font-weight: 600; font-size: 13px;
    background: rgba(255,255,255,.2); color: #fff;
  }
  a.back { position: fixed; top: 10px; right: 10px; z-index: 50;
    color: #fff; background: rgba(0,0,0,.5); padding: 6px 12px;
    border-radius: 8px; text-decoration: none; font-size: 13px; }
</style>
</head>
<body>
<video id="player" playsinline muted></video>
<button id="startBtn">▶ Mulai Putar</button>

<div id="log">Memulai diagnostik…</div>

<div class="controls">
  <button id="btnPrev">⏮ Prev</button>
  <button id="btnNext">Next ⏭</button>
  <button id="btnMute">🔊 Suara</button>
  <button id="btnFull">⛶ Fullscreen</button>
  <button id="btnHideLog">Sembunyikan Log</button>
</div>
<a class="back" href="index.php">← Galeri</a>

<script>
const player   = document.getElementById('player');
const startBtn = document.getElementById('startBtn');
const logBox   = document.getElementById('log');

let playlist = [];
let current  = 0;
let muted    = true;

function log(msg, cls) {
  const line = document.createElement('div');
  if (cls) line.className = cls;
  line.textContent = new Date().toLocaleTimeString('id-ID') + '  ' + msg;
  logBox.appendChild(line);
  logBox.scrollTop = logBox.scrollHeight;
  console.log(msg);
}

function srcOf(name){ return 'api.php?action=stream&name=' + encodeURIComponent(name); }

async function fetchList() {
  const url = 'api.php?action=videos';
  log('Memanggil: ' + url);
  let res;
  try {
    res = await fetch(url);
  } catch (e) {
    log('GAGAL fetch (jaringan/CORS): ' + e.message, 'err');
    return null;
  }
  log('Status HTTP: ' + res.status + ' ' + res.statusText, res.ok ? null : 'err');
  const text = await res.text();
  if (!res.ok) {
    log('Isi respons: ' + text.slice(0, 300), 'err');
    return null;
  }
  let data;
  try {
    data = JSON.parse(text);
  } catch (e) {
    log('Respons BUKAN JSON. Isi mentah: ' + text.slice(0, 300), 'err');
    return null;
  }
  if (!Array.isArray(data)) {
    log('JSON bukan array: ' + JSON.stringify(data).slice(0,200), 'err');
    return null;
  }
  log('Jumlah video diterima: ' + data.length +
      (data.length ? ' → ' + data.map(v=>v.name).join(', ') : ' (KOSONG)'),
      data.length ? null : 'warn');
  return data.map(v => v.name);
}

function playIndex(i) {
  if (!playlist.length) { log('Playlist kosong, tidak ada yang diputar.', 'warn'); return; }
  current = (i + playlist.length) % playlist.length;
  const name = playlist[current];
  log('Memutar [' + (current+1) + '/' + playlist.length + ']: ' + name);
  player.src = srcOf(name);
  player.muted = muted;
  player.load();
  const p = player.play();
  if (p && p.catch) {
    p.catch(err => {
      log('Autoplay DIBLOKIR browser: ' + err.message + ' → klik tombol Mulai', 'warn');
      startBtn.style.display = 'block';
    });
  }
}

player.addEventListener('playing', () => log('▶ Video sedang diputar (OK).'));
player.addEventListener('ended',   () => { log('Video selesai → berikutnya.'); next(); });
player.addEventListener('error',   () => {
  const e = player.error;
  const codes = {1:'ABORTED',2:'NETWORK',3:'DECODE (codec tidak didukung)',4:'SRC_NOT_SUPPORTED (file/codec/format ditolak)'};
  log('GAGAL memuat "' + (playlist[current]||'') + '" → ' +
      (e ? codes[e.code] || ('code '+e.code) : 'unknown') + '. Lewati 2 detik…', 'err');
  setTimeout(next, 2000);
});

function next() { playIndex(current + 1); }
function prev() { playIndex(current - 1); }

async function init() {
  log('Lokasi halaman: ' + location.href);
  const list = await fetchList();
  if (list === null) {
    log('Tidak bisa mengambil daftar video. Periksa api.php (lihat pesan merah di atas).', 'err');
    return;
  }
  if (list.length === 0) {
    log('Folder "videos" kosong ATAU semua video disembunyikan moderator.', 'warn');
    log('Solusi: taruh file .mp4 ke folder videos, atau buka admin.php → Tampilkan.', 'warn');
    return;
  }
  playlist = list;
  playIndex(0);
}

// Kontrol
document.getElementById('btnNext').onclick = next;
document.getElementById('btnPrev').onclick = prev;
document.getElementById('btnMute').onclick = function() {
  muted = !muted; player.muted = muted;
  this.textContent = muted ? '🔇 Bisu' : '🔊 Suara';
  log('Suara: ' + (muted ? 'BISU' : 'AKTIF'));
};
document.getElementById('btnFull').onclick = () => {
  if (!document.fullscreenElement) document.documentElement.requestFullscreen();
  else document.exitFullscreen();
};
document.getElementById('btnHideLog').onclick = function() {
  logBox.style.display = logBox.style.display === 'none' ? 'block' : 'none';
  this.textContent = logBox.style.display === 'none' ? 'Tampilkan Log' : 'Sembunyikan Log';
};
startBtn.onclick = () => {
  startBtn.style.display = 'none';
  log('Tombol Mulai diklik → mencoba play()');
  player.play().then(()=>log('OK, mulai diputar.')).catch(e=>log('Masih gagal: '+e.message,'err'));
};

init();
</script>
</body>
</html>
