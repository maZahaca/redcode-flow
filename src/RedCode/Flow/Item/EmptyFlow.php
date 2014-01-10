<?php
/**
 * @author maZahaca
 */
namespace RedCode\Flow\Item;

use RedCode\Flow\Movement;

class EmptyFlow extends BaseFlow
{
    public function __construct()
    {
        $this->roles = false;
    }

    /**
     * @inheritdoc
     */
    public function execute($entity, Movement $movement)
    {
        return $entity;
    }
}
