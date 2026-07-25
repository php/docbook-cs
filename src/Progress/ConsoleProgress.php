<?php

declare(strict_types=1);

namespace DocbookCS\Progress;

final class ConsoleProgress implements ProgressInterface
{
    private const int BAR_WIDTH = 30;

    /** @var resource */
    private $stream;

    private bool $useColors;

    private int $totalFiles = 0;

    private int $processedFiles = 0;

    /**
     * @param resource $stream
     */
    public function __construct($stream, bool $useColors = true)
    {
        $this->stream = $stream;
        $this->useColors = $useColors;
    }

    public function start(int $totalFiles): void
    {
        $this->totalFiles = $totalFiles;
        $this->processedFiles = 0;

        if ($totalFiles === 0) {
            $this->write($this->dim('No files to scan.') . PHP_EOL);

            return;
        }

        $suffix = $totalFiles === 1 ? 'file' : 'files';
        $formattedTotal = number_format($totalFiles);

        $message = sprintf('Scanning %s %s...', $formattedTotal, $suffix);

        $this->write($this->dim($message) . PHP_EOL);
        $this->drawBar(0, '');
    }

    public function advance(string $filePath, int $violations): void
    {
        if ($this->totalFiles === 0) {
            return;
        }

        $this->drawBar(++$this->processedFiles, $filePath, $violations);
    }

    public function finish(): void
    {
        if ($this->totalFiles === 0) {
            return;
        }

        $this->drawBar($this->totalFiles, 'Done.');
        $this->write(PHP_EOL);
    }

    private function drawBar(int $current, string $filePath, int $violations = 0): void
    {
        $percent = $this->totalFiles > 0
            ? (int)floor(($current / $this->totalFiles) * 100)
            : 0; // @codeCoverageIgnore

        $filled = $this->totalFiles > 0
            ? (int)floor(($current / $this->totalFiles) * self::BAR_WIDTH)
            : 0; // @codeCoverageIgnore

        $empty = self::BAR_WIDTH - $filled;

        $bar = str_repeat('=', max(0, $filled - 1))
            . ($filled > 0 ? '>' : '')
            . str_repeat(' ', $empty);

        // If we completed everything, fill the whole bar.
        if ($current >= $this->totalFiles) {
            $bar = str_repeat('=', self::BAR_WIDTH);
        }

        $counter = sprintf('(%d/%d)', $current, $this->totalFiles);

        // Truncate the file path so the line fits in \~120 columns.
        $displayPath = $this->truncatePath($filePath, 40);

        // Show a dot indicator for violations on this file.
        $marker = '';
        if ($current > 0 && $current < $this->totalFiles) {
            $marker = $violations > 0
                ? ' ' . $this->red('x')
                : ' ' . $this->green('.');
        }

        $line = sprintf(
            "\r  [%s] %3d%% %s%s %s",
            $this->colorizeBar($bar),
            $percent,
            $this->dim($counter),
            $marker,
            $this->dim($displayPath),
        );

        $this->write($line . str_repeat(' ', 10));
    }

    private function truncatePath(string $path, int $maxLen): string
    {
        if ($path === '' || strlen($path) <= $maxLen) {
            return $path;
        }

        return '...' . substr($path, -(max(3, $maxLen - 3)));
    }

    private function colorizeBar(string $bar): string
    {
        if (!$this->useColors) {
            return $bar;
        }

        $filled = rtrim($bar);
        $empty = substr($bar, strlen($filled));

        return $this->green($filled) . $this->dim($empty);
    }

    private function green(string $text): string
    {
        return $this->wrap($text, '32');
    }

    private function red(string $text): string
    {
        return $this->wrap($text, '31');
    }

    private function dim(string $text): string
    {
        return $this->wrap($text, '2');
    }

    private function wrap(string $text, string $code): string
    {
        if (!$this->useColors || $text === '') {
            return $text;
        }

        return "\033[{$code}m{$text}\033[0m";
    }

    private function write(string $text): void
    {
        fwrite($this->stream, $text);
    }
}
