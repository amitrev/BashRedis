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

    /**
     * Deletes keys matching a pattern.
     * Optimization: scan and delete in chunks to avoid fetching all keys into memory.
     */
    public function delByPattern(string $pattern): bool
    {
        try {
            if (false === $this->client->isConnected()) {
                return false;
            }

            $iterator = null;
            $totalDeleted = 0;
            while (false !== ($keys = $this->client->scan($iterator, $pattern, 1000))) {
                if (!empty($keys)) {
                    $totalDeleted += $this->delKeys($keys);
                }
            }

            return $totalDeleted > 0;
        } catch (NoConnectionException $e) {
            return false;
        }
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

    /**
     * Finds keys by pattern and returns their hGetAll results.
     * Optimization: use pipeline to reduce network roundtrips.
     */
    public function findAndHGetAll(string $pattern): array
    {
        $result = [];

        try {
            $keys = $this->findAllKeys($pattern);
        } catch (NoConnectionException $e) {
            return $result;
        }

        if (!empty($keys)) {
            $pipe = $this->client->multi(Redis::PIPELINE);
            $normalizedKeys = [];
            foreach ($keys as $key) {
                $key = $this->removePrefix($key);
                $normalizedKeys[] = $key;
                $pipe->hGetAll($key);
            }
            $replies = $pipe->exec();

            if (is_array($replies)) {
                foreach ($normalizedKeys as $index => $key) {
                    $result[$key] = $replies[$index];
                }
            }
        }

        return $result;
    }

    /**
     * Finds the first key matching a pattern and returns its value.
     * Optimization: Stops scanning as soon as the first key is found.
     */
    public function findAndGet(string $pattern)
    {
        if (false === $this->client->isConnected()) {
            return null;
        }

        $iterator = null;
        while (false !== ($keys = $this->client->scan($iterator, $pattern, 100))) {
            if (!empty($keys)) {
                $key = $this->removePrefix($keys[0]);

                return $this->client->get($key);
            }
        }

        return null;
    }

    /**
     * Finds all keys matching a pattern using SCAN.
     * Optimization: more efficient array merging.
     *
     * @throws NoConnectionException
     */
    public function findAllKeys(string $pattern): array
    {
        if (false === $this->client->isConnected()) {
            throw new NoConnectionException();
        }

        $foundKeys = [];
        $iterator = null;
        while (false !== ($keys = $this->client->scan($iterator, $pattern, 1000))) {
            foreach ($keys as $key) {
                $foundKeys[] = $key;
            }
        }

        return $foundKeys;
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

    /**
     * Removes the prefix from the key.
     * Optimization: replaced preg_replace with strpos/substr for better performance.
     */
    private function removePrefix(string $key): string
    {
        if ('' === $this->prefix || 0 !== strpos($key, $this->prefix)) {
            return $key;
        }

        return substr($key, \strlen($this->prefix));
    }

    private function setPrefix(?string $prefix): void
    {
        $this->prefix = $prefix ?? '';
        $this->client->setOption(Redis::OPT_PREFIX, $prefix);
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }
}
