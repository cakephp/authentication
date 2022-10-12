Prise en main rapide
####################

Installez le plugin avec `composer <https://getcomposer.org/>`_ depuis le
répertoire ROOT de votre projet CakePHP (là où se trouve le fichier
**composer.json**).

.. code-block:: bash

    php composer.phar require "cakephp/authentication:^2.0"

La version 2 du Plugin Authentication est compatible avec CakePHP 4.

Chargez le plugin en ajoutant l'instruction suivante dans le fichier
``src/Application.php`` de votre projet::

    public function bootstrap(): void
    {
        parent::bootstrap();

        $this->addPlugin('Authentication');
    }


Pour commencer
==============

Le plugin d'authentification s'intègre dans votre application comme un
`middleware <https://book.cakephp.org/4/en/controllers/middleware.html>`_. Il
peut aussi être utilisé comme un composant pour faciliter l'accès sans
authentification. Tout d'abord, mettons en place le middleware. Dans votre
**src/Application.php**, ajoutez ce qui suit aux imports de la classe::

    use Authentication\AuthenticationService;
    use Authentication\AuthenticationServiceInterface;
    use Authentication\AuthenticationServiceProviderInterface;
    use Authentication\Identifier\IdentifierInterface;
    use Authentication\Middleware\AuthenticationMiddleware;
    use Cake\Http\MiddlewareQueue;
    use Cake\Routing\Router;
    use Psr\Http\Message\ServerRequestInterface;


Ensuite, ajoutez ``AuthenticationServiceProviderInterface`` aux interfaces implémentées
par votre application::

    class Application extends BaseApplication implements AuthenticationServiceProviderInterface


Puis modifier votre méthode ``middleware()`` pour la faire ressembler à ceci::

    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue->add(new ErrorHandlerMiddleware(Configure::read('Error')))
            // Autres middleware fournis par CakePHP.
            ->add(new AssetMiddleware())
            ->add(new RoutingMiddleware($this))
            ->add(new BodyParserMiddleware())

            // Ajoutez le AuthenticationMiddleware. Il doit se trouver
            // après routing et body parser.
            ->add(new AuthenticationMiddleware($this));

        return $middlewareQueue();
    }

.. warning::
    L'ordre des middlewares est important. Assurez-vous d'avoir
    ``AuthenticationMiddleware`` après les middlewares routing et body parser.
    Si vous avez des problèmes pour vous connecter avec des requêtes JSON ou si
    les redirections sont incorrectes, revérifiez l'ordre de vos middlewares.

``AuthenticationMiddleware`` appellera une méthode-crochet (*hook*) dans votre
application quand il commencera à traiter la requête. Cette méthode-crochet
permet à votre application de définir l'\ ``AuthenticationService`` qu'elle veut
utiliser. Ajoutez la méthode suivante à votre **src/Application.php**::

    /**
     * Renvoie une instance du fournisseur de service.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return \Authentication\AuthenticationServiceInterface
     */
    public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
    {
        $service = new AuthenticationService();

        // Définissez vers où les utilisateurs doivent être redirigés s'ils ne
        // sont pas authentifiés
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
            IdentifierInterface::CREDENTIAL_USERNAME => 'email',
            IdentifierInterface::CREDENTIAL_PASSWORD => 'password'
        ];
        // Chargez les authentificateurs. Session est censé figurer en premier.
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

        // Chargez les identificateurs
        $service->loadIdentifier('Authentication.Password', compact('fields'));

        return $service;
    }

Premièrement, nous configurons ce qu'il faut faire lorsque les utilisateurs ne
sont pas authentifiés.
Puis nous rattachons les :doc:`/authenticators` ``Session`` et ``Form`` qui
définissent les mécanismes que votre application utilisera pour authentifier les
utilisateurs. ``Session`` active l'identification des utilisateurs à partir des
données de session, tandis que ``Form`` active le traitement par un formulaire
de connexion à l'adresse ``loginUrl``.
Enfin, nous rattachons un :doc:`identifier </identifiers>` pour convertir les
identifiants que l'utilisateur nous donnera en une
:doc:`identity </identity-object>` qui représentera l'utilisateur connecté.

