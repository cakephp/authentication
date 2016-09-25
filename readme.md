# CakePHP Middleware Authentication

**Work in progress!**

This is a proof of concept to implement a middle ware based authentication.

It requires the `3.next` branch of CakePHP and will probably break a few times due to ongoing changes.

## How to use it

Use it like any other middleware, see the book.

The configuration options take an array *authenticators* you can configure that will handle the authentication.

```php
class Application extends BaseApplication {

	public function middleware($middleware) {
		// Bind the error handler into the middleware queue.
		$middleware->add(new ErrorHandlerMiddleware([
			Configure::read('Error.exceptionRenderer')
		]));

		// Assets
		$middleware->add(new AssetMiddleware());

		// Routing
		$middleware->add(new RoutingMiddleware());

        // Authentication
        $middleware = new AuthenticationMiddleware([
            'authenticators' => [
                'Auth.Form'
            ]
        ]);

		return $middleware;
	}

}
```
