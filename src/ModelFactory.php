<?php
/**
 * ark.database
 * @copyright 2014-2016 Liu Dong <ddliuhb@gmail.com>
 * @license MIT
 */

namespace Ark\Database;

/**
 * Model factory
 */
class ModelFactory
{
    protected $db;
    protected $modelClass;
    protected $table;
    protected $pk;
    protected $with;
    
    public function __construct($db, $model, $pk = null){
        $this->db = $db;
        if($model[0] === '@'){
            $this->modelClass = '\Ark\Database\Model';
            $this->table = substr($model, 1);
        }
        else{
            $this->modelClass = $model;
            if(null !== $model::getConfig('table')){
                $this->table = $model::getConfig('table');
            }
            else{
                $this->table = $model::entityNameToDBName($model);
            }
        }
        if(null !== $pk){
            $this->pk = $pk;
        }
        else{
            $class = $this->modelClass;
            $this->pk = $class::getConfig('pk');
        }
    }
    
    /**
     * PK getter
     */
    public function getPK(){
        return $this->pk;
    }
    
    /**
     * Table getter
     */
    public function getTable(){
        return $this->table;
    }
    
    /**
     * @todo fetch related data together
     */
    public function with($with){
        $this->with = $with;
        
        return $this;
    }
    
    public function buildWith(){
        $with = $this->with;
        $columns = explode(',', $with);
        foreach($columns as $column){
            //if(strpos($column, '.')
        }
        $this->with = null;
    }
    
    /**
     * Map array to model
     * 
     * @param array $row
     * @return \Ark\Database\Model
     */
    public function map($row){
        $class = $this->modelClass;
        $model = new $class($this->db, $row, false);
        $model->setAttribute('table', $this->table)->setAttribute('pk', $this->pk);
        return $model;
    }
    
    /**
     * Map data rows to model array
     * 
     * @param array $rows
     * @return array
     */
    public function mapModels($rows){
        $rst = array();
        foreach($rows as $row){
            $rst[] = $this->map($row);
        }
        
        return $rst;
    }
    
    /**
     * Create a fresh model from array
     * 
     * @param array $row
     * @return \Ark\Database\Model
     */
    public function create($row = array()){
        $class = $this->modelClass;
        $model = new $class($this->db, $row);
        $model->setAttribute('table', $this->table)->setAttribute('pk', $this->pk);
        
        return $model;
    }
    
    /**
     * Build PK condition(multiple pk is also supported)
     * 
     * @param string|array $pk
     * @param mixed $_data
     * @return array
     */
    protected function buildPKConditions($pk, $_data){
        if(is_string($pk)){
            $pks = array($pk);
            if(!is_array($_data)){
                $_data = array($pk => $_data);
            }
        }
        else{
            $pks = $pk;
        }
        $params = array();
        foreach($pks as $k => $pk){
            $pks[$k] = $pk.'=:pk'.$k;
            $params[':pk'.$k] = $_data[$pk];
        }
        array_unshift($pks, 'AND');
        
        return array($pks, $params);
    }
    
    /**
     * Count with conditions
     * 
     * @param string|array $conditions
     * @param array $params
     * @return integer
     */
    public function count($conditions = '', $params = array()){
        return $this->db->builder()->select('COUNT(*)')->from($this->table)->where($conditions, $params)->queryValue();
    }
    
    /**
     * Count by key
     * 
     * @param string $key
     * @param mixed $value
     * @return integer
     */
    public function countBy($key, $value){
        return $this->count($key.'=:key', array(':key' => $value));
    }
    
    /**
     * Find by PK
     * @param int $id
     */
    public function find($pk){
        return $this->findByPK($pk);
    }
    
    /**
     * Find all
     */
    public function findAll(){
        $rows = $this->db->builder()->select()->from($this->table)->queryAll();
        if(false === $rows){
            return $rows;
        }
        
        return $this->mapModels($rows);
    }
    
    /**
     * Find one model with conditions
     * 
     * @param string|array $conditions
     * @param array $params
     * @return \Ark\Database\Model Or false on failure
     */
    public function findOne($conditions, $params = array()){
        $row = $this->db->builder()->select()->from($this->table)->where($conditions, $params)->limit(1)->queryRow();
        if(false === $row){
            return false;
        }
        
        return $this->map($row);
    }
        
