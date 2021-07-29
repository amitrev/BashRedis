<?php

namespace Bash\Bundle\CacheBundle\BashRedis;

use Bash\Bundle\CacheBundle\Exception\InvalidExpireKeyException;
use Bash\Bundle\CacheBundle\Exception\InvalidInputArgumentsException;
use Bash\Bundle\CacheBundle\Exception\NoConnectionException;
use Bash\Bundle\CacheBundle\Exception\WriteOperationFailedException;
use function call_user_func_array;
use function is_array;
use function is_callable;
use Redis;

class Client implements ClientInterface
{
    private Redis $client;
    private array $expires;
    private string $prefix;

    public function __construct(?array $parameters = null, ?array $options = null)
    {
        $this->prefix = $options['prefix'] ?? '';
        $this->expires = $options['expires'] ?? [];
        $this->client = new Redis();

        if (isset($options['persistent'])) {
            $connect = $this->client->pconnect($parameters['dsn'], $parameters['port'], $parameters['timeout'], $options['persistent']);
        } else {
            $connect = $this->client->connect($parameters['dsn'], $parameters['port'], $parameters['timeout']);
        }

        if ($connect) {
            $this->setPrefix($this->prefix);
            $this->setIGbinary();
            $this->client->select($parameters['database']);
        }
    }

