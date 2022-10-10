<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concept\Enumeration;

/**
 * Frequently-used HTTP headers
 *
 */
final class HttpHeader extends Enumeration
{
    public const ACCEPT       = "Accept";
    public const CONTENT_TYPE = "Content-Type";
    public const USER_AGENT   = "User-Agent";

}
