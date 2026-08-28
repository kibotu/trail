<?php

declare(strict_types=1);

namespace Trail\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trail\Models\Collection;

class CollectionTest extends TestCase
{
    public function testValidateSlugAcceptsValidSlugs(): void
    {
        $this->assertNull(Collection::validateSlug('android'));
        $this->assertNull(Collection::validateSlug('a-b'));
        $this->assertNull(Collection::validateSlug('android-dev-2026'));
    }

    public function testValidateSlugRejectsTooShort(): void
    {
        $this->assertNotNull(Collection::validateSlug('a'));
    }

    public function testValidateSlugRejectsTooLong(): void
    {
        $this->assertNotNull(Collection::validateSlug(str_repeat('a', 65)));
        // Boundary: exactly 64 chars is valid.
        $this->assertNull(Collection::validateSlug(str_repeat('a', 64)));
    }

    public function testValidateSlugRejectsUppercaseAndNonHyphen(): void
    {
        $this->assertNotNull(Collection::validateSlug('Android')); // uppercase
        $this->assertNotNull(Collection::validateSlug('android!')); // punctuation
        $this->assertNotNull(Collection::validateSlug('android dev')); // space
    }

    public function testSlugifyLowercasesAndReplacesRuns(): void
    {
        $this->assertSame('android-dev', Collection::slugify('Android Dev'));
        $this->assertSame('hello-world', Collection::slugify('Hello World!'));
        $this->assertSame('multiple-spaces', Collection::slugify('  Multiple   Spaces  '));
        $this->assertSame('rust', Collection::slugify('Rust'));
    }

    public function testSlugifyTruncatesTo64(): void
    {
        $this->assertSame(64, mb_strlen(Collection::slugify(str_repeat('a ', 100))));
    }

    public function testReservedSlugsBlockRouteCollisions(): void
    {
        $this->assertContains('api', Collection::RESERVED_SLUGS);
        $this->assertContains('collection', Collection::RESERVED_SLUGS);
        $this->assertContains('collections', Collection::RESERVED_SLUGS);
        $this->assertContains('admin', Collection::RESERVED_SLUGS);
    }
}
