<?php

require_once 'uri_parser.php';
require_once 'fact_info.php';
require_once 'sql_statement.php';

/*
A partire dalla rappresentazione sintatticamente resa esplicita dalla UriParser (che ha costruito un albero che ricorsivamente, tramite il paradigma nome-predicato-complemento oggetto, rappresenta il contenuto semantico della url tramite la quale la api è stata invocata) questa classe costruisce la query sql che traduce fedelmente 
la richiesta contenuta nella url di chiamata della api
*/

class OlapQuery {
	
	static $factConf = NULL;

	
	static $rootNode;
	private $returningSql;
	private $returningJson;
	private $returningType;
	
	static $sqlStatement;
		
	function __construct ($requestTree)
	{
		self::$rootNode = $requestTree;
		date_default_timezone_set ('Europe/Rome');
		
		try {
			if ($requestTree->getNameType() == 'fact') {
				self::$factConf = new FactInfo ($requestTree->getName());
				self::$sqlStatement = new SqlStatement (self::$factConf);
				
				self::$sqlStatement->setFromStatement ($requestTree->getName());
			
				$this->returningType = 'sql';
							
				foreach ($requestTree->getPredicate() as $p) 	
					if ($p->getNameType() == 'function') {
						$fr = new FunctionRender($p);
						if ($fr->getFunctionType() == 'dimensions') {
							$this->returningType = 'json';
							$this->returningJson = $this->getFactInformations
								($requestTree->getName(), 'dimensions');
								
							return;
						} else if ($fr->getFunctionType() == 'measures') {
							$this->returningType = 'json';
							$this->returningJson = $this->getFactInformations
								($requestTree->getName(), 'measures');
								
							return;
						}
					}
					
				self::$sqlStatement->setMeasuresInSelectExpr (OlapQuery::$factConf->getMeasuresInfo());
				
				$this->returningSql = self::$sqlStatement->getSqlStatement();
										
			} else if ($requestTree->getNameType() == 'command' && 
												$requestTree->getName() == 'fact_tables') {
				self::$factConf = $this->getFactsInformations();
				$this->returningType = 'json';
				$this->returningJson = self::$factConf;
			}
		}
		catch (Exception $e) {
			$this->returningType = 'error';
			$this->returningJson = $e->getMessage();
		}
	}
	
	public function getType ()
	{
		return $this->returningType;
	}
	
	public function getValue ()
	{
		return $this->returningType == 'sql' ? $this->returningSql : $this->returningJson;
	}
} 

/* 
rendering dei parametri relativi alla funzione richiesta
*/

class FunctionRender
{
	private $functionType = NULL;
	
	function __construct ($requestTree)
	{
		switch ($requestTree->getName())  {
			case 'aggregate':
				$this->functionType = 'aggregate';
				if (($sons = $requestTree->getPredicate()) != NULL)
					foreach ($sons as $p) 
						if ($p->getNameType() == 'parameterType')  
							$ptr = new ParameterTypeRender ($p);
				break;
			case 'dimensions':
				$this->functionType = 'dimensions';
				break;
			case 'measures':
				$this->functionType = 'measures';
				break;
			default:
				throw new Exception ("Function ".$requestTree->getName()." non riconosciuta");
				
		}
	}
	
	function getFunctionType ()
	{
		return $this->functionType;
	}
}

/*
rendering del tipo di parametro
*/

class ParameterTypeRender
{
	private $sqlParameterType = NULL;
	private $typeSqlParameterType;
	
	function __construct ($requestTree)
	{
		switch ($requestTree->getName())  {
			case 'cut':
				foreach ($requestTree->getPredicate() as $p) 
					if ($p->getNameType() == 'dimension') {
						$dr = new DimensionRender ($p);
						OlapQuery::$sqlStatement->setWhereStatement($dr->getSqlDimension());
					}
				break;
			case 'drilldown':
				if (($sons = $requestTree->getPredicate()) == NULL) 
					throw new Exception ("Drilldown has not parameters");


				$branch = 0;
				foreach ($sons as $s)
					if ($s->getNameType() == 'dimension') 
						$dr = new DrilldownRender ($s, $branch++);
					

				break;
		/*	case 'measure':
				foreach ($requestTree->getPredicate() as $p) 
					if ($p->getNameType() == 'dimension') 
						OlapQuery::setSelectExpr($p->getName(), 
									OlapQuery::$getAggregationFunction($p->getName()));

				break; */

			default:
				throw new 
					Exception ("parameterType ".$requestTree->getName()." non riconosciuto");
		}
	}
}

