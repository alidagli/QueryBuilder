# 🔧 PHP - MySQL QueryBuilder

PHP ve MySQL için basit query builder.


## Kullanımı

Varsayılan pdo bağlantısı yapın.
Ardından query builder sınıfını oluşturup oluşturduğunuz pdo bağlantı değişkenini constructor olarak query builder'a verin.
```php
$pdo = new PDO(...);
$db = new QueryBuilder($pdo);
```

### Tüm kayıtları listeleme
```php
$users = $db->table('users')->all();
// SELECT * FROM users

$users = $db->table('users')->all(['id','name','email']);
// SELECT id,name,email FROM users
```

### Kayıt Ekleme
```php
$users = $db->table('users')
    ->create([
        'name' => 'John Doe',
        'email' => 'mail@example.com'
    ]);
// INSERT INTO users(name,email) VALUES('John Doe','mail@example.com')
```

### Kayıt Güncelleme
```php
$users = $db->table('users')
    ->where('id',1)
    ->update([
        'name' => 'John Doe',
        'email' => 'mail@example.com'
    ]);
// UPDATE users SET name = 'John Doe', email = 'mail@example.com' WHERE id = 1
```

### Kayıt Silme
```php
$users = $db->table('users')
    ->where('id',1)
    ->delete();
// DELETE FROM users WHERE id = 1
```

### Tek Satır Veri Alma
```php
$users = $db->table('users')
    ->where('id',1)
    ->first();
// SELECT * FROM users WHERE id = 1 LIMIT 1
```

### Belirli Kolonları Çekme
```php
$users = $db->table('users')
    ->select(['id','name','email'])
    ->where('id',1)
    ->get();
// SELECT id,name,email FROM users WHERE id = 1
```

### GROUP BY Kullanımı
```php
$users = $db->table('users')
    ->groupBy('group_id')
    ->get();
// SELECT id,name,email FROM users GROUP BY group_id
```

> 'get()' ve 'all()' methodları $pdo->fetchAll() kullanır
> first() methodu $pdo->fetch() kullanır ve otomatik limit ekler, sizin eklediğiniz limitleri yok sayar

### Toplam Satır Sayısı Alma
```php
$users = $db->table('users')
    ->where('status','deleted')
    ->count();
// SELECT COUNT(*) AS aggregate FROM users WHERE status = 'deleted'
```

### SUM(column) kullanımı
```php
$users = $db->table('orders')
    ->selectSum('order_price','total')
    ->where('status','completed')
    ->one();
// SELECT SUM(order_price) AS total FROM orders WHERE status = 'completed'
```

> '->one()' methodu $pdo->fetchColumn() kullanır

### DISTINCT(column) kullanımı
```php
$users = $db->table('orders')
    ->selectDistinct('customer_id','customer')
    ->where('status','completed')
    ->one();
// SELECT DISTINCT(customer_id) AS customer FROM orders WHERE status = 'completed'
```

### Where kullanımı
```php
$users = $db->table('users')
    ->where('status','active')
    ->get();
// SELECT * FROM users WHERE status = 'active'
    
$users = $db->table('users')
    ->where('status','active')
    ->where('group_id',5)
    ->get();
// SELECT * FROM users WHERE status = 'active' AND group_id = 5

$users = $db->table('users')
    ->where('group_id',5)
    ->orWhere('status','active')
    ->orWhere('status','deleted')
    ->get();
// SELECT * FROM users WHERE group_id = 5 OR status = 'active' OR status = 'deleted'

$users = $db->table('users')
    ->select(['id'])
    ->where('status','active')
    ->where(function($query){
        $query->orWhere('login','admin')
              ->orWhere('email','admin')    
    })
    ->first();
// SELECT id FROM users WHERE status = 'active' AND (login = 'admin' OR email = 'admin') LIMIT 1 

//Where ile farklı operatör işaretleri kullanımı - orWhere() için de geçerlidir
$users = $db->table('users')
    ->where('status','!=','active')
    ->get();
// SELECT * FROM users WHERE status != 'active'

$users = $db->table('users')
    ->whereIn('id',[1,2,3,4,5])
    ->get();
// SELECT * FROM users WHERE id IN(1,2,3,4,5)

$users = $db->table('users')
    ->whereNotIn('id',[7,8,9])
    ->get();
// SELECT * FROM users WHERE id NOT IN(7,8,9)

$users = $db->table('users')
    ->whereBetween('id',1,10)
    ->get();
// SELECT * FROM users WHERE id BETWEEN 1 AND 10

$users = $db->table('users')
    ->whereRaw('id IN (SELECT user_id FROM ban_list WHERE group_id = 3)')
    ->get();
// SELECT * FROM users WHERE id IN(SELECT user_id FROM ban_list WHERE group_id = 3)
```

### LIMIT Kullanımı
```php
$users = $db->table('users')
    ->limit(10)
    ->get();
// SELECT * FROM users LIMIT 10

$users = $db->table('users')
    ->limit(10,10)
    ->get();
// SELECT * FROM users LIMIT 10,10
```

### ORDER BY Kullanımı
```php
$users = $db->table('users')
    ->orderBy('id','desc')
    ->get();
// SELECT * FROM users ORDER BY id DESC

$users = $db->table('users')
    ->orderBy('id','desc')
    ->orderBy('name','asc')
    ->get();
// SELECT * FROM users ORDER BY id DESC, name ASC
```

### JOIN Kullanımı
```php
// join('hedef tablo', 'hedef tablodaki ilişki id', 'users(local) tablosundaki ilişki id')

// kullanılabilir join tipleri; join(), innerJoin(), leftJoin(), rightJoin()
// crossJoin(), outerJoin()
// hepsinde kullanılan parametreler aynıdır

$users = $db->table('users')
    ->join('user_groups','id','group_id') 
    ->get();
// SELECT * FROM users JOIN user_groups ON users.group_id = user_groups.id
```

### Oluşturulan Sorguyu Text Olarak Çıktı Alma
```php
$users = $db->table('users')
    ->select(['id','name'])
    ->where('status','active')
    ->orderBy('id','desc')
    ->limit(10)
    ->toRawSql();
// Text Çıktı Olarak -> SELECT id,name FROM users WHERE status = 'active' ORDER BY id DESC LIMIT 10
```

### Düz Sorgu Çalıştırma (Builder Olmadan)
```php
$users = $db->raw("SELECT * FROM users")->fetchAll();
// $pdo->query($query)->fetchAll(); olarak çalıştırılır

$user_id = 1;
$users = $db->raw("SELECT * FROM users WHERE user_id = ?",[$user_id])->fetchAll();
// 2. parametre array olarak gönderilirse pdo prepare olarak çalışır
// $pdo->prepare($query)->execute([$user_id])->fetchAll(); olarak çalıştırılır

$users = $db->execRaw("SET NAMES 'utf8'");
// $pdo->exec($query) olarak çalıştırılır
```
