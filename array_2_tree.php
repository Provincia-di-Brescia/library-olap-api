<?php

class Array2Tree {
	
	private $tree = NULL;
	
	function __construct ($flat_array)
	{
		foreach ($flat_array as $fa) {
			$parameters = NULL;
			foreach ($fa as $key => $value) {
				$parameters[] = $key;
				if ($value !== 'measures')
					$parameters[] = $value;
			}
					
			if ($parameters[0] == 'measures') {
				$this->tree = array ('measures' => array());
				array_shift ($parameters);
				for ($i = 0; $i < count($parameters); $i++)
					$this->tree['measures'][$parameters[$i]] = $parameters[++$i];
			} else {
				$first_element = array_shift($parameters);
				if ($this->tree == NULL)
					$this->tree = array ($first_element => array());
				$this->insertInTree ($parameters, $this->tree[$first_element]);
			}
		}
	}
	
	private function insertInTree ($input_array, &$key_array)
	{
		$key = array_shift($input_array);
		
		if ($key_array != NULL)
			foreach ($key_array as $ka => $ke) 
				if ($ka == $key) {
					$this->insertInTree ($input_array, $key_array[$ka]);
					return;
				}
			
		if ($key == 'measures') {
			$key_array[$key] = array();
			for ($i = 0; $i < count($input_array); $i++)
				$key_array[$key][$input_array[$i]] = $input_array[++$i];
		} else {
			$key_array[$key] = array();
			$this->insertInTree ($input_array, $key_array[$key]);
		}
	}
	
	function getTree ()
	{
		return $this->tree;
	}
}