<?php declare(strict_types=1);

namespace Lkrms\Utility;

use CallbackFilterIterator;
use FilesystemIterator;
use Lkrms\Facade\Compute;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\File;
use Lkrms\Facade\Sys;
use Lkrms\Support\Iterator\Contract\FluentIteratorInterface;
use Lkrms\Utility\Test;
use LogicException;
use Phar;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Work with files, directories and paths
 *
 */
final class Filesystem
{
    /**
     * Iterate over files in a directory
     *
     * Exclusions are applied before inclusions.
     *
     * @param string|null $exclude A regular expression that specifies paths to
     * exclude. No files are excluded if `null`.
     *
     * To exclude a directory, provide an expression that matches its name and a
     * subsequent forward slash (`/`).
     *
     * @param string|null $include A regular expression that specifies paths to
     * include. All files are included if `null`.
     * @param array<string,callable> $excludeCallbacks An array that maps
     * regular expressions to callbacks that return `true` for matching files or
     * directories to exclude.
     *
     * To exclude a directory, provide an expression that matches its name and a
     * subsequent forward slash (`/`).
     *
     * @phpstan-param array<string,callable(SplFileInfo): bool> $excludeCallbacks
     * @param array<string,callable> $includeCallbacks An array that maps
     * regular expressions to callbacks that return `true` for matching files to
     * include.
     * ```php
     * [$regex => fn(SplFileInfo $file) => $file->isExecutable()]
     * ```
     * @phpstan-param array<string,callable(SplFileInfo): bool> $includeCallbacks
     * @return FluentIteratorInterface<string,SplFileInfo>
     */
    public function find(
        string $directory,
        ?string $exclude = null,
        ?string $include = null,
        ?array $excludeCallbacks = null,
        ?array $includeCallbacks = null,
        bool $recursive = true,
        bool $withDirectories = false
    ): FluentIteratorInterface {
        $flags = FilesystemIterator::KEY_AS_PATHNAME
            | FilesystemIterator::CURRENT_AS_FILEINFO
            | FilesystemIterator::SKIP_DOTS
            | FilesystemIterator::UNIX_PATHS;
        $mode = $withDirectories
            ? RecursiveIteratorIterator::SELF_FIRST
            : RecursiveIteratorIterator::LEAVES_ONLY;

        if ($exclude || $include || $excludeCallbacks || $includeCallbacks) {
            $callback =
                function (SplFileInfo $current, string $key) use (
                    $exclude, $include, $excludeCallbacks, $includeCallbacks
                ): bool {
                    if ($exclude && preg_match($exclude, $key)) {
                        return false;
                    }
                    if ($excludeCallbacks) {
                        foreach ($excludeCallbacks as $regex => $callback) {
                            if (!preg_match($regex, $key) &&
                                !($current->isDir() &&
                                    preg_match($regex, $key . '/'))) {
                                continue;
                            }
                            if ($callback($current)) {
                                return false;
                            }
                        }
                    }
                    if ($current->isDir()) {
                        return !($exclude && preg_match($exclude, $key . '/'));
                    }
                    if ($include && preg_match($include, $key)) {
                        return true;
                    }
                    if ($includeCallbacks) {
                        foreach ($includeCallbacks as $regex => $callback) {
                            if (!preg_match($regex, $key)) {
                                continue;
                            }
                            if ($callback($current)) {
                                return true;
                            }
                        }
                    }

                    return !$include && !$includeCallbacks;
                };
        }

        if ($recursive) {
            $iterator = new RecursiveDirectoryIterator($directory, $flags);
            if ($callback ?? null) {
                $iterator = new RecursiveCallbackFilterIterator($iterator, $callback);
            }
            $iterator = new RecursiveIteratorIterator($iterator, $mode);
            /** @var FluentIteratorInterface<string,SplFileInfo> */
            $iterator = new \Lkrms\Support\Iterator\FluentIterator($iterator);

            return $iterator;
        }

        $iterator = new FilesystemIterator($directory, $flags);
        if ($callback ?? null) {
            $iterator = new CallbackFilterIterator($iterator, $callback);
        }
        if (!$withDirectories) {
            $iterator = new CallbackFilterIterator($iterator, fn(SplFileInfo $current) => !$current->isDir());
        }
        /** @var FluentIteratorInterface<string,SplFileInfo> */
        $iterator = new \Lkrms\Support\Iterator\FluentIterator($iterator);

        return $iterator;
    }

