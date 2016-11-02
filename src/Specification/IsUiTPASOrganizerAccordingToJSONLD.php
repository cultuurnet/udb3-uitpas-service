<?php

namespace CultuurNet\UDB3\UiTPASService\Specification;

use CultuurNet\UDB3\Label;
use CultuurNet\UDB3\LabelCollection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class IsUiTPASOrganizerAccordingToJSONLD implements OrganizerSpecificationInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var LabelCollection
     */
    protected $uitpasLabels;

    /**
     * @param string $url
     * @param LabelCollection $uitpasLabels
     */
    public function __construct($url, $uitpasLabels)
    {
        $this->url = $url;
        $this->uitpasLabels = $uitpasLabels;
        $this->logger = new NullLogger();
    }

    /**
     * @inheritdoc
     */
    public function isSatisfiedBy($organizerId)
    {
        $organizer = null;
        $organizerUrl = $this->url . $organizerId;

        $logContext = [
            'organizer' => $organizerId,
            'url' => $organizerUrl,
        ];

        // Using an HTTP client like Guzzle would be cleaner, but in the long
        // term we should use SAPI3 or another central repository anyway so
        // we shouldn't put too much work into the retrieval of the organizer
        // right now.
        $data = @file_get_contents($organizerUrl);
        if (!$data) {
            $this->logger->error(
                'unable to retrieve organizer JSON-LD',
                $logContext
            );

            return false;
        }

        $organizer = json_decode($data);

        $jsonLogContext = $logContext + ['json' => $data];

        if (!is_object($organizer)) {
            $this->logger->error(
                'unable to decode organizer JSON-LD',
                $jsonLogContext + ['json_error' => json_last_error_msg()]
            );

            return false;
        } else {
            $this->logger->debug(
                'successfully retrieved organizer JSON-LD',
                $jsonLogContext
            );
        }

        $organizerLabels = isset($organizer->labels) ? $organizer->labels : [];

        $uitpasLabelsPresentOnOrganizer = array_filter(
            $organizerLabels,
            function ($label) {
                $label = new Label($label->name);
                return $this->uitpasLabels->contains($label);
            }
        );

        $labelLogContext = $logContext + [
            'uitpas_labels' => $this->uitpasLabels->asArray(),
            'extracted_organizer_labels' => $organizerLabels,
            'organizer_uitpas_labels' => array_values($uitpasLabelsPresentOnOrganizer),
        ];

        if (empty($uitpasLabelsPresentOnOrganizer)) {
            $this->logger->debug('no uitpas labels present on organizer', $labelLogContext);
            return false;
        } else {
            $this->logger->debug('uitpas labels present on organizer', $labelLogContext);
            return true;
        }
    }
}
