<?php
/**
 * ark.database
 * @copyright 2015 Liu Dong <ddliuhb@gmail.com>
 * @license MIT
 */

namespace Ark\Database;

/**
 * Database query builder
 */
class QueryBuilder
{
    protected $db;
    protected $statement;
    protected $params = array();
    protected $query = array();
    protected $sql;
    
    /**
     * Constructor
     * 
     * @param \Ark\Database\Connection $db Database connection
     */
    public function __construct($db){
        $this->db = $db;
    }
    
    protected function matchAlias($entry){
        if(preg_match('#^(.*?)(?i:\s+as\s+|\s+)(.*)$#', $entry, $matches)){
            return array(
                $matches[1],
                $matches[2],
            );
        }
        else{
            return false;
        }
    }
    
    protected function splitParts($parts){
        return preg_split('#\s*,\s*#', trim($parts), -1, PREG_SPLIT_NO_EMPTY);
    }
    
    /**
     * Reset builder
     * 
     * @return \Ark\Database\QueryBuilder
     */
    public function reset(){
        $this->statement = null;
        $this->params = array();
        $this->query = array();
        $this->sql = null;
        
        return $this;
    }
    
    /**
     * Prepare PDO statement
     *
     * @return \Ark\Database\QueryBuilder
     */
    public function prepare(){
        if(null === $this->statement){
            $this->statement = $this->db->prepare($this->getSql());
        }
        
        return $this;
    }
    
    /**
     * @see http://php.net/manual/en/pdostatement.bindparam.php
     * @return \Ark\Database\QueryBuilder
     */
    public function bindParam(){
        $this->prepare();
        call_user_func_array(array($this->statement, 'bindParam'), func_get_args());

        return $this;
    }
    
    /**
     * @see http://php.net/manual/en/pdostatement.bindvalue.php
     * @return \Ark\Database\QueryBuilder
     */
    public function bindValue(){
        $this->prepare();
        call_user_func_array(array($this->statement, 'bindValue'), func_get_args());
        
        return $this;
    }
    
    /**
     * Bind values
     * 
     * @param  array $values
     * @return \Ark\Database\QueryBuilder
     */
    public function bindValues($values){
        $this->prepare();
        
        foreach($values as $k=> $value){
            $this->statement->bindValue($k, $value);
        }
        
        return $this;
    }
    
    /**
     * Merge params
     * 
     * @param  array $params
     * @return \Ark\Database\QueryBuilder
     */
    public function mergeParams($params){
        foreach($params as $k => $v){
            $this->params[$k] = $v;
        }

        return $this;
    }
    
    /**
     * SELECT statement
     * example:
     *  select('contact.*, user.email)
     *  select(array('contact.*', 'user.email'))
     *  
     * @param string|array $fields
     * @return \Ark\Database\QueryBuilder
     */
    public function select($fields = '*'){
        if(is_string($fields) && strpos($fields, '(') !== false){
            $this->query['select'] = $fields;
        }
        else{
            if(!is_array($fields)){
                $fields = $this->splitParts($fields);
            }
            foreach($fields as $k => $field){
                if(false === strpos($field, '(')){
                    if($alias = $this->matchAlias($field)){
                        $fields[$k] = $this->db->quoteColumn($alias[0]).' AS '.$this->db->quoteColumn($alias[1]);
                    }
                    else{
                        $fields[$k] = $this->db->quoteColumn($field);
                    }
                }
            }
            $this->query['select'] = implode(', ', $fields);
        }
        
        return $this;
    }
    
    /**
     * DISTINCT statement
     * 
     * @return \Ark\Database\QueryBuilder
     */
    public function distinct(){
        $this->query['distinct'] = true;
        
        return $this;
    }
    
    /**
     * FROM statement
     * example:
     *  from('contact, user AS U')
     *  from(array('contact', 'user AS U'))
     *  
     * @param string|array $tables
     * @return \Ark\Database\QueryBuilder
     */
    public function from($tables){
        if(is_string($tables)){
            $tables = $this->splitParts($tables);
        }
        foreach($tables as $k => $table){
            if($alias = $this->matchAlias($table)){
                $tables[$k] = $this->db->quoteTable($alias[0]).' AS '.$this->db->quoteTable($alias[1]);
            }
            else{
                $tables[$k] = $this->db->quoteTable($table);
            }
        }
        
        $this->query['from'] = implode(', ', $tables);
        
        return $this;
    }
    
