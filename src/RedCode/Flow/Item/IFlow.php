<?php
/**
 * User: matrlx
 * Date: 9/14/12
 * Time: 4:32 PM
 */
namespace RedCode\Flow\Item;

use RedCode\Flow\FlowMovement;

interface IFlow
{
    /**
     * Execute actions on entity by current flow
     * @param object $entity
     * @param FlowMovement $movement
     */
    public function execute($entity, FlowMovement $movement);

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
