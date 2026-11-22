# PLAN.md — Blueprint Induk & Aturan AI Agent: Sistem Manajemen & POS Apotek Hybrid

> **DOKUMEN INDUK (SINGLE SOURCE OF TRUTH: PLAN + AGENTS RULES)**
> Seluruh AI coding agent (termasuk Antigravity) WAJIB membaca dokumen ini secara menyeluruh sebelum menginisialisasi proyek, menginstal package, membuat migrasi, atau menulis kode apa pun.

---

## 1. Standar Stack & Lingkungan (Laravel 13 + PHP 8.3+)

* **Backend & Web Framework:** Laravel 13 (`^13.0`) untuk `/apotek-pos-local` dan `/apotek-cloud-hub`.
* **Runtime PHP:** PHP 8.3+ dengan ekstensi wajib aktif: `ext-sodium`, `pdo_mysql`, `zip`, `bcmath`, `mbstring`, `openssl`.
* **Database:** MySQL 8.0+ / MariaDB 10.6+ (Engine `InnoDB`, charset `utf8mb4`, collation `utf8mb4_unicode_ci`).
* **Frontend POS Lokal:** Livewire (versi terbaru yang mendukung `illuminate/support: ^13.0`), Alpine.js, Tailwind CSS, Vite (**100% asset lokal**, dilarang menggunakan CDN).
* **API Auth Cloud:** Laravel Sanctum (versi kompatibel Laravel 13).
* **Hardware Service:** Python 3.11+ dengan **FastAPI + Uvicorn + WebSocket + `python-escpos`** di `http://127.0.0.1:8765`.
* **Shell Desktop:** Electron Wrapper / Chromium Kiosk Mode di Windows 11.

---

## 2. Peta Workspace & Larangan Keras (HARD PROHIBITIONS)

### Peta Monorepo
```text
/
├── PLAN.md                 # Dokumen induk ini
├── apotek-pos-local/       # Laravel 13 + MySQL Lokal (POS Kasir, FEFO, DBM, Offline License, Sync Worker)
├── apotek-cloud-hub/       # Laravel 13 + MySQL Cloud (License Server Ed25519, Sync API, Web Monitoring)
└── hardware-bridge/        # Python FastAPI Local Service (Thermal ESC/POS, Cash Drawer, Fingerprint)
```

### 12 Larangan Keras (Pelanggaran = Gagal Kritis)
1. **DILARANG downgrade Laravel atau memakai struktur versi lama.** Wajib Laravel `^13.0` dengan `bootstrap/app.php` untuk registrasi middleware, exception, dan scheduler (BUKAN `app/Http/Kernel.php`). Sebelum menginstal package Composer baru, verifikasi dukungan `illuminate/support: ^13.0`.
2. **DILARANG menginstal `mike42/escpos-php` di Laravel.** Seluruh cetak struk Thermal ESC/POS (58mm/80mm) dan pembukaan Cash Drawer WAJIB melalui `/hardware-bridge` (`http://127.0.0.1:8765`).
3. **DILARANG menggunakan `auto_increment` (`$table->id()`)** pada tabel master dan transaksional. WAJIB gunakan `$table->ulid('id')->primary()` dan trait `Illuminate\Database\Eloquent\Concerns\HasUlids` pada Model agar sinkronisasi multi-cabang bebas bentrok ID.
4. **DILARANG mengubah `product_batches.stock_base_unit` secara langsung** di Controller atau Livewire. Semua mutasi stok WAJIB lewat `StockFefoService` / `StockMutationService`, dibungkus `DB::transaction()`, memakai `lockForUpdate()`, dan wajib mencatat pasangan mutasi di `stock_movements`.
5. **DILARANG menaruh `LICENSE_PRIVATE_KEY` di `/apotek-pos-local`** dalam bentuk apa pun (termasuk `.env.example`, seeder, atau test produksi). Private Key HANYA boleh ada di `/apotek-cloud-hub`; aplikasi lokal HANYA menyimpan `LICENSE_PUBLIC_KEY`.
6. **DILARANG memanggil API Cloud secara sinkron (blocking)** di dalam alur transaksi POS. Transaksi kasir harus selesai 100% di MySQL lokal; sinkronisasi hanya berjalan lewat Background Job saat `CLOUD_SYNC_ENABLED=true` DAN fitur `cloud_sync_push` aktif pada `license.lic`.
7. **DILARANG menggunakan perintah `wmic`** (sudah dihapus di Windows 11 modern). Ambil Hardware ID menggunakan PowerShell `Get-CimInstance` via `Symfony\Component\Process\Process`.
8. **DILARANG menggunakan `shell_exec()`, `exec()`, atau string command mentah** untuk `mysqldump`, `mysql`, maupun PowerShell. WAJIB pakai `Symfony\Component\Process\Process` dengan **array argumen** agar aman dari command injection dan path Windows yang mengandung spasi (contoh: `C:\laragon\bin\mysql\...`).
9. **DILARANG menghitung atau menyimpan nilai uang dengan `float` mentah.** Semua kolom uang di MySQL wajib `DECIMAL(15, 2)`. Di PHP, gunakan `bcmath` atau integer presisi untuk subtotal, diskon, tuslah, embalase, pajak, dan kembalian.
10. **DILARANG menjalankan Restore Database tanpa membuat *pre-restore emergency snapshot* terlebih dahulu.** Jika snapshot darurat gagal dibuat, proses restore WAJIB dibatalkan.
11. **DILARANG membiarkan `hardware-bridge` offline menghambat transaksi kasir.** Setiap request ke `127.0.0.1:8765` wajib memiliki timeout maksimal **2 detik** dan otomatis jatuh ke fallback (Fingerprint -> Modal PIN Supervisor; Thermal Printer -> Preview Struk HTML / Cetak Browser).
12. **DILARANG memuat CSS, JS, Font, atau Icon dari CDN eksternal** di `/apotek-pos-local`. Semua aset wajib di-bundle lokal melalui Vite (`npm run build`).

