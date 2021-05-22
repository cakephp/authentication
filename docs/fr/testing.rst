Tester avec Authentication
##########################

Une fois le middleware ``authentication`` activé dans votre application, vous
aurez besoin de simuler des identifiants de connexion dans vos tests
d'intégration.
Selon le type d'authentification que vous utilisez, vous aurez besoin de
simuler les identifiants de connexion d'une certaine façon ou d'une autre.
Examinons quelques types d'authentification parmi les plus répandus.

Authentification par la session
===============================

L'authentification par la session implique de simuler les informations
utilisateur qui devraient normalement se trouver dans la session. Dans vos
scénarios de test vous pouvez définir une méthode helper qui vous 'connecte'::

   protected function login($userId = 1)
   {
       $users = TableRegistry::getTableLocator()->get('Users');
       $user = $users->get($userId);
       $this->session(['Auth' => $user]);
   }

Dans vos tests d'intégration vous pouvez utiliser ``login()`` pour simuler un
utilisateur connecté::

   public function testGet()
   {
       $this->login();
       $this->get('/bookmarks/1');
       $this->assertResponseOk();
   }

Authentification par jeton d'accès
==================================

Avec l'authentification par jeton d'accès, vous aurez besoin de simuler
l'en-tête ``Authorization``. Après avoir obtenu un jeton d'accès valide,
paramétrez la requête::

   public function testGet()
   {
       $token = $this->getToken();
       $this->configRequest([
           'headers' => ['Authorization' => 'Bearer ' . $token]
       ]);
       $this->get('/api/bookmarks');
       $this->assertResponseOk();
   }
