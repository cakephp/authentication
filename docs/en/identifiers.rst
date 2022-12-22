Identifiers
###########

Identifiers will identify an user or service based on the information
that was extracted from the request by the authenticators. Identifiers
can take options in the ``loadIdentifier`` method. A holistic example of
using the Password Identifier looks like::

   $service->loadIdentifier('Authentication.Password', [
       'fields' => [
           'username' => 'email',
           'password' => 'passwd',
       ],
       'resolver' => [
           'className' => 'Authentication.Orm',
           'userModel' => 'Users',
           'finder' => 'active', // default: 'all'
       ],
       'passwordHasher' => [
           'className' => 'Authentication.Fallback',
           'hashers' => [
               'Authentication.Default',
               [
                   'className' => 'Authentication.Legacy',
                   'hashType' => 'md5',
               ],
           ],
       ],
   ]);

Password
========

The password identifier checks the passed credentials against a
datasource.

Configuration options:

-  **fields**: The fields for the lookup. Default is
   ``['username' => 'username', 'password' => 'password']``. You can
   also set the ``username`` to an array. For e.g. using
   ``['username' => ['username', 'email'], 'password' => 'password']``
   will allow you to match value of either username or email columns.
-  **resolver**: The identity resolver. Default is
   ``Authentication.Orm`` which uses CakePHP ORM.
-  **passwordHasher**: Password hasher. Default is
   ``DefaultPasswordHasher::class``.

Token
=====

Checks the passed token against a datasource.

Configuration options:

-  **tokenField**: The field in the database to check against. Default
   is ``token``.
-  **dataField**: The field in the passed data from the authenticator.
   Default is ``token``.
-  **resolver**: The identity resolver. Default is
   ``Authentication.Orm`` which uses CakePHP ORM.
-  **hashAlgorithm**: The algorithm used to hash the incoming token
   with before compairing it to the ``tokenField``. Recommended value is
   ``sha256```. Default is ``null``.

JWT Subject
===========

Checks the passed JWT token against a datasource.

Configuration options:

-  **tokenField**: The field in the database to check against. Default
   is ``id``.
-  **dataField**: The payload key to get user identifier from. Default
   is ``sub``.
-  **resolver**: The identity resolver. Default is
   ``Authentication.Orm`` which uses CakePHP ORM.

LDAP
====

Checks the passed credentials against a LDAP server. This identifier
requires the PHP LDAP extension.

Configuration options:

-  **fields**: The fields for the lookup. Default is
   ``['username' => 'username', 'password' => 'password']``.
-  **host**: The FQDN of your LDAP server.
-  **port**: The port of your LDAP server. Defaults to ``389``.
-  **bindDN**: The Distinguished Name of the user to authenticate. Must
   be a callable. Anonymous binds are not supported.
-  **ldap**: The extension adapter. Defaults to
   ``\Authentication\Identifier\Ldap\ExtensionAdapter``. You can pass a
   custom object/classname here if it implements the
   ``AdapterInterface``.
-  **options**: Array of additional LDAP options, including
    ``tls``: Boolean. If ``true``, tries to start TLS on the connection.
    Also LDAP config options such as
    ``LDAP_OPT_PROTOCOL_VERSION`` or ``LDAP_OPT_NETWORK_TIMEOUT``. See
   `php.net <https://php.net/manual/en/function.ldap-set-option.php>`__
   for more valid options.

Callback
========

Allows you to use a callback for identification. This is useful for
simple identifiers or quick prototyping.

Configuration options:

-  **callback**: Default is ``null`` and will cause an exception. You’re
   required to pass a valid callback to this option to use the
   authenticator.

Callback identifiers can either return ``null|ArrayAccess`` for simple results,
or an ``Authentication\Authenticator\Result`` if you want to forward error
messages::

    // A simple callback identifier
    $authenticationService->loadIdentifier('Authentication.Callback', [
        'callback' => function($data) {
            // do identifier logic

            // Return an array of the identified user or null for failure.
            if ($result) {
                return $result;
            }

            return null;
        },
    ]);

    // Using a result object to return error messages.
    $authenticationService->loadIdentifier('Authentication.Callback', [
        'callback' => function($data) {
            // do identifier logic

            if ($result) {
                return new Result($result, Result::SUCCESS);
            }

            return new Result(
                null,
                Result::FAILURE_OTHER,
                ['message' => 'Removed user.']
            );
        },
    ]);


Identity resolvers
==================

Identity resolvers provide adapters for different datasources. They
allow you to control which source identities are searched in. They are
separate from the identifiers so that they can be swapped out
independently from the identifier method (form, jwt, basic auth).

ORM Resolver
------------

Identity resolver for the CakePHP ORM.

Configuration options:

-  **userModel**: The user model identities are located in. Default is
   ``Users``.
-  **finder**: The finder to use with the model. Default is ``all``.
   You can read more about model finders `here <https://book.cakephp.org/4/en/orm/retrieving-data-and-resultsets.html#custom-finder-methods>`__.

In order to use ORM resolver you must require ``cakephp/orm`` in your
``composer.json`` file (if you are not already using the full CakePHP framework).

Writing your own resolver
-------------------------

Any ORM or datasource can be adapted to work with authentication by
creating a resolver. Resolvers must implement
``Authentication\Identifier\Resolver\ResolverInterface`` and should
reside under ``App\Identifier\Resolver`` namespace.

Resolver can be configured using ``resolver`` config option::

   $service->loadIdentifier('Authentication.Password', [
       'resolver' => [
            // can be a full class name: \Some\Other\Custom\Resolver::class
           'className' => 'MyResolver',
           // Pass additional options to the resolver constructor.
           'option' => 'value',
       ],
   ]);

Or injected using a setter::

   $resolver = new \App\Identifier\Resolver\CustomResolver();
   $identifier = $service->loadIdentifier('Authentication.Password');
   $identifier->setResolver($resolver);
