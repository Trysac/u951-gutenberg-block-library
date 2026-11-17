<?php

declare(strict_types=1);

namespace Trysac\GutenbergBlockLibrary;

/**
 * Exception thrown when text normalization receives blank input.
 */
final class BlankInputException extends \InvalidArgumentException
{
    /**
     * Creates a new BlankInputException.
     */
    public function __construct(string $message = 'input is blank', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
