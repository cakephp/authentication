Quick Start
###########

Install the plugin with `composer <https://getcomposer.org/>`_ from your CakePHP
Project's ROOT directory (where the **composer.json** file is located)

.. code-block:: bash

    php composer.phar require cakephp/authentication:^2.0

Load the plugin by adding the following statement in your project's ``src/Application.php``::

    public function bootstrap(): void
    {
        parent::bootstrap();

        $this->addPlugin('Authentication');
    }


Getting Started
===============

Add the authentication to the middleware. See the CakePHP `documentation
<http://book.cakephp.org/3.0/en/controllers/middleware.html>`_ on how to use
middleware if you are not familiar with it.

Example of configuring the authentication middleware using ``authentication`` application hook::

    use Authentication\AuthenticationService;
    use Authentication\AuthenticationServiceInterface;
    use Authentication\AuthenticationServiceProviderInterface;
    use Authentication\Middleware\AuthenticationMiddleware;
    use Cake\Http\MiddlewareQueue;
    use Psr\Http\Message\ServerRequestInterface;

    class Application extends BaseApplication implements AuthenticationServiceProviderInterface
    {
        /**
         * Returns a service provider instance.
         *
         * @param \Psr\Http\Message\ServerRequestInterface $request Request
         * @return \Authentication\AuthenticationServiceInterface
         */
        public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
        {
            $service = new AuthenticationService();

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
                'loginUrl' => '/users/login'
            ]);

            return $service;
        }

        /**
         * Setup the middleware queue your application will use.
         *
         * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue.
         * @return \Cake\Http\MiddlewareQueue The updated middleware queue.
         */
        public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
        {
            // Various other middlewares for error handling, routing etc. added here.

            // Create an authentication middleware object
            $authentication = new AuthenticationMiddleware($this);

            // Add the middleware to the middleware queue.
            // Authentication should be added *after* RoutingMiddleware.
            // So that subdirectory information and routes are loaded.
            $middlewareQueue->add($authentication);

            return $middlewareQueue;
        }
    }

If one of the configured authenticators was able to validate the credentials,
the middleware will add the authentication service to the request object as an
`attribute <http://www.php-fig.org/psr/psr-7/>`_.

Next, in your ``AppController`` load the :doc:`/authentication-component`::

    // in src/Controller/AppController.php
    public function initialize()
    {
        parent::initialize();

        $this->loadComponent('Authentication.Authentication');
    }

By default the component will require an authenticated user for **all** actions.
You can disable this behavior in specific controllers using
``allowUnauthenticated()``::

    // in a controller beforeFilter or initialize
    // Make view and index not require a logged in user.
    $this->Authentication->allowUnauthenticated(['view', 'index']);

Building a Login Action
=======================

Once you have the middleware applied to your application you'll need a way for
users to login. A simplistic login action in a ``UsersController`` would look
like::

    public function login()
    {
        $result = $this->Authentication->getResult();
        // If the user is logged in send them away.
        if ($result->isValid()) {
            $target = $this->Authentication->getLoginRedirect() ?? '/home';
            return $this->redirect($target);
        }
        if ($this->request->is('post') && !$result->isValid()) {
            $this->Flash->error('Invalid username or password');
        }
    }

Then add a simple logout action::

    public function logout()
    {
        $this->Authentication->logout();
        return $this->redirect(['controller' => 'Users', 'action' => 'login']);
    }

In order to login your users will need to have hashed passwords. You can
automatically hash passwords when users update their password using an entity
setter method::

    // in src/Model/Entity/User.php
    use Authentication\PasswordHasher\DefaultPasswordHasher;

    class User extends Entity
    {
        // ... other methods

        // Automatically hash passwords when they are changed.
        protected function _setPassword(string $password)
        {
            $hasher = new DefaultPasswordHasher();
            return $hasher->hash($password);
        }
    }


Further Reading
===============

.. toctree::
    :maxdepth: 2

    /authenticators
    /identifiers
    /password-hashers
    /identity-object
    /authentication-component
    /migration-from-the-authcomponent
    /url-checkers
    /testing
    /view-helper