    /**
     * Build condition SQL
     * 
     * @param string|array $conditions
     * @return string
     */
    public function buildConditions($conditions){
        if(!is_array($conditions)){
            if(null === $conditions){
                return '';
            }
            return $conditions;
        }
        elseif($conditions === array()){
            return '';
        }
        
        $n = count($conditions);
        $operator = strtoupper($conditions[0]);
        if($operator === 'AND' || $operator === 'OR'){
            $result = array();
            for($i = 1; $i < $n; $i++){
                $condition = $this->buildConditions($conditions[$i]);
                if('' !== $condition){
                    $result[] = '('.$condition.')';
                }
            }
            if($result === array()){
                return '';
            }
            else{
                return implode(' '.$operator.' ', $result);
            }
        }
        
        if(!isset($conditions[1], $conditions[2])){
            return '';
        }
        
        $column = $this->db->quoteColumn($conditions[1]);
        $values = $conditions[2];
        if(!is_array($values)){
            $values = array($values);
        }
        if($operator === 'IN' || $operator === 'NOT IN'){
            if($values === array()){
                return $operator === 'IN'?'0':'';
            }
            foreach($values as $k => $value){
                $values[$k] = $this->db->quote($value);
            }
            
            return $column.' '.$operator.' ('.implode(',', $values).')';
        }
        elseif($operator === 'LIKE' || $operator === 'NOT LIKE' || $operator === 'OR LIKE' || $operator === 'OR NOT LIKE'){
            if($values === array()){
                return ($operator === 'LIKE' || $operator === 'OR LIKE')?'0':'';
            }
            if($operator === 'LIKE' || $operator === 'NOT LIKE'){
                $andor = 'AND';
            }
            else{
                $andor = 'OR';
                $operator = $operator === 'OR LIKE'?'LIKE':'NOT LIKE';
            }
            
            $result = array();
            foreach($values as $k => $value){
                $result[] = $column.' '.$operator.' '.$this->db->quote($value);
            }
            
            return implode($andor, $result);
        }
        else{
            throw new Exception('Invalid operator "'.$operator.'"');
        }
    }
    
    /**
     * WHERE statement
     * 
     * @param string|array $conditions
     * @param array $params
     * @return \Ark\Database\QueryBuilder
     */
    public function where($conditions, $params = array()){
        $this->mergeParams($params);
        $this->query['where'] = $this->buildConditions($conditions);
        
        return $this;
    }
    
    /**
     * ORDER BY statement
     * example:
     *  orderBy('id DESC')
     *  orderBy('firstname, lastname DESC')
     *  
     * @param string|array $fields
     * @return \Ark\Database\QueryBuilder
     */
    public function orderBy($fields){
        if(!is_array($fields)){
            $fields = $this->splitParts($fields);
        }
        
        foreach($fields as $k => $field){
            if(preg_match('#^(.*?)\s+(asc|desc)$#i', $field, $matches)){
                $fields[$k] = $this->db->quoteColumn($matches[1]).' '.$matches[2];
            }
            else{
                $fields[$k] = $this->db->quoteColumn($field);
            }
        }
        
        $this->query['order'] = implode(',', $fields);
        
        return $this;
    }
    
    /**
     * LIMIT statement
     * 
     * @param int $limit
     * @param int $offset
     * @return \Ark\Database\QueryBuilder
     */
    public function limit($limit, $offset = null){
        $this->query['limit'] = $limit;
        if(null !== $offset){
            $this->offset($offset);
        }
        
        return $this;
    }
    
    /**
     * OFFSET statement
     * 
     * @param int $offset
     * @return \Ark\Database\QueryBuilder
     */
    public function offset($offset){
        $this->query['offset'] = $offset;
        
        return $this;
    }
    
    /**
     * * JOIN statement
     * 
     * @param string $type LEFT JOIN|RIGHT JOIN...
     * @param string $table
     * @param string|array $conditions
     * @param array $params
     * @return \Ark\Database\QueryBuilder
     */
    protected function anyJoin($type, $table, $conditions = '', $params = array()){
        $this->mergeParams($params);
        if($alias = $this->matchAlias($table)){
            $table = $this->db->quoteTable($alias[0]).' AS '.$this->db->quoteTable($alias[1]);
        }
        else{
            $table = $this->db->quoteTable($table);
        }
        $conditions = $this->buildConditions($conditions);
        if('' !== $conditions){
            $conditions = ' ON '.$conditions;
        }
        
        if(isset($this->query['join']) && is_string($this->query['join'])){
            $this->query['join'] = array($this->query['join']);
        }
        
        $this->query['join'][] = $type.' '.$table.' '.$conditions;
        
        return $this;
    }
    
