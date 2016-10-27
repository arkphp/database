<?php
/**
 * ark.database
 * @copyright 2014-2016 Liu Dong <ddliuhb@gmail.com>
 * @license MIT
 */

namespace Ark\Database;

/**
 * Model
 */
class Model
{
    private $_table;
    
    private $_pk;
    
    private $_relations;
        
    protected $_db;
    
    protected $_data = array();
    
    protected $_safe;
    
    protected $_dirty = array();
    
    protected $_isNew;
    
    /**
     * Model configuration
     * 
     * Sub classes should extend this static method
     * @return array
     */
    public static function config(){
        return array(
            //'table' => 'table',
            'pk' => 'id',
            //'relations' => array(),
        );
    }
    
    /**
     * Get model configuration
     * 
     * @param string $key
     * @return mixed
     */
    final public static function getConfig($key = null){
        $config = static::config();
        if(null === $key){
            return $config;
        }
        elseif(isset($config[$key])){
            return $config[$key];
        }
        else{
            return null;
        }
    }
    
    /**
     * Get model table
     */
    public function getTable(){
        return $this->_table;
    }

    /**
     * Get model pk
     */
    public function getPK(){
        return $this->_pk;
    }
        
    /**
     * Get model relations
     */
    public function getRelations(){
        return $this->_relations;
    }
    
