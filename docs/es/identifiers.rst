Identificadores
###############

Los Identificadores identificarán a un usuario o servicio en función de la información
que fue extraída de la request por los autenticadores. Los Identificadores
pueden tomar opciones en el método ``loadIdentifier``. Un ejemplo holístico de
el uso del Identificador de Contraseña se ve así::

   $service->loadIdentifier('Authentication.Password', [
       'fields' => [
           'username' => 'email',
           'password' => 'passwd',
       ],
       'resolver' => [
           'className' => 'Authentication.Orm',
           'userModel' => 'Users'
           'finder' => 'active'
       ],
       'passwordHasher' => [
           'className' => 'Authentication.Fallback',
           'hashers' => [
               'Authentication.Default',
               [
                   'className' => 'Authentication.Legacy',
                   'hashType' => 'md5'
               ],
           ]
       ]
   ]);

Contraseña
==========

El identificador de contraseña compara las credenciales suministradas
con una fuente de datos.

Opciones de configuración:

-  **fields**: Los campos a buscar. Por defecto son
   ``['username' => 'username', 'password' => 'password']``. Tambien puede
   colocar el ``username`` en un array. Por ejemplo usando
   ``['username' => ['username', 'email'], 'password' => 'password']``
   lo que le permitirá hacer coincidir con la columna username o la columna email.
-  **resolver**: El resolver de la identidad. Por defecto es
   ``Authentication.Orm`` el cual usa el ORM de CakePHP.
-  **passwordHasher**: Hasher del password. Por defecto es
   ``DefaultPasswordHasher::class``.

Token
=====

Comprueba el token suministrado con una fuente de datos.

Opciones de configuración:

-  **tokenField**: El campo a verificar en la base de datos. Por defecto
   es ``token``.
-  **dataField**: El campo en los datos suministrados del autenticador.
   Por defecto es ``token``.
-  **resolver**: El resolver de la identidad. Por defecto es
   ``Authentication.Orm`` el cual usa el ORM de CakePHP.

JWT Subject
===========

Comprueba el token JWT suministrado con una fuente de datos.

Opciones de configuración:

-  **tokenField**: El campo a verificar en la base de datos. Por defecto
   es ``id``.
-  **dataField**: La clave del payload para obetener el usuario desde el identificador.
   Por defecto es ``sub``.
-  **resolver**: El resolver de la identidad. Por defecto es
   ``Authentication.Orm`` el cual usa el ORM de CakePHP.

LDAP
====

Compara las credenciales suministradas con un servidor LDAP. Este identificador
requiere la extensión PHP LDAP.

Opciones de configuración:

-  **fields**: Los campos a utilizar. Por defecto son
   ``['username' => 'username', 'password' => 'password']``.
-  **host**: El FQDN de tu servidor LDAP.
-  **port**: El puerto de tu servidor LDAP. Por defecto ``389``.
-  **bindDN**: El Distinguished Name de el usuario a autenticar. Debe
   ser un callable. Los enlaces anónimos no están soportados.
-  **ldap**: El adaptador de la extensión. Por defecto
   ``\Authentication\Identifier\Ldap\ExtensionAdapter``. Puedes suministrar un
   object/classname personalizado(a) si este(a) implementa el
   ``AdapterInterface``.
-  **options**: Opciones adicionales del LDAP, como
   ``LDAP_OPT_PROTOCOL_VERSION`` o ``LDAP_OPT_NETWORK_TIMEOUT``. Ver
   `php.net <https://php.net/manual/en/function.ldap-set-option.php>`__
   para mas opciones válidas.

Callback
========

Le permite usar un callback para la identificación. Esto es útil para
identificadores simples o creación rápida de prototipos.

Opciones de configuración:

-  **callback**: Por defecto es ``null`` y arrojará una excepción. Se requiere
   suministrar un callback válido en esta opción para usar el
   autenticador.

Los identificadores callback pueden retornar ``ArrayAccess|null`` para resultados simples,
o un ``Authentication\Authenticator\Result`` si se quiere enviar mensajes de
error::

    // Un indentificador callback simple
    $authenticationService->loadIdentifier('Authentication.Callback', [
        'callback' => function($data) {
            // Hacer la lógica del identificador

            // Devuelve un array con el usuario identificado o nulo si falla.
            if ($result) {
                return $result;
            }

            return null;
        }
    ]);

    // Usar un result object para devolver mensajes de error.
    $authenticationService->loadIdentifier('Authentication.Callback', [
        'callback' => function($data) {
            // Hacer la lógica del identificador

            if ($result) {
                return new Result($result, Result::SUCCESS);
            }

            return new Result(
                null,
                Result::FAILURE_OTHER,
                ['message' => 'Removed user.']
            );
        }
    ]);


Resolvers de identidad
======================

Los resolvers de identidades proporcionan adaptadores para diferentes fuentes de datos.
Le permiten controlar en qué identidades de origen buscar. Están separados
de los identificadores para que puedan intercambiarse independientemente del
método del identificador (formulario, jwt, autenticación básica).

ORM Resolver
------------

Es el resolver de dentidad para el ORM de CakePHP.

Opciones de configuración:

-  **userModel**: El modelo donde están localizadas las indentidades. Por defecto es
   ``Users``.
-  **finder**: El finder a usar con el modelo. Por defecto es ``all``.
   Puede leer mas sobre los finders de los modelos `aquí <https://book.cakephp.org/4/en/orm/retrieving-data-and-resultsets.html#custom-finder-methods>`__.

Para usar el resolver ORM se requiere tener ``cakephp/orm`` en su archivo
``composer.json`` (si no estás usando el framework CakePHP completo).

Escribiendo tu propio resolver
------------------------------

Cualquier ORM o fuente de datos puede ser adaptada para trabajar como
autenticación al crear un resolver. Los resolvers deben implementar
``Authentication\Identifier\Resolver\ResolverInterface`` y debe estar
bajo el namespace ``App\Identifier\Resolver``.

Un resolver puede ser configurado usando las opciones de configuración
de ``resolver``::

   $service->loadIdentifier('Authentication.Password', [
       'resolver' => [
            // puede ser un nombre de clase completo: \Some\Other\Custom\Resolver::class
           'className' => 'MyResolver',
           // Suministrar opciones adicionales al constructor del resolver.
           'option' => 'value'
       ]
   ]);

O usando un setter inyectado::

   $resolver = new \App\Identifier\Resolver\CustomResolver();
   $identifier = $service->loadIdentifier('Authentication.Password');
   $identifier->setResolver($resolver);
