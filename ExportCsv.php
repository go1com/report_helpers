<?php

namespace go1\report_helpers;

use Elasticsearch\Client as ElasticsearchClient;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use function is_null;

class ExportCsv
{
    /** @var ElasticsearchClient */
    protected $elasticsearchClient;
    protected $elasticSearchBatchSize = 50;
    protected ?PostProcessorInterface $postProcessor;
    protected ?PreprocessorInterface $preprocessor;

    public function __construct(ElasticsearchClient $elasticsearchClient, ?PreprocessorInterface $preprocessor = null, ?PostProcessorInterface $postProcessor = null)
    {
        $this->elasticsearchClient = $elasticsearchClient;
        $this->preprocessor = $preprocessor;
        $this->postProcessor = $postProcessor;
    }

    public function setElasticSearchBatchSize(int $size)
    {
        $this->elasticSearchBatchSize = $size;
    }

    /**
     * @return resource
     */
    public function export(
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
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('Could not create file descriptor');
        }

        // Write header.
        fputcsv($stream, $headers);

        if (!$allSelected) {
            // Improve performance by not loading all records then filter out.
            $params['body']['query']['bool']['must'][] = [
                'ids' => [
                    'values' => $selectedIds,
                ],
            ];
        }

        $params += ['scroll' => '30s', 'size' => $this->elasticSearchBatchSize];
        $docs = $this->elasticsearchClient->search($params);
        $docs = is_scalar($docs) ? json_decode($docs, true) : $docs;
        $scrollId = $docs['_scroll_id'] ?? null;

        if ($this->preprocessor && $this->postProcessor) {
            $this->postProcessor->setQuery($params)->checkQueryForStatusFilter();
            $this->preprocessor->setPostProcessor($this->postProcessor);
        }

        while (true) {
            if (!is_null($preprocess)) {
                $preprocess($docs);
            }

            if ($this->preprocessor) {
                $this->preprocessor->process($docs);
            }

            if (isset($docs['hits']['hits']) && count($docs['hits']['hits']) > 0) {
                foreach ($docs['hits']['hits'] as $hit) {
                    if (empty($excludedIds) || !in_array($hit['_id'], $excludedIds)) {
                        $csv = $this->getValues($fields, $hit, $formatters, $customValuesSettings);
                        fputcsv($stream, $csv);
                    }
                }
            } else {
                if (isset($scrollId)) {
                    try {
                        $this->elasticsearchClient->clearScroll(['scroll_id' => $scrollId]);
                    } catch (Missing404Exception $e) {
                    }
                }
                break;
            }

            $docs = $this->elasticsearchClient->scroll([
                'scroll_id' => $scrollId,
                'scroll'    => '30s',
            ]);

            if (isset($docs['_scroll_id'])) {
                $scrollId = $docs['_scroll_id'];
            }
        }

        fseek($stream, 0);

        return $stream;
    }

    protected function getValues($fields, $hit, $formatters = [], $customValuesSettings = [])
    {
        $values = [];
        $customValues = $this->parseCustomValuesSettings($customValuesSettings);
        foreach ($fields as $key) {
            if (isset($formatters[$key]) && is_callable($formatters[$key])) {
                if (isset($customValues[$key])) {
                    $values[] = $formatters[$key]($hit, $customValues[$key]);
                } else {
                    $values[] = $formatters[$key]($hit);
                }
            } else {
                if (isset($formatters[$key]) && is_string($formatters[$key])) {
                    $value = array_get($hit['_source'], $formatters[$key]);
                } elseif (isset($hit['_source'][$key])) {
                    $value = $hit['_source'][$key];
                } else {
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

    public function parseCustomValuesSettings($customValuesSettings = [])
    {
        $results = [];
        if (!empty($customValuesSettings)) {
            foreach ($customValuesSettings as $customValuesSetting) {
                foreach ($customValuesSetting['fields'] as $field) {
                    $results[$field] = $customValuesSetting['rules'];
                }
            }
        }

        return $results;
    }
}
