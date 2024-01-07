<?php declare(strict_types=1);

namespace Lkrms\Console;

use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Catalog\ConsoleLevelGroup as LevelGroup;
use Lkrms\Console\Catalog\ConsoleMessageType as MessageType;
use Lkrms\Console\Catalog\ConsoleTargetTypeFlag as TargetTypeFlag;
use Lkrms\Console\Contract\ConsoleTargetInterface as Target;
use Lkrms\Console\Contract\ConsoleTargetPrefixInterface as TargetPrefix;
use Lkrms\Console\Contract\ConsoleTargetStreamInterface as TargetStream;
use Lkrms\Console\Target\StreamTarget;
use Lkrms\Console\ConsoleFormatter as Formatter;
use Lkrms\Contract\IFacade;
use Lkrms\Contract\ReceivesFacade;
use Lkrms\Exception\Contract\ExceptionInterface;
use Lkrms\Exception\Contract\MultipleErrorExceptionInterface;
use Lkrms\Exception\InvalidEnvironmentException;
use Lkrms\Facade\Console;
use Lkrms\Utility\Arr;
use Lkrms\Utility\Compute;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Debug;
use Lkrms\Utility\Env;
use Lkrms\Utility\File;
use Lkrms\Utility\Get;
use Lkrms\Utility\Str;
use Throwable;

/**
 * Logs messages to registered targets
 *
 * {@see ConsoleWriter} methods should generally be called via the
 * {@see Console} facade. If a {@see ConsoleWriter} instance is required, call
 * {@see Console::getInstance()}.
 */
final class ConsoleWriter implements ReceivesFacade
{
    /**
     * @var array<Level::*,TargetStream[]>
     */
    private array $StdioTargetsByLevel = [];

    /**
     * @var array<Level::*,TargetStream[]>
     */
    private array $TtyTargetsByLevel = [];

    /**
     * @var array<Level::*,Target[]>
     */
    private array $TargetsByLevel = [];

    /**
     * @var array<int,Target>
     */
    private array $Targets = [];

    /**
     * @var array<int,int-mask-of<TargetTypeFlag::*>>
     */
    private array $TargetTypeFlags = [];

    private ?TargetStream $StdoutTarget = null;

    private ?TargetStream $StderrTarget = null;

    private int $GroupLevel = -1;

    /**
     * @var string[]
     */
    private array $GroupMessageStack = [];

    private int $Errors = 0;

    private int $Warnings = 0;

    /**
     * @var array<string,true>
     */
    private array $Written = [];

    /**
     * @var class-string<IFacade<static>>|null
     */
    private ?string $Facade = null;

    /**
     * @inheritDoc
     */
    public function setFacade(string $name)
    {
        $this->Facade = $name;
        return $this;
    }

    /**
     * Register the default output log as a target for all console messages
     *
     * Console output is appended to a file in the default temporary directory,
     * created with mode `0600` if it doesn't already exist:
     *
     * ```php
     * <?php
     * sys_get_temp_dir() . '/<script_basename>-<realpath_hash>-<user_id>.log'
     * ```
     *
     * @return $this
     */
    public function registerDefaultOutputLog()
    {
        return $this->registerTarget(
            StreamTarget::fromPath(File::getStablePath('.log')),
            LevelGroup::ALL
        );
    }

