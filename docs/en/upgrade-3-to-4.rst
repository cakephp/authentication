Upgrade Guide 3.x to 4.x
#########################

Version 4.0 is a major release with several breaking changes focused on
simplifying the API and removing deprecated code.

Breaking Changes
================

IdentifierCollection Removed
-----------------------------

The deprecated ``IdentifierCollection`` has been removed. Authenticators now
accept a nullable ``IdentifierInterface`` directly.

**Before (3.x):**

.. code-block:: php

    use Authentication\Identifier\IdentifierCollection;

    $identifiers = new IdentifierCollection([
        'Authentication.Password',
    ]);

    $authenticator = new FormAuthenticator($identifiers);

**After (4.x):**

.. code-block:: php

    use Authentication\Identifier\IdentifierFactory;

    // Option 1: Pass identifier directly
    $identifier = IdentifierFactory::create('Authentication.Password');
    $authenticator = new FormAuthenticator($identifier);

    // Option 2: Pass null and let authenticator create default
    $authenticator = new FormAuthenticator(null);

    // Option 3: Configure identifier in authenticator config
    $service->loadAuthenticator('Authentication.Form', [
        'identifier' => 'Authentication.Password',
    ]);

AuthenticationService Changes
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

The ``loadIdentifier()`` method has been removed from ``AuthenticationService``.
Identifiers are now managed by individual authenticators.

**Before (3.x):**

.. code-block:: php

    $service = new AuthenticationService();
    $service->loadIdentifier('Authentication.Password');
    $service->loadAuthenticator('Authentication.Form');

**After (4.x):**

.. code-block:: php

    $service = new AuthenticationService();
    $service->loadAuthenticator('Authentication.Form', [
        'identifier' => 'Authentication.Password',
    ]);

CREDENTIAL Constants Moved
---------------------------

The ``CREDENTIAL_USERNAME`` and ``CREDENTIAL_PASSWORD`` constants have been
moved from ``AbstractIdentifier`` to specific identifier implementations.

**Before (3.x):**

.. code-block:: php

    use Authentication\Identifier\AbstractIdentifier;

    $fields = [
        AbstractIdentifier::CREDENTIAL_USERNAME => 'email',
        AbstractIdentifier::CREDENTIAL_PASSWORD => 'password',
    ];

**After (4.x):**

.. code-block:: php

    use Authentication\Identifier\PasswordIdentifier;

    $fields = [
        PasswordIdentifier::CREDENTIAL_USERNAME => 'email',
        PasswordIdentifier::CREDENTIAL_PASSWORD => 'password',
    ];

For LDAP authentication:

.. code-block:: php

    use Authentication\Identifier\LdapIdentifier;

    $fields = [
        LdapIdentifier::CREDENTIAL_USERNAME => 'uid',
        LdapIdentifier::CREDENTIAL_PASSWORD => 'password',
    ];

URL Checker Renamed
-------------------

``CakeRouterUrlChecker`` has been renamed to ``CakeUrlChecker`` and now accepts
both string and array URLs (just like ``Router::url()``).

**Before (3.x):**

.. code-block:: php

    $service->loadAuthenticator('Authentication.Form', [
        'urlChecker' => 'Authentication.CakeRouter',
        'loginUrl' => [
            'controller' => 'Users',
            'action' => 'login',
        ],
    ]);

**After (4.x):**

.. code-block:: php

    // CakeUrlChecker is now the default when CakePHP is installed
    $service->loadAuthenticator('Authentication.Form', [
        'loginUrl' => [
            'controller' => 'Users',
            'action' => 'login',
        ],
    ]);

    // Or explicitly:
    $service->loadAuthenticator('Authentication.Form', [
        'urlChecker' => 'Authentication.Cake',
        'loginUrl' => [
            'controller' => 'Users',
            'action' => 'login',
        ],
    ]);

Simplified URL Checker API
---------------------------

URL checkers now accept a single URL in either string or array format.
For multiple URLs, you must explicitly use ``MultiUrlChecker``.

**Multiple URLs - Before (3.x):**

.. code-block:: php

    // This would auto-select the appropriate checker
    $service->loadAuthenticator('Authentication.Form', [
        'loginUrl' => [
            '/en/users/login',
            '/de/users/login',
        ],
    ]);

**Multiple URLs - After (4.x):**

.. code-block:: php

    // Must explicitly configure MultiUrlChecker
    $service->loadAuthenticator('Authentication.Form', [
        'urlChecker' => 'Authentication.Multi',
        'loginUrl' => [
            '/en/users/login',
            '/de/users/login',
        ],
    ]);

Single URLs work the same in both versions:

.. code-block:: php

    // String URL
    $service->loadAuthenticator('Authentication.Form', [
        'loginUrl' => '/users/login',
    ]);

    // Array URL (CakePHP route)
    $service->loadAuthenticator('Authentication.Form', [
        'loginUrl' => ['controller' => 'Users', 'action' => 'login'],
    ]);

Auto-Detection Changes
----------------------

URL Checkers
^^^^^^^^^^^^

- When CakePHP Router is available: defaults to ``CakeUrlChecker``
- Without CakePHP: defaults to ``DefaultUrlChecker``
- For multiple URLs: you **must** explicitly configure ``MultiUrlChecker``

DefaultUrlChecker Changes
^^^^^^^^^^^^^^^^^^^^^^^^^

``DefaultUrlChecker`` no longer accepts array-based URLs. It throws a
``RuntimeException`` if an array URL is provided:

.. code-block:: php

    // This will throw an exception in 4.x
    $checker = new DefaultUrlChecker();
    $checker->check($request, ['controller' => 'Users', 'action' => 'login']);

    // Use CakeUrlChecker instead:
    $checker = new CakeUrlChecker();
    $checker->check($request, ['controller' => 'Users', 'action' => 'login']);

New Features
============

IdentifierFactory
-----------------

New factory class for creating identifiers from configuration:

.. code-block:: php

    use Authentication\Identifier\IdentifierFactory;

    // Create from string
    $identifier = IdentifierFactory::create('Authentication.Password');

    // Create with config
    $identifier = IdentifierFactory::create('Authentication.Password', [
        'fields' => [
            'username' => 'email',
            'password' => 'password',
        ],
    ]);

    // Pass existing instance (returns as-is)
    $identifier = IdentifierFactory::create($existingIdentifier);

MultiUrlChecker
---------------

New dedicated checker for multiple login URLs:

.. code-block:: php

    $service->loadAuthenticator('Authentication.Form', [
        'urlChecker' => 'Authentication.Multi',
        'loginUrl' => [
            '/en/login',
            '/de/login',
            ['lang' => 'fr', 'controller' => 'Users', 'action' => 'login'],
        ],
    ]);

Migration Tips
==============

1. **Search and Replace**:

   - ``AbstractIdentifier::CREDENTIAL_`` → ``PasswordIdentifier::CREDENTIAL_``
   - ``IdentifierCollection`` → ``IdentifierFactory``
   - ``'Authentication.CakeRouter'`` → ``'Authentication.Cake'``
   - ``CakeRouterUrlChecker`` → ``CakeUrlChecker``

2. **Multiple Login URLs**:

   If you have multiple login URLs, add ``'urlChecker' => 'Authentication.Multi'``
   to your authenticator configuration.

3. **Custom Identifier Setup**:

   If you were passing ``IdentifierCollection`` to authenticators, switch to
   either passing a single identifier or null (to use defaults).

4. **Test Thoroughly**:

   The changes to identifier management and URL checking are significant.
   Test all authentication flows after upgrading.
