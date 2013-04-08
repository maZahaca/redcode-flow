<?php
/**
 * User: matrlx
 * Date: 9/14/12
 * Time: 5:03 PM
 */
namespace RedCode\Flow;

class FlowMovement
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

    public function __construct($from = null, $to = null, $callback = null)
    {
        $this->from     = $from;
        $this->to       = $to;
        $this->callback = $callback;
    }

    public function __toString()
    {
        return "{$this->from}-{$this->to}";
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
