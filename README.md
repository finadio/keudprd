# Sistem Pembukuan Keuangan DPRD (KEUDPRD)

Aplikasi Web Pembukuan dan Penatausahaan Kas Keuangan Sekretariat DPRD (Berbasis Laravel 12 + Inertia.js Vue 3 + Tailwind CSS + MySQL).

---

## 🚀 Panduan Setup & Instalasi Lokal (Clone)

Bagi rekan / teman yang melakukan clone repository ini, ikuti langkah mudah berikut:

### 1. Clone Repository & Setup Environment
```bash
git clone https://github.com/finadio/keudprd.git
cd keudprd
cp .env.example .env
```

### 2. Install Dependencies
```bash
composer install
npm install
npm run build
```

### 3. Generate App Key
```bash
php artisan key:generate
```

### 4. Konfigurasi Database `.env`
Buka file `.env`, sesuaikan pengaturan database:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=keudprd
DB_USERNAME=root
DB_PASSWORD=
```

### 5. Import Database (`database/keudprd.sql`)
1. Buat database baru bernama **`keudprd`** (misal via phpMyAdmin atau HeidiSQL).
2. Import file **`database/keudprd.sql`** yang sudah tersedia di project ini:
   - **Via Terminal / Command Prompt:**
     ```bash
     mysql -u root -p keudprd < database/keudprd.sql
     ```
   - **Via phpMyAdmin:**
     Buka phpMyAdmin -> Pilih database `keudprd` -> Tab **Import** -> Pilih file `database/keudprd.sql` -> Klik **Go / Kirim**.

### 6. Jalankan Aplikasi
```bash
php artisan serve
```
Atau jika menggunakan Laragon, akses via browser di: `http://keudprd.test`

---

## 🔐 Akun Login Default

| Role | Email | Password |
|---|---|---|
| **Administrator Keuangan** | `admin@dprd.banyumaskab.go.id` | `password` |
| **Bendahara Pengeluaran** | `bendahara@dprd.banyumaskab.go.id` | `password` |
| **Operator Pembukuan** | `operator@dprd.banyumaskab.go.id` | `password` |

---

## 🛠️ Fitur Utama
- **Buku Kas Umum (BKU)**: Mutasi debet, kredit, saldo berjalan otomatis, penyesuaian manual (reversal).
- **Kalkulasi Reses & Pajak Otomatis**: PPh 21, PPh 22, PPh 23, PPN, Pajak Daerah, dan Zakat 100% presisi sesuai format Excel Setwan.
- **Multi-Tahun Anggaran (Fiscal Year)**: Switcher tahun aktif & lock/tutup periode akuntansi bulanan.
- **Export & Import Excel Laporan**: Export BKU, Pajak, Panjar, SP2D, dan Cetak PDF Berita Acara Rekonsiliasi.
