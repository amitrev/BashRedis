<?php

namespace Bash\Bundle\CacheBundle\BashRedis;

use Bash\Bundle\CacheBundle\Exception\InvalidExpireKeyException;
use Bash\Bundle\CacheBundle\Exception\InvalidInputArgumentsException;
use Bash\Bundle\CacheBundle\Exception\NoConnectionException;
use Bash\Bundle\CacheBundle\Exception\WriteOperationFailedException;

use function call_user_func_array;
use function is_array;
use function is_callable;

use JsonException;
use Redis;

class Client implements ClientInterface
{
    private Redis $client;
    private array $expires;
    private string $prefix;
    private int $serialize;

    public function __construct(?array $parameters = null, ?array $options = null)
    {
        $this->prefix = $options['prefix'] ?? '';
        $this->expires = $options['expires'] ?? [];
        $this->serialize = defined('Redis::SERIALIZER_IGBINARY') ? Redis::SERIALIZER_IGBINARY : Redis::SERIALIZER_PHP;

        $this->client = new Redis();

        $method = 'connect';
        if (isset($options['persistent'])) {
            $method = 'pconnect';
        }

        if (false === strpos($parameters['dsn'], 'tcp')) {
            $connect = $this->client->{$method}($parameters['dsn']);
        } else {
            $connect = $this->client->{$method}($parameters['dsn'], $parameters['port'], $parameters['timeout'], $options['persistent'] ?? null);
        }

        if (true === $connect) {
            $this->setPrefix($this->prefix);
            $this->setSerialize();
            $this->client->select($parameters['database']);
        }
    }

    private function setSerialize(): void
    {
        if ($this->client->isConnected()) {
            $this->client->setOption(Redis::OPT_SERIALIZER, $this->serialize);
        }
    }

    private function removeSerialize(): void
    {
        if ($this->client->isConnected()) {
            $this->client->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
        }
    }

    /**
     * @throws NoConnectionException
     */
    public function get($key, ?int $expire = null)
    {
        if ($this->client->isConnected()) {
            $cacheKey = $this->generateKey($key);

            return $this->client->get($cacheKey);
        }

        throw new NoConnectionException();
    }

    /**
     * @throws NoConnectionException
     */
    public function set($key, $data, ?int $expire = null): bool
    {
        if ($this->client->isConnected()) {
            $cacheKey = $this->generateKey($key);

            $isInt = is_int($data);

            if ($isInt) {
                $this->removeSerialize();
            }

            $status = $this->client->set($cacheKey, $data, $expire);

            if ($isInt) {
                $this->setSerialize();
            }

            return $status;
        }

        throw new NoConnectionException();
    }

    /**
     * @throws NoConnectionException
     */
    public function del($key): int
    {
        if ($this->client->isConnected()) {
            $cacheKey = $this->generateKey($key);

            return $this->client->del($cacheKey);
        }

        throw new NoConnectionException();
    }

    /**
     * @throws WriteOperationFailedException
     * @throws NoConnectionException
     * @throws InvalidInputArgumentsException
     */
    public function getAndSet($key, $dataCarry, ?array $params = null, ?int $expire = null)
    {
        if ($this->client->isConnected()) {
            try {
                $data = $this->get($key, $expire);
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

    /**
     * @throws WriteOperationFailedException
     * @throws NoConnectionException
     */
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

    /**
     * @throws NoConnectionException
     */
    public function hgetall($key, ?int $expire = null): array
    {
        if ($this->client->isConnected()) {
            $key = $this->generateKey($key);

            return $this->client->hGetAll($key);
        }

        throw new NoConnectionException();
    }

    /**
     * @throws NoConnectionException
     */
    public function hget($key, string $field, ?int $expire = null)
    {
        if ($this->client->isConnected()) {
            $key = $this->generateKey($key);

            return $this->client->hGet($key, $field);
        }

        throw new NoConnectionException();
    }

    /**
     * @throws WriteOperationFailedException
     * @throws NoConnectionException
     */
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

    /**
     * @throws NoConnectionException
     */
    public function hmget($key, array $fields, ?int $expire = null): array
    {
        if ($this->client->isConnected()) {
            $key = $this->generateKey($key);

            return $this->client->hMGet($key, $fields);
        }

        throw new NoConnectionException();
    }

    public function delByPattern(string $pattern): bool
    {
        $status = false;

        try {
            $keys = $this->findAllKeys($pattern);
        } catch (NoConnectionException $e) {
            $keys = [];
        }

        if (!empty($keys)) {
            try {
                $status = $this->delKeys($keys);
            } catch (NoConnectionException $e) {
                return false;
            }
        }

        return $status;
    }

    /**
     * @throws NoConnectionException
     */
    public function delKeys(array $keys): bool
    {
        if ($this->client->isConnected()) {
            $this->setPrefix(null);
            $success = $this->client->del($keys);
            $this->setPrefix($this->prefix);

            return (bool) $success;
        }

        throw new NoConnectionException();
    }

    public function mget(array $keys)
    {
        $items = [];

        if (!empty($keys)) {
            $this->setPrefix(null);
            $items = $this->client->mget($keys);
            $this->setPrefix($this->prefix);
        }

        return $items;
    }

    public function mset(array $data)
    {
        $isSuccess = false;

        if (!empty($data)) {
            $this->setPrefix(null);
            $isSuccess = $this->client->mset($data);
            $this->setPrefix($this->prefix);
        }

        return $isSuccess;
    }

    public function findAndHGetAll(string $pattern): array
    {
        $result = [];

        try {
            $keys = $this->findAllKeys($pattern);
        } catch (NoConnectionException $e) {
            return $result;
        }

        if (!empty($keys)) {
            foreach ($keys as $key) {
                $key = $this->removePrefix($key);
                $result[$key] = $this->client->hGetAll($key);
            }
        }

        return $result;
    }

    public function findAndGet(string $pattern)
    {
        try {
            $keys = $this->findAllKeys($pattern);
        } catch (NoConnectionException $e) {
        }

        if (isset($keys[0])) {
            $key = $this->removePrefix($keys[0]);
            $result = $this->client->get($key);
        }

        return $result ?? null;
    }

    /**
     * @throws NoConnectionException
     */
    public function findAllKeys(string $pattern): array
    {
        if (false === $this->client->isConnected()) {
            throw new NoConnectionException();
        }

        $foundKeys = [];
        $iterator = null;
        while (false !== ($keys = $this->client->scan($iterator, $pattern))) {
            $foundKeys[] = $keys;
        }

        return array_merge([], ...$foundKeys);
    }

    /**
     * @throws NoConnectionException
     */
    public function __call(string $command, array $arguments = [])
    {
        if ($this->client->isConnected()) {
            $arguments[0] = $this->removePrefix($arguments[0]);

            return $this->client->{$command}(...$arguments);
        }

        throw new NoConnectionException();
    }

    /**
     * @throws InvalidExpireKeyException
     */
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
            $cacheKey = '';
            if (isset($value['base'])) {
                $base = $value['base'];
                unset($value['base']);
                $cacheKey = $base.'_';
            }

            try {
                $valueStr = json_encode($value, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                $valueStr = implode('', $value);
            }

            $cacheKey .= md5($valueStr);
        }

        return $this->removePrefix($cacheKey);
    }

    private function removePrefix(string $key): string
    {
        return preg_replace('/^'.$this->prefix.'/', '', $key);
    }

    private function setPrefix(?string $prefix): void
    {
        $this->client->setOption(Redis::OPT_PREFIX, $prefix);
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }
}
