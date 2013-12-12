<?php

namespace RedCode\Flow;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManager;
use RedCode\Flow\Exception\MovementUnsupportedException;
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
     * @var AnnotationReader
     */
    private $annotationReader;

    /**
     * @var PropertyInfo
     */
    private $property;

    /**
     * @var \Symfony\Component\Security\Core\SecurityContext
     */
    protected $securityContext;

    /**
     * @param EntityManager $em
     * @param AnnotationReader $reader
     * @param string $className
     * @param IFlow[] $flows
     * @param SecurityContextInterface|null $securityContext
     * @throws \Exception
     */
    public function __construct(EntityManager $em, AnnotationReader $reader, $className, $flows, $securityContext = null)
    {
        $this->em                       = $em;
        $this->class                    = ltrim($className, "\\");
        $this->securityContext          = $securityContext;
        $this->annotationReader         = $reader;
        $this->property                 = new PropertyInfo($className, $em, $reader);

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
     * @throws MovementUnsupportedException
     * @return IFlow
     */
    public function getFlow($entity)
    {
        $movement = $this->getMovement($entity);

        $movementString = (string)$movement;
        if(array_key_exists($movementString, $this->allowMovements))
            return $this->allowMovements[$movementString];

        $fromAnyToConcrete = preg_replace('/^([^-]+)-([^-]+)$/i', '*-$2', $movementString);
        if(array_key_exists($fromAnyToConcrete, $this->allowMovements))
            return $this->allowMovements[$fromAnyToConcrete];

        $fromConcreteToAny = preg_replace('/^([^-]+)-([^-]+)$/i', '$1-*', $movementString);
        if(array_key_exists($fromAnyToConcrete, $this->allowMovements))
            return $this->allowMovements[$fromConcreteToAny];

        if((string)$movement->getFrom() == (string)$movement->getTo()) {
            return $this->allowMovements['empty'];
        }
        throw new MovementUnsupportedException($movement);
    }

    /**
     * @param object $entity
     * @return FlowMovement
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
        return new FlowMovement($from, $to);
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
        $this->em->getConnection()->beginTransaction();

        try {

            foreach($entities as $entity) {
                $this->validateEntity($entity);
                $this->execute($entity);
            }

            $this->em->flush();
            $this->em->getConnection()->commit();
        }
        catch(\Exception $ex) {
            $this->em->getConnection()->rollback();
            throw $ex;
        }
        return true;
    }

    public function execute($entity)
    {
        $statusMovement = $this->getMovement($entity);

        /** @var $flow IFlow */
        $flow = $this->getFlow($entity);
        if(
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
        $flow->execute($entity, $statusMovement);

        $this->em->persist($entity);

        return $entity;
    }

    /**
     * @param object $entity
     * @throws \Exception
     */
    private function validateEntity($entity)
    {
        if(!$this->isSupports($entity)) {
            throw new \Exception('Entity must be instance of ' . $this->class);
        }
    }

    public function getMovements($role = null, $entity = null)
    {
        $result = [];

        $currentStatus = $entity !== null ? $this->property->getPropertyValue($entity) : false;
        $entityMovement = $entity !== null ? $this->getMovement($entity) : false;

        foreach($this->flows as $flow) {
            foreach($flow->getMovements() as $movement) {
                if(
                    ($role === null || $flow->getRoles() === false || in_array($role, $flow->getRoles())) &&
                    ($currentStatus === false || $movement->getFrom() == '*' || $movement->getFrom() == (string)$currentStatus) &&
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

    public function isSupports($entity)
    {
        $className = $this->class;
        return $entity instanceof $className;
    }
}