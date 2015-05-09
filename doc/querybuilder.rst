*************
Query Builder
*************

Query builder makes it easier to working with SQL statements.

Select
======

Query builder provide a chainable API to build SQL query like a charm.

.. code-block:: php

    <?php
    $users = $db->builder()
        ->select('username, email')
        ->from('user')
        ->where('level > :level', array(':level' => $level))
        ->limit(10)
        ->orderBy('username ASC')
        ->queryAll();

In addition to ``queryAll`` to fetch all rows, you can use:

- ``queryRow`` to get the first row
- ``queryValue`` to get the first column of first row (the value)
- ``queryColumn`` to get the first column of all rows

Condition
---------

TODO
  
Insert
======

.. code-block:: php

    <?php
    $db->builder()
        ->insert('user', array(
            'userame' => 'test',
            'email' => 'test@example.com',
        ));

Update
======

.. code-block:: php

    <?php
    $db->builder()
        ->update(
            'user', 
            array(
                'email' => 'test1@example.com',
            ), // columns to be updated
            'username = :username', // conditions
            array(':username' => $username) // params
        );

Delete
======

.. code-block:: php

    $db->builder()
        ->delete(
            'user', 
            'username = :username',
            array(':username' => $username)
        );