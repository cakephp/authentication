# CakePHP Middleware Authentication

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/burzum/cakephp-middleware-auth/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/burzum/cakephp-middleware-auth/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/burzum/cakephp-middleware-auth/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/burzum/cakephp-middleware-auth/?branch=master)

This is a proof of concept to implement a middleware based authentication. **It's still work in progress, don't use it in production!**

## Quick Start

See the CakePHP [documentation](http://book.cakephp.org/3.0/en/controllers/middleware.html#) on how to use a middleware.

```php
class Application extends BaseApplication
{
    public function middleware($middleware)
    {
        // Instantiate the authentication service and configure authenticators
        $service = new AuthenticationService([
            'authenticators' => [
                'Auth.Form'
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

```php
$authentication = $request->getAttribute('authentication');
$user = $authentication->getIdentity();
```
