<?php

namespace CultuurNet\UDB3\UiTPASService\Broadway\Saga\State;

use Broadway\Saga\State;
use Broadway\UuidGenerator\UuidGeneratorInterface;

class StateCopierTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var State
     */
    private $state;

    /**
     * @var UuidGeneratorInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $generator;

    /**
     * @var StateCopier
     */
    private $stateCopier;

    protected function setUp()
    {
        $this->id = '6cd53be3-7512-4a64-98b9-b51b2ed6988d';

        $this->state = $this->createStateWithValues(
            'e9d11f11-9579-48c3-b531-33deaebc5947'
        );

        $this->generator = $this->createMock(UuidGeneratorInterface::class);

        $this->generator->expects($this->once())
            ->method('generate')
            ->willReturn($this->id);

        $this->stateCopier = new StateCopier($this->generator);
    }

    /**
     * @test
     */
    public function it_can_copy_a_state()
    {
        $this->state->setDone();

        $copiedState = $this->stateCopier->copy($this->state);

        $expectedState = $this->createStateWithValues($this->id);
        $expectedState->setDone();

        $this->assertEquals($expectedState, $copiedState);
    }

    /**
     * @test
     */
    public function it_can_copy_a_state_with_values()
    {
        $extraValues = [
            'key3' => 'value3',
            'key4' => 'value4'
        ];

        $copiedState = $this->stateCopier->copyWithValues(
            $this->state,
            $extraValues
        );

        $expectedState = $this->createStateWithValues(
            $this->id,
            $extraValues
        );

        $this->assertEquals($expectedState, $copiedState);
    }

    /**
     * @param string $id
     * @param array $extraValues
     * @return State
     */
    private function createStateWithValues($id, array $extraValues = [])
    {
        $state = new State($id);

        $state->set('key1', 'value1');
        $state->set('key2', 'value2');

        foreach ($extraValues as $key => $extraValue) {
            $state->set($key, $extraValue);
        }

        return $state;
    }
}
