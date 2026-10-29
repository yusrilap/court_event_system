# Court Event System

Aplikasi pengelolaan turnamen padel dan tenis untuk tim penyelenggara lokal di Cianjur. Dibangun menggunakan Laravel 12, Filament 5, dan MySQL/MariaDB.

> **Status: dalam pengembangan aktif.**
> Pengembangan saat ini berfokus pada panel admin dan backend penjadwalan. Halaman peserta, pencatatan skor, dan klasemen belum tersedia.

## Latar Belakang

Panitia turnamen perlu mengelola event, kategori, pasangan, pembagian grup, jalur gugur, dan penggunaan court secara konsisten.

Court Event System dikembangkan untuk menyederhanakan alur kerja tim penyelenggara, sekaligus menjadi dasar pencatatan riwayat turnamen dan prestasi pemain lokal.

Dalam pengembangan selanjutnya, riwayat tersebut akan membantu panitia melakukan kurasi kategori berdasarkan event yang tercatat di aplikasi. Penentuan kategori tetap menjadi keputusan panitia.

## Teknologi

| Komponen | Teknologi |
|---|---|
| Backend | Laravel 12 |
| Panel admin | Filament 5 |
| Bahasa pemrograman | PHP 8.3 |
| Database | MySQL / MariaDB |
| Frontend tooling | Node.js 22, npm, Vite |
| Environment pengembangan | Windows, XAMPP, Visual Studio Code |
| Version control | Git dan GitHub |

## Fitur yang Sudah Diimplementasikan

### Pengelolaan Event

- Data event: nama, cabang olahraga, tanggal, lokasi, dan deskripsi.
- Informasi technical meeting dan peraturan.
- Beberapa kategori pertandingan dalam satu event.
- Pemilihan court sesuai cabang olahraga.
- Pengaturan jam mulai, jeda court, dan waktu istirahat pemain.
- Penguncian perubahan pengaturan waktu setelah jadwal tersimpan.

### Master Data

- Pemain dengan identitas tersimpan di database.
- Lokasi dan court.
- Status aktif lokasi dan court.
- Pasangan tetap yang didaftarkan ke kategori.
- Pemilihan pemain lama atau penambahan pemain baru saat pendaftaran.
- Validasi agar pemain tidak terdaftar dua kali dalam kategori yang sama.

### Fase Grup

- Pengaturan awal untuk 8 atau 16 pasangan.
- Validasi pembagian pasangan secara merata ke grup.
- Penempatan pasangan ke grup oleh panitia.
- Generate pertandingan round-robin satu putaran.
- Pemeriksaan kelengkapan kombinasi pertandingan.
- Penolakan generate ulang jika pertandingan grup sudah tersedia.
- Penguncian penempatan grup setelah pertandingan dibuat.

### Bagan Gugur

- Pembuatan struktur babak sesuai jumlah peserta yang lolos.
- Pengaturan sumber slot dari peringkat grup.
- Pengaturan jalur pemenang dan pihak yang kalah.
- Dukungan final dan perebutan juara 3.
- Validasi kelengkapan slot dan penggunaan sumber.
- Penyusunan urutan pertandingan berdasarkan ketergantungan bagan.

### Backend Penjadwalan

- Penyusunan jadwal pertandingan grup dan gugur.
- Pemilihan slot tersedia pada beberapa court.
- Pemeriksaan bentrok court dan pemain.
- Perhitungan jeda court dan waktu istirahat pemain.
- Pembacaan booking tersimpan yang berkaitan dengan court atau pemain.
- Pemisahan kolom waktu rencana dan waktu aktual.
- Penyimpanan jadwal dalam transaksi database.
- Penguncian bersama untuk menjalankan proses generate secara bergantian.
- Penolakan generate awal jika event sudah memiliki jadwal.

> Backend penjadwalan saat ini diuji melalui Laravel Tinker. Integrasi tampilan dan aksi penjadwalan di panel admin masih dalam pengembangan.

## Alur Penggunaan

Alur yang sedang dibangun:

1. Membuat event dan kategori.
2. Menentukan format pertandingan.
3. Mendaftarkan pasangan.
4. Menempatkan pasangan ke grup.
5. Menentukan sumber slot dan jalur gugur.
6. Membuat pertandingan grup dan memvalidasi bagan.
7. Menghasilkan serta memeriksa rancangan jadwal.
8. Menyimpan jadwal.

Tahap berikutnya adalah publikasi informasi peserta, pencatatan hasil pertandingan, pengesahan klasemen, dan penyelesaian event.

## Pendekatan Teknis

### Pemisahan Logika Bisnis

Logika turnamen ditempatkan dalam service agar tidak seluruhnya berada di halaman atau action Filament.

Contoh service:

| Service | Tanggung jawab |
|---|---|
| `GroupMatchGenerator` | Membuat pertandingan round-robin |
| `GroupMatchReadiness` | Memeriksa kelengkapan pertandingan grup |
| `KnockoutBracketValidator` | Memvalidasi struktur dan sumber slot bagan |
| `KnockoutPotentialPlayers` | Menelusuri calon pemain dari sumber slot gugur |
| `KnockoutScheduleOrder` | Menyusun urutan ketergantungan pertandingan |
| `ScheduleSlotFinder` | Mencari court dan slot waktu yang tersedia |
| `ExistingScheduleBookings` | Membaca booking yang perlu diperhitungkan |
| `EventSchedulePlanner` | Menyusun rancangan jadwal |
| `EventScheduleGenerator` | Menyimpan jadwal dalam transaksi |

### Integritas Data