---

## 3. Konvensi Coding (Laravel 13, PHP 8.3+, Livewire & Alpine.js)

### A. Standar PHP & Struktur Direktori
* Wajib tulis `declare(strict_types=1);` di awal setiap file PHP.
* Terapkan **Thin Controller / Thin Livewire, Fat Service**.
* **Penamaan Bahasa:** Tabel, kolom, class, method, route, variabel, dan komentar teknis dalam **Bahasa Inggris**. Teks antarmuka (label, tombol, toast, validasi, struk) dalam **Bahasa Indonesia** yang ringkas untuk kasir apotek.
* Wajib gunakan **Backed Enums** PHP 8.3+ untuk semua kolom status dan kategori (`DrugClass`, `PaymentMethod`, `SyncStatus`, `UserRole`, `StockMovementType`, `LicensePlan`, `LicenseFeature`).
* Cegah N+1 query di seluruh halaman daftar dan laporan dengan eager loading (`with()`).

```text
apotek-pos-local/app/
├── Actions/          # CompletePosTransactionAction, VoidTransactionAction, CloseShiftAction
├── DTOs/             # Readonly DTOs (PosItemData, ReceiptPayloadData, LicensePayloadData)
├── Enums/            # Backed Enums (LicensePlan, LicenseFeature, DrugClass, PaymentMethod, dll.)
├── Exceptions/       # InsufficientStockException, LicenseInvalidException, ClockTamperedException
├── Http/Middleware/  # VerifyLicenseMiddleware, EnsureLicenseFeatureMiddleware, EnsureShiftIsOpenMiddleware, EnsureTerminalQuotaMiddleware
├── Jobs/             # SyncPushJob, SyncPullJob, RunScheduledBackupJob
├── Livewire/         # Pos/, Inventory/, Purchases/, Reports/, Settings/, Auth/
├── Models/           # Eloquent models dengan HasUlids
└── Services/
    ├── Licensing/    # HwidService, LicenseService, LicenseEntitlementService
    ├── Inventory/    # StockFefoService, StockMutationService, StockIntegrityAuditService
    ├── Printing/     # ReceiptPayloadBuilderService
    ├── Database/     # DatabaseBackupService, DatabaseRestoreService
    └── Sync/         # CloudSyncService
```

### B. Standar Kecepatan POS (Target Respons Di Bawah 50ms)
* **Alpine.js menangani:** Global keyboard shortcuts (`F1`–`F10`, `Ctrl+V`, `ESC` dengan `e.preventDefault()`), *permanent auto-focus* ke `#barcode-input`, buka/tutup modal cepat, hitung kembalian real-time saat mengetik uang tunai, serta fetch/WebSocket ke `/hardware-bridge` (`http://127.0.0.1:8765`).
* **Livewire menangani:** Pencarian `FULLTEXT` obat di MySQL, validasi stok FEFO, manajemen keranjang tersimpan, Hold/Recall transaksi, dan checkout final (memancarkan browser event `receipt-ready` berisi JSON struk agar langsung dikirim Alpine.js ke `/hardware-bridge`).
* **Aturan Binding:**
  * Input barcode: WAJIB pakai `wire:keydown.enter="scanBarcode"` (JANGAN `.live`).
  * Input pencarian obat (`F1`): WAJIB pakai `wire:model.live.debounce.200ms`, minimal 2 karakter, hasil maksimal `LIMIT 25`.
  * Gunakan atribut `#[Computed]` untuk kalkulasi ringkasan keranjang.

