<?php

declare(strict_types=1);

namespace Trysac\GutenbergBlockLibrary\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Trysac\GutenbergBlockLibrary\BlockParser;
use Trysac\GutenbergBlockLibrary\BlockSerializer;
use Trysac\GutenbergBlockLibrary\Renderer;

/**
 * Property-based tests over deterministically generated block trees and adversarial inputs.
 *
 * @internal
 */
final class BlockPropertiesTest extends TestCase
{
    /**
     * Generates 40 deterministic block documents with a fixed seed.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function generatedBlockDocuments(): iterable
    {
        $rng = new Randomizer(new Mt19937(951_2025));
        $types = ['info', 'success', 'warning', 'error'];
        $specialChars = ['&', '<', '>', '"', "'", 'Q&A', '100% <safe>', 'Line 1'];

        for ($i = 1; $i <= 40; ++$i) {
            $type = $types[$rng->getInt(0, count($types) - 1)];
            $title = 'Title ' . $specialChars[$rng->getInt(0, count($specialChars) - 1)] . ' #' . (string) $i;
            $open = 1 === $rng->getInt(0, 1);
            $level = $rng->getInt(2, 6);
            $btnText = 'Action ' . $specialChars[$rng->getInt(0, count($specialChars) - 1)];

            $noticeAttrs = json_encode(['type' => $type], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $accAttrs = json_encode([
                'title' => $title,
                'open' => $open,
                'headingLevel' => $level,
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $ctaAttrs = json_encode([
                'text' => $btnText,
                'url' => '/path/' . (string) $i,
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $doc = sprintf(
                "<!-- wp:trysac/notice %s -->Body %d<!-- /wp:trysac/notice -->\n"
                . '<!-- wp:trysac/accordion %s --><!-- wp:trysac/cta-button %s /--><!-- /wp:trysac/accordion -->',
                $noticeAttrs,
                $i,
                $accAttrs,
                $ctaAttrs,
            );

            yield 'seed-case-' . (string) $i => [$doc, $title];
        }
    }

    #[DataProvider('generatedBlockDocuments')]
    public function testParseSerializeRoundTripAndEscapingInvariants(string $serialized, string $rawTitle): void
    {
        $parsed = BlockParser::parse($serialized);
        $reSerialized = BlockSerializer::serialize($parsed);
        self::assertSame($serialized, $reSerialized);

        $renderer = new Renderer();
        $html = $renderer->renderAll($serialized);
        self::assertNotEmpty($html);
        self::assertStringContainsString('id="trysac-accordion-1"', $html);

        if (str_contains($rawTitle, '<')) {
            self::assertStringNotContainsString($rawTitle, $html);
        }
    }
}
