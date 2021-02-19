<?php
/**
 * Sms traffic transport factory
 *
 * @author Maxim Petrovich <m.petrovich@artox.com>
 */

namespace ArtoxLab\Component\Notifier\Bridge\SmsTraffic;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

class SmsTrafficTransportFactory extends AbstractTransportFactory
{

    /**
     * Supported schemes
     *
     * @return array|string[]
     */
    protected function getSupportedSchemes(): array
    {
        return ['smstraffic'];
    }

    /**
     * Create
     *
     * @param Dsn $dsn DSN
     *
     * @return TransportInterface
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme   = $dsn->getScheme();

        if ('smstraffic' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'smstraffic', $this->getSupportedSchemes());
        }

        $login    = $this->getUser($dsn);
        $password = $this->getPassword($dsn);
        $from     = $dsn->getOption('from');
        $host     = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port     = $dsn->getPort();

        $transport = new SmsTrafficTransport($login, $password, $from, $this->client, $this->dispatcher);
        $transport->setHost($host);
        $transport->setPort($port);

        return $transport;
    }

}
