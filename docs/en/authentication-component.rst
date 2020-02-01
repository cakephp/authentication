Authentication Component
========================

You can use the ``AuthenticationComponent`` to access the result of
authentication, get user identity and logout user. Load the component in your
``AppController::initialize()`` like any other component::

    $this->loadComponent('Authentication.Authentication', [
        'logoutRedirect' => '/users/login'  // Default is false
    ]);

Once loaded, the ``AuthenticationComponent`` will require that all actions have an
authenticated user present, but perform no other access control checks. You can
disable this check for specific actions using ``allowUnauthenticated()``::

    // In your controller's beforeFilter method.
    $this->Authentication->allowUnauthenticated(['view']);

Accessing the logged in user
----------------------------

You can get the authenticated user identity data using the authentication
component::

    $user = $this->Authentication->getIdentity();

You can also get the identity directly from the request instance::

    $user = $request->getAttribute('identity');

Checking the login status
-------------------------

You can check if the authentication process was successful by accessing the
result object::

    // Using Authentication component
    $result = $this->Authentication->getResult();

    // Using request object
    $result = $request->getAttribute('authentication')->getResult();

    if ($result->isValid()) {
        $user = $request->getAttribute('identity');
    } else {
        $this->log($result->getStatus());
        $this->log($result->getErrors());
    }

The result sets objects status returned from ``getStatus()`` will match one of
these these constants in the Result object:

* ``ResultInterface::SUCCESS``, when successful.
* ``ResultInterface::FAILURE_IDENTITY_NOT_FOUND``, when identity could not be found.
* ``ResultInterface::FAILURE_CREDENTIALS_INVALID``, when credentials are invalid.
* ``ResultInterface::FAILURE_CREDENTIALS_MISSING``, when credentials are missing in the request.
* ``ResultInterface::FAILURE_OTHER``, on any other kind of failure.

The error array returned by ``getErrors()`` contains **additional** information
coming from the specific system against which the authentication attempt was
made. For example LDAP or OAuth would put errors specific to their
implementation in here for easier logging and debugging the cause. But most of
the included authenticators don't put anything in here.

Logging out the identity
------------------------

To log an identity out just do::

    $this->Authentication->logout();

If you have set the ``logoutRedirect`` config, ``Authentication::logout()`` will
return that value else will return ``false``. It won't perform any actual redirection
in either case.

Alternatively, instead of the component you can also use the service to log out::

    $return = $request->getAttribute('authentication')->clearIdentity($request, $response);

The result returned will contain an array like this::

    [
        'response' => object(Cake\Http\Response) { ... },
        'request' => object(Cake\Http\ServerRequest) { ... },
    ]

.. note::
    This will return an array containing the request and response
    objects. Since both are immutable you'll get new objects back. Depending on your
    context you're working in you'll have to use these instances from now on if you
    want to continue to work with the modified response and request objects.

Configure Automatic Identity Checks
-----------------------------------

By default ``AuthenticationComponent`` will automatically enforce an identity to
be present during the ``Controller.initialize`` event. You can have this check
applied during the ``Controller.startup`` event instead::

    // In your controller's initialize() method.
    $this->loadComponent('Authentication.Authentication', [
        'identityCheckEvent' => 'Controller.startup',
    ]);

You can also disable identity checks entirely with the ``requireIdentity``
option.
