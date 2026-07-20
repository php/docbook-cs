<?php

declare(strict_types=1);

namespace DocbookCS;

use DocbookCS\Config\ConfigData;
use DocbookCS\Config\ConfigParser;
use DocbookCS\Config\ConfigParserException;
use DocbookCS\Progress\ConsoleProgress;
use DocbookCS\Progress\NullProgress;
use DocbookCS\Progress\ProgressInterface;
use DocbookCS\Report\Reporter\CheckstyleReporter;
use DocbookCS\Report\Reporter\ConsoleReporter;
use DocbookCS\Report\Reporter\JsonReporter;
use DocbookCS\Report\Reporter\ReporterInterface;
use DocbookCS\Runner\RunCoordinator;
use DocbookCS\Runner\RunMode;
use DocbookCS\Runner\RunPlanner;

final class Application
{
    private const string VERSION = '0.1.0';

    private const string DEFAULT_CONFIG = 'docbookcs.xml';

    /** @var list<string> */
    private array $argv;

    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    private ?string $stdin;

    /**
     * @param list<string> $argv
     * @throws \RuntimeException if redirected stdin cannot be read.
     * @api
     */
    public static function withArguments(array $argv): self
    {
        $stdin = null;
        $stat = fstat(STDIN);

        if ($stat === false) {
            return new self($argv, unifiedDiff: $stdin);
        }

        $type = $stat['mode'] & 0170000;

        if ($type === 0010000 || $type === 0100000) {
            $stdin = stream_get_contents(STDIN);

            if ($stdin === false) {
                throw new \RuntimeException('Could not read diff from stdin.');
            }
        }

        return new self($argv, unifiedDiff: $stdin);
    }

    /**
     * @param list<string> $argv
     * @param ?resource $stdout
     * @param ?resource $stderr
     */
    public function __construct(
        array $argv,
        mixed $stdout = null,
        mixed $stderr = null,
        ?string $unifiedDiff = null,
    ) {
        $this->argv = $argv;
        $this->stdout = $stdout ?? STDOUT;
        $this->stderr = $stderr ?? STDERR;
        $this->stdin = $unifiedDiff;
    }

    /**
     * @return int Exit code (0 = success, 1 = violations found, 2 = runtime error).
     */
    public function run(): int
    {
        try {
            $options = $this->parseArgv();
        } catch (\InvalidArgumentException $e) {
            $this->writeError('Error: ' . $e->getMessage() . PHP_EOL);

            return 2;
        }

        if ($options['help']) {
            $this->printHelp();

            return 0;
        }

        if ($options['version']) {
            $this->write('DocbookCS version ' . self::VERSION . PHP_EOL);

            return 0;
        }

        try {
            $config = $this->loadConfig($options['config']);
        } catch (\Throwable $e) {
            $this->writeError('Error: ' . $e->getMessage() . PHP_EOL);

            return 2;
        }

        try {
            $runPlan = new RunPlanner(
                config: $config,
                mode: RunMode::fromFixFlag($options['fix']),
                wide: $options['wide'],
            )->plan($options['paths'], $this->stdin);
        } catch (\Throwable $e) {
            $this->writeError('Error resolving input: ' . $e->getMessage() . PHP_EOL);

            return 2;
        }

        $progress = $this->createProgress($options);

        try {
            $report = new RunCoordinator($progress)->run($runPlan);
        } catch (\Throwable $e) {
            $this->writeError('Runtime error: ' . $e->getMessage() . PHP_EOL);

            return 2;
        }

        $reporter = $this->createReporter(
            $options['report'],
            $options['colors'],
            $options['perf']
        );

        $this->write($reporter->generate($report));

        return (int) $report->hasViolations();
    }

    /**
     * @return array{
     *     help: bool,
     *     version: bool,
     *     config: string,
     *     report: string,
     *     colors: bool,
     *     fix: bool,
     *     wide: bool,
     *     quiet: bool,
     *     paths: list<string>,
     *     perf: bool,
     * }
     * @throws \InvalidArgumentException for unsupported or removed options.
     */
    private function parseArgv(): array
    {
        $result = [
            'help' => false,
            'version' => false,
            'config' => self::DEFAULT_CONFIG,
            'report' => 'console',
            'colors' => $this->detectColorSupport(),
            'fix' => false,
            'wide' => false,
            'quiet' => false,
            'paths' => [],
            'perf' => false,
        ];

        $args = array_slice($this->argv, 1); // skip script name
        $i = 0;
        $count = count($args);

        while ($i < $count) {
            $arg = $args[$i];

            if ($arg === '-h' || $arg === '--help') {
                $result['help'] = true;

                return $result;
            }

            if ($arg === '-v' || $arg === '--version') {
                $result['version'] = true;

                return $result;
            }

            if ($arg === '-q' || $arg === '--quiet') {
                $result['quiet'] = true;
                $i++;
                continue;
            }

            if ($arg === '--config' && isset($args[$i + 1])) {
                $result['config'] = $args[++$i];
                $i++;
                continue;
            }

            if (str_starts_with($arg, '--config=')) {
                $result['config'] = substr($arg, 9);
                $i++;
                continue;
            }

            if ($arg === '--report' && isset($args[$i + 1])) {
                $result['report'] = $args[++$i];
                $i++;
                continue;
            }

            if (str_starts_with($arg, '--report=')) {
                $result['report'] = substr($arg, 9);
                $i++;
                continue;
            }

            if ($arg === '--colors') {
                $result['colors'] = true;
                $i++;
                continue;
            }

            if ($arg === '--no-colors') {
                $result['colors'] = false;
                $i++;
                continue;
            }

            if ($arg === '--perf') {
                $result['perf'] = true;
                $i++;
                continue;
            }

            if ($arg === '--fix') {
                $result['fix'] = true;
                $i++;
                continue;
            }

            if ($arg === '--wide') {
                $result['wide'] = true;
                $i++;
                continue;
            }

            // todo: remove; noop - for now kept for CI compatibility
            if ($arg === '--diff') {
                $i++;
                continue;
            }

            // Anything else is a path to scan.
            if (!str_starts_with($arg, '-')) {
                $result['paths'][] = $arg;
                $i++;
                continue;
            }

            throw new \InvalidArgumentException(sprintf('Unknown option: %s', $arg));
        }

        return $result;
    }

