Migration depuis AuthComponent
##############################

Différences
===========

-  Volontairement, ce plugin **ne** gère **pas** les autorisations. La
   fonctionnalité a été `découplée
   <https://fr.wikipedia.org/wiki/Couplage_(informatique)>`__ de l'autorisation
   dans le but de proposer une
   `séparation des préoccupations <https://fr.wikipedia.org/wiki/S%C3%A9paration_des_pr%C3%A9occupations>`__
   claire. Cf. aussi
   `Contrôle d'accès <https://fr.wikipedia.org/wiki/Contr%C3%B4le_d%27acc%C3%A8s_logique>`__.
   Ce plugin gère seulement l'\ *identification* et l'\ *authentification*. On
   peut avoir un autre plugin pour l'autorisation.
-  Il n'y a pas de vérification automatique de la session. Pour aller chercher
   les informations utilisateur dans la session, vous devrez utiliser le
   ``SessionAuthenticator``. Il va vérifier dans la session s'il y a des données
   sous la clé de session configurée, et les place ensuite dans l'objet
   Identité.
-  Les informations sur l'utilisateur ne sont plus disponibles en passant par
   l'ancien AuthComponent, mais sont accessibles *via* un attribut de la requête
   et encapsulées dans un objet Identité:
   ``$request->getAttribute('authentication')->getIdentity();``.
   En complément, vous pouvez exploiter les méthodes ``getIdentity()`` ou
   ``getIdentityData()`` de ``AuthenticationComponent``.
-  La logique du processus d'authentification a été scindée en authentificateurs
   et identificateurs. Un authentificateur va extraire les identifiants de
   l'utilisateur (*credentials*) dans la requête, tandis que les
   identificateurs les vérifieront et désigneront l'utilisateur correspondant.
-  DigestAuthenticate a été renommé en HttpDigestAuthenticator.
-  BasicAuthenticate a été renommé en HttpBasicAuthenticator.

Similitudes
===========

-  Tous les adaptateurs d'authentification existants, Form, Basic, Digest sont
   toujours là mais ont été remodelés en authentificateurs.

Identificateurs et authentificateurs
====================================

Suivant en cela le principe de séparation des préoccupations, les anciens objets
d'authentification ont été scindés en objets bien séparés, les identificateurs
et les authentificateurs.

-  Les **authentificateurs** prennent la requête entrante et tentent d'en
   extraire les identifiants de l'utilisateur. S'ils les trouvent, ils les
   passent à une collection d'identificateurs qui recherchent où se trouve
   l'utilisateur.
   Pour cette raison, les authentificateurs prennent une IdentifierCollection en
   premier argument dans leur constructeur.
-  Les **identificateurs** confrontent les identifiants à un système de stockage
   (par exemple des tables ORM, LDAP, etc) et renvoient les informations de
   l'utilisateur identifié.

Cela facilite le changement de logique d'identification en tant que de besoin,
ou l'utilisation de plusieurs sources d'informations sur les utilisateurs.

Si vous voulez implémenter vos propres identificateurs, votre identificateur
doit implémenter l'interface ``IdentifierInterface``.

Migrer votre système d'authentification
=======================================

La première chose à faire pour migrer votre application est de charger le plugin
authentication dans la méthode bootstrap de votre application::

    public function bootstrap(): void
    {
        parent::bootstrap();
        $this->addPlugin('Authentication');
    }