---

## 4. Desain Skema Database MySQL 8.0+ (InnoDB)

### A. Database Lokal (`apotek_local`)
Seluruh tabel transaksional wajib memiliki `branch_id`, `terminal_id`, `sync_status` (`ENUM('pending','synced','failed')`), dan `synced_at`.

1. **`system_meta`**: `key` (VARCHAR PK), `value` (TEXT), `updated_at` — menyimpan `last_seen_at` terenkripsi (anti-manipulasi jam), timestamp sync terakhir, dan profil apotek (nama, alamat, telp, nomor SIA, nama APA, SIPA, footer struk).
2. **`active_terminals`**: `terminal_id` (VARCHAR PK), `hostname`, `ip_address`, `user_id (ULID nullable)`, `last_ping_at (TIMESTAMP)` — untuk penegakan batas `limits.max_terminals` dari lisensi (aktif jika ping dalam 15 menit terakhir).
3. **`users`**: `id (ULID)`, `branch_id`, `name`, `username (UNIQUE)`, `password`, `role` (`owner`, `apoteker`, `kasir`), `pin_hash`, `fingerprint_template (LONGTEXT nullable)`, `is_active`.
4. **`shifts`**: `id (ULID)`, `branch_id`, `terminal_id`, `user_id`, `opened_at`, `closed_at`, `opening_cash (DECIMAL 15,2)`, `expected_cash`, `actual_cash`, `cash_difference`, `status` (`open`, `closed`), `sync_status`, `synced_at`.
5. **`products`**: `id (ULID)`, `sku (UNIQUE)`, `barcode (INDEX)`, `name`, `generic_name` (zat aktif), `category`, `drug_class` (`bebas`, `bebas_terbatas`, `keras`, `psikotropika`, `narkotika`, `prekursor`, `oot`, `alkes`), `rack_location`, `base_unit_name` (Tablet/Kapsul/Botol), `min_stock_base (INT)`, `is_active`, `updated_at`.
   * **Wajib Index:** `FULLTEXT INDEX idx_product_search (name, generic_name, sku)`.
6. **`product_units`**: `id (ULID)`, `product_id (FK)`, `unit_name` (Box/Strip/Tablet), `conversion_factor (INT)` (Box=100, Strip=10, Tablet=1), `barcode_unit (INDEX nullable)`, `sell_price (DECIMAL 15,2)`, `is_default_pos (BOOLEAN)`.
7. **`product_batches`**: `id (ULID)`, `branch_id`, `product_id (FK)`, `batch_number`, `expired_date (DATE, INDEX)`, `stock_base_unit (INT)`, `buy_price_base (DECIMAL 15,2)`, `supplier_id (nullable)`, `received_at`.
   * **Wajib Composite Index:** `INDEX idx_fefo (product_id, expired_date, stock_base_unit)`.
8. **`stock_movements`**: `id (ULID)`, `branch_id`, `product_id`, `batch_id`, `type` (`in_purchase`, `out_sale`, `in_return`, `out_return`, `adjustment_opname`, `expired_writeoff`), `qty_change_base (INT +/-)`, `stock_after_base (INT)`, `reference_type`, `reference_id`, `user_id`, `notes`, `created_at`, `sync_status`.
9. **`transactions`**: `id (ULID)`, `invoice_no (UNIQUE, format INV-BR01-YYYYMMDD-0001)`, `branch_id`, `terminal_id`, `shift_id`, `cashier_id`, `type` (`otc`, `resep`), `patient_name`, `doctor_name`, `subtotal`, `tuslah_embalase_total`, `discount_total`, `tax_total`, `grand_total`, `payment_method` (`cash`, `qris`, `transfer`, `debit`), `paid_amount`, `change_amount`, `status` (`completed`, `voided`, `returned`), `authorized_by_user_id (nullable)`, `created_at`, `sync_status`, `synced_at`.
10. **`transaction_items`**: `id (ULID)`, `transaction_id (FK)`, `product_id`, `batch_id`, `product_unit_id`, `is_racikan (BOOLEAN)`, `racikan_group_name`, `qty_input (DECIMAL 10,2)`, `conversion_factor (INT)`, `qty_base_deducted (INT)`, `unit_price (DECIMAL 15,2)`, `hpp_base_snapshot (DECIMAL 15,2)`, `discount_item`, `subtotal`.
11. **`suppliers`, `purchases`, `purchase_items`**: Pencatatan faktur masuk PBF, nomor batch, expired date, update HPP, dan monitoring jatuh tempo hutang PBF.
12. **`sync_outbox`**: `id (ULID)`, `entity_type`, `entity_id`, `action`, `payload (JSON)`, `status`, `attempts`, `last_error`, `created_at`.
13. **`db_backup_logs`**: `id (ULID)`, `filename`, `file_size_bytes`, `storage_path`, `trigger_type` (`scheduled`, `shift_close`, `manual`, `pre_restore`), `status`, `created_at`.

