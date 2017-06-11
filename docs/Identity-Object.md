# The Identity Object

The identity object is returned by the service and made available in the request. The object provides a method `getIdentifier()` that can be called to get the id of the current log in identity.

The reason this object exists is to provide an interface that makes it easy to get access to the identity's across various implementations/sources.

```php
// Service
$authenticationService
    ->getIdentity()
    ->getIdentifier()

// Component
$this->Authentication
    ->getIdentity()
    ->getIdentifier();

// Request
$this->request
    ->getAttribute('identity')
    ->getIdentifier();
```

The identity object provides ArrayAccess but as well a `get()` method to access data. It is strongly recommended to use the `get()` method over array access because the get method is aware of the field mapping. 

```php
$identity->get('email');
$identity->get('username');
```

The default Identity object class can be configured to map fields. This is pretty useful if the identifier of the identity is a non-conventional `id` field or if you want to map other fields to more generic and common names.

```php
    $identity = new Identity($data, [
        'fieldMap' => [
            'id' => 'uid',
            'username' => 'first_name'
        ]
    ]);
};
```
## Creating your own identity object

If you want to create your own identity object, your object must implement the IdentityInterface.

## Using another identity object

```php
// You can use a callable...
$identityResolver = function ($data) {
    return new MyCustomIdentity($data);
};

//...or a class name to inject your custom identity object.
$identityResolver = MyCustomIdentity::class;

// Then pass it to the service configuration
$service = new AuthenticationService([
    'identityClass' => $identityResolver,
    'identifiers' => [
        'Authentication.Password'
    ],
    'authenticators' => [
        'Authentication.Form'
    ]
]);
```
