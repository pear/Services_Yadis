<?php
require_once 'Services/Yadis.php';
require_once 'HTTP/Request2.php';
require_once 'HTTP/Request2/Adapter/Mock.php';

class Services_YadisTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException Services_Yadis_Exception
     * @expectedExceptionMessage Invalid response to Yadis protocol received: A test error
     */
    public function testGetException()
    {
        $httpMock = new HTTP_Request2_Adapter_Mock();
        $httpMock->addResponse(
            new HTTP_Request2_Exception('A test error', 500)
        );

        $http = new HTTP_Request2();
        $http->setAdapter($httpMock);

        $sy = new Services_Yadis('http://example.org/openid');
        $sy->setHttpRequest($http);
        $xrds = $sy->discover();
    }
}
?>
