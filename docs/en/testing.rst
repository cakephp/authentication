Testing with Authentication
###########################

With the ``authentication`` middleware active in your application you'll
need to simulate authentication credentials in your integration tests. First,
ensure that your controller or middleware tests are using the
``IntegrationTestTrait``::

    // In a controller test.
    use Cake\TestSuite\IntegrationTestTrait;
    use Cake\TestSuite\TestCase;

    class ArticlesControllerTest extends TestCase
    {
        use IntegrationTestTrait;

        // Test methods and helpers to follow.
    }

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


Basic/Digest based authentication
=================================

When testing Basic or Digest Authentication, you can add the environment
variables that `PHP creates <https://php.net/manual/en/features.http-auth.php>`_
automatically.::

   public function testGet()
   {
        $this->configRequest([
            'environment' => [
                'PHP_AUTH_USER' => 'username',
                'PHP_AUTH_PW' => 'password',
            ]
        ]);
       $this->get('/api/bookmarks');
       $this->assertResponseOk();
   }
