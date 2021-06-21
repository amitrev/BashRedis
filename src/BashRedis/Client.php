<?php

namespace Bash\Bundle\CacheBundle\BashRedis;

use Bash\Bundle\CacheBundle\Exception\InvalidExpireKeyException;
use Redis;

class Client implements ClientInterface
{
    private Redis $client;

    private string $prefix;

    private array $expires;

    public function __construct(?array $parameters = null, ?array $options = null)
    {
        $this->prefix = $options['prefix'] ?? '';
        $this->expires = $options['expires'] ?? [];
        $this->client = new Redis();

        if (isset($options['persistent'])) {
            $connect = $this->client->pconnect($parameters['dsn'], $parameters['port'], $parameters['timeout'], $options['persistent']);
        } else {
            $connect = $this->client->connect($parameters['dsn'], $parameters['port'], $parameters['timeout']);
        }

        if ($connect) {
            $this->select($parameters['database']);
        }
    }

    public function __call(string $command, array $arguments = [])
    {
        if ($this->client->isConnected()) {
            return $this->client->{$command}(...$arguments);
        }

        return null;
    }


    public function getExpireTime(string $key): int
    {
        if (isset($this->expires[$key])) {
            return $this->expires[$key];
        }

        throw new InvalidExpireKeyException('Key (' . $key . ') not found');
    }

    public function generateKey(array $keyArray): string
    {
        return sprintf('%s.%s', $this->prefix, 'temp');
    }
}
