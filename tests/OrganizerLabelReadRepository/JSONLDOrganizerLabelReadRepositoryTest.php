<?php

namespace CultuurNet\UDB3\UiTPASService\OrganizerLabelReadRepository;

use CultuurNet\UDB3\Label;
use CultuurNet\UDB3\LabelCollection;
use Monolog\Handler\TestHandler;
use Monolog\Logger;

class JSONLDOrganizerLabelReadRepositoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var TestHandler
     */
    private $logHandler;

    /**
     * @var string
     */
    private $organizerUrl;

    /**
     * @var JSONLDOrganizerLabelReadRepository
     */
    private $organizerLabelReader;

    public function setUp()
    {
        $this->organizerUrl = __DIR__ . '/samples/';
        $this->logHandler = new TestHandler();

        $this->organizerLabelReader = new JSONLDOrganizerLabelReadRepository(
            $this->organizerUrl
        );

        $this->organizerLabelReader->setLogger(
            new Logger('organizer jsonld reader', [$this->logHandler])
        );
    }

    /**
     * @test
     */
    public function it_returns_an_empty_label_collection_for_non_existing_organizers()
    {
        $organizerId = 'non-existing-organizer-id';

        $this->assertEquals(
            new LabelCollection(),
            $this->organizerLabelReader->getLabels($organizerId)
        );

        $this->assertLogged(
            Logger::ERROR,
            'unable to retrieve organizer JSON-LD',
            $this->createDefaultLogContext($organizerId)
        );
    }

    /**
     * @test
     */
    public function it_returns_an_empty_label_collection_for_organizers_with_a_syntax_error()
    {
        $organizerId = 'organizer-with-syntax-error';

        $this->assertEquals(
            new LabelCollection(),
            $this->organizerLabelReader->getLabels(
                $organizerId
            )
        );

        $expectedLogContext = $this->createDefaultLogContext($organizerId);
        $expectedLogContext += [
                'json' => file_get_contents($expectedLogContext['url']),
                'json_error' => 'Syntax error',
            ];

        $this->assertLogged(
            Logger::ERROR,
            'unable to decode organizer JSON-LD',
            $expectedLogContext
        );
    }

    /**
     * @test
     * @dataProvider sampleDataProvider
     */
    public function it_returns_a_combination_of_all_visible_and_hidden_labels($organizerId, LabelCollection $expectedLabels)
    {
        $labels = $this->organizerLabelReader->getLabels($organizerId);

        $this->assertEquals($expectedLabels, $labels);

        $expectedLogContext = $this->createDefaultLogContext($organizerId);
        $expectedLogContext += [
            'json' => file_get_contents($expectedLogContext['url']),
        ];

        $this->assertLogged(
            Logger::DEBUG,
            'successfully retrieved organizer JSON-LD',
            $expectedLogContext
        );
    }

    public function sampleDataProvider()
    {
        return [
            'organizer-with-uitpas-label' => [
                'organizer-with-uitpas-label',
                new LabelCollection(
                    [
                        new Label('foo'),
                        new Label('UiTPAS'),
                        new Label('bar'),
                    ]
                ),
            ],
            'organizer-with-lowercase-uitpas-label' => [
                'organizer-with-lowercase-uitpas-label',
                new LabelCollection(
                    [
                        new Label('foo'),
                        new Label('uitpas'),
                        new Label('bar'),
                    ]
                ),
            ],
            'organizer-with-hidden-uitpas-label' => [
                'organizer-with-hidden-uitpas-label',
                new LabelCollection(
                    [
                        new Label('foo'),
                        new Label('bar'),
                        new Label('UiTPAS'),
                        new Label('qa'),
                    ]
                ),
            ],
            'organizer-without-uitpas-label' => [
                'organizer-without-uitpas-label',
                new LabelCollection(
                    [
                        new Label('foo'),
                        new Label('bar'),
                    ]
                ),
            ],
            'organizer-without-labels' => [
                'organizer-without-labels',
                new LabelCollection([]),
            ],
        ];
    }

    /**
     * @param string $organizerId
     * @return array
     */
    private function createDefaultLogContext($organizerId)
    {
        return [
            'organizer' => $organizerId,
            'url' => $this->organizerUrl . $organizerId,
        ];
    }

    private function assertLogged($level, $message, $context)
    {
        $logs = $this->logHandler->getRecords();

        $this->assertCount(1, $logs);

        $log = reset($logs);

        $this->assertEquals($level, $log['level']);
        $this->assertEquals($message, $log['message']);
        $this->assertEquals($context, $log['context']);
    }
}
