<?php

namespace RedCode\Flow;

class Movement
{
    /**
     * @var string|object
     */
    private $from;

    /**
     * @var string|object
     */
    private $to;

    /**
     * @var null|\Closure
     */
    private $callback = null;

    /**
     * @param mixed $from
     * @param mixed $to
     * @param \Closure|null $callback
     */
    public function __construct($from = null, $to = null, $callback = null)
    {
        $this->from     = $from;
        $this->to       = $to;
        $this->callback = $callback;
    }

    /**
     * @return object|string
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * @return object|string
     */
    public function getTo()
    {
        return $this->to;
    }

    public function isAllowed($entity, $entityMovement = null)
    {
        if($this->callback !== null) {
            return call_user_func($this->callback, $entity, $entityMovement);
        }
        return true;
    }
}
