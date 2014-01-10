<?php
/**
 * User: matrlx
 * Date: 9/14/12
 * Time: 4:32 PM
 */
namespace RedCode\Flow\Item;

use RedCode\Flow\Movement;

interface IFlow
{
    /**
     * Execute actions on entity by current flow
     * @param object $entity
     * @param Movement $movement
     */
    public function execute($entity, Movement $movement);

    /**
     * Execute actions on entity by current flow
     * @param object $entity
     * @param Movement $movement
     */
    public function postExecute($entity, Movement $movement);

    /**
     * Get allowed movement for flow
     * @return \RedCode\Flow\Movement[]
     */
    public function getMovements();

    /**
     * Get user roles allowed to execute flow
     * @return array|bool
     */
    public function getRoles();
}
