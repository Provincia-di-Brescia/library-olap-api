<?php

class UriParser 
{
	private $name;
	private $nameType;
	private $predicate = array();
	private $parserMessage = 'ok';
		
	private $levelOperations = array (
		array ('type'=>'operator', 'symbol'=>'/', 'nameType'=>'fact', 'leafLevel'=>TRUE),
		array ('type'=>'operator', 'symbol'=>'?', 'nameType'=>'function', 'leafLevel'=>TRUE),
		array ('type'=>'separator', 'symbol'=>'&'),
		array ('type'=>'operator', 'symbol'=>'=', 'nameType'=>'parameterType', 'leafLevel'=>TRUE),
		array ('type'=>'separator', 'symbol'=>'|'),
		array ('type'=>'operator', 'symbol'=>':', 'nameType'=>'dimension', 'leafLevel'=>TRUE),
		array ('type'=>'separator', 'symbol'=>';'),
		array ('type'=>'namedSeparator', 'symbol'=>'-', 'nameType'=>'interval'),
		array ('type'=>'operator', 'symbol'=>',', 'nameType'=>'memberValue', 'leafLevel'=>TRUE),
		array ('type'=>'operator', 'symbol'=>NULL, 'nameType'=>'memberValue', 'leafLevel'=>TRUE)
	);
	
	function __construct ($uri, $fatherLevel = NULL, &$fatherPredicate = NULL)
	{
		for ($level = 0; $level < count($this->levelOperations); $level++) {
			$levelOperation = $this->levelOperations[$level];
			
			if (strpos ($uri , $levelOperation['symbol']) !== FALSE) { // operator
		
				switch ($levelOperation['type']) {
					case 'operator': 
						$paramArray = explode ($levelOperation['symbol'], $uri);
						$this->nameType = $levelOperation['nameType'];
						$this->name = array_shift($paramArray);
						
						$newUri = implode ($levelOperation['symbol'], $paramArray);
											
						$tmp = new UriParser ($newUri, $level, $this->predicate);
			
						if ($tmp->getParserMessage() == 'ok')
							$this->predicate[] = $tmp;
						
						return;
					case 'separator':
						$paramArray = explode ($levelOperation['symbol'], $uri);
						$this->parserMessage = 'doNotUseThis';
						foreach ($paramArray as $pa)
							$fatherPredicate[] = new UriParser ($pa, $level, $fatherPredicate);
						return;
					case 'namedSeparator':
						$paramArray = explode ($levelOperation['symbol'], $uri);
						
						$this->nameType = $levelOperation['nameType'];
						foreach ($paramArray as $pa)
							$this->predicate[] = new UriParser ($pa, $level, $this->predicate);
						return;
					default:
						echo "Not recognised pareser element\n";
						exit;
				}
			}
		}
		
		if ($fatherLevel === NULL)
			$this->nameType = 'command';
		else {
			for ($l = $fatherLevel+1; $this->levelOperations[$l]['type'] != 'operator'
									|| $this->levelOperations[$l]['leafLevel'] != TRUE; $l++)
				;
			$this->nameType = $this->levelOperations[$l]['nameType'];
		}
		$this->name = $uri;
		$this->predicate = NULL;
	} 
	
	function getParserMessage ()
	{
		return $this->parserMessage;
	}
	
	function getPredicate ()
	{
		return $this->predicate;
	}
	
	function getName ()
	{
		return $this->name;
	}
	
	function getNameType ()
	{
		return $this->nameType;
	}
	
	function getNodeByName ($node, $searchPattern, $level = 0)
	{
		if ($node->getName() == $searchPattern[$level]) {
			if (count($searchPattern) == $level+1)
				return $node;
			
			if ($node->getPredicate() == NULL)
				return NULL;
			
			$level++;
			
		}
		if ($node->getPredicate() == NULL)
			return NULL;
		foreach ($node->getPredicate() as $p) {
			$retval = $this->getNodeByName ($p, $searchPattern, $level);
			if ($retval)
				return $retval;
		}
		return NULL;
	}
}