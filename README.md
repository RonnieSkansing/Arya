> **WARNING:** Arya is still under development and there is very little unit-testing present at
> the moment. Use at your own risk.

# Arya

Arya is a minimalist PHP web SAPI framework providing URI routing, dependency injection and
middleware hooks. The framework leverages HTTP protocol adherence and S.O.L.I.D design principles to
maximize flexibility while maintaining the simplicity of PHP's static request model.

**IO as Design Philosophy**

An ideal web application architecture is little more than a callable receiving input (the request)
and returning output (the response). Arya allows the specification of any valid callable or instance
method as a URI route target. The framework acts as a simple front controller providing routing,
lazy-loading and automated dependency injection to service individual HTTP requests.

> The PHP web SAPI *is* a web application framework in its own right. Layering unnecessary components
> on top serves no purpose but vendor lock-in and performance degradation. Arya provides a baseline
> architecture for testable, performant applications with low coupling and high cohesion; the rest
> is up to you.

**Project Goals**

- Model all code as closely as possible on the HTTP/1.1 protocol outlined in RFC 2616;
- Build all components using S.O.L.I.D., readable and thoroughly unit-tested code;
- Prevent vendor lock-in by eschewing design decisions that tightly couple application callables
  to the framework;
- Minimize processing overhead because good architecture should not be synonymous with excessive
  performance degradation.

## Requirements

