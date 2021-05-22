View Helper (Assistant)
=======================

Dans votre AppView, chargez le Helper ainsi::

   $this->loadHelper('Authentication.Identity');

Pour vérifier très simplement si l'utilisateur est connecté, vous pouvez
utiliser::

   if ($this->Identity->isLoggedIn()) {
       ...
   }

Il est possible d'obtenir les informations sur l'utilisateur avec::

   $username = $this->Identity->get('username');

Vous pouvez utiliser la vérification suivante pour savoir si un enregistrement
qui appartient à un certain utilisateur est bien celui de l'utilisateur
actuellement connecté, et même pour comparer d'autres champs::

   $isCurrentUser = $this->Identity->is($user->id);
   $isCurrentRole = $this->Identity->is($user->role_id, 'role_id');

Cette méthode est surtout une méthode de confort pour les cas simples et n'a pas
vocation à remplacer une quelconque implémentation d'autorisations à proprement
parler.
