Hacheurs de mots de passe
#########################

Default
=======

Ce hacheur utilise la constante PHP ``PASSWORD_DEFAULT`` comme méthode de
cryptage. Le type de hachage par défaut est ``bcrypt``.

Cf. `la documentation PHP <https://www.php.net/manual/fr/function.password-hash.php>`__
pour plus d'informations sur bcrypt et le hachage de mots de passe de PHP.

Les options de configuration pour cet adaptateur sont:

-  **hashType**: L'algorithme de hachage à utiliser. Les valeurs valides sont
   celles qui sont supportées par l'argument ``$algo`` de ``password_hash()``.
   Par défaut ``PASSWORD_DEFAULT``.
-  **hashOptions**: Tableau associatif d'options. Consultez le manuel PHP pour
   les options supportées pour chaque type de hachage. Par défaut un tableau
   vide.

Legacy
======

C'est un hacheur de mots de passe pour les applications migrées de CakePHP2.

Fallback
========

Le hacheur de mots de passe de repli (*fallback*) vous permet de
configurer plusieurs hacheurs qu'il vérifiera séquentiellement. Cela permet aux
utilisateurs de se connecter avec un ancien type de hachage jusqu'à ce que leur
mot de passe soit redéfini et mis à niveau vers le nouveau hachage.

Mettre à Niveau les Algorithmes de Hachage
==========================================

CakePHP propose un moyen propre de migrer les mots de passe de vos utilisateurs
d'un algorithme à un autre ; cela s'accomplit avec la classe
``FallbackPasswordHasher``. En supposant que vous veuillez migrer un mot de
passe Legacy vers le hacheur par défaut bcrypt, vous pouvez configurer le
hacheur fallback comme suit::

   $service->loadIdentifier('Authentication.Password', [
       // Autres options de configuration
       'passwordHasher' => [
           'className' => 'Authentication.Fallback',
           'hashers' => [
               'Authentication.Default',
               [
                   'className' => 'Authentication.Legacy',
                   'hashType' => 'md5',
                   'salt' => false // coupe l'utilisation par défaut du sel
               ],
           ]
       ]
   ]);

Ensuite, dans votre action login, vous pouvez utiliser le service
d'authentification pour accéder à l'identificateur ``Password`` et vérifier si
le mot de passe de l'utilisateur actuel a besoin d'être mis à niveau::

   public function login()
   {
       $authentication = $this->request->getAttribute('authentication');
       $result = $authentication->getResult();

       // Que l'on soit en POST ou GET, rediriger l'utilisateur s'il est connecté
       if ($result->isValid()) {
           // En supposant que vous utilisez l'identificateur `Password`.
           if ($authentication->identifiers()->get('Password')->needsPasswordRehash()) {
               // Le re-hachage se produit lors de la sauvegarde.
               $user = $this->Users->get($this->Auth->user('id'));
               $user->password = $this->request->getData('password');
               $this->Users->save($user);
           }

           // Rediriger ou afficher un template.
       }
   }