    /**
     * JOIN statement
     * 
     * @param string $table
     * @param string|array $conditions
     * @param array $params
     * @return \Ark\Database\QueryBuilder
     */
    public function join($table, $conditions = '', $params = array()){
        return $this->anyJoin('JOIN', $table, $conditions, $params);
    }
    
    /**
     * LEFT JOIN statement
     * 
     * @param string $table
     * @param string|array $conditions
     * @param array $params
     * @return \Ark\Database\QueryBuilder
     */
    public function leftJoin($table, $conditions = '', $params = array()){
        return $this->anyJoin('LEFT JOIN', $table, $conditions, $params);
    }
    
    /**
     * RIGHT JOIN statement
     * 
     * @param string $table
     * @param string|array $conditions
     * @param array $params
     * @return \Ark\Database\QueryBuilder
     */
    public function rightJoin($table, $condtions = '', $params = array()){
        return $this->anyJoin('RIGHT JOIN', $table, $conditions, $params);
    }
    
    /**
     * GROUP BY statement
     * 
     * @param string|array $fields
     * @return \Ark\Database\QueryBuilder
     */
    public function groupBy($fields){
        if(!is_array($fields)){
            $fields = $this->splitParts($fields);
        }
        
        foreach($fields as $k => $field){
            $fields[$k] = $this->db->quoteColumn($field);
        }
        
        $this->query['group'] = implode(',', $fields);
        
        return $this;
    }
    
    /**
     * HAVING statement
     * 
     * @param string|array $conditions
     * @param array $params
     * @return \Ark\Database\QueryBuilder
     */
    public function having($conditions, $params = array()){
        $this->mergeParams($params);
        $this->query['having'] = $this->buildConditions($conditions);
        
        return $this;
    }
    
    /**
     * UNION statement
     * 
     * @param string $sql
     * @return \Ark\Database\QueryBuilder
     */
    public function union($sql){
        if(isset($this->query['union']) && is_string($this->query['union'])){
            $this->query['union'][] = $this->query['union'];
        }
        
        $this->query['union'][] = $sql;
        
        return $this;
    }
    
    /**
     * Build query SQL
     * 
     * @param array $query use $this->query if not specified
     * @return string
     */
    public function buildQuery($query = null){
        if(null === $query){
            $query = $this->query;
        }
        
        $sql = "SELECT ";
        if(isset($query['distinct']) && $query['distinct']){
            $sql .= 'DISTINCT ';
        }
        $sql .= isset($query['select'])?$query['select']:'*';
        
        if(!isset($query['from'])){
            return false;
        }
        
        $sql .= "\nFROM ".$query['from'];
        if(isset($query['join'])){
            $sql .= "\n".(is_array($query['join'])?implode("\n", $query['join']):$query['join']);
        }
        if(isset($query['where']) && $query['where'] !== ''){
            $sql .= "\nWHERE ".$query['where'];
        }
        if(isset($query['group'])){
            $sql .= "\nGROUP BY ".$query['group'];
            if(isset($query['having'])){
                $sql .= "\nHAVING ".$query['having'];
            }
        }
        if(isset($query['order'])){
            $sql .= "\n ORDER BY ".$query['order'];
        }
        $limit = isset($query['limit'])?$query['limit']:0;
        $offset = isset($query['offset'])?$query['offset']:0;
        $sql = $this->db->buildLimitOffset($sql, $limit, $offset);
        
        if(isset($query['union'])){
            $sql .= "\n".(is_array($query['union'])?implode("\n", $query['union']):$query['union']);
        }
        
        return $sql;
    }
    
    /**
     * Set SQL for this command
     * 
     * @param string $sql
     * @return \Ark\Database\QueryBuilder
     */
    public function setSql($sql){
        if('' !== $prefix = $this->db->getPrefix()){
            $sql = preg_replace('#{{(.*?)}}#', $prefix.'\1', $sql);
        }
        $this->sql = $sql;
        
        return $this;
    }
    
