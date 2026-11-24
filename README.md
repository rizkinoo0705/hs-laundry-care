# HS Laundry & Care

*Bersih, Wangi, Kami yang Urus*

Aplikasi web untuk kasir, member, mitra dropbox, dan antar-jemput HS Laundry & Care.
Dibangun dengan **Laravel 12** + **PostgreSQL**, tanpa build front-end (cukup PHP).

## Fitur

| Modul | Isi |
|---|---|
| **Kasir & order** | Input order kiloan/satuan/premium/gosok, diskon, bayar sekarang/nanti, status (Diterima → Dicuci → Disetrika → Siap → Selesai), nota printer 58 mm, tombol kirim status ke WhatsApp pelanggan |
| **Pelanggan & member** | Pelanggan otomatis dibuat dari nomor HP saat order, kode member `HS-M0001`, kartu 10 stempel, tukar hadiah, riwayat stempel |
| **Mitra dropbox** | Data outlet mitra, komisi per kg (mis. Rp1.000/kg) atau persen, komisi dihitung otomatis per order, pencairan komisi tercatat |
| **Antar-jemput** | Jadwal jemput/antar ke pelanggan atau mitra, petugas, status perjalanan |
| **Laporan** | Omzet, uang masuk, piutang, per hari, per layanan, per mitra |
| **Pengguna** | Admin/pemilik (akses penuh), Kasir (order, pelanggan, antar-jemput), Mitra (portal: cucian & komisi miliknya saja) |

### Aturan bisnis

- **Stempel member**: member dapat 1 stempel saat order **selesai dan lunas** (sekali per order). Target & hadiah diatur di `.env` (`LAUNDRY_STAMP_TARGET`, `LAUNDRY_STAMP_REWARD`).
- **Komisi mitra**: dihitung saat order dibuat (berat kg × tarif, atau % dari total). Komisi baru bisa **dicairkan** setelah order selesai dan lunas. Mengubah tarif mitra hanya berlaku untuk order baru.
- **Berat minimum**: layanan bisa punya minimum (mis. kiloan 3 kg). Cucian 2,5 kg ditagih 3 kg; 4 kg ditagih 4 kg. Berat asli tetap tercatat; komisi mitra dihitung dari berat yang ditagih.
- **Status order** hanya bisa maju (boleh melompat), atau dibatalkan.

## Menjalankan dengan Docker (paling mudah)

Butuh [Docker Desktop](https://www.docker.com/products/docker-desktop/).

```bash
git clone https://github.com/rizkinoo0705/hs-laundry-care.git
cd hs-laundry-care
docker compose up --build
```

Buka **http://localhost:8000**. Container otomatis menginstal dependensi, membuat `.env`, migrasi database, dan mengisi data awal.

## Menjalankan tanpa Docker

Butuh PHP 8.2+ (ekstensi `pdo_pgsql`), Composer, dan PostgreSQL.

```bash
composer install
cp .env.example .env
php artisan key:generate

# buat database di PostgreSQL
createdb -U postgres hs_laundry      # sesuaikan DB_* di .env

php artisan migrate --seed
php artisan serve
```

## Akun awal

| Peran | Email | Password |
|---|---|---|
| Admin | admin@hslaundry.test | password |
| Kasir | kasir@hslaundry.test | password |
| Mitra (contoh) | mitra@hslaundry.test | password |

> **Ganti password semua akun ini** di menu *Pengguna* sebelum dipakai sungguhan.
> Harga layanan di data awal hanya contoh (kiloan Rp7.000/kg); ubah di menu *Layanan & Harga*.

## Tes

```bash
php artisan test
```

Secara bawaan tes memakai SQLite in-memory. GitHub Actions (`.github/workflows/tests.yml`) menjalankan tes yang sama dengan PostgreSQL 16 setiap kali ada push.

## Pengaturan (`.env`)

| Kunci | Keterangan |
|---|---|
| `LAUNDRY_WA` | Nomor WhatsApp outlet (tampil di nota & portal mitra) |
| `LAUNDRY_ADDRESS` | Alamat outlet di nota |
| `LAUNDRY_STAMP_TARGET` | Jumlah stempel untuk 1 hadiah (bawaan 10) |
| `LAUNDRY_STAMP_REWARD` | Nama hadiah |
| `LAUNDRY_STAMP_MIN_TOTAL` | Minimal total order agar dapat stempel (bawaan 0) |

## Struktur singkat

```
app/Models          Customer, Order, OrderItem, Service, Mitra, MitraPayout, Delivery, StampTransaction, User
app/Services        OrderService (hitung total, komisi, status), StampService (stempel member)
app/Http/Controllers
resources/views     Tampilan Blade
public/css/app.css  Gaya (warna biru tua #1E3A8A & biru muda #38BDF8)
```

## Rencana berikutnya (belum ada)

- Ganti logo sementara "HS" dengan logo final
- Notifikasi WhatsApp otomatis (sekarang lewat tombol "Kirim WA")
- Ekspor laporan ke Excel/PDF