- PHP >= 5.3
- [Auryn](https://github.com/rdlowrey/Auryn) (Dependency Injection)

## Installation

**Github**

You can clone the latest Arya iteration at anytime from the github repository. By using the
`--recursive` option git will automatically retrieve the Auryn submodule dependency and store it in
the vendor directory.

```bash
$ git clone --recursive git://github.com/rdlowrey/Arya.git
```

**Composer**

@TODO

**Manual Download**

Archived tagged release versions are also available for manual download on the project
[tags page](https://github.com/rdlowrey/Arya/tags)

## The Guide

**Routing**

* [Standard Route Targets](#standard-route-targets)
* [Route Execution Paths](#route-execution-paths)
* [Extended Route Targets](#extended-route-targets)
* [Route Arguments](#route-arguments)

**Dependency Injection**

@TODO

**HTTP Protocol**

* [The HTTP Request](#the-http-request)
* [Simple Responses](#simple-responses)
* [The HTTP Response](#the-http-response)
* [Callable Response Bodies](#callable-response-bodies)

**Middleware**

* [Before](#before)
* [After](#after)
* [Finalize](#finalize)

**Other**

* [App Options](#app-options)
* [Debug Mode](#debug-mode)
* [Custom Error Handlers](#custom-error-handlers)

**Server Setup**

* [Apache](#apache)
* [Nginx](#nginx)
* [PHP Server (5.4+)](#php-server)


## Routing

### Standard Route Targets

An Arya route definition consists of exactly three elements:

- HTTP method verb
- URI path
- Target callable

Arya supports any valid [PHP callable](http://www.php.net/manual/en/language.types.callable.php) as
a route target. This means that you can specify function names, lambdas, static class methods and
array instance method constructions. Consider:

```php
<?php
$app = (new Arya\Application)
    ->route('GET', '/', 'myFunctionName')
    ->route('GET', '/lambda-hello', function() { return 'Hello world'; })
    ->route('POST', '/static-method', 'MyClass::myStaticPostHandler')
    ->route('GET', '/array-callback', array($someObject, 'someMethod'))
    ->run()
;
```

### Route Execution Paths

Every client request follows one of three paths through the routing system:

1. No request URI match is found: `404 Not Found`
2. A request URI is matched, but the HTTP verb does not match: `405 Method Not Allowed`
3. The request URI and HTTP method match a route and the associated target is invoked

> **NOTE:** HTTP method verbs are *case-sensitive* as defined in RFC 2616. Arya automatically
> normalizes method names specified at Argument 1 of `Application::route` to all-caps to avoid
> errors. This behavior may be optionally disabled should you wish to handle custom HTTP methods
> containing lower-case characters.

### Extended Route Targets

Thankfully, we aren't limited to standard callables in our route targes. Arya will also recursive
instantiate and provision classes subject to injection definitions you specify. This behavior makes
it possible to use the `ClassName::methodName` construction as a route target to benefit from
dependency injection without introducing anti-patterns like Service Locator or tightly coupling your
controllers to arbitrary framework implementations.

The biggest benefit from automatic controller provisioning is lazy-loading. By specifying
class-based controllers we can avoid loading our full application and autoload only the libraries
needed to handle the exact request. This is a much more sensible approach than loading an endless
list of closures or functions we won't ever need on each and every page load.

Arya's built-in dependency injection facility is discussed at length in the
[Dependency Injection](#dependency-injection) section but for now let's consider the following
simple example in which our `MyClass` route target is automatically instantiated and invoked for
requests to the `/` index resource:

```php
<?php
class Templater {
    function render($uri) {
        return "<html><body><p>Hello from {$uri}!</p></body></html>";
    }
}
class MyClass {
    private $templater;
    function __construct(Templater $tpl) {
        $this->templater = $tpl;
    }
    function get(Request $request) {
        return $this->templater->render($request['REQUEST_URI']);
    }
}

$app = (new Arya\Application)->route('GET', '/', 'MyClass::get')->run();
```

> **IMPORTANT:** Arya also recursively injects any dependencies you typehint in your controller
> method signatures. In the above example we use constructor injection to provide the `Templater`
> object that renders our HTML response. However, we could have alternatively typehinted the
> `Templater` in our `MyClass::get` method signature and injected it there.

### Route Arguments

@TODO Discuss route argument syntax
@TODO Discuss how args are always stored by name in `$request['ROUTE_ARGS']`
@TODO Discuss how args are also injected into the route target method signature if named params
      matching the argument names exist.



## Dependency Injection
@TODO

## HTTP Protocol

### The HTTP Request

Arya represents every request as an `Arya\Request` instance. This class provides an object-oriented
alternative to the superglobal anti-pattern present by default in PHP web SAPI environments.

**Request Mutability**

`Request` instances implement `ArrayAccess` to provide mutability for middleware callables. In this
way it's possible for middleware components to alter the request to perform actions such as URI
rewriting. Note that applications *always* have access to the original request information parsed
from superglobals at boot time.

**Request Provisioning**

Because route targets are automatically provisioned they must typehint the `Arya\Request` class in
their method signatures (extended instance method targets may also typehint in `__construct`). For
example, the following route targets demonstrate equivalent ways to ask for the request instance in
your application:

```php
<?php
use Arya\Application, Arya\Request;

function myFunctionTarget(Request $request) {
    return "Hello from " . $request['REQUEST_URI'];
}

class MyCtorRouteClass {
    private $request;
    function __construct(Request $request) {
        $this->request = $request;
    }
    function myTarget() {
        return "Hello from " . $this->request['REQUEST_URI'];
    }
}

class MyMethodRouteClass {
    function myTarget(Request $request) {
        return "Hello from " . $request['REQUEST_URI'];
    }
}

$app = (new Application)
    ->route('GET', '/function', 'myFunctionTarget')
    ->route('GET', '/my-ctor-route', 'MyCtorRouteClass::myTarget')
    ->route('GET', '/my-method-route', 'MyMethodRouteClass::myTarget')
    ->run()
;
```

### Simple Responses

One of the most user-friendly aspects of the PHP web SAPI is the ability to simply `echo` output as
your HTTP response entity body. This approach, however, is suboptimal in terms of testability and
enterprise sustainability. In the spirit of simplifying HTTP Arya allows application callables to
return strings directly to generate a standard 200 response. Consider:

```php
<?php
function helloWorld(Arya\Request $request) {
    return "Hello from " . $request['REQUEST_URI'];
}

$app = new Arya\Application;
$app->route('GET', '/', function(Arya\Request $request) {
    return "Hello from " . $request['REQUEST_URI'];
});
$app->run();
```

Arya actively prevents the manual output of response data via output buffering. If your application
generates any output (including error output) the result is a `500 Internal Server Error` response.
When in DEBUG mode you'll receive a helpful print-out containing the output data. In production
environments a generic 500 error message is displayed. This behavior is designed to funnel all
output through your route target return values so that "after" middleware callables have an
opportunity to inspect/modify output. Note that when in DEBUG mode this behavior also simplifies
n00b-core browser output debugging at runtime.

### The HTTP Response

When your application needs fine-grained control over the HTTP response a simple string is not enough.
In these cases route targets may return an instance of the `Arya\Response` class.

@TODO Talk about status code
@TODO Talk about headers
@TODO Talk about entity body

### Callable Response Bodies
@TODO

## Middleware
@TODO

### Before
@TODO

### After
@TODO

### Finalize
@TODO

## Other
@TODO Intro

### App Options
@TODO

### Debug Mode
@TODO

### Custom Error Handlers
@TODO

## Server Setup

Arya acts as a front-controller to route all requests. To make this work you must configure your
front-facing server to direct all requests to a single file.

### Apache

With Apache 2.2.16 or higher, you can use the FallbackResource directive in your configuration
file (.htaccess/httpd.conf/vhost.conf):

```
FallbackResource /index.php
```

If you have an older version of Apach you should instead add this block to your config file:

```
<IfModule mod_rewrite.c>
    Options -MultiViews

    RewriteEngine On
    #RewriteBase /path/to/app
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [QSA,L]
</IfModule>
```

## Nginx
@TODO

## PHP Server

As of PHP 5.4 you can use the built-in development server to quickly run your application:

```php
<?php // router.php

// Return FALSE if the request is for a static file
$filename = __DIR__.preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']);
if (php_sapi_name() === 'cli-server' && is_file($filename)) {
    return FALSE;
}

$app = (new Application)->route('GET', '/', function() { return 'Hello, world!'; })->run();
```

```bash
$ php -S localhost:8080 router.php
```