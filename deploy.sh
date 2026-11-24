#!/bin/bash

# ==============================================================================
# Script Otomatis Deployment - KeuDPRD Banyumas
# Repository : https://github.com/finadio/keudprd.git
# Framework  : Laravel 12 + Inertia Vue 3 (Vite)
# ==============================================================================
# Cara Penggunaan:
#   chmod +x deploy.sh
#   ./deploy.sh
#
# Opsi Tambahan:
#   ./deploy.sh --skip-build    (Lewati proses build frontend / npm)
#   ./deploy.sh --skip-composer (Lewati instalasi composer / dependensi PHP)
#   ./deploy.sh --skip-migrate  (Lewati proses migrasi database)
#   ./deploy.sh --seed          (Jalankan seeder database jika inisialisasi)
# ==============================================================================

# Hentikan eksekusi jika terjadi error kritis di luar blok penanganan
set -e

# ------------------------------------------------------------------------------
# 1. Konfigurasi Path, Repository & Environment
# ------------------------------------------------------------------------------
# Repository GitHub Resmi
REPO_URL="https://github.com/finadio/keudprd.git"
BRANCH="main"

# Path direktori Laravel utama di cPanel / Server (otomatis mendeteksi folder saat ini)
CURRENT_DIR="$(pwd -P 2>/dev/null || pwd)"
LARAVEL_PATH="${LARAVEL_PATH:-$CURRENT_DIR}"

# Nama folder project otomatis diambil dari nama folder aktif saat ini
PROJECT_DIR="$(basename "$LARAVEL_PATH")"

# Path folder publik jika menggunakan cPanel / Shared Hosting (default: ../public_html)
PUBLIC_HTML_PATH="${PUBLIC_HTML_PATH:-../public_html}"

# Executables
PHP_BIN="php"
COMPOSER_BIN="composer"
NPM_BIN="npm"

# Flag Opsi Default
SKIP_BUILD=false
SKIP_COMPOSER=false
SKIP_MIGRATE=false
RUN_SEED=false

for arg in "$@"; do
    case $arg in
        --skip-build|--skip-npm)
            SKIP_BUILD=true
            shift
            ;;
        --skip-composer)
            SKIP_COMPOSER=true
            shift
            ;;
        --skip-migrate)
            SKIP_MIGRATE=true
            shift
            ;;
        --seed)
            RUN_SEED=true
            shift
            ;;
    esac
done

echo "===================================================================="
echo "🚀 MEMULAI DEPLOYMENT: KEUDPRD BANYUMAS"
echo "📂 Project Directory : $PROJECT_DIR"
echo "🌐 Laravel Path      : $LARAVEL_PATH"
echo "🕒 Tanggal/Waktu     : $(date '+%Y-%m-%d %H:%M:%S')"
echo "===================================================================="

# ------------------------------------------------------------------------------
# 2. Verifikasi File .env & Pembersihan Cache Dev
# ------------------------------------------------------------------------------
echo "🔍 [1/8] Memeriksa konfigurasi environment (.env) & membersihkan cache lama..."
# Hapus cache bootstrap lama (sering terbawa dari zip/dev lokal sehingga memicu Class not found)
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php bootstrap/cache/routes*.php 2>/dev/null || true

if [ ! -f ".env" ]; then
    if [ -f ".env.production" ]; then
        echo "   📄 Menyalin .env.production ke .env..."
        cp .env.production .env
    elif [ -f ".env.example" ]; then
        echo "   ⚠️ File .env tidak ditemukan! Menyalin dari .env.example..."
        cp .env.example .env
        $PHP_BIN artisan key:generate --force
    else
        echo "   ❌ ERROR: File .env tidak ditemukan dan tidak ada template."
        exit 1
    fi
fi

