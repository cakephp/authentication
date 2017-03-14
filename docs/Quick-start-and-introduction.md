# Quick Start

Add the authentication service to the middleware. See the CakePHP [documentation](http://book.cakephp.org/3.0/en/controllers/middleware.html#) on how to use a middleware if you don't know what it is or how to work with it.

## Configuration

Example of configuring the authentication middleware.

```php
use Authentication\AuthenticationService;
use Authentication\Middleware\AuthenticationMiddleware;

class Application extends BaseApplication
{
    public function middleware($middlewareQueue)
    {
        // Various other middlewares for error handling, routing etc. added here.

        // Instantiate the service
        $service = new AuthenticationService();

        // Load identifiers
        $service->loadIdentifier('Authentication.Orm', [
            'fields' => [
                'username' => 'email',
                'password' => 'password'
            ]
        ]);

        // Load the authenticators, you want session first
        $service->loadAuthenticator('Authentication.Session');
        $service->loadAuthenticator('Authentication.Form');

        // Add it to the authentication middleware
        $authentication = new AuthenticationMiddleware($service);

        // Add the middleware to the middleware queue
        $middlewareQueue->add($authentication);

        return $middlewareQueue;
    }
}
```

If one of the configured authenticators was able to validate the credentials,
the middleware will add the authentication service to the request object as an
attribute. If you're not yet familiar with request attributes [check the PSR7
documentation](http://www.php-fig.org/psr/psr-7/).

## Using Stateless Authenticators with other Authenticators

When using `HttpBasic` or `HttpDigest` with other authenticators, you should
remember that these authenticators will halt the request when authentication
credentials are missing or invalid. This is necessary as these authenticators
must send specific challenge headers in the response. If you want to combine
`HttpBasic` or `HttpDigest` with other authenticators, you may want to configure
these authenticators as the *last* authenticators:

```php
use Authentication\AuthenticationService;

// Instantiate the service
$service = new AuthenticationService();

// Load identifiers
$service->loadIdentifier('Authentication.Orm', [
    'fields' => [
        'username' => 'email',
        'password' => 'password'
    ]
]);

// Load the authenticators leaving Basic as the last one.
$service->loadAuthenticator('Authentication.Session');
$service->loadAuthenticator('Authentication.Form');
$service->loadAuthenticator('Authentication.HttpBasic');
```

## Accessing the user / identity data

You can get the authenticated identity data from the request by doing this:

```php
$user = $request->getAttribute('identity');
```

## Checking the login status

You can check if the authentication process was successful by accessing the result object of the authentication process that comes as well as a request attribute:

```php
$result = $request->getAttribute('authentication')->getResult();
if ($result->isValid()) {
    $user = $request->getAttribute('identity');
} else {
    $this->log($result->getCode());
    $this->log($result->getErrors());
}
```

The result sets objects code returned from `getCode()` will match one of these:

 * 1 - Success
 * 0 - Failure
 * -1 - Identify not found
 * -2 - Credentials invalid
 * -3 - Any other kind of failure
 * -4 - Credentials not found

The codes are implemented as class constants in the Result object.

The error array returned by `getErrors()` contains *additional* information coming from the specific system against which the authentication attempt was made. For example LDAP or OAuth would put errors specific to their implementation in here for easier logging and debugging the cause. But most of the included authenticators don't put anything in here.

## Clearing the identity / logging the user out

To log an identity out just call the services clearIdentity() method:

```php
$result = $request->getAttribute('authentication')->clearIdentity($request, $response);
debug($result);
```

The debug will show you an array like this:

```
[
    'response' => object(Cake\Http\Response) { ... },
    'request' => object(Cake\Http\ServerRequest) { ... }
]
```

**Attention!** This will return an array containing the request and response objects. Since both are immutable you'll get new objects back. Depending on your context you're working in you'll have to use these instances from now on if you want to continue to work with the modified response and request objects.
