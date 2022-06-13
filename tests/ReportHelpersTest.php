<?php

namespace go1\report_helpers\tests;

use go1\report_helpers\Export;
use go1\report_helpers\ReportHelpersServiceProvider;
use go1\util\UtilServiceProvider;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

class ReportHelpersTest extends TestCase
{
    public function testContainerValidation()
    {
        $c = new Container;
        $c
            ->register(new UtilServiceProvider, [
                    's3Options'    => [
                        'key' => 'testing key',
                        'secret' => 'testing secret',
                        'region' => 'AU',
                        'bucket' => 'test bucket',
                        'version' => 'latest',
                        'endpoint' => 'test endpoint',
                    ],
                    'esOptions'    => [
                        'credential' => true,
                        'key' => 'testing key',
                        'secret' => 'testing secret',
                        'region' => 'testing region',
                        'endpoint' => 'http://es:9200',
                    ],
                    'esOptions.v8' => [
                        'credential' => true,
                        'key' => 'testing key',
                        'secret' => 'testing secret',
                        'region' => 'testing region',
                        'endpoint' => 'http://es:9200',
                    ]
                ]);
        $c->register(new ReportHelpersServiceProvider);

        $this->assertTrue($c['report_export'] instanceof Export);
    }
}
