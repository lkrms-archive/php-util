<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Facade\Console;
use Lkrms\Utility\Convert;
use Lkrms\Utility\File;
use LogicException;
use RuntimeException;
use SQLite3;

/**
 * Get information about the runtime environment
 */
final class System
{
    /**
     * Type => name => start count
     *
     * @var array<string,array<string,int>>
     */
    private $TimerRuns = [];

    /**
     * Type => name => start nanoseconds
     *
     * @var array<string,array<string,int|float>>
     */
    private $RunningTimers = [];

    /**
     * Type => name => elapsed nanoseconds
     *
     * @var array<string,array<string,int|float>>
     */
    private $ElapsedTime = [];

    /**
     * @var array<array{array<string,array<string,int>>,array<string,array<string,int|float>>,array<string,array<string,int|float>>}>
     */
    private $TimerStack = [];

    /**
     * Get the configured memory_limit, in bytes
     */
    public static function getMemoryLimit(): int
    {
        return Convert::sizeToBytes(ini_get('memory_limit') ?: '0');
    }

    /**
     * Get the current memory usage of the script, in bytes
     */
    public static function getMemoryUsage(): int
    {
        return memory_get_usage();
    }

    /**
     * Get the peak memory usage of the script, in bytes
     */
    public static function getPeakMemoryUsage(): int
    {
        return memory_get_peak_usage();
    }

    /**
     * Get the current memory usage of the script as a percentage of the
     * memory_limit
     */
    public static function getMemoryUsagePercent(): int
    {
        $limit = self::getMemoryLimit();

        return $limit <= 0
            ? 0
            : (int) round(memory_get_usage() * 100 / $limit);
    }

    /**
     * Get user and system CPU times for the current run, in microseconds
     *
     * @return array{int,int} User CPU time is at index 0 and is followed by
     * system CPU time.
     */
    public static function getCpuUsage(): array
    {
        $usage = getrusage();

        return
            $usage === false
                ? [0, 0]
                : [
                    ($usage['ru_utime.tv_sec'] ?? 0) * 1000000
                        + ($usage['ru_utime.tv_usec'] ?? 0),
                    ($usage['ru_stime.tv_sec'] ?? 0) * 1000000
                        + ($usage['ru_stime.tv_usec'] ?? 0),
                ];
    }

    /**
     * Start a timer using the system's high-resolution time
     *
     * @api
     */
    public function startTimer(string $name, string $type = 'general'): void
    {
        $now = hrtime(true);
        if (array_key_exists($name, $this->RunningTimers[$type] ?? [])) {
            throw new LogicException(sprintf('Timer already running: %s', $name));
        }
        $this->RunningTimers[$type][$name] = $now;
        $this->TimerRuns[$type][$name] = ($this->TimerRuns[$type][$name] ?? 0) + 1;
    }

    /**
     * Stop a timer and return the elapsed milliseconds
     *
     * Elapsed time is also added to the totals returned by
     * {@see System::getTimers()}.
     *
     * @api
     */
    public function stopTimer(string $name, string $type = 'general'): float
    {
        $now = hrtime(true);
        if (!array_key_exists($name, $this->RunningTimers[$type] ?? [])) {
            throw new LogicException(sprintf('Timer not running: %s', $name));
        }
        $elapsed = $now - $this->RunningTimers[$type][$name];
        unset($this->RunningTimers[$type][$name]);
        $this->ElapsedTime[$type][$name] = ($this->ElapsedTime[$type][$name] ?? 0) + $elapsed;

        return $elapsed / 1000000;
    }

    /**
     * Push timer state onto the stack
     *
     * @api
     */
    public function pushTimers(): void
    {
        $this->TimerStack[] =
            [$this->TimerRuns, $this->RunningTimers, $this->ElapsedTime];
    }

    /**
     * Pop timer state off the stack
     *
     * @api
     */
    public function popTimers(): void
    {
        $timers = array_pop($this->TimerStack);
        if (!$timers) {
            throw new LogicException('No timer state to pop off the stack');
        }

        [$this->TimerRuns, $this->RunningTimers, $this->ElapsedTime] =
            $timers;
    }