    /**
     * Register STDOUT and STDERR as targets in their default configuration
     *
     * If the value of environment variable `CONSOLE_TARGET` is `stderr` or
     * `stdout`, console output is written to `STDERR` or `STDOUT` respectively.
     *
     * Otherwise, when running on the command line:
     *
     * - If `STDERR` is a TTY and `STDOUT` is not, console messages are written
     *   to `STDERR` so output to `STDOUT` isn't tainted
     * - Otherwise, errors and warnings are written to `STDERR`, and
     *   informational messages are written to `STDOUT`
     *
     * Debug messages are suppressed if environment variable `DEBUG` is empty or
     * not set.
     *
     * @param bool $replace If `false` (the default) and a target backed by
     * `STDOUT` or `STDERR` has already been registered, no action is taken.
     * @return $this
     */
    public function registerDefaultStdioTargets(bool $replace = false)
    {
        $output = Env::get('CONSOLE_TARGET', null);

        if ($output !== null) {
            switch (Str::lower($output)) {
                case 'stderr':
                    return $this->registerStdioTarget(
                        $replace,
                        $this->getStderrTarget(),
                        true,
                    );

                case 'stdout':
                    return $this->registerStdioTarget(
                        $replace,
                        $this->getStdoutTarget(),
                        true,
                    );

                default:
                    throw new InvalidEnvironmentException(
                        sprintf('Invalid CONSOLE_TARGET value: %s', $output)
                    );
            }
        }

        if (stream_isatty(\STDERR) && !stream_isatty(\STDOUT)) {
            return $this->registerStderrTarget($replace);
        }

        return $this->registerStdioTargets($replace);
    }

    /**
     * Register STDOUT and STDERR as targets if running on the command line
     *
     * Errors and warnings are written to `STDERR`, informational messages are
     * written to `STDOUT`, and debug messages are suppressed if environment
     * variable `DEBUG` is empty or not set.
     *
     * @param bool $replace If `false` (the default) and a target backed by
     * `STDOUT` or `STDERR` has already been registered, no action is taken.
     * @return $this
     */
    public function registerStdioTargets(bool $replace = false)
    {
        if (\PHP_SAPI !== 'cli' || ($this->StdioTargetsByLevel && !$replace)) {
            return $this;
        }

        $stderr = $this->getStderrTarget();
        $stderrLevels = LevelGroup::ERRORS_AND_WARNINGS;

        $stdout = $this->getStdoutTarget();
        $stdoutLevels = Env::debug()
            ? LevelGroup::INFO
            : LevelGroup::INFO_EXCEPT_DEBUG;

        return $this
            ->clearStdioTargets()
            ->registerTarget($stderr, $stderrLevels)
            ->registerTarget($stdout, $stdoutLevels);
    }

    /**
     * Register STDERR as a target for all console messages if running on the
     * command line
     *
     * @param bool $replace If `false` (the default) and a target backed by
     * `STDOUT` or `STDERR` has already been registered, no action is taken.
     * @return $this
     */
    public function registerStderrTarget(bool $replace = false)
    {
        return $this->registerStdioTarget(
            $replace,
            $this->getStderrTarget(),
        );
    }

    /**
     * Register a target to receive console output
     *
     * @param array<Level::*> $levels
     * @return $this
     */
    public function registerTarget(
        Target $target,
        array $levels = LevelGroup::ALL
    ) {
        $type = 0;

        if ($target instanceof TargetStream) {
            $type |= TargetTypeFlag::STREAM;

            if ($target->isStdout()) {
                $type |= TargetTypeFlag::STDIO | TargetTypeFlag::STDOUT;
                $this->StdoutTarget = $target;
            }

            if ($target->isStderr()) {
                $type |= TargetTypeFlag::STDIO | TargetTypeFlag::STDERR;
                $this->StderrTarget = $target;
            }

            if ($type & TargetTypeFlag::STDIO) {
                $targetsByLevel[] = &$this->StdioTargetsByLevel;
            }

            if ($target->isTty()) {
                $type |= TargetTypeFlag::TTY;
                $targetsByLevel[] = &$this->TtyTargetsByLevel;
            }
        }

        $targetsByLevel[] = &$this->TargetsByLevel;

        $targetId = spl_object_id($target);

        foreach ($targetsByLevel as &$targetsByLevel) {
            foreach ($levels as $level) {
                $targetsByLevel[$level][$targetId] = $target;
            }
        }

        $this->Targets[$targetId] = $target;
        $this->TargetTypeFlags[$targetId] = $type;

        return $this;
    }

