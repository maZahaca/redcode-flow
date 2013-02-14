<?php
/**
 * @author maZahaca
 */
namespace RedCode\Flow\Item;

class EmptyFlow extends BaseFlow
{
    /**
     * Execute actions on entity by current flow
     * @param object $entity
     * @param FlowMovement $movement
     * @return object $entity
     */
    public function execute($entity, FlowMovement $movement)
    {
        return $entity;
    }
}
