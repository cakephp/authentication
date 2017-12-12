# Quick Start

## Installation

Install the plugin with [composer](https://getcomposer.org/) from your CakePHP Project's ROOT directory (where the **composer.json** file is located)
```sh
php composer.phar require cakephp/authentication
```

Load the plugin by adding the following statement in your project's `config/bootstrap.php`
```php
Plugin::load('Authentication');
```

## Configuration

Add the authentication to the middleware. See the CakePHP [documentation](http://book.cakephp.org/3.0/en/controllers/middleware.html#) on how to use middleware if you don't know what it is or how to work with it.

Example of configuring the authentication middleware using `authentication` application hook.

```php
use Authentication\AuthenticationService;
use Authentication\Middleware\AuthenticationMiddleware;

class Application extends BaseApplication
{
    public function authentication($service)
    {
        $fields = [
            'username' => 'email',
            'password' => 'password'
        ];

        // Load identifiers
        $service->loadIdentifier('Authentication.Password', compact('fields'));

        // Load the authenticators, you want session first
        $service->loadAuthenticator('Authentication.Session');
        $service->loadAuthenticator('Authentication.Form', [
            'fields' => $fields,
            'loginUrl' => [
                'plugin' => false,
                'controller' => 'Users',
                'action' => 'login',
            ]
        ]);

        return $service;
    }

    public function middleware($middleware)
    {
        // Various other middlewares for error handling, routing etc. added here.

        // Add the authentication middleware
        $authentication = new AuthenticationMiddleware($this);

        // Add the middleware to the middleware queue
        $middleware->add($authentication);

        return $middleware;
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
$service->loadIdentifier('Authentication.Password', [
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

## Authentication Component

You can use the `AuthenticationComponent` to access the result of authentication,
get user identity and logout user. Load the component in your `AppController::initialize()`
like any other component.

```php
$this->loadComponent('Authentication.Authentication', [
    'logoutRedirect' => '/users/login'  // Default is false
]);
```

## Accessing the user / identity data

You can get the authenticated identity data using the authentication component:

```php
$user = $this->Authentication->getIdentity();
```

You can also get the identity directly from the request instance:

```php
$user = $request->getAttribute('identity');
```

## Checking the login status

You can check if the authentication process was successful by accessing the result
object:

```php
// Using Authentication component
$result = $this->Authentication->getResult();

// Using request object
$result = $request->getAttribute('authentication')->getResult();

if ($result->isValid()) {
    $user = $request->getAttribute('identity');
} else {
    $this->log($result->getCode());
    $this->log($result->getErrors());
}
```

The result sets objects code returned from `getCode()` will match one of these codes. The codes are implemented as class constants in the Result object.

 * `'SUCCESS'`, value of `Result::SUCCESS`, when successful.
 * `'FAILURE'`, value of `Result::FAILURE`, on any general failure.
 * `'FAILURE_IDENTITY_NOT_FOUND'`, value of `Result::FAILURE_IDENTITY_NOT_FOUND`, when identify could not be found.
 * `'FAILURE_CREDENTIALS_INVALID'`, value of `Result::FAILURE_CREDENTIALS_INVALID`, when credentials are invalid.
 * `'FAILURE_CREDENTIALS_NOT_IN_REQUEST'`, value of `Result::FAILURE_CREDENTIALS_NOT_IN_REQUEST`, when credentials are not found in request.
 * `'FAILURE_OTHER'`, value of `Result::FAILURE_OTHER`, on any other kind of failure.

The error array returned by `getErrors()` contains *additional* information coming from the specific system against which the authentication attempt was made. For example LDAP or OAuth would put errors specific to their implementation in here for easier logging and debugging the cause. But most of the included authenticators don't put anything in here.

## Clearing the identity / logging the user out

To log an identity out just do:

```php
$this->Authentication->logout();
```

If you have set the `loginRedirect` config, `Authentication::logout()` will
return that value else will return `false`. It won't perform any actual redirection
in either case.

Alternatively, instead of the component you can also use the request instance to log out:

```php
$return = $request->getAttribute('authentication')->clearIdentity($request, $response);
debug($return);
```

The debug will show you an array like this:

```
[
    'response' => object(Cake\Http\Response) { ... },
    'request' => object(Cake\Http\ServerRequest) { ... }
]
```

**Attention!** This will return an array containing the request and response objects. Since both are immutable you'll get new objects back. Depending on your context you're working in you'll have to use these instances from now on if you want to continue to work with the modified response and request objects.
