<?php declare(strict_types=1);

namespace Salient\Tests\Core\AbstractCatalog;

use Salient\Core\AbstractReflectiveEnumeration;

/**
 * @extends AbstractReflectiveEnumeration<int>
 */
class MyEmptyReflectiveEnum extends AbstractReflectiveEnumeration
{
    protected const IS_PUBLIC = false;
}
