<?php

namespace RedCode\Flow;
use Doctrine\ORM\Event\LifecycleEventArgs;

/**
 * @author maZahaca
 */ 
class Listener
{
    /**
     * @var Manager
     */
    private $manager;

    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }

    public function prePersist(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getEntity();
        if($this->manager->isSupports($entity)) {
            $this->manager->executeFlow($entity);
        }
    }

    public function preUpdate(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getEntity();
        if($this->manager->isSupports($entity)) {
            $this->manager->executeFlow($entity);
        }
    }
}
 