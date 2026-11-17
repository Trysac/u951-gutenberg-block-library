<?php

declare(strict_types=1);

namespace Trysac\GutenbergBlockLibrary;

/**
 * Parses WordPress Gutenberg block serialization grammar into structured ParsedBlock trees.
 */
final class BlockParser
{
    /**
     * Default maximum input length in bytes (1 MiB).
     */
    public const int DEFAULT_MAX_BYTES = 1_048_576;

    /**
     * Default maximum block nesting depth.
     */
    public const int DEFAULT_MAX_DEPTH = 32;

    /**
     * Parses serialized block content into a list of blocks.
     *
     * @param string $content serialized post_content containing Gutenberg block comments
     * @param int $maxBytes maximum allowed byte length of $content
     * @param int $maxDepth maximum allowed nesting depth
     *
     * @return list<ParsedBlock>
     *
     * @throws BlockException when limits are exceeded or the block grammar is malformed
     */
    public static function parse(
        string $content,
        int $maxBytes = self::DEFAULT_MAX_BYTES,
        int $maxDepth = self::DEFAULT_MAX_DEPTH,
    ): array {
        if (strlen($content) > $maxBytes) {
            throw new BlockException('input_too_large', sprintf('Input exceeds maximum size of %d bytes.', $maxBytes));
        }

        if ('' === $content) {
            return [];
        }

        $tokens = self::tokenize($content);
        if ([] === $tokens) {
            return [
                new ParsedBlock(
                    name: '',
                    attributes: [],
                    innerHtml: $content,
                    innerBlocks: [],
                    innerContent: [$content],
                ),
            ];
        }

        /** @var list<ParseFrame> $stack */
        $stack = [];
        /** @var list<ParsedBlock> $rootBlocks */
        $rootBlocks = [];
        $cursor = 0;

        foreach ($tokens as $token) {
            if ($token['offset'] > $cursor) {
                $htmlChunk = substr($content, $cursor, $token['offset'] - $cursor);
                self::appendHtml($stack, $rootBlocks, $htmlChunk);
            }

            $cursor = $token['offset'] + $token['length'];

            if ('closer' === $token['type']) {
                $topFrame = [] !== $stack ? $stack[count($stack) - 1] : null;
                if (null === $topFrame || $topFrame->name !== $token['name']) {
                    throw new BlockException(
                        'unclosed_block',
                        sprintf('Unexpected closing tag for block "%s".', $token['rawName']),
                    );
                }

                $frame = array_pop($stack);
                if (null === $frame) {
                    throw new BlockException(
                        'unclosed_block',
                        sprintf('Unexpected closing tag for block "%s".', $token['rawName']),
                    );
                }
                $completed = new ParsedBlock(
                    name: $frame->name,
                    attributes: $frame->attributes,
                    innerHtml: $frame->innerHtml,
                    innerBlocks: $frame->innerBlocks,
                    innerContent: $frame->innerContent,
                    rawName: $frame->rawName,
                    rawAttributes: $frame->rawAttributes,
                    selfClosing: false,
                );
                self::appendBlock($stack, $rootBlocks, $completed);
                continue;
            }

            $nextDepth = count($stack) + 1;
            if ($nextDepth > $maxDepth) {
                throw new BlockException(
                    'too_deep',
                    sprintf('Block nesting exceeds maximum depth of %d.', $maxDepth),
                );
            }

            $attributes = self::parseAttributes($token['rawAttributes']);

            if ('void' === $token['type']) {
                $voidBlock = new ParsedBlock(
                    name: $token['name'],
                    attributes: $attributes,
                    innerHtml: '',
                    innerBlocks: [],
                    innerContent: [],
                    rawName: $token['rawName'],
                    rawAttributes: $token['rawAttributes'],
                    selfClosing: true,
                );
                self::appendBlock($stack, $rootBlocks, $voidBlock);
                continue;
            }

            $stack[] = new ParseFrame(
                rawName: $token['rawName'],
                name: $token['name'],
                attributes: $attributes,
                rawAttributes: $token['rawAttributes'],
            );
        }

        if ([] !== $stack) {
            $unclosed = $stack[count($stack) - 1];
            throw new BlockException(
                'unclosed_block',
                sprintf('Unclosed block "%s".', $unclosed->rawName),
            );
        }

        if ($cursor < strlen($content)) {
            $trailing = substr($content, $cursor);
            self::appendHtml($stack, $rootBlocks, $trailing);
        }

        return $rootBlocks;
    }