Ensuite, modifiez votre application pour lui faire implémenter l'interface de
fournisseur de service d'authentification. Cela permet à votre
AuthenticationMiddleware de savoir comment obtenir un service d'authentification
à partir de votre application::

    // dans src/Application.php

    // Ajoutez les instructions 'use' suivantes.
    use Authentication\AuthenticationService;
    use Authentication\AuthenticationServiceInterface;
    use Authentication\AuthenticationServiceProviderInterface;
    use Authentication\Middleware\AuthenticationMiddleware;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;

    // Ajoutez l'interface d'authentification.
    class Application extends BaseApplication implements AuthenticationServiceProviderInterface
    {
        /**
         * Renvoie une instance du service provider.
         *
         * @param \Psr\Http\Message\ServerRequestInterface $request Request
         * @param \Psr\Http\Message\ResponseInterface $response Response
         * @return \Authentication\AuthenticationServiceInterface
         */
        public function getAuthenticationService(ServerRequestInterface $request) : AuthenticationServiceInterface
        {
            $service = new AuthenticationService();
            // Configurez le service. (cf. ci-dessous pour les détails)
            return $service;
        }
    }

Puis ajoutez l'\ ``AuthenticationMiddleware`` à votre application::

    // dans src/Application.php
    public function middleware($middlewareQueue)
    {
        // Divers autres middlewares pour la gestion des erreurs, le routing, etc, sont ajoutés ici.

        // Ajoutez le middleware à la middleware queue
        $middlewareQueue->add(new AuthenticationMiddleware($this));

        return $middlewareQueue;
    }

Migrer vos réglages de AuthComponent
------------------------------------

Le tableau de configuration de ``AuthComponent`` a besoin d'être scindé en
identificateurs et authentificateurs lors de la configuration du service. Ainsi,
si votre ``AuthComponent`` était configuré de cette façon::

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

Vous devrez maintenant le configurer de cette façon::

   // Instancier le service
   $service = new AuthenticationService();

   // Charger les identificateurs
   $service->loadIdentifier('Authentication.Password', [
       'fields' => [
           'username' => 'email',
           'password' => 'password',
       ]
   ]);

   // Charger les authentificateurs
   $service->loadAuthenticator('Authentication.Session');
   $service->loadAuthenticator('Authentication.Form');

Si vous aviez personnalisé le ``userModel``, vous pouvez utiliser la
configuration suivante::

   // Instancier le service
   $service = new AuthenticationService();

   // Charger les identificateurs
   $service->loadIdentifier('Authentication.Password', [
       'resolver' => [
           'className' => 'Authentication.Orm',
           'userModel' => 'Employes',
       ],
       'fields' => [
           'username' => 'email',
           'password' => 'password',
       ]
   ]);

Bien qu'il y ait un petit peu plus de code qu'avant, vous avez plus de souplesse
dans la gestion des authentifications.

Action Login
------------

L'\ ``AuthenticationMiddleware`` va se charger de la vérification et de la
définition de l'identité de l'utilisateur en s'appuyant sur les
authentificateurs. D'habitude, après la connexion, ``AuthComponent`` redirigeait
vers une URL définie dans la configuration. Pour rediriger après une connexion
réussie, changez votre action login pour vérifier le résultat de la nouvelle
identité::

    public function login()
    {
        $result = $this->Authentication->getResult();

        // Que l'on soit en POST ou GET, rediriger l'utilisateur s'il est connecté
        if ($result->isValid()) {
            $target = $this->Authentication->getLoginRedirect();
            return $this->redirect($target);
        }

        // Afficher une erreur si l'utilisateur a validé le formulaire et que
        // l'authentification a échoué
        if ($this->request->is(['post']) && !$result->isValid()) {
            $this->Flash->error('Identifiant ou mot de passe invalide');
        }
    }

Vérifier les identités
----------------------

Après avoir appliqué le middleware vous pouvez utiliser les données d'identité
en consultant l'attribut ``identity`` de la requête. Cela remplace les appels à
``$this->Auth->user()`` que vous utilisiez jusqu'à présent. Si l'utilisateur en
cours n'est pas authentifié ou si les identifiants fournis étaient invalides,
l'attribut ``identity`` sera ``null``::

   $user = $request->getAttribute('identity');