### B. Database Cloud (`apotek_cloud`)
Menyimpan tabel `branches`, mirror agregasi cabang (`products`, `product_batches`, `transactions`, `transaction_items`, `shifts`), serta tabel **Server Lisensi**:

1. **`licenses`**:
   * `id (ULID)`, `license_key (UNIQUE, format APT-XXXX-XXXX-XXXX)`, `customer_name`, `pharmacy_name`, `branch_code`, `plan_type` (`STANDARD_LOCAL`, `PRO_CLOUD_SYNC`, `ENTERPRISE`), `billing_type` (`lifetime`, `subscription`), `limits_json (JSON)`, `features_json (JSON)`, `status` (`active`, `suspended`, `revoked`, `expired`), `bound_hwid (VARCHAR 64 nullable)`, `bound_hostname`, `max_reset_count (INT default 2)`, `reset_used (INT default 0)`, `activated_at`, `expires_at (nullable untuk lifetime)`, `grace_days (INT default 14)`, `heartbeat_interval_days (INT default 7)`, `last_heartbeat_at`.
2. **`license_audit_logs`**:
   * `id (ULID)`, `license_id`, `action` (`activated`, `refreshed`, `heartbeat`, `rejected_hwid_mismatch`, `rejected_revoked`, `hwid_reset`), `hwid`, `ip_address`, `app_version`, `notes`, `created_at`.

---

## 5. Spesifikasi Teknis Fitur Kritis

### A. Sistem Lisensi: Online Activation + Offline Ed25519 + Tier Entitlements
Internet **hanya diperlukan saat aktivasi baru** (atau saat refresh/upgrade/reset PC). Operasional harian berjalan **100% offline**.

1. **Keypair & Kriptografi:**
   * Command `php artisan license:generate-keys` di `/apotek-cloud-hub` memakai `sodium_crypto_sign_keypair()`.
   * `LICENSE_PRIVATE_KEY` disimpan rahasia HANYA di `.env` Cloud Hub; `LICENSE_PUBLIC_KEY` disimpan di `config/license.php` POS Lokal.
   * Cloud Hub menandatangani string `payload_b64` mentah dengan `sodium_crypto_sign_detached()` dan mengembalikan file sertifikat berformat:
     ```json
     { "version": 1, "algorithm": "Ed25519", "payload_b64": "...", "signature_b64": "..." }
     ```
   * Aplikasi lokal menyimpannya ke `storage/app/private/license.lic`.
2. **Struktur Payload (`payload_b64` decoded) & Preset 3 Tier:**
   Payload wajib memuat `schema_version`, `license_key`, `pharmacy_name`, `branch_code`, `hwid`, `plan`, `billing_type`, `issued_at`, `expires_at`, `grace_days`, `heartbeat_interval_days`, `limits`, dan `features`:
   * **`STANDARD_LOCAL`:** `billing_type="lifetime"` (default), `expires_at=null`, `heartbeat_interval_days=0`, `limits: { max_terminals: 2, max_branches: 1, max_users: 5 }`, seluruh `features` bernilai `false` (hanya fitur inti POS, FEFO, Resep/Racikan, Shift, Thermal Print, Backup/Restore Lokal & Audit Stok yang aktif).
   * **`PRO_CLOUD_SYNC`:** `billing_type="subscription"`, `grace_days=14`, `heartbeat_interval_days=7`, `limits: { max_terminals: 5, max_branches: 1, max_users: 15 }`, `features` aktif: `fingerprint_auth`, `label_printing`, `offsite_backup`, `cloud_sync_push`, `sipnap_export`, `profit_analytics` (sisanya `false`).
   * **`ENTERPRISE`:** `billing_type="subscription"`, `limits: { max_terminals: null, max_branches: 10, max_users: null }`, seluruh 9 `features` bernilai `true` (termasuk `cloud_sync_pull`, `multi_branch_ops`, `external_api`).
   * Panel admin Cloud Hub mengizinkan override per fitur pada `features_json` (untuk add-on satuan) sebelum penandatanganan sertifikat.
