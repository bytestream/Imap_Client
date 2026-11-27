<?php
/**
 * Tests for server responses that arrive after a read timeout, and for
 * commands that are abandoned after 'read_timeout'.
 *
 * @author     David Dreschner
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */

class Horde_Imap_Client_SocketReadTimeoutTest extends \PHPUnit\Framework\TestCase
{
    /* CRAM-MD5 exchange from RFC 2195 [2]. */
    const CRAM_USERNAME = 'tim';
    const CRAM_PASSWORD = 'tanstaaftanstaaf';
    const CRAM_CHALLENGE = 'PDE4OTYuNjk3MTcwOTUyQHBvc3RvZmZpY2UucmVzdG9uLm1jaS5uZXQ+';
    const CRAM_RESPONSE = 'dGltIGI5MTNhNjAyYzdlZGE3YTQ5NWI0ZTZlNzMzNGQzODkw';

    public function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/Stub/Connection.php';
        require_once __DIR__ . '/Stub/Socket.php';
    }

    /**
     * Servers like Dovecot delay their answers after failed logins. A
     * continuation request that arrives after a read timeout must still be
     * answered, or the client sends its next command while the server waits
     * for the continuation data.
     */
    public function testLateResponsesAreProcessed()
    {
        $connection = new Horde_Imap_Client_Stub_Connection(array(
            Horde_Imap_Client_Stub_Connection::READ_TIMEOUT,
            Horde_Imap_Client_Stub_Connection::READ_TIMEOUT,
            '+ ' . self::CRAM_CHALLENGE,
            Horde_Imap_Client_Stub_Connection::READ_TIMEOUT,
            '1 NO [AUTHENTICATIONFAILED] Authentication failed.',
        ));
        $socket = $this->createSocket($connection, 120);

        try {
            $socket->tryLogin('CRAM-MD5');
            $this->fail('tryLogin() must throw the NO response.');
        } catch (Horde_Imap_Client_Exception $e) {
            $this->assertInstanceOf(Horde_Imap_Client_Exception_ServerResponse::class, $e);
        }

        $this->assertSame(
            array('1 AUTHENTICATE CRAM-MD5', self::CRAM_RESPONSE),
            $connection->written
        );
    }

    public function testContinuationWaitDropsConnectionAfterReadTimeout()
    {
        $connection = new Horde_Imap_Client_Stub_Connection(array(
            Horde_Imap_Client_Stub_Connection::READ_TIMEOUT,
        ));
        $socket = $this->createSocket($connection, 0);

        $this->assertReadTimeout(function () use ($socket) {
            $socket->tryLogin('CRAM-MD5');
        });

        $this->assertFalse($socket->hasConnection());
    }

    public function testTaggedResponseWaitDropsConnectionAfterReadTimeout()
    {
        $connection = new Horde_Imap_Client_Stub_Connection(array(
            '+ ' . self::CRAM_CHALLENGE,
            Horde_Imap_Client_Stub_Connection::READ_TIMEOUT,
        ));
        $socket = $this->createSocket($connection, 0);

        $this->assertReadTimeout(function () use ($socket) {
            $socket->tryLogin('CRAM-MD5');
        });

        $this->assertFalse($socket->hasConnection());
    }

    /**
     * Without this, the next mechanism's command goes into the abandoned
     * AUTHENTICATE as continuation data, or to a dropped connection.
     */
    public function testLoginStopsTryingMechanismsWhenConnectionWasDropped()
    {
        $connection = new Horde_Imap_Client_Stub_Connection(array(
            '* CAPABILITY IMAP4rev1 AUTH=CRAM-MD5',
            '1 OK CAPABILITY completed.',
            Horde_Imap_Client_Stub_Connection::READ_TIMEOUT,
        ));
        $socket = $this->createSocket($connection, 0);

        $this->assertReadTimeout(function () use ($socket) {
            $socket->loginWithAvailableMechanisms();
        });

        $this->assertSame(
            array('1 CAPABILITY', '2 AUTHENTICATE CRAM-MD5'),
            $connection->written
        );
    }

    public function testNewConnectionTreatsByeAsDisconnect()
    {
        $socket = $this->createSocket(
            new Horde_Imap_Client_Stub_Connection(array(
                '+ ' . self::CRAM_CHALLENGE,
                Horde_Imap_Client_Stub_Connection::READ_TIMEOUT,
            )),
            0
        );
        $this->assertReadTimeout(function () use ($socket) {
            $socket->tryLogin('CRAM-MD5');
        });
        $socket->setConnection(new Horde_Imap_Client_Stub_Connection(array(
            '* BYE Idle timeout.',
        )));

        try {
            $socket->tryLogin('CRAM-MD5');
            $this->fail('A BYE response must end the command.');
        } catch (Horde_Imap_Client_Exception $e) {
            $this->assertSame(Horde_Imap_Client_Exception::DISCONNECT, $e->getCode());
        }
    }

    private function assertReadTimeout(callable $command)
    {
        try {
            $command();
            $this->fail('The command must give up after read_timeout.');
        } catch (Horde_Imap_Client_Exception $e) {
            $this->assertSame(Horde_Imap_Client_Exception::SERVER_READTIMEOUT, $e->getCode());
        }
    }

    private function createSocket(Horde_Imap_Client_Stub_Connection $connection, $readTimeout)
    {
        $socket = new Horde_Imap_Client_Stub_Socket(array(
            'password' => self::CRAM_PASSWORD,
            'username' => self::CRAM_USERNAME,
        ));
        // The constructor drops a 'read_timeout' of 0.
        $socket->setParam('read_timeout', $readTimeout);
        $socket->setConnection($connection);
        return $socket;
    }
}
