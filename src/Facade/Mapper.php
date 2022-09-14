<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Closure;
use Lkrms\Concept\Facade;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\ArrayMapper;
use Lkrms\Support\ArrayMapperFlag;

/**
 * A facade for \Lkrms\Support\ArrayMapper
 *
 * @method static ArrayMapper load() Load and return an instance of the underlying ArrayMapper class
 * @method static ArrayMapper getInstance() Return the underlying ArrayMapper instance
 * @method static bool isLoaded() Return true if an underlying ArrayMapper instance has been loaded
 * @method static void unload() Clear the underlying ArrayMapper instance
 * @method static Closure getKeyMapClosure(array $keyMap, int $conformity = ArrayKeyConformity::NONE, int $flags = ArrayMapperFlag::ADD_UNMAPPED) Get a closure to move array values from one set of keys to another (see {@see ArrayMapper::getKeyMapClosure()})
 *
 * @uses ArrayMapper
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Support\ArrayMapper' --generate='Lkrms\Facade\Mapper'
 */
final class Mapper extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return ArrayMapper::class;
    }
}
