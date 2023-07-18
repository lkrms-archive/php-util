<?php declare(strict_types=1);

namespace Lkrms\Support\Http;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;
use Lkrms\Curler\Contract\ICurlerHeaders;
use Lkrms\Curler\CurlerHeaders;
use Lkrms\Utility\Convert;

/**
 * Represents an outgoing HTTP response
 *
 * @property-read string $ProtocolVersion
 * @property-read int $StatusCode
 * @property-read string|null $ReasonPhrase
 * @property-read ICurlerHeaders $Headers
 * @property-read string|null $Body
 */
final class HttpResponse implements IReadable
{
    use TFullyReadable;

    /**
     * @var string
     */
    protected $ProtocolVersion;

    /**
     * @var int
     */
    protected $StatusCode;

    /**
     * @var string|null
     */
    protected $ReasonPhrase;

    /**
     * @var ICurlerHeaders
     */
    protected $Headers;

    /**
     * @var string|null
     */
    protected $Body;

    public function __construct(
        ?string $body,
        int $statusCode = 200,
        ?string $reasonPhrase = null,
        ?ICurlerHeaders $headers = null,
        string $protocolVersion = '1.1'
    ) {
        $headers = $headers ?: new CurlerHeaders();

        $this->Body = $body;
        $this->StatusCode = $statusCode;
        $this->ReasonPhrase = $reasonPhrase;
        $this->Headers = $headers->setHeader('Content-Length', (string) strlen($this->Body));
        $this->ProtocolVersion = $protocolVersion;
    }

    public function __toString(): string
    {
        $response = [
            Convert::sparseToString(' ', [
                sprintf('HTTP/%s', $this->ProtocolVersion ?: '1.1'),
                $this->StatusCode,
                $this->ReasonPhrase,
            ])
        ];
        array_push($response, ...$this->Headers->getHeaders());
        $response[] = '';
        $response[] = $this->Body ?: '';

        return implode("\r\n", $response);
    }
}
