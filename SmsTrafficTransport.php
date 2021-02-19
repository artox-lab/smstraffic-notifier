<?php
/**
 * Sms traffic transport
 *
 * @author Maxim Petrovich <m.petrovich@artox.com>
 */
namespace  ArtoxLab\Component\Notifier\Bridge\SmsTraffic;

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SmsTrafficTransport extends AbstractTransport
{

    public const HOST = 'sds.smstraffic.ru';

    /**
     * Login
     *
     * @var string
     */
    private string $login;

    /**
     * Password
     *
     * @var string
     */
    private string $password;

    /**
     * Sender name
     *
     * @var string
     */
    private string $from;

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

        $endpoint = sprintf('https://%s/smartdelivery-in/multi.php', $this->getEndpoint());
        $response = $this->client->request(
            'POST',
            $endpoint,
            [
                'json' => [
                    'login'      => $this->login,
                    'password'   => $this->password,
                    'phones'     => $message->getPhone(),
                    'message'    => $message->getSubject(),
                    'rus'        => 0,
                    'originator' => $this->from,
                ],
            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new TransportException(
                'Unable to send the SMS',
                $response
            );
        }

        return new SentMessage($message, (string) $this);
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
