<?php

namespace Bash\Bundle\CacheBundle\Service;

class RedisClientFactory
{
    public function __invoke(string $host, string $port, int $db = 0, int $timeout = 3)
    {
        return new RedisClient($host, $port, $db, $timeout);
    }
}
