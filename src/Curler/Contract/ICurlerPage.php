<?php

declare(strict_types=1);

namespace Lkrms\Curler\Contract;

use Lkrms\Curler\CurlerHeaders;

interface ICurlerPage
{
    /**
     * Return data extracted from the upstream response
     *
     */
    public function entities(): array;

    /**
     * Return true if no more data is available
     *
     */
    public function isLastPage(): bool;

    /**
     * Return the URL of the next page
     *
     * If the URL has a query string, it should be included.
     */
    public function nextUrl(): string;

    /**
     * Return data to send in the body of the request for the next page
     *
     */
    public function nextData(): ?array;

    /**
     * Return the HTTP headers to use when requesting the next page
     *
     * Return `null` to use the same headers sent with the last request.
     */
    public function nextHeaders(): ?CurlerHeaders;

}
