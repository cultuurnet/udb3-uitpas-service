<?php

namespace CultuurNet\UDB3\UiTPASService\OrganizerLabelReadRepository;

use CultuurNet\UDB3\LabelCollection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class JSONLDOrganizerLabelReadRepository implements OrganizerLabelReadRepositoryInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private $url;

    /**
     * @param string $url
     */
    public function __construct($url)
    {
        $this->url = $url;
        $this->logger = new NullLogger();
    }

    /**
     * @inheritdoc
     */
    public function getLabels($organizerId)
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

            return new LabelCollection();
        }

        $organizer = json_decode($data);

        $jsonLogContext = $logContext + ['json' => $data];

        if (!is_object($organizer)) {
            $this->logger->error(
                'unable to decode organizer JSON-LD',
                $jsonLogContext + ['json_error' => json_last_error_msg()]
            );

            return new LabelCollection();
        } else {
            $this->logger->debug(
                'successfully retrieved organizer JSON-LD',
                $jsonLogContext
            );
        }

        $organizerLabels = $this->extractAllLabels($organizer);

        return $organizerLabels;
    }

    /**
     * Extracts all labels from an organizer, both visible and hidden.
     *
     * @param object $organizer
     * @return LabelCollection
     */
    private function extractAllLabels($organizer)
    {
        $visibleLabels = isset($organizer->labels) ? $organizer->labels : [];
        $hiddenLabels = isset($organizer->hiddenLabels) ? $organizer->hiddenLabels : [];

        $labels = array_merge($visibleLabels, $hiddenLabels);

        return LabelCollection::fromStrings($labels);
    }
}
