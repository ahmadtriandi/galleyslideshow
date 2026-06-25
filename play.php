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
  #player, #imgPlayer {
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
<img id="imgPlayer" style="display:none">
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
const imgPlayer = document.getElementById('imgPlayer');
const startBtn= document.getElementById('startBtn');
const message = document.getElementById('message');
const topbar  = document.getElementById('topbar');
const controls= document.getElementById('controls');
const titleEl = document.getElementById('title');
const counter = document.getElementById('counter');

let allItems = [];            // semua nama item yang valid saat ini (sesuai filter)
let queue    = [];            // antrian acak yang akan diputar (diambil dari depan)
let priority = [];            // antrian prioritas: item baru, menyela lebih dulu
let currentName = null;       // nama item yang sedang tampil
let loop     = true;
let muted    = true;
let typeOf   = {};            // peta nama file -> 'video' | 'image'
let imgTimer = null;          // timer untuk gambar
let paused   = false;         // status jeda (berlaku untuk gambar)
const IMAGE_DURATION = 10000; // gambar tampil 10 detik

// Acak array (Fisher-Yates)
function shuffle(arr) {
  const a = arr.slice();
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

// Isi ulang antrian dengan urutan acak baru (1 putaran)
function refillQueue() {
  queue = shuffle(allItems);
}

function srcOf(name){ return 'api.php?action=stream&name=' + encodeURIComponent(name); }

async function fetchList() {
  try {
    // Ambil filter aktif dari server (all | video | image)
    let filter = 'all';
    try {
      const fres = await fetch('api.php?action=get_filter');
      if (fres.ok) { const fd = await fres.json(); filter = fd.filter || 'all'; }
    } catch {}

    const res = await fetch('api.php?action=videos');
    if (!res.ok) return [];
    let videos = await res.json();
    if (!Array.isArray(videos)) return [];
    videos.forEach(v => { typeOf[v.name] = v.type || 'video'; });  // simpan tipe

    // Saring sesuai filter moderator
    if (filter === 'video' || filter === 'image') {
      videos = videos.filter(v => (v.type || 'video') === filter);
    }
    return videos.map(v => v.name);
  } catch { return []; }
}

function updateInfo() {
  if (!currentName) return;
  titleEl.textContent = currentName;
  const sisa = priority.length + queue.length;
  counter.textContent = 'Antrian tersisa: ' + sisa + ' • total ' + allItems.length;
}

function playItem(name) {
  if (!name) return;
  currentName = name;

  // Bersihkan timer gambar sebelumnya bila ada
  if (imgTimer) { clearTimeout(imgTimer); imgTimer = null; }

  if (typeOf[name] === 'image') {
    // ----- Tampilkan GAMBAR -----
    player.pause();
    player.style.display = 'none';
    imgPlayer.src = srcOf(name);
    imgPlayer.style.display = 'block';
    startBtn.style.display = 'none';
    if (!paused) imgTimer = setTimeout(next, IMAGE_DURATION);
  } else {
    // ----- Tampilkan VIDEO -----
    imgPlayer.style.display = 'none';
    imgPlayer.removeAttribute('src');
    player.style.display = 'block';
    player.src = srcOf(name);
    player.muted = muted;
    player.load();
    const p = player.play();
    if (p && p.catch) p.catch(() => { startBtn.style.display = 'block'; });
  }
  updateInfo();
}

// Ambil item berikutnya: prioritaskan antrian 'priority', lalu 'queue'.
// Saat queue habis → acak ulang (putaran baru).
function next() {
  if (!allItems.length) return;

  let name = null;
  // 1) Item baru yang menyela
  while (priority.length) {
    const cand = priority.shift();
    if (allItems.includes(cand)) { name = cand; break; }
  }
  // 2) Antrian acak normal
  if (!name) {
    while (queue.length) {
      const cand = queue.shift();
      if (allItems.includes(cand)) { name = cand; break; }
    }
  }
  // 3) Queue habis → putaran baru (acak ulang)
  if (!name) {
    if (!loop) return;          // kalau loop mati, berhenti di akhir
    refillQueue();
    while (queue.length) {
      const cand = queue.shift();
      if (allItems.includes(cand)) { name = cand; break; }
    }
  }
  if (name) playItem(name);
}

function prev() {
  // 'Sebelumnya' pada mode acak: cukup acak satu item lain sebagai variasi
  if (allItems.length) playItem(allItems[Math.floor(Math.random()*allItems.length)]);
}

player.addEventListener('ended', next);
player.addEventListener('error', () => {
  message.innerHTML = 'Gagal memuat: ' + (currentName||'') + '<br><small>Lanjut berikutnya...</small>';
  message.style.display = 'flex';
  setTimeout(() => { message.style.display = 'none'; next(); }, 1500);
});
imgPlayer.addEventListener('error', () => {
  if (!allItems.length) return;
  if (imgTimer) { clearTimeout(imgTimer); imgTimer = null; }
  message.innerHTML = 'Gagal memuat: ' + (currentName||'') + '<br><small>Lanjut berikutnya...</small>';
  message.style.display = 'flex';
  setTimeout(() => { message.style.display = 'none'; next(); }, 1500);
});

async function init() {
  allItems = await fetchList();
  if (!allItems.length) { message.style.display = 'flex'; return; }
  message.style.display = 'none';
  refillQueue();           // acak putaran pertama
  next();                  // mulai putar item pertama
}

setInterval(async () => {
  const fresh = await fetchList();

  if (!fresh.length) {                 // semua item hilang/disembunyikan/tersaring
    allItems = []; queue = []; priority = []; currentName = null;
    player.pause();
    player.removeAttribute('src');
    player.load();
    imgPlayer.removeAttribute('src');
    if (imgTimer) { clearTimeout(imgTimer); imgTimer = null; }
    message.style.display = 'flex';
    return;
  }

  const sebelumnyaKosong = allItems.length === 0;

  // Deteksi item BARU (ada di fresh, belum dikenal sebelumnya)
  const baru = fresh.filter(n => !allItems.includes(n));

  // Perbarui daftar master
  allItems = fresh;

  // Bersihkan antrian dari item yang sudah tidak ada lagi
  queue    = queue.filter(n => allItems.includes(n));
  priority = priority.filter(n => allItems.includes(n));

  // Item baru → masukkan ke antrian PRIORITAS (menyela setelah yang sekarang)
  baru.forEach(n => { if (!priority.includes(n)) priority.push(n); });

  if (sebelumnyaKosong) {
    // Dari kosong → langsung mulai
    message.style.display = 'none';
    refillQueue();
    next();
    return;
  }

  // Kalau item yang SEDANG tampil sudah tidak ada (disembunyikan/dihapus/tersaring) → skip
  if (currentName && !allItems.includes(currentName)) {
    message.style.display = 'none';
    next();
    return;
  }

  message.style.display = 'none';
  updateInfo();
}, 10000);

document.getElementById('btnNext').onclick = next;
document.getElementById('btnPrev').onclick = prev;

const btnPlay = document.getElementById('btnPlay');
btnPlay.onclick = () => {
  const isImage = typeOf[currentName] === 'image';
  if (isImage) {
    // Jeda/lanjut untuk GAMBAR (pakai timer)
    if (paused) {
      paused = false;
      btnPlay.innerHTML = '&#9208; Jeda';
      imgTimer = setTimeout(next, IMAGE_DURATION);   // lanjutkan hitung mundur
    } else {
      paused = true;
      btnPlay.innerHTML = '&#9654; Putar';
      if (imgTimer) { clearTimeout(imgTimer); imgTimer = null; }
    }
  } else {
    // Jeda/lanjut untuk VIDEO
    if (player.paused) { paused = false; player.play(); btnPlay.innerHTML = '&#9208; Jeda'; }
    else { paused = true; player.pause(); btnPlay.innerHTML = '&#9654; Putar'; }
  }
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
