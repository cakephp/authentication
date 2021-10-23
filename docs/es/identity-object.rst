Objetos de identidad
####################

Los objetos de identidad son devueltos por el servicio de autenticación y están
disponibles en la request. Las identidades proporcionan un método ``getIdentifier()``
que se puede llamar para obtener el valor de la id primaria de la identidad con la sesión iniciada.

La razón por la que este objeto existe es para proporcionar una interfaz que haga
implementaciones/fuentes::

   // Servicio
   $authenticationService
       ->getIdentity()
       ->getIdentifier()

   // Componente
   $this->Authentication
       ->getIdentity()
       ->getIdentifier();

   // Request
   $this->request
       ->getAttribute('identity')
       ->getIdentifier();

El objeto de identidad proporciona un ArrayAccess y también un método ``get()`` para
acceder a los datos. Se recomienda encarecidamente utilizar el método ``get()`` sobre el 
array de acceso porque el método get es consciente del mapeo de campos::

    $identity->get('email');
    $identity->get('username');

El método ``get()`` tambien puede ser type-hinted via archivo IDE meta, ejemplo a traves del
`IdeHelper <https://github.com/dereuromark/cakephp-ide-helper>`__.

Sin embargo, si lo desea, puede usar el acceso a la propiedad::

    $identity->email;
    $identity->username;

La clase de objeto de identidad predeterminada se puede configurar para
asignar campos. Esto es muy útil si el identificador de la identidad es
un campo ``id`` no convencional o si desea asignar otros campos a nombres
más genéricos y comunes::

   $identity = new Identity($data, [
       'fieldMap' => [
           'id' => 'uid',
           'username' => 'first_name'
       ]
   ]);

Crear su propio objeto de identidad
-----------------------------------

De forma predeterminada, el plugin de autenticación contendrá los datos de usuario devueltos
en un ``IdentityDecorator`` que representa los métodos y el acceso a las propiedades.
Si desea crear su propio objeto de identidad, su objeto debe implementar el
``IdentityInterface``.

Implementar IdentityInterface en su clase User
----------------------------------------------

Si desea continuar usando su clase User existente con este plugin,
puede implementar ``Authentication\IdentityInterface``::

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

       // Otros métodos
   }

Usar un Decorador de Identidad Personalizado
--------------------------------------------

Si sus identificadores no pueden modificar los objetos resultantes
al implementar ``IdentityInterface``, puede implementar un decorador
personalizado que implemente la interfaz requerida::

   // Puede usar un callable...
   $identityResolver = function ($data) {
       return new MyCustomIdentity($data);
   };

   //...o un nombre de clase para establecer el contenedor de identidad.
   $identityResolver = MyCustomIdentity::class;

   // Luego pasarlo a la configuración del servicio
   $service = new AuthenticationService([
       'identityClass' => $identityResolver,
       'identifiers' => [
           'Authentication.Password'
       ],
       'authenticators' => [
           'Authentication.Form'
       ]
   ]);
