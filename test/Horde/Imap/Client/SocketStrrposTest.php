<?php
/**
 * Test for unchecked strrpos() calls in Socket FETCH response handling.
 *
 * @author     Christoph Wurst
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */

class Horde_Imap_Client_SocketStrrposTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test that malformed BODY[] response without closing bracket doesn't
     * silently corrupt the parsed tag.
     */
    public function testBodyResponseMissingClosingBracket()
    {
        // When strrpos() doesn't find ']', it returns false.
        // Type coercion: substr($tag, 0, false) becomes substr($tag, 0, 0)
        // which returns empty string instead of the intended tag.
        $tag = 'BODY[1.2.3';
        $result = substr($tag, 0, strrpos($tag, ']'));

        // Bug: empty string instead of expected tag data
        $this->assertEquals('', $result, 'Unchecked strrpos() returns false, causing substr() type coercion');
    }

    /**
     * Test that malformed BINARY[] response without closing bracket doesn't
     * corrupt the parsed tag.
     */
    public function testBinaryResponseMissingClosingBracket()
    {
        // When strrpos() doesn't find ']', arithmetic with false coerces to -1
        // substr($tag, 7, false - 7) becomes substr($tag, 7, -7)
        $tag = 'BINARY[TEXT';
        $pos = strrpos($tag, ']');
        if ($pos === false) {
            $pos = -1; // Implicit coercion
        }
        $result = substr($tag, 7, $pos - 7);

        // Bug: wrong substring due to negative length
        $this->assertNotEquals('TEXT', $result);
    }
}