3. **HWID Generator Windows (`HwidService`):**
   * Ambil Motherboard UUID (`Win32_ComputerSystemProduct.UUID`) + Disk Serial (`Win32_DiskDrive.SerialNumber`) via PowerShell `Get-CimInstance` (`Process` array).
   * Gabung dengan salt dan hash SHA-256. Dukung `LICENSE_HWID_MOCK` khusus saat `APP_ENV=testing`.
4. **Verifikasi Offline, Anti-Clock Tampering & Entitlement Enforcement:**
   * `VerifyLicenseMiddleware` memvalidasi: keberadaan `license.lic`, kecocokan signature Ed25519 dengan `LICENSE_PUBLIC_KEY`, kecocokan `payload.hwid === currentMachineHwid()`, dan validitas tanggal (`now <= expires_at + grace_days`).
   * **Anti-Clock Tampering:** Simpan `last_seen_at` terenkripsi (`Crypt::encryptString()`) di `system_meta`. Jika jam Windows mundur lebih dari 5 menit dibanding `last_seen_at`, lempar `ClockTamperedException`.
   * **Entitlement & Soft-Lock:** Daftarkan alias middleware `license.valid` dan `license.feature` di `bootstrap/app.php`. Gunakan `LicenseEntitlementService` untuk mengecek fitur pada route, komponen Livewire, dan Background Job (`SyncPushJob`/`SyncPullJob`). Di UI sidebar, tampilkan menu terkunci dengan lencana `PRO`/`ENTERPRISE` dan modal info upgrade.
   * **Kuota Terminal:** `EnsureTerminalQuotaMiddleware` mengecek tabel `active_terminals` terhadap `limits.max_terminals`.
   * **Graceful Expiration:** H-14 tampilkan banner pengingat; saat masa tenggang (`grace_days`) POS tetap berjalan dengan banner peringatan dan sync dijeda; lewat masa tenggang, kunci transaksi ke `/license/activate` tetapi **tetap buka akses unduh Backup Database (`.zip`) bagi Owner**.

### B. Mesin POS Kasir "Alfamart-Style" & Algoritma FEFO
* **Keyboard Shortcuts:** `F1` (Modal Cari Obat/Zat Aktif), `F2` (Ubah Qty), `F3` (Ubah Satuan Box/Strip/Tablet), `F4` (Mode Resep & Racikan + Tuslah/Embalase), `F8` (Hold Transaksi), `F9` (Recall Hold), `F10` (Modal Pembayaran), `Ctrl+V` (Void Item — wajib otorisasi Fingerprint/PIN Supervisor), `ESC` (Tutup modal & kembalikan fokus ke `#barcode-input`).
* **Algoritma FEFO (`StockFefoService`):**
  * Hitung `needed_base = qty_input * conversion_factor`.
  * Query `product_batches` where `product_id`, `stock_base_unit > 0`, `expired_date > today()`, urut `expired_date ASC` dengan `lockForUpdate()`.
  * Potong berurutan dari batch paling dekat kedaluwarsa; pecah otomatis menjadi beberapa baris `transaction_items` jika melintasi lebih dari 1 batch (masing-masing menyimpan `hpp_base_snapshot` sendiri).
  * Catat setiap mutasi ke `stock_movements`. Jika total stok kurang, rollback dan lempar `InsufficientStockException`.

### C. Arsitektur `/hardware-bridge` (Jalur Utama Printer Thermal & Fingerprint)
Berjalan lokal di setiap PC kasir pada `http://127.0.0.1:8765` (CORS khusus origin POS lokal + header `X-Bridge-Token`).

* **Health Check:** `GET /api/v1/health` mengembalikan `{ status, printer_ready, fingerprint_ready, mock_mode }` untuk indikator status perangkat di header POS.
* **Modul Printer Thermal ESC/POS & Cash Drawer (`python-escpos`):**
  * Endpoint: `POST /api/v1/print/receipt`, `POST /api/v1/print/test`, `POST /api/v1/drawer/open`.
  * Driver via `.env` bridge: `win32raw` (nama printer Windows), `usb`, `network` (IP LAN), `serial`, dan `mock` (menulis preview teks struk 58mm/80mm ke folder log).
  * Menerima JSON terstruktur dari `ReceiptPayloadBuilderService` (header SIA/APA apotek, nomor nota, kasir, item reguler, grup obat racikan, tuslah/embalase, total, pembayaran, `open_drawer`, `paper_width: 58 | 80`).
  * Jika bridge timeout (2 detik) atau mati, Alpine.js menampilkan toast non-blocking + tombol **"Cetak via Browser / Lihat Struk"**.
