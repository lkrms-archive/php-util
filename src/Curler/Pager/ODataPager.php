<?php declare(strict_types=1);

namespace Lkrms\Curler\Pager;

use Lkrms\Curler\Contract\ICurlerPage;
use Lkrms\Curler\Contract\ICurlerPager;
use Lkrms\Curler\Support\CurlerPageBuilder;
use Lkrms\Curler\Curler;
use Lkrms\Support\Catalog\HttpHeader;

final class ODataPager implements ICurlerPager
{
    /**
     * @var string|null
     */
    private $Prefix;

    /**
     * @var int|null
     */
    private $MaxPageSize;

    /**
     * @param string|null $prefix The OData property prefix, e.g. `"@odata."`.
     * Extrapolated from the `OData-Version` HTTP header if `null`.
     */
    public function __construct(?int $maxPageSize = null, ?string $prefix = null)
    {
        $this->Prefix = $prefix;
        $this->MaxPageSize = $maxPageSize;
    }

    public function prepareQuery(?array $query): ?array
    {
        return $query;
    }

    public function prepareData($data)
    {
        return $data;
    }

    public function prepareCurler(Curler $curler): Curler
    {
        if (!is_null($this->MaxPageSize)) {
            return $curler
                ->unsetHeader(HttpHeader::PREFER, '/^odata\.maxpagesize\h*=/')
                ->addHeader(HttpHeader::PREFER, sprintf('odata.maxpagesize=%d', $this->MaxPageSize));
        }

        return $curler;
    }

    public function getPage($data, Curler $curler, ?ICurlerPage $previous = null): ICurlerPage
    {
        $prefix = $this->Prefix
            ?: (($curler->ResponseHeadersByName['odata-version'] ?? null) == '4.0'
                ? '@odata.'
                : '@');

        return CurlerPageBuilder::build()
            ->entities($data['value'])
            ->curler($curler)
            ->previous($previous)
            ->nextUrl($data[$prefix . 'nextLink'] ?? null)
            ->go();
    }
}
