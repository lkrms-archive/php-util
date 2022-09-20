<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Closure;
use Lkrms\Concept\FluentInterface;
use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IPipe;
use Lkrms\Contract\IPipeline;
use Lkrms\Facade\Mapper;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

/**
 * Sends a payload through a series of pipes to a destination
 *
 */
class Pipeline extends FluentInterface implements IPipeline
{
    /**
     * @var IContainer|null
     */
    private $Container;

    private $Payload;

    /**
     * @var bool|null
     */
    private $Stream;

    /**
     * @var array<int,IPipe|Closure|string>
     */
    private $Pipes = [];

    /**
     * @var Closure|null
     */
    private $PipeStack;

    /**
     * @var Closure|null
     */
    private $Then;

    /**
     * @var array
     */
    private $ThenArgs = [];

    final public function __construct(?IContainer $container = null)
    {
        $this->Container = $container;
    }

    /**
     * @return static
     */
    final public static function create(?IContainer $container = null)
    {
        return new static($container);
    }

    public function send($payload)
    {
        $this->Payload = $payload;
        $this->Stream  = false;

        return $this;
    }

    public function stream(iterable $payload)
    {
        $this->Payload = $payload;
        $this->Stream  = true;

        return $this;
    }

    public function through(...$pipes)
    {
        array_push($this->Pipes, ...$pipes);
        $this->PipeStack = null;

        return $this;
    }

    public function pipe($pipe)
    {
        $this->Pipes[]   = $pipe;
        $this->PipeStack = null;

        return $this;
    }

    public function apply(callable $callback)
    {
        return $this->pipe(fn($payload, Closure $next) => $next($callback($payload)));
    }

    public function map(array $keyMap, int $conformity = ArrayKeyConformity::NONE, int $flags = ArrayMapperFlag::ADD_UNMAPPED)
    {
        return $this->pipe(fn($payload, Closure $next) => $next(
            (Mapper::getKeyMapClosure($keyMap, $conformity, $flags))($payload)
        ));
    }

    public function then(callable $callback, ...$args)
    {
        if ($this->Then)
        {
            throw new RuntimeException(static::class . "::then() has already been applied");
        }
        $this->Then     = $callback;
        $this->ThenArgs = $args;

        return $this;
    }

    public function run()
    {
        if ($this->Stream)
        {
            throw new RuntimeException(static::class . "::run() cannot be called after " . static::class . "::stream()");
        }
        $this->checkThen();

        return ($this->getPipeStack())($this->Payload);
    }

    public function start(): iterable
    {
        if (!$this->Stream)
        {
            throw new RuntimeException(static::class . "::stream() must be called before " . static::class . "::start()");
        }
        $this->checkThen();

        $pipeStack = $this->getPipeStack();
        foreach ($this->Payload as $payload)
        {
            yield ($pipeStack)($payload);
        }
    }

    /**
     * Run the pipeline
     *
     * If {@see Pipeline::stream()} has been called, use
     * {@see Pipeline::start()} to run the pipeline and return an iterator,
     * otherwise call {@see Pipeline::run()} and return the result.
     */
    public function go()
    {
        if ($this->Stream)
        {
            return $this->start();
        }

        return $this->run();
    }

    protected function handleException($payload, Throwable $ex)
    {
        throw $ex;
    }

    private function checkThen(): void
    {
        if (!$this->Then)
        {
            $this->Then = fn($result) => $result;
        }
    }

    private function getPipeStack(): Closure
    {
        return $this->PipeStack ?: ($this->PipeStack = array_reduce(
            array_reverse($this->Pipes),
            function (Closure $next, $pipe): Closure
            {
                if (is_callable($pipe))
                {
                    $closure = fn($payload) => $pipe($payload, $next);
                }
                else
                {
                    if (is_string($pipe))
                    {
                        $container = $this->Container ?: Container::maybeGetGlobalContainer();
                        $pipe      = $container ? $container->get($pipe) : new $pipe();
                    }
                    if (!($pipe instanceof IPipe))
                    {
                        throw new UnexpectedValueException("Pipe does not implement " . IPipe::class);
                    }
                    $closure = fn($payload) => $pipe->handle($payload, $next);
                }
                return function ($payload) use ($closure)
                {
                    try
                    {
                        return $closure($payload);
                    }
                    catch (Throwable $ex)
                    {
                        return $this->handleException($payload, $ex);
                    }
                };
            },
            fn($result) => ($this->Then)($result, ...$this->ThenArgs)
        ));
    }

}
