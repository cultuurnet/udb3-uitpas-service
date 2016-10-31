<?php

namespace CultuurNet\UDB3\UiTPASService\Specification;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class IsUiTPASOrganizerAccordingToJSONLD implements OrganizerSpecificationInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var string[]
     */
    protected $uitpasLabels;

    /**
     * @var string
     */
    protected $url;

    /**
     * @param string $url
     * @param string[] $uitpasLabels
     */
    public function __construct($url, $uitpasLabels)
    {
        $this->uitpasLabels = $uitpasLabels;
        $this->url = $url;
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

        $data = file_get_contents($organizerUrl);
        if ($data) {
            $organizer = json_decode($data);
        }

        if (!is_object($organizer)) {
            $this->logger->error(
                'unable to retrieve organizer JSON-LD',
                $logContext
            );

            return false;
        } else {
            $this->logger->debug(
                'succesfully retrieved organizer JSON-LD',
                $logContext + array(
                    'jsonld' => $organizer,
                )
            );
        }

        $organizerLabels = $organizer->labels ?: [];

        $uitpasLabels = $this->uitpasLabels;
        $uitpasLabelsPresentOnOrganizer = array_filter(
            $organizerLabels,
            function ($label) use ($uitpasLabels) {
                return in_array($label->name, $uitpasLabels);
            }
        );

        if (empty($uitpasLabelsPresentOnOrganizer)) {
            $this->logger->debug(
                'no uitpas labels present on organizer',
                $logContext + array(
                    'organizer_labels' => $organizerLabels,
                )
            );
        } else {
            $this->logger->debug(
                'uitpas labels present on organizer',
                $logContext + array(
                    'organizer_labels' => $organizerLabels,
                    'organizer_uitpas_labels' => $uitpasLabelsPresentOnOrganizer,
                )
            );
        }

        return !empty($uitpasLabelsPresentOnOrganizer);
    }
}
