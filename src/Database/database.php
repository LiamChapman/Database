<?php

namespace Database;

class Database {

	static $pdo;
	public $table = null;	

	public function __construct ($host, $username, $password, $database) {		
		try {
			self::$pdo = new \PDO("mysql:host=$host;dbname=$database", $username, $password, array(\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));			
		} catch(\PDOException $e) {
			echo $e->getMessage();
		}
	}

	public function columns () {		
		//Column 
		$columns = array();
		// Get Columns from Database
		$db_columns = self::$pdo->query("SHOW COLUMNS FROM " . $this->table);
		$db_columns = $db_columns->fetchAll(\PDO::FETCH_OBJ);
		foreach ($db_columns as $obj) {
			$columns[] = $obj->Field;
		}
		return $columns;
	}

	public function valid_to_process ($data) {
		$columns 	= $this->columns();
		$ok_to_use 	= array();
		// updated_at timestamp add here by default.
		if (!array_key_exists('updated_at', $data)) {
			$data['updated_at'] = date('Y-m-d H:i:s');
		}
		foreach ($data as $column => $value) {
			if (in_array($column, $columns)) {
				$ok_to_use[$column] = $value; 
			}//endif
		}//endforeach
		return $ok_to_use;
	}

	public function raw_query ($sql, $insert_id = true) {
		$query = self::$pdo->prepare($sql);
		if ($insert_id) {
			$query->execute();
			return self::$pdo->lastInsertId();
		} else {
			return $query->execute();
		}
	}

	public function rows ($where = '', $columns = '*', $table = null) {
		$table 	 = is_null($table) ? $this->table : $table;
		$columns = is_string($columns) ? $columns : implode(",", $columns);
		$query 	 = self::$pdo->query("SELECT " . $columns . " FROM " . $table . " " . $where);
		$rows  	 = $query->fetchAll(\PDO::FETCH_OBJ);				
		if (count($rows) == 1) {
			return $rows[0];
		} else {
			return $rows;
		}
	}
	
	public function prep_rows ($where = '', $values = array(), $columns = '*', $table = null) {
		$table 	 = is_null($table) ? $this->table : $table;
		$columns = is_string($columns) ? $columns : implode(",", $columns);
		$query 	 = self::$pdo->prepare("SELECT " . $columns . " FROM " . $table . " " . $where);		
		if ($query->execute($values)) {
			$rows = $query->fetchAll(\PDO::FETCH_OBJ);
			if (count($rows) == 1) {
				return $rows[0];
			} else {
				return $rows;
			}
		}
	}

	public function insert ($data = array()) {
		$data 	 = self::valid_to_process($data);
		$columns = array();
		$values  = array();
		foreach ($data as $column => $value) {
			$columns[] = $column;
			$binded[]  = ':'.$column;
			$values[]  = $value;
		}
		$query = self::$pdo->prepare("INSERT INTO " . $this->table . " (".implode(',', $columns).") VALUES (".implode(",", $binded).")");
		foreach($binded as $key => $bind_to) {
			$query->bindParam($bind_to, $values[$key]);
		}
		if ($query->execute()) {
			return self::$pdo->lastInsertId();
		}
	}

	public function update ($data = array(), $id = null) {
		$data 	 = self::valid_to_process($data);
		$columns = array();
		$values  = array();
		foreach ($data as $column => $value) {
			$columns[] = $column . ' = :'. $column;
			$binded[]  = ':'.$column;
			$values[]  = $value;
		}
		$query = self::$pdo->prepare("UPDATE " . $this->table . " SET ".implode(",", $columns)."  WHERE id = :id");
		foreach($binded as $key => $bind_to) {
			$query->bindParam($bind_to, $values[$key]);
		}
		$query->bindParam(':id', $id, \PDO::PARAM_INT);
		if ($query->execute()) {
			return $id;
		}
	}

	public function save ($data) {		
		if (!array_key_exists('id', $data)) {
			return self::insert($data);
		} else {
			return self::update($data, $data['id']);
		}
	}

	public function delete ($id = null) {
		$query = self::$pdo->prepare("DELETE FROM " . $this->table . " WHERE id = :id");
		$query->bindParam(':id', $id, \PDO::PARAM_INT);
		if ($query->execute()) {
			return $id;
		}
	}
	
	public function table_exists () {
		$query = "SHOW TABLES LIKE '".$this->table."'";
		if ($result = self::$pdo->query($query)) {
			return count($result);
		}
	}
	
	public function create ($name, $columns = array(), $engine = 'InnoDB', $charset = 'utf8', $settings = array()) {
		if (isset($name) && $name != '') {
			$query = "CREATE TABLE IF NOT EXISTS `" . $name .'`';
			// Columns
			if (isset($columns) && count($columns) > 0 ) {
				// Open 
				$query .= ' (';
					$query .= '`id` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,';					
					foreach ($columns as $column => $parameters) {
						$query .= '`'.$column.'` ' . $parameters.',';
					}
				//remove last comma
				#$query  = substr($query, 0, -1);
				$query .= '`created_at` TIMESTAMP DEFAULT NOW(),';
				$query .= '`updated_at` DATETIME';
				// Close
				$query .= ') ';
			}
			// Settings
			if (isset($settings) && count($settings) > 0 ) {
				foreach ($settings as $setting => $value) {
					$query .= $setting . '=' . $value . ' ';
				}
			}
			// Defaults
			$query .= ' ENGINE='.$engine.' DEFAULT CHARSET='.$charset;
			// Close the statement			
			$query .= ';';
			// Run Query
			if (self::raw_query($query, false)) {
				return true;
			} else {
				return false;
			}
		}
	}
	
	public function truncate () {
		if (self::raw_query('TRUNCATE ' . $this->table, false)) {
			return true;
		} else {
			return false;
		}
	}

	public function beginTransaction () {
 		self::$pdo->beginTransaction();
	}
		
	public function commit () {
		self::$pdo->commit();
	}
	
	public function rollback () {
		self::$pdo->rollback();
	}

	public function __destruct () {
		self::$pdo = null;
	}

}