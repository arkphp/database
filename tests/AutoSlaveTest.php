<?php
/**
 * ark.database
 * @copyright 2014-2016 Liu Dong <ddliuhb@gmail.com>
 * @license MIT
 */

class AutoSlaveTest extends PHPUnit_Framework_TestCase{
    protected $db;
    
    protected function getSampleUser($key = 1){
        return array(
            'name' => 'user'.$key,
            'email' => 'user'.$key.'@example.com',
            'point' => $key
        );
    }
    
    protected function setup(){
        $this->db = new \Ark\Database\Connection('sqlite::memory:', '', '', [
            'auto_slave' => true,
        ]);

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS contact (
                id INTEGER PRIMARY KEY, 
                name TEXT, 
                email TEXT,
                point INTEGER DEFAULT 0
            )
         ");

        $this->db->addConnection('slave', 'sqlite::memory:', '', '');

        $this->db->switchConnection('slave');
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS contact (
                id INTEGER PRIMARY KEY, 
                name TEXT, 
                email TEXT,
                point INTEGER DEFAULT 0
            )
         ");

        $this->db->switchConnection();
    }

    protected function getAllByConnection($connection) {
        $this->db->switchConnection($connection);
        $result = [];
        foreach ($this->db->query('SELECT * FROM contact') as $row) {
            $result[] = $row['name'];
        }

        // switch to automatic mode
        $this->db->switchConnection();

        return $result;
    }

    public function testAutoSlave() {
        // insert into master
        $rst = $this->db->builder()
            ->insert('contact', [
                'name' => 'user1',
                'email' => 'user1@test.com',
            ]);

        $this->assertTrue($rst > 0);
        
        // the slave is not updated in our test case
        $rst = $this->db->builder()
            ->select('name')
            ->from('contact')
            ->where('name = ?', ['user1'])
            ->queryValue();

        $this->assertFalse($rst);

        // confirm above tests
        $this->assertContains('user1', $this->getAllByConnection('default'));
        $this->assertNotContains('user1', $this->getAllByConnection('slave'));
    }
}