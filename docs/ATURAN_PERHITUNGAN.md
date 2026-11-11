# DOKUMEN ATURAN PERHITUNGAN & FORMULA PEMBUKUAN
## STANDAR AKUNTANSI KEUANGAN INTERNAL SEKRETARIAT DPRD KABUPATEN BANYUMAS

---

## 1. STANDAR PRESISI & TIPE DATA RUPIAH

Untuk menghindari *floating-point error* atau selisih pembulatan desimal ($0.0000001$) yang lazim terjadi pada komputasi umum:
1. Seluruh nilai mata uang Rupiah pada backend disimpan menggunakan tipe **`DECIMAL(15, 2)`** pada database MySQL.
2. Seluruh kalkulasi aritmetika pada PHP/Laravel menggunakan fungsi presisi tinggi **`bcmath`** atau pembulatan eksak integer `round($val, 2)` / integer sen (`bcmul`, `bcadd`, `bcsub`).
3. Pada frontend Vue 3, nilai selalu diformat menggunakan formatter resmi mata uang Indonesia:
   - Simbol mata uang: `Rp `
   - Pemisah ribuan: Titik (`.`)
   - Pemisah desimal: Koma (`,`) atau tanpa desimal untuk nominal bulat (`Rp 1.500.000`).

---

## 2. ATURAN FORMULA PERHITUNGAN BUKU BANK

### 2.1. Saldo Berjalan Harian (Daily Running Balance)
Untuk setiap baris transaksi $n$ pada sheet bulan aktif ($n \ge 13$):
$$\text{Saldo}_n = \text{Saldo}_{n-1} + \text{Penerimaan}_n - \text{Pengeluaran}_n$$
- Jika transaksi adalah **Penerimaan** (Kredit): Saldo bertambah.
- Jika transaksi adalah **Pengeluaran** (Debet): Saldo berkurang.

### 2.2. Saldo Awal Bulan Baru (Monthly Rollover)
Saldo awal pada tanggal 1 bulan baru diambil secara mutlak dari saldo penutupan bulan sebelumnya:
$$\text{Saldo Awal Bulan}_M = \text{Saldo Akhir Periode Bulan}_{M-1}$$
Contoh implementasi:
- `Februari Row 12 (Saldo Lalu)` = `=Jan!F124`
- `Maret Row 12 (Saldo Lalu)` = `=Feb!F281`
- `April Row 12 (Saldo Lalu)` = `=Maret!F...`

### 2.3. Rekapitulasi Penutupan Kas Bulanan
Pada bagian bawah setiap sheet Buku Bank, dilakukan perhitungan penutupan kas berjenjang:
1. **Jumlah Periode Ini:**
   $$\text{Penerimaan Periode Ini} = \sum_{i=12}^{\text{akhir}} \text{Penerimaan}_i$$
   $$\text{Pengeluaran Periode Ini} = \sum_{i=12}^{\text{akhir}} \text{Pengeluaran}_i$$
   $$\text{Saldo Periode Ini} = \text{Penerimaan Periode Ini} - \text{Pengeluaran Periode Ini}$$
2. **Jumlah Sampai Periode Lalu:**
   Akumulasi penerimaan dan pengeluaran sejak awal tahun anggaran hingga akhir bulan lalu.
3. **Jumlah Sampai Periode Ini:**
   $$\text{Total Penerimaan Akumulatif} = \text{Penerimaan Periode Lalu} + \text{Penerimaan Periode Ini}$$
   $$\text{Total Pengeluaran Akumulatif} = \text{Pengeluaran Periode Lalu} + \text{Pengeluaran Periode Ini}$$
   $$\text{Sisa Kas Akhir} = \text{Total Penerimaan Akumulatif} - \text{Total Pengeluaran Akumulatif}$$
4. **Verifikasi Fisik Sisa Kas:**
   $$\text{Sisa Kas Akhir} = \text{A. Kas Tunai} + \text{B. Saldo Bank} + \text{C. Surat Berharga} + \text{D. UMK}$$

---

## 3. ATURAN FORMULA PERHITUNGAN BKU UP & KAS KECIL (KK)

### 3.1. Uang Persediaan (UP)
- **Penerimaan Awal (SP2D UP):**
  Pagu UP tahun anggaran 2026 sebesar **Rp 720.000.000,00** masuk sebagai Penerimaan di baris awal.
- **Pengeluaran UP:**
  Terdiri dari:
  1. Penarikan kas tunai ke Kas Kecil (KK) untuk panjar PPTK.
  2. Debet langsung belanja operasional ke pihak ketiga.
- **Mekanisme Saldo Negatif Sementara:**
  Pada sistem kas berbasis UP, saldo kas berjalan dapat menjadi minus sementara ketika panjar tunai disalurkan sebelum diterbitkannya SP2D GU (Ganti Uang) dari BPKAD/Kasda. Saldo kembali positif saat SP2D GU dicairkan.

### 3.2. Kas Kecil (KK / Kas Tunai Fisik)
- **Penerimaan:**
  Fisik uang tunai yang ditarik dari teller/ATM Bank Jateng dimasukkan ke brankas bendahara:
  `Penerimaan KK = Ambil Tunai dari Bank`
- **Pengeluaran:**
  Pemberian panjar tunai fisik kepada staf PPTK yang memegang tanda terima panjar:
  `Pengeluaran KK = Panjar Tunai PPTK`
- **Penerimaan Pengembalian:**
  Sisa panjar tunai yang tidak habis dibelanjakan dikembalikan oleh PPTK ke kasir:
  `Penerimaan KK = Pengembalian Sisa Panjar`

---

## 4. ATURAN FORMULA BUKU PEMBANTU PPTK & SHEET `Hitungan`

### 4.1. Buku PPTK Individual (`Aul-Ety`, `Gaya`, `Ratman`, dsb)
Setiap PPTK mempertanggungjawabkan dua jenis pembiayaan kegiatan:
1. **Debet Rekening ($H$):** Belanja non-tunai langsung via giro ke toko/rekanan.
2. **Panjar Tunai ($I$):** Uang tunai yang diterima untuk operasional lapangan/perjalanan dinas.

Setiap PPTK wajib mengembalikan sisa dana atau menyerahkan kuitansi/bukti belanja:
- **Pengembalian Panjar:** Mengurangi nilai panjar ($I = I - \text{Pengembalian}$).
- **SPJ Definitif ($J$):** Nilai kuitansi yang sah diverifikasi untuk pengajuan GU.
- **Sisa Kewajiban Perjalanan/Kegiatan ($K$):**
  $$K = H + I - J$$

### 4.2. Matriks Bulanan Sheet `Jml Deb Rek`
- **Total Debet Tahunan PPTK ($O_4 \dots O_{12}$):**
  $$O_k = \sum_{m=1}^{12} \text{Debet Bulan } m \pm \text{Penyesuaian Manual}$$
- **Total Panjar Tahunan PPTK ($O_{24} \dots O_{32}$):**
  $$O_{20+k} = \sum_{m=1}^{12} \text{Panjar Bulan } m$$

### 4.3. Formula Rekapitulasi Eksekutif Sheet `Hitungan`
Berdasarkan analisis sel pada baris 2 s.d. 14 sheet `Hitungan`:
1. **Total Kewajiban (Kolom E / Jml):**
   $$E = C + D = \text{Debet} + \text{Panjar}$$
2. **Total Hutang Panjar (Kolom G):**
   $$G = E - F = \text{Total Kewajiban} - \text{SPJ}$$
3. **Sisa Panjar Tunai Riil (Kolom K):**
   $$K = I - J = \text{Sisa Tunai Fisik} - \text{SPJ Lanjutan}$$
4. **Total Sisa Riil (Kolom L):**
   $$L = K + H = \text{Sisa Panjar Tunai Riil} + \text{Sisa di Bank}$$
5. **KONTROL INTEGRITAS SELISIH (Kolom N):**
   $$N = G - L$$
   > **ATURAN KUNCI KEBERHASILAN (VALIDASI EMAS):**
   > Nilai pada Kolom $N$ **HARUS EQUAL KE 0**.
   > Jika $N \ne 0$, berarti terjadi selisih antara hutang pembukuan dengan sisa fisik uang (di bank + tunai) yang harus segera diinvestigasi oleh petugas keuangan.

---

## 5. ATURAN PERHITUNGAN KEGIATAN RESES & PAJAK PPH 23

Pada pelaksanaan Reses Sidang 1, 2, dan 3:
1. **Penerimaan SP2D TU Reses:**
   Dicatat pada sheet `Reses` sebagai penerimaan kas utama.
2. **Kalkulasi Potongan Pajak PPh Pasal 23 Sewa Tempat:**
   Setiap sewa tempat kegiatan reses dikenakan pemotongan PPh 23 sebesar **2% (0,02)**:
   $$\text{Pajak PPh 23} = 0,02 \times \text{Nilai Bruto Sewa}$$
   $$\text{Nilai Netto Transfer ke Pemilik Tempat} = \text{Nilai Bruto Sewa} - \text{Pajak PPh 23}$$
3. **Pajak SPJ & Selisih Setor Kasda:**
   $$\text{PPh SPJ} = 0,02 \times \text{Nilai SPJ Disahkan}$$
   $$\text{Netto SPJ} = \text{SPJ Disahkan} - \text{PPh SPJ}$$
   $$\text{Setor Sisa Kasda} = \text{Pagu Bruto} - \text{SPJ Disahkan}$$

---

## 6. PENANGANAN KASUS KHUSUS & PENYESUAIAN MANUAL (ADJUSTMENT ENGINE)

Aplikasi mengimplementasikan tabel `manual_adjustments` dengan aturan audit:
1. Penyesuaian tidak boleh langsung mengubah nilai transaksi riil bank atau faktur asli.
2. Penyesuaian dicatat sebagai entitas koreksi formal dengan relasi ke PPTK, bulan buku, nominal ($+$ atau $-$), tanggal, dan keterangan otorisasi.
3. Rumus agregasi otomatis menyertakan baris koreksi ini dalam subtotal `Jml Deb Rek` sehingga hasil akhirnya seimbang (*zero discrepancy*).
