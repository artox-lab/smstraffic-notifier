<?php
/**
 * Sms traffic transport
 *
 * @author Maxim Petrovich <m.petrovich@artox.com>
 */
namespace  ArtoxLab\Component\Notifier\Bridge\SmsTraffic;

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\RuntimeException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SmsTrafficTransport extends AbstractTransport
{

    public const HOST = 'api.smstraffic.ru';
    public const FAILOVER_HOST = 'api2.smstraffic.ru';

    /**
     * Login
     *
     * @var string
     */
    private $login;

    /**
     * Password
     *
     * @var string
     */
    private $password;

    /**
     * Sender name
     *
     * @var string
     */
    private $from;

    /**
     * SmsLineTransport constructor.
     *
     * @param string                        $login      Login
     * @param string                        $password   Password
     * @param string                        $from       Sender name
     * @param HttpClientInterface|null      $client     Http client
     * @param EventDispatcherInterface|null $dispatcher Event dispatcher
     */
    public function __construct(
        string $login,
        string $password,
        string $from,
        HttpClientInterface $client = null,
        EventDispatcherInterface $dispatcher = null
    ) {
        $this->login    = $login;
        $this->password = $password;
        $this->from     = $from;

        parent::__construct($client, $dispatcher);
    }

    /**
     * Send message
     *
     * @param MessageInterface $message Message
     *
     * @return SentMessage
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (false === $message instanceof SmsMessage) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" (instance of "%s" given).', __CLASS__, SmsMessage::class, get_debug_type($message)));
        }

        try {
            $response = $this->makeRequest("https://{$this->getEndpoint()}/multi.php", $message);
            $this->validateResponse($response);
        } catch (TransportExceptionInterface $exception) {
            try {
                $response = $this->makeRequest("https://" . self::FAILOVER_HOST . "/multi.php", $message);
                $this->validateResponse($response);
            } catch (TransportExceptionInterface $exception) {
                throw new RuntimeException(
                    'Unable to send the SMS: ' . $exception->getMessage()
                );
            }
        }

        return new SentMessage($message, (string) $this);
    }

    /**
     * Make Api request
     *
     * @param string $endpoint Api endpoint
     * @param MessageInterface $message Message
     *
     * @return ResponseInterface
     * @throws TransportExceptionInterface
     */
    private function makeRequest(string $endpoint, MessageInterface $message): ResponseInterface
    {
        return $this->client->request(
            'POST',
            $endpoint,
            [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'login'      => $this->login,
                    'password'   => $this->password,
                    'originator' => $this->from,
                    'phones'     => $message->getPhone(),
                    'message'    => $message->getSubject(),
                ],
            ]
        );
    }

    /**
     * Validate response
     *
     * @param ResponseInterface $response Response
     *
     * @return void
     * @throws TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     */
    private function validateResponse(ResponseInterface $response): void
    {
        if (200 !== $response->getStatusCode()) {
            throw new TransportException('Unable to send the SMS', $response);
        }

        if (false === strpos($response->getContent(), '<result>OK</result>')){
            $message = 'Unable to send the SMS';

            if (preg_match('|<code>(\d+)</code>|s', $response->getContent(), $match)) {
                $message .= ". Code $match[1]";
            }

            throw new TransportException($message, $response);
        }
    }

    /**
     * Supports
     *
     * @param MessageInterface $message Message
     *
     * @return bool
     */
    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    /**
     * To string
     *
     * @return string
     */
    public function __toString(): string
    {
        return sprintf('smstraffic://%s?from=%s', $this->getEndpoint(), $this->from);
    }
}
