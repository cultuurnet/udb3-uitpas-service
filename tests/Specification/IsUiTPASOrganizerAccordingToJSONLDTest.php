<?php

namespace CultuurNet\UDB3\UiTPASService\Specification;

use CultuurNet\UDB3\LabelCollection;
use Psr\Log\LoggerInterface;

class IsUiTPASOrganizerAccordingToJSONLDTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $organizerUrl;

    /**
     * @var LabelCollection
     */
    private $uitpasLabels;

    /**
     * @var LoggerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $logger;

    /**
     * @var IsUiTPASOrganizerAccordingToJSONLD
     */
    private $specification;

    /**
     * @var string
     */
    private $organizerWithExactUiTPASLabel;

    /**
     * @var string
     */
    private $organizerWithLowercaseUiTPASLabel;

    /**
     * @var string
     */
    private $organizerWithoutUiTPASLabel;

    /**
     * @var string
     */
    private $organizerWithoutLabels;

    /**
     * @var string
     */
    private $organizerWithSyntaxError;

    /**
     * @var string
     */
    private $organizerNotFound;

    /**
     * @var array
     */
    private $logs;

    public function setUp()
    {
        $this->organizerUrl = __DIR__ . '/samples/';
        $this->uitpasLabels = LabelCollection::fromStrings(['UiTPAS']);
        $this->logger = $this->getMock(LoggerInterface::class);

        $this->specification = new IsUiTPASOrganizerAccordingToJSONLD(
            $this->organizerUrl,
            $this->uitpasLabels
        );

        $this->specification->setLogger($this->logger);

        $this->organizerWithExactUiTPASLabel = 'organizer-with-uitpas-label';
        $this->organizerWithLowercaseUiTPASLabel = 'organizer-with-lowercase-uitpas-label';
        $this->organizerWithoutUiTPASLabel = 'organizer-without-uitpas-label';
        $this->organizerWithoutLabels = 'organizer-without-labels';
        $this->organizerWithSyntaxError = 'organizer-with-syntax-error';
        $this->organizerNotFound = 'organizer-not-found';

        $this->logs = [
            'error' => [],
            'debug' => [],
        ];

        $this->logger->expects($this->any())
            ->method('error')
            ->willReturnCallback(
                function ($message, array $context) {
                    $this->logs['error'][] = [
                        'message' => $message,
                        'context' => $context,
                    ];
                }
            );

        $this->logger->expects($this->any())
            ->method('debug')
            ->willReturnCallback(
                function ($message, array $context) {
                    $this->logs['debug'][] = [
                        'message' => $message,
                        'context' => $context,
                    ];
                }
            );
    }

    /**
     * @test
     */
    public function it_returns_true_for_organizers_with_an_exact_uitpas_label_match()
    {
        $this->assertTrue(
            $this->specification->isSatisfiedBy(
                $this->organizerWithExactUiTPASLabel
            )
        );

        $expectedLogContext = $this->createDefaultLogContext($this->organizerWithExactUiTPASLabel);

        $expectedJsonLogContext = $expectedLogContext + [
            'json' => file_get_contents($expectedLogContext['url']),
        ];

        $expectedLabelLogContext = $expectedLogContext + [
            'uitpas_labels' => $this->uitpasLabels->asArray(),
            'extracted_organizer_labels' => [
                (object) [
                    'uuid' => '71945e50-2158-4922-94d2-fd1da6286b51',
                    'name' => 'foo',
                ],
                (object) [
                    'uuid' => '12dabcf9-9598-4e8d-8642-c1af42698875',
                    'name' => 'UiTPAS',
                ],
                (object) [
                    'uuid' => '9fac1824-8c65-4d0e-845f-9bef03fa05a1',
                    'name' => 'bar',
                ],
            ],
            'organizer_uitpas_labels' => [
                (object) [
                    'uuid' => '12dabcf9-9598-4e8d-8642-c1af42698875',
                    'name' => 'UiTPAS',
                ],
            ],
        ];

        $this->assertLogMessage('debug', 0, 'successfully retrieved organizer JSON-LD', $expectedJsonLogContext);
        $this->assertLogMessage('debug', 1, 'uitpas labels present on organizer', $expectedLabelLogContext);
    }

    /**
     * @test
     */
    public function it_returns_true_for_organizers_with_a_case_insensitive_uitpas_label_match()
    {
        $this->assertTrue(
            $this->specification->isSatisfiedBy(
                $this->organizerWithLowercaseUiTPASLabel
            )
        );

        $expectedLogContext = $this->createDefaultLogContext($this->organizerWithLowercaseUiTPASLabel);

        $expectedJsonLogContext = $expectedLogContext + [
            'json' => file_get_contents($expectedLogContext['url']),
        ];

        $expectedLabelLogContext = $expectedLogContext + [
            'uitpas_labels' => $this->uitpasLabels->asArray(),
            'extracted_organizer_labels' => [
                (object) [
                    'uuid' => '71945e50-2158-4922-94d2-fd1da6286b51',
                    'name' => 'foo',
                ],
                (object) [
                    'uuid' => '12dabcf9-9598-4e8d-8642-c1af42698875',
                    'name' => 'uitpas',
                ],
                (object) [
                    'uuid' => '9fac1824-8c65-4d0e-845f-9bef03fa05a1',
                    'name' => 'bar',
                ],
            ],
            'organizer_uitpas_labels' => [
                (object) [
                    'uuid' => '12dabcf9-9598-4e8d-8642-c1af42698875',
                    'name' => 'uitpas',
                ],
            ],
        ];

        $this->assertLogMessage('debug', 0, 'successfully retrieved organizer JSON-LD', $expectedJsonLogContext);
        $this->assertLogMessage('debug', 1, 'uitpas labels present on organizer', $expectedLabelLogContext);
    }

    /**
     * @test
     */
    public function it_returns_false_for_organizers_without_uitpas_label()
    {
        $this->assertFalse(
            $this->specification->isSatisfiedBy(
                $this->organizerWithoutUiTPASLabel
            )
        );

        $expectedLogContext = $this->createDefaultLogContext($this->organizerWithoutUiTPASLabel);

        $expectedJsonLogContext = $expectedLogContext + [
            'json' => file_get_contents($expectedLogContext['url']),
        ];

        $expectedLabelLogContext = $expectedLogContext + [
            'uitpas_labels' => $this->uitpasLabels->asArray(),
            'extracted_organizer_labels' => [
                (object) [
                    'uuid' => '71945e50-2158-4922-94d2-fd1da6286b51',
                    'name' => 'foo',
                ],
                (object) [
                    'uuid' => '9fac1824-8c65-4d0e-845f-9bef03fa05a1',
                    'name' => 'bar',
                ],
            ],
            'organizer_uitpas_labels' => [],
        ];

        $this->assertLogMessage('debug', 0, 'successfully retrieved organizer JSON-LD', $expectedJsonLogContext);
        $this->assertLogMessage('debug', 1, 'no uitpas labels present on organizer', $expectedLabelLogContext);
    }

    /**
     * @test
     */
    public function it_returns_false_for_organizers_without_labels()
    {
        $this->assertFalse(
            $this->specification->isSatisfiedBy(
                $this->organizerWithoutLabels
            )
        );

        $expectedLogContext = $this->createDefaultLogContext($this->organizerWithoutLabels);

        $expectedJsonLogContext = $expectedLogContext + [
            'json' => file_get_contents($expectedLogContext['url']),
        ];

        $expectedLabelLogContext = $expectedLogContext + [
            'uitpas_labels' => $this->uitpasLabels->asArray(),
            'extracted_organizer_labels' => [],
            'organizer_uitpas_labels' => [],
        ];

        $this->assertLogMessage('debug', 0, 'successfully retrieved organizer JSON-LD', $expectedJsonLogContext);
        $this->assertLogMessage('debug', 1, 'no uitpas labels present on organizer', $expectedLabelLogContext);
    }

    /**
     * @test
     */
    public function it_returns_false_for_organizers_with_a_syntax_error()
    {
        $this->assertFalse(
            $this->specification->isSatisfiedBy(
                $this->organizerWithSyntaxError
            )
        );

        $expectedLogContext = $this->createDefaultLogContext($this->organizerWithSyntaxError);

        $expectedJsonLogContext = $expectedLogContext + [
            'json' => file_get_contents($expectedLogContext['url']),
            'json_error' => 'Syntax error',
        ];

        $this->assertLogMessage('error', 0, 'unable to decode organizer JSON-LD', $expectedJsonLogContext);
    }

    /**
     * @test
     */
    public function it_returns_false_for_non_existing_organizers()
    {
        $this->assertFalse(
            $this->specification->isSatisfiedBy(
                $this->organizerNotFound
            )
        );

        $expectedLogContext = $this->createDefaultLogContext($this->organizerNotFound);

        $this->assertLogMessage('error', 0, 'unable to retrieve organizer JSON-LD', $expectedLogContext);
    }

    /**
     * @param string $type
     * @param int $index
     * @param string $message
     * @param array $context
     */
    private function assertLogMessage($type, $index, $message, $context)
    {
        $log = [
            'message' => $message,
            'context' => $context,
        ];

        $this->assertEquals($log, $this->logs[$type][$index]);
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
}