    /**
     * Get SQL for this command
     * 
     * @return string
     */
    public function getSql(){
        if(null === $this->sql){
            if(!empty($this->query)){
                $this->setSql($this->buildQuery());
            }
            else{
                return false;
            }
        }
        
        return $this->sql;
    }
    
    /**
     * Prepare statement before query
     * @param array $params
     */
    protected function beginQuery($params = array()){
        $params = array_merge($this->params, $params);
        $this->prepare();
        if(false === $this->statement->execute($params)){
            $info = $this->statement->errorInfo();
            throw new Exception(sprintf('Statement error #%s: %s', $info[0], $info[2]));
        }
    }
    
    /**
     * Query
     * Statement cursor should be closed after fetching data($statement->closeCursor)
     * 
     * @param array $params
     * @return \PDOStatement
     */
    public function query($params = array()){
        $this->beginQuery($params);
        return $this->statement;
    }
    
    /**
     * Get query result as array
     * 
     * @param array $params
     * @return array
     */
    public function queryAll($params = array()){
        $this->beginQuery($params);
        $rst = $this->statement->fetchAll(\PDO::FETCH_ASSOC);
        $this->statement->closeCursor();
        return $rst;
    }
    
    /**
     * Get first row of result
     * 
     * @param array $params
     * @return array
     */
    public function queryRow($params = array()){
        $this->beginQuery($params);
        $rst = $this->statement->fetch(\PDO::FETCH_ASSOC);
        $this->statement->closeCursor();
        return $rst;
    }
    
    /**
     * Get first column of result set
     * 
     * @param array $params
     * @return array
     */
    public function queryColumn($params = array()){
        $this->beginQuery($params);
        $rst = $this->statement->fetchAll(\PDO::FETCH_COLUMN);
        $this->statement->closeCursor();
        return $rst;
    }
    
    /**
     * Get first column of first row of result set
     * 
     * @param array $params
     * @return string
     */
    public function queryValue($params = array()){
        $this->beginQuery($params);
        $rst = $this->statement->fetchColumn();
        $this->statement->closeCursor();
        
        return $rst;
    }
    
    /**
     * Execute statement and return rows affected
     * 
     * @param array $params
     * @return  int|boolean
     */
    public function execute($params = array()){
        $this->prepare();
        if(false === $rst = $this->statement->execute($params)){
            return false;
        }
        
        return $this->statement->rowCount();
    }
    
    /**
     * Insert data
     * 
     * @param string $table
     * @param array $values
     * @return last insert id, false on error
     */
    public function insert($table, $values){
        $keys = array();
        $params = array();
        $placeholders = array();
        foreach($values as $k => $v){
            $keys[] = $this->db->quoteColumn($k);
            $params[':'.$k] = $v;
            $placeholders[] = ':'.$k;
        }
        $sql = "INSERT INTO ".$this->db->quoteTable($table).' ('.implode(', ',$keys).') VALUES ('.implode(',', $placeholders).')';
        $result = $this->setSql($sql)->execute($params);
        if (!$result) {
            return false;
        }

        return $this->db->lastInsertId();
    }
    
    /**
     * Update table
     * 
     * @param string $table
     * @param string|array $values
     * @param string|array $conditions
     * @param array $params
     * @return int Rows affected, false on error
     */
    public function update($table, $values, $conditions = '', $params = array()){
        $updates = array();
        if (!is_array($values)) {
            $values = array($values);
        }
        foreach($values as $k => $value){
            if (is_integer($k)) {
                $updates[] = $value;
            } else {
                $updates[] = $this->db->quoteColumn($k).' = :'.$k;
                $params[':'.$k] = $value;
            }
        }
        
        $sql = "UPDATE ".$this->db->quoteTable($table).' SET '.implode(', ', $updates);
        $conditions = $this->buildConditions($conditions);
        if('' !== $conditions){
            $sql .= ' WHERE '.$conditions;
        }
        
        return $this->setSql($sql)->execute($params);
    }
    
    /**
     * Delete
     * 
     * @param string $table
     * @param array $values
     * @param string|array $conditions
     * @param array $params
     * @return int Rows affected, false on error
     */
    public function delete($table, $conditions = '', $params = array()){
        $sql = 'DELETE FROM '.$this->db->quoteTable($table);
        $conditions = $this->buildConditions($conditions);
        if('' !== $conditions){
            $sql .= ' WHERE '.$conditions;
        }
        
        return $this->setSql($sql)->execute($params);
    }
}
