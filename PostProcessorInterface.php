<?php

namespace go1\report_helpers;

interface PostProcessorInterface
{
    public function setQuery(array $params);
    
    public function checkQueryForStatusFilter();
		
    public function process(array &$results);
}
