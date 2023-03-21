<?php
class Services_YadisTest extends \PHPUnit\Framework\TestCase
{
    public function testGetException()
    {
        $this->expectException(Services_Yadis_Exception::class);
        $this->expectExceptionMessage('Invalid response to Yadis protocol received: A test error');
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
