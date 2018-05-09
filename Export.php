<?php

namespace go1\report_helpers;

use Aws\S3\S3Client;
use Elasticsearch\Client as ElasticsearchClient;
use Elasticsearch\Common\Exceptions\Missing404Exception;

class Export
{
    /** @var S3Client */
    protected $s3Client;
    /** @var ElasticsearchClient */
    protected $elasticsearchClient;

    public function __construct(S3Client $s3Client, ElasticsearchClient $elasticsearchClient)
    {
        $this->s3Client = $s3Client;
        $this->elasticsearchClient = $elasticsearchClient;
    }

    public function doExport($bucket, $key, $fields, $headers, $params, $selectedIds, $excludedIds, $allSelected, $formatters = [])
    {
        $this->s3Client->registerStreamWrapper();
        $context = stream_context_create(array(
            's3' => array(
                'ACL' => 'public-read'
            )
        ));
        // Opening a file in 'w' mode truncates the file automatically.
        $stream = fopen("s3://{$bucket}/{$key}", 'w', 0, $context);

        // Write header.
        fputcsv($stream, $headers);

        if (!$allSelected) {
            // Improve performance by not loading all records then filter out.
            $params['body']['query']['bool']['must'][] = [
                'ids' => [
                    'values' => $selectedIds
                ]
            ];
        }

        $params += [
            'scroll' => '30s',
            'size' => 50,
        ];

        $docs = json_decode($this->elasticsearchClient->search($params), true);
        $scrollId = $docs['_scroll_id'];

        while (\true) {
            if (count($docs['hits']['hits']) > 0) {
                foreach ($docs['hits']['hits'] as $hit) {
                    if (empty($excludedIds) || !in_array($hit['_id'], $excludedIds)) {
                        $csv = $this->getValues($fields, $hit, $formatters);
                        // Write row.
                        fputcsv($stream, $csv);
                    }
                }
            }
            else {
                if (isset($scrollId)) {
                    try {
                        $this->elasticsearchClient->clearScroll([
                                'scroll_id' => $scrollId,
                        ]);
                    }
                    catch (Missing404Exception $e) {
                    }
                }
                break;
            }

            $docs = json_decode($this->elasticsearchClient->scroll([
                'scroll_id' => $scrollId,
                'scroll' => '30s',
            ]), true);

            if (isset($docs['_scroll_id'])) {
                $scrollId = $docs['_scroll_id'];
            }
        }

        fclose($stream);
    }

    public function getFile($region, $bucket, $key)
    {
        $domain = getenv('MONOLITH') ? getenv('AWS_S3_ENDPOINT') : "https://s3-{$region}.amazonaws.com";
        return "$domain/$bucket/$key";
    }

    protected function getValues($fields, $hit, $formatters = [])
    {
        $values = [];
        foreach ($fields as $key) {
            if (isset($formatters[$key]) && is_callable($formatters[$key])) {
                $values[] = $formatters[$key]($hit);
            }
            else {
                if (isset($formatters[$key]) && is_string($formatters[$key])) {
                    $value = array_get($hit['_source'], $formatters[$key]);
                }
                elseif (isset($hit['_source'][$key])) {
                    $value = $hit['_source'][$key];
                }
                else {
                    $value = '';
                }
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $values[] = $value;
            }
        }
        return $values;
    }
}
