<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;

/**
 * A facade for \Lkrms\Utility\Composer
 *
 * @method static \Lkrms\Utility\Composer load() Load and return an instance of the underlying Composer class
 * @method static \Lkrms\Utility\Composer getInstance() Return the underlying Composer instance
 * @method static bool isLoaded() Return true if an underlying Composer instance has been loaded
 * @method static void unload() Clear the underlying Composer instance
 * @method static string|null getClassPath(string $class) Use ClassLoader to find the file where a class is defined (see {@see \Lkrms\Utility\Composer::getClassPath()})
 * @method static string|null getNamespacePath(string $namespace) Use ClassLoader's PSR-4 prefixes to resolve a namespace to a path (see {@see \Lkrms\Utility\Composer::getNamespacePath()})
 * @method static string|null getPackagePath(string $name = 'lkrms/util') A facade for \Lkrms\Utility\Composer::getPackagePath()
 * @method static string|null getPackageVersion(string $name = 'lkrms/util') A facade for \Lkrms\Utility\Composer::getPackageVersion()
 * @method static string getRootPackageName() A facade for \Lkrms\Utility\Composer::getRootPackageName()
 * @method static string getRootPackagePath() A facade for \Lkrms\Utility\Composer::getRootPackagePath()
 * @method static string getRootPackageVersion() A facade for \Lkrms\Utility\Composer::getRootPackageVersion()
 *
 * @uses \Lkrms\Utility\Composer
 * @extends Facade<\Lkrms\Utility\Composer>
 * @lkrms-generate-command lk-util generate facade 'Lkrms\Utility\Composer' 'Lkrms\Facade\Composer'
 */
final class Composer extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return \Lkrms\Utility\Composer::class;
    }
}
