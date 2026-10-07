<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ARPriceSync — Penyesuaian Harga dan Stok Shopee</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <style>
        :root {
            color-scheme: light;
            --ink: #1f1f1f;
            --muted: #5d5d5d;
            --orange: #ee5a24;
            --orange-dark: #cf4513;
            --panel: #3a3a3a;
            --line: #d4d4d4;
            --surface: #f6f6f6;
            --background: #ebebeb;
        }

        * { box-sizing: border-box; }
        [hidden] { display: none !important; }
        body {
            margin: 0;
            min-height: 100vh;
            background: var(--background);
            color: var(--ink);
            font-family: "Helvetica Neue", Helvetica, Arial, "Segoe UI", sans-serif;
        }

        /* Menu bar */
        .topbar {
            height: 64px;
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 0 max(24px, calc((100vw - 1080px) / 2));
            background: var(--background);
        }
        .brand { display: flex; align-items: center; }
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
        /* Workspace "artboard" */
        main {
            width: min(100% - 40px, 960px);
            margin: 8px auto 70px;
            padding: 56px 48px 76px;
            background: var(--surface);
            border: 1.5px solid #8d8d8d;
            position: relative;
        }
        main::before {
            content: ""; position: absolute; left: 0; bottom: 0; width: 22%; height: 28px;
            background: var(--orange);
        }

        .intro { max-width: 760px; margin-bottom: 34px; position: relative; }
        .eyebrow {
            display: inline-block;
            background: var(--orange); color: #fff; padding: 4px 11px;
            font-size: 13px; font-weight: 900; letter-spacing: .02em; text-transform: uppercase;
        }
        h1 {
            position: relative; display: inline-block;
            margin: 20px 0 14px; padding: 6px 10px;
            font-size: clamp(34px, 6.4vw, 66px); font-weight: 900;
            letter-spacing: -.055em; line-height: 1.02; color: #222;
            outline: 1px solid var(--orange);
        }
        h1::before, h1::after {
            content: ""; position: absolute; width: 8px; height: 8px;
            background: #fff; border: 1px solid var(--orange);
        }
        h1::before { top: -5px; left: -5px; }
        h1::after { bottom: -5px; right: -5px; }
        .intro p { margin: 0; color: var(--muted); font-size: 16px; line-height: 1.65; max-width: 620px; }

        .card { background: transparent; border: 0; border-radius: 0; padding: 0; box-shadow: none; }

        /* Mode selector: tool-options panel */
        .mode-field {
            position: relative; margin: 0 0 18px; padding: 24px 18px 16px;
            border: 2px solid var(--panel); background: #fff; min-width: 0;
        }
        .mode-field legend {
            float: left; width: 100%; padding: 0; margin: -24px 0 12px -18px; width: calc(100% + 36px);
            background: var(--panel); color: #fff; padding: 7px 18px;
            font-size: 13px; font-weight: 900; letter-spacing: -.01em;
        }
        .mode-options { display: flex; flex-wrap: wrap; gap: 12px; clear: both; }
        .mode-option {
            display: inline-flex; align-items: center; gap: 10px; cursor: pointer;
            padding: 9px 16px; border: 2px solid var(--panel); background: var(--surface);
            font-size: 14px; font-weight: 800;
            transition: background-color .16s ease, border-color .16s ease;
        }
        .mode-option:has(input:checked) { border-color: var(--orange); background: #fff1ea; }
        .mode-option input {
            appearance: none; -webkit-appearance: none; margin: 0;
            width: 18px; height: 18px; border: 2px solid var(--panel); background: #fff;
            display: grid; place-items: center; cursor: pointer;
            transition: background-color .16s ease, border-color .16s ease;
        }
        .mode-option input:checked { background: var(--orange); border-color: var(--orange); }
        .mode-option input:checked::after {
            content: ""; width: 9px; height: 5px; margin-top: -2px;
            border-left: 2.5px solid #fff; border-bottom: 2.5px solid #fff; transform: rotate(-45deg);
        }

        .stock-rules {
            margin: 0 0 18px; padding: 18px; border: 2px solid var(--panel); background: #fff;
        }
        .stock-rules h2 { margin: 0 0 6px; font-size: 16px; }
        .stock-rules > p { margin: 0 0 14px; color: var(--muted); font-size: 12px; line-height: 1.5; }
        .stock-rule-row {
            display: grid; grid-template-columns: minmax(110px, 1fr) auto minmax(110px, 1fr) auto;
            align-items: end; gap: 10px; margin-top: 10px;
        }
        .stock-rule-field { display: grid; gap: 5px; color: var(--ink); font-size: 12px; font-weight: 800; }
        .stock-rule-field input {
            width: 100%; min-width: 0; min-height: 44px; padding: 8px 10px; border: 1.5px solid var(--panel);
            background: #fff; color: var(--ink); font: inherit; font-size: 14px;
            transition: border-color .16s ease, box-shadow .16s ease;
        }
        .stock-rule-field input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px rgb(238 90 36 / 16%); }
        .stock-rule-arrow { padding-bottom: 11px; font-weight: 900; color: var(--orange-dark); }
        .stock-rule-row button, .add-stock-rule {
            padding: 9px 12px; box-shadow: none; font-size: 12px;
        }
        .remove-stock-rule { background: var(--panel); }
        .add-stock-rule { margin-top: 14px; }

        /* Upload panels */
        .upload-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 18px; }
        .upload-box {
            display: block; padding: 22px; min-height: 186px; cursor: pointer;
            background: var(--panel); color: #f1f1f1;
            border: 3px solid var(--orange); border-radius: 0;
            transition: background-color .16s ease, border-color .16s ease, transform .16s ease, box-shadow .16s ease;
        }
        .upload-box:hover, .upload-box:focus-within { background: #2c2c2c; border-color: var(--orange-dark); }
        @media (hover: hover) {
            .upload-box:hover { transform: translateY(-2px); box-shadow: 0 6px 14px rgb(31 31 31 / 12%); }
        }
        .upload-icon {
            display: grid; place-items: center; width: 40px; height: 40px; margin-bottom: 16px;
            color: #fff; background: var(--orange); font-weight: 900; font-size: 15px;
            border-radius: 50%;
        }
        .upload-title { display: block; font-size: 17px; font-weight: 900; letter-spacing: -.02em; margin-bottom: 5px; }
        .upload-help { display: block; min-height: 36px; color: #cfcfcf; font-size: 12px; line-height: 1.5; }
        input[type=file] { display: block; width: 100%; min-width: 0; margin-top: 13px; color: #e6e6e6; font-size: 12px; }
        input[type=file]::file-selector-button {
            border: 0; background: var(--orange); color: #fff; font-weight: 800;
            padding: 6px 12px; margin-right: 10px; cursor: pointer;
            transition: background-color .16s ease;
        }
        input[type=file]::file-selector-button:hover { background: var(--orange-dark); }
        .error {
            margin-bottom: 18px; padding: 13px 15px; border: 2px solid var(--orange);
            background: #fff; color: var(--orange-dark); font-size: 14px; font-weight: 700; line-height: 1.5;
        }
        .notice {
            margin-bottom: 18px; padding: 13px 15px; border: 2px solid var(--panel);
            background: #fff; color: var(--ink); font-size: 14px; font-weight: 700; line-height: 1.5;
        }
        .notice.warning { border-color: #b7791f; color: #744210; }

        .steps {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px;
            margin: 28px 0 26px; padding: 0; list-style: none;
        }
        .steps li { display: flex; gap: 10px; color: #3b3b3b; font-size: 12px; line-height: 1.45; font-weight: 600; }
        .step-number {
            flex: 0 0 24px; width: 24px; height: 24px; display: grid; place-items: center;
            border-radius: 4px; background: #fff; color: var(--orange); border: 1.5px solid var(--orange);
            font-size: 12px; font-weight: 900;
        }

        .actions { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .format-note { color: var(--muted); font-size: 12px; font-weight: 600; }
        button {
            min-height: 44px; border: 0; border-radius: 4px; padding: 14px 24px; background: var(--orange); color: #fff;
            font: inherit; font-size: 14px; font-weight: 900; letter-spacing: -.01em; cursor: pointer;
            box-shadow: 4px 4px 0 var(--panel);
            transition: transform .12s, box-shadow .12s, background .12s;
        }
        button:hover { background: var(--orange-dark); }
        button:active { transform: translate(3px, 3px); box-shadow: 1px 1px 0 var(--panel); }
        button:focus-visible, input:focus-visible { outline: 3px solid var(--panel); outline-offset: 2px; }
        .mode-option:focus-within { border-color: var(--orange); }

        .hint {
            margin-top: 22px; padding: 15px 17px; background: #fff;
            border-left: 5px solid var(--orange);
            color: #4a4a4a; font-size: 12px; line-height: 1.65;
        }
        .hint strong { color: var(--ink); }

        @media (max-width: 650px) {
            .topbar {
                min-height: 64px; height: auto; padding: 8px 14px; gap: 8px;
                justify-content: space-between;
            }
            .brand { min-width: 0; }
            .brand img { width: clamp(105px, 34vw, 128px); }
            .topbar-nav { flex: 0 0 auto; gap: 2px; margin-left: 0; }
            .topbar-nav a { min-height: 44px; padding: 8px 7px; font-size: 12px; }
            main {
                width: calc(100% - 24px); margin: 6px auto 30px;
                padding: 28px 16px 54px; overflow-wrap: anywhere;
            }
            .intro { margin-bottom: 24px; }
            h1 { max-width: 100%; font-size: clamp(32px, 9vw, 48px); }
            .intro p { font-size: 15px; }
            .mode-field { padding-right: 12px; padding-left: 12px; }
            .mode-field legend { margin-left: -12px; width: calc(100% + 24px); padding-right: 12px; padding-left: 12px; }
            .mode-option { flex: 1 1 120px; justify-content: center; padding: 10px 12px; }
            .stock-rules { padding: 14px 12px; }
            .steps { grid-template-columns: 1fr; gap: 11px; }
            .upload-grid { grid-template-columns: minmax(0, 1fr); gap: 12px; }
            .upload-box { min-width: 0; min-height: auto; padding: 18px; }
            .upload-help { min-height: 0; }
            input[type=file] { font-size: 13px; }
            input[type=file]::file-selector-button { min-height: 40px; }
            .actions { align-items: stretch; flex-direction: column; gap: 12px; }
            .format-note { line-height: 1.5; }
            button { width: 100%; }
            .stock-rule-row { grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); gap: 8px; }
            .stock-rule-row .remove-stock-rule { grid-column: 1 / -1; }
            .stock-rule-arrow { text-align: center; }
            .hint { padding: 13px; }
        }
        @media (max-width: 360px) {
            .topbar { padding-right: 10px; padding-left: 10px; }
            .brand img { width: 100px; }
            .topbar-nav a { padding-right: 5px; padding-left: 5px; font-size: 11px; }
            main { width: calc(100% - 16px); padding-right: 12px; padding-left: 12px; }
            .stock-rule-field { font-size: 11px; }
            .stock-rule-field input { padding-right: 6px; padding-left: 6px; font-size: 16px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition: none !important; animation: none !important; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand">
            <img src="<?= base_url('img/logo.png') ?>" alt="ARPriceSync">
        </div>
        <nav class="topbar-nav" aria-label="Menu utama">
            <a href="<?= site_url('/') ?>" aria-current="page">Sinkronisasi</a>
            <a href="<?= site_url('references') ?>">Referensi</a>
        </nav>
    </header>

    <main>
        <section class="intro">
            <div class="eyebrow">Alat penyesuaian produk</div>
            <h1>Sesuaikan produk Shopee dalam hitungan detik.</h1>
            <p>Pilih data yang ingin diperbarui, lalu cocokkan harga dan/atau stok otomatis berdasarkan SKU.</p>
        </section>

        <section class="card" aria-label="Unggah file">
            <?php if (! empty($error)): ?>
                <div class="error" role="alert"><?= esc($error) ?></div>
            <?php endif; ?>
            <div id="sync-notice" class="notice" role="status" aria-live="polite" hidden></div>

            <form id="sync-form" action="<?= site_url('sync') ?>" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <fieldset class="mode-field">
                    <legend>Data yang diperbarui</legend>
                    <div class="mode-options">
                        <label class="mode-option">
                            <input type="checkbox" id="update-price" checked>
                            <span>Harga</span>
                        </label>
                        <label class="mode-option">
                            <input type="checkbox" id="update-stock">
                            <span>Stok</span>
                        </label>
                    </div>
                    <input type="hidden" id="sync-mode" name="mode" value="price">
                </fieldset>
                <section class="stock-rules" id="stock-rules" aria-labelledby="stock-rules-title" hidden>
                    <h2 id="stock-rules-title">Aturan perubahan stok</h2>
                    <p>Opsional. Jika stok di bawah ambang, ganti dengan nilai yang ditentukan. Jika beberapa aturan cocok, ambang terkecil yang dipakai.</p>
                    <div id="stock-rule-list">
                        <div class="stock-rule-row">
                            <label class="stock-rule-field">
                                Jika stok di bawah
                                <input type="number" name="stock_rules[0][threshold]" min="0" step="1" inputmode="numeric" placeholder="Contoh: 1000">
                            </label>
                            <span class="stock-rule-arrow" aria-hidden="true">→</span>
                            <label class="stock-rule-field">
                                Ubah menjadi
                                <input type="number" name="stock_rules[0][replacement]" min="0" step="1" inputmode="numeric" placeholder="Contoh: 10">
                            </label>
                            <button class="remove-stock-rule" type="button" aria-label="Hapus aturan stok">Hapus</button>
                        </div>
                    </div>
                    <button class="add-stock-rule" id="add-stock-rule" type="button">Tambah aturan</button>
                </section>
                <section class="stock-rules" id="price-rules" aria-labelledby="price-rules-title" hidden>
                    <h2 id="price-rules-title">Aturan perubahan harga</h2>
                    <p>Opsional. Jika harga di atas ambang, bulatkan ke kelipatan terdekat. Jika beberapa aturan cocok, ambang tertinggi yang dipakai.</p>
                    <div id="price-rounding-rule-list">
                        <div class="stock-rule-row">
                            <label class="stock-rule-field">
                                Jika harga di atas
                                <input type="number" name="price_rounding_rules[0][threshold]" min="0" step="1" inputmode="numeric" placeholder="Contoh: 2000">
                            </label>
                            <span class="stock-rule-arrow" aria-hidden="true">→</span>
                            <label class="stock-rule-field">
                                Bulatkan ke kelipatan
                                <input type="number" name="price_rounding_rules[0][unit]" min="1" step="1" inputmode="numeric" placeholder="Contoh: 100">
                            </label>
                            <button class="remove-stock-rule" type="button" aria-label="Hapus aturan pembulatan harga">Hapus</button>
                        </div>
                    </div>
                    <button class="add-stock-rule" id="add-price-rounding-rule" type="button">Tambah aturan pembulatan</button>
                    <label class="stock-rule-field" style="margin-top: 18px; max-width: 280px;">
                        Kenaikan harga setelah pembulatan (%)
                        <input type="number" name="price_increase_percent" min="0" step="any" inputmode="decimal" placeholder="Contoh: 5">
                    </label>
                    <label class="stock-rule-field" style="margin-top: 14px; max-width: 280px;">
                        Pembulatan akhir setelah kenaikan (kelipatan)
                        <input type="number" name="price_final_rounding_unit" min="1" step="1" inputmode="numeric" placeholder="Opsional, contoh: 100">
                    </label>
                </section>
                <div class="upload-grid">
                    <label class="upload-box">
                        <span class="upload-icon">01</span>
                        <span class="upload-title">Template</span>
                        <span class="upload-help">Template Shopee yang ingin diperbarui.</span>
                        <input type="file" name="template_file" accept=".xlsx,.xls,.csv" required>
                    </label>
                    <label class="upload-box" data-source="price">
                        <span class="upload-icon">03</span>
                        <span class="upload-title">Harga raw</span>
                        <span class="upload-help">Kolom Brand, Description, Model, Size; harga dibaca dari kolom I.</span>
                        <input type="file" name="price_file" accept=".xlsx,.xls,.csv">
                    </label>
                    <label class="upload-box" data-source="stock" hidden>
                        <span class="upload-icon">04</span>
                        <span class="upload-title">Stok raw</span>
                        <span class="upload-help">File stok raw; Ref di kolom C dan jumlah pada kolom Quantity.</span>
                        <input type="file" name="stock_file" accept=".xlsx,.xls,.csv" disabled>
                    </label>
                </div>

                <ol class="steps">
                    <li><span class="step-number">1</span><span>Pilih data lalu unggah file sumber dan template.</span></li>
                    <li><span class="step-number">2</span><span>Data raw dicocokkan melalui referensi produk.</span></li>
                    <li><span class="step-number">3</span><span>Unduh template Shopee yang telah diperbarui.</span></li>
                </ol>

                <div class="actions">
                    <span class="format-note">Format: XLSX, XLS, atau CSV · Maks. 10 MB per file</span>
                    <button type="submit">Cocokkan &amp; unduh hasil</button>
                </div>
            </form>

            <div class="hint">
                <strong>Catatan:</strong> Kolom SKU dapat bernama SKU, Seller SKU, SKU Model, atau Kode SKU;
                kolom harga dapat bernama Harga, Harga (Rp), Harga Jual, atau Price; kolom stok dapat bernama
                Stok, Stock, Qty, atau Quantity. Unggah referensi harga/stok melalui menu Referensi.
                Untuk stok raw, kolom Ref dibaca dari kolom C dan jumlah stok dari kolom Quantity;
                stok pecahan dibulatkan ke bawah dan stok negatif diubah menjadi 0. Header dicari pada 30 baris pertama.
                SKU yang tidak ditemukan akan mempertahankan nilai lama.
            </div>
        </section>
    </main>
    <script>
        const priceCheckbox = document.getElementById('update-price');
        const stockCheckbox = document.getElementById('update-stock');
        const modeInput = document.getElementById('sync-mode');
        const sourceFields = document.querySelectorAll('[data-source]');
        const syncForm = document.getElementById('sync-form');
        const syncNotice = document.getElementById('sync-notice');
        const stockRulesContainer = document.getElementById('stock-rules');
        const stockRuleList = document.getElementById('stock-rule-list');
        const addStockRuleButton = document.getElementById('add-stock-rule');
        const priceRulesContainer = document.getElementById('price-rules');
        const priceRoundingRuleList = document.getElementById('price-rounding-rule-list');
        const addPriceRoundingRuleButton = document.getElementById('add-price-rounding-rule');

        function updateSourceFields() {
            const updatePrice = priceCheckbox.checked;
            const updateStock = stockCheckbox.checked;

            if (!updatePrice && !updateStock) {
                priceCheckbox.setCustomValidity('Pilih harga, stok, atau keduanya untuk diperbarui.');
            } else {
                priceCheckbox.setCustomValidity('');
            }

            modeInput.value = updatePrice && updateStock
                ? 'both'
                : (updatePrice ? 'price' : 'stock');
            stockRulesContainer.hidden = !updateStock;
            priceRulesContainer.hidden = !updatePrice;

            sourceFields.forEach((field) => {
                const input = field.querySelector('input[type="file"]');
                const enabled = field.dataset.source === 'price' ? updatePrice : updateStock;
                field.hidden = !enabled;
                input.disabled = !enabled;
                input.required = enabled;
            });
        }

        priceCheckbox.addEventListener('change', updateSourceFields);
        stockCheckbox.addEventListener('change', updateSourceFields);
        updateSourceFields();

        function updateStockRuleNames() {
            stockRuleList.querySelectorAll('.stock-rule-row').forEach((row, index) => {
                row.querySelectorAll('input').forEach((input) => {
                    const field = input.name.endsWith('[threshold]') ? 'threshold' : 'replacement';
                    input.name = `stock_rules[${index}][${field}]`;
                });
            });
        }

        addStockRuleButton.addEventListener('click', () => {
            const row = stockRuleList.querySelector('.stock-rule-row').cloneNode(true);
            row.querySelectorAll('input').forEach((input) => {
                input.value = '';
            });
            stockRuleList.append(row);
            updateStockRuleNames();
        });

        stockRuleList.addEventListener('click', (event) => {
            const removeButton = event.target.closest('.remove-stock-rule');
            if (!removeButton) {
                return;
            }

            removeButton.closest('.stock-rule-row').remove();
            updateStockRuleNames();
        });

        function updatePriceRoundingRuleNames() {
            priceRoundingRuleList.querySelectorAll('.stock-rule-row').forEach((row, index) => {
                row.querySelectorAll('input').forEach((input) => {
                    const field = input.name.endsWith('[threshold]') ? 'threshold' : 'unit';
                    input.name = `price_rounding_rules[${index}][${field}]`;
                });
            });
        }

        addPriceRoundingRuleButton.addEventListener('click', () => {
            const row = priceRoundingRuleList.querySelector('.stock-rule-row').cloneNode(true);
            row.querySelectorAll('input').forEach((input) => {
                input.value = '';
            });
            priceRoundingRuleList.append(row);
            updatePriceRoundingRuleNames();
        });

        priceRoundingRuleList.addEventListener('click', (event) => {
            const removeButton = event.target.closest('.remove-stock-rule');
            if (!removeButton) {
                return;
            }

            removeButton.closest('.stock-rule-row').remove();
            updatePriceRoundingRuleNames();
        });

        syncForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            syncNotice.hidden = true;
            syncNotice.classList.remove('warning');

            try {
                const response = await fetch(syncForm.action, {
                    method: 'POST',
                    body: new FormData(syncForm),
                });

                const disposition = response.headers.get('Content-Disposition') || '';
                if (!response.ok || !disposition.toLowerCase().includes('attachment')) {
                    const responseText = await response.text();
                    const responseDocument = new DOMParser().parseFromString(responseText, 'text/html');
                    const serverError = responseDocument.querySelector('.error')?.textContent?.trim();
                    throw new Error(serverError || 'File gagal diproses. Periksa kembali file yang diunggah lalu coba lagi.');
                }

                const blob = await response.blob();
                const downloadUrl = URL.createObjectURL(blob);
                const downloadLink = document.createElement('a');
                downloadLink.href = downloadUrl;
                downloadLink.download = 'shopee_produk_diperbarui.xlsx';
                downloadLink.click();
                window.setTimeout(() => URL.revokeObjectURL(downloadUrl), 1000);

                const warningCount = Number(response.headers.get('X-PriceSync-Warnings') || 0);
                syncNotice.textContent = warningCount > 0
                    ? `File berhasil diunduh. Peringatan: ${warningCount} baris referensi dengan data produk atau SKU yang tidak lengkap diabaikan.`
                    : 'File berhasil diperbarui dan diunduh.';
                syncNotice.classList.toggle('warning', warningCount > 0);
                syncNotice.hidden = false;
                if (warningCount > 0) {
                    window.alert(syncNotice.textContent);
                }
            } catch (error) {
                syncNotice.textContent = error instanceof Error
                    ? error.message
                    : 'Terjadi kesalahan saat memproses file.';
                syncNotice.classList.add('warning');
                syncNotice.hidden = false;
                window.alert(syncNotice.textContent);
            }
        });
    </script>
</body>
</html>