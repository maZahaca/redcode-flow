<?php

namespace RedCode\Flow\Exception;
use RedCode\Flow\Movement;

/**
 * @author maZahaca
 */ 
class MovementUnsupportedException extends \Exception
{
    public function __construct(Movement $movement)
    {
        $this->message = sprintf('Status transit from %s to %s unsupported', $movement->getFrom(), $movement->getTo());
    }
}
 