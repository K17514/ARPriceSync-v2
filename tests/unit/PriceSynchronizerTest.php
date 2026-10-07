<?php

namespace Tests\App\Libraries;

use App\Libraries\PriceSynchronizer;
use CodeIgniter\Test\CIUnitTestCase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use RuntimeException;

class PriceSynchronizerTest extends CIUnitTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pricesync_' . bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    public function testUpdatesMatchingTemplatePricesAndLeavesUnmatchedRowsAlone(): void
    {
        $templatePath = $this->directory . DIRECTORY_SEPARATOR . 'template.xlsx';
        $priceListPath = $this->directory . DIRECTORY_SEPARATOR . 'prices.xlsx';
        $outputPath = $this->directory . DIRECTORY_SEPARATOR . 'result.xlsx';

        $template = new Spreadsheet();
        $templateSheet = $template->getActiveSheet();
        $templateSheet->fromArray([
            ['Seller SKU', 'Harga', 'Nama Produk', 'Stok'],
            ['001', 9000, 'Produk Satu', 7],
            ['sku-2', 10000, 'Produk Dua', 8],
            ['not-listed', 12000, 'Produk Tiga', 9],
        ]);
        IOFactory::createWriter($template, 'Xlsx')->save($templatePath);

        $priceList = new Spreadsheet();
        $priceSheet = $priceList->getActiveSheet();
        $priceSheet->fromArray([
            ['SKU', 'Harga'],
            ['001', 'Rp 15.000'],
            ['sku-2', 27000],
        ]);
        IOFactory::createWriter($priceList, 'Xlsx')->save($priceListPath);

        $result = (new PriceSynchronizer())->synchronize($templatePath, $priceListPath, $outputPath);

        self::assertSame(['matched' => 2, 'unmatched' => 1, 'total' => 3], $result);
        $updated = IOFactory::load($outputPath);
        $updatedSheet = $updated->getActiveSheet();
        self::assertSame(15000, $updatedSheet->getCell('B2')->getValue());
        self::assertSame(27000, $updatedSheet->getCell('B3')->getValue());
        self::assertSame(12000, $updatedSheet->getCell('B4')->getValue());
        self::assertSame('Produk Tiga', $updatedSheet->getCell('C4')->getValue());
        self::assertSame(7, $updatedSheet->getCell('D2')->getValue());
        $updated->disconnectWorksheets();
    }

    public function testRejectsDuplicateSkusInPriceList(): void
    {
        $templatePath = $this->directory . DIRECTORY_SEPARATOR . 'template.xlsx';
        $priceListPath = $this->directory . DIRECTORY_SEPARATOR . 'prices.xlsx';

        $template = new Spreadsheet();
        $template->getActiveSheet()->fromArray([
            ['SKU', 'Harga'],
            ['A-1', 1000],
        ]);
        IOFactory::createWriter($template, 'Xlsx')->save($templatePath);

        $priceList = new Spreadsheet();
        $priceList->getActiveSheet()->fromArray([
            ['SKU', 'Harga'],
            ['A-1', 2000],
            ['A-1', 3000],
        ]);
        IOFactory::createWriter($priceList, 'Xlsx')->save($priceListPath);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('muncul lebih dari sekali');

        (new PriceSynchronizer())->synchronize(
            $templatePath,
            $priceListPath,
            $this->directory . DIRECTORY_SEPARATOR . 'result.xlsx'
        );
    }

    public function testReadsSemicolonSeparatedPriceLists(): void
    {
        $templatePath = $this->directory . DIRECTORY_SEPARATOR . 'template.xlsx';
        $priceListPath = $this->directory . DIRECTORY_SEPARATOR . 'prices.tmp';
        $outputPath = $this->directory . DIRECTORY_SEPARATOR . 'result.xlsx';

        $template = new Spreadsheet();
        $template->getActiveSheet()->fromArray([
            ['SKU', 'Harga'],
            ['A-1', 1000],
        ]);
        IOFactory::createWriter($template, 'Xlsx')->save($templatePath);
        file_put_contents($priceListPath, "SKU;Harga\r\nA-1;15.000\r\n");

        $result = (new PriceSynchronizer())->synchronize($templatePath, $priceListPath, $outputPath);
        $updated = IOFactory::load($outputPath);

        self::assertSame(1, $result['matched']);
        self::assertSame(15000, $updated->getActiveSheet()->getCell('B2')->getValue());
        $updated->disconnectWorksheets();
    }

    public function testFindsShopeeHeadersInAnotherSheetWithinFirstThirtyRows(): void
    {
        $templatePath = $this->directory . DIRECTORY_SEPARATOR . 'template.xlsx';
        $priceListPath = $this->directory . DIRECTORY_SEPARATOR . 'prices.xlsx';
        $outputPath = $this->directory . DIRECTORY_SEPARATOR . 'result.xlsx';

        $template = new Spreadsheet();
        $template->getActiveSheet()->setTitle('Petunjuk');
        $template->getActiveSheet()->fromArray([
            ['SKU Induk', 'SKU Induk', 'Harga'],
            ['PARENT-1', 'PARENT-2', 10000],
        ]);
        $templateSheet = $template->createSheet();
        $templateSheet->setTitle('Produk');
        $templateSheet->fromArray([
            ['SKU Induk', 'SKU', 'Harga', 'Nama Produk'],
            ['PARENT-1', 'SKU-1', 10000, 'Produk Satu'],
        ], null, 'A30');
        IOFactory::createWriter($template, 'Xlsx')->save($templatePath);

        $priceList = new Spreadsheet();
        $priceList->getActiveSheet()->setTitle('Ringkasan');
        $priceList->getActiveSheet()->setCellValue('A1', 'Ringkasan harga');
        $priceSheet = $priceList->createSheet();
        $priceSheet->setTitle('Harga');
        $priceSheet->fromArray([
            ['Seller SKU', 'Price'],
            ['SKU-1', 15000],
        ]);
        IOFactory::createWriter($priceList, 'Xlsx')->save($priceListPath);

        $result = (new PriceSynchronizer())->synchronize($templatePath, $priceListPath, $outputPath);
        $updated = IOFactory::load($outputPath);

        self::assertSame(['matched' => 1, 'unmatched' => 0, 'total' => 1], $result);
        self::assertSame(15000, $updated->getSheetByName('Produk')?->getCell('C31')->getValue());
        $updated->disconnectWorksheets();
    }

    public function testUpdatesPricesAndStockFromSeparateFiles(): void
    {
        $templatePath = $this->directory . DIRECTORY_SEPARATOR . 'template.xlsx';
        $priceListPath = $this->directory . DIRECTORY_SEPARATOR . 'prices.xlsx';
        $stockListPath = $this->directory . DIRECTORY_SEPARATOR . 'stock.xlsx';
        $outputPath = $this->directory . DIRECTORY_SEPARATOR . 'result.xlsx';

        $template = new Spreadsheet();
        $template->getActiveSheet()->fromArray([
            ['SKU', 'Harga', 'Stok'],
            ['SKU-1', 10000, 4],
            ['SKU-2', 12000, 8],
        ]);
        IOFactory::createWriter($template, 'Xlsx')->save($templatePath);

        $priceList = new Spreadsheet();
        $priceList->getActiveSheet()->fromArray([
            ['Seller SKU', 'Harga'],
            ['SKU-1', 15000],
        ]);
        IOFactory::createWriter($priceList, 'Xlsx')->save($priceListPath);

        $stockList = new Spreadsheet();
        $stockList->getActiveSheet()->fromArray([
            ['SKU Model', 'Qty'],
            ['SKU-1', 0],
        ]);
        $stockList->getActiveSheet()->setCellValue('B2', 0);
        IOFactory::createWriter($stockList, 'Xlsx')->save($stockListPath);

        $result = (new PriceSynchronizer())->synchronize(
            $templatePath,
            $priceListPath,
            $outputPath,
            'both',
            $stockListPath
        );
        $updated = IOFactory::load($outputPath);
        $updatedSheet = $updated->getActiveSheet();

        self::assertSame(['matched' => 1, 'unmatched' => 1, 'total' => 2], $result);
        self::assertSame(15000, $updatedSheet->getCell('B2')->getValue());
        self::assertSame(0, $updatedSheet->getCell('C2')->getValue());
        self::assertSame(12000, $updatedSheet->getCell('B3')->getValue());
        self::assertSame(8, $updatedSheet->getCell('C3')->getValue());
        $updated->disconnectWorksheets();
    }

    public function testUpdatesStockOnlyWithoutPriceFile(): void
    {
        $templatePath = $this->directory . DIRECTORY_SEPARATOR . 'template.xlsx';
        $stockListPath = $this->directory . DIRECTORY_SEPARATOR . 'stock.xlsx';
        $outputPath = $this->directory . DIRECTORY_SEPARATOR . 'result.xlsx';

        $template = new Spreadsheet();
        $template->getActiveSheet()->fromArray([
            ['SKU', 'Stock', 'Harga'],
            ['SKU-1', 4, 10000],
        ]);
        IOFactory::createWriter($template, 'Xlsx')->save($templatePath);

        $stockList = new Spreadsheet();
        $stockList->getActiveSheet()->fromArray([
            ['SKU', 'Stok'],
            ['SKU-1', 12],
        ]);
        IOFactory::createWriter($stockList, 'Xlsx')->save($stockListPath);

        $result = (new PriceSynchronizer())->synchronize(
            $templatePath,
            null,
            $outputPath,
            'stock',
            $stockListPath
        );
        $updated = IOFactory::load($outputPath);

        self::assertSame(['matched' => 1, 'unmatched' => 0, 'total' => 1], $result);
        self::assertSame(12, $updated->getActiveSheet()->getCell('B2')->getValue());
        self::assertSame(10000, $updated->getActiveSheet()->getCell('C2')->getValue());
        $updated->disconnectWorksheets();
    }

    public function testUpdatesStockRawByResolvingColumnCRefThroughReferenceFile(): void
    {
        $templatePath = $this->directory . DIRECTORY_SEPARATOR . 'template.xlsx';
        $stockListPath = $this->directory . DIRECTORY_SEPARATOR . 'stock_raw.xlsx';
        $stockReferencePath = $this->directory . DIRECTORY_SEPARATOR . 'stock_reference.xlsx';
        $outputPath = $this->directory . DIRECTORY_SEPARATOR . 'result.xlsx';

        $template = new Spreadsheet();
        $template->getActiveSheet()->fromArray([
            ['SKU', 'Stok'],
            ['SKU-1', 4],
            ['SKU-2', 8],
        ]);
        IOFactory::createWriter($template, 'Xlsx')->save($templatePath);

        $stockReference = new Spreadsheet();
        $stockReference->getActiveSheet()->fromArray([
            ['Ref', 'SKU'],
            [101, 'SKU-1'],
            [102, 'SKU-2'],
            [103, null],
        ]);
        $stockReference->getActiveSheet()->getStyle('A2:A4')->getNumberFormat()->setFormatCode(';;;');
        IOFactory::createWriter($stockReference, 'Xlsx')->save($stockReferencePath);

        $stockRaw = new Spreadsheet();
        $stockRaw->getActiveSheet()->fromArray([
            ['Kode', 'Nama', 'Ref', 'Quantity'],
            ['A', 'Produk satu', 101, 0.81],
            ['B', 'Produk dua', 102, '12,9'],
        ]);
        $stockRaw->getActiveSheet()->getStyle('C2:C3')->getNumberFormat()->setFormatCode(';;;');
        IOFactory::createWriter($stockRaw, 'Xlsx')->save($stockListPath);

        $result = (new PriceSynchronizer())->synchronize(
            $templatePath,
            null,
            $outputPath,
            'stock',
            $stockListPath,
            $stockReferencePath
        );
        $updated = IOFactory::load($outputPath);
        $updatedSheet = $updated->getActiveSheet();

        self::assertSame(['matched' => 2, 'unmatched' => 0, 'total' => 2, 'warnings' => 1], $result);
        self::assertSame(0, $updatedSheet->getCell('B2')->getValue());
        self::assertSame(12, $updatedSheet->getCell('B3')->getValue());
        $updated->disconnectWorksheets();
    }

    public function testClampsNegativeStockValuesToZero(): void
    {
        $templatePath = $this->directory . DIRECTORY_SEPARATOR . 'template.xlsx';
        $stockListPath = $this->directory . DIRECTORY_SEPARATOR . 'stock.xlsx';
        $outputPath = $this->directory . DIRECTORY_SEPARATOR . 'result.xlsx';

        $template = new Spreadsheet();
        $template->getActiveSheet()->fromArray([
            ['SKU', 'Stok'],
            ['SKU-1', 4],
        ]);
        IOFactory::createWriter($template, 'Xlsx')->save($templatePath);

        $stockList = new Spreadsheet();
        $stockList->getActiveSheet()->fromArray([
            ['SKU', 'Stok'],
            ['SKU-1', -2],
        ]);
        IOFactory::createWriter($stockList, 'Xlsx')->save($stockListPath);

        $result = (new PriceSynchronizer())->synchronize(
            $templatePath,
            null,
            $outputPath,
            'stock',
            $stockListPath
        );
        $updated = IOFactory::load($outputPath);

        self::assertSame(['matched' => 1, 'unmatched' => 0, 'total' => 1], $result);
        self::assertSame(0, $updated->getActiveSheet()->getCell('B2')->getValue());
        $updated->disconnectWorksheets();
    }

    public function testAppliesMostSpecificStockThresholdRule(): void
    {
        $templatePath = $this->directory . DIRECTORY_SEPARATOR . 'template.xlsx';
        $stockListPath = $this->directory . DIRECTORY_SEPARATOR . 'stock.xlsx';
        $outputPath = $this->directory . DIRECTORY_SEPARATOR . 'result.xlsx';

        $template = new Spreadsheet();
        $template->getActiveSheet()->fromArray([
            ['SKU', 'Stok'],
            ['SKU-999', 1],
            ['SKU-99', 1],
            ['SKU-19', 1],
            ['SKU-1000', 1],
        ]);
        IOFactory::createWriter($template, 'Xlsx')->save($templatePath);

        $stockList = new Spreadsheet();
        $stockList->getActiveSheet()->fromArray([
            ['SKU', 'Quantity'],
            ['SKU-999', 999],
            ['SKU-99', 99],
            ['SKU-19', 19],
            ['SKU-1000', 1000],
        ]);
        IOFactory::createWriter($stockList, 'Xlsx')->save($stockListPath);

        $result = (new PriceSynchronizer())->synchronize(
            $templatePath,
            null,
            $outputPath,
            'stock',
            $stockListPath,
            null,
            [
                ['threshold' => 1000, 'replacement' => 10],
                ['threshold' => 100, 'replacement' => 2],
                ['threshold' => 20, 'replacement' => 3],
            ]
        );
        $updated = IOFactory::load($outputPath);
        $updatedSheet = $updated->getActiveSheet();

        self::assertSame(['matched' => 4, 'unmatched' => 0, 'total' => 4], $result);
        self::assertSame(10, $updatedSheet->getCell('B2')->getValue());
        self::assertSame(2, $updatedSheet->getCell('B3')->getValue());
        self::assertSame(3, $updatedSheet->getCell('B4')->getValue());
        self::assertSame(1000, $updatedSheet->getCell('B5')->getValue());
        $updated->disconnectWorksheets();
    }

    public function testMapsRawPricesAndAppliesRoundingBeforePercentIncrease(): void
    {
        $templatePath = $this->directory . DIRECTORY_SEPARATOR . 'template.xlsx';
        $priceReferencePath = $this->directory . DIRECTORY_SEPARATOR . 'price_reference.xlsx';
        $priceRawPath = $this->directory . DIRECTORY_SEPARATOR . 'price_raw.xlsx';
        $outputPath = $this->directory . DIRECTORY_SEPARATOR . 'result.xlsx';

        $template = new Spreadsheet();
        $template->getActiveSheet()->fromArray([
            ['SKU', 'Harga'],
            ['SKU-1', 1],
            ['SKU-2', 1],
            ['SKU-3', 1],
        ]);
        IOFactory::createWriter($template, 'Xlsx')->save($templatePath);

        $priceReference = new Spreadsheet();
        $priceReference->getActiveSheet()->fromArray([
            ['Brand', 'Description', 'Model', 'Size', 'SKU'],
            ['Brand A', 'Produk Satu', 'Model X', 'S', 'SKU-1'],
            ['Brand A', 'Produk Dua', null, 'M', 'SKU-2'],
            ['Brand B', 'Produk Tiga', 'Model Z', null, 'SKU-3'],
        ]);
        IOFactory::createWriter($priceReference, 'Xlsx')->save($priceReferencePath);

        $priceRaw = new Spreadsheet();
        $priceRaw->getActiveSheet()->fromArray([
            ['Brand Name', 'Product details', 'Variant code', 'Ukuran', 'E', 'F', 'G', 'H', 'Price'],
            ['Brand A', 'Produk Satu', 'Model X', 'S', null, null, null, null, 2501],
            ['Brand A', 'Produk Dua', null, 'M', null, null, null, null, 1000001],
            ['Brand B', 'Produk Tiga', 'Model Z', null, null, null, null, null, 1500],
        ]);
        IOFactory::createWriter($priceRaw, 'Xlsx')->save($priceRawPath);

        $result = (new PriceSynchronizer())->synchronize(
            $templatePath,
            $priceRawPath,
            $outputPath,
            'price',
            null,
            null,
            [],
            $priceReferencePath,
            [
                ['threshold' => 2000, 'unit' => 100],
                ['threshold' => 1000000, 'unit' => 1000],
            ],
            10,
            100
        );
        $updated = IOFactory::load($outputPath);
        $updatedSheet = $updated->getActiveSheet();

        self::assertSame(['matched' => 3, 'unmatched' => 0, 'total' => 3], $result);
        self::assertSame(2900, $updatedSheet->getCell('B2')->getValue());
        self::assertSame(1101100, $updatedSheet->getCell('B3')->getValue());
        self::assertSame(1700, $updatedSheet->getCell('B4')->getValue());
        $updated->disconnectWorksheets();
    }

}
