Quick Start
###########

Install the plugin with `composer <https://getcomposer.org/>`_ from your CakePHP
Project's ROOT directory (where the **composer.json** file is located)

.. code-block:: bash

    php composer.phar require "cakephp/authentication:^2.0"

Load the plugin by adding the following statement in your project's ``src/Application.php``::

    public function bootstrap(): void
    {
        parent::bootstrap();

        $this->addPlugin('Authentication');
    }


Getting Started
===============

The authentication plugin integrates with your application as a `middleware <http://book.cakephp.org/4/en/controllers/middleware.html>`_. It can also
be used as a component to make unauthenticated access simpler. First, let's
apply the middleware. In **src/Application.php**, add the following to the class
imports::

    use Authentication\AuthenticationService;
    use Authentication\AuthenticationServiceInterface;
    use Authentication\AuthenticationServiceProviderInterface;
    use Authentication\Identifier\IdentifierInterface;
    use Authentication\Middleware\AuthenticationMiddleware;
    use Cake\Http\MiddlewareQueue;
    use Cake\Routing\Router;
    use Psr\Http\Message\ServerRequestInterface;
    

Next, add the ``AuthenticationProviderInterface`` to the implemented interfaces
on your application::

    class Application extends BaseApplication implements AuthenticationServiceProviderInterface


Then add ``AuthenticationMiddleware`` to the middleware queue in your ``middleware()`` function::

    $middlewareQueue->add(new AuthenticationMiddleware($this));
    
.. note::
    Make sure you add ``AuthenticationMiddleware`` before ``AuthorizationMiddleware`` if you have both.

``AuthenticationMiddleware`` will call a hook method on your application when
it starts handling the request. This hook method allows your application to
define the ``AuthenticationService`` it wants to use. Add the following method to your
**src/Application.php**::

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
            'unauthenticatedRedirect' => Router::url([
                    'prefix' => false,
                    'plugin' => null,
                    'controller' => 'Users',
                    'action' => 'login',
            ]),
            'queryParam' => 'redirect',
        ]);

        $fields = [
            IdentifierInterface::CREDENTIAL_USERNAME => 'email',
            IdentifierInterface::CREDENTIAL_PASSWORD => 'password'
        ];
        // Load the authenticators. Session should be first.
        $service->loadAuthenticator('Authentication.Session');
        $service->loadAuthenticator('Authentication.Form', [
            'fields' => $fields,
            'loginUrl' => Router::url([
                'prefix' => false,
                'plugin' => null,
                'controller' => 'Users',
                'action' => 'login',
            ]),
        ]);

        // Load identifiers
        $service->loadIdentifier('Authentication.Password', compact('fields'));

        return $service;
    }

First, we configure what to do with users when they are not authenticated.
Next, we attach the ``Session`` and ``Form`` :doc:`/authenticators` which define the
mechanisms that our application will use to authenticate users. ``Session`` enables us to identify
users based on data in the session while ``Form`` enables us
to handle a login form at the ``loginUrl``. Finally we attach an :doc:`identifier
</identifiers>` to convert the credentials users will give us into an
:doc:`identity </identity-object>` which represents our logged in user.

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
users to login. First generate a Users model and controller with bake:

.. code-block:: shell

    bin/cake bake model Users
    bin/cake bake controller Users

Then, we'll add a basic login action to your ``UsersController``. It should look
like::

    // in src/Controller/UsersController.php
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

Make sure that you allow access to the ``login`` action in your controller's
``beforeFilter()`` callback as mentioned in the previous section, so that
unauthenticated users are able to access it::

    // in src/Controller/UsersController.php
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated(['login']);
    }

Next we'll add a view template for our login form::

    // in templates/Users/login.php
    <div class="users form content">
        <?= $this->Form->create() ?>
        <fieldset>
            <legend><?= __('Please enter your email and password') ?></legend>
            <?= $this->Form->control('email') ?>
            <?= $this->Form->control('password') ?>
        </fieldset>
        <?= $this->Form->button(__('Login')); ?>
        <?= $this->Form->end() ?>
    </div>

Then add a simple logout action::

    // in src/Controller/UsersController.php
    public function logout()
    {
        $this->Authentication->logout();
        return $this->redirect(['controller' => 'Users', 'action' => 'login']);
    }

We don't need a template for our logout action as we redirect at the end of it.

Adding Password Hashing
=======================

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

You should now be able to go to ``/users/add`` and register a new user. Once
registered you can go to ``/users/login`` and login with your newly created
user.


Further Reading
===============

* :doc:`/authenticators`
* :doc:`/identifiers`
* :doc:`/password-hashers`
* :doc:`/identity-object`
* :doc:`/authentication-component`
* :doc:`/migration-from-the-authcomponent`
* :doc:`/url-checkers`
* :doc:`/testing`
* :doc:`/view-helper`
