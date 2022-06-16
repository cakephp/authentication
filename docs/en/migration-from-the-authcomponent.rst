Migration from the AuthComponent
################################

Differences
===========

-  This plugin intentionally **does not** handle authorization. It was
   `decoupled <https://en.wikipedia.org/wiki/Coupling_(computer_programming)>`__
   from authorization on purpose for a clear `separation of
   concerns <https://en.wikipedia.org/wiki/Separation_of_concerns>`__.
   See also `Computer access
   control <https://en.wikipedia.org/wiki/Computer_access_control>`__.
   This plugin handles only *identification* and *authentication*. We
   might have another plugin for authorization.
-  There is no automatic checking of the session. To get the actual user
   data from the session you’ll have to use the
   ``SessionAuthenticator``. It will check the session if there is data
   in the configured session key and put it into the identity object.
-  The user data is no longer available through the old AuthComponent but is
   accessible via a request attribute and encapsulated in an identity
   object: ``$request->getAttribute('authentication')->getIdentity();``.
   Additionally, you can leverage the ``AuthenticationComponent`` ``getIdentity()`` or ``getIdentityData()`` methods.
-  The logic of the authentication process has been split into
   authenticators and identifiers. An authenticator will extract the
   credentials from the request, while identifiers verify the
   credentials and find the matching user.
-  DigestAuthenticate has been renamed to HttpDigestAuthenticator
-  BasicAuthenticate has been renamed to HttpBasicAuthenticator

Similarities
============

-  All the existing authentication adapters, Form, Basic, Digest are
   still there but have been refactored into authenticators.

Identifiers and authenticators
==============================

Following the principle of separation of concerns, the former
authentication objects were split into separate objects, identifiers and
authenticators.

-  **Authenticators** take the incoming request and try to extract
   identification credentials from it. If credentials are found, they
   are passed to a collection of identifiers where the user is located.
   For that reason authenticators take an IdentifierCollection as first
   constructor argument.
-  **Identifiers** verify identification credentials against a storage
   system. eg. (ORM tables, LDAP etc) and return identified user data.

This makes it easy to change the identification logic as needed or use
several sources of user data.

If you want to implement your own identifiers, your identifier must
implement the ``IdentifierInterface``.

Migrating your authentication setup
===================================

The first step to migrating your application is to load the authentication
plugin in your application's bootstrap method::

    public function bootstrap(): void
    {
        parent::bootstrap();
        $this->addPlugin('Authentication');
    }

Then update your application to implement the authentication provider interface.
This lets the AuthenticationMiddleware know how to get the authentication
service from your application::

    // in src/Application.php

    // Add the following use statements.
    use Authentication\AuthenticationService;
    use Authentication\AuthenticationServiceInterface;
    use Authentication\AuthenticationServiceProviderInterface;
    use Authentication\Middleware\AuthenticationMiddleware;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;

    // Add the authentication interface.
    class Application extends BaseApplication implements AuthenticationServiceProviderInterface
    {
        /**
         * Returns a service provider instance.
         *
         * @param \Psr\Http\Message\ServerRequestInterface $request Request
         * @param \Psr\Http\Message\ResponseInterface $response Response
         * @return \Authentication\AuthenticationServiceInterface
         */
        public function getAuthenticationService(ServerRequestInterface $request) : AuthenticationServiceInterface
        {
            $service = new AuthenticationService();
            // Configure the service. (see below for more details)
            return $service;
        }
    }

Next add the ``AuthenticationMiddleware`` to your application::

    // in src/Application.php
    public function middleware($middlewareQueue)
    {
        // Various other middlewares for error handling, routing etc. added here.

        // Add the middleware to the middleware queue
        $middlewareQueue->add(new AuthenticationMiddleware($this));

        return $middlewareQueue;
    }

Migrate AuthComponent settings
------------------------------

The configuration array from ``AuthComponent`` needs to be split into
identifiers and authenticators when configuring the service. So when you
had your ``AuthComponent`` configured this way::

   $this->loadComponent('Auth', [
       'authentication' => [
           'Form' => [
               'fields' => [
                   'username' => 'email',
                   'password' => 'password',
               ]
           ]
       ]
   ]);

You’ll now have to configure it this way::

   // Instantiate the service
   $service = new AuthenticationService();

   // Load identifiers
   $service->loadIdentifier('Authentication.Password', [
       'fields' => [
           'username' => 'email',
           'password' => 'password',
       ]
   ]);

   // Load the authenticators
   $service->loadAuthenticator('Authentication.Session');
   $service->loadAuthenticator('Authentication.Form');

