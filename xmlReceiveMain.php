<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
$logCheck = $_REQUEST['dev'];


 if($logCheck){
	 error_reporting(2);
 } else {
	error_reporting(E_ERROR);
 }
 
ini_set('display_errors', '1');

include 'config.inc.php';
include 'config.service.php';
include 'include/nusoap/nusoap.php';
include_once 'include/utils/CommonUtils.php';
//include 'soap/xmlreceive.php';

function xml_receive($XMLRecFile,$templogname)
{
//    global $adb;
    global $root_directory;

    if (file_exists($XMLRecFile)) 
    {
        $xml = new DOMDocument;
        $xml->preserveWhiteSpace = false;
        $xml->load($XMLRecFile);

        $xpath = new DOMXPath($xml);

        $query = 'collections/*[documenttype]/documenttype';
        $entries = $xpath->query($query, $xml);
        //print_r($entries->length);
        $xmllengthes = $entries->length;
        
        if ($xmllengthes == 0)
        {
            $auditquerydocid = 'collections/auditing/transactionid';
            $auditentriesdocid = $xpath->query($auditquerydocid, $xml);
            foreach ($auditentriesdocid as $entry) {
                $auditdocumentid = $entry->nodeValue;
            }
            $auditquerydiscd = 'collections/auditing/logby';
            $auditentriesdiscd = $xpath->query($auditquerydiscd, $xml);
            foreach ($auditentriesdiscd as $entry) {
                $auditfromid = $entry->nodeValue;
            }
            $auditquerysouapp = 'collections/auditing/sourceapplication';
            $auditentriessouapp = $xpath->query($auditquerysouapp, $xml);
            foreach ($auditentriessouapp as $entry) {
                $auditsourceapplication = $entry->nodeValue;
            }
            $auditquerydesapp = 'collections/auditing/destapplication';
            $auditentriesdesapp = $xpath->query($auditquerydesapp, $xml);
            foreach ($auditentriesdesapp as $entry) {
                $auditdestapplication = $entry->nodeValue;
            }
            $auditquerydocdt = 'collections/auditing/logdate';
            $auditentriesdocdt = $xpath->query($auditquerydocdt, $xml);
            foreach ($auditentriesdocdt as $entry) {
                $auditcreateddate = $entry->nodeValue;
            }
            $auditquerydoctyp = 'collections/auditing/sourcedocument';
            $auditentriesdoctyp = $xpath->query($auditquerydoctyp, $xml);
            foreach ($auditentriesdoctyp as $entry) {
                $auditdocumenttype = $entry->nodeValue;
            }
            $auditqueryrawurl = 'collections/auditing/rawurl';
            $auditentriesrawurl = $xpath->query($auditqueryrawurl, $xml);
            foreach ($auditentriesrawurl as $entry) {
                $auditrawurl = $entry->nodeValue;
            }
            sendreceiveaudit($auditdocumentid, 'Receive', 'Failed', 'Data recived failed (Invalid Username/Password OR User Inactive)', '', $auditfromid, $auditsourceapplication, $auditcreateddate, $auditdocumenttype, $auditrawurl, $auditdestapplication);
            $insertstatus = 103;
            $Resulrpatth = $templogname;
            $fpx = fopen($Resulrpatth, 'w');
            fwrite($fpx, $insertstatus."\n");
            fclose($fpx);
        }
        
        foreach ($entries as $entry) {
            $documenttype = $entry->nodeValue;
            //echo $documenttype;

            $inserted = getObjFrXML($documenttype,$xml,$xpath,'',$templogname);
        }
    }
//        $fpx = fopen('C:\wamp\www\receive.txt', 'w');
//        fwrite($fpx, $inserted);
//        fclose($fpx);   
    return $inserted;
}

global $client,$site_URL,$root_directory,$adb;

/* ----------- $root_directory URL value change based on Configuration ------------ */

    $result = $adb->pquery("SELECT id,`key` as lablename,`value` as lablevalue,treatment FROM sify_inv_mgt_config WHERE treatment='serviceUrl' AND `key`='LOGFILEARCHIVE'", array());
	$tags = $newarry = array();
    if(!empty($result->fields['lablevalue'])){
       $root_directory = '';  
		   $root_directory = $result->fields['lablevalue'];
		   if(!empty($root_directory)){
				$newarry = $tags = explode('/' ,rtrim($root_directory,'/'));      
				$mkDir = "";
				$countlen =count($tags);
				$corname = end($newarry);
				array_pop($newarry);
				$logflname = end($newarry);
				$folderarray = array();
				$folderarray[] = implode('/',$newarry);
				$folderarray[] = $corname;
				foreach($folderarray as $folder) {   
					$mkDir = $mkDir . rtrim($folder, '/') ."/"; 
					if(!is_dir($mkDir)) {           
					  mkdir($mkDir, 0777);  
						if(is_dir($mkDir)) {           
							chmod($mkDir, 0777);          
						}
					}
				}
			}
    } 

