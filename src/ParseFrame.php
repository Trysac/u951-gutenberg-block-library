<?php

declare(strict_types=1);

namespace Trysac\GutenbergBlockLibrary;

/**
 * Stack frame used during block parsing.
 */
final class ParseFrame
{
    /**
     * Creates a new ParseFrame instance.
     *
     * @param string $rawName original block name as written in the comment
     * @param string $name normalized block name
     * @param array<string, mixed> $attributes parsed block attributes
     * @param string $rawAttributes original JSON string in the comment
     * @param string $innerHtml concatenated inner HTML excluding inner blocks
     * @param list<ParsedBlock> $innerBlocks nested blocks in document order
     * @param list<string|null> $innerContent HTML chunks interleaved with null placeholders for each inner block
     */
    public function __construct(
        public string $rawName,
        public string $name,
        public array $attributes,
        public string $rawAttributes,
        public string $innerHtml = '',
        public array $innerBlocks = [],
        public array $innerContent = [],
    ) {}
}