    /**
     * Deregister a previously registered target
     *
     * @return $this
     */
    public function deregisterTarget(Target $target)
    {
        $targetId = spl_object_id($target);

        unset($this->Targets[$targetId]);

        foreach ([
            &$this->TargetsByLevel,
            &$this->TtyTargetsByLevel,
            &$this->StdioTargetsByLevel,
        ] as &$targetsByLevel) {
            foreach ($targetsByLevel as $level => &$targets) {
                unset($targets[$targetId]);
                if (!$targets) {
                    unset($targetsByLevel[$level]);
                }
            }
        }

        if ($this->StderrTarget === $target) {
            $this->StderrTarget = null;
        }

        if ($this->StdoutTarget === $target) {
            $this->StdoutTarget = null;
        }

        // Reinstate previous STDOUT and STDERR targets if possible
        if (
            $this->Targets &&
            (!$this->StdoutTarget || !$this->StderrTarget)
        ) {
            foreach (array_reverse($this->Targets) as $target) {
                if (!($target instanceof TargetStream)) {
                    continue;
                }
                if (!$this->StdoutTarget && $target->isStdout()) {
                    $this->StdoutTarget = $target;
                    if ($this->StderrTarget) {
                        break;
                    }
                }
                if (!$this->StderrTarget && $target->isStderr()) {
                    $this->StderrTarget = $target;
                    if ($this->StdoutTarget) {
                        break;
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Set or unset the prefix applied to each line of output by targets that
     * implement ConsoleTargetPrefixInterface
     *
     * @param int-mask-of<TargetTypeFlag::*> $flags
     * @return $this
     */
    public function setTargetPrefix(?string $prefix, int $flags = 0)
    {
        $invertFlags = false;
        if ($flags & TargetTypeFlag::INVERT) {
            $flags &= ~TargetTypeFlag::INVERT;
            $invertFlags = true;
        }

        foreach ($this->Targets as $targetId => $target) {
            if (!($target instanceof TargetPrefix) || (
                $flags &&
                !($this->TargetTypeFlags[$targetId] & $flags xor $invertFlags)
            )) {
                continue;
            }
            $target->setPrefix($prefix);
        }

        return $this;
    }

    /**
     * Get the width of a registered target in columns
     *
     * Returns {@see Target::getWidth()} from whichever is found first:
     *
     * - the first TTY target registered with the given level
     * - the first `STDOUT` or `STDERR` target registered with the given level
     * - the first target registered with the given level
     * - the target returned by {@see getStderrTarget()} if backed by a TTY
     * - the target returned by {@see getStdoutTarget()}
     *
     * @param Level::* $level
     */
    public function getWidth($level = Level::INFO): ?int
    {
        /** @var Target[] */
        $targets = $this->TtyTargetsByLevel[$level]
            ?? $this->StdioTargetsByLevel[$level]
            ?? $this->TargetsByLevel[$level]
            ?? [];

        $target = reset($targets);
        if (!$target) {
            $target = $this->getStderrTarget();
            if (!$target->isTty()) {
                $target = $this->getStdoutTarget();
            }
        }

        return $target->getWidth();
    }

    /**
     * Get a target for STDOUT, creating it if necessary
     */
    public function getStdoutTarget(): TargetStream
    {
        if (!$this->StdoutTarget) {
            return $this->StdoutTarget = new StreamTarget(\STDOUT);
        }
        return $this->StdoutTarget;
    }

    /**
     * Get a target for STDERR, creating it if necessary
     */
    public function getStderrTarget(): TargetStream
    {
        if (!$this->StderrTarget) {
            return $this->StderrTarget = new StreamTarget(\STDERR);
        }
        return $this->StderrTarget;
    }

    /**
     * Get the number of errors reported so far
     */
    public function getErrors(): int
    {
        return $this->Errors;
    }

    /**
     * Get the number of warnings reported so far
     */
    public function getWarnings(): int
    {
        return $this->Warnings;
    }

    /**
     * Print a "command finished" message with a summary of errors and warnings
     *
     * Prints `" // $finishedText $successText"` with level INFO if no errors or
     * warnings have been reported (default: `" // Command finished without
     * errors"`).
     *
     * Otherwise, prints one of the following with level ERROR or WARNING:
     * - `" !! $finishedText with $errors errors[ and $warnings warnings]"`
     * - `"  ! $finishedText with 0 errors and $warnings warnings"`
     *
     * @return $this
     */
    public function summary(
        string $finishedText = 'Command finished',
        string $successText = 'without errors'
    ) {
        $msg1 = trim($finishedText);
        if ($this->Errors + $this->Warnings === 0) {
            return $this->write(Level::INFO, "$msg1 $successText", null, MessageType::SUCCESS);
        }

        $msg2 = 'with ' . Convert::plural($this->Errors, 'error', null, true);
        if ($this->Warnings) {
            $msg2 .= ' and ' . Convert::plural($this->Warnings, 'warning', null, true);
        }

        return $this->write(
            $this->Errors ? Level::ERROR : Level::WARNING,
            "$msg1 $msg2",
            null
        );
    }

    /**
     * Print "$msg"
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    public function print(
        string $msg,
        $level = Level::INFO,
        $type = MessageType::UNDECORATED
    ) {
        return $this->_write($level, $msg, null, $type, null, $this->TargetsByLevel);
    }

    /**
     * Print "$msg" to I/O stream targets (STDOUT or STDERR)
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    public function out(
        string $msg,
        $level = Level::INFO,
        $type = MessageType::UNDECORATED
    ) {
        return $this->_write($level, $msg, null, $type, null, $this->StdioTargetsByLevel);
    }

    /**
     * Print "$msg" to TTY targets
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    public function tty(
        string $msg,
        $level = Level::INFO,
        $type = MessageType::UNDECORATED
    ) {
        return $this->_write($level, $msg, null, $type, null, $this->TtyTargetsByLevel);
    }

    /**
     * Print "$msg" to STDOUT, creating a target for it if necessary
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    public function stdout(
        string $msg,
        $level = Level::INFO,
        $type = MessageType::UNFORMATTED
    ) {
        $targets = [$level => [$this->getStdoutTarget()]];
        $this->_write($level, $msg, null, $type, null, $targets);

        return $this;
    }

    /**
     * Print "$msg" to STDERR, creating a target for it if necessary
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    public function stderr(
        string $msg,
        $level = Level::INFO,
        $type = MessageType::UNFORMATTED
    ) {
        $targets = [$level => [$this->getStderrTarget()]];
        $this->_write($level, $msg, null, $type, null, $targets);

        return $this;
    }

    /**
     * Print "$msg1 $msg2" with prefix and formatting optionally based on $level
     *
     * This method increments the message counter for `$level`.
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    public function message(
        $level,
        string $msg1,
        ?string $msg2 = null,
        $type = MessageType::STANDARD,
        ?Throwable $ex = null,
        bool $count = true
    ) {
        if ($count) {
            $this->count($level);
        }

        return $this->write($level, $msg1, $msg2, $type, $ex);
    }

    /**
     * Print "$msg1 $msg2" with prefix and formatting optionally based on $level
     * once per run
     *
     * This method increments the message counter for `$level`.
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    public function messageOnce(
        $level,
        string $msg1,
        ?string $msg2 = null,
        $type = MessageType::STANDARD,
        ?Throwable $ex = null,
        bool $count = true
    ) {
        if ($count) {
            $this->count($level);
        }

        return $this->writeOnce($level, $msg1, $msg2, $type, $ex);
    }

    /**
     * Increment the message counter for $level without printing anything
     *
     * @param Level::* $level
     * @return $this
     */
    public function count($level)
    {
        switch ($level) {
            case Level::EMERGENCY:
            case Level::ALERT:
            case Level::CRITICAL:
            case Level::ERROR:
                $this->Errors++;
                break;

            case Level::WARNING:
                $this->Warnings++;
                break;
        }

        return $this;
    }

    /**
     * Print " !! $msg1 $msg2" with level ERROR
     *
     * @return $this
     */
    public function error(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        bool $count = true
    ) {
        !$count || $this->Errors++;

        return $this->write(Level::ERROR, $msg1, $msg2, MessageType::STANDARD, $ex);
    }

    /**
     * Print " !! $msg1 $msg2" with level ERROR once per run
     *
     * @return $this
     */
    public function errorOnce(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        bool $count = true
    ) {
        !$count || $this->Errors++;

        return $this->writeOnce(Level::ERROR, $msg1, $msg2, MessageType::STANDARD, $ex);
    }

    /**
     * Print "  ! $msg1 $msg2" with level WARNING
     *
     * @return $this
     */
    public function warn(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        bool $count = true
    ) {
        !$count || $this->Warnings++;

        return $this->write(Level::WARNING, $msg1, $msg2, MessageType::STANDARD, $ex);
    }

    /**
     * Print "  ! $msg1 $msg2" with level WARNING once per run
     *
     * @return $this
     */
    public function warnOnce(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        bool $count = true
    ) {
        !$count || $this->Warnings++;

        return $this->writeOnce(Level::WARNING, $msg1, $msg2, MessageType::STANDARD, $ex);
    }

    /**
     * Print "==> $msg1 $msg2" with level NOTICE
     *
     * @return $this
     */
    public function info(
        string $msg1,
        ?string $msg2 = null
    ) {
        return $this->write(Level::NOTICE, $msg1, $msg2);
    }

    /**
     * Print "==> $msg1 $msg2" with level NOTICE once per run
     *
     * @return $this
     */
    public function infoOnce(
        string $msg1,
        ?string $msg2 = null
    ) {
        return $this->writeOnce(Level::NOTICE, $msg1, $msg2);
    }

    /**
     * Print " -> $msg1 $msg2" with level INFO
     *
     * @return $this
     */
    public function log(
        string $msg1,
        ?string $msg2 = null
    ) {
        return $this->write(Level::INFO, $msg1, $msg2);
    }

    /**
     * Print " -> $msg1 $msg2" with level INFO once per run
     *
     * @return $this
     */
    public function logOnce(
        string $msg1,
        ?string $msg2 = null
    ) {
        return $this->writeOnce(Level::INFO, $msg1, $msg2);
    }

    /**
     * Print " -> $msg1 $msg2" with level INFO to TTY targets without moving to
     * the next line
     *
     * The next message sent to TTY targets is written with a leading "clear to
     * end of line" sequence unless {@see maybeClearLine()} has been called in
     * the meantime.
     *
     * {@see logProgress()} can be called repeatedly to display transient
     * progress updates when running interactively, without disrupting other
     * console messages or bloating output logs.
     *
     * @return $this
     */
    public function logProgress(
        string $msg1,
        ?string $msg2 = null
    ) {
        if (!($this->TtyTargetsByLevel[Level::INFO] ?? null)) {
            return $this;
        }

        if ((string) $msg2 === '') {
            $msg1 = rtrim($msg1, "\r") . "\r";
        } else {
            $msg2 = rtrim($msg2, "\r") . "\r";
        }

        return $this->writeTty(Level::INFO, $msg1, $msg2);
    }

    /**
     * Print a "clear to end of line" control sequence with level INFO to any
     * TTY targets with a pending logProgress() message
     *
     * Useful when progress updates that would disrupt other output to STDOUT or
     * STDERR may have been displayed.
     *
     * @return $this
     */
    public function maybeClearLine()
    {
        if (!($this->TtyTargetsByLevel[Level::INFO] ?? null)) {
            return $this;
        }

        return $this->writeTty(Level::INFO, "\r", null, MessageType::UNFORMATTED);
    }

    /**
     * Print "--- {CALLER} $msg1 $msg2" with level DEBUG
     *
     * @param int $depth Passed to {@see Debug::getCaller()}. To print your
     * caller's name instead of your own, set `$depth` to 1.
     * @return $this
     */
    public function debug(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        int $depth = 0
    ) {
        if ($this->Facade) {
            $depth++;
        }

        $caller = implode('', Debug::getCaller($depth));
        $msg1 = $msg1 ? ' __' . $msg1 . '__' : '';

        return $this->write(Level::DEBUG, "{{$caller}}{$msg1}", $msg2, MessageType::STANDARD, $ex);
    }

    /**
     * Print "--- {CALLER} $msg1 $msg2" with level DEBUG once per run
     *
     * @param int $depth Passed to {@see Debug::getCaller()}. To print your
     * caller's name instead of your own, set `$depth` to 1.
     * @return $this
     */
    public function debugOnce(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        int $depth = 0
    ) {
        if ($this->Facade) {
            $depth++;
        }

        $caller = implode('', Debug::getCaller($depth));

        return $this->writeOnce(Level::DEBUG, "{{$caller}} __" . $msg1 . '__', $msg2, MessageType::STANDARD, $ex);
    }

    /**
     * Create a new message group and print "<<< $msg1 $msg2" with level NOTICE
     *
     * The message group will remain open, and subsequent messages will be
     * indented, until {@see groupEnd()} is called.
     *
     * @return $this
     */
    public function group(
        string $msg1,
        ?string $msg2 = null
    ) {
        $this->GroupLevel++;
        $this->GroupMessageStack[] = Arr::implode(' ', [$msg1, $msg2]);

        return $this->write(Level::NOTICE, $msg1, $msg2, MessageType::GROUP_START);
    }

    /**
     * Close the most recently created message group
     *
     * @return $this
     * @see ConsoleWriter::group()
     */
    public function groupEnd(bool $printMessage = false)
    {
        $msg = array_pop($this->GroupMessageStack);
        if ($printMessage &&
                $msg !== '' &&
                ($msg = Formatter::removeTags($msg)) !== '') {
            $this->write(Level::NOTICE, '', $msg ? "{ $msg } complete" : null, MessageType::GROUP_END);
        }
        $this->out('', Level::NOTICE);

        if ($this->GroupLevel > -1) {
            $this->GroupLevel--;
        }

        return $this;
    }

    /**
     * Report an uncaught exception
     *
     * Prints `" !! <exception>: <message> in <file>:<line>"` with level
     * `$messageLevel` (default: ERROR), followed by the exception's stack trace
     * with level `$stackTraceLevel` (default: DEBUG).
     *
     * @param Level::* $messageLevel
     * @param Level::*|null $stackTraceLevel If `null`, the exception's stack
     * trace is not printed.
     * @return $this
     */
    public function exception(
        Throwable $exception,
        $messageLevel = Level::ERROR,
        $stackTraceLevel = Level::DEBUG
    ) {
        $ex = $exception;
        $msg2 = '';
        $i = 0;
        do {
            if ($i++) {
                $class = Formatter::escapeTags(Get::basename(get_class($ex)));
                $msg2 .= sprintf("\nCaused by __%s__: ", $class);
            }

            if ($ex instanceof MultipleErrorExceptionInterface &&
                    !$ex->hasUnreportedErrors()) {
                $message = Formatter::escapeTags($ex->getMessageWithoutErrors());
            } else {
                $message = Formatter::escapeTags($ex->getMessage());
            }

            $file = Formatter::escapeTags($ex->getFile());
            $line = $ex->getLine();
            $msg2 .= sprintf('%s ~~in %s:%d~~', $message, $file, $line);

            $ex = $ex->getPrevious();
        } while ($ex);

        $class = Formatter::escapeTags(Get::basename(get_class($exception)));
        $this->count($messageLevel)->write(
            $messageLevel,
            sprintf('__%s__:', $class),
            $msg2,
            MessageType::STANDARD,
            $exception,
            true
        );
        if ($stackTraceLevel === null) {
            return $this;
        }
        $this->write(
            $stackTraceLevel,
            '__Stack trace:__',
            "\n" . $exception->getTraceAsString()
        );
        if ($exception instanceof ExceptionInterface) {
            foreach ($exception->getDetail() as $section => $text) {
                $this->write(
                    $stackTraceLevel,
                    "__{$section}:__",
                    "\n{$text}"
                );
            }
        }

        return $this;
    }

    /**
     * @param bool $force If `true`, the target is registered even if not
     * running on the command line.
     * @return $this
     */
    private function registerStdioTarget(
        bool $replace,
        Target $target,
        bool $force = false
    ) {
        if (!($force || \PHP_SAPI === 'cli') ||
                ($this->StdioTargetsByLevel && !$replace)) {
            return $this;
        }

        $levels = Env::debug()
            ? LevelGroup::ALL
            : LevelGroup::ALL_EXCEPT_DEBUG;

        return $this
            ->clearStdioTargets()
            ->registerTarget($target, $levels);
    }

    /**
     * @return $this
     */
    private function clearStdioTargets()
    {
        if (!$this->StdioTargetsByLevel) {
            return $this;
        }
        $targets = $this->reduceTargets($this->StdioTargetsByLevel);
        foreach ($targets as $target) {
            $this->deregisterTarget($target);
        }
        return $this;
    }

    /**
     * @param array<Level::*,Target[]> $targets
     * @return Target[]
     */
    private function reduceTargets(array $targets): array
    {
        foreach ($targets as $levelTargets) {
            foreach ($levelTargets as $target) {
                $targetId = spl_object_id($target);
                $reduced[$targetId] = $target;
            }
        }
        return $reduced ?? [];
    }

    /**
     * Send a message to registered targets
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    private function write(
        $level,
        string $msg1,
        ?string $msg2,
        $type = MessageType::STANDARD,
        ?Throwable $ex = null,
        bool $msg2HasTags = false
    ) {
        return $this->_write($level, $msg1, $msg2, $type, $ex, $this->TargetsByLevel, $msg2HasTags);
    }

    /**
     * Send a message to registered targets once per run
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    private function writeOnce(
        $level,
        string $msg1,
        ?string $msg2,
        $type = MessageType::STANDARD,
        ?Throwable $ex = null,
        bool $msg2HasTags = false
    ) {
        $hash = Compute::hash($level, $msg1, $msg2, $type, $msg2HasTags);
        if ($this->Written[$hash] ?? false) {
            return $this;
        }
        $this->Written[$hash] = true;
        return $this->_write($level, $msg1, $msg2, $type, $ex, $this->TargetsByLevel, $msg2HasTags);
    }

    /**
     * Send a message to registered TTY targets
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    private function writeTty(
        $level,
        string $msg1,
        ?string $msg2,
        $type = MessageType::STANDARD,
        ?Throwable $ex = null,
        bool $msg2HasTags = false
    ) {
        return $this->_write($level, $msg1, $msg2, $type, $ex, $this->TtyTargetsByLevel, $msg2HasTags);
    }

    /**
     * @param Level::* $level
     * @param MessageType::* $type
     * @param array<Level::*,Target[]> $targets
     * @return $this
     */
    private function _write(
        $level,
        string $msg1,
        ?string $msg2,
        $type,
        ?Throwable $ex,
        array &$targets,
        bool $msg2HasTags = false
    ) {
        if (!$this->Targets) {
            $this->registerDefaultOutputLog();
            $this->registerDefaultStdioTargets();
        }

        // As per PSR-3 Section 1.3
        if ($ex) {
            $context['exception'] = $ex;
        }

        $margin = max(0, $this->GroupLevel * 4);

        /** @var Target $target */
        foreach ($targets[$level] ?? [] as $target) {
            $formatter = $target->getFormatter();

            $indent = strlen($formatter->getMessagePrefix($level, $type));
            $indent = max(0, strpos($msg1, "\n") !== false ? $indent : $indent - 4);

            $_msg1 = $msg1 === '' ? '' : $formatter->formatTags($msg1);

            if ($margin + $indent > 0 && strpos($msg1, "\n") !== false) {
                $_msg1 = str_replace("\n", "\n" . str_repeat(' ', $margin + $indent), $_msg1);
            }

            if ((string) $msg2 !== '') {
                $_msg2 = $msg2HasTags ? $formatter->formatTags($msg2) : $msg2;
                $_msg2 = strpos($msg2, "\n") !== false
                    ? str_replace("\n", "\n" . str_repeat(' ', $margin + $indent + 2), "\n" . ltrim($_msg2))
                    : ($_msg1 !== '' ? ' ' : '') . $_msg2;
            }

            $message = $formatter->formatMessage($_msg1, $_msg2 ?? null, $level, $type);
            $target->write($level, str_repeat(' ', $margin) . $message, $context ?? []);
        }

        return $this;
    }
}
