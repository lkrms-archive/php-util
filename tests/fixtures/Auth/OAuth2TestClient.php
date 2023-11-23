<?php declare(strict_types=1);

namespace Lkrms\Tests\Auth;

use League\OAuth2\Client\Provider\GenericProvider;
use Lkrms\Auth\AccessToken;
use Lkrms\Auth\OAuth2Client;
use Lkrms\Auth\OAuth2Flow;
use Lkrms\Support\Http\HttpServer;

class OAuth2TestClient extends OAuth2Client
{
    protected function getListener(): ?HttpServer
    {
        $listener = new HttpServer(
            $this->Env->get('app_host', 'localhost'),
            $this->Env->getInt('app_port', 27755),
        );

        $proxyHost = $this->Env->getNullable('app_proxy_host', null);
        $proxyPort = $this->Env->getNullableInt('app_proxy_port', null);

        if ($proxyHost !== null && $proxyPort !== null) {
            return $listener->withProxy(
                $proxyHost,
                $proxyPort,
                $this->Env->getNullableBool('app_proxy_tls', null),
                $this->Env->getNullable('app_proxy_base_path', null),
            );
        }

        return $listener;
    }

    protected function getProvider(): GenericProvider
    {
        $tenantId = $this->Env->get('microsoft_graph_tenant_id');

        return new GenericProvider([
            'clientId' => $this->Env->get('microsoft_graph_app_id'),
            'clientSecret' => $this->Env->get('microsoft_graph_secret'),
            'redirectUri' => $this->getRedirectUri(),
            'urlAuthorize' => sprintf('https://login.microsoftonline.com/%s/oauth2/authorize', $tenantId),
            'urlAccessToken' => sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/token', $tenantId),
            'urlResourceOwnerDetails' => sprintf('https://login.microsoftonline.com/%s/openid/userinfo', $tenantId),
            'scopes' => ['openid', 'profile', 'email', 'offline_access', 'https://graph.microsoft.com/.default'],
            'scopeSeparator' => ' ',
        ]);
    }

    protected function getFlow(): int
    {
        return OAuth2Flow::CLIENT_CREDENTIALS;
    }

    protected function getJsonWebKeySetUrl(): ?string
    {
        return 'https://login.microsoftonline.com/common/discovery/keys';
    }

    protected function receiveToken(AccessToken $token, ?array $idToken, string $grantType): void {}
}
