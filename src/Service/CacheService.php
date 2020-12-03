<?php

namespace Bash\Bundle\CacheBundle\Service;

use Predis\Client as RedisClient;

use function json_encode;
use function md5;

class CacheService
{
    private RedisClient $cacheData;
    private RedisClient $cacheCounter;
    private string $expireType = 'EX';

    public function __construct(RedisClient $cacheData, RedisClient $cacheCounter)
    {
        $this->cacheData = $cacheData;
        $this->cacheCounter = $cacheCounter;
    }

    public function getAndSetData($key, ?callable $callback = null, ?array $paramArr = null, ?int $expirationTime = null) {
        $data = $this->getData($key);

        if (null === $data && null !== $callback) {
            $data = call_user_func_array($callback, $paramArr);
            $status = $this->setData($key, $data, $expirationTime);
            //TODO: log on dev or not?
        }

        return $data;
    }

    public function getData($key) {
        $cacheKey = $this->generateCacheKey($key);
        $data = $this->cacheData->get($cacheKey);

        return json_decode($data, true);
    }

    public function setData($key, $data, ?int $expirationTime = null) {
        $cacheKey = $this->generateCacheKey($key);

        $moreParams = [];
        if ( null !== $expirationTime ) {
            $moreParams = [
                $this->expireType,
                $expirationTime
            ];
        }

        $data = json_encode($data);

        return $this->cacheData->set($cacheKey, $data, ...$moreParams);
    }

    public function delData($key): void
    {
        $cacheKey = $this->generateCacheKey($key);
        $this->cacheData->del($cacheKey);
    }

    public function delDataByPatterns(array $patterns): void
    {
        if (empty($patterns)) {
            return;
        }

        foreach ($patterns as $pattern) {
            $keys = $this->cacheData->keys($pattern);
            if ( !empty($keys) ) {
                foreach ($keys as $key) {
                    $this->cacheData->del($key);
                }
            }
        }
    }

    public function getCounter($key) {
        $cacheKey = $this->generateCacheKey($key);
        $data = $this->cacheCounter->get($cacheKey);

        return json_decode($data, true);
    }

    public function setCounter($key, $data, ?int $expirationTime = null) {
        $cacheKey = $this->generateCacheKey($key);

        $moreParams = [];
        if ( null !== $expirationTime ) {
            $moreParams = [
                $this->expireType,
                $expirationTime
            ];
        }

        $data = json_encode($data);

        return $this->cacheCounter->set($cacheKey, $data, ...$moreParams);
    }

    public function delCounter($key): void
    {
        $cacheKey = $this->generateCacheKey($key);
        $this->cacheCounter->del($cacheKey);
    }

    public function incrementCounter($key): void
    {
        $cacheKey = $this->generateCacheKey($key);
        $this->cacheCounter->incr($cacheKey);
    }

    private function generateCacheKey($value): string
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
