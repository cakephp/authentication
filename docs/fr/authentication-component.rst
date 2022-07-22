Composant Authentication
========================

Vous pouvez utiliser ``AuthenticationComponent`` pour accéder au résultat de
l'authentification, obtenir l'identité de l'utilisateur et déconnecter
l'utilisateur. Chargez le composant dans votre méthode
``AppController::initialize()`` comme n'importe quel autre composant::

    $this->loadComponent('Authentication.Authentication', [
        'logoutRedirect' => '/users/login'  // false par défaut
    ]);

Une fois chargé, ``AuthenticationComponent`` exigera la présence d'un
utilisateur authentifié pour toutes les actions, mais ne réalise pas d'autres
contrôles d'accès. Vous pouvez désactiver cette vérification pour certaines
actions en utilisant ``allowUnauthenticated()``::

    // Dans la méthode beforeFilter de votre contrôleur.
    $this->Authentication->allowUnauthenticated(['view']);

Accéder à l'utiliseur connecté
------------------------------

Vous pouvez obtenir les données d'identité de l'utilisateur authentifié à partir
du composant Authentication::

    $user = $this->Authentication->getIdentity();

Vous pouvez aussi obtenir l'identité directement depuis l'instance de la
requête::

    $user = $request->getAttribute('identity');

Vérifier le statut de connexion
-------------------------------

Vous pouvez vérifier si le processus d'authentification s'est bien déroulé en
accédant à l'objet résultat::

    // En utilisant le composant Authentication
    $result = $this->Authentication->getResult();

    // En utilisant l'objet requête
    $result = $request->getAttribute('authentication')->getResult();

    if ($result->isValid()) {
        $user = $request->getAttribute('identity');
    } else {
        $this->log($result->getStatus());
        $this->log($result->getErrors());
    }

Le statut des objets result sets renvoyé par ``getStatus()`` correspondra à
l'une de ces constantes dans l'objet Result:

* ``ResultInterface::SUCCESS``, en cas de succès.
* ``ResultInterface::FAILURE_IDENTITY_NOT_FOUND``, lorsque l'identité n'a pas été trouvée.
* ``ResultInterface::FAILURE_CREDENTIALS_INVALID``, lorsque les identifiants de connexion sont invalides.
* ``ResultInterface::FAILURE_CREDENTIALS_MISSING``, lorsque les identifiants sont absents de la requête.
* ``ResultInterface::FAILURE_OTHER``, en cas d'échec pour toute autre raison.

Le tableau d'erreur renvoyé par ``getErrors()`` contient des informations
**supplémentaires** venant du système spécifique qui a tenté l'authentification.
Par exemple LDAP ou OAuth y placeraient les erreurs spécifiques à leurs
implémentations pour faciliter le logging et déboguer le problème. Mais la
plupart des *authenticators* n'insèrent rien à cet endroit.

Déconnecter l'utilisateur
-------------------------

Pour déconnecter un utilisateur, exécutez simplement::

    $this->Authentication->logout();

Si vous avez défini une configuration pour le paramètre ``logoutRedirect``,
``Authentication::logout()`` renverra cette valeur, sinon il renverra ``false``.
Dans tous les cas, il ne fera aucune redirection.

Au choix, vous pouvez déconnecter l'utilisateur en utilisant le service plutôt
que le composant::

    $return = $request->getAttribute('authentication')->clearIdentity($request, $response);

Le résultat renvoyé contiendra un tableau tel que celui-ci::

    [
        'response' => object(Cake\Http\Response) { ... },
        'request' => object(Cake\Http\ServerRequest) { ... },
    ]

.. note::
    Cela renverra un tableau contenant les objets requête et réponse. Puisque
    les deux sont immuables, vous aurez de nouveaux objets en retour. À partir
    de ce pointe, selon le contexte dans lequel vous travaillez, vous devrez
    utiliser ces instances si vous voulez continuer à travailler avec les objets
    requête et réponse modifiés.

Configurer les Vérifications d'Identité Automatiques
----------------------------------------------------

Par défaut, ``AuthenticationComponent`` imposera qu'une identité soit présente
pendant l'événement ``Controller.startup``. Vous pouvez faire appliquer cette
vérification plutôt dans l'événement ``Controller.initialize``::

    // Dans la méthode initialize() de votre contrôleur.
    $this->loadComponent('Authentication.Authentication', [
        'identityCheckEvent' => 'Controller.initialize',
    ]);

Vous pouvez aussi désactiver entièrement ces vérifications d'identité avec
l'option ``requireIdentity``.
