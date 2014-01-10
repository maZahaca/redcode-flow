<?php

namespace RedCode\Flow\Exception;

/**
 * @author maZahaca
 */ 
class EntityUnsupportedException extends \Exception
{
    public function __construct($class)
    {
        if(is_object($class)) {
            $class = get_class($class);
        }
        $this->message = sprintf('Object should be an instance of %s', $class);
    }
}
 