* **Modul Fingerprint Scanner:**
  * Endpoint: `GET /api/v1/fingerprint/status`, `POST /api/v1/fingerprint/enroll`, `POST /api/v1/fingerprint/verify`, serta WebSocket `ws://127.0.0.1:8765/ws/fingerprint`.
  * Pola **Driver Adapter**: `BaseFingerprintDriver`, `MockFingerprintDriver` (dev/test), dan kerangka `WindowsSdkFingerprintDriver` (siap sambung SDK DigitalPersona U.are.U 4500 / scanner USB Windows lain).
  * Hanya aktif di POS jika `LicenseEntitlementService::hasFeature(FINGERPRINT_AUTH)` bernilai `true`; jika fitur terkunci atau bridge mati, otomatis gunakan fallback **Modal PIN Supervisor**.

### D. Modul Database Management (DBM) Bawaan (MySQL)
Menu **Pengaturan -> Database & Maintenance** (khusus `owner`):
1. **Auto-Backup (`DatabaseBackupService`):** Menjalankan `mysqldump` via `Process` array (`MYSQL_DUMP_PATH` di `.env`, kompatibel Laragon/MySQL Windows), kompres `.zip` ke `BACKUP_LOCAL_PATH`, catat di `db_backup_logs`, retensi otomatis 30 hari. Jalan otomatis lewat Scheduler **jam 23:00** dan event **`ShiftClosed`**.
2. **Safe One-Click Restore (`DatabaseRestoreService`):** Upload `.zip`/`.sql` -> WAJIB buat *emergency pre-restore backup* -> `php artisan down` -> jalankan restore `mysql` via `Process` -> `php artisan optimize:clear` -> verifikasi tabel inti -> `php artisan up`.
3. **Audit & Maintenance (`StockIntegrityAuditService`):** Cek kecocokan `SUM(stock_movements.qty_change_base)` terhadap `product_batches.stock_base_unit` per batch, plus tombol `OPTIMIZE TABLE` dan pruning log sync lebih dari 90 hari.

### E. Engine Sinkronisasi Hybrid (Localhost-First -> Multi-Cabang)
* Default `.env` lokal: `CLOUD_SYNC_ENABLED=false`.
* Aktif HANYA jika `CLOUD_SYNC_ENABLED=true` DAN lisensi memiliki hak `cloud_sync_push` (atau `cloud_sync_pull` untuk tarik master pusat).
* `SyncPushJob` (tiap 2 menit): kirim maksimal 50 record `pending` dari `sync_outbox` ke `POST /api/v1/sync/push` secara *idempotent* (`updateOrCreate` by ULID) dengan Sanctum Bearer Token + `X-License-Key`.
* `SyncPullJob` (tiap 10 menit): tarik pembaruan master obat & harga terpusat dari `GET /api/v1/sync/pull?since=...`.

---

## 6. Roadmap Implementasi Bertahap & Protokol Eksekusi

### Protokol Wajib Setiap Fase
1. **Rencanakan Dulu:** Sebutkan daftar file yang akan dibuat/diubah sebelum menulis kode.
2. **Satu Fase per Perintah:** Kerjakan hanya fase yang diminta user; jangan melompat ke fase berikutnya.
3. **Automated Tests Wajib:** Tulis Pest/PHPUnit untuk Laravel dan Pytest untuk `/hardware-bridge`.
4. **Verifikasi Sebelum Lapor:** Jalankan `php artisan test` (wajib 0 failed) dan `npm run build` di `/apotek-pos-local` (wajib 0 error).
5. **Update Progress:** Centang (`[x]`) item yang selesai di `PLAN.md`, lalu laporkan: (a) file dibuat/diubah, (b) ringkasan hasil test, (c) panduan uji manual, (d) konfirmasi siap ke fase berikutnya.

---

