<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Utils;

use DDD\Domain\SiteInsights\Entities\Sites\InsightsPageGroup;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsSite;

/**
 * RE2 expressions of the module's provider filters: exact alternations of keys (counterpart fetch) and the page
 * expression of a page group. Static helpers; no provider call.
 */
class SearchRegex
{
    /** @var string Characters RE2 reads as syntax; each gets a backslash */
    public const string SYNTAX_CHARACTERS = '\\.+*?()|[]{}^$';

    /** @var string Any scheme and host of a domain property */
    public const string DOMAIN_PROPERTY_HOST_EXPRESSION = '^https?://[^/]+';

    /**
     * @param string $value
     * @return string the value as a literal: every RE2 syntax character escaped, everything else (non-ASCII included)
     *         kept; no `(?i)`, keys come from the provider's own answers and match exactly
     */
    public static function escapeLiteral(string $value): string
    {
        return addcslashes($value, self::SYNTAX_CHARACTERS);
    }

    /**
     * @param string[] $keys
     * @return string `^(?:k1|k2|…)$` matching exactly the keys
     */
    public static function buildExactAlternation(array $keys): string
    {
        return '^(?:' . implode('|', array_map(self::escapeLiteral(...), $keys)) . ')$';
    }

    /**
     * The page expression of a group: the property's host part, then the group's path patterns as alternatives. A
     * pattern anchored with `^` starts at the path; an unanchored one may match anywhere in the path. A domain
     * property matches every host of the domain (`^https?://[^/]+`); a URL-prefix property matches its own scheme,
     * host and port only.
     *
     * @param InsightsSite $insightsSite
     * @param InsightsPageGroup $insightsPageGroup
     * @return string
     */
    public static function buildPageGroupExpression(InsightsSite $insightsSite, InsightsPageGroup $insightsPageGroup): string
    {
        $alternatives = [];
        foreach ($insightsPageGroup->pathPatterns as $pathPattern) {
            $alternatives[] = str_starts_with($pathPattern, '^')
                ? '(?:' . substr($pathPattern, 1) . ')'
                : '[^?#]*(?:' . $pathPattern . ')';
        }
        return self::buildPropertyHostExpression($insightsSite) . '(?:' . implode('|', $alternatives) . ')';
    }

    /**
     * @param InsightsSite $insightsSite
     * @return string the anchored scheme and host part of the property's URLs
     */
    public static function buildPropertyHostExpression(InsightsSite $insightsSite): string
    {
        if ($insightsSite->isDomainProperty()) {
            return self::DOMAIN_PROPERTY_HOST_EXPRESSION;
        }
        $scheme = (string)parse_url($insightsSite->property, PHP_URL_SCHEME);
        $host = (string)parse_url($insightsSite->property, PHP_URL_HOST);
        $port = parse_url($insightsSite->property, PHP_URL_PORT);
        return '^' . self::escapeLiteral($scheme . '://' . $host . ($port !== null && $port !== false ? ':' . $port : ''));
    }
}
