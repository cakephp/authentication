Identificateurs
###############

Les identificateurs vont identifier un utilisateur ou un service à partir des
informations qui auront été extraites de la requête par les authentificateurs.
Les identificateurs peuvent prendre des options dans la méthode
``loadIdentifier``.
Voici un exemple général d'utilisation du *Password Identifier*::

   $service->loadIdentifier('Authentication.Password', [
       'fields' => [
           'username' => 'email',
           'password' => 'passwd',
       ],
       'resolver' => [
           'className' => 'Authentication.Orm',
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

Password
========

L'identificateur par mot de passe confronte les identifiants avec la source de
données.

Options de configuration:

-  **fields**: Les champs à regarder. Par défaut
   ``['username' => 'username', 'password' => 'password']``. Vous pouvez aussi
   définir le ``username`` en tableau. Par exemple, utiliser
   ``['username' => ['username', 'email'], 'password' => 'password']`` vous
   permettra de confronter la valeur soit de la colonne ``username``, soit de la
   colonne ``email``.
-  **resolver**: Le résolveur d'identité. Par défaut ``Authentication.Orm``, qui
   utilise l'ORM CakePHP.
-  **passwordHasher**: Le hacheur de mots de passe. Par défaut
   ``DefaultPasswordHasher::class``.

Token
=====

Confronte le jeton d'accès avec la source de données.

Options de configuration:

-  **tokenField**: Le champ à confronter dans la base de données. Par défaut
   ``token``.
-  **dataField**: Le champ dans les données transmises par l'authentificateur.
   Par défaut ``token``.
-  **resolver**: Le résolveur d'identité. Par défaut ``Authentication.Orm``, qui
   utilise l'ORM CakePHP.

JWT Subject
===========

Confronte le jeton d'accès JWT avec la source de données.

-  **tokenField**: Le champ à confronter dans la base de données. Par défaut
   ``id``.
-  **dataField**: La clé payload à partir de laquelle obtenir l'utilisateur. Par
   défaut ``sub``.
-  **resolver**: Le résolveur d'identité. Par défaut ``Authentication.Orm``, qui
   utilise l'ORM CakePHP.

LDAP
====

Confronte les identifiants fournis avec un serveur LDAP. Cet identificateur
nécessite l'extention PHP LDAP.

-  **fields**: Les champs à regarder. Par défaut
   ``['username' => 'username', 'password' => 'password']``.
-  **host**: Le nom complet de domaine (FQDN) de votre serveur LDAP.
-  **port**: Le port de votre serveur LDAP. Par défaut ``389``.
-  **bindDN**: Le nom distinctif (*Distinguished Name*) de l'utilisateur
   à identifier. Doit être *callable*. Les *binds* anonymes de
   sont pas supportés.
-  **ldap**: L'adaptateur d'extension. Par défaut
   ``\Authentication\Identifier\Ldap\ExtensionAdapter``. Vous pouvez passer un
   objet ou une classe personnalisée ici à condition qu'elle implémente
   l'\ ``AdapterInterface``.
-  **options**: Options supplémentaires LDAP, telles que
   ``LDAP_OPT_PROTOCOL_VERSION`` ou ``LDAP_OPT_NETWORK_TIMEOUT``. Cf.
   `php.net <http://php.net/manual/en/function.ldap-set-option.php>`__
   pour en savoir plus sur les options valides.

Callback
========

Permet d'utiliser un callback pour l'identification. C'est utile pour des
identificateurs simples ou pour un prototypage rapide.

Options de configuration:

-  **callback**: La valeur par défaut est ``null`` et entraînera une exception.
   Vous devez impérativement placer un callback valide dans cette option pour
   utiliser l'authentificateur.

Les identificateurs Callback peuvent renvoyer soit ``null|ArrayAccess`` pour des
résultats simples, soit un ``Authentication\Authenticator\Result`` si vous
voulez transférer des messages d'erreur::

    // Un identificateur simple par callback
    $authenticationService->loadIdentifier('Authentication.Callback', [
        'callback' => function($data) {
            // faire la logique de l'identification

            // Renvoyer un tableau de l'utilisateur identifié
            // ou null en cas d'échec
            if ($result) {
                return $result;
            }

            return null;
        }
    ]);

    // Utiliser un objet result pour renvoyer des messages d'erreur.
    $authenticationService->loadIdentifier('Authentication.Callback', [
        'callback' => function($data) {
            // faire la logique de l'identification

            if ($result) {
                return new Result($result, Result::SUCCESS);
            }

            return new Result(
                null,
                Result::FAILURE_OTHER,
                ['message' => 'Utilisateur effacé.']
            );
        }
    ]);


Résolveurs d'identité
=====================

Les résolveurs d'identité fournissent des adaptateurs pour différentes sources
de données. Ils vous permettent de contrôler dans quelle source les identités
sont recherchées. Ils sont séparés des identificateurs, de sorte qu'ils sont
interchangeables indépendamment de la méthode d'identification (form, jwt, basic
auth).

Résolveur ORM
-------------

Le résolveur d'identité pour l'ORM CakePHP.

Options de configuration:

-  **userModel**: Le modèle utilisateur dans lequel sont situées les identités.
   Par défaut ``Users``.
-  **finder**: Le finder à utiliser avec le modèle. Par défaut ``all``.

Afin d'utiliser le résolveur ORM, vous devez requérir ``cakephp/orm`` dans votre
fichier ``composer.json`` (si vous n'utilisez pas déjà le framework CakePHP
complet).

Écrire vos propres résolveurs
-----------------------------

Chaque ORM ou source de données peut être adapté pour fonctionner avec
l'authentification en créant un résolveur. Les résolveurs doivent implémenter
``Authentication\Identifier\Resolver\ResolverInterface`` et devraient être
placés dans le namespace ``App\Identifier\Resolver``.

Les résolveurs peuvent être configurés en utilisant l'option de configuration
``resolver``::

   $service->loadIdentifier('Authentication.Password', [
       'resolver' => [
            // peut être un nom de classe complet: \Some\Other\Custom\Resolver::class
           'className' => 'MyResolver',
           // Passer des options supplémentaires pour le constructeur du résolveur.
           'option' => 'value'
       ]
   ]);

Ou être injectés avec un setter::

   $resolver = new \App\Identifier\Resolver\CustomResolver();
   $identifier = $service->loadIdentifier('Authentication.Password');
   $identifier->setResolver($resolver);
