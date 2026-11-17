<?php

declare(strict_types=1);

namespace Trysac\GutenbergBlockLibrary\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Trysac\GutenbergBlockLibrary\BlockException;
use Trysac\GutenbergBlockLibrary\BlockParser;
use Trysac\GutenbergBlockLibrary\BlockSerializer;
use Trysac\GutenbergBlockLibrary\Cli;
use Trysac\GutenbergBlockLibrary\Html;
use Trysac\GutenbergBlockLibrary\ParsedBlock;
use Trysac\GutenbergBlockLibrary\Registry;
use Trysac\GutenbergBlockLibrary\Renderer;

/**
 * Comprehensive unit and acceptance tests for the Gutenberg block library.
 *
 * @internal
 */
final class BlockLibraryTest extends TestCase
{
    public function testAcceptanceParseNoticeParagraphAndCtaButton(): void
    {
        $blocks = BlockParser::parse('<!-- wp:trysac/notice {"type":"warning"} -->Careful<!-- /wp:trysac/notice -->');
        self::assertCount(1, $blocks);
        self::assertSame('trysac/notice', $blocks[0]->name);
        self::assertSame(['type' => 'warning'], $blocks[0]->attributes);
        self::assertSame('Careful', $blocks[0]->innerHtml);

        $coreBlocks = BlockParser::parse('<!-- wp:paragraph -->x<!-- /wp:paragraph -->');
        self::assertCount(1, $coreBlocks);
        self::assertSame('core/paragraph', $coreBlocks[0]->name);
        self::assertSame('x', $coreBlocks[0]->innerHtml);

        $selfClosing = BlockParser::parse('<!-- wp:trysac/cta-button {"text":"Go","url":"/start"} /-->');
        self::assertCount(1, $selfClosing);
        self::assertSame('trysac/cta-button', $selfClosing[0]->name);
        self::assertSame(['text' => 'Go', 'url' => '/start'], $selfClosing[0]->attributes);
        self::assertTrue($selfClosing[0]->selfClosing);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function committedFixturesProvider(): iterable
    {
        $files = glob(__DIR__ . '/fixtures/*.html');
        self::assertIsArray($files);
        self::assertNotEmpty($files);

        foreach ($files as $file) {
            yield basename($file) => [$file];
        }
    }

    #[DataProvider('committedFixturesProvider')]
    public function testCommittedFixturesRoundTripByteForByte(string $fixturePath): void
    {
        $x = file_get_contents($fixturePath);
        self::assertIsString($x);
        self::assertSame($x, BlockSerializer::serialize(BlockParser::parse($x)));
    }

    public function testAcceptanceNoticeRenderingAndValidation(): void
    {
        $renderer = new Renderer();

        $warningHtml = $renderer->renderAll('<!-- wp:trysac/notice {"type":"warning"} -->Careful<!-- /wp:trysac/notice -->');
        self::assertSame(
            '<div class="wp-block-trysac-notice is-type-warning" role="alert">Careful</div>',
            $warningHtml,
        );

        $infoHtml = $renderer->renderAll('<!-- wp:trysac/notice {"type":"info"} -->Note<!-- /wp:trysac/notice -->');
        self::assertSame(
            '<div class="wp-block-trysac-notice is-type-info" role="status">Note</div>',
            $infoHtml,
        );

        $defaultHtml = $renderer->renderAll('<!-- wp:trysac/notice -->Default<!-- /wp:trysac/notice -->');
        self::assertSame(
            '<div class="wp-block-trysac-notice is-type-info" role="status">Default</div>',
            $defaultHtml,
        );

        $errorHtml = $renderer->renderAll('<!-- wp:trysac/notice {"type":"error"} -->Failed<!-- /wp:trysac/notice -->');
        self::assertStringContainsString('role="alert"', $errorHtml);

        try {
            $renderer->renderAll('<!-- wp:trysac/notice {"type":"danger"} -->Bad<!-- /wp:trysac/notice -->');
            self::fail('Expected invalid_attribute BlockException');
        } catch (BlockException $e) {
            self::assertSame('invalid_attribute', $e->errorCode);
        }
    }

    public function testAcceptanceAccordionRenderingAndSequentialIds(): void
    {
        $renderer = new Renderer();

        $markup = '<!-- wp:trysac/accordion {"title":"Q&A"} -->Answer 1<!-- /wp:trysac/accordion -->'
            . '<!-- wp:trysac/accordion {"title":"Second","open":true,"headingLevel":4} -->Answer 2<!-- /wp:trysac/accordion -->';

        $rendered = $renderer->renderAll($markup);

        self::assertStringContainsString('aria-expanded="false"', $rendered);
        self::assertStringContainsString('aria-controls="trysac-accordion-1"', $rendered);
        self::assertStringContainsString('>Q&amp;A</button></h3>', $rendered);
        self::assertStringContainsString('<div id="trysac-accordion-1" class="wp-block-trysac-accordion__panel" role="region" aria-labelledby="trysac-accordion-1-button" hidden>Answer 1</div>', $rendered);

        self::assertStringContainsString('aria-expanded="true"', $rendered);
        self::assertStringContainsString('aria-controls="trysac-accordion-2"', $rendered);
        self::assertStringContainsString('<h4 class="wp-block-trysac-accordion__heading">', $rendered);
        self::assertStringContainsString('<div id="trysac-accordion-2" class="wp-block-trysac-accordion__panel" role="region" aria-labelledby="trysac-accordion-2-button">Answer 2</div>', $rendered);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeUrlsProvider(): iterable
    {
        yield 'javascript lowercase' => ['javascript:alert(1)'];
        yield 'javascript leading space uppercase' => [' JAVASCRIPT:x'];
        yield 'javascript control char' => ["java\tscript:alert(1)"];
        yield 'data uri' => ['data:text/html,<script>alert(1)</script>'];
        yield 'vbscript' => ['vbscript:msgbox(1)'];
        yield 'protocol relative' => ['//evil.example.com/payload.js'];
        yield 'whitespace only' => ['   '];
    }

    #[DataProvider('unsafeUrlsProvider')]
    public function testAcceptanceCtaButtonRejectsUnsafeUrls(string $unsafeUrl): void
    {
        $renderer = new Renderer();
        $block = new ParsedBlock(
            name: 'trysac/cta-button',
            attributes: ['text' => 'Go', 'url' => $unsafeUrl],
            selfClosing: true,
        );

        try {
            $renderer->render($block);
            self::fail('Expected unsafe_url BlockException');
        } catch (BlockException $e) {
            self::assertSame('unsafe_url', $e->errorCode);
        }
    }

    public function testAcceptanceCtaButtonValidUrlsAndEmptyText(): void
    {
        $renderer = new Renderer();

        $html = $renderer->renderAll('<!-- wp:trysac/cta-button {"text":"Go","url":"/start"} /-->');
        self::assertSame('<a class="wp-block-trysac-cta-button" href="/start">Go</a>', $html);

        $httpsHtml = $renderer->renderAll('<!-- wp:trysac/cta-button {"text":"Docs","url":"https://example.com/docs"} /-->');
        self::assertSame('<a class="wp-block-trysac-cta-button" href="https://example.com/docs">Docs</a>', $httpsHtml);

        $mailtoHtml = $renderer->renderAll('<!-- wp:trysac/cta-button {"text":"Contact","url":"mailto:info@example.com"} /-->');
        self::assertSame('<a class="wp-block-trysac-cta-button" href="mailto:info@example.com">Contact</a>', $mailtoHtml);

        $hashHtml = $renderer->renderAll('<!-- wp:trysac/cta-button {"text":"Jump","url":"#section"} /-->');
        self::assertSame('<a class="wp-block-trysac-cta-button" href="#section">Jump</a>', $hashHtml);

        try {
            $renderer->renderAll('<!-- wp:trysac/cta-button {"text":"","url":"/start"} /-->');
            self::fail('Expected invalid_attribute BlockException');
        } catch (BlockException $e) {
            self::assertSame('invalid_attribute', $e->errorCode);
        }
    }

    public function testAcceptanceFigureAndTabsRendering(): void
    {
        $renderer = new Renderer();

        try {
            $renderer->renderAll('<!-- wp:trysac/figure {"url":"https://example.com/a.jpg"} /-->');
            self::fail('Expected missing_alt BlockException');
        } catch (BlockException $e) {
            self::assertSame('missing_alt', $e->errorCode);
        }

        $decorativeHtml = $renderer->renderAll('<!-- wp:trysac/figure {"url":"https://example.com/a.jpg","decorative":true} /-->');
        self::assertSame('<figure class="wp-block-trysac-figure"><img src="https://example.com/a.jpg" alt=""></figure>', $decorativeHtml);

        $captionedHtml = $renderer->renderAll('<!-- wp:trysac/figure {"url":"https://example.com/a.jpg","alt":"Photo","caption":"A caption"} /-->');
        self::assertSame('<figure class="wp-block-trysac-figure"><img src="https://example.com/a.jpg" alt="Photo"><figcaption>A caption</figcaption></figure>', $captionedHtml);

        $tabsMarkup = '<!-- wp:trysac/tabs -->'
            . '<!-- wp:trysac/tab {"label":"One"} -->Panel 1<!-- /wp:trysac/tab -->'
            . '<!-- wp:trysac/tab {"label":"Two"} -->Panel 2<!-- /wp:trysac/tab -->'
            . '<!-- wp:trysac/tab {"label":"Three"} -->Panel 3<!-- /wp:trysac/tab -->'
            . '<!-- /wp:trysac/tabs -->';

        $tabsHtml = $renderer->renderAll($tabsMarkup);
        self::assertStringContainsString('role="tablist"', $tabsHtml);
        self::assertSame(1, substr_count($tabsHtml, 'aria-selected="true"'));
        self::assertSame(2, substr_count($tabsHtml, 'aria-selected="false"'));
        self::assertSame(2, substr_count($tabsHtml, ' hidden>'));
    }

    public function testAcceptanceRegistryBlockJsonAndParserLimits(): void
    {
        $json = Registry::blockJson('trysac/notice');
        /** @var array{apiVersion: int, name: string, attributes: array<string, array{type: string}>} $decoded */
        $decoded = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        self::assertSame(3, $decoded['apiVersion']);
        self::assertSame('trysac/notice', $decoded['name']);
        self::assertNotEmpty($decoded['attributes']);
        foreach ($decoded['attributes'] as $attrSpec) {
            self::assertArrayHasKey('type', $attrSpec);
        }

        foreach (Registry::names() as $blockName) {
            $bJson = Registry::blockJson($blockName);
            self::assertNotEmpty($bJson);
        }

        try {
            BlockParser::parse(str_repeat('a', 1_048_577));
            self::fail('Expected input_too_large');
        } catch (BlockException $e) {
            self::assertSame('input_too_large', $e->errorCode);
        }

        $deep = str_repeat('<!-- wp:trysac/notice -->', 33) . str_repeat('<!-- /wp:trysac/notice -->', 33);
        try {
            BlockParser::parse($deep);
            self::fail('Expected too_deep');
        } catch (BlockException $e) {
            self::assertSame('too_deep', $e->errorCode);
        }

        try {
            BlockParser::parse('<!-- wp:trysac/notice -->');
            self::fail('Expected unclosed_block');
        } catch (BlockException $e) {
            self::assertSame('unclosed_block', $e->errorCode);
        }

        try {
            BlockParser::parse('<!-- wp:trysac/notice {not-json} -->x<!-- /wp:trysac/notice -->');
            self::fail('Expected invalid_attributes');
        } catch (BlockException $e) {
            self::assertSame('invalid_attributes', $e->errorCode);
        }
    }

    public function testAdversarialGrammarAndValidationEdgeCases(): void
    {
        self::assertSame([], BlockParser::parse(''));

        $freeform = BlockParser::parse('<p>Plain HTML with <!-- regular comment --> inside</p>');
        self::assertCount(1, $freeform);
        self::assertSame('', $freeform[0]->name);
        self::assertSame('<p>Plain HTML with <!-- regular comment --> inside</p>', BlockSerializer::serialize($freeform));

        try {
            BlockParser::parse('<!-- wp:trysac/notice [1,2,3] -->x<!-- /wp:trysac/notice -->');
            self::fail('Expected invalid_attributes for JSON array');
        } catch (BlockException $e) {
            self::assertSame('invalid_attributes', $e->errorCode);
        }

        try {
            BlockParser::parse('<!-- /wp:trysac/notice -->');
            self::fail('Expected unclosed_block for orphan closer');
        } catch (BlockException $e) {
            self::assertSame('unclosed_block', $e->errorCode);
        }

        try {
            BlockParser::parse('<!-- wp:trysac/notice {"type":"info"');
            self::fail('Expected unclosed_block for unfinished comment delimiter');
        } catch (BlockException $e) {
            self::assertSame('unclosed_block', $e->errorCode);
        }

        try {
            BlockParser::parse('<!-- wp:INVALID_CAPS -->x<!-- /wp:INVALID_CAPS -->');
            self::fail('Expected invalid_attributes for uppercase block name');
        } catch (BlockException $e) {
            self::assertSame('invalid_attributes', $e->errorCode);
        }

        try {
            BlockParser::parse('<!-- wp:trysac/notice -->x<!-- /wp:trysac/notice {"a":1} -->');
            self::fail('Expected invalid_attributes for closer with attributes');
        } catch (BlockException $e) {
            self::assertSame('invalid_attributes', $e->errorCode);
        }

        try {
            Registry::metadata('trysac/nonexistent');
            self::fail('Expected unknown_block');
        } catch (BlockException $e) {
            self::assertSame('unknown_block', $e->errorCode);
        }

        try {
            Registry::validateAttributes('trysac/notice', ['unknownProp' => 'x']);
            self::fail('Expected invalid_attribute for unknown attribute');
        } catch (BlockException $e) {
            self::assertSame('invalid_attribute', $e->errorCode);
        }

        try {
            Registry::validateAttributes('trysac/accordion', ['title' => 'Valid', 'open' => 'not-bool']);
            self::fail('Expected invalid_attribute for wrong boolean type');
        } catch (BlockException $e) {
            self::assertSame('invalid_attribute', $e->errorCode);
        }

        try {
            Registry::validateAttributes('trysac/accordion', ['title' => 'Valid', 'headingLevel' => 1]);
            self::fail('Expected invalid_attribute for headingLevel < 2');
        } catch (BlockException $e) {
            self::assertSame('invalid_attribute', $e->errorCode);
        }

        try {
            Registry::validateAttributes('trysac/accordion', ['title' => 'Valid', 'headingLevel' => 7]);
            self::fail('Expected invalid_attribute for headingLevel > 6');
        } catch (BlockException $e) {
            self::assertSame('invalid_attribute', $e->errorCode);
        }

        try {
            Registry::validateAttributes('trysac/accordion', ['title' => 'Valid', 'headingLevel' => '3']);
            self::fail('Expected invalid_attribute for non-int headingLevel');
        } catch (BlockException $e) {
            self::assertSame('invalid_attribute', $e->errorCode);
        }

        try {
            Registry::validateAttributes('trysac/accordion', []);
            self::fail('Expected invalid_attribute for missing required attribute');
        } catch (BlockException $e) {
            self::assertSame('invalid_attribute', $e->errorCode);
        }

        try {
            Registry::validateAttributes('trysac/notice', ['type' => 123]);
            self::fail('Expected invalid_attribute for non-string type');
        } catch (BlockException $e) {
            self::assertSame('invalid_attribute', $e->errorCode);
        }

        $renderer = new Renderer();
        try {
            $renderer->renderAll('<!-- wp:trysac/tabs --><!-- /wp:trysac/tabs -->');
            self::fail('Expected invalid_attribute for empty tabs');
        } catch (BlockException $e) {
            self::assertSame('invalid_attribute', $e->errorCode);
        }

        try {
            $renderer->renderAll('<!-- wp:trysac/tabs --><!-- wp:trysac/notice -->x<!-- /wp:trysac/notice --><!-- /wp:trysac/tabs -->');
            self::fail('Expected invalid_attribute for non-tab child inside tabs');
        } catch (BlockException $e) {
            self::assertSame('invalid_attribute', $e->errorCode);
        }

        $standaloneTab = $renderer->renderAll('<!-- wp:trysac/tab {"label":"Solo"} -->Body<!-- /wp:trysac/tab -->');
        self::assertStringContainsString('role="tabpanel"', $standaloneTab);

        $coreRendered = $renderer->renderAll('<!-- wp:paragraph --><p>Core</p><!-- /wp:paragraph -->');
        self::assertSame('<p>Core</p>', $coreRendered);
    }

    public function testBlockExceptionMessageTruncationAndCode(): void
    {
        $longInput = str_repeat('X', 300);
        $ex = new BlockException('invalid_attribute', $longInput);
        self::assertSame('invalid_attribute', $ex->errorCode);
        self::assertSame(0, $ex->getCode());
        self::assertLessThanOrEqual(200, strlen($ex->getMessage()));
        self::assertStringEndsWith('...', $ex->getMessage());
    }

    public function testManualParsedBlockSerialization(): void
    {
        $manual = new ParsedBlock(
            name: 'trysac/cta-button',
            attributes: ['text' => 'Click', 'url' => '/go'],
            selfClosing: true,
        );
        self::assertSame(
            '<!-- wp:trysac/cta-button {"text":"Click","url":"/go"} /-->',
            BlockSerializer::serialize([$manual]),
        );

        $parent = new ParsedBlock(
            name: 'core/group',
            attributes: ['layout' => 'flex'],
            innerHtml: '<span>Header</span>',
            innerBlocks: [$manual],
        );
        self::assertSame(
            '<!-- wp:group {"layout":"flex"} --><span>Header</span><!-- wp:trysac/cta-button {"text":"Click","url":"/go"} /--><!-- /wp:group -->',
            BlockSerializer::serialize([$parent]),
        );
    }

    public function testCliRunExitCodes(): void
    {
        $stdout = fopen('php://memory', 'wb+');
        $stderr = fopen('php://memory', 'wb+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        $codeUsage = Cli::run(['bin/cli'], $stdout, $stderr);
        self::assertSame(2, $codeUsage);

        $codeMissing = Cli::run(['bin/cli', 'render', '/nonexistent/path/file.html'], $stdout, $stderr);
        self::assertSame(2, $codeMissing);

        $fixture = __DIR__ . '/fixtures/notice.html';
        ftruncate($stdout, 0);
        rewind($stdout);
        $codeOk = Cli::run(['bin/cli', 'render', $fixture], $stdout, $stderr);
        self::assertSame(0, $codeOk);
        rewind($stdout);
        $out = stream_get_contents($stdout);
        self::assertIsString($out);
        self::assertStringContainsString('wp-block-trysac-notice', $out);

        $tmpBad = tempnam(sys_get_temp_dir(), 'trysac-bad-');
        self::assertIsString($tmpBad);
        file_put_contents($tmpBad, '<!-- wp:trysac/notice {"type":"danger"} -->Bad<!-- /wp:trysac/notice -->');
        try {
            $codeBlockErr = Cli::run(['bin/cli', 'render', $tmpBad], $stdout, $stderr);
            self::assertSame(1, $codeBlockErr);
        } finally {
            @unlink($tmpBad);
            fclose($stdout);
            fclose($stderr);
        }
    }

    public function testHtmlEscapingAdversarialPayloads(): void
    {
        $payload = '<script>alert("xss" & \'single\')</script>';
        $escapedText = Html::text($payload);
        $escapedAttr = Html::attr($payload);
        self::assertStringNotContainsString('<script>', $escapedText);
        self::assertStringNotContainsString('"', $escapedAttr);
        self::assertStringNotContainsString("'", $escapedAttr);
    }
}
