```
SocialAutopostEngine/
├── app/
│   ├── Dispatcher/
│   │   ├── DispatcherInterface.php
│   │   ├── Telegram.php
│   │   ├── Instagram.php
│   │   ├── Twitter.php
│   │   └── Facebook.php
│   ├── Helpers/
│   │   ├── Config.php
│   │   ├── Database.php
│   │   └── Logger.php
│   ├── Models/
│   │   ├── PlatformAccount.php
│   │   ├── SocialPost.php
│   │   └── SocialQueue.php
│   ├── Worker/
│   │   ├── QueueWorker.php
│   │   └── MiniWorker.php
│   └── bootstrap.php
├── config/
│   └── db.php
├── public/
│   ├── index.php
│   ├── admin/
│   │   ├── index.php
│   │   └── assets/
│   │       ├── css/
│   │       └── js/
│   ├── api/
│   │   └── autopost/
│   │       ├── queue.php
│   │       └── posts.php
│   └── assets/
│       └── (gambar/js front-end publik)
├── storage/
│   ├── logs/
│   │   └── worker.log
│   └── cache/
├── worker.php
├── mini_worker.php
├── why_skip.php
├── seed.php
├── composer.json
├── composer.lock
├── vendor/
└── README.md
```

### Penjelasan Singkat

- **app/** – kode utama.
  - **Dispatcher/** – kelas untuk setiap platform yang menerima data dari worker dan memanggil API platform; semuanya mengimplementasi `DispatcherInterface`.
  - **Helpers/** – utilitas umum:
    - `Config.php` mengambil konfigurasi global.
    - `Database.php` koneksi MySQL.
    - `Logger.php` pencatatan proses.
  - **Models/** – representasi tabel database (`social_queue`, `social_posts`, `platform_accounts`).
  - **Worker/** – logika pekerja:
    - `QueueWorker.php` loop pemrosesan antrian.
    - `MiniWorker.php` pemrosesan 1 `queue_id`.
  - `bootstrap.php` – autoload Composer, init config/db.
- **config/db.php** – detail kredensial MySQL.
- **public/** – akses web:
  - `index.php` – landing atau redirect dashboard.
  - **admin/** – dashboard read-only untuk monitoring.
  - **api/autopost/** – endpoint REST `queue.php` dan `posts.php`.
- **storage/** – log dan cache.
- **worker.php** – CLI yang menjalankan `QueueWorker` secara berkala (cron loop).
- **mini_worker.php** – CLI yang memproses satu antrean spesifik (debug/manually run).
- **why_skip.php** – CLI untuk memeriksa alasan antrean di-skip (debug).
- **seed.php** – CLI untuk menambahkan sampel data ke `social_queue`.
- **composer.json / vendor/** – konfigurasi dan dependensi Composer.
- **README.md** – dokumentasi proyek.

### Aliran Eksekusi Program

1. **Insert ke `social_queue`**
   - Bisa dilakukan oleh sistem eksternal atau skrip `seed.php`.
   - Baris baru berisi konten, target platform, jadwal, status `pending`.

2. **Worker mengambil antrean**
   - `worker.php` dijalankan via cron/CLI.
   - `QueueWorker` memeriksa tabel `social_queue` untuk status `pending` dengan jadwal due.

3. **Dispatcher mem-posting**
   - Worker menentukan platform (mis. Telegram) dan memanggil `Dispatcher/<Platform>.php`.
   - Dispatcher mengambil kredensial dari `platform_accounts`, mem-posting via API, lalu:
     - Menyimpan hasil ke `social_posts`.
     - Mengubah status baris pada `social_queue` menjadi `posted` atau `failed`.

4. **Log ke `social_posts`**
   - Berisi catatan posting (waktu, platform, status, pesan error jika ada).
   - Dashboard read-only di `/public/admin/` menampilkan data ini dan antrean via endpoint `/api/autopost/queue` & `/api/autopost/posts`.

Dengan struktur di atas, SAE memiliki alur jelas: data masuk ke antrian → worker memproses → dispatcher berhubungan dengan API platform → hasil dicatat ke `social_posts` untuk monitoring.