# ------------------------------------------------------------------------------
# 3. Mode Maintenance Sementara (Opsional / Graceful)
# ------------------------------------------------------------------------------
echo "🚧 [2/8] Mengaktifkan Maintenance Mode sementara..."
if $PHP_BIN artisan --version >/dev/null 2>&1; then
    if [ -n "${MAINTENANCE_SECRET:-}" ]; then
        $PHP_BIN artisan down --render="errors::503" --secret="$MAINTENANCE_SECRET" || true
    else
        $PHP_BIN artisan down --render="errors::503" || true
    fi
fi

# ------------------------------------------------------------------------------
# 4. Tarik Pembaruan dari Git
# ------------------------------------------------------------------------------
echo "📥 [3/8] Mengambil kode terbaru dari Git ($REPO_URL)..."
if [ -d ".git" ]; then
    # Pastikan remote origin mengarah ke repository yang sesuai
    git remote set-url origin "$REPO_URL" 2>/dev/null || true
    git fetch origin "$BRANCH" 2>/dev/null || true
    # Simpan perubahan lokal jika ada agar tidak menggagalkan pull
    git stash 2>/dev/null || true
    if ! git pull origin "$BRANCH"; then
        echo "   ⚠️ Terjadi konflik lokal, menyinkronkan paksa ke origin/$BRANCH..."
        git reset --hard "origin/$BRANCH" || true
    fi
else
    echo "   ℹ️ Inisialisasi Git remote ke $REPO_URL..."
    git init
    git remote add origin "$REPO_URL" 2>/dev/null || true
    git fetch origin "$BRANCH" 2>/dev/null || true
    git pull origin "$BRANCH" 2>/dev/null || true
fi

# ------------------------------------------------------------------------------
# 5. Composer Dependencies (Optimized for Production)
# ------------------------------------------------------------------------------
echo "📦 [4/8] Menginstall dependensi Composer (PHP)..."
if [ "$SKIP_COMPOSER" = true ]; then
    echo "   ⏭️ Melewati instalasi composer (--skip-composer aktif)."
elif command -v $COMPOSER_BIN >/dev/null 2>&1; then
    # Menggunakan --no-scripts agar tidak gagal di shared hosting yang membatasi proc_open
    $COMPOSER_BIN install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts --ignore-platform-req=ext-fileinfo || \
    $COMPOSER_BIN install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs || \
    echo "   ⚠️ Composer install dibatasi pada hosting ini, melanjutkan proses..."

    # Hapus manifest lama dan jalankan package discovery bersih
    rm -f bootstrap/cache/packages.php bootstrap/cache/services.php 2>/dev/null || true
    $PHP_BIN artisan package:discover --ansi 2>/dev/null || true
else
    echo "   ⚠️ $COMPOSER_BIN tidak ditemukan di PATH sistem. Melewati composer install."
fi

# ------------------------------------------------------------------------------
# 6. Kompilasi Frontend Assets (Vite / Vue 3)
# ------------------------------------------------------------------------------
echo "🎨 [5/8] Memeriksa & mengompilasi aset frontend (Vite)..."
# Hapus file hot dev server agar Laravel selalu memakai build produksi
rm -f public/hot "$PUBLIC_HTML_PATH/hot" 2>/dev/null || true

if [ "$SKIP_BUILD" = false ]; then
    if command -v $NPM_BIN >/dev/null 2>&1; then
        echo "   ⚡ Menjalankan npm run build..."
        $NPM_BIN run build
    else
        echo "   ℹ️ npm tidak ditemukan di server. Menggunakan aset build produksi dari repository."
    fi
else
    echo "   ⏭️ Melewati kompilasi frontend (--skip-build aktif)."
fi

# Pastikan file hot selalu terhapus setelah proses apapun
rm -f public/hot "$PUBLIC_HTML_PATH/hot" 2>/dev/null || true

# ------------------------------------------------------------------------------
# 7. Migrasi Database & Seeder
# ------------------------------------------------------------------------------
echo "🗄️ [6/8] Menjalankan migrasi database..."
if [ "$SKIP_MIGRATE" = false ]; then
    $PHP_BIN artisan migrate --force
    if [ "$RUN_SEED" = true ]; then
        echo "   🌱 Menjalankan database seeder..."
        $PHP_BIN artisan db:seed --force
    fi
