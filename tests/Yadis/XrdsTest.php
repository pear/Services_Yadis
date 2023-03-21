<?php

require_once 'Services/Yadis/Xrds.php';

class Services_Yadis_XrdsTest extends \PHPUnit\Framework\TestCase
{
    protected $_namespace = null;

    public function setUp(): void
    {
        $this->_namespace = $this->getMockBuilder('Services_Yadis_Xrds_Namespace')
            ->getMock();
    }

    public function test()
    {
    }

}
