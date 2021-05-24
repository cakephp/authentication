Objets Identité
###############

Les objets Identité sont renvoyés par le service d'authentification et rendus
disponibles dans la requête. Les identités fournissent une méthode
``getIdentifier()`` qui peut être appelée pour obtenir dans l'identité la valeur
de l'identifiant primaire de la connexion en cours.

La raison pour laquelle cet objet existe est le besoin de fournir une interface
qui réalise ces implémentations/sources::

   // Service
   $authenticationService
       ->getIdentity()
       ->getIdentifier()

   // Component
   $this->Authentication
       ->getIdentity()
       ->getIdentifier();

   // Request
   $this->request
       ->getAttribute('identity')
       ->getIdentifier();

L'objet identité fournit ArrayAccess mais aussi une méthode ``get()`` pour
accéder aux données. Il est fortement recommandé d'utiliser la méthode ``get()``
plutôt qu'un accès à la façon des tableaux parce que la méthode ``get()`` a
connaissance du mappage des champs::

    $identity->get('email');
    $identity->get('username');

La méthode ``get()`` peut aussi être typée explicitement *via* un
méta-fichier EDI, par exemple avec
`IdeHelper <https://github.com/dereuromark/cakephp-ide-helper>`__.

Si vous voulez, vous pouvez malgré tout utiliser l'accès par propriétés::

    $identity->email;
    $identity->username;

La classe par défaut de l'objet identité peut être configurée pour mapper des
champs. C'est particulièrement utile si l'identifiant de l'identité n'est pas un
champ conventionnel ``id`` ou si vous voulez mapper des champs avec des noms
plus généraux ou plus communs::

   $identity = new Identity($data, [
       'fieldMap' => [
           'id' => 'uid',
           'username' => 'prenom'
       ]
   ]);

Créer votre propre Objet Identité
---------------------------------

Par défaut le plugin Authentication va envelopper les données utilisateur
renvoyées dans un ``IdentityDecorator`` qui mandate (*proxy*) l'accès aux
méthodes et aux propriétés. Si vous voulez créer votre propre objet identité,
votre objet doit implémenter ``IdentityInterface``.

Implémenter IdentityInterface dans votre classe User
----------------------------------------------------

Si vous voulez continuer à utiliser votre classe User existante avec ce plugin,
vous pouvez implémenter l'interface ``Authentication\IdentityInterface``::

   namespace App\Model\Entity;

   use Authentication\IdentityInterface;
   use Cake\ORM\Entity;

   class User extends Entity implements IdentityInterface
   {
       /**
        * Authentication\IdentityInterface method
        */
       public function getIdentifier()
       {
           return $this->id;
       }

       /**
        * Authentication\IdentityInterface method
        */
       public function getOriginalData()
       {
           return $this;
       }

       // Autres méthodes
   }

Utiliser un Décorateur d'Identité Personnalisé
----------------------------------------------

Si les objets résultant de vos identificateurs ne peuvent pas être modifiés pour
implémenter l'interface ``IdentityInterface``, vous pouvez implémenter un
décorateur personnalisé qui l'implémente lui-même::

   // Vous pouvez utiliser un callable...
   $identityResolver = function ($data) {
       return new MyCustomIdentity($data);
   };

   //...ou un nom de classe pour définir le wrapper d'identité.
   $identityResolver = MyCustomIdentity::class;

   // Ensuite passez-le dans la configuration du service
   $service = new AuthenticationService([
       'identityClass' => $identityResolver,
       'identifiers' => [
           'Authentication.Password'
       ],
       'authenticators' => [
           'Authentication.Form'
       ]
   ]);
