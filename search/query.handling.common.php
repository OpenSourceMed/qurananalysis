<?php



$lang = "EN";
$direction = "ltr";



$query = $_GET['q'];

$script = $_GET['script'];

if ( empty($script))
{
	$script = "simple";
}



///DETEDCT LANGUAGE //LOCATION SIGNIFICANT
if (isArabicString($query))
{
	$lang = "AR";
	$direction = "rtl";
}



if ($lang=="EN" && !isDevEnviroment() )
{
	file_put_contents( dirname(__FILE__)."/../data/query.log.en", time().",".$query."\n",FILE_APPEND);
}
else if ($lang=="AR" && !isDevEnviroment())
{
	file_put_contents( dirname(__FILE__)."/../data/query.log.ar",  time().",".$query."\n",FILE_APPEND);
}


	//echoN(time());
	loadModels("core,search,qac,ontology",$lang);
	//echoN(time());
	
	
//preprint_r($MODEL_CORE);

//echoN($query);exit;

$MODEL_CORE_UTH = loadUthmaniDataModel();

//$TRANSLATION_MAP_EN_TO_AR = apc_fetch("WORDS_TRANSLATIONS_EN_AR");

//preprint_r($TRANSLATION_MAP_EN_TO_AR);

$isPhraseSearch = false;
$isQuestion = false;
$isColumnSearch = false;
$columnSearchType = null;
$columnSearchKeyValParams = null;

$matchesCount = preg_match("/\".*?\"/", $query);

if ( $matchesCount >=1 ) $isPhraseSearch = true;




if ( !$isPhraseSearch && preg_match("/\?|؟/", $query)>0  )
{
	$isQuestion = true;
	$query = preg_replace("/\?|؟/", "", $query);
}

if ( !$isPhraseSearch && containsQuestionWords($query,$lang))
{
	$isQuestion = true;
}


if ( !$isPhraseSearch && !$isQuestion && strpos($query,":")!==false)
{
	$isColumnSearch = true;
	
	$columnSearchArr = explode(":",$query);

	
	if (is_numeric ($columnSearchArr[0]) && is_numeric ($columnSearchArr[1]))
	{
		$columnSearchType = "VERSE";
		
		//CHAPTER
		$columnSearchKeyValParams['KEY'] = $columnSearchArr[0];
		//VERSE
		$columnSearchKeyValParams['VAL'] = $columnSearchArr[1];
	}
}

//preprint_r($columnSearchKeyValParams);
//echoN("IS QUESTION:$isQuestion");


/// CLEANING
$query = cleanAndTrim($query);



//$query = removeTashkeel($query);

//  remove tashkeel - convert from uthmani to simple
// didn't use remove tashkeel since it leaves "hamzet el wasl" which is not in the simple text
$query = shallowUthmaniToSimpleConversion($query);



// CASE HANDLING
if ($lang=="EN" )
{
	$query = strtolower($query);
}






if ( $isQuestion && !$isPhraseSearch)
{

	$taggedSignificantWords = posTagUserQuery($query,$lang);

	$taggedSignificantWords = extendQueryWordsByDerivations($taggedSignificantWords,$lang);
	
	//preprint_r($taggedSignificantWords);exit;
	$queryWordsArr = array_keys($taggedSignificantWords);
}
else
{
	$queryWordsArr = preg_split("/ /",$query);

	//consider the full query as oen word, needed for Qurana pronouns
	if ($isPhraseSearch)
	{
		$queryWordsArr[]=$query;
	}
	
	
}


if ( !$isColumnSearch  )
{
	$conceptsFromTaxRelations = extendQueryWordsByConceptTaxRelations($queryWordsArr, $lang);
	
	
	$queryWordsArr  = array_merge($queryWordsArr,$conceptsFromTaxRelations);
}

//echoN($query);preprint_r($queryWordsArr);exit;

//////////////
$scoringTable = array();

$lastWord = null;

$extendedQueryWordsArr = array_fill_keys($queryWordsArr,1);


// IF NOT PHRASE OF QUESTION SEARCH, EXTEND QUERY BY ADDING DERVIATION OF THE QUERY WORDS
if ( $lang=="AR" && $isPhraseSearch==false && $isQuestion==false && !$isColumnSearch)
{

	$extendedQueryWordsArr = extendQueryByExtractingWordDerviations($extendedQueryWordsArr);
}



// SEARCH INVERTED INDEX FOR DOCUMENTS
$scoringTable = getScoredDocumentsFromInveretdIndex($extendedQueryWordsArr,$query,$isPhraseSearch,$isQuestion,$isColumnSearch,$columnSearchKeyValParams);


// NOT RESULTS FOUND
handleEmptyResults($scoringTable,$extendedQueryWordsArr,$query);


///// GET STATS BYT SCORING TABLE

$resultStatsArr = getStatsByScoringTable($scoringTable);



?>