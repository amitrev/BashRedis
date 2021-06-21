<?php

namespace Bash\Bundle\CacheBundle\BashRedis;

class Factory implements FactoryInterface
{
    protected static string $clientClass = Client::class;

    public static function create(array $parameters = [], array $options = []): ClientInterface
    {
        return new static::$clientClass($parameters, $options);
    }
}
