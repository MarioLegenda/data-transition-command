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
            'platformAccountId' => 5,
            'productAccountId' => 5,
            'platformLinkUuid' => Uuid::uuid4()->toString()
        ];
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
}