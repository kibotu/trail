<?php

declare(strict_types=1);

namespace Trail\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trail\Services\SearchService;

class SearchServiceTest extends TestCase
{
    public function testParseQuerySeparatesTextFromTag(): void
    {
        $result = SearchService::parseQuery('foo #llm-memory');

        $this->assertSame('foo', $result['text']);
        $this->assertSame(['llm-memory'], $result['tags']);
    }

    public function testParseQueryKeepsEveryTagInOrder(): void
    {
        $result = SearchService::parseQuery('#llm-memory openai #gpt-4');

        $this->assertSame('openai', $result['text']);
        $this->assertSame(['llm-memory', 'gpt-4'], $result['tags']);
    }

    public function testParseQueryCollapsesWhitespaceLeftByRemovedTags(): void
    {
        $result = SearchService::parseQuery('a  #x   b');

        $this->assertSame('a b', $result['text']);
        $this->assertSame(['x'], $result['tags']);
    }

    public function testParseQueryDeduplicatesTags(): void
    {
        $result = SearchService::parseQuery('#php #PHP');

        $this->assertSame('', $result['text']);
        $this->assertSame(['php'], $result['tags']);
    }

    public function testParseQueryTrimsHyphensFromTagSlug(): void
    {
        $result = SearchService::parseQuery('#llm-memory- next');

        $this->assertSame('next', $result['text']);
        $this->assertSame(['llm-memory'], $result['tags']);
    }

    public function testParseQueryLowercasesUmlautTagSlug(): void
    {
        $result = SearchService::parseQuery('#Café');

        $this->assertSame('', $result['text']);
        $this->assertSame(['café'], $result['tags']);
    }

    public function testParseQueryWithoutTagKeepsTextUntouched(): void
    {
        $result = SearchService::parseQuery('plain text search');

        $this->assertSame('plain text search', $result['text']);
        $this->assertSame([], $result['tags']);
    }

    public function testParseQueryKeepsBareHashAsText(): void
    {
        $result = SearchService::parseQuery('c# and #');

        $this->assertSame('c# and #', $result['text']);
        $this->assertSame([], $result['tags']);
    }
}
