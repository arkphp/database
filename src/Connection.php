<?php
/**
 * ark.database
 * @copyright 2015 Liu Dong <ddliuhb@gmail.com>
 * @license MIT
 */

namespace Ark\Database;

/**
 * Database connection management
 */
class Connection
{
    protected $current;
    protected $connections = [];
    protected $configs = [];

    /**
     * Constructor
     * @param string $dsn      Database DSN
     * @param string $username 
     * @param string $password 
     * @param array  $options
     */
    public function __construct($dsn = null, $username = null, $password = null, $options = array()){
        $this->current = 'default';
        $this->addConnection($this->current, $dsn, $username, $password, $options);
    }
    
    /**
     * Get connection config by name
     * @param  string $name Connection name
     * @return array
     */
    public function getConfig($name = null){
        if(null === $name){
            $name = $this->current;
        }
        return $this->configs[$name];
    }
    
    /**
     * Get PDO instance
     * @param  string $name Connection name
     * @return \PDO
     */
    public function getPDO($name = null){
        if(null === $name){
            $name = $this->current;
        }
        if(!isset($this->connections[$name])){
            $config = $this->configs[$name];
            $this->connections[$name] = new \PDO($config['dsn'], $config['username'], $config['password'], $config['options']);
        }
        
        return $this->connections[$this->current];
    }
    
    /**
     * Add a new connection configuration
     * 
     * @param string $name
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @param array $options
     * @return \Ark\Database\Connection
     */
    public function addConnection($name, $dsn, $username = null, $password = null, $options = []){
        //default driver options
        static $defaultOptions = array(
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            //PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        );
        if(isset($options['prefix'])){
            $prefix = $options['prefix'];
            unset($options['prefix']);
        }
        else{
            $prefix = '';
        }
        $this->configs[$name] = [
            'dsn' => $dsn,
            'username' => $username,
            'password' => $password,
            'prefix' => $prefix,
            'options' =>  $options + $defaultOptions,
        ];
        
        return $this;
    }
    
    /**
     * Switch connection
     * @param string $name
     * @return \Ark\Database\Connection
     */
    public function switchConnection($name = 'default'){
        $this->current = $name;
        
        return $this;
    }

    /**
     * Create a model factory
     * 
     * @param string $model
     * @return \Ark\Database\ModelFactory
     */
    public function factory($model){
        return new ModelFactory($this, $model);
    }
    
    /**
     * Create query builder
     * 
     * @return \Ark\Database\QueryBuilder
     */
    public function builder(){
        return new QueryBuilder($this);
    }
    
    /**
     * @link http://www.php.net/manual/en/pdo.getattribute.php
     */
    public function getAttribute($attribute){
        return $this->getPDO()->getAttribute($attribute);
    }
    
    /**
     * @link http://www.php.net/manual/en/pdo.setattribute.php
     */
    public function setAttribute($attribute, $value){
        return $this->getPDO()->setAttribute($attribute, $value);
    }
    
    /**
     * @link http://www.php.net/manual/en/pdo.prepare.php
     */
    public function prepare(){
        return call_user_func_array(array($this->getPDO(), 'prepare'), func_get_args());
    }
    
    /**
     * @link http://www.php.net/manual/en/pdo.query.php
     */
    public function query(){
        return call_user_func_array(array($this->getPDO(), 'query'), func_get_args());
    }
    
    /**
     * @link http://www.php.net/manual/en/pdo.exec.php
     */
    public function exec(){
        return call_user_func_array(array($this->getPDO(), 'exec'), func_get_args());
    }
    
    /**
     * @link http://www.php.net/manual/en/pdo.begintransaction.php
     */
    public function beginTransaction(){
        return $this->getPDO()->beginTransaction();
    }
    
    /**
     * @link http://www.php.net/manual/en/pdo.rollback.php
     */
    public function rollBack(){
        return $this->getPDO()->rollBack();
    }
    
    /**
     * @link http://www.php.net/manual/en/pdo.commit.php
     */
    public function commit(){
        return $this->getPDO()->commit();
    }
    
    /**
     * @link http://www.php.net/manual/en/pdo.intransaction.php
     */
    public function inTransaction(){
        return $this->getPDO()->inTransaction();
    }
    
    /**
     * @link http://www.php.net/manual/en/pdo.lastinsertid.php
     */
    public function lastInsertId(){
        return call_user_func_array(array($this->getPDO(), 'lastInsertId'), func_get_args());
    }
    
    /**
     * @link http://www.php.net/manual/en/pdo.quote.php
     */
    public function quote(){
        return call_user_func_array(array($this->getPDO(), 'quote'), func_get_args());
    }
    
    public function errorCode(){
        return call_user_func_array(array($this->getPDO(), 'errorCode'), func_get_args());
    }
    
    public function errorInfo(){
        return call_user_func_array(array($this->getPDO(), 'errorInfo'), func_get_args());
    }
    
    /**
     * Quote table name
     * @param string $name
     * @return string
     */
    public function quoteTable($name){
        return $this->quoteIdentifier($name);
    }
    
    /**
     * Quote column name
     * @param string $name
     * @return string
     */
    public function quoteColumn($name){
        return $this->quoteIdentifier($name);
    }
    
    /**
     * Quote table or column
     * @param string $name
     * @return string
     */
    public function quoteIdentifier($name){
        $quote = null;
        switch($this->getAttribute(\PDO::ATTR_DRIVER_NAME)){
            case 'pgsql':
            case 'sqlsrv':
            case 'dblib':
            case 'mssql':
            case 'sybase':
                $quote = '"';
                break;
            case 'mysql':
            case 'sqlite':
            case 'sqlite2':
            default:
                $quote = '`';
        }
        
        $parts = explode('.', $name);
        foreach($parts as $k => $part){
            if($part !== '*'){
                $parts[$k] = $quote.$part.$quote;
            }
        }
        
        return implode('.', $parts);
    }
    
    public function buildLimitOffset($sql, $limit, $offset = 0){
        switch($this->getAttribute(\PDO::ATTR_DRIVER_NAME)){
            case 'sqlsrv':
            case 'dblib':
            case 'mssql':
            case 'sybase':
                throw new Exception('Limit/offset not implemented yet');
            case 'pgsql':
            case 'mysql':
            case 'sqlite':
            case 'sqlite2':
            default:
                if($limit > 0){
                    $sql .= "\n LIMIT ".$limit;
                }
                if($offset > 0){
                    $sql .= " OFFSET ".$offset;
                }
                
                return $sql;
        }
    }

    public function getPrefix(){
        return $this->configs[$this->current]['prefix'];
    }
    
    public function fixPrefix($sql){
        
    }
}
