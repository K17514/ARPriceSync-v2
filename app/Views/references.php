<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Referensi — ARPriceSync</title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= base_url('favicon-32x32.png') ?>">
    <style>
        :root {
            --ink: #1f1f1f;
            --muted: #5d5d5d;
            --orange: #ee5a24;
            --orange-dark: #cf4513;
            --panel: #3a3a3a;
            --surface: #f6f6f6;
            --background: #ebebeb;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; background: var(--background); color: var(--ink);
            font-family: "Helvetica Neue", Helvetica, Arial, "Segoe UI", sans-serif;
        }
        .topbar {
            height: 64px; display: flex; align-items: center; gap: 18px;
            padding: 0 max(24px, calc((100vw - 1080px) / 2));
        }
        .brand img { display: block; width: 180px; height: auto; }
        .topbar-nav { display: flex; gap: 8px; margin-left: auto; }
        .topbar-nav a {
            padding: 9px 12px; color: var(--ink); font-size: 13px; font-weight: 800;
            text-decoration: none; border: 1px solid transparent; min-height: 40px;
            display: inline-flex; align-items: center;
            transition: color .16s ease, background-color .16s ease, border-color .16s ease;
        }
        .topbar-nav a:hover { color: var(--orange-dark); border-color: var(--orange); background: #fff; }
        .topbar-nav a[aria-current="page"] { color: var(--orange-dark); border-color: var(--orange); background: #fff; }
        main {
            width: min(100% - 40px, 960px); margin: 8px auto 70px; padding: 48px;
            background: var(--surface); border: 1.5px solid #8d8d8d; position: relative;
        }
        main::before { content: ""; position: absolute; left: 0; bottom: 0; width: 22%; height: 28px; background: var(--orange); }
        .eyebrow {
            display: inline-block; background: var(--orange); color: #fff; padding: 4px 11px;
            font-size: 13px; font-weight: 900; text-transform: uppercase;
        }
        h1 { margin: 20px 0 12px; font-size: clamp(34px, 6vw, 58px); line-height: 1.05; letter-spacing: -.05em; }
        .intro { margin: 0 0 28px; color: var(--muted); line-height: 1.6; }
        .status-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-bottom: 24px; }
        .status { padding: 15px; border: 2px solid var(--panel); background: #fff; font-size: 14px; font-weight: 800; }
        .status span { display: block; margin-top: 6px; color: var(--muted); font-size: 12px; font-weight: 600; }
        .status.ready span { color: #276749; }
        .upload-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .upload-box {
            display: block; min-width: 0; min-height: 190px; padding: 22px; border: 3px solid var(--orange);
            background: var(--panel); color: #f1f1f1;
            transition: background-color .16s ease, border-color .16s ease, transform .16s ease, box-shadow .16s ease;
        }
        .upload-box:hover, .upload-box:focus-within { background: #2c2c2c; border-color: var(--orange-dark); }
        @media (hover: hover) {
            .upload-box:hover { transform: translateY(-2px); box-shadow: 0 6px 14px rgb(31 31 31 / 12%); }
        }
        .upload-title { display: block; margin-bottom: 6px; font-size: 17px; font-weight: 900; }
        .upload-help { display: block; min-height: 36px; color: #cfcfcf; font-size: 12px; line-height: 1.5; }
        input[type=file] { display: block; width: 100%; min-width: 0; margin-top: 15px; color: #e6e6e6; font-size: 12px; }
        input[type=file]::file-selector-button {
            min-height: 40px; border: 0; padding: 7px 11px; margin-right: 9px;
            background: var(--orange); color: #fff; font-weight: 800; cursor: pointer;
            transition: background-color .16s ease;
        }
        input[type=file]::file-selector-button:hover { background: var(--orange-dark); }
        .actions { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-top: 24px; }
        .format-note { color: var(--muted); font-size: 12px; font-weight: 600; }
        button {
            min-height: 44px; border: 0; border-radius: 4px; padding: 14px 24px; background: var(--orange); color: #fff;
            font: inherit; font-size: 14px; font-weight: 900; cursor: pointer; box-shadow: 4px 4px 0 var(--panel);
            transition: transform .12s ease, box-shadow .12s ease, background-color .16s ease;
        }
        button:hover { background: var(--orange-dark); }
        button:active { transform: translate(3px, 3px); box-shadow: 1px 1px 0 var(--panel); }
        button:focus-visible, input:focus-visible { outline: 3px solid var(--panel); outline-offset: 2px; }
        .message { margin-bottom: 18px; padding: 13px 15px; border: 2px solid var(--panel); background: #fff; line-height: 1.5; }
        .message.error { border-color: var(--orange); color: var(--orange-dark); font-weight: 700; }
        .message.success { border-color: #276749; color: #276749; font-weight: 700; }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition: none !important; animation: none !important; }
        }
        @media (max-width: 650px) {
            .topbar {
                min-height: 64px; height: auto; padding: 8px 14px; gap: 8px;
                justify-content: space-between;
            }
            .brand { min-width: 0; }
            .brand img { width: clamp(105px, 34vw, 128px); }
            .topbar-nav { flex: 0 0 auto; gap: 2px; }
            .topbar-nav a { min-height: 44px; padding: 8px 7px; font-size: 12px; }
            main {
                width: calc(100% - 24px); margin: 6px auto 30px;
                padding: 28px 16px 54px; overflow-wrap: anywhere;
            }
            h1 { font-size: clamp(32px, 9vw, 48px); }
            .intro { font-size: 15px; }
            .upload-grid, .status-grid { grid-template-columns: 1fr; }
            .upload-box { min-height: auto; padding: 18px; }
            .upload-help { min-height: 0; }
            input[type=file] { font-size: 13px; }
            .actions { align-items: stretch; flex-direction: column; gap: 12px; }
            .format-note { line-height: 1.5; }
            button { width: 100%; }
        }
        @media (max-width: 360px) {
            .topbar { padding-right: 10px; padding-left: 10px; }
            .brand img { width: 100px; }
            .topbar-nav a { padding-right: 5px; padding-left: 5px; font-size: 11px; }
            main { width: calc(100% - 16px); padding-right: 12px; padding-left: 12px; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <a class="brand" href="<?= site_url('/') ?>"><img src="<?= base_url('img/logo.png') ?>" alt="ARPriceSync"></a>
        <nav class="topbar-nav" aria-label="Menu utama">
            <a href="<?= site_url('/') ?>">Sinkronisasi</a>
            <a href="<?= site_url('references') ?>" aria-current="page">Referensi</a>
        </nav>
    </header>
    <main>
        <div class="eyebrow">Pengaturan data</div>
        <h1>Referensi produk</h1>
        <p class="intro">Unggah dan simpan file referensi di satu tempat. File ini akan digunakan otomatis saat sinkronisasi berikutnya.</p>

        <?php if (! empty($error)): ?>
            <div class="message error" role="alert"><?= esc($error) ?></div>
        <?php endif; ?>
        <?php if (! empty($success)): ?>
            <div class="message success" role="status"><?= esc($success) ?></div>
        <?php endif; ?>

        <div class="status-grid">
            <div class="status <?= $priceReferenceExists ? 'ready' : '' ?>">
                Referensi harga: <?= $priceReferenceExists ? 'Tersimpan' : 'Belum diunggah' ?>
                <span>Kolom Brand, Description, Model, Size, dan SKU.</span>
            </div>
            <div class="status <?= $stockReferenceExists ? 'ready' : '' ?>">
                Referensi stok: <?= $stockReferenceExists ? 'Tersimpan' : 'Belum diunggah' ?>
                <span>Kolom Ref dan SKU.</span>
            </div>
        </div>

        <form action="<?= site_url('references/upload') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="upload-grid">
                <label class="upload-box">
                    <span class="upload-title">Referensi harga</span>
                    <span class="upload-help">Pilih file berisi Brand, Description, Model, Size, dan SKU. File tersimpan di public/referensi.</span>
                    <input type="file" name="price_reference_file" accept=".xlsx,.xls,.csv">
                </label>
                <label class="upload-box">
                    <span class="upload-title">Referensi stok</span>
                    <span class="upload-help">Pilih file berisi Ref dan SKU. File tersimpan di public/referensi.</span>
                    <input type="file" name="stock_reference_file" accept=".xlsx,.xls,.csv">
                </label>
            </div>
            <div class="actions">
                <span class="format-note">Pilih satu atau kedua file · XLSX, XLS, atau CSV · Maks. 10 MB per file</span>
                <button type="submit">Simpan referensi</button>
            </div>
        </form>
    </main>
</body>
</html>
