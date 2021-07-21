Componente Authentication
=========================

Puede utilizar el ``AuthenticationComponent`` para acceder al resultado de la
autenticación, obtener la identidad del usuario y cerrar la sesión del usuario. Cargue el componente en su
``AppController::initialize()`` como cualquier otro componente::

    $this->loadComponent('Authentication.Authentication', [
        'logoutRedirect' => '/users/login'  // Default is false
    ]);

Una vez cargado, el ``AuthenticationComponent`` requerirá que todas las acciones tengan un usuario
autenticado presente, pero no realiza otras comprobaciones de control de acceso. Puede deshabilitar
esta verificación para acciones específicas usando  ``allowUnauthenticated()``::

    // In your controller's beforeFilter method.
    $this->Authentication->allowUnauthenticated(['view']);

Accediendo al usuario autenticado
---------------------------------

Puede obtener los datos de identidad del usuario autenticado utilizando el componente
authentication::

    $user = $this->Authentication->getIdentity();

También puede obtener la identidad directamente desde la instancia del request::

    $user = $request->getAttribute('identity');

Comprobación del estado de inicio de sesión
-------------------------------------------

Puede verificar si el proceso de autenticación fue exitoso accediendo al objeto
result::

    // Using Authentication component
    $result = $this->Authentication->getResult();

    // Using request object
    $result = $request->getAttribute('authentication')->getResult();

    if ($result->isValid()) {
        $user = $request->getAttribute('identity');
    } else {
        $this->log($result->getStatus());
        $this->log($result->getErrors());
    }

El estado devuelto por ``getStatus()`` coincidirá con una de estas
constantes en el objeto Result:

* ``ResultInterface::SUCCESS``, when successful.
* ``ResultInterface::FAILURE_IDENTITY_NOT_FOUND``, when identity could not be found.
* ``ResultInterface::FAILURE_CREDENTIALS_INVALID``, when credentials are invalid.
* ``ResultInterface::FAILURE_CREDENTIALS_MISSING``, when credentials are missing in the request.
* ``ResultInterface::FAILURE_OTHER``, on any other kind of failure.

El array error devuelto por ``getErrors()`` contiene información **adicional**
procedente del sistema específico contra el que se realizó el intento de autenticación.
Por ejemplo, LDAP u OAuth colocarán aquí los errores específicos de su implementación
para facilitar el registro y la depuración. Pero la mayoría de los autenticadores
incluidos no ponen nada aquí.

Cerrar sesión en la identidad
-----------------------------

La sesión de una identidad se cierra simplemente con::

    $this->Authentication->logout();

Si ha establecido la configuración ``logoutRedirect``, ``Authentication::logout()`` devolverá
ese valor, de lo contrario devolverá ``false``. En este caso no realizará ninguna redirección.

Alternativamente, en lugar del componente, también puede usar el servicio para cerrar sesión::

    $return = $request->getAttribute('authentication')->clearIdentity($request, $response);

El resultado devuelto contendrá un array como este::

    [
        'response' => object(Cake\Http\Response) { ... },
        'request' => object(Cake\Http\ServerRequest) { ... },
    ]

.. note::
    Esto devolverá un array que contiene los objetos request y response,
    como ambos son inmutables se obtendran nuevos objetos. Dependiendo del contexto
    en el que esté trabajando, tendrá que usar estas instancias a partir de ahora si desea
    continuar trabajando con los objetos response y request modificados.


Configurar comprobaciones de identidad automáticas
--------------------------------------------------

De forma predeterminada, ``AuthenticationComponent`` automáticamente forzará que una entidad
esté presente durante el evento ``Controller.initialize``. Puede hacer que esta verificación se
aplique durante el evento ``Controller.startup`` en su lugar::

    // In your controller's initialize() method.
    $this->loadComponent('Authentication.Authentication', [
        'identityCheckEvent' => 'Controller.startup',
    ]);

También puede deshabilitar las verificaciones de identidad por completo con la 
opción ``requireIdentity``