    /** @param array{report: string, quiet: bool, colors: bool} $options */
    private function createProgress(array $options): ProgressInterface
    {
        if ($options['quiet']) {
            return new NullProgress();
        }

        if ($options['report'] !== 'console') {
            // Structured output (checkstyle, json) goes to stdout.
            // Progress on stderr would be fine, but many CI runners
            // merge streams, so stay silent unless explicitly console.
            return new NullProgress();
        }

        if (!$this->isInteractive($this->stderr)) {
            return new NullProgress();
        }

        return new ConsoleProgress($this->stderr, $options['colors']); // @codeCoverageIgnore
    }

    /**
     * @throws ConfigParserException if the file cannot be read or contains invalid XML.
     * @throws \InvalidArgumentException if a SniffEntry is constructed with an invalid class name.
     */
    private function loadConfig(string $configPath): ConfigData
    {
        $parser = new ConfigParser();

        // If the path is relative, resolve against cwd.
        if (!str_starts_with($configPath, '/') && !preg_match('#^[a-zA-Z]:[/\\\\]#', $configPath)) {
            $configPath = (getcwd() ?: '.') . '/' . $configPath;
        }

        return $parser->parseFile($configPath);
    }

    private function createReporter(string $format, bool $colors, bool $perf): ReporterInterface
    {
        return match ($format) {
            'checkstyle' => new CheckstyleReporter(),
            'json' => new JsonReporter(),
            default => new ConsoleReporter($colors, $perf),
        };
    }

    // @codeCoverageIgnoreStart
    private function detectColorSupport(): bool
    {
        if (!is_resource($this->stdout)) {
            return false;
        }

        if (!function_exists('posix_isatty')) {
            $term = getenv('TERM');
            return $term !== false && $term !== 'dumb';
        }

        if (get_resource_type($this->stdout) !== 'stream') {
            return false;
        }

        // Suppress warnings in case the stream is not a TTY (e.g. when piped).
        return @posix_isatty($this->stdout);
    }
    // @codeCoverageIgnoreEnd

    // @codeCoverageIgnoreStart
    /** @param resource $stream */
    private function isInteractive($stream): bool
    {
        if (!is_resource($stream)) {
            return false;
        }

        if (!function_exists('posix_isatty')) {
            return false;
        }

        if (get_resource_type($stream) !== 'stream') {
            return false;
        }

        // Suppress warnings in case the stream is not a TTY (e.g. when piped).
        return @posix_isatty($stream);
    }
    // @codeCoverageIgnoreEnd

    private function printHelp(): void
    {
        $help = <<<'HELP'
DocbookCS - DocBook Code Sniffer

Usage:
  docbook-cs [options] [<file-or-directory> ...]

Options:
  -h, --help            Show this help message and exit.
  -v, --version         Show version information and exit.
  -q, --quiet           Suppress progress output.
  --config=<file>       Path to configuration file (default: docbookcs.xml).
  --report=<format>     Output format: console (default), checkstyle, json.
  --colors              Force ANSI color output.
  --no-colors           Disable ANSI color output.
  --fix                 Automatically fix violations when fixers exist
                        (experimental).
  --wide                Check whole selected files and recursively include
                        referenced XML files.

Arguments:
  <file-or-directory>   One or more files or directories to scan.
                        Paths cannot be combined with diff input.

Examples:
  docbook-cs
  docbook-cs --config=myconfig.xml reference/
  docbook-cs --report=checkstyle --no-colors > report.xml
  docbook-cs . --fix
  docbook-cs reference/
  docbook-cs reference/strings/functions/strlen.xml
  docbook-cs reference/strings/functions/strlen.xml  --wide
  docbook-cs reference/strings/functions/strlen.xml  --wide --fix
  git diff HEAD | docbook-cs
  git diff HEAD | docbook-cs --wide
  git diff HEAD | docbook-cs --wide --fix --report=checkstyle

HELP;

        $this->write($help);
    }

    private function write(string $text): void
    {
        fwrite($this->stdout, $text);
    }

    private function writeError(string $text): void
    {
        fwrite($this->stderr, $text);
    }
}
