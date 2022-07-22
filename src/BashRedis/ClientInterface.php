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

    public function hget($key, string $field, ?int $expire = null);

    public function hmset($key, array $keyValues, ?int $expire = null): bool;

    public function hmget($key, array $fields, ?int $expire = null): array;

    public function __call(string $command, array $arguments = []);

    public function getExpireTime(string $key);

    public function generateKey($value);
}
