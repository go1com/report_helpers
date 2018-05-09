<?php

namespace {
    $csvFileContent = '';
}

namespace go1\report_helpers {
    function stream_context_create() {
        return 'context';
    }

    function fopen() {
        return [];
    }

    function fputcsv(&$stream, $data) {
        $stream[] = $data;
    }

    function fclose($stream) {
        global $csvFileContent;
        $lines = [];
        foreach($stream as $line) {
            $lines[] = implode(',', $line);
        }
        $csvFileContent = implode("\n", $lines);
    }
}

namespace go1\report_helpers\tests\export {

    use Aws\S3\S3Client;
    use Elasticsearch\Client as ElasticsearchClient;
    use go1\report_helpers\Export;
    use PHPUnit\Framework\TestCase;
    use ReflectionObject;

    class ExportTest extends TestCase
    {
        protected $s3Client;
        protected $elasticsearchClient;
        /** @var Export */
        protected $helper;
        protected $fields;
        protected $headers;
        protected $results;
        protected $formatters;

        public function setUp()
        {
            $this->s3Client = $this
                ->getMockBuilder(S3Client::class)
                ->setMethods(['registerStreamWrapper'])
                ->disableOriginalConstructor()
                ->getMock();

            $this->elasticsearchClient = $this
                ->getMockBuilder(ElasticsearchClient::class)
                ->setMethods(['search', 'scroll', 'clearScroll'])
                ->disableOriginalConstructor()
                ->getMock();

            $this->helper = new Export($this->s3Client, $this->elasticsearchClient);

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

        public function testDoExportAllSelected()
        {
            $portal = 'portal object';
            $payload = 'jwt payload';
            $lo = 'lo object';

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

            $this->elasticsearchClient
                ->expects($this->once())
                ->method('search')
                ->with($params + [
                    'scroll' => '30s',
                    'size' => 50,
                ])
                ->will($this->returnValue(json_encode([
                    '_scroll_id' => 1234567,
                    'hits' => [
                        'hits' => [
                            ['_id' => 123, '_source' => ['id' => 123]],
                            ['_id' => 234, '_source' => ['id' => 234]],
                            ['_id' => 345, '_source' => ['id' => 345]],
                            ['_id' => 456, '_source' => ['id' => 456]],
                        ]
                    ]
                ])));
            $this->elasticsearchClient
                ->expects($this->once())
                ->method('scroll')
                ->with([
                    'scroll_id' => 1234567,
                    'scroll' => '30s',
                ])
                ->will($this->returnValue(json_encode([
                    '_scroll_id' => 1234568,
                    'hits' => [
                        'hits' => []
                    ]
                ])));
            $this->elasticsearchClient
                ->expects($this->once())
                ->method('clearScroll')
                ->with([
                    'scroll_id' => 1234568,
                ]);

            $this->helper->doExport('s3 bucket', 's3 key', $this->fields, $this->headers, $params, $selectedIds, $excludedIds, $allSelected, $this->formatters);

            global $csvFileContent;
            $this->assertEquals("ID,Field 1,Field 2,Field 3\n345,3,4,5\n456,4,5,6", $csvFileContent);
        }

        public function testDoExportNotAllSelected()
        {
            $portal = 'portal object';
            $payload = 'jwt payload';
            $lo = 'lo object';

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

            $this->elasticsearchClient
                ->expects($this->once())
                ->method('search')
                ->with([
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
                ])
                ->will($this->returnValue(json_encode([
                    '_scroll_id' => 1234567,
                    'hits' => [
                        'hits' => [
                            ['_id' => 123, '_source' => ['id' => 123]],
                            ['_id' => 234, '_source' => ['id' => 234]],
                        ]
                    ]
                ])));
            $this->elasticsearchClient
                ->expects($this->once())
                ->method('scroll')
                ->with([
                    'scroll_id' => 1234567,
                    'scroll' => '30s',
                ])
                ->will($this->returnValue(json_encode([
                    '_scroll_id' => 1234568,
                    'hits' => [
                        'hits' => []
                    ]
                ])));
            $this->elasticsearchClient
                ->expects($this->once())
                ->method('clearScroll')
                ->with([
                    'scroll_id' => 1234568,
                ]);

            $this->helper->doExport('s3 bucket', 's3 key', $this->fields, $this->headers, $params, $selectedIds, $excludedIds, $allSelected, $this->formatters);

            global $csvFileContent;
            $this->assertEquals("ID,Field 1,Field 2,Field 3\n123,1,2,3\n234,2,3,4", $csvFileContent);
        }

        public function testGetFile()
        {
            $file = $this->helper->getFile('anywhere', 'abc', '123.jpg');
            $this->assertEquals('https://s3-anywhere.amazonaws.com/abc/123.jpg', $file);
        }

        public function testGetValues()
        {
            $fields = ['field_key_1', 'field_key_2', 'field_key_3', 'field_invalid'];
            $hit = ['_source' => ['field_key_1' => '123', 'field_key_2' => 'abc', 'field_key_3' => 'abc123', 'abc' => ['123' => 123]]];
            $formatters = [
                'field_key_1' => function ($hit) {
                    return $hit['_source']['field_key_1'] . ' rendered';
                },
                'field_key_2' => 'abc.123'
            ];
            $method = (new ReflectionObject($this->helper))->getMethod('getValues');
            $method->setAccessible(true);
            $values = $method->invoke($this->helper, $fields, $hit, $formatters);
            $this->assertEquals(['123 rendered', 123, 'abc123', ''], $values);
        }
    }

}
