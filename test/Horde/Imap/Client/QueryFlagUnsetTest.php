<?php
/**
 * Test for uninitialized array access in Query newMsgs()
 *
 * @author     Christoph Wurst
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */

class Horde_Imap_Client_QueryFlagUnsetTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test newMsgs() doesn't try to unset flag keys when flag() wasn't called
     */
    public function testNewMsgsWithoutFlagInitialized()
    {
        $query = new Horde_Imap_Client_Search_Query();

        // Call newMsgs() without calling flag() first
        // Before fix: this would try to unset keys in non-existent $ptr['flag'] array
        // After fix: isset() checks prevent this
        $query->newMsgs(true);

        // Build the query - this internally calls the problematic code path
        $result = $query->build();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('query', $result);
    }

    /**
     * Test newMsgs(false) also doesn't unset without flag initialized
     */
    public function testNewMsgsOldWithoutFlagInitialized()
    {
        $query = new Horde_Imap_Client_Search_Query();

        $query->newMsgs(false);

        // Should not throw or notice
        $result = $query->build();

        $this->assertIsArray($result);
    }
}
