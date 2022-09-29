<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\Filesystem;

/**
 * A facade for \Lkrms\Utility\Filesystem
 *
 * @method static Filesystem load() Load and return an instance of the underlying Filesystem class
 * @method static Filesystem getInstance() Return the underlying Filesystem instance
 * @method static bool isLoaded() Return true if an underlying Filesystem instance has been loaded
 * @method static void unload() Clear the underlying Filesystem instance
 * @method static string|false getEol(string $filename) Get a file's end-of-line sequence (see {@see Filesystem::getEol()})
 * @method static string getStablePath(string $suffix = '.log', ?string $dir = null) Return the name of a file unique to the current script and user (see {@see Filesystem::getStablePath()})
 * @method static string|null getStreamUri(resource $stream) Get the URI or filename associated with a stream (see {@see Filesystem::getStreamUri()})
 * @method static bool maybeCreate(string $filename, int $permissions = 511, int $dirPermissions = 511) Create a file if it doesn't exist (see {@see Filesystem::maybeCreate()})
 * @method static bool maybeCreateDirectory(string $filename, int $permissions = 511) Create a directory if it doesn't exist (see {@see Filesystem::maybeCreateDirectory()})
 * @method static string|false|void writeCsv(array $data, ?string $filename = null, bool $headerRow = true, string $nullValue = null) Convert an array to CSV (see {@see Filesystem::writeCsv()})
 *
 * @uses Filesystem
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Utility\Filesystem' --generate='Lkrms\Facade\File'
 */
final class File extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return Filesystem::class;
    }
}