    /**
     * Find one model by key
     * 
     * @param string $key
     * @param mixed $value
     * @return \Ark\Database\Model Or false on failure
     */
    public function findOneBy($key, $value){
        return $this->findOne($key.'=:key', array(':key' => $value));
    }
    
    /**
     * Find one model by primary key
     * 
     * @param string|array $pk
     * @return \Ark\Database\Model Or false on failure
     */
    public function findByPK($pk){
        $pkConditions = $this->buildPKConditions($this->pk, $pk);
        return $this->findOne($pkConditions[0], $pkConditions[1]);
    }
    
    /**
     * Find many models with conditions
     * 
     * @param string|array $conditions
     * @param array $params
     * @param string|array $orderBy
     * @param int $limit
     * @param int $offset
     * @return array Or false on failure
     */
    public function findMany($conditions = '', $params = array(), $orderBy = null, $limit = null, $offset = null){
        $cmd = $this->db->builder()->select()->from($this->table)->where($conditions, $params);
        if($orderBy){
            $cmd->orderBy($orderBy);
        }
        $rows = $cmd->limit($limit, $offset)->queryAll();
        if(false === $rows){
            return false;
        }
        else{
            return $this->mapModels($rows);
        }
    }
    
    /**
     * Find many models with by key
     * 
     * @param string $key
     * @param mixed $value
     * @param string|array $orderBy
     * @param int $limit
     * @param int $offset
     * @return array Or false on failure
     */
    public function findManyBy($key, $value,  $orderBy = null, $limit = null, $offset = null){
        return $this->findMany($key.'=:key', array(':key' => $value), $orderBy, $limit, $offset);
    }
    
    /**
     * Update table with condition
     * 
     * @param array $values
     * @param string|array $conditions
     * @param array $params
     * @return int|boolean
     */
    public function update($values, $conditions, $params = array()){
        return $this->db->builder()->update($this->table, $values, $conditions, $params);
    }
    
    /**
     * Update table by key
     * 
     * @param string $key
     * @param mixed $value
     * @param array $values
     * @return int|boolean
     */
    public function updateBy($key, $value, $values){
        return $this->update($values, $key.'=:key', array(':key' => $value));
    }
    
    /**
     * Update table by primary key
     * @param string|array $pk
     * @param array $values
     * @return int|boolean
     */
    public function updateByPK($pk, $values){
        $pkConditions = $this->buildPKConditions($this->pk, $pk);
        return $this->update($values, $pkConditions[0], $pkConditions[1]);
    }
    
    /**
     * Insert
     * 
     * @param array $values
     * @return int|boolean
     */
    public function insert($values){
        return $this->db->builder()->insert($this->table, $values);
    }
    
    /**
     * Delete with condition
     * 
     * @param string|array $conditioins
     * @param array $params
     * @return int|boolean
     */
    public function delete($conditions, $params = array()){
        return $this->db->builder()->delete($this->table, $conditions, $params);
    }
    
    /**
     * Delete by key
     * 
     * @param string $key
     * @param mixed $value
     * @return int|boolean
     */
    public function deleteBy($key, $value){
        return $this->delete($key.'=:key', array(':key' => $value));
    }
    
    /**
     * Delete by primary key
     * 
     * @param string|array $pk
     * @return int|boolean
     */
    public function deleteByPK($pk){
        $pkConditions = $this->buildPKConditions($this->pk, $pk);
        return $this->delete($pkConditions[0], $pkConditions[1]);
    }
    
    /**
     * Override __call magic method to provide many helper methods
     * 
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments){
        //findOneByXXX/findManyByXXX
        if(preg_match('#^find(One|Many)By(.+)$#', $name, $matches)){
            $one = $matches[1] === 'One';
            $findByKey = Model::entityNameToDBName($matches[2]);
            array_unshift($arguments, $findByKey);
            if($one){
                return call_user_func_array(array($this, 'findOneBy'), $arguments);
            }
            else{
                return call_user_func_array(array($this, 'findManyBy'), $arguments);
            }
        }
        elseif(preg_match('#^(update|delete|count)By(.+)$#', $name, $matches)){
            $action = $matches[1];
            $actionByKey = Model::entityNameToDBName($matches[2]);
            array_unshift($arguments, $actionByKey);
            return call_user_func_array(array($this, $action.'By'), $arguments); 
        }
        else{
            throw new Exception(sprintf('Helper method "%s" does not exist', $name));
        }
    }
}
