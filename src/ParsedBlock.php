<?php

declare(strict_types=1);

namespace Trysac\GutenbergBlockLibrary;

/**
 * Immutable value object representing a parsed WordPress Gutenberg block or freeform HTML segment.
 */
final readonly class ParsedBlock
{
    /**
     * Creates a new ParsedBlock instance.
     *
     * @param string $name normalized block name (e.g. 'trysac/notice', 'core/paragraph', or '' for freeform HTML)
     * @param array<string, mixed> $attributes parsed block attributes
     * @param string $innerHtml concatenated inner HTML excluding inner blocks
     * @param list<ParsedBlock> $innerBlocks nested blocks in document order
     * @param list<string|null> $innerContent HTML chunks interleaved with null placeholders for each inner block
     * @param string $rawName original block name as written in the comment (for lossless serialization)
     * @param string $rawAttributes original JSON string in the comment (for lossless serialization)
     * @param bool $selfClosing whether the block was serialized as self-closing
     */
    public function __construct(
        public string $name,
        public array $attributes = [],
        public string $innerHtml = '',
        public array $innerBlocks = [],
        public array $innerContent = [],
        public string $rawName = '',
        public string $rawAttributes = '',
        public bool $selfClosing = false,
    ) {}
}
