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

    public function __construct($from = null, $to = null)
    {
        $this->from = $from;
        $this->to = $to;
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
}
