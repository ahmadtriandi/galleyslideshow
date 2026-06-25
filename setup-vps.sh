#!/bin/bash
# ============================================================
#  Skrip pemasangan Galeri Video di VPS (Ubuntu/Debian + Apache/Nginx)
#  Jalankan dari dalam folder aplikasi: sudo bash setup-vps.sh
# ============================================================

set -e

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
echo ">> Folder aplikasi: $APP_DIR"

# Deteksi user web server (Apache/Nginx di Ubuntu biasanya www-data)
WEB_USER="www-data"
if id "$WEB_USER" >/dev/null 2>&1; then
  echo ">> User web server terdeteksi: $WEB_USER"
else
  echo "!! User www-data tidak ditemukan. Sesuaikan WEB_USER di skrip ini."
  exit 1
fi

# Pastikan folder videos ada
mkdir -p "$APP_DIR/videos"

# Atur kepemilikan: web server perlu bisa menulis (untuk hapus video & hidden.json)
echo ">> Mengatur kepemilikan ke $WEB_USER ..."
chown -R "$WEB_USER":"$WEB_USER" "$APP_DIR"

# Izin: file 644, folder 755, folder videos perlu bisa ditulis web server
find "$APP_DIR" -type f -exec chmod 644 {} \;
find "$APP_DIR" -type d -exec chmod 755 {} \;
chmod 775 "$APP_DIR/videos"

# Buat hidden.json kosong jika belum ada, agar bisa langsung ditulis
if [ ! -f "$APP_DIR/hidden.json" ]; then
  echo "[]" > "$APP_DIR/hidden.json"
  chown "$WEB_USER":"$WEB_USER" "$APP_DIR/hidden.json"
  chmod 664 "$APP_DIR/hidden.json"
fi

# Buat filter.json default (all) jika belum ada
if [ ! -f "$APP_DIR/filter.json" ]; then
  echo "all" > "$APP_DIR/filter.json"
  chown "$WEB_USER":"$WEB_USER" "$APP_DIR/filter.json"
  chmod 664 "$APP_DIR/filter.json"
fi

echo ""
echo ">> Selesai. Cek hal berikut:"
echo "   1. Pastikan folder ini berada di dalam web root (mis. /var/www/html/)."
echo "   2. WAJIB ganti MOD_PASSWORD di config.php (jangan biarkan 'admin123')."
echo "   3. Buka di browser: http://DOMAIN-ATAU-IP-ANDA/video-php/index.php"
echo ""
echo "   Password moderator saat ini:"
grep "MOD_PASSWORD" "$APP_DIR/config.php" | head -1