### Fase 0: Setup Monorepo Laravel 13, MySQL & Scaffold Hardware Bridge
- [x] Inisialisasi `/apotek-pos-local` dengan `composer create-project laravel/laravel:^13.0 apotek-pos-local`, instal Livewire (kompatibel Laravel 13), Tailwind CSS, Vite.
- [x] Inisialisasi `/apotek-cloud-hub` dengan `composer create-project laravel/laravel:^13.0 apotek-cloud-hub`, instal Laravel Sanctum.
- [x] Verifikasi `php -v` (minimal 8.3+), `php artisan --version` (wajib 13.x), serta ekstensi `ext-sodium`, `pdo_mysql`, `zip`, `bcmath`.
- [x] Buat scaffold `/hardware-bridge` (Python FastAPI + Uvicorn + `pytest` + `config.example.env` + endpoint `GET /api/v1/health`).
- [x] Siapkan `.env.example` lengkap di ketiga folder (`BRANCH_ID`, `TERMINAL_ID`, `CLOUD_SYNC_ENABLED=false`, `HARDWARE_BRIDGE_URL=http://127.0.0.1:8765`, `HARDWARE_MOCK_MODE=true`, `MYSQL_DUMP_PATH`, `BACKUP_LOCAL_PATH`, `LICENSE_PUBLIC_KEY`).
- **DoD:** Kedua aplikasi Laravel 13 berjalan dengan MySQL lokal, `npm run build` sukses, dan `GET http://127.0.0.1:8765/api/v1/health` merespons `200 OK`.

### Fase 1: Sistem Lisensi Ed25519 & Tier Entitlements (Cloud Hub + POS Lokal)
- [x] Di `/apotek-cloud-hub`: Migrasi `licenses` (dengan `plan_type`, `billing_type`, `limits_json`, `features_json`) & `license_audit_logs` (ULID); command `license:generate-keys`; panel admin CRUD generate license key dengan preset 3 tier (`STANDARD_LOCAL`, `PRO_CLOUD_SYNC`, `ENTERPRISE`), override fitur, dan tombol **Reset HWID**; API `POST /api/v1/license/activate`, `POST /api/v1/license/refresh`, dan `POST /api/v1/license/heartbeat`.
- [x] Di `/apotek-pos-local`: Migrasi `system_meta` & `active_terminals`; Backed Enums `LicensePlan` & `LicenseFeature`; `HwidService` (PowerShell `Get-CimInstance` + `LICENSE_HWID_MOCK`), `LicenseService` (Ed25519 offline + anti-clock tampering `last_seen_at` + grace period), `LicenseEntitlementService`; registrasi middleware `license.valid`, `license.feature`, dan `terminal.quota` di `bootstrap/app.php`; UI `/license/activate` + info status lisensi.
- [x] Feature Tests: aktivasi sukses, tolak HWID berbeda, tolak `license.lic` dimodifikasi 1 karakter, tolak jam mundur lebih dari 5 menit, verifikasi fitur terkunci sesuai tier `STANDARD_LOCAL` vs `PRO_CLOUD_SYNC`, batas `max_terminals`, dan verifikasi sukses saat Cloud Hub mati.
- **DoD:** Aplikasi lokal terkunci sebelum aktivasi, aktif via Cloud Hub dengan payload tier lengkap, berjalan 100% offline, dan seluruh test Fase 1 hijau.

### Fase 2: Master Farmasi, Multi-Satuan & Inventori FEFO
- [x] Migrasi ULID MySQL di `/apotek-pos-local`: `users`, `products` (dengan `FULLTEXT` index), `product_units`, `product_batches` (dengan composite index FEFO), `stock_movements`, `suppliers`, `purchases`, `purchase_items`.
- [x] CRUD Master Obat dengan Multi-Satuan bertingkat (Box -> Strip -> Tablet) dan harga jual masing-masing.
- [x] Modul Penerimaan Faktur Pembelian PBF (input Batch Number, Expired Date, konversi qty ke `base_unit`, update HPP, catat `stock_movements`, status hutang jatuh tempo).
- [x] `StockFefoService` & `StockMutationService` + Automated Tests lengkap (1 batch cukup, split otomatis lintas 2 batch, lewati batch expired, rollback saat stok kurang, total mutasi sama dengan stok batch).
- [x] Seeder 5.000 dummy produk obat untuk benchmark query `FULLTEXT`.
- **DoD:** Semua test FEFO hijau; pencarian 5.000 obat di MySQL selesai di bawah 50ms.