    /**
     * Get a file's end-of-line sequence
     *
     * @param string $filename
     * @return string|false `"\r\n"` or `"\n"` on success, or `false` if the
     * file's line endings couldn't be determined.
     */
    public function getEol(string $filename)
    {
        if (($f = fopen($filename, 'r')) === false ||
                ($line = fgets($f)) === false ||
                fclose($f) === false) {
            return false;
        }

        foreach (["\r\n", "\n"] as $eol) {
            if (substr($line, -strlen($eol)) == $eol) {
                return $eol;
            }
        }

        return false;
    }

    /**
     * True if a file appears to contain PHP code
     *
     * @param string $filename
     */
    public function isPhp(string $filename): bool
    {
        if (!($f = fopen($filename, 'r'))) {
            return false;
        }
        try {
            if (($line = fgets($f)) && substr($line, 0, 2) === '#!') {
                $line = fgets($f);
            }

            return $line && preg_match('/^<\?(php\s|(?!php))/', $line);
        } finally {
            fclose($f);
        }
    }

    /**
     * Create a file if it doesn't exist
     *
     * @param string $filename Full path to the file.
     * @param int $permissions Only used if `$filename` needs to be created.
     * @param int $dirPermissions Only used if `$filename`'s parent directory
     * needs to be created.
     * @return bool `true` on success or `false` on failure.
     */
    public function maybeCreate(string $filename, int $permissions = 0777, int $dirPermissions = 0777): bool
    {
        $dir = dirname($filename);

        if ((is_dir($dir) || mkdir($dir, $dirPermissions, true)) &&
                (is_file($filename) || (touch($filename) && chmod($filename, $permissions)))) {
            return true;
        }

        return false;
    }

    /**
     * Create a directory if it doesn't exist
     *
     * @param string $filename Full path to the directory.
     * @param int $permissions Only used if `$filename` needs to be created.
     * @return bool `true` on success or `false` on failure.
     */
    public function maybeCreateDirectory(string $filename, int $permissions = 0777): bool
    {
        if (is_dir($filename) || mkdir($filename, $permissions, true)) {
            return true;
        }

        return false;
    }

    /**
     * Delete a file if it exists
     *
     * @return bool `true` on success or `false` on failure.
     */
    public function maybeDelete(string $filename): bool
    {
        if (!is_file($filename)) {
            return true;
        }

        return unlink($filename);
    }

    /**
     * A Phar-friendly, file descriptor-aware realpath()
     *
     * 1. If `$filename` is a file descriptor in `/dev/fd` or `/proc`,
     *    `php://fd/<DESCRIPTOR>` is returned.
     *
     * 2. If a Phar archive is running and `$filename` is a `phar://` URL:
     *    - relative path segments in `$filename` (e.g. `/../..`) are resolved
     *      by {@see Conversions::resolvePath()}
     *    - if the file or directory exists, the resolved pathname is returned
     *    - if `$filename` doesn't exist, `false` is returned
     *
     * 3. The return value of `realpath($filename)` is returned.
     *
     * @return string|false
     */
    public function realpath(string $filename)
    {
        if (preg_match(
            '#^/(?:dev|proc/(?:self|[0-9]+))/fd/([0-9]+)$#',
            $filename,
            $matches
        )) {
            return 'php://fd/' . $matches[1];
        }
        if (Test::isPharUrl($filename) && Phar::running()) {
            $filename = Convert::resolvePath($filename);

            return file_exists($filename) ? $filename : false;
        }

        return realpath($filename);
    }

    /**
     * Get the URI or filename associated with a stream
     *
     * @param resource $stream A stream opened by `fopen()`, `fsockopen()`,
     * `pfsockopen()` or `stream_socket_client()`.
     * @return string|null `null` if `$stream` is not an open stream resource.
     */
    public function getStreamUri($stream): ?string
    {
        if (is_resource($stream) && get_resource_type($stream) == 'stream') {
            return stream_get_meta_data($stream)['uri'];
        }

        return null;
    }

