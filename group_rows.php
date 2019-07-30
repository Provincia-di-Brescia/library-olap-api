<?php

/*
Predispone il raggruppamento dei dati in modo tale che sia compatibile con quanto atteso come
output della api.
In buona sostanza esegue il "pivoting" del dataset ottenuto come input
*/

class GroupRows
{
	private $groupedRows = array();
	private $isEmptyDataSet = FALSE;
	
	
	function __construct ($rows)
	{
		if (count($rows) == 0) {
			$this->isEmptyDataSet = TRUE;
			return;
		}
	
		$groupsTemplate = array();
		foreach ($rows[0] as $key => $field) {
			$groupName = $this->getGroupAndLabel($key)['group'];
			$this->addGroup($groupName, $groupsTemplate);
		}

		foreach ($rows as $row)	{
			$template = $groupsTemplate;
			foreach ($row as $key => $value) {
				$group = $this->getGroupAndLabel($key)['group'];
				$label = $this->getGroupAndLabel($key)['label'];
				$this->fillGroupsTemplate ($group, $label, $value, $template);
			}
			$this->groupedRows[] = $template;
		}
	}
	
	function getValues ()
	{
		if ($this->isEmptyDataSet) {
			return ['empty_dataset' => TRUE];
		}
		if (count($this->groupedRows) == 1)
			return $this->groupedRows[0];
		return $this->groupedRows;
	}
	
	private function fillGroupsTemplate ($group, $label, $value, &$groupsTemplate)
	{
		if (is_array($groupsTemplate[$group]))
			$groupsTemplate[$group][$label] = $value;
		else {
			$groupsTemplate[$group] = $value;
		}
			
	}
	
	private function getGroupAndLabel ($field)
	{
		$retval = explode ( '__' , $field);
		if (count($retval) != 3)
			throw new Exception ("Error: try to grouping $field field");
		return array ('group' => $retval[1], 'label' => $retval[2]);
	}
	
	private function addGroup ($groupName, &$group)
	{
		foreach ($group as $key => &$groupedRow) {
			if (is_array($groupedRow)) {
				if ($key == $groupName)
					return;
			} else if ($key == $groupName) {
				$groupedRow = array();
				return;
			}
		}
			
		$group[$groupName] = NULL;
	}
}