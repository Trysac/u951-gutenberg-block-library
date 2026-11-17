<?php

declare(strict_types=1);

namespace Trysac\GutenbergBlockLibrary;

/**
 * HTML escaping and URL validation helpers.
 */
final class Html
{
    /**
     * Escapes a string for safe inclusion in HTML text content.
     */
    public static function text(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /**
     * Escapes a string for safe inclusion inside an HTML attribute value.
     */
    public static function attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /**
     * Validates that a URL uses an allowed scheme (http, https, mailto) or is a relative path starting with / or #.
     *
     * @throws BlockException with errorCode 'unsafe_url' when the URL is empty or uses a disallowed scheme
     */
    public static function validateUrl(string $url): string
    {
        $cleaned = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $url);
        $trimmed = trim($cleaned);
        if ('' === $trimmed) {
            throw new BlockException('unsafe_url', 'URL must not be empty.');
        }

        if (str_starts_with($trimmed, '/') || str_starts_with($trimmed, '#')) {
            if (str_starts_with($trimmed, '//')) {
                throw new BlockException('unsafe_url', 'Protocol-relative URLs are not allowed.');
            }

            return trim($url);
        }

        $lower = strtolower($trimmed);
        if (
            str_starts_with($lower, 'http://')
            || str_starts_with($lower, 'https://')
            || str_starts_with($lower, 'mailto:')
        ) {
            return trim($url);
        }

        throw new BlockException('unsafe_url', 'URL uses an unsupported or unsafe scheme.');
    }
}
