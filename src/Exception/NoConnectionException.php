<?php


namespace Bash\Bundle\CacheBundle\Exception;


use Throwable;

class NoConnectionException extends \Exception
{
    public function __construct($message = "Redis server connection faded away!", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
