<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Unit\Search;

use DDD\Domain\SiteInsights\Entities\Sites\InsightsPageGroup;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsSite;
use DDD\Domain\SiteInsights\Utils\SearchRegex;
use PHPUnit\Framework\TestCase;

/** RE2 expressions of the counterpart fetch and the page groups */
class SearchRegexTest extends TestCase
{
    public function testEveryRe2SyntaxCharacterIsEscapedAndNothingElse(): void
    {
        self::assertSame('a\\.b\\(c\\)\\|d\\\\e\\[f\\]\\{g\\}\\^h\\$i\\*\\+\\?', SearchRegex::escapeLiteral('a.b(c)|d\\e[f]{g}^h$i*+?'));
        self::assertSame('größe café 東京 - _ / : #', SearchRegex::escapeLiteral('größe café 東京 - _ / : #'), 'non-ASCII and other characters stay literal');
    }

    public function testExactAlternationMatchesTheKeysOnly(): void
    {
        $expression = SearchRegex::buildExactAlternation(['widget (blue)', 'https://example.test/a?b=1']);

        self::assertSame('^(?:widget \\(blue\\)|https://example\\.test/a\\?b=1)$', $expression);
        self::assertSame(1, preg_match('~' . $expression . '~u', 'widget (blue)'));
        self::assertSame(0, preg_match('~' . $expression . '~u', 'widget (blue) cheap'));
        self::assertSame(1, preg_match('~' . $expression . '~u', 'https://example.test/a?b=1'));
    }

    public function testPageGroupExpressions(): void
    {
        $insightsPageGroup = new InsightsPageGroup();
        $insightsPageGroup->key = 'mixed';
        $insightsPageGroup->pathPatterns = ['^/guides/', 'faq$'];
        $urlPrefixSite = new InsightsSite();
        $urlPrefixSite->property = 'https://example.test/';
        $domainSite = new InsightsSite();
        $domainSite->property = 'sc-domain:example.test';

        $urlPrefixExpression = SearchRegex::buildPageGroupExpression($urlPrefixSite, $insightsPageGroup);
        $domainExpression = SearchRegex::buildPageGroupExpression($domainSite, $insightsPageGroup);

        self::assertSame('^https://example\\.test(?:(?:/guides/)|[^?#]*(?:faq$))', $urlPrefixExpression);
        self::assertSame('^https?://[^/]+(?:(?:/guides/)|[^?#]*(?:faq$))', $domainExpression);
        foreach (['https://example.test/guides/a/' => 1, 'https://example.test/help/faq' => 1, 'https://other.example.com/guides/' => 0, 'https://example.test/blog/guides/' => 0] as $url => $expectedMatch) {
            self::assertSame($expectedMatch, preg_match('~' . $urlPrefixExpression . '~u', $url), $url);
        }
        self::assertSame(1, preg_match('~' . $domainExpression . '~u', 'http://www.example.test/guides/a/'));
    }
}
