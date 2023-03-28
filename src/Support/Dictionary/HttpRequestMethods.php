<?php declare(strict_types=1);

namespace Lkrms\Support\Dictionary;

use Lkrms\Concept\Dictionary;

/**
 * Groups of HTTP request methods
 *
 */
final class HttpRequestMethods extends Dictionary
{
    /**
     * @phpstan-var array<HttpRequestMethod::*>
     */
    const ALL = [
        HttpRequestMethod::GET,
        HttpRequestMethod::HEAD,
        HttpRequestMethod::POST,
        HttpRequestMethod::PUT,
        HttpRequestMethod::PATCH,
        HttpRequestMethod::DELETE,
        HttpRequestMethod::CONNECT,
        HttpRequestMethod::OPTIONS,
        HttpRequestMethod::TRACE,
    ];
}
