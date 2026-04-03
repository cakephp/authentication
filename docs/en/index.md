# Quick Start

Install the plugin with [composer](https://getcomposer.org/) from your CakePHP
Project's ROOT directory (where the **composer.json** file is located)

``` bash
php composer.phar require cakephp/authentication
```

Version 4 of the Authentication Plugin is compatible with CakePHP 5.

Load the plugin using the following command:

``` shell
bin/cake plugin load Authentication
```

## Getting Started

The authentication plugin integrates with your application as a [middleware](https://book.cakephp.org/5/en/controllers/middleware.html). It can also
be used as a component to make unauthenticated access simpler. First, let's
apply the middleware. In **src/Application.php**, add the following to the class
imports:

``` php
use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\AuthenticationServiceProviderInterface;
use Authentication\Identifier\PasswordIdentifier;
use Authentication\Middleware\AuthenticationMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;
```

Next, add `AuthenticationServiceProviderInterface` to the implemented interfaces
on your application:

```php
class Application extends BaseApplication implements AuthenticationServiceProviderInterface
```

Then update your application's `middleware()` method to look like:

``` php
public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
{
    $middlewareQueue->add(new ErrorHandlerMiddleware(Configure::read('Error')))
        // Other middleware that CakePHP provides.
        ->add(new AssetMiddleware())
        ->add(new RoutingMiddleware($this))
        ->add(new BodyParserMiddleware())

        // Add the AuthenticationMiddleware. It should be
        // after routing and body parser.
        ->add(new AuthenticationMiddleware($this));

    return $middlewareQueue;
}
```

> [!WARNING]
> The order of middleware is important. Ensure that you have
> `AuthenticationMiddleware` after the routing and body parser middleware.
> If you're having trouble logging in with JSON requests or redirects are
> incorrect double check your middleware order.

`AuthenticationMiddleware` will call a hook method on your application when
it starts handling the request. This hook method allows your application to
define the `AuthenticationService` it wants to use. Add the following method to your
**src/Application.php**:

``` php
/**
 * Returns a service provider instance.
 *
 * @param \Psr\Http\Message\ServerRequestInterface $request Request
 * @return \Authentication\AuthenticationServiceInterface
 */
public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
{
    $service = new AuthenticationService();

    // Define where users should be redirected to when they are not authenticated
    $service->setConfig([
        'unauthenticatedRedirect' => [
                'prefix' => false,
                'plugin' => false,
                'controller' => 'Users',
                'action' => 'login',
        ],
        'queryParam' => 'redirect',
    ]);

    $fields = [
        PasswordIdentifier::CREDENTIAL_USERNAME => 'email',
        PasswordIdentifier::CREDENTIAL_PASSWORD => 'password',
    ];

    // Load the authenticators. Session should be first.
    // Session just uses session data directly as identity, no identifier needed.
    $service->loadAuthenticator('Authentication.Session');
    $service->loadAuthenticator('Authentication.Form', [
        'identifier' => [
            'Authentication.Password' => [
                'fields' => $fields,
            ],
        ],
        'fields' => $fields,
        'loginUrl' => Router::url([
            'prefix' => false,
            'plugin' => false,
            'controller' => 'Users',
            'action' => 'login',
        ]),
    ]);

    return $service;
}
```

First, we configure what to do with users when they are not authenticated.
Next, we attach the `Session` and `Form` [Authenticators](authenticators) which define the
mechanisms that our application will use to authenticate users. `Session` enables us to identify
users based on data in the session - it uses the session data directly as identity without any
database lookup. `Form` enables us to handle a login form at the `loginUrl` and uses an
[identifier](identifiers) to convert the credentials users will give us into an
[identity](identity-object) which represents our logged in user.

If one of the configured authenticators was able to validate the credentials,
the middleware will add the authentication service to the request object as an
[attribute](https://www.php-fig.org/psr/psr-7/).

Next, in your `AppController` load the [Authentication Component](authentication-component):

``` php
// in src/Controller/AppController.php
public function initialize(): void
{
    parent::initialize();

    $this->loadComponent('Authentication.Authentication');
}
```

By default the component will require an authenticated user for **all** actions.
You can disable this behavior in specific controllers using
`allowUnauthenticated()`:

``` php
// in a controller beforeFilter or initialize
// Make view and index not require a logged in user.
$this->Authentication->allowUnauthenticated(['view', 'index']);
```

## Building a Login Action

Once you have the middleware applied to your application you'll need a way for
users to login. Please ensure your database has been created with the Users table structure used in the [CMS tutorial](https://book.cakephp.org/5/en/tutorials-and-examples/cms/database.html). First generate a Users model and controller with bake:

``` bash
bin/cake bake model Users
bin/cake bake controller Users
```

Then, we'll add a basic login action to your `UsersController`. It should look
like:

``` php
// in src/Controller/UsersController.php
public function login(): ?\Cake\Http\Response
{
    $result = $this->Authentication->getResult();
    // If the user is logged in send them away.
    if ($result && $result->isValid()) {
        $target = $this->Authentication->getLoginRedirect() ?? '/home';

        return $this->redirect($target);
    }
    if ($this->request->is('post')) {
        $this->Flash->error('Invalid username or password');
    }

    return null;
}
```

Make sure that you allow access to the `login` action in your controller's
`beforeFilter()` callback as mentioned in the previous section, so that
unauthenticated users are able to access it:

``` php
// in src/Controller/UsersController.php
public function beforeFilter(\Cake\Event\EventInterface $event): void
{
    parent::beforeFilter($event);

    $this->Authentication->allowUnauthenticated(['login']);
}
```

Next we'll add a view template for our login form:

``` php
// in templates/Users/login.php
<div class="users form content">
    <?= $this->Form->create() ?>
    <fieldset>
        <legend><?= __('Please enter your email and password') ?></legend>
        <?= $this->Form->control('email') ?>
        <?= $this->Form->control('password') ?>
    </fieldset>
    <?= $this->Form->button(__('Login')) ?>
    <?= $this->Form->end() ?>
</div>
```

Then add a simple logout action:

``` php
// in src/Controller/UsersController.php
public function logout(): \Cake\Http\Response
{
    $this->Authentication->logout();

    return $this->redirect(['controller' => 'Users', 'action' => 'login']);
}
```

We don't need a template for our logout action as we redirect at the end of it.

## Adding Password Hashing

In order to login your users will need to have hashed passwords. You can
automatically hash passwords when users update their password using an entity
setter method:

``` php
// in src/Model/Entity/User.php
use Authentication\PasswordHasher\DefaultPasswordHasher;

class User extends Entity
{
    // ... other methods

    // Automatically hash passwords when they are changed.
    protected function _setPassword(string $password): string
    {
        $hasher = new DefaultPasswordHasher();

        return $hasher->hash($password);
    }
}
```

You should now be able to go to `/users/add` and register a new user. Once
registered you can go to `/users/login` and login with your newly created
user.

## Further Reading

- [Authenticators](authenticators)
- [Identifiers](identifiers)
- [Password Hashers](password-hashers)
- [Identity Objects](identity-object)
- [Middleware](middleware)
- [Authentication Component](authentication-component)
- [User Impersonation](impersonation)
- [URL Checkers](url-checkers)
- [Redirect Validation](redirect-validation)
- [Testing with Authentication](testing)
- [View Helper](view-helper)
- [Migration from the AuthComponent](migration-from-the-authcomponent)
