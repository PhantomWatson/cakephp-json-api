<?php
namespace JsonApi\Test\TestCase\View;

use Cake\Controller\Controller;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\ORM\Exception\MissingEntityException;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use JsonApi\View\Exception\MissingViewVarException;
use Neomerx\JsonApi\Document\Link;
//use Neomerx\JsonApi\Schema\Link;

class JsonApiViewTest extends TestCase
{
    public $fixtures = [
        'core.articles',
        'core.authors'
    ];

    public function setUp()
    {
        parent::setUp();
    }

    protected function _getView($viewVars = [])
    {
        $Request = new ServerRequest();
        $Response = new Response();
        $Controller = new Controller($Request, $Response);

        $builder = $Controller->viewBuilder();
        $builder->setClassName('JsonApi\View\JsonApiView');

        if ($viewVars) {
            $Controller->set($viewVars);
        }

        return $Controller->createView();
    }

    /**
     * Test Render
     * @return [type] [description]
     */
    public function testRenderUsingBaseSchema()
    {
        $records = TableRegistry::getTableLocator()->get('Articles')->find()->all();

        $view = $this->_getView([
            'articles' => $records,
            '_url' => 'http://localhost',
            '_entities' => [
                'Article'
            ],
            '_serialize' => true
        ]);

        $this->assertJsonStringEqualsJsonFile(
            ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'articles.json',
            $view->render()
        );
    }

    /**
     * Test Render
     * @return [type] [description]
     */
    public function testRenderUsingCustomSchema()
    {
        $records = TableRegistry::getTableLocator()->get('Authors')->find()
            ->contain(['Articles'])
            ->all();


        $view = $this->_getView([
            'author' => $records,
            '_serialize' => true,
            '_url' => 'http://localhost',
            '_entities' => [
                'Author',
                'Article'
            ]
        ]);

        $this->assertJsonStringEqualsJsonFile(
            ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'authors.json',
            $view->render()
        );
    }

    public function testViewResponse()
    {
        $records = TableRegistry::getTableLocator()->get('Articles')->find()->all();

        $view = $this->_getView([
            'articles' => $records,
            '_entities' => ['Article'],
            '_serialize' => true
        ]);

        $output = $view->render();

        $this->assertSame('application/vnd.api+json', $view->getResponse()->getType());
    }

    /**
     * Test Render
     * @return [type] [description]
     */
    public function testEncodeWithIncludeAndFieldSet()
    {
        $records = TableRegistry::getTableLocator()->get('Authors')->find()
            ->contain(['Articles'])
            ->all();

        $view = $this->_getView([
            'authors' => $records,
            '_url' => 'http://localhost',
            '_entities' => [
                'Author',
                'Article'
            ],
            '_serialize' => true,
            '_include' => ['articles'],
            '_fieldsets' => ['articles' => ['title']]
        ]);

        $output = $view->render();
        $output = json_decode($output, true);

        $expectedSubset = [
            'included' => [
                [
                    'type' => 'articles',
                    'id' => '1',
                    'attributes' => [
                        'title' => 'First Article'
                    ]
                ]
            ]
        ];

        $this->assertArraySubset($expectedSubset, $output);
    }

    public function testOnlyMetaData()
    {
        $meta = ['meta' => 'data'];
        $view = $this->_getView([
            '_url' => 'http://localhost',
            '_entities' => [
                'Article'
            ],
            '_meta' => $meta
        ]);

        $output = $view->render();
        $this->assertEquals(['meta' => $meta], json_decode($output, true));
    }


    public function testResponseWithLinksAndMeta()
    {
        $records = TableRegistry::getTableLocator()->get('Articles')->find()->all();

        $expectedMeta = [
            'meta' => 'data'
        ];

        $view = $this->_getView([
            'articles' => $records,
            '_url' => 'http://localhost',
            '_entities' => [
                'Article'
            ],
            '_serialize' => true,
            '_meta' => $expectedMeta,
            '_links' => [
                Link::FIRST => new Link('/authors?page=1'),
                Link::LAST => new Link('/authors?page=4'),
                Link::NEXT => new Link('/authors?page=6'),
                Link::LAST => new Link('/authors?page=9', [
                    'meta' => 'data'
                ])
            ]
        ]);

        $output = $view->render();
        $output = json_decode($output, true);

        $expectedLinks = [
            'first' => 'http://localhost/authors?page=1',
            'last' => [
                'href' => 'http://localhost/authors?page=9',
                'meta' => [
                    'meta' => 'data'
                ]
            ],
            'next' => 'http://localhost/authors?page=6'
        ];

        $this->assertArraySubset(['meta' => $expectedMeta], $output);
        $this->assertArraySubset(['links' => $expectedLinks], $output);
    }

    public function testDataToSerialize()
    {
        $records = TableRegistry::getTableLocator()->get('Articles')->find()->all();

        $view = $this->_getView([
            'articles' => $records,
            '_entities' => ['Article'],
            '_url' => 'http://localhost',
            '_serialize' => true
        ]);

        $this->assertJsonStringEqualsJsonFile(
            ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'articles.json',
            $view->render()
        );

        $view = $this->_getView([
            'articles' => $records,
            '_entities' => ['Article'],
            '_url' => 'http://localhost',
            '_serialize' => ['articles']
        ]);

        $this->assertJsonStringEqualsJsonFile(
            ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'articles.json',
            $view->render()
        );
    }

    public function testDataToSerializeAssertSerializeValueNotAssigned()
    {
        $records = TableRegistry::getTableLocator()->get('Articles')->find()->all();

        $view = $this->_getView([
            'articles' => $records,
            '_entities' => ['Article'],
            '_url' => 'http://localhost',
            '_serialize' => 'authors'
        ]);

        $output = $view->render();
        $this->assertEquals(['data' => null], json_decode($output, true));
    }

    public function testDataToSerializeAssertSerializingObjectsStillWorks()
    {
        $restore = error_reporting(E_ALL & ~E_USER_DEPRECATED);
        $records = TableRegistry::getTableLocator()->get('Articles')->find()->all();

        $view = $this->_getView([
            '_entities' => ['Article'],
            '_url' => 'http://localhost',
            '_serialize' => $records
        ]);

        $this->assertJsonStringEqualsJsonFile(
            ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'articles.json',
            $view->render()
        );
        error_reporting($restore);
    }

    public function testJsonOptions()
    {
        $view = $this->_getView([
            '_jsonOptions' => JSON_HEX_QUOT,
            '_entities' => ['Article']
        ]);

        $view->render();
        $this->assertEquals(8, $view->viewVars['_jsonOptions']);

        $view = $this->_getView([
            '_jsonOptions' => false,
            '_entities' => ['Article']
        ]);

        $view->render();
        $this->assertEquals(0, $view->viewVars['_jsonOptions']);
    }

    public function testEmptyView()
    {
        $view = $this->_getView([
            '_entities' => ['Article'],
            '_serialize' => true
        ]);
        $output = $view->render();

        $this->assertEquals(['data' => null], json_decode($output, true));
    }

    public function testEmptyEntitiesViewVarException()
    {
        $this->expectException(MissingViewVarException::class);

        $view = $this->_getView([
            '_entities' => []
        ]);

        $output = $view->render();
    }

    public function testUndefinedEntitiesViewVarException()
    {
        $this->expectException(MissingViewVarException::class);

        $view = $this->_getView();

        $output = $view->render();
    }

    public function testEntityNotFoundException()
    {
        $this->expectException(MissingEntityException::class);

        $view = $this->_getView([
            '_entities' => ['FakeEntity']
        ]);

        $output = $view->render();
    }
}
