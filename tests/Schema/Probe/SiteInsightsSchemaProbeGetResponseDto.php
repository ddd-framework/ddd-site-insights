<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Schema\Probe;

use DDD\Domain\SiteInsights\Entities\Search\SearchDataAvailability;
use DDD\Domain\SiteInsights\Entities\Search\SearchPerformanceRows;
use DDD\Domain\SiteInsights\Entities\Search\SearchReports;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * The neutral report classes of B0 as a response, for the schema gate only: their endpoints arrive with the search
 * controllers (B2), so they are not part of the committed schema yet.
 */
class SiteInsightsSchemaProbeGetResponseDto extends RestResponseDto
{
    /** @var SearchPerformanceRows A list of rows with window and coverage */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public SearchPerformanceRows $searchPerformanceRows;

    /** @var SearchReports Answers per question */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public SearchReports $searchReports;

    /** @var SearchDataAvailability The last final day */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public SearchDataAvailability $searchDataAvailability;
}
