Authentificateurs
#################

Les authentificateurs (*authenticators*) sont chargés de convertir les
données de la requête en opérations d'authentification. Ils s'appuient sur les
:doc:`/identifiers` pour trouver un :doc:`/identity-object` connu.

Session
=======

Cet authentificateur va vérifier si la session contient des informations
utilisateur ou des identifiants. Quand vous utilisez des authentificateurs à
états listés ci-dessous, tels que ``Form``, assurez-vous de charger d'abord
l'authentificateur ``Session``, de manière à ce qu'une fois que l'utilisateur
est connecté, ses données soient récupérées depuis la session elle-même lors des
requêtes suivantes.

Les options de configuration:

-  **sessionKey**: La clé de session pour les données de l'utilisateur, par
   défaut ``Auth``.
-  **identify**: Définissez cette clé avec la valeur booléenne ``true`` pour
   activer la confrontation des identifiants utilisateur contenus dans la
   session avec les identificateurs (*identifiers*). Lorsque que la valeur est
   ``true``, les :doc:`/identifiers` configurés sont utilisés à chaque requête
   pour identifier l'utilisateur à partir des informations stockées en session.
   La valeur par défaut est ``false``.
-  **fields**: Vous permet de mapper le champ ``username`` à l'identifiant
   unique dans votre système de stockage des utilisateurs. Vaut ``username`` par
   défaut. Cette option est utilisée quand l'option ``identify`` est définie à
   *true*.

Form
====

Consulte les données dans le corps de la requête, habituellement quand un
formulaire a été soumis via POST / PUT.

Options de configuration:

-  **loginUrl**: L'URL de connexion, chaîne de texte ou tableau d'URLs. La
   valeur par défaut est ``null`` et toutes les pages seront vérifiées.
-  **fields**: Tableau qui mappe ``username`` et ``password`` aux champs de
   données POST spécifiés.
-  **urlChecker**: La classe ou l'instance de vérification d'URL. Par défaut
   ``DefaultUrlChecker``.
-  **useRegex**: Indique si l' *URL matching* doit ou non utiliser des
   expressions régulières. Par défaut ``false``.
-  **checkFullUrl**: Indique s'il faut vérifier l'URL entière, y compris la
   *query string*. Utile quand le formulaire de connexion est dans un
   sous-domaine différent. Par défaut ``false``. Cette option ne fonctionne pas
   correctement lorsqu'on conserve des redirections en cas de
   non-authentification dans la query string.

Si vous construisez une API et que vous voulez accepter les identifiants envoyés
dans une requête JSON, veillez à ce que ``BodyParserMiddleware`` soit défini
**avant** le ``AuthenticationMiddleware``.

.. warning::
    Si vous utilisez la syntaxe en tableau pour l'URL, l'URL sera générée par le
    routeur de CakePHP. Selon la gestion des routes, **il se peut** que le résultat soit différent de ce que
    vous avez en réalité dans l'URI de la requête. Dès lors, considérez cela
    comme sensible à la casse!

Token
=====

L'authentificateur par jeton d'accès (*token*) peut authentifier une
requête en se fondant sur un jeton d'accès qui est transmis avec la requête,
que ce soit dans les en-têtes ou dans les paramètres de cette dernière.

Options de configuration:

-  **queryParam**: Nom du paramètre dans la requête. Configurez-le si vous
   voulez récupérer le jeton d'accès depuis les paramètres de la requête.
-  **header**: Nom de l'en-tête. Configurez-le si vous voulez récupérer le jeton
   d'accès depuis l'en-tête.
-  **tokenPrefix**: Le préfixe du jeton d'accès (optionnel).

Un exemple de récupération d'un jeton d'accès à partir d'une en-tête ou d'une
query string pourrait être::

    $service->loadAuthenticator('Authentication.Token', [
        'queryParam' => 'token',
        'header' => 'Authorization',
        'tokenPrefix' => 'Token'
    ]);

Ce qui précède lirait le paramètre GET ``token`` ou l'en-tête ``Authorization``,
dès lors que le jeton d'accès serait précédé par ``Token`` et d'une espace.

Le jeton d'accès sera toujours passé de la façon suivante à l'identificateur
configuré::

    [
        'token' => '{token-value}',
    ]

JWT
===

L'authentificateur JWT obtient le `jeton d'accès JWT <https://jwt.io/>`__ à
partir de l'en-tête ou du paralètre de la requête et, selon le cas, renvoie la
payload directement la passe aux identificateurs pour la confronter à une autre
source de données, par exemple.

