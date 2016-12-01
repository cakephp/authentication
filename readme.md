# CakePHP Authentication

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.txt)
[![Build Status](https://img.shields.io/travis/cakephp/authentication/master.svg?style=flat-square)](https://travis-ci.org/cakephp/authentication)
[![Coverage Status](https://img.shields.io/codecov/c/github/cakephp/authentication.svg?style=flat-square)](https://codecov.io/github/cakephp/authentication)

[PSR7](http://www.php-fig.org/psr/psr-7/) Middleware authentication stack for the CakePHP framework.

Don't know what a middleware is? [Check the CakePHP documentation](http://book.cakephp.org/3.0/en/controllers/middleware.html) and additionally [read this.](https://philsturgeon.uk/php/2016/05/31/why-care-about-php-middleware/)

This plugin is for CakePHP 3.3+. 
If your application existed before (<= CakePHP 3.2), please make sure it is adjusted to leverage middleware as described in the [docs](http://book.cakephp.org/3.0/en/controllers/middleware.html#adding-the-new-http-stack-to-an-existing-application).

## Quick Start

Add the authentication service to the middleware. See the CakePHP [documentation](http://book.cakephp.org/3.0/en/controllers/middleware.html#) on how to use a middleware if you don't know what it is or how to work with it.

```php
class Application extends BaseApplication
{
    public function middleware($middleware)
    {
        // Instantiate the authentication service and configure authenticators
        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Orm' => [
                    'fields' => [
                        'username' => 'email',
                        'password' => 'password'
                    ]
                ]
            ],
            'authenticators' => [
                'Authentication.Form',
                'Authentication.Session'
            ]
        ]);

        // Add it to the authentication middleware
        $authentication = new AuthenticationMiddleware($service);

        // Add the middleware to the middleware stack
        $middleware->add($authentication);
    }
}
```

If one of the configured authenticators was able to validate the credentials, the middeleware will add the authentication service to the request object as attribute.

If you're not yet familiar with request attributes [check the PSR7 documentation](http://www.php-fig.org/psr/psr-7/).

You can get the authenticated user credentials from the request by doing this:

```php
$user = $request->getAttribute('identity');
```

You can check if the authentication process was successful by accessing the result object of the authentication process that comes as well as a request attribute:

```php
$auth = $request->getAttribute('authentication');
if ($auth->isValid()) {
    $user = $request->getAttribute('identity');
} else {
    $this->log($auth->getCode());
    $this->log($auth->getErrors());
}
```

## Migration from the AuthComponent

### Differences

* There is no automatic checking of the session. To get the actual user data from the session you'll have to use the `SessionAuthenticator`. It will check the session if there is data in the configured session key and put it into the identity object.
* The user data is no longer available through the AuthComponent but is accessible via a request attribute and encapsulated in an identity object: `$request->getAttribute('authentication')->getIdentity();`
* The logic of the authentication process has been split into authenticators and identifiers. An authenticator will extract the credentials from the request, while identifiers verify the credentials and find the matching user.
* DigestAuthenticate has been renamed to HttpDigestAuthenticator
* BasicAuthenticate has been renamed to HttpBasicAuthenticator

### Similarities

* All the existing authentication adapters, Form, Basic, Digest are still there but have been refactored into authenticators.

### Identifiers and authenticators

Following the principle of separation of concerns, the former authentication objects were split into separate objects, identifiers and authenticators.

* **Authenticators** take the incoming request and try to extract identification credentials from it. If credentials are found, they are passed to a collection of identifiers where the user is located. For that reason authenticators take an IdentifierCollection as first constructor argument.
* **Identifiers** are verify identification credentials against a storage system. eg. (ORM tables, LDAP etc) and return identified user data.

This makes it easy to change the identification logic as needed or use several sources of user data.

If you want to implement your own identifiers, your identifier must implement the `IdentifierInterface`.

### Migrating your authentication setup

Remove authentication from the AuthComponent and put the middleware in place like shown above. Then configure your authenticators the same way as you did for the AuthComponent before.

Change your code to use the identity data from the `identity` request attribute instead of using `$this->Auth->user();`. The returned value is null if no identity was found or the identification of the provided credentials failed.

```php
$user = $request->getAttribute('identity');
```

For more details about the result of the authentication process you can access the result object that also comes with the request and is accessible as `authentication` attribute.

```php
$auth = $request->getAttribute('authentication');
// Bool if the result is valid
debug($auth->isValid());
// A status code
debug($auth->getCode());
// An array of error messages or data if the identifier provided any
debug($auth->getError());
```

The huge config array from the AuthComponent needs to be split into identifiers and authenticators when configuring the service. So when you had your AuthComponent configured this way

```php
$this->loadComponent('Auth', [
    'authentication' => [
        'Form' => [
            'fields' => [
                'username' => 'email',
                'password' => 'password'
            ]
        ]
    ]
]);
```

you'll now have to configure it this way.

```php
$service = new AuthenticationService([
    'identifiers' => [
        'Authentication.Orm' => [
            'fields' => [
                'username' => 'email',
                'password' => 'password'
            ]
        ]
    ],
    'authenticators' => [
        'Authentication.Session',
        'Authentication.Form'
    ]
]);
```

While this seems to be a little more to write, the benefit is a greater flexibility and better [separation of concerns](https://en.wikipedia.org/wiki/Separation_of_concerns).

## Authenticators

### Token

The token authenticator can authenticate a request based on a token that comes along with the request in the headers or in the request parameters.

Configuration options:

* **queryParam**: Name of the query parameter. Configure it if you want to get the token from the query parameters.
* **header**: Name of the header. Configure it if you want to get the token from the header.

### Session

This authenticator will check the session if it contains user data or credentials

Configuration options:

* **sessionKey**: The session key for the user data, default is `Auth` 

### Form

Looks up the data in the request body, usually when a form submit happens via POST / PUT.

Configuration options:

* **fields**: Array that maps `username` and `password` to the specified fields.
* **passwordHasher**: Password hasher class, defaults to `DefaultPasswordHasher::class`

### HttpBasic

See https://en.wikipedia.org/wiki/Basic_access_authentication

Configuration options:

* **realm**: Default is `$_SERVER['SERVER_NAME']` override it as needed.

### HttpDigest

See https://en.wikipedia.org/wiki/Digest_access_authentication

Configuration options:

* **realm**: Default is `null` 
* **qop**: Default is `auth`
* **nonce**: Default is `uniqid(''),`
* **opaque**: Default is `null`
