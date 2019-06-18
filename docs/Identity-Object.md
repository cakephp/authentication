# The Identity Object

The identity object is returned by the service and made available in the request. The object provides a method `getIdentifier()` that can be called to get the id of the current log in identity.

The reason this object exists is to provide an interface that makes it easy to get access to the identity's id across various implementations/sources.

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

The identity object provides ArrayAccess but as well a `get()` method to access data. 
It is strongly recommended to use the `get()` method over array access because the get method is aware of the field mapping. 

```php
$identity->get('email');
$identity->get('username');
```
The get() method can also be typehinted via IDE meta file, e.g. through [IdeHelper](https://github.com/dereuromark/cakephp-ide-helper).

If you want, you can use property access, however:
```php
$identity->email;
$identity->username;
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
## Creating your own Identity Object

If you want to create your own identity object, your object must implement the
`IdentityInterface`.

## Implementing the IdentityInterface on your User class

If you'd like to continue using your existing User class with this plugin you
can implement the `Authentication\IdentityInterface`:

```php
namespace App\Model\Entity;

use Authentication\IdentityInterface;
use Cake\ORM\Entity;

class User extends Entity implements IdentityInterface
{

    /**
     * Authentication\IdentityInterface method
     */
    public function getIdentifier()
    {
        return $this->id;
    }

    /**
     * Authentication\IdentityInterface method
     */
    public function getOriginalData()
    {
        return $this;
    }

    // Other methods
}
```

## Using a Custom Identity Decorator

If your identifiers cannot have their resulting objects modified to implement
the `IdentityInterface` you can implement a custom decorator that implements the
required interface:

```php
// You can use a callable...
$identityResolver = function ($data) {
    return new MyCustomIdentity($data);
};

//...or a class name to set the identity wrapper.
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
