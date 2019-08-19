<?php

/*
A partire dall'istruzione sql predisposta eseguendo il parsing dell'url attraverso il quale
la api è stata invocata, esegue la lettura del database di produzione.
*/

class Api {
	
	private $returningType;
	private $returningValue = array();
	
	function __construct ($sqlStatement)
	{
		$settings = json_decode (file_get_contents ('config.json'));

		$dsn = $settings->dsnOlap;

		try {
            $db = new PDO($dsn, $settings->usernameOlap, $settings->passwordOlap);
			if (($ret = $db->query($sqlStatement, PDO::FETCH_ASSOC)) == FALSE)
				throw new Exception ("Fail to exec Olap query");	

			if ($ret->rowCount() != 0)
				foreach ($ret as $r) {
					$this->returningValue[] = $this->castValues($r);
				}
			else {
				$getFieldsFromQuery = function ($query) {
					$arr = explode('as `', $query);
					array_shift($arr);
					array_walk ( $arr , function(&$item) {$itemFrag = explode('`', $item); $item = $itemFrag[0];});
					foreach ($arr as $a)
						$this->returningValue[0][$a] = strstr ( $a , '__measures__') ? 0 : 'null';
				};
				$getFieldsFromQuery ($sqlStatement);
			}
					
			$this->returnigType = 'json';
			
        } catch  (Exception $e) {
			$this->returningType = 'error';
			$this->returningValue[] = $e->getMessage();
		}
	}
	
	private function castValues ($array)
	{
		foreach ($array as $key => &$a) 
			$array[$key] = is_numeric($a) ? (float)$a : $a;
		
		return $array;
	}
	
	function getType()
	{
		return $this->returningType;
	}
	
	function getValue()
	{
		return $this->returningValue;
	}
	
}
