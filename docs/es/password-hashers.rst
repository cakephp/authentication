Hashers de Contraseña
#####################

Por defecto
===========

Usando la constante php ``PASSWORD_DEFAULT`` para el método de encriptación.
El tipo de hash por defecto es ``bcrypt``.

Ver `la documentación
php <https://php.net/manual/en/function.password-hash.php>`__
para obtener más información sobre bcrypt y el hash de contraseñas de PHP.

Las opciones de configuración de este adaptador son:

-  **hashType**: Algoritmo hash a usar. Los valores válidos son los admitidos
   por ``$algo`` argumento de ``password_hash()``. Por defecto es
   ``PASSWORD_DEFAULT``
-  **hashOptions**: Array asociativo de opciones. Revisa las opciones soportadas
   para cada tipo de hash en el manual PHP. Por defecto es un array vacío.

Legacy
======

Este es un hasher de contraseñas para aplicaciones que fueron migradas
de CakePHP2.

Fallback
========

El hasher de contraseña fallback le permite configurar varios hashers y los
comprobará secuencialmente. Esto permite a los usuarios iniciar sesión con un
tipo de hash antiguo hasta que se restablezca su contraseña y se actualice a un nuevo hash.

Actualización de algoritmos hash
================================

CakePHP proporciona una forma limpia de migrar las contraseñas de sus
usuarios de un algoritmo a otro, esto se logra a través de la clase
``FallbackPasswordHasher``. Suponiendo que desea migrar de una contraseña
Legacy a el hasher bcrypt predeterminado, puede configurar el hasher fallback
de la siguiente manera::

   $service->loadIdentifier('Authentication.Password', [
       // Otras opciones de configuración
       'passwordHasher' => [
           'className' => 'Authentication.Fallback',
           'hashers' => [
               'Authentication.Default',
               [
                   'className' => 'Authentication.Legacy',
                   'hashType' => 'md5',
                   'salt' => false // desactiva el uso predeterminado de salt
               ],
           ]
       ]
   ]);

Luego, en su acción login, puede usar el servicio de autenticación para acceder
al identificador del ``Password`` y verificar si la contraseña del usuario
actual debe actualizarse::

   public function login()
   {
       $authentication = $this->request->getAttribute('authentication');
       $result = $authentication->getResult();

       // independientemente de si es POST o GET, redirige si el usuario ha iniciado sesión
       if ($result->isValid()) {
           // Suponiendo que está utilizando el identificador `Password`.
           if ($authentication->identifiers()->get('Password')->needsPasswordRehash()) {
               // El rehash ocurre al guardar.
               $user = $this->Users->get($this->Auth->user('id'));
               $user->password = $this->request->getData('password');
               $this->Users->save($user);
           }

           // Redirige o muestra una plantilla..
       }
   }
