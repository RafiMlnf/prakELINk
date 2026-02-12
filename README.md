# PRAKELINK - Sistem Monitoring Prakerin SMKN 2 Garut

**PRAKELINK** adalah aplikasi web untuk mempermudah monitoring kegiatan Praktek Kerja Industri (Prakerin/PKL) siswa jurusan Elektronika Industri di SMKN 2 Garut. Aplikasi ini mengintegrasikan presensi berbasis lokasi (GPS) dan jurnal kegiatan harian dalam satu platform yang mudah diakses.

## Fitur Utama

- **Presensi Berbasis GPS**  
  Siswa melakukan *check-in* dan *check-out* presensi yang divalidasi berdasarkan koordinat lokasi tempat PKL (Geolocation & Radius Check).

- **Jurnal Kegiatan Digital**  
  Pencatatan aktivitas harian siswa disertai bukti foto kegiatan, memudahkan pembina dalam memantau progress siswa.

- **Multi-Role User**  
  - **Siswa**: Melakukan presensi, mengisi jurnal, dan melihat riwayat kegiatan.
  - **Pembina**: MemVerifikasi jurnal, memantau kehadiran siswa bimbingan, dan memberikan catatan evaluasi.
  - **Admin**: Manajemen data siswa, guru, lokasi industri, dan pengaturan sistem.

- **Laporan & Rekapitulasi**  
  Fitur ekspor data presensi dan jurnal ke format Excel untuk kebutuhan administrasi sekolah.

- **Manajemen Profil**  
  Fitur upload foto profil dengan *image cropping* terintegrasi.

## Teknologi yang Digunakan

- **Backend**: Native PHP 8.0+
- **Database**: MySQL / MariaDB
- **Frontend**: HTML5, CSS3 (Custom Styling), JavaScript
- **Libraries & API**:
  - `Leaflet.js` (Peta & Lokasi)
  - `Cropper.js` (Manipulasi Gambar)
  - `Font Awesome` (Ikon Interface)
  - `Google Fonts` (Tipografi)

## Instalasi

1. Clone repositori ini ke dalam folder root web server (htdocs/www).
2. Buat database baru di MySQL (misal: `db_prakelink`).
3. Import file database (jika tersedia) ke dalam database yang baru dibuat.
4. Sesuaikan konfigurasi koneksi database di file `config/database.php`.
5. Buka aplikasi melalui browser (contoh: `http://localhost/pkl-tracking`).

---

Dibuat untuk memenuhi kebutuhan monitoring Prakerin SMKN 2 Garut.
# prakELINk