//echo file_get_contents( 'php://input' );
//echo $_SERVER['QUERY_STRING'];

//$client = new soapclient2($site_URL.'vtigerservice.php?service=xmlreceive', FALSE);
//echo "<pre>"; print_r($client);die;

$timestamp = time();
$date = date(Ymd);
$currentdate = date("Ymd_H_i_s_").microtime(true);

$y[$xmlpath] = file_get_contents( 'php://input' );
$Resulrpatth1_dir_log = $root_directory."storage";
if(!is_dir($Resulrpatth1_dir_log))
    mkdir($Resulrpatth1_dir_log, 0777);

if(is_dir($Resulrpatth1_dir_log))
    chmod($Resulrpatth1_dir_log, 0777);

$Resulrpatth1_dir_log = $root_directory."storage/log";
if(!is_dir($Resulrpatth1_dir_log))
    mkdir($Resulrpatth1_dir_log, 0777);

if(is_dir($Resulrpatth1_dir_log))
    chmod($Resulrpatth1_dir_log, 0777);

$Resulrpatth1_dir = $root_directory."storage/log/receivexml";
if(!is_dir($Resulrpatth1_dir))
    mkdir($Resulrpatth1_dir, 0777);

if(is_dir($Resulrpatth1_dir))
    chmod($Resulrpatth1_dir, 0777);

$successpath_dir = $root_directory."storage/log/receivexml/xmlsuccess";
if(!is_dir($successpath_dir))
    mkdir($successpath_dir, 0777);

if(is_dir($successpath_dir))
    chmod($successpath_dir, 0777);

$xmlfails_dir = $root_directory."storage/log/receivexml/xmlfails";
if(!is_dir($xmlfails_dir))
    mkdir($xmlfails_dir, 0777);

if(is_dir($xmlfails_dir))
    chmod($xmlfails_dir, 0777);

    $Resulrpatth1 = $root_directory."storage/log/receivexml/$currentdate.xml";
    $successpath = $root_directory."storage/log/receivexml/xmlsuccess/$currentdate.xml";
    $xmlfails = $root_directory."storage/log/receivexml/xmlfails/$currentdate.xml";
                   
    $fpx = fopen($Resulrpatth1, 'w');
    fwrite($fpx, $y[$xmlpath]."\n");
    fclose($fpx);
//$x[$xmlpath] = 'C:\wamp\www\INTE0002\xProduct0.xml';
//$x[$xmlpath] = 'C:\wamp\www\INTE0002\PurchaseInvoice0.xml';
//$x[$xmlpath] = 'C:\wamp\www\INTE0002\audit.xml';
$x[$xmlpath] = $Resulrpatth1;

$Resulrpatth = $root_directory."storage/receiveresult_".$currentdate.".txt";

//$x['templogname']=$Resulrpatth;
$params = Array('XMLRecFile'=>$Resulrpatth1,'templogname'=>$Resulrpatth);
//$snxmlreceive = $client->call('xml_receive',$params);
//file_put_contents($Resulrpatth,PHP_EOL."Before Call".PHP_EOL,FILE_APPEND);
xml_receive($Resulrpatth1, $Resulrpatth);
//file_put_contents($Resulrpatth,PHP_EOL."After Call".PHP_EOL,FILE_APPEND);

$file = fopen($Resulrpatth, "r");

$errorval = 0;
while(!feof($file))
{
    $filval = fgets($file);
    if ($filval == 200)
    {
        $errorval = 1;
        brack;
    }
    elseif ($filval == 100 || $filval == 101 || $filval == 102 || $filval == 103) 
    {
        $errorval = 0;
    }
}
if ($errorval == 1)
{
    header('HTTP/1.1 200 OK');
   //echo "200";
    copy($Resulrpatth1, $successpath);
    unlink($Resulrpatth1);
}
else
{
    header('HTTP/1.1 406 Not Acceptable');
   //echo "406";
    copy($Resulrpatth1, $xmlfails);
    unlink($Resulrpatth1);
}

fclose($file);
unlink($Resulrpatth);
unlink($Resulrpatth1);
?>
