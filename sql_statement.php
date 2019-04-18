<?php

require_once 'fact_info.php';

/*
Fornisce alla OlapQuery gli strumenti per la costruzione della query sql_regcase
*/

class SqlStatement {
	private $selectExpr; 
	private $fromTable;
	private $whereCondition;
	private $groupCondition;
	private $orderByCondition;
	
	
	private $factConf;
	
	function __construct ($factConfiguration)
	{
		$this->factConf = $factConfiguration;
	}
	
	function getSqlStatement()
	{
		return "select ".$this->selectExpr.
				" from ".$this->fromTable.	
				($this->whereCondition != NULL ? " where ".$this->whereCondition : '').
				($this->groupCondition != NULL ? " group by ".$this->groupCondition : '').
				($this->orderByCondition != NULL ? " order by ".$this->orderByCondition : '');
	}
	
	function setFromStatement($s)
	{
		$this->fromTable = $s;
	}
	
	function setWhereStatement ($condition)
	{
		$this->whereCondition .= ($this->whereCondition == NULL ? '' : ' AND ').$condition;
	}
	
	function setGroupStatement ($dimensionStruct)
	{
		$this->setSelectExpr ($dimensionStruct);
		
		foreach ($dimensionStruct as $dimS) {
			$dimKey = $this->factConf->getDimensionInfo($dimS)['key'];
			$dimLabel = $this->factConf->getDimensionInfo($dimS)['label'];
			$dimMainName = $this->factConf->getDimensionInfo($dimS)['main_name'];
			$groupDimensionName = $this->getGroupedLabel ($dimMainName, $dimKey);
			$orderByDimensionName = $this->getGroupedLabel ($dimMainName, $dimLabel);
			$this->groupCondition .=  ($this->groupCondition ? ', ' : '' ).$groupDimensionName;
			$this->orderByCondition .=  ($this->orderByCondition ? ', ' : '' ).$orderByDimensionName;
		}

	}
	
	private function setSelectExpr ($dimensionStruct)
	{
		foreach ($dimensionStruct as $dim)
			$this->setAttributesInSelectExpr ($dim);
			
			
	}
	
	private function setAttributesInSelectExpr ($dimensionName)
	{
		$dimensionAttributes = $this->factConf->getDimensionInfo($dimensionName)['attributes'];
	
		
		$dimensionIsDate = FALSE;
		if ($this->factConf->getDimensionInfo($dimensionName)['father'] == 'date') {
			$this->addDateSpec($dimensionAttributes);
			$dimensionIsDate = TRUE;
		}

		if ($dimensionMainName = $this->factConf->getDimensionInfo($dimensionName)['main_name'])
			$groupStr = $dimensionMainName;
		else
			$groupStr = NULL;
		$tmp = NULL;
		foreach ($dimensionAttributes as $dimensionAttribute)
			$tmp .= ($dimensionIsDate ? $dimensionAttribute['date_spec'] : $dimensionAttribute['mapped_name']).
							" as ".$this->getGroupedLabel ($groupStr, $dimensionAttribute['name']).", ";

		
		$this->selectExpr = $tmp.$this->selectExpr;
	}
	
	private function addDateSpec(&$dimensionAttributes)
	{
		foreach ($dimensionAttributes as &$dimensionAttribute)
			switch ($dimensionAttribute['name']) {
				case 'year':
					$dimensionAttribute['date_spec'] = "date(LAST_DAY(concat(year,'-','12-01')))";
					break;
				case 'month':
					$dimensionAttribute['date_spec'] = "date(LAST_DAY(concat(year,'-',month,'-01')))";
					break;
				case 'day':
					$dimensionAttribute['date_spec'] = "date(concat(year,'-',month,'-', day(DATE_ADD(concat(year, '-01-01'), INTERVAL day-1 DAY))))";
					break;
				default:
					throw new Exception ("Invalid date struct");
			}
	}
	

	
	function setMeasuresInSelectExpr ($measuresInfo)
	{
		$subStr = NULL;
		foreach ($measuresInfo as $measureInfo) {
			$measureName = $measureInfo['name'];
			$subStr .= ($subStr != NULL ? ', ' : ' ').$measureInfo['aggregation_function'];
			
			$subStr .= "(";
			
			if (isset ($measureInfo['filters']))
				foreach ($measureInfo['filters'] as $filter)
					$subStr .= $this->getFilteredMeasure ($measureInfo['mapped_name'], $filter);
			else
				$subStr .= " ".$measureInfo['mapped_name']." ";
			
			$subStr .= ")";
			$subStr .= ' as ' .$this->getGroupedLabel ('measures', $measureName);
			
		}
		
		$this->selectExpr .= $subStr;
	} 
	
	private function getFilteredMeasure ($measureName, $filterInfo)
	{
		$tmp = $filterInfo['dimension'].' REGEXP "'.$filterInfo['argument'].'"';
			
		return "IF ($tmp, $measureName, 0)";
	}
	
	private function getGroupedLabel ($group, $label)
	{
		if ($group)
			return '`__'.$group.'__'.$label.'`';
		return $label;
	}
}