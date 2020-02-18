<?php
namespace go1\report_helpers\tests;

use Elasticsearch\Client;
use go1\report_helpers\ExportCsv;
use go1\report_helpers\PreprocessorInterface;
use PHPUnit\Framework\TestCase;

class ExportCsvTest extends TestCase
{
    private $fields;
    private $headers;
    private $results;
    private $formatters;

    protected function setUp() : void
    {
        $this->fields = ['field_id', 'field_key_1', 'field_key_2', 'field_key_3'];
        $this->headers = ['ID', 'Field 1', 'Field 2', 'Field 3'];
        $this->results = [
            123 => [1, 2, 3],
            234 => [2, 3, 4],
            345 => [3, 4, 5],
            456 => [4, 5, 6],
        ];
        $this->formatters = [
            'field_id' => function ($hit) {
                return $hit['_id'];
            },
            'field_key_1' => function ($hit) {
                return $this->results[$hit['_id']][0];
            },
            'field_key_2' => function ($hit) {
                return $this->results[$hit['_id']][1];
            },
            'field_key_3' => function ($hit) {
                return $this->results[$hit['_id']][2];
            },
        ];
    }

    public function testExportAllSelected()
    {
        $esMock = $this->prophesize(Client::class);
        $testSubject = new ExportCsv($esMock->reveal());
        $params = [
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'type' => [
                                    'key' => 'value'
                                ]
                            ]
                        ],
                    ]
                ],
                'sort' => 'enrolment sort',
                'aggs' => 'enrolment aggs',
            ]
        ];
        $selectedIds = [];
        $excludedIds = [123, 234];
        $allSelected = true;


        $esMock->search($params + [
                'scroll' => '30s',
                'size'   => 50,
            ])->shouldBeCalled()
            ->willReturn([
                '_scroll_id' => 1234567,
                'hits'       => [
                    'hits' => [
                        ['_id' => 123, '_source' => ['id' => 123]],
                        ['_id' => 234, '_source' => ['id' => 234]],
                        ['_id' => 345, '_source' => ['id' => 345]],
                        ['_id' => 456, '_source' => ['id' => 456]],
                    ],
                ],
            ])
        ;

        $esMock->scroll([
            'scroll_id' => 1234567,
            'scroll'    => '30s',
        ])->shouldBeCalled()
            ->willReturn([
                '_scroll_id' => 1234568,
                'hits'       => [
                    'hits' => [],
                ],
            ])
        ;

        $esMock->clearScroll(['scroll_id' => 1234568])->shouldBeCalled();

        try {
            $resource = $testSubject->export($this->fields, $this->headers, $params, $selectedIds, $excludedIds,
                $allSelected, $this->formatters);
            $this->assertTrue(is_resource($resource), 'Is resource');
            $this->assertEquals("ID,\"Field 1\",\"Field 2\",\"Field 3\"\n345,3,4,5\n456,4,5,6\n", fread($resource, 4096));
        } finally {
            if (!empty($resource) && is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    public function testExportNotAllSelected()
    {
        $esMock = $this->prophesize(Client::class);
        $testSubject = new ExportCsv($esMock->reveal());
        $params = [
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'type' => [
                                    'key' => 'value'
                                ]
                            ]
                        ],
                    ]
                ],
                'sort' => 'enrolment sort',
                'aggs' => 'enrolment aggs',
            ]
        ];
        $selectedIds = [123, 234];
        $excludedIds = [];
        $allSelected = false;

        $esMock->search([
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'type' => [
                                    'key' => 'value'
                                ]
                            ],
                            [
                                'ids' => [
                                    'values' => [123, 234]
                                ]
                            ]
                        ]
                    ]
                ],
                'sort' => 'enrolment sort',
                'aggs' => 'enrolment aggs',
            ],
            'scroll' => '30s',
            'size' => 50,
        ])->shouldBeCalled()
            ->willReturn([
                '_scroll_id' => 1234567,
                'hits'       => [
                    'hits' => [
                        ['_id' => 123, '_source' => ['id' => 123]],
                        ['_id' => 234, '_source' => ['id' => 234]],
                    ],
                ],
            ])
        ;

        $esMock->scroll([
            'scroll_id' => 1234567,
            'scroll'    => '30s',
        ])->shouldBeCalled()
            ->willReturn([
                '_scroll_id' => 1234568,
                'hits'       => [
                    'hits' => [],
                ],
            ])
        ;

        $esMock->clearScroll(['scroll_id' => 1234568])->shouldBeCalled();

        try {
            $resource = $testSubject->export($this->fields, $this->headers, $params, $selectedIds, $excludedIds,
                $allSelected, $this->formatters);
            $this->assertTrue(is_resource($resource), 'Is resource');
            $this->assertEquals("ID,\"Field 1\",\"Field 2\",\"Field 3\"\n123,1,2,3\n234,2,3,4\n", fread($resource, 4096));
        } finally {
            if (!empty($resource) && is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    public function testFormatters()
    {
        $esMock = $this->prophesize(Client::class);
        $testSubject = new ExportCsv($esMock->reveal());
        $params = [
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'type' => [
                                    'key' => 'value'
                                ]
                            ]
                        ],
                    ]
                ],
                'sort' => 'enrolment sort',
                'aggs' => 'enrolment aggs',
            ]
        ];
        $selectedIds = [];
        $excludedIds = [123, 234];
        $allSelected = true;


        $esMock->search($params + [
                'scroll' => '30s',
                'size'   => 50,
            ])->shouldBeCalled()
            ->willReturn([
                '_scroll_id' => 1234567,
                'hits'       => [
                    'hits' => [
                        ['_id' => 123, '_source' => ['id' => 123]],
                        ['_id' => 234, '_source' => ['id' => 234]],
                        ['_id' => 345, '_source' => ['id' => 345]],
                        ['_id' => 456, '_source' => ['id' => 456]],
                    ],
                ],
            ])
        ;

        $esMock->scroll([
            'scroll_id' => 1234567,
            'scroll'    => '30s',
        ])->shouldBeCalled()
            ->willReturn([
                '_scroll_id' => 1234568,
                'hits'       => [
                    'hits' => [],
                ],
            ])
        ;

        $esMock->clearScroll(['scroll_id' => 1234568])->shouldBeCalled();
        $formatters = array_merge($this->formatters, [
            'field_key_1' => function ($hit) {
                return $this->results[$hit['_id']][0].' rendered';
            },
            'field_key_2' => 'id'
        ]);

        try {
            $resource = $testSubject->export($this->fields, $this->headers, $params, $selectedIds, $excludedIds, $allSelected, $formatters);
            $this->assertTrue(is_resource($resource), 'Is resource');
            $this->assertEquals("ID,\"Field 1\",\"Field 2\",\"Field 3\"\n345,\"3 rendered\",345,5\n456,\"4 rendered\",456,6\n", fread($resource, 4096));
        } finally {
            if (!empty($resource) && is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    public function testPreprocess()
    {
        $esMock = $this->prophesize(Client::class);
        $preprocessMock = $this->prophesize(PreprocessorInterface::class);

        $testSubject = new ExportCsv($esMock->reveal(), $preprocessMock->reveal());
        $params = [];
        $selectedIds = [];
        $excludedIds = [123, 234];
        $allSelected = true;

        $esMock->search($params + ['scroll' => '30s', 'size'   => 50])
            ->shouldBeCalled()
            ->willReturn($results = [
                '_scroll_id' => 1234567,
                'hits'       => [
                    'hits' => [
                        ['_id' => 123, '_source' => ['id' => 123]],
                    ],
                ],
            ])
        ;

        $esMock->scroll(['scroll_id' => 1234567, 'scroll' => '30s'])
            ->shouldBeCalled()
            ->willReturn([
                '_scroll_id' => 1234568,
                'hits'       => [
                    'hits' => [],
                ],
            ])
        ;

        $esMock->clearScroll(['scroll_id' => 1234568])->shouldBeCalled();

        try {
            $resource = $testSubject->export($this->fields, $this->headers, $params, $selectedIds, $excludedIds, $allSelected, []);
            $preprocessMock->process($results)->shouldBeCalled();

            $this->assertTrue(is_resource($resource), 'Is resource');
        } finally {
            if (!empty($resource) && is_resource($resource)) {
                fclose($resource);
            }
        }
    }
}
