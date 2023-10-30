<?php declare(strict_types=1);

namespace Lkrms\Support\Http;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IImmutable;
use Lkrms\Contract\IReadable;
use Lkrms\Curler\CurlerHeaders;
use Lkrms\Curler\CurlerHeadersFlag;
use Lkrms\Facade\Console;
use Lkrms\Support\Catalog\HttpRequestMethods;
use Lkrms\Support\Http\HttpRequest;
use Lkrms\Support\Http\HttpResponse;
use RuntimeException;

/**
 * Listen for HTTP requests on a local address
 *
 * @property-read string $Host
 * @property-read int $Port
 * @property-read int $Timeout
 * @property-read string|null $ProxyHost
 * @property-read int|null $ProxyPort
 * @property-read bool|null $ProxyTls
 */
final class HttpServer implements IReadable, IImmutable
{
    use TFullyReadable;

    /**
     * @var string
     */
    protected $Host;

    /**
     * @var int
     */
    protected $Port;

    /**
     * @var int
     */
    protected $Timeout;

    /**
     * @var string|null
     */
    protected $ProxyHost;

    /**
     * @var int|null
     */
    protected $ProxyPort;

    /**
     * @var bool|null
     */
    protected $ProxyTls;

    /**
     * @var string|null
     */
    protected $ProxyBasePath;

    /**
     * @var resource|null
     */
    private $Server;

    public function __construct(string $host, int $port, int $timeout = 300)
    {
        $this->Host = $host;
        $this->Port = $port;
        $this->Timeout = $timeout;
    }

    /**
     * Get a copy of the server configured to run behind a reverse proxy
     *
     * Returns a server that listens at the same host and port, but refers to
     * itself in client-facing URLs as:
     *
     * ```
     * http[s]://<proxy_host>[:<proxy_port>][<proxy_base_path>]
     * ```
     *
     * @return $this
     * @see HttpServer::getBaseUrl()
     */
    public function withProxy(
        string $proxyHost,
        int $proxyPort,
        ?bool $proxyTls = null,
        ?string $proxyBasePath = null
    ) {
        $proxyBasePath = trim($proxyBasePath ?? '', '/');

        $clone = clone $this;
        $clone->ProxyHost = $proxyHost;
        $clone->ProxyPort = $proxyPort;
        $clone->ProxyTls = $proxyTls === null ? ($proxyPort === 443) : $proxyTls;
        $clone->ProxyBasePath =
            $proxyBasePath === ''
                ? null
                : '/' . $proxyBasePath;

        $clone->Server = null;

        return $clone;
    }

    /**
     * Get the server's client-facing base URL with no trailing slash
     */
    public function getBaseUrl(): string
    {
        if ($this->ProxyHost && $this->ProxyPort) {
            return
                ($this->ProxyTls && $this->ProxyPort === 443) ||
                (!$this->ProxyTls && $this->ProxyPort === 80)
                    ? sprintf(
                        '%s://%s%s',
                        $this->ProxyTls ? 'https' : 'http',
                        $this->ProxyHost,
                        (string) $this->ProxyBasePath,
                    )
                    : sprintf(
                        '%s://%s:%d%s',
                        $this->ProxyTls ? 'https' : 'http',
                        $this->ProxyHost,
                        $this->ProxyPort,
                        $this->ProxyBasePath,
                    );
        }

        return $this->ProxyPort === 80
            ? sprintf('http://%s', $this->Host)
            : sprintf('http://%s:%d', $this->Host, $this->Port);
    }

    /**
     * @return $this
     */
    public function start()
    {
        if ($this->Server) {
            return $this;
        }

        $errMessage = $errCode = null;
        if ($server = stream_socket_server(
            "tcp://{$this->Host}:{$this->Port}", $errCode, $errMessage
        )) {
            $this->Server = $server;

            return $this;
        }

        throw new RuntimeException(sprintf(
            'Unable to start HTTP server at %s:%d (error %d: %s)',
            $this->Host,
            $this->Port,
            $errCode,
            $errMessage
        ));
    }

    /**
     * @return $this
     */
    public function stop()
    {
        if ($this->Server) {
            fclose($this->Server);
            $this->Server = null;
        }

        return $this;
    }

    public function isRunning(): bool
    {
        return !is_null($this->Server);
    }

    /**
     * Wait for a request and return a response
     *
     * @template T
     * @param callable(HttpRequest $request, bool &$continue, T &$return): HttpResponse $callback
     * Receives an {@see HttpRequest} and returns an {@see HttpResponse}. May
     * also set `$continue = true` to make {@see HttpServer::listen()} wait for
     * another request, or use `$return = <value>` to pass `<value>` back to the
     * caller.
     * @return T|null
     */
    public function listen(callable $callback, ?int $timeout = null)
    {
        if (!$this->Server) {
            throw new RuntimeException('start() must be called first');
        }

        $timeout = is_null($timeout) ? $this->Timeout : $timeout;
        do {
            $peer = null;
            $socket = stream_socket_accept($this->Server, $timeout, $peer);
            $client = $peer ? preg_replace('/:[0-9]+$/', '', $peer) : null;
            $peer = $peer ?: '<unknown>';

            if (!$socket) {
                throw new RuntimeException("Unable to accept connection from $peer");
            }

            $startLine = null;
            $version = null;
            $headers = new CurlerHeaders();
            $body = null;
            do {
                if (($line = fgets($socket)) === false) {
                    throw new RuntimeException("Error reading request from $peer");
                }

                if (is_null($startLine)) {
                    $startLine = explode(' ', rtrim($line, "\r\n"));
                    if (count($startLine) !== 3 ||
                            !in_array($startLine[0], HttpRequestMethods::ALL, true) ||
                            !preg_match(
                                '/^HTTP\/([0-9]+(?:\.[0-9]+)?)$/',
                                $startLine[2],
                                $version
                            )) {
                        throw new RuntimeException("Invalid HTTP request from $peer");
                    }
                    continue;
                }

                $headers = $headers->addRawHeader($line);

                if (!trim($line)) {
                    break;
                }
            } while (true);

            /** @todo Add support for Transfer-Encoding */
            if ($length = $headers->getHeaderValue('Content-Length', CurlerHeadersFlag::KEEP_LAST)) {
                if (($body = fread($socket, (int) $length)) === false) {
                    throw new RuntimeException("Error reading request body from $peer");
                }
            }

            [[$method, $target], [1 => $version]] = [$startLine, $version];
            $request =
                new HttpRequest($method, $target, $version, $headers, $body, $client);

            Console::debug("$method request received from $client:", $target);

            $continue = false;
            $return = null;

            try {
                /** @var HttpResponse */
                $response = $callback($request, $continue, $return);
            } finally {
                fwrite(
                    $socket,
                    (string) ($response ?? new HttpResponse(
                        'Internal server error', 500, 'Internal Server Error'
                    ))
                );
                fclose($socket);
            }
        } while ($continue);

        return $return;
    }
}
