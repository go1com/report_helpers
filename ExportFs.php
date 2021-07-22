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

    public function doExport(
        string $key,
        array $fields,
        array $headers,
        array $params,
        array $selectedIds,
        array $excludedIds,
        bool $allSelected,
        array $formatters = [],
        array $customValuesSettings = [],
        callable $preprocess = null
    ) {
        $stream = $this->exportCsv->export($fields, $headers, $params, $selectedIds, $excludedIds, $allSelected, $formatters, $customValuesSettings, $preprocess);
        $this->fileSystem->writeStream($key, $stream, ['visibility' => AdapterInterface::VISIBILITY_PUBLIC]);
        if (is_resource($stream)) { //Some adapters already close the stream
            @fclose($stream);
        }
    }

    /**
     * Returns the file URL
     *
     * @param string $key
     * @return string
     */
    public function getFile($key)
    {
        return $this->fileSystem->getUrl($key);
    }

    public function getExportCSV(): ExportCsv
    {
        return $this->exportCsv;
    }
}
