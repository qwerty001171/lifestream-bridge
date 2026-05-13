<?php

namespace App\Http\Clients;

use App\Contracts\LifestreamClientInterface;
use App\Lifestream\Client;
use App\Lifestream\Model\V2AccountsIdResetPasswordPostBody;
use App\Lifestream\Model\V2AccountsIdSubscriptionsPostBody;
use App\Lifestream\Model\V2AccountsPostBody;
use Http\Client\Common\Plugin\BaseUriPlugin;
use Http\Client\Common\Plugin\HeaderAppendPlugin;
use Http\Client\Common\Plugin\RetryPlugin;
use Http\Client\Common\PluginClient;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use RuntimeException;

class LifestreamClientAdapter implements LifestreamClientInterface
{
    private ?Client $client = null;

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 30,
        private readonly int $retries = 3,
        private readonly int $rateLimit = 10,
    ) {}

    private function client(): Client
    {
        if ($this->client === null) {
            $uriFactory = Psr17FactoryDiscovery::findUriFactory();
            $baseUri    = $uriFactory->createUri(rtrim($this->baseUrl, '/'));

            $plugins = [
                new BaseUriPlugin($baseUri),
                new HeaderAppendPlugin(['Accept' => 'application/json']),
                new RetryPlugin(['retries' => $this->retries]),
            ];

            $this->client = Client::create(new PluginClient(Psr18ClientDiscovery::find(), $plugins));
        }

        return $this->client;
    }

    public function createAccount(array $data): array
    {
        $body = new V2AccountsPostBody();
        $body->setUsername($data['username']);

        if (!empty($data['email'])) {
            $body->setEmail($data['email']);
        }

        if (!empty($data['info'])) {
            $body->setInfo($data['info']);
        }

        $result = $this->client()->postV2Account($body);

        if ($result === null) {
            throw new RuntimeException('Lifestream createAccount returned empty response');
        }

        return [
            'id'      => $result->getId(),
            'created' => $result->getCreated(),
        ];
    }

    public function manageSubscription(string $lifestreamId, string $offerId, bool $valid): array
    {
        $body = new V2AccountsIdSubscriptionsPostBody()
            ->setId($offerId)
            ->setValid($valid);

        $result = $this->client()->postV2AccountsByIdSubscription($lifestreamId, $body);

        if ($result === null) {
            return [];
        }

        return [
            'id'    => $result->getId(),
            'valid' => $result->getValid(),
        ];
    }

    public function resetPassword(string $lifestreamId, string $password): array
    {
        $body = new V2AccountsIdResetPasswordPostBody()
            ->setPassword($password);

        $result = $this->client()->postV2AccountsByIdResetPassword($lifestreamId, $body);

        if ($result === null) {
            return [];
        }

        return [
            'status'  => $result->getStatus(),
            'created' => $result->getCreated(),
        ];
    }
}
