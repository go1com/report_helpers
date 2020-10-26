<?php
namespace go1\report_helpers;

use League\Flysystem\AdapterInterface;
use Mrubiosan\FlyUrl\Filesystem\UrlFilesystemInterface;

class ExportFs
{
    /**
     * @var UrlFilesystemInterface
     */
    private $fileSystem;

    /**
     * @var ExportCsv
     */
    private $exportCsv;

    public function __construct(UrlFilesystemInterface $fileSystem, ExportCsv $exportCsv)
    {
        $this->fileSystem = $fileSystem;
        $this->exportCsv = $exportCsv;
    }

    /**
     * @param string $key
     * @param array $fields
     * @param array $headers
     * @param array $params
     * @param array $selectedIds
     * @param array $excludedIds
     * @param bool $allSelected
     * @param array $formatters
     * @param array $customValuesSettings
     * @throws \RuntimeException
     */
    public function doExport($key, $fields, $headers, $params, $selectedIds, $excludedIds, $allSelected, $formatters = [], $customValuesSettings = [])
    {
        $stream = $this->exportCsv->export($fields, $headers, $params, $selectedIds, $excludedIds, $allSelected, $formatters, $customValuesSettings);
        $this->fileSystem->writeStream($key, $stream, ['visibility' => AdapterInterface::VISIBILITY_PUBLIC]);
        if (is_resource($stream)) { //Some adapters already close the stream
            @fclose($stream);
        }
    }

    /**
     * Returns the file URL
     * @param string $key
     * @return string
     */
    public function getFile($key)
    {
        return $this->fileSystem->getUrl($key);
    }
}
