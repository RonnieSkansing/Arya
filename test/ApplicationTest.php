<?php

namespace Arya\Test;

use Arya\Application;

class ApplicationTest extends \PHPUnit_Framework_TestCase {

    public function testConstructorSetsInjectorSoftDependencyOnProviderByDefault() {
        $application = new Application;
        $reflectionProperty = $this->getReflectedProperty($application, 'injector');
        $this->assertInstanceOf('Auryn\Provider', $reflectionProperty->getValue($application));
    }

    public function testConstructorSetstDebugToTrueByDefault() {
        $application = $this->getApplicationWithNulledArguments();
        $this->assertTrue($application->getOption('app.debug'));
    }

    public function testConstructorSetsApplicationOptionDebug() {
        $application = new Application(NULL, FALSE);
        $this->assertFalse($application->getOption('app.debug'));
    }

    public function testConstructorCastsDebugParameterToBoolean() {
        $application = new Application(NULL, 0);
        $this->assertFalse($application->getOption('app.debug'));
    }

    public function testConstructorSetsRoutingOptionCacheFileDefault() {
        $application = $this->getApplicationWithNulledArguments();
        $cacheAbsPath = $application->getOption('routing.cache_file');
        $this->assertSame(
            $cacheAbsPath,
            str_replace('test', 'lib', __DIR__ . '/../var/routes.cache')
        );
    }

    /**
    * @expectedException \DomainException
    */
    public function testGetOptionThrowsDomainExceptionIfUnknownOption() {
        $application = $this->getApplicationWithNulledArguments();
        $application->getOption('Foo');
    }

    public function testGetOptionGetter() {
        $application = $this->getApplicationWithNulledArguments();
        $this->assertTrue($application->getOption('app.debug'));
    }

    /**
    * @expectedException \DomainException
    */
    public function testSetOptionThrowsDomainExceptionIfOptionIsUnknown() {
        $application = $this->getApplicationWithNulledArguments();
        $application->setOption('Foo', 42);
    }

    /**
    *   @expectedException \InvalidArgumentException
    */
    public function testSetOptionThrowsInvalidArgumentExceptionIfOptionSessionClassIsNotString() {
        $application = $this->getApplicationWithNulledArguments();
        $application->setOption('session.class', 42);
    }

    /**
    *   @expectedException \LogicException
    */
    public function testSetOptionThrowsLogicExceptionIfOptionSessionClassIsDoesNotExistsAsClass() {
        $application = $this->getApplicationWithNulledArguments();
        $application->setOption('session.class', 'Foo\bar\Baz\quuX');
    }

    public function testSetOptionSetsOptionSessionClassIfOptionIsSessionClass() {
        $injectorMock = $this->getMockedAurynInjector();
        $injectorMock->expects( $this->once() )->method('alias')->with('Arya\Sessions\SessionHandler', 'StdClass');
        $application = new Application($injectorMock, NULL);
        $application->setOption('session.class' , 'StdClass');
        $this->assertSame('StdClass', $application->getOption('session.class'));
    }

    /**
    *   @expectedException \InvalidArgumentException
    *   @expectedExceptionMesssage session.class requires a string; integer provided
    */
    public function testSetOptionThrowsInvalidArgumentExceptionIfOptionSessionSavePathIsNotString() {
        $application = $this->getApplicationWithNulledArguments();
        $application->setOption('session.save_path', 42);
    }

    /**
    *   @expectedException \InvalidArgumentException
    *   @expectedExceptionMesssage session.save_path requires a writable directory path: /___\_Arya___\_/__\__noFolder__\_!
    */
    public function testSetOptionThrowsLogicExceptionIfOptionNotValidAndWrittableDirectory() {
        $application = $this->getApplicationWithNulledArguments();
        $application->setOption('session.save_path', '/___\_Arya___\_/__\__noFolder__\_!');
    }

