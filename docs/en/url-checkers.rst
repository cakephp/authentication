URL Checkers
############

To provide an abstract and framework agnostic solution there are URL
checkers implemented that allow you to customize the comparison of the
current URL if needed. For example to another frameworks routing.

All checkers support single URLs in either string or array format (like ``Router::url()``).
For multiple login URLs, use ``MultiUrlChecker``.

Included Checkers
=================

DefaultUrlChecker
-----------------

The default URL checker. Supports both string URLs and CakePHP's array-based
routing notation. Uses CakePHP Router and works with named routes.

Single URL (string):

.. code-block:: php

    $service->loadAuthenticator('Authentication.Form', [
        'loginUrl' => '/users/login',
    ]);

Single URL (CakePHP route array):

.. code-block:: php

    $service->loadAuthenticator('Authentication.Form', [
        'loginUrl' => [
            'prefix' => false,
            'plugin' => false,
            'controller' => 'Users',
            'action' => 'login',
        ],
    ]);

Options:

-  **checkFullUrl**: To compare the full URL, including protocol, host
   and port or not. Default is ``false``.

GenericUrlChecker
------------------

Framework-agnostic checker for string URLs. Supports regex matching.
Use this for non-CakePHP projects.

.. code-block:: php

    $service->loadAuthenticator('Authentication.Form', [
        'urlChecker' => 'Authentication.Generic',
        'loginUrl' => '/users/login',
    ]);

Using regex:

.. code-block:: php

    $service->loadAuthenticator('Authentication.Form', [
        'urlChecker' => [
            'className' => 'Authentication.Generic',
            'useRegex' => true,
        ],
        'loginUrl' => '%^/[a-z]{2}/users/login/?$%',
    ]);

Options:

-  **checkFullUrl**: To compare the full URL, including protocol, host
   and port or not. Default is ``false``
-  **useRegex**: Compares the URL by a regular expression provided in
   the ``loginUrl`` configuration.

MultiUrlChecker
---------------

Use this checker when you need to support multiple login URLs (e.g., for multi-language sites).
You must explicitly configure this checker - it is not auto-detected.

Multiple string URLs:

.. code-block:: php

    $service->loadAuthenticator('Authentication.Form', [
        'urlChecker' => 'Authentication.Multi',
        'loginUrl' => [
            '/en/users/login',
            '/de/users/login',
        ],
    ]);

Multiple CakePHP route arrays:

.. code-block:: php

    $service->loadAuthenticator('Authentication.Form', [
        'urlChecker' => 'Authentication.Multi',
        'loginUrl' => [
            ['lang' => 'en', 'controller' => 'Users', 'action' => 'login'],
            ['lang' => 'de', 'controller' => 'Users', 'action' => 'login'],
        ],
    ]);

Options:

-  **checkFullUrl**: To compare the full URL, including protocol, host
   and port or not. Default is ``false``
-  **useRegex**: Compares URLs by regular expressions. Default is ``false``

Implementing your own Checker
-----------------------------

An URL checkers **must** implement the ``UrlCheckerInterface``.
