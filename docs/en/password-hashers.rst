Password Hashers
################

Default
=======

This is using the php constant ``PASSWORD_DEFAULT`` for the encryption
method. The default hash type is ``bcrypt``.

See `the php
documentation <http://php.net/manual/en/function.password-hash.php>`__
for further information on bcrypt and PHP’s password hashing.

The config options for this adapter are:

-  **hashType**: Hashing algorithm to use. Valid values are those
   supported by ``$algo`` argument of ``password_hash()``. Defaults to
   ``PASSWORD_DEFAULT``
-  **hashOptions**: Associative array of options. Check the PHP manual
   for supported options for each hash type. Defaults to empty array.

Legacy
======

This is a password hasher for applications that have migrated from
CakePHP2.

Fallback
========

The fallback password hasher allows you to configure multiple hashers
and will check them sequentially. This allows users to login with an old
hash type until their password is reset and upgraded to a new hash.

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
                   'hashType' => 'md5',
                   'salt' => false // turn off default usage of salt
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