Pour plus de détails sur le résultat du processus d'authentification, vous
pouvez accéder à l'objet Résultat qui est aussi fourni dans la requête et est
accessible sous l'attribut ``authentication``::

   $result = $request->getAttribute('authentication')->getResult();
   // Booléen si le résultat est valide
   $isValid = $result->isValid();
   // Un code de statut
   $statusCode = $result->getStatus();
   // Un tableau de messages d'erreur, ou des données si l'identificateur en a fournies
   $errors = $result->getErrors();

À chaque endroit où vous appeliez ``AuthComponent::setUser()``, vous devriez à
présent utiliser ``setIdentity()``::

   // Supposons que vous ayez besoin de rechercher un utilisateur à partir d'un jeton d'accès
   $user = $this->Users->find('byToken', ['token' => $token])->first();

   // Rendre l'utilisateur persistant dans les authentificateurs configurés.
   $this->Authentication->setIdentity($user);


Migrer la logique allow/deny
----------------------------

Comme ``AuthComponent``, l'\ ``AuthenticationComponent`` rend aisé le marquage
d'actions spécifiques comme étant 'publiques' et ne nécessitant pas la présence
d'une identité valide::

   // Dans la méthode beforeFilter de votre contrôleur.
   $this->Authentication->allowUnauthenticated(['view']);

Chaque appel à ``allowUnauthenticated()`` écrasera la liste d'actions en cours.

Migrer les Redirections en cas de Non Authentification
======================================================

Par défaut, ``AuthComponent`` renvoie les utilisateurs vers la page de connexion
lorsqu'une authentification est exigée. Au contraire, dans ce scénario,
l'\ ``AuthenticationComponent`` de ce plugin soulèvera une exception. Vous
pouvez convertir cette exception en redirection en utilisant
``unauthenticatedRedirect`` dans la configuration de
l'\ ``AuthenticationService``.

Vous pouvez aussi passer l'URI ciblée par la requête en cours en tant que
paramètre dans la query string de la redirection avec l'option ``queryParam``::

   // Dans la méthode getAuthenticationService() de votre src/Application.php

   $service = new AuthenticationService();

   // Configurer la redirection en cas de non authentification
   $service->setConfig([
       'unauthenticatedRedirect' => '/users/login',
       'queryParam' => 'redirect',
   ]);

Puis, dans la méthode login de votre contrôleur, vous pouvez utiliser en toute
sécurité ``getLoginRedirect()`` pour obtenir la cible redirigée, à partir du
paramètre de la query string::

    public function login()
    {
        $result = $this->Authentication->getResult();

        // Que l'on soit en POST ou GET, rediriger l'utilisateur s'il est connecté
        if ($result->isValid()) {
            // Utiliser le paramètre de redirection s'il est présent.
            $target = $this->Authentication->getLoginRedirect();
            if (!$target) {
                $target = ['controller' => 'Pages', 'action' => 'display', 'home'];
            }
            return $this->redirect($target);
        }
    }

Migrer la Mise à Niveau de la Logique de Hachage
================================================

Si votre application utilise la fonctionnalité de ``AuthComponent`` de mise à
niveau du hachage. Vous pouvez répliquer cette logique dans ce plugin en tirant
parti de l'\ ``AuthenticationService``::

   public function login()
   {
       $result = $this->Authentication->getResult();

       // Que l'on soit en POST ou GET, rediriger l'utilisateur s'il est connecté
       if ($result->isValid()) {
           $authService = $this->Authentication->getAuthenticationService();

           // En supposant que vous utilisez l'identificateur `Password`.
           if ($authService->identifiers()->get('Password')->needsPasswordRehash()) {
               // Le re-hachage se produit lors de la sauvegarde.
               $user = $this->Users->get($this->Authentication->getIdentityData('id'));
               $user->password = $this->request->getData('password');
               $this->Users->save($user);
           }

           // Rediriger vers une page connectée
           return $this->redirect([
               'controller' => 'Pages',
               'action' => 'display',
               'home'
           ]);
       }
   }
