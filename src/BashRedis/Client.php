<?php

namespace Bash\Bundle\CacheBundle\BashRedis;

use Bash\Bundle\CacheBundle\Exception\InvalidExpireKeyException;
use Redis;

class Client implements ClientInterface
{
    private Redis $client;

    private string $prefix;

    private array $expires;

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
            $this->select($parameters['database']);
        }
    }

    public function get(array $key, ?int $expire = null, bool $ttlRefresh = false)
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

            return unserialize($data);
        }

        return false;
    }

    public function set(array $key, $data, ?int $expire = null): bool
    {
        if ($this->client->isConnected()) {
            $cacheKey = $this->generateKey($key);

            $moreParams = [];
            if ($expire !== null) {
                $moreParams = [
                    'EX',
                    $expire,
                ];
            }

            $data = serialize($data);

            return $this->client->set($cacheKey, $data, ...$moreParams);
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
                    //TODO: exception or silent log?
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

        return sprintf('%s:%s', $this->prefix, $cacheKey);
    }
}
