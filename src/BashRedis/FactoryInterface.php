<?php

namespace Bash\Bundle\CacheBundle\BashRedis;

interface FactoryInterface
{
    public static function create(array $parameters = [], array $options = []): ClientInterface;
}
