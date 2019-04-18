<?php

/* viene eseguito dal web server (apache) e si incarica di istanziare la classe UriParser per
la decifrazione dei parametri passati dalla GET dell'http. Una volta eseguito il parsing della url
istanzia la classe OlapQuery che, a partire dalle istruzioni ricavate dalla url, predispone la query
sql da utilizzare per interrogare l'olap. Per finire, l'esecuzione della vera e propria lettura del db olap
avviene attraverso la classe Api che, ottenuti i record, li raggrupperà utilizzando la classe GroupRows
Riassumendo 
							url
							|
							UriParser esegue ill parsing dell'url ricavandone l'albero logico dei componenti
							|
							OlapQuery traduce l'albero in una query sql
							|
							Api esegue la query sul database Olap
							|
							GroupRows esegue gli eventuali raggruppamenti necessari (drilldown)
*/

header('Content-type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once 'uri_parser.php';
require_once 'OLAP_query.php';
require_once 'api.php';
require_once 'group_rows.php';

$requestUri = $_SERVER['REQUEST_URI'];
$requestUri = substr ( $requestUri , strpos ( $requestUri , '/' , 1 ));
$requestUri = urldecode(trim ($requestUri, '/'));

$parsedUri = new UriParser ($requestUri);
//print_r($parsedUri);

$queryElements = new OlapQuery ($parsedUri);
// print_r ($queryElements);

$type = $queryElements->getType();
$value = $queryElements->getValue();

switch ($type) {
	case 'json':
		echo json_encode ($value);
		break;
	case 'error':
		$value = ['error', $value];
		echo json_encode ($value);
		break;
	default:
	//	echo "passed to api: $value\n";
		$api = new Api ($value);
		if ($api->getType() == 'error')
			$value = ['error', $api->getValue()[0]];
		else {
			$value = $api->getValue();
			$t = new GroupRows ($value);
			$value = $t->getValues();
		}
		echo json_encode ($value);
}

function printParsedUri ($uri, $indentation = 0)
{
	$tabs = str_repeat ('    ', $indentation);

	echo $tabs."nameType: ".$uri->getNameType()."\n";
	echo $tabs."name: ".$uri->getName()."\n\n";

	$sons = $uri->getPredicate();
	if ($sons != NULL)
		foreach ($sons as $s)
			printParsedUri ($s, $indentation+1);

}
