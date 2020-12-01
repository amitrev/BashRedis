<?php

namespace Bash\Bundle\CacheBundle\Service;

use Predis\Client as RedisClient;

class RedisClientFactory
{
    public static function createRedis(array $config): RedisClient
    {

        return new RedisClient([
            'scheme' => 'tcp',
            'host'   => $config['host'],
            'port'   => $config['port'],
        ]);

        //return new RedisClient($config['host'], $config['port'], $config['db'], $config['timeout']);
    }
}
