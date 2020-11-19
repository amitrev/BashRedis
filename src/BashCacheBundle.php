<?php

namespace Bash\Bundle\CacheBundle;

use Bash\Bundle\CacheBundle\DependencyInjection\BashCacheExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BashCacheBundle extends Bundle
{
    public function getContainerExtension()
    {
       return new BashCacheExtension();
    }
}
