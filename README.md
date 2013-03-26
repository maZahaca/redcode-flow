Using transit between statuses with redcode-flow bundle
===========================

**Steps to start**
* Mark with annotation status field or status entity.
Example:

``` php
use RedCode\Flow\Annotation\Status as Flow;
/**
 * @ORM\Entity
 */
class Order
{
     /**
     * @var OrderStatus
     * @Flow\StatusEntity
     * @ORM\ManyToOne(targetEntity="OrderStatus", cascade={"persist"})
     * @ORM\JoinColumn(name="status_id")
     */
    private $status;
}


use RedCode\Flow\Annotation\Status as Flow;
/**
 * @ORM\Entity
 */
class OrderStatus
{
     /**
     * @var int
     * @Flow\StatusValue
     * @ORM\Column(type="integer")
     */
    private $status;
}
```

* Create your manager and extend it from FlowManager

``` php
use RedCode\Flow\FlowManager;
use RedCode\Flow\Annotation\Reader;

class OrderManager extends FlowManager
{
    public function __construct(SecurityContext $securityContext, EntityManager $em, Reader $reader, array $flows = array ())
    {
        parent::__construct(
            $securityContext,
            $em,
            $reader,
            'Order', // class name
            $flows
        );
    }
}
```

* Create your own flows between statuses

``` php
use RedCode\Flow\Item\BaseFlow;

class SomeFlow extends BaseFlow
{
    public function __construct()
    {
        // set allowed user roles or set false to switch off option
        $this->roles = [
            'ROLE_ADMIN'
        ];
        
        // set allowed movements between statuses
        $this->movements = [
            new FlowMovement(1, 2),
            new FlowMovement(null, 2),
        ];
    }

    /**
     * @inheritDoc
     */
    public function execute($entity, FlowMovement $movement)
    {
        // some actions when transit comes
        return $entity;
    }
}
```

* Define service in xml or yaml file

``` xml
<service id="order.manager" class="OrderManager">
            <argument type="service" id="security.context" />
            <argument type="service" id="em" />
            <argument type="service">
                <service class="RedCode\Flow\Annotation\Reader">
                    <argument type="service" id="annotation_reader"/>
                    <argument type="service" id="em"/>
                </service>
            </argument>
            <argument type="collection">
                <argument type="service">
                    <service class="SomeFlow">
                    </service>
                </argument>
            </argument>
            <argument type="service" id="em" />
        </service>
```

* Use service in your code

``` php
/**
 * Modify order or it status and save using method execute
 */
$container->get('order.manager')->execute($order);
``` 
