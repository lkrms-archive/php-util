<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concept\FluentInterface;
use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IPipe;
use Lkrms\Contract\IPipeline;
use Lkrms\Exception\PipelineResultRejectedException;
use Lkrms\Facade\Mapper;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\Catalog\ArrayMapperFlag;
use Closure;
use Generator;
use LogicException;
use Throwable;

/**
 * Sends a payload through a series of pipes to a destination
 *
 * @template TInput
 * @template TOutput
 * @template TArgument
 * @implements IPipeline<TInput,TOutput,TArgument>
 */
final class Pipeline extends FluentInterface implements IPipeline
{
    /**
     * @var IContainer|null
     */
    private $Container;

    /**
     * @var TInput|iterable<TInput>
     */
    private $Payload;

    /**
     * @var ArrayKeyConformity::*
     */
    private $PayloadConformity;

    /**
     * @var TArgument|null
     */
    private $Arg;

    /**
     * @var bool|null
     */
    private $Stream;

    /**
     * @var array<IPipe<TInput,TOutput,TArgument>|(callable(TInput|TOutput, Closure, IPipeline<TInput,TOutput,TArgument>, TArgument): (TInput|TOutput))|class-string<IPipe<TInput,TOutput,TArgument>>>
     */
    private $Pipes = [];

    /**
     * @var (callable(TInput, IPipeline<TInput,TOutput,TArgument>, TArgument): (TInput|TOutput))|null
     */
    private $After;

    /**
     * @var (callable(TInput, IPipeline<TInput,TOutput,TArgument>, TArgument): TOutput)|null
     */
    private $Then;

    /**
     * @var bool
     */
    private $CollectThen = false;

    /**
     * @var array<callable(TOutput, IPipeline<TInput,TOutput,TArgument>, TArgument): mixed>
     */
    private $Cc = [];

    /**
     * @var (callable(TOutput, IPipeline<TInput,TOutput,TArgument>, TArgument): bool)|null
     */
    private $Unless;

    /**
     * Creates a new Pipeline object
     */
    public function __construct(?IContainer $container = null)
    {
        $this->Container = $container;
    }

    /**
     * Get a new pipeline
     *
     * Syntactic sugar for `new Pipeline()`.
     *
     * @return self<TInput,TOutput,TArgument>
     */
    public static function create(?IContainer $container = null): self
    {
        return new self($container);
    }

