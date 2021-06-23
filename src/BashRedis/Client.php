<?php

namespace Bash\Bundle\CacheBundle\BashRedis;

use Bash\Bundle\CacheBundle\Exception\InvalidExpireKeyException;
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
            $this->client->setOption(Redis::OPT_PREFIX, $options['prefix'] ?? '' . ':');
            $this->client->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_IGBINARY);
            $this->client->select($parameters['database']);
        }
    }

    public function get($key, ?int $expire = null, bool $ttlRefresh = false)
    {
        if ($this->client->isConnected()) {
            $cacheKey = $this->generateKey($key);

            $data = $this->client->get($cacheKey);

            if ($data === false) {
                return false;
            }

            if ($expire !== null && $ttlRefresh === true) {
                $this->client->expire($cacheKey, $expire);
            }

            return $data;
        }

        return false;
    }

    public function set($key, $data, ?int $expire = null): bool
    {
        if ($this->client->isConnected()) {
            $cacheKey = $this->generateKey($key);

            return $this->client->set($cacheKey, $data, $expire);
        }

        return false;
    }

    public function del($key): void
    {
        if ($this->client->isConnected()) {
            $cacheKey = $this->generateKey($key);
            $this->client->del($cacheKey);
        }
    }

    public function getAndSet($key, ?callable $callback = null, ?array $params = null, ?int $expire = null, bool $ttlRefresh = true)
    {
        if ($this->client->isConnected()) {
            $data = $this->get($key);

            if (false === $data && null !== $callback) {
                $data = \call_user_func_array($callback, $params);
                $status = $this->set($key, $data, $expire);

                if ($status === false) {
                    throw new WriteOperationFailedException('Problem with write to key ' . $key);
                }
            }

            return $data;
        }

        return null;
    }

    public function __call(string $command, array $arguments = [])
    {
        if ($this->client->isConnected()) {
            return $this->client->{$command}(...$arguments);
        }

        return null;
    }

    public function getExpireTime(string $key): int
    {
        if (isset($this->expires[$key])) {
            return $this->expires[$key];
        }

        throw new InvalidExpireKeyException('Key (' . $key . ') not found');
    }

    public function generateKey($value): string
    {
        $cacheKey = $value;

        if (\is_array($value)) {
            $cacheKey = md5(json_encode($value));

            if (isset($value['base'])) {
                $base = $value['base'];
                unset($value['base']);

                $cacheKey = $base . '_' . md5(json_encode($value));
            }
        }

        return $cacheKey;
    }
}
