<?php

namespace App\Controllers;

use App\Libraries\PriceSynchronizer;
use RuntimeException;
use Throwable;

class PriceSync extends BaseController
{
    public function index(): string
    {
        return view('price_sync', [
            'error' => session()->getFlashdata('error'),
        ]);
    }

    public function references(): string
    {
        return view('references', [
            'error' => session()->getFlashdata('error'),
            'success' => session()->getFlashdata('success'),
            'priceReferenceExists' => $this->findSavedReference('harga') !== null,
            'stockReferenceExists' => $this->findSavedReference('stok') !== null,
        ]);
    }

    public function saveReferences()
    {
        $priceFile = $this->request->getFile('price_reference_file');
        $stockFile = $this->request->getFile('stock_reference_file');
        $rules = [];

        if ($priceFile !== null && $priceFile->getError() !== UPLOAD_ERR_NO_FILE) {
            $rules['price_reference_file'] = [
                'label' => 'Referensi harga',
                'rules' => 'uploaded[price_reference_file]|max_size[price_reference_file,10240]|ext_in[price_reference_file,xlsx,xls,csv]',
            ];
        }
        if ($stockFile !== null && $stockFile->getError() !== UPLOAD_ERR_NO_FILE) {
            $rules['stock_reference_file'] = [
                'label' => 'Referensi stok',
                'rules' => 'uploaded[stock_reference_file]|max_size[stock_reference_file,10240]|ext_in[stock_reference_file,xlsx,xls,csv]',
            ];
        }
        if ($rules === []) {
            return redirect()->back()->with('error', 'Pilih minimal satu file referensi untuk diunggah.');
        }
        if (! $this->validate($rules)) {
            return redirect()->back()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $directory = FCPATH . 'referensi' . DIRECTORY_SEPARATOR;
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            log_message('error', 'Could not create PriceSync reference directory: {path}', [
                'path' => $directory,
            ]);

            return redirect()->back()->with('error', 'Folder penyimpanan referensi tidak dapat dibuat.');
        }

        try {
            foreach ([
                ['file' => $priceFile, 'name' => 'harga'],
                ['file' => $stockFile, 'name' => 'stok'],
            ] as $reference) {
                $file = $reference['file'];
                if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $extension = strtolower($file->getClientExtension());
                $file->move($directory, $reference['name'] . '.' . $extension, true);
                $this->removeOldReferenceVersions($directory, $reference['name'], $extension);
            }
        } catch (Throwable $exception) {
            log_message('error', 'Could not save PriceSync reference file: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return redirect()->back()->with('error', 'File referensi gagal disimpan. Silakan coba lagi.');
        }

        return redirect()->back()->with('success', 'File referensi berhasil disimpan.');
    }

    public function process()
    {
        $mode = $this->request->getPost('mode');
        if (! is_string($mode)) {
            $mode = '';
        }

        $rules = [
            'mode' => [
                'label' => 'Mode penyesuaian',
                'rules' => 'required|in_list[both,price,stock]',
            ],
            'template_file' => [
                'label' => 'File template Shopee',
                'rules' => 'uploaded[template_file]|max_size[template_file,10240]|ext_in[template_file,xlsx,xls,csv]',
            ],
        ];

        $updatePrice = in_array($mode, ['both', 'price'], true);
        $updateStock = in_array($mode, ['both', 'stock'], true);
        $stockRules = [];
        $priceRoundingRules = [];
        $priceIncreasePercent = 0.0;
        $priceFinalRoundingUnit = null;
        if ($updatePrice) {
            try {
                $priceRoundingRules = $this->parsePriceRoundingRules(
                    $this->request->getPost('price_rounding_rules')
                );
                $priceIncreasePercent = $this->parsePriceIncrease(
                    $this->request->getPost('price_increase_percent')
                );
                $priceFinalRoundingUnit = $this->parsePriceFinalRoundingUnit(
                    $this->request->getPost('price_final_rounding_unit')
                );
            } catch (RuntimeException $exception) {
                return redirect()->back()->with('error', $exception->getMessage());
            }
        }
        if ($updateStock) {
            try {
                $stockRules = $this->parseStockRules($this->request->getPost('stock_rules'));
            } catch (RuntimeException $exception) {
                return redirect()->back()->with('error', $exception->getMessage());
            }
        }

        $priceReferencePath = $updatePrice ? $this->findSavedReference('harga') : null;
        if ($updatePrice && $priceReferencePath === null) {
            return redirect()->back()->with(
                'error',
                'Referensi harga belum tersedia. Unggah terlebih dahulu melalui menu Referensi.'
            );
        }
        $stockReferencePath = $updateStock ? $this->findSavedReference('stok') : null;
        if ($updateStock && $stockReferencePath === null) {
            return redirect()->back()->with(
                'error',
                'Referensi stok belum tersedia. Unggah terlebih dahulu melalui menu Referensi.'
            );
        }

        if ($updatePrice) {
            $rules['price_file'] = [
                'label' => 'File harga raw',
                'rules' => 'uploaded[price_file]|max_size[price_file,10240]|ext_in[price_file,xlsx,xls,csv]',
            ];
        }
        if ($updateStock) {
            $rules['stock_file'] = [
                'label' => 'File stok raw',
                'rules' => 'uploaded[stock_file]|max_size[stock_file,10240]|ext_in[stock_file,xlsx,xls,csv]',
            ];
        }

        if (! $this->validate($rules)) {
            return redirect()->back()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $templateFile = $this->request->getFile('template_file');
        $priceFile = $updatePrice ? $this->request->getFile('price_file') : null;
        $stockFile = $updateStock ? $this->request->getFile('stock_file') : null;
        $outputPath = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR
            . 'pricesync_' . bin2hex(random_bytes(12)) . '.xlsx';

        try {
            $result = (new PriceSynchronizer())->synchronize(
                $templateFile->getTempName(),
                $priceFile?->getTempName(),
                $outputPath,
                $mode,
                $stockFile?->getTempName(),
                $stockReferencePath,
                $stockRules,
                $priceReferencePath,
                $priceRoundingRules,
                $priceIncreasePercent,
                $priceFinalRoundingUnit
            );
        } catch (RuntimeException $exception) {
            if (is_file($outputPath)) {
                unlink($outputPath);
            }

            return redirect()->back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            if (is_file($outputPath)) {
                unlink($outputPath);
            }

            log_message('error', 'Price synchronization failed: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return redirect()->back()->with(
                'error',
                'File tidak dapat diproses. Pastikan file Excel tidak rusak dan coba unggah kembali.'
            );
        }

        register_shutdown_function(static function () use ($outputPath): void {
            if (is_file($outputPath)) {
                unlink($outputPath);
            }
        });

        $response = $this->response->download($outputPath, null, true);
        $response->setFileName('shopee_produk_diperbarui.xlsx');
        $response->setHeader('X-PriceSync-Matched', (string) $result['matched']);
        $response->setHeader('X-PriceSync-Unmatched', (string) $result['unmatched']);
        $response->setHeader('X-PriceSync-Warnings', (string) ($result['warnings'] ?? 0));

        return $response;
    }

    private function findSavedReference(string $name): ?string
    {
        $directory = FCPATH . 'referensi' . DIRECTORY_SEPARATOR;
        foreach (['xlsx', 'xls', 'csv'] as $extension) {
            $path = $directory . $name . '.' . $extension;
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function removeOldReferenceVersions(string $directory, string $name, string $keepExtension): void
    {
        foreach (['xlsx', 'xls', 'csv'] as $extension) {
            if ($extension === $keepExtension) {
                continue;
            }

            $path = $directory . $name . '.' . $extension;
            if (is_file($path) && ! unlink($path)) {
                log_message('error', 'Could not remove outdated PriceSync reference file: {path}', [
                    'path' => $path,
                ]);
            }
        }
    }

    /**
     * @return list<array{threshold: int, replacement: int}>
     */
    private function parseStockRules(mixed $input): array
    {
        if ($input === null || $input === []) {
            return [];
        }
        if (! is_array($input)) {
            throw new RuntimeException('Aturan stok harus dikirim sebagai daftar aturan yang valid.');
        }

        $rules = [];
        $thresholds = [];
        foreach ($input as $rule) {
            if (! is_array($rule)) {
                throw new RuntimeException('Format aturan stok tidak valid.');
            }

            $thresholdText = $rule['threshold'] ?? '';
            $replacementText = $rule['replacement'] ?? '';
            if (! is_scalar($thresholdText) || ! is_scalar($replacementText)) {
                throw new RuntimeException('Ambang dan stok pengganti harus berupa bilangan bulat.');
            }

            $thresholdText = trim((string) $thresholdText);
            $replacementText = trim((string) $replacementText);
            if ($thresholdText === '' && $replacementText === '') {
                continue;
            }
            if ($thresholdText === '' || $replacementText === '') {
                throw new RuntimeException('Isi ambang dan stok pengganti pada setiap aturan stok.');
            }

            $threshold = $this->parseNonNegativeInteger($thresholdText);
            $replacement = $this->parseNonNegativeInteger($replacementText);
            if ($threshold === null || $replacement === null) {
                throw new RuntimeException('Ambang dan stok pengganti harus berupa bilangan bulat nol atau lebih.');
            }
            if (isset($thresholds[$threshold])) {
                throw new RuntimeException('Setiap ambang stok hanya boleh digunakan satu kali.');
            }

            $thresholds[$threshold] = true;
            $rules[] = ['threshold' => $threshold, 'replacement' => $replacement];
        }

        return $rules;
    }

    private function parseNonNegativeInteger(string $value): ?int
    {
        if (! preg_match('/^\d+$/', $value)) {
            return null;
        }

        $normalized = ltrim($value, '0');
        $normalized = $normalized === '' ? '0' : $normalized;
        $maximum = (string) PHP_INT_MAX;
        if (strlen($normalized) > strlen($maximum)
            || (strlen($normalized) === strlen($maximum) && strcmp($normalized, $maximum) > 0)) {
            return null;
        }

        return (int) $normalized;
    }

    /**
     * @return list<array{threshold: int, unit: int}>
     */
    private function parsePriceRoundingRules(mixed $input): array
    {
        if ($input === null || $input === []) {
            return [];
        }
        if (! is_array($input)) {
            throw new RuntimeException('Aturan pembulatan harga harus berupa daftar aturan yang valid.');
        }

        $rules = [];
        $thresholds = [];
        foreach ($input as $rule) {
            if (! is_array($rule)) {
                throw new RuntimeException('Format aturan pembulatan harga tidak valid.');
            }

            $thresholdText = $rule['threshold'] ?? '';
            $unitText = $rule['unit'] ?? '';
            if (! is_scalar($thresholdText) || ! is_scalar($unitText)) {
                throw new RuntimeException('Ambang dan kelipatan pembulatan harus berupa bilangan bulat.');
            }

            $thresholdText = trim((string) $thresholdText);
            $unitText = trim((string) $unitText);
            if ($thresholdText === '' && $unitText === '') {
                continue;
            }
            if ($thresholdText === '' || $unitText === '') {
                throw new RuntimeException('Isi ambang dan kelipatan pembulatan pada setiap aturan harga.');
            }

            $threshold = $this->parseNonNegativeInteger($thresholdText);
            $unit = $this->parseNonNegativeInteger($unitText);
            if ($threshold === null || $unit === null || $unit === 0) {
                throw new RuntimeException('Ambang harus nol atau lebih dan kelipatan pembulatan harus bilangan bulat positif.');
            }
            if (isset($thresholds[$threshold])) {
                throw new RuntimeException('Setiap ambang pembulatan harga hanya boleh digunakan satu kali.');
            }

            $thresholds[$threshold] = true;
            $rules[] = ['threshold' => $threshold, 'unit' => $unit];
        }

        return $rules;
    }

    private function parsePriceIncrease(mixed $input): float
    {
        if ($input === null || $input === '') {
            return 0.0;
        }
        if (! is_scalar($input) || ! is_numeric($input)) {
            throw new RuntimeException('Persentase kenaikan harga harus berupa angka nol atau lebih.');
        }

        $increase = (float) $input;
        if (! is_finite($increase) || $increase < 0) {
            throw new RuntimeException('Persentase kenaikan harga harus berupa angka nol atau lebih.');
        }

        return $increase;
    }

    private function parsePriceFinalRoundingUnit(mixed $input): ?int
    {
        if ($input === null || $input === '') {
            return null;
        }
        if (! is_scalar($input)) {
            throw new RuntimeException('Kelipatan pembulatan akhir harus berupa bilangan bulat positif.');
        }

        $unit = $this->parseNonNegativeInteger(trim((string) $input));
        if ($unit === null || $unit === 0) {
            throw new RuntimeException('Kelipatan pembulatan akhir harus berupa bilangan bulat positif.');
        }

        return $unit;
    }
}