    /**
     * Translate class name to table name.
     * example:
     *  BlogPost => blog_post
     *  Acme\BlogPost => blog_post
     * @param string $class Class name
     * @return string
     */
    static public function entityNameToDBName($class){
        //namespace
        if(false !== $pos = strrpos($class, '\\')){
            $class = substr($class, $pos + 1);
        }
        
        return strtolower(preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $class));
    }
    
    /**
     * Model constructor
     * 
     * @param \Ark\Database\Connection $db
     * @param array $data
     * @param boolean $isNew Is it a new model or a model loaded from database.
     */
    public function __construct($db, $data = [], $isNew = true){
        $this->_db = $db;
        
        if($isNew){
            $this->_dirty = $data;
        }
        else{
            $this->_data = $data;
        }
        $this->_isNew = $isNew;
        
        $this->setAttributes(static::config());
    }
    
    /**
     * Set attribute
     *
     * @return \Ark\Database\Model
     */
    public function setAttribute($attribute, $value){
        if($attribute === 'table'){
            $this->_table = $value;
        }
        elseif($attribute === 'pk'){
            $this->_pk = $value;
        }
        elseif($attribute === 'relations'){
            $this->_relations = $value;
        }
        
        return $this;
    }
    
    /**
     * Set attributes
     * 
     * @param array $attributes
     * @return \Ark\Database\Model
     */
    public function setAttributes($attributes){
        foreach($attributes as $k => $v){
            $this->setAttribute($k, $v);
        }
        
        return $this;
    }
    
    /**
     * Is it a new model
     * 
     * @return boolean
     */
    public function isNew(){
        return $this->_isNew;
    }
    
    /**
     * Is it synced with database
     * 
     * @return boolean
     */
    public function isDirty(){
        return !empty($this->_dirty);
    }
    
    /**
     * Get raw data
     * 
     * @param string $key
     * @return mixed
     */
    public function getRaw($key = null){
        if(null === $key){
            return $this->_data;
        }
        else{
            return isset($this->_data[$key])?$this->_data[$key]:null;
        }
    }
    
    /**
     * Get data
     * 
     * @param string $key
     * @return mixed Find in dirty data first, return all data if key is not specified
     */
    public function get($key = null){
        if(null === $key){
            return array_merge($this->_data, $this->_dirty);
        }
        else{
            if(isset($this->_dirty[$key])){
                return $this->_dirty[$key];
            }
            elseif(isset($this->_data[$key])){
                return $this->_data[$key];
            }
            else{
                return null;
            }
        }
    }
    
    /**
     * Magic method to fetch a model field
     * @param  string $key
     * @return mixed
     */
    public function __get($key){
        $relations = $this->getRelations();
        if(isset($relations[$key])){
            return $this->getWithRelation($key);
        }
        return $this->get($key);
    }
    
    /**
     * Get a field with relation
     * @param  string $name
     * @return mixed
     */
    public function getWithRelation($name){
        $relations = $this->getRelations();
        if(isset($relations[$name])){
            $relation = $relations[$name];
            if($relation['relation'] === 'OTO'){
                return $this->getOneToOne($relation['target'], isset($relation['key'])?$relation['key']:null, isset($relation['target_key'])?$relation['target_key']:null);
            }
            elseif($relation['relation'] == 'OTM'){
                return $this->getOneToMany($relation['target'], isset($relation['key'])?$relation['key']:null, isset($relation['target_key'])?$relation['target_key']:null);
            }
            elseif($relation['relation'] == 'MTO'){
                return $this->getManyToOne($relation['target'], isset($relation['key'])?$relation['key']:null, isset($relation['target_key'])?$relation['target_key']:null);
            }
            elseif($relation['relation'] == 'MTM'){
                return $this->getManyToMany($relation['target'], $relation['through'], isset($relation['key'])?$relation['key']:null, isset($relation['target_key'])?$relation['target_key']:null);
            }
            else{
                throw new Exception('Invalid relation "'.$relation['relation'].'"');
            }
        }
        else{
            return false;
        }
    }
    
    /**
     * Has one
     * 
     * @param string $target target model or @table
     * @param string $key
     * @param string $target_key
     * @return \Ark\Database\Model
     */
    public function getOneToOne($target, $key = null, $target_key = null){
        $factory = $this->_db->factory($target);
        if(null === $key){
            $key = $this->getPK();
        }
        if(null === $target_key){
            $target_key = $factory->getPK();
        }
        
        return $factory->findOneBy($target_key, $this->get($key));
    }
    
    /**
     * Has many
     * 
     * @param string $target
     * @param string $key
     * @param string $target_key
     * @return array
     */
    public function getOneToMany($target, $key = null, $target_key = null){
        $factory = $this->_db->factory($target);
        if(null === $key){
            $key = $this->getPK();
        }
        if(null === $target_key){
            $target_key = $key;
        }
        
        return $factory->findManyBy($target_key, $this->get($key));
    }
    
    /**
     * Belongs to
     * 
     * @param string $target
     * @param string $key
     * @param string $target_key
     * @return \Ark\Database\Model
     */
    public function getManyToOne($target, $key = null, $target_key = null){
        $factory = $this->_db->factory($target);
        if(null === $target_key){
            $target_key = $factory->getPK();
        }
        if(null === $key){
            $key = $target_key;
        }
        
        return $factory->findOneBy($target_key, $this->get($key));
    }
    
    /**
     * Many to many
     * 
     * @param string $target
     * @param string $through
     * @param string $key
     * @param string $target_key
     * @return array
     */
    public function getManyToMany($target, $through, $key = null, $target_key = null){
        $factory = $this->_db->factory($target);

        if(null === $key){
            $key = $this->getPK();
        }
        if(null === $target_key){
            $target_key = $factory->getPK();
        }
        
        $through = $this->parseThrough($through);
        if(!$through[1]){
            $through[1] = $key;
        }
        if(!$through[2]){
            $through[2] = $target_key;
        }
        
        $rows = $this->_db->builder()
            ->select('t.*')
            ->from($factory->getTable().' t')
            ->leftJoin($through[0].' m', 'm.'.$through[2].'=t.'.$target_key)
            ->where('m.'.$through[1].'=:value', array(
                ':value' => $this->get($key)
            ))
        ->queryAll();
        
        if(false === $rows){
            return false;
        }
        
        return $factory->mapModels($rows);
    }
    
    protected function parseThrough($through){
        $through = explode(',', $through);
        $table = trim($through[0]);
        $key = isset($through[1])?trim($through[1]):null;
        $target_key = isset($through[2])?trim($through[2]):null;
        
        return array($table, $key, $target_key);
    }
    
    /**
     * Set data
     * 
     * @param string|array $key
     * @param mixed $value
     * @return \Ark\Database\Model
     */
    public function set($key, $value = null){
        if(is_array($key)){
            $this->_dirty = $key;
        }
        else{
            $this->_dirty[$key] = $value;
        }
        
        return $this;
    }
    
    public function __set($key, $value){
        $this->_dirty[$key] = $value;
    }
    
    public function __isset($key){
        return isset($this->_dirty[$key]) || isset($this->_data[$key]);
    }
    
    public function __unset($key){
        if(isset($this->_dirty[$key])){
            unset($this->_dirty[$key]);
        }
    }
    
    protected function buildPKConditions(){
        $pk = $this->getPK();
        if(is_string($pk)){
            $pks = array($pk);
        }
        else{
            $pks = $pk;
        }
        $params = array();
        foreach($pks as $k => $pk){
            $pks[$k] = $pk.'=:pk'.$k;
            $params[':pk'.$k] = $this->_data[$pk];
        }
        array_unshift($pks, 'AND');
        
        return array($pks, $params);
    }
    
    /**
     * Save modified data to db
     * 
     * @return int|boolean
     */
    public function save(){
        if($this->beforeSave()){
            if($this->isNew()){
                $data = $this->_dirty;
                
                //insert
                if(false !== $rst = $this->_db->builder()->insert($this->getTable(), $data))
                {
                    if(is_string($this->getPK()) && $id = $this->_db->lastInsertId()){
                        $data[$this->getPK()] = $id;
                    }
                    $this->_data = $data;
                    $this->_dirty = array();
                    $this->_isNew = false;
                    $this->afterSave();
                    return $rst;
                }
            }
            else{
                if($this->isDirty()){
                    //update
                    $pkConditions = $this->buildPKConditions();
                    if(false !== $rst = $this->_db->builder()->update($this->getTable(), $this->_dirty, $pkConditions[0], $pkConditions[1])){
                        $this->_data = array_merge($this->_data, $this->_dirty);
                        $this->_dirty = array();
                        $this->afterSave();
                        return $rst;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Delete model
     * @return int|boolean
     */
    public function delete(){
        if($this->beforeDelete()){
            $pkConditions = $this->buildPKConditions();
            if(false !== $rst = ($this->isNew() || $this->_db->builder()->delete($this->getTable(), $pkConditions[0], $pkConditions[1]))){
                $this->_data = array();
                $this->_dirty = array();
                $this->afterDelete();
                
                return $rst;
            }
        }
        
        return false;
    }
    
    protected function beforeSave(){
        return true;
    }
    
    protected function afterSave(){
        return true;
    }
    
    protected function beforeDelete(){
        return true;
    }
    
    protected function afterDelete(){
        return true;
    }
    
}