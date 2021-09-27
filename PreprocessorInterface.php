<?php

namespace go1\report_helpers;

interface PreprocessorInterface
{
   /**
    * For post processing tasks like filtering
    **/
    public function setPostProcessor(PostProcessorInterface $e);


    /**
     * Elastic search result.
     *
     * @param array $results
     */
    public function process(array &$results);
}
