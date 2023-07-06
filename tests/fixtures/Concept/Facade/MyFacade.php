<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept\Facade;

use Lkrms\Concept\Facade;

final class MyFacade extends Facade
{
    protected static function getServiceName(): string
    {
        return MyUnderlyingClass::class;
    }
}