Si l'un des authentificateurs configurés a été en mesure de valider les
identifiants utilisateur, le middleware ajoutera le service d'authentification à
l'objet requête en tant qu'\ `attribut <https://www.php-fig.org/psr/psr-7/>`_.

Ensuite, chargez le :doc:`/authentication-component` dans votre
``AppController``::

    // dans src/Controller/AppController.php
    public function initialize()
    {
        parent::initialize();

        $this->loadComponent('Authentication.Authentication');
    }

Par défaut, ce composant exigera un utilisateur authentifié pour **toutes** les
actions. Vous pouvez désactiver ce comportement dans certains contrôleurs en
utilisant ``allowUnauthenticated()``::

    // dans beforeFilter ou initialize d'un contrôleur
    // Faire que view et index n'exigent pas un utilisateur connecté.
    $this->Authentication->allowUnauthenticated(['view', 'index']);

Construire une Action Login
===========================

Une fois que vous aurez appliqué le middleware à votre application, vous aurez
besoin d'un moyen pour connecter les utilisateurs. Tout d'abord, générez un
modèle et un contrôleur Users avec bake:

.. code-block:: shell

    bin/cake bake model Users
    bin/cake bake controller Users

Ensuite, nous allons ajouter une action de connexion basique à votre
``UsersController``. Cela devrait ressembler à::

    // dans src/Controller/UsersController.php
    public function login()
    {
        $result = $this->Authentication->getResult();
        // Si l'utilisateur est connecté, le renvoyer ailleurs
        if ($result->isValid()) {
            $target = $this->Authentication->getLoginRedirect() ?? '/home';
            return $this->redirect($target);
        }
        if ($this->request->is('post')) {
            $this->Flash->error('Identifiant ou mot de passe invalide');
        }
    }

Assurez-vous d'autoriser l'accès à l'action ``login`` dans le callback
``beforeFilter()`` de votre contrôleur comme mentionné dans la section
précédente, de façon à ce que les utilisateurs non authentifiés puissent y avoir
accès::

    // dans src/Controller/UsersController.php
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated(['login']);
    }

Ensuite nous allons ajouter un template de vue pour notre formulaire de
connexion::

    // dans templates/Users/login.php
    <div class="users form content">
        <?= $this->Form->create() ?>
        <fieldset>
            <legend><?= __('Saisissez votre identifiant et votre mot de passe svp') ?></legend>
            <?= $this->Form->control('email') ?>
            <?= $this->Form->control('password') ?>
        </fieldset>
        <?= $this->Form->button(__('Login')); ?>
        <?= $this->Form->end() ?>
    </div>

Puis ajoutez une action de déconnexion toute simple::

    // dans src/Controller/UsersController.php
    public function logout()
    {
        $this->Authentication->logout();
        return $this->redirect(['controller' => 'Users', 'action' => 'login']);
    }

Nous n'avons pas besoin de template pour notre action logout puisque nous
faisons une redirection à la fin de celle-ci.

Ajouter un Hachage de Mot de Passe
==================================

Pour connecter vos utilisateurs, vous aurez besoin d'avoir des mots de passe
hachés. Vous pouvez hacher des mots de passe automatiquement quand les
utilisateurs mettent à jour leur mot de passe en utilisant un setter de
l'entité::

    // dans src/Model/Entity/User.php
    use Authentication\PasswordHasher\DefaultPasswordHasher;

    class User extends Entity
    {
        // ... autres méthodes

        // Hacher automatiquement les mots de passe quand ils sont modifiés.
        protected function _setPassword(string $password)
        {
            $hasher = new DefaultPasswordHasher();
            return $hasher->hash($password);
        }
    }

Vous devriez maintenant pouvoir aller à ``/users/add`` et enregistrer un nouvel
utilisateur. Une fois enregistré, vous pouvez aller à ``/users/login`` et vous
connecter sous le nom de l'utilisateur que vous venez de créer.


Pour en savoir plus
===================

* :doc:`/authenticators`
* :doc:`/identifiers`
* :doc:`/password-hashers`
* :doc:`/identity-object`
* :doc:`/authentication-component`
* :doc:`/migration-from-the-authcomponent`
* :doc:`/url-checkers`
* :doc:`/testing`
* :doc:`/view-helper`
