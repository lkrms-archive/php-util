<?php declare(strict_types=1);

namespace Salient\Tests\Container;

use Salient\Container\Container;
use Salient\Container\ContainerInterface;
use LogicException;

trait TestTrait
{
    protected ?ContainerInterface $Container = null;

    protected ?string $Service = null;

    protected int $SetServiceCount = 0;

    public function service(): string
    {
        return $this->Service ?? static::class;
    }

    public function app(): ContainerInterface
    {
        return $this->container();
    }

    public function container(): ContainerInterface
    {
        return $this->Container ??= new Container();
    }

    public function setContainer(ContainerInterface $container): void
    {
        if ($this->Container !== null) {
            throw new LogicException('Container already set');
        }
        $this->Container = $container;
    }

    public function setService(string $service): void
    {
        $this->Service = $service;
        $this->SetServiceCount++;
    }

    public function getSetServiceCount(): int
    {
        return $this->SetServiceCount;
    }
}