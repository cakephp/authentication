Identifiers
###########

Identifiersは認証者がリクエストから抽出した情報に基づいてユーザやサービスを識別します。
Identifiersは ``loadIdentifier`` メソッドでオプション指定することができます。
パスワード識別子を使用した全体的な例は次のようになります。::

   $service->loadIdentifier('Authentication.Password', [
       'fields' => [
           'username' => 'email',
           'password' => 'passwd',
       ],
       'resolver' => [
           'className' => 'Authentication.Orm',
           'finder' => 'active'
       ],
       'passwordHasher' => [
           'className' => 'Authentication.Fallback',
           'hashers' => [
               'Authentication.Default',
               [
                   'className' => 'Authentication.Legacy',
                   'hashType' => 'md5'
               ],
           ]
       ]
   ]);

Password
========

パスワード識別子は、渡された資格情報をデータソースに対してチェックします。

設定オプション:

-  **fields**: 調べるためのフィールドです。デフォルトは ``['username' => 'username', 'password' => 'password']``。
   ``ユーザー名`` を配列にすることができます。
   例えば、 ``['username' => ['username', 'email'], 'password' => 'password']`` を使うと、
   ユーザ名とemailのどちらかのカラムの値をマッチさせることができます。
-  **resolver**: 同一性を決意します. デフォルトは ``Authentication.Orm`` で、CakePHPのORMを使用しています。
-  **passwordHasher**: パスワードハッシャー. デフォルトは、
   ``DefaultPasswordHasher::class`` 。

トークン
==========

渡されたトークンをデータソースと照合します。

設定オプション:

-  **tokenField**: データベース内のフィールドをチェックします。 デフォルトは ``token`` です。
-  **dataField**: 認証器から渡されたデータの中のフィールド。デフォルトは ``token`` です。
-  **resolver**: 同一性を決意します。デフォルトは ``Authentication.Orm`` で、CakePHPのORMを使用しています。

JWT サブジェクト
=================

渡された JWT トークンをデータソースと照合します。

-  **tokenField**: データベース内のフィールドをチェックします。デフォルトは ``id`` です。
-  **dataField**: ユーザ識別子を取得するためのペイロードキー。デフォルトは、 ``sub`` です。
-  **resolver**: 同一性を決意します。デフォルトは ``Authentication.Orm`` で、CakePHPのORMを使用しています。

LDAP
====

LDAP サーバーに対して渡された資格情報をチェックします。 この識別子には PHP LDAP 拡張モジュールが必要です。

-  **fields**: 調べるためのフィールドです。 デフォルトは ``['username' => 'username', 'password' => 'password']`` です。
-  **host**: LDAPサーバーのFQDN。
-  **port**: LDAPサバーのポート。デフォルトは ``389`` 。
-  **bindDN**:  認証するユーザーの識別名。呼び出し可能である必要があります。匿名バインドはサポートされていません。
-  **ldap**: 延長アダプターです。 デフォルトは ``\Authentication\Identifier\Ldap\ExtensionAdapter``。
   もしそれが ``AdapterInterface`` を実装しているならば、そのobject/classnameをここに渡すことができます。
-  **options**: 付加的な LDAP オプション, like ``LDAP_OPT_PROTOCOL_VERSION`` または ``LDAP_OPT_NETWORK_TIMEOUT`` のようなものです。
   `php.net <http://php.net/manual/en/function.ldap-set-option.php>`__ をみてより多くのオプションを参照してください。

コールバック
============

識別のためにコールバックを使用できるようにします。 シンプルな識別子やクイックプロトタイピングに便利です。

設定オプション:

-  **callback**: デフォルトは ``null`` で例外が発生します。
   認証機能を使用するには、このオプションに有効なコールバックを渡す必要があります。

コールバックの識別子は、単純な結果を得るために ``null|ArrayAccess`` を返すこともできます。,
または、エラーメッセージを転送したい場合、 ``Authentication\Authenticator\Result`` を使用します。::

    // シンプルなコールバック識別子
    $authenticationService->loadIdentifier('Authentication.Callback', [
        'callback' => function($data) {
            // 識別子のロジック

            // 識別されたユーザの配列を返すか、失敗した場合はnullを返します。
            if ($result) {
                return $result;
            }

            return null;
        }
    ]);

    // エラーメッセージを返すために結果オブジェクトを使用します。
    $authenticationService->loadIdentifier('Authentication.Callback', [
        'callback' => function($data) {
            // 識別子のロジック

            if ($result) {
                return new Result($result, Result::SUCCESS);
            }

            return new Result(
                null,
                Result::FAILURE_OTHER,
                ['message' => 'Removed user.']
            );
        }
    ]);


Identity resolvers
======================

Identity resolvers は、異なるデータソース用のアダプタを提供する。
これにより、どのソースのアイデンティティを検索するかを制御することができます。
これらは識別子とは別個のものであり、
識別子の方法(form, jwt, basic auth)とは独立してスワップアウトできるようになっています。

ORM Resolver
------------------

CakePHP ORM の Identity resolvers。

設定オプション:

-  **userModel**: ユーザーモデルのアイデンティティが配置されています。 デフォルトは ``Users`` 。
-  **finder**: モデルと一緒に使うファインダー. デフォルトは ``all`` 。

ORM resolverを使用するには ``composer.json`` ファイルの中に ``cakephp/orm`` が必要です。

独自のリゾルバを書く
-------------------------

どんなORMやデータソースでも、リゾルバを作成することで認証に対応できるようにすることができます。
リゾルバは、``App\Identifier\Resolver`` の名前空間の下に、
``Authentication\Identifier\Resolver\ResolverInterface`` を実装しなければなりません。

リゾルバの設定は``resolver`` のconfigオプションを使って行えます。::

   $service->loadIdentifier('Authentication.Password', [
       'resolver' => [
            // name: \Some\Other\Custom\Resolver::class フルのクラス名です。
           'className' => 'MyResolver',
           // レゾルバのコンストラクタに追加のオプションを渡します。
           'option' => 'value'
       ]
   ]);

またはセッターを使用して注入してください。::

   $resolver = new \App\Identifier\Resolver\CustomResolver();
   $identifier = $service->loadIdentifier('Authentication.Password');
   $identifier->setResolver($resolver);
