<?php

declare(strict_types=1);

namespace Trail\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for combined tag and text search on /api/entries.
 * HTTP-driven against a live server; self-skips when the server is not up.
 */
class TagSearchTest extends TestCase
{
    private string $baseUrl = 'http://localhost:18000';

    protected function setUp(): void
    {
        $response = $this->makeRequest('/api/tags');

        if ($response['status'] !== 200) {
            $this->markTestSkipped('API server not running or DB down. Start with: ./run.sh');
        }
    }

    public function testTagOnlyQueryFiltersByTag(): void
    {
        $slug = $this->tagWithEntries();
        if ($slug === null) {
            $this->markTestSkipped('No tags with entries on the live server to exercise.');
        }

        $data = $this->makeRequest('/api/entries?q=' . rawurlencode('#' . $slug) . '&limit=100')['data'];
        $entries = $data['entries'] ?? [];

        $this->assertSame('#' . $slug, $data['search_query'] ?? null);
        $this->assertNotEmpty($entries, 'A tag with entries must return them.');

        foreach ($entries as $entry) {
            $this->assertContains($slug, $this->tagSlugsOf($entry));
        }

        if ($data['has_more'] ?? false) {
            $this->assertGreaterThanOrEqual(count($entries), (int) ($data['total_count'] ?? 0));
        } else {
            $this->assertCount((int) ($data['total_count'] ?? 0), $entries, 'total_count must match the page when nothing is left to load.');
        }
    }

    public function testTwoTagsReturnOnlyEntriesCarryingBoth(): void
    {
        $slugs = $this->twoTagsWithEntries();
        if ($slugs === null) {
            $this->markTestSkipped('No two tags sharing an entry on the live server to exercise.');
        }

        [$first, $second] = $slugs;
        $singleIds = $this->entryIdsOf($this->entriesOf('/api/entries?q=' . rawurlencode('#' . $first) . '&limit=100'));

        $combined = $this->entriesOf('/api/entries?q=' . rawurlencode('#' . $first . ' #' . $second) . '&limit=100');

        foreach ($combined as $entry) {
            $tagSlugs = $this->tagSlugsOf($entry);
            $this->assertContains($first, $tagSlugs);
            $this->assertContains($second, $tagSlugs, 'Entries must carry every tag in the query.');
            $this->assertContains($entry['hash_id'], $singleIds, 'Adding a tag must only narrow the result set.');
        }
    }

    public function testTextAndTagBothApply(): void
    {
        $slug = $this->tagWithEntries();
        if ($slug === null) {
            $this->markTestSkipped('No tags with entries on the live server to exercise.');
        }

        $tagged = $this->entriesOf('/api/entries?q=' . rawurlencode('#' . $slug) . '&limit=100');
        $word = $this->longestWordOf((string) ($tagged[0]['text'] ?? ''));
        if ($word === null) {
            $this->markTestSkipped('Tagged entry has no word long enough for a FULLTEXT match.');
        }

        $singleIds = $this->entryIdsOf($tagged);

        foreach ($this->entriesOf('/api/entries?q=' . rawurlencode($word . ' #' . $slug) . '&limit=100') as $entry) {
            $this->assertContains($slug, $this->tagSlugsOf($entry), 'The tag must still filter.');
            $this->assertContains($entry['hash_id'], $singleIds, 'Adding text must only narrow the result set.');
        }
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function twoTagsWithEntries(): ?array
    {
        $tags = $this->makeRequest('/api/tags')['data']['tags'] ?? [];

        $withEntries = [];
        foreach ($tags as $tag) {
            if ((int) ($tag['entry_count'] ?? 0) > 0) {
                $withEntries[] = $tag['slug'];
            }
        }

        foreach ($withEntries as $first) {
            foreach ($this->entriesOf('/api/entries?q=' . rawurlencode('#' . $first) . '&limit=100') as $entry) {
                foreach ($this->tagSlugsOf($entry) as $slug) {
                    if ($slug !== $first && in_array($slug, $withEntries, true)) {
                        return [$first, $slug];
                    }
                }
            }
        }

        return null;
    }

    private function tagWithEntries(): ?string
    {
        foreach ($this->makeRequest('/api/tags')['data']['tags'] ?? [] as $tag) {
            if ((int) ($tag['entry_count'] ?? 0) > 0) {
                return $tag['slug'];
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function entriesOf(string $path): array
    {
        return $this->makeRequest($path)['data']['entries'] ?? [];
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return string[]
     */
    private function entryIdsOf(array $entries): array
    {
        $ids = [];
        foreach ($entries as $entry) {
            $ids[] = (string) ($entry['hash_id'] ?? $entry['id'] ?? '');
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $entry
     * @return string[]
     */
    private function tagSlugsOf(array $entry): array
    {
        $slugs = [];
        foreach ($entry['tags'] ?? [] as $tag) {
            $slugs[] = $tag['slug'];
        }

        return $slugs;
    }

    private function longestWordOf(string $text): ?string
    {
        preg_match_all('/[a-zA-Z]{6,}/', $text, $matches);

        $longest = null;
        foreach ($matches[0] ?? [] as $word) {
            if ($longest === null || strlen($word) > strlen($longest)) {
                $longest = $word;
            }
        }

        return $longest;
    }

    /**
     * @return array{status: int, data: array<string, mixed>}
     */
    private function makeRequest(string $path): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0']);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return [
            'status' => $status,
            'data' => is_string($body) ? (json_decode($body, true) ?? []) : [],
        ];
    }
}
