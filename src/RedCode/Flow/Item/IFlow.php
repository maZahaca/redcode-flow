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
     * @param \RedCode\Flow\FlowMovement $movement
     * @return object $entity
     */
    public function execute($entity, \RedCode\Flow\FlowMovement $movement);

    /**
     * Get allowed movement for flow
     * @return \RedCode\Flow\FlowMovement[]
     */
    public function getMovements();

    /**
     * Get user roles allowed to execute flow
     * @return array|bool
     */
    public function getRoles();
}
