<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class PriceSynchronizer
{
    private const HEADER_SCAN_LIMIT = 30;

    private const SKU_HEADERS = [
        'sku',
        'skupenjual',
        'sellersku',
        'sellingsku',
        'sellerproductsku',
        'kodesku',
        'skuproduk',
        'skumodel',
        'modelsku',
        'skuanak',
        'childsku',
        'variantsku',
        'variationsku',
        'skuinduk',
        'skuprodukinduk',
        'parentsku',
        'productsku',
        'itemsku',
    ];

    private const PRICE_HEADERS = [
        'harga',
        'hargajual',
        'hargabaru',
        'hargaproduk',
        'price',
        'sellingprice',
        'newprice',
        'productprice',
        'normalprice',
        'originalprice',
        'hargaasli',
    ];

    private const STOCK_HEADERS = [
        'stok',
        'stock',
        'qty',
        'quantity',
        'availablestock',
        'availablequantity',
        'availableqty',
        'stockquantity',
        'jumlahstok',
        'inventory',
    ];

    /**
     * @return array{matched: int, unmatched: int, total: int, warnings?: int}
     * @param list<array{threshold: int, replacement: int}> $stockRules
     * @param list<array{threshold: int, unit: int}> $priceRoundingRules
     */
    public function synchronize(
        string $templatePath,
        ?string $priceListPath,
        string $outputPath,
        string $mode = 'price',
        ?string $stockListPath = null,
        ?string $stockReferencePath = null,
        array $stockRules = [],
        ?string $priceReferencePath = null,
        array $priceRoundingRules = [],
        float $priceIncreasePercent = 0,
        ?int $priceFinalRoundingUnit = null
    ): array {
        if (! in_array($mode, ['both', 'price', 'stock'], true)) {
            throw new RuntimeException('Mode penyesuaian tidak valid.');
        }

        $updatePrice = $mode === 'both' || $mode === 'price';
        $updateStock = $mode === 'both' || $mode === 'stock';
        if ($updatePrice && $priceListPath === null) {
            throw new RuntimeException('File daftar harga wajib diunggah untuk mode penyesuaian harga.');
        }
        if ($updateStock && $stockListPath === null) {
            throw new RuntimeException('File daftar stok wajib diunggah untuk mode penyesuaian stok.');
        }

        $template = $this->loadSpreadsheet($templatePath);
        $priceList = $priceListPath === null ? null : $this->loadSpreadsheet($priceListPath);
        $stockList = $stockListPath === null ? null : $this->loadSpreadsheet($stockListPath);
        $stockReference = $stockReferencePath === null ? null : $this->loadSpreadsheet($stockReferencePath);
        $priceReference = $priceReferencePath === null ? null : $this->loadSpreadsheet($priceReferencePath);

        try {
            $warnings = 0;
            if (! $updatePrice) {
                $priceMap = [];
            } elseif ($priceReference === null) {
                $priceMap = $this->makeValueMap($priceList, self::PRICE_HEADERS, 'daftar harga', 'harga');
            } else {
                $rawPrice = $this->makeRawPriceMap($priceList, $priceReference);
                $priceMap = $rawPrice['values'];
                $warnings += $rawPrice['warnings'];
            }
            if (! $updateStock) {
                $stockMap = [];
            } elseif ($stockReference === null) {
                $stockMap = $this->makeValueMap($stockList, self::STOCK_HEADERS, 'daftar stok', 'stok');
            } else {
                $rawStock = $this->makeRawStockMap($stockList, $stockReference);
                $stockMap = $rawStock['values'];
                $warnings += $rawStock['warnings'];
            }
            if ($updatePrice) {
                $priceMap = $this->applyPriceRules(
                    $priceMap,
                    $priceRoundingRules,
                    $priceIncreasePercent,
                    $priceFinalRoundingUnit
                );
            }
            if ($updateStock && $stockRules !== []) {
                $stockMap = $this->applyStockRules($stockMap, $stockRules);
            }
            $templateLayout = $this->findTemplateLayout($template, $updatePrice, $updateStock);
            $sheet = $templateLayout['sheet'];

            $highestRow = $sheet->getHighestDataRow();
            $matched = 0;
            $unmatched = 0;

            for ($row = $templateLayout['row'] + 1; $row <= $highestRow; $row++) {
                $sku = $this->normalizeSku(
                    $sheet->getCell([$templateLayout['skuColumn'], $row])->getFormattedValue()
                );

                if ($sku === '') {
                    continue;
                }

                $priceExists = ! $updatePrice || array_key_exists($sku, $priceMap);
                $stockExists = ! $updateStock || array_key_exists($sku, $stockMap);
                if ($priceExists && $stockExists) {
                    $matched++;
                } else {
                    $unmatched++;
                }
                if ($updatePrice && array_key_exists($sku, $priceMap)) {
                    $sheet->getCell([$templateLayout['priceColumn'], $row])->setValue($priceMap[$sku]);
                }
                if ($updateStock && array_key_exists($sku, $stockMap)) {
                    $sheet->getCell([$templateLayout['stockColumn'], $row])->setValue($stockMap[$sku]);
                }
            }

            (new Xlsx($template))->save($outputPath);

            $result = [
                'matched' => $matched,
                'unmatched' => $unmatched,
                'total' => $matched + $unmatched,
            ];
            if ($warnings > 0) {
                $result['warnings'] = $warnings;
            }

            return $result;
        } finally {
            $template->disconnectWorksheets();
            $priceList?->disconnectWorksheets();
            $stockList?->disconnectWorksheets();
            $stockReference?->disconnectWorksheets();
            $priceReference?->disconnectWorksheets();
        }
    }

    /**
     * @param list<array{threshold: int, unit: int}> $roundingRules
     * @return array<string, int|float>
     */
    private function applyPriceRules(
        array $priceMap,
        array $roundingRules,
        float $increasePercent,
        ?int $finalRoundingUnit
    ): array
    {
        foreach ($priceMap as $sku => $price) {
            $selectedRule = null;
            foreach ($roundingRules as $rule) {
                if ($price > $rule['threshold']
                    && ($selectedRule === null || $rule['threshold'] > $selectedRule['threshold'])) {
                    $selectedRule = $rule;
                }
            }

            if ($selectedRule !== null) {
                $price = ceil($price / $selectedRule['unit']) * $selectedRule['unit'];
            }
            if ($increasePercent > 0) {
                $price *= 1 + ($increasePercent / 100);
            }
            if ($finalRoundingUnit !== null) {
                $price = ceil($price / $finalRoundingUnit) * $finalRoundingUnit;
            }

            if (! is_finite((float) $price)) {
                throw new RuntimeException('Hasil perubahan harga untuk SKU "' . $sku . '" terlalu besar.');
            }

            $nearestInteger = round($price);
            $priceMap[$sku] = abs($price - $nearestInteger) < 0.000001
                ? (int) $nearestInteger
                : $price;
        }

        return $priceMap;
    }

    /**
     * @return array{values: array<string, int|float>, warnings: int}
     */
    private function makeRawPriceMap(?Spreadsheet $priceList, ?Spreadsheet $priceReference): array
    {
        if ($priceList === null || $priceReference === null) {
            throw new RuntimeException('File harga raw dan referensi harga wajib diunggah.');
        }

        $referenceLayout = $this->findProductIdentityLayout($priceReference, true, 'referensi harga');
        $referenceSheet = $referenceLayout['sheet'];
        $skuByProduct = [];
        $warnings = 0;
        for ($row = $referenceLayout['row'] + 1; $row <= $referenceSheet->getHighestDataRow(); $row++) {
            $key = $this->productIdentity($referenceSheet, $referenceLayout['columns'], $row);
            $sku = $this->getCellText($referenceSheet, $referenceLayout['skuColumn'], $row);
            if ($key === null && $sku === '') {
                continue;
            }
            if ($key === null || $sku === '') {
                $warnings++;
                continue;
            }
            if (array_key_exists($key, $skuByProduct)) {
                throw new RuntimeException('Kombinasi Brand, Description, Model, dan Size muncul lebih dari sekali di referensi harga.');
            }
            $skuByProduct[$key] = $sku;
        }
        if ($skuByProduct === []) {
            throw new RuntimeException('Tidak ada kombinasi Brand, Description, Model, Size, dan SKU yang dapat dibaca dari referensi harga.');
        }

        $rawLayout = $this->findRawPriceLayout($priceList);
        $rawSheet = $rawLayout['sheet'];
        $prices = [];
        for ($row = $rawLayout['row']; $row <= $rawSheet->getHighestDataRow(); $row++) {
            $key = $this->productIdentity($rawSheet, $rawLayout['columns'], $row);
            if ($key === null || ! array_key_exists($key, $skuByProduct)) {
                continue;
            }

            $sku = $skuByProduct[$key];
            if (array_key_exists($sku, $prices)) {
                throw new RuntimeException('SKU "' . $sku . '" muncul lebih dari sekali di harga raw setelah dicocokkan melalui Brand, Description, Model, dan Size.');
            }
            $prices[$sku] = $this->parsePrice($rawSheet->getCell([9, $row])->getValue(), $sku);
        }
        if ($prices === []) {
            throw new RuntimeException('Tidak ada harga raw yang cocok dengan referensi harga. Pastikan Brand, Description, Model, dan Size sesuai.');
        }

        return ['values' => $prices, 'warnings' => $warnings];
    }

    /**
     * @return array{sheet: \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet, row: int, columns: array{brand: int, description: int, model: int, size: int}}
     */
    private function findRawPriceLayout(Spreadsheet $spreadsheet): array
    {
        foreach ($this->getSheets($spreadsheet) as $sheet) {
            $lastRow = min(self::HEADER_SCAN_LIMIT, $sheet->getHighestDataRow());
            for ($row = 1; $row <= $lastRow; $row++) {
                $identityValues = [];
                foreach ([1, 2, 3, 4] as $column) {
                    $identityValues[] = $this->getCellText($sheet, $column, $row);
                }

                $priceValue = $sheet->getCell([9, $row])->getValue();
                if (in_array('', $identityValues, true) || $priceValue === null || $priceValue === '') {
                    continue;
                }

                $priceHeader = $this->normalizeHeader($sheet->getCell([9, $row])->getFormattedValue());
                $isHeader = in_array($priceHeader, self::PRICE_HEADERS, true);

                return [
                    'sheet' => $sheet,
                    'row' => $row + ($isHeader ? 1 : 0),
                    'columns' => [
                        'brand' => 1,
                        'description' => 2,
                        'model' => 3,
                        'size' => 4,
                    ],
                ];
            }
        }

        throw new RuntimeException(
            'Data harga raw tidak ditemukan di kolom A sampai D dan kolom I pada 30 baris pertama.'
        );
    }

    /**
     * @return array{sheet: \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet, row: int, columns: array{brand: int, description: int, model: int, size: int}, skuColumn?: int}
     */
    private function findProductIdentityLayout(
        Spreadsheet $spreadsheet,
        bool $requireSku,
        string $description
    ): array {
        $fields = [
            'brand' => ['brand'],
            'description' => ['description', 'productdescription'],
            'model' => ['model'],
            'size' => ['size'],
        ];
        foreach ($this->getSheets($spreadsheet) as $sheet) {
            $lastRow = min(self::HEADER_SCAN_LIMIT, $sheet->getHighestDataRow());
            $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
            for ($row = 1; $row <= $lastRow; $row++) {
                $headers = [];
                for ($column = 1; $column <= $lastColumn; $column++) {
                    $headers[$column] = $this->normalizeHeader(
                        $sheet->getCell([$column, $row])->getFormattedValue()
                    );
                }

                $columns = [];
                $ambiguity = null;
                foreach ($fields as $field => $aliases) {
                    $column = $this->findHeaderColumn($headers, $aliases, $field, $ambiguity);
                    if ($column === null) {
                        continue 2;
                    }
                    $columns[$field] = $column;
                }
                $layout = ['sheet' => $sheet, 'row' => $row, 'columns' => $columns];
                if ($requireSku) {
                    $skuColumn = $this->findHeaderColumn($headers, self::SKU_HEADERS, 'SKU', $ambiguity);
                    if ($skuColumn === null) {
                        continue;
                    }
                    $layout['skuColumn'] = $skuColumn;
                }

                return $layout;
            }
        }

        $required = $requireSku ? 'Brand, Description, Model, Size, dan SKU' : 'Brand, Description, Model, dan Size';
        throw new RuntimeException(
            'Kolom ' . $required . ' tidak ditemukan pada 30 baris pertama ' . $description . '.'
        );
    }

    /**
     * @param array{brand: int, description: int, model: int, size: int} $columns
     */
    private function productIdentity(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        array $columns,
        int $row
    ): ?string {
        $parts = [];
        foreach (['brand', 'description', 'model', 'size'] as $field) {
            $value = $this->getCellText($sheet, $columns[$field], $row);
            $parts[] = $value;
        }

        if ($parts === ['', '', '', '']) {
            return null;
        }

        return json_encode($parts, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, int> $stockMap
     * @param list<array{threshold: int, replacement: int}> $stockRules
     * @return array<string, int>
     */
    private function applyStockRules(array $stockMap, array $stockRules): array
    {
        foreach ($stockMap as $sku => $stock) {
            $selectedRule = null;
            foreach ($stockRules as $rule) {
                if ($stock < $rule['threshold']
                    && ($selectedRule === null || $rule['threshold'] < $selectedRule['threshold'])) {
                    $selectedRule = $rule;
                }
            }

            if ($selectedRule !== null) {
                $stockMap[$sku] = $selectedRule['replacement'];
            }
        }

        return $stockMap;
    }

    /**
     * @return array{values: array<string, int>, warnings: int}
     */
    private function makeRawStockMap(?Spreadsheet $stockList, ?Spreadsheet $stockReference): array
    {
        if ($stockList === null || $stockReference === null) {
            throw new RuntimeException('File stok raw dan referensi stok wajib diunggah.');
        }

        $referenceLayout = $this->findReferenceLayout($stockReference);
        $referenceSheet = $referenceLayout['sheet'];
        $referenceMap = [];
        $warningCount = 0;
        for ($row = $referenceLayout['row'] + 1; $row <= $referenceSheet->getHighestDataRow(); $row++) {
            $ref = $this->getCellText($referenceSheet, $referenceLayout['refColumn'], $row);
            $sku = $this->getCellText($referenceSheet, $referenceLayout['skuColumn'], $row);
            if ($ref === '' && $sku === '') {
                continue;
            }
            if ($ref === '' || $sku === '') {
                $warningCount++;
                continue;
            }
            if (array_key_exists($ref, $referenceMap)) {
                throw new RuntimeException(
                    'Ref "' . $ref . '" muncul lebih dari sekali di file referensi stok.'
                );
            }

            $referenceMap[$ref] = $sku;
        }

        if ($referenceMap === []) {
            throw new RuntimeException('Tidak ada pasangan Ref dan SKU yang dapat dibaca dari file referensi stok.');
        }

        $stockLayout = $this->findRawStockLayout($stockList);
        $stockSheet = $stockLayout['sheet'];
        $stockMap = [];
        for ($row = $stockLayout['row'] + 1; $row <= $stockSheet->getHighestDataRow(); $row++) {
            $ref = $this->getCellText($stockSheet, 3, $row);
            if ($ref === '' || ! array_key_exists($ref, $referenceMap)) {
                continue;
            }

            $sku = $referenceMap[$ref];
            if (array_key_exists($sku, $stockMap)) {
                throw new RuntimeException(
                    'SKU "' . $sku . '" muncul lebih dari sekali di stok raw setelah dicocokkan melalui Ref.'
                );
            }

            $value = $stockSheet->getCell([$stockLayout['quantityColumn'], $row])->getValue();
            $stockMap[$sku] = $this->parseStock($value, $sku);
        }

        if ($stockMap === []) {
            throw new RuntimeException(
                'Tidak ada stok raw yang dapat dicocokkan. Pastikan Ref di kolom C cocok dengan file referensi stok.'
            );
        }

        return ['values' => $stockMap, 'warnings' => $warningCount];
    }

    private function getCellText(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $column,
        int $row
    ): string {
        $cell = $sheet->getCell([$column, $row]);
        $value = $cell->getFormattedValue();
        if (trim((string) $value) === '') {
            $value = $cell->getValue();
            if (is_string($value) && str_starts_with($value, '=')) {
                $value = $cell->getCalculatedValue();
            }
        }

        return $this->normalizeSku($value);
    }

    /**
     * @return array{sheet: \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet, row: int, refColumn: int, skuColumn: int}
     */
    private function findReferenceLayout(Spreadsheet $spreadsheet): array
    {
        foreach ($this->getSheets($spreadsheet) as $sheet) {
            $lastRow = min(self::HEADER_SCAN_LIMIT, $sheet->getHighestDataRow());
            $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
            for ($row = 1; $row <= $lastRow; $row++) {
                $headers = [];
                for ($column = 1; $column <= $lastColumn; $column++) {
                    $headers[$column] = $this->normalizeHeader(
                        $sheet->getCell([$column, $row])->getFormattedValue()
                    );
                }

                $ambiguity = null;
                $refColumn = $this->findHeaderColumn($headers, ['ref'], 'Ref', $ambiguity);
                $skuColumn = $this->findHeaderColumn($headers, self::SKU_HEADERS, 'SKU', $ambiguity);
                if ($refColumn !== null && $skuColumn !== null) {
                    return [
                        'sheet' => $sheet,
                        'row' => $row,
                        'refColumn' => $refColumn,
                        'skuColumn' => $skuColumn,
                    ];
                }
            }
        }

        throw new RuntimeException(
            'Kolom Ref dan SKU tidak ditemukan pada 30 baris pertama file referensi stok.'
        );
    }

    /**
     * @return array{sheet: \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet, row: int, quantityColumn: int}
     */
    private function findRawStockLayout(Spreadsheet $spreadsheet): array
    {
        foreach ($this->getSheets($spreadsheet) as $sheet) {
            $lastRow = min(self::HEADER_SCAN_LIMIT, $sheet->getHighestDataRow());
            $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
            for ($row = 1; $row <= $lastRow; $row++) {
                $headers = [];
                for ($column = 1; $column <= $lastColumn; $column++) {
                    $headers[$column] = $this->normalizeHeader(
                        $sheet->getCell([$column, $row])->getFormattedValue()
                    );
                }

                $ambiguity = null;
                $quantityColumn = $this->findHeaderColumn($headers, ['quantity'], 'Quantity', $ambiguity);
                if ($quantityColumn !== null) {
                    return [
                        'sheet' => $sheet,
                        'row' => $row,
                        'quantityColumn' => $quantityColumn,
                    ];
                }
            }
        }

        throw new RuntimeException(
            'Kolom Quantity tidak ditemukan pada 30 baris pertama file stok raw.'
        );
    }

    /**
     * @return list<\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet>
     */
    private function getSheets(Spreadsheet $spreadsheet): array
    {
        $sheets = [$spreadsheet->getActiveSheet()];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            if ($sheet !== $sheets[0]) {
                $sheets[] = $sheet;
            }
        }

        return $sheets;
    }

    private function loadSpreadsheet(string $path): Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($path);
        if ($reader instanceof Csv) {
            $reader->setValueBinder(new StringValueBinder());
        }

        return $reader->load($path);
    }

    /**
     * @return array<string, int|float>
     */
    private function makeValueMap(
        ?Spreadsheet $spreadsheet,
        array $valueHeaders,
        string $fileDescription,
        string $valueDescription
    ): array {
        if ($spreadsheet === null) {
            throw new RuntimeException('File ' . $fileDescription . ' tidak tersedia.');
        }

        $layout = $this->findHeaderLayout(
            $spreadsheet,
            $fileDescription,
            $valueHeaders,
            'kolom ' . $valueDescription
        );
        $sheet = $layout['sheet'];
        $highestRow = $sheet->getHighestDataRow();
        $values = [];

        for ($row = $layout['row'] + 1; $row <= $highestRow; $row++) {
            $sku = $this->normalizeSku(
                $sheet->getCell([$layout['skuColumn'], $row])->getFormattedValue()
            );

            if ($sku === '') {
                continue;
            }

            if (array_key_exists($sku, $values)) {
                throw new RuntimeException(
                    'SKU "' . $sku . '" muncul lebih dari sekali di ' . $fileDescription
                    . '. Periksa agar setiap SKU hanya memiliki satu ' . $valueDescription . '.'
                );
            }

            $value = $sheet->getCell([$layout['priceColumn'], $row])->getValue();
            $values[$sku] = $valueDescription === 'harga'
                ? $this->parsePrice($value, $sku)
                : $this->parseStock($value, $sku);
        }

        if ($values === []) {
            throw new RuntimeException('Tidak ada SKU yang dapat dibaca dari ' . $fileDescription . '.');
        }

        return $values;
    }

    /**
     * @param list<string> $valueHeaders
     * @return array{sheet: \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet, row: int, skuColumn: int, priceColumn: int}
     */
    private function findHeaderLayout(
        Spreadsheet $spreadsheet,
        string $fileDescription,
        array $valueHeaders,
        string $valueDescription
    ): array {
        $sheets = [$spreadsheet->getActiveSheet()];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            if ($sheet !== $sheets[0]) {
                $sheets[] = $sheet;
            }
        }

        $ambiguity = null;
        foreach ($sheets as $sheet) {
            $sheetAmbiguity = null;
            $layout = $this->findHeaderLayoutInSheet(
                $sheet,
                $valueHeaders,
                $valueDescription,
                $sheetAmbiguity
            );
            if ($layout !== null) {
                return ['sheet' => $sheet] + $layout;
            }

            $ambiguity ??= $sheetAmbiguity;
        }

        if ($ambiguity !== null) {
            throw new RuntimeException(
                'Ada lebih dari satu kolom ' . $ambiguity['field'] . ' yang sama di sheet "'
                . $ambiguity['sheet'] . '", baris ' . $ambiguity['row'] . ': '
                . implode(', ', $ambiguity['columns'])
                . '. Hapus duplikasi atau pastikan hanya ada satu kolom untuk data tersebut.'
            );
        }

        throw new RuntimeException(
            'Kolom SKU dan ' . $valueDescription . ' tidak ditemukan pada ' . $fileDescription
            . '. Pastikan header SKU dan kolom data yang sesuai ada pada 30 baris pertama di salah satu sheet.'
        );
    }

    /**
     * @return array{sheet: \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet, row: int, skuColumn: int, priceColumn?: int, stockColumn?: int}
     */
    private function findTemplateLayout(Spreadsheet $spreadsheet, bool $updatePrice, bool $updateStock): array
    {
        $sheets = [$spreadsheet->getActiveSheet()];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            if ($sheet !== $sheets[0]) {
                $sheets[] = $sheet;
            }
        }

        $ambiguity = null;
        foreach ($sheets as $sheet) {
            $lastRow = min(self::HEADER_SCAN_LIMIT, $sheet->getHighestDataRow());
            $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
            for ($row = 1; $row <= $lastRow; $row++) {
                $headers = [];
                for ($column = 1; $column <= $lastColumn; $column++) {
                    $headers[$column] = $this->normalizeHeader(
                        $sheet->getCell([$column, $row])->getFormattedValue()
                    );
                }

                $rowAmbiguity = null;
                $skuColumn = $this->findHeaderColumn($headers, self::SKU_HEADERS, 'SKU', $rowAmbiguity);
                $priceColumn = $updatePrice
                    ? $this->findHeaderColumn($headers, self::PRICE_HEADERS, 'harga', $rowAmbiguity)
                    : null;
                $stockColumn = $updateStock
                    ? $this->findHeaderColumn($headers, self::STOCK_HEADERS, 'stok', $rowAmbiguity)
                    : null;

                if ($skuColumn !== null
                    && (! $updatePrice || $priceColumn !== null)
                    && (! $updateStock || $stockColumn !== null)) {
                    $layout = [
                        'sheet' => $sheet,
                        'row' => $row,
                        'skuColumn' => $skuColumn,
                    ];
                    if ($priceColumn !== null) {
                        $layout['priceColumn'] = $priceColumn;
                    }
                    if ($stockColumn !== null) {
                        $layout['stockColumn'] = $stockColumn;
                    }

                    return $layout;
                }

                if ($rowAmbiguity !== null) {
                    $ambiguity ??= [
                        'sheet' => $sheet->getTitle(),
                        'row' => $row,
                    ] + $rowAmbiguity;
                }
            }
        }

        if ($ambiguity !== null) {
            throw new RuntimeException(
                'Ada lebih dari satu kolom ' . $ambiguity['field'] . ' yang sama di sheet "'
                . $ambiguity['sheet'] . '", baris ' . $ambiguity['row'] . ': '
                . implode(', ', $ambiguity['columns'])
                . '. Hapus duplikasi atau pastikan hanya ada satu kolom untuk data tersebut.'
            );
        }

        $requiredValues = [];
        if ($updatePrice) {
            $requiredValues[] = 'Harga / Price';
        }
        if ($updateStock) {
            $requiredValues[] = 'Stok / Stock / Qty';
        }
        throw new RuntimeException(
            'Header SKU dan ' . implode(' serta ', $requiredValues)
            . ' tidak ditemukan bersama pada 30 baris pertama di salah satu sheet template Shopee.'
        );
    }

    /**
     * @param list<string> $valueHeaders
     * @return array{row: int, skuColumn: int, priceColumn: int}|null
     */
    private function findHeaderLayoutInSheet(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        array $valueHeaders,
        string $valueDescription,
        ?array &$ambiguity
    ): ?array {
        $lastRow = min(self::HEADER_SCAN_LIMIT, $sheet->getHighestDataRow());
        $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        for ($row = 1; $row <= $lastRow; $row++) {
            $headers = [];
            for ($column = 1; $column <= $lastColumn; $column++) {
                $headers[$column] = $this->normalizeHeader(
                    $sheet->getCell([$column, $row])->getFormattedValue()
                );
            }

            $rowAmbiguity = null;
            $skuColumn = $this->findHeaderColumn($headers, self::SKU_HEADERS, 'SKU', $rowAmbiguity);
            $priceColumn = $this->findHeaderColumn($headers, $valueHeaders, $valueDescription, $rowAmbiguity);

            if ($skuColumn !== null && $priceColumn !== null) {
                return [
                    'row' => $row,
                    'skuColumn' => $skuColumn,
                    'priceColumn' => $priceColumn,
                ];
            }

            if ($rowAmbiguity !== null) {
                $ambiguity ??= [
                    'sheet' => $sheet->getTitle(),
                    'row' => $row,
                ] + $rowAmbiguity;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $headers
     * @param list<string> $aliases
     * @param array{field: string, columns: list<string>}|null $ambiguity
     */
    private function findHeaderColumn(array $headers, array $aliases, string $field, ?array &$ambiguity): ?int
    {
        $matches = [];
        foreach ($headers as $column => $header) {
            $rank = array_search($header, $aliases, true);
            if ($rank !== false) {
                $matches[$column] = $rank;
            }
        }

        if ($matches === []) {
            foreach ($headers as $column => $header) {
                if ($header === '') {
                    continue;
                }

                foreach ($aliases as $rank => $alias) {
                    if (str_contains($header, $alias)) {
                        $matches[$column] = $rank;
                        break;
                    }
                }
            }
        }

        if ($matches === []) {
            return null;
        }

        $bestRank = min($matches);
        $bestColumns = array_keys(array_filter(
            $matches,
            static fn (int $rank): bool => $rank === $bestRank
        ));

        if (count($bestColumns) > 1) {
            $ambiguity = [
                'field' => $field,
                'columns' => array_map(
                    static fn (int $column): string => Coordinate::stringFromColumnIndex($column)
                        . ' (' . $headers[$column] . ')',
                    $bestColumns
                ),
            ];

            return null;
        }

        return $bestColumns[0];
    }

    private function normalizeHeader(mixed $value): string
    {
        return mb_strtolower((string) preg_replace('/[^a-z0-9]/i', '', (string) $value));
    }

    private function normalizeSku(mixed $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function parsePrice(mixed $value, string $sku): int|float
    {
        if (is_int($value) || is_float($value)) {
            $price = $value;
        } else {
            $text = trim((string) $value);
            $text = preg_replace('/^rp\s*/iu', '', $text);
            $text = preg_replace('/[^0-9,.\-]/u', '', (string) $text);

            if ($text === '' || ! preg_match('/^-?[0-9][0-9,.]*$/', $text)) {
                throw new RuntimeException('Harga untuk SKU "' . $sku . '" kosong atau bukan angka yang valid.');
            }

            $comma = strrpos($text, ',');
            $dot = strrpos($text, '.');

            if ($comma !== false && $dot !== false) {
                $decimalSeparator = $comma > $dot ? ',' : '.';
                $thousandsSeparator = $decimalSeparator === ',' ? '.' : ',';
                $text = str_replace($thousandsSeparator, '', $text);
                $text = str_replace($decimalSeparator, '.', $text);
            } elseif ($comma !== false || $dot !== false) {
                $separator = $comma !== false ? ',' : '.';
                $parts = explode($separator, $text);
                $decimals = strlen((string) end($parts));

                if ($decimals > 0 && $decimals <= 2) {
                    $text = str_replace($separator, '.', $text);
                } else {
                    $text = str_replace($separator, '', $text);
                }
            }

            $price = (float) $text;
            if (floor($price) === $price) {
                $price = (int) $price;
            }
        }

        if (! is_finite((float) $price) || $price < 0) {
            throw new RuntimeException('Harga untuk SKU "' . $sku . '" harus berupa angka nol atau lebih.');
        }

        return $price;
    }

    private function parseStock(mixed $value, string $sku): int
    {
        if (is_int($value)) {
            return max(0, $value);
        } elseif (is_float($value) && is_finite($value)) {
            return max(0, (int) floor($value));
        } else {
            $text = trim((string) $value);
            if (! preg_match('/^-?\d+(?:[., ]\d+)*$/', $text)) {
                throw new RuntimeException(
                    'Stok untuk SKU "' . $sku . '" harus berupa angka nol atau lebih.'
                );
            }

            $isNegative = str_starts_with($text, '-');
            $text = ltrim($text, '-');
            $separators = ['.', ',', ' '];
            $presentSeparators = array_values(array_filter(
                $separators,
                static fn (string $separator): bool => str_contains($text, $separator)
            ));

            if (count($presentSeparators) > 1) {
                $decimalSeparator = $presentSeparators[0];
                foreach ($presentSeparators as $separator) {
                    if (strrpos($text, $separator) > strrpos($text, $decimalSeparator)) {
                        $decimalSeparator = $separator;
                    }
                }
                $decimalPosition = strrpos($text, $decimalSeparator);
                $integerPart = substr($text, 0, $decimalPosition);
                $fractionPart = substr($text, $decimalPosition + 1);
                $integerPart = str_replace($presentSeparators, '', $integerPart);
                if ($fractionPart === '' || ! ctype_digit($integerPart) || ! ctype_digit($fractionPart)) {
                    throw new RuntimeException(
                        'Stok untuk SKU "' . $sku . '" harus berupa angka nol atau lebih.'
                    );
                }
                $stock = (int) $integerPart;
            } elseif ($presentSeparators === []) {
                $stock = (int) $text;
            } else {
                $separator = $presentSeparators[0];
                $parts = explode($separator, $text);
                $isThousandsGrouped = $separator !== ' '
                    && count($parts) === 2
                    && strlen($parts[0]) <= 3
                    && strlen($parts[1]) === 3
                    && $parts[0] !== '0';

                if (count($parts) > 2) {
                    $isThousandsGrouped = preg_match(
                        '/^\d{1,3}(?:' . preg_quote($separator, '/') . '\d{3})+$/',
                        $text
                    ) === 1;
                }

                if ($isThousandsGrouped) {
                    $stock = (int) str_replace($separator, '', $text);
                } else {
                    $integerPart = $parts[0];
                    $fractionPart = implode('', array_slice($parts, 1));
                    if (! ctype_digit($integerPart) || ! ctype_digit($fractionPart)) {
                        throw new RuntimeException(
                            'Stok untuk SKU "' . $sku . '" harus berupa angka nol atau lebih.'
                        );
                    }
                    $stock = (int) $integerPart;
                }
            }

            if ($isNegative) {
                return 0;
            }
        }

        if ($stock < 0) {
            return 0;
        }

        return $stock;
    }

}
