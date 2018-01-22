<?php
declare(strict_types=1);

namespace Genkgo\Mail\Protocol\Imap\Negotiation;

use Genkgo\Mail\Exception\ImapAuthenticationException;
use Genkgo\Mail\Protocol\Imap\Client;
use Genkgo\Mail\Protocol\Imap\CompletionResult;
use Genkgo\Mail\Protocol\Imap\NegotiationInterface;
use Genkgo\Mail\Protocol\Imap\Request\AuthPlainCommand;
use Genkgo\Mail\Protocol\Imap\Request\AuthPlainCredentialsRequest;
use Genkgo\Mail\Protocol\Imap\Request\CapabilityCommand;
use Genkgo\Mail\Protocol\Imap\Response\CapabilityList;

final class AuthNegotiation implements NegotiationInterface
{
    /**
     * @var int
     */
    private $method;
    /**
     * @var string
     */
    private $username;
    /**
     * @var string
     */
    private $password;

    /**
     * AuthNegotiation constructor.
     * @param int $method
     * @param string $username
     * @param string $password
     */
    public function __construct(int $method, string $username, string $password)
    {
        $this->method = $method;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * @param Client $client
     * @throws ImapAuthenticationException
     */
    public function negotiate(Client $client): void
    {
        $method = $this->method;
        if ($method === Client::AUTH_AUTO) {
            $responseList = $client->emit(new CapabilityCommand());

            $capabilities = CapabilityList::fromResponse(
                $responseList
                    ->first()
                    ->assertCommand('CAPABILITY')
            );

            $responseList
                ->last()
                ->assertTagged();

            $options = [
                'AUTH=PLAIN' => Client::AUTH_PLAIN,
            ];

            foreach ($options as $capability => $auth) {
                if ($capabilities->isAdvertising($capability)) {
                    $method = $auth;
                }
            }

            if ($method === Client::AUTH_AUTO) {
                throw new ImapAuthenticationException(
                    'IMAP server does not advertise one the supported AUTH methods (AUTH PLAIN)'
                );
            }
        }

        switch ($method) {
            case Client::AUTH_PLAIN:
                $client
                    ->emit(new AuthPlainCommand())
                    ->first()
                    ->assertContinuation();

                $client
                    ->emit(
                        new AuthPlainCredentialsRequest(
                            $this->username,
                            $this->password
                        )
                    )
                    ->first()
                    ->assertCompletion(CompletionResult::ok())
                    ->assertTagged();
                break;
        }
    }
}