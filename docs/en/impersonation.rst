User Impersonation
##################

.. versionadded:: 3.0.0
   User impersonation was added.

After deploying your application, you may occasionally need to
'impersonate' another user in order to debug problems that your customers report
or to see the application in the state that your customers are seeing it.

Enabling Impersonation
======================

To impersonate another user you can use the ``impersonate()`` method on the
``AuthenticationComponent``. To impersonate a user you first need to load that
user from your application's database::

    // In a controller 
    public function impersonate()
    {
        $this->request->allowMethod(['POST']);

        $currentUser = $this->request->getAttribute('identity');

        // You should always check that the current user is allowed
        // to impersonate other users first.
        if (!$currentUser->isStaff()) {
            throw new NotFoundException();
        }

        // Fetch the user we want to impersonate.
        $targetUser = $this->Users->findById(
            $this->request->getData('user_id')
        )->firstOrFail();

        // Enable impersonation.
        $this->Authentication->impersonate($targetUser);
        $this->redirect($this->referer());
    }

Once you have started to impersonate a user, all subsequent requests will have
``$targetUser`` as the active identity.

Ending Impersonation
====================

Once you are done impersonating a user, you can then end impersonation and revert
back to your previous identity using ``AuthenticationComponent``::

    // In a controller
    public function revertIdentity()
    {
        $this->request->allowMethod(['POST']);

        // Make sure we are still impersonating a user.
        if (!$this->Authentication->isImpersonating()) {
            throw new NotFoundException();
        }
        $this->Authentication->stopImpersonating();
    }

Limitations to Impersonation
============================

There are a few limitations to impersonation.

#. Your application must be using the ``Session`` authenticator.
#. You cannot impersonate another user while impersonation is active. Instead
   you must ``stopImpersonation()`` and then start it again.
