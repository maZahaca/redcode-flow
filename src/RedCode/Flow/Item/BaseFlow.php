<?php
/**
 * @author maZahaca
 */
namespace RedCode\Flow\Item;

use Doctrine\ORM\EntityManager;
use RedCode\Flow\Annotation\Reader;

abstract class BaseFlow implements IFlow
{
    /**
     * Allowed movements
     * @var array
     */
    protected $movements = array ();

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
}
