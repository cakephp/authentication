# Password Hashers

## Default

This is using the php constant `PASSWORD_DEFAULT` for the encryption method. It is `bcrypt` by default.

See [the official php documentation](http://php.net/manual/en/function.password-hash.php) for further information.

The config options for this adapter are: 

 * **hashType**: Hashing algo to use. Valid values are those supported by `$algo` argument of `password_hash()`. Defaults to `PASSWORD_DEFAULT`
 * **hashOptions**: Associative array of options. Check the PHP manual for supported options for each hash type. Defaults to empty array.

## Legacy

This is a password hasher for old CakePHP2 applications that are migrated to newer versions.

## Fallback

The fallback password hasher allows you to configure multiple hashers and will check them. This allows users to still login until their password is reset and upgraded to a new hash.  
