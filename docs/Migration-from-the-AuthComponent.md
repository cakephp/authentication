# Migration from the AuthComponent

## Differences

* This plugin intentionally **does not** handle authorization. It was [decoupled](https://en.wikipedia.org/wiki/Coupling_(computer_programming)) from authorization on purpose for a clear [separation of concerns](https://en.wikipedia.org/wiki/Separation_of_concerns). See also [Computer access control](https://en.wikipedia.org/wiki/Computer_access_control). This plugin handles only  *identification* and *authentication*. We might have another plugin for authorization. 
* There is no automatic checking of the session. To get the actual user data from the session you'll have to use the `SessionAuthenticator`. It will check the session if there is data in the configured session key and put it into the identity object.
* The user data is no longer available through the AuthComponent but is accessible via a request attribute and encapsulated in an identity object: `$request->getAttribute('authentication')->getIdentity();`
* The logic of the authentication process has been split into authenticators and identifiers. An authenticator will extract the credentials from the request, while identifiers verify the credentials and find the matching user.
* DigestAuthenticate has been renamed to HttpDigestAuthenticator
* BasicAuthenticate has been renamed to HttpBasicAuthenticator

## Similarities

* All the existing authentication adapters, Form, Basic, Digest are still there but have been refactored into authenticators.

## Identifiers and authenticators

Following the principle of separation of concerns, the former authentication objects were split into separate objects, identifiers and authenticators.

* **Authenticators** take the incoming request and try to extract identification credentials from it. If credentials are found, they are passed to a collection of identifiers where the user is located. For that reason authenticators take an IdentifierCollection as first constructor argument.
* **Identifiers** verify identification credentials against a storage system. eg. (ORM tables, LDAP etc) and return identified user data.

This makes it easy to change the identification logic as needed or use several sources of user data.

If you want to implement your own identifiers, your identifier must implement the `IdentifierInterface`.

## Migrating your authentication setup

### Login action

The AuthenticationMiddleware will handle checking and setting the identity based on your authenticators. Usually after logging in, the AuthComponent would redirect to a configured location. To redirect upon a successful login, change your login action to check the new identity results:

```php
function login()
{
    // regardless of POST or GET, redirect if user is logged in
    if ($this->Authentication->getResult()->isValid()) {
        return $this->redirect('/');
    }
}
```

### Checking identity

Remove authentication from the AuthComponent and put the middleware in place like shown above. Then configure your authenticators the same way as you did for the AuthComponent before.

Change your code to use the identity data from the `identity` request attribute instead of using `$this->Auth->user();`. The returned value is null if no identity was found or the identification of the provided credentials failed.

```php
$user = $request->getAttribute('identity');
```

For more details about the result of the authentication process you can access the result object that also comes with the request and is accessible on the `authentication` attribute.

```php
$result = $request->getAttribute('authentication')->getResult();
// Boolean if the result is valid
debug($result->isValid());
// A status code
debug($result->getStatus());
// An array of error messages or data if the identifier provided any
debug($result->getErrors());
```

Any place you were calling `AuthComponent::setUser()`, you should now use
``setIdentity()``

```php
// Assume you need to read a user by access token
$user = $this->Users->find('byToken', ['token' => $token])->first();

// Persist the user into configured authenticators.
$this->Authentication->setIdentity($user);
```

### Migrate AuthComponent settings

The huge config array from the AuthComponent needs to be split into identifiers and authenticators when configuring the service. So when you had your AuthComponent configured this way

```php
$this->loadComponent('Auth', [
    'authentication' => [
        'Form' => [
            'fields' => [
                'username' => 'email',
                'password' => 'password',
            ]
        ]
    ]
]);
```

You'll now have to configure it this way:

```php
// Instantiate the service
$service = new AuthenticationService();

// Load identifiers
$service->loadIdentifier('Authentication.Password', [
    'fields' => [
        'username' => 'email',
        'password' => 'password',
    ]
]);

// Load the authenticators
$service->loadAuthenticator('Authentication.Session');
$service->loadAuthenticator('Authentication.Form');
```

If you have customized the `userModel` you can use the following configuration:

```php
// Instantiate the service
$service = new AuthenticationService();

// Load identifiers
$service->loadIdentifier('Authentication.Password', [
    'resolver' => [
        'className' => 'Authentication.Orm',
        'userModel' => 'Employees',
    ],
    'fields' => [
        'username' => 'email',
        'password' => 'password',
    ]
]);
```

While there is a bit more code than before, you have more flexibility in how
your authentication is handled.


