<?php

namespace Arya\Test;

use Arya\Sessions\SessionMiddlewareProxy;

class SessionMiddlewareProxyTest extends \PHPUNIT_Framework_TestCase {

    public function testConstructSetsMiddlewarePriorityOptionByDefaultTo20() {
        $sessionHandlerMock = $this->getFileSessionHandlerMock();
        $requestMock = $this->getRequestMock();
        $applicationMock = $this->getApplicationMock();
        $applicationMock->expects($this->any())->method('after')->with(
            $this->equalTo(function(){}),
            $this->equalTo([
               'priority' => 20,
                'uri' => Null,
                'method' => Null
            ]
        ));
        new SessionMiddlewareProxy($applicationMock, $requestMock, $sessionHandlerMock);
    }

    public function testConstructSetsMiddlewareMethodAndUriOptionFromRequest() {
        $uri = 'foo';
        $method = 'bar';
        $sessionHandlerMock = $this->getFileSessionHandlerMock();
        $requestMock = $this->getRequestMock();
        $requestMock->expects($this->any())->method('offsetGet')->will(
            $this->onConsecutiveCalls($uri, $method)
        );
        $applicationMock = $this->getApplicationMock();
        $applicationMock->expects($this->any())->method('after')->with(
            $this->equalTo(function(){}),
            $this->equalTo([
               'priority' => 20,
                'uri' => $uri,
                'method' => $method
            ]
        ));
        new SessionMiddlewareProxy($applicationMock, $requestMock, $sessionHandlerMock);
    }

    public function testConstructArgumentPriorityChangesPriority() {
        $priority = 99;
        $sessionHandlerMock = $this->getFileSessionHandlerMock();
        $requestMock = $this->getRequestMock();
        $applicationMock = $this->getApplicationMock();
        $applicationMock->expects($this->any())->method('after')->with(
            $this->equalTo(function(){}),
            $this->equalTo([
               'priority' => $priority,
                'uri' => Null,
                'method' => Null
            ]
        ));
        new SessionMiddlewareProxy($applicationMock, $requestMock, $sessionHandlerMock, $priority);
    }

    public function testAfterMiddlewarePassedClosureIntegration() {
        $boxedOptions = ['foo', 'bar', []];
        $sessionHandlerMock = $this->getBlackHoleFileSessionHandler();
        $requestMock = $this->getRequestMock();
        $applicationMock = $this->getApplicationMock();
        $responseMock = $this->getResponseMock();
        $sessionMiddlewareProxyStubBuilder = $this->getMockBuilder('\Arya\Sessions\SessionMiddlewareProxy');
        $sessionMiddlewareProxyStubBuilder->setConstructorArgs([$applicationMock, $requestMock, $sessionHandlerMock]);
        $sessionMiddlewareProxyMock = $sessionMiddlewareProxyStubBuilder->getMock();
        $sessionMiddlewareProxyMock->expects($this->once())->method('getCookieElements')->will($this->returnValue($boxedOptions));
        $sessionMiddlewareProxyMock->expects($this->once())->method('shouldSetCookie')->will($this->returnValue(TRUE));
        $sessionMiddlewareProxyMock->expects($this->any())->method('getOption')->will($this->returnCallBack(function($option) {
            switch($option) {
                case 'cache_expire':

                    return 42;
                case 'cache_limiter':

                    return 'private_no_expire';
            }
        }));
        $responseMock->expects($this->once())->method('setHeader')->with(
            'Cache-Control',
            'private_no_expire, max-age=42, pre-check=42'
        );
        $applicationMock->expects($this->once())->method('after')->will($this->returnCallback(function($closure) use ($requestMock, $responseMock) {
            $closure($requestMock, $responseMock);
        }));
        // recall construct to recall after in constructor to allow for running the closure itself with configured test doubles
        $sessionMiddlewareProxyMock->__construct($applicationMock, $requestMock, $sessionHandlerMock);
    }

    private function getApplicationMock() {
        $applicationStubBuilder = $this->getMockBuilder('\Arya\Application');
        $applicationStubBuilder->disableOriginalConstructor();
        $applicationMock = $applicationStubBuilder->getMock();

        return  $applicationMock;
    }

    private function getRequestMock() {
        $requestStubBuilder = $this->getMockBuilder('\Arya\Request');
        $requestStubBuilder->disableOriginalConstructor();

        return $requestStubBuilder->getMock();
    }

    private function getResponseMock() {
        $requestStubBuilder = $this->getMockBuilder('\Arya\Response');
        $requestStubBuilder->disableOriginalConstructor();

        return $requestStubBuilder->getMock();
    }

    private function getFileSessionHandlerMock() {
        $requestStubBuilder = $this->getMockBuilder('\Arya\Sessions\FileSessionHandler');
        $requestStubBuilder->disableOriginalConstructor();

        return $requestStubBuilder->getMock();
    }

    private function getBlackHoleFileSessionHandler() {
        $fileSessionHandlerMock = $this->getFileSessionHandlerMock();
        $fileSessionHandlerMock->expects($this->any())->method('exists')->will($this->returnValue(True));
        $fileSessionHandlerMock->expects($this->any())->method('open')->will($this->returnValue(True));
        $fileSessionHandlerMock->expects($this->any())->method('write')->will($this->returnValue(True));
        $fileSessionHandlerMock->expects($this->any())->method('save')->will($this->returnValue(True));
        $fileSessionHandlerMock->expects($this->any())->method('close')->will($this->returnValue(True));
        $fileSessionHandlerMock->expects($this->any())->method('destroy')->will($this->returnValue(True));

        return $fileSessionHandlerMock;
    }

}
