<?php
namespace go1\report_helpers\tests;

use Aws\S3\S3Client;
use go1\report_helpers\Export;
use go1\report_helpers\ExportCsv;
use go1\report_helpers\ExportFs;
use Mockery;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophet;

class ExportTest extends TestCase
{
    private $s3ClientMock;

    private $exportCsvMock;

    private $testSubject;

    private Prophet $prophet;

    protected function setUp() : void
    {
        $this->prophet = new Prophet();
        $this->s3ClientMock = $this->prophet->prophesize(S3Client::class);
    }

    protected function tearDown(): void
    {
        $this->prophet->checkPredictions();
    }

    protected function setUpTestSubject($stream)
    {
        $this->exportCsvMock = Mockery::mock(ExportCSV::class);
        $this->exportCsvMock
            ->shouldReceive('export')
            ->andReturn($stream)
            ->atLeast()
            ->times(1);

        $this->testSubject = new Export(
            $this->s3ClientMock->reveal(),
            $this->exportCsvMock
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
        $this->setUpTestSubject($stream);

        $this->s3ClientMock->upload('bucket', 'key', $stream, 'public-read', Argument::cetera())
            ->shouldBeCalled();

        $this->testSubject->doExport('bucket', 'key', $fields, $headers, $params, $selectedIds, $excludedIds,
            $allSelected, $formatters);
    }

    public function testGetFile()
    {
        $stream = fopen('php://memory', 'w+');
        $this->setUpTestSubject($stream);

        $result = $this->testSubject->getFile('region', 'bucket', 'key');
        $this->assertEquals("https://s3-region.amazonaws.com/bucket/key", $result);
    }
}
