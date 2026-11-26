<?php
/**
 * Scripted IMAP connection: returns the given server lines in order and
 * records the lines the client writes.
 *
 * @author     David Dreschner
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */

class Horde_Imap_Client_Stub_Connection
extends Horde_Imap_Client_Socket_Connection_Socket
{
    /** Script entry for a read that times out without data. */
    const READ_TIMEOUT = false;

    /** @var string[]  Lines written by the client, without line endings. */
    public $written = array();

    /** @var array<string|false> */
    private $responses;

    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function write($data, $eol = false)
    {
        $this->_buffer .= $data;
        if ($eol) {
            $this->written[] = $this->_buffer;
            $this->_buffer = '';
        }
    }

    public function read($size = null)
    {
        if (!$this->responses) {
            throw new LogicException('No scripted server response left.');
        }

        $response = array_shift($this->responses);
        if ($response === self::READ_TIMEOUT) {
            /* A real read timeout takes time; let elapsed-time checks see
             * some. */
            usleep(1000);
            throw new Horde_Imap_Client_Exception(
                Horde_Imap_Client_Translation::r("Error when communicating with the mail server."),
                Horde_Imap_Client_Exception::SERVER_READTIMEOUT
            );
        }

        $token = new Horde_Imap_Client_Tokenize();
        $token->add($response);
        return $token;
    }
}
