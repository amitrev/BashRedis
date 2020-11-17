<?php

namespace Bash\Bundle\RedisBundle\Tests\Service;

use Bash\Bundle\RedisBundle\Service\SimpleService;
use PHPUnit\Framework\TestCase;

class SimpleServiceTest extends TestCase
{
    public function testAddition(): void
    {
        $service = new SimpleService();

        self::assertEquals(2, $service->addition());
    }
}
