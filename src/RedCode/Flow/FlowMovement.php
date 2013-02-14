<?php
/**
 * User: matrlx
 * Date: 9/14/12
 * Time: 5:03 PM
 */
namespace RedCode\Flow;

class FlowMovement
{
    private $from;
    private $to;

    public function __construct($from = '', $to = '')
    {
        $this->from = $from;
        $this->to = $to;
    }

    public function __toString()
    {
        return "{$this->from}-{$this->to}";
    }

    public function setFrom($from)
    {
        $this->from = $from;
    }

    public function getFrom()
    {
        return $this->from;
    }

    public function setTo($to)
    {
        $this->to = $to;
    }

    public function getTo()
    {
        return $this->to;
    }
}
