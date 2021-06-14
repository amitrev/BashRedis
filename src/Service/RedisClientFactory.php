<?php

namespace Bash\Bundle\CacheBundle\Service;

use Predis\Client as RedisClient;

class RedisClientFactory
{
    public static function createRedis(array $config): RedisClient
    {
        $clientConfig = [
            'scheme' => $config['scheme'],
            'database' => $config['db'],
            'timeout' => $config['timeout'],
            'persistent' => $config['persistent'],
        ];

        if (isset($config['path'])) {
            $clientConfig['path'] = $config['path'];
        } else {
            $clientConfig['host'] = $config['host'];
            $clientConfig['port'] = $config['port'];
        }

        return new RedisClient(
            $clientConfig,
            [
                'prefix' => $config['prefix'] . ':',
            ]
        );
    }
}
