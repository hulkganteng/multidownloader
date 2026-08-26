# 🚀 DownloadIn — Modern Multi-Platform Media Downloader

Aplikasi pengunduh video dan audio berbasis **Laravel 11** yang cepat, andal, dan modern. Mendukung berbagai platform populer seperti **YouTube, TikTok, Instagram, Facebook, Twitter/X**, serta **Direct Media Links** dengan arsitektur **Zero-Database (100% File Storage & Browser LocalStorage)**.

---

## ✨ Fitur Utama

- 🎯 **Multi-Platform Support**: YouTube, TikTok (tanpa watermark), Instagram Reels/Video, Facebook Watch, Twitter/X, dan link file langsung.
- 🎵 **Pilihan Format Lengkap**: Unduh sebagai video **MP4** (resolusi 360p s.d. 4K) atau ekstrak audio ke **MP3** (128k, 192k, 320k).
- ⚡ **Super Cepat (Multi-Thread Concurrent Download)**: Mengunduh fragmen video secara paralel (`--concurrent-fragments 4`) untuk memaksimalkan kecepatan transfer.
- 🗄️ **Zero-Database (Bebas Database SQL)**:
  - Metadata tugas unduhan disimpan dalam file JSON di `storage/app/downloads/{uuid}/task.json`.
  - Riwayat unduhan & preferensi format disimpan di **LocalStorage browser**.
  - Tidak memerlukan instalasi MySQL/PostgreSQL atau migrasi database (`php artisan migrate`).
- 📊 **Real-Time Progress Bar**: Menampilkan persentase unduhan aktual secara live di antarmuka web.
- 🛡️ **Anti-Bot & Reverse Proxy Ready**: Dilengkapi extractor modern, browser User-Agent, dan siap berjalan di balik **Cloudflare Tunnel / HTTPS Reverse Proxy**.

---

## 📋 Persyaratan Sistem (Prerequisites)

- **PHP**: Versi 8.2 atau lebih baru (dengan ekstensi `curl`, `mbstring`, `fileinfo`, `json`, `openssl`).
- **Composer**: Dependency manager PHP.
- **Node.js & NPM**: Untuk build asset frontend (Tailwind CSS & Vite).
- **Tools Binary**: `yt-dlp` dan `ffmpeg` (sudah tersedia untuk Windows di folder `tools/bin/`).

---

## 🛠️ Panduan Instalasi Lokal (Quick Start)

1. **Clone repository & masuk ke direktori proyek**:
   ```bash
   git clone <url-repository-anda>
   cd multidownloader
   ```

2. **Install dependensi PHP & Node.js**:
   ```bash
   composer install
   npm install
   ```

3. **Salin file konfigurasi lingkungan (`.env`)**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Build asset tampilan frontend**:
   ```bash
   npm run build
   ```

5. **Jalankan web server lokal**:
   ```bash
   php artisan serve
   ```
   Buka browser di `http://127.0.0.1:8000`.

---

## 🌐 Panduan Hosting di Laptop Pribadi + Cloudflare Tunnel

Anda bisa menjadikan laptop Anda sebagai server publik gratis agar website bisa diakses dari HP dan komputer lain di seluruh dunia melalui **Cloudflare Tunnel**.

### Langkah 1: Jalankan Server Laravel
Buka Terminal / PowerShell di folder proyek:
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

### Langkah 2: Jalankan Cloudflare Tunnel

#### Opsi A: Quick Tunnel (Gratis & Instan Tanpa Domain)
Jalankan perintah berikut di terminal terpisah:
```bash
cloudflared tunnel --url http://localhost:8000
```
Cloudflare akan menampilkan URL publik acak (contoh: `https://your-tunnel-name.trycloudflare.com`). URL ini sudah bisa langsung dibuka dari internet.

#### Opsi B: Menggunakan Domain Pribadi (Rekomendasi)
1. Buka dashboard [Cloudflare Zero Trust](https://one.dash.cloudflare.com/) → **Networks** → **Tunnels**.
2. Buat tunnel baru dan tambahkan **Public Hostname**:
   - **Service Type**: `HTTP`
   - **URL**: `localhost:8000`
3. Jalankan tunnel di laptop Anda:
   ```bash
   cloudflared tunnel run <nama-tunnel-anda>
   ```
4. Perbarui file `.env` di proyek:
   ```env
   APP_URL=https://downloadin.domainanda.com
   ```

### 💡 Tips Agar Laptop Optimal Sebagai Server:
- **Pengaturan Daya (Power & Sleep)**: Atur Windows ke *"Never sleep when plugged in"* agar server tidak mati saat layar ditutup.
- **Koneksi Jaringan**: Gunakan koneksi Wi-Fi yang stabil atau kabel LAN.

---

## 🐧 Panduan Deployment di Shared Hosting (cPanel) / VPS Linux

Jika ingin memindahkan website ke Shared Hosting atau VPS Linux:

1. **Pastikan fungsi PHP `proc_open` dan `exec` diaktifkan** pada menu *Select PHP Version* di cPanel.
2. **Unduh binary Linux standalone** ke folder `tools/bin/`:
   ```bash
   # Unduh yt-dlp Linux standalone
   curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o tools/bin/yt-dlp
   chmod +x tools/bin/yt-dlp

   # Pastikan ffmpeg Linux executable
   chmod +x tools/bin/ffmpeg
   ```
3. **Sesuaikan file `.env`**:
   ```env
   YOUTUBE_DL_PATH=tools/bin/yt-dlp
   TIKTOK_DL_PATH=tools/bin/yt-dlp
   INSTAGRAM_DL_PATH=tools/bin/yt-dlp
   FACEBOOK_DL_PATH=tools/bin/yt-dlp
   TWITTER_DL_PATH=tools/bin/yt-dlp
   FFMPEG_PATH=tools/bin/ffmpeg
   ```

---

## 🧹 Pembersihan File Unduhan Otomatis (Cleanup)

File media yang diunduh memiliki masa berlaku (default: 24 jam). Untuk membersihkan file yang kedaluwarsa dari penyimpanan:

```bash
php artisan downloads:cleanup
```

> **Tips di Hosting / Server**: Pasang cron job harian pada server:
> ```bash
> 0 2 * * * cd /path/to/multidownloader && php artisan downloads:cleanup >> /dev/null 2>&1
> ```

---

## 📜 Lisensi & Kontribusi

Proyek ini dibuat untuk keperluan personal & edukasi di bawah lisensi [MIT License](LICENSE).
# multidownloader
