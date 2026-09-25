# iClock / ADMS Protocol Scripts

Repositori ini berisi sekumpulan skrip PHP endpoint untuk menangani komunikasi protokol **iClock / ADMS** dari perangkat mesin absensi (seperti ZKTeco, Fingerspot, dan mesin sejenis yang mendukung fitur cloud/ADMS).

## Struktur File
- **`cdata.php`**: Endpoint utama untuk menerima data log absensi (kehadiran) dan transaksi data secara *real-time* dari mesin absensi.
- **`getrequest.php`**: Endpoint yang digunakan oleh mesin untuk melakukan *polling* atau mengambil perintah/perintah konfigurasi yang tertunda dari server.

---

## Cara Penggunaan & Alur Kerja Log `cdata`

1. **Penerimaan Data (`cdata.php`):**
   - Ketika karyawan melakukan *tap* atau verifikasi absensi di mesin, mesin secara otomatis mengirimkan data log dalam bentuk parameter HTTP POST/GET ke URL endpoint `cdata.php`.
   - Skrip ini bertugas memproses data tersebut (seperti parsing data punch log) dan menyimpannya ke dalam basis data (database) server Anda.
   - Contoh parameter yang sering dikirim oleh mesin mencakup: `SN` (Serial Number mesin), `table=rtlog` atau `table=operlog`, dan data stamp waktu absensi.

2. **Sinkronisasi Perintah (`getrequest.php`):**
   - Mesin secara berkala akan mengakses `getrequest.php` untuk mengecek apakah ada perintah baru dari server (misalnya perintah sinkronisasi nama karyawan, buka pintu jarak jauh, atau restart perangkat).

---

## Konfigurasi Alamat Server Cloud pada Mesin Absensi

Agar mesin absensi dapat terhubung ke server hosting/cloud tempat skrip ini diunggah, ikuti langkah-langkah pengaturan pada menu fisik mesin absensi:

1. Nyalakan mesin absensi, masuk ke menu utama (biasanya memerlukan verifikasi admin).
2. Pilih menu **Comm.** (Communication / Komunikasi) -> **Network Settings** atau langsung cari pengaturan **Cloud Server Settings** / **ADMS**.
3. Sesuaikan parameter berikut:
   - **Enable Domain Name / Server Domain:** Diaktifkan (ON) jika menggunakan nama domain (misalnya `it-pro.co.id` atau subdomain Anda).
   - **Server Address / IP Address:** Masukkan alamat URL atau IP server cloud tempat skrip ini di-hosting.
   - **Port:** Masukkan port server (biasanya `80` untuk HTTP atau `443` untuk HTTPS).
   - **Server URL / Endpoint Path:** Masukkan path direktori skrip `iclock`, contoh format URL lengkap yang dituju oleh mesin:
     ```text
     http://IP_SERVER_ATAU_DOMAIN_ANDA/iclock/
     ```
     *(Beberapa jenis firmware mesin memerlukan endpoint spesifik seperti `http://domain-anda.com/iclock/cdata.php` atau cukup direktori utamanya tergantung pada implementasi skrip).*

4. **Simpan Pengaturan:**
   - Simpan konfigurasi pada mesin, lalu lakukan *Test Connection* atau *Reboot* mesin.
   - Periksa log di sisi server atau tabel database untuk memastikan mesin sudah berhasil mengirimkan data (*handshake* berhasil).
