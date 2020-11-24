<?php

namespace Bash\Bundle\CacheBundle\Service;

use function json_encode;
use function md5;

class CacheService
{
    private RedisClient $cacheData;
    private RedisClient $cacheCounter;

    public function __construct(RedisClient $cacheData, RedisClient $cacheCounter)
    {
        $this->cacheData = $cacheData;
        $this->cacheCounter = $cacheCounter;
    }

    public function getData($key, ?callable $callback = null, ?array $paramArr = null, ?int $expirationTime = null) {
        $cacheKey = $this->generateCacheKey($key);

        $data = $this->cacheData->get($cacheKey);

        return [$cacheKey, $data];

        if (null !== $callback && null === $data) {
            $data = call_user_func_array($callback, $paramArr);
        }

        return $data;
    }

    private function setData($key, $data) {

    }

    private function generateCacheKey($value)
    {
        $cacheKey = $value;
        if (is_array($value)) {
            if (isset($value['base'])) {
                $base = $value['base'];
                unset($value['base']);
                return $base . '_' . md5(json_encode($value));
            }
            return md5(json_encode($value));
        }
        return $cacheKey;
    }
}