/*
rendering del drilldown
*/

class DrilldownRender 
{
	private static $constructedDimension = array();	
	
	function __construct ($node, $branch)
	{
		$drilldownDimension = $node->getName();
		
		if (($son = $node->getPredicate()) == NULL)
			$drilldownParameter = NULL;
		else
			$drilldownParameter = $son[0]->getName();

		$dimensionInfo = OlapQuery::$factConf->getDimensionInfo($drilldownDimension);
		
		if (($drilldownDimensionHierarchy = $dimensionInfo['sons']) != NULL) {
			
			$highGroupingIndex = $this->getHighGroupingIndex ($dimensionInfo, $branch);
				
			if ($drilldownParameter == NULL)
				$lowGroupingIndex = NULL;
			else {
				$lowGroupingIndex = 0;

				$drilldownParameterInfo = OlapQuery::$factConf->getDimensionInfo($drilldownParameter);
				
				foreach ($drilldownDimensionHierarchy as $dh) {
					$dhDimensionInfo = OlapQuery::$factConf->getDimensionInfo($dh);
					$dhFather = $dhDimensionInfo['father'];
					$dhMain_name = $dhDimensionInfo['main_name'];
					
					$father = $drilldownParameterInfo['father'];
					$main_name = $drilldownParameterInfo['main_name'];
					
					if ($dhMain_name == $main_name)
						break;
					else
						$lowGroupingIndex++;
				}
			}
			
			$dimensionStruct = array ();
			if ($lowGroupingIndex === NULL) {
				if (!isset ($drilldownDimensionHierarchy[$highGroupingIndex]))
					throw new Exception ("Invalid drilldown request");
				$dimensionStruct[] = $drilldownDimensionHierarchy[$highGroupingIndex];
			} else if ($highGroupingIndex < $lowGroupingIndex) 
				for ($i = $highGroupingIndex; $i <= $lowGroupingIndex; $i++)
					$dimensionStruct[] = $drilldownDimensionHierarchy[$i];
			else 
				$dimensionStruct[] = $drilldownDimensionHierarchy[$lowGroupingIndex];
			
			OlapQuery::$sqlStatement->setGroupStatement ($dimensionStruct);

			
		} else
			OlapQuery::$sqlStatement->setGroupStatement ($drilldownDimension);
			
	}
	
	function getConstructedDimension ()
	{
		return self::$constructedDimension;
	}
	
	private function getHighGroupingIndex ($ddDimensionInfo, $branch)
	{
		$dimensionName = $ddDimensionInfo['main_name'];

		if (($cutNode = OlapQuery::$rootNode->getNodeByName (OlapQuery::$rootNode, ['cut', $dimensionName])) == NULL){
			
			if ($ddDimensionInfo['sons'])
				foreach ($ddDimensionInfo['sons'] as $dimensionName) {

					if (($cutNode = OlapQuery::$rootNode
								->getNodeByName (OlapQuery::$rootNode, ['cut', $dimensionName])) != NULL)
						break;
				}
		}
		
		if ($cutNode == NULL)
			return 0;
		
		for ($i = 0, $n = $cutNode; TRUE; $i++) {
			if ($n->getNameType() == 'interval') {
				$i--;
				$selectedBranch = $branch;
			} else
				$selectedBranch = 0;
			if ($n->getPredicate() != NULL) 
				$n = $n->getPredicate()[$selectedBranch];
			else
				return $i;
		}
	}
	

} 

/*
Rendering della dimensione
*/

class DimensionRender
{
	private $sqlDimension = NULL;
	
	function __construct ($requestTree)
	{
		$dimensionName = $requestTree->getName();
		
		if (($sons = $requestTree->getPredicate()) == NULL)
			throw new Exception ("Miss parameter for dimension '$dimensionName'");
		
		
		foreach ($sons as $s)
			$this->sqlDimension .= ($this->sqlDimension == NULL ? '(' : ' OR ').
									$this->getDimensionParameter($dimensionName, $s);
		$this->sqlDimension .= ')';
		
		
	}
	
	function getSqlDimension ()
	{
		return $this->sqlDimension;
	}
	
