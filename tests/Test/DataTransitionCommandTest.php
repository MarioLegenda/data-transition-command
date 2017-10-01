<?php

namespace Test;

use DataTransmissionCommand\DataTransitionCommand;
use Ramsey\Uuid\Uuid;

class DataTransitionCommandTest extends \PHPUnit_Framework_TestCase
{
    private $transition = [];

    public function setUp()
    {
        parent::setUp();

        $this->transition = [
            'platformAccount' => [
                'id' => 6,
                'parentId' => null,
                'displayId' => 'PlatformAccountDisplayId',
            ],
            'productAccount' => [
                'productVersion' => [
                    'product' => [
                        'id' => 5,
                        'category' => 'Product category',
                        'provider' => 'ProductProvider',
                        'group' => 'ProductGroup',
                        'aggregator' => 'nsoft',
                        'displayId' => 'ProductDisplayId',
                    ]
                ],
                'id' => 5,
                'version' => 5,
            ],
            'platformAccountId' => 6,
            'productAccountId' => 5,
            'platformLinkUuid' => Uuid::uuid4()->toString()
        ];
    }

    public function test_invalid_alias_syntax()
    {
        $didThrowException = false;

        try {
            new DataTransitionCommand([
                'platformLinkUuid#string#uuid|linkUuid|multiple_alias'
            ], $this->transition);
        } catch (\InvalidArgumentException $e) {
            $didThrowException = true;

            var_dump($e->getMessage());
        }

        $this->assertTrue($didThrowException);
    }
    /**
     * @expectedException \InvalidArgumentException
     */
    public function test_invalid_transition()
    {
        new DataTransitionCommand([
            1,
            6
        ], $this->transition);
    }

    public function test_transition()
    {
        $uuid = Uuid::uuid4()->toString();

        $this->transition['platformLinkUuid'] = $uuid;

        $command = new DataTransitionCommand([
            'platformAccountId#integer',
            'productAccountId|productAccountId',
            'platformLinkUuid#uuid#string|linkUuid',
            'productAccount>productVersion>product>id#integer|productId',
            'productAccount>productVersion>product>category#string|productCategory',
            'productAccount#isArray|productAccountArray'
        ], $this->transition);

        $this->assertEquals($command->platformAccountId, 6);
        $this->assertEquals($command->productAccountId, 5);
        $this->assertInternalType('array', $command->productAccountArray);
        $this->assertEquals($command->linkUuid, $uuid);
        $this->assertEquals($command->productId, 5);
        $this->assertEquals($command->productCategory, 'Product category');
    }

    public function test_helper_methods()
    {
        $uuid = Uuid::uuid4()->toString();

        $this->transition['platformLinkUuid'] = $uuid;

        $command = new DataTransitionCommand([
            'platformAccountId#integer',
            'productAccountId|productAccountId',
            'platformLinkUuid#uuid#string|linkUuid',
            'productAccount>productVersion>product>id#integer|productId',
            'productAccount>productVersion>product>category#string|productCategory',
            'productAccount#isArray|productAccountArray'
        ], $this->transition);

        $this->assertEquals(6, count($command));
        $this->assertEquals($command['platformAccountId'], 6);
        $this->assertEquals($command['productAccountId'], 5);
        $this->assertInternalType('array', $command['productAccountArray']);
        $this->assertEquals($command['linkUuid'], $uuid);
        $this->assertEquals($command['productId'], 5);
        $this->assertEquals($command['productCategory'], 'Product category');

        $this->assertInstanceOf(\Generator::class, $command->getGenerator());
        $this->assertInstanceOf(\ArrayIterator::class, $command->getIterator());

        foreach ($command as $key => $item) {
            $this->assertEquals($item, $command->{$key});
        }
    }
}