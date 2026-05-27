<?php

namespace Bash\Bundle\CacheBundle\BashRedis;

use Bash\Bundle\CacheBundle\Exception\InvalidExpireKeyException;
use Bash\Bundle\CacheBundle\Exception\InvalidInputArgumentsException;
use Bash\Bundle\CacheBundle\Exception\NoConnectionException;
use Bash\Bundle\CacheBundle\Exception\WriteOperationFailedException;

use function call_user_func_array;
use function defined;
use function implode;
use function is_array;
use function is_callable;
use function is_int;
use function is_string;
use function json_encode;
use function md5;
use function strlen;
use function strncmp;
use function strpos;
use function substr;

use JsonException;
use Redis;

class Client implements ClientInterface
{
    private Redis $client;
    private array $expires;
    private string $prefix;
    private int $prefixLength;
    private int $serialize;
    private ?array $parameters;
    private ?array $options;
    private bool $isConnected = false;
    private bool $connectionAttempted = false;
    private ?int $currentSerializer = null;

    private static ?int $defaultSerializer = null;

    public function __construct(?array $parameters = null, ?array $options = null)
    {
        $this->parameters = $parameters;
        $this->options = $options;
        $this->prefix = $options['prefix'] ?? '';
        $this->prefixLength = \strlen($this->prefix);
        $this->expires = $options['expires'] ?? [];

        if (null === self::$defaultSerializer) {
            self::$defaultSerializer = defined('Redis::SERIALIZER_IGBINARY') ? Redis::SERIALIZER_IGBINARY : Redis::SERIALIZER_PHP;
        }
        $this->serialize = self::$defaultSerializer;

        $this->client = new Redis();
    }