	private function getDimensionParameter ($dimensionName, $node)
	{
		$dimensionStruct = NULL;
		
		$dimensionInfo = OlapQuery::$factConf->getDimensionInfo ($dimensionName);

		
		if ($dimensionInfo['father'] == NULL && $dimensionInfo['sons'] == NULL)
			$dimensionStruct = array (array ('name'=>'flat', 'mapped_name' => NULL, 
																		'value'=>NULL, 'otherValue'=>NULL));
		else {
			$levels = $dimensionInfo['siblings'] ?: $dimensionInfo['sons'];
			$startToMakeDimensionStruct = FALSE;
			foreach ($levels as $level) {
				$levelInfo = OlapQuery::$factConf->getDimensionInfo ($level);
				if ($dimensionName == $levelInfo['name'] || $dimensionName == $levelInfo['father'])
					$startToMakeDimensionStruct = TRUE;
				if ($startToMakeDimensionStruct) {
					$dimensionStruct[] = array ('name'=> $levelInfo ['name'], 
							'mapped_name' => $levelInfo ['mapped_name'], 'value'=>NULL, 'otherValue'=>NULL);
				}
			}
		} 
		
		if ($dimensionStruct == NULL)	
			throw new Exception ("Not found dimension description");

		$isInterval = $this->updateDimensionStruct ($node, $dimensionStruct) == 'interval' ? TRUE : FALSE;
																		
		if ($isInterval == FALSE)
			$parsedDimension = $this->parseDimension($dimensionInfo, $dimensionStruct);
		else {
			$firstParsedDimension = 
					$this->parseDimension($dimensionInfo, $dimensionStruct, 'infEdge');
			$secondParsedDimension = 
					$this->parseDimension($dimensionInfo, $dimensionStruct,	'supEdge');
					
			if ($secondParsedDimension == NULL)
				$parsedDimension = $firstParsedDimension;
			else if ($firstParsedDimension == NULL)
				$parsedDimension = $secondParsedDimension;
			else
				$parsedDimension = $firstParsedDimension." AND ".$secondParsedDimension;
		}

		return $parsedDimension;
	}
	
	private function updateDimensionStruct ($node, &$dimensionStruct, $dimStructIndex=0,
															$setOtherValue=FALSE)
	{
		$retval = 'normal';
		if ($node->getNameType() != 'interval') {
			$dimensionStruct[$dimStructIndex][$setOtherValue == FALSE ? 'value' : 'otherValue'] =
																			$node->getName();
			$dimStructIndex++;
		} else
			$retval = 'interval';
		
		if (($sons = $node->getPredicate()) == NULL)
			return;
		$firstTime = TRUE;
		foreach ($sons as $s) {
			$this->updateDimensionStruct ($s, $dimensionStruct, $dimStructIndex, 
													$firstTime == TRUE ? FALSE : TRUE);
			$firstTime = FALSE;
		}
		
		return $retval;
	}
	
	private function parseDimension($dimensionInfo, $dimensionStruct, $type = NULL)
	{
		if ($type == NULL) {
			$value = 'value';
			$operator = " = ";
		} else if ($type == 'infEdge') {
			$value = 'value';
			$operator = " >= ";
		} else if ($type == 'supEdge') {
			$value = 'otherValue';
			$operator = " <= ";
		}
		
		if ($dimensionInfo['main_name'] == 'date') {
			$day = $this->dimStructSearch ('day', $value, $dimensionStruct);
			$month = $this->dimStructSearch ('month', $value, $dimensionStruct);
			$year = $this->dimStructSearch ('year', $value, $dimensionStruct);
		}
		
		if ($dimensionInfo['main_name'] == 'date' && $day != NULL) {

			$date = new DateTime($year.'-'.$month.'-'.$day);
			return "(year $operator $year AND day = ".($date->format('z')+1).")";
		} else {
			$retValue = NULL;
			$firstTime = TRUE;
			
			foreach ($dimensionStruct as $ds)
				if ($ds['name'] == 'flat') {
					$field = $dimensionInfo['mapped_key'];

					if ($ds[$value] != NULL)
						return "(".$field .$operator.$this->quoteIfString($ds[$value]).")";
					else
						return NULL;
				} else if ($ds[$value] != NULL) {
					$field = $ds['mapped_name'];
															
					$retValue .= ($firstTime == FALSE ? " AND " : "").$field .$operator. $this->quoteIfString($ds[$value]);
					$firstTime = FALSE;
				}
			
			return $retValue ? "(".$retValue.")" : NULL ;
		}
	}
	
	private function dimStructSearch ($name, $value, $dimStruct)
	{
		foreach ($dimStruct as $d)
			if ($d['name'] == $name)
				return $d[$value];
		return NULL;
	}
	
	private function quoteIfString ($val)
	{
		if (is_numeric($val) == TRUE)
			return $val;
		return "'".$val."'";
	}
}

