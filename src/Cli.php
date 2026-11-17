<?php

declare(strict_types=1);

namespace Trysac\GutenbergBlockLibrary;

/**
 * Command-line interface for rendering Gutenberg block markup files to HTML.
 */
final class Cli
{
    /**
     * Runs the CLI command.
     *
     * Usage: render <file>
     * Exit codes: 0 on success, 1 on BlockException, 2 on usage or file I/O error.
     *
     * @param list<string> $argv command-line arguments including script name at index 0
     * @param resource $stdout stream to write rendered HTML to
     * @param resource $stderr stream to write error messages to
     */
    public static function run(array $argv, $stdout, $stderr): int
    {
        if (count($argv) !== 3 || 'render' !== $argv[1]) {
            fwrite($stderr, "Usage: gutenberg-block-library render <file>\n");

            return 2;
        }

        $path = $argv[2];
        if ('' === $path || !is_file($path) || !is_readable($path)) {
            fwrite($stderr, sprintf("Error: Cannot read file '%s'.\n", $path));

            return 2;
        }

        $content = file_get_contents($path);
        if (false === $content) {
            fwrite($stderr, sprintf("Error: Failed to read file '%s'.\n", $path));

            return 2;
        }

        try {
            $renderer = new Renderer();
            $html = $renderer->renderAll($content);
            fwrite($stdout, $html);

            return 0;
        } catch (BlockException $e) {
            fwrite($stderr, sprintf("[%s] %s\n", $e->errorCode, $e->getMessage()));

            return 1;
        }
    }
}
