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

    public function doExport($key, $fields, $headers, $params, $selectedIds, $excludedIds, $allSelected, $formatters = [])
    {
        $stream = $this->exportCsv->export($fields, $headers, $params, $selectedIds, $excludedIds, $allSelected, $formatters);
        $this->fileSystem->writeStream($key, $stream, ['visibility' => AdapterInterface::VISIBILITY_PUBLIC]);
        if (is_resource($stream)) { //Some adapters already close the stream
            @fclose($stream);
        }
    }

    public function getFile($key)
    {
        return $this->fileSystem->getUrl($key);
    }
}