-  **header**: La ligne d'en-tête dans laquelle chercher le jeton d'accès. La
   valeur par défaut est ``Authorization``.
-  **queryParam**: Le paramètre de requête dans lequel chercher le jeton
   d'accès. La valeur par défaut est ``token``.
-  **tokenPrefix**: Le préfixe du jeton d'accès. La valeur par défaut est
   ``bearer``.
-  **algorithm**: L'algorithme de hachage pour Firebase JWT. La valeur par défaut
   est ``'HS256'``.
-  **returnPayload**: Renvoyer ou non la payload du jeton d'accès directement
   sans passer par les identificateurs. La valeur par défaut est ``true``.
-  **secretKey**: La valeur par défaut est ``null`` mais vous **devez
   impérativement** transmettre une clé secrète si vous n'êtes pas dans le
   contexte d'une application CakePHP qui le fournit déjà par
   ``Security::salt()``.
-  **jwks**: Par défaut ``null``. Tableau associatif avec une clé ``'keys'``.
   S'il est fourni, il sera utilisé à la place de ``secret key``.

Pour utiliser le ``JwtAuthenticator``, vous devez ajouter à votre application la
bibliothèque `firebase/php-jwt <https://github.com/firebase/php-jwt>`__ v6.2 ou
supérieure.

Par défaut, le ``JwtAuthenticator`` utilise l'algorithme de clé symétrique
``HS256`` et utilise la valeur de ``Cake\Utility\Security::salt()`` comme clé de
cryptage.
Pour plus de sécurité, il est possible d'utiliser à la place l'algorithme de clé
asymétrique ``RS256``. Vous pouvez générer les clés nécessaires comme suit::

    # générer la clé privée
    openssl genrsa -out config/jwt.key 1024
    # générer la clé publique
    openssl rsa -in config/jwt.key -outform PEM -pubout -out config/jwt.pem

Le fichier ``jwt.key`` est la clé privée et doit être gardé en sécurité. Le
fichier ``jwt.pem`` est la clé publique. Ce fichier devrait être utilisé quand
vous avez besoin de vérifier les jetons d'accès créés par une application
externe, par exemple les applications mobiles.

L'exemple suivant vous permet d'identifier l'utilisateur à partir du ``sub``
(*subject*) du jeton d'accès en utilisant l'identificateur ``JwtSubject``, et
configure l'\ ``Authenticator`` pour utiliser une clé publique lors de la
vérification du jeton d'accès.

Ajoutez ce qui suit dans votre classe ``Application``::

    public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
    {
        $service = new AuthenticationService();
        // ...
        $service->loadIdentifier('Authentication.JwtSubject');
        $service->loadAuthenticator('Authentication.Jwt', [
            'secretKey' => file_get_contents(CONFIG . '/jwt.pem'),
            'algorithm' => 'RS256',
            'returnPayload' => false
        ]);
    }

Dans votre ``UsersController``::

    use Firebase\JWT\JWT;

    public function login()
    {
        $result = $this->Authentication->getResult();
        if ($result->isValid()) {
            $privateKey = file_get_contents(CONFIG . '/jwt.key');
            $user = $result->getData();
            $payload = [
                'iss' => 'myapp',
                'sub' => $user->id,
                'exp' => time() + 60,
            ];
            $json = [
                'token' => JWT::encode($payload, $privateKey, 'RS256'),
            ];
        } else {
            $this->response = $this->response->withStatus(401);
            $json = [];
        }
        $this->set(compact('json'));
        $this->viewBuilder()->setOption('serialize', 'json');
    }

Cela marche aussi en utilisant un JWKS récupéré depuis un terminal JWKS
extérieur::

    // Application.php
    public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
    {
        $service = new AuthenticationService();
        // ...
        $service->loadIdentifier('Authentication.JwtSubject');

        $jwksUrl = 'https://appleid.apple.com/auth/keys';

        // Ensemble de clés. La clé "keys" est nécessaire. De plus les clés
        // nécessitent une clé "alg".
        // Ajoutez-la manuellement à votre tableau JWK si elle n'existe pas déjà.
        $jsonWebKeySet = Cache::remember('jwks-' . md5($jwksUrl), function () use ($jwksUrl) {
            $http = new Client();
            $response = $http->get($jwksUrl);
            return $response->getJson();
        });

        $service->loadAuthenticator('Authentication.Jwt', [
            'jwks' => $jsonWebKeySet,
            'returnPayload' => false
        ]);
    }

La ressource JWKS renverra la plupart du temps le même ensemble de clés.
Les applications devraient mettre ces ressources en cache, mais elles doivent
aussi être préparées à gérer la rotation des clés de chiffrement.

