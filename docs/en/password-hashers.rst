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
