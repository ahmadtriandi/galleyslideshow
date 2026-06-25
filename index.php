<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Galeri Video</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: system-ui, sans-serif; background: #0f1115; color: #e7e9ee; padding: 24px; }
  header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
  h1 { font-size: 22px; }
  .status { font-size: 13px; color: #8b90a0; }
  .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
  .card { background: #181b22; border: 1px solid #262a35; border-radius: 12px; overflow: hidden; }
  .card video { width: 100%; display: block; background: #000; aspect-ratio: 16/9; }
  .meta { padding: 12px 14px; }
  .meta .name { font-size: 14px; font-weight: 600; word-break: break-all; }
  .meta .sub { font-size: 12px; color: #8b90a0; margin-top: 4px; }
  .badge-new { display: inline-block; background: #2563eb; color: #fff; font-size: 10px; padding: 2px 7px; border-radius: 6px; margin-left: 6px; vertical-align: middle; }
  .empty { color: #8b90a0; text-align: center; padding: 60px; }
  a.modlink { color: #6b8afd; font-size: 13px; text-decoration: none; }
</style>
</head>
<body>
<header>
  <h1>🎬 Galeri Video</h1>
  <div style="text-align:right">
    <div class="status" id="status">Memuat…</div>
    <a class="modlink" href="play.php">▶ Putar Otomatis (Full Page)</a> &nbsp;
    <a class="modlink" href="admin.php">Panel Moderator →</a>
  </div>
</header>
<div class="grid" id="grid"></div>
<div class="empty" id="empty" style="display:none">Belum ada video di folder.</div>

<script>
let knownNames = new Set();
let firstLoad = true;

function fmtSize(b){ return (b/1048576).toFixed(1)+' MB'; }
function fmtTime(ms){ return new Date(ms).toLocaleString('id-ID'); }

async function load() {
  try {
    const res = await fetch('api.php?action=videos');
    const videos = await res.json();
    const grid = document.getElementById('grid');
    const empty = document.getElementById('empty');
    empty.style.display = videos.length ? 'none' : 'block';

    const currentNames = new Set(videos.map(v => v.name));
    const changed = currentNames.size !== knownNames.size ||
      [...currentNames].some(n => !knownNames.has(n));

    if (changed) {
      grid.innerHTML = '';
      videos.forEach(v => {
        const isNew = !firstLoad && !knownNames.has(v.name);
        const card = document.createElement('div');
        card.className = 'card';
        card.innerHTML = `
          <video controls preload="metadata" src="api.php?action=stream&name=${encodeURIComponent(v.name)}"></video>
          <div class="meta">
            <div class="name">${v.name}${isNew ? '<span class="badge-new">BARU</span>' : ''}</div>
            <div class="sub">${fmtSize(v.size)} • ${fmtTime(v.mtime)}</div>
          </div>`;
        grid.appendChild(card);
      });
      knownNames = currentNames;
      firstLoad = false;
    }
    document.getElementById('status').textContent =
      videos.length + ' video • diperbarui ' + new Date().toLocaleTimeString('id-ID');
  } catch (e) {
    document.getElementById('status').textContent = 'Gagal memuat';
  }
}

load();
setInterval(load, 5000); // cek video baru tiap 5 detik
</script>
</body>
</html>
