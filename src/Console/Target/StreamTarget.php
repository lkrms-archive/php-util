<?php declare(strict_types=1);

namespace Lkrms\Console\Target;

use Lkrms\Console\Concept\ConsoleTarget;
use Lkrms\Support\Catalog\TtyControlSequence;
use Lkrms\Utility\Convert;
use Lkrms\Utility\File;
use DateTime;
use LogicException;

/**
 * Write console messages to a stream (e.g. a file or TTY)
 */
final class StreamTarget extends ConsoleTarget
{
    /**
     * @var resource
     */
    private $Stream;

    /**
     * @var bool
     */
    private $AddTimestamp = false;

    /**
     * @var string
     */
    private $Timestamp = '[d M y H:i:s.vO] ';

    /**
     * @var \DateTimeZone|null
     */
    private $Timezone;

    /**
     * @var bool
     */
    private $IsStdout;

    /**
     * @var bool
     */
    private $IsStderr;

    /**
     * @var bool
     */
    private $IsTty;

    /**
     * @var string|null
     */
    private $Path;

    /**
     * @var bool
     */
    private static $HasPendingClearLine = false;

    /**
     * Use an open stream as a console output target
     *
     * @param resource $stream
     * @param bool|null $addTimestamp If `null`, timestamps are added if
     * `$stream` is not STDOUT or STDERR.
     * @param string|null $timestamp Default: `[d M y H:i:s.vO] `
     * @param \DateTimeZone|string|null $timezone Default: as per
     * `date_default_timezone_set` or INI setting `date.timezone`
     */
    public function __construct(
        $stream,
        ?bool $addTimestamp = null,
        ?string $timestamp = null,
        $timezone = null
    ) {
        stream_set_write_buffer($stream, 0);

        $this->Stream = $stream;
        $this->IsStdout = File::getStreamUri($stream) === 'php://stdout';
        $this->IsStderr = File::getStreamUri($stream) === 'php://stderr';
        $this->IsTty = stream_isatty($stream);

        if ($addTimestamp ||
            ($addTimestamp === null &&
                !($this->IsStdout || $this->IsStderr))) {
            $this->AddTimestamp = true;
            if ($timestamp !== null) {
                $this->Timestamp = $timestamp;
            }
            if ($timezone !== null) {
                $this->Timezone = Convert::toTimezone($timezone);
            }
        }
    }

    public function isStdout(): bool
    {
        return $this->IsStdout;
    }

    public function isStderr(): bool
    {
        return $this->IsStderr;
    }

    public function isTty(): bool
    {
        return $this->IsTty;
    }

    /**
     * @return $this
     */
    public function reopen(string $path = null)
    {
        if ($this->Path === null) {
            throw new LogicException(
                sprintf(
                    'Only instances created by %s::fromPath() can be reopened',
                    static::class,
                ),
            );
        }

        $path = $path === null || $path === '' ? $this->Path : $path;

        File::close($this->Stream, $this->Path);
        File::create($path, 0600);
        $this->Stream = File::open($path, 'a');
        $this->Path = $path;

        return $this;
    }

    /**
     * Open a file in append mode and return a console output target for it
     *
     * @param bool|null $addTimestamp If `null`, timestamps will be added unless
     * `$path` is STDOUT, STDERR, or a TTY
     * @param string|null $timestamp Default: `[d M y H:i:s.vO] `
     * @param \DateTimeZone|string|null $timezone Default: as per
     * `date_default_timezone_set` or INI setting `date.timezone`
     */
    public static function fromPath(
        string $path,
        ?bool $addTimestamp = null,
        ?string $timestamp = null,
        $timezone = null
    ): StreamTarget {
        File::create($path, 0600);
        $stream =
            new StreamTarget(
                File::open($path, 'a'),
                $addTimestamp,
                $timestamp,
                $timezone,
            );
        $stream->Path = $path;

        return $stream;
    }

    protected function writeToTarget($level, string $message, array $context): void
    {
        if ($this->AddTimestamp) {
            $now = (new DateTime('now', $this->Timezone))->format($this->Timestamp);
            $message = $now . str_replace("\n", "\n" . $now, $message);
        }

        // If writing a progress message to a TTY, suppress the usual newline
        // and write a "clear to end of line" sequence before the next message
        if ($this->IsTty) {
            if (self::$HasPendingClearLine) {
                fwrite($this->Stream, "\r" . TtyControlSequence::CLEAR_LINE . TtyControlSequence::WRAP_ON);
                self::$HasPendingClearLine = false;
            }
            if ($message === "\r") {
                return;
            }
            if (($message[-1] ?? null) === "\r") {
                fwrite($this->Stream, TtyControlSequence::WRAP_OFF . rtrim($message, "\r"));
                self::$HasPendingClearLine = true;

                return;
            }
        }

        fwrite($this->Stream, rtrim($message, "\r") . "\n");
    }

    public function getPath(): string
    {
        return $this->Path;
    }

    public function __destruct()
    {
        if ($this->IsTty && self::$HasPendingClearLine && is_resource($this->Stream)) {
            fwrite($this->Stream, "\r" . TtyControlSequence::CLEAR_LINE . TtyControlSequence::WRAP_ON);
            self::$HasPendingClearLine = false;
        }
    }
}