.. warning::

    Les applications doivent choisir une durée de vie du cache qui fasse un
    compromis entre la performance et la sécurité.
    C'est particulièrement important dans les situations où une clé privée
    serait compromise.

Au lieu de partager votre clé publique avec des applications externes, vous
pouvez les distribuer via un point terminal JWKS en configurant votre
application comme suit::

    // config/routes.php
    $builder->setExtensions('json');
    $builder->connect('/.well-known/:controller/*', [
        'action' => 'index',
    ], [
        'controller' => '(jwks)',
    ]); // connect /.well-known/jwks.json to JwksController

    // controller/JwksController.php
    public function index()
    {
        $pubKey = file_get_contents(CONFIG . './jwt.pem');
        $res = openssl_pkey_get_public($pubKey);
        $detail = openssl_pkey_get_details($res);
        $key = [
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'e' => JWT::urlsafeB64Encode($detail['rsa']['e']),
            'n' => JWT::urlsafeB64Encode($detail['rsa']['n']),
        ];
        $keys['keys'][] = $key;

        $this->viewBuilder()->setClassName('Json');
        $this->set(compact('keys'));
        $this->viewBuilder()->setOption('serialize', 'keys');
    }

Consultez https://datatracker.ietf.org/doc/html/rfc7517 ou
https://auth0.com/docs/tokens/json-web-tokens/json-web-key-sets pour plus
d'informations à propos de JWKS.

HttpBasic
=========

Cf. https://en.wikipedia.org/wiki/Basic_access_authentication

.. note::

    Cet authentificateur arrêtera la requête si les identifiants
    d'authentification sont absents ou invalides.

Options de configuration:

-  **realm**: Par défaut ``$_SERVER['SERVER_NAME']``. Remplacez-le en tant que
   de besoin.

HttpDigest
==========

Cf. https://en.wikipedia.org/wiki/Digest_access_authentication

Options de configuration:

-  **realm**: Par défaut ``null``
-  **qop**: Par défaut ``auth``
-  **nonce**: Par défaut ``uniqid(''),``
-  **opaque**: Par défaut ``null``

Authentificateur Cookie, alias "Se Souvenir de Moi"
===================================================

L'authentificateur ``Cookie`` vous permet d'implémenter la fonctionnalité "se
souvenir de moi" dans vos formulaires de connexion.

Assurez-vous simplement que votre formulaire a un champ qui correspond au nom de
champ configuré dans cet authentificateur.

Pour crypter et décrypter votre cookie assurez-vous d'avoir ajouté
l'EncryptedCookieMiddleware à votre application *avant*
l'AuthenticationMiddleware.

Options de configuration:

-  **rememberMeField**: Par défaut ``remember_me``
-  **cookie**: Tableau d'options du cookie:

   -  **name**: Nom du cookie, par défaut ``CookieAuth``
   -  **expires**: Expiration, par défaut ``null``
   -  **path**: Chemin, par défaut ``/``
   -  **domain**: Domaine, par défaut une chaîne vide.
   -  **secure**: Booléen, par défaut ``false``
   -  **httponly**: Booléen, par défaut ``false``
   -  **value**: Valeur, par défaut une chaîne vide.
   -  **samesite**: String/null La valeur de l'attribut samesite.

   Les valeurs par défaut des diverses options, à part ``cookie.name``, seront
   celles définies pour la classe ``Cake\Http\Cookie\Cookie``. Référez-vous à
   `Cookie::setDefaults() <https://api.cakephp.org/4.0/class-Cake.Http.Cookie.Cookie.html#setDefaults>`_
   pour les valeurs par défaut.

-  **fields**: Tableau qui mappe ``username`` et ``password`` aux champs
   d'identité spécifiés.
-  **urlChecker**: La classe ou l'instance du vérificateur d'URL. Par défaut
   ``DefaultUrlChecker``.
-  **loginUrl**: L'URL de connexion, chaîne ou tableau d'URLs. Par défaut
   ``null`` et toutes les pages seront vérifiées.
-  **passwordHasher**: Le hacheur de mot de passe à utiliser pour le hachage du
   jeton d'accès. Par défaut ``DefaultPasswordHasher::class``.
-  **salt**: Si ``false``, aucun grain de sel n'est utilisé. Si c'est une chaîne
   de caractères, cette chaîne est utilisée comme grain de sel. Si ``true``,
   c'est la valeur par défaut Security.salt qui sera utilisée. ``true`` Par
   défaut. Quand un grain de sel est utilisé, la valeur du cookie contiendra
   `hash(username + password + hmac(username + password, salt))`. Cela contribue
   à durcir les jetons contre de possible failles de la base de données et
   active l'invalidation des cookies à chaque rotation du grain de sel.

