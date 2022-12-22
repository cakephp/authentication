Password Hashers
################

Default
=======

暗号化方法にPHPの定数 ``PASSWORD_DEFAULT`` を使用しています。
デフォルトのハッシュ型は ``bcrypt`` です。


こちらをご覧ください `PHPのドキュメント <https://php.net/manual/en/function.password-hash.php>`__
bcryptとPHPのパスワードハッシュについての詳細情報が書かれています。

このアダプタの設定オプションは次のとおりです:

-  **hashType**: 使用するハッシュ化アルゴリズム。
   有効な値は ``password_hash()`` の引数 ``$algo`` でサポートされている値です。
   デフォルトは、 ``PASSWORD_DEFAULT`` です。
-  **hashOptions**: オプションの連想配列。
   各ハッシュ型でサポートしているオプションについてはPHPマニュアルを参照してください。
   デフォルトは空の配列です。

レガシー
=========

cakePHP2から移行したアプリケーションのためのパスワードハッシャーです。

フォールバック
================

フォールバックパスワードハッシャーでは、
複数のハッシャーを設定することができ、それらを順次チェックしていきます。
これにより、パスワードがリセットされて新しいハッシュにアップグレードされるまで、
古いハッシュタイプでログインすることができます。

ハッシュアルゴリズムのアップグレード
====================================

CakePHPは、ユーザーのパスワードをあるアルゴリズムから別のアルゴリズムに移行するためのクリーンな方法を提供します。
これは ``FallbackPasswordHasher`` クラスによって実現されます。
レガシーパスワードからデフォルトのbcryptハッシャーに移行したい場合を想定しています。
以下のようにフォールバックハッシュアーを想定することができます。::

   $service->loadIdentifier('Authentication.Password', [
       // その他の設定オプション
       'passwordHasher' => [
           'className' => 'Authentication.Fallback',
           'hashers' => [
               'Authentication.Default',
               [
                   'className' => 'Authentication.Legacy',
                   'hashType' => 'md5',
                   'salt' => false // saltのデフォルトをoffにする
               ],
           ]
       ]
   ]);

ログインアクションの中で認証サービスを使って ``Password`` 識別子にアクセスし、
現在のユーザーのパスワードをアップグレードする必要があるかどうかをチェックすることができます。::

   public function login()
   {
       $authentication = $this->request->getAttribute('authentication');
       $result = $authentication->getResult();

       // POST  GETに関係なくログインする時にリダイレクトする
       if ($result->isValid()) {
           // 識別子に `Password` を使用していると仮定します。
           if ($authentication->identifiers()->get('Password')->needsPasswordRehash()) {
               // セーブ時にリハッシュが発生します。
               $user = $this->Users->get($this->Auth->user('id'));
               $user->password = $this->request->getData('password');
               $this->Users->save($user);
           }

           // テンプレートをリダイレクトしたり、表示したりします。
       }
   }
