PROJECT BRIEF 

Sistem Monitoring PKL Terintegrasi 

Jurusan Teknik Elektronika Industri 

SMKN 2 Garut 

1\. LATAR BELAKANG 

Pelaksanaan Praktik Kerja Lapangan (PKL) merupakan bagian penting dalam pembelajaran 

di Jurusan Teknik Elektronika Industri SMKN 2 Garut. Setiap periode, ±125 siswa 

melaksanakan PKL di berbagai Dunia Usaha/Dunia Industri (DUDI). 

Selama ini, monitoring kehadiran dan pencatatan jurnal kegiatan masih dilakukan secara 

manual menggunakan buku jurnal fisik. Metode ini menimbulkan beberapa kendala: 

• Sulit memantau kehadiran secara real-time 

• Validasi lokasi kehadiran tidak terukur 

• Rekap absensi memakan waktu 

• Potensi manipulasi data kehadiran 

• Beban administrasi tinggi bagi guru pembimbing 

Untuk meningkatkan akuntabilitas, transparansi, dan efisiensi monitoring, diperlukan 

Sistem Monitoring PKL berbasis web yang terintegrasi. 

2\. TUJUAN PENGEMBANGAN SISTEM 

1\. Memastikan kehadiran siswa PKL tervalidasi berdasarkan lokasi DUDI. 

2\. Mengontrol jam kerja PKL melalui mekanisme pengajuan dan persetujuan. 

3\. Menggantikan jurnal manual dengan jurnal digital. 

4\. Memudahkan monitoring oleh guru dan admin. 

5\. Menyediakan rekapitulasi data kehadiran dan jurnal secara otomatis. 

6\. Mendukung pengambilan keputusan berbasis data. 

3\. RUANG LINGKUP SISTEM 

3.1 Cakupan Implementasi 

• Digunakan oleh Jurusan Teknik Elektronika Industri 

• ±125 siswa aktif per periode 

• Digunakan dalam satu sekolah 

• Hosting pada server lokal sekolah 

4\. PERAN DAN HAK AKSES PENGGUNA 

4.1 Siswa PKL 

• Mengajukan jam kerja 

• Melakukan absensi masuk dan pulang 

• Mengisi jurnal harian 

• Melihat riwayat absensi dan jurnal 

4.2 Guru Pembimbing 

• Melihat absensi siswa bimbingan 

• Memvalidasi jurnal 

• Melihat grafik kehadiran siswa 

4.3 Admin Sekolah 

• Mengelola data siswa 

• Mengelola data DUDI 

• Mengelola radius lokasi 

• Memvalidasi pengajuan jam kerja 

• Monitoring keseluruhan sistem 

• Import data siswa via CSV 

4.4 Pembimbing Industri (Opsional) 

• Memvalidasi jurnal 

• Melihat absensi siswa pada DUDI terkait 

5\. MODUL DAN FITUR SISTEM 

A. Modul Manajemen Data 

5.1 Data Siswa 

Field data meliputi: - NIS - Nama - Kelas - Alamat rumah - Titik koordinat rumah (latitude \& 

longitude) - DUDI penempatan 

Data dapat diimpor melalui file CSV. 

5.2 Data DUDI 

Field data meliputi: - Nama DUDI - Alamat - Latitude - Longitude - Radius validasi (meter) 

B. Modul Pengajuan dan Validasi Jam Kerja 

1\. Siswa mengajukan jam masuk dan jam pulang. 

2\. Status pengajuan: 

o Menunggu persetujuan 

o Disetujui 

o Ditolak 

3\. Admin melakukan validasi. 

4\. Jam kerja yang disetujui menjadi dasar evaluasi keterlambatan. 

5\. Siswa tidak dapat melakukan absensi sebelum jam kerja disetujui. 

C. Modul Absensi Berbasis GPS 

5.3 Check-in \& Check-out 

Wajib memenuhi: - GPS aktif - Foto selfie real-time (tidak dari galeri) - Dalam radius DUDI 

Data tersimpan: - Jam masuk - Jam pulang - Koordinat lokasi - Foto masuk dan pulang - 

Status validasi 

5.4 Anti Fake GPS 

Sistem harus mampu: - Mendeteksi mock location - Menolak absensi jika terindikasi 

manipulasi lokasi 

5.5 Status Kehadiran 

Status otomatis: - Hadir tepat waktu - Terlambat - Pulang lebih awal - Tidak check-out - 

Tidak valid (di luar radius) 

D. Modul Jurnal Harian 

1\. Format jurnal: teks saja. 

2\. Maksimal satu jurnal per hari. 

3\. Hanya dapat diinput setelah check-in. 

4\. Validasi dapat dilakukan oleh: 

o Guru pembimbing 

o Pembimbing industri 

5\. Status jurnal: 

o Menunggu 

o Disetujui 

o Ditolak 

E. Dashboard dan Monitoring 

5.6 Dashboard Admin 

• Statistik kehadiran global 

• Grafik kehadiran per periode 

• Rekap absensi per DUDI 

• Monitoring persetujuan jam kerja 

• Monitoring lokasi DUDI dan titik rumah siswa 

5.7 Dashboard Guru 

• Grafik kehadiran per siswa 

• Riwayat absensi siswa 

• Status validasi jurnal 

5.8 Live Monitoring Map 

• Peta lokasi DUDI 

• Titik kehadiran siswa 

• Informasi jumlah siswa hadir per DUDI 

6\. STRUKTUR DATA UTAMA 

Tabel Siswa 

• id 

• nis 

• nama 

• kelas 

• alamat\_rumah 

• lat\_rumah 

• long\_rumah 

• dudi\_id 

Tabel DUDI 

• id 

• nama 

• alamat 

• latitude 

• longitude 

• radius 

Tabel Pengajuan Jam Kerja 

• id 

• siswa\_id 

• jam\_masuk 

• jam\_pulang 

• status 

• catatan\_admin 

Tabel Absensi 

• id 

• siswa\_id 

• tanggal 

• jam\_masuk 

• jam\_pulang 

• foto\_masuk 

• foto\_pulang 

• latitude 

• longitude 

• status 

Tabel Jurnal 

• id 

• siswa\_id 

• tanggal 

• isi\_jurnal 

• validasi\_guru 

• validasi\_industri 

• status 

7\. SPESIFIKASI TEKNIS 

1\. Berbasis web (mobile-friendly). 

2\. Hosting server lokal sekolah. 

3\. Mendukung ±125 pengguna aktif. 

4\. Enkripsi password (hash). 

5\. Waktu respon absensi < 3 detik. 

6\. Sistem stabil untuk penggunaan harian. 

7\. Backup database disarankan dilakukan secara berkala. 

8\. OUTPUT YANG DIHARAPKAN DARI PENGEMBANG 

1\. Aplikasi web siap digunakan. 

2\. Source code lengkap. 

3\. Skema database. 

4\. Dokumentasi instalasi server lokal. 

5\. Panduan penggunaan (User Manual). 

9\. INDIKATOR KEBERHASILAN 

1\. Seluruh siswa menggunakan absensi digital. 

2\. Tidak ada lagi jurnal PKL manual. 

3\. Data kehadiran terdokumentasi otomatis. 

4\. Guru dapat melakukan monitoring tanpa kunjungan harian. 

5\. Rekap absensi dan jurnal dapat diakses real-time. 



