<?php

namespace Bash\Bundle\CacheBundle\Service;

use function json_encode;
use function md5;

class CacheService
{
    private $cacheData;
    private $cacheCounter;

    public function __construct($cacheData, $cacheCounter)
    {
        $this->cacheData = $cacheData;
        $this->cacheCounter = $cacheCounter;
    }

    public function getData($key, callable $callback, array $paramArr, int $expirationTime = null, array $tags = []) {

    }
}
