<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Container\ContainerInterface;
use Salient\Core\Contract\DateFormatterInterface;
use Salient\Core\Contract\ProviderContextInterface;
use Salient\Core\Contract\ProviderInterface;
use Salient\Core\Exception\MethodNotImplementedException;

/**
 * Base class for providers
 *
 * @implements ProviderInterface<ProviderContext<static,AbstractEntity>>
 */
abstract class AbstractProvider implements ProviderInterface
{
    protected ContainerInterface $App;

    private DateFormatterInterface $DateFormatter;

    /**
     * Creates a new Provider object
     */
    public function __construct(ContainerInterface $app)
    {
        $this->App = $app;
    }

    /**
     * Get a date formatter to work with the backend's date and time format
     * and/or timezone
     *
     * The {@see DateFormatterInterface} returned will be cached for the
     * lifetime of the {@see Provider} instance.
     */
    abstract protected function getDateFormatter(): DateFormatterInterface;

    /**
     * @inheritDoc
     */
    public function getContext(?ContainerInterface $container = null): ProviderContextInterface
    {
        $container ??= $this->App;

        return $container->get(ProviderContext::class, [$this]);
    }

    /**
     * @inheritDoc
     */
    public function checkHeartbeat(int $ttl = 300)
    {
        throw new MethodNotImplementedException(
            static::class,
            __FUNCTION__,
            ProviderInterface::class
        );
    }

    /**
     * @inheritDoc
     */
    final public function app(): ContainerInterface
    {
        return $this->App;
    }

    /**
     * @inheritDoc
     */
    final public function container(): ContainerInterface
    {
        return $this->App;
    }

    /**
     * @inheritDoc
     */
    final public function dateFormatter(): DateFormatterInterface
    {
        return $this->DateFormatter ??= $this->getDateFormatter();
    }

    /**
     * Check if the date formatter returned by dateFormatter() has been cached
     */
    final protected function hasDateFormatter(): bool
    {
        return isset($this->DateFormatter);
    }

    /**
     * Set or unset the date formatter returned by dateFormatter()
     *
     * @return $this
     */
    final protected function setDateFormatter(?DateFormatterInterface $formatter)
    {
        if ($formatter === null) {
            unset($this->DateFormatter);
        } else {
            $this->DateFormatter = $formatter;
        }

        return $this;
    }
}
