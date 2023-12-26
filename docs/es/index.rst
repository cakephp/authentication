Inicio Rápido
#############

Instale el plugin con `composer <https://getcomposer.org/>`_ desde el directorio ROOT
del Proyecto CakePHP (donde está localizado el archivo **composer.json**)

.. code-block:: bash

    php composer.phar require "cakephp/authentication:^2.0"

Carge el plugin agregando la siguiente declaración en ``src/Application.php``::

    public function bootstrap(): void
    {
        parent::bootstrap();

        $this->addPlugin('Authentication');
    }


Empezando
=========

El  plugin authentication se integra con su aplicación como un `middleware <https://book.cakephp.org/4/en/controllers/middleware.html>`_. También, se
puede utilizar como un componente para simplificar el acceso no autenticado. Primero
aplique el middleware. En **src/Application.php**, agregue las siguientes importaciones
de clase::

    use Authentication\AuthenticationService;
    use Authentication\AuthenticationServiceInterface;
    use Authentication\AuthenticationServiceProviderInterface;
    use Authentication\Identifier\AbstractIdentifier;
    use Authentication\Middleware\AuthenticationMiddleware;
    use Cake\Http\MiddlewareQueue;
    use Cake\Routing\Router;
    use Psr\Http\Message\ServerRequestInterface;

A continuación, agregue ``AuthenticationServiceProviderInterface`` a las interfaces implementadas
en su aplicación::

    class Application extends BaseApplication implements AuthenticationServiceProviderInterface

Luego agregue ``AuthenticationMiddleware`` a la cola de middleware en la función ``middleware()``::

    $middlewareQueue->add(new AuthenticationMiddleware($this));

.. note::
    Asegúrese de agregar ``AuthenticationMiddleware`` antes de
    ``AuthorizationMiddleware`` si tiene ambos, y después de
    ``RoutingMiddleware``.

``AuthenticationMiddleware`` llamará a un método hook en su aplicación cuando
comience a manejar la solicitud. Este método hook permite que su aplicación defina
el ``AuthenticationService`` que quiere usar. Agregue el siguiente método a su
**src/Application.php**::

    /**
     * Returns a service provider instance.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return \Authentication\AuthenticationServiceInterface
     */
    public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
    {
        $service = new AuthenticationService();

        // Define where users should be redirected to when they are not authenticated
        $service->setConfig([
            'unauthenticatedRedirect' => Router::url([
                    'prefix' => false,
                    'plugin' => null,
                    'controller' => 'Users',
                    'action' => 'login',
            ]),
            'queryParam' => 'redirect',
        ]);

        $fields = [
            AbstractIdentifier::CREDENTIAL_USERNAME => 'email',
            AbstractIdentifier::CREDENTIAL_PASSWORD => 'password'
        ];
        // Load the authenticators. Session should be first.
        $service->loadAuthenticator('Authentication.Session');
        $service->loadAuthenticator('Authentication.Form', [
            'fields' => $fields,
            'loginUrl' => Router::url([
                'prefix' => false,
                'plugin' => null,
                'controller' => 'Users',
                'action' => 'login',
            ]),
        ]);

        // Load identifiers
        $service->loadIdentifier('Authentication.Password', compact('fields'));

        return $service;
    }

En el bloque anterior, primero, configuramos qué hacer con los usuarios cuando no están autenticados.
A continuación, adjuntamos la ``Session`` y el ``Form`` :doc:`/authenticators` que definen los
mecanismos que utilizará nuestra aplicación para autenticar usuarios. ``Session`` nos permite identificar a los
usuarios en función de los datos de la sesión, mientras que ``Form`` nos permite gestionar un
formulario de inicio de sesión en el ``loginUrl``. Finalmente adjuntamos un :doc:`identifier
</identifiers>` para convertir las credenciales que los usuarios nos darán en un
:doc:`identity </identity-object>` que representa nuestro usuario registrado.

Si uno de los autenticadores configurados pudo validar las credenciales,
el middleware agregará el servicio de autenticación al objeto request como un
`attribute <https://www.php-fig.org/psr/psr-7/>`_.

A continuación, en su ``AppController`` cargue el :doc:`/authentication-component`::

    // in src/Controller/AppController.php
    public function initialize()
    {
        parent::initialize();

        $this->loadComponent('Authentication.Authentication');
    }

De forma predeterminada, el componente requerirá un usuario autenticado para **todas** las acciones.
Puede deshabilitar este comportamiento en controladores específicos usando
``allowUnauthenticated()``::

    // in a controller beforeFilter or initialize
    // Make view and index not require a logged in user.
    $this->Authentication->allowUnauthenticated(['view', 'index']);

Creación de una acción Login
============================

Una vez que haya aplicado el middleware a su aplicación, necesitará una forma para que los
usuarios inicien sesión. Primero genere un modelo y un controlador de usuarios con ``bake``:

.. code-block:: shell

    bin/cake bake model Users
    bin/cake bake controller Users

Luego, agregue una acción login a su ``UsersController``. Debería verse
así::

    // in src/Controller/UsersController.php
    public function login()
    {
        $result = $this->Authentication->getResult();
        // If the user is logged in send them away.
        if ($result->isValid()) {
            $target = $this->Authentication->getLoginRedirect() ?? '/home';
            return $this->redirect($target);
        }
        if ($this->request->is('post')) {
            $this->Flash->error('Invalid username or password');
        }
    }

Asegúrese de permitir el acceso a la acción ``login`` en su contralador en
``beforeFilter()`` callback como se menciona en la sección anterior, así
los usuarios no autenticados puedan acceder a ella::

    // in src/Controller/UsersController.php
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated(['login']);
    }

A continuación, agregaremos un template para nuestro formulario login::

    // in templates/Users/login.php
    <div class="users form content">
        <?= $this->Form->create() ?>
        <fieldset>
            <legend><?= __('Please enter your email and password') ?></legend>
            <?= $this->Form->control('email') ?>
            <?= $this->Form->control('password') ?>
        </fieldset>
        <?= $this->Form->button(__('Login')); ?>
        <?= $this->Form->end() ?>
    </div>

Luego agregue una acción logout::

    // in src/Controller/UsersController.php
    public function logout()
    {
        $this->Authentication->logout();
        return $this->redirect(['controller' => 'Users', 'action' => 'login']);
    }

No necesitamos un template para nuestra acción logout ya que redirigimos al final.

Adición de hash de contraseña
=============================

Para iniciar sesión, sus usuarios deberán tener contraseñas hash. Puede aplicar hash
a las contraseñas automáticamente cuando los usuarios actualizan su contraseña mediante un método
entity setter::

    // in src/Model/Entity/User.php
    use Authentication\PasswordHasher\DefaultPasswordHasher;

    class User extends Entity
    {
        // ... other methods

        // Automatically hash passwords when they are changed.
        protected function _setPassword(string $password)
        {
            $hasher = new DefaultPasswordHasher();
            return $hasher->hash($password);
        }
    }

Ahora debería poder ir a ``/users/add`` y registrar un nuevo usuario. Una vez registrado,
puede ir a ``/users/login``  iniciar sesión con su usuario recién creado.

Otras lecturas
==============

* :doc:`/authenticators`
* :doc:`/authentication-component`
