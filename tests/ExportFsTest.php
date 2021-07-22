<?php
namespace go1\report_helpers\tests;

use go1\report_helpers\ExportCsv;
use go1\report_helpers\ExportFs;
use Mrubiosan\FlyUrl\Filesystem\UrlFilesystemInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophet;
use \Mockery;

class ExportFsTest extends TestCase
{
    private $fsMock;

    private $exportCsvMock;

    private $testSubject;

    private Prophet $prophet;

    protected function setUp() : void
    {
        $this->prophet = new Prophet();
        $this->fsMock = $this->prophet->prophesize(UrlFilesystemInterface::class);
    }

    protected function setUpTestSubject($stream)
    {
        $this->exportCsvMock = Mockery::mock(ExportCSV::class);
        $this->exportCsvMock
            ->shouldReceive('export')
            ->andReturn($stream)
            ->atLeast()
            ->times(1);

        $this->testSubject = new ExportFs(
            $this->fsMock->reveal(),
            $this->exportCsvMock
        );
    }

    protected function tearDown(): void
    {
        $this->prophet->checkPredictions();
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
        $this->fsMock->writeStream('key', $stream, ['visibility' => 'public'])
            ->shouldBeCalled();
        $this->setUpTestSubject($stream);
        $this->testSubject->doExport('key', $fields, $headers, $params, $selectedIds, $excludedIds, $allSelected, $formatters, $settings);
    }

    public function testGetFile()
    {
        $stream = fopen('php://memory', 'w+');
        $this->setUpTestSubject($stream);
        $expectedFile = "'/tmp/abcd";
        $this->fsMock->getUrl('foo')->shouldBeCalled()->willReturn($expectedFile);
        $result = $this->testSubject->getFile('foo');
        $this->assertEquals($expectedFile, $result );
    }
}
