<?php
/**
 * ark.database
 * @copyright 2014-2016 Liu Dong <ddliuhb@gmail.com>
 * @license MIT
 */

namespace Ark\Database;

/**
 * Database connection management
 */
class Connection
{
    /**
     * Current connection, null means select automatically, default means the default connection
     * @var string
     */
    protected $current = null;
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
        $this->addConnection('default', $dsn, $username, $password, $options);
    }
    
    /**
     * Is connection manager in auto slave mode: it has `auto_slave` config, and connection is in automatic mode
     * @return boolean
     */
    public function isAutoSlave() {
        return (!empty($this->configs['default']['options']['auto_slave'])) && isset($this->configs['slave']) && ($this->current === null);
    }

    /**
     * Get current connection config by key
     * @param  string $key Config key
     * @param mixed $default
     * @return mixed
     */
    public function getConfig($key = null, $default = null){
        $name = $this->current?:'default';

        $configs = $this->configs[$name];
        if ($key === null) {
            return $configs;
        }

        return isset($configs[$key])?$configs[$key]:$default;
    }

    /**
     * Get current connection option by key
     * @param  string $key
     * @param  mixed $default
     * @return mixed
     */
    public function getOption($key = null, $default = null) {
        $name = $this->current?:'default';

        $options = isset($this->configs[$name]['options'])?$this->configs[$name]['options']:[];
        if ($key === null) {
            return $options;
        }

        return isset($options[$key])?$options[$key]:$default;
    }
    
    /**
     * Set option(some options may not work when connected.)
     * @param string|array $key
     * @param mixed $value
     */
    public function setOption($key, $value = null) {
        $name = $this->current?:'default';

        if (is_array($key)) {
            $this->configs[$name]['options'] = array_merge($this->configs[$name]['options'], $key);
        } else {
            $this->configs[$name]['options'][$key] = $value;
        }
    }

    /**
     * Get PDO instance
     * @param  string $name Connection name
     * @return \PDO
     */
    public function getPDO($name = null){
        if(null === $name){
            $name = $this->current?:'default';
        }
        if(!isset($this->connections[$name])){
            $config = $this->configs[$name];
            $this->connections[$name] = new \PDO($config['dsn'], $config['username'], $config['password'], $config['options']);
        }
        
        return $this->connections[$name];
    }

    /**
     * Reset connections
     * @return \Ark\Database\Connection
     */
    public function reset() {
        $this->connections = [];

        return $this;
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
    public function switchConnection($name = null){
        $this->current = $name;
        
        return $this;
    }

    /**
     * Create a model factory
     * 
     * @param string $model
     * @param mixed $pk
     * @return \Ark\Database\ModelFactory
     */
    public function factory($model, $pk = null){
        return new ModelFactory($this, $model, $pk);
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
     * Call PDO methods
     *
     * @param  string $name
     *  - getAttribute: http://www.php.net/manual/en/pdo.getattribute.php
     *  - setAttribute: http://www.php.net/manual/en/pdo.setattribute.php
     *  - prepare: http://www.php.net/manual/en/pdo.prepare.php
     *  - query: http://www.php.net/manual/en/pdo.query.php
     *  - exec: http://www.php.net/manual/en/pdo.exec.php
     *  - beginTransaction: http://www.php.net/manual/en/pdo.begintransaction.php
     *  - rollBack: http://www.php.net/manual/en/pdo.rollback.php
     *  - commit: http://www.php.net/manual/en/pdo.commit.php
     *  - inTransaction: http://www.php.net/manual/en/pdo.intransaction.php
     *  - lastInsertId: http://www.php.net/manual/en/pdo.lastinsertid.php
     *  - quote: http://www.php.net/manual/en/pdo.quote.php
     *  - errorCode: http://php.net/manual/en/pdo.errorcode.php
     *  - errorInfo: http://php.net/manual/en/pdo.errorinfo.php
     *  
     * @param  array $arguments
     * @return mixed
     */
    public function __call($name, $arguments) {
        return call_user_func_array(array($this->getPDO(), $name), $arguments);
        
        // Reconnection does not apply on \Ark\Database\Connection for now, it only works on query builder.
        // static $noReconnectMethods = ['errorCode', 'errorInfo'];

        // $reconnect = $this->getOption('reconnect');
        // $reconnectRetries = $this->getOption('reconnect_retries', 1);
        // $reconnectDelayMS = $this->getOption('reconnect_delay_ms', 1000);

        // if (!$reconnect || $reconnectRetries <= 0 || in_array($name, $noReconnectMethods)) {
        //     return call_user_func_array(array($this->getPDO(), $name), $arguments);
        // }

        // while (true) {
        //     $e = null;
        //     $errorCode = null;
        //     $errorInfo = null;
        //     $result = null;

        //     $isReconnectError = false;
        //     try {
        //         $result = call_user_func_array(array($this->getPDO(), $name), $arguments);
        //     } catch (\Exception $e) {
        //     }

        //     if (!$e) {
        //         $errorCode = $this->getPDO()->errorCode();
        //         $errorInfo = $this->getPDO()->errorInfo();
        //     }

        //     $isReconnectError = Util::checkReconnectError($errorCode, $errorInfo, $e);

        //     // reconnect
        //     if ($isReconnectError && $reconnectRetries > 0) {
        //         $reconnectRetries--;
        //         $this->close();
        //         $reconnectDelayMS && usleep($reconnectDelayMS * 1000);
        //         continue;
        //     }

        //     if ($e) {
        //         throw $e;
        //     }

        //     return $result;
        // }
    }

    /**
     * Close connection(might not work!!!)
     * 
     * @param  mixed $name Connection name
     */
    public function close($name = null) {
        if(null === $name){
            $name = $this->current?:'default';
        }
        
        if(isset($this->connections[$name])) {
            unset($this->connections[$name]);
        }
    }

    public function beginReconnect() {
        $this->close();
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
        return $this->configs[$this->current?:'default']['prefix'];
    }
    
    public function fixPrefix($sql){
        
    }
}
