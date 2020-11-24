<?php

namespace Bash\Bundle\CacheBundle\Service;

class RedisClientFactory
{
    public static function createRedis(array $config): RedisClient
    {
        return new RedisClient($config['host'], $config['port'], $config['db'], $config['timeout']);
    }
}
