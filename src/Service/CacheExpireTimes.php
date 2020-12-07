<?php

namespace Bash\Bundle\CacheBundle\Service;

use Symfony\Component\DependencyInjection\ContainerBuilder;

class CacheExpireTimes
{
    private int $shortExpire;
    private int $mediumExpire;
    private int $longExpire;

    public function __construct(ContainerBuilder $container)
    {
        $this->shortExpire = $container->getParameter('bash_cache.expires.short');
        $this->mediumExpire = $container->getParameter('bash_cache.expires.medium');
        $this->longExpire = $container->getParameter('bash_cache.expires.long');
    }

    public function getShortExpire(): int
    {
        return $this->shortExpire;
    }

    public function getMediumExpire(): int
    {
        return $this->mediumExpire;
    }

    public function getLongExpire(): int
    {
        return $this->longExpire;
    }
}