### Fase 3: Mesin POS Kasir "Alfamart-Style" & Silent Print via Hardware Bridge
- [x] Manajemen Shift Kasir (Buka Shift + Modal Awal, Kas Masuk/Keluar, Tutup Shift + Blind Closing) dan `EnsureShiftIsOpenMiddleware`.
- [x] Antarmuka POS Kasir layar penuh (Livewire + Alpine.js) dengan *permanent auto-focus* barcode dan seluruh shortcut (`F1`, `F2`, `F3`, `F4`, `F8`, `F9`, `F10`, `Ctrl+V`, `ESC`).
- [x] Transaksi OTC multi-satuan, Resep Dokter, Obat Racikan + Tuslah/Embalase, Hold/Recall, dan Void dengan otorisasi PIN Supervisor.
- [x] Di `/hardware-bridge`: Implementasi `POST /api/v1/print/receipt`, `POST /api/v1/print/test`, `POST /api/v1/drawer/open` memakai `python-escpos` (layout 58mm & 80mm) + `MockPrinterDriver` + Pytest.
- [x] Di `/apotek-pos-local`: `ReceiptPayloadBuilderService`, integrasi Alpine.js ke bridge (timeout 2 detik + fallback preview struk HTML), dan indikator status bridge di header POS.
- **DoD:** Transaksi multi-item + racikan selesai tanpa mouse, stok terpotong FEFO, dan struk tercetak/ter-render otomatis via `/hardware-bridge` (serta fallback berjalan mulus saat bridge dimatikan).

### Fase 4: Modul Database Management (DBM) Bawaan MySQL
- [x] `DatabaseBackupService` (`mysqldump` via `Process` array + kompresi `.zip` + retensi 30 hari + log `db_backup_logs`).
- [x] Jadwal auto-backup harian jam 23:00 di `routes/console.php` / `bootstrap/app.php` dan trigger otomatis pada event `ShiftClosed`.
- [x] `DatabaseRestoreService` dengan mekanisme wajib *pre-restore emergency snapshot* + maintenance mode.
- [x] `StockIntegrityAuditService` (cek kecocokan `stock_movements` vs `product_batches`) + aksi `OPTIMIZE TABLE` & pruning log sync lama.
- [x] Halaman UI **Pengaturan -> Database & Maintenance** khusus Owner (tetap dapat diakses untuk unduh backup saat lisensi expired) + Feature Tests.
- **DoD:** Backup manual & otomatis menghasilkan `.zip` valid; simulasi hapus data lalu One-Click Restore mengembalikan database secara utuh.

### Fase 5: Integrasi Fingerprint & Perangkat Eksternal via Hardware Bridge
- [x] Di `/hardware-bridge`: Modul Fingerprint dengan pola **Driver Adapter** (`BaseFingerprintDriver`, `MockFingerprintDriver`, kerangka `WindowsSdkFingerprintDriver`) pada `POST /api/v1/fingerprint/enroll`, `POST /api/v1/fingerprint/verify`, dan WebSocket `ws://127.0.0.1:8765/ws/fingerprint` + Pytest.
- [x] Di `/apotek-pos-local`: Halaman pendaftaran sidik jari user/supervisor (diproteksi `license.feature:fingerprint_auth`, soft-lock di `STANDARD_LOCAL`).
- [x] Integrasi Alpine.js di Buka Shift dan Modal Void/Diskon POS agar menerima verifikasi Fingerprint real-time dari bridge, dengan fallback instan ke PIN Supervisor.
- **DoD:** Enroll dan verifikasi fingerprint berjalan via bridge (`MockFingerprintDriver`), terkunci rapi di tier `STANDARD_LOCAL`, dan fallback PIN bekerja saat bridge offline.

### Fase 6: Engine Sinkronisasi Hybrid, Web Monitoring Multi-Cabang & Packaging
- [x] Di `/apotek-cloud-hub`: Endpoint `POST /api/v1/sync/push` (idempotent by ULID) & `GET /api/v1/sync/pull` terproteksi Sanctum + validasi status Lisensi & hak tier aktif.
- [x] Di `/apotek-pos-local`: `SyncPushJob` & `SyncPullJob` (aktif hanya jika `CLOUD_SYNC_ENABLED=true` DAN fitur `cloud_sync_push`/`cloud_sync_pull` aktif di `license.lic`), retry backoff, halaman monitor `sync_outbox`, serta laporan `PRO` (`profit_analytics` & `sipnap_export`) terproteksi middleware `license.feature`.
- [x] Di `/apotek-cloud-hub`: Dashboard Web Monitoring Owner (omzet real-time per cabang, perbandingan cabang, alert obat mendekati ED & stok kritis semua cabang, laporan laba kotor berdasarkan `hpp_base_snapshot`).
- [x] Launcher desktop Windows (Electron Wrapper / Chromium Kiosk Mode + script auto-start `/hardware-bridge` di background).
- **DoD:** Simulasi 2 instance lokal (`BRANCH_ID=1` dan `BRANCH_ID=2`) transaksi offline lalu sync ke Cloud Hub masuk tanpa bentrok ULID dan tanpa duplikasi saat payload dikirim ulang.