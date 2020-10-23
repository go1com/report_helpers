<?php
namespace go1\report_helpers\tests;

use go1\report_helpers\ExportCsv;
use go1\report_helpers\ExportFs;
use Mrubiosan\FlyUrl\Filesystem\UrlFilesystemInterface;
use PHPUnit\Framework\TestCase;

class ExportFsTest extends TestCase
{
    private $fsMock;

    private $exportCsvMock;

    private $testSubject;

    protected function setUp() : void
    {
        $this->fsMock = $this->prophesize(UrlFilesystemInterface::class);
        $this->exportCsvMock = $this->prophesize(ExportCsv::class);
        $this->testSubject = new ExportFs(
            $this->fsMock->reveal(),
            $this->exportCsvMock->reveal()
        );
    }

    public function testDoExport()
    {
        $fields = ['foo'];
        $headers = ['bar'];
        $params = ['params'];
        $selectedIds = [1];
        $excludedIds = [2];
        $allSelected = true;
        $formatters = ['foo' => 'bar'];
        $settings = [];

        $stream = fopen('php://memory', 'w+');
        $this->exportCsvMock->export($fields, $headers, $params, $selectedIds, $excludedIds, $allSelected, $formatters, $settings)
            ->shouldBeCalled()
            ->willReturn($stream);

        $this->fsMock->writeStream('key', $stream, ['visibility' => 'public'])
            ->shouldBeCalled();

        $this->testSubject->doExport('key', $fields, $headers, $params, $selectedIds, $excludedIds, $allSelected, $formatters, $settings);
    }

    public function testGetFile()
    {
        $this->fsMock->getUrl('foo')->shouldBeCalled();
        $this->testSubject->getFile('foo');
    }
}
