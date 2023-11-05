<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\System;

/**
 * A facade for \Lkrms\Utility\System
 *
 * @method static System load() Load and return an instance of the underlying System class
 * @method static System getInstance() Get the underlying System instance
 * @method static bool isLoaded() True if an underlying System instance has been loaded
 * @method static void unload() Clear the underlying System instance
 * @method static string escapeCommand(string[] $args) Get a command string with arguments escaped for this platform's shell (see {@see System::escapeCommand()})
 * @method static array{int,int} getCpuUsage() Get user and system CPU times for the current run, in microseconds (see {@see System::getCpuUsage()})
 * @method static string getCwd() Get the current working directory without resolving symbolic links
 * @method static int getMemoryLimit() Get the configured memory_limit, in bytes
 * @method static int getMemoryUsage() Get the current memory usage of the script, in bytes
 * @method static int getMemoryUsagePercent() Get the current memory usage of the script as a percentage of the memory_limit
 * @method static int getPeakMemoryUsage() Get the peak memory usage of the script, in bytes
 * @method static string getProgramBasename(string ...$suffixes) Get the basename of the file used to run the script (see {@see System::getProgramBasename()})
 * @method static string getProgramName(?string $basePath = null) Get the filename used to run the script (see {@see System::getProgramName()})
 * @method static array<string,array<string,array{float,int}>> getTimers(bool $includeRunning = true, string[]|string|null $types = null) Get elapsed milliseconds and start counts for timers started in the current run (see {@see System::getTimers()})
 * @method static bool handleExitSignals() Handle SIGINT and SIGTERM to make a clean exit from the running script (see {@see System::handleExitSignals()})
 * @method static void popTimers() Pop timer state off the stack (see {@see System::popTimers()})
 * @method static void pushTimers() Push timer state onto the stack (see {@see System::pushTimers()})
 * @method static bool sqliteHasUpsert() True if the SQLite3 library supports UPSERT syntax (see {@see System::sqliteHasUpsert()})
 * @method static void startTimer(string $name, string $type = 'general') Start a timer using the system's high-resolution time (see {@see System::startTimer()})
 * @method static float stopTimer(string $name, string $type = 'general') Stop a timer and return the elapsed milliseconds (see {@see System::stopTimer()})
 *
 * @uses System
 *
 * @extends Facade<System>
 */
final class Sys extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getServiceName(): string
    {
        return System::class;
    }
}