    /**
     * Write data to a CSV file or stream
     *
     * For maximum interoperability with Excel across all platforms, data is
     * written in UTF-16LE by default.
     *
     * @param iterable<mixed[]> $data Data to write.
     * @param string|resource|null $target Either a filename, a stream opened by
     * `fopen()`, `fsockopen()` or similar, or `null`. If `null`, data is
     * returned as a string.
     * @param bool $headerRow If `true`, write the first record's array keys
     * before the first row.
     * @param int|string|null $nullValue Optionally replace `null` values before
     * writing data.
     * @param callable(mixed): mixed[] $callback Applied to each record before
     * it is written.
     * @param int|null $count Receives the number of records written.
     * @param bool $utf16le If `true` (the default), encode output in UTF-16LE.
     * @param bool $bom If `true` (the default), add a BOM (byte order mark) to
     * the output.
     * @return string|true
     */
    public function writeCsv(
        iterable $data,
        $target = null,
        bool $headerRow = true,
        $nullValue = null,
        ?callable $callback = null,
        ?int &$count = null,
        string $eol = "\r\n",
        bool $utf16le = true,
        bool $bom = true
    ) {
        if (is_resource($target)) {
            if (($type = get_resource_type($target)) !== 'stream') {
                throw new LogicException(sprintf('Invalid resource type: %s', $type));
            }
            $f = $target;
        } elseif (is_string($target)) {
            $f = fopen($target, 'wb');
        } else {
            $target = null;
            $f = fopen('php://memory', 'w+b');
        }

        if ($utf16le) {
            if (extension_loaded('iconv')) {
                stream_filter_append($f, 'convert.iconv.UTF-8.UTF-16LE', STREAM_FILTER_WRITE);
            } else {
                Console::warnOnce("'iconv' extension required for UTF-16LE encoding");
            }
        }

        if ($bom) {
            fwrite($f, '﻿');
        }

        $count = 0;
        foreach ($data as $row) {
            if ($callback) {
                $row = $callback($row);
            }

            foreach ($row as &$value) {
                if ($value === null) {
                    $value = $nullValue;
                } elseif (!is_scalar($value)) {
                    $value = json_encode($value);
                }
            }

            if (!$count && $headerRow) {
                $this->fputcsv($f, array_keys($row), ',', '"', $eol);
            }

            $this->fputcsv($f, $row, ',', '"', $eol);
            $count++;
        }

        if ($target === null) {
            rewind($f);
            $csv = stream_get_contents($f);
            fclose($f);

            return $csv;
        }
        if (is_string($target)) {
            fclose($f);
        }

        return true;
    }

    /**
     * A polyfill for PHP 8.1's fputcsv, minus $escape
     *
     * @param resource $stream
     * @param mixed[] $fields
     * @return int|false
     */
    private function fputcsv(
        $stream,
        array $fields,
        string $separator = ',',
        string $enclosure = '"',
        string $eol = "\n"
    ) {
        $special = $separator . $enclosure . "\n\r\t ";
        foreach ($fields as &$field) {
            if (strpbrk((string) $field, $special) !== false) {
                $field = $enclosure
                    . str_replace($enclosure, $enclosure . $enclosure, $field)
                    . $enclosure;
            }
        }
        return fwrite($stream, implode($separator, $fields) . $eol);
    }

    /**
     * Return the name of a file unique to the current script and user
     *
     * Unlike with `tempnam()`, nothing is created on the filesystem.
     *
     * @param string $suffix
     * @param string|null $dir If null, `sys_get_temp_dir()` is used.
     * @return string
     */
    public function getStablePath(string $suffix = '.log', string $dir = null)
    {
        $program = Sys::getProgramName();
        $basename = basename($program);
        $hash = Compute::hash(File::realpath($program));
        $euid = posix_geteuid();

        return (is_null($dir)
                ? realpath(sys_get_temp_dir()) . '/'
                : ($dir ? rtrim($dir, '/\\') . '/' : ''))
            . "$basename-$hash-$euid$suffix";
    }
}
