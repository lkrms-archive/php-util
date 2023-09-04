<?php declare(strict_types=1);

namespace Lkrms\Auth;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;
use Lkrms\Utility\Convert;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * An immutable access token
 *
 * @property-read string $Token
 * @property-read string $Type
 * @property-read DateTimeImmutable|null $Expires
 * @property-read string[] $Scopes
 * @property-read array<string,mixed> $Claims
 */
final class AccessToken implements IReadable
{
    use TFullyReadable;

    /**
     * @var string
     */
    protected $Token;

    /**
     * @var string
     */
    protected $Type;

    /**
     * @var DateTimeImmutable|null
     */
    protected $Expires;

    /**
     * @var string[]
     */
    protected $Scopes;

    /**
     * @var array<string,mixed>
     */
    protected $Claims;

    /**
     * @param DateTimeInterface|int|null $expires
     * @param string[]|null $scopes
     * @param array<string,mixed>|null $claims
     */
    public function __construct(string $token, string $type, $expires, ?array $scopes = null, ?array $claims = null)
    {
        $this->Token = $token;
        $this->Type = $type;
        $this->Expires = $expires instanceof DateTimeInterface
            ? Convert::toDateTimeImmutable($expires)
            : ($expires !== null && $expires > 0 ? new DateTimeImmutable("@$expires") : null);
        $this->Scopes = $scopes ?: [];
        $this->Claims = $claims ?: [];
    }
}
