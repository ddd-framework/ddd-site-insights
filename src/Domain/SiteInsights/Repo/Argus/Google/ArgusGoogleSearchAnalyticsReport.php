<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Repo\Argus\Google;

use DDD\Domain\Base\Repo\Argus\Attributes\ArgusLoad;
use DDD\Domain\Base\Repo\Argus\Traits\ArgusTrait;
use DDD\Domain\Base\Repo\Argus\Utils\ArgusApiOperation;
use DDD\Domain\Base\Repo\Argus\Utils\ArgusCache;
use DDD\Domain\SiteInsights\Entities\Google\GoogleApiFailure;
use DDD\Domain\SiteInsights\Entities\Google\GoogleSearchAnalyticsReport;
use DDD\Domain\SiteInsights\Entities\Google\GoogleSearchAnalyticsRow;
use DDD\Domain\SiteInsights\Entities\Google\GoogleSearchAnalyticsRows;
use DDD\Domain\SiteInsights\Services\GoogleApiRequestsService;
use DDD\Domain\SiteInsights\Utils\SearchDays;
use DDD\Infrastructure\Base\DateTime\DateTime;
use DDD\Infrastructure\Services\DDDService;

/**
 * Loads one Search Analytics question. Always a child of an {@see ArgusGoogleSearchAnalyticsReports} batch; the
 * source service sets cache level and lifetime per instance (`getArgusSettings()->argusLoad`).
 */
#[ArgusLoad(
    loadEndpoint: 'POST:https://www.googleapis.com/webmasters/v3/sites/{siteUrl}/searchAnalytics/query',
    cacheLevel: ArgusCache::CACHELEVEL_DB,
    cacheTtl: ArgusCache::CACHE_TTL_ONE_DAY
)]
class ArgusGoogleSearchAnalyticsReport extends GoogleSearchAnalyticsReport
{
    use ArgusTrait;

    /** @var int Seconds without a byte after which a transfer counts as stalled */
    public const int TRANSFER_STALL_SECONDS = 15;

    /** @var int Calls built for this question in this process (retries included); never serialised */
    protected int $providerCallAttempts = 0;

    /** @return int calls built for this question in this process (0 for an answer from the cache) */
    public function getProviderCallAttempts(): int
    {
        return $this->providerCallAttempts;
    }

    /** Google's payload: external call on the private transport, the token as header only */
    protected function getLoadPayload(): ?array
    {
        $this->providerCallAttempts++;
        /** @var GoogleApiRequestsService $googleApiRequestsService */
        $googleApiRequestsService = DDDService::instance()->getService(GoogleApiRequestsService::class);
        return $googleApiRequestsService->buildPayload(
            $this->credentialsEntryName,
            ['siteUrl' => $this->siteUrl],
            $this->buildRequestBody()
        );
    }

    /** Google's answer has no status/data envelope: never getResponseDataFromArgusResponse() */
    public function handleLoadResponse(mixed &$callResponseData = null, ?ArgusApiOperation &$apiOperation = null): void
    {
        $googleApiFailure = GoogleApiFailure::fromAnswer($callResponseData, $apiOperation);
        if ($googleApiFailure !== null) {
            $this->failure = $googleApiFailure;
            $this->postProcessLoadResponse($callResponseData, false, cacheFailure: false);
            return;
        }
        $this->rows = new GoogleSearchAnalyticsRows();
        foreach ((array)($callResponseData->rows ?? []) as $rowData) {
            if (!is_object($rowData)) {
                continue;
            }
            $googleSearchAnalyticsRow = new GoogleSearchAnalyticsRow();
            $googleSearchAnalyticsRow->keys = array_map('strval', (array)($rowData->keys ?? []));
            $googleSearchAnalyticsRow->clicks = (float)($rowData->clicks ?? 0);
            $googleSearchAnalyticsRow->impressions = (float)($rowData->impressions ?? 0);
            $googleSearchAnalyticsRow->ctr = (float)($rowData->ctr ?? 0);
            if (isset($rowData->position)) {
                $googleSearchAnalyticsRow->position = (float)$rowData->position;
            }
            $this->rows->add($googleSearchAnalyticsRow);
        }
        if (isset($callResponseData->responseAggregationType)) {
            $this->responseAggregationType = (string)$callResponseData->responseAggregationType;
        }
        $firstIncompleteDate = $callResponseData->metadata->first_incomplete_date ?? null;
        if (is_string($firstIncompleteDate) && SearchDays::isValidDay($firstIncompleteDate)) {
            $this->firstIncompleteDate = SearchDays::toDate($firstIncompleteDate);
        }
        if (isset($callResponseData->metadata->first_incomplete_hour)) {
            $this->firstIncompleteHour = (string)$callResponseData->metadata->first_incomplete_hour;
        }
        $this->fetchedAt = DateTime::fromTimestamp(time());
        $this->postProcessLoadResponse($callResponseData, true);
    }

    /**
     * Read by the Argus executor: a transfer without a byte for TRANSFER_STALL_SECONDS ends before the timeout.
     *
     * @return int[]
     */
    public function buildTransferStallCurlOptions(): array
    {
        return [CURLOPT_LOW_SPEED_LIMIT => 1, CURLOPT_LOW_SPEED_TIME => self::TRANSFER_STALL_SECONDS];
    }
}
