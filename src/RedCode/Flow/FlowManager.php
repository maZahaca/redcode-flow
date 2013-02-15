<?php
/**
 * @author maZahaca
 */
namespace RedCode\Flow;

use Doctrine\ORM\EntityManager;
use RedCode\Flow\Item\EmptyFlow;
use RedCode\Flow\Annotation\Status\StatusEntity;
use RedCode\Flow\Annotation\Status\StatusValue;
use RedCode\Flow\Annotation\Reader;

class FlowManager
{
    /**
     * @var array
     */
    private $flows = array();

    /**
     * @var array
     */
    private $allowMovements = array ();

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var string
     */
    private $class;

    /**
     * @var string
     */
    private $field = null;

    /**
     * @var string
     */
    private $mappedEntityField = null;

    public function __construct(EntityManager $em, Reader $reader, $class, $flows = array ())
    {
        $this->em = $em;
        $this->class    = ltrim($class, "\\");

        $found = $reader->getFields($class, StatusValue::className());
        $found = current($found);

        if(!$found) {
            $found = $reader->getFields($class, StatusEntity::className());
            $found = current($found);

            if($found) {
                $this->field = $found;

                $found = $reader->getMappedEntityFields($class, $found, StatusValue::className());
                $found = (bool)count($found);
                if(!$found) {
                    throw new \Exception('You must set annotation ' . StatusValue::className() . ' in mapped status entity');
                }
                else {
                    $this->mappedEntityField = $found;
                }
            }
            else {
                throw new \Exception('You must set annotation ' . StatusValue::className() . ' to status field in ' . $class);
            }
        } else {
            if($reader->isFieldReference($class, $found)) {
                throw new \Exception('Status field is reference. You must set annotation ' . StatusEntity::className() . ' to status field in ' . $class);
            }
            $this->field = $found;
        }


        $index = 0;
        /** @var \RedCode\Flow\Item\IFlow $service */
        foreach($flows as $service) {
            if(!($service instanceof IFlow))
                throw new \Exception('Status flow is unsupported');

            $this->flows[$index] = $service;
            foreach($service->getMovements() as $move) {
                $this->allowMovements[(string)$move] = &$this->flows[$index];
            }
            $index++;
        }

        $this->allowMovements['empty'] = new EmptyFlow();
    }

    /**
     * Get executive flow
     * @param object $entity
     * @return IFlow
     */
    public function getFlow($entity)
    {
        $movement = $this->getMovement($entity);
        if(array_key_exists((string)$movement, $this->allowMovements))
            return $this->allowMovements[(string)$movement];
        return $this->allowMovements['empty'];
    }

    /**
     * @param object $entity
     * @return FlowMovement
     */
    public function getMovement($entity)
    {
        $this->validateEntity($entity);

        $movement = new FlowMovement();
        if($entity->getId()) {
            $qb = $this->em->createQueryBuilder();

            $tableAlias = $this->mappedEntityField ? 't' : 'target';
            $fieldAlias = $this->mappedEntityField ? $this->mappedEntityField : $this->field;

            $qb
                ->select("target.{$fieldAlias} as status")
                ->from($this->class, $tableAlias);

            if($this->mappedEntityField) {
                $qb
                    ->leftJoin("t.{$this->field}", 'target')
                    ->where($qb->expr()->eq('t.id', ':id'))
                    ->setParameter(':id', $entity->getId());
            } else {
                $qb
                    ->where($qb->expr()->eq('target.id', ':id'))
                    ->setParameter(':id', $entity->getId());
            }

            $status = $qb->getQuery()->getOneOrNullResult();
            if($status) {
                $status = $status['status'];
            }

            $movement->setFrom($status);
            $movement->setTo($this->getEntityStatus($entity));
        }
        else {
            $movement->setTo($this->getEntityStatus($entity));
        }
        return $movement;
    }

    /**
     * Process Transaction(s)
     * Can be called:
     * ->process([] $entityArray),
     * ->process($entity1 [, ..., $entityN])
     * @throws \Exception
     */
    public function process()
    {
        $entities = func_get_args();
        if(count($entities) == 1 && is_array($entities[0])) {
            $entities = $entities[0];
        }

        $upperTransaction = $this->em->getConnection()->isTransactionActive();

        if(!$upperTransaction)
            $this->em->getConnection()->beginTransaction();

        try {

            foreach($entities as $entity) {
                $this->validateEntity($entity);
                $this->execute($entity);
            }

            $this->em->flush();
            if(!$upperTransaction)
                $this->em->getConnection()->commit();
        }
        catch(\Exception $ex) {
            if(!$upperTransaction)
                $this->em->getConnection()->rollback();
            throw $ex;
        }
        return true;
    }

    private function execute($entity)
    {
        $statusMovement = $this->getFlowManager()->getMovement($entity);

        // execute current flow into entity
        $this->getFlowManager()->getFlow($entity)->execute($entity, $statusMovement);

        $this->em->persist($entity);

        return $entity;
    }

    /**
     * @param $entity
     * @return mixed|null
     */
    private function getEntityStatus($entity)
    {
        if($this->mappedEntityField) {
            $entity = $this->getEntityField($entity, $this->mappedEntityField);
        }

        if($entity) {
            return $this->getEntityField($entity, $this->field);
        }

        return null;
    }

    /**
     * @param $entity
     * @param $field
     * @return mixed
     */
    private function getEntityField($entity, $field)
    {
        $field[0] = strtoupper($field[0]);
        $method = "get{$field}";

        return call_user_func(array($entity, $method));
    }

    /**
     * @param object $entity
     * @throws \Exception
     */
    private function validateEntity($entity)
    {
        if(ltrim(get_class($entity), "\\") != $this->class) {
            throw new \Exception('Entity must be instance of ' . $this->class);
        }
    }
}
