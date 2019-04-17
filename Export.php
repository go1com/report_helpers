<?php
namespace go1\report_helpers;

use Aws\S3\S3Client;
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

    public function __construct(S3Client $s3Client, ExportCsv $exportCsv)
    {
        $this->s3Client = $s3Client;
        $this->exportCsv = $exportCsv;
    }

    /**
     * @param string $bucket
     * @param string $key
     * @param array  $fields
     * @param array  $headers
     * @param array  $params
     * @param array  $selectedIds
     * @param array  $excludedIds
     * @param bool   $allSelected
     * @param array  $formatters
     */
    public function doExport($bucket, $key, $fields, $headers, $params, $selectedIds, $excludedIds, $allSelected, $formatters = [])
    {
        $fs = new UrlFilesystem(new UrlAwsS3Adapter($this->s3Client, $bucket), [
            'disable_asserts' => true
        ]);
        $exportFs = new ExportFs($fs, $this->exportCsv);
        $exportFs->doExport($key, $fields, $headers, $params, $selectedIds, $excludedIds, $allSelected, $formatters);
    }

    /**
     * @param string $region
     * @param string $bucket
     * @param string $key
     * @return string
     */
    public function getFile($region, $bucket, $key)
    {
        $domain = getenv('MONOLITH') ? getenv('AWS_S3_ENDPOINT') : "https://s3-{$region}.amazonaws.com";
        return "$domain/$bucket/$key";
    }
}
