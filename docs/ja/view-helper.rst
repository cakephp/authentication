View Helper
===========

AppViewでヘルパーを次のようにロードします。::

   $this->loadHelper('Authentication.Identity');

ユーザーがログインしているかどうかを非常に簡単に確認するには::

   if ($this->Identity->isLoggedIn()) {
       ...
   }

ユーザーデータの取得は::

   $username = $this->Identity->get('username');

次のチェックは、あるユーザーに属するレコードが現在ログインしているユーザーであるかどうかを判断し、
他のフィールドを比較するために使用することができます。::

   $isCurrentUser = $this->Identity->is($user->id);
   $isCurrentRole = $this->Identity->is($user->role_id, 'role_id');

このメソッドは主に単純なケースのための便利なメソッドであり、いかなる種類の適切な認可実装を置き換えることを意図したものではありません。