    /**
     * Get elapsed milliseconds and start counts for timers started in the
     * current run
     *
     * @api
     *
     * @param string[]|string|null $types If `null` or `["*"]`, all timers are
     * returned, otherwise only timers of the given types are returned.
     * @return array<string,array<string,array{float,int}>> An array that maps
     * timer types to `<timer_name> => [ <elapsed_ms>, <start_count> ]` arrays.
     */
    public function getTimers(bool $includeRunning = true, $types = null): array
    {
        if ($types === null || $types === ['*']) {
            $timerRuns = $this->TimerRuns;
        } else {
            $timerRuns = array_intersect_key($this->TimerRuns, array_flip((array) $types));
        }

        foreach ($timerRuns as $type => $runs) {
            foreach ($runs as $name => $count) {
                $elapsed = $this->ElapsedTime[$type][$name] ?? 0;
                if ($includeRunning &&
                        array_key_exists($name, $this->RunningTimers[$type] ?? [])) {
                    $elapsed += ($now ?? ($now = hrtime(true))) - $this->RunningTimers[$type][$name];
                }
                if (!$elapsed) {
                    continue;
                }
                $timers[$type][$name] = [$elapsed / 1000000, $count];
            }
        }

        return $timers ?? [];
    }

    /**
     * Get the filename used to run the script
     *
     * To get the running script's canonical path relative to the application,
     * set `$basePath` to the application's root directory.
     *
     * @throws RuntimeException if the filename used to run the script doesn't
     * belong to `$basePath`.
     */
    public static function getProgramName(?string $basePath = null): string
    {
        $filename = $_SERVER['SCRIPT_FILENAME'];

        if ($basePath === null) {
            return $filename;
        }

        if (($basePath = File::realpath($basePath)) !== false &&
                ($filename = File::realpath($filename)) !== false &&
                strpos($filename, $basePath . DIRECTORY_SEPARATOR) === 0) {
            return substr($filename, strlen($basePath) + 1);
        }

        throw new RuntimeException('SCRIPT_FILENAME is not in $basePath');
    }

    /**
     * Get the basename of the file used to run the script
     *
     * @param string ...$suffixes Removed from the end of the filename.
     */
    public static function getProgramBasename(string ...$suffixes): string
    {
        $basename = basename($_SERVER['SCRIPT_FILENAME']);
        if (!$suffixes) {
            return $basename;
        }
        $regex = implode('|', array_map(fn(string $s) => preg_quote($s, '/'), $suffixes));

        return preg_replace("/(?<=.)({$regex})+\$/", '', $basename);
    }

    /**
     * Get a command string with arguments escaped for this platform's shell
     *
     * Don't use this method to prepare commands for `proc_open()`. Its quoting
     * behaviour on Windows is unstable.
     *
     * @param string[] $args
     */
    public static function escapeCommand(array $args): string
    {
        $command = '';

        if (PHP_OS_FAMILY !== 'Windows') {
            foreach ($args as $arg) {
                $command .= ($command ? ' ' : '') . Convert::toShellArg($arg);
            }

            return $command;
        }

        foreach ($args as $arg) {
            $command .= ($command ? ' ' : '') . Convert::toCmdArg($arg);
        }

        return $command;
    }

    /**
     * Get the current working directory without resolving symbolic links
     */
    public static function getCwd(): string
    {
        $handle = popen(PHP_OS_FAMILY === 'Windows' ? 'cd' : 'pwd', 'rb');
        $dir = stream_get_contents($handle);
        $status = pclose($handle);

        if (!$status) {
            if (substr($dir, -strlen(PHP_EOL)) === PHP_EOL) {
                $dir = substr($dir, 0, -strlen(PHP_EOL));
            }
            return $dir;
        }

        $dir = getcwd();
        if ($dir === false) {
            throw new RuntimeException('Unable to get current working directory');
        }
        return $dir;
    }

    /**
     * True if the SQLite3 library supports UPSERT syntax
     *
     * @link https://www.sqlite.org/lang_UPSERT.html
     */
    public static function sqliteHasUpsert(): bool
    {
        return SQLite3::version()['versionNumber'] >= 3024000;
    }

    /**
     * Handle SIGINT and SIGTERM to make a clean exit from the running script
     *
     * If `posix_getpgid()` is available, `SIGINT` is propagated to the current
     * process group just before PHP exits.
     *
     * @return bool `false` if signal handlers can't be installed on this
     * platform, otherwise `true`.
     */
    public static function handleExitSignals(): bool
    {
        if (!function_exists('pcntl_async_signals')) {
            return false;
        }

        $handler =
            function (int $signal): void {
                Console::debug(sprintf('Received signal %d', $signal));
                if ($signal === SIGINT &&
                        function_exists('posix_getpgid') &&
                        ($pgid = posix_getpgid(posix_getpid())) !== false) {
                    // Stop handling SIGINT before propagating it
                    pcntl_signal(SIGINT, SIG_DFL);
                    register_shutdown_function(
                        function () use ($pgid) {
                            Console::debug(sprintf('Sending SIGINT to process group %d', $pgid));
                            posix_kill($pgid, SIGINT);
                        }
                    );
                }
                exit (64 + $signal);
            };

        pcntl_async_signals(true);
        pcntl_signal(SIGINT, $handler);
        pcntl_signal(SIGTERM, $handler);
        return true;
    }
}
