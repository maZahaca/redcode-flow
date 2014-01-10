<?php
/**
 * @author maZahaca
 */
namespace RedCode\Flow\Item;

use RedCode\Flow\Movement;

abstract class BaseFlow implements IFlow
{
    /**
     * Allowed movements
     * @var array
     */
    protected $movements = array ();

    /**
     * Get allowed movements to flow
     * @var array|bool
     */
    protected $roles = array ();

    public function getMovements()
    {
        return $this->movements;
    }

    /**
     * Return array of user roles or false if user check is switched off
     * @return array|bool
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * @inheritdoc
     */
    public function postExecute($entity, Movement $movement)
    {

    }
}