    /**
     * Scans the input string for valid or malformed block comment tokens.
     *
     * @return list<array{type: 'opener'|'closer'|'void', rawName: string, name: string, rawAttributes: string, offset: int, length: int}>
     */
    private static function tokenize(string $content): array
    {
        $tokens = [];
        $offset = 0;
        $len = strlen($content);

        while ($offset < $len) {
            $start = strpos($content, '<!--', $offset);
            if (false === $start) {
                break;
            }

            $end = strpos($content, '-->', $start + 4);
            if (false === $end) {
                $after = ltrim(substr($content, $start + 4));
                if (str_starts_with($after, 'wp:') || str_starts_with($after, '/wp:')) {
                    throw new BlockException('unclosed_block', 'Unclosed block comment delimiter.');
                }
                break;
            }

            $commentLength = ($end + 3) - $start;
            $comment = substr($content, $start, $commentLength);

            if (1 === preg_match('/^<!--\s+(\/)?wp:([a-zA-Z0-9_\/-]+)(.*?)-->\z/s', $comment, $prelim)) {
                $isCloser = '/' === $prelim[1];
                $rawName = $prelim[2];
                $rest = $prelim[3];

                if (1 !== preg_match('/^(?:[a-z][a-z0-9_-]*\/)?[a-z][a-z0-9_-]*\z/', $rawName)) {
                    throw new BlockException('invalid_attributes', sprintf('Invalid block name "%s".', $rawName));
                }

                $normalizedName = str_contains($rawName, '/') ? $rawName : 'core/' . $rawName;

                if ($isCloser) {
                    if ('' !== trim($rest)) {
                        throw new BlockException('invalid_attributes', 'Closing block comment cannot contain attributes.');
                    }

                    $tokens[] = [
                        'type' => 'closer',
                        'rawName' => $rawName,
                        'name' => $normalizedName,
                        'rawAttributes' => '',
                        'offset' => $start,
                        'length' => $commentLength,
                    ];
                    $offset = $end + 3;
                    continue;
                }

                if (!str_starts_with($rest, ' ') && !str_starts_with($rest, "\t") && !str_starts_with($rest, "\n") && !str_starts_with($rest, "\r")) {
                    $offset = $end + 3;
                    continue;
                }

                $trimmedRest = trim($rest);
                $isVoid = false;
                if (str_ends_with($trimmedRest, '/')) {
                    $isVoid = true;
                    $trimmedRest = rtrim(substr($trimmedRest, 0, -1));
                }

                if ('' !== $trimmedRest) {
                    if (!str_starts_with($trimmedRest, '{') || !str_ends_with($trimmedRest, '}')) {
                        throw new BlockException('invalid_attributes', 'Block attributes must be a JSON object.');
                    }
                }

                $tokens[] = [
                    'type' => $isVoid ? 'void' : 'opener',
                    'rawName' => $rawName,
                    'name' => $normalizedName,
                    'rawAttributes' => $trimmedRest,
                    'offset' => $start,
                    'length' => $commentLength,
                ];
            }

            $offset = $end + 3;
        }

        return $tokens;
    }

    /**
     * Decodes and validates a JSON object of block attributes.
     *
     * @return array<string, mixed>
     */
    private static function parseAttributes(string $rawAttributes): array
    {
        if ('' === $rawAttributes) {
            return [];
        }

        try {
            $decoded = json_decode($rawAttributes, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new BlockException('invalid_attributes', 'Malformed JSON in block attributes.', $e);
        }

        if (!is_array($decoded) || array_is_list($decoded) && [] !== $decoded) {
            throw new BlockException('invalid_attributes', 'Block attributes JSON must decode to an object.');
        }

        /** @var array<string, mixed> $result */
        $result = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($key)) {
                throw new BlockException('invalid_attributes', 'Attribute keys must be strings.');
            }
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Appends raw HTML either to the active block on the stack or as a root freeform block.
     *
     * @param list<ParseFrame> $stack
     * @param list<ParsedBlock> $rootBlocks
     */
    private static function appendHtml(array $stack, array &$rootBlocks, string $html): void
    {
        if ([] !== $stack) {
            $top = $stack[count($stack) - 1];
            $top->innerHtml .= $html;
            $top->innerContent[] = $html;

            return;
        }

        $rootBlocks[] = new ParsedBlock(
            name: '',
            attributes: [],
            innerHtml: $html,
            innerBlocks: [],
            innerContent: [$html],
        );
    }

    /**
     * Appends a finished block either to its parent on the stack or to the root block list.
     *
     * @param list<ParseFrame> $stack
     * @param list<ParsedBlock> $rootBlocks
     */
    private static function appendBlock(array $stack, array &$rootBlocks, ParsedBlock $block): void
    {
        if ([] !== $stack) {
            $top = $stack[count($stack) - 1];
            $top->innerBlocks[] = $block;
            $top->innerContent[] = null;

            return;
        }

        $rootBlocks[] = $block;
    }
}
