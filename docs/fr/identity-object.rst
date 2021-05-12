Identity Objects
################

Identity objects are returned by the authentication service and made available
in the request. Identities provides a method ``getIdentifier()`` that can be
called to get the primary id value of the current log in identity.

The reason this object exists is to provide an interface that makes it
implementations/sources::

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

The identity object provides ArrayAccess but as well a ``get()`` method to
access data.  It is strongly recommended to use the ``get()`` method over array
access because the get method is aware of the field mapping::

    $identity->get('email');
    $identity->get('username');

The ``get()`` method can also be type-hinted via IDE meta file, e.g. through
`IdeHelper <https://github.com/dereuromark/cakephp-ide-helper>`__.

If you want, you can use property access, however::

    $identity->email;
    $identity->username;

The default Identity object class can be configured to map fields. This
is pretty useful if the identifier of the identity is a non-conventional
``id`` field or if you want to map other fields to more generic and
common names::

   $identity = new Identity($data, [
       'fieldMap' => [
           'id' => 'uid',
           'username' => 'first_name'
       ]
   ]);

Creating your own Identity Object
---------------------------------

By default the Authentication plugin will wrap your returned user data in an
``IdentityDecorator`` that proxies methods and property access.  If you want to
create your own identity object, your object must implement the
``IdentityInterface``.

Implementing the IdentityInterface on your User class
-----------------------------------------------------

If you’d like to continue using your existing User class with this
plugin you can implement the ``Authentication\IdentityInterface``::

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

Using a Custom Identity Decorator
---------------------------------

If your identifiers cannot have their resulting objects modified to
implement the ``IdentityInterface`` you can implement a custom decorator
that implements the required interface::

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
