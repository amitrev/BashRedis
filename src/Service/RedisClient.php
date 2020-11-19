<?php

namespace Bash\Bundle\CacheBundle\Service;

use RuntimeException;

class RedisClient
{
    private $conn;
    private string $host;
    private string $port;
    private string $timeout;

    public function __construct(string $host, string $port, int $db, int $timeout) {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;

        $this->getSocket();
        $this->select($db);
    }

    public function __call(string $method, array $args)
    {
        if ($this->conn === false) {
            return null;
        }
        array_unshift($args, $method);
        $cmd = '*' . count($args) . "\r\n";
        foreach ($args as $item) {
            $cmd .= '$' . strlen($item) . "\r\n" . $item . "\r\n";
        }

        fwrite($this->getSocket(), $cmd);

        return $this->parseResponse();
    }

    private function getSocket()
    {
        return $this->conn ?: ($this->conn = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout));
    }

    private function parseResponse()
    {
        $line = fgets($this->getSocket());
        [$type, $result] = [$line[0], substr($line, 1, -2)];
        if ($type === '-') {
            throw new RuntimeException($result);
        }

        if ($type === '$') {
            if ($result === -1) {
                $result = null;
            } else {
                $fp = $this->getSocket();
                if ($result > 8192) {
                    $result = '';
                    while (!feof($fp)) {
                        $result .= fgets($fp);
                        $stream_meta_data = stream_get_meta_data($fp);
                        if ($stream_meta_data['unread_bytes'] <= 0) {
                            break;
                        }
                    }
                } else {
                    $line = fread($fp, $result + 2);
                    $result = substr($line, 0, -2);
                }
            }
        } elseif ($type === '*') {
            $count = ( int ) $result;
            for ($i = 0, $result = array(); $i < $count; $i++) {
                $result[] = $this->parseResponse();
            }
        }

        return $result;
    }
}
