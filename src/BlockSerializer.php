<?php

declare(strict_types=1);

namespace Trysac\GutenbergBlockLibrary;

/**
 * Serializes ParsedBlock trees back into WordPress Gutenberg block comment markup.
 */
final class BlockSerializer
{
    /**
     * Serializes a list of parsed blocks into a WordPress block grammar string.
     *
     * @param list<ParsedBlock> $blocks
     */
    public static function serialize(array $blocks): string
    {
        $output = '';
        foreach ($blocks as $block) {
            $output .= self::serializeBlock($block);
        }

        return $output;
    }

    /**
     * Serializes a single ParsedBlock instance.
     */
    public static function serializeBlock(ParsedBlock $block): string
    {
        if ('' === $block->name) {
            return $block->innerHtml;
        }

        $serializedName = '' !== $block->rawName
            ? $block->rawName
            : (str_starts_with($block->name, 'core/') ? substr($block->name, 5) : $block->name);

        $attrsSegment = '';
        if ('' !== $block->rawAttributes) {
            $attrsSegment = ' ' . $block->rawAttributes;
        } elseif ([] !== $block->attributes) {
            $encoded = json_encode(
                $block->attributes,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
            $attrsSegment = ' ' . $encoded;
        }

        if ($block->selfClosing) {
            return sprintf('<!-- wp:%s%s /-->', $serializedName, $attrsSegment);
        }

        $inner = '';
        if ([] !== $block->innerContent) {
            $childIndex = 0;
            foreach ($block->innerContent as $chunk) {
                if (null === $chunk) {
                    if (isset($block->innerBlocks[$childIndex])) {
                        $inner .= self::serializeBlock($block->innerBlocks[$childIndex]);
                        ++$childIndex;
                    }
                } else {
                    $inner .= $chunk;
                }
            }
        } else {
            $inner = $block->innerHtml;
            foreach ($block->innerBlocks as $child) {
                $inner .= self::serializeBlock($child);
            }
        }

        return sprintf('<!-- wp:%s%s -->%s<!-- /wp:%s -->', $serializedName, $attrsSegment, $inner, $serializedName);
    }
}
