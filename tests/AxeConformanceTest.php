<?php

declare(strict_types=1);

namespace Trysac\GutenbergBlockLibrary\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Trysac\GutenbergBlockLibrary\Renderer;

/**
 * Conformance test suite verifying rendered block fixtures against axe-core 4.14.0 in jsdom 30.1.3.
 *
 * @internal
 */
#[Group('conformance')]
final class AxeConformanceTest extends TestCase
{
    public function testEveryCommittedFixturePassesAxeCoreWithZeroViolations(): void
    {
        $driverPath = dirname(__DIR__) . '/.conformance/axe-driver.mjs';
        $nodeCheck = @shell_exec('command -v node 2>/dev/null');
        if (!is_string($nodeCheck) || '' === trim($nodeCheck) || !is_file($driverPath)) {
            self::markTestSkipped('Node.js or .conformance/axe-driver.mjs is not installed (run ci/conformance-setup.sh).');
        }

        $fixtureFiles = glob(__DIR__ . '/fixtures/*.html');
        self::assertIsArray($fixtureFiles);
        self::assertNotEmpty($fixtureFiles);
        sort($fixtureFiles);

        $renderer = new Renderer();
        $fragments = [];
        foreach ($fixtureFiles as $file) {
            $raw = file_get_contents($file);
            self::assertIsString($raw);
            $fragments[] = $renderer->renderAll($raw);
        }

        $fullPage = "<!DOCTYPE html>\n<html lang=\"en\">\n<head><meta charset=\"utf-8\"><title>Block Conformance</title></head>\n<body>\n<main>\n"
            . implode("\n", $fragments)
            . "\n</main>\n</body>\n</html>\n";

        $tempHtml = tempnam(sys_get_temp_dir(), 'trysac-axe-') . '.html';
        file_put_contents($tempHtml, $fullPage);

        try {
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = proc_open(['node', $driverPath, $tempHtml], $descriptors, $pipes, dirname(__DIR__));
            self::assertIsResource($process);
            self::assertIsArray($pipes);
            self::assertArrayHasKey(0, $pipes);
            self::assertArrayHasKey(1, $pipes);
            self::assertArrayHasKey(2, $pipes);

            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            self::assertSame(0, $exitCode, sprintf("axe-driver failed (exit %d): %s", $exitCode, (string) $stderr));
            self::assertIsString($stdout);

            /** @var array{violations: list<mixed>} $report */
            $report = json_decode($stdout, true, 32, JSON_THROW_ON_ERROR);
            self::assertSame([], $report['violations']);
        } finally {
            @unlink($tempHtml);
        }
    }
}
