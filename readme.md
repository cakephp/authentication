# CakePHP Middleware Authentication

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.txt) 
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/burzum/cakephp-middleware-auth/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/burzum/cakephp-middleware-auth/?branch=master) 
[![Code Coverage](https://scrutinizer-ci.com/g/burzum/cakephp-middleware-auth/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/burzum/cakephp-middleware-auth/?branch=master)

[PSR7](http://www.php-fig.org/psr/psr-7/) Middleware authentication stack for the CakePHP framework.

Don't know what a middleware is? [Check the CakePHP documentation](http://book.cakephp.org/3.0/en/controllers/middleware.html) and additionally [read this.](https://philsturgeon.uk/php/2016/05/31/why-care-about-php-middleware/)

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
                'Auth.Orm'
            ],
            'authenticators' => [
                'Auth.Form',
                'Auth.Session'
            ]
        ]);
        
        // Add it to the authentication middleware
        $authentication = new AuthenticationMiddleware($service);
        
        // Add the middleware to the middleware stack
        $middleware->add($authentication);
    }
}
```

If one of the configured authenticators was able to validate the credentials the middeleware will add the authentication service to the request object as attribute.

If you're not yet familiar with request attributes [check the PSR7 documentation](http://www.php-fig.org/psr/psr-7/).

You can get the authenticated user credentials from the request by doing this:

```php
$authentication = $request->getAttribute('authentication');
$user = $authentication->getIdentity();
```

## Differences and similarities to the AuthComponent

### Differences

* There is no automatic checking of the session. To get the actual user data from the session you'll have to use the `Auth.Session` authenticator. It will check the session if there is data in the configured session key and put it into the identity object.
* The user data is no longer available  through the auth component but accessible via a request attribute and encapsulated in an identity object: `$request->getAttribute('authentication')->getIdentity();`
* The logic of the authentication process has been split into authenticators and identifiers. An authenticator will extract the credentials and check them againts a set of identifiers to actually verify and identify the request.

### Similarities

* All the existing authentication adapters, Form, Basic, Digest still use the same configuration options as they do in the old implementation for the AuthComponent.

## Migration from the AuthComponent

* Remove authentication from the Auth component and put the middleware in place like shown above and configure your authenticators the same way as you did for the Auth component before.
* Change your code to use the identity object instead of using `$this->Auth->user()`;
