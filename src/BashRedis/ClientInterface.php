<?php

namespace Bash\Bundle\CacheBundle\BashRedis;

interface ClientInterface
{
    public function get($key, ?int $expire = null);

    public function set($key, $data, ?int $expire = null);

    public function del($key): int;

    public function getAndSet($key, $dataCarry, ?array $params = null, ?int $expire = null);

    public function hset($key, string $field, $data, ?int $expire = null): void;

    public function hgetall($key, ?int $expire = null): array;

    public function hincrbyfloat($key, string $field, float $value): float;

    public function hget($key, string $field, ?int $expire = null);

    public function hmset($key, array $keyValues, ?int $expire = null): bool;

    public function hmget($key, array $fields, ?int $expire = null): array;

    public function hexists($key, string $field): bool;

    public function hlen($key): int;

    public function hkeys($key): array;

    public function hdel($key, ...$fields): int;

    public function hincrby($key, string $field, int $value): int;

    public function hincrbyfloat($key, string $field, float $value): float;

    public function incr($key): int;

    public function decr($key): int;

    public function incrBy($key, int $value): int;

    public function decrBy($key, int $value): int;

    public function exists($key): int;

    public function ttl($key): int;

    public function expire($key, int $expire): bool;

    public function mget(array $keys);

    public function mset(array $data, ?int $expire = null);

    public function delKeys(array $keys): bool;

    public function delByPattern(string $pattern): bool;

    public function findAndHGetAll(string $pattern): array;

    public function findAndGet(string $pattern);

    public function findAllKeys(string $pattern): array;

    public function __call(string $command, array $arguments = []);

    public function getExpireTime(string $key);

    public function generateKey($value);
}
