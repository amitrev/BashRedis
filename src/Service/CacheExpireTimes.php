<?php

namespace Bash\Bundle\CacheBundle\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class CacheExpireTimes
{
    private int $shortExpire;
    private int $mediumExpire;
    private int $longExpire;

    public function __construct(ContainerBagInterface $params)
    {
        $this->shortExpire = (int) $params->get('short');
        $this->mediumExpire = (int) $params->get('medium');
        $this->longExpire = (int) $params->get('long');
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
