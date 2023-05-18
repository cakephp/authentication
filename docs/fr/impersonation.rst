Emprunt d'identité de l'utilisateur
##################

.. version ajoutée :: 2.10.0
   L'emprunt d'identité a été ajouté.

Après avoir déployé votre application, vous devrez peut-être, occasionnellement,
emprunter l'identité d'un autre utilisateur, afin de déboguer les problèmes signalés par vos clients
ou pour voir l'application dans l'état où vos clients la voient.

Activation de la l'emprunt d'identité
======================

Pour emprunter l'identité d'un autre utilisateur, vous pouvez utiliser la méthode ``impersonate()`` sur le
``AuthenticationComponent``. Pour emprunter l'identité d'un utilisateur, vous devez d'abord le charger 
depuis la base de données de votre application ::

    // Dans un contrôleur
    public function impersonate()
    {
        $this->request->allowMethod(['POST']);

        $currentUser = $this->request->getAttribute('identity');

        // Premièrement, vous devez toujours vérifier 
        // que l'utilisateur actuel est autorisé
        // à se faire passer pour d'autres utilisateurs.
        if (!$currentUser->isStaff()) {
             throw new NotFoundException('Emprunt d\'identité interdit à l\'utilisateur courant.');
        }

        // Récupère l'utilisateur dont nous voulons emrpunter l'identité
        $targetUser = $this->Utilisateurs->findById(
            $this->request->getData('user_id')
        )->firstOrFail();

        // Activer l'emprunt d'identité.
        $this->Authentication->usurper l'identité($targetUser);
        $this->redirect($this->referer());
    }

Une fois que vous avez commencé à emprunter l'identité d'un utilisateur, toutes les demandes ultérieures auront
``$targetUser`` comme identité active.

Arrêter l'emprunt d'identité
====================

Une fois que vous avez fini d'emprunter l'identité d'un utilisateur, vous pouvez y mettre fin et revenir en arrière.

Procéder ainsi en utilisant ``AuthenticationComponent`` ::

    // Dans un contrôleur
    public function revertIdentity()
    {
        $this->request->allowMethod(['POST']);

        // Assurez-vous de l'activation du mode emprunt d'identité.
        if (!$this->Authentication->isImpersonating()) {
            throw new NotFoundException();
        }
        $this->Authentication->stopImpersonating();
    }

Limites à l'emprunt d'identité
============================

Il existe quelques limites à l'emprunt d'identité.

#. Votre application doit utiliser l'authentificateur ``Session``.
#. Vous ne pouvez pas emprunter l'identité d'un autre utilisateur lorsque l'emprunt d'identité est actif. Vous devez d'abord sortir de ce mode en lançant ``stopImpersonation()`` puis recommencer.
