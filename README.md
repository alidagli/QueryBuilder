# 🔧 PHP - MySQL QueryBuilder

PHP ve MySQL için basit query builder.


## Kullanımı

Varsayılan pdo bağlantısı yapın.
Ardından query builder sınıfını oluşturup oluşturduğunuz pdo bağlantı değişkenini constructor olarak query builder a verin.
```php
$pdo = new PDO(...);
$db = new QueryBuilder($pdo);
```

### Tüm kayıtları listeleme
```php
$users = $db->table('users')->all(); // SELECT * FROM users
$users = $db->table('users')->all(['id','name','email']); // SELECT id,name,email FROM users
```

### Kayıt Ekleme
```php
$users = $db->table('users')
    ->create([
        'name' => 'John Doe',
        'email' => 'mail@example.com'
    ]); // INSERT INTO users(name,email) VALUES('John Doe','mail@example.com')
```

### Kayıt Güncelleme
```php
$users = $db->table('users')
    ->where('id',1)
    ->update([
        'name' => 'John Doe',
        'email' => 'mail@example.com'
    ]); // UPDATE users SET name = 'John Doe', email = 'mail@example.com' WHERE id = 1
```

### Kayıt Silme
```php
$users = $db->table('users')
    ->where('id',1)
    ->delete(); // DELETE FROM users WHERE id = 1
```

### Tek Satır Veri Alma
```php
$users = $db->table('users')
    ->where('id',1)
    ->first(); // SELECT * FROM users WHERE id = 1 LIMIT 1
```

### Belirli Kolonları Çekme
```php
$users = $db->table('users')
    ->select(['id','name','email'])
    ->where('id',1)
    ->get(); // SELECT id,name,email FROM users WHERE id = 1
```

### GROUP BY Kullanımı
```php
$users = $db->table('users')
    ->groupBy('group_id')
    ->get(); // SELECT id,name,email FROM users GROUP BY group_id
```

> 'get()' ve 'all()' methodları $pdo->fetchAll() kullanır
> first() methodu $pdo->fetch() kullanır ve otomatik limit ekler, sizin eklediğiniz limitleri yok sayar

### Toplam Satır Sayısı Alma
```php
$users = $db->table('users')
    ->where('status','deleted')
    ->count(); // SELECT COUNT(*) FROM users WHERE status = 'deleted'
```

### Where kullanımı
```php
$users = $db->table('users')
    ->where('status','active')
    ->get(); // SELECT * FROM users WHERE status = 'active'
    
$users = $db->table('users')
    ->where('status','active')
    ->where('group_id',5)
    ->get(); // SELECT * FROM users WHERE status = 'active' AND group_id = 5

$users = $db->table('users')
    ->where('group_id',5)
    ->orWhere('status','active')
    ->orWhere('status','deleted')
    ->get(); // SELECT * FROM users WHERE group_id = 5 AND (status = 'active' OR status = 'deleted')

//Where ile farklı operatör işaretleri kullanımı - orWhere() için de geçerlidir
$users = $db->table('users')
    ->where('status','!=','active')
    ->get(); // SELECT * FROM users WHERE status != 'active'

$users = $db->table('users')
    ->whereIn('id',[1,2,3,4,5])
    ->get(); // SELECT * FROM users WHERE id IN(1,2,3,4,5)

$users = $db->table('users')
    ->whereNotIn('id',[7,8,9])
    ->get(); // SELECT * FROM users WHERE id NOT IN(7,8,9)

$users = $db->table('users')
    ->whereBetween('id',1,10)
    ->get(); // SELECT * FROM users WHERE id BETWEEN 1 AND 10

$users = $db->table('users')
    ->whereRaw('id IN (SELECT user_id FROM ban_list WHERE group_id = 3)')
    ->get(); // SELECT * FROM users WHERE id IN(SELECT user_id FROM ban_list WHERE group_id = 3)
```

### LIMIT Kullanımı
```php
$users = $db->table('users')
    ->limit(10)
    ->get(); // SELECT * FROM users LIMIT 10

$users = $db->table('users')
    ->limit(10,10)
    ->get(); // SELECT * FROM users LIMIT 10,10
```

### ORDER BY Kullanımı
```php
$users = $db->table('users')
    ->orderBy('id','desc')
    ->get(); // SELECT * FROM users ORDER BY id DESC

$users = $db->table('users')
    ->orderBy('id','desc')
    ->orderBy('name','asc')
    ->get(); // SELECT * FROM users ORDER BY id DESC, name ASC
```

### JOIN Kullanımı
```php
// join('hedef tablo', 'hedef tablodaki ilişki id', 'users(local) tablosundaki ilişki id')

// kullanılabilir join tipleri; join(), innerJoin(), leftJoin(), rightJoin()
// crossJoin(), outerJoin()
// hepsinde kullanılan parametreler aynıdır

$users = $db->table('users')
    ->join('user_groups','id','group_id') 
    ->get(); // SELECT * FROM users JOIN user_groups ON user_groups.id = users.group_id
```

### Oluşturulan Sorguyu Text Olarak Çıktı Alma
```php
$users = $db->table('users')
    ->select(['id','name'])
    ->where('status','active')
    ->orderBy('id','desc')
    ->limit(10)
    ->toRawSql(); // Text Çıktı Olarak -> SELECT id,name FROM users WHERE status = 'active' ORDER BY id DESC LIMIT 10
```

### Düz Sorgu Çalıştırma (Builder Olmadan)
```php
$users = $db->raw("SELECT * FROM users")->fetchAll(); // $pdo->query($query)->fetchAll(); olarak çalıştırılır

$users = $db->execRaw("SET NAMES 'utf8'"); // $pdo->exec($query) olarak çalıştırılır
```
