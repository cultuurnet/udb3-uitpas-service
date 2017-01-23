<?php

namespace CultuurNet\UDB3\UiTPASService\Broadway\Saga\Testing;

use Broadway\CommandHandling\Testing\TraceableCommandBus;
use Broadway\Domain\DomainMessage;
use Broadway\Domain\Metadata;
use CultuurNet\UDB3\UiTPASService\Broadway\Saga\MultipleSagaManager;
use PHPUnit_Framework_TestCase;

/**
 * Copied from Broadway\Saga\Testing\Scenario and modified
 * to use the forked MultipleSagaManager class.
 */
class Scenario
{
    private $testCase;
    private $sagaManager;
    private $traceableCommandBus;
    private $playhead;

    public function __construct(
        PHPUnit_Framework_TestCase $testCase,
        MultipleSagaManager $sagaManager,
        TraceableCommandBus $traceableCommandBus
    ) {
        $this->testCase            = $testCase;
        $this->sagaManager         = $sagaManager;
        $this->traceableCommandBus = $traceableCommandBus;
        $this->playhead            = -1;
    }

    /**
     * @param array $events
     *
     * @return Scenario
     */
    public function given(array $events = [])
    {
        foreach ($events as $given) {
            $this->sagaManager->handle($this->createDomainMessageForEvent($given));
        }

        return $this;
    }

    /**
     * @param mixed $event
     *
     * @return Scenario
     */
    public function when($event)
    {
        $this->traceableCommandBus->record();

        $this->sagaManager->handle($this->createDomainMessageForEvent($event));

        return $this;
    }

    /**
     * @param array $commands
     *
     * @return Scenario
     */
    public function then(array $commands)
    {
        $this->testCase->assertEquals($commands, $this->traceableCommandBus->getRecordedCommands());

        return $this;
    }

    private function createDomainMessageForEvent($event)
    {
        $this->playhead++;

        return DomainMessage::recordNow(1, $this->playhead, new Metadata([]), $event);
    }
}