    private function setIGbinary(): void
    {
        if ($this->client->isConnected()) {
            $this->client->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_IGBINARY);
        }
    }

    private function removeIGbinary(): void
    {
        if ($this->client->isConnected()) {
            $this->client->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
        }
    }

    public function get($key, ?int $expire = null, bool $ttlRefresh = true)
    {
        if ($this->client->isConnected()) {
            $cacheKey = $this->generateKey($key);

            $data = $this->client->get($cacheKey);

            if (false !== $data && null !== $expire && true === $ttlRefresh) {
                $this->client->expire($cacheKey, $expire);
            }

            return $data;
        }

        throw new NoConnectionException();
    }

    public function set($key, $data, ?int $expire = null): bool
    {
        if ($this->client->isConnected()) {
            $cacheKey = $this->generateKey($key);

            $isInt = is_int($data);

            if ($isInt) {
                $this->removeIGbinary();
            }

            $status = $this->client->set($cacheKey, $data, $expire);

            if ($isInt) {
                $this->setIGbinary();
            }

            return $status;
        }

        throw new NoConnectionException();
    }

    public function del($key): int
    {
        if ($this->client->isConnected()) {
            $cacheKey = $this->generateKey($key);
            $cacheKey = $this->_removePrefix($cacheKey);

            return $this->client->del($cacheKey);
        }

        throw new NoConnectionException();
    }

    public function getAndSet($key, $dataCarry, ?array $params = null, ?int $expire = null, bool $ttlRefresh = true)
    {
        if ($this->client->isConnected()) {
            try {
                $data = $this->get($key, $expire, $ttlRefresh);
            } catch (NoConnectionException $e) {
                throw new NoConnectionException('No redis', 0, $e);
            }

            if (false === $data && null !== $dataCarry) {
                if (is_callable($dataCarry)) {
                    if (null !== $params) {
                        $data = call_user_func_array($dataCarry, $params);
                    } else {
                        throw new InvalidInputArgumentsException('Params argument cannot be null');
                    }
                } else {
                    $data = $dataCarry;
                }
                $status = $this->set($key, $data, $expire);

                if (false === $status) {
                    throw new WriteOperationFailedException('Problem with write to key '.$key);
                }
            }

            return $data;
        }

        throw new NoConnectionException();
    }

    public function hset($key, string $field, $data, ?int $expire = null): void
    {
        if ($this->client->isConnected()) {
            $key = $this->generateKey($key);
            $status = $this->client->hSet($key, $field, $data);
            if (false === $status) {
                throw new WriteOperationFailedException('Problem with write to key '.$key);
            }

            if (null !== $expire) {
                $this->client->expire($key, $expire);
            }
        }

        throw new NoConnectionException();
    }

    public function hgetall($key, ?int $expire = null, bool $ttlRefresh = true): array
    {
        if ($this->client->isConnected()) {
            $key = $this->generateKey($key);
            $data = $this->client->hGetAll($key);
            if (!empty($data) && null !== $expire && true === $ttlRefresh) {
                $this->client->expire($key, $expire);
            }

            return $data;
        }

        throw new NoConnectionException();
    }

    public function hget($key, string $field, ?int $expire = null, bool $ttlRefresh = true)
    {
        if ($this->client->isConnected()) {
            $key = $this->generateKey($key);
            $data = $this->client->hGet($key, $field);

            if (false !== $data && null !== $expire && true === $ttlRefresh) {
                $this->client->expire($key, $expire);
            }

            return $data;
        }

        throw new NoConnectionException();
    }

    public function hmset($key, array $keyValues, ?int $expire = null): bool
    {
        if ($this->client->isConnected()) {
            $key = $this->generateKey($key);
            $status = $this->client->hMSet($key, $keyValues);
            if (false === $status) {
                throw new WriteOperationFailedException('Problem with write to key '.$key);
            }

            if (null !== $expire) {
                $this->client->expire($key, $expire);
            }

            return true;
        }

        throw new NoConnectionException();
    }

    public function hmget($key, array $fields, ?int $expire = null, bool $ttlRefresh = true): array
    {
        if ($this->client->isConnected()) {
            $key = $this->generateKey($key);
            $data = $this->client->hMGet($key, $fields);
            if (!empty($data) && true === $ttlRefresh && null !== $expire) {
                $this->client->expire($key, $ttlRefresh);
            }

            return $data;
        }

        throw new NoConnectionException();
    }

    public function delByPattern(string $pattern): bool
    {
        $status = false;

        try {
            $keys = $this->keys($pattern);
        } catch (NoConnectionException $e) {
        }

        if (!empty($keys)) {
            $this->setPrefix(null);
            $success = $this->client->del($keys);
            $this->setPrefix($this->prefix);

            $status = (bool) $success;
        }

        return $status;
    }

    public function findAndHGetAll(string $pattern): array
    {
        $result = [];

        try {
            $keys = $this->keys($pattern);
        } catch (NoConnectionException $e) {
            return $result;
        }

        if (!empty($keys)) {
            foreach ($keys as $key) {
                $key = $this->_removePrefix($key);
                $result[$key] = $this->client->hGetAll($key);
            }
        }

        return $result;
    }

    public function findAndGet(string $pattern)
    {
        try {
            $keys = $this->keys($pattern);
        } catch (NoConnectionException $e) {
        }

        if (isset($keys[0])) {
            $key = $this->_removePrefix($keys[0]);
            $result = $this->client->get($key);
        }

        return $result ?? null;
    }

    public function __call(string $command, array $arguments = [])
    {
        if ($this->client->isConnected()) {
            return $this->client->{$command}(...$arguments);
        }

        throw new NoConnectionException();
    }

    public function getExpireTime(string $key): int
    {
        if (isset($this->expires[$key])) {
            return $this->expires[$key];
        }

        throw new InvalidExpireKeyException('Key ('.$key.') not found');
    }

    public function generateKey($value): string
    {
        $cacheKey = $value;

        if (is_array($value)) {
            $cacheKey = md5(json_encode($value));

            if (isset($value['base'])) {
                $base = $value['base'];
                unset($value['base']);

                $cacheKey = $base.'_'.md5(json_encode($value));
            }
        }

        return $cacheKey;
    }

    public function _removePrefix(string $key): string
    {
        return preg_replace('/^'.$this->prefix.'/', '', $key);
    }

    private function setPrefix(?string $prefix): void
    {
        $this->client->setOption(Redis::OPT_PREFIX, $prefix);
    }
}
