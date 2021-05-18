Middleware
##########

``AuthenticationMiddleware`` forms the heart of the authentication plugin.
It intercepts each request to your application and attempts to authenticate
a user with one of the authenticators. Each authenticator is tried in order
until a user is authenticated or no user could be found. The ``authentication``,
``identity`` and ``authenticationResult`` attributes are set on the request
containing the identity if one was found and the authentication result object
which can contain additional errors provided by the authenticators.

At the end of each request  the ``identity`` is persisted into each stateful
authenticator, like the ``Session`` authenticator.

Configuration
=============

All configuration for the middleware is done on the ``AuthenticationService``.
On the service you can use the following configuration options:

- ``identityClass`` - The class name of identity or a callable identity builder.
- ``identityAttribute`` - The request attribute used to store the identity.
  Default to ``identity``.
- ``unauthenticatedRedirect`` - The URL to redirect unauthenticated errors to.
- ``queryParam`` - The name of the query string parameter containing the
  previously blocked URL in case of unauthenticated redirect, or null to disable
  appending the denied URL. Defaults to ``null``.


Configuring Multiple Authentication Setups
==========================================

If your application requires different authentication setups for different parts
of the application for example the API and Web UI. You can do so by using conditional
logic in your applications ``getAuthenticationService()`` hook method. By
inspecting the request object you can configure authentication appropriately::

    public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
    {
        $path = $request->getPath();

        $service = new AuthenticationService();
        if (strpos($path, '/api') === 0) {
            // Accept API tokens only
            $service->loadAuthenticator('Authentication.Token');
            $service->loadIdentifier('Authentication.Token');

            return $service;
        }

        // Web authentication
        // Support sessions and form login.
        $service->loadAuthenticator('Authentication.Session');
        $service->loadAuthenticator('Authentication.Form');

        $service->loadIdentifier('Authentication.Password');

        return $service;
    }

While the above example uses a path prefix, you could apply similar logic to the
subdomain, domain, or any other header or attribute present in the request.
