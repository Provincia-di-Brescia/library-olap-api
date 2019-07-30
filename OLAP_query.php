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
				$measuresInfo = self::$factConf->getMeasuresInfo();
				self::$sqlStatement = new SqlStatement (self::$factConf);
				
				if ($measuresInfo)
					self::$sqlStatement->setFromStatement ($requestTree->getName());
			
				$this->returningType = 'sql';
							
				foreach ($requestTree->getPredicate() as $p) 	
					if ($p->getNameType() == 'function') {
						$fr = new FunctionRender($p);
						if ($fr->getFunctionType() == 'dimensions') {
							$this->returningType = 'json';
							$this->returningJson = $this->getFactInformations($requestTree->getName(), 'dimensions');
							return;
						} else if ($fr->getFunctionType() == 'measures') {
							$this->returningType = 'json';
							$this->returningJson = $this->getFactInformations($requestTree->getName(), 'measures');
							return;
						}
					}

				
//				$measuresInfo = $this->getCorrectedMeasuresInfo($measuresInfo);
				self::$sqlStatement->setMeasuresInSelectExpr ($measuresInfo);
				$this->returningSql = self::$sqlStatement->getSqlStatement();
										
			} else if ($requestTree->getNameType() == 'command' && $requestTree->getName() == 'fact_tables') {
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

	

/*	private function adjustRequestTree($timeScope)
	{
		echo "adjustrequest {$timeScope}\n";
		$measuresInfo = OlapQuery::$factConf->getMeasuresInfo();
		
	}

	private function getCorrectedMeasuresInfo($measuresInfo)
	{
		$setCorrectScopeDate = function ($node) {
			$dateArr = array (
				array ('scope' => 'year', 'value' => NULL),
				array ('scope' => 'month', 'value' => NULL),
				array ('scope' => 'day', 'value' => NULL)
			);
			$index = 0;
			do {
				$dateArr[$index]['value'] = $node->getName();
				$index++;
				$node = $node->getPredicate()[0];
			} while ($node);
			$date = date_create (
				($dateArr[0]['value']).
				'-'.
				($dateArr[1]['value'] ?: '12').
				'-'.
				($dateArr[2]['value'] ?: '31')
			);
			
			$crtlFuncs = array (
				array ('scope' => 'day', 'func' => function ($date) {return $date;}),
				array ('scope' => 'week', 'func' => function ($date) {
											date_sub($date, date_interval_create_from_date_string('6 days'));
											return $date;
				}),
				array ('scope' => 'month', 'func' => function ($date) {
					date_sub($date, date_interval_create_from_date_string('1 month'));
					return $date;
				}),
				array ('scope' => 'year', 'func' => function ($date) {
					date_sub($date, date_interval_create_from_date_string('1 years'));
					return $date;
				})
			);

			$cronTime = self::$factConf->getCronTime ();
			foreach ($crtlFuncs as $cf)
				if ($cf['scope'] == $cronTime)
					return $cf['func']($date);

			throw new Exception ("Invalid time	 scope");

		};	
		foreach ($measuresInfo as &$m) {
			if ($m['aggregation_function'] == 'last') {
				$m['aggregation_function'] = 'sum';
				$dateNode = &self::$rootNode->getNodeByName (self::$rootNode, ['date']);
				if ($dateNode)
					$intervalNode = &$dateNode->getPredicate()[0];
				else
					return $measuresInfo;
				if ($intervalNode->getNameType() == 'interval') {
					$newDate = $setCorrectScopeDate($intervalNode->getPredicate()[1]);
					$arrDate = [$newDate->format('Y'), $newDate->format('m'), $newDate->format('d')];
					$nodeToChange = &$intervalNode->getPredicate()[0];
					foreach ($arrDate as $ad) {
						$nodeToChange->setName($ad);
						if ($nodeToChange->getPredicate())
							$nodeToChange = &$nodeToChange->getPredicate()[0];
						else
							break;
					}
				}
			}
		}

		return $measuresInfo;	
		
	} */

	private function getFactsInformations ()
	{
		$ret = array ();
		
		$settings = json_decode (file_get_contents ('config.json'));
		$olapSettings = json_decode (file_get_contents ($settings->olapSettingsFile));
		
		foreach ($olapSettings->fact_tables as $f) {
			$isEnabled = FALSE;
			foreach ($f->fact_queries as $fq)
				if ($fq->enable)
					$isEnabled = TRUE;
			if (!$isEnabled)
				continue;
			unset ($f->fact_queries);
			$ret[] = $f;
		}
		
		if ($ret == NULL)
			throw new Exception ("Not found configuration files");
		return $ret;
			
	}

	private function getFactInformations ($fact, $type)
	{
		$factSettings = json_decode (file_get_contents($fact.'.json'));
		
		return $type == 'dimensions' ? $factSettings->dimensions : $factSettings->measures;
			
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

				$measuresInfo = OlapQuery::$factConf->getMeasuresInfo();
				foreach ($measuresInfo as $mi)
					if ($mi['type'] == 'not_additive') {
						$this->addDrilldown();
				}

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

	private function addDrilldown()
	{
		$printParsedUri = function ($uri, $indentation = 0)
		{
			$tabs = str_repeat ('    ', $indentation);
			
			echo $tabs."nameType: ".$uri->getNameType()."\n";
			echo $tabs."name: ".$uri->getName()."\n\n";
			
			$sons = $uri->getPredicate();	
			if ($sons != NULL)
				foreach ($sons as $s)
					printParsedUri ($s, $indentation+1);
			
		};
		$drilldownNode = OlapQuery::$rootNode->getNodeByName (OlapQuery::$rootNode, ['drilldown', 'date']);
		if (!$drilldownNode) {
			$aggregateNode = &OlapQuery::$rootNode->getNodeByName (OlapQuery::$rootNode, ['aggregate']);
			if (!$aggregateNode)
				throw new Exception ("Try to add drilldown node but not find aggregate node");
			//$aggregateNode->setName('pipp');
			$toAdd = array(
				array ('nameType' => 'parameterType', 'name' => 'drilldown'),
				array ('nameType' => 'dimension', 'name' => 'date'),
				array ('nameType' => 'memberValue', 'name' => 'day')
			);
			foreach ($toAdd as $ta)
				$aggregateNode = OlapQuery::$rootNode->addNode ($aggregateNode, $ta['nameType'], $ta['name']);
			// $printParsedUri (self::$rootNode);
		}
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
					if ($s->getNameType() == 'dimension') { 
						$dr = new DrilldownRender ($s, $branch++);
					}
					

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
				
				/*if ($dimensionInfo['name'] == 'date')
					$drilldownParameter = $this->getCorrectDrillDownParameter($drilldownParameter, $dimensionInfo['sons']); */

				
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
	
	
/*	private function getCorrectDrillDownParameter ($drilldownParameter, $timeVal)
	{
		echo "getCorrect {$drilldownParameter}\n";
		print_r($timeVal);
		$timeScope = ['year', 'month', 'week', 'day'];	
//		$maxTimeScope = OlapQuery::$factConf->getCronTime();

		if (in_array($drilldownParameter, $timeVal))
			return $drilldownParameter;
		
		$getScopeIndex = function ($val) use ($timeScope) {
			$ret = array_search($val, $timeScope);
			if (!$ret)
				throw new Exception ("Invalid scope in drilldown request");
			return $ret;
		};

		for ($drillDownParameterIndex = $getScopeIndex($drilldownParameter)-1; $drillDownParameterIndex >= 0; $drillDownParameterIndex--)
			if (in_array($timeScope[$drillDownParameterIndex], $timeVal))
				return $timeScope[$drillDownParameterIndex];


		$maxIndex = $getScopeIndex($maxTimeScope);
					
		$retval = function ($val) use ($getScopeIndex, $maxIndex, &$timeValIndex, &$retval, $timeVal) {
			$valIndex = $getScopeIndex($val);
			if ($valIndex > $maxIndex) {
				$timeValIndex--;
				return $retval($timeVal[$timeValIndex]);
			} else
				return $timeVal[$timeValIndex];
				
		};
		
		return $retval($drilldownParameter); 
	} */
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
													$firstTime == TRUE && $setOtherValue == FALSE ? FALSE : TRUE);
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
		if ($dimensionInfo['main_name'] == 'date' /* && $day != NULL*/) {

			$date = new DateTime($year.'-'.$month.'-'.$day);
			//return "(year $operator $year AND day = ".($date->format('z')+1).")";
			return "(data $operator '".($date->format('Y-m-d'))."')";
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

