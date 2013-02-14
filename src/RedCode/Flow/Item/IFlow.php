<?php
/**
 * User: matrlx
 * Date: 9/14/12
 * Time: 4:32 PM
 */
namespace RedCode\Flow\Item;

interface IFlow
{
    /**
     * Execute actions on entity by current flow
     * @param object $entity
     * @param FlowMovement $movement
     * @return object $entity
     */
    public function execute($entity, FlowMovement $movement);

    /**
     * Get allowed movement for flow
     * @return array
     */
    public function getMovements();
}
