<?php

declare(strict_types=1);

namespace Trail\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for collection endpoints.
 * HTTP-driven against a live server; self-skips when the server is not up.
 */
class CollectionsIntegrationTest extends TestCase
{
    private string $baseUrl = 'http://localhost:18000';

    protected function setUp(): void
    {
        // /api/collections proves server up + DB reachable + feature present.
        $ch = curl_init($this->baseUrl . '/api/collections');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0']);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->markTestSkipped('API server not running or DB down. Start with: ./run.sh');
        }
    }

    public function testCollectionsListReturnsCollections(): void
    {
        $response = $this->makeRequest('GET', '/api/collections');

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('collections', $response['data']);
        $this->assertIsArray($response['data']['collections']);
    }

    public function testCollectionDetailEntriesAndRss(): void
    {
        $list = $this->makeRequest('GET', '/api/collections');
        $this->assertEquals(200, $list['status']);

        $collections = $list['data']['collections'] ?? [];
        if (empty($collections)) {
            $this->markTestSkipped('No collections on live server to exercise.');
        }

        $slug = $collections[0]['slug'];

        // Detail
        $detail = $this->makeRequest('GET', "/api/collections/{$slug}");
        $this->assertEquals(200, $detail['status']);
        $this->assertArrayHasKey('collection', $detail['data']);
        $this->assertSame($slug, $detail['data']['collection']['slug']);

        // Entries
        $entries = $this->makeRequest('GET', "/api/collections/{$slug}/entries");
        $this->assertEquals(200, $entries['status']);
        $this->assertArrayHasKey('entries', $entries['data']);
        $this->assertArrayHasKey('has_more', $entries['data']);

        // RSS
        $rss = $this->makeRequest('GET', "/api/collections/{$slug}/rss");
        $this->assertEquals(200, $rss['status']);
        $this->assertStringContainsString('<rss', $rss['raw']);
        $this->assertStringContainsString($slug, $rss['raw']);
    }

    /**
     * Spec §7/§8: seed via the admin APIs, assert the claim on the landing
     * feed (/), /api/entries, /api/rss; assert the poster still shows on
     * /@{nickname}; delete the collection and assert the entry reverts.
     *
     * Requires TRAIL_ADMIN_API_TOKEN (an admin user's api_token) in the env.
     */
    public function testCollectionAttributionEndToEnd(): void
    {
        $token = getenv('TRAIL_ADMIN_API_TOKEN') ?: null;
        if ($token === null) {
            $this->markTestSkipped('TRAIL_ADMIN_API_TOKEN not set - export an admin api_token to run the attribution test.');
        }

        $suffix = str_replace('.', '-', uniqid());
        $tagName = 'it-' . $suffix;
        $slug = 'it-' . $suffix;
        $name = 'IT Collection ' . $suffix;

        $entryId = null;
        $tagId = null;
        $collectionId = null;

        try {
            // 1. Seed an entry
            $create = $this->makeRequest('POST', '/api/entries', ['text' => 'Collections IT entry ' . $suffix], $token);
            $this->assertEquals(201, $create['status']);
            $entryId = (int) ($create['data']['id'] ?? 0);
            $this->assertGreaterThan(0, $entryId);

            // 2. Fetch the entry (hash_id + nickname) from the public list
            $list = $this->makeRequest('GET', '/api/entries?limit=50', null, $token);
            $this->assertEquals(200, $list['status']);
            $entry = $this->findEntry($list['data'], $entryId);
            $this->assertNotNull($entry, 'Seeded entry not found in /api/entries');
            $hashId = $entry['hash_id'];
            $nickname = $entry['user_nickname'];

            // 3. Tag it
            $tagged = $this->makeRequest('PUT', "/api/entries/{$hashId}/tags", ['tags' => [$tagName]], $token);
            $this->assertEquals(200, $tagged['status']);
            $tagId = (int) ($tagged['data']['tags'][0]['id'] ?? 0);
            $this->assertGreaterThan(0, $tagId);

            // 4. Create a collection claiming the tag
            $created = $this->makeRequest('POST', '/api/admin/collections', ['name' => $name, 'slug' => $slug, 'tag_ids' => [$tagId]], $token);
            $this->assertEquals(201, $created['status']);
            $collectionId = (int) ($created['data']['collection']['id'] ?? 0);
            $this->assertGreaterThan(0, $collectionId);

            // 5. Assert the claim on every surface
            $landing = $this->makeRequest('GET', '/');
            $this->assertStringContainsString('/collection/' . $slug, $landing['raw']);

            $api = $this->makeRequest('GET', '/api/entries?limit=50', null, $token);
            $claimed = $this->findEntry($api['data'], $entryId);
            $this->assertNotNull($claimed, 'Entry missing from /api/entries');
            $this->assertIsArray($claimed['collection']);
            $this->assertSame($slug, $claimed['collection']['slug']);

            $rss = $this->makeRequest('GET', '/api/rss');
            $this->assertEquals(200, $rss['status']);
            $this->assertStringContainsString($name, $rss['raw']);

            // Poster page unchanged: entry under the poster, no collection key
            $profile = $this->makeRequest('GET', "/api/users/{$nickname}/entries?limit=50");
            $this->assertEquals(200, $profile['status']);
            $posterEntry = $this->findEntry($profile['data'], $entryId);
            $this->assertNotNull($posterEntry, 'Entry missing from poster page');
            $this->assertArrayNotHasKey('collection', $posterEntry);

            // Collection page + scoped endpoints
            $page = $this->makeRequest('GET', '/collection/' . $slug);
            $this->assertEquals(200, $page['status']);
            $this->assertStringContainsString($name, $page['raw']);

            $scoped = $this->makeRequest('GET', "/api/collections/{$slug}/entries?limit=50");
            $this->assertEquals(200, $scoped['status']);
            $this->assertNotNull($this->findEntry($scoped['data'], $entryId), 'Entry not found in collection entries');

            $collectionRss = $this->makeRequest('GET', "/api/collections/{$slug}/rss");
            $this->assertEquals(200, $collectionRss['status']);
            $this->assertStringContainsString($name, $collectionRss['raw']);

            // 6. Delete the collection and assert the revert
            $deleted = $this->makeRequest('DELETE', "/api/admin/collections/{$collectionId}", null, $token);
            $this->assertEquals(200, $deleted['status']);

            $reverted = $this->makeRequest('GET', '/api/entries?limit=50', null, $token);
            $unclaimed = $this->findEntry($reverted['data'], $entryId);
            $this->assertNotNull($unclaimed, 'Entry missing after revert check');
            $this->assertNull($unclaimed['collection'] ?? null, 'Entry still claimed after collection delete');

            $landingAfter = $this->makeRequest('GET', '/');
            $this->assertStringNotContainsString('/collection/' . $slug, $landingAfter['raw']);

            $rssAfter = $this->makeRequest('GET', '/api/rss');
            $this->assertStringNotContainsString($name, $rssAfter['raw']);
        } finally {
            // Best-effort cleanup, reverse order
            if ($collectionId !== null) {
                $this->makeRequest('DELETE', "/api/admin/collections/{$collectionId}", null, $token);
            }
            if ($entryId !== null) {
                $this->makeRequest('DELETE', "/api/entries/{$entryId}", null, $token);
            }
            if ($tagId !== null) {
                $this->makeRequest('DELETE', "/api/admin/tags/{$tagId}", null, $token);
            }
        }
    }

    /**
     * Find an entry by id in a list response's entries array.
     */
    private function findEntry(array $data, int $id): ?array
    {
        foreach ($data['entries'] ?? [] as $entry) {
            if ((int) $entry['id'] === $id) {
                return $entry;
            }
        }

        return null;
    }

    private function makeRequest(string $method, string $path, ?array $data = null, ?string $token = null): array
    {
        $ch = curl_init($this->baseUrl . $path);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $headers = ['Content-Type: application/json', 'User-Agent: Mozilla/5.0'];
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $body = (string) substr($response, $headerSize);

        return [
            'status' => $httpCode,
            'data' => json_decode($body, true) ?? [],
            'raw' => $body,
        ];
    }
}
