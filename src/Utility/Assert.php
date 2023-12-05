<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Concept\Utility;
use Lkrms\Exception\AssertionFailedException;
use Lkrms\Exception\FilesystemErrorException;
use Throwable;

/**
 * Throw an exception if a condition isn't met
 */
final class Assert extends Utility
{
    /**
     * Assert that a file or directory exists
     *
     * @throws FilesystemErrorException if `$filename` does not exist.
     */
    public static function fileExists(string $filename): void
    {
        if (file_exists($filename)) {
            return;
        }
        throw new FilesystemErrorException(sprintf('File not found: %s', $filename));
    }

    /**
     * Assert that a file exists
     *
     * @throws FilesystemErrorException if `$filename` is not a regular file.
     */
    public static function isFile(string $filename): void
    {
        if (is_file($filename)) {
            return;
        }
        throw new FilesystemErrorException(sprintf('Not a file: %s', $filename));
    }

    /**
     * Assert that a directory exists
     *
     * @throws FilesystemErrorException if `$filename` is not a directory.
     */
    public static function isDir(string $filename): void
    {
        if (is_dir($filename)) {
            return;
        }
        throw new FilesystemErrorException(sprintf('Not a directory: %s', $filename));
    }

    /**
     * Assert that a value is not empty
     *
     * @template TException of Throwable
     *
     * @param mixed $value
     * @param class-string<TException> $exception
     * @throws TException if `$value == false`.
     */
    public static function notEmpty(
        $value,
        ?string $name = null,
        string $exception = AssertionFailedException::class
    ): void {
        if ($value) {
            return;
        }
        self::throw('{} must not be empty', $name, $exception);
    }

    /**
     * Assert that a value is an instance of a class or interface
     *
     * @template TClass of object
     * @template TException of Throwable
     *
     * @param mixed $value
     * @param class-string<TClass> $class
     * @param class-string<TException> $exception
     * @throws TException if `$value` does not inherit `$class`.
     * @phpstan-assert TClass $value
     */
    public static function instanceOf(
        $value,
        string $class,
        ?string $name = null,
        string $exception = AssertionFailedException::class
    ): void {
        if (is_a($value, $class)) {
            return;
        }
        self::throw(sprintf('{} must be an instance of %s', $class), $name, $exception);
    }

    /**
     * Assert that a value is an array
     *
     * @template TException of Throwable
     *
     * @param mixed $value
     * @param class-string<TException> $exception
     * @throws TException if `$value` is not an array.
     * @phpstan-assert mixed[] $value
     */
    public static function isArray(
        $value,
        ?string $name = null,
        string $exception = AssertionFailedException::class
    ): void {
        if (is_array($value)) {
            return;
        }
        self::throw('{} must be an array', $name, $exception);
    }

    /**
     * Assert that a value is an integer
     *
     * @template TException of Throwable
     *
     * @param mixed $value
     * @param class-string<TException> $exception
     * @throws TException if `$value` is not an integer.
     * @phpstan-assert int $value
     */
    public static function isInt(
        $value,
        ?string $name = null,
        string $exception = AssertionFailedException::class
    ): void {
        if (is_int($value)) {
            return;
        }
        self::throw('{} must be an integer', $name, $exception);
    }

    /**
     * Assert that a value is a string
     *
     * @template TException of Throwable
     *
     * @param mixed $value
     * @param class-string<TException> $exception
     * @throws TException if `$value` is not a string.
     * @phpstan-assert string $value
     */
    public static function isString(
        $value,
        ?string $name = null,
        string $exception = AssertionFailedException::class
    ): void {
        if (is_string($value)) {
            return;
        }
        self::throw('{} must be a string', $name, $exception);
    }

    /**
     * Assert that a value is a string that matches a regular expression
     *
     * @template TException of Throwable
     *
     * @param mixed $value
     * @param class-string<TException> $exception
     * @throws TException if `$value` is not a string or does not match
     * `$pattern`.
     * @phpstan-assert string $value
     */
    public static function isMatch(
        $value,
        string $pattern,
        ?string $name = null,
        string $exception = AssertionFailedException::class
    ): void {
        if (is_string($value) && Pcre::match($pattern, $value)) {
            return;
        }
        self::throw(sprintf('{} must match regular expression: %s', $pattern), $name, $exception);
    }

    /**
     * Assert that PHP is running on the command line
     *
     * @template TException of Throwable
     *
     * @param class-string<TException> $exception
     * @throws TException if the value of PHP_SAPI is not `"cli"`.
     */
    public static function runningOnCli(
        string $exception = AssertionFailedException::class
    ): void {
        if (\PHP_SAPI === 'cli') {
            return;
        }
        // @codeCoverageIgnore
        throw new $exception('CLI required');
    }

    /**
     * Assert that PHP's register_argc_argv directive is enabled
     *
     * @template TException of Throwable
     *
     * @param class-string<TException> $exception
     * @throws TException if `register_argc_argv` is disabled.
     */
    public static function argvIsDeclared(
        string $exception = AssertionFailedException::class
    ): void {
        if (ini_get('register_argc_argv')) {
            return;
        }
        // @codeCoverageIgnore
        throw new $exception('register_argc_argv must be enabled');
    }

    /**
     * @param class-string<Throwable> $exception
     */
    private static function throw(
        string $message,
        ?string $name,
        string $exception
    ): void {
        throw new $exception(
            str_replace(
                '{}',
                $name === null ? 'value' : $name,
                $message
            )
        );
    }
}
