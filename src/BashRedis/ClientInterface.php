<?php

namespace Bash\Bundle\CacheBundle\BashRedis;

interface ClientInterface
{
    public function get($key, ?int $expire = null, bool $ttlRefresh = false);

    public function set($key, $data, ?int $expire = null);

    public function del($key): void;

    public function getAndSet($key, $dataCarry, ?array $params = null, ?int $expire = null, bool $ttlRefresh = true);

    public function hset($key, string $field, $data, ?int $expire = null): void;

    public function hgetall($key, ?int $expire = null, bool $ttlRefresh = true): array;

    public function hget($key, string $field, ?int $expire = null, bool $ttlRefresh = true);

    public function hmset($key, array $keyValues, ?int $expire = null): bool;

    public function hmget($key, array $fields, ?int $expire = null, bool $ttlRefresh = true): array;

    public function __call(string $command, array $arguments = []);

    public function getExpireTime(string $key);

    public function generateKey($value);
}
