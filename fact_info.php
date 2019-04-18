<?php

/*
Ogni "fatto" previsto nella olapSettings.json, per poter essere gestito correttamente dalla API,
deve essere corredato da un file con nome <factName>.json, che descrive il modo in cui la API
si deve comportare.
Per esempio si prenda il seguente file relativo alle accessioni eseguite da una sistema bibliotecario

{
  "dimensions": [{           // l'array "dimension" illustra l'elenco delle "dimensioni", ossia delle grandezze che,
								dal putno di vista della raccolta dei dati, fungono da variabili indipendenti
      "name": "docType",	// nome della dimensione
      "label": "tipo di documento"
    },
    {
      "name": "organization_level",  // questa dimensione è gerarchicamente ordinata: ogni organization_level,
										infatti, è composto da consortiaId e libraryId
      "levels": [{
        "name": "consortiaId"
      }, {
        "name": "libraryId"
      }],
      "label": "livello organizzativo",
      "info": "",
      "hierarchies": [{				// la api può ricevere come parametro il tipo di gerarchia da utilizzare.
										in questo caso ce n'è una sola, ma potrebbero essere molteplici
        "name": "cl",
        "order": ["consortiaId", "libraryId"]
      }]
    },
    {
      "name": "date",
      "levels": [{
        "name": "year"
      }, {
        "name": "month"
      }],
      "hierarchies": [{
        "name": "ym",
        "order": ["year", "month"]
      }]
    }
  ],
  "measures": [{				// le "misure" rappresentano le variabili dipendenti, ossia i valori che
									perlopiù, si rappresentano sull'asse delle ordinate di un sistema cartesiano
    "name": "accessions",
    "label": "numero di accessioni",
    "aggregate": "sum"			// vengono qui indicate le operazioni da eseguire sul raggruppamento di valori
									(sum, avg o count)
  }],
  "aggregates": [{
    "name": "sum",
    "label": "somma",
    "function": "sum"
  }, {
    "name": "average",
    "label": "media",
    "function": "avg"
  }, {
    "name": "count",
    "label": "conteggio",
    "function": "count"
  }],
  "mappings": [{				// mappatura che mette in corrispondenza le etichette utilizzate in questo file
									con quelle che provengono dal db OLAP
    "accessions": "accessions.value",
    "target": "accessions.target",
    "docType": "accessions.docType",
    "libraryId": "accessions.libraryId",
    "consortiaId": "accessions.consortiaId",
    "year": "accessions.year",
    "month": "accessions.month"
  }]
}
*/

class FactInfo 
{
	private $factInfo;
	
	function __construct ($factName)
	{
		$factName .= ".json";
		if (($factJsonStr = @file_get_contents ($factName)) === FALSE) 
			throw new Exception('Invalid fact name');

		if (($this->factInfo = json_decode ($factJsonStr)) == NULL) 
			throw new Exception ("Error: not decode $factJsonStr");
	}
	
	function getDimensionInfo ($name, $hierarchieNumber=0)
	{
		$info = array (
			'name' => NULL,
			'main_name' => NULL,
			'attribute_number' => NULL,
			'key' => NULL,
			'mapped_key' => NULL,
			'mapped_name' => NULL,
			'father' => NULL,
			'siblings' => NULL,
			'sons' => NULL
		);
		
		$stop = FALSE;
		foreach ($this->factInfo->dimensions as $dim) {
			$attributeNumber = $this->getDimensionAttributeNumber($name, $dim);
			if ($dim->name == $name || $attributeNumber !== NULL) {
				$info['father'] = NULL;
				$info['name'] = $name;
				$info['main_name'] = $dim->name;
				$info['attribute_number'] = $attributeNumber;
				$info['key'] = $this->getDimensionKey($dim);
				$info['mapped_key'] = $this->getMappedDimension($info['key']);
				$info['label'] = $this->getDimensionLabel($dim);
				$info['mapped_label'] = $this->getMappedDimension($info['label']);
				$info['mapped_name'] = $this->getMappedDimension($info['name']) ?: $info['mapped_key'];
				$info['attributes'] = $this->getDimensionAttributes($dim);
				$stop = TRUE;
			}
		 
			if (isset($dim->levels) && !$stop)
				foreach ($dim->levels as $level) {
					$attributeNumber = $this->getDimensionAttributeNumber($name, $level);
					if ($level->name == $name || $attributeNumber !== NULL) {
						$info['father'] = $dim->name;
						$info['name'] = $name;
						$info['main_name'] = $level->name;
						$info['attribute_number'] = $attributeNumber;
						$info['key'] = $this->getDimensionKey($level);
						$info['mapped_key'] = $this->getMappedDimension($info['key']);
						$info['label'] = $this->getDimensionLabel($level);
						$info['mapped_label'] = $this->getMappedDimension($info['label']);
						$info['mapped_name'] = $this->getMappedDimension($info['name']) ?: $info['mapped_key'];
						$info['attributes'] = $this->getDimensionAttributes($level);
						$stop = TRUE;
					}
				}
				
			if (isset($dim->levels) && $stop)
				foreach ($dim->levels as $level) 
					foreach ($dim->hierarchies[$hierarchieNumber]->order as $o)
						if ($o == $level->name) {

							$info[$info['father'] ? 'siblings' : 'sons']
								[] = $this->getAttributeByNumber ($level, $info['attribute_number']);
						}
				
				
			if ($stop == TRUE)
				break;
		}
		
		if ($info['name'] == NULL)
			throw new Exception ("Not found information about dimension $name");
		return $info;
			
	}
	