Utilisation
-----------

L'authentificateur par cookie peut compléter un système d'authentification basé
sur Form & Session. L'authentificateur Cookie reconnectera automatiquement les
utilisateurs après que leur session aura expiré, aussi longtemps que le cookie
restera valide. Si un utilisateur est explicitement déconnecté via
``AuthenticationComponent::logout()``, l'authentificateur cookie est **lui aussi
détruit**. Un exemple de configuration serait::

    // Dans Application::getAuthService()

    // Réutiliser les champs dans plusieurs authentificateurs.
    $fields = [
        IdentifierInterface::CREDENTIAL_USERNAME => 'email',
        IdentifierInterface::CREDENTIAL_PASSWORD => 'password',
    ];

    // Placer l'authentification par formulaire en premier de façon à ce que les
    // utilisateurs puissent se reconnecter via le formulaire si besoin.
    $service->loadAuthenticator('Authentication.Form', [
        'loginUrl' => '/users/login',
        'fields' => [
            IdentifierInterface::CREDENTIAL_USERNAME => 'email',
            IdentifierInterface::CREDENTIAL_PASSWORD => 'password',
        ],
    ]);
    // Ensuite utiliser les sessions si elles sont actives.
    $service->loadAuthenticator('Authentication.Session');

    // Si l'utilisateur est sur la page de connexion, vérifier aussi un éventuel cookie.
    $service->loadAuthenticator('Authentication.Cookie', [
        'fields' => $fields,
        'loginUrl' => '/users/login',
    ]);

Vous aurez aussi besoin d'ajouter une case à cocher à votre formulaire pour
générer la création de cookie::

    // Dans la vue de votre formulaire de connesion
    <?= $this->Form->control('remember_me', ['type' => 'checkbox']);

Après la connexion, si votre case à cocher a été cochée, vous devriez voir un
cookie ``CookieAuth`` dans les outils de développement de votre navigateur. Le
cookie enregistre l'identifiant de l'utilisateur (*username*) et un jeton
d'accès haché qui est utilisé ultérieurement pour se réauthentifier.

Événements
==========

Il n'y a qu'un événement déclenché par l'authentification:
``Authentication.afterIdentify``.

Si vous ne savez pas ce que sont les événements ou comment les utiliser,
`consultez la documentation <https://book.cakephp.org/4/fr/core-libraries/events.html>`__.

L'événement ``Authentication.afterIdentify`` est lancé par
l'\ ``AuthenticationComponent`` après qu'une identité a été identifiée avec
succès.

L'événement contient les informations suivantes:

-  **provider**: Un objet qui implémente
   ``\Authentication\Authenticator\AuthenticatorInterface``
-  **identity**: Un objet qui implémente ``\ArrayAccess``
-  **service**: Un objet qui implémente
   ``\Authentication\AuthenticationServiceInterface``

Le sujet de l'événement sera l'instance du contrôleur en cours auquel
l'AuthenticationComponent est attaché.

Mais l'événement ne sera déclenché que si l'authentificateur qui a été utilisé
pour identifier l'identité n'est *ni* persistant *ni* stateless. La raison en
est que sinon, l'évenement serait déclenché à chaque fois parce que
les authentificateurs par session ou par jeton, par exemple, le lanceraient
systématiquement à chaque requête.

Parmi les authentificateurs fournis, seul FormAuthenticator entraînera le
déclenchement de l'événement. Par la suite, l'authentificateur par session
fournira l'identité.

Vérificateurs d'URL
===================

Certains authentificateurs comme ``Form`` ou ``Cookie`` ne devraient être
exécutés que sur certaines pages, telles que la page ``/login``. Cela peut être
obtenu grâce aux vérificateurs d'URL.

Par défaut, CakePHP utilise un ``DefaultUrlChecker`` qui confronte le texte des
URLs à un moteur d'expressions régulières.

Options de configuration:

-  **useRegex**: S'il faut ou non utiliser des expressions régulières pour la
   l'analyse des URL. La valeur par défaut est ``false``.
-  **checkFullUrl**: S'il faut ou non vérifier l'URL entière. Utile quand le
   formulaire de connexion se trouve dans un sous-domaine différent. La valeur
   par défaut est ``false``.

Un vérificateur d'URL personnalisé peut par exemple être implémenté si on a
besoin de supporter des URLs spécifiques à un framework. Dans ce cas,
l'interface ``Authentication\UrlChecker\UrlCheckerInterface`` devrait être
implémentée.

