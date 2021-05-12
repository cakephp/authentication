Testing with Authentication
###########################

With the ``authentication`` middleware active in your application you'll
need to simulate authentication credentials in your integration tests.
Based on the type of authentication you're using you will need to
simulate credentials differently. Lets review a few more common types of
authentication.

Session based authentication
============================

Session based authentication requires simulating the User data that
normally would be found in the session. In your test cases you can
define a helper method that lets you 'login'::

   protected function login($userId = 1)
   {
       $users = TableRegistry::getTableLocator()->get('Users');
       $user = $users->get($userId);
       $this->session(['Auth' => $user]);
   }

In your integration tests you can use ``login()`` to simulate a user
being logged in::

   public function testGet()
   {
       $this->login();
       $this->get('/bookmarks/1');
       $this->assertResponseOk();
   }

Token based authentication
==========================

With token based authentication you need to simulate the
``Authorization`` header. After getting valid token setup the request::

   public function testGet()
   {
       $token = $this->getToken();
       $this->configRequest([
           'headers' => ['Authorization' => 'Bearer ' . $token]
       ]);
       $this->get('/api/bookmarks');
       $this->assertResponseOk();
   }
