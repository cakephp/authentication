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
           'finder' => 'active'
       ],
       'passwordHasher' => [
           'className' => 'Authentication.Fallback',
           'hashers' => [
               'Authentication.Default',
               [
                   'className' => 'Authentication.Legacy',
                   'hashType' => 'md5'
               ],
           ]
       ]
   ]);

Password
--------

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
-----

Checks the passed token against a datasource.

Configuration options:

-  **tokenField**: The field in the database to check against. Default
   is ``token``.
-  **dataField**: The field in the passed data from the authenticator.
   Default is ``token``.
-  **resolver**: The identity resolver. Default is
   ``Authentication.Orm`` which uses CakePHP ORM.

JWT Subject
-----------

Checks the passed JWT token against a datasource.

-  **tokenField**: The field in the database to check against. Default
   is ``id``.
-  **dataField**: The payload key to get user identifier from. Default
   is ``sub``.
-  **resolver**: The identity resolver. Default is
   ``Authentication.Orm`` which uses CakePHP ORM.

LDAP
----

Checks the passed credentials against a LDAP server. This identifier
requires the PHP LDAP extension.

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
-  **options**: Additional LDAP options, like
   ``LDAP_OPT_PROTOCOL_VERSION`` or ``LDAP_OPT_NETWORK_TIMEOUT``. See
   `php.net <http://php.net/manual/en/function.ldap-set-option.php>`__
   for more valid options.

Callback
--------

Allows you to use a callback for identification. This is useful for
simple identifiers or quick prototyping.

Configuration options:

-  **callback**: Default is ``null`` and will cause an exception. You’re
   required to pass a valid callback to this option to use the
   authenticator.

Upgrading Hashing Algorithms
============================

CakePHP provides a clean way to migrate your users’ passwords from one
algorithm to another, this is achieved through the
``FallbackPasswordHasher`` class. Assuming you want to migrate from a
Legacy password to the Default bcrypt hasher, you can configure the
fallback hasher as follows::

   $service->loadIdentifier('Authentication.Password', [
       // Other config options
       'passwordHasher' => [
           'className' => 'Authentication.Fallback',
           'hashers' => [
               'Authentication.Default',
               [
                   'className' => 'Authentication.Legacy',
                   'hashType' => 'md5'
               ],
           ]
       ]
   ]);

Then in your login action you can use the authentication service to
access the ``Password`` identifier and check if the current user’s
password needs to be upgraded::

   public function login()
   {
       $authentication = $this->request->getAttribute('authentication');
       $result = $authentication->getResult();

       // regardless of POST or GET, redirect if user is logged in
       if ($result->isValid()) {

           // Assuming you are using the `Password` identifier.
           if ($authentication->identifiers()->get('Password')->needsPasswordRehash()) {
               // Rehash happens on save.
               $user = $this->Users->get($this->Auth->user('id'));
               $user->password = $this->request->getData('password');
               $this->Users->save($user);
           }

           // Redirect or display a template.
       }
   }

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

In order to use ORM resolver you must require ``cakephp/orm`` in your
``composer.json`` file.

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
           'option' => 'value'
       ]
   ]);

Or injected using a setter::

   $resolver = new \App\Identifier\Resolver\CustomResolver();
   $identifier = $service->loadIdentifier('Authentication.Password');
   $identifier->setResolver($resolver);
