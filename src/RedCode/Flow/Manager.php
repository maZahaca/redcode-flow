<?php

namespace RedCode\Flow;

use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManager;
use RedCode\Flow\Exception\EntityUnsupportedException;
use RedCode\Flow\Exception\MovementUnsupportedException;
use RedCode\Flow\Item\EmptyFlow;
use RedCode\Flow\Item\IFlow;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\SecurityContextInterface;


/**
 * @author maZahaca
 */ 
class Manager
{
    /**
     * @var IFlow[]
     */
    private $flows = [];

    /**
     * @var string[]
     */
    private $allowMovements = [];

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var string
     */
    private $class;

    /**
     * @var Reader
     */
    private $annotationReader;

    /**
     * @var PropertyInfo
     */
    private $property;

    /**
     * @var SecurityContextInterface
     */
    protected $securityContext;

    /**
     * @var \Closure
     */
    protected $nameResolver;

    /**
     * @param EntityManager $em
     * @param Reader $reader
     * @param string $className
     * @param IFlow[] $flows
     * @param SecurityContextInterface|null $securityContext
     * @param \Closure|null $nameResolver
     * @throws \Exception
     */
    public function __construct(EntityManager $em, Reader $reader, $className, $flows, $securityContext = null, \Closure $nameResolver = null)
    {
        $this->em                       = $em;
        $this->class                    = ltrim($className, "\\");
        $this->securityContext          = $securityContext;
        $this->annotationReader         = $reader;
        $this->property                 = new PropertyInfo($className, $em, $reader);
        $this->nameResolver             = $nameResolver ? $nameResolver : (function ($name) { return $name; });

        $index = 0;
        /** @var \RedCode\Flow\Item\IFlow $service */
        foreach($flows as $service) {
            if(!($service instanceof IFlow))
                throw new \Exception('Status flow is unsupported');

            $this->flows[$index] = $service;
            foreach($service->getMovements() as $move) {
                $this->allowMovements[$this->getMovementKey($move)] = &$this->flows[$index];
            }
            $index++;
        }
        $this->allowMovements['empty'] = new EmptyFlow();
    }

    private function getMovementKey(Movement $movement)
    {
        return sprintf('%s-%s', $this->property->getPropertyValue($movement->getFrom()), $this->property->getPropertyValue($movement->getTo()));
    }


    /**
     * Get executive flow
     * @param object|Movement $object
     * @throws Exception\MovementUnsupportedException
     * @return IFlow
     */
    public function getFlow($object)
    {
        $movement = $object;
        if(!($movement instanceof Movement)) {
            $this->validateEntity($object);
            $movement = $this->getMovement($object);
        }

        $movementKey = $this->getMovementKey($movement);
        if(array_key_exists($movementKey, $this->allowMovements))
            return $this->allowMovements[$movementKey];

        if($this->property->getPropertyValue($movement->getFrom()) == $this->property->getPropertyValue($movement->getTo())) {
            return $this->allowMovements['empty'];
        }

        $fromAnyToConcrete = preg_replace('/^([^-]+)-([^-]+)$/i', '*-$2', $movementKey);
        if(array_key_exists($fromAnyToConcrete, $this->allowMovements)) {
            return $this->allowMovements[$fromAnyToConcrete];
        }

        $fromConcreteToAny = preg_replace('/^([^-]+)-([^-]+)$/i', '$1-*', $movementKey);
        if(array_key_exists($fromAnyToConcrete, $this->allowMovements)) {
            return $this->allowMovements[$fromConcreteToAny];
        }

        throw new MovementUnsupportedException($movement);
    }

