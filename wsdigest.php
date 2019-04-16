<?php

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