    private function connect(): void
    {
        if ($this->connectionAttempted) {
            return;
        }

        $this->connectionAttempted = true;

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
            if (0 !== (int)$this->parameters['database']) {
                $this->client->select($this->parameters['database']);
            }
        }
    }

    private function setSerialize(): void
    {
        if ($this->currentSerializer !== $this->serialize) {
            $this->client->setOption(Redis::OPT_SERIALIZER, $this->serialize);
            $this->currentSerializer = $this->serialize;
        }
    }

    private function removeSerialize(): void
    {
        if ($this->currentSerializer !== Redis::SERIALIZER_NONE) {
            $this->client->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
            $this->currentSerializer = Redis::SERIALIZER_NONE;
        }
    }

    /**
     * @throws InvalidExpireKeyException
     */
    public function getExpireTime(string $key): int
    {
        if (isset($this->expires[$key])) {
            return $this->expires[$key];
        }

        throw new InvalidExpireKeyException('Key (' . $key . ') not found');
    }

    /**
     * @throws NoConnectionException
     */
    public function hexists($key, string $field): bool
    {
        $this->connect();
        if ($this->isConnected) {
            $key = $this->generateKey($key);

            return (bool)$this->client->hExists($key, $field);
        }

        throw new NoConnectionException();
    }

    /**
     * @throws NoConnectionException
     */
    public function decr($key): int
    {
        $this->connect();
        if ($this->isConnected) {
            $key = $this->generateKey($key);

            return (int)$this->client->decr($key);
        }

        throw new NoConnectionException();
    }

    /**
     * @throws NoConnectionException
     */
    public function hincrbyfloat($key, string $field, float $value): float
    {
        $this->connect();
        if ($this->isConnected) {
            $key = $this->generateKey($key);

            return (float)$this->client->hIncrByFloat($key, $field, $value);
        }

        throw new NoConnectionException();
    }

    /**
     * @throws NoConnectionException
     */
    public function hlen($key): int
    {
        $this->connect();
        if ($this->isConnected) {
            $key = $this->generateKey($key);

            return (int)$this->client->hLen($key);
        }

        throw new NoConnectionException();
    }

    /**
     * @throws NoConnectionException
     */
    public function hkeys($key): array
    {
        $this->connect();
        if ($this->isConnected) {
            $key = $this->generateKey($key);

            return (array)$this->client->hKeys($key);
        }

        throw new NoConnectionException();
    }

    /**
     * @throws NoConnectionException
     */
    public function hincrby($key, string $field, int $value): int
    {
        $this->connect();
        if ($this->isConnected) {
            $key = $this->generateKey($key);

            return (int)$this->client->hIncrBy($key, $field, $value);
        }

        throw new NoConnectionException();
    }

    /**
     * @throws NoConnectionException
     */
    public function hdel($key, ...$fields): int
    {
        $this->connect();
        if ($this->isConnected) {
            $key = $this->generateKey($key);

            return (int)$this->client->hDel($key, ...$fields);
        }

        throw new NoConnectionException();
    }

    /**
     * @throws NoConnectionException
     */
    public function incr($key): int
    {
        $this->connect();
        if ($this->isConnected) {
            $key = $this->generateKey($key);

            return (int)$this->client->incr($key);
        }

        throw new NoConnectionException();
    }

    /**
     * @throws NoConnectionException
     */
    public function ttl($key): int
    {
        $this->connect();
        if ($this->isConnected) {
            $key = $this->generateKey($key);

            return (int)$this->client->ttl($key);
        }

        throw new NoConnectionException();
    }

    /**
     * @throws NoConnectionException
     */
    public function incrBy($key, int $value): int
    {
        $this->connect();
        if ($this->isConnected) {
            $key = $this->generateKey($key);

            return $this->client->incrBy($key, $value);
        }

        throw new NoConnectionException();
    }

    /**
     * @throws NoConnectionException
     */
    public function decrBy($key, int $value): int
    {
        $this->connect();
        if ($this->isConnected) {
            $key = $this->generateKey($key);

            return $this->client->decrBy($key, $value);
        }

        throw new NoConnectionException();
    }

    /**
     * @throws NoConnectionException
     */
    public function exists($key): int
    {
        $this->connect();
        if ($this->isConnected) {
            $key = $this->generateKey($key);

            return (int)$this->client->exists($key);
        }

        throw new NoConnectionException();
    }

    /**
     * @throws NoConnectionException
     */
    public function expire($key, int $expire): bool
    {
        $this->connect();
        if ($this->isConnected) {
            $key = $this->generateKey($key);

            return $this->client->expire($key, $expire);
        }

        throw new NoConnectionException();
    }

    /**
     * @throws NoConnectionException
     */
    public function get($key, ?int $expire = null)
    {
        $this->connect();
        if ($this->isConnected) {
            $cacheKey = $this->generateKey($key);

            if (null !== $expire) {
                $pipe = $this->client->multi(Redis::PIPELINE);
                $pipe->get($cacheKey);
                $pipe->expire($cacheKey, $expire);
                $replies = $pipe->exec();

                return $replies[0] ?? false;
            }

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
        if ($this->isConnected) {
            $cacheKey = $this->generateKey($key);

            return $this->doSet($cacheKey, $key, $data, $expire);
        }

        throw new NoConnectionException();
    }

    private function doSet(string $cacheKey, $key, $data, ?int $expire = null): bool
    {
        $isInt = is_int($data);

        if ($isInt) {
            $this->removeSerialize();
        }

        if (null === $expire && \is_string($key) && isset($this->expires[$key])) {
            $expire = $this->expires[$key];
        }

        $status = $this->client->set($cacheKey, $data, $expire);

        if ($isInt) {
            $this->setSerialize();
        }

        return $status;
    }

    /**
     * @throws NoConnectionException
     */
    public function del($key): int
    {
        $this->connect();
        if ($this->isConnected) {
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
        if (!$this->isConnected) {
            throw new NoConnectionException();
        }

        $cacheKey = $this->generateKey($key);
        $data = $this->client->get($cacheKey);

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

            $status = $this->doSet($cacheKey, $key, $data, $expire);

            if (false === $status) {
                throw new WriteOperationFailedException('Problem with write to key ' . $cacheKey);
            }
        }

        return $data;
    }

    /**
     * @throws WriteOperationFailedException
     * @throws NoConnectionException
     */
    public function hset($key, string $field, $data, ?int $expire = null): void
    {
        $this->connect();
        if (!$this->isConnected) {
            throw new NoConnectionException();
        }

        $key = $this->generateKey($key);

        if (null !== $expire) {
            $pipe = $this->client->multi(Redis::PIPELINE);
            $pipe->hSet($key, $field, $data);
            $pipe->expire($key, $expire);
            $replies = $pipe->exec();
            $status = $replies[0] ?? false;
        } else {
            $status = $this->client->hSet($key, $field, $data);
        }

        if (false === $status) {
            throw new WriteOperationFailedException('Problem with write to key ' . $key);
        }
    }

    /**
     * @throws NoConnectionException
     */
    public function hgetall($key, ?int $expire = null): array
    {
        $this->connect();
        if ($this->isConnected) {
            $key = $this->generateKey($key);

            if (null !== $expire) {
                $pipe = $this->client->multi(Redis::PIPELINE);
                $pipe->hGetAll($key);
                $pipe->expire($key, $expire);
                $replies = $pipe->exec();

                return is_array($replies[0]) ? $replies[0] : [];
            }

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
        if ($this->isConnected) {
            $key = $this->generateKey($key);

            if (null !== $expire) {
                $pipe = $this->client->multi(Redis::PIPELINE);
                $pipe->hGet($key, $field);
                $pipe->expire($key, $expire);
                $replies = $pipe->exec();

                return $replies[0] ?? false;
            }

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
        if (!$this->isConnected) {
            throw new NoConnectionException();
        }

        $key = $this->generateKey($key);

        if (null !== $expire) {
            $pipe = $this->client->multi(Redis::PIPELINE);
            $pipe->hMSet($key, $keyValues);
            $pipe->expire($key, $expire);
            $replies = $pipe->exec();
            $status = $replies[0] ?? false;
        } else {
            $status = $this->client->hMSet($key, $keyValues);
        }

        if (false === $status) {
            throw new WriteOperationFailedException('Problem with write to key ' . $key);
        }

        return true;
    }

    /**
     * @throws NoConnectionException
     */
    public function hmget($key, array $fields, ?int $expire = null): array
    {
        $this->connect();
        if ($this->isConnected) {
            $key = $this->generateKey($key);

            if (null !== $expire) {
                $pipe = $this->client->multi(Redis::PIPELINE);
                $pipe->hMGet($key, $fields);
                $pipe->expire($key, $expire);
                $replies = $pipe->exec();

                return is_array($replies[0]) ? $replies[0] : [];
            }

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
            if (false === $this->isConnected) {
                return false;
            }

            $iterator = null;
            $isDeleted = false;
            while (true) {
                $keys = $this->client->scan($iterator, $pattern, 1000);
                if (is_array($keys) && !empty($keys)) {
                    $strippedKeys = [];
                    foreach ($keys as $key) {
                        $strippedKeys[] = $this->removePrefix($key);
                    }
                    $this->delKeys($strippedKeys);
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
        if (empty($keys)) {
            return false;
        }

        $this->connect();
        if ($this->isConnected) {
            if ($this->prefixLength > 0) {
                $this->client->setOption(Redis::OPT_PREFIX, null);
            }
            $success = $this->client->del($keys);
            if ($this->prefixLength > 0) {
                $this->client->setOption(Redis::OPT_PREFIX, $this->prefix);
            }

            return (bool) $success;
        }

        throw new NoConnectionException();
    }

    public function mget(array $keys)
    {
        $this->connect();
        $items = [];

        if (!empty($keys)) {
            if ($this->prefixLength > 0) {
                $this->client->setOption(Redis::OPT_PREFIX, null);
            }
            $items = $this->client->mget($keys);
            if ($this->prefixLength > 0) {
                $this->client->setOption(Redis::OPT_PREFIX, $this->prefix);
            }
        }

        return $items;
    }

    public function mset(array $data, ?int $expire = null)
    {
        $this->connect();
        $isSuccess = false;

        if (!empty($data)) {
            if ($this->prefixLength > 0) {
                $this->client->setOption(Redis::OPT_PREFIX, null);
            }

            if (null !== $expire) {
                $pipe = $this->client->multi(Redis::PIPELINE);
                $pipe->mset($data);
                foreach ($data as $key => $value) {
                    $pipe->expire($key, $expire);
                }
                $replies = $pipe->exec();
                $isSuccess = $replies[0] ?? false;
            } else {
                $isSuccess = $this->client->mset($data);
            }

            if ($this->prefixLength > 0) {
                $this->client->setOption(Redis::OPT_PREFIX, $this->prefix);
            }
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
        if (false === $this->isConnected) {
            return [];
        }

        $result = [];
        $iterator = null;

        while (true) {
            $keys = $this->client->scan($iterator, $pattern, 1000);
            if (is_array($keys) && !empty($keys)) {
                if ($this->prefixLength > 0) {
                    $this->client->setOption(Redis::OPT_PREFIX, null);
                }

                $pipe = $this->client->multi(Redis::PIPELINE);
                foreach ($keys as $key) {
                    $pipe->hGetAll($key);
                }
                $replies = $pipe->exec();

                if ($this->prefixLength > 0) {
                    $this->client->setOption(Redis::OPT_PREFIX, $this->prefix);
                }

                if (is_array($replies)) {
                    foreach ($keys as $index => $key) {
                        $strippedKey = $this->removePrefix($key);
                        $result[$strippedKey] = $replies[$index];
                    }
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
        if (false === $this->isConnected) {
            return null;
        }

        $iterator = null;
        while (true) {
            $keys = $this->client->scan($iterator, $pattern, 100);
            if (is_array($keys) && !empty($keys)) {
                $prefixedKey = $keys[0];

                if ($this->prefixLength > 0) {
                    $this->client->setOption(Redis::OPT_PREFIX, null);
                }
                $result = $this->client->get($prefixedKey);
                if ($this->prefixLength > 0) {
                    $this->client->setOption(Redis::OPT_PREFIX, $this->prefix);
                }

                return $result;
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
        if (false === $this->isConnected) {
            throw new NoConnectionException();
        }

        $foundKeys = [];
        $iterator = null;
        while (true) {
            $keys = $this->client->scan($iterator, $pattern, 1000);
            if (is_array($keys) && !empty($keys)) {
                foreach ($keys as $key) {
                    $foundKeys[] = $this->removePrefix($key);
                }
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
        if ($this->isConnected) {
            if (!empty($arguments) && is_string($arguments[0])) {
                $arguments[0] = $this->removePrefix($arguments[0]);
            }

            return $this->client->{$command}(...$arguments);
        }

        throw new NoConnectionException();
    }

    public function generateKey($value): string
    {
        if (!is_array($value)) {
            return $this->removePrefix((string)$value);
        }

        $cacheKey = '';
        if (isset($value['base'])) {
            $cacheKey = $value['base'] . '_';
            unset($value['base']);
        }

        try {
            $valueStr = json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $valueStr = implode('', $value);
        }

        return $this->removePrefix($cacheKey . md5($valueStr));
    }

    /**
     * Removes the prefix from the key.
     * Optimization: replaced preg_replace with strncmp/substr for better performance.
     */
    private function removePrefix(string $key): string
    {
        if (0 === $this->prefixLength || 0 !== strncmp($key, $this->prefix, $this->prefixLength)) {
            return $key;
        }

        return substr($key, $this->prefixLength);
    }

    private function setPrefix(?string $prefix): void
    {
        $this->prefix = $prefix ?? '';
        $this->prefixLength = \strlen($this->prefix);
        $this->client->setOption(Redis::OPT_PREFIX, $prefix);
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }
}
