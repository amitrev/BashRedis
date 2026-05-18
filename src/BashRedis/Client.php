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
    private ?array $parameters;
    private ?array $options;
    private bool $isConnected = false;
    private ?int $currentSerializer = null;

    public function __construct(?array $parameters = null, ?array $options = null)
    {
        $this->parameters = $parameters;
        $this->options = $options;
        $this->prefix = $options['prefix'] ?? '';
        $this->expires = $options['expires'] ?? [];
        $this->serialize = defined('Redis::SERIALIZER_IGBINARY') ? Redis::SERIALIZER_IGBINARY : Redis::SERIALIZER_PHP;

        $this->client = new Redis();
    }

    private function connect(): void
    {
        if ($this->isConnected) {
            return;
        }

        $method = 'connect';
        if (isset($this->options['persistent'])) {
            $method = 'pconnect';
        }

        if (false === strpos($this->parameters['dsn'], 'tcp')) {
            $connect = $this->client->{$method}($this->parameters['dsn']);
        } else {
            $connect = $this->client->{$method}(
                $this->parameters['dsn'],
                $this->parameters['port'],
                $this->parameters['timeout'],
                $this->options['persistent'] ?? null
            );
        }

        if (true === $connect) {
            $this->isConnected = true;
            $this->setPrefix($this->prefix);
            $this->setSerialize();
            $this->client->select($this->parameters['database']);
        }
    }

    private function setSerialize(): void
    {
        if ($this->currentSerializer !== $this->serialize && $this->client->isConnected()) {
            $this->client->setOption(Redis::OPT_SERIALIZER, $this->serialize);
            $this->currentSerializer = $this->serialize;
        }
    }

    private function removeSerialize(): void
    {
        if ($this->currentSerializer !== Redis::SERIALIZER_NONE && $this->client->isConnected()) {
            $this->client->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
            $this->currentSerializer = Redis::SERIALIZER_NONE;
        }
    }

    /**
     * @throws NoConnectionException
     */
    public function get($key, ?int $expire = null)
    {
        $this->connect();
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
        $this->connect();
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
        $this->connect();
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
        $this->connect();
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
        $this->connect();
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
        $this->connect();
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
        $this->connect();
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
        $this->connect();
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
        $this->connect();
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
            $this->connect();
            if (false === $this->client->isConnected()) {
                return false;
            }

            $iterator = null;
            $isDeleted = false;
            while (true) {
                $keys = $this->client->scan($iterator, $pattern, 1000);
                if (!empty($keys)) {
                    $this->delKeys($keys);
                    $isDeleted = true;
                }

                if (0 === (int)$iterator) {
                    break;
                }
            }

            return $isDeleted;
        } catch (NoConnectionException $e) {
            return false;
        }
    }

    /**
     * @throws NoConnectionException
     */
    public function delKeys(array $keys): bool
    {
        $this->connect();
        if ($this->client->isConnected()) {
            $this->client->setOption(Redis::OPT_PREFIX, null);
            $success = $this->client->del($keys);
            $this->client->setOption(Redis::OPT_PREFIX, $this->prefix);

            return (bool) $success;
        }

        throw new NoConnectionException();
    }

    public function mget(array $keys)
    {
        $this->connect();
        $items = [];

        if (!empty($keys)) {
            $this->client->setOption(Redis::OPT_PREFIX, null);
            $items = $this->client->mget($keys);
            $this->client->setOption(Redis::OPT_PREFIX, $this->prefix);
        }

        return $items;
    }

    public function mset(array $data)
    {
        $this->connect();
        $isSuccess = false;

        if (!empty($data)) {
            $this->client->setOption(Redis::OPT_PREFIX, null);
            $isSuccess = $this->client->mset($data);
            $this->client->setOption(Redis::OPT_PREFIX, $this->prefix);
        }

        return $isSuccess;
    }

    /**
     * Finds keys by pattern and returns their hGetAll results.
     * Optimization: use chunked pipeline to reduce network roundtrips and memory pressure.
     */
    public function findAndHGetAll(string $pattern): array
    {
        $this->connect();
        if (false === $this->client->isConnected()) {
            return [];
        }

        $result = [];
        $iterator = null;

        while (true) {
            $keys = $this->client->scan($iterator, $pattern, 1000);
            if (empty($keys)) {
                if (0 === (int)$iterator) {
                    break;
                }
                continue;
            }

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

            if (0 === (int)$iterator) {
                break;
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
        $this->connect();
        if (false === $this->client->isConnected()) {
            return null;
        }

        $iterator = null;
        while (true) {
            $keys = $this->client->scan($iterator, $pattern, 100);
            if (!empty($keys)) {
                $key = $this->removePrefix($keys[0]);

                return $this->client->get($key);
            }

            if (0 === (int)$iterator) {
                break;
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
        $this->connect();
        if (false === $this->client->isConnected()) {
            throw new NoConnectionException();
        }

        $foundKeys = [];
        $iterator = null;
        while (true) {
            $keys = $this->client->scan($iterator, $pattern, 1000);
            if (!empty($keys)) {
                array_push($foundKeys, ...$keys);
            }

            if (0 === (int)$iterator) {
                break;
            }
        }

        return $foundKeys;
    }

    /**
     * @throws NoConnectionException
     */
    public function __call(string $command, array $arguments = [])
    {
        $this->connect();
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
