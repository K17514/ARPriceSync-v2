# PriceSync — Penyesuaian Harga Shopee

PriceSync memperbarui harga dan/atau stok pada template produk Shopee dengan mencocokkan SKU
dari file sumber terkait. Jalankan aplikasi dari folder `public`
sesuai konfigurasi web server CodeIgniter 4.

## Cara menggunakan

1. Buka halaman aplikasi.
2. Pilih mode `Harga dan stok`, `Harga saja`, atau `Stok saja`.
3. Unggah referensi harga dan/atau stok satu kali dari menu `Referensi`.
   Referensi tersimpan di `public/referensi`; unggah ulang dari halaman tersebut
   untuk mengganti atau memperbarui setiap referensi. Akses HTTP langsung ke
   folder referensi diblokir oleh konfigurasi server.
4. Unggah file template Shopee dan file raw yang dibutuhkan (XLSX, XLS, atau CSV;
   maks. 10 MB per file).
5. Aplikasi mencari kolom SKU dan nilai pada 30 baris pertama di setiap sheet,
   memperbarui nilai yang SKU-nya cocok, lalu mengunduh template yang diperbarui.
6. Nilai yang tidak ditemukan di file sumber tetap menggunakan nilai awal pada template.

Header yang dikenali mencakup `SKU`, `Seller SKU`, `SKU Model`, `Kode SKU`,
`Harga`, `Harga (Rp)`, `Harga Jual`, dan `Price`. Teks tambahan pada judul kolom
juga diterima. Kolom stok dikenali sebagai `Stok`, `Stock`, `Qty`, atau
`Quantity`. Stok pecahan dibulatkan ke bawah dan stok negatif diubah menjadi
nol. Setiap SKU harus unik di masing-masing file sumber.

Untuk pembaruan harga, file referensi berisi kolom `Brand`, `Description`,
`Model`, `Size`, dan `SKU`. Kombinasi empat kolom produk tersebut digunakan
untuk mencocokkan file harga raw, dengan nilai harga di kolom I. Aturan
pembulatan harga menggunakan ambang dan kelipatan terdekat yang dapat diatur;
jika beberapa ambang terpenuhi, ambang tertinggi yang dipakai. Kenaikan harga
dalam persen diterapkan setelah pembulatan, lalu pengguna dapat mengatur
kelipatan pembulatan akhir secara terpisah. Kedua tahap pembulatan harga selalu
membulatkan ke atas. Baris referensi dengan atribut
produk atau SKU yang tidak lengkap dilewati dan ikut dihitung dalam peringatan.
Pada file harga raw, Brand, Description, Model, dan Size selalu dibaca langsung
dari kolom A-D sehingga nama header raw tidak perlu sama persis. Atribut yang
kosong diperbolehkan selama kombinasi nilai yang terisi sama di file referensi
dan harga raw; baris dengan keempat atribut kosong dilewati.

Pada file stok raw, nilai `Ref` dibaca dari kolom C dan jumlah stok dibaca dari
kolom `Quantity`. Nilai Ref dicocokkan dengan kolom `Ref` pada file referensi
stok, lalu SKU hasil referensi digunakan untuk memperbarui template.
Baris referensi stok yang hanya memiliki salah satu nilai `Ref` atau `SKU`
akan dilewati; setelah file hasil diunduh, aplikasi menampilkan jumlah baris
yang dilewati sebagai peringatan.

Aturan perubahan stok bersifat opsional. Pengguna dapat menambahkan beberapa
pasangan ambang dan stok pengganti, misalnya stok di bawah 1000 menjadi 10,
di bawah 100 menjadi 2, dan di bawah 20 menjadi 3. Jika lebih dari satu ambang
cocok, ambang terkecil yang dipakai. Aturan memakai perbandingan ketat (`<`).

PhpSpreadsheet digunakan untuk membaca dan menulis file Excel. Aktifkan ekstensi
PHP `gd` selain persyaratan standar CodeIgniter sebelum menjalankan `composer install`.

## What is CodeIgniter?

CodeIgniter is a PHP full-stack web framework that is light, fast, flexible and secure.
More information can be found at the [official site](https://codeigniter.com).

This repository holds a composer-installable app starter.
It has been built from the
[development repository](https://github.com/codeigniter4/CodeIgniter4).

More information about the plans for version 4 can be found in [CodeIgniter 4](https://forum.codeigniter.com/forumdisplay.php?fid=28) on the forums.

You can read the [user guide](https://codeigniter.com/user_guide/)
corresponding to the latest version of the framework.

## Installation & updates

`composer create-project codeigniter4/appstarter` then `composer update` whenever
there is a new release of the framework.

When updating, check the release notes to see if there are any changes you might need to apply
to your `app` folder. The affected files can be copied or merged from
`vendor/codeigniter4/framework/app`.

## Setup

Copy `env` to `.env` and tailor for your app, specifically the baseURL
and any database settings.

## Important Change with index.php

`index.php` is no longer in the root of the project! It has been moved inside the *public* folder,
for better security and separation of components.

This means that you should configure your web server to "point" to your project's *public* folder, and
not to the project root. A better practice would be to configure a virtual host to point there. A poor practice would be to point your web server to the project root and expect to enter *public/...*, as the rest of your logic and the
framework are exposed.

**Please** read the user guide for a better explanation of how CI4 works!

## Repository Management

We use GitHub issues, in our main repository, to track **BUGS** and to track approved **DEVELOPMENT** work packages.
We use our [forum](http://forum.codeigniter.com) to provide SUPPORT and to discuss
FEATURE REQUESTS.

This repository is a "distribution" one, built by our release preparation script.
Problems with it can be raised on our forum, or as issues in the main repository.

## Server Requirements

PHP version 8.2 or higher is required, with the following extensions installed:

- [intl](http://php.net/manual/en/intl.requirements.php)
- [mbstring](http://php.net/manual/en/mbstring.installation.php)

> [!WARNING]
> - The end of life date for PHP 7.4 was November 28, 2022.
> - The end of life date for PHP 8.0 was November 26, 2023.
> - The end of life date for PHP 8.1 was December 31, 2025.
> - If you are still using below PHP 8.2, you should upgrade immediately.
> - The end of life date for PHP 8.2 will be December 31, 2026.

Additionally, make sure that the following extensions are enabled in your PHP:

- json (enabled by default - don't turn it off)
- [mysqlnd](http://php.net/manual/en/mysqlnd.install.php) if you plan to use MySQL
- [libcurl](http://php.net/manual/en/curl.requirements.php) if you plan to use the HTTP\CURLRequest library
