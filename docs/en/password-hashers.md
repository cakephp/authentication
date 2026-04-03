# Password Hashers

## Default

This is using the php constant `PASSWORD_DEFAULT` for the encryption
method. The default hash type is `bcrypt`.

See [the php
documentation](https://php.net/manual/en/function.password-hash.php)
for further information on bcrypt and PHP’s password hashing.

The config options for this adapter are:

- **hashType**: Hashing algorithm to use. Valid values are those
  supported by `$algo` argument of `password_hash()`. Defaults to
  `PASSWORD_DEFAULT`
- **hashOptions**: Associative array of options. Check the PHP manual
  for supported options for each hash type. Defaults to empty array.

## Legacy

This is a password hasher for applications that have migrated from
CakePHP2.

## Fallback

The fallback password hasher allows you to configure multiple hashers
and will check them sequentially. This allows users to login with an old
hash type until their password is reset and upgraded to a new hash.

## Upgrading Hashing Algorithms

CakePHP provides a clean way to migrate your users’ passwords from one
algorithm to another, this is achieved through the
`FallbackPasswordHasher` class. Assuming you want to migrate from a
Legacy password to the Default bcrypt hasher, you can configure the
fallback hasher as follows:

```php
$passwordIdentifier = [
    'Authentication.Password' => [
        // Other config options
        'passwordHasher' => [
            'className' => 'Authentication.Fallback',
            'hashers' => [
                'Authentication.Default',
                [
                    'className' => 'Authentication.Legacy',
                    'hashType' => 'md5',
                    'salt' => false, // turn off default usage of salt
                ],
            ]
        ]
    ],
];
```

Then in your login action you can use the authentication service to
access the `Password` identifier and check if the current user’s
password needs to be upgraded:

```php
public function login(): ?\Cake\Http\Response
{
    $authentication = $this->request->getAttribute('authentication');
    $result = $authentication->getResult();

    // Regardless of POST or GET, redirect if user is logged in
    if ($result->isValid()) {
        if ($authentication->getIdentificationProvider()->needsPasswordRehash()) {
            // Rehash happens on save.
            $user = $this->fetchTable('Users')->get(
                $authentication->getIdentity()->getIdentifier()
            );
            $user->password = $this->request->getData('password');
            $this->fetchTable('Users')->saveOrFail($user);
        }

        // Redirect or display a template.
        return $this->redirect('/');
    }

    return null;
}
```
