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

			foreach ($ret as $r) {
				$this->returningValue[] = $this->castValues($r);
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
