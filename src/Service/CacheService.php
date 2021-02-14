<?php

namespace Bash\Bundle\CacheBundle\Service;

use function json_encode;
use function md5;
use Predis\Client as RedisClient;

class CacheService
{
    private RedisClient $cacheData;
    private RedisClient $cacheCounter;
    private CacheKeyService $cacheKeys;
    private string $expireType = 'EX';

    public function __construct(RedisClient $cacheData, RedisClient $cacheCounter, CacheKeyService $cacheKeys)
    {
        $this->cacheData = $cacheData;
        $this->cacheCounter = $cacheCounter;
        $this->cacheKeys = $cacheKeys;
    }

    public function getAndSetData($key, ?callable $callback = null, ?array $paramArr = null, ?int $expirationTime = null)
    {
        $data = $this->getData($key);

        if (false === $data && null !== $callback) {
            $data = \call_user_func_array($callback, $paramArr);
            $status = $this->setData($key, $data, $expirationTime);
            //TODO: log on dev or not?
        }

        return $data;
    }

    public function getData($key)
    {
        $cacheKey = $this->cacheKeys->generateCacheKey($key);
        $data = $this->cacheData->get($cacheKey);

        //TODO: use Symfony Serialize @ver:1
        return unserialize($data);
    }

    public function setData($key, $data, ?int $expirationTime = null)
    {
        $cacheKey = $this->cacheKeys->generateCacheKey($key);

        $moreParams = [];
        if (null !== $expirationTime) {
            $moreParams = [
                $this->expireType,
                $expirationTime,
            ];
        }

        //TODO: use Symfony Serialize @ver:1
        $data = serialize($data);

        return $this->cacheData->set($cacheKey, $data, ...$moreParams);
    }

    public function delData($key): void
    {
        $cacheKey = $this->cacheKeys->generateCacheKey($key);
        $this->cacheData->del($cacheKey);
    }

    public function delDataByPatterns(array $patterns): void
    {
        if (empty($patterns)) {
            return;
        }

        $prefix = (string) $this->cacheData->getOptions()->prefix;
        foreach ($patterns as $pattern) {
            $keys = $this->cacheData->keys($pattern);
            if (!empty($keys)) {
                foreach ($keys as $key) {
                    $key = str_replace($prefix, '', $key);
                    $this->cacheData->del($key);
                }
            }
        }
    }

    public function getAndSetCounter($key, int $count, int $expirationTime = null): int
    {
        $currentCount = $this->getCounter($key);

        if (null === $currentCount && null !== $count) {
            $currentCount = $count;
            $this->setCounter($key, $currentCount, $expirationTime);
        }

        return (int) $currentCount;
    }

    public function getCounter($key): ?int
    {
        $cacheKey = $this->cacheKeys->generateCacheKey($key);

        return $this->cacheCounter->get($cacheKey);
    }

    public function setCounter($key, $data, ?int $expirationTime = null): void
    {
        $cacheKey = $this->cacheKeys->generateCacheKey($key);

        $moreParams = [];
        if (null !== $expirationTime) {
            $moreParams = [
                $this->expireType,
                $expirationTime,
            ];
        }

        $this->cacheCounter->set($cacheKey, $data, ...$moreParams);
    }

    public function delCounter($key): void
    {
        $cacheKey = $this->cacheKeys->generateCacheKey($key);
        $this->cacheCounter->del($cacheKey);
    }

    public function getCountersByPattern(string $pattern): array
    {
        $keys = $this->cacheCounter->keys($pattern);
        $counters = [];

        if (!empty($keys)) {
            foreach ($keys as $key) {
                [$prefix, $key] = explode(':', $key);
                $counters[$key] = $this->cacheCounter->get($key);
            }
        }

        return $counters;
    }

    public function incrementCounter($key): void
    {
        $cacheKey = $this->cacheKeys->generateCacheKey($key);
        $this->cacheCounter->incr($cacheKey);
    }
}