	private function getDimensionAttributes ($infoNode)
	{
		if (!isset ($infoNode->attributes))
			return array (
				array ('name' => $infoNode->name, 'mapped_name' => $this->getMappedDimension($infoNode->name))
			);
		$retval = array();
		foreach ($infoNode->attributes as $attribute)
			$retval[] = array ('name' => $attribute, 'mapped_name' => $this->getMappedDimension($attribute));
		return $retval;
		
	}
	
	private function getAttributeByNumber ($infoLevel, $attributeNumber)
	{
		if (isset ($infoLevel->attributes))
			if (isset ($infoLevel->attributes[$attributeNumber]))
				return $infoLevel->attributes[$attributeNumber];
		return $infoLevel->name;
	}
	
	private function getDimensionAttributeNumber ($name, $infoNode)
	{
		if (isset ($infoNode->attributes)) {
			$attributeNumber = 0;
			foreach ($infoNode->attributes as $attribute)
				if ($attribute == $name)
					return $attributeNumber;
				else
					$attributeNumber++;
		}
				
		return NULL;
	}
	
	private function getDimensionKey ($infoNode)
	{
		if (isset ($infoNode->attributes)) {
			foreach ($infoNode->attributes as $attribute) {
				if (substr ($attribute, -count('.key')) == '.key')
					return $attribute;
				
			}
			return $infoNode->attributes[0];
		}
			
		return $infoNode->name;
	}
	
	private function getDimensionLabel ($infoNode)
	{
		if (isset ($infoNode->attributes)) {
			foreach ($infoNode->attributes as $attribute) {
				if (substr ($attribute, -count('.label')) == '.label')
					return $attribute;
				
			}
			return isset($infoNode->attributes[1]) ? $infoNode->attributes[1] : $infoNode->attributes[0];
		}
			
		return $infoNode->name;
	}

	
	private function getMappedDimension ($name)
	{
		if (isset ($this->factInfo->mappings->$name))
			return $this->factInfo->mappings->$name;
		return NULL;
	}
	
	function getMeasuresInfo ()
	{
		$measureTemplate = array (
			'name' => NULL,
			'mapped_name' => NULL,
			'aggregation_function' => NULL,
			'filters' => NULL,
		);
		$measuresInfo = array();
		
		foreach ($this->factInfo->measures as $measure) {
			$measureInfo = $measureTemplate;
			$measureInfo['name'] = $measure->name;
			$measureInfo['mapped_name'] = $this->getMappedDimension($measure->name);
			
			foreach ($this->factInfo->aggregates as $aggregate)
				if ($aggregate->name == $measure->aggregate)
					$measureInfo['aggregation_function'] = $aggregate->function;
				
			if (!$measureInfo['aggregation_function'])
				throw new Exception ("$measure measure has no aggregation_function\n");
			
			if (isset ($measure->filter)) {
				foreach ($measure->filter as $key => $filter) {
					$filterDimension = $this->getMappedDimension($key);
					$filterArgument = $filter;
					$measureInfo['filters'][] = 
								array ("dimension" => $filterDimension, "argument" => $filterArgument);
				}
			}
			
			$measuresInfo[]= $measureInfo;
		}
		
		return $measuresInfo;
	}
}