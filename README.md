# ark/database [![Travis](https://img.shields.io/travis/arkphp/database.svg)](https://travis-ci.org/arkphp/database) [![Documentation Status](https://readthedocs.org/projects/ark-database/badge/?version=latest)](https://readthedocs.org/projects/ark-database/?badge=latest) [![Packagist](https://img.shields.io/packagist/v/ark/database.svg)](https://packagist.org/packages/ark/database)

Light weight database abstraction.

[Document](http://ark-database.readthedocs.org/en/latest/)

[API](http://arkphp.github.io/database/)

## Quick Start

### Create Database Connection

First of all, create a database connection with DSN, username and password. Take the Mysql Database for example:

```php
<?php
use Ark\Database\Connection;

$db = new Connection('mysql:host=localhost;dbname=testdb', 'username', 'password');
```


### Query Builder

```php
<?php
// Query
$db->builder()
    ->select('*')
    ->from('user')
    ->where('age > :age and created_at > :time', [
        ':age' => 20,
        ':time' => time() - 3600
    ])
    ->limit(10)
    ->queryAll();

// Insert
$db->builder()
    ->insert('user', [
        'name' => 'user1',
        'password' => 'pa$$word',
    ]);
```

### Work with Model

```php
<?php
// Create model factory
$factory = $db->factory('@user');

// Insert
$factory->insert([
    'name' => 'user1',
    'age' => 20,
]);

// Get model
$user = $factory->findOneByName('user1');

// Update
$user->email = 'user1@example.com';
$user->save();

// Delete
$user->delete();
```