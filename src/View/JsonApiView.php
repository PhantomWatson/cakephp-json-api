<?php
namespace JsonApi\View;

use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Event\EventManager;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\ORM\Exception\MissingEntityException;
use Cake\Utility\Hash;
use Cake\View\View;
use JsonApi\View\Exception\MissingViewVarException;
use Neomerx\JsonApi\Encoder\Encoder;

class JsonApiView extends View
{
    /**
     * List of special view vars.
     *
     * @var array
     */
    protected $_specialVars = [
        '_url',
        '_entities',
        '_include',
        '_fieldsets',
        '_links',
        '_meta',
        '_serialize',
        '_jsonOptions',
        '_jsonp'
    ];

    /**
     * Constructor
     *
     * @param \Cake\Http\ServerRequest $request Request instance.
     * @param \Cake\Http\Response $response Response instance.
     * @param \Cake\Event\EventManager $eventManager EventManager instance.
     * @param array $viewOptions An array of view options
     */
    public function __construct(
        ServerRequest $request = null,
        Response $response = null,
        EventManager $eventManager = null,
        array $viewOptions = []
    ) {
        parent::__construct($request, $response, $eventManager, $viewOptions);

        if ($this->response && $this->response instanceof Response) {
            $this->response = $this->response->withType('jsonapi');
        }
    }

    /**
     * Map entities to schema files
     * @param  array  $entities An array of entity names that need to be mapped to a schema class
     *   If the schema class does not exist, the default EntitySchema will be used.
     *
     * @return array A list of Entity class names as its key and a closure returning the schema class
     * @throws MissingViewVarException when the _entities view variable is empty
     * @throws MissingEntityException when defined entity class was not found in entities array
     */
    protected function _entitiesToSchema(array $entities)
    {
        if (empty($entities)) {
            throw new MissingViewVarException(['_entities']);
        }

        $schemas = [];
        $entities = Hash::normalize($entities);
        foreach ($entities as $entityName => $options) {
            $entityclass = App::className($entityName, 'Model\Entity');

            if (!$entityclass) {
                throw new MissingEntityException([$entityName]);
            }

            $schemaClass = App::className($entityName, 'View\Schema', 'Schema');

            if (!$schemaClass) {
                $schemaClass = App::className('JsonApi.Entity', 'View\Schema', 'Schema');
            }

            $schema = function ($factory) use ($schemaClass, $entityName) {
                return new $schemaClass($factory, $this, $entityName);
            };

            $schemas[$entityclass] = $schema;
        }

        return $schemas;
    }

    /**
     * Serialize view vars
     *
     * ### Special parameters
     * `_serialize` This holds the actual data to pass to the encoder
     * `_url` The base url of the api endpoint
     * `_entities` A list of entitites that are going to be mapped to Schemas
     * `_include` An array of hash paths of what should be in the 'included'
     *   section of the response. see: http://jsonapi.org/format/#fetching-includes
     *   eg: [ 'posts.author' ]
     * `_fieldsets` A hash path of fields a list of names that should be in the resultset
     *   eg: [ 'sites'  => ['name'], 'people' => ['first_name'] ]
     * `_meta` Metadata to add to the document
     * `_links' Links to add to the document
     *  this should be an array of Neomerx\JsonApi\Schema\Link objects.
     *  example:
     * ```
     * $this->set('_links', [
     *     Link::FIRST => new Link('/authors?page=1'),
     *     Link::LAST  => new Link('/authors?page=4'),
     *     Link::NEXT  => new Link('/authors?page=6'),
     *     Link::LAST  => new Link('/authors?page=9'),
     * ]);
     * ```
     *
     * @param string|null $template Name of view file to use
     * @param string|null $layout Layout to use.
     * @return string The serialized data
     * @throws MissingViewVarException when required view variable was not set
     */
    public function render(?string $template = null, $layout = null): string
    {
        if (isset($this->viewVars['_entities'])) {
            $schemas = $this->_entitiesToSchema($this->viewVars['_entities']);
        } else {
            throw new MissingViewVarException(['_entities']);
        }

        $encoder = Encoder::instance($schemas);

        $encoder->withEncodeOptions($this->getJsonOptions());

        if (isset($this->viewVars['_url'])) {
            $url = rtrim($this->viewVars['_url'], '/');
            $encoder->withUrlPrefix($url);
        }

        if (isset($this->viewVars['_links'])) {
            $encoder->withLinks($this->viewVars['_links']);
        }

        $serialize = $this->viewVars['_serialize'] ?? false !== false
            ? $this->_dataToSerialize($this->viewVars['_serialize'])
            : null;

        if (isset($this->viewVars['_meta'])) {
            if (empty($serialize)) {
                return $encoder->encodeMeta($this->viewVars['_meta']);
            }

            $encoder->withMeta($this->viewVars['_meta']);
        }

        if (isset($this->viewVars['_fieldsets'])) {
            $encoder->withFieldsets($this->viewVars['_fieldsets']);
        }

        if (isset($this->viewVars['_include'])) {
            $encoder->withIncludedPaths((array)$this->viewVars['_include']);
        }

        return $encoder->encodeData($serialize);
    }

    /**
     * Returns data to be serialized.
     *
     * @param array|string|bool $serialize The name(s) of the view variable(s) that
     *   need(s) to be serialized. If true all available view variables will be used.
     * @return mixed The data to serialize.
     */
    protected function _dataToSerialize($serialize = true)
    {
        if ($serialize === true) {
            $data = array_diff_key(
                $this->viewVars,
                array_flip($this->_specialVars)
            );

            if (empty($data)) {
                return null;
            }

            return current($data);
        }

        if (is_object($serialize)) {
            trigger_error('Assigning and object to "_serialize" is deprecated, assign the object to its own variable and assign "_serialize" = true instead.', E_USER_DEPRECATED);

            return $serialize;
        }

        if (is_array($serialize)) {
            $serialize = current($serialize);
        }

        return isset($this->viewVars[$serialize]) ? $this->viewVars[$serialize] : null;
    }

    /**
     * Return json options
     *
     * ### Special parameters
     * `_jsonOptions` You can set custom options for json_encode() this way,
     *   e.g. `JSON_HEX_TAG | JSON_HEX_APOS`.
     *
     * @return int json option constant
     */
    protected function getJsonOptions()
    {
        $jsonOptions = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
        if (isset($this->viewVars['_jsonOptions'])) {
            if ($this->viewVars['_jsonOptions'] === false) {
                $jsonOptions = 0;
            } else {
                $jsonOptions = $this->viewVars['_jsonOptions'];
            }
        }

        if (Configure::read('debug')) {
            $jsonOptions = $jsonOptions | JSON_PRETTY_PRINT;
        }

        return $jsonOptions;
    }
}