Pour plus de détails sur les vérificateurs d'URLs,
:doc:`reportez-vous à cette page </url-checkers>`.

Obtenir l'Authentificateur ou l'Identificateur qui a réussi
===========================================================

Après qu'un utilisateur a été identifié, vous voudrez sans doute inspecter
l'Authenticator qui a réussi à authentifier l'utilisateur, ou
interagir avec lui::

    // Dans une action d'un contrôleur
    $service = $this->request->getAttribute('authentication');

    // Sera null en cas d'échec d'authentification, sinon un authentificateur.
    $authenticator = $service->getAuthenticationProvider();

Vous pouvez tout aussi bien obtenir l'identificateur qui a identifié
l'utilisateur::

    // Dans une action d'un contrôleur
    $service = $this->request->getAttribute('authentication');

    // Sera null en cas d'échec d'authentification, sinon un identificateur.
    $identifier = $service->getIdentificationProvider();


Utiliser conjointement des Authentificateurs Stateless et Stateful
==================================================================

Quand vous utilisez ``HttpBasic``, ``HttpDigest`` avec d'autres
authentificateurs, vous devez vous souvenir que ces authentificateurs arrêteront
la requête si les identifiants de connexion sont absents ou invalides. C'est
indispensable puisque ces authentificateurs doivent envoyer dans la réponse des
en-têtes comportant un défi spécifique::

    use Authentication\AuthenticationService;

    // Instancier le service
    $service = new AuthenticationService();

    // Charger les identificateurs
    $service->loadIdentifier('Authentication.Password', [
        'fields' => [
            'username' => 'email',
            'password' => 'password'
        ]
    ]);
    $service->loadIdentifier('Authentication.Token');

    // Charger les authentificateurs en plaçant Basic en dernier.
    $service->loadAuthenticator('Authentication.Session');
    $service->loadAuthenticator('Authentication.Form');
    $service->loadAuthenticator('Authentication.HttpBasic');

Si vous voulez combiner ``HttpBasic`` ou ``HttpDigest`` avec d'autres
authentificateurs, ayez conscience que ces authentificateurs interrompront la
requête et forceront l'ouverture d'une boîte de dialogue dans le navigateur.

Gérer les Erreurs de Non-Authentification
=========================================

Le composant ``AuthenticationComponent`` soulèvera une exception lorsque des
utilisateurs ne sont pas connectés. Vous pouvez convertir ces exceptions en
redirections en utilisant ``unauthenticatedRedirect`` dans la configuration de
l'\ ``AuthenticationService``.

Vous pouvez aussi passer l'URI cible de la requête en cours en tant que
paramètre de requête en utilisant l'option ``queryParam``::

   // Dans la méthode getAuthenticationService() de votre src/Application.php

   $service = new AuthenticationService();

   // Configure la redirection en cas de non-authentification
   $service->setConfig([
       'unauthenticatedRedirect' => '/users/login',
       'queryParam' => 'redirect',
   ]);

Ensuite, dans la méthode login de votre contrôleur, vous pouvez utiliser
``getLoginRedirect()`` pour obtenir la cible de redirection en toute sécurité à
partir du paramètre de la *query string*::

    public function login()
    {
        $result = $this->Authentication->getResult();

        // Que l'on soit en POST ou GET, rediriger l'utilisateur s'il est connecté
        if ($result->isValid()) {
            // Utiliser le paramètre de redirection s'il est présent
            $target = $this->Authentication->getLoginRedirect();
            if (!$target) {
                $target = ['controller' => 'Pages', 'action' => 'display', 'home'];
            }
            return $this->redirect($target);
        }
    }

Avoir Plusieurs Canaux d'Authentication
=======================================

Dans une application qui fournit à la fois une API et une interface web, vous
voudrez probablement des configurations différentes d'authentification selon que
la requête est ou non une requête d'API. Par exemple, vous pourriez vouloir
utiliser une authentification JWT pour votre API, mais des sessions pour votre
interface web. Pour prendre en charge ces différents flux, vous pouvez renvoyer
des services d'authentification différents selon le chemin de l'URL, ou selon
n'importe quel autre attribut de la requête::

    public function getAuthenticationService(
        ServerRequestInterface $request
    ): AuthenticationServiceInterface {
        $service = new AuthenticationService();

        // La configuration commune à l'API et au web est placée ici.

        if ($request->getParam('prefix') == 'Api') {
            // Inclure les authentificateurs spécifiques pour l'API
        } else {
            // Authentificateurs spécifiques pour l'interface web.
        }

        return $service;
    }