If you have customized the ``userModel`` you can use the following
configuration::

   // Instantiate the service
   $service = new AuthenticationService();

   // Load identifiers
   $service->loadIdentifier('Authentication.Password', [
       'resolver' => [
           'className' => 'Authentication.Orm',
           'userModel' => 'Employees',
       ],
       'fields' => [
           'username' => 'email',
           'password' => 'password',
       ]
   ]);

While there is a bit more code than before, you have more flexibility in
how your authentication is handled.

Login action
------------

The ``AuthenticationMiddleware`` will handle checking and setting the
identity based on your authenticators. Usually after logging in,
``AuthComponent`` would redirect to a configured location. To redirect
upon a successful login, change your login action to check the new
identity results::

    public function login()
    {
        $result = $this->Authentication->getResult();

        // regardless of POST or GET, redirect if user is logged in
        if ($result->isValid()) {
            $target = $this->Authentication->getLoginRedirect();
            return $this->redirect($target);
        }

        // display error if user submitted and authentication failed
        if ($this->request->is(['post'])) {
            $this->Flash->error('Invalid username or password');
        }
    }

Checking identities
-------------------

After applying the middleware you can use identity data by using the
``identity`` request attribute. This replaces the
``$this->Auth->user()`` calls you are using now. If the current
user is unauthenticated or if the provided credentials were invalid, the
``identity`` attribute will be ``null``::

   $user = $request->getAttribute('identity');

For more details about the result of the authentication process you can
access the result object that also comes with the request and is
accessible on the ``authentication`` attribute::

   $result = $request->getAttribute('authentication')->getResult();
   // Boolean if the result is valid
   $isValid = $result->isValid();
   // A status code
   $statusCode = $result->getStatus();
   // An array of error messages or data if the identifier provided any
   $errors = $result->getErrors();

Any place you were calling ``AuthComponent::setUser()``, you should now
use ``setIdentity()``::

   // Assume you need to read a user by access token
   $user = $this->Users->find('byToken', ['token' => $token])->first();

   // Persist the user into configured authenticators.
   $this->Authentication->setIdentity($user);


Migrating allow/deny logic
--------------------------

Like ``AuthComponent`` the ``AuthenticationComponent`` makes it easy to
make specific actions ‘public’ and not require a valid identity to be
present::

   // In your controller's beforeFilter method.
   $this->Authentication->allowUnauthenticated(['view']);

Each call to ``allowUnauthenticated()`` will overwrite the current
action list.

Migrating Unauthenticated Redirects
===================================

By default ``AuthComponent`` redirects users back to the login page when
authentication is required. In contrast, the ``AuthenticationComponent``
in this plugin will raise an exception in this scenario. You can convert
this exception into a redirect using the ``unauthenticatedRedirect``
when configuring the ``AuthenticationService``.

You can also pass the current request target URI as a query parameter
using the ``queryParam`` option::

   // In the getAuthenticationService() method of your src/Application.php

   $service = new AuthenticationService();

   // Configure unauthenticated redirect
   $service->setConfig([
       'unauthenticatedRedirect' => '/users/login',
       'queryParam' => 'redirect',
   ]);

Then in your controller's login method you can use ``getLoginRedirect()`` to get
the redirect target safely from the query string parameter::

    public function login()
    {
        $result = $this->Authentication->getResult();

        // Regardless of POST or GET, redirect if user is logged in
        if ($result->isValid()) {
            // Use the redirect parameter if present.
            $target = $this->Authentication->getLoginRedirect();
            if (!$target) {
                $target = ['controller' => 'Pages', 'action' => 'display', 'home'];
            }
            return $this->redirect($target);
        }
    }

Migrating Hashing Upgrade Logic
===============================

If your application uses ``AuthComponent``\ ’s hash upgrade
functionality. You can replicate that logic with this plugin by
leveraging the ``AuthenticationService``::

   public function login()
   {
       $result = $this->Authentication->getResult();

       // regardless of POST or GET, redirect if user is logged in
       if ($result->isValid()) {
           $authService = $this->Authentication->getAuthenticationService();

           // Assuming you are using the `Password` identifier.
           if ($authService->identifiers()->get('Password')->needsPasswordRehash()) {
               // Rehash happens on save.
               $user = $this->Users->get($this->Authentication->getIdentityData('id'));
               $user->password = $this->request->getData('password');
               $this->Users->save($user);
           }

           // Redirect to a logged in page
           return $this->redirect([
               'controller' => 'Pages',
               'action' => 'display',
               'home'
           ]);
       }
   }
