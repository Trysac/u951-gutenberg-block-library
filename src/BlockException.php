<?php

declare(strict_types=1);

namespace Trysac\GutenbergBlockLibrary;

/**
 * Exception thrown when block parsing, validation, registry lookup, or rendering fails.
 */
final class BlockException extends \InvalidArgumentException
{
    /**
     * Creates a new BlockException with a stable machine-readable error code.
     *
     * @param string $errorCode stable error code
     * @param string $message human-readable error message (truncated if needed)
     * @param ?\Throwable $previous optional underlying cause
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(self::truncate($message), 0, $previous);
    }

    /**
     * Truncates potentially untrusted excerpts so messages never exceed 200 characters.
     */
    private static function truncate(string $text): string
    {
        if (strlen($text) <= 200) {
            return $text;
        }

        return substr($text, 0, 197) . '...';
    }
}