    /**
     * @inheritDoc
     */
    public function send($payload, $arg = null)
    {
        $clone = clone $this;
        $clone->Payload = $payload;
        $clone->PayloadConformity = ArrayKeyConformity::NONE;
        $clone->Arg = $arg;
        $clone->Stream = false;

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function stream(iterable $payload, $arg = null)
    {
        $clone = clone $this;
        $clone->Payload = $payload;
        $clone->PayloadConformity = ArrayKeyConformity::NONE;
        $clone->Arg = $arg;
        $clone->Stream = true;

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function withConformity($conformity = ArrayKeyConformity::PARTIAL)
    {
        $clone = clone $this;
        $clone->PayloadConformity = $conformity;

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function after(callable $callback)
    {
        if ($this->After) {
            throw new LogicException(static::class . '::after() has already been applied');
        }
        $clone = clone $this;
        $clone->After = $callback;

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function afterIf(callable $callback)
    {
        if ($this->After) {
            return $this;
        }

        return $this->after($callback);
    }

    /**
     * @inheritDoc
     */
    public function through(...$pipes)
    {
        $clone = clone $this;
        array_push($clone->Pipes, ...$pipes);

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function throughCallback(callable $callback)
    {
        return $this->through(
            fn($payload, Closure $next, IPipeline $pipeline, $args) =>
                $next($callback($payload, $pipeline, $args))
        );
    }

    /**
     * @inheritDoc
     */
    public function throughKeyMap(array $keyMap, int $flags = ArrayMapperFlag::ADD_UNMAPPED)
    {
        return $this->through(
            fn($payload, Closure $next, IPipeline $pipeline) =>
                $next((Mapper::getKeyMapClosure(
                    $keyMap,
                    $pipeline->getConformity(),
                    $flags
                ))($payload))
        );
    }

    /**
     * @inheritDoc
     */
    public function then(callable $callback)
    {
        if ($this->Then) {
            throw new LogicException(static::class . '::then() has already been applied');
        }
        $clone = clone $this;
        $clone->Then = $callback;

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function thenIf(callable $callback)
    {
        if ($this->Then) {
            return $this;
        }

        return $this->then($callback);
    }

    /**
     * @inheritDoc
     */
    public function collectThen(callable $callback)
    {
        if ($this->Then) {
            throw new LogicException(static::class . '::then() has already been applied');
        }
        $clone = clone $this;
        $clone->Then = $callback;
        $clone->CollectThen = true;

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function collectThenIf(callable $callback)
    {
        if ($this->Then) {
            return $this;
        }

        return $this->collectThen($callback);
    }

    /**
     * @inheritDoc
     */
    public function cc(callable $callback)
    {
        $clone = clone $this;
        $clone->Cc[] = $callback;

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function unless(callable $filter)
    {
        if ($this->Unless) {
            throw new LogicException(static::class . '::unless() has already been applied');
        }
        $clone = clone $this;
        $clone->Unless = $filter;

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function unlessIf(callable $filter)
    {
        if ($this->Unless) {
            return $this;
        }

        return $this->unless($filter);
    }

    /**
     * @inheritDoc
     */
    public function run()
    {
        if ($this->Stream || $this->CollectThen) {
            throw new LogicException(
                static::class . '::run() cannot be called after '
                . static::class . '::stream() or '
                . static::class . '::collectThen()'
            );
        }

        $result = $this->getClosure()(
            $this->After
                ? ($this->After)($this->Payload, $this, $this->Arg)
                : $this->Payload
        );

        if ($this->Unless &&
                ($this->Unless)($result, $this, $this->Arg) === true) {
            throw new PipelineResultRejectedException($this->Payload, $result);
        }

        if ($this->Cc) {
            $this->ccResult($result);
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function start(): Generator
    {
        if (!$this->Stream) {
            throw new LogicException(
                static::class . '::stream() must be called before '
                . static::class . '::start()'
            );
        }

        $closure = $this->getClosure();
        $results = [];
        foreach ($this->Payload as $key => $payload) {
            $result = $closure(
                $this->After
                    ? ($this->After)($payload, $this, $this->Arg)
                    : $payload
            );

            if ($this->Unless &&
                    ($this->Unless)($result, $this, $this->Arg) === true) {
                continue;
            }

            if ($this->CollectThen) {
                $results[$key] = $result;
                continue;
            }

            if ($this->Cc) {
                $this->ccResult($result);
            }

            yield $key => $result;
        }

        if (!$this->CollectThen || !$results) {
            return;
        }

        $results = ($this->Then)($results, $this, $this->Arg);
        foreach ($results as $key => $result) {
            if ($this->Cc) {
                $this->ccResult($result);
            }

            yield $key => $result;
        }
    }

    /**
     * @inheritDoc
     */
    public function getConformity()
    {
        return $this->PayloadConformity;
    }

    /**
     * @inheritDoc
     */
    public function runInto(IPipeline $next)
    {
        return $next->send($this->run(), $this->Arg);
    }

    /**
     * @inheritDoc
     */
    public function startInto(IPipeline $next)
    {
        return $next->stream($this->start(), $this->Arg);
    }

    /**
     * @param mixed $result
     */
    private function ccResult($result): void
    {
        foreach ($this->Cc as $callback) {
            $callback($result, $this, $this->Arg);
        }
    }

    /**
     * @param mixed $payload
     * @return never
     */
    private function handleException($payload, Throwable $ex)
    {
        throw $ex;
    }

    private function getClosure(): Closure
    {
        return array_reduce(
            array_reverse($this->Pipes),
            function (Closure $next, $pipe): Closure {
                if (is_callable($pipe)) {
                    $closure = fn($payload) => $pipe($payload, $next, $this, $this->Arg);
                } else {
                    if (is_string($pipe)) {
                        $container = $this->Container ?: Container::maybeGetGlobalContainer();
                        $pipe = $container ? $container->get($pipe) : new $pipe();
                    }
                    if (!($pipe instanceof IPipe)) {
                        throw new LogicException('Pipe does not implement ' . IPipe::class);
                    }
                    $closure = fn($payload) => $pipe->handle($payload, $next, $this, $this->Arg);
                }

                return
                    function ($payload) use ($closure) {
                        try {
                            return $closure($payload);
                        } catch (Throwable $ex) {
                            return $this->handleException($payload, $ex);
                        }
                    };
            },
            $this->Then && !$this->CollectThen
                ? fn($result) => ($this->Then)($result, $this, $this->Arg)
                : fn($result) => $result
        );
    }
}
