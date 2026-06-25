<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Galeri Kiosk</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: system-ui, sans-serif; background: #0f1115; color: #e7e9ee; padding: 24px; }
  header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
  h1 { font-size: 22px; }
  .status { font-size: 13px; color: #8b90a0; }
  a.navlink { color: #6b8afd; font-size: 13px; text-decoration: none; }

  /* Grid thumbnail */
  .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px; }
  .card {
    background: #181b22; border: 1px solid #262a35; border-radius: 12px;
    overflow: hidden; cursor: pointer; position: relative;
    transition: transform .12s, border-color .12s;
  }
  .card:hover { transform: translateY(-2px); border-color: #6b8afd; }
  .thumb {
    width: 100%; aspect-ratio: 16/10; object-fit: cover;
    background: #000; display: block;
  }
  .badge {
    position: absolute; top: 8px; left: 8px;
    background: rgba(0,0,0,.65); color: #fff; font-size: 10px;
    padding: 2px 8px; border-radius: 6px; backdrop-filter: blur(4px);
  }
  .badge-new { position: absolute; top: 8px; right: 8px; background: #2563eb; }
  .cardname {
    padding: 9px 11px; font-size: 12px; color: #cfd3dd;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .empty { color: #8b90a0; text-align: center; padding: 60px; }

  /* Popup */
  #modal {
    position: fixed; inset: 0; z-index: 100; display: none;
    background: rgba(0,0,0,.85); padding: 24px;
    align-items: center; justify-content: center;
  }
  #modal.show { display: flex; }
  .modalbox {
    background: #15181f; border: 1px solid #2a2f3a; border-radius: 14px;
    max-width: 92vw; max-height: 90vh; overflow: auto;
    display: flex; gap: 22px; padding: 22px; flex-wrap: wrap;
    align-items: flex-start; justify-content: center;
  }
  .mediawrap { flex: 1 1 420px; min-width: 280px; max-width: 70vw; }
  .mediawrap video, .mediawrap img {
    width: 100%; max-height: 72vh; border-radius: 10px; background: #000;
    display: block; object-fit: contain;
  }
  .sidewrap {
    flex: 0 0 240px; display: flex; flex-direction: column;
    align-items: center; gap: 12px; color: #cfd3dd;
  }
  .sidewrap h3 { font-size: 14px; word-break: break-all; text-align: center; }
  #qrbox {
    background: #fff; padding: 12px; border-radius: 10px;
    width: 200px; height: 200px; display: flex; align-items: center; justify-content: center;
  }
  #qrbox img, #qrbox canvas { width: 100% !important; height: 100% !important; }
  .qrhint { font-size: 12px; color: #8b90a0; text-align: center; }
  .btnrow { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; }
  .btn {
    text-decoration: none; cursor: pointer; border: none; border-radius: 8px;
    padding: 9px 14px; font-size: 13px; font-weight: 600;
    background: #2563eb; color: #fff;
  }
  .btn.sec { background: rgba(255,255,255,.15); }
  .close {
    position: fixed; top: 18px; right: 24px; font-size: 30px;
    color: #fff; cursor: pointer; background: none; border: none; z-index: 110;
  }
  .linkbox {
    font-size: 11px; color: #8b90a0; word-break: break-all; text-align: center;
    background: #0f1115; padding: 8px; border-radius: 8px; width: 100%;
  }
</style>
</head>
<body>
<header>
  <h1>🖼️ Galeri Kiosk</h1>
  <div style="text-align:right">
    <div class="status" id="status">Memuat…</div>
    <a class="navlink" href="index.php">← Galeri biasa</a>
  </div>
</header>

<div class="grid" id="grid"></div>
<div class="empty" id="empty" style="display:none">Belum ada media di folder.</div>

<!-- Popup -->
<button class="close" onclick="closeModal()" style="display:none" id="closeBtn">✕</button>
<div id="modal" onclick="if(event.target.id==='modal')closeModal()">
  <div class="modalbox">
    <div class="mediawrap" id="mediaWrap"></div>
    <div class="sidewrap">
      <h3 id="mTitle">—</h3>
      <div id="qrbox"></div>
      <div class="qrhint">Scan untuk buka di HP</div>
      <div class="btnrow">
        <a class="btn" id="openBtn" target="_blank" rel="noopener">Buka</a>
        <a class="btn sec" id="dlBtn" download>Unduh</a>
      </div>
      <div class="linkbox" id="linkBox"></div>
    </div>
  </div>
</div>

<!-- Library QR (dengan fallback ke gambar QR online bila gagal dimuat) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
let knownNames = new Set();
let firstLoad = true;

function fmtSize(b){ return (b/1048576).toFixed(1)+' MB'; }
function urlOf(name){ return 'api.php?action=stream&name=' + encodeURIComponent(name); }
// URL absolut (untuk QR & buka di HP)
function absUrlOf(name){
  return location.origin + location.pathname.replace(/kiosk\.php$/, '') + urlOf(name);
}

async function load() {
  try {
    const res = await fetch('api.php?action=videos');
    const items = await res.json();
    const grid = document.getElementById('grid');
    const empty = document.getElementById('empty');
    empty.style.display = items.length ? 'none' : 'block';

    const currentNames = new Set(items.map(v => v.name));
    const changed = currentNames.size !== knownNames.size ||
      [...currentNames].some(n => !knownNames.has(n));

    if (changed) {
      grid.innerHTML = '';
      items.forEach(v => {
        const isNew = !firstLoad && !knownNames.has(v.name);
        const card = document.createElement('div');
        card.className = 'card';
        const url = urlOf(v.name);
        // Thumbnail ringan: gambar pakai <img>, video pakai <video> preload metadata
        const thumb = v.type === 'image'
          ? `<img class="thumb" src="${url}" loading="lazy">`
          : `<video class="thumb" muted preload="metadata" src="${url}#t=0.5"></video>`;
        card.innerHTML = `
          ${thumb}
          <span class="badge">${v.type === 'image' ? 'Gambar' : 'Video'}</span>
          ${isNew ? '<span class="badge badge-new">BARU</span>' : ''}
          <div class="cardname">${v.name}</div>`;
        card.onclick = () => openModal(v.name, v.type);
        grid.appendChild(card);
      });
      knownNames = currentNames;
      firstLoad = false;
    }
    document.getElementById('status').textContent =
      items.length + ' media • diperbarui ' + new Date().toLocaleTimeString('id-ID');
  } catch (e) {
    document.getElementById('status').textContent = 'Gagal memuat';
  }
}

function makeQR(text) {
  const box = document.getElementById('qrbox');
  box.innerHTML = '';
  // Coba library QRCode.js
  if (typeof QRCode !== 'undefined') {
    try {
      new QRCode(box, { text: text, width: 200, height: 200, correctLevel: QRCode.CorrectLevel.M });
      return;
    } catch (e) { /* jatuh ke fallback */ }
  }
  // Fallback: gambar QR dari layanan online (butuh internet di HP/penonton)
  const img = document.createElement('img');
  img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(text);
  img.alt = 'QR';
  box.appendChild(img);
}

function openModal(name, type) {
  const wrap = document.getElementById('mediaWrap');
  const abs = absUrlOf(name);
  wrap.innerHTML = type === 'image'
    ? `<img src="${urlOf(name)}">`
    : `<video src="${urlOf(name)}" controls autoplay playsinline></video>`;
  document.getElementById('mTitle').textContent = name;
  document.getElementById('openBtn').href = abs;
  document.getElementById('dlBtn').href = urlOf(name);
  document.getElementById('dlBtn').setAttribute('download', name);
  document.getElementById('linkBox').textContent = abs;
  makeQR(abs);
  document.getElementById('modal').classList.add('show');
  document.getElementById('closeBtn').style.display = 'block';
}

function closeModal() {
  const wrap = document.getElementById('mediaWrap');
  const vid = wrap.querySelector('video');
  if (vid) { vid.pause(); }
  wrap.innerHTML = '';
  document.getElementById('modal').classList.remove('show');
  document.getElementById('closeBtn').style.display = 'none';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

load();
setInterval(load, 5000); // cek media baru tiap 5 detik
</script>
</body>
</html>