else
    echo "   ⏭️ Melewati migrasi database (--skip-migrate aktif)."
fi

# ------------------------------------------------------------------------------
# 8. Optimasi Cache Laravel & Permission
# ------------------------------------------------------------------------------
echo "⚡ [7/8] Mengoptimasi Cache & Pengaturan Hak Akses (Storage & Cache)..."
$PHP_BIN artisan optimize:clear
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache
$PHP_BIN artisan event:cache 2>/dev/null || true

# Buat / perbarui symbolic link storage Laravel secara aman
if [ -L "public/storage" ] && [ -e "public/storage" ]; then
    echo "   🔗 Symlink public/storage sudah aktif dan valid."
else
    rm -rf public/storage
    $PHP_BIN artisan storage:link >/dev/null 2>&1 || true
    echo "   🔗 Symlink public/storage berhasil disinkronkan."
fi

# Pastikan folder writable oleh web server
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# ------------------------------------------------------------------------------
# 9. Penanganan Khusus cPanel / Shared Hosting (public_html)
# ------------------------------------------------------------------------------
echo "🌐 [8/8] Memeriksa struktur Document Root (cPanel / VPS)..."

if [ -d "$PUBLIC_HTML_PATH" ]; then
    echo "   📂 Terdeteksi folder cPanel: $PUBLIC_HTML_PATH"
    echo "   📋 Menyalin file publik ke $PUBLIC_HTML_PATH..."

    # Pastikan file hot tidak terbawa ke public_html
    rm -f public/hot "$PUBLIC_HTML_PATH/hot" 2>/dev/null || true

    # Salin semua isi public/ ke public_html
    cp -r public/* "$PUBLIC_HTML_PATH/"

    # Pastikan kembali file hot terhapus di public_html
    rm -f "$PUBLIC_HTML_PATH/hot" 2>/dev/null || true

    # Salin .htaccess jika ada
    if [ -f "public/.htaccess" ]; then
        cp public/.htaccess "$PUBLIC_HTML_PATH/.htaccess"
    fi

    # Buat file index.php yang terhubung secara benar ke folder project ini
    echo "   📝 Mengonfigurasi index.php khusus cPanel..."
    cat > "$PUBLIC_HTML_PATH/index.php" << EOL
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Path ke Laravel utama
\$laravelPath = '${LARAVEL_PATH}';

// Maintenance mode
if (file_exists(\$maintenance = \$laravelPath.'/storage/framework/maintenance.php')) {
    require \$maintenance;
}

// Register Composer autoloader
require \$laravelPath.'/vendor/autoload.php';

// Bootstrap Laravel
/** @var Application \$app */
\$app = require_once \$laravelPath.'/bootstrap/app.php';

\$app->handleRequest(Request::capture());
EOL

    # Perbaiki Symbolic Link Storage di public_html
    echo "   🔗 Memperbaiki Symlink Storage di $PUBLIC_HTML_PATH/storage..."
    rm -rf "$PUBLIC_HTML_PATH/storage"
    ln -sfn "${LARAVEL_PATH}/storage/app/public" "$PUBLIC_HTML_PATH/storage" 2>/dev/null || \
    ln -sfn "../${PROJECT_DIR}/storage/app/public" "$PUBLIC_HTML_PATH/storage" 2>/dev/null || \
    cp -r "storage/app/public" "$PUBLIC_HTML_PATH/storage"
else
    echo "   ℹ️ Menjalankan mode standar (Document Root langsung mengarah ke public/)."
fi

# ------------------------------------------------------------------------------
# 10. Matikan Maintenance Mode (Website Kembali Online)
# ------------------------------------------------------------------------------
echo "🟢 Mengaktifkan kembali website (php artisan up)..."
$PHP_BIN artisan up || true

echo "===================================================================="
echo "✅ DEPLOYMENT BERHASIL SELESAI!"
echo "✨ KeuDPRD Banyumas Siap Digunakan!"
echo "===================================================================="
