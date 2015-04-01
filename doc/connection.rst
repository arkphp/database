*******************
Database Connection
*******************

Connection is the fundamental of this library.

Setup Connection
================

.. code-block:: php

    <?php
    use Ark\Database\Connection;

    $db = new Connection($dsn, $username, $password, $options);

``$username`` and ``$password`` are not required for Sqlite.

DSN
===

The Data Source Name, or DSN, contains the information required to connect to the database.

For more information, refer to the `PDO manual <http://php.net/manual/en/pdo.construct.php>`_.

Mysql
-----

::

    mysql:host=localhost;port=3333;dbname=testdb

Sqlite
------

::

    sqlite:/path/to/db


Database Options
================

The connection can be customized with the ``$options`` param. Commonly used options are listed below:

- ``prefix``: Table prefix, use {{table}} as table name, prefix will be prepended automatically.
- ``PDO::ATTR_ERRMODE`` Error reporting. Possible values:

    - ``PDO::ERRMODE_SILENT``: Just set error codes.
    - ``PDO::ERRMODE_WARNING``: Raise E_WARNING.
    - ``PDO::ERRMODE_EXCEPTION``: Throw exceptions.

- ``PDO::ATTR_TIMEOUT``: Specifies the timeout duration in seconds. Not all drivers support this option, and its meaning may differ from driver to driver. For example, sqlite will wait for up to this time value before giving up on obtaining an writable lock, but other drivers may interpret this as a connect or a read timeout interval. Requires int.
  
For more options, refer to the `PDO manual <http://php.net/manual/en/pdo.setattribute.php>`_.

Usage
=====

The connection instance can act just like class PDO. For example, you can exec a SQL statement:

.. code-block:: php

    <?php
    $db->exec("INSERT INTO user (username, email) VALUES ('test', 'test@localhost')");

Or you can query some results with a SQL statement:

.. code-block:: php

    foreach ($db->query("SELECT * FROM user") as $user) {
        echo $user['username']."\n";
    }

Additionaly, it provide two important shortcut methods for you to work with Query Builder and Model Factory:

.. code-block:: php

    <?php
    $builder = $db->builder();
    $factory = $db->factory('@user');