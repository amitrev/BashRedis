<?php

namespace Bash\Bundle\CacheBundle\BashRedis;

use Bash\Bundle\CacheBundle\Exception\InvalidExpireKeyException;
use Bash\Bundle\CacheBundle\Exception\InvalidInputArgumentsException;
use Bash\Bundle\CacheBundle\Exception\NoConnectionException;
use Bash\Bundle\CacheBundle\Exception\WriteOperationFailedException;
use Redis;

class Client implements ClientInterface
{
    private Redis $client;
    private array $expires;

    public function __construct(?array $parameters = null, ?array $options = null)
    {
        $this->expires = $options['expires'] ?? [];
        $this->client = new Redis();

        if (isset($options['persistent'])) {
            $connect = $this->client->pconnect($parameters['dsn'], $parameters['port'], $parameters['timeout'], $options['persistent']);
        } else {
            $connect = $this->client->connect($parameters['dsn'], $parameters['port'], $parameters['timeout']);
        }

        if ($connect) {
            $this->client->setOption(Redis::OPT_PREFIX, $options['prefix'] ?? ''.':');
            $this->client->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_IGBINARY);
            $this->client->select($parameters['database']);
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

            return $this->client->set($cacheKey, $data, $expire);
        }

        throw new NoConnectionException();
    }

    public function del($key): int
    {
        if ($this->client->isConnected()) {
            $cacheKey = $this->generateKey($key);

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
                throw new NoConnectionException();
            }

            if (false === $data && null !== $dataCarry) {
                if (\is_callable($dataCarry)) {
                    if (null !== $params) {
                        $data = \call_user_func_array($dataCarry, $params);
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

            return $status;
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
        try {
            $keys = $this->keys($pattern);
        } catch (NoConnectionException $e) {
            return false;
        }

        if (!empty($keys)) {
            $prefix = $this->client->getOption(Redis::OPT_PREFIX) ?? '';

            $this->client->setOption(Redis::OPT_PREFIX, null);
            $success = $this->client->del($keys);
            $this->client->setOption(Redis::OPT_PREFIX, $prefix);

            return (bool) $success;
        }

        return true;
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

        if (\is_array($value)) {
            $cacheKey = md5(json_encode($value));

            if (isset($value['base'])) {
                $base = $value['base'];
                unset($value['base']);

                $cacheKey = $base.'_'.md5(json_encode($value));
            }
        }

        return $cacheKey;
    }
}
