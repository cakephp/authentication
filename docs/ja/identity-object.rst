Identity Objects
################

Identity オブジェクトは、認証サービスによって返され、リクエストで利用可能になる。
Identitiesは現在のログインIDのプライマリIDの値を取得するためのメソッド ``getIdentifier()`` を提供しています。

このオブジェクトが存在する理由は、それを実装/ソースにするインターフェースを提供するためです::

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

identityオブジェクトはArrayAccessを提供していますが、
データにアクセスするための ``get()`` メソッドも提供しています。
getメソッドはフィールドマッピングを認識しているので、
配列へのアクセスよりも ``get()`` メソッドを使うことを強く推奨します。::

    $identity->get('email');
    $identity->get('username');

また、 ``get`` メソッドはIDEのメタファイルを介してタイプヒントを与えることもできます。
例えば、 `IdeHelper <https://github.com/dereuromark/cakephp-ide-helper>`__ 。

もしあなたが望むならば、プロパティアクセスを使用することができます。::

    $identity->email;
    $identity->username;

デフォルトの Identity オブジェクトクラスは、フィールドをマップするように構成できます。
これは、ID の識別子が型にはまらない ``id`` フィールドである場合や、
他のフィールドをより一般的で一般的な名前にマップしたい場合に非常に便利です。::

   $identity = new Identity($data, [
       'fieldMap' => [
           'id' => 'uid',
           'username' => 'first_name'
       ]
   ]);

独自のIdentityオブジェクトの作成
---------------------------------

デフォルトでは、Authentication プラグインは
メソッドとプロパティアクセスをプロキシする ``IdentityDecorator`` で返されたユーザーデータをラップします。
自分の ID オブジェクトを作りたい場合は ``IdentityInterface`` を実装しなければなりません。

User クラスへの IdentityInterface の実装
-----------------------------------------------------

このプラグインで既存のUserクラスを使い続けたい場合は、 ``Authentication\IdentityInterface`` を実装してください::

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

       // Other methods
   }

カスタムIdentityデコレータを使用する
-----------------------------------

もしあなたの識別子が ``IdentityInterface`` を実装したオブジェクトに変更を加えることができない場合は、
必要なインターフェイスを実装したカスタムデコレータを実装することができます。::

   // 呼び出し可能な...
   $identityResolver = function ($data) {
       return new MyCustomIdentity($data);
   };

   //...またはクラス名を指定して ID ラッパーを設定することができます。
   $identityResolver = MyCustomIdentity::class;

   // そしてそれをサービスの設定に渡します。
   $service = new AuthenticationService([
       'identityClass' => $identityResolver,
       'identifiers' => [
           'Authentication.Password'
       ],
       'authenticators' => [
           'Authentication.Form'
       ]
   ]);
