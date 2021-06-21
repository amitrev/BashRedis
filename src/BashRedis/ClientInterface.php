<?php

namespace Bash\Bundle\CacheBundle\BashRedis;

interface ClientInterface
{
    public function __call(string $command, array $arguments = []);

    public function getExpireTime(string $key);

    public function generateKey(array $keyArray);
}
