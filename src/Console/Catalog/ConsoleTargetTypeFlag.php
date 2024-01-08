<?php declare(strict_types=1);

namespace Lkrms\Console\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Console output target type flags
 *
 * @api
 *
 * @extends Enumeration<int>
 */
final class ConsoleTargetTypeFlag extends Enumeration
{
    public const STREAM = 1;
    public const STDIO = 2;
    public const STDOUT = 4;
    public const STDERR = 8;
    public const TTY = 16;
    public const INVERT = 32;
}
