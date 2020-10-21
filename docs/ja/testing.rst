認証によるテスト
######################

アプリケーションで ``authentication`` ミドルウェアがアクティブな状態であれば、
統合テストで認証情報をシミュレートする必要があります。
使用している認証の種類に応じて、クレデンシャルを異なる方法でシミュレートする必要があります。
認証の一般的なタイプをいくつか確認してみましょう。

セッションベースの認証
============================

セッションベースの認証では、通常セッションで見つかるであろうユーザデータをシミュレートする必要があります。
テストケースでは、「ログイン」するためのヘルパーメソッドを定義することができます。::

   protected function login($userId = 1)
   {
       $users = TableRegistry::get('Users');
       $user = $users->get($userId);
       $this->session(['Auth' => $user]);
   }

統合テストでは ``login()`` を使ってユーザがログインしたときのシミュレーションをすることができます。::

   public function testGet()
   {
       $this->login();
       $this->get('/bookmarks/1');
       $this->assertResponseOk();
   }

トークンベースの認証
==========================

トークンベースの認証では ``Authorization`` ヘッダーをシミュレートする必要があります。
有効なトークンを取得した後、リクエスト::

   public function testGet()
   {
       $token = $this->getToken();
       $this->configRequest([
           'headers' => ['Authorization' => 'Bearer ' . $token]
       ]);
       $this->get('/api/bookmarks');
       $this->assertResponseOk();
   }
