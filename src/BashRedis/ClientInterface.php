<?php

namespace Bash\Bundle\CacheBundle\BashRedis;

interface ClientInterface
{
    public function get($key, ?int $expire = null, bool $ttlRefresh = false);

    public function set($key, $data, ?int $expire = null);

    public function del($key): void;

    public function getAndSet($key, ?callable $callback = null, ?array $params = null, ?int $expire = null, bool $ttlRefresh = true);

    public function __call(string $command, array $arguments = []);

    public function getExpireTime(string $key);

    public function generateKey($value);
}
