<?php
/**
 * @author maZahaca
 */
namespace RedCode\Flow\Annotation;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManager;

/**
 * @author maZahaca
 */
class Reader
{
    private $annotationReader;

    private $entityManager;

    static private $reflected = array();

    public function __construct(AnnotationReader $annotationReader, EntityManager $entityManager)
    {
        $this->annotationReader = $annotationReader;
        $this->entityManager    = $entityManager;
    }

    /**
     * Get Reflection by object
     * @param $object Object of some class
     * @return \ReflectionObject|\ReflectionClass
     */
    protected function getReflection($object)
    {
        if(is_string($object)) {
            $className = $object;
            if(!isset(self::$reflected[$className])) {
                self::$reflected[$className] = new \ReflectionClass($object);
            }
        }
        else {
            $className = get_class($object);
            if(!isset(self::$reflected[$className])) {
                self::$reflected[$className] = new \ReflectionObject($object);
            }
        }

        if(!isset(self::$reflected[$className])) {
            self::$reflected[$className] = new ReflectionObject($object);
        }

        return self::$reflected[$className];
    }

    /**
     * Get fields with annotation
     * @param object|string $object Some object of class
     * @param string $annotation annotation class string
     * @return array fields with annotation
     */
    public function getFields($object, $annotation)
    {
        $fields = array();
        /** @var $reflectionProperty \ReflectionProperty */
        foreach($this->getReflection($object)->getProperties(\ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PUBLIC) as $reflectionProperty) {
            $annotations = $this->annotationReader->getPropertyAnnotations($reflectionProperty);
            foreach ($annotations as $annotationType) {
                if(preg_match('/' . $annotation . '/u', get_class($annotationType))) {
                    $fields[] = $reflectionProperty->getName();
                }
            }
        }

        return $fields;
    }

    /**
     * Get class name of $object->field
     * @param object|string $object
     * @param string $field
     * @return string|null
     */
    public function getMappedEntityClass($object, $field)
    {
        $property = null;

        $properties = $this->getReflection($object)->getProperties(\ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PUBLIC);
        foreach($properties as $reflectionProperty) {
            if($reflectionProperty->getName() == $field) {
                $property = $reflectionProperty;
            }
        }

        if($property === null) {
            return array ();
        }

        $annotations = $this->annotationReader->getPropertyAnnotations($property);
        foreach ($annotations as $annotation) {
            if($annotation instanceof \Doctrine\ORM\Mapping\OneToOne || $annotation instanceof \Doctrine\ORM\Mapping\ManyToOne) {
                /** @var $classMetadata \Doctrine\ORM\Mapping\ClassMetadata */
                $classMetadata = $this->entityManager->getClassMetadata($annotation->targetEntity);
                return $classMetadata->getReflectionClass()->getName();
            }
        }
        return null;
    }

    /**
     * Get fields with annotation in mapped entity
     * @param $object
     * @param $field
     * @param $annotation
     * @return array
     */
    public function getMappedEntityFields($object, $field, $annotation)
    {
        $class = $this->getMappedEntityClass($object, $field);
        if($class === null) {
            return array();
        }

        return $this->getFields($class, $annotation);
    }
}
