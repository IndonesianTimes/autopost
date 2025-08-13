Berikut panduan langkah demi langkah untuk menyiapkan workflow aplikasi PHP dengan server lokal, konfigurasi framework, pembuatan endpoint API, serta pengujian respons:

### 1. Menyiapkan server lokal
1. **Instal PHP 8.x** dan **Composer**.
2. (Opsional) Instal MySQL atau MariaDB jika aplikasi memerlukan database.
3. Jalankan server built-in PHP:
   ```bash
   php -S localhost:8000 -t public
   ```
   Perintah ini melayani file di folder `public/` sebagai root server.

### 2. Mengonfigurasi framework & dependensi
1. Buat struktur proyek dan file `composer.json`, lalu jalankan `composer install` untuk mengunduh dependensi.
2. Atur autoload dengan PSR-4 dan load **Dotenv** agar variabel `.env` otomatis dimuat pada bootstrap aplikasi (`src/bootstrap.php`). File ini juga mendefinisikan helper untuk mengembalikan respons JSON yang konsisten.

### 3. Membuat endpoint API
1. Tambahkan logika routing di `public/index.php`. Contoh route `/health` (GET) dan `/api/autopost/ingest` (POST) terlihat sebagai berikut:
   - `GET /health` mengembalikan status aplikasi.
   - `POST /api/autopost/ingest` memanggil controller tertentu.
2. Untuk endpoint baru, tambahkan kondisi lain (misalnya `if ($method === 'GET' && $uri === '/users') { ... }`), lalu panggil fungsi atau controller yang sesuai.

### 4. Menulis controller/handler
1. Buat file controller di `src/controllers/`, misal `UserController.php`.
2. Di dalamnya tulis metode statis (misal `UserController::index()`) yang membaca input, mengakses database bila perlu, dan mengembalikan respons memakai helper `json()` agar formatnya seragam.

### 5. Pengujian endpoint
1. Jalankan server lalu uji dengan **cURL** atau **Postman**:
   ```bash
   curl -i http://localhost:8000/health
   ```
2. Pastikan respons JSON memiliki struktur yang benar (`status` dan `data`/`error`) sesuai fungsi helper yang dipakai.
3. Jika ada autentikasi (misalnya Bearer token), sertakan header `Authorization: Bearer <token>` saat mengirim request.

Dengan mengikuti langkah di atas, Anda dapat menyiapkan workflow aplikasi PHP sederhana: mulai dari server lokal, konfigurasi lingkungan, pembuatan endpoint API, hingga pengujian agar endpoint merespons dengan benar.
