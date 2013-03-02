<?php
/**
 * @author maZahaca
 */
namespace RedCode\Flow\Item;

use RedCode\Flow\FlowMovement;

abstract class BaseFlow implements IFlow
{
    /**
     * Allowed movements
     * @var array
     */
    protected $movements = array ();

    /**
     * @var array|bool
     */
    protected $roles = array ();

    public function getMovements()
    {
        return $this->movements;
    }

    /**
     * @param array $movements
     */
    protected function setMovements($movements)
    {
        $this->movements = $movements;
    }

    /**
     * @return array|bool
     */
    public function getRoles()
    {
        return $this->roles;
    }
}
