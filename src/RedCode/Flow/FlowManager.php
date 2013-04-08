<?php
/**
 * @author maZahaca
 */
namespace RedCode\Flow;

use Doctrine\ORM\EntityManager;
use RedCode\Flow\Item\IFlow;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\SecurityContext;
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
    protected $em;

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

    /**
     * @var string|null
     */
    private $mappedEntity = null;

    /**
     * @var \Closure
     */
    private $getStatusNameFunction;

    /**
     * @var array
     */
    private $entityMovements = array();

    /**
     * @var \Symfony\Component\Security\Core\SecurityContext
     */
    protected $securityContext;

    /**
     * @param \Symfony\Component\Security\Core\SecurityContext $securityContext
     * @param \Doctrine\ORM\EntityManager $em
     * @param Annotation\Reader $reader
     * @param string $class
     * @param \RedCode\Flow\Item\IFlow[] $flows
     * @param \Closure $getStatusNameFunction
     * @throws \Exception
     */
    public function __construct(SecurityContext $securityContext, EntityManager $em, Reader $reader, $class, $flows = array (), \Closure $getStatusNameFunction = null)
    {
        $this->securityContext = $securityContext;
        $this->em = $em;
        $this->class    = ltrim($class, "\\");
        $this->getStatusNameFunction = $getStatusNameFunction;

        $found = $reader->getFields($class, StatusValue::className());
        $found = current($found);

        if(!$found) {
            $found = $reader->getFields($class, StatusEntity::className());
            $found = current($found);

            if($found) {
                $this->field = $found;

                $found = $reader->getMappedEntityFields($class, $found, StatusValue::className());
                $found = current($found);
                if(!$found) {
                    throw new \Exception('You must set annotation ' . StatusValue::className() . ' in mapped status entity');
                }
                else {
                    $this->mappedEntity = $reader->getMappedEntityClass($class, $this->field);
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
     * @throws \Exception
     * @return IFlow
     */
    public function getFlow($entity)
    {
        $movement = $this->getMovement($entity);
        if(array_key_exists((string)$movement, $this->allowMovements))
            return $this->allowMovements[(string)$movement];
        if((string)$movement->getFrom() == (string)$movement->getTo()) {
            return $this->allowMovements['empty'];
        }
        throw new \Exception("Status movement from {$movement->getFrom()} to {$movement->getTo()} unsupported");
    }

    /**
     * @param object $entity
     * @return FlowMovement
     */
    public function getMovement($entity)
    {
        $this->validateEntity($entity);
        $from = $to = null;
        $entityId = spl_object_hash($entity) . $this->getEntityStatus($entity);

        if(!isset($this->entityMovements[$entityId])) {
            if($entity->getId()) {
                $qb = $this->em->createQueryBuilder();

                if($this->mappedEntity) {
                    $qbSb = $this->em->createQueryBuilder();
                    $qb
                        ->select('s')
                        ->from($this->mappedEntity, 's')
                        ->where($qb->expr()->in(
                            's.id',
                            $qbSb
                                ->select("ss.id")
                                ->from($this->class, 'o')
                                ->leftJoin("o.{$this->field}", 'ss')
                                ->where($qbSb->expr()->eq('o.id', $entity->getId()))
                                ->getDQL()
                        ))
                        ->setMaxResults(1);
                    $status = $qb->getQuery()->getOneOrNullResult();
                }
                else {
                    $tableAlias = 'target';
                    $fieldAlias = $this->field;

                    $qb
                        ->select("target.{$fieldAlias} as status")
                        ->from($this->class, $tableAlias)
                        ->where($qb->expr()->eq('target.id', ':id'))
                        ->setParameter(':id', $entity->getId());

                    $status = $qb->getQuery()->getOneOrNullResult();
                    if($status) {
                        $status = $status['status'];
                    }
                }

                $from = $status;
                $to = $this->getEntityField($entity, $this->field);
            }
            else {
                $to = $this->getEntityField($entity, $this->field);
            }
            $this->entityMovements[$entityId] = new FlowMovement($from, $to);
        }

        return $this->entityMovements[$entityId];
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
        $ignoreRights = false;
        if(count($entities) >= 2) {
            $ignoreRights = end($entities);
            if(is_bool($ignoreRights)) {
                array_pop($entities);
            }
            reset($entities);
        }

        $upperTransaction = $this->em->getConnection()->isTransactionActive();

        if(!$upperTransaction)
            $this->em->getConnection()->beginTransaction();

        try {

            foreach($entities as $entity) {
                $this->validateEntity($entity);
                $this->execute($entity, $ignoreRights);
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

    private function execute($entity, $ignoreRights = false)
    {
        $statusMovement = $this->getMovement($entity);

        /** @var $flow \RedCode\Flow\Item\IFlow */
        $flow = $this->getFlow($entity);
        if(!$ignoreRights && $flow->getRoles() !== false && !count(array_intersect($this->securityContext->getToken()->getUser()->getRoles(), $flow->getRoles()))) {
            throw new AccessDeniedException('Access denied to change status');
        }

        // execute current flow into entity
        $flow->execute($entity, $statusMovement);

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

    public function getMovementsArray($role = null, $entity = null)
    {
        $result = array ();

        $currentStatus = $entity !== null ? $this->getEntityStatus($entity) : null;
        $entityMovement = $entity !== null ? $this->getMovement($entity) : null;

        /** @var $flow \RedCode\Flow\Item\IFlow */
        foreach($this->flows as $flow) {
            foreach($flow->getMovements() as $movement) {
                if(
                    ($role === null || $flow->getRoles() === false || in_array($role, $flow->getRoles())) &&
                    ($currentStatus === null || $movement->getFrom() == (string)$currentStatus) &&
                    ($entity === null || $movement->isAllowed($entity, $entityMovement))
                  ) {
                    $result[(string)$movement] = array(
                        'from'=> array (
                            'id' => $movement->getFrom(),
                            'name' => $this->getName($movement->getFrom())
                        ),
                        'to'=> array (
                            'id' => $movement->getTo(),
                            'name' => $this->getName($movement->getTo())
                        )
                    );
                }
            }
        }
        return $result;
    }

    private function getName($status)
    {
        if($this->getStatusNameFunction) {
            return call_user_func($this->getStatusNameFunction, $status);
        }
        throw new \Exception('For get status name you should set $getStatusNameFunction in __construct');
    }
}
