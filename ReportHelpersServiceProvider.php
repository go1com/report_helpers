<?php

namespace go1\report_helpers;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class ReportHelpersServiceProvider implements ServiceProviderInterface
{
    public function register(Container $c)
    {
        $c['report_export'] = function (Container $c) {
            return new Export($c['go1.client.s3'], $c['go1.client.es']);
        };
    }
}
