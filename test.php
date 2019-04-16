<?php

header('Content-type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

//$uri = "/olap/fact_tables";
//$uri = "/olap/loans/dimensions";
//$uri = "/olap/test_external_script/dimensions";
//$uri = "/olap/test_external_script/aggregate?cut=CAP:25100";
//$uri = "/olap/errore/dimensions";
//$uri = "/olap/loans/measures";

$uri = "/olap/loans/aggregate?cut=consortia.label:Area di Dalmine";
$uri = "/olap/loans/aggregate?cut=consortia.label:Area di Dalmine,Dalmine";
$uri = "/olap/loans/aggregate?cut=consortia:ad"; 
$uri = "/olap/loans/aggregate";
$uri = "olap/loans/aggregate?cut=library:23;50";
$uri = "olap/loans/aggregate?cut=library.label:madone;mapello";
$uri = "olap/loans/aggregate?cut=date:2017,8,3";
$uri = "olap/loans/aggregate?cut=date:2017,8";
$uri = "olap/loans/aggregate?cut=date:2017";

$uri = "olap/loans/aggregate?cut=date:2017,10|consortia:ad";
$uri = "olap/loans/aggregate?cut=library:1-120";	
$uri = "olap/loans/aggregate?cut=library:-11";	
$uri = "olap/loans/aggregate?cut=library.id:11-";	
$uri = "olap/loans/aggregate?cut=library:1-120|library.label:madone;mapello";	
$uri = "olap/loans/aggregate?cut=library:1-120|library.label:madone;mapello|date:2017;2018";	

$uri = "olap/loans/aggregate?cut=date:2017,10&drilldown=date";
$uri = "/olap/loans/aggregate?drilldown=date";
$uri = "/olap/loans/aggregate?cut=date:2017&drilldown=date";
$uri= "/olap/loans/aggregate?cut=date:2017&drilldown=date:day";
$uri = "/olap/loans/aggregate?cut=date:2017&drilldown=date|organization_level";

$uri="/olap/loans/aggregate?cut=date:2016,10-2017,02&drilldown=date:year|date:month";
$uri="/olap/loans/aggregate?cut=date:2016,10-2017,02&drilldown=date:year";
$uri="/olap/loans/aggregate?cut=date:2016,10-2017,02&drilldown=date";
$uri="/olap/loans/aggregate?cut=date:2016,10-2017&drilldown=date";
// $uri = "olap/loans/aggregate?cut=date:2017,10,7|library:10-12&drilldown=organization_level";
// $uri = "olap/loans/aggregate?cut=date:2017,10,7|library:10-12&drilldown=organization_level:consortia";
// $uri = "olap/loans/aggregate?cut=date:2017,10,7|library:10-12&drilldown=organization_level:consortia|organization_level:library";
// $uri = "olap/loans/aggregate?cut=date:2017,10,7|library:10-12&drilldown=organization_level:library.label";
// $uri = "olap/loans/aggregate?cut=date:2017,10,7|library:10-12&drilldown=date|organization_level:library.label";
// $uri = "olap/loans/aggregate?cut=date:2017|library:10-12&drilldown=date|organization_level:library.label";
// $uri = "olap/loans/aggregate?cut=date:2017,10,7&drilldown=date";




require_once 'uri_parser.php';
require_once 'OLAP_query.php';
require_once 'api.php';
require_once 'group_rows.php';

$requestUri = substr ( $uri , strpos ( $uri , '/' , 1 ));
$requestUri = trim ($requestUri, '/');
//print_r ($requestUri);

echo "requestUri: $requestUri\n\n";
$parsedUri = new UriParser ($requestUri);

printParsedUri ($parsedUri);

$queryElements = new OlapQuery ($parsedUri);

$type = $queryElements->getType();
$value = $queryElements->getValue();
echo "sql is \n$value\n";



switch ($type) {
	case 'json':
		echo json_encode ($value);
		break;
	case 'error':
		$value = ['error', $value];
		echo json_encode ($value);
		break;
	default:
		echo "This is default\n";
		$api = new Api ($value);
		echo "This is getType: ".$api->getType()."\n";
		if ($api->getType() == 'error') {
			echo "This is an error\n";
			$value = ['error', $api->getValue()[0]];
		} else {
			$value = $api->getValue();
			$grouping = new GroupRows ($value);
			$value = $grouping->getValues();
		}
		echo json_encode ($value);
		
}
echo "\n";





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

class Test {
	public static $var = 'pippo';
}

class Test1 {
	function __construct ()
	{
		print_r (Test::$var);
		echo Test::$var."\n\n\n";
		
	}
}