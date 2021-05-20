Middleware
##########

``AuthenticationMiddleware`` forme le cœur du plugin authentication. Il
intercepte chaque requête à destination de votre application et tente
d'authentifier l'utilisateur avec l'un des authentificateurs. Chaque
authentificateur est essayé dans l'ordre, jusqu'à ce qu'un utilisateur soit
authentifié ou qu'aucun utilisateur ne soit trouvé. Les attributs
``authentication``, ``identity`` et ``authenticationResult`` sont définis sur la
requête et contiennent l'identité, s'il y en a une qui a été trouvée, et l'objet
résultat de l'authentification qui peut contenir des messages d'erreur
supplémentaires fournis par les authentificateurs.

À la fin de chaque requête, l'\ ``identity`` est rendue persistante dans chaque
authentificateur qui supporte les états, tels que l'authentificateur
``Session``.

Configuration
=============

Toute la configuration du middleware se fait dans l'\ ``AuthenticationService``.
Vous pouvez utiliser les options de configuration suivantes sur le service:

- ``identityClass`` - Le nom de la classe de l'identité ou un constructeur
  d'identité callable.
- ``identityAttribute`` - L'attribut de la requête utilisé pour stocker
  l'identité. Par défaut ``identity``.
- ``unauthenticatedRedirect`` - L'URL vers laquelle rediriger les erreurs dues à
  l'absence d'authentification.
- ``queryParam`` - Le nom du paramètre de la query string qui contiendra l'URL
  précédemment bloquée en cas de redirection due à l'absence d'authentification,
  ou null pour désactiver l'ajout de l'URL refusée. Par défaut ``null``.


Configurer Plusieurs Paramétrages d'Authentification
====================================================

Si votre application a besoin de plusieurs paramétrages d'authentification
différents pour différentes parties de l'application, par exemple l'API et le
Web UI, vous pouvez y parvenir en utilisant une logique conditionnelle dans la
méthode crochet ``getAuthenticationService()`` de vos applications. En inspectant
l'objet requête, vous pouvez configurer l'authentification de façon appropriée::

    public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
    {
        $path = $request->getPath();

        $service = new AuthenticationService();
        if (strpos($path, '/api') === 0) {
            // Accepter uniquement les jetons d'accès API
            $service->loadAuthenticator('Authentication.Token');
            $service->loadIdentifier('Authentication.Token');

            return $service;
        }

        // Authentication web
        // Supporter les sessions et le formulaire de connexion.
        $service->loadAuthenticator('Authentication.Session');
        $service->loadAuthenticator('Authentication.Form');

        $service->loadIdentifier('Authentication.Password');

        return $service;
    }

De même que l'exemple ci-dessus utilise un préfixe de chemin, vous pouvez
appliquer une logique similaire au sous-domaine, au domaine, ou à n'importe quel
autre en-tête ou attribut présent dans la requête.
