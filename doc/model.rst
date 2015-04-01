*****
Model
*****

Define a model by extending the class ``Ark\Database\Model``. You need to override the ``config`` method to specify table name, pk or relations of your model.

.. code-block:: php

    <?php
    use Ark\Database\Model;
    class User extends Model {
        static public function config() {
            return array(
                'table' => 'user',
                'pk' => 'uid',
            );
        }
    }

To be convienient, it’s not necessary to create a class for a simple model. You’ll see that in the next section.