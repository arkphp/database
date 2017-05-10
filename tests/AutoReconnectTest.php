<?php
/**
 * ark.database
 * @copyright 2014-2016 Liu Dong <ddliuhb@gmail.com>
 * @license MIT
 */

/*

HOW TO TEST:

1. Start a test database with docker:

docker run --name=arkdb-mysql -p 3306:3306 -e MYSQL_ROOT_PASSWORD=123456 -d index.alauda.cn/library/mysql:5.6

2. Init db:

echo 'create database if not exists arkdb ;use arkdb;DROP TABLE IF EXISTS `user`;CREATE TABLE IF NOT EXISTS `user` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT "", PRIMARY KEY (`id`)) AUTO_INCREMENT=1 ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;' | docker exec -i arkdb-mysql mysql --user=root --password=123456

3. Start test script:

phpunit tests/AutoReconnectTest

4(1). Kill all connections:

echo -e '\\! rm /tmp/a.txt \nselect concat("KILL ",id,";") from information_schema.processlist where INFO IS NULL OR INFO not like "%processlist%" into outfile "/tmp/a.txt";source /tmp/a.txt;' | docker exec -i arkdb-mysql mysql --user=root --password=123456

4(2). Restart mysql:

docker restart arkdb-mysql

5. Cleanup:

docker stop arkdb-mysql && docker rm arkdb-mysql

Tips: 

Inspect: docker exec -it arkdb-mysql mysql --user=root --password=123456
*/

// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', '\PHPUnit\Framework\TestCase');
}

class AutoReconnectTest extends \PHPUnit\Framework\TestCase{
    protected $db;
    // Local test only
    protected $on = false;

    protected function setup(){
        if (!$this->on) return;
        $this->db = new \Ark\Database\Connection('mysql:host=127.0.0.1;port=3306;dbname=arkdb;charset=utf8mb4', 'root', '123456', [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
    }

    public function testReconnect() {
        if (!$this->on) return;

        $this->db->setOption('reconnect', true);
        $count = 100;
        $sleep = 1;
        while((--$count) > 0) {
            try {
                $v = $this->db->builder()->setSql('SELECT 1')->queryValue();
                $this->assertEquals($v, '1');
                fwrite(STDOUT, "1\n");
                sleep($sleep);

                $insertId = $this->db->builder()
                    ->insert('user', [
                        'name' => 'hello',
                    ]);
                // $this->assertTrue($insertId > 0);
                fwrite(STDOUT, "2:".$insertId."\n");
                sleep($sleep);

                $maxId = $this->db->builder()
                    ->select('id')
                    ->from('user')
                    ->orderBy('id DESC')
                    ->limit(1)
                    ->queryValue();

                // $this->assertEquals($insertId, $maxId);
                fwrite(STDOUT, "3:".$maxId."\n");
                sleep($sleep);
            } catch (\Exception $e) {
                fwrite(STDOUT, $e);
            }
        }
    }
}