<?php

namespace Bash\Bundle\RedisBundle;

use Bash\Bundle\RedisBundle\DependencyInjection\BashNameExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BashRedisBundle extends Bundle
{
    public $extension;

    /**
     * Overridden to allow for the custom extension alias.
     */
    public function getContainerExtension(): BashNameExtension
    {
        if (null === $this->extension) {
            $this->extension = new BashNameExtension();
        }

        return $this->extension;
    }
}
