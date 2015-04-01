*************
Model Factory
*************

With model factory, you can manipulate data in object-oriented (or ORM) way.

Get the Factory
===============

First of all, declare which model you will be working with.

.. code-block:: php

    <?php
    // Model factory for User class
    $factory = $db->factory('User');

    // Or for a table without model class created, just use "@{table_name}" as the model!
    $factory = $db->factory('@user');

Find
====

.. code-block:: php

    <?php
    //Get all users
    $users = $factory->findAll();
    foreach($users as $user){
        echo $user->email;
    }

    //find(findByPK)
    $user = $factory->find(1);

    //findByXX 
    $users = $factory->findByAge(30);

    //findOneByXX
    $user = $factory->findOneByUsername('user1');

Update
======

.. code-block:: php

    <?php
    //create a model
    $user = $factory->create();

    //and then set data
    $user->username = 'user2';
    $user->email = 'email2';

    //You can also create this model with initial data
    $user = $factory->create(array(
        'username' => 'user2',
        'email' => 'email2',
    ));

    //save model
    $user->save();

    //delete model
    $user->delete();