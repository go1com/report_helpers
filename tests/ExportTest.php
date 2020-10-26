<?php
namespace go1\report_helpers\tests;

use Aws\S3\S3Client;
use go1\report_helpers\Export;
use go1\report_helpers\ExportCsv;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

class ExportTest extends TestCase
{
    private $s3ClientMock;

    private $exportCsvMock;

    private $testSubject;

    protected function setUp() : void
    {
        $this->s3ClientMock = $this->prophesize(S3Client::class);
        $this->exportCsvMock = $this->prophesize(ExportCsv::class);
        $this->testSubject = new Export(
            $this->s3ClientMock->reveal(),
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
        $customValuesSettings = [];

        $stream = fopen('php://memory', 'w+');
        $this->exportCsvMock->export($fields, $headers, $params, $selectedIds, $excludedIds, $allSelected, $formatters, $customValuesSettings)
            ->shouldBeCalled()
            ->willReturn($stream);

        $this->s3ClientMock->upload('bucket', 'key', $stream, 'public-read', Argument::cetera())
            ->shouldBeCalled();

        $this->testSubject->doExport('bucket', 'key', $fields, $headers, $params, $selectedIds, $excludedIds,
            $allSelected, $formatters);
    }

    public function testGetFile()
    {
        $result = $this->testSubject->getFile('region', 'bucket', 'key');
        $this->assertEquals("https://s3-region.amazonaws.com/bucket/key", $result);
    }
}