    /**
     * @param object $entity
     * @return Movement
     */
    public function getMovement($entity)
    {
        $this->validateEntity($entity);
        $from = $to = null;

        if($this->property->getIdentifierValue($entity)) {
            $qb = $this->em->createQueryBuilder();

            if($this->property->getChild()) {
                $subIdName = $this->property->getChild()->getIdentifier();

                $qbSb = $this->em->createQueryBuilder();
                $qb
                    ->select('s')
                    ->from($this->property->getChild()->getClass(), 's')
                    ->where($qb->expr()->in(
                        "s.{$subIdName}",
                        $qbSb
                            ->select("ss.{$subIdName}")
                            ->from($this->property->getClass(), 'o')
                            ->leftJoin("o.{$this->property->getProperty()}", 'ss')
                            ->where($qbSb->expr()->eq("o.{$this->property->getIdentifier()}", $this->property->getIdentifierValue($entity)))
                            ->getDQL()
                    ))
                    ->setMaxResults(1);
                $status = $qb->getQuery()->getOneOrNullResult();
            }
            else {
                $tableAlias = 'target';
                $fieldAlias = $this->property->getProperty();

                $qb
                    ->select("target.{$fieldAlias} as status")
                    ->from($this->property->getClass(), $tableAlias)
                    ->where($qb->expr()->eq("target.{$this->property->getIdentifier()}", ':id'))
                    ->setParameter(':id', $this->property->getIdentifierValue($entity));

                $status = $qb->getQuery()->getOneOrNullResult();
                if($status) {
                    $status = $status['status'];
                }
            }

            $from = $status;
            $to = $this->property->getPropertyValue($entity);
        }
        else {
            $to = $this->property->getPropertyValue($entity);
        }
        return new Movement($from, $to);
    }

    /**
     * Process Transaction(s)
     * Can be called:
     * ->process([] $entityArray),
     * ->process($entity1 [, ..., $entityN])
     * @throws \Exception
     */
    public function process($entity, $ignoreRights = false)
    {
        $this->em->getConnection()->beginTransaction();

        $flowMovement = $this->getMovement($entity);
        $flow = $this->getFlow($flowMovement);
        try {
            $this->validateEntity($entity);
            $this->executeFlow($entity, $ignoreRights);

            $this->em->flush();
            $this->em->getConnection()->commit();
        }
        catch(\Exception $ex) {
            $this->em->getConnection()->rollback();
            throw $ex;
        }

        $flow->postExecute($entity, $flowMovement);
        return true;
    }

    public function executeFlow($entity, $ignoreRights = false)
    {
        $movement = $this->getMovement($entity);
        /** @var $flow IFlow */
        $flow = $this->getFlow($movement);
        if(
            !$ignoreRights &&
            $flow->getRoles() !== false &&
            $this->securityContext &&
            $this->securityContext->getToken() &&
            !count(
                array_intersect(
                    $this->securityContext->getToken()->getUser()->getRoles(),
                    $flow->getRoles()
                )
            )
        ) {
            throw new AccessDeniedException('Access denied to change status');
        }
        // execute current flow into entity
        $flow->execute($entity, $movement);
        $this->em->persist($entity);

        return $entity;
    }

    /**
     * @param object $entity
     * @throws EntityUnsupportedException
     */
    private function validateEntity($entity)
    {
        if(!$this->isSupports($entity)) {
            throw new EntityUnsupportedException($entity);
        }
    }

    public function getMovements($role = null, $entity = null)
    {
        $result = [];

        $currentStatus = $entity !== null ? $this->property->getPropertyValue($entity) : false;
        $entityMovement = $entity !== null ? $this->getMovement($entity) : false;

        $nameResolver = $this->nameResolver;

        foreach($this->flows as $flow) {
            foreach($flow->getMovements() as $movement) {
                if(
                    ($role === null || $flow->getRoles() === false || in_array($role, $flow->getRoles())) &&
                    ($currentStatus === false || $movement->getFrom() == '*' || $movement->getFrom() == $this->property->getPropertyValue($currentStatus)) &&
                    ($entity === null || $movement->isAllowed($entity, $entityMovement))
                ) {
                    $result[$this->getMovementKey($movement)] = array(
                        'from'=> array (
                            'id' => $movement->getFrom(),
                            'name' => $nameResolver($movement->getFrom())
                        ),
                        'to'=> array (
                            'id' => $movement->getTo(),
                            'name' => $nameResolver($movement->getTo())
                        )
                    );
                }
            }
        }
        return $result;
    }

    public function isSupports($entity)
    {
        return $entity instanceof $this->class;
    }
}