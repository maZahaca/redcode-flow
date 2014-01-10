<?php

namespace RedCode\Flow;


use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManager;
use RedCode\Flow\Annotation\Status;

/**
 * @author maZahaca
 */ 
class PropertyInfo
{
    private $class;
    private $property;
    private $identifier;
    private $metadata;

    private $reflection;

    /** @var PropertyInfo|null */
    private $child;

    public function __construct($className, EntityManager $em, Reader $annotationReader)
    {
        $this->class = $className;
        $this->reflection = new \ReflectionClass($this->class);

        $metadata = $em->getClassMetadata($className);
        if(empty($metadata)) {
            throw new \Exception('Entity not found');
        }

        $this->identifier = $metadata->getIdentifier();
        $this->identifier = reset($this->identifier);

        foreach($metadata->getReflectionProperties() as $reflectionProperty) {
            /** @var $reflectionProperty \ReflectionProperty */
            if($annotationReader->getPropertyAnnotation($reflectionProperty, Status::className()) !== null) {
                $this->property = $reflectionProperty->getName();

                if($annotationReader->getPropertyAnnotation($metadata->getReflectionProperty($this->property), Status::className()) === null) {
                    throw new \Exception('Unsupported property');
                }
                if($metadata->hasField($this->property)) {
                    $this->metadata = $metadata->getFieldMapping($this->property);
                } else if($metadata->hasAssociation($this->property)) {
                    $this->metadata = $metadata->getAssociationMapping($this->property);
                    $this->child    = new self($this->metadata['targetEntity'], $em, $annotationReader);
                }
            }
        }
        if($this->property === null) {
            throw new \Exception('');
        }
    }

    /**
     * @return null|\RedCode\Flow\PropertyInfo
     */
    public function getChild()
    {
        return $this->child;
    }

    /**
     * @return string
     */
    public function getProperty()
    {
        return $this->property;
    }

    /**
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @return array
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    public function getPropertyValue($object)
    {
        if(is_scalar($object)) {
            return (string)$object;
        }
        else if(is_object($object) && $this->reflection->isInstance($object)) {
            $getter = 'get' . ucfirst(strtolower($this->property));
            if($this->reflection->hasMethod($getter) && ($getter = $this->reflection->getMethod($getter))) {
                return $getter->invoke($object);
            }
        }
        else if($this->child) {
            return $this->child->getPropertyValue($object);
        }
        return null;
    }

    public function getIdentifierValue($object)
    {
        if(is_object($object) && $this->reflection->isInstance($object)) {
            $getter = 'get' . ucfirst(strtolower($this->identifier));
            if($this->reflection->hasMethod($getter) && ($getter = $this->reflection->getMethod($getter))) {
                return $getter->invoke($object);
            }
        }
        return null;
    }
}
 