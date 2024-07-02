<?php
namespace JsonApi\View\Schema;

use Cake\Utility\Inflector;
use Cake\View\View;
use Neomerx\JsonApi\Contracts\Factories\FactoryInterface;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\BaseSchema;

abstract class EntitySchema extends BaseSchema
{
    /**
     * The default field used for an id
     * @var string
     */
    public $idField = 'id';

    /**
     * Holds the instance of Cake\View\View
     * @var \Cake\View\View
     */
    protected $_view;

    /**
     * @var string
     */
    public $resourceType;

    /**
     * Class constructor
     *
     * @param \Neomerx\JsonApi\Contracts\Factories\FactoryInterface $container SchemaFactoryInterface
     * @param \Cake\View\View $view Instance of the cake view we are rendering this in
     * @param string $entityName Name of the entity this schema is for
     */
    public function __construct(
        FactoryInterface $factory,
        View $view,
        $entityName
    ) {
        $this->_view = $view;

        if (!$this->resourceType) {
            $this->resourceType = strtolower(Inflector::pluralize($entityName));
        }

        parent::__construct($factory); //$container
    }

    /**
     * Magic accessor for helpers.
     *
     * @param string $name Name of the attribute to get.
     * @return mixed
     */
    public function __get($name)
    {
        return $this->_view->__get($name);
    }

    /**
     * Get resource id.
     *
     * @param \Cake\ORM\Entity $resource Entity resource
     * @return string
     */
    public function getId($resource): ?string
    {
        return (string)$resource->get($this->idField);
    }

    /**
     * Get resource attributes.
     *
     * @param \Cake\ORM\Entity $resource Entity resource
     * @param ContextInterface $context
     * @return array
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        if ($resource->has($this->idField)) {
            $hidden = array_merge($resource->getHidden(), [$this->idField]);
            $resource->setHidden($hidden);
        }

        return $resource->toArray();
    }

    /**
     * Return the view instance
     *
     * @return \Cake\View\View View instance
     */
    public function getView()
    {
        return $this->_view;
    }
}