    public function testSetOptionSetsOptionSessionSavePathIfOptionIsSessionSavePath() {
        $injectorMock = $this->getMockedAurynInjector();
        $injectorMock->expects( $this->once() )->method('define')->with(
            'Arya\Sessions\FileSessionHandler',
                [':dir' => __DIR__]
            );
        $application = new Application($injectorMock, NULL);
        $application->setOption('session.save_path' , __DIR__);
        $this->assertSame(__DIR__, $application->getOption('session.save_path'));
    }

    public function testSetOptionSetter() {
        $application = $this->getApplicationWithNulledArguments();
        $application->setOption('session.cookie_name', 'ayrA');
        $this->assertSame('ayrA', $application->getOption('session.cookie_name'));
    }

    public function testSetOptionIsChainable() {
        $application = $this->getApplicationWithNulledArguments();
        $application
            ->setOption('session.cookie_name', 'ayrA')
            ->setOption('session.cookie_domain', 'mydomain.com');
        $this->assertSame(
            ['ayrA', 'mydomain.com'],
            [$application->getOption('session.cookie_name'), $application->getOption('session.cookie_domain')]
        );
    }

    public function testSetAllOptionsSetter() {
        $options = [
            'session.cookie_name' => 'ayrA',
            'session.cookie_domain' => 'mydomain.com'
        ];
        $application = $this->getApplicationWithNulledArguments();
        $application->setAllOptions($options);
        $this->assertSame($options['session.cookie_name'], $application->getOption('session.cookie_name'));
        $this->assertSame($options['session.cookie_domain'], $application->getOption('session.cookie_domain'));
    }

    public function testRouteSetsCanSerializeRoutesToFalseIfHandlerIsClosure() {
        $application = $this->getApplicationWithNulledArguments();
        $application->route('GET', '/', function() {});
        $reflectionProperty = $this->getReflectedProperty($application, 'canSerializeRoutes');
        $this->assertFalse($reflectionProperty->getValue($application));
    }

    public function testRouteAddsRoute() {
        $application = $this->getApplicationWithNulledArguments();
        $closure = function(){};
        $application->route('GET', '/foo', $closure);
        $reflectionProperty = $this->getReflectedProperty($application, 'routes');
        $this->assertSame(
            ['GET', '/foo', $closure],
            $reflectionProperty->getValue($application)[0]
        );
    }

    public function testRouteIsChainable() {
        $application = $this->getApplicationWithNulledArguments();
        $closure = function(){};
        $application
            ->route('GET', '/foo', $closure)
            ->route('POST', '/bar', $closure);
        $reflection = new \ReflectionClass($application);
        $reflectionProperty = $reflection->getProperty('routes');
        $reflectionProperty->setAccessible(TRUE);
        $this->assertSame(
            ['GET', '/foo', $closure],
            $reflectionProperty->getValue($application)[0]
        );
        $this->assertSame(
            ['POST', '/bar', $closure],
            $reflectionProperty->getValue($application)[1]
        );
    }

    public function testBeforeArgumentOptionMethodIsSetToNullIfEmpty() {
        $method = '';
        $middleware = 'middleware';
        $application = $this->getApplicationWithNulledArguments();
        $application->before($middleware, $this->generateMiddleware($method));
        $reflectionProperty = $this->getReflectedProperty($application, 'befores');
        $this->assertNull($reflectionProperty->getValue($application)[0][1]);
    }

    public function testBeforeArgumentOptionUriIsSetToNullIfEmpty() {
        $uri = FALSE;
        $middleware = 'middleware';
        $application = $this->getApplicationWithNulledArguments();
        $application->before($middleware, $this->generateMiddleware(NULL, $uri));
        $reflectionProperty = $this->getReflectedProperty($application, 'befores');
        $this->assertNull($reflectionProperty->getValue($application)[0][2]);
    }

