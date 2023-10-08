<?php declare(strict_types=1);

namespace Lkrms\Tests\Polyfill;

use PhpToken;

/**
 * @requires PHP >= 8.0
 */
final class NativePhpTokenTest extends PhpTokenTestCase
{
    protected static string $Token = PhpToken::class;
}