- Foreign key menjaga keterkaitan data.
- Unique constraint membatasi duplikasi pada relasi tertentu.
- Validasi bisnis memeriksa kapasitas, peserta, grup, dan sumber slot.
- Transaksi digunakan untuk operasi yang harus berhasil secara utuh.
- Row locking digunakan pada proses perubahan dan generate tertentu.

Pengamanan perubahan konfigurasi setelah penjadwalan masih dikembangkan bertahap.

### Waktu dan Penjadwalan

- Input dan tampilan jadwal ditujukan untuk WIB (`Asia/Jakarta`).
- Waktu jadwal disimpan dalam UTC.
- Waktu rencana dipisahkan dari pelaksanaan aktual.
- Pertandingan gugur mempertimbangkan calon pemain sebelum hasil sumber diketahui.

Algoritma penjadwalan menggunakan pendekatan greedy: memilih slot tersedia paling awal sesuai urutan pemrosesan. Pendekatan ini belum menjamin durasi keseluruhan turnamen paling singkat.

## Struktur Utama

```text
app/
├── Filament/
│   └── Resources/       # Panel admin dan relation manager
├── Models/              # Entitas serta relasi Eloquent
└── Services/            # Validasi dan logika turnamen

database/
└── migrations/          # Struktur tabel dan constraint
```

Entitas utama mencakup event, kategori, pemain, pasangan, grup, pertandingan grup, pertandingan gugur, sumber slot, lokasi, court, dan jadwal.

## Menjalankan Secara Lokal

### Prasyarat

- PHP 8.3 dengan ekstensi yang diperlukan Laravel dan Filament, termasuk `intl`.
- Composer.
- Node.js 22 dan npm.
- MySQL atau MariaDB.

Environment pengembangan proyek menggunakan XAMPP. Pastikan perintah `php` di terminal mengarah ke PHP XAMPP.

### Instalasi

```bash
git clone https://github.com/yusrilap/court_event_system.git court-event-system
cd court-event-system

composer install
npm install
```

Salin `.env.example` menjadi `.env`.

Untuk terminal Command Prompt di Windows:

```bat
copy .env.example .env
```

Buat database kosong, kemudian sesuaikan konfigurasi `.env`:

```dotenv
APP_NAME="Court Event System"
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=court_event_system
DB_USERNAME=root
DB_PASSWORD=
```

Sesuaikan port, username, dan password dengan konfigurasi database lokal. Jangan menggunakan database yang sudah berisi data tanpa memeriksa konfigurasi terlebih dahulu.

```bash
php artisan key:generate
php artisan migrate
php artisan make:filament-user
npm run build
php artisan serve
```

Buka:

```text
http://127.0.0.1:8000/admin
```

Login menggunakan akun yang dibuat melalui `make:filament-user`.

### Alternatif Server Lokal di Windows

Jika `php artisan serve` mengalami error saat membaca output server PHP, jalankan dari folder proyek melalui Command Prompt:

```bat
cd public
"C:\xampp\php\php.exe" -S 127.0.0.1:8000 "../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"
```

Untuk penggunaan Apache XAMPP, arahkan `DocumentRoot` ke folder `public` Laravel.

## Validasi Pengembangan

Pemeriksaan pengembangan dilakukan secara bertahap melalui panel admin, pemeriksaan sintaks PHP, dan Laravel Tinker.

Skenario pemeriksaan yang digunakan antara lain:

- 8 pasangan dalam 2 grup menghasilkan 12 pertandingan grup.
- Setiap pasangan menghadapi 3 lawan berbeda dalam grup berisi 4 pasangan.
- Generate ulang tidak menambah pertandingan atau jadwal duplikat.
- Pemindahan pasangan ditolak setelah pertandingan grup dibuat.
- Pemain berbeda dapat bermain bersamaan pada court berbeda.
- Pemain yang sama harus memenuhi waktu istirahat.
- Pertandingan gugur menunggu pertandingan sumber.
- Perubahan pengaturan waktu event ditolak setelah jadwal tersedia.

Daftar ini merupakan skenario validasi pengembangan, bukan klaim cakupan automated testing. Test suite otomatis dan dokumentasi bukti pengujian belum dicantumkan.

## Roadmap

- [ ] Melengkapi pengamanan court dan sumber slot setelah jadwal dibuat.
- [ ] Menampilkan jadwal dan menyediakan aksi generate melalui panel admin.
- [ ] Penyesuaian jadwal ketika pertandingan terlambat.
- [ ] Pencatatan waktu pelaksanaan aktual.
- [ ] Input skor dan perhitungan klasemen.
- [ ] Finalisasi aturan tie-break, WO, pengunduran diri, dan hasil seri.
- [ ] Pengesahan hasil grup dan pengisian pasangan ke bagan gugur.
- [ ] Penyelesaian event dengan konfirmasi rangkuman juara.
- [ ] Penguncian hasil dan audit koreksi.
- [ ] Riwayat keikutsertaan serta prestasi pemain.
- [ ] Dashboard statistik event dan pemain.
- [ ] Halaman peserta yang nyaman digunakan di HP.
- [ ] Automated testing untuk aturan turnamen dan penjadwalan.

## Batasan Saat Ini

- Pengembangan berfokus pada kebutuhan tim penyelenggara lokal.
- Format awal dibatasi pada 8 atau 16 pasangan.
- Alur pertandingan memakai pasangan tetap.
- Perhitungan calon pemain gugur bersifat konservatif dan dapat memperpanjang jadwal, termasuk membuat final dan perebutan juara 3 berlangsung bergantian.
- Riwayat pemain nantinya hanya mencakup event yang tercatat dalam aplikasi.
- Proyek belum dinyatakan siap digunakan untuk operasional turnamen produksi.

## Pengembang

**Yusril Azhar Permana**

GitHub: [github.com/yusrilap](https://github.com/yusrilap)