    public function testBeforeArgumentOptionSetToInteger() {
        $priority = '3';
        $middleware = 'middleware';
        $application = $this->getApplicationWithNulledArguments();
        $application->before($middleware, $this->generateMiddleware(NULL, NULL, $priority));
        $reflectionProperty = $this->getReflectedProperty($application, 'befores');
        $this->assertSame(3, $reflectionProperty->getValue($application)[0][3]);
    }

    public function testAfterArgumentOptionMethodIsSetToNullIfEmpty() {
        $method = '';
        $middleware = 'middleware';
        $application = $this->getApplicationWithNulledArguments();
        $application->after($middleware, $this->generateMiddleware($method));
        $reflectionProperty = $this->getReflectedProperty($application, 'afters');
        $this->assertNull($reflectionProperty->getValue($application)[0][1]);
    }

    public function testAfterArgumentOptionUriIsSetToNullIfEmpty() {
        $uri = FALSE;
        $middleware = 'middleware';
        $application = $this->getApplicationWithNulledArguments();
        $application->after($middleware, $this->generateMiddleware(NULL, $uri));
        $reflectionProperty = $this->getReflectedProperty($application, 'afters');
        $this->assertNull($reflectionProperty->getValue($application)[0][2]);
    }

    public function testAfterArgumentOptionSetToInteger() {
        $priority = '3';
        $middleware = 'middleware';
        $application = $this->getApplicationWithNulledArguments();
        $application->after($middleware, $this->generateMiddleware(NULL, NULL, $priority));
        $reflectionProperty = $this->getReflectedProperty($application, 'afters');
        $this->assertSame(3, $reflectionProperty->getValue($application)[0][3]);
    }

    public function testFinalizeArgumentOptionMethodIsSetToNullIfEmpty() {
        $method = '';
        $middleware = 'middleware';
        $application = $this->getApplicationWithNulledArguments();
        $application->finalize($middleware, $this->generateMiddleware($method));
        $reflectionProperty = $this->getReflectedProperty($application, 'finalizers');
        $this->assertNull($reflectionProperty->getValue($application)[0][1]);
    }

    public function testFinalizeArgumentOptionUriIsSetToNullIfEmpty() {
        $uri = NuLL;
        $middleware = 'middleware';
        $application = $this->getApplicationWithNulledArguments();
        $application->finalize($middleware, $this->generateMiddleware(NULL, $uri));
        $reflectionProperty = $this->getReflectedProperty($application, 'finalizers');
        $this->assertNull($reflectionProperty->getValue($application)[0][2]);
    }

    public function testFinalizeArgumentOptionSetToInteger() {
        $priority = '3';
        $middleware = 'middleware';
        $application = $this->getApplicationWithNulledArguments();
        $application->Finalize($middleware, $this->generateMiddleware(NULL, NULL, $priority));
        $reflectionProperty = $this->getReflectedProperty($application, 'finalizers');
        $this->assertSame(3, $reflectionProperty->getValue($application)[0][3]);
    }

    private function getReflectedProperty($class, $property) {
        $reflection = new \ReflectionClass($class);
        $reflectionProperty = $reflection->getProperty($property);
        $reflectionProperty->setAccessible(TRUE);
        return $reflectionProperty;
    }

    private function getMockedRequest() {
        $stubBuilder = $this->getMockBuilder('Arya\Request');
        $stubBuilder->disableOriginalConstructor();

        return $stubBuilder->getMock();
    }

    private function generateMiddleware($method = 'method', $uri = 'uri', $priority = 10) {
        return [
            'method' => $method,
            'uri' => $uri,
            'priority' => $priority
        ];
    }

    private function getMockedAurynInjector() {
        $stubBuilder = $this->getMockBuilder('\Auryn\Injector');
        $stubBuilder->disableOriginalConstructor();

        return $stubBuilder->getMock();
    }

    private function getApplicationWithNulledArguments() {
        return new Application($this->getMockedAurynInjector(),NULL);
    }
}
