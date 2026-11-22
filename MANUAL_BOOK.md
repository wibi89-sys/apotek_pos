# BUKU PANDUAN PENGGUNA (MANUAL BOOK)
# SISTEM POS APOTEK HYBRID ENTERPRISE
**Solusi Manajemen Farmasi Offline-First, Multi-Cabang, Multi-Satuan FEFO, & Silent Printing ESC/POS**

---

## DAFTAR ISI
1. [Pengenalan & Arsitektur Sistem Hybrid](#1-pengenalan--arsitektur-sistem-hybrid)
2. [Panduan Memulai & Peluncur Desktop (Quick Start)](#2-panduan-memulai--peluncur-desktop-quick-start)
3. [Manajemen Lisensi & Hak Fitur (Tier Entitlements)](#3-manajemen-lisensi--hak-fitur-tier-entitlements)
4. [Manajemen Shift Kasir (Buka & Tutup Kasir)](#4-manajemen-shift-kasir-buka--tutup-kasir)
5. [Mesin POS Kasir "Alfamart-Style" (Shortcut Keyboard & FEFO)](#5-mesin-pos-kasir-alfamart-style-shortcut-keyboard--fefo)
6. [Master Obat & Multi-Satuan Farmasi](#6-master-obat--multi-satuan-farmasi)
7. [Penerimaan Faktur Pembelian PBF (Faktur Masuk)](#7-penerimaan-faktur-pembelian-pbf-faktur-masuk)
8. [Database Management (DBM), Backup & Restore](#8-database-management-dbm-backup--restore)
9. [Biometrik Sidik Jari & Hardware Bridge](#9-biometrik-sidik-jari--hardware-bridge)
10. [Sinkronisasi Cloud & Laporan Regulasi (SIPNAP & Laba Kotor)](#10-sinkronisasi-cloud--laporan-regulasi-sipnap--laba-kotor)
11. [Troubleshooting & Tanya Jawab (FAQ)](#11-troubleshooting--tanya-jawab-faq)

---

## 1. Pengenalan & Arsitektur Sistem Hybrid

Sistem POS Apotek Hybrid dirancang khusus untuk memenuhi standar ketat apotek modern di Indonesia, mengombinasikan ketahanan **100% Offline-First** di meja kasir dengan keunggulan **Cloud Monitoring Terpusat** bagi Pemilik Sarana Apotek (PSA).

### Struktur Monorepo 3 Pilar:
| Pilar Komponen | Lokasi Folder | Port Default | Deskripsi & Peran |
| :--- | :--- | :--- | :--- |
| **POS Lokal Cabang** | `/apotek-pos-local` | `8001` | Antarmuka kasir meja depan (Livewire, Tailwind CSS v4, MySQL). Bekerja 100% offline tanpa ketergantungan internet. |
| **Cloud Hub Terpusat** | `/apotek-cloud-hub` | `8000` | Server lisensi Ed25519, dashboard multi-cabang owner, dan agregasi data penjualan online. |
| **Hardware Bridge** | `/hardware-bridge` | `8765` | Service latar belakang (Python FastAPI) pengendali printer thermal ESC/POS, cash drawer, dan sensor sidik jari. |

### 3 Paket Lisensi (Tier Plans):
1. **STANDARD LOCAL (Lifetime)**: Lisensi lokal permanen untuk 1 cabang & 2 terminal. Mendukung seluruh modul inti: Kasir POS Alfamart-style, Multi-Satuan FEFO, Resep/Racikan, Shift Kasir, Silent Printing thermal, Backup/Restore lokal, dan Audit Integritas Stok.
2. **PRO CLOUD SYNC (Subscription)**: Menambah kapasitas hingga 5 terminal, otentikasi biometrik sidik jari, cetak label barcode, ekspor regulasi resmi **SIPNAP Kemenkes**, analisis laba kotor riil HPP, dan sinkronisasi otomatis ke Cloud Hub.
3. **ENTERPRISE (Multi-Branch)**: Kapasitas cabang tanpa batas, katalog terpusat (*cloud sync pull*), dan integrasi API pihak ketiga.

---

## 2. Panduan Memulai & Peluncur Desktop (Quick Start)

### Menjalankan Sistem Kasir dengan Satu Klik:
Sistem telah dilengkapi skrip peluncur otomatis di folder `/scripts`:

1. **Jalankan Aplikasi Kasir Normal**:
   - Dobel-klik file: `scripts\start-all.bat`
   - Skrip akan otomatis:
     - Memeriksa dan menjalankan **MySQL Database Server** (Port 3306).
     - Memeriksa dan menjalankan **Cloud Hub** (Port 8000).
     - Memeriksa dan menjalankan **Hardware Bridge** (Port 8765 - U.are.U & Printer Thermal).
     - Memeriksa dan menjalankan **Server POS Lokal** (Port 8001).
     - Membuka antarmuka kasir POS di browser standar dengan tombol kontrol jendela aktif.
2. **Menutup & Mengakhiri Layanan Kasir**:
   - Klik tombol merah **`🚪 Tutup / Keluar Kasir`** di pojok kanan atas layar kasir untuk memilih:
     - Tutup Shift Kasir (*Blind Closing*).
     - Berpindah ke menu modul web lain (*Master Obat, Faktur PBF, dll*).
     - Menutup jendela browser atau tekan shortcut keyboard **`Alt + F4`**.
   - Dobel-klik file: `scripts\stop-all.bat` untuk menghentikan seluruh proses latar belakang dengan bersih.

### Alamat Akses Browser (Manual):
- **POS Kasir Lokal**: [http://127.0.0.1:8001/pos](http://127.0.0.1:8001/pos)
- **Master Obat & Multi-Satuan**: [http://127.0.0.1:8001/products](http://127.0.0.1:8001/products)
- **Kelola Pegawai & Hak Akses Kasir**: [http://127.0.0.1:8001/settings/users](http://127.0.0.1:8001/settings/users)
- **Master Data Dokter Resep**: [http://127.0.0.1:8001/masters/doctors](http://127.0.0.1:8001/masters/doctors)
- **Master Distributor & PBF Obat**: [http://127.0.0.1:8001/masters/suppliers](http://127.0.0.1:8001/masters/suppliers)
- **Faktur Masuk PBF**: [http://127.0.0.1:8001/purchases/create](http://127.0.0.1:8001/purchases/create)
- **Informasi Lisensi Lokal**: [http://127.0.0.1:8001/license/info](http://127.0.0.1:8001/license/info)
- **Database & Maintenance**: [http://127.0.0.1:8001/settings/database](http://127.0.0.1:8001/settings/database)

---

## 3. Manajemen Lisensi & Hak Fitur (Tier Entitlements)

### A. Alur Aktivasi Lisensi Baru:
1. Hubungi administrator Cloud Hub untuk mendapatkan Kunci Lisensi (Format: `APT-XXXX-XXXX-XXXX`).
2. Buka menu **Lisensi -> Aktivasi** (`http://127.0.0.1:8001/license/activate`).
3. Masukkan Kunci Lisensi atau klik tombol **`⚡ Pasang Otomatis: APT-DEMO-PRO-2026`**, lalu klik **Aktivasi Sekarang**.
4. Sistem akan mengontak Cloud Hub, memverifikasi Machine Hardware ID (HWID), mengunduh sertifikat digital Ed25519, dan menyimpannya di file `storage/app/private/license.lic`.
5. **Setelah aktivasi, koneksi internet dapat diputus total!** POS akan berjalan 100% offline.

### B. Proteksi Anti-Clock Tampering:
Sistem menyimpan cap waktu aman terenkripsi di database. Jika jam Windows dimundurkan lebih dari 5 menit (upaya manipulasi masa aktif lisensi), sistem akan secara otomatis mengunci transaksi dengan galat `ClockTamperedException`. Untuk membukanya, sinkronkan kembali jam Windows dengan waktu internet (*Internet Time Sync*).

### C. Masa Tenggang (Grace Period):
- **H-14 sebelum habis masa aktif**: Muncul banner peringatan kuning di header kasir.
- **Masa Tenggang (14 hari setelah jatuh tempo)**: Kasir tetap dapat beroperasi normal offline; fungsi sinkronisasi cloud dijeda sementara.
- **Setelah masa tenggang berakhir**: Transaksi kasir terkunci, namun **tombol Unduh Backup Darurat Database tetap dibuka untuk Pemilik Apotek**.

---

## 4. Manajemen Shift Kasir (Buka & Tutup Kasir)

Untuk mencegah kecurangan dan memastikan uang fisik di laci kasir (*cash drawer*) cocok dengan sistem, transaksi POS hanya dapat dibuka jika shift kasir berstatus **Aktif**.

### A. Membuka Shift Kasir (Awal Hari / Pergantian Regu):
1. Buka menu **Shift Kasir** (`http://127.0.0.1:8001/pos/shift`).
2. Masukkan jumlah **Modal Awal Kasir** (uang receh kembalian, contoh: `Rp 100.000`).
3. Klik tombol **Buka Shift Kasir**.
4. Sistem mencatat waktu buka, nama kasir, dan mengarahkan langsung ke antarmuka POS.

### B. Menutup Shift Kasir & Blind Closing (Akhir Hari):
Sistem menerapkan metode **Blind Closing** (Kasir tidak diberitahu berapa omzet sistem sebelum menghitung uang fisik, demi integritas kasir):
1. Di akhir jam kerja, kasir menghitung seluruh uang tunai yang ada di dalam laci kasir secara fisik.
2. Buka menu **Shift Kasir -> Tutup Shift**.
3. Masukkan nominal fisik yang dihitung pada kolom **Uang Fisik Kasir**.
4. Klik tombol **Tutup Shift Kasir**.
5. Sistem akan menghitung:
   $$\text{Expected Cash} = \text{Modal Awal} + \text{Total Penjualan Tunai}$$
   $$\text{Selisih} = \text{Uang Fisik} - \text{Expected Cash}$$
   - **Selisih = 0**: Uang fisik COCOK pas.
   - **Selisih Positif**: Uang fisik LEBIH.
   - **Selisih Negatif**: Uang fisik KURANG.
6. **Auto-Backup**: Sistem secara otomatis mengeksekusi cadangan database (`.zip`) instan saat shift resmi ditutup.

---

## 5. Mesin POS Kasir "Alfamart-Style" (Shortcut Keyboard & FEFO)

Layar Kasir dirancang untuk kecepatan tinggi (*High-Throughput*) tanpa menyentuh mouse sama sekali. Kursor secara otomatis terkunci (*Permanent Auto-Focus*) pada kolom barcode obat.

### Tabel Tombol Pintas Keyboard (Shortcuts):
| Tombol | Fungsi Cepat | Keterangan Penggunaan |
| :---: | :--- | :--- |
| **`F1`** | **Cari Obat / Zat Aktif** | Membuka jendela pop-up pencarian Fulltext (< 50ms) berdasarkan nama obat, nama generik, komposisi zat aktif, atau golongan obat. |
| **`F2`** | **Ubah Jumlah (Qty)** | Mengubah kuantitas item obat yang sedang disorot di keranjang belanja. |
| **`F3`** | **Ubah Satuan Jual** | Mengganti satuan secara cepat (misal: dari **Tablet** ke **Strip** atau **Box**), harga jual langsung menyesuaikan otomatis. |
| **`F4`** | **Mode Resep & Racikan** | Membuka input data resep: Nama Pasien, Nama Dokter, biaya Tuslah & Embalase (jasa peracikan), serta obat racikan puyer/kapsul. |
| **`F8`** | **Hold Transaksi** | Menunda transaksi pelanggan saat ini ke memori sementara (misal: saat pelanggan mengambil barang tambahan). |
| **`F9`** | **Recall Transaksi** | Memanggil kembali transaksi yang sebelumnya ditunda (Hold) ke kasir. |
| **`F10`** | **Layar Pembayaran** | Membuka jendela pembayaran. Pilih metode: **Tunai (Cash)**, **QRIS**, **Kartu Debit**, atau **Transfer Bank**. |
| **`Ctrl + V`** | **Void Transaksi / Item** | Membatalkan item/transaksi yang salah input. **Wajib otorisasi Sidik Jari atau PIN Supervisor (`123456`)**. Stok obat otomatis dikembalikan utuh ke nomor batch asalnya. |
| **`ESC`** | **Tutup Modal & Batal** | Menutup modal pop-up yang terbuka dan mengembalikan kursor fokus ke input scan barcode. |

### Algoritma Pemotongan Stok FEFO (First-Expired, First-Out):
1. **Prioritas Tanggal Kadaluwarsa**: Ketika kasir memindai obat, sistem otomatis memotong stok dari nomor batch yang memiliki tanggal expired paling dekat.
2. **Auto-Split Lintas Batch**: Jika pelanggan membeli 15 tablet, sementara Batch A yang mendekati expired hanya tersisa 10 tablet, sistem secara cerdas mengambil 10 tablet dari Batch A dan 5 tablet dari Batch B.
3. **Penyaringan Batch Kedaluwarsa**: Batch yang telah melewati tanggal kedaluwarsa hari ini otomatis **dilewati / diblokir** sehingga tidak akan pernah terjual ke pasien.
4. **Snapshot HPP Riil**: Setiap baris item mencatat harga pokok pembelian (HPP) modal batch secara akurat untuk menghitung laba kotor apotek.

---

## 6. Master Obat & Multi-Satuan Farmasi

Kelola katalog sediaan farmasi di menu **Master Obat** (`http://127.0.0.1:8001/products`).

### A. Pengaturan Multi-Satuan Bertingkat:
Apotek menjual obat dalam berbagai satuan. Sistem mendukung konversi fleksibel:
- **Satuan Terkecil (Base Unit)**: Tablet / Kapsul / Botol / Sachet / Ampul.
- **Satuan Menengah**: Strip (Faktor konversi = 10 Tablet).
- **Satuan Terbesar**: Box (Faktor konversi = 100 Tablet).
*Setiap satuan dapat memiliki barcode sendiri dan harga jual yang berbeda.*

### B. Kategori Golongan Obat BPOM:
- 🟢 **Obat Bebas**: Penjualan bebas tanpa resep (Contoh: Paracetamol, Vitamin C).
- 🔵 **Obat Bebas Terbatas**: Penjualan bebas terbatas dengan tanda peringatan P.No 1 - P.No 6 (Contoh: CTM, Antasida).
- 🔴 **Obat Keras (K)**: Wajib penyerahan dengan resep dokter atau penyerahan DWA oleh Apoteker (Contoh: Antibiotik Amoxicillin, Asam Mefenamat).
- 🟣 **Psikotropika & Narkotika**: Terpantau ketat regulasi Kemenkes; tercatat otomatis untuk pelaporan SIPNAP.

---

## 7. Penerimaan Faktur Pembelian PBF (Faktur Masuk)

Saat barang obat datang dari Pedagang Besar Farmasi (PBF), staf apotek wajib mencatat faktur di menu **Faktur Masuk (PBF)** (`http://127.0.0.1:8001/purchases/create`):

1. **Pilih Supplier / Distributor PBF** (misal: Kimia Farma Trading, Enseval, Anugrah Argon).
2. **Nomor Faktur & Tanggal Jatuh Tempo Pembayaran**.
3. **Rincian Obat**:
   - Pilih nama obat.
   - Masukkan **Nomor Batch Pabrik** (misal: `BATCH-2026A`).
   - Masukkan **Tanggal Kadaluwarsa / Expired Date** (wajib diisi untuk FEFO).
   - Masukkan Satuan Pembelian (misal: 5 Box) dan Harga Beli Per Box.
4. **Klik Simpan Penerimaan Faktur**:
   - Sistem secara otomatis mengonversikan jumlah box ke satuan dasar (misal: $5 \times 100 = 500$ tablet).
   - Batch baru otomatis tercipta di tabel `product_batches`.
   - Riwayat mutasi stok masuk (`in_purchase`) tersimpan rapi untuk audit.

---

## 8. Database Management (DBM), Backup & Restore

Apotek tidak boleh kehilangan riwayat penjualan dan stok. Menu **Database & Maintenance** (`http://127.0.0.1:8001/settings/database`) menyediakan alat pemeliharaan tingkat lanjut:

### A. Cadangan Database Manual & Otomatis:
- **Jadwal Otomatis**: Setiap malam pukul **23:00**, sistem otomatis membuat dump database MySQL terkompresi `.zip`.
- **Trigger Tutup Shift**: Setiap kasir menutup shift, cadangan instan otomatis tercipta.
- **Cadangan Manual**: Klik tombol **Buat Backup (.zip)** kapan saja untuk membuat arsip langsung.
- **Retensi 30 Hari**: File arsip cadangan yang berusia lebih dari 30 hari akan dibersihkan secara otomatis demi menghemat kapasitas disk.

### B. Protokol Restore Aman (Snapshot Darurat Pra-Restore):
*Sesuai standar operasional keamanan apotek, DILARANG memulihkan database tanpa snapshot darurat.*
1. Pilih salah satu file cadangan dari tabel riwayat, lalu klik **Restore**.
2. Sebelum menimpa database, sistem **secara wajib membuat cadangan darurat (Snapshot Pre-Restore)** terlebih dahulu.
3. Database dipulihkan utuh, dan cache sistem otomatis di-refresh (`optimize:clear`).
4. Jika restore keliru, pengguna dapat langsung mengembalikan data ke file Snapshot Pre-Restore tersebut.

### C. Audit Integritas Stok & Defragmentasi:
- **Tombol Audit Integritas Stok**: Memeriksa apakah penjumlahan seluruh transaksi mutasi stok (`SUM(stock_movements)`) cocok $100\%$ dengan stok fisik batch obat. Jika ada manipulasi data manual di database, sistem langsung mendeteksinya.
- **Tombol Optimasi Tabel**: Menjalankan perintah `OPTIMIZE TABLE` pada MySQL untuk mempercepat kecepatan query dan merapikan indeks fisik di disk.

---

## 9. Biometrik Sidik Jari & Hardware Bridge

Modul Hardware Bridge (FastAPI Port 8765) menjembatani aplikasi kasir berbasis web dengan perangkat keras kasir fisik:

### A. Percetakan Struk Thermal (ESC/POS) & Buka Laci Kasir:
- Mendukung printer thermal ukuran kertas **58mm** dan **80mm** (Koneksi USB / Ethernet / Serial).
- **Silent Printing**: Kasir tidak perlu menekan tombol print di dialog browser; struk terpotong otomatis (*auto-cutter*) dan laci kasir (*cash drawer*) membuka otomatis via sinyal RJ-11 bridge.
- **Fallback Otomatis (Timeout 2 Detik)**: Jika kabel printer terlepas atau bridge tidak aktif, sistem langsung memunculkan jendela pratinjau struk di layar kasir sehingga transaksi pelanggan tidak terganggu.

### B. Otorisasi Biometrik Sidik Jari:
- **Pendaftaran Sidik Jari**: Masuk ke menu **Sidik Jari (PRO)** (`http://127.0.0.1:8001/settings/fingerprint`), pilih nama Apoteker/Supervisor, dan tempelkan jari pada sensor.
- **Otorisasi Instan**: Saat kasir menekan `Ctrl+V` (Void), kasir cukup meminta Supervisor menempelkan jari pada sensor untuk menyetujui pembatalan seketika.
- **Fallback PIN Darurat**: Jika sensor sidik jari sedang tidak terpasang, Supervisor dapat mengetikkan PIN darurat **`123456`**.

---

## 10. Sinkronisasi Cloud & Laporan Regulasi (SIPNAP & Laba Kotor)

### A. Sinkronisasi Hybrid Asinkron (Sync Outbox):
- Setiap kali transaksi kasir selesai, data disimpan ke antrean lokal `sync_outbox`.
- Jika komputer kasir terhubung ke internet dan fitur `CLOUD_SYNC_ENABLED=true`, data otomatis dikirim ke Cloud Hub secara background tanpa memperlambat layar kasir.
- **Jaminan Idempotensi ULID**: Jika koneksi internet terputus-putus dan payload terkirim ganda, Cloud Hub menjamin data tidak akan terduplikasi.

### B. Laporan Laba Kotor & HPP Riil (`/reports/profit`):
- Menampilkan total Omzet Penjualan, Total HPP Modal Obat, Laba Kotor Bersih (*Gross Profit*), dan Margin Keuntungan (%).
- Menampilkan peringkat **10 Produk Penyumbang Keuntungan Terbesar** bagi apotek.

### C. Ekspor Regulasi Resmi SIPNAP Kemenkes RI (`/reports/sipnap`):
- Memilih bulan dan tahun pelaporan.
- Menghasilkan file **CSV resmi SIPNAP** sesuai standar Kementerian Kesehatan RI yang berisi kode SIA, nama obat keras/psikotropika, stok awal, pemasukan PBF, pengeluaran resep, dan stok akhir. File ini dapat langsung diunggah ke portal resmi `sipnap.kemkes.go.id`.

### D. Dashboard Web Monitoring Multi-Cabang untuk Owner (`/admin/dashboard`):
Pemilik Sarana Apotek (PSA) dapat membuka server Cloud Hub dari smartphone atau laptop di luar apotek:
- Memantau total omzet harian dan bulanan seluruh cabang secara real-time.
- Membandingkan performa penjualan antar-cabang.
- Mendapatkan peringatan otomatis obat yang mendekati tanggal kedaluwarsa (< 90 hari) dan stok kritis (&le; 10 unit) di semua cabang apotek.

---

## 11. Troubleshooting & Tanya Jawab (FAQ)

### Q1: Struk tidak tercetak dan laci kasir tidak terbuka otomatis.
- **Solusi**: Periksa apakah service Hardware Bridge sudah berjalan di port 8765. Anda dapat mengklik file `scripts\start-all.bat` untuk memastikannya aktif. Periksa juga kabel USB printer thermal. Jika bridge offline, kasir dapat menggunakan cetak struk browser bawaan yang muncul otomatis.

### Q2: Muncul pesan error "ClockTamperedException: Jam sistem terdeteksi mundur".
- **Solusi**: Waktu pada sistem operasi Windows Anda telah dimundurkan dibanding transaksi terakhir. Buka pengaturan tanggal dan waktu Windows (*Date & Time Settings*), aktifkan fitur **"Set time automatically"**, lalu klik **"Sync now"**.

### Q3: Apakah kasir bisa tetap bertransaksi jika internet kantor mati total?
- **Jawaban**: **BISA 100%!** Sistem Apotek POS Hybrid didesain dengan prinsip *offline-first*. Seluruh transaksi penjualan, pemotongan stok FEFO, buka/tutup shift, dan pencetakan struk berjalan di komputer lokal. Saat internet kembali menyala, transaksi yang tertunda di antrean `sync_outbox` akan terkirim otomatis ke Cloud Hub.

### Q4: Bagaimana cara mengganti komputer kasir jika komputer lama rusak?
- **Solusi**: 
  1. Unduh file cadangan database terakhir (`.zip`) dari komputer lama atau dari link cadangan darurat `/db/emergency-download`.
  2. Buka dashboard Cloud Hub di `http://127.0.0.1:8000/admin/licenses`.
  3. Klik tombol **Reset HWID** pada lisensi apotek yang bersangkutan.
  4. Jalankan aplikasi di komputer baru, masukkan Kunci Lisensi di `/license/activate`, lalu pulihkan database via menu **Database & Maintenance**.

### Q5: Berapa PIN default Otorisasi Supervisor untuk Void?
- **Jawaban**: PIN default darurat adalah **`123456`** (dapat diubah pada konfigurasi `.env` melalui variabel `LICENSE_SUPERVISOR_PIN`).

---
*Dokumentasi Resmi Sistem POS Apotek Hybrid Enterprise — 2026. Hak Cipta Dilindungi.*
