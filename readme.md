# CakePHP Middleware Authentication

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/burzum/cakephp-middleware-auth/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/burzum/cakephp-middleware-auth/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/burzum/cakephp-middleware-auth/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/burzum/cakephp-middleware-auth/?branch=master)

**Work in progress!**

This is a proof of concept to implement a middle ware based authentication. It's experimental - it will change and it will break.

## Quick Start

See the CakePHP [documentation](http://book.cakephp.org/3.0/en/controllers/middleware.html#) on how to use a middleware.

```php
// Instantiate the authentication service and configure authenticators
$service = new AuthenticationService([
    'authenticators' => [
        'Auth.Form'
    ]
]);

// Add it to the authentication middleware
$middleware = new AuthenticationMiddleware($service);
```
