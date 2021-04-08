<?php

namespace Bash\Bundle\CacheBundle\Service;

use function json_encode;
use function md5;
use Predis\Client as RedisClient;

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

    public function getAndSetData($key, ?callable $callback = null, ?array $paramArr = null, ?int $expirationTime = null, bool $ttlRefresh = true)
    {
        $data = $this->getData($key, $expirationTime, $ttlRefresh);

        if (false === $data && null !== $callback) {
            $data = \call_user_func_array($callback, $paramArr);
            $status = $this->setData($key, $data, $expirationTime);
            //TODO: log on dev or not?
        }

        return $data;
    }

    public function getData($key, $resetTtl = null, bool $ttlRefresh = true)
    {
        $cacheKey = $this->generateCacheKey($key);
        $data = $this->cacheData->get($cacheKey);

        if (null !== $data && null !== $resetTtl && true === $ttlRefresh) {
            $this->cacheData->expire($cacheKey, $resetTtl);
        }

        //TODO: use Symfony Serialize @ver:1
        return unserialize($data);
    }

    public function setData($key, $data, ?int $expirationTime = null)
    {
        $cacheKey = $this->generateCacheKey($key);

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

    public function hgetAllDataByPattern(string $pattern): array
    {
        $keys = $this->cacheData->keys($pattern);
        $counters = [];

        if (!empty($keys)) {
            foreach ($keys as $key) {
                [$prefix, $key] = explode(':', $key);
                $counters[$key] = $this->cacheData->hgetall($key);
            }
        }

        return $counters;
    }

    public function delData($key): void
    {
        $cacheKey = $this->generateCacheKey($key);
        $this->cacheData->del($cacheKey);
    }

    public function hset($key, string $field, $data, ?int $expirationTime = null): void
    {
        $key = $this->generateCacheKey($key);

        $data = serialize($data);

        $this->cacheData->hset($key, $field, $data);

        if (null !== $expirationTime) {
            $this->cacheData->expire($key, $expirationTime);
        }
    }

    public function hget($key, $field, ?int $resetTtl = null, bool $ttlRefresh = true)
    {
        $cacheKey = $this->generateCacheKey($key);
        $data = $this->cacheData->hget($cacheKey, $field);

        if (null !== $data && null !== $resetTtl && true === $ttlRefresh) {
            $this->cacheData->expire($cacheKey, $resetTtl);
        }

        //TODO: use Symfony Serialize @ver:1
        return unserialize($data);
    }

    public function hmget($key, $fields, ?int $resetTtl = null, bool $ttlRefresh = true)
    {
        $cacheKey = $this->generateCacheKey($key);
        $data = $this->cacheData->hmget($cacheKey, $fields);

        if (null !== $data && null !== $resetTtl && true === $ttlRefresh) {
            $this->cacheData->expire($cacheKey, $resetTtl);
        }

        $result = [];
        foreach ($data as $i => $item) {
            $result[$fields[$i]] = unserialize($item);
        }

        return $result;
    }

    public function hmset($key, array $keyValues, ?int $expirationTime = null)
    {
        $key = $this->generateCacheKey($key);

        foreach ($keyValues as $field => $data) {
            $keyValues[$field] = serialize($data);
        }

        $this->cacheData->hmset($key, $keyValues);

        if (null !== $expirationTime) {
            $this->cacheData->expire($key, $expirationTime);
        }
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
        $cacheKey = $this->generateCacheKey($key);

        return $this->cacheCounter->get($cacheKey);
    }

    public function setCounter($key, $data, ?int $expirationTime = null): void
    {
        $cacheKey = $this->generateCacheKey($key);

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
        $cacheKey = $this->generateCacheKey($key);
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
        $cacheKey = $this->generateCacheKey($key);
        $this->cacheCounter->incr($cacheKey);
    }

    public function decrementCounter($key): void
    {
        $cacheKey = $this->generateCacheKey($key);
        $this->cacheCounter->decr($cacheKey);
    }

    private function generateCacheKey($value): string
    {
        $cacheKey = $value;
        if (\is_array($value)) {
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
