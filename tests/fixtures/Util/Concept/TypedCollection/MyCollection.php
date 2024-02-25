<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept\TypedCollection;

use Salient\Collection\AbstractTypedCollection;

/**
 * @extends AbstractTypedCollection<array-key,MyClass>
 */
class MyCollection extends AbstractTypedCollection
{
    protected function compareItems($a, $b): int
    {
        return strlen($b->Name) - strlen($a->Name);
    }
}
