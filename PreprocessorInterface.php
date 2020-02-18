<?php

namespace go1\report_helpers;

interface PreprocessorInterface
{
    /**
     * Elastic search result.
     *
     * @param array $results
     */
    public function process(array &$results);
}
