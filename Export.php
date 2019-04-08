<?php
namespace go1\report_helpers;

use Aws\S3\S3Client;
use Elasticsearch\Client as ElasticsearchClient;
use Mrubiosan\FlyUrl\Adapter\UrlAwsS3Adapter;
use Mrubiosan\FlyUrl\Filesystem\UrlFilesystem;

/**
 * Wrapper on ExportFs for backwards compatibility
 * @package go1\report_helpers
 */
class Export
{
    /**
     * @var S3Client
     */
    private $s3Client;

    /**
     * @var ExportCsv
     */
    private $exportCsv;

    /**
     * @var ElasticsearchClient
     */
    private $elasticsearchClient;

    public function __construct(S3Client $s3Client, ElasticsearchClient $elasticsearchClient)
    {
        $this->s3Client = $s3Client;
        $this->elasticsearchClient = $elasticsearchClient;
    }

    /**
     *
     * @param ExportCsv $exportCsv
     */
    public function setExportCsv(ExportCsv $exportCsv)
    {
        $this->exportCsv = $exportCsv;
    }

    protected function getExportCsv() : ExportCsv
    {
        if (!$this->exportCsv) {
            $this->exportCsv = new ExportCsv($this->elasticsearchClient);
        }

        return $this->exportCsv;
    }

    public function doExport($bucket, $key, $fields, $headers, $params, $selectedIds, $excludedIds, $allSelected, $formatters = [])
    {
        $fs = new UrlFilesystem(new UrlAwsS3Adapter($this->s3Client, $bucket), [
            'disable_asserts' => true
        ]);
        $exportFs = new ExportFs($fs, $this->getExportCsv());
        $exportFs->doExport($key, $fields, $headers, $params, $selectedIds, $excludedIds, $allSelected, $formatters);
    }

    public function getFile($region, $bucket, $key)
    {
        $domain = getenv('MONOLITH') ? getenv('AWS_S3_ENDPOINT') : "https://s3-{$region}.amazonaws.com";
        return "$domain/$bucket/$key";
    }
}
