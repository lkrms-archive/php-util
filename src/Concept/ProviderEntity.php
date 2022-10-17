<?php

declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Concern\TProvidable;
use Lkrms\Contract\IProvidable;

/**
 * Base class for entities instantiated by an IProvider
 *
 * @see \Lkrms\Contract\IProvider
 */
abstract class ProviderEntity extends Entity implements IProvidable
{
    use TProvidable;

}
