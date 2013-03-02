<?php
/**
 * @author maZahaca
 */
namespace RedCode\Flow\Item;

use RedCode\Flow\FlowMovement;

class EmptyFlow extends BaseFlow
{
    public function __construct()
    {
        $this->roles = false;
    }

    /**
     * @inheritDoc
     */
    public function execute($entity, FlowMovement $movement)
    {
        return $entity;
    }
}
