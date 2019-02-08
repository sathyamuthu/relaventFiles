<?php
require_once('include/logging.php');
require_once('include/database/PearDatabase.php');
require_once('include/WorkflowBase.php');
require_once("modules/xDefinePriceEffective/price_calculation.php");
require_once('user_privileges/default_module_view.php');
        
include_once('include/stockFunctions.php');
include_once('include/workflowFunctions.php');        
include_once('include/serialFunctions.php');        
include_once('include/serialValidation.php'); 
include_once('include/versionFunctions.php');       
include_once('include/ssfConfigUpdate.php');       
include_once('include/configuration.php');
require_once('include/dataimport.php');
require_once('include/nusoap/nusoap.php');
//PaymentDetail Module for manual Key generation
require_once('include/esnecill_snoitcnuf.php');

//require_once 'config.decimal.php';
require_once('config.fifo.php');

require_once('config.masters.php');
include_once ("modules/xScheme/applySchmeForProduct_Bulkorder.php");

global $LBL_QUANTITY_DECIMAL;
global $LBL_CURRENCY_DECIMAL;

        function numberformat($number, $decimals = 0)
        {
            return number_format($number, $decimals, ".", "");
        }
        
	function getDistributorMapping() {
		global $adb;
		$query = "SELECT mt.*,ct.crmid,ct.deleted FROM vtiger_xdistributorusermappingcf mt 
LEFT JOIN vtiger_crmentity ct ON mt.xdistributorusermappingid=ct.crmid WHERE 
ct.deleted=0 AND mt.cf_xdistributorusermapping_supporting_staff=".$_SESSION["authenticated_user_id"];
		$result = $adb->pquery($query);
		$num = $adb->num_rows($result);
		return $num;
                                
	}
	function getConversionValuesForPR($product_id, $product_series_no, $transaction_series_no) {
		global $adb;
		$trans_where = (!empty($transaction_series_no)) ? " AND picf.cf_purchaseinvoice_transaction_number = '$transaction_series_no'" : '';	
		$result = $adb->pquery(" SELECT  pi.vendorid, pi.pi_godown, ser.distributorid, ser.serialnumber as serial_num FROM vtiger_xtransaction_serialinfo ser 
							     LEFT JOIN vtiger_xtransaction_batchinfo bat ON bat.id = ser.batch_id
							     LEFT JOIN vtiger_piproductrel prorel ON prorel.productid = ser.product_id AND bat.product_id = prorel.productid
							     LEFT JOIN vtiger_xproduct pro ON pro.xproductid = ser.product_id AND bat.product_id = pro.xproductid
								 LEFT JOIN vtiger_xproductcf procf ON procf.xproductid = pro.xproductid AND bat.product_id = procf.xproductid
								 LEFT JOIN vtiger_uom uom ON uom.uomid=procf.cf_xproduct_base_uom	
								 LEFT JOIN vtiger_purchaseinvoice pi ON pi.purchaseinvoiceid = prorel.id AND bat.transaction_id = pi.purchaseinvoiceid
			 					 LEFT JOIN vtiger_purchaseinvoicecf picf ON picf.purchaseinvoiceid = pi.purchaseinvoiceid
			 					 LEFT JOIN vtiger_crmentity crm ON crm.crmid = pi.purchaseinvoiceid
							     WHERE pi.status = 'Created' AND ser.transaction_type = 'PI' 
								 AND pro.xproductid = '$product_id' AND ser.serialnumber = '$product_series_no' $trans_where AND crm.deleted = 0 
								 GROUP BY pro.xproductid ");	
			$result_row = $adb->fetchByAssoc($result);				   
			if($result_row) {
				return $result_row;
			} else {
				return false;
			}	
	}
	function getDefaultChannelHierarchy() {
		global $adb, $DEFAULT_CHANNEL_TYPE, $DEFAULT_CUSTOMER_TYPE;
		$customerType = (!empty($DEFAULT_CUSTOMER_TYPE)) ? $DEFAULT_CUSTOMER_TYPE : 'All'; 
		$customerType = "'" . implode("','", explode('@', $customerType)) . "'";
		$where = '';
		if (stripos($customerType,'All') === false) {
			$where = "AND ch.customer_type IN ($customerType)";
		}
		if(!empty($DEFAULT_CHANNEL_TYPE)) {
			$result = $adb->pquery("SELECT ch.* FROM vtiger_xchannelhierarchy ch INNER JOIN vtiger_crmentity crm ON ch.xchannelhierarchyid = crm.crmid WHERE ch.channelhierarchycode = '$DEFAULT_CHANNEL_TYPE' $where AND crm.deleted=0");
			$result_row = $adb->fetch_row($result);
			if($result_row) {
				$checkChannelHierarchy = checkDefaultChannelHierarchy($result_row['xchannelhierarchyid']);
				if($checkChannelHierarchy) {
					return $result_row;
				} else {
					return false;
				}	
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	function getWarrantyGroupName($value) {
		global $adb;
		if(!empty($value)) {
			$query = $adb->pquery("SELECT warranty_policy_group_name FROM vtiger_xwarrantypolicygrouping WHERE xwarrantypolicygroupingid = ?", array($value));
			$warranty_policy_group_name = $adb->query_result($query, 0 , 'warranty_policy_group_name');	
			$warranty_policy_group_link = "<a href='index.php?module=xWarrantyPolicyGrouping&action=DetailView&"."record=$value' title='Warranty Policy Mapping'>$warranty_policy_group_name</a>";
			return $warranty_policy_group_link;
		} else {
			return false;
		}	
	}
	function checkApproveProcess($modName,$transID) {
		global $adb;
		if($modName == 'xUniversal') { 
			$query = $adb->mquery("SELECT vtiger_attachments.path,vtiger_attachments.name FROM vtiger_xuniversal 
									LEFT JOIN vtiger_xuniversalattachmentsrel ON vtiger_xuniversalattachmentsrel.xuid = vtiger_xuniversal.xuniversalid 
									INNER JOIN vtiger_attachments ON vtiger_attachments.attachmentsid = vtiger_xuniversalattachmentsrel.attachmentsid 
									WHERE vtiger_xuniversal.imagename = vtiger_attachments.name AND xuniversalid = ? ", array($transID));
			$result_row = $adb->fetchByAssoc($query);		
		}
		if(!empty($result_row)) {
			return array(0=>TRUE);
		} else {
			return array(0=>FALSE,1=>'IMAGE_NOT_FOUND');
		}
	}
	function updatePeningItems($modName,$transID) {
		global $adb;
		if($modName == 'xSchemePoints') {
			/* get current retailer */
			$sp_query = $adb->mquery("SELECT xretailerid FROM vtiger_xschemepoints WHERE xschemepointsid=? AND status=?", array($transID,'Created'));	
			$sp_result = $adb->fetchByAssoc($sp_query);
			if(!empty($sp_result)) {
				/* get pre balance for retailer */
				$retailer_query = $adb->pquery("SELECT IFNULL(vtiger_xschemepoints.opening_balance,0.0)+(IFNULL(vtiger_xschemepoints.earned,0.0)-IFNULL(vtiger_xschemepoints.claimed,0.0)) as prev_points FROM vtiger_xschemepoints INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xschemepoints.xschemepointsid WHERE vtiger_crmentity.deleted=0 AND vtiger_xschemepoints.xretailerid=? AND vtiger_xschemepoints.status=? ORDER BY vtiger_crmentity.modifiedtime DESC LIMIT 0,1", array($sp_result['xretailerid'],'Published'));	
				$retailer_result = $adb->fetchByAssoc($retailer_query);
				if(!empty($retailer_result)) {
					/* update new balance for current transaction */
					$update = $adb->pquery("UPDATE vtiger_xschemepoints sp JOIN vtiger_crmentity crm ON sp.xschemepointsid = crm.crmid SET sp.opening_balance = ?, crm.modifiedtime = ? WHERE sp.xschemepointsid = ? AND crm.crmid = ? ", array($retailer_result['prev_points'], date('Y-m-d H:i:s'), $transID, $transID));	
				}
			}
		}
		return array(0=>TRUE);
	}
	function getModuleEntityField($module,$transID){
		global $adb;
		/* get entity field name and table */
		$result = $adb->pquery(" SELECT fieldname,tablename,entityidfield FROM vtiger_entityname WHERE modulename=? ", array($module));
		$result_row = $adb->fetch_row($result);
		extract($result_row);
		/* get entity field value */
                if($fieldname!='' && $tablename!='' && $entityidfield!='')
                {
		$moduleResult = $adb->pquery("SELECT $fieldname FROM $tablename WHERE $entityidfield = '$transID'");
		$entityFieldVal = $adb->query_result($moduleResult, 0 , $fieldname);
                }
                else
                {
                    $entityFieldVal=$module;
                }
		return $entityFieldVal;
	}
	function checkDefaultChannelHierarchy($id) {
		global $adb, $DEFAULT_CUSTOMER_TYPE;
		
		$customerType = (!empty($DEFAULT_CUSTOMER_TYPE)) ? $DEFAULT_CUSTOMER_TYPE : 'All'; 
		if(!empty($customerType) && !empty($id)) {
			$customerType = "'" . implode("','", explode('@', $customerType)) . "'";
			if (stripos($customerType,'All') === false) {
				$where = "AND customer_type IN ($customerType)";
			}
			$result = $adb->pquery("SELECT * FROM vtiger_xchannelhierarchy WHERE xchannelhierarchyid = '$id' $where");
			$result_row = $adb->fetch_row($result);
			if($result_row) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	function setMaxDiscountforRetailer() {
		global $SET_MAXIMUM_DISCOUNT;
		echo $value = "<script> var max_discount_val = '".$SET_MAXIMUM_DISCOUNT."';  </script>"; 
	}
	
	function getDistrIDbyUserID() {
		global $adb;
		$ret = array();
        $temp=$_SESSION['dist_id'];
        
        if(is_array($temp) && $temp['id']!='')
            return $temp;
        
		$query = "SELECT vtiger_xdistributor.xdistributorid as `id`,vtiger_xdistributor.distributorname as `name`,vtiger_xdistributor.distributorcode as `code`,vtiger_users.date_format  FROM vtiger_xdistributorusermappingcf mt 
LEFT JOIN vtiger_crmentity ct ON mt.xdistributorusermappingid=ct.crmid
LEFT JOIN vtiger_xdistributor on vtiger_xdistributor.xdistributorid=mt.cf_xdistributorusermapping_distributor
LEFT JOIN vtiger_users ON vtiger_users.id=mt.cf_xdistributorusermapping_supporting_staff WHERE 
ct.deleted=0 AND mt.cf_xdistributorusermapping_supporting_staff='".$_SESSION["authenticated_user_id"]."' LIMIT 0,1";
       
                $result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret = $adb->raw_query_result_rowdata($result,$index);
		}            
        $_SESSION['dist_id']=$ret;
        return $ret;
	}
        function valuebasedworkflow($workflowarray,$module,$removedvalone,$removedvaltwo,$removedthrid)
        {
            for($i=0;$i<count($workflowarray);$i++)
            {
                if($module=='universal')
                {
                    if($removedvalone=='no' && trim($workflowarray[$i]['cf_workflowstage_possible_action'])=='Create Vendor')
                    {
                      $workflowarray[$i]['cf_workflowstage_next_content_status'] ='';
                    }
                    if($removedvaltwo=='no' && trim($workflowarray[$i]['cf_workflowstage_possible_action'])=='Create Retailer')
                    {
                      $workflowarray[$i]['cf_workflowstage_next_content_status'] ='';
                    } 
                    
                    if($removedthrid=='yes' && trim($workflowarray[$i]['cf_workflowstage_possible_action'])=='Create Distributor')
                    {
                      $workflowarray[$i]['cf_workflowstage_possible_action']='Create Business Entity';
                    } 
                    elseif($removedthrid=='no' && trim($workflowarray[$i]['cf_workflowstage_possible_action'])=='Create Distributor')
                    {
                      $workflowarray[$i]['cf_workflowstage_next_content_status'] ='';
                    }
                }if($module=='SalesInvoice')
                {
                    if($removedvalone=='no' && trim($workflowarray[$i]['cf_workflowstage_possible_action'])=='Cancel')
                    {
                      $workflowarray[$i]['cf_workflowstage_next_content_status'] ='';
                    }
                }
                //echo 'Val::'.$workflowarray[$i]['cf_workflowstage_possible_action'].'<br>';
               // print_r($workflowarray);
            }
            return $workflowarray;
        }
        function getDistrStateId($distId){
            global $adb;
            $query = "SELECT cf_xdistributor_state FROM vtiger_xdistributor 
                    LEFT JOIN vtiger_xdistributorcf ON vtiger_xdistributor.xdistributorid = vtiger_xdistributorcf.xdistributorid
                    LEFT JOIN vtiger_crmentity ct ON vtiger_xdistributor.xdistributorid = ct.crmid
                    WHERE ct.deleted=0 AND vtiger_xdistributor.xdistributorid = " . $distId . " LIMIT 0,1";
            $result = $adb->pquery($query);
            $state = $adb->query_result($result, $index, 'cf_xdistributor_state');
            $_SESSION['dist_id']=$state;
            return $state;
        }
        
        function Checkpricedate($module,$record) {
		global $adb;
                 $modname = strtolower($module);
        $querycheck = "SELECT  * FROM vtiger_".$modname." where vtiger_".$modname.".".$modname."id = ".$record." and vtiger_".$modname.".effective_to_date >= CURRENT_DATE()";
                     $num =  $adb->num_rows($adb->pquery($querycheck));
                    return $num;
                    
        }             
        
        function getDistrList($id) {
		global $adb;
		$ret = array();
		$query = "SELECT mt.xdistributorid as `id`,mt.distributorname as `name`,mt.distributorcode as `code` FROM vtiger_xdistributor mt 
LEFT JOIN vtiger_crmentity ct ON mt.xdistributorid=ct.crmid
LEFT JOIN vtiger_xdistributorcf on vtiger_xdistributorcf.xdistributorid=mt.xdistributorid WHERE 
ct.deleted=0 AND vtiger_xdistributorcf.cf_xdistributor_geography IN (".$id.")";
		$result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret[] = $adb->raw_query_result_rowdata($result,$index);
		}
              
                return $ret;
	}
        
        function Maginclustersave($clusid,$marginid,$revokedate) {
		global $adb;
		$ret = array();
                $focus=CRMEntity::getInstance('xMarginDistributorMapping');
                
		 $query = "SELECT * FROM vtiger_xdistributorclusterrel WHERE 
 distclusterid=".$clusid."";
		$result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            //$ret[] = $adb->raw_query_result_rowdata($result,$index);
                    $focus->column_fields['distributor_name'] = $adb->query_result($result,$index,'distributorid');
                    $focus->column_fields['distributor_cluster_name'] = $clusid;
                    $focus->column_fields['revoke_date'] = $revokedate;
                    $focus->column_fields['active'] = 1;
                    $focus->save('xMarginDistributorMapping');
                    $return_id = $focus->id;
                    if($marginid!="" && $return_id!=""){
                    $insert = "insert into vtiger_crmentityrel(crmid,module,relcrmid,relmodule) values('".$marginid."','xMargin','".$return_id."','xMarginDistributorMapping')";
                    $adb->pquery($insert);
                    }
		}
             // exit;
              
              //  return $ret;
	}
        function clustersave($clusid,$marginid,$revokedate,$mod,$relmod) {
		global $adb;
		$ret = array();
                $focus=CRMEntity::getInstance($relmod);
                
		 $query = "SELECT * FROM vtiger_xdistributorclusterrel WHERE 
 distclusterid=".$clusid."";
		$result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            //$ret[] = $adb->raw_query_result_rowdata($result,$index);
                    $focus->column_fields['distributor_name'] = $adb->query_result($result,$index,'distributorid');
                    $focus->column_fields['distributor_cluster_name'] = $clusid;
                    $focus->column_fields['revoke_date'] = $revokedate;
                    $focus->column_fields['active'] = 1;
                    $focus->save($relmod);
                    $return_id = $focus->id;
                    if($marginid!="" && $return_id!=""){
                    $insert = "insert into vtiger_crmentityrel(crmid,module,relcrmid,relmodule) values('".$marginid."','".$mod."','".$return_id."','".$relmod."')";
                    $adb->pquery($insert);
                    }
		}
             // exit;
              
              //  return $ret;
	}
        
        function Salesmanclustersave($clusid,$salesmapid) {
		global $adb;
		$ret = array();
                $focus=CRMEntity::getInstance('xSalesdistrevoke');
                
		 $query = "SELECT ft.*,st.distributorname,st.distributorcode,st.xdistributorid,tt.distributorclustername,tt.distributorclustercode FROM vtiger_xdistributorclusterrel ft,vtiger_xdistributor st,vtiger_xdistributorcluster tt  WHERE ft.distclusterid=".$clusid." and ft.distributorid=st.xdistributorid and ft.distclusterid=tt.xdistributorclusterid";
                // echo $clusid.'<br>';
                // echo $salesmapid.'<br>';
                // echo  $query;
                
		$result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            //$ret[] = $adb->raw_query_result_rowdata($result,$index);
                    //$focus->column_fields['clustercode'] = $clusid;
                    $focus->column_fields['clustercode'] = $adb->query_result($result,$index,'distributorclustercode');
                    $focus->column_fields['cluster_description'] = $adb->query_result($result,$index,'distributorclustername');
                    $focus->column_fields['distributorcode'] = $adb->query_result($result,$index,'distributorcode');
                    $focus->column_fields['distributorname'] = $adb->query_result($result,$index,'distributorname');
                    $focus->column_fields['cf_xsalesdistrevoke_revoke_date'] = '';
                    $focus->column_fields['cf_xsalesdistrevoke_status'] = 1;
                    $focus->save('xSalesdistrevoke');
                    $return_id = $focus->id;
                    $updateQry = "UPDATE vtiger_xsalesdistrevoke SET distributorid=".$adb->query_result($result,$index,'xdistributorid').",clusterid=".$clusid." where xsalesdistrevokeid='$return_id'";    
                    $adb->pquery($updateQry);  
                    //echo '<br>'.'retur id'.$return_id.'<br>';
                     //exit;
                    if($salesmapid!="" && $return_id!=""){
                    $insert = "insert into vtiger_crmentityrel(crmid,module,relcrmid,relmodule) values('".$salesmapid."','xSalesmanGpMapping','".$return_id."','xSalesdistrevoke')";
                    $adb->pquery($insert);
                    }
		}
	} 
        
        function FocusProdclustersave($clusid,$salesmapid, $revoke_date) {
		global $adb;
		$ret = array();
                $focus=CRMEntity::getInstance('xSalesdistrevoke');
                
		 $query = "SELECT ft.*,st.distributorname,st.distributorcode,st.xdistributorid,tt.distributorclustername,tt.distributorclustercode 
                     FROM vtiger_xdistributorclusterrel ft,vtiger_xdistributor st,vtiger_xdistributorcluster tt  
                     WHERE ft.distclusterid IN ($clusid) and ft.distributorid=st.xdistributorid and ft.distclusterid=tt.xdistributorclusterid";
                // echo $clusid.'<br>';
                // echo $salesmapid.'<br>';
                // echo  $query;
                
		$result = $adb->pquery($query);
                
                $rel_id_qry = $adb->pquery("select relcrmid from vtiger_crmentityrel where crmid=? AND module=?  AND relmodule=?", 
                        array($salesmapid, "xFocusProductMapping", "xSalesdistrevoke"));
                for ($index = 0; $index < $adb->num_rows($rel_id_qry); $index++) {
                    $adb->pquery("Delete from vtiger_xsalesdistrevoke where xsalesdistrevokeid=?", array($adb->query_result($rel_id_qry,$index,'relcrmid')));
                    $adb->pquery("Delete from vtiger_xsalesdistrevokecf where xsalesdistrevokeid=?", array($adb->query_result($rel_id_qry,$index,'relcrmid')));
                }
                $adb->pquery("Delete from vtiger_crmentityrel where crmid=? AND module=? AND relmodule=?", array($salesmapid, "xFocusProductMapping", "xSalesdistrevoke"));
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            //$ret[] = $adb->raw_query_result_rowdata($result,$index);
                    //$focus->column_fields['clustercode'] = $clusid;
                    $focus->column_fields['clustercode'] = $adb->query_result($result,$index,'distributorclustercode');
                    $focus->column_fields['cluster_description'] = $adb->query_result($result,$index,'distributorclustername');
                    $focus->column_fields['distributorcode'] = $adb->query_result($result,$index,'distributorcode');
                    $focus->column_fields['distributorname'] = $adb->query_result($result,$index,'distributorname');
                    $focus->column_fields['cf_xsalesdistrevoke_revoke_date'] = $revoke_date;
                    $focus->column_fields['clusterid'] = $clusid;
                    $focus->column_fields['distributorid'] = $adb->query_result($result,$index,'xdistributorid');
                    $focus->column_fields['cf_xsalesdistrevoke_status'] = 1;
                    $focus->save('xSalesdistrevoke');
                    $return_id = $focus->id;
                    //echo '<br>'.'retur id'.$return_id.'<br>';
                     //exit;
                    if($salesmapid!="" && $return_id!=""){
                    $insert = "insert into vtiger_crmentityrel(crmid,module,relcrmid,relmodule) values('".$salesmapid."','xFocusProductMapping','".$return_id."','xSalesdistrevoke')";
                    $adb->pquery($insert);
                    }
		}
	}
 ////pppppp
function convert_number_to_words($number) {
   /*
    $hyphen      = '-';
    $conjunction = ' and ';
    $separator   = ' ';
    $negative    = 'negative ';
    $decimal     = ' & ';
    $dictionary  = array(
        0                   => 'zero',
        1                   => 'one',
        2                   => 'two',
        3                   => 'three',
        4                   => 'four',
        5                   => 'five',
        6                   => 'six',
        7                   => 'seven',
        8                   => 'eight',
        9                   => 'nine',
        10                  => 'ten',
        11                  => 'eleven',
        12                  => 'twelve',
        13                  => 'thirteen',
        14                  => 'fourteen',
        15                  => 'fifteen',
        16                  => 'sixteen',
        17                  => 'seventeen',
        18                  => 'eighteen',
        19                  => 'nineteen',
        20                  => 'twenty',
        30                  => 'thirty',
        40                  => 'fourty',
        50                  => 'fifty',
        60                  => 'sixty',
        70                  => 'seventy',
        80                  => 'eighty',
        90                  => 'ninety',
        100                 => 'hundred',
        1000                => 'thousand',
        100000              => 'lakh',
        1000000             => 'million',
        1000000000          => 'billion',
        1000000000000       => 'trillion',
        1000000000000000    => 'quadrillion',
        1000000000000000000 => 'quintillion'
    );
   
    if (!is_numeric($number)) {
        return false;
    }
   
    if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) {
        // overflow
        trigger_error(
            'convert_number_to_words only accepts numbers between -' . PHP_INT_MAX . ' and ' . PHP_INT_MAX,
            E_USER_WARNING
        );
        return false;
    }

    if ($number < 0) {
        return $negative . convert_number_to_words(abs($number));
    }
   
    $string = $fraction = null;
   
    if (strpos($number, '.') !== false) {
        list($number, $fraction) = explode('.', $number);
    }
   
    switch (true) {
        case $number < 21:
            $string = $dictionary[$number];
            break;
        case $number < 100:
            $tens   = ((int) ($number / 10)) * 10;
            $units  = $number % 10;
            $string = $dictionary[$tens];
            if ($units) {
                $string .= $hyphen . $dictionary[$units];
            }
            break;
        
        case $number < 100000:
            $hundreds  = $number / 100000;
            $remainder = $number % 100000;
            $string = $dictionary[$hundreds] . ' ' . $dictionary[100000];
            if ($remainder) {
                $string .= $conjunction . convert_number_to_words($remainder);
            }
            break;
        default:
            $baseUnit = pow(1000, floor(log($number, 1000)));
            $numBaseUnits = (int) ($number / $baseUnit);
            $remainder = $number % $baseUnit;
            $string = convert_number_to_words($numBaseUnits) . ' ' . $dictionary[$baseUnit];
            if ($remainder) {
                $string .= $remainder < 100 ? $conjunction : $separator;
                $string .= convert_number_to_words($remainder);
            }
            break;
    }
   
    if (null !== $fraction && is_numeric($fraction)) {
       
        $words = array();
        foreach (str_split((string) $fraction) as $number) {
            $words[] = $dictionary[$number];
        }
        if($fraction > 0)
        {
             $string .= $decimal;
             $string .= implode(' ', $words)." Paise Only";
        }
        else
            $string .= " Only";
    }
       */
    
    /*
  $no = round($number);
   $point = round($number - $no, 2) * 100;
   $hundred = null;
   $digits_1 = strlen($no);
   $i = 0;
   $str = array();
   $words = array('0' => '', '1' => 'one', '2' => 'two',
    '3' => 'three', '4' => 'four', '5' => 'five', '6' => 'six',
    '7' => 'seven', '8' => 'eight', '9' => 'nine',
    '10' => 'ten', '11' => 'eleven', '12' => 'twelve',
    '13' => 'thirteen', '14' => 'fourteen',
    '15' => 'fifteen', '16' => 'sixteen', '17' => 'seventeen',
    '18' => 'eighteen', '19' =>'nineteen', '20' => 'twenty',
    '30' => 'thirty', '40' => 'forty', '50' => 'fifty',
    '60' => 'sixty', '70' => 'seventy',
    '80' => 'eighty', '90' => 'ninety');
   $digits = array('', 'hundred', 'thousand', 'lakh', 'crore');
   while ($i < $digits_1) {
     $divider = ($i == 2) ? 10 : 100;
     $number = floor($no % $divider);
     $no = floor($no / $divider);
     $i += ($divider == 10) ? 1 : 2;
     if ($number) {
        $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
        $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
        $str [] = ($number < 21) ? $words[$number] .
            " " . $digits[$counter] . $plural . " " . $hundred
            :
            $words[floor($number / 10) * 10]
            . " " . $words[$number % 10] . " "
            . $digits[$counter] . $plural . " " . $hundred;
     } else $str[] = null;
  }
  $str = array_reverse($str);
  $result = implode('', $str);
  $points = ($point) ?
    "." . $words[$point / 10] . " " . 
          $words[$point = $point % 10] : '';
  //echo $points;exit;
  if($points=="")
  {
   $string=$result . " Only";
  }else{
    $string=$result." and " . $points . " Paise Only";
  }
    */
    
    $decimal = round($number - ($no = floor($number)), 2) * 100;
    $hundred = null;
    $digits_length = strlen($no);
    $i = 0;
    $str = array();
    $words = array(0 => 'zero', 1 => 'one', 2 => 'two',
        3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six',
        7 => 'seven', 8 => 'eight', 9 => 'nine',
        10 => 'ten', 11 => 'eleven', 12 => 'twelve',
        13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen',
        16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen',
        19 => 'nineteen', 20 => 'twenty', 30 => 'thirty',
        40 => 'forty', 50 => 'fifty', 60 => 'sixty',
        70 => 'seventy', 80 => 'eighty', 90 => 'ninety');
    $digits = array('', 'hundred','thousand','lakh', 'crore');
    while( $i < $digits_length ) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += $divider == 10 ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
            $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
            $str [] = ($number < 21) ? $words[$number].' '. $digits[$counter]. $plural.' '.$hundred:$words[floor($number / 10) * 10].' '.$words[$number % 10]. ' '.$digits[$counter].$plural.' '.$hundred;
        } else $str[] = null;
    }
    $Rupees = implode('', array_reverse($str));
    $paise = ($decimal) ? " " . ($words[$decimal / 10] . " " . $words[$decimal % 10]) . ' Paise' : '';
    //return ($Rupees ? $Rupees . 'Rupees ' : '') . $paise .;
    if($paise!="")
    {
    $final=($Rupees ? $Rupees . 'and ' : '') . $paise . ' only';
    }else{
         $final=($Rupees ? $Rupees .' only' : ''); 
    }
    return ucwords($final);
}
//////pppp
        function Schemedistclustersave($clusid,$salesmapid) {
		global $adb;
		$ret = array();
                $focus=CRMEntity::getInstance('xSchemeDistributorRevoke');
                
		 //$query = "SELECT ft.*,st.distributorname,st.distributorcode,st.xdistributorid,tt.distributorclustername,tt.distributorclustercode FROM vtiger_xdistributorclusterrel ft,vtiger_xdistributor st,vtiger_xdistributorcluster tt  WHERE ft.distclusterid=".$clusid." and ft.distributorid=st.xdistributorid and ft.distclusterid=tt.xdistributorclusterid";
$query = "SELECT dcr.distributorid
            FROM vtiger_xdistributorclusterrel dcr
            LEFT JOIN vtiger_xdistributorcluster dc on dc.xdistributorclusterid=dcr.distclusterid
            WHERE dc.active='1' and dcr.distclusterid in (".$clusid.") group by dcr.distributorid";                 
		$result = $adb->pquery($query);
                
                
                $check = $adb->pquery("select prefix from vtiger_modentity_num where semodule=? and active = 1", array('xSchemeDistributorRevoke'));
                $prefix=$adb->query_result($check,0,0);
                $incT=$adb->pquery("SELECT count(*) as `cnt` FROM vtiger_crmentity where setype=?",array('xSchemeDistributorRevoke'));
                $incNo=$adb->query_result($incT,0,'cnt');
                
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
                    $focus->column_fields['distributor_code'] = $adb->query_result($result,$index,'distributorid');
                    $focus->column_fields['xschemeid'] = $salesmapid;                    
                    $focus->column_fields['revoke_date'] = '';
                    //$x=new CRMEntity();
                    $revoke_code= $prefix.''.($incNo+$index);                   
                    $focus->column_fields['revoke_code'] = $revoke_code;  
                    
                    $focus->save('xSchemeDistributorRevoke');
                    $return_id = $focus->id;
//                    $updateQry = "UPDATE vtiger_xschemedistributorrevoke SET xschemeid='$salesmapid' where xschemedistributorrevokeid='$return_id'";    
//                    $adb->pquery($updateQry);                    
                   // echo '<br>'.'retur id'.$return_id.'<br>';
                     //exit;
                    if($salesmapid!="" && $return_id!=""){
                    $insert = "insert into vtiger_crmentityrel(crmid,module,relcrmid,relmodule) values('".$salesmapid."','xScheme','".$return_id."','xSchemeDistributorRevoke')";
                    $adb->pquery($insert);
                    }
		}
             // exit;
              
              //  return $ret;
	}         
        
        function Schemebudgetallocationsave($clusid,$salesmapid) {
		global $adb;
		$ret = array();
                $focus=CRMEntity::getInstance('xSchemeBudgetAllocation');
                
		 //$query = "SELECT ft.*,st.distributorname,st.distributorcode,st.xdistributorid,tt.distributorclustername,tt.distributorclustercode FROM vtiger_xdistributorclusterrel ft,vtiger_xdistributor st,vtiger_xdistributorcluster tt  WHERE ft.distclusterid=".$clusid." and ft.distributorid=st.xdistributorid and ft.distclusterid=tt.xdistributorclusterid";
$query = "SELECT dcr.distributorid
            FROM vtiger_xdistributorclusterrel dcr
            LEFT JOIN vtiger_xdistributorcluster dc on dc.xdistributorclusterid=dcr.distclusterid
            WHERE dc.active='1' and dcr.distclusterid in (".$clusid.") group by dcr.distributorid"; 
		$result = $adb->pquery($query);
                
                $check = $adb->pquery("select prefix from vtiger_modentity_num where semodule=? and active = 1", array('xSchemeBudgetAllocation'));
                $prefix=$adb->query_result($check,0,0);
                $incT=$adb->pquery("SELECT count(*) as `cnt` FROM vtiger_crmentity where setype=?",array('xSchemeBudgetAllocation'));
                $incNo=$adb->query_result($incT,0,'cnt');
                
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
                    $focus->column_fields['xdistributorid'] = $adb->query_result($result,$index,'distributorid');
                    $focus->column_fields['xschemeid'] = $salesmapid;                    
                    $focus->column_fields['budget_allocated'] = '';
                    
                    //$x=new CRMEntity();
                    $sba_code= $prefix.''.($incNo+$index);
                    
                    $focus->column_fields['xsba_code'] = $sba_code;
                    
                    $focus->save('xSchemeBudgetAllocation');
                    $return_id = $focus->id;
                    //echo '<br>'.'retur id'.$return_id.'<br>';
                     //exit;
                    if($salesmapid!="" && $return_id!=""){
                    $insert = "insert into vtiger_crmentityrel(crmid,module,relcrmid,relmodule) values('".$salesmapid."','xScheme','".$return_id."','xSchemeBudgetAllocation')";
                    $adb->pquery($insert);
                    }
		}
             // exit;
              
              //  return $ret;
	}       
        
        function Pointschemerevoke($clusid,$salesmapid) {
		global $adb;
		$ret = array();
                $focus=CRMEntity::getInstance('xPointSchemeDistributorRevoke');
                
		 //$query = "SELECT ft.*,st.distributorname,st.distributorcode,st.xdistributorid,tt.distributorclustername,tt.distributorclustercode FROM vtiger_xdistributorclusterrel ft,vtiger_xdistributor st,vtiger_xdistributorcluster tt  WHERE ft.distclusterid=".$clusid." and ft.distributorid=st.xdistributorid and ft.distclusterid=tt.xdistributorclusterid";
$query = "SELECT dcr.distributorid
            FROM vtiger_xdistributorclusterrel dcr
            LEFT JOIN vtiger_xdistributorcluster dc on dc.xdistributorclusterid=dcr.distclusterid
            WHERE dc.active='1' and dcr.distclusterid='$clusid' group by dcr.distributorid"; 
                // echo $clusid.'<br>';
                // echo $salesmapid.'<br>';
                // echo  $query;
                
		$result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
                    $focus->column_fields['xdistributorid'] = $adb->query_result($result,$index,'distributorid');
                    $focus->column_fields['xpointschemeruleid'] = $salesmapid;                    
                    $focus->column_fields['revoke_date'] = '';
                    $x=new CRMEntity();
                    $revoke_code= $x->seModSeqNumber('increment','xPointSchemeDistributorRevoke');                   
                    $focus->column_fields['code'] = $revoke_code;                      
                    $focus->save('xPointSchemeDistributorRevoke');
                    $return_id = $focus->id;
                    //echo '<br>'.'retur id'.$return_id.'<br>';
                     //exit;
                    $updateQry = "UPDATE vtiger_xpointschemedistributorrevoke SET xpointschemeruleid='$salesmapid' where xpointschemedistributorrevokeid='$return_id'";    
                    $adb->pquery($updateQry);                      
                    if($salesmapid!="" && $return_id!=""){
                    $insert = "insert into vtiger_crmentityrel(crmid,module,relcrmid,relmodule) values('".$salesmapid."','xPointSchemeRule','".$return_id."','xPointSchemeDistributorRevoke')";
                    $adb->pquery($insert);
                    }
		}
	}         
        
        function Pointschemebudgetallocation($clusid,$salesmapid) {
		global $adb;
		$ret = array();
                $focus=CRMEntity::getInstance('xPointSchemeBudgetAllocation');
                
		 //$query = "SELECT ft.*,st.distributorname,st.distributorcode,st.xdistributorid,tt.distributorclustername,tt.distributorclustercode FROM vtiger_xdistributorclusterrel ft,vtiger_xdistributor st,vtiger_xdistributorcluster tt  WHERE ft.distclusterid=".$clusid." and ft.distributorid=st.xdistributorid and ft.distclusterid=tt.xdistributorclusterid";
$query = "SELECT dcr.distributorid
            FROM vtiger_xdistributorclusterrel dcr
            LEFT JOIN vtiger_xdistributorcluster dc on dc.xdistributorclusterid=dcr.distclusterid
            WHERE dc.active='1' and dcr.distclusterid='$clusid' group by dcr.distributorid";           
		$result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
                    $focus->column_fields['xdistributorid'] = $adb->query_result($result,$index,'distributorid');
                    $focus->column_fields['xpointschemeruleid'] = $salesmapid;                    
                    $focus->column_fields['budget_allocated'] = '';
                    $x=new CRMEntity();
                    $psba_code= $x->seModSeqNumber('increment','xPointSchemeBudgetAllocation');                   
                    $focus->column_fields['code'] = $psba_code; 
                    
                    $focus->save('xPointSchemeBudgetAllocation');
                    $return_id = $focus->id;
                    $updateQry = "UPDATE vtiger_xpointschemebudgetallocation SET xpointschemeruleid='$salesmapid' where xpointschemebudgetallocationid='$return_id'";    
                    $adb->pquery($updateQry);
                    if($salesmapid!="" && $return_id!=""){
                    $insert = "insert into vtiger_crmentityrel(crmid,module,relcrmid,relmodule) values('".$salesmapid."','xPointSchemeRule','".$return_id."','xPointSchemeBudgetAllocation')";
                    $adb->pquery($insert);
                    }
		}
	}       
        
        function WDSchemedistclustersave($clusid,$salesmapid) {
		global $adb;
		$ret = array();
                $focus=CRMEntity::getInstance('xWDSchemeDistributorRevoke');                		 
$query = "SELECT dcr.distributorid
            FROM vtiger_xdistributorclusterrel dcr
            LEFT JOIN vtiger_xdistributorcluster dc on dc.xdistributorclusterid=dcr.distclusterid
            WHERE dc.active='1' and dcr.distclusterid in (".$clusid.") group by dcr.distributorid";                 
		$result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
                    $focus->column_fields['xdistributorid'] = $adb->query_result($result,$index,'distributorid');
                    $focus->column_fields['xwindowdisplayschemeid'] = $salesmapid;                    
                    $focus->column_fields['revoke_date'] = '';
                    $x=new CRMEntity();
                    $revoke_code= $x->seModSeqNumber('increment','xWDSchemeDistributorRevoke');                   
                    $focus->column_fields['code'] = $revoke_code;                     
                    $focus->save('xWDSchemeDistributorRevoke');
                    $return_id = $focus->id;
                   // echo '<br>'.'retur id'.$return_id.'<br>';
                     //exit;
                    $updateQry = "UPDATE vtiger_xwdschemedistributorrevoke SET xwindowdisplayschemeid='$salesmapid' where xwdschemedistributorrevokeid='$return_id'";    
                    $adb->pquery($updateQry);                    
                    if($salesmapid!="" && $return_id!=""){
                    $insert = "insert into vtiger_crmentityrel(crmid,module,relcrmid,relmodule) values('".$salesmapid."','xWindowDisplayScheme','".$return_id."','xWDSchemeDistributorRevoke')";
                    $adb->pquery($insert);
                    }
		}
	}   
        
        function WDSchemebudgetallocationsave($clusid,$salesmapid) {
		global $adb;
		$ret = array();
                $focus=CRMEntity::getInstance('xWDSchemeBudgetAllocation');
                
		 
$query = "SELECT dcr.distributorid
            FROM vtiger_xdistributorclusterrel dcr
            LEFT JOIN vtiger_xdistributorcluster dc on dc.xdistributorclusterid=dcr.distclusterid
            WHERE dc.active='1' and dcr.distclusterid in (".$clusid.") group by dcr.distributorid"; 
                
		$result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
                    $focus->column_fields['xdistributorid'] = $adb->query_result($result,$index,'distributorid');
                    $focus->column_fields['xwindowdisplayschemeid'] = $salesmapid;                    
                    $focus->column_fields['budget_allocated'] = '';
                    $x=new CRMEntity();
                    $wsba_code= $x->seModSeqNumber('increment','xWDSchemeBudgetAllocation');                   
                    $focus->column_fields['code'] = $wsba_code;                      
                    $focus->save('xWDSchemeBudgetAllocation');
                    $return_id = $focus->id;
                    //echo '<br>'.'retur id'.$return_id.'<br>';
                     //exit;
                    $updateQry = "UPDATE vtiger_xwdschemebudgetallocation SET xwindowdisplayschemeid='$salesmapid' where xwdschemebudgetallocationid='$return_id'";    
                    $adb->pquery($updateQry);                      
                    if($salesmapid!="" && $return_id!=""){
                    $insert = "insert into vtiger_crmentityrel(crmid,module,relcrmid,relmodule) values('".$salesmapid."','xWindowDisplayScheme','".$return_id."','xWDSchemeBudgetAllocation')";
                    $adb->pquery($insert);
                    }
		}
	}         
        
        function getDistrIDbyUID($id) {
		global $adb;
		$ret = array();
		$query = "SELECT vtiger_xdistributor.xdistributorid as `id`,vtiger_xdistributorcf.cf_xdistributor_status as `status`,
vtiger_xdistributor.distributorname as `name`,vtiger_xdistributor.distributorcode as `code`,
vtiger_xdistributorcf.cf_xdistributor_active as login,vtiger_xsupplychainhiermetacf.cf_xsupplychainhiermeta_hierarchy_level as supplychainlevel,
vtiger_xsupplychainhiercf.xsupplychainhierid as supplychainid,vtiger_xsupplychainhiermetacf.xsupplychainhiermetaid as supplychainmetaid
FROM vtiger_xdistributorusermappingcf mt 
LEFT JOIN vtiger_crmentity ct ON mt.xdistributorusermappingid=ct.crmid 
LEFT JOIN vtiger_xdistributor on vtiger_xdistributor.xdistributorid=mt.cf_xdistributorusermapping_distributor 
LEFT JOIN vtiger_xdistributorcf ON vtiger_xdistributorcf.xdistributorid = vtiger_xdistributor.xdistributorid
LEFT JOIN vtiger_xsupplychainhiercf ON vtiger_xsupplychainhiercf.xsupplychainhierid= vtiger_xdistributorcf.cf_xdistributor_supply_chain
LEFT JOIN vtiger_xsupplychainhiermetacf ON vtiger_xsupplychainhiermetacf.xsupplychainhiermetaid = vtiger_xsupplychainhiercf.cf_xsupplychainhier_level
WHERE ct.deleted=0 AND mt.cf_xdistributorusermapping_supporting_staff='".$id."' LIMIT 0,1";
		$result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret = $adb->raw_query_result_rowdata($result,$index);
		}
                return $ret;
	}
        function getDistrIDbyCode($code) {
		global $adb;
		$ret = array();
		$query = "SELECT vtiger_xdistributor.xdistributorid as `id`,vtiger_xdistributorcf.cf_xdistributor_status as `status`,
vtiger_xdistributor.distributorname as `name`,vtiger_xdistributor.distributorcode as `code`,
vtiger_xdistributorcf.cf_xdistributor_active as login,vtiger_xsupplychainhiermetacf.cf_xsupplychainhiermeta_hierarchy_level as supplychainlevel,
vtiger_xsupplychainhiercf.xsupplychainhierid as supplychainid,vtiger_xsupplychainhiermetacf.xsupplychainhiermetaid as supplychainmetaid
FROM vtiger_xdistributorusermappingcf mt 
LEFT JOIN vtiger_crmentity ct ON mt.xdistributorusermappingid=ct.crmid 
LEFT JOIN vtiger_xdistributor on vtiger_xdistributor.xdistributorid=mt.cf_xdistributorusermapping_distributor 
LEFT JOIN vtiger_xdistributorcf ON vtiger_xdistributorcf.xdistributorid = vtiger_xdistributor.xdistributorid
LEFT JOIN vtiger_xsupplychainhiercf ON vtiger_xsupplychainhiercf.xsupplychainhierid= vtiger_xdistributorcf.cf_xdistributor_supply_chain
LEFT JOIN vtiger_xsupplychainhiermetacf ON vtiger_xsupplychainhiermetacf.xsupplychainhiermetaid = vtiger_xsupplychainhiercf.cf_xsupplychainhier_level
WHERE ct.deleted=0 AND vtiger_xdistributor.distributorcode='".$code."' LIMIT 0,1";
		$result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret = $adb->raw_query_result_rowdata($result,$index);
		}
                return $ret;
	}        
        
        
        function getCurrentCompanyID() {
		global $adb;
		$ret = array();
		$query = "SELECT companyid FROM vtiger_company LIMIT 1";
		$result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret = $adb->raw_query_result_rowdata($result,$index);
		}
                return $ret['companyid'];
	}
        
        function getNextstageByStatus($module,$status) {
		global $adb;
		$ret = array();
	       $query = "SELECT * FROM vtiger_workflow w LEFT JOIN vtiger_workflowcf wc ON w.workflowid=wc.workflowid  LEFT JOIN vtiger_workflowstagecf s ON w.workflowid=s.cf_workflowstage_workflow_id WHERE wc.cf_workflow_module='".$module."' AND s.workflowstageid!='' 
                    AND s.cf_workflowstage_next_content_status='".$status."' LIMIT 1";
		//echo $query;
               
               $result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret = $adb->raw_query_result_rowdata($result,$index);
		}
                return $ret;
	}
        
        function getNextstageByPosAction($module,$posaction) {
		global $adb;
		$ret = array();
	        $query = "SELECT * FROM vtiger_workflow w 
                    LEFT JOIN vtiger_workflowcf wc ON w.workflowid=wc.workflowid 
                    LEFT JOIN vtiger_workflowstagecf s ON w.workflowid=s.cf_workflowstage_workflow_id 
                    LEFT JOIN vtiger_crmentity ct ON ct.crmid=s.workflowstageid 
                    WHERE ct.deleted=0 AND wc.cf_workflow_module='".$module."' AND s.workflowstageid!='' 
                    AND s.cf_workflowstage_possible_action='".$posaction."' LIMIT 1";
		$result = $adb->mquery($query);//echo "Hi :".$query.'<br>';//exit;
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret = $adb->raw_query_result_rowdata($result,$index);
		}
                return $ret;
	}
        
        function getTransactionSeriesNameByID($id) {
            global $adb;
            $query = "SELECT mt.*,ct.crmid,ct.deleted FROM vtiger_xtransactionseries mt 
LEFT JOIN vtiger_crmentity ct ON mt.xtransactionseriesid=ct.crmid WHERE 
ct.deleted=0 AND mt.xtransactionseriesid=".$id;
            $result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret = $adb->raw_query_result_rowdata($result,$index);
		}
                return $ret['transactionseriesname'];
        }
        
        function getDefaultVendor() {
            global $adb;
            $query = "SELECT v.vendorid,v.vendorname 
FROM `vtiger_vendor` v LEFT JOIN vtiger_vendorcf cf on v.vendorid=cf.vendorid LEFT JOIN vtiger_crmentity ct ON v.vendorid=ct.crmid WHERE 
ct.deleted=0 AND cf.cf_vendors_active=1 LIMIT 1";
            $result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret = $adb->raw_query_result_rowdata($result,$index);
		}
            return $ret;
        }
        
        function getDefaultVendorNew() {
            global $adb;
            //$id=getDistrIDbyUserID();
            /*$query = "SELECT v.vendorid,v.vendorname 
FROM `vtiger_vendor` v LEFT JOIN vtiger_vendorcf cf on v.vendorid=cf.vendorid LEFT JOIN vtiger_crmentity ct ON v.vendorid=ct.crmid WHERE 
ct.deleted=0 AND cf.cf_vendors_active=1 AND v.distributor_id = ".$id['id']." LIMIT 1";*/
            $query = "SELECT v.vendorid,v.vendorname 
FROM `vtiger_vendor` v LEFT JOIN vtiger_vendorcf cf on v.vendorid=cf.vendorid LEFT JOIN vtiger_crmentity ct ON v.vendorid=ct.crmid WHERE 
ct.deleted=0 AND cf.cf_vendors_active=1 AND (v.distributor_id is null OR v.distributor_id = '') LIMIT 1";
            $result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret = $adb->raw_query_result_rowdata($result,$index);
		}
            return $ret;
        }
        
        function getDistDefaultDepot() {
            global $adb; $ret = array();
            $distArr = getDistrIDbyUserID();
            $query = "SELECT xdistributorid,cf_xdistributor_default_depot as depotid,dt.supplylocation 
FROM vtiger_xdistributorcf cf LEFT JOIN vtiger_xdepot dt ON dt.xdepotid=cf.cf_xdistributor_default_depot 
WHERE cf.xdistributorid=".$distArr['id'];
            $result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret = $adb->raw_query_result_rowdata($result,$index);
		}
            if($ret['depotid']=="") {
                $query = "SELECT mt.xdepotid as depotid,mt.supplylocation FROM vtiger_xdepot mt 
LEFT JOIN vtiger_xdepotcf cf ON cf.xdepotid=mt.xdepotid 
LEFT JOIN vtiger_crmentity ct ON cf.xdepotid=ct.crmid 
WHERE ct.deleted=0 AND cf.cf_xdepot_status=1 LIMIT 1";
                $result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret = $adb->raw_query_result_rowdata($result,$index);
		}
            }    
                return $ret;
        }
        
        function getDefaultRetailer() {
            global $adb;
            $query = "SELECT r.xretailerid,r.customername FROM `vtiger_xretailer` r 
LEFT JOIN vtiger_xretailercf cf ON r.xretailerid=cf.xretailerid LEFT JOIN vtiger_crmentity ct ON r.xretailerid=ct.crmid
 WHERE ct.deleted=0  AND cf.cf_xretailer_active=1 LIMIT 1";
            $result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret = $adb->raw_query_result_rowdata($result,$index);
		}
            return $ret;
        }
        
    function getDefaultTransactionSeries($module) {
		global $adb;
		$dist_id = getDistrIDbyUserID();
		$where = '';
		if(!empty($dist_id)) {
			$cmpny_detail = getDistributorCompany($dist_id['id']);
			$cmpny_user_id = $cmpny_detail['reports_to_id'];
			//$where = " AND m.xdistributorid = '".$dist_id['id']."' ";
		$where .= " AND  (m.cf_xtransactionseries_user_id = '".$cmpny_user_id."' AND m.xdistributorid=0 OR (m.xdistributorid = '".$dist_id['id']."'))"; 
		}
		
		$query = "SELECT m.xtransactionseriesid, transactionseriesname
		FROM vtiger_xtransactionseries m 
		LEFT JOIN `vtiger_xtransactionseriescf` cf ON m.xtransactionseriesid=cf.xtransactionseriesid 
		LEFT JOIN vtiger_crmentity ct ON m.xtransactionseriesid=ct.crmid 
		WHERE ct.deleted=0 AND cf.cf_xtransactionseries_status=1 AND cf_xtransactionseries_mark_as_default=1 
		AND cf.cf_xtransactionseries_transaction_type='".$module."' $where";
				$result = $adb->pquery($query);
			for ($index = 0; $index < $adb->num_rows($result); $index++) {
					$ret = $adb->raw_query_result_rowdata($result,$index);
			}
		return $ret;
	}

            function getDefaultTransactionSeriesbasedtin($module,$xretailer_tin_number='') {
		global $adb;
                
		$dist_id = getDistrIDbyUserID();
		$where = '';
		if(!empty($dist_id)) {
			$cmpny_detail = getDistributorCompany($dist_id['id']);
			$cmpny_user_id = $cmpny_detail['reports_to_id'];
			$where .= " AND  (ts.xdistributorid = '".$dist_id['id']."')"; 
		}
                if($cmpny_user_id=='' || $cmpny_user_id==null){
                    $cmpny_user_id=0;
                }
        $rettransactionseries="select ts.xtransactionseriesid,ts.transactionseriesname,ts.tinnumber 
                                from vtiger_xtransactionseries ts
                                LEFT JOIN vtiger_xtransactionseriescf tscf on  tscf.xtransactionseriesid=ts.xtransactionseriesid
                                LEFT JOIN vtiger_crmentity ct ON ts.xtransactionseriesid=ct.crmid 
                                where ct.deleted=0 AND ts.tinnumber=1 
                                and tscf.cf_xtransactionseries_transaction_type='Sales Invoice' $where";
        $rettransactionseriesresult = $adb->pquery($rettransactionseries); 
        $transactionseriesid = $adb->query_result($rettransactionseriesresult,0,'xtransactionseriesid');
        $transactionseriesname = $adb->query_result($rettransactionseriesresult,0,'transactionseriesname');
        $tinnumber = $adb->query_result($rettransactionseriesresult,0,'tinnumber');
        	
        $defaulttransactionseries="select ts.xtransactionseriesid,ts.transactionseriesname,ts.tinnumber  
                                from vtiger_xtransactionseries ts
                                LEFT JOIN vtiger_xtransactionseriescf tscf on  tscf.xtransactionseriesid=ts.xtransactionseriesid
                                LEFT JOIN vtiger_crmentity ct ON ts.xtransactionseriesid=ct.crmid 
                                where ct.deleted=0 AND tscf.cf_xtransactionseries_mark_as_default=1 
                                and tscf.cf_xtransactionseries_transaction_type='Sales Invoice' $where";
        $defaulttransactionseriesresult = $adb->pquery($defaulttransactionseries); 
		if($adb->num_rows($defaulttransactionseriesresult)==0)
		{
				$defaulttransactionseries="select ts.xtransactionseriesid,ts.transactionseriesname,ts.tinnumber  
                                from vtiger_xtransactionseries ts
                                LEFT JOIN vtiger_xtransactionseriescf tscf on  tscf.xtransactionseriesid=ts.xtransactionseriesid
                                LEFT JOIN vtiger_crmentity ct ON ts.xtransactionseriesid=ct.crmid 
                                where ct.deleted=0 and tscf.cf_xtransactionseries_transaction_type='Sales Invoice' AND  (ts.xdistributorid=0 OR (ts.xdistributorid = ".$dist_id['id'].")) order by ts.xtransactionseriesid DESC";
				$defaulttransactionseriesresult = $adb->pquery($defaulttransactionseries); 
		}
		$dftransactionseriesid = $adb->query_result($defaulttransactionseriesresult,0,'xtransactionseriesid');
		$dftransactionseriesname = $adb->query_result($defaulttransactionseriesresult,0,'transactionseriesname'); 
                $dftinnumber = $adb->query_result($defaulttransactionseriesresult,0,'tinnumber'); 
                
            if($xretailer_tin_number!='' && $xretailer_tin_number!=null){  
                if($transactionseriesid!=null && $transactionseriesname!=null && $transactionseriesid!='' && $transactionseriesname!='')
                  {
                     $res['xtransactionseriesid'] = $transactionseriesid;
                     $res['transactionseriesname'] = $transactionseriesname;
                     $res['tinnumber'] = $tinnumber; 
                  }
                  else
                  {
                     $res['xtransactionseriesid'] = $dftransactionseriesid; 
                     $res['transactionseriesname'] = $dftransactionseriesname; 
                     $res['tinnumber'] = $dftinnumber; 
                  }
            }else { 
                $res['xtransactionseriesid'] = $dftransactionseriesid; 
                $res['transactionseriesname'] = $dftransactionseriesname; 
                $res['tinnumber'] = 0; 
            }   
            
		return $res;
	}

        
        function getpaymentmodeoptions() {
            global $adb;
            $query = "select cf.cf_xcollectionmethod_collection_type from vtiger_xcollectionmethod as cf 
                INNER JOIN vtiger_xcollectionmethodcf c_scf ON c_scf.xcollectionmethodid  =cf.xcollectionmethodid  
                INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=cf.xcollectionmethodid  
                where vtiger_crmentity.deleted=0 and c_scf.cf_xcollectionmethod_status='1' order by cf.cf_xcollectionmethod_collection_type= 'cash' desc";
            $result = $adb->pquery($query);
            $ret = "";
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret .= "<option value=".$adb->query_result($result,$index,'cf_xcollectionmethod_collection_type').">".$adb->query_result($result,$index,'cf_xcollectionmethod_collection_type')."</option>";
		}
            return $ret;
        }
        
        function getProductcodeByID($id) {
            global $adb; 
            $query = "SELECT xproductid,productcode FROM vtiger_xproduct WHERE xproductid=".$id;
             $result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret = $adb->raw_query_result_rowdata($result,$index);
		}
            return $ret['productcode'];
        }
        
        function claimOffTakeSchemes($curmodule,$transid)
        {
            $arrval[0] = array('SchemeOffTake','Free');
            $arrval[1] = array('Main','points');
                    
            for($i = 0;$i<2;$i++){
            global $adb; 
            $r = getTables($curmodule);
             //$moduleStockQry = "SELECT ".$r['productrelIndex'].",".$r['productrelproductid']." FROM ".$r['productrelTable']." WHERE ".$r['productrelIndex']."=".$transid;
            $moduleStockQry = "SELECT *,SUM(baseqty) as `baseofftakeqty` FROM ".$r['productrelTable']." LEFT JOIN vtiger_itemwise_scheme ON (vtiger_itemwise_scheme.transaction_id=".$r['productrelTable'].".".$r['productrelIndex']." 
                    AND vtiger_itemwise_scheme.lineitem_id = vtiger_siproductrel.lineitem_id) LEFT JOIN vtiger_xschemeslabrel ON (xschemeslabrelid = scheme_id) WHERE product_type IN ('".$arrval[$i][0]."') AND vtiger_itemwise_scheme.scheme_applied in ('".$arrval[$i][1]."')  AND ".$r['productrelTable'].".".$r['productrelIndex']."=".$transid.""
                    . " GROUP BY schemecode,xschemeslabrelid,productid";
            //echo $moduleStockQry;
            
            
            
             $si=CRMEntity::getInstance('SalesInvoice');
             $si->retrieve_entity_info($transid, 'SalesInvoice');
           
             $retailerId=$si->column_fields['cf_salesinvoice_buyer_id'];
             
             $distributor = getDistrIDbyUserID();
             $distID = $distributor['id'];
             $distCode = $distributor['code'];
             
             global $current_user;               
             
              $result = $adb->mquery($moduleStockQry);
              
             
              
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret = $adb->raw_query_result_rowdata($result,$index);
                    $bentype=$ret['scheme_applied'];
                    $proId=$ret['productid'];
                    $proQty=$ret['baseofftakeqty'];
                    $schSlabId=$ret['scheme_id'];
                    //$schId=$ret['scheme_name'];
                    $schId=$ret['schemecode'];
                    $schpoints=floor($ret['disc_qty']);
                    
                    if($bentype == 'Free'){
                        $offTakeRecord=$adb->pquery("SELECT *,sum(benifit_qty) as benifitvalue FROM vtiger_offtake_scheme_multi_log WHERE scheme_id=? and scheme_slab_id=? AND distributor_id=? AND retailer_id=? AND benifit_product=? "
                                 ,array($schId,$schSlabId,$distID,$retailerId,$proId));
                        if(!$adb->query_result($offTakeRecord,0,'benifitvalue') > 0){
                            $offTakeRecord=$adb->pquery("SELECT *,sum(benifit_value) as benifitvalue FROM vtiger_offtake_scheme_log WHERE scheme_id=? and scheme_slab_id=? AND distributor_id=? AND retailer_id=? AND benifit_product=? "
                                 ,array($schId,$schSlabId,$distID,$retailerId,$proId));                            
                        }
                    }
                    else if($bentype !== 'points'){
                        //$offTakeRecord=$adb->pquery("SELECT * FROM vtiger_offtake_scheme_log WHERE scheme_id=? and scheme_slab_id=? AND distributor_id=? AND retailer_id=? AND benifit_product=? AND claimed = 0 LIMIT 0,1"
                        // JIRA 5835
                        $offTakeRecord=$adb->pquery("SELECT *,sum(benifit_value) as benifitvalue FROM vtiger_offtake_scheme_log WHERE scheme_id=? and scheme_slab_id=? AND distributor_id=? AND retailer_id=? AND benifit_product=? "
                                 ,array($schId,$schSlabId,$distID,$retailerId,$proId));
                    }else{
                        //$offTakeRecord=$adb->pquery("SELECT * FROM vtiger_offtake_scheme_log WHERE scheme_id=? and scheme_slab_id=? AND distributor_id=? AND retailer_id=? AND benifit_value=? AND claimed = 0 LIMIT 0,1"
                        // JIRA 5835
                        $offTakeRecord=$adb->pquery("SELECT *,sum(benifit_value) as benifitvalue FROM vtiger_offtake_scheme_log WHERE scheme_id=? and scheme_slab_id=? AND distributor_id=? AND retailer_id=? AND benifit_value=? "
                                 ,array($schId,$schSlabId,$distID,$retailerId,$schpoints));    
                    }
                    
                    for ($index1 = 0; $index1 <  $adb->num_rows($offTakeRecord); $index1++) 
                    {
                         $ret2 = $adb->raw_query_result_rowdata($offTakeRecord,$index1);
                         $claimed = $ret2['claimed'];   
                         
                         //print_r($ret2);exit;
                         
                         if(trim($claimed)=='1')
                         {
                             $check_si_qry = $adb->pquery("SELECT amend_id FROM vtiger_salesinvoice WHERE 1=1 AND salesinvoiceid=? LIMIT 0,1",array($transid));
                             $is_si_amended = $adb->num_rows($check_si_qry);
                             if($is_si_amended > 0)
                             {
                               //amended
                                $benValue=$ret2['benifitvalue'];  
                                $claimed=$ret2['claimed'];
                                $offtakeId=$ret2['id'];

//                                if($bentype !== 'points'){
//                                   if($benValue!=$proQty)
//                                   {
//                                       return array(0=>FALSE,1=>'CLAIM_DATA_WRONG');
//                                   }
//                                }
                                $adb->pquery("UPDATE vtiger_offtake_scheme_log SET claimed_date=NOW(),claimed_transaction_type=? ,claimed_transaction_id=?,claimed_person=? WHERE id=?"
                                     ,array($curmodule,$transid,$current_user->id,$offtakeId)); 
                             }
                             else
                             {
                                 //not amendment
                                 return array(0=>FALSE,1=>'OFFTAKE_SCHEME_ALREADY_CLAIMED');
                             }
                         }    
                         else
                         {
                            $benValue=$ret2['benifitvalue'];  
                            $claimed=$ret2['claimed'];
                            $offtakeId=$ret2['id'];
                            $offtakeRefId=$ret2['offtake_log_id'];
                            
                            $benValue=(double)$benValue;
                            $proQty=(double)$proQty;
                            
                            //echo $benValue;
                            //echo $proQty;
                            
                            if($bentype !== 'points'){
                               if($benValue!=$proQty)
                               {
                                   //echo "123";
                                   //print_r(array($bentype,$proQty));exit;
                                   return array(0=>FALSE,1=>'CLAIM_DATA_WRONG');
                               }
                            }
                            if($offtakeRefId!='')
                            {
                                $adb->pquery("UPDATE vtiger_offtake_scheme_multi_log SET claimed=1 ,claimed_transaction_type=? ,claimed_transaction_id=?,claimed_person=? WHERE id=?"
                                 ,array($curmodule,$transid,$current_user->id,$offtakeId));
                                
                                 $adb->pquery("UPDATE vtiger_offtake_scheme_log SET claimed=1 ,claimed_date=NOW(),claimed_transaction_type=? ,claimed_transaction_id=?,claimed_person=? WHERE id=?"
                                 ,array($curmodule,$transid,$current_user->id,$offtakeRefId));
                            }
                            else
                            {
                                $adb->pquery("UPDATE vtiger_offtake_scheme_log SET claimed=1 ,claimed_date=NOW(),claimed_transaction_type=? ,claimed_transaction_id=?,claimed_person=? WHERE id=?"
                                 ,array($curmodule,$transid,$current_user->id,$offtakeId));
                            }
                             
                         }
                    }
		}
            }
             return array(0=>TRUE);
        }
        
        // Check OfftakeScheme benefit before stock update - Ranjith 
        function chkClaimOffTakeSchemes($curmodule,$transid)
        {
            $arrval[0] = array('SchemeOffTake','Free');
            $arrval[1] = array('Main','points');
                    
            for($i = 0;$i<2;$i++){
            global $adb; 
            $r = getTables($curmodule);
             //$moduleStockQry = "SELECT ".$r['productrelIndex'].",".$r['productrelproductid']." FROM ".$r['productrelTable']." WHERE ".$r['productrelIndex']."=".$transid;
            $moduleStockQry = "SELECT *,SUM(baseqty) as `baseofftakeqty` FROM ".$r['productrelTable']." LEFT JOIN vtiger_itemwise_scheme ON (vtiger_itemwise_scheme.transaction_id=".$r['productrelTable'].".".$r['productrelIndex']." 
                    AND vtiger_itemwise_scheme.lineitem_id = vtiger_siproductrel.lineitem_id) LEFT JOIN vtiger_xschemeslabrel ON (xschemeslabrelid = scheme_id) WHERE product_type IN ('".$arrval[$i][0]."') AND vtiger_itemwise_scheme.scheme_applied in ('".$arrval[$i][1]."')  AND ".$r['productrelTable'].".".$r['productrelIndex']."=".$transid.""
                    . " GROUP BY schemecode,xschemeslabrelid,productid";
            //echo $moduleStockQry;
            
            
            
             $si=CRMEntity::getInstance('SalesInvoice');
             $si->retrieve_entity_info($transid, 'SalesInvoice');
           
             $retailerId=$si->column_fields['cf_salesinvoice_buyer_id'];
             
             $distributor = getDistrIDbyUserID();
             $distID = $distributor['id'];
             $distCode = $distributor['code'];
             
             global $current_user;               
             
              $result = $adb->mquery($moduleStockQry);
              
             
              
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret = $adb->raw_query_result_rowdata($result,$index);
                    $bentype=$ret['scheme_applied'];
                    $proId=$ret['productid'];
                    $proQty=$ret['baseofftakeqty'];
                    $schSlabId=$ret['scheme_id'];
                    //$schId=$ret['scheme_name'];
                    $schId=$ret['schemecode'];
                    $schpoints=floor($ret['disc_qty']);
                    
                    if($bentype == 'Free'){
                        $offTakeRecord=$adb->pquery("SELECT *,sum(benifit_qty) as benifitvalue FROM vtiger_offtake_scheme_multi_log WHERE scheme_id=? and scheme_slab_id=? AND distributor_id=? AND retailer_id=? AND benifit_product=? "
                                 ,array($schId,$schSlabId,$distID,$retailerId,$proId));
                        if(!$adb->query_result($offTakeRecord,0,'benifitvalue') > 0){
                            $offTakeRecord=$adb->pquery("SELECT *,sum(benifit_value) as benifitvalue FROM vtiger_offtake_scheme_log WHERE scheme_id=? and scheme_slab_id=? AND distributor_id=? AND retailer_id=? AND benifit_product=? "
                                 ,array($schId,$schSlabId,$distID,$retailerId,$proId));                            
                        }
                    }
                    else if($bentype !== 'points'){
                        //$offTakeRecord=$adb->pquery("SELECT * FROM vtiger_offtake_scheme_log WHERE scheme_id=? and scheme_slab_id=? AND distributor_id=? AND retailer_id=? AND benifit_product=? AND claimed = 0 LIMIT 0,1"
                        // JIRA 5835
                        $offTakeRecord=$adb->pquery("SELECT *,sum(benifit_value) as benifitvalue FROM vtiger_offtake_scheme_log WHERE scheme_id=? and scheme_slab_id=? AND distributor_id=? AND retailer_id=? AND benifit_product=? "
                                 ,array($schId,$schSlabId,$distID,$retailerId,$proId));
                    }else{
                        //$offTakeRecord=$adb->pquery("SELECT * FROM vtiger_offtake_scheme_log WHERE scheme_id=? and scheme_slab_id=? AND distributor_id=? AND retailer_id=? AND benifit_value=? AND claimed = 0 LIMIT 0,1"
                        // JIRA 5835
                        $offTakeRecord=$adb->pquery("SELECT *,sum(benifit_value) as benifitvalue FROM vtiger_offtake_scheme_log WHERE scheme_id=? and scheme_slab_id=? AND distributor_id=? AND retailer_id=? AND benifit_value=? "
                                 ,array($schId,$schSlabId,$distID,$retailerId,$schpoints));    
                    }
                    
                    for ($index1 = 0; $index1 <  $adb->num_rows($offTakeRecord); $index1++) 
                    {
                         $ret2 = $adb->raw_query_result_rowdata($offTakeRecord,$index1);
                         $claimed = $ret2['claimed'];   
                         
                         //print_r($ret2);exit;
                         
                         if(trim($claimed)=='1')
                         {
                             $check_si_qry = $adb->pquery("SELECT amend_id FROM vtiger_salesinvoice WHERE 1=1 AND salesinvoiceid=? LIMIT 0,1",array($transid));
                             $is_si_amended = $adb->num_rows($check_si_qry);
                             if($is_si_amended > 0)
                             {
                               //amended
                                $benValue=$ret2['benifitvalue'];  
                                $claimed=$ret2['claimed'];
                                $offtakeId=$ret2['id'];

                                if($bentype !== 'points'){
                                   if($benValue!=$proQty)
                                   {
                                       return array(0=>FALSE,1=>'CLAIM_DATA_WRONG');
                                   }
                                }
                             }
                             else
                             {
                                 //not amendment
                                 return array(0=>FALSE,1=>'OFFTAKE_SCHEME_ALREADY_CLAIMED');
                             }
                         }    
                         else
                         {
                            $benValue=$ret2['benifitvalue'];  
                            $claimed=$ret2['claimed'];
                            $offtakeId=$ret2['id'];
                            $offtakeRefId=$ret2['offtake_log_id'];
                            
                            $benValue=(double)$benValue;
                            $proQty=(double)$proQty;
                            
                            //echo $benValue;
                            //echo $proQty;
                            
                            if($bentype !== 'points'){
                               if($benValue!=$proQty)
                               {
                                   //echo "123";
                                   //print_r(array($bentype,$proQty));exit;
                                   return array(0=>FALSE,1=>'CLAIM_DATA_WRONG');
                               }
                            }
                         }
                    }
		}
            }
             return array(0=>TRUE);
        }
     
        function updateUniversal($module,$transID,$universalcode) { //pk
            global $adb;
            $focus = CRMEntity::getInstance('xUniversal');
            $Qrysql = "SELECT prefix, cur_id from vtiger_modentity_num where semodule ='xUniversal' and active=1";
            $resultsql = $adb->pquery($Qrysql);
            $prefix=$adb->query_result($resultsql,0,'prefix');           
            //$Qrycount = "SELECT customer_code from vtiger_xuniversal";
            $Qrycount = "SELECT customer_code from vtiger_xuniversal order by xuniversalid desc limit 1";
            $resultcount = $adb->pquery($Qrycount);
            
            $resultcode=$adb->query_result($resultcount,0,'customer_code');
            $stripcodeval = str_replace(trim($prefix),"",trim($resultcode));
           // $rescount = $adb->num_rows($resultcount)+1;
            $rescount = $stripcodeval+1;
            $adb->pquery("UPDATE vtiger_modentity_num SET cur_id=? WHERE semodule ='xUniversal' and active=1",array($rescount));
            $customer_code = $prefix.$rescount; 
            //echo '<pre>$module='.$module;
           
            if($module == "retailer" || $module=="xRetailer"){
                $mod_seq_field = getModuleSequenceField('xRetailer');
                
                $Qry = "SELECT rcf.*,r.* FROM vtiger_xretailercf rcf LEFT JOIN vtiger_xretailer r on r.xretailerid=rcf.xretailerid WHERE rcf.xretailerid=".$transID;            
                $result = $adb->pquery($Qry);
                //echo '<pre>$return_id='.$transID;
                //echo '<pre>UCV='; print_r($universalcode); die;
                if($universalcode != "" && $universalcode != null){
                    $update = "update vtiger_xuniversal set conveted_retailer_id=? where customer_code=?";
                    $qparams = array($transID, $universalcode);
                    $adb->pquery($update,$qparams);
                    $Qryrelation = "insert into `vtiger_crmentityrel` (`crmid`, `module`, `relcrmid`, `relmodule`)VALUES('".$adb->query_result($result,0,'xretailerid')."','xUniversal','".$transID."','xRetailer')";            
                    $resultrelation = $adb->pquery($Qryrelation);
                } else {
                    //if customer realignment means we are not updating universal code
                    $custrealignQry1 = "SELECT cust_id,to_dist FROM vtiger_xcustomerrealignmentdetails WHERE `new_cust_id` = ".$transID;            
                    $custrealignresult1 = $adb->pquery($custrealignQry1);                
                    $adb->num_rows($custrealignresult1);
                    
                    $Qry1 = "SELECT * FROM vtiger_xuniversal WHERE vtiger_xuniversal.conveted_retailer_id=".$transID;            
                    $result1 = $adb->pquery($Qry1);                
                    $adb->num_rows($result1);
                    
                        if(($adb->num_rows($result1)==0) && ($adb->num_rows($custrealignresult1)==0) ){
                            $focus->column_fields['customer_name'] = $adb->query_result($result,0,'customername');
                            $focus->column_fields['customer_code'] = $customer_code;
                            $focus->column_fields['address'] = $adb->query_result($result,0,'cf_xretailer_address_1');
                            $focus->column_fields['state'] = $adb->query_result($result,0,'cf_xretailer_state');
                            $focus->column_fields['postal_code'] = $adb->query_result($result,0,'cf_xretailer_pin_code');
                            $focus->column_fields['stockist_zone'] = $adb->query_result($result,0,'cf_xretailer_supply_chain_distributor');
                            $focus->column_fields['contact_person'] = $adb->query_result($result,0,'cf_xretailer_contact_person');
                            $focus->column_fields['phone_number'] = $adb->query_result($result,0,'cf_xretailer_phone');
                            $focus->column_fields['mobile_number'] = $adb->query_result($result,0,'cf_xretailer_mobile_no');
                            $focus->column_fields['email_id'] = $adb->query_result($result,0,'cf_xretailer_email');
                            $focus->column_fields['purchase_zone'] = $adb->query_result($result,0,'cf_xretailer_geography');
                            $focus->column_fields['conveted_retailer_id'] = $transID;
                            $focus->column_fields['status'] = 'Published';
                            $focus->column_fields['creater_id'] = $current_user->id;
                            $disid = getDistrIDbyUserID();
                            $focus->column_fields['distributor_id'] = $disid['id'];
                            $focus->save('xUniversal');  
                            $update = "update vtiger_xuniversal set conveted_retailer_id=?,status=? where xuniversalid=?";
                            $qparams = array($transID,'Published',$focus->id);
                            $adb->pquery($update,$qparams);

                            $Qryrelation = "insert into `vtiger_crmentityrel` (`crmid`, `module`, `relcrmid`, `relmodule`)VALUES('".$focus->id."','xUniversal','".$transID."','xRetailer')";            
                            $resultrelation = $adb->pquery($Qryrelation);

                        }else{ 
                            if($adb->num_rows($custrealignresult1)==0){
                                $update = "update vtiger_xuniversal set customer_name=?,address=?,state=?,postal_code=?,stockist_zone=?,contact_person=?,purchase_zone=?,phone_number=?,mobile_number=?,email_id=? where conveted_retailer_id=?";
                                $qparams = array($adb->query_result($result,0,'customername'),$adb->query_result($result,0,'cf_xretailer_address_1'),$adb->query_result($result,0,'cf_xretailer_state'),$adb->query_result($result,0,'cf_xretailer_pin_code'),$adb->query_result($result,0,'cf_xretailer_supply_chain_distributor'),$adb->query_result($result,0,'cf_xretailer_contact_person'),$adb->query_result($result,0,'cf_xretailer_geography'),$adb->query_result($result,0,'cf_xretailer_phone'),$adb->query_result($result,0,'cf_xretailer_mobile_no'),$adb->query_result($result,0,'cf_xretailer_email'),$transID);
                                $adb->pquery($update,$qparams);   
                            }else{
                                 $oldcutid = $adb->query_result($custrealignresult1,0,'cust_id');
                                 $to_dist  = $adb->query_result($custrealignresult1,0,'to_dist');
                                 $custrealignupdate = "update vtiger_xuniversal set conveted_retailer_id=?,distributor_id=? where conveted_retailer_id=?";
                                 $qparams3 = array($transID,$to_dist, $oldcutid);
                                 $adb->pquery($custrealignupdate,$qparams3);
                            }
                        }
                    
                }
             
            }elseif($module == "distributor" || $module=="xDistributor"){
                $Qry = "SELECT dcf.*,d.* FROM vtiger_xdistributorcf dcf LEFT JOIN vtiger_xdistributor d on d.xdistributorid=dcf.xdistributorid WHERE dcf.xdistributorid=".$transID;            
                $result = $adb->pquery($Qry);
                
                $Qry1 = "SELECT * FROM vtiger_xuniversal WHERE vtiger_xuniversal.conveted_distributor_id=".$transID;            
                $result1 = $adb->pquery($Qry1);
                
                if($adb->num_rows($result1)==0){                     
                    $focus->column_fields['customer_name'] = $adb->query_result($result,0,'distributorname');
                    $focus->column_fields['customer_code'] = $customer_code;
                    $focus->column_fields['address'] = $adb->query_result($result,0,'cf_xdistributor_street');
                    $focus->column_fields['state'] = $adb->query_result($result,0,'cf_xdistributor_state');
                    $focus->column_fields['postal_code'] = $adb->query_result($result,0,'cf_xdistributor_pin_code');                   
                    $focus->column_fields['contact_person'] = $adb->query_result($result,0,'cf_xdistributor_contact_person');
                    $focus->column_fields['purchase_zone'] = $adb->query_result($result,0,'cf_xdistributor_geography');
                    $focus->column_fields['phone_number'] = $adb->query_result($result,0,'cf_xdistributor_phone');
                    $focus->column_fields['email_id'] = $adb->query_result($result,0,'cf_xdistributor_email');
                    $focus->column_fields['customer_category'] = $adb->query_result($result,0,'cf_xdistributor_supply_chain');
                    $focus->column_fields['conveted_distributor_id'] = $transID;
                    $focus->column_fields['status'] = 'Published';
                    $focus->column_fields['creater_id'] = $current_user->id;
                    $disid = getDistrIDbyUserID();
                    $focus->column_fields['distributor_id'] = $disid['id'];                    
                      $focus->save('xUniversal');  
                    $update = "update vtiger_xuniversal set conveted_distributor_id=?,status=? where xuniversalid=?";
                    $qparams = array($transID,'Published',$focus->id);
                    $adb->pquery($update,$qparams); 
                    
                    $Qryrelation = "insert into `vtiger_crmentityrel` (`crmid`, `module`, `relcrmid`, `relmodule`)VALUES('".$focus->id."','xUniversal','".$transID."','xDistributor')";            
                    $resultrelation = $adb->pquery($Qryrelation);
                    
                }else{
                    $update = "update vtiger_xuniversal set customer_name=?,address=?,state=?,postal_code=?,contact_person=?,customer_category=?,purchase_zone=?,phone_number=?,email_id=? where conveted_distributor_id=?";
                    $qparams = array($adb->query_result($result,0,'distributorname'),$adb->query_result($result,0,'cf_xdistributor_street'),$adb->query_result($result,0,'cf_xdistributor_state'),$adb->query_result($result,0,'cf_xdistributor_pin_code'),$adb->query_result($result,0,'cf_xdistributor_contact_person'),$adb->query_result($result,0,'cf_xdistributor_supply_chain'),$adb->query_result($result,0,'cf_xdistributor_geography'),$adb->query_result($result,0,'cf_xdistributor_phone'),$adb->query_result($result,0,'cf_xdistributor_email'),$transID);
                    $adb->pquery($update,$qparams);
                }
                
            } 
            return $focus->id; 
        }
        

function customercodedupchk($codeval,$codemaster)
{  
                    global $adb;
                    if($codemaster=='xUniversal')
                    {            
                    $Qrycount = "SELECT customer_code from vtiger_xuniversal where customer_code='".$codeval."'";
                    $resultcount = $adb->pquery($Qrycount);
                             //echo 'query1:<pre>'; print_r($resultcount);
                    $rescount = $adb->num_rows($resultcount);
                        if($rescount>0)
                        {
                            $Qrysql = "SELECT prefix, cur_id from vtiger_modentity_num where semodule ='xUniversal' and active=1";
                            $resultsql = $adb->pquery($Qrysql);
                            //echo 'query1:<pre>'; print_r($resultsql);
                            $prefix=$adb->query_result($resultsql,0,'prefix');                   
                            $resultcode=$adb->query_result($resultcount,0,'customer_code');
                            $stripcodeval = str_replace(trim($prefix),"",trim($resultcode));
                            $rescountval = $stripcodeval+1;
                            $customer_code = $prefix.$rescountval;  //die;
                            customercodedupchk($customer_code,'xUniversal');
                        }
                        else
                        {
                            $customer_code=$codeval;                
                        }
                    }
                    elseif($codemaster=='xRetailer')
                    {          
                    $Qrycount = "SELECT customercode from vtiger_xretailer where customercode='".$codeval."'";
                    $resultcount = $adb->pquery($Qrycount);
                        if($adb->num_rows($resultcount)>0)
                        {
                            $Qrysql = "SELECT prefix, cur_id from vtiger_modentity_num where semodule ='xRetailer' and active=1";
                            $resultsql = $adb->pquery($Qrysql);
                            $prefix=$adb->query_result($resultsql,0,'prefix');                     

                            $resultcode=$adb->query_result($resultcount,0,'customercode');
                            $stripcodeval = str_replace(trim($prefix),"",trim($resultcode));
                            // $rescount = $adb->num_rows($resultcount)+1;
                            $rescount = $stripcodeval+1;
                            $customer_code = $prefix.$rescount;    
                            customercodedupchk($customer_code,'xRetailer');
                        }
                        else
                        {
                            $customer_code = $codeval;    
                        }            
                    }              
            return $customer_code;
}        
        
        function getOpenStockAdjustment() {
		global $adb;
                $dist =  getDistrIDbyUserID();
                $distid = $dist['id'];
		$ret = 0;
		$query = "SELECT mt.stockopeningid
FROM vtiger_stockopeningcf mt 
LEFT JOIN vtiger_crmentity ct ON mt.stockopeningid=ct.crmid 
WHERE ct.deleted=0 AND mt.cf_stockopening_buyer_id=".$distid;
		$result = $adb->pquery($query);
                $ret = $adb->num_rows($result);
	        return $ret;
	}
        function getOpenStockOpeningCount() {
		global $adb;
                $dist =  getDistrIDbyUserID();
                $distid = $dist['id'];
		$ret = 0;
                /*$query ="SELECT sl.id
                FROM vtiger_stocklots sl
                WHERE sl.distributorcode=".$distid;// Issue Fixed based on FRPRDINXT-2544*/
                $query ="SELECT stcf.cf_stockopening_distributor_id 
                        FROM vtiger_stockopening stop
                        INNER JOIN vtiger_crmentity ct ON stop.stockopeningid = ct.crmid
                        INNER JOIN vtiger_stockopeningcf stcf ON stop.stockopeningid = stcf.stockopeningid 
                        WHERE  ct.deleted=0 AND stcf.cf_stockopening_distributor_id =".$distid;                
		$result = $adb->pquery($query);
                $ret = $adb->num_rows($result);
	        return $ret;
	}
        
        function getCurrentStatus($id,$modName,$tableType,$statusName) {
            global $adb;
            $idname = ($module == 'xSalesOrder' ? 'salesorderid' : strtolower($modName)."id");
            $table = ($tableType=="custom" ? strtolower($modName)."cf" : strtolower($modName));
            $query = "SELECT * FROM vtiger_".$table." WHERE $statusName=1 AND $idname=".$id;
            $result = $adb->pquery($query);
            $ret = $adb->num_rows($result);
            return $ret;
        }
        
        function checkFieldModuleRel($module) {
            global $adb;
            $query = "SELECT * FROM `vtiger_fieldmodulerel` WHERE `relmodule` = '".$module."'";
            $result = $adb->pquery($query);
            for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
            return $ret;
        }
        
        function getLineItemID($module,$transID) {
            global $adb;
            $rl = getTables($module);  
             $Qry = "SELECT lineitem_id FROM ".$rl['productrelTable']." WHERE ".$rl['productrelIndex']."=".$transID;
             $result = $adb->pquery($Qry);
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
        }
        //ADDED BY GOWTHAMAN.M
        function getLineItemIDPITOGRN($module,$transID) {
            global $adb;
            //$rl = getTables($module);  
             $dist=  getDistrIDbyUserID();
                
		 $query="select  lineitem_id " .
				" from vtiger_piproductrel" .
                                " inner join vtiger_purchaseinvoice on vtiger_purchaseinvoice.purchaseinvoiceid=vtiger_piproductrel.id " .
				" left join vtiger_xproduct on vtiger_xproduct.xproductid=vtiger_piproductrel.productid " .
				" left join vtiger_xproductcf on vtiger_xproductcf.xproductid=vtiger_xproduct.xproductid " .
				" left join vtiger_service on vtiger_service.serviceid=vtiger_piproductrel.productid " .
				" left join vtiger_uom on vtiger_uom.uomid=vtiger_piproductrel.tuom" .
                                " left join vtiger_xtransaction_batchinfo as bi on (bi.trans_line_id=vtiger_piproductrel.lineitem_id and 
                                  bi.transaction_id=vtiger_piproductrel.id)" .
				" left join (SELECT  distributorcode,productid,IFNULL(batchnumber,'') as `batchnumber`,IFNULL(pkg,'') as pkg,IFNULL(expiry,'') as expiry,(SUM(IFNULL(salable_qty,0)) - SUM(IFNULL(sold_salable_qty,0))) as `mq`,(SUM(IFNULL(free_qty,0))  - SUM(IFNULL(sold_free_qty,0))) as `fq` FROM vtiger_stocklots 
GROUP BY distributorcode,batchnumber,pkg,expiry) as iq on iq.batchnumber=bi.batch_no AND bi.pkd=iq.pkg AND iq.expiry=bi.expiry AND iq.distributorcode='".$dist['id']."'" .
                        	" left join vtiger_stockposition on (vtiger_stockposition.productid=vtiger_xproduct.xproductid
                                     and vtiger_stockposition.location_id=vtiger_purchaseinvoice.pi_godown and vtiger_stockposition.distributorcode='".$dist['code']."')".                             
				" where vtiger_piproductrel.id=?  and bi.transaction_type='PI' and  vtiger_piproductrel.bal_qty >0 group by vtiger_piproductrel.lineitem_id ORDER BY sequence_no"; // vtiger_piproductrel.baseqty >= vtiger_piproductrel.grnqty  for pi to grn convertion product balance qty >0 only display FRPRDINXT-2342

             $result = $adb->pquery($query,array($transID));
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
        }
        //END
        
        function getUOMnameByID($id) {
            global $adb; 
            if(!($id > 0))
                return "";
            
            $query = "SELECT uomname FROM vtiger_uom WHERE uomid=".$id;
             $result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret = $adb->raw_query_result_rowdata($result,$index);
		}
            return $ret['uomname'];
        }
        function getProductByField($field,$fieldID) {
           global $adb;
//           $Qry = "SELECT TMCF.cf_xtaxmapping_product FROM vtiger_xtaxmapping TM
//                LEFT JOIN vtiger_xtaxmappingcf TMCF ON TMCF.xtaxmappingid = TM.xtaxmappingid
//                LEFT JOIN vtiger_crmentity CRM ON CRM.crmid = TM.xtaxmappingid
//                WHERE CRM.deleted = 0
//                AND TMCF.cf_xtaxmapping_active = 1
//                AND TMCF.".$field." =" .$fieldID;
           $Qry = "SELECT xproductid FROM vtiger_xproductcf 
                LEFT JOIN vtiger_crmentity ct ON vtiger_xproductcf.xproductid=ct.crmid 
                WHERE $field=".$fieldID." AND cf_xproduct_active=1 AND ct.deleted=0";           
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
       }
       
       function getTransType($id) {
           global $adb;
           $Qry = "SELECT cf_xtransactionseries_transaction_type FROM `vtiger_xtransactionseriescf` WHERE xtransactionseriesid=".$id;
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret[0]['cf_xtransactionseries_transaction_type'];
       }
       
       
       function getBankName($id) {
           global $adb;
           $Qry = "SELECT bankmastername,bankmasterbranch FROM `vtiger_xbankmaster` WHERE xbankmasterid=".$id;
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret[0]['bankmastername']." ".$ret[0]['bankmasterbranch'];
       }
       
       function getDistBankList($id) {
            session_start();
          global $adb;
          global $current_user;
           $Qry = "SELECT b.xbankmasterid,b.bankmastername,b.bankmasterbranch FROM `vtiger_xbankmaster` b LEFT JOIN vtiger_crmentity ct ON b.xbankmasterid=ct.crmid LEFT JOIN vtiger_xbankmastercf bcf ON b.xbankmasterid=bcf.xbankmasterid  WHERE bcf.cf_xbankmaster_status=1 and b.cf_xbankmaster_user_id=".$_SESSION["authenticated_user_id"];
           $result = $adb->pquery($Qry);
           $ret = "";
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret .= "<option value='".$adb->query_result($result,$index,'xbankmasterid')."'>".$adb->query_result($result,$index,'bankmastername')." ".$adb->query_result($result,$index,'bankmasterbranch')."</option>";
	     }
             return $ret;
       }
       
       function getDistAccount(){
           session_start();
          global $adb;
          global $current_user;
         // echo "===>".$_SESSION["authenticated_user_id"];
           $Qry = "SELECT b.xbankaccountid,b.bankaccountnumber FROM `vtiger_xbankaccount` b LEFT JOIN vtiger_crmentity ct ON b.xbankaccountid=ct.crmid LEFT JOIN vtiger_xbankaccountcf bcf ON b.xbankaccountid=bcf.xbankaccountid  WHERE bcf.cf_xbankaccount_status=1 and b.cf_xbankaccount_user_id=".$_SESSION["authenticated_user_id"];
           $result = $adb->pquery($Qry);
           $ret = "";
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret .= "<option value='".$adb->query_result($result,$index,'xbankaccountid')."'>".$adb->query_result($result,$index,'bankaccountnumber')."</option>";
	     }
             return $ret; 
       }
       
       
       function getTaxMappingByField($fieldID) {
           global $adb;
           $Qry = "SELECT xtaxmappingid FROM vtiger_xtaxmappingcf  
                LEFT JOIN vtiger_crmentity ct ON vtiger_xtaxmappingcf.xtaxmappingid=ct.crmid 
                WHERE (cf_xtaxmapping_purchase_tax=".$fieldID." OR cf_xtaxmapping_sales_tax=".$fieldID.") AND cf_xtaxmapping_active=1 AND ct.deleted=0";
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
       }
       
       function getState($fieldID) {
           global $adb;
           $Qry = " SELECT  vtiger_xstate.* FROM vtiger_xstate left join vtiger_xstatecf on vtiger_xstate.xstateid=vtiger_xstatecf.xstateid   
                LEFT JOIN vtiger_crmentity ct ON vtiger_xstate.xstateid=ct.crmid 
                WHERE vtiger_xstatecf.xstateid=".$fieldID." and vtiger_xstatecf.cf_xstate_active=1 AND ct.deleted=0";
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
       }
       
       function getCreditlimit($tot,$type) {
           global $adb, $PI_LBL_VENDOR_CREDIT_LIMIT_POPUP, $LBL_VENDOR_CREDIT_LIMIT_POPUP;
           $DistrID = getDistrIDbyUserID(); 
           $Qry = " select sum(vtiger_purchaseorder.total) as cl from vtiger_purchaseordercf LEFT JOIN vtiger_purchaseorder on vtiger_purchaseorder.purchaseorderid=vtiger_purchaseordercf.purchaseorderid where vtiger_purchaseorder.status NOT IN('Published','Draft') and vtiger_purchaseordercf.cf_purchaseorder_buyer_id=".$DistrID['id'];
           $result = $adb->pquery($Qry);
           $creditwithpo=$adb->query_result($result,0,'cl');
           $Qry2 = " select sum(vtiger_purchaseinvoicecf.cf_purchaseinvoice_outstanding) as p from vtiger_purchaseinvoicecf LEFT JOIN vtiger_purchaseinvoice on vtiger_purchaseinvoicecf.purchaseinvoiceid=vtiger_purchaseinvoice.purchaseinvoiceid  where vtiger_purchaseinvoice.status NOT IN('Published','Draft') and vtiger_purchaseinvoicecf.cf_purchaseinvoice_buyer_id=".$DistrID['id'];
           $result2 = $adb->pquery($Qry2);
           $creditofpi=$adb->query_result($result2,0,'p');
           $creditwithpo=$adb->query_result($result,0,'cl')+$creditofpi;
           $Qry1 = " select cf_xdistributor_credit_limit from vtiger_xdistributorcf where xdistributorid=".$DistrID['id'];
           $result1 = $adb->pquery($Qry1);
           $creditlimit=$adb->query_result($result1,0,'cf_xdistributor_credit_limit');
           
          // $ret[] = round($creditlimit, 2);
          // $ret[] = round($creditwithpo, 2);
          // $ret[] = round($creditofpi, 2);
           if($type == "po"){
             $totadd  = $tot;
             $totpi = 0;
           }else{
              $totadd  = $tot; 
              $totpi = $tot;
           }
           $balancechk = round($creditlimit, 2) - (round($creditwithpo, 2)+$totadd);
            if($balancechk < 0){
                    
         //echo '<script type="text/javascript">$j.jAlert("You are Alredy cross the Creditlimit\n Your Creditlimit = '. $Creditlimit[0] .' \\n\n Current Balance = '. $Creditlimit[1] .'","error",function(){});</script>';
             $ret= '<input type="hidden" id="climit" name="climit" value="'.round($creditlimit, 2).'" />
                    <input type="hidden" id="withoutpo" name="withoutpo" value="'.(round($creditofpi, 2)+$totpi).'" />
                    <input type="hidden" id="withpo" name="withpo" value="'.(round($creditwithpo, 2)+$totadd).'" />  
                    <input type="hidden" id="checklimit" name="checklimit" value="1" />';
             if(($PI_LBL_VENDOR_CREDIT_LIMIT_POPUP == 'True' && $type == "pi") || ($LBL_VENDOR_CREDIT_LIMIT_POPUP == 'True' && $type == "po"))
                 $ret .= '<script type="text/javascript">ShowBoxWithConfirm('.round($creditlimit, 2).','.(round($creditwithpo, 2)+$totadd).','.(round($creditofpi, 2)+$totpi).');</script>';
             }else{
                  $ret= '<input type="hidden" id="climit" name="climit" value="'.round($creditlimit, 2).'" />
                    <input type="hidden" id="withoutpo" name="withoutpo" value="'.round($creditofpi, 2).'" />
                    <input type="hidden" id="checklimit" name="checklimit" value="0" />
                    <input type="hidden" id="withpo" name="withpo" value="'.(round($creditwithpo, 2)+$totadd).'" />';
             }
           
         return $ret;
       }
       
       function getAllDistCluster() {
           global $adb,$current_user;
           $Qry = "SELECT mt.xdistributorclusterid,mt.distributorclustername,mt.distributorclustercode FROM vtiger_xdistributorcluster  mt 
		   LEFT JOIN vtiger_crmentity ct ON mt.xdistributorclusterid=ct.crmid 
		   INNER JOIN vtiger_xdistributorclustercf ON vtiger_xdistributorclustercf.xdistributorclusterid = mt.xdistributorclusterid
			INNER JOIN vtiger_xdistributorclusterrel ON vtiger_xdistributorclusterrel.distclusterid = mt.xdistributorclusterid 
			WHERE ct.deleted=0 AND mt.active=1";
           $Qry.=" AND vtiger_xdistributorclustercf.cf_xdistributorcluster_status = 'Approved'";
			if($current_user->is_admin!='on' && $current_user->is_sadmin!='on'){
				//$pqry = "SELECT GROUP_CONCAT(cf.xdistributorid SEPARATOR ', ') distid FROM vtiger_xdistributorcf cf WHERE cf.cf_xdistributor_active=1";
				//$geoid = $current_user->geography_hierarchy;   
				//$listbuyer = getgeoid($pqry,$geoid); 
				$pqry = "SELECT GROUP_CONCAT(vtiger_xcpdpmappingcf.cf_xcpdpmapping_distributor SEPARATOR ',') as distid
						FROM vtiger_xcpdpmappingcf INNER JOIN vtiger_xcpdpmapping ON
						vtiger_xcpdpmappingcf.xcpdpmappingid = vtiger_xcpdpmapping.xcpdpmappingid
						where vtiger_xcpdpmapping.cpusers=".$current_user->id;
				$result = $adb->pquery($pqry);
				if($adb->num_rows($result)>0){
					$dis_ids = $adb->query_result($result,0,'distid');
				}else{
					$dis_ids = 0;
				}
				$clusterquery.="SELECT GROUP_CONCAT(distclusterid SEPARATOR ',') as clusterid FROM vtiger_xdistributorclusterrel WHERE distributorid IN (".$dis_ids.") ";
				$cresult = $adb->pquery($clusterquery);
				if($adb->num_rows($cresult)>0){
					$clusterid = $adb->query_result($cresult,0,'clusterid');
				}else{
					$clusterid = 0;
				}
			//	echo "dis_ids=".$dis_ids."--".$clusterid;
				$Qry.=" AND mt.xdistributorclusterid IN(".$clusterid.") group by mt.xdistributorclusterid";
			}
                        else
                        {            
                            $Qry.=" GROUP BY mt.xdistributorclusterid";
                        }
            
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
       }
       
    function getAllChannelHierarchy() {
        global $adb;
           $Qry = "SELECT mt.xchannelhierarchyid,mt.channel_hierarchy,st.cf_xchannelhierarchy_channel_hierarchy_path,mt.channelhierarchycode FROM vtiger_xchannelhierarchy  mt,vtiger_crmentity ct ,vtiger_xchannelhierarchycf st 
WHERE mt.xchannelhierarchyid=ct.crmid AND mt.xchannelhierarchyid=st.xchannelhierarchyid AND ct.deleted=0 AND cf_xchannelhierarchy_active = 1"; 
           //echo $Qry;
           $result = $adb->pquery($Qry);
           $ret = array();
            for ($index = 0; $index < $adb->num_rows($result); $index++) {
				$ret[] = $adb->raw_query_result_rowdata($result,$index);
			}
        return $ret;
    }    
       
       function getAllGeneralClass() {
           global $adb;
           $Qry = "SELECT mt.xgeneralclassificationid,mt.generalclassdescription,mt.generalclasscode FROM vtiger_xgeneralclassification  mt,vtiger_crmentity ct 
WHERE mt.xgeneralclassificationid=ct.crmid AND ct.deleted=0";
        //   echo $Qry;
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
       }     
       
       function getAllvalueclass($getvalues='') {
           global $adb;
           //$Qry = "SELECT mt.xvalueclassificationid,mt.valueclassdescription,mt.valueclasscode FROM vtiger_xvalueclassification  mt,vtiger_crmentity ct WHERE mt.xvalueclassificationid=ct.crmid AND ct.deleted=0";
        //   echo $Qry;
          $Qry = " SELECT mt.xvalueclassificationid AS id,mt.valueclassdescription AS name,mt.valueclasscode AS code FROM vtiger_xvalueclassification  mt,vtiger_crmentity ct 
WHERE mt.xvalueclassificationid=ct.crmid AND ct.deleted=0 ";
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
       }  
       function getAllPotentialClass() {
           global $adb;
           $Qry = "SELECT mt.xpotentialclassificationid,mt.potentialclassdesc,mt.potentialclasscode FROM vtiger_xpotentialclassification  mt,vtiger_crmentity ct 
WHERE mt.xpotentialclassificationid=ct.crmid AND ct.deleted=0 AND active = 1 ";
        //   echo $Qry;
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
       }   
       
       function getAllCustomerGroup() {
           global $adb;
           $Qry = "SELECT mt.xcustomergroupid,mt.customergroupname,mt.customergroupcode,st.cf_xcustomergroup_customer_group_type FROM vtiger_xcustomergroup  mt,vtiger_crmentity ct ,vtiger_xcustomergroupcf st
WHERE mt.xcustomergroupid=ct.crmid AND ct.deleted=0 AND mt.xcustomergroupid=st.xcustomergroupid AND st.cf_xcustomergroup_active=1 AND st.cf_xcustomergroup_customer_group_type='Retailer'";
        //   echo $Qry;
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
       } 

       function getAllProdCatGroup($cluster) {
           global $adb;
           $Qry=''; $Qry1='';
           
           if($cluster=='' || $cluster==null)
           {
           $Qry = "SELECT mt.xcategorygroupid as id,mt.categorygroupname as name,mt.categorygroupcode,mt.categorygrouptype FROM vtiger_xcategorygroup  mt
                   LEFT JOIN vtiger_crmentity ct on ct.crmid=mt.xcategorygroupid
                   WHERE ct.deleted=0 and mt.active=1 and mt.categorygrouptype='Product'";     
           }
           else
           { 
            $Qry1 = "SELECT mt.productcategorygroup FROM vtiger_xproductcategorygroupmapping  mt
                   LEFT JOIN vtiger_crmentity ct on ct.crmid=mt.xproductcategorygroupmappingid
                   WHERE ct.deleted=0 and mt.active=1 and FIND_IN_SET(mt.distributorcluster, '".$cluster."') group by mt.productcategorygroup";               

           $result1 = $adb->pquery($Qry1);
           $ret = array();
           $retcusgp = array();
             for ($index1 = 0; $index1 < $adb->num_rows($result1); $index1++) {
	        //$retcusgp[] = $adb->raw_query_result_rowdata($result1,$index1);
                 $retcombine.=$adb->query_result($result1,$index1, 'productcategorygroup').',';
	     }            
              $retcombine = substr($retcombine,0,-1);
           $Qry = "SELECT mt.xcategorygroupid as id,mt.categorygroupname as name,mt.categorygroupcode,mt.categorygrouptype FROM vtiger_xcategorygroup  mt
                   LEFT JOIN vtiger_crmentity ct on ct.crmid=mt.xcategorygroupid
                   WHERE ct.deleted=0 and mt.active=1 and mt.categorygrouptype='Product' and FIND_IN_SET(mt.xcategorygroupid, '".$retcombine."')";               
           }
          //echo "Hi :".$Qry;
           /*$result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;*/
                $result = $adb->pquery($Qry,array());
                $emptycheck='';
                $countval=$adb->num_rows($result);
                if($countval>0)
                {
                  $emptycheck=$countval;              
                }
                for ($index = 0; $index < $adb->num_rows($result); $index++) {       
                    $Arr = $adb->raw_query_result_rowdata($result,$index);
                    $ret[$Arr['id']] = $Arr['name'];
                   // $ret[$Arr['categorygroupcode']] = $Arr['categorygroupcode'];
                   // $ret[$Arr['categorygrouptype']] = $Arr['categorygrouptype'];
                }
                if($emptycheck=='')
                {
                   $ret=''; 
                }
               return $ret;          
       }         
       
       function getAlleditProdCatGroup($cluster=null) {
           global $adb;

            $Qry1 = "SELECT mt.productcategorygroup FROM vtiger_xproductcategorygroupmapping  mt
                   LEFT JOIN vtiger_crmentity ct on ct.crmid=mt.xproductcategorygroupmappingid
                   WHERE ct.deleted=0 and mt.active=1 and FIND_IN_SET(mt.distributorcluster, '".$cluster."') group by mt.productcategorygroup";               

           $result1 = $adb->pquery($Qry1);
           $ret = array();
           $retcusgp = array();
             for ($index1 = 0; $index1 < $adb->num_rows($result1); $index1++) {
	        //$retcusgp[] = $adb->raw_query_result_rowdata($result1,$index1);
                 $retcombine.=$adb->query_result($result1,$index1, 'productcategorygroup').',';
	     }            
              $retcombine = substr($retcombine,0,-1);
           $Qry = "SELECT mt.xcategorygroupid as id,mt.categorygroupname as name,mt.categorygroupcode,mt.categorygrouptype FROM vtiger_xcategorygroup  mt
                   LEFT JOIN vtiger_crmentity ct on ct.crmid=mt.xcategorygroupid
                   WHERE ct.deleted=0 and mt.active=1 and mt.categorygrouptype='Product' and FIND_IN_SET(mt.xcategorygroupid, '".$retcombine."')";                    
           
          // return $Qry;
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
               /* $result = $adb->pquery($Qry,array());
                $emptycheck='';
                $countval=$adb->num_rows($result);
                if($countval>0)
                {
                  $emptycheck=$countval;              
                }
                for ($index = 0; $index < $adb->num_rows($result); $index++) {       
                    $Arr = $adb->raw_query_result_rowdata($result,$index);
                    $ret[$Arr['id']] = $Arr['name'];
                   // $ret[$Arr['categorygroupcode']] = $Arr['categorygroupcode'];
                   // $ret[$Arr['categorygrouptype']] = $Arr['categorygrouptype'];
                }
                if($emptycheck=='')
                {
                   $ret=''; 
                }
               return $ret;   */       
       }     
       
       function getdetailviewincentive($selectedid) {           
           global $adb;
           $ret = array();
           $BaseQry = "SELECT mt.xdistributorclusterid FROM  vtiger_xdistributorcluster_mrel  mt 
                       WHERE mt.relmodule='xIncentiveSetting' AND mt.crmid=".$selectedid; 
           $result1 = $adb->pquery($BaseQry);
             for ($index = 0; $index < $adb->num_rows($result1); $index++) {
                 $qrydistid.= $adb->query_result($result1,$index,'xdistributorclusterid').',';                  
	     }
             $qrydistid = substr($qrydistid,0,-1);
           $BaseQry2 = "SELECT mt.distributorclustername FROM  vtiger_xdistributorcluster  mt 
                       WHERE   FIND_IN_SET(mt.xdistributorclusterid, '".$qrydistid."')";
           $result2 = $adb->pquery($BaseQry2);
             for ($index2 = 0; $index2 < $adb->num_rows($result2); $index2++) {
                 $qrydistname.= $adb->query_result($result2,$index2,'distributorclustername').',';
	     }  
             $distname  = substr($qrydistname ,0,-1);
             $ret['distname']=$distname;             
           
           $addQry = "SELECT mt.xcustomergroupid FROM  vtiger_xcustomergroup_mrel  mt 
                       WHERE mt.relmodule='xIncentiveSetting' AND mt.crmid=".$selectedid; 
           $result3 = $adb->pquery($addQry);
             for ($index3 = 0; $index3 < $adb->num_rows($result3); $index3++) {
                 $qrycatid.= $adb->query_result($result3,$index3,'xcustomergroupid').',';                  
	     }
             $qrycatid = substr($qrycatid,0,-1);
           $BaseQry4 = "SELECT mt.categorygroupname FROM  vtiger_xcategorygroup  mt 
                       WHERE   FIND_IN_SET(mt.xcategorygroupid, '".$qrycatid."')";
           $result4 = $adb->pquery($BaseQry4);
             for ($index4 = 0; $index4 < $adb->num_rows($result4); $index4++) {
                 $qrycatname.= $adb->query_result($result4,$index4,'categorygroupname').',';
	     }  
             $catname  = substr($qrycatname ,0,-1);
             $ret['catname']=$catname;                
             
             
             return $ret;
       }            
       
       function getAllSalesCatGroup() {
           global $adb;
           $Qry = "SELECT mt.xcategorygroupid,mt.categorygroupname,mt.categorygroupcode,mt.categorygrouptype FROM vtiger_xcategorygroup  mt
                   LEFT JOIN vtiger_crmentity ct on ct.crmid=mt.xcategorygroupid
                   WHERE ct.deleted=0 and mt.active=1 and mt.categorygrouptype='Salesman'";           
        //   echo $Qry;
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
       }        
       
       function getAllHierDetails() {
           global $adb;
           $Qry = "SELECT mt.xprodhiermetaid AS `id`,mt.levelname AS `name`       
                    FROM vtiger_xprodhiermeta mt 
                    LEFT JOIN vtiger_crmentity ct ON mt.xprodhiermetaid=ct.crmid 
                    LEFT JOIN vtiger_xprodhiermetacf cf ON mt.xprodhiermetaid=cf.xprodhiermetaid 
                    WHERE ct.deleted=0 AND cf.cf_xprodhiermeta_active=1";
        //   echo $Qry;
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
       }        
       function getAllBillingMode() {
           global $adb;
           $Qry = "SELECT mt.xcollectionmethodid,st.cf_xcollectionmethod_collection_type FROM vtiger_xcollectionmethod  mt,vtiger_crmentity ct ,vtiger_xcollectionmethod st
WHERE mt.xcollectionmethodid=ct.crmid AND ct.deleted=0 AND mt.xcollectionmethodid=st.xcollectionmethodid";
        //   echo $Qry;
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
       }
       function getAllRetailer(){
           global $adb;
           $Qry = "SELECT ret.xretailerid,ret.customername, ret.customercode, vtiger_xdistributor.distributorcode FROM vtiger_xretailer  ret 
               inner join vtiger_crmentity ct on ret.xretailerid=ct.crmid 
               inner join vtiger_xretailercf ret_cf on ret.xretailerid=ret_cf.xretailerid 
               inner join vtiger_xdistributor on vtiger_xdistributor.xdistributorid = ret_cf.cf_xretailer_supply_chain_distributor 
               inner join vtiger_xdistributorcf on vtiger_xdistributor.xdistributorid = vtiger_xdistributorcf.xdistributorid  
               inner join vtiger_crmentity ct_dist on vtiger_xdistributor.xdistributorid=ct_dist.crmid 
               WHERE ct.deleted=0 AND ret_cf.cf_xretailer_active=1 AND ct_dist.deleted=0  AND vtiger_xdistributorcf.cf_xdistributor_active=1 
               group by ret.xretailerid order by vtiger_xdistributor.distributorcode, ret.customercode";
        //   echo $Qry;
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
           
       }
 
       function getallschememultiselval($selectedid) {
           global $adb;
           $Qry = "SELECT xcf.scheme_distributor_cluster,xcf.retailer_channel_hierarchy,xcf.retailer_general_classification,xcf.retailer_value_classification,
                   xcf.retailer_potential_classification,xcf.retailer_customer_group,xcf.retailer_billing_mode ,xcf.cf_xscheme_effective_from,xcf.cf_xscheme_effective_to 
                   FROM vtiger_xscheme xs,vtiger_xschemecf xcf WHERE xs.xschemeid='$selectedid' and xs.xschemeid=xcf.xschemeid";
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $rethead = $adb->raw_query_result_rowdata($result,$index);
	     }
             
            foreach($rethead as $key=>$val) 
            {
                if($key=='scheme_distributor_cluster')
                $distributorselval=$val;
                if($key=='retailer_channel_hierarchy')
                $channelhierarchyval=$val;
                if($key=='retailer_general_classification')
                $generalclassval=$val;
                if($key=='retailer_value_classification')
                $valueclassval=$val;
                if($key=='retailer_potential_classification')
                $potentialclassval=$val;
                if($key=='retailer_customer_group')
                $customergroupval=$val;
                if($key=='retailer_billing_mode')
                $billingmodeval=$val;  
                if($key=='cf_xscheme_effective_from')
                $ef_fromdate=$val;  
                if($key=='cf_xscheme_effective_to')
                $ef_todate=$val;  
            }          
            
            $explodedistval=  explode(',', $distributorselval);
            $explodechannval=  explode(',', $channelhierarchyval);
            $explodegeneralclassval=  explode(',', $generalclassval);
            $explodevaluclassval=  explode(',', $valueclassval);
            $explodepotentilaclassval=  explode(',', $potentialclassval);
            $explodecustomergroupval=  explode(',', $customergroupval);
            $explodebillingmodenval=  explode(',', $billingmodeval);    
            
            $getexplodedistval=  "'" . implode("','", $explodedistval) . "'";
            $getexplodechannval=  "'" . implode("','", $explodechannval) . "'";
            $getgeneralclassval=  "'" . implode("','", $explodegeneralclassval) . "'";
            $getvaluclassval=  "'" . implode("','", $explodevaluclassval) . "'";
            $getpotentilaclassval=  "'" . implode("','", $explodepotentilaclassval) . "'";
            $getcustomergroupval=  "'" . implode("','", $explodecustomergroupval) . "'";
            $getbillingmodenval=  "'" . implode("','", $explodebillingmodenval) . "'";             
            
           $Qry2 = "SELECT mt.distributorclustername,mt.distributorclustercode FROM vtiger_xdistributorcluster  mt 
                   LEFT JOIN vtiger_crmentity ct ON mt.xdistributorclusterid=ct.crmid 
                   WHERE ct.deleted=0 AND mt.active=1 AND mt.xdistributorclusterid in (".$getexplodedistval.")";
           $result2 = $adb->pquery($Qry2);
           $ret2 = array();
             for ($index = 0; $index < $adb->num_rows($result2); $index++) {
	        //$ret2[] = $adb->raw_query_result_rowdata($result2,$index);
                 $shmdistcluster.= $adb->query_result($result2,$index,'distributorclustername').',';
	     }  
             
           $Qry3 = "SELECT mt.channel_hierarchy,st.cf_xchannelhierarchy_channel_hierarchy_path,mt.channelhierarchycode 
                    FROM vtiger_xchannelhierarchy  mt,vtiger_crmentity ct ,vtiger_xchannelhierarchycf st 
                    WHERE mt.xchannelhierarchyid=ct.crmid AND mt.xchannelhierarchyid=st.xchannelhierarchyid 
                    AND ct.deleted=0 AND mt.xchannelhierarchyid in (".$getexplodechannval.")";
           $result3 = $adb->pquery($Qry3);
           $ret3 = array();
             for ($index = 0; $index < $adb->num_rows($result3); $index++) {
	        //$ret3[] = $adb->raw_query_result_rowdata($result3,$index);
                 $shmchannelhier.= $adb->query_result($result3,$index,'channel_hierarchy').',';
	     } 
             
           $Qry4 = "SELECT mt.xgeneralclassificationid,mt.generalclassdescription,mt.generalclasscode 
                    FROM vtiger_xgeneralclassification  mt,vtiger_crmentity ct 
                    WHERE mt.xgeneralclassificationid=ct.crmid AND ct.deleted=0
                    AND mt.xgeneralclassificationid in (".$getgeneralclassval.")";
           
           $result4 = $adb->pquery($Qry4);
           $ret4 = array();
             for ($index = 0; $index < $adb->num_rows($result4); $index++) {
	        //$ret4[] = $adb->raw_query_result_rowdata($result4,$index);
                 $shmgeneralclass.= $adb->query_result($result4,$index,'generalclassdescription').',';
	     } 
           $Qry5 = "SELECT mt.xvalueclassificationid AS id,mt.valueclassdescription AS name,mt.valueclasscode AS code 
                    FROM vtiger_xvalueclassification  mt,vtiger_crmentity ct 
                    WHERE mt.xvalueclassificationid=ct.crmid AND ct.deleted=0 AND mt.xvalueclassificationid in (".$getvaluclassval.")";
           $result5 = $adb->pquery($Qry5);
           $ret5 = array();
             for ($index = 0; $index < $adb->num_rows($result5); $index++) {
	        //$ret5[] = $adb->raw_query_result_rowdata($result5,$index);
                 $shmvalueclass.= $adb->query_result($result5,$index,'name').',';
	     } 
             
           $Qry6 = "SELECT mt.xpotentialclassificationid,mt.potentialclassdesc,mt.potentialclasscode 
                    FROM vtiger_xpotentialclassification  mt,vtiger_crmentity ct 
                    WHERE mt.xpotentialclassificationid=ct.crmid AND ct.deleted=0 AND mt.xpotentialclassificationid in (".$getpotentilaclassval.")";
           $result6 = $adb->pquery($Qry6);
           $ret6 = array();
             for ($index = 0; $index < $adb->num_rows($result6); $index++) {
	        //$ret6[] = $adb->raw_query_result_rowdata($result6,$index);
                 $shmpotenclass.= $adb->query_result($result6,$index,'potentialclassdesc').',';
	     }  
             
           $Qry7 = "SELECT mt.xcustomergroupid,mt.customergroupname,mt.customergroupcode,st.cf_xcustomergroup_customer_group_type 
                    FROM vtiger_xcustomergroup  mt,vtiger_crmentity ct ,vtiger_xcustomergroupcf st
                    WHERE mt.xcustomergroupid=ct.crmid AND ct.deleted=0 AND mt.xcustomergroupid=st.xcustomergroupid 
                    AND st.cf_xcustomergroup_active=1 AND st.cf_xcustomergroup_customer_group_type='Retailer' 
                    AND mt.xcustomergroupid in (".$getcustomergroupval.")";
           $result7 = $adb->pquery($Qry7);
           $ret7 = array();
             for ($index = 0; $index < $adb->num_rows($result7); $index++) {
	        //$ret7[] = $adb->raw_query_result_rowdata($result7,$index);
                 $shmcustomergroup.= $adb->query_result($result7,$index,'customergroupname').',';
	     }                
            
           $Qry8 = "SELECT mt.xcollectionmethodid,st.cf_xcollectionmethod_collection_type 
                    FROM vtiger_xcollectionmethod  mt,vtiger_crmentity ct ,vtiger_xcollectionmethod st
                    WHERE mt.xcollectionmethodid=ct.crmid AND ct.deleted=0 AND mt.xcollectionmethodid=st.xcollectionmethodid 
                    AND mt.xcollectionmethodid in (".$getbillingmodenval.")";
           $result8 = $adb->pquery($Qry8);
           $ret8 = array();
             for ($index = 0; $index < $adb->num_rows($result8); $index++) {
	        //$ret8[] = $adb->raw_query_result_rowdata($result8,$index);
                 $shmbillingmode.= $adb->query_result($result8,$index,'cf_xcollectionmethod_collection_type').',';
	     }     
             $ret['shmdistcluster']=substr($shmdistcluster,0,-1);
             $ret['shmchannelhier']=substr($shmchannelhier,0,-1);
             $ret['shmgeneralclass']=substr($shmgeneralclass,0,-1);
             $ret['shmvalueclass']=substr($shmvalueclass,0,-1);
             $ret['shmpotenclass']=substr($shmpotenclass,0,-1);
             $ret['shmcustomergroup']=substr($shmcustomergroup,0,-1);
             $ret['shmbillingmode']=substr($shmbillingmode,0,-1);
          return $ret;
       }        
       
       function getalldigitalmultiselval($selectedid) {
           global $adb;
           $Qry = "SELECT xdistributorclusterid AS digital_cluster,xdistributorid AS digital_distirbutor,xchannelhierarchyid AS retailer_channel_hierarchy,xgeneralclassificationid AS retailer_general_classification,xvalueclassificationid AS retailer_value_classification,xpotentialclassificationid AS retailer_potential_classification,xcustomergroupid AS retailer_customer_group FROM vtiger_xdigitalcontent WHERE xdigitalcontentid='$selectedid'";
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $rethead = $adb->raw_query_result_rowdata($result,$index);
	     }
             
            foreach($rethead as $key=>$val) 
            {
                if($key=='digital_distirbutor')
                $distributor=$val;   
                if($key=='digital_cluster')
                $distributorselval=$val;
                if($key=='retailer_channel_hierarchy')
                $channelhierarchyval=$val;
                if($key=='retailer_general_classification')
                $generalclassval=$val;
                if($key=='retailer_value_classification')
                $valueclassval=$val;
                if($key=='retailer_potential_classification')
                $potentialclassval=$val;
                if($key=='retailer_customer_group')
                $customergroupval=$val;
                if($key=='cf_xscheme_effective_from')
                $ef_fromdate=$val;  
                if($key=='cf_xscheme_effective_to')
                $ef_todate=$val;  
            }          
            
            $distributorval =  explode(',', $distributor);
            $explodedistval =  explode(',', $distributorselval);
            $explodechannval=  explode(',', $channelhierarchyval);
            $explodegeneralclassval=  explode(',', $generalclassval);
            $explodevaluclassval=  explode(',', $valueclassval);
            $explodepotentilaclassval=  explode(',', $potentialclassval);
            $explodecustomergroupval=  explode(',', $customergroupval);

            
            $getexplodedistributor =  "'" . implode("','", $distributorval) . "'";
            $getexplodedistval=  "'" . implode("','", $explodedistval) . "'";
            $getexplodechannval=  "'" . implode("','", $explodechannval) . "'";
            $getgeneralclassval=  "'" . implode("','", $explodegeneralclassval) . "'";
            $getvaluclassval=  "'" . implode("','", $explodevaluclassval) . "'";
            $getpotentilaclassval=  "'" . implode("','", $explodepotentilaclassval) . "'";
            $getcustomergroupval=  "'" . implode("','", $explodecustomergroupval) . "'";
            
            $Qry2 = "SELECT vtiger_xdistributor.distributorname,vtiger_xdistributor.distributorcode FROM vtiger_xdistributor   
                   LEFT JOIN vtiger_crmentity ON vtiger_xdistributor.xdistributorid=vtiger_crmentity.crmid 
                   WHERE vtiger_crmentity.deleted=0 AND vtiger_xdistributor.xdistributorid in (".$getexplodedistributor.")";
           $result2 = $adb->pquery($Qry2);
           $ret2 = array();
             for ($index = 0; $index < $adb->num_rows($result2); $index++) {
	        //$ret2[] = $adb->raw_query_result_rowdata($result2,$index);
                 $shmdistdistributor.= $adb->query_result($result2,$index,'distributorname').',';
	     }  
            
           $Qry2 = "SELECT mt.distributorclustername,mt.distributorclustercode FROM vtiger_xdistributorcluster  mt 
                   LEFT JOIN vtiger_crmentity ct ON mt.xdistributorclusterid=ct.crmid 
                   WHERE ct.deleted=0 AND mt.active=1 AND mt.xdistributorclusterid in (".$getexplodedistval.")";
           $result2 = $adb->pquery($Qry2);
           $ret2 = array();
             for ($index = 0; $index < $adb->num_rows($result2); $index++) {
	        //$ret2[] = $adb->raw_query_result_rowdata($result2,$index);
                 $shmdistcluster.= $adb->query_result($result2,$index,'distributorclustername').',';
	     }  
             
           $Qry3 = "SELECT mt.channel_hierarchy,st.cf_xchannelhierarchy_channel_hierarchy_path,mt.channelhierarchycode 
                    FROM vtiger_xchannelhierarchy  mt,vtiger_crmentity ct ,vtiger_xchannelhierarchycf st 
                    WHERE mt.xchannelhierarchyid=ct.crmid AND mt.xchannelhierarchyid=st.xchannelhierarchyid 
                    AND ct.deleted=0 AND mt.xchannelhierarchyid in (".$getexplodechannval.")";
           $result3 = $adb->pquery($Qry3);
           $ret3 = array();
             for ($index = 0; $index < $adb->num_rows($result3); $index++) {
	        //$ret3[] = $adb->raw_query_result_rowdata($result3,$index);
                 $shmchannelhier.= $adb->query_result($result3,$index,'channel_hierarchy').',';
	     } 
             
           $Qry4 = "SELECT mt.xgeneralclassificationid,mt.generalclassdescription,mt.generalclasscode 
                    FROM vtiger_xgeneralclassification  mt,vtiger_crmentity ct 
                    WHERE mt.xgeneralclassificationid=ct.crmid AND ct.deleted=0
                    AND mt.xgeneralclassificationid in (".$getgeneralclassval.")";
           
           $result4 = $adb->pquery($Qry4);
           $ret4 = array();
             for ($index = 0; $index < $adb->num_rows($result4); $index++) {
	        //$ret4[] = $adb->raw_query_result_rowdata($result4,$index);
                 $shmgeneralclass.= $adb->query_result($result4,$index,'generalclassdescription').',';
	     } 
           $Qry5 = "SELECT mt.xvalueclassificationid AS id,mt.valueclassdescription AS name,mt.valueclasscode AS code 
                    FROM vtiger_xvalueclassification  mt,vtiger_crmentity ct 
                    WHERE mt.xvalueclassificationid=ct.crmid AND ct.deleted=0 AND mt.xvalueclassificationid in (".$getvaluclassval.")";
           $result5 = $adb->pquery($Qry5);
           $ret5 = array();
             for ($index = 0; $index < $adb->num_rows($result5); $index++) {
	        //$ret5[] = $adb->raw_query_result_rowdata($result5,$index);
                 $shmvalueclass.= $adb->query_result($result5,$index,'name').',';
	     } 
             
           $Qry6 = "SELECT mt.xpotentialclassificationid,mt.potentialclassdesc,mt.potentialclasscode 
                    FROM vtiger_xpotentialclassification  mt,vtiger_crmentity ct 
                    WHERE mt.xpotentialclassificationid=ct.crmid AND ct.deleted=0 AND mt.xpotentialclassificationid in (".$getpotentilaclassval.")";
           $result6 = $adb->pquery($Qry6);
           $ret6 = array();
             for ($index = 0; $index < $adb->num_rows($result6); $index++) {
	        //$ret6[] = $adb->raw_query_result_rowdata($result6,$index);
                 $shmpotenclass.= $adb->query_result($result6,$index,'potentialclassdesc').',';
	     }  
             
           $Qry7 = "SELECT mt.xcustomergroupid,mt.customergroupname,mt.customergroupcode,st.cf_xcustomergroup_customer_group_type 
                    FROM vtiger_xcustomergroup  mt,vtiger_crmentity ct ,vtiger_xcustomergroupcf st
                    WHERE mt.xcustomergroupid=ct.crmid AND ct.deleted=0 AND mt.xcustomergroupid=st.xcustomergroupid 
                    AND st.cf_xcustomergroup_active=1 AND st.cf_xcustomergroup_customer_group_type='Retailer' 
                    AND mt.xcustomergroupid in (".$getcustomergroupval.")";
           $result7 = $adb->pquery($Qry7);
           $ret7 = array();
             for ($index = 0; $index < $adb->num_rows($result7); $index++) {
	        //$ret7[] = $adb->raw_query_result_rowdata($result7,$index);
                 $shmcustomergroup.= $adb->query_result($result7,$index,'customergroupname').',';
	     }                
            
             $ret['distributors']=substr($shmdistdistributor,0,-1);
             $ret['shmdistcluster']=substr($shmdistcluster,0,-1);
             $ret['shmchannelhier']=substr($shmchannelhier,0,-1);
             $ret['shmgeneralclass']=substr($shmgeneralclass,0,-1);
             $ret['shmvalueclass']=substr($shmvalueclass,0,-1);
             $ret['shmpotenclass']=substr($shmpotenclass,0,-1);
             $ret['shmcustomergroup']=substr($shmcustomergroup,0,-1);

          return $ret;
       }      
       
 function getallmultiselvalfrommerltables($selectedid,$currentModule) {
      global $adb;
    
           $Qry2 = "SELECT GROUP_CONCAT(mt.distributorclustername SEPARATOR ',') as distributorclustername,
                   GROUP_CONCAT(mt.distributorclustercode SEPARATOR ',') as distributorclustercode FROM vtiger_xdistributorcluster  mt 
                   LEFT JOIN vtiger_crmentity ct ON mt.xdistributorclusterid=ct.crmid 
                   LEFT JOIN vtiger_xdistributorcluster_mrel cmrel ON cmrel.xdistributorclusterid=mt.xdistributorclusterid
                   WHERE ct.deleted=0 AND mt.active=1 AND cmrel.relmodule='".$currentModule."' AND cmrel.crmid =".$selectedid;
          $result2 = $adb->pquery($Qry2);              
           //echo "<pre>";print_r($result2);
           $shmdistcluster = $adb->query_result($result2,0,'distributorclustername');            

           $Qry3 = "SELECT GROUP_CONCAT(mt.channel_hierarchy SEPARATOR ',') as channel_hierarchy,
                    GROUP_CONCAT(st.cf_xchannelhierarchy_channel_hierarchy_path SEPARATOR ',') as cf_xchannelhierarchy_channel_hierarchy_path,
                    GROUP_CONCAT(mt.channelhierarchycode SEPARATOR ',') as channelhierarchycode 
                    FROM vtiger_xchannelhierarchy  mt
                    LEFT JOIN vtiger_crmentity ct ON mt.xchannelhierarchyid=ct.crmid 
                    LEFT JOIN vtiger_xchannelhierarchycf st ON mt.xchannelhierarchyid=st.xchannelhierarchyid
                    LEFT JOIN vtiger_xchannelhier_mrel cmrel ON cmrel.xchannelhierarchyid=mt.xchannelhierarchyid
                    WHERE ct.deleted=0 AND cmrel.relmodule='".$currentModule."' AND cmrel.crmid =".$selectedid;
           $result3 = $adb->pquery($Qry3);
           $shmchannelhier= $adb->query_result($result3,0,'channel_hierarchy');

             
           $Qry4 = "SELECT GROUP_CONCAT(mt.xgeneralclassificationid SEPARATOR ',') as xgeneralclassificationid,GROUP_CONCAT(mt.generalclassdescription SEPARATOR ',') as generalclassdescription,
                    GROUP_CONCAT(mt.generalclasscode SEPARATOR ',') as generalclasscode 
                    FROM vtiger_xgeneralclassification mt 
                    LEFT JOIN vtiger_crmentity ct ON mt.xgeneralclassificationid=ct.crmid
                    LEFT JOIN vtiger_xgeneralclass_mrel cmrel ON cmrel.xgeneralclassificationid=mt.xgeneralclassificationid
                    WHERE ct.deleted=0 AND cmrel.relmodule='".$currentModule."' AND cmrel.crmid =".$selectedid;
           
           $result4 = $adb->pquery($Qry4);
           $shmgeneralclass= $adb->query_result($result4,0,'generalclassdescription');
 
           $Qry5 = "SELECT GROUP_CONCAT(mt.xvalueclassificationid SEPARATOR ',') AS xvalueclassificationid,GROUP_CONCAT(mt.valueclassdescription SEPARATOR ',') AS name,
                    GROUP_CONCAT(mt.valueclasscode SEPARATOR ',') AS valueclasscode 
                    FROM vtiger_xvalueclassification  mt 
                    LEFT JOIN vtiger_crmentity ct ON mt.xvalueclassificationid=ct.crmid
                    LEFT JOIN vtiger_xvalueclass_mrel cmrel ON cmrel.xvalueclassificationid=mt.xvalueclassificationid                    
                    WHERE mt.xvalueclassificationid=ct.crmid AND ct.deleted=0 AND cmrel.relmodule='".$currentModule."' AND cmrel.crmid =".$selectedid;
           
           $result5 = $adb->pquery($Qry5);
           //echo "<pre>";print_r($result5);
           $ret5 = array();
             for ($index = 0; $index < $adb->num_rows($result5); $index++) {
	        //$ret5[] = $adb->raw_query_result_rowdata($result5,$index);
                 $shmvalueclass.= $adb->query_result($result5,$index,'name').',';
	     } 
             
           $Qry6 = "SELECT GROUP_CONCAT(mt.xpotentialclassificationid SEPARATOR ',') as xpotentialclassificationid,GROUP_CONCAT(mt.potentialclassdesc SEPARATOR ',') as potentialclassdesc,
                    GROUP_CONCAT(mt.potentialclasscode SEPARATOR ',') as potentialclasscode 
                    FROM vtiger_xpotentialclassification  mt   
                    LEFT JOIN vtiger_crmentity ct ON mt.xpotentialclassificationid=ct.crmid 
                    LEFT JOIN vtiger_xpotentialclass_mrel cmrel ON cmrel.xpotentialclassificationid=mt.xpotentialclassificationid                    
                    WHERE ct.deleted=0 AND cmrel.relmodule='".$currentModule."' AND cmrel.crmid =".$selectedid;
           $result6 = $adb->pquery($Qry6);
           $ret6 = array();
             for ($index = 0; $index < $adb->num_rows($result6); $index++) {
	        //$ret6[] = $adb->raw_query_result_rowdata($result6,$index);
                 $shmpotenclass.= $adb->query_result($result6,$index,'potentialclassdesc').',';
	     }  
             
           $Qry7 = "SELECT GROUP_CONCAT(mt.xcustomergroupid SEPARATOR ',') as xcustomergroupid,GROUP_CONCAT(mt.customergroupname SEPARATOR ',') as customergroupname,
                    GROUP_CONCAT(mt.customergroupcode SEPARATOR ',') as customergroupcode,GROUP_CONCAT(st.cf_xcustomergroup_customer_group_type  SEPARATOR ',') as cf_xcustomergroup_customer_group_type 
                    FROM vtiger_xcustomergroup  mt 
                    INNER JOIN vtiger_xcustomergroupcf st ON mt.xcustomergroupid = st.xcustomergroupid
                    LEFT JOIN vtiger_crmentity ct ON mt.xcustomergroupid=ct.crmid 
                    LEFT JOIN vtiger_xcustomergroup_mrel cmrel ON cmrel.xcustomergroupid=mt.xcustomergroupid                      
                    WHERE mt.xcustomergroupid=ct.crmid AND ct.deleted=0 
                    AND st.cf_xcustomergroup_active=1 AND st.cf_xcustomergroup_customer_group_type='Retailer' 
                    AND cmrel.relmodule='".$currentModule."' AND cmrel.crmid =".$selectedid;
                    //,vtiger_crmentity ct ,vtiger_xcustomergroupcf st
           $result7 = $adb->pquery($Qry7);
           $ret7 = array();
             for ($index = 0; $index < $adb->num_rows($result7); $index++) {
	        //$ret7[] = $adb->raw_query_result_rowdata($result7,$index);
                 $shmcustomergroup.= $adb->query_result($result7,$index,'customergroupname').',';
	     }                
            
          /* $Qry8 = "SELECT mt.xcollectionmethodid,st.cf_xcollectionmethod_collection_type 
                    FROM vtiger_xcollectionmethod  mt,vtiger_crmentity ct ,vtiger_xcollectionmethod st
                    WHERE mt.xcollectionmethodid=ct.crmid AND ct.deleted=0 AND mt.xcollectionmethodid=st.xcollectionmethodid 
                    AND mt.xcollectionmethodid in (".$getbillingmodenval.")";
           $result8 = $adb->pquery($Qry8);
           $ret8 = array();
             for ($index = 0; $index < $adb->num_rows($result8); $index++) {
	        //$ret8[] = $adb->raw_query_result_rowdata($result8,$index);
                 $shmbillingmode.= $adb->query_result($result8,$index,'cf_xcollectionmethod_collection_type').',';
	     }   */ 
            $Qry8 = "SELECT mt.xprodhierid,mt.prodhiername,mt.prodhiercode 
                    FROM vtiger_xprodhier mt
                    LEFT JOIN vtiger_crmentity ct ON mt.xprodhierid=ct.crmid 
                    LEFT JOIN vtiger_xproducthier_mrel cmrel ON cmrel.xprodhierid=mt.xprodhierid
                    WHERE mt.xprodhierid=ct.crmid AND ct.deleted=0  
                    AND cmrel.relmodule='".$currentModule."' AND cmrel.crmid =".$selectedid;
             
              $result8 = $adb->pquery($Qry8);
              $ret8 = array();
             for ($index = 0; $index < $adb->num_rows($result8); $index++) {
	        //$ret8[] = $adb->raw_query_result_rowdata($result8,$index);
                $shmbillingmode.= $adb->query_result($result8,$index,'prodhiername').',';
	     }   
             
             
            $Qry9 = "SELECT levelname from vtiger_xprodhiermeta meta 
                         INNER JOIN 				
                        (SELECT cf_xprodhier_level FROM vtiger_xprodhiercf mt
                        LEFT JOIN vtiger_crmentity ct ON mt.xprodhierid=ct.crmid 
                        LEFT JOIN vtiger_xproducthier_mrel cmrel ON cmrel.xprodhierid=mt.xprodhierid
                        WHERE mt.xprodhierid=ct.crmid AND ct.deleted=0 AND cmrel.relmodule='".$currentModule."' AND cmrel.crmid ='".$selectedid."') as t1 ON
                        meta.xprodhiermetaid = t1.cf_xprodhier_level";
            $result9 = $adb->pquery($Qry9); 
            $ret9 = array();
            $shmproducthierlevel.= $adb->query_result($result9,$index[0],'levelname');
            
             

             $ret['shmdistcluster']=(substr($shmdistcluster, -1, 1) == ',' ? substr($shmdistcluster,0,-1) : $shmdistcluster);
             $ret['shmchannelhier']=(substr($shmchannelhier, -1, 1) == ',' ? substr($shmchannelhier,0,-1) : $shmchannelhier);
             $ret['shmgeneralclass']=(substr($shmgeneralclass, -1, 1) == ',' ? substr($shmgeneralclass,0,-1) : $shmgeneralclass);
             $ret['shmvalueclass']=(substr($shmvalueclass, -1, 1) == ',' ? substr($shmvalueclass,0,-1) : $shmvalueclass);
             $ret['shmpotenclass']=(substr($shmpotenclass, -1, 1) == ',' ? substr($shmpotenclass,0,-1) : $shmpotenclass);
             $ret['shmcustomergroup']=(substr($shmcustomergroup, -1, 1) == ',' ? substr($shmcustomergroup,0,-1) : $shmcustomergroup);
            $ret['shmbillingmode']=(substr($shmbillingmode, -1, 1) == ',' ? substr($shmbillingmode,0,-1) : $shmbillingmode);
            $ret['shmproducthierlevel']=(substr($shmproducthierlevel, -1, 1) == ',' ? substr($shmproducthierlevel,0,-1) : $shmproducthierlevel);

          return $ret;     
     
 }       
       
       function getselectedvalues($selectedid) {
           global $adb;
           $Qry = "SELECT xcf.scheme_distributor_cluster,xcf.retailer_channel_hierarchy,xcf.retailer_general_classification,xcf.retailer_value_classification,
                   xcf.retailer_potential_classification,xcf.retailer_customer_group,xcf.retailer_billing_mode ,xcf.cf_xscheme_effective_from,xcf.cf_xscheme_effective_to 
                   FROM vtiger_xscheme xs,vtiger_xschemecf xcf WHERE xs.xschemeid='$selectedid' and xs.xschemeid=xcf.xschemeid";
          // echo $Qry;
          // exit;
           $result = $adb->pquery($Qry);
          // $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
       } 
       
       function getselectedDigitalval($selectedid) {
           global $adb;
           $Qry = "SELECT xdistributorclusterid AS digital_cluster,xdistributorid AS digital_distirbutor,xchannelhierarchyid AS retailer_channel_hierarchy,xgeneralclassificationid AS retailer_general_classification,xvalueclassificationid AS retailer_value_classification,xpotentialclassificationid AS retailer_potential_classification,xcustomergroupid AS retailer_customer_group FROM vtiger_xdigitalcontent WHERE xdigitalcontentid='$selectedid'";
           //echo $Qry;
          // exit;
           $result = $adb->pquery($Qry);
          // $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
       }
       
       function getFocusProdvalues($selectedid) {
           global $adb;
           $Qry = "SELECT xfocusproductid FROM vtiger_xfpm_rel_focus_prod WHERE xfocusproductmappingid='$selectedid'";
          // echo $Qry;
          // exit;
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->query_result($result,$index, 'xfocusproductid');
	     }
             return $ret;
       } 
       
       function getFocusProdvaluesMapCluster($selectedid) {
           global $adb;
           $Qry = "SELECT xdistributorclusterid FROM vtiger_xfpm_rel_cluster WHERE xfocusproductmappingid='$selectedid'";
          // echo $Qry;
          // exit;
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->query_result($result,$index, 'xdistributorclusterid');
	     }
             return $ret;
       }
       
       function getFocusProductMapppingClusterDetail($selectedid) {
           global $adb;
           $Qry = "SELECT distributorclustername FROM vtiger_xfpm_rel_cluster 
               Inner join vtiger_xdistributorcluster on vtiger_xdistributorcluster.xdistributorclusterid=vtiger_xfpm_rel_cluster.xdistributorclusterid 
               Inner join vtiger_xdistributorclustercf on vtiger_xdistributorclustercf.xdistributorclusterid=vtiger_xdistributorcluster.xdistributorclusterid  
               WHERE vtiger_xfpm_rel_cluster.xfocusproductmappingid='$selectedid' AND vtiger_xdistributorcluster.active=1";
           //echo $Qry;exit;
          // exit;
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->query_result($result,$index, 'distributorclustername');
	     }
             return $ret;
       }
       
       function getFocusProductMapppingFPDetail($selectedid) {
           global $adb;
           $Qry = "SELECT focus_product_description FROM vtiger_xfpm_rel_focus_prod  
               Inner join vtiger_xfocusproduct on vtiger_xfocusproduct.xfocusproductid=vtiger_xfpm_rel_focus_prod.xfocusproductid 
               Inner join vtiger_xfocusproductcf on vtiger_xfocusproductcf.xfocusproductid=vtiger_xfocusproduct.xfocusproductid  
               WHERE vtiger_xfpm_rel_focus_prod.xfocusproductmappingid='$selectedid' AND vtiger_xfocusproduct.active=1";
          // echo $Qry;
          // exit;
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->query_result($result,$index, 'focus_product_description');
	     }
             return $ret;
       }
       
       function getAllFocusProd(){
           global $adb;
           $Qry = "SELECT xs.xfocusproductid, xs.focus_product_description, xs.focus_product_code  
                   FROM vtiger_xfocusproduct xs Inner Join vtiger_xfocusproductcf xcf on xs.xfocusproductid=xcf.xfocusproductid 
                   WHERE xs.active=1";
          // echo $Qry;
          // exit;
           $result = $adb->pquery($Qry);
           $ret = array();
           for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	   }
           return $ret;
           
       }
       
       function getdistclustermultiselect($selectedid,$currentModule) {
           global $adb;
           $Qry = "SELECT dcm.xdistributorclusterid
                   FROM vtiger_xdistributorcluster_mrel dcm 
                   WHERE dcm.crmid='$selectedid' and dcm.relmodule='$currentModule'";
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        //$ret[] = $adb->raw_query_result_rowdata($result,$index);
                 $ret[] = $adb->query_result($result,$index,'xdistributorclusterid');                  
	     }
             return $ret;
       }     
       
       function getchannelmultiselect($selectedid,$currentModule) {
           global $adb;
           $Qry = "SELECT cm.xchannelhierarchyid
                   FROM vtiger_xchannelhier_mrel cm 
                   WHERE cm.crmid='$selectedid' and cm.relmodule='$currentModule'";
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
                 $ret[] = $adb->query_result($result,$index,'xchannelhierarchyid');                  
	     }
             return $ret;
       }      
       
       function getgenclassmultiselect($selectedid,$currentModule) {
           global $adb;
           $Qry = "SELECT gcm.xgeneralclassificationid
                   FROM vtiger_xgeneralclass_mrel gcm 
                   WHERE gcm.crmid='$selectedid' and gcm.relmodule='$currentModule'";
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
                 $ret[] = $adb->query_result($result,$index,'xgeneralclassificationid');                  
	     }
             return $ret;
       }        
       
       function getvalclassmultiselect($selectedid,$currentModule) {
           global $adb;
           $Qry = "SELECT vcm.xvalueclassificationid
                   FROM vtiger_xvalueclass_mrel vcm 
                   WHERE vcm.crmid='$selectedid' and vcm.relmodule='$currentModule'";
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        //$ret[] = $adb->raw_query_result_rowdata($result,$index);
                 $ret[] = $adb->query_result($result,$index,'xvalueclassificationid');                  
	     }
             return $ret;
       }   
       
       function getpotclassmultiselect($selectedid,$currentModule) {
           global $adb;
           $Qry = "SELECT pcm.xpotentialclassificationid
                   FROM vtiger_xpotentialclass_mrel pcm 
                   WHERE pcm.crmid='$selectedid' and pcm.relmodule='$currentModule'";
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        //$ret[] = $adb->raw_query_result_rowdata($result,$index);
                 $ret[] = $adb->query_result($result,$index,'xpotentialclassificationid');                  
	     }
             return $ret;
       }    
       
       function getcustgroupmultiselect($selectedid,$currentModule) {
           global $adb;
           $Qry = "SELECT cgm.xcustomergroupid
                   FROM vtiger_xcustomergroup_mrel cgm 
                   WHERE cgm.crmid='$selectedid' and cgm.relmodule='$currentModule'";
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        //$ret[] = $adb->raw_query_result_rowdata($result,$index);
                 $ret[] = $adb->query_result($result,$index,'xcustomergroupid');                  
	     }
             return $ret;
       }   
       
       function getprodhierlevelvalmultiselect($selectedid,$currentModule) {
           global $adb;
           $Qry = "SELECT phm.xprodhierid,phm.xprodhiermetaid
                   FROM vtiger_xproducthier_mrel phm 
                   WHERE phm.crmid='$selectedid' and phm.relmodule='$currentModule'";
           //echo 'query::'.$Qry;
          // exit;
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        //$ret[] = $adb->raw_query_result_rowdata($result,$index);
                 $ret[] = $adb->query_result($result,$index,'xprodhierid'); 
	     }
             return $ret;
       }     
       
       function getprodhierlevelnameselect($selectedid,$currentModule) {
           global $adb;
           $Qry = "SELECT phm.xprodhiermetaid
                   FROM vtiger_xproducthier_mrel phm 
                   WHERE phm.crmid='$selectedid' and phm.relmodule='$currentModule' group by phm.xprodhiermetaid";
           //echo 'query::'.$Qry;
          // exit;
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        //$ret[] = $adb->raw_query_result_rowdata($result,$index);
                 $ret[] = $adb->query_result($result,$index,'xprodhiermetaid');
	     }
             return $ret;
       }        
             
       
       function getprodcatgpmul($selectedid,$currentModule) {
           global $adb;
           $Qry = "SELECT pcm.xproductcatgpid
                   FROM vtiger_xproductcatgp_mrel pcm 
                   WHERE pcm.crmid='$selectedid' and pcm.relmodule='$currentModule' group by pcm.xproductcatgpid";
           //echo 'query::'.$Qry;
          // exit;
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        //$ret[] = $adb->raw_query_result_rowdata($result,$index);
                 $ret[] = $adb->query_result($result,$index,'xproductcatgpid');
	     }
             return $ret;
       }    
       
       
       function getsalesmancatgpmul($selectedid,$currentModule) {
           global $adb;
           $Qry = "SELECT smcg.xsalesmancatgpid
                   FROM vtiger_xsalesmancatgp_mrel smcg 
                   WHERE smcg.crmid='$selectedid' and smcg.relmodule='$currentModule' group by smcg.xsalesmancatgpid";
           //echo 'query::'.$Qry;
          // exit;
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        //$ret[] = $adb->raw_query_result_rowdata($result,$index);
                 $ret[] = $adb->query_result($result,$index,'xsalesmancatgpid');
	     }
             return $ret;
       }        
             
       
       function getprodcatgpmulvalname($selectedid,$currentModule) {
           global $adb;
			
			$Qry2 = "SELECT GROUP_CONCAT(pcm.xproductcatgpid SEPARATOR ',') as xproductcatgpidconcat 
                   FROM vtiger_xproductcatgp_mrel pcm 
                   WHERE pcm.crmid='$selectedid' and pcm.relmodule='$currentModule'";
            $resultrvc = $adb->pquery($Qry2);      
            $totprodcatlist = $adb->query_result($resultrvc,0,'xproductcatgpidconcat');         
			if ( (strlen($totprodcatlist) == 0) || ($totprodcatlist == '0') || ($totprodcatlist == 'null') ){
				$totprodcatlist = 0;
			}
           // echo 'prod car::'.$totprodcatlist.'<br>';
		  $Qry = "SELECT GROUP_CONCAT(cgv.categorygroupname SEPARATOR ',') as xproductcategorygroupname
                   FROM vtiger_xcategorygroup cgv
                   WHERE cgv.categorygrouptype='Product' and cgv.xcategorygroupid in (".$totprodcatlist.")";                
           //echo 'query::'.$Qry;
          // exit;
//           $result = $adb->pquery($Qry);
//           $ret = array();
//             for ($index = 0; $index < $adb->num_rows($result); $index++) {
//	        //$ret[] = $adb->raw_query_result_rowdata($result,$index);
//                 $ret[] = $adb->query_result($result,$index,'xproductcatgpid');
//	     }
            
            $resultset = $adb->pquery($Qry);                
            $totprodcatlistname = $adb->query_result($resultset,0,'xproductcategorygroupname');               
             return $totprodcatlistname;
       } 
       
       function getsalesmancatgpmulvalname($selectedid,$currentModule) {
           global $adb;
           $Qry2 = "SELECT GROUP_CONCAT(smm.xsalesmancatgpid SEPARATOR ',') as xsalesmancatgpidconcat 
                   FROM vtiger_xsalesmancatgp_mrel smm 
                   WHERE smm.crmid='$selectedid' and smm.relmodule='$currentModule'";
            $resulsalesmantrvc = $adb->pquery($Qry2);                
            $totsalesmancatlist = $adb->query_result($resulsalesmantrvc,0,'xsalesmancatgpidconcat');    
          if ((strlen($totsalesmancatlist) == 0) || ($totsalesmancatlist == '0') || ($totsalesmancatlist == 'null') ){
				$totsalesmancatlist = 0;
			}
           $Qry = "SELECT GROUP_CONCAT(cgv.categorygroupname SEPARATOR ',') as xsalesmancategorygroupname
                   FROM vtiger_xcategorygroup cgv
                   WHERE cgv.categorygrouptype='Salesman' and cgv.xcategorygroupid in (".$totsalesmancatlist.")";        
           //echo 'query::'.$Qry;
          // exit;
//           $result = $adb->pquery($Qry);
//           $ret = array();
//             for ($index = 0; $index < $adb->num_rows($result); $index++) {
//	        //$ret[] = $adb->raw_query_result_rowdata($result,$index);
//                 $ret[] = $adb->query_result($result,$index,'xsalesmancatgpid');
//	     }
            $resultset = $adb->pquery($Qry);                
            $totsalesmancatlistname = $adb->query_result($resultset,0,'xsalesmancategorygroupname');               
             return $totsalesmancatlistname;
       }        
       
       function getVanLoadRel($id) {
           global $adb;
           $Qry = "SELECT * FROM vtiger_xvanloadingprodrel
               INNER JOIN vtiger_xproduct ON vtiger_xvanloadingprodrel.productcode = vtiger_xproduct.xproductid 
               LEFT JOIN vtiger_xvanloadingprodrelcf ON vtiger_xvanloadingprodrel.xvanloadingprodrelid = vtiger_xvanloadingprodrelcf.xvanloadingprodrelid 
WHERE vtiger_xvanloadingprodrel.vanloadingid=$id";
          // echo $Qry;
          // exit;
           $result = $adb->pquery($Qry);
           $ret = $resultss= array();
		   $productidarr = $line_item_idarr = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
			 $productid = $adb->query_result($result,$index,'xproductid');
			 $line_item_id = $adb->query_result($result,$index,'xvanloadingprodrelid');
			 array_push($productidarr,$productid);
                         if(!empty($line_item_id)){
			 array_push($line_item_idarr,$line_item_id);
                         }
	        $ret[$index] = $adb->raw_query_result_rowdata($result,$index);
			
	     }
		 $combineprodid = implode(',',array_unique($productidarr));
		 $comblineid = implode(',',array_unique($line_item_idarr));
                                  
                 if($comblineid!='' && $combineprodid!='' && $comblineid!=null && $combineprodid!=null)
                 {
			$res_ssi = $adb->pquery('select ssi.serialnumber, ssi.stock_type, ssi.trans_line_id from vtiger_xsalestransaction_serialinfo ssi 
			 join vtiger_xbatch_transfer_info bt on(bt.trans_lineid = ssi.trans_line_id and bt.product_id = ssi.product_id) 
			 where ssi.trans_line_id  in ('.$comblineid.')  and ssi.product_id in ('.$combineprodid.') and bt.transaction_type = ? and ssi.transaction_type=?' ,array('Van Loading','VL'));			 
			while($results = $adb->fetch_array($res_ssi)) :
				$serialkey[$results['trans_line_id']][] = array($results['serialnumber'], $results['stock_type'], $results['trans_line_id']);
			endwhile;
			$ret['serialkey'] = $serialkey;
                 }
            return $ret;
       }
       
       
       function getBilllist($id) {
           global $adb;
//             $Qry = "SELECT vtiger_salesinvoice.salesinvoiceid, vtiger_xretailer.customername,vtiger_salesinvoicecf.cf_salesinvoice_transaction_number,vtiger_salesinvoicecf.cf_salesinvoice_sales_invoice_date,vtiger_salesinvoice.salesinvoice_no,vtiger_salesinvoice.total,vtiger_salesinvoicecf.cf_salesinvoice_outstanding,vtiger_xmcollectiondetails.*,vtiger_xmcollectionadjustments.* FROM vtiger_xmcollectiondetails
//                    LEFT JOIN vtiger_xmcollectionadjustments ON vtiger_xmcollectiondetails.xmcollectionid = vtiger_xmcollectionadjustments.xmcollectionid 
//                    LEFT JOIN vtiger_xretailer ON vtiger_xmcollectiondetails.xretailerid = vtiger_xretailer.xretailerid LEFT JOIN vtiger_salesinvoice ON vtiger_xmcollectiondetails.xsalesinvoiceid = vtiger_salesinvoice.salesinvoiceid  LEFT JOIN vtiger_salesinvoicecf ON vtiger_xmcollectiondetails.xsalesinvoiceid = vtiger_salesinvoicecf.salesinvoiceid
//                    WHERE vtiger_xmcollectiondetails.xmcollectionid=$id group by vtiger_salesinvoice.salesinvoiceid";
             $Qry = "SELECT 'SI' as listtype,sum(if(vtiger_xmcollectionadjustments.adjust_type = 'C' OR vtiger_xmcollectionadjustments.adjust_type = 'SR',vtiger_xmcollectionadjustments.adjustment_amount,'')) as creditadjust,
                    sum(if(vtiger_xmcollectionadjustments.adjust_type = 'A' ,vtiger_xmcollectionadjustments.adjustment_amount,'')) as advadjust,vtiger_salesinvoice.salesinvoiceid,vtiger_xretailer.customername,vtiger_salesinvoicecf.cf_salesinvoice_transaction_number
                                    ,vtiger_salesinvoicecf.cf_salesinvoice_sales_invoice_date,vtiger_salesinvoice.salesinvoice_no,vtiger_salesinvoice.total
                                    ,vtiger_salesinvoicecf.cf_salesinvoice_outstanding,vtiger_xmcollectiondetails.*,vtiger_xmcollectionadjustments.* 
                    FROM vtiger_xmcollectiondetails
                    LEFT JOIN vtiger_xmcollectionadjustments ON 
                    (vtiger_xmcollectiondetails.xmcollectionid = vtiger_xmcollectionadjustments.xmcollectionid and vtiger_xmcollectiondetails.xretailerid = vtiger_xmcollectionadjustments.xretailerid  and vtiger_xmcollectiondetails.xsalesinvoiceid = vtiger_xmcollectionadjustments.xsalesinvoiceid  )
                    LEFT JOIN vtiger_xretailer ON vtiger_xmcollectiondetails.xretailerid = vtiger_xretailer.xretailerid
                    LEFT JOIN vtiger_salesinvoice ON vtiger_xmcollectiondetails.xsalesinvoiceid = vtiger_salesinvoice.salesinvoiceid
                    LEFT JOIN vtiger_salesinvoicecf ON vtiger_xmcollectiondetails.xsalesinvoiceid = vtiger_salesinvoicecf.salesinvoiceid
                    WHERE vtiger_xmcollectiondetails.xmcollectionid = $id and vtiger_xmcollectiondetails.listtype ='SI'
                    GROUP BY vtiger_xmcollectiondetails.xmcollectionid,vtiger_xmcollectiondetails.xretailerid,vtiger_xmcollectiondetails.xsalesinvoiceid
                    UNION
                    SELECT 'DN' as listtype,sum(if(vtiger_xmcollectionadjustments.adjust_type = 'C' OR vtiger_xmcollectionadjustments.adjust_type = 'SR',vtiger_xmcollectionadjustments.adjustment_amount,'')) as creditadjust,
                    sum(if(vtiger_xmcollectionadjustments.adjust_type = 'A' ,vtiger_xmcollectionadjustments.adjustment_amount,'')) as advadjust,vtiger_xdebitnote.xdebitnoteid,vtiger_xretailer.customername,vtiger_xdebitnote.debitnoteno
                                    ,vtiger_xdebitnotecf.cf_xdebitnote_debit_note_date,vtiger_xdebitnote.debitnotecode,vtiger_xdebitnote.amount
                                    ,vtiger_xdebitnotecf.cf_xdebitnote_debit_note_adjusted,vtiger_xmcollectiondetails.*,vtiger_xmcollectionadjustments.*
                    FROM vtiger_xmcollectiondetails
                    LEFT JOIN vtiger_xmcollectionadjustments ON 
                    (vtiger_xmcollectiondetails.xmcollectionid = vtiger_xmcollectionadjustments.xmcollectionid and vtiger_xmcollectiondetails.xretailerid = vtiger_xmcollectionadjustments.xretailerid  and vtiger_xmcollectiondetails.xsalesinvoiceid = vtiger_xmcollectionadjustments.xsalesinvoiceid  )
                    LEFT JOIN vtiger_xretailer ON vtiger_xmcollectiondetails.xretailerid = vtiger_xretailer.xretailerid
                    LEFT JOIN vtiger_xdebitnote ON vtiger_xmcollectiondetails.xsalesinvoiceid = vtiger_xdebitnote.xdebitnoteid
                    LEFT JOIN vtiger_xdebitnotecf ON vtiger_xmcollectiondetails.xsalesinvoiceid = vtiger_xdebitnotecf.xdebitnoteid
                    WHERE vtiger_xmcollectiondetails.xmcollectionid = $id and vtiger_xmcollectiondetails.listtype ='DN' 
                    group by vtiger_xmcollectiondetails.xmcollectionid,vtiger_xmcollectiondetails.xretailerid,vtiger_xmcollectiondetails.xsalesinvoiceid ";
             
           //echo $Qry;
          // exit;
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
       }
       
       function getChequeList($id) {
           global $adb;
              $Qry = "SELECT vtiger_xretailer.*,vtiger_xchequemanagementdoc.*,vtiger_xbankmaster.* FROM vtiger_xchequemanagementdoc
               LEFT JOIN vtiger_xretailer ON vtiger_xretailer.xretailerid = vtiger_xchequemanagementdoc.xretailerid LEFT JOIN vtiger_xbankmaster on vtiger_xbankmaster.xbankmasterid=vtiger_xchequemanagementdoc.xbankmasterid WHERE vtiger_xchequemanagementdoc.xchequemanagementid=$id";
          // echo $Qry;
          // exit;
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
       }
       
       function getVanUnloadRel($id) {
           global $adb;
           $Qry = "SELECT * FROM vtiger_xvanunloadingprodrel 
               LEFT JOIN  vtiger_xvanunloadingprodrelcf ON vtiger_xvanunloadingprodrel.xvanunloadingprodrelid =  vtiger_xvanunloadingprodrelcf.xvanunloadingprodrelid 
               INNER JOIN vtiger_xproduct ON vtiger_xvanunloadingprodrel.productcode = vtiger_xproduct.xproductid 
WHERE vtiger_xvanunloadingprodrel.vanunloadingid=$id";
           $result = $adb->pquery($Qry);
           $ret = array();
		   $productidarr = $line_item_idarr = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
			 $productid = $adb->query_result($result,$index,'xproductid');
			 $line_item_id = $adb->query_result($result,$index,'xvanunloadingprodrelid');
			 array_push($productidarr,$productid);
			 array_push($line_item_idarr,$line_item_id);
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
			$ret[$index]['serialkey'] = $serialkey;
	     }
			$combineprodid = implode(',',array_unique($productidarr));
			$comblineid = implode(',',array_unique($line_item_idarr));
			$res_ssi = $adb->pquery('select ssi.serialnumber, ssi.stock_type, ssi.trans_line_id from vtiger_xsalestransaction_serialinfo ssi 
			 join vtiger_xbatch_transfer_info bt on(bt.trans_lineid = ssi.trans_line_id and bt.product_id = ssi.product_id) 
			 where ssi.trans_line_id in ('.$comblineid.')  and ssi.product_id in ('.$combineprodid.') and bt.transaction_type = ? and ssi.transaction_type=?' ,array('Van Unloading','VUL'));
			while($results = $adb->fetch_array($res_ssi)) :
				$serialkey[$results['trans_line_id']][] = array($results['serialnumber'], $results['stock_type'], $results['trans_line_id']);
			endwhile;
			$ret['serialkey'] = $serialkey;
             return $ret;
       }
       
       function getAllProductCat() {
           global $adb;
           $Qry = "SELECT mt.xcategorygroupid,mt.categorygroupname,mt.categorygroupcode FROM  vtiger_xcategorygroup  mt,vtiger_crmentity ct ,vtiger_xcategorygroupcf st 
               WHERE ct.deleted=0 AND mt.xcategorygroupid=ct.crmid AND mt.xcategorygroupid=st.xcategorygroupid AND mt.active=1 AND mt.categorygrouptype='Product'";
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
       }    
       
       function getVanDetails($id) {
           global $adb;
            $Qry = "SELECT * FROM  vtiger_xvan WHERE xvanid=".$id;
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
       }
       
       function getAllSalesmancatgpcode() {
           global $adb;
           $Qry = "SELECT mt.xcategorygroupid,mt.categorygroupname,mt.categorygroupcode FROM  vtiger_xcategorygroup  mt,vtiger_crmentity ct ,vtiger_xcategorygroupcf st 
               WHERE ct.deleted=0 AND mt.xcategorygroupid=ct.crmid AND mt.xcategorygroupid=st.xcategorygroupid AND mt.active=1 AND mt.categorygrouptype='Salesman'";
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
       }        
       
       
       function getprodcatgpvaldetail($selectedid) {           
           global $adb;
           $ret = array();
//           $BaseQry = "SELECT mt.product_catgp_code,mt.product_catgp_name,mt.saleman_catgp_code FROM  vtiger_xsalesmangpmappingcf  mt
//                       LEFT JOIN vtiger_crmentity ct on mt.xsalesmangpmappingid=ct.crmid
//                       LEFT JOIN vtiger_xsalesmangpmapping st on mt.xsalesmangpmappingid=st.xsalesmangpmappingid 
//                       WHERE ct.deleted=0 AND st.xsalesmangpmappingid=".$selectedid; 
           //Below changes query for multiple records insert into tables instead of comma separated values.
           $BaseQry = "SELECT GROUP_CONCAT(tt.xproductcatgpid SEPARATOR ',') as product_catgp_code,GROUP_CONCAT(ft.xsalesmancatgpid SEPARATOR ',') as saleman_catgp_code FROM  vtiger_xsalesmangpmappingcf  mt
                       LEFT JOIN vtiger_crmentity ct on mt.xsalesmangpmappingid=ct.crmid
                       LEFT JOIN vtiger_xsalesmangpmapping st on mt.xsalesmangpmappingid=st.xsalesmangpmappingid 
                       LEFT JOIN vtiger_xproductcatgp_mrel tt on tt.crmid=st.xsalesmangpmappingid 
                       LEFT JOIN vtiger_xsalesmancatgp_mrel ft on ft.crmid=st.xsalesmangpmappingid 
                       WHERE ct.deleted=0 AND tt.relmodule='xSalesmanGpMapping' AND ft.relmodule='xSalesmanGpMapping' AND st.xsalesmangpmappingid=".$selectedid;            
           $result1 = $adb->pquery($BaseQry);
           $ret1 = array();
           
           $qryprodcatcode='';
           $ret2 = array();
             for ($index = 0; $index < $adb->num_rows($result1); $index++) {
                 $qryprodcatcode= $adb->query_result($result1,$index,'product_catgp_code'); 
                 $qrysalesmancatcode= $adb->query_result($result1,$index,'saleman_catgp_code');
                 
	     }
             $prodcatcodeexplode=explode(',', $qryprodcatcode);
             if(count($prodcatcodeexplode>0))
             { 
                 if(count($prodcatcodeexplode)>1)
                 {
                 for($i=0;$i<count($prodcatcodeexplode);$i++)
                 { 
                     $prodcatcode.= $prodcatcodeexplode[$i].',';
                 }
                    $prodcatcode  = substr($prodcatcode ,0,-1);
                 }
                 else
                 {
                     $prodcatcode=$prodcatcodeexplode[0];
                 }                
             }
            if($prodcatcode=='')
                $prodcatcode="''";   
            
             $salesmancatcodeexplode=explode(',', $qrysalesmancatcode);
             if(count($salesmancatcodeexplode>0))
             { 
                 if(count($salesmancatcodeexplode)>1)
                 {
                 for($i=0;$i<count($salesmancatcodeexplode);$i++)
                 { 
                     $salesmancatcode.= $salesmancatcodeexplode[$i].',';
                 }
                    $salesmancatcode  = substr($salesmancatcode ,0,-1);
                 }
                 else
                 {
                     $salesmancatcode=$salesmancatcodeexplode[0];
                 }                
             }
            if($salesmancatcode=='')
                $salesmancatcode="''";            
            
           $Qry = "SELECT mt.categorygroupcode,mt.categorygroupname 
                   FROM  vtiger_xcategorygroup mt WHERE mt.xcategorygroupid in (".$prodcatcode.")";
           $result = $adb->pquery($Qry);
           
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        //$ret[] = $adb->raw_query_result_rowdata($result,$index);
                 $qrycatgpprodcatcode.= $adb->query_result($result,$index,'categorygroupcode').',';
                 $qrycatgpprodcatname.= $adb->query_result($result,$index,'categorygroupname').',';
	     }
             $qrycatgpprodcatcode  = substr($qrycatgpprodcatcode ,0,-1);
             $qrycatgpprodcatname  = substr($qrycatgpprodcatname ,0,-1);
             $ret['categorygroupcode']=$qrycatgpprodcatcode;
             $ret['categorygroupname']=$qrycatgpprodcatname;
             
             
           $Qry2 = "SELECT mt.categorygroupcode,mt.categorygroupname 
                   FROM  vtiger_xcategorygroup mt WHERE mt.xcategorygroupid in (".$salesmancatcode.")";
           $result2 = $adb->pquery($Qry2);
           
             for ($index = 0; $index < $adb->num_rows($result2); $index++) {
	        //$ret[] = $adb->raw_query_result_rowdata($result,$index);
                 $qrycatgpsalescatcode.= $adb->query_result($result2,$index,'categorygroupcode').',';
                 $qrycatgpsalescatname.= $adb->query_result($result2,$index,'categorygroupname').',';
	     }
             $qrycatgpsalescatcode  = substr($qrycatgpsalescatcode ,0,-1);
             $qrycatgpsalescatname  = substr($qrycatgpsalescatname ,0,-1);
             $ret['salcategorygroupcode']=$qrycatgpsalescatcode;
             $ret['salcategorygroupname']=$qrycatgpsalescatname;             
             
             return $ret;
       }   
       
     function getselectedsalesmanvalue($selectedid) {
           global $adb;
           $Qry = "SELECT xcf.distributor_cluster_code,xcf.distributor_cluster_name,xcf.product_catgp_code,xcf.product_catgp_name,
                   xcf.saleman_catgp_code,xcf.saleman_catgp_name
                   FROM vtiger_xsalesmangpmapping xs,vtiger_xsalesmangpmappingcf xcf WHERE xs.salesmangpcode='$selectedid' and xs.xsalesmangpmappingid=xcf.xsalesmangpmappingid";
           $result = $adb->pquery($Qry);
          // $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
       } 
       
       
     
     
       
      function getStageQuery($module,$id) {
           global $adb;
           global $current_user_role_name;
           global $current_user,$LBL_SET_LIST_VIEW;
           $module=strtolower($module);
           $query="";
           $status=0;
           $companyquery = "SELECT DISTINCT cf_workflowstage_stage_name,cf_workflowstage_user_role FROM vtiger_workflowstagecf
                    where FIND_IN_SET(?,REPLACE(cf_workflowstage_user_role,' |##| ',','))";    
           //TODO: Please change the query add module in that
           
            $result = $adb->pquery($companyquery,array($current_user_role_name)); 

            $stageNames='';           
            for ($index = 0; $index < $adb->num_rows($result); $index++) {
                if($index>0)
                    $stageNames.=',';
                $stageNames.= '\''.$adb->query_result($result,$index,'cf_workflowstage_stage_name').'\'';                
            }
            
            if(($current_user_role_name == 'ForumLite_Role' || $current_user_role_name == 'Company Admin')&&($module == 'purchaseorder')){
                $stageNames='';
            }
            
           
            /*
            $selectuid = "select cf_xdistributorusermapping_supporting_staff from vtiger_xdistributorusermappingcf where cf_xdistributorusermapping_distributor=?";
            $resultuid = $adb->pquery($selectuid,array($current_user->id));
            $num=$adb->num_rows($resultuid);
             //echo "<pre>";print_r($resultuid);       
           // || $current_user_role_name == 'Distributor'
            if($num==0) {
                $query=" AND vtiger_crmentity.deleted=0 AND (vtiger_crmentity.smcreatorid=".$current_user->id.")";
            } else {
                $uid = "";
                for ($index = 0; $index < $adb->num_rows($resultuid); $index++) {
                    if($index>0)
                        $uid.=',';
                    $uid.= '\''.$adb->query_result($resultuid,$index,'cf_xdistributorusermapping_supporting_staff').'\'';                
                }    
                 $query=" AND vtiger_crmentity.deleted=0 AND (vtiger_crmentity.smcreatorid IN (".$uid.") AND vtiger_".$module."cf.cf_".$module."_next_stage_name IN (".$stageNames."))";
            }*/
            if($stageNames=="" || $current_user_role_name == 'Distributorx') {
                //$query=" AND vtiger_crmentity.deleted=0 AND (vtiger_crmentity.smcreatorid=".$current_user->id.")";
                $distributor=getDistrIDbyUserID();
                $distid = $distributor['id'];
        
                $query=" AND vtiger_crmentity.deleted=0 AND (vtiger_crmentity.smcreatorid IN (SELECT U.id FROM vtiger_users U
                            INNER JOIN vtiger_xdistributorusermappingcf DMCF ON DMCF.cf_xdistributorusermapping_supporting_staff = U.id AND DMCF.cf_xdistributorusermapping_distributor = '".$distid."'
                            INNER JOIN vtiger_crmentity CRM ON CRM.crmid = DMCF.xdistributorusermappingid AND CRM.deleted = 0) OR vtiger_crmentity.smcreatorid = 0 )";
            }elseif(($stageNames=="" || $current_user_role_name == 'Distributor') && ($module == 'xrsalesorder' || $module == 'xrsalesreturn')) {
                //$query=" AND vtiger_crmentity.deleted=0 AND (vtiger_crmentity.smcreatorid=".$current_user->id.")";
                $distributor=getDistrIDbyUserID();
                $distid = $distributor['id'];
        
                $query=" AND vtiger_crmentity.deleted=0 AND (vtiger_crmentity.smcreatorid IN (SELECT U.id FROM vtiger_users U
                            INNER JOIN vtiger_xdistributorusermappingcf DMCF ON DMCF.cf_xdistributorusermapping_supporting_staff = U.id AND DMCF.cf_xdistributorusermapping_distributor = '".$distid."'
                            INNER JOIN vtiger_crmentity CRM ON CRM.crmid = DMCF.xdistributorusermappingid AND CRM.deleted = 0) OR vtiger_crmentity.smcreatorid = 0 )";
            }elseif($current_user_role_name == 'Distributor' && $module == 'xrpurchaseinvoice' ){
                $fieldname = getTables($module);
                $cfnameavailable = strrpos($fieldname['next_stage_name'], "cf");
                 
                 if ($cfnameavailable === 0){
                     $table = $fieldname['relTableName'];
                 }else{
                     $table = $fieldname['TableName'];
                 }
                 
                if ($fieldname != ""){
                    $query=" AND vtiger_crmentity.deleted=0 AND (".$table.".".$fieldname['next_stage_name']." IN (".$stageNames."))";
                }                
            }else {              
                $fieldname = getTables($module);
                $cfnameavailable = strrpos($fieldname['next_stage_name'], "cf");

                if ($cfnameavailable === 0)
                    $table = $fieldname['relTableName'];
                else
                    $table = $fieldname['TableName'];
                
                if($current_user_role_name == 'Distributor' && $module == 'xreceivecustomermaster' ){
                    if($LBL_SET_LIST_VIEW == 'True'){
                        $stageNames .= ",' '";
                    }
                }                  
                if ($fieldname != "")
                   $query=" AND vtiger_crmentity.deleted=0 AND (vtiger_crmentity.smcreatorid=".$current_user->id." OR ".$table.".".$fieldname['next_stage_name']." IN (".$stageNames."))";
            }
             
          return $query;
      }
      
      function checkMappedModules($mappedTable,$mappedField,$relValue,$statusTable,$statusAttr,$TableIndex) {
          global $adb;
          $Query = "SELECT mt.$TableIndex FROM $mappedTable mt LEFT JOIN vtiger_crmentity ct ON mt.$TableIndex=ct.crmid ";
          if($statusTable!="" && $mappedTable!=$statusTable) {
                $Query .= " LEFT JOIN $statusTable rt ON mt.$TableIndex=rt.$TableIndex "; 
          }
          $Query .= " WHERE ct.deleted=0 AND mt.$mappedField=$relValue  ";
          if($statusAttr != ""){
          $Query .= ($mappedTable!=$statusTable ? " AND rt." : " AND mt.");
          $Query .= "$statusAttr=1";
          }
          
         //echo $Query; exit;
          $result = $adb->pquery($Query);
          $ret = $adb->num_rows($result); 
          return $ret;
      }
      
      function validateTransactions($moduleName,$transId)
      {
          if($moduleName=='xPurchaseReturn')
          {
              
          }
      }
      
      function getTranSeriesFields($type) {
          $retArr = array();
          switch ($type) {
              case "Purchase Order":
                    $retArr[0] = "vtiger_purchaseordercf";
                    $retArr[1] = "cf_purchaseorder_transaction_series";
                    $retArr[2] = "purchaseorderid";
                  break;
              case "Sales Invoice":
                    $retArr[0] = "vtiger_salesinvoicecf";
                    $retArr[1] = "cf_salesinvoice_transaction_series";
                    $retArr[2] = "salesinvoiceid";
                  break;
              case "Sales Order":
                    $retArr[0] = "vtiger_xsalesordercf";
                    $retArr[1] = "cf_salesorder_transaction_series";
                    $retArr[2] = "salesorderid";
                  break;
              case "Purchase Invoice":
                    $retArr[0] = "vtiger_purchaseinvoicecf";
                    $retArr[1] = "cf_purchaseinvoice_transaction_series";
                    $retArr[2] = "purchaseinvoiceid";
                  break;
              case "Sales Return":
                    $retArr[0] = "vtiger_xsalesreturncf";
                    $retArr[1] = "cf_xsalesreturn_transaction_series";
                    $retArr[2] = "xsalesreturnid";
                  break;
              case "Collection":
                    $retArr[0] = "vtiger_xcollectioncf";
                    $retArr[1] = "cf_xcollection_transaction_series";
                    $retArr[2] = "xcollectionid";
                  break;
              case "Purchase Return":
                    $retArr[0] = "vtiger_xpurchasereturncf";
                    $retArr[1] = "cf_xpurchasereturn_transaction_series";
                    $retArr[2] = "xpurchasereturnid";
                  break;
              case "Stock Adjustment":
                    $retArr[0] = "vtiger_stockadjustmentcf";
                    $retArr[1] = "cf_stockadjustment_transaction_series";
                    $retArr[2] = "stockadjustmentid";
                  break;
              case "Payments":
                    $retArr[0] = "vtiger_xpaymentcf";
                    $retArr[1] = "cf_xpayment_transaction_series";
                    $retArr[2] = "xpaymentid";
                  break;
              case "GRN":
                    $retArr[0] = "vtiger_xgrncf";
                    $retArr[1] = "cf_xgrn_transaction_series";
                    $retArr[2] = "xgrnid";
                  break;
              case "Dispatch":
                    $retArr[0] = "vtiger_xdispatchcf";
                    $retArr[1] = "cf_xdispatch_transaction_series";
                    $retArr[2] = "xdispatchid";
                  break;
              default:
                  break;
          }
          
          return $retArr;
      }
      
      function getCurrentDistDetails($distributorID = '') {
          global $adb;
		$ret = array();
                if(($distributorID > 0) && ($distributorID !='')){
                    $distributorID = $distributorID;
                }else{
                    $distributorID = $_SESSION["authenticated_user_id"];
                }
               
		$query = "SELECT * FROM vtiger_xdistributorusermappingcf mt 
LEFT JOIN vtiger_crmentity ct ON mt.xdistributorusermappingid=ct.crmid
LEFT JOIN vtiger_xdistributorcf cf on cf.xdistributorid=mt.cf_xdistributorusermapping_distributor 
LEFT JOIN vtiger_xstate st on st.xstateid=cf.cf_xdistributor_state 
WHERE ct.deleted=0 AND mt.cf_xdistributorusermapping_supporting_staff='".$distributorID."' LIMIT 1 ";
		$result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret = $adb->raw_query_result_rowdata($result,$index);
		}
                return $ret;
      }
      
      function getAddressid() {
          global $adb;
          $id = getDistrIDbyUserID();
		$ret123 = array();
		$query123 = "SELECT vtiger_crmentityrel.relcrmid FROM vtiger_crmentityrel 
WHERE vtiger_crmentityrel.crmid=".$id["id"]." and vtiger_crmentityrel.relmodule='xAddress'";
		$result123 = $adb->pquery($query123);                
		for ($index = 0; $index < $adb->num_rows($result123); $index++) {                    
	            $ret123[$index] = $adb->query_result($result123,$index,'relcrmid');                   
		}   
                //$ret12 = implode(',',array_filter($ret123));
                return $ret123;
      }
      
      function getAddressid_1($id) {
          global $adb;         
		$ret123 = array();
                if($id!=""){
		 $query123 = "SELECT vtiger_crmentityrel.relcrmid FROM vtiger_crmentityrel 
WHERE vtiger_crmentityrel.crmid=".$id." and vtiger_crmentityrel.relmodule='xAddress'";
		$result123 = $adb->pquery($query123);                
		for ($index = 0; $index < $adb->num_rows($result123); $index++) {                    
	            $ret123[$index] = $adb->query_result($result123,$index,'relcrmid');                   
		} 
                }
                //$ret12 = implode(',',array_filter($ret123));
                return $ret123;
      }
      function getAddressid_2() {
          global $adb;
          $id = getDistrIDbyUserID();
          $ret1234 = array();
          $seel = "SELECT `xretailerid` FROM `vtiger_xretailer` where `distributor_id`=".$id["id"]."";
          $seel123 = $adb->pquery($seel);                
		for ($index = 0; $index < $adb->num_rows($seel123); $index++) {                    
	            $ret1234[$index] = $adb->query_result($seel123,$index,'xretailerid');                   
		} 
               $ret1 = implode(',',array_filter($ret1234));
               if($ret1 == "")$ret1 = 0;
		$ret123 = array();
		$query123 = "SELECT vtiger_crmentityrel.relcrmid FROM vtiger_crmentityrel 
WHERE vtiger_crmentityrel.crmid IN (".$ret1.") and vtiger_crmentityrel.relmodule='xAddress'";
		$result123 = $adb->pquery($query123);                
		for ($index = 0; $index < $adb->num_rows($result123); $index++) {                    
	            $ret123[$index] = $adb->query_result($result123,$index,'relcrmid');                   
		}   
                //$ret12 = implode(',',array_filter($ret123));
                return $ret123;
      }
      function getDistrmappedUID() {
		global $adb;
                global $current_user_role_name;
                global $current_user;
                $id = getDistrIDbyUserID();                
		$ret = array();
		 $query = "SELECT mt.cf_xdistributorusermapping_supporting_staff as `id` FROM vtiger_xdistributorusermappingcf mt 
LEFT JOIN vtiger_crmentity ct ON mt.xdistributorusermappingid=ct.crmid
LEFT JOIN vtiger_xdistributor on vtiger_xdistributor.xdistributorid=mt.cf_xdistributorusermapping_distributor WHERE 
ct.deleted=0 AND mt.cf_xdistributorusermapping_distributor=".$id['id']."";
		$result = $adb->pquery($query);
               //echo "<pre>"; print_r($result);
                //echo $adb->num_rows($result);
		for ($index = 0; $index < $adb->num_rows($result); $index++) { 
	             $ret[$index] = $adb->query_result($result,$index,'id');
		}               
                $ret1 = @implode(',',array_filter($ret));
                return $ret1;
	}
        
        
         function getDistributorTerms($module) {
             global $adb;
             global $current_user_role_name;
             global $current_user;
             $buyer =  getDistrIDbyUserID(); 
             $query = "SELECT vtiger_xdistributorterms.distributortermsterms from vtiger_xdistributorterms INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=vtiger_xdistributorterms.xdistributortermsid INNER JOIN vtiger_xdistributortermscf ON vtiger_xdistributortermscf.xdistributortermsid=vtiger_xdistributorterms.xdistributortermsid where  vtiger_crmentity.deleted=0 and vtiger_xdistributortermscf.cf_xdistributorterms_status=1 and vtiger_xdistributorterms.cf_xdistributorterms_transaction_type='".$module."' and vtiger_xdistributorterms.cf_xdistributorterms_distributor_name=".$buyer['id'];
            $terms_conditions = $adb->pquery($query);             
            $textterms = $adb->query_result($terms_conditions,0,'distributortermsterms');
            if($textterms!=""){
                $txt = $textterms; 
             }
            return $txt;
         }
        
    
function buildTree($ar, $pid = 0) { //pk
    $op = array();
    foreach( $ar as $item ) {
        if( $item['id'] == $pid ) { //print_r($item);
            $op[$item['id']] = array($item['name']);
            // using recursion
            $children =  buildTree( $ar, $item['parent'] );
            if( $children ) {               
                //$op[] = $children;
                $op[$item['id']]['children'] = $children;
            }
        }
    }    
    return $op;
}
function buildTreecode($ar, $pid = 0) { 
    $op = array();
   // print_r($ar);
    foreach( $ar as $item ) { 
        if( $item['id'] == $pid ) {//print_r($item);
            $op[$item['id']] = array($item['code']);
            // using recursion
            $children =  buildTreecode( $ar, $item['parent'] );
            if( $children ) {               
                //$op[] = $children;
                $op[$item['id']]['children'] = $children;
            }
        }
    }    //print_r($op);
    return $op;
}

function getpathtext($pQry,$return_id){
    global $adb;
    $result = $adb->pquery($pQry);
    for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret[] = $adb->raw_query_result_rowdata($result,$index);
		}

    $resultarr = buildTree($ret,$return_id);
    $flat_array = array();
    foreach(new RecursiveIteratorIterator(new RecursiveArrayIterator($resultarr)) as $k=>$v){
        $flat_array[] = $v;
    }    
        $path = implode(' - ',array_reverse($flat_array));  
        
 return $path;
}
function getcodetextsave($pQry,$return_id){
    global $adb;
    $result = $adb->pquery($pQry);
    for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret[] = $adb->raw_query_result_rowdata($result,$index);
		}

    $resultarr = buildTreecode($ret,$return_id);//print_r($resultarr);
    $flat_array = array();
    foreach(new RecursiveIteratorIterator(new RecursiveArrayIterator($resultarr)) as $k=>$v){
        $flat_array[] = $v;
    }    //print_r($flat_array);
        $code = implode(' - ',array_reverse($flat_array));  
        
 return $code;
}
function buildTreegeo($ar, $pid = 0) { //pk
    //echo "<pre>";print_r($ar);exit;
    $op = array();
    foreach( $ar as $item ) {
        if( $item['parent'] == $pid && $pid!='' && $item['parent']!='') {
            $op[$item['id']] = array($item['id']);
            // using recursion
            //$children =  buildTreegeo( $ar, $item['id'] );
            if( $children ) {               
                //$op[] = $children;
                $op[$item['id']]['children'] = $children;
            }
        }
    }
    if(count($op) == 0)
    {
        $op[0] = 0;
    }
    
    return $op;
}
function getgeoid($pQry,$geoid){
    global $adb;
    $result = $adb->pquery($pQry);
    for ($index = 0; $index < $adb->num_rows($result); $index++) {
        $ret[] = $adb->raw_query_result_rowdata($result,$index);
    }

    $parents = array();
	$resultarr=buildTreegeo($ret,$geoid);
    $flat_array = array();
    foreach(new RecursiveIteratorIterator(new RecursiveArrayIterator($resultarr)) as $k=>$v){
        $flat_array[] = $v;
   }    
        if($geoid!='')
            $flat_array[] = $geoid;
        $path = implode(',',array_reverse($flat_array));  

 return $path;
}

function getCreditNoteType($id) {
     global $adb;
     $ret = '';
     $query = "SELECT creditnote_type FROM vtiger_xcreditnotecf 
WHERE xcreditnoteid=".$id;
	$result = $adb->pquery($query);
	for ($index = 0; $index < $adb->num_rows($result); $index++) {
	           $ret = $adb->raw_query_result_rowdata($result,$index);
	}
        return $ret['creditnote_type'];
}
function getDebitNoteType($id) {
     global $adb;
     $ret = '';
     $query = "SELECT debitnote_type FROM vtiger_xdebitnote WHERE xdebitnoteid=".$id;
	 $result = $adb->pquery($query);
	 for ($index = 0; $index < $adb->num_rows($result); $index++) {
	 	$ret = $adb->raw_query_result_rowdata($result,$index);
	 }
     return $ret['debitnote_type'];
}

    function getUserGeo() {
        global $adb; $ret = ''; $resArr = array();
        $query = "SELECT id,geography_hierarchy FROM vtiger_users WHERE id=".$_SESSION["authenticated_user_id"];
        $result = $adb->pquery($query, array());
        $geoid = $adb->query_result($result,0,'geography_hierarchy');
        if($geoid!="") {
            $resArr = getChildsNod('vtiger_xgeohiercf','xgeohierid','cf_xgeohier_parent',$geoid,'cf_xgeohier_active','Y');
        } else {
            $query = "SELECT mt.xgeohierid FROM vtiger_xgeohiercf mt 
            LEFT JOIN vtiger_crmentity ct ON mt.xgeohierid=ct.crmid 
            WHERE ct.deleted=0 AND mt.cf_xgeohier_active=1 ";
            $result  = $adb->pquery($query);
            for ($index = 0; $index < $adb->num_rows($result); $index++) {
                $resArr[] = $adb->query_result($result,$index,'xgeohierid');
            }
        }
        
        foreach ($resArr as $res) {
            $ret .= $res.',';
        }
        
        if($ret!='') $ret = substr($ret,0,-1);
        
        return $ret;
    }
    
    function chkmappedCluster($clID, $table, $tabIndex, $actTable, $activeField,$actTableIndex,$clusterAttr) {
        global $adb; $res = 0;
        $query = "SELECT COUNT(*) as cnt FROM $table mt 
LEFT JOIN vtiger_crmentity ct ON mt.$tabIndex=ct.crmid ";
        if($table!=$actTable) $query.="LEFT JOIN $actTable cf ON mt.$tabIndex=cf.$actTableIndex ";
$query.="WHERE $clusterAttr=$clID AND ct.deleted=0 AND $activeField=1 ";
        $result  = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $res = $adb->query_result($result,$index,'cnt');
        }
        if($res > 0) 
            return "Y";
        else 
            return "N";
    }
    
   function chkmappedClusterStock($distIdArray){
        global $adb; $res = 0;
        
        $distid = implode(",", $distIdArray);
        $query = "SELECT COUNT(*) as cnt FROM vtiger_stocklots"
                . " WHERE distributorcode IN ($distid)";
        $result  = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $res = $adb->query_result($result,$index,'cnt');
        }
        if($res > 0) 
            return "Y";
        else 
            return "N";
   }
    
    function chkmappedCluster2($clID, $table, $tabIndex, $actTable, $activeField,$actTableIndex,$clusterAttr) {
        global $adb; $res = 0;
        $query = "SELECT COUNT(*) as cnt FROM $table mt 
LEFT JOIN vtiger_crmentity ct ON mt.$tabIndex=ct.crmid ";
        if($table!=$actTable) $query.="LEFT JOIN $actTable cf ON mt.$tabIndex=cf.$actTableIndex ";
$query.="WHERE $clusterAttr IN ($clID) AND ct.deleted=0 AND $activeField=1 ";
        $result  = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $res = $adb->query_result($result,$index,'cnt');
        }
        if($res > 0) 
            return "Y";
        else 
            return "N";
    }

    function getChildsNod ($table, $tabIndex, $parent, $currentValue,$activeField,$self='N') {
        global $adb; $ret = array();
        $query = "SELECT * FROM $table mt 
            LEFT JOIN vtiger_crmentity ct ON mt.$tabIndex=ct.crmid 
            WHERE ct.deleted=0 ";
        $result  = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $Arr = $adb->raw_query_result_rowdata($result,$index);
            $data[$index]['id'] = $Arr[$tabIndex];
            $data[$index]['parent_id'] = $Arr[$parent];
        }
        //echo "<pre>";// print_r($data);
        $cats = fetch_data_recursive($data,$currentValue);
        $str = "";
        //print_r($cats);
        if(!empty($cats)) {
            foreach($cats as $val) {
                if($self!='Y') {
                    if($val['id']!=$currentValue) {
                        $activeQry = "SELECT * FROM $table mt 
                LEFT JOIN vtiger_crmentity ct ON mt.$tabIndex=ct.crmid 
                WHERE ct.deleted=0 AND mt.$activeField=1 AND mt.$tabIndex=".$val['id'];
                        $result  = $adb->pquery($activeQry);
                        for ($index = 0; $index < $adb->num_rows($result); $index++) {
                            $Arr = $adb->raw_query_result_rowdata($result,$index);
                            $ret[] = $Arr[$tabIndex];
                        }
                        // $str .= $val['id'].",";
                    }
                } else {
                     $activeQry = "SELECT * FROM $table mt 
            LEFT JOIN vtiger_crmentity ct ON mt.$tabIndex=ct.crmid 
            WHERE ct.deleted=0 AND mt.$activeField=1 AND mt.$tabIndex=".$val['id'];
                    $result  = $adb->pquery($activeQry);
                    for ($index = 0; $index < $adb->num_rows($result); $index++) {
                        $Arr = $adb->raw_query_result_rowdata($result,$index);
                        $ret[] = $Arr[$tabIndex];
                    }
                }
            }
           //$str = substr($str,0,-1);
        }
        return $ret;
    }
    
    function getChildsNodNxt ($table, $tabIndex, $parent, $currentValue,$activeField,$self='N') {
        global $adb; $ret = array(); $retStr = '';
        $query = "SELECT * FROM $table mt 
            LEFT JOIN vtiger_crmentity ct ON mt.$tabIndex=ct.crmid 
            WHERE ct.deleted=0 ";
        $result  = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $Arr = $adb->raw_query_result_rowdata($result,$index);
            $data[$index]['id'] = $Arr[$tabIndex];
            $data[$index]['parent_id'] = $Arr[$parent];
        }
        //echo "<pre>";// print_r($data);
        $cats = fetch_data_recursive($data,$currentValue);
        $str = "";
        //print_r($cats);
        if(!empty($cats)) {
            foreach($cats as $val) {
                if($self!='Y') {
                    if($val['id']!=$currentValue) {
                        $activeQry = "SELECT * FROM $table mt 
                LEFT JOIN vtiger_crmentity ct ON mt.$tabIndex=ct.crmid 
                WHERE ct.deleted=0 AND mt.$activeField=1 AND mt.$tabIndex=".$val['id'];
                        $result  = $adb->pquery($activeQry);
                        for ($index = 0; $index < $adb->num_rows($result); $index++) {
                            $Arr = $adb->raw_query_result_rowdata($result,$index);
                            $ret[] = $Arr[$tabIndex];
                        }
                        // $str .= $val['id'].",";
                    }
                } else {
                     $activeQry = "SELECT * FROM $table mt 
            LEFT JOIN vtiger_crmentity ct ON mt.$tabIndex=ct.crmid 
            WHERE ct.deleted=0 AND mt.$activeField=1 AND mt.$tabIndex=".$val['id'];
                    $result  = $adb->pquery($activeQry);
                    for ($index = 0; $index < $adb->num_rows($result); $index++) {
                        $Arr = $adb->raw_query_result_rowdata($result,$index);
                        //$ret[] = $Arr[$tabIndex];
                        $retStr .= $Arr[$tabIndex].",";
                    }
                }
            }
        }
        //if($retStr!='') $retStr = substr($retStr,0,-1);
        return $retStr;
    }
    
    function fetch_data_recursive($src_arr, $currentid, $parentfound = false, $cats = array()) {
        foreach($src_arr as $row) {
            if((!$parentfound && $row['id'] == $currentid) || $row['parent_id'] == $currentid){
                $rowdata = array();
                foreach($row as $k => $v)
                    $rowdata[$k] = $v;
                $cats[] = $rowdata;
                if($row['parent_id'] == $currentid)
                   $cats = array_merge($cats, fetch_data_recursive($src_arr, $row['id'], true));
            }
        }
        return $cats;
    }
    
   function getStates() {
        global $adb; $ret = ''; $Arr = array();
        $query = "SELECT mt.xstateid AS id,mt.statename AS name FROM vtiger_xstate mt 
                  LEFT JOIN vtiger_xstatecf cf ON mt.xstateid=cf.xstateid 
                  LEFT JOIN vtiger_crmentity ct ON mt.xstateid=ct.crmid 
                  WHERE ct.deleted=0 AND cf.cf_xstate_active=1 ";
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $Arr = $adb->raw_query_result_rowdata($result,$index);
            $ret[$Arr['id']] = $Arr['name'];
        }
        return $ret;
   }
    
   function getChannelHierarchy() {
      global $adb; $ret = ''; $Arr = array();
         $query = "SELECT ch.xchannelhierarchyid AS id, ch.channel_hierarchy AS name FROM vtiger_xchannelhierarchy AS ch "
				  ."   INNER JOIN vtiger_xchannelhierarchycf ON vtiger_xchannelhierarchycf.xchannelhierarchyid = ch.xchannelhierarchyid  "
                 . " INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = ch.xchannelhierarchyid "
                 . " WHERE vtiger_crmentity.deleted = 0  AND cf_xchannelhierarchy_active=1"; //FRPRDINXT-2352
         $result = $adb->pquery($query);
         for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $Arr = $adb->raw_query_result_rowdata($result,$index);
            $ret[$Arr['id']] = $Arr['name'];
         }
        return $ret;   
    }
    


    function getHierDetails($value) {
        global $adb,$current_user;
        if($value == "Sales") {
           /* $query = "SELECT mt.xsupplychainhiermetaid AS `id`,mt.levelname AS `name`       
FROM vtiger_xsupplychainhiermeta mt 
LEFT JOIN vtiger_crmentity ct ON mt.xsupplychainhiermetaid=ct.crmid 
LEFT JOIN vtiger_xsupplychainhiermetacf cf ON mt.xsupplychainhiermetaid=cf.xsupplychainhiermetaid 
WHERE ct.deleted=0 ";*/
  //   $query .= " AND cf.cf_xsupplychainhiermeta_active=1";
            $query = "SELECT mt.xorganisationhiermetaid AS `id`,mt.levelname AS `name`       
FROM vtiger_xorganisationhiermeta mt 
LEFT JOIN vtiger_crmentity ct ON mt.xorganisationhiermetaid=ct.crmid 
LEFT JOIN vtiger_xorganisationhiermetacf cf ON mt.xorganisationhiermetaid=cf.xorganisationhiermetaid 
WHERE ct.deleted=0 AND cf.cf_xorganisationhiermeta_active=1 ";
        } else if($value == "Geo") {
             $roleid = $current_user->roleid;
			  $geoid = $current_user->geography_hierarchy;    
			 $query = "SELECT mt.xgeohiermetaid AS `id`,mt.levelname AS `name`       
FROM vtiger_xgeohiermeta mt 
LEFT JOIN vtiger_crmentity ct ON mt.xgeohiermetaid=ct.crmid 
LEFT JOIN vtiger_xgeohiermetacf cf ON mt.xgeohiermetaid=cf.xgeohiermetaid 
WHERE ct.deleted=0 AND cf.cf_xgeohiermeta_active=1 ";
 
       // $query .= " AND cf.cf_xgeohiermeta_active=1 ";
           /*$query = "SELECT mt.xgeohierid as id,mt.geohiername as name FROM `vtiger_xgeohier` mt 
LEFT JOIN vtiger_crmentity ct ON mt.xgeohierid=ct.crmid 
LEFT JOIN vtiger_xgeohiercf cf ON mt.xgeohierid=cf.xgeohierid 
WHERE ct.deleted=0 ";*/
        } else if($value == "DGrp") {
            $query = "SELECT mt.xcustomergroupid AS `id`,mt.customergroupname AS `name`       
FROM vtiger_xcustomergroup mt 
LEFT JOIN vtiger_crmentity ct ON mt.xcustomergroupid=ct.crmid 
LEFT JOIN vtiger_xcustomergroupcf cf ON mt.xcustomergroupid=cf.xcustomergroupid 
WHERE ct.deleted=0 AND cf.cf_xcustomergroup_active=1 AND cf.cf_xcustomergroup_customer_group_type='Distributor' ";
        } else if($value == "Product") {
            $query = "SELECT mt.xprodhiermetaid AS `id`,mt.levelname AS `name`       
FROM vtiger_xprodhiermeta mt 
LEFT JOIN vtiger_crmentity ct ON mt.xprodhiermetaid=ct.crmid 
LEFT JOIN vtiger_xprodhiermetacf cf ON mt.xprodhiermetaid=cf.xprodhiermetaid 
WHERE ct.deleted=0 AND cf.cf_xprodhiermeta_active=1 ";
        } else {
            $query = "SELECT mt.xsupplychainhiermetaid AS `id`,mt.levelname AS `name`       
FROM vtiger_xsupplychainhiermeta mt 
LEFT JOIN vtiger_crmentity ct ON mt.xsupplychainhiermetaid=ct.crmid 
LEFT JOIN vtiger_xsupplychainhiermetacf cf ON mt.xsupplychainhiermetaid=cf.xsupplychainhiermetaid 
WHERE ct.deleted=0 AND cf.cf_xsupplychainhiermeta_active=1 ";
        }
        
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $Arr = $adb->raw_query_result_rowdata($result,$index);
            $ret[$Arr['id']] = $Arr['name'];
        }
        return $ret;
    }
    
    function getHierDetailsRedefined($value) {
        global $adb,$current_user;
    }
    function getAllDistributor() {
        global $adb,$current_user;
        $query = "SELECT mt.xdistributorid AS `id`,
       mt.distributorname AS `name`,mt.distributorcode AS `code` 
FROM vtiger_xdistributor mt
LEFT JOIN vtiger_crmentity ct ON mt.xdistributorid=ct.crmid
LEFT JOIN vtiger_xdistributorcf cf ON mt.xdistributorid=cf.xdistributorid 
WHERE ct.deleted=0 AND cf.cf_xdistributor_active=1"; 
if($current_user->is_admin!='on' && $current_user->is_sadmin!='on'){
   $geoid = $current_user->geography_hierarchy;   
                   // $query.=" and cf.cf_xdistributor_geography=$geoid";
				   
				   $pqry = "SELECT GROUP_CONCAT(vtiger_xcpdpmappingcf.cf_xcpdpmapping_distributor SEPARATOR ',') as distid
FROM vtiger_xcpdpmappingcf INNER JOIN vtiger_xcpdpmapping ON
vtiger_xcpdpmappingcf.xcpdpmappingid = vtiger_xcpdpmapping.xcpdpmappingid
where vtiger_xcpdpmapping.cpusers=".$current_user->id;
	$presult = $adb->pquery($pqry);
	if($adb->num_rows($presult)>0){
	$dis_ids = $adb->query_result($presult,0,'distid');
	}else{
	$dis_ids = 0;
	}
	$query.=" AND mt.xdistributorid IN(".$dis_ids.")";
                 }
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret[] = $adb->raw_query_result_rowdata($result,$index);
        }
        return $ret;
        
    }
    
    function getDistributor($id) {
        global $adb;
        $query = "SELECT mt.xdistributorid AS `id`,
       mt.distributorname AS `name`,mt.distributorcode AS `code` 
FROM vtiger_xdistributor mt
LEFT JOIN vtiger_crmentity ct ON mt.xdistributorid=ct.crmid
LEFT JOIN vtiger_xdistributorcf cf ON mt.xdistributorid=cf.xdistributorid 
WHERE ct.deleted=0 AND cf.cf_xdistributor_active=1 AND mt.xdistributorid=$id";
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret = $adb->raw_query_result_rowdata($result,$index);
        }
        return $ret;
        
    }
    
    function getAllDistributorClusterRel($clID='') {
        global $adb,$current_user;
        $query = "SELECT mt.xdistributorid AS `id`,
       mt.distributorname AS `name`,mt.distributorcode AS `code`,dc.addition_date 
FROM vtiger_xdistributor mt
LEFT JOIN vtiger_crmentity ct ON mt.xdistributorid=ct.crmid
LEFT JOIN vtiger_xdistributorcf cf ON mt.xdistributorid=cf.xdistributorid 
INNER JOIN vtiger_xdistributorclusterrel dc ON dc.distributorid=mt.xdistributorid ";

        if($clID!='') {
            $query .= "WHERE ct.deleted=0 AND dc.distclusterid=$clID  GROUP BY mt.xdistributorid";
        }else {
            $query .= "WHERE ct.deleted=0  GROUP BY mt.xdistributorid";
        }
         $roleid = $current_user->roleid;
	 $usr_role_sql = "SELECT * FROM vtiger_role WHERE roleid=?";
	 $usr_role_result = $adb->pquery($usr_role_sql,array($roleid));
	 $usr_role = $adb->query_result($usr_role_result,0,'rolename');
        
         $geoid = $current_user->geography_hierarchy;  
        
		  if($current_user->is_admin!='on' && $current_user->is_sadmin!='on'){
         $query .= " AND vtiger_xdistributorcf.cf_xdistributor_geography = ".$geoid;
        }
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret[] = $adb->raw_query_result_rowdata($result,$index);
        }
        return $ret;
        
    }
    
    function getAllProduct() {
        global $adb; $ret = array();
        $query = "SELECT mt.xproductid AS `id`,cf.cf_xproduct_category as hier,
       mt.productname AS `name`,mt.productcode AS `code` 
FROM vtiger_xproduct mt
LEFT JOIN vtiger_crmentity ct ON mt.xproductid=ct.crmid
LEFT JOIN vtiger_xproductcf cf ON mt.xproductid=cf.xproductid 
WHERE ct.deleted=0 AND cf.cf_xproduct_active=1 ";
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret[] = $adb->raw_query_result_rowdata($result,$index);
        }
        return $ret;
    }
    
    function getProductCategoryGroupMapping($id) {
        global $adb; $ret = array();
        $query = "SELECT * FROM vtiger_xproductcategorygroupmapping mt 
            LEFT JOIN vtiger_xdistributorcluster dc ON dc.xdistributorclusterid=mt.distributorcluster 
WHERE mt.xproductcategorygroupmappingid=".$id." LIMIT 1 ";
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret[] = $adb->raw_query_result_rowdata($result,$index);
        }
        return $ret;
    }
    
    function getDistributorProductMapping($id) {
        global $adb; $ret = array();
        $query = "SELECT * FROM vtiger_xdistributorproductsmapping mt 
            LEFT JOIN vtiger_xdistributorcluster dc ON dc.xdistributorclusterid=mt.distributor_cluster_code 
WHERE mt.xdistributorproductsmappingid=".$id." LIMIT 1 ";
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret[] = $adb->raw_query_result_rowdata($result,$index);
        }
        return $ret;
    }
    
    function getProLevelnameByID($id) {
        global $adb; $ret = array();
        $query = "SELECT prodhiername FROM vtiger_xprodhier WHERE xprodhierid=".$id." LIMIT 1 ";
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret = $adb->raw_query_result_rowdata($result,$index,'prodhiername');
        }
        return $ret;
    }
    
    function getClusterDetails($id) {
        global $adb; $ret = array();
        $query = "SELECT * FROM vtiger_xdistributorclustordetails WHERE distclusterid=".$id." LIMIT 1 ";
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret[] = $adb->raw_query_result_rowdata($result,$index);
        }
        return $ret;
    }
    
    function getCatGrpMappingrel($id) {
        global $adb; $ret = array();
        $query = "SELECT * FROM vtiger_xproductcategorygroupmappingrel WHERE productcategorygroupmappingid=".$id." LIMIT 1 ";
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret[] = $adb->raw_query_result_rowdata($result,$index);
        }
        return $ret;
    }
    
    function getDPMappingrel($id) {
        global $adb; $ret = array();
        $query = "SELECT * FROM vtiger_xdistributorproductsmappingrels WHERE xdistributorproductsmappingid=".$id." LIMIT 1 ";
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret[] = $adb->raw_query_result_rowdata($result,$index);
        }
        return $ret;
    }
    
    function getDPMapping($id) {
        global $adb; $ret = array();
        $query = "SELECT mt.effective_from_date,mt.producthierid,mt.productid,mt.xdistributorproductsmappingid,
ph.prodhiername,p.productname,p.productcode FROM vtiger_xdistributorproductsmappingrel mt 
LEFT JOIN vtiger_xprodhier ph ON ph.xprodhierid=mt.producthierid 
LEFT JOIN vtiger_xproduct p ON p.xproductid=mt.productid 
WHERE mt.xdistributorproductsmappingid=".$id." ";
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret[] = $adb->raw_query_result_rowdata($result,$index);
        }
        return $ret;
    }
    
    function getRetailerProductMappingrel($id)
    {
        global $adb; $ret = array();
        $query = "SELECT mt.effective_from_date,mt.xprodhierid,mt.xproductid,mt.xretailerproductmappingid,
ph.prodhiername,p.productname,p.productcode FROM vtiger_xretailerproductsmappingrel mt 
LEFT JOIN vtiger_xprodhier ph ON ph.xprodhierid=mt.xprodhierid 
LEFT JOIN vtiger_xproduct p ON p.xproductid=mt.xproductid 
WHERE mt.xretailerproductmappingid=".$id." group by p.xproductid order by ph.xprodhierid,p.xproductid ";
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret[] = $adb->raw_query_result_rowdata($result,$index);
        }
        return $ret;
    }
    
    function getRetailerMappingrel($id) {
        global $adb; $ret = array();
        $query = "SELECT * FROM vtiger_xproducthier_mrel WHERE crmid=".$id;
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret[] = $adb->raw_query_result_rowdata($result,$index);
        }
        return $ret;
    }
    
    function getFocusProductMappingrel($id) {
        global $adb; $ret = array();
        $query = "SELECT * FROM vtiger_xfocusproduct_mapping_hier_lvl WHERE xfocusproductid=".$id;
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret[] = $adb->raw_query_result_rowdata($result,$index);
        }
        
        return $ret;
    }
    
    function getDetailFocusProductMappingrel($id) {
        global $adb; $ret = array();
        $query = "SELECT vtiger_xprodhiermeta.levelname, group_concat(prodhiername) as prodhiername FROM vtiger_xfocusproduct_mapping_hier_lvl fp_map 
            Inner Join vtiger_xprodhiermeta on vtiger_xprodhiermeta.xprodhiermetaid=fp_map.xprodhiermetaid 
            Inner Join vtiger_xprodhier on vtiger_xprodhier.xprodhierid=fp_map.xprodhierid  
            WHERE fp_map.xfocusproductid=$id group by fp_map.xprodhiermetaid";
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret[] = $adb->raw_query_result_rowdata($result,$index);
        }
        
        return $ret;
    }
    
    
    function getFocusProductMapping($id) {
        global $adb; $ret = array();
        $query = "SELECT mt.producthierid,mt.productid,mt.xfocusproductid,
ph.prodhiername,p.productname,p.productcode FROM vtiger_xfocusproductrel mt 
LEFT JOIN vtiger_xprodhier ph ON ph.xprodhierid=mt.producthierid 
LEFT JOIN vtiger_xproduct p ON p.xproductid=mt.productid 
WHERE mt.xfocusproductid=".$id." ";
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret[] = $adb->raw_query_result_rowdata($result,$index);
        }
        return $ret;
    }
    
    function getCatGrpMapping($id) {
        global $adb; $ret = array();
        $query = "SELECT mt.revokedate,mt.producthierid,mt.productid,mt.xproductcategorygroupmappingid,
ph.prodhiername,p.productname,p.productcode FROM vtiger_xproductgroupmappingrel mt 
LEFT JOIN vtiger_xprodhier ph ON ph.xprodhierid=mt.producthierid 
LEFT JOIN vtiger_xproduct p ON p.xproductid=mt.productid 
WHERE mt.xproductcategorygroupmappingid=".$id." ";
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret[] = $adb->raw_query_result_rowdata($result,$index);
        }
        return $ret;
    }
    
    function getStockNormMapping($id) {
        global $adb; $ret = array();
        $query = "SELECT mt.max_uomid,mt.max_qty,mt.min_uomid,mt.min_qty,
                mt.reorder_uomid,mt.reorder_qty,mt.lotsize_uomid,mt.lot_qty,            
                mt.prodhierid,mt.productid,
                ph.prodhiername,p.productname,p.productcode FROM vtiger_xdiststocknormmaprel mt 
                LEFT JOIN vtiger_xprodhier ph ON ph.xprodhierid=mt.prodhierid 
                LEFT JOIN vtiger_xproduct p ON p.xproductid=mt.productid 
                WHERE mt.xdistributorstocknormmapid='$id'";
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret[] = $adb->raw_query_result_rowdata($result,$index);
        }
        return $ret;
    }    
    
    function getStockNormMappingrel($id) {
        global $adb; $ret = array();
        $query = "SELECT * FROM vtiger_xdistributorstocknormmap WHERE xdistributorstocknormmapid=".$id." LIMIT 1 ";
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret[] = $adb->raw_query_result_rowdata($result,$index);
        }
        return $ret;
    }    
    
    function getClusterDistributors($id) {
        global $adb; $resArr = array();
        //This function also included distributor cluster save.
        //$query = "SELECT * FROM vtiger_xdistributorclusterrel WHERE distclusterid=".$id." ";
        $query = "SELECT * FROM vtiger_xdistributorclusterrel WHERE distclusterid in (".$id.") group by distributorid";
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $res[] = $adb->raw_query_result_rowdata($result,$index);
        } $i=0;
        foreach($res as $re) {
           // $query = "SELECT * FROM vtiger_xdistributor WHERE xdistributorid=".$re['distributorid']." ";
           //CHANGES MADE FOR DISTRIBUTOR CLUSTOR MAPPING ISSUE.
             $query = "SELECT * FROM vtiger_xdistributor AS d "
                    . "INNER JOIN vtiger_xdistributorcf AS dcf ON dcf.xdistributorid=d.xdistributorid "
                    . "WHERE  d.xdistributorid=".$re['distributorid']." ";
            $result = $adb->pquery($query);
            for ($index = 0; $index < $adb->num_rows($result); $index++) {
                //$ret[] = $adb->raw_query_result_rowdata($result,$index);
                $resArr[$i]['id'] = $adb->query_result($result,$index,'xdistributorid');
                $resArr[$i]['name'] = $adb->query_result($result,$index,'distributorname');
                $resArr[$i]['code'] = $adb->query_result($result,$index,'distributorcode');
            } $i++;
        }
        
        return $resArr;
    }
    
    function getRealignCustomer($id){
            global $adb; $ret = array();
            $query	= "SELECT ret.xretailerid as custid, ret.customername as  customername,ret.customercode as customercode, retcf.cf_xretailer_address_1 as address,ret.unique_retailer_code as uniquecode,
                ch.channel_hierarchy as channeltype ,vc.valueclassdescription as valueclass,gc.generalclassdescription as generalclass, pc.potentialclassdesc as potentialclass , xu.customer_code as universalcode
		FROM vtiger_xcustomerrealignmentdetails crd
                LEFT JOIN `vtiger_xretailer` ret ON  ret.xretailerid = crd.cust_id
		INNER JOIN vtiger_crmentity ct ON ret.xretailerid=ct.crmid 
		
                INNER JOIN `vtiger_xretailercf` retcf ON retcf.xretailerid = ret.xretailerid
                LEFT JOIN `vtiger_xgeneralclassification` gc ON gc.xgeneralclassificationid = retcf.cf_xretailer_general_classification
                LEFT JOIN `vtiger_xvalueclassification` vc ON vc.xvalueclassificationid = retcf.cf_xretailer_value_classification
                LEFT JOIN `vtiger_xchannelhierarchy` ch ON ch.xchannelhierarchyid = retcf.cf_xretailer_channel_type
                LEFT JOIN `vtiger_xpotentialclassification` pc ON pc.xpotentialclassificationid = retcf.cf_xretailer_potential
                LEFT JOIN `vtiger_xuniversal` xu ON xu.conveted_retailer_id = ret.xretailerid
   WHERE crd.xcustomerrealignmentid = '".$id."' group by custid ";
		
                $result = $adb->pquery($query);
                $resArr1 = array();
                for ($index = 0; $index < $adb->num_rows($result); $index++) {
                        $resArr[$index]['custid'] = $adb->query_result($result,$index,'custid');
                        $resArr[$index]['customercode'] = $adb->query_result($result,$index,'customercode');
                        $resArr[$index]['customername'] = $adb->query_result($result,$index,'customername');
                        
                        $resArr1[$index]['uniquecode'] = $adb->query_result($result,$index,'uniquecode');   
                        $resArr1[$index]['universalcode'] = $adb->query_result($result,$index,'universalcode');
                       
                        $resArr[$index]['uniquecode'] = ($resArr1[$index]['uniquecode'] !='' && !empty($resArr1[$index]['uniquecode'])) ? $resArr1[$index]['uniquecode'] : $resArr1[$index]['universalcode'] ;
                        
                        $resArr[$index]['address'] = $adb->query_result($result,$index,'address');
                        $resArr[$index]['channeltype'] = $adb->query_result($result,$index,'channeltype');
                        $resArr[$index]['valueclass'] = $adb->query_result($result,$index,'valueclass');
                        $resArr[$index]['generalclass'] = $adb->query_result($result,$index,'generalclass');
                        $resArr[$index]['potentialclass'] = $adb->query_result($result,$index,'potentialclass');

                }
                return $resArr; 
    }
    
    function editClusterDistributors($id) {
        global $adb; $arr = $resArr = array();
        $arr = getClusterDistributors($id);
        $i=0;$date='';
        foreach($arr as $ar) {
            $query = "SELECT * FROM vtiger_xdistributorclusterrel WHERE distclusterid=".$id." AND distributorid=".$ar['id'];
            $result = $adb->pquery($query);
            for ($index = 0; $index < $adb->num_rows($result); $index++) {
                //$ret[] = $adb->raw_query_result_rowdata($result,$index);
                $date = $adb->query_result($result,$index,'addition_date');
            }
            $arr[$i]['created_date'] = $date;
            $resArr[$arr[$i]['id']] = $date;
            $i++;
        }
        return $resArr;
    }
    
    function isCompVendor($id) {
            global $adb; $vid='';
            $query = "SELECT * FROM vtiger_vendor WHERE vendorid=".$id;
            $result = $adb->pquery($query);
            for ($index = 0; $index < $adb->num_rows($result); $index++) {
                //$ret[] = $adb->raw_query_result_rowdata($result,$index);
                $vid = $adb->query_result($result,$index,'distributor_id');
            }
            if($vid!='' &&  $vid!='null') return false;
            else return true;
    }
    
    function DeletemanualEntity($currentModule,$record) {
    global $adb;
    /*For scheme master record check start*/
    if($currentModule=='xValueclassification')
    {
    $Query = "SELECT mt.xschemeid FROM vtiger_xscheme mt LEFT JOIN vtiger_crmentity ct ON mt.xschemeid=ct.crmid ";
    $Query .= " LEFT JOIN vtiger_xschemecf st ON mt.xschemeid=st.xschemeid ";
    $Query .= " WHERE ct.deleted=0 AND st.retailer_value_classification like '%".$record."%' ";
    }
    else if($currentModule=='xGeneralclassification')
    {
    $Query = "SELECT mt.xschemeid FROM vtiger_xscheme mt LEFT JOIN vtiger_crmentity ct ON mt.xschemeid=ct.crmid ";
    $Query .= " LEFT JOIN vtiger_xschemecf st ON mt.xschemeid=st.xschemeid ";
    $Query .= " WHERE ct.deleted=0 AND st.retailer_general_classification like '%".$record."%' ";
    }
     else if($currentModule=='xCustomerGroup')
    {
    $Query = "SELECT mt.xschemeid FROM vtiger_xscheme mt LEFT JOIN vtiger_crmentity ct ON mt.xschemeid=ct.crmid ";
    $Query .= " LEFT JOIN vtiger_xschemecf st ON mt.xschemeid=st.xschemeid ";
    $Query .= " WHERE ct.deleted=0 AND st.retailer_customer_group like '%".$record."%' ";
    }
     else if($currentModule=='xDistributorCluster')
    {
    $Query = "SELECT mt.xschemeid FROM vtiger_xscheme mt LEFT JOIN vtiger_crmentity ct ON mt.xschemeid=ct.crmid ";
    $Query .= " LEFT JOIN vtiger_xschemecf st ON mt.xschemeid=st.xschemeid ";
    $Query .= " WHERE ct.deleted=0 AND st.scheme_distributor_cluster like '%".$record."%' ";
    }
    else if($currentModule=='xPotentialClassification')
    {
    $Query = "SELECT mt.xschemeid FROM vtiger_xscheme mt LEFT JOIN vtiger_crmentity ct ON mt.xschemeid=ct.crmid ";
    $Query .= " LEFT JOIN vtiger_xschemecf st ON mt.xschemeid=st.xschemeid ";
    $Query .= " WHERE ct.deleted=0 AND st.retailer_potential_classification like '%".$record."%' ";
    }else if($currentModule=='xTaxMapping'){
       /* $Query = "SELECT * FROM sify_xtransaction_tax_rel ";
        $Query .= " INNER JOIN vtiger_xtax on vtiger_xtax.taxcode=sify_xtransaction_tax_rel.tax_label";
        $Query .= " LEFT JOIN vtiger_xtaxmappingcf on vtiger_xtaxmappingcf.cf_xtaxmapping_sales_tax=vtiger_xtax.xtaxid";
        $Query .= " LEFT JOIN vtiger_xtaxmappingcf as m2 on m2.cf_xtaxmapping_purchase_tax=vtiger_xtax.xtaxid";
        $Query .= " WHERE sify_xtransaction_tax_rel.tax_label!='' AND ( vtiger_xtaxmappingcf.xtaxmappingid is NOT NULL OR m2.xtaxmappingid is NOT NULL)";
        $Query .=" AND (vtiger_xtaxmappingcf.xtaxmappingid='".$record."' OR m2.xtaxmappingid='".$record."')";
        // $Query .= " AND (vtiger_xtaxmappingcf.cf_xtaxmapping_sales_tax ='".$record."' OR vtiger_xtaxmappingcf.cf_xtaxmapping_purchase_tax ='".$record."')";
        $Query1 = " SELECT vtiger_xtaxmappingcf.xtaxmappingid,sify_xtransaction_tax_rel.tax_label from vtiger_xtaxmappingcf ";
        $Query1 .= " LEFT JOIN vtiger_xtax ON vtiger_xtax.xtaxid =  vtiger_xtaxmappingcf.cf_xtaxmapping_sales_tax";
        $Query1 .= " LEFT JOIN vtiger_xtax as vtax ON vtax.xtaxid =  vtiger_xtaxmappingcf.cf_xtaxmapping_purchase_tax";
        $Query1 .= " INNER JOIN sify_xtransaction_tax_rel ON sify_xtransaction_tax_rel.tax_label = vtax.taxcode";
        $Query1 .= " where vtiger_xtaxmappingcf.xtaxmappingid='".$record."'"; //45836
        */
        $Query .= "SELECT transaction_id FROM sify_xtransaction_tax_rel";
        $Query .= " INNER JOIN vtiger_xtaxmappingcf on vtiger_xtaxmappingcf.cf_xtaxmapping_product=sify_xtransaction_tax_rel.lineitem_id";
        $Query .= " AND vtiger_xtaxmappingcf.xtaxmappingid=".$record;
		$Query .= " UNION ALL ";
		$Query .= "SELECT transaction_id FROM sify_xtransaction_tax_rel_si tax";
        $Query .= " INNER JOIN vtiger_xtaxmappingcf on vtiger_xtaxmappingcf.cf_xtaxmapping_product=tax.lineitem_id";
        $Query .= " AND vtiger_xtaxmappingcf.xtaxmappingid=".$record;
        $Query .= " UNION ALL ";
        $Query .= "SELECT transaction_id FROM sify_xtransaction_tax_rel_so tax";
        $Query .= " INNER JOIN vtiger_xtaxmappingcf on vtiger_xtaxmappingcf.cf_xtaxmapping_product=tax.lineitem_id";
        $Query .= " AND vtiger_xtaxmappingcf.xtaxmappingid=".$record;
        $Query .= " UNION ALL ";
        $Query .= "SELECT transaction_id FROM sify_xtransaction_tax_rel_pi tax";
        $Query .= " INNER JOIN vtiger_xtaxmappingcf on vtiger_xtaxmappingcf.cf_xtaxmapping_product=tax.lineitem_id";
        $Query .= " AND vtiger_xtaxmappingcf.xtaxmappingid=".$record;
        $Query .= " UNION ALL ";
        $Query .= "SELECT transaction_id FROM sify_xtransaction_tax_rel_po tax";
        $Query .= " INNER JOIN vtiger_xtaxmappingcf on vtiger_xtaxmappingcf.cf_xtaxmapping_product=tax.lineitem_id";
        $Query .= " AND vtiger_xtaxmappingcf.xtaxmappingid=".$record;
        $Query .= " UNION ALL ";
        $Query .= "SELECT transaction_id FROM sify_xtransaction_tax_rel_sr tax";
        $Query .= " INNER JOIN vtiger_xtaxmappingcf on vtiger_xtaxmappingcf.cf_xtaxmapping_product=tax.lineitem_id";
        $Query .= " AND vtiger_xtaxmappingcf.xtaxmappingid=".$record;
        $Query .= " UNION ALL ";
        $Query .= "SELECT transaction_id FROM sify_xtransaction_tax_rel_rsi tax";
        $Query .= " INNER JOIN vtiger_xtaxmappingcf on vtiger_xtaxmappingcf.cf_xtaxmapping_product=tax.lineitem_id";
        $Query .= " AND vtiger_xtaxmappingcf.xtaxmappingid=".$record;
        $Query .= " UNION ALL ";
        $Query .= "SELECT transaction_id FROM sify_xtransaction_tax_rel_rpi tax";
        $Query .= " INNER JOIN vtiger_xtaxmappingcf on vtiger_xtaxmappingcf.cf_xtaxmapping_product=tax.lineitem_id";
        $Query .= " AND vtiger_xtaxmappingcf.xtaxmappingid=".$record;
        $Query .= " UNION ALL ";
        $Query .= "SELECT transaction_id FROM sify_xtransaction_tax_rel_pr tax";
        $Query .= " INNER JOIN vtiger_xtaxmappingcf on vtiger_xtaxmappingcf.cf_xtaxmapping_product=tax.lineitem_id";
        $Query .= " AND vtiger_xtaxmappingcf.xtaxmappingid=".$record;
        $Query .= " UNION ALL ";
        $Query .= "SELECT transaction_id FROM sify_xtransaction_tax_rel_service tax";
        $Query .= " INNER JOIN vtiger_xtaxmappingcf on vtiger_xtaxmappingcf.cf_xtaxmapping_product=tax.lineitem_id";
        $Query .= " AND vtiger_xtaxmappingcf.xtaxmappingid=".$record;
    }else if($currentModule=='xServiceTaxMapping'){
        $Query .= "SELECT * FROM vtiger_xserviceinvoice_details";
        $Query .= " INNER JOIN vtiger_xservicetaxmapping on vtiger_xservicetaxmapping.xservicecodeid=vtiger_xserviceinvoice_details.xservicecodeid";
        $Query .= " AND vtiger_xservicetaxmapping.xservicetaxmappingid=".$record;
    }
  
    $result = $adb->pquery($Query);
    $ret = $adb->num_rows($result);
    return $ret;
}

function getDistrInvConfig($key, $dist_id) {
        global $adb;
        $ret = array();
        $query = "SELECT * FROM sify_inv_mgt_config WHERE `key`='$key' and dist_id='$dist_id'";

        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret[$index] = $adb->raw_query_result_rowdata($result,$index);
        }
        return $ret;
}

function getDistrRoundOffInvConfig($dist_id) {
        global $adb;
        $ret = array();
        $query = "SELECT * FROM sify_inv_mgt_roundoff_config WHERE dist_id='$dist_id'";

        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret[$index] = $adb->raw_query_result_rowdata($result,$index);
        }
        return $ret;
}


function getGodownDefualtName() {
        global $adb;
        $ret = array();
        $buyer =  getDistrIDbyUserID();
        $query = "SELECT xgodownid,godown_name FROM vtiger_xgodown INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xgodown.xgodownid 
            WHERE vtiger_xgodown.xgodownid > 0 AND vtiger_crmentity.deleted = 0 AND xgodown_distributor = ? and xgodown_default = '1' and xgodown_active = '1'";

        $result = $adb->pquery($query, array($buyer['id']));
        $ret = $adb->raw_query_result_rowdata($result,0);
        return $ret;
}

function getRetailerSequence($salesman, $beat) {
        global $adb;
        $ret = array();
        $buyer  =  getDistrIDbyUserID();
        $query = "SELECT retseq.xsalesmanid,retseq.xbeatid,salesman.salesman,beat.beatname,retailer.customername,retseqrel.* FROM vtiger_xretailersequence as retseq
                INNER JOIN vtiger_xretailersequencerel as retseqrel ON retseqrel.xretailersequenceid = retseq.xretailersequenceid
                INNER JOIN vtiger_xsalesman as salesman ON salesman.xsalesmanid = retseq.xsalesmanid
                INNER JOIN vtiger_xbeat as beat ON beat.xbeatid = retseq.xbeatid
                INNER JOIN vtiger_xretailer as retailer ON retailer.xretailerid = retseqrel.xretailerid
                INNER JOIN vtiger_xretailercf AS retailercf ON retailercf.xretailerid = retailer.xretailerid
                INNER JOIN vtiger_crmentity AS crm ON crm.crmid = retailer.xretailerid
                INNER JOIN vtiger_xchannelhierarchy ON vtiger_xchannelhierarchy.xchannelhierarchyid = retailercf.cf_xretailer_channel_type
                INNER JOIN vtiger_xstate ON vtiger_xstate.xstateid = retailercf.cf_xretailer_state
                WHERE   retseq.xsalesmanid = ".$salesman."
                    AND retseq.xbeatid = ".$beat."
                    AND retseq.distributor_id = ".$buyer['id']."
                    AND retseq.active = 1
                    AND crm.deleted = 0
                    AND retailercf.cf_xretailer_active = 1
                    AND retailercf.cf_xretailer_status = 'Approved'
                    AND retailer.distributor_id = ".$buyer['id']."
                ORDER BY ABS(`coverage_sequence`) ASC";
        
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret[] = $adb->raw_query_result_rowdata($result,$index);
        }
        
        if(count($ret) == 0){
            $query = "SELECT RET.xretailerid, RET.customercode, RET.customername, BE.beatname
                    FROM vtiger_xretailer RET
                    INNER JOIN vtiger_xretailercf retailercf ON retailercf.xretailerid = RET.xretailerid
                    LEFT  JOIN vtiger_xbeat BE ON BE.xbeatid = retailercf.cf_xretailer_beat
                    INNER JOIN vtiger_crmentity CRM ON CRM.crmid = RET.xretailerid
                    INNER JOIN vtiger_crmentityrel CRMREL ON CRMREL.crmid = retailercf.xretailerid
                    INNER JOIN vtiger_xchannelhierarchy ON vtiger_xchannelhierarchy.xchannelhierarchyid = retailercf.cf_xretailer_channel_type
                    INNER JOIN vtiger_xstate ON vtiger_xstate.xstateid = retailercf.cf_xretailer_state
                    WHERE CRM.deleted = '0' 
                        AND RET.xretailerid > 0  
                        AND retailercf.cf_xretailer_status ='Approved' 
                        AND retailercf.cf_xretailer_active='1' 
                    AND CRMREL.relcrmid ='".$beat."' AND RET.distributor_id ='".$buyer['id']."' ORDER BY RET.customername asc";
            
            $result = $adb->pquery($query);
             
            for ($index = 0; $index < $adb->num_rows($result); $index++) {
                $ret[] = $adb->raw_query_result_rowdata($result,$index);
            }
            
        }
        
        return $ret;
}
function getRoundOff($mod) {
        global $adb;
        $ret = array();
        $buyer =  getDistrIDbyUserID();
        //$query = "SELECT xgodownid,godown_name FROM vtiger_xgodown WHERE xgodown_distributor=? and xgodown_default='1'";
			$result = $adb->pquery("select * from sify_inv_mgt_roundoff_config where dist_id='".$buyer['id']."' and transaction='".$mod."'");
		if($adb->num_rows($result) == 0){
			$result = $adb->pquery("select * from sify_inv_mgt_roundoff_config where dist_id = 0 and transaction='".$mod."'");
		}
        //$result = $adb->pquery($query, array($buyer['id']));
        if($adb->query_result($result,0,'value') > 0){
            echo "<input type=hidden name=ro_amount id=ro_amount value='".$adb->query_result($result,0,'round_off_amount')."'>";
            echo "<input type=hidden name=ro_rule id=ro_rule value='".$adb->query_result($result,0,'round_off_rule')."'>";
        }else{
            echo "<input type=hidden name=ro_amount id=ro_amount value=''>";
            echo "<input type=hidden name=ro_rule id=ro_rule value=''>";
        }
       //$adb->query_result($result,0,'round_off_rule');
        //return $ret; 
}

function getsetRoundOff($mod,$gettotal) {
        global $adb;
        $ret = array();  
        $buyer =  getDistrIDbyUserID();
			$result = $adb->pquery("select * from sify_inv_mgt_roundoff_config where dist_id='".$buyer['id']."' and transaction='".$mod."'");
		if($adb->num_rows($result) == 0){
			$result = $adb->pquery("select * from sify_inv_mgt_roundoff_config where dist_id = 0 and transaction='".$mod."'");
		}
            if($adb->num_rows($result) > 0)
                                {
                                        $roundamount = $adb->query_result($result,0,'round_off_amount');
                                        $roundrule =$adb->query_result($result,0,'round_off_rule');
                                        $splitgettotal = explode('.',$gettotal);  
                                        if($roundamount != '' && $roundrule != '' && $splitgettotal[1] > 0)
                                            {                
                                                    if($roundamount == "50 Paisa"){ 
                                                                    $tempamt = 0;
                                                                    $ptest = '0.'.$splitgettotal[1];
                                                                    if($roundrule == "Higher"){
                                                                        if($ptest < 0.51){
                                                                          $tempamt = 0.50;
                                                                        }else{
                                                                           $tempamt = 1.00; 
                                                                        }
                                                                    }else if($roundrule == "Nearest"){
                                                                        if($ptest < 0.25){
                                                                          $tempamt = 0.00;
                                                                        }else if($ptest > 0.24 && $ptest < 0.50){
                                                                           $tempamt = 0.50; 
                                                                        }else if($ptest > 0.74 && $ptest < 1.00){
                                                                           $tempamt = 1.00; 
                                                                        }else if($ptest > 0.50 && $ptest < 0.75){
                                                                           $tempamt = 0.50; 
                                                                        }
                                                                    }else if($roundrule == "Lower"){
                                                                       if($ptest < 0.51){
                                                                          $tempamt = 0.00;
                                                                        }else{
                                                                           $tempamt = 0.50; 
                                                                        } 
                                                                    }                                                                   
                                                                    $getgrandtotal = $splitgettotal[0]+$tempamt;
                                                                    }else if($roundamount == "1 Rupee"){
                                                                            if($roundrule == "Higher"){
                                                                                    $getgrandtotal = ceil($gettotal);
                                                                            }else if($roundrule == "Nearest"){
                                                                                     $getgrandtotal = round($gettotal);
                                                                            }else if($roundrule == "Lower"){
                                                                                            $getgrandtotal = floor($gettotal);
                                                                            } 
                                                                      }else{
                                                                             $getgrandtotal=$gettotal;
                                                                           } 
                                                        }else{
                                                                $getgrandtotal=$gettotal;
                                                             }            
                                                }else{
                                                $getgrandtotal=$gettotal;
                                                    if($gettotal=='pdfroundcheck')
                                                    {
                                                        $getgrandtotal='notroundconfig';
                                                    }
                                                } 
        return $getgrandtotal; 
}

function getprintconfig($reset=false) {
        global $adb;
        $ret = array();
        $distid =  getDistrIDbyUserID();
        $ret = $_SESSION['NXT_PRINT_CONFIG']; 
        if(count($ret)<=0 || $reset){  
			$result = $adb->pquery("select `key`,`value` from sify_inv_mgt_config where dist_id='".$distid['id']."' and treatment='PRINT'");
                        $num_rows=$adb->num_rows($result);
                        if($num_rows<=0)
                        {
                            $result = $adb->pquery("select `key`,`value` from sify_inv_mgt_config where dist_id = 0 and treatment='PRINT'"); 
                            $num_rows=$adb->num_rows($result);
                        }
                        for($i=0;$i<=$num_rows;$i++){      
                            $_SESSION[$adb->query_result($result,$i,'key')]=$adb->query_result($result,$i,'value');
                            $ret[$adb->query_result($result,$i,'key')]=$adb->query_result($result,$i,'value');
                        }                        
                        $_SESSION['NXT_PRINT_CONFIG'] = $ret; 
                        //echo "<pre>";print_r($ret);exit;
        }
}

function getprintformate($module,$record,$template,$print_option) {
global $adb,$si_id,$print_copy_name;
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
// set document information
$pdf->SetCreator(PDF_CREATOR);
// set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
// set auto page breaks
if($template=='T1' || $template=='T2')
$pdf->SetAutoPageBreak(TRUE, FOOTER +55);
else
$pdf->SetAutoPageBreak(TRUE, FOOTER);    
// set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
//Template selection from parameter
if($template=='T4')
{
    $settemplateheader = HEADERDMHP;
    $settemplatefooter = FOOTERDMHP;
    $setpagesize = PAGE_SIZEDMHP;
}
elseif($template=='T3')
{
    $settemplateheader = HEADERHP;
    $settemplatefooter = FOOTERHP;
    $setpagesize = PAGE_SIZEHP;
}
elseif($template=='T2')
{
    $settemplateheader = HEADERDM;
    $settemplatefooter = FOOTERDM;
    $setpagesize = 'PAGE_SIZEDM';
}
else
{
    $settemplateheader = HEADER;
    $settemplatefooter = FOOTER;
    $setpagesize = PAGE_SIZE;
}    
// set some language-dependent strings (optional)
if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
require_once(dirname(__FILE__).'/lang/eng.php');
$pdf->setLanguageArray($l);
}
// set font
$pdf->SetFont('', '', 8);
	if($module=='SalesInvoice')
	{
			if(isset($_REQUEST['print_option'])){ // For Printing the copy name
			$print_option = explode('@',$_REQUEST['print_option']);
			}
			else {
			$print_option[] = 1; // For Default Printing the copy name as Original
			}
			//$print_copy_count =1;
			foreach ($print_option as $print_copy){
				if($print_copy !=0){
                                    if($print_copy==1) $print_copy_name = "Original - Buyer's Copy";
                                    if($print_copy==2) $print_copy_name = "Duplicate - Transporter's Copy";
                                    if($print_copy==3) $print_copy_name = "Triplicate - Office Copy";
                                    $si_id = $record;
                                    $pdf->currentSiID=$si_id;
                                    // set margins
                                    if($template=='T1' || $template=='T2')
                                    {
                                    $pdf->SetMargins(10, $settemplateheader+43, 10);
                                    $pdf->SetHeaderMargin($settemplateheader);
                                    $pdf->SetFooterMargin($settemplatefooter+55);
                                    }
                                    else
                                    {
                                    $pdf->SetMargins(10, $settemplateheader, 10);
                                    $pdf->SetHeaderMargin($settemplateheader);
                                    $pdf->SetFooterMargin($settemplatefooter);                                                    
                                    }

                                    $pdf->my_page_no = 1;
                                    // add a page
                                    $pdf->AddPage('',$setpagesize,false,false);
                                    //if($print_copy_count==1) {
                                    $html123 = $pdf->BodyOfTheContent($si_id);
                                    //	$print_copy_count++;
                                    //}
                                    // output the HTML content
                                    $pdf->writeHTML($html123, true, false, true, false, '');

                                    // reset pointer to the last page
                                    $pdf->lastPage();
				}
			}
	}
	if($module=='SIBulkPrint')
	{
	 //echo '<pre>'; print_r($record); die;
                        if($print_option!=''){ // For Printing the copy name
			$print_option = explode('@',$print_option);
			}
			else {
			$print_option[] = 1; // For Default Printing the copy name as Original
			}
			if(is_array($record))
			{
					for($i=0; $i<count($record); $i++)
					{
						$si_id = $record[$i];
                                                foreach ($print_option as $print_copy){
                                                if($print_copy !=0){
                                                if($print_copy==1) $print_copy_name = "Original - Buyer's Copy";
                                                if($print_copy==2) $print_copy_name = "Duplicate - Transporter's Copy";
                                                if($print_copy==3) $print_copy_name = "Triplicate - Office Copy";
						if($si_id > 0)
						{
                                                    
                                                    
							// create new PDF document
                                                if($template=='T1' || $template=='T2')
                                                {                                                    
							$pdf->SetMargins(10, $settemplateheader+43, 10);
							$pdf->SetHeaderMargin($settemplateheader);
							$pdf->SetFooterMargin($settemplatefooter+55);
                                                }
                                                else
                                                {
							$pdf->SetMargins(10, $settemplateheader, 10);
							$pdf->SetHeaderMargin($settemplateheader);
							$pdf->SetFooterMargin($settemplatefooter);                                                    
                                                }
						
							//$pdf->my_page_no = 1;                                                        
							$pdf->AddPage('', '', false, false, true);
                                                        $pdf->currentSiID=$si_id;
							$html = $pdf->BodyOfTheContent($si_id);
							$pdf->writeHTML($html, true, false, true, false, '');

							$pdf->lastPage();
						}
                                                }
                                                }
					}
            }					
							
	}
        
        if($module=='xSalesReturn')
	{
			if(isset($_REQUEST['print_option'])){ // For Printing the copy name
			$print_option = explode('@',$_REQUEST['print_option']);
			}
			else {
			$print_option[] = 1; // For Default Printing the copy name as Original
			}
			//$print_copy_count =1;
			foreach ($print_option as $print_copy){
				if($print_copy !=0){
                                    if($print_copy==1) $print_copy_name = "Original - Buyer's Copy";
                                    if($print_copy==2) $print_copy_name = "Duplicate - Transporter's Copy";
                                    if($print_copy==3) $print_copy_name = "Triplicate - Office Copy";
                                    $si_id = $record;
                                    $pdf->currentSiID=$si_id;
                                    // set margins
                                    if($template=='T1' || $template=='T2')
                                    {
                                    $pdf->SetMargins(10, $settemplateheader+43, 10);
                                    $pdf->SetHeaderMargin($settemplateheader);
                                    $pdf->SetFooterMargin($settemplatefooter+55);
                                    }
                                    else
                                    {
                                    $pdf->SetMargins(10, $settemplateheader, 10);
                                    $pdf->SetHeaderMargin($settemplateheader);
                                    $pdf->SetFooterMargin($settemplatefooter);                                                    
                                    }

                                    $pdf->my_page_no = 1;
                                    // add a page
                                    $pdf->AddPage('',$setpagesize,false,false);
                                    //if($print_copy_count==1) {
                                    $html123 = $pdf->BodyOfTheContent($si_id);
                                    //	$print_copy_count++;
                                    //}
                                    // output the HTML content
                                    $pdf->writeHTML($html123, true, false, true, false, '');

                                    // reset pointer to the last page
                                    $pdf->lastPage();
				}
			}
	}
       //if($module=='PODashboard'){
       //    $html123 = $pdf->BodyOfTheContent(12);
       //    $pdf->lastPage();
       //}
	// force print dialog
	$js = 'print(true);';
	// set javascript
	$pdf->IncludeJS($js);
	/* Added For WaterMark Start */
        if($template=='T3')
        {
            $setwatermark = WATERMARKHP;
        }  
        else
        {
             $setwatermark = WATERMARK;
        }
	if ($setwatermark==1){
	$ImageW = 105; //WaterMark Size 
	$ImageH = 80; 
	$total_page = $pdf->getNumPages();
	for ($i=1; $i<= $total_page; $i++){
	$pdf->setPage( $i ); //WaterMark Page     
	$myPageWidth = $pdf->getPageWidth(); 
	$myPageHeight = $pdf->getPageHeight(); 
	$myX = ( $myPageWidth / 2 ) - 50;  //WaterMark Positioning 
	$myY = ( $myPageHeight / 2 ) -40; 

	$pdf->SetAlpha(0.05); 
	$pdf->Image('test/logo/sifylogo_watermark.jpg', $myX, $myY, $ImageW, $ImageH, '', '', '', true, 150); 
        $pdf->SetAlpha(1); 
	}
	$pdf->SetAlpha(1); //Reset Alpha Setings
	}   /* Added For WaterMark End */
	//Close and output PDF document
        ob_end_clean();
	$pdf->Output('SalesInvoice.pdf', 'FI');
	
}

function getprintdosmatrixformate($module,$record,$template) {  //echo '$module:'.$module.'<br>'; echo 'Get 2nd rec:'.$record.'<br>';
global $adb,$si_id;
// create new PDF document
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
if($module=='SalesInvoice') 
{ 
// set document information
$pdf->SetCreator(PDF_CREATOR);

// set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// set auto page breaks
if($template=='T1' || $template=='T2')
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_FOOTER +55);
else
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_FOOTER);    
// set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

//Template selection from parameter
if($template=='T4')
{
    $settemplateheader = HEADERDMHP;
    $settemplatefooter = FOOTERDMHP;
}
elseif($template=='T3')
{
    $settemplateheader = HEADERHP;
    $settemplatefooter = FOOTERHP;
}
elseif($template=='T2')
{
    $settemplateheader = HEADERDM;
    $settemplatefooter = FOOTERDM;
}
else
{
    $settemplateheader = HEADER;
    $settemplatefooter = FOOTER;
} 

// set some language-dependent strings (optional)
if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
    require_once(dirname(__FILE__).'/lang/eng.php');
    $pdf->setLanguageArray($l);
}

// set font
$pdf->SetFont('', '', 7);

}
	if($module=='SalesInvoice' && $template=='T2') 
	{   $si_id = $record; // echo 'Get 3rt rec:'.$si_id.'<br>';// exit;
        // set margins
            //$pdf->SetMargins(10, PDF_MARGIN_TOP, 10);
        if($template=='T1' || $template=='T2')
        {
            $pdf->SetMargins(10, PDF_MARGIN_HEADER+43, 10);
            $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
            $pdf->SetFooterMargin(PDF_MARGIN_FOOTER+55);
        }
        else
        {
            $pdf->SetMargins(10, PDF_MARGIN_HEADER, 10);
            $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
            $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);            
        }
            $pdf->my_page_no = 1;

            // add a page
            $pdf->AddPage();            
            $html = $pdf->BodyOfTheContent($si_id);   
            print_r($html);
            $pdf->Footer();
	}
        elseif($module=='SalesInvoice' && $template=='T4')
        { 
                $si_id = $record;
                if(isset($_REQUEST['print_option'])){ // For Printing the copy name
                $print_option = explode('@',$_REQUEST['print_option']);
                }
                else {
                $print_option[] = 1; // For Default Printing the copy name as Original
                }
                //$print_copy_count =1;
                foreach ($print_option as $print_copy){
                    
                    if($print_copy !=0){
                            if($print_copy==1) $print_copy_name = "Original - Buyer's Copy";
                            if($print_copy==2) $print_copy_name = "Duplicate - Transporter's Copy";
                            if($print_copy==3) $print_copy_name = "Triplicate - Office Copy";
                            $pdf->my_page_no = 1;
                            $pdf->BodyOfTheContent($si_id);
                        }
                }
            
        }
	elseif($module=='SIBulkPrint' && $template=='T2')
	{
	 //echo '<pre>'; print_r($record); die;
			if(is_array($record))
			{
					for($i=0; $i<count($record); $i++)
					{
						$si_id = $record[$i];
						if($si_id > 0)
						{							 
                                                        $pdf->my_page_no = 1;
                                                        $pdf->Header();
                                                        $html = $pdf->BodyOfTheContent($si_id);            
                                                        print_r($html);
                                                        $pdf->Footer();
                                                        echo '<p style="page-break-after:always;"></p>';                                                    
						}
					}
            }					
							
	}	
	elseif($module=='SIBulkPrint' && $template=='T4')
	{
	 //echo '<pre>'; print_r($record); die;
			if(is_array($record))
			{
					for($i=0; $i<count($record); $i++)
					{
						$si_id = $record[$i];
						if($si_id > 0)
						{							 
                                                    $pdf->my_page_no = 1;
                                                    $pdf->BodyOfTheContent($si_id);
						}
					}
            }					
							
	}        
	
}
//Merchandise product mapping Distributor Details
    function getDistributorRevoke($id){
        global $adb;
        $revoke_det = array();          
        $query = "SELECT vtiger_xdistributorclusterrel.distributorid,vtiger_xdistributorclusterrel.distclusterid,vtiger_xdistributorclusterrel.addition_date,
                vtiger_xmerchandiseproductsmapping.distributorcluster_name,
                vtiger_xdistributor.distributorname, vtiger_xdistributor.distributorcode,vtiger_xdistributor.xdistributorid,
                vtiger_xdistributorcluster.distributorclustername,vtiger_xdistributorcluster.distributorclustercode,vtiger_xdistributorcluster.active
                FROM vtiger_xmerchandiseproductsmapping LEFT JOIN vtiger_xdistributorclusterrel ON
                vtiger_xdistributorclusterrel.distclusterid = vtiger_xmerchandiseproductsmapping.distributorcluster
                LEFT JOIN vtiger_xdistributorcluster ON vtiger_xdistributorcluster.xdistributorclusterid = vtiger_xdistributorclusterrel.distclusterid
                LEFT JOIN vtiger_xdistributor ON vtiger_xdistributor.xdistributorid = vtiger_xdistributorclusterrel.distributorid
                where vtiger_xmerchandiseproductsmapping.distributorcluster= $id";    
         $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $revoke_det[$index] = $adb->raw_query_result_rowdata($result,$index);
        }
        return $revoke_det;
    }
    function getMerchandiseProductMapping($id) {
            global $adb; $ret = array();
                $query = "SELECT * FROM vtiger_xmerchandiseproductsmapping  mt 
                        LEFT JOIN vtiger_xdistributorcluster dc ON dc.xdistributorclusterid=mt.distributorcluster 
                        WHERE mt.xmerchandiseproductsmappingid=".$id." LIMIT 1 ";
            $result = $adb->pquery($query);
            for ($index = 0; $index < $adb->num_rows($result); $index++) {
                $ret[] = $adb->raw_query_result_rowdata($result,$index);
            }
        return $ret;
    }
    
    function getAllClusterDistibutor($disid){
         global $adb; $ret = array();
        $query = "SELECT distclusterid FROM vtiger_xdistributorclusterrel
WHERE distributorid = ".$disid."";
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret[] = $adb->raw_query_result_rowdata($result,$index);
        }
         return $ret;
    }
    
    function getAllRetailerMapGenrealClass($crmid) {
           global $adb;
           $Qry = "SELECT xgeneralclassificationid from vtiger_xgeneralclass_mrel WHERE crmid=$crmid AND relmodule='xRetailerProductMapping'";
           
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->query_result($result,$index, 'xgeneralclassificationid');
	     }
             return $ret;
     }
     function getAllRetailerMapValueClass($crmid) {
           global $adb;
           $Qry = "SELECT xvalueclassificationid from vtiger_xvalueclass_mrel WHERE crmid=$crmid AND relmodule='xRetailerProductMapping'";
           
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->query_result($result,$index, 'xvalueclassificationid');
	     }
             return $ret;
      }
      function getAllRetailerMapPotentailClass($crmid) {
           global $adb;
           $Qry = "SELECT xpotentialclassificationid from vtiger_xpotentialclass_mrel WHERE crmid=$crmid AND relmodule='xRetailerProductMapping'";
           
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->query_result($result,$index, 'xpotentialclassificationid');
	     }
             return $ret;
      }
      function getAllRetailerMapCustGrp($crmid) {
           global $adb;
           $Qry = "SELECT xcustomergroupid from vtiger_xcustomergroup_mrel WHERE crmid=$crmid AND relmodule='xRetailerProductMapping'";
          
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->query_result($result,$index, 'xcustomergroupid');
	     }
             return $ret;
      }
      function getAllRetailerMapChannelHierachy($crmid) {
           global $adb;
           $Qry = "SELECT xchannelhierarchyid from vtiger_xchannelhier_mrel WHERE crmid=$crmid AND relmodule='xRetailerProductMapping'";
           
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->query_result($result,$index, 'xchannelhierarchyid');
	     }
             return $ret;
      }
      function getAllRetailerProdMap($crmid) {
           global $adb;
           $Qry = "SELECT xretailerid from vtiger_xretailerproductsmappingrel WHERE xretailerproductmappingid=$crmid";
        
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->query_result($result,$index, 'xretailerid');
	     }
             return $ret;
      }
    
      function checkTheDistributorEleglbleForStock($distId,$schemeId,$amount)
      {
          global $adb;
      }
       function Claimdistrevokesave($clusid,$salesmapid) {
		global $adb;
		$ret = array();
                $focus=CRMEntity::getInstance('xClaimNormDistributorRevoke');
                
		 //$query = "SELECT ft.*,st.distributorname,st.distributorcode,st.xdistributorid,tt.distributorclustername,tt.distributorclustercode FROM vtiger_xdistributorclusterrel ft,vtiger_xdistributor st,vtiger_xdistributorcluster tt  WHERE ft.distclusterid=".$clusid." and ft.distributorid=st.xdistributorid and ft.distclusterid=tt.xdistributorclusterid";
$query = "SELECT dcr.distributorid
            FROM vtiger_xdistributorclusterrel dcr
            LEFT JOIN vtiger_xdistributorcluster dc on dc.xdistributorclusterid=dcr.distclusterid
            WHERE dc.active='1' and dcr.distclusterid in (".$clusid.") group by dcr.distributorid";                 
		$result = $adb->pquery($query);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
                    $focus->column_fields['xdistributorid'] = $adb->query_result($result,$index,'distributorid');
                    $focus->column_fields['xclaimnormid'] = $salesmapid;                    
                    $focus->column_fields['revoke_date'] = '';
                    $x=new CRMEntity();
                    $revoke_code= $x->seModSeqNumber('increment','xClaimNormDistributorRevoke');                   
                    $focus->column_fields['code'] = $revoke_code;  
                    
                    $focus->save('xClaimNormDistributorRevoke');
                    $return_id = $focus->id;
                    $updateQry = "UPDATE vtiger_xclaimnormdistributorrevoke SET xclaimnormid='$salesmapid' where xclaimnormdistributorrevokeid='$return_id'";    
                    $adb->pquery($updateQry);                    
                   // echo '<br>'.'retur id'.$return_id.'<br>';
                     //exit;
                    if($salesmapid!="" && $return_id!=""){
                    $insert = "insert into vtiger_crmentityrel(crmid,module,relcrmid,relmodule) values('".$salesmapid."','xClaimNorm','".$return_id."','xClaimNormDistributorRevoke')";
                    $adb->pquery($insert);
                    }
		}
             // exit;
              
              //  return $ret;
	}  
function getBeatbySalesman($distId,$salesman,$typeBeat='',$classBeat,$module=''){ 
    global $adb;
      /* $beatResQuery="SELECT be.xbeatid as id ,be.beatname FROM vtiger_xbeat be
                            LEFT JOIN vtiger_crmentity cr ON cr.crmid = be.xbeatid
                            LEFT JOIN vtiger_xbeatcf btcf ON btcf.xbeatid = be.xbeatid
                            LEFT JOIN vtiger_crmentityrel ctr ON be.xbeatid=ctr.relcrmid
                            WHERE cr.deleted = 0 AND be.cf_xbeat_distirbutor_id = '".$distId."'";
       $beatResQuery="SELECT be.xbeatid as id ,be.beatname FROM vtiger_salesinvoicecf sicf
                            LEFT JOIN vtiger_xbeat be ON be.xbeatid = sicf.cf_salesinvoice_beat
                            LEFT JOIN vtiger_crmentity cr ON cr.crmid = be.xbeatid
                            LEFT JOIN vtiger_xbeatcf btcf ON btcf.xbeatid = be.xbeatid
                            WHERE cr.deleted = 0 AND btcf.cf_xbeat_active=1
                            ";    */ 
         
        $beatquery = '';
        if($module == 'CounterSalesInvoiceReport')
            $beatquery = " AND be.counter_sales_beat=1";
 
        $beatResQuery="SELECT be.xbeatid as id ,be.beatname,be.beatcode FROM vtiger_xbeat be
                            LEFT JOIN vtiger_crmentity cr ON cr.crmid = be.xbeatid
                            LEFT JOIN vtiger_xbeatcf btcf ON btcf.xbeatid = be.xbeatid
                            LEFT JOIN vtiger_crmentityrel ctr ON be.xbeatid=ctr.relcrmid
                            WHERE cr.deleted = 0 AND btcf.cf_xbeat_active=1 $beatquery ";
       if($distId!='' && $distId!=null)       
           $beatResQuery.=" AND be.cf_xbeat_distirbutor_id IN (".$distId.") ";
           
      if($salesman!='' && $salesman!=null)                
       //$beatResQuery.=" AND sicf.cf_salesinvoice_sales_man IN (".$salesman.")";
       $beatResQuery .= " AND ctr.crmid IN (".$salesman.")";
      if($classBeat!='' && $classBeat!='null' && $classBeat!='undefined')
       {
         $classBeat = str_replace(',', "','", $classBeat);
      $beatResQuery .= " AND be.classification IN('$classBeat')";
       }
      //echo $beatResQuery;exit;
     //return $beatResQuery;
    /*if(empty($salesman)){
        $condition= "";
    }else{
         $pos = explode(',',$salesman);
        if(count($pos)==1){
      $pQry = "SELECT DISTINCT p.relcrmid FROM vtiger_crmentityrel p LEFT JOIN vtiger_crmentity ct ON p.relcrmid=ct.crmid WHERE ct.deleted=0 and p.crmid=".$salesman." and module='xSalesman' and relmodule='xBeat'";
        }else{
            $pQry = "SELECT DISTINCT p.relcrmid FROM vtiger_crmentityrel p LEFT JOIN vtiger_crmentity ct ON p.relcrmid=ct.crmid WHERE ct.deleted=0 and p.crmid IN (".$salesman.") and module='xSalesman' and relmodule='xBeat'";
        }
         $result = $adb->pquery($pQry);
                for ($index = 0; $index < $adb->num_rows($result); $index++) {
                                $ret[] =  $adb->query_result($result,$index,'relcrmid');
                            }
            $beatid = implode(',',array_filter($ret)); 
           
            if($beatid!=""){
                $condition=" AND be.xbeatid IN (".$beatid.")";
            }
    }
    $beatResQuery .=$condition;*/
    //echo $beatResQuery;  
      //echo '<pre>'; print_r($beatResQuery); die;
    $beatRes= $adb->pquery($beatResQuery);
     for ($index1 = 0; $index1 < $adb->num_rows($beatRes); $index1++) {
            $Arr = $adb->raw_query_result_rowdata($beatRes,$index1);
			if($typeBeat  == 'BeatID')
            $ret1[$Arr['id']] = $Arr['beatname']."-".$Arr['beatcode']."";
		else
			$ret1[$Arr['id']] = $Arr['beatname']."-".$Arr['beatcode']."";
        }

        if(!empty($ret1)) return $ret1;
        else return "";
        
       
} 
/* FRPRDINXT-697 */
function getRetailerSalesmanBeat($distId,$beat=null,$sm=null,$typeId='',$sourcefrom='',$module=''){ 
    global $adb;
    
      /* $pos = explode(',',$sm);
        if(count($pos)==1){
            $salescondition = " p.crmid=".$sm."";
        }else{
            $salescondition = " p.crmid IN (".$sm.")";
        }
        $posbeat = explode(',',$beat);
        if(count($posbeat)==1){
             $beatcondition = "p.relcrmid=". $beat."";
        }else{
            $beatcondition = "p.relcrmid IN (". $beat.")";
        } */   
        /* if($sm=='' || $sm==null)
             $sm="''";
         if($beat=='' || $beat==null)
             $beat="''"; */       
        /*      
        $query="SELECT re.xretailerid,re.customername FROM vtiger_salesinvoice si
                            LEFT JOIN vtiger_xretailer re ON re.xretailerid = si.vendorid
                            LEFT JOIN vtiger_crmentity cr ON cr.crmid = re.xretailerid
                            LEFT JOIN vtiger_xretailercf recf ON recf.xretailerid = re.xretailerid
                            LEFT JOIN vtiger_crmentityrel ctr ON be.xbeatid=ctr.relcrmid
                            LEFT JOIN vtiger_salesinvoicecf sicf ON sicf.salesinvoiceid = si.salesinvoiceid
                            WHERE cr.deleted = 0 AND recf.cf_xretailer_active=1";
        */
		$wherActive =' AND recf.cf_xretailer_active=1';
		if($sourcefrom=='report'){
			$wherActive ='';
		}
	
        $counter_cust_query = '';
        if($module == 'CounterSalesInvoiceReport')
            $counter_cust_query = " AND re.counter_sales_customer=1";
        
        $query = "SELECT re.xretailerid,re.customername,re.customercode FROM vtiger_xretailer re
                    LEFT JOIN vtiger_crmentity cr ON cr.crmid = re.xretailerid
                    LEFT JOIN vtiger_xretailercf recf ON recf.xretailerid = re.xretailerid
                    LEFT JOIN vtiger_crmentityrel ctr ON re.xretailerid = ctr.crmid
                    LEFT JOIN vtiger_xbeat be ON be.xbeatid = ctr.relcrmid
                    LEFT JOIN vtiger_crmentityrel ctr1 ON be.xbeatid = ctr1.relcrmid
                    LEFT JOIN vtiger_xsalesman sm ON sm.xsalesmanid = ctr1.crmid
                    WHERE cr.deleted = 0 $wherActive $counter_cust_query ";        
      if($distId!='' && $distId!='null'){
          $query.="  AND re.distributor_id IN (".$distId.") ";
      }        
        
      if($sm!='' && $sm!='null')                
       //$query.=" AND sicf.cf_salesinvoice_sales_man IN (".$sm.")";
          $query.=" AND sm.xsalesmanid IN (".$sm.")";
      if($beat!='' && $beat!='null')
       //$query.=" AND sicf.cf_salesinvoice_beat IN (".$beat.")";        
       $query.=" AND be.xbeatid IN (".$beat.")";
       // return $query;
    /* $query="SELECT re.xretailerid,re.customername FROM vtiger_xretailer re
                            INNER JOIN vtiger_crmentity cr ON cr.crmid = re.xretailerid
                            INNER JOIN vtiger_xretailercf recf ON recf.xretailerid = re.xretailerid
                            WHERE cr.deleted = 0 AND re.distributor_id = '".$distId."' AND recf.cf_xretailer_active=1
                            AND re.xretailerid IN (".$salemanid.")";*/

   /* if($sm!="" && $beat!=""){
                //$pQry = "SELECT DISTINCT p.crmid FROM vtiger_crmentityrel p LEFT JOIN vtiger_crmentity ct ON p.relcrmid=ct.crmid WHERE ct.deleted=0 and p.relcrmid IN (SELECT p.relcrmid FROM vtiger_crmentityrel p LEFT JOIN vtiger_crmentity ct ON p.relcrmid=ct.crmid WHERE ct.deleted=0 and  p.crmid=".$sm." and  p.relcrmid=". $beat." and relmodule='xBeat') and module='xRetailer'";             
                $pQry = "SELECT DISTINCT p.crmid FROM vtiger_crmentityrel p LEFT JOIN vtiger_crmentity ct ON p.relcrmid=ct.crmid WHERE ct.deleted=0 and p.relcrmid IN (SELECT p.relcrmid FROM vtiger_crmentityrel p LEFT JOIN vtiger_crmentity ct ON p.relcrmid=ct.crmid WHERE ct.deleted=0 and  $salescondition  and $beatcondition and relmodule='xBeat') and module='xRetailer'";             
            }elseif($sm=="" &&  $beat!=""){
               // $pQry = "SELECT DISTINCT p.crmid FROM vtiger_crmentityrel p LEFT JOIN vtiger_crmentity ct ON p.relcrmid=ct.crmid WHERE ct.deleted=0 and p.relcrmid=".$beat." and module='xRetailer' and relmodule='xBeat'";
                $pQry = "SELECT DISTINCT p.crmid FROM vtiger_crmentityrel p LEFT JOIN vtiger_crmentity ct ON p.relcrmid=ct.crmid WHERE ct.deleted=0 and $beatcondition and module='xRetailer' and relmodule='xBeat'";
            }elseif($sm!="" &&  $beat==""){
               // $pQry = "SELECT DISTINCT p.crmid FROM vtiger_crmentityrel p LEFT JOIN vtiger_crmentity ct ON p.relcrmid=ct.crmid WHERE ct.deleted=0 and p.relcrmid IN (SELECT DISTINCT p.relcrmid FROM vtiger_crmentityrel p LEFT JOIN vtiger_crmentity ct ON p.relcrmid=ct.crmid WHERE ct.deleted=0 and p.crmid=".$sm." and module='xSalesman' and relmodule='xBeat') and module='xRetailer'";
               $pQry = "SELECT DISTINCT p.crmid FROM vtiger_crmentityrel p LEFT JOIN vtiger_crmentity ct ON p.relcrmid=ct.crmid WHERE ct.deleted=0 and p.relcrmid IN (SELECT DISTINCT p.relcrmid FROM vtiger_crmentityrel p LEFT JOIN vtiger_crmentity ct ON p.relcrmid=ct.crmid WHERE ct.deleted=0 and $salescondition  and module='xSalesman' and relmodule='xBeat') and module='xRetailer'";
            } return $pQry;
       if($pQry != ""){
                $result = $adb->pquery($pQry);
                for ($index = 0; $index < $adb->num_rows($result); $index++) {
                                $ret1[] =  $adb->query_result($result,$index,'crmid');
                            }            
                 $salemanid = implode(',',array_filter($ret1));  
                 if(count($ret1)>0){
                    $query .= " AND re.xretailerid IN (".$salemanid.") ";
                 }else{
                    //$query .= " AND vtiger_xsalesman.xsalesmanid IN (0) "; 
                 }
             }*/
             $retRes=$adb->pquery($query);
              for ($index1 = 0; $index1 < $adb->num_rows($retRes); $index1++) {
            $Arr = $adb->raw_query_result_rowdata($retRes,$index1);
			if($typeId == 'CustomerId')
				$ret2[$Arr['xretailerid']] = $Arr['customername']."-".$Arr['customercode'];
				else
            $ret2[$Arr['xretailerid']] = $Arr['customername'];
                                
            if($module == 'CounterSalesInvoiceReport'){
                 for ($index1 = 0; $index1 < $adb->num_rows($retRes); $index1++) {
                    $rgc .= $adb->query_result($retRes,$index1,'xretailerid').',';
                    
                 }
                 $rgc = rtrim($rgc,",");
                 
                 $query1 ='';
                 if($distId!='' && $distId!='null'){
                    $query1 =" AND sicf.cf_salesinvoice_seller_id ='".$distId."' ";
                 }
      
                 $retRes=$adb->pquery("SELECT cs.xcountersalesid as xretailerid ,cs.customername,cs.customercode FROM vtiger_salesinvoice si inner join vtiger_salesinvoicecf sicf on (sicf.salesinvoiceid=si.salesinvoiceid) inner join sify_countersales_customer_master cs on cs.xcountersalesid=si.countersales_customerid WHERE sicf.cf_salesinvoice_buyer_id IN ($rgc) $query1 AND si.type='countersales' ");
                 for ($index1 = 0; $index1 < $adb->num_rows($retRes); $index1++) {
                    $Arr = $adb->raw_query_result_rowdata($retRes,$index1);
                                if($typeId == 'CustomerId')
                                        $ret2[$Arr['xretailerid']] = $Arr['customername']."-".$Arr['customercode'];
                                        else
                    $ret2[$Arr['xretailerid']] = $Arr['customername'];
                 }
            }
        }
     if(!empty($ret2)) return $ret2;
        else return "";
}
function getOfftakeScheme($val,$schid=null){
    global $adb;
    
    $sql = "SELECT vtiger_xscheme.xschemeid,vtiger_xscheme.schemecode,vtiger_xschemecf.cf_xscheme_scheme_name,vtiger_xscheme.schemedescription,
vtiger_xschemecf.cf_xscheme_effective_from,vtiger_xschemecf.cf_xscheme_effective_to
FROM vtiger_xscheme
LEFT JOIN vtiger_xschemecf ON vtiger_xschemecf.xschemeid = vtiger_xscheme.xschemeid
WHERE vtiger_xschemecf.cf_xscheme_offtake=1 AND vtiger_xschemecf.cf_xscheme_active=1";
    $condn = '';
    if($schid!=""){
        $condn = " AND vtiger_xscheme.xschemeid=".$schid;
    }
    $sql .=$condn;
    
    $result=$adb->pquery($sql);
              for ($index1 = 0; $index1 < $adb->num_rows($result); $index1++) {
            $Arr = $adb->raw_query_result_rowdata($result,$index1);
             if($schid!=""){
                 $ret2['schemedescription'] = $Arr['schemedescription'];  
                 $ret2['from_date'] = $Arr['cf_xscheme_effective_from'];  
                 $ret2['to_date'] = $Arr['cf_xscheme_effective_to'];  
             }else{
               $ret2[$Arr['xschemeid']] = $Arr['schemecode']." -- ".$Arr['cf_xscheme_scheme_name'];   
             }
           
        }
       // echo'<pre>lll==>';print_r($Arr);die;
         if(!empty($ret2)) return $ret2;
        else return "";
}
function getPointeSchemedetail($val,$schid=null){
     global $adb; $condn = '';
     
     if($schid!=""){
       $condn =  " AND xpointschemeruleid=".$schid; 
     }
     $sql = "SELECT * FROM vtiger_xpointschemerule WHERE status = '1'".$condn;
      $result=$adb->pquery($sql);
              for ($index1 = 0; $index1 < $adb->num_rows($result); $index1++) {
            $Arr = $adb->raw_query_result_rowdata($result,$index1);
             if($schid!=""){
                 $ret2['schemedescription'] = $Arr['description'];  
                 $ret2['from_date'] = $Arr['start_date'];  
                 $ret2['to_date'] = $Arr['end_date'];  
             }else{
               $ret2[$Arr['xpointschemeruleid']] = $Arr['code']. " -- " .$Arr['description'];   
             }
           
        }
         if(!empty($ret2)) return $ret2;
        else return "";
}

function getDetails($val,$filedname)
       {
        global $adb;
        if($val==""){ $val='0'; }
            if($filedname == "retailer_general_classification"){                
                $retailer_general_classification=explode('##',$val);                
                $queryrgc = "select DISTINCT c.* from vtiger_xretailercf r left join vtiger_xgeneralclassification c on r.cf_xretailer_general_classification = c.xgeneralclassificationid where c.xgeneralclassificationid in (".implode(',',$retailer_general_classification).")";
                $resultrgc = $adb->pquery($queryrgc);
                $rgc = "";
                for ($index = 0; $index < $adb->num_rows($resultrgc); $index++) {                    
                $rgc .= $adb->query_result($resultrgc,$index,'generalclassdescription').',';
                }
                $value =  rtrim($rgc, ",");
            }else if($filedname == "retailer_channel"){
               $retailer_channel=explode('##',$val);
               $querychannel = "select DISTINCT c.* from vtiger_xretailercf r left join vtiger_xchannelhierarchy c on r.cf_xretailer_channel_type = c.xchannelhierarchyid where c.xchannelhierarchyid in (".implode(',',$retailer_channel).")";
               $resultchannel = $adb->pquery($querychannel);
               $chn = "";
		for ($index = 0; $index < $adb->num_rows($resultchannel); $index++) {                    
	            $chn .= $adb->query_result($resultchannel,$index,'channel_hierarchy').',';
		}
                $value = rtrim($chn, ",");   
            }else if($filedname == "cf_purchaseinvoice_buyer_id"){
               $distributor_code=explode('##',$val);
               $querydisname = "select DISTINCT dis.* from vtiger_xrpicf rpicf left join vtiger_xdistributor dis on rpicf.cf_purchaseinvoice_buyer_id = dis.distributorcode where dis.distributorcode in (".implode(',',$distributor_code).")";
               $resultdisname = $adb->pquery($querydisname);
               $distributorname = "";
		for ($index = 0; $index < $adb->num_rows($resultdisname); $index++) {                    
	            $distributorname .= $adb->query_result($resultdisname,$index,'distributorname').',';
		}
                $value = rtrim($distributorname, ",");  
            }else if($filedname == "cf_xsalesorder_seller_id"){
               $distributor_id=explode('##',$val);
               $querydistname = "select DISTINCT dist.* from vtiger_xsalesordercf socf left join vtiger_xdistributor dist on socf.cf_xsalesorder_seller_id = dist.xdistributorid where dist.xdistributorid in (".implode(',',$distributor_id).")";
               $resultdistname = $adb->mquery($querydistname);
               $distributornameso = "";
		for ($index = 0; $index < $adb->num_rows($resultdistname); $index++) {                    
	            $distributornameso .= $adb->query_result($resultdistname,$index,'distributorname').',';
		}
                $value = rtrim($distributornameso, ",");  
            }
            else if($filedname == "retailer_value_classification"){
                $retailer_value_classification=explode('##',$val);
                $queryrvc = "select DISTINCT c.* from vtiger_xretailercf r left join vtiger_xvalueclassification c on r.cf_xretailer_value_classification = c.xvalueclassificationid where c.xvalueclassificationid in (".implode(',',$retailer_value_classification).")";
                $resultrvc = $adb->pquery($queryrvc);
                $rvc = "";
		for ($index = 0; $index < $adb->num_rows($resultrvc); $index++) {                   
	            $rvc .= $adb->query_result($resultrvc,$index,'valueclassdescription').',';
		}
                $value = rtrim($rvc, ",");
            }else if($filedname == "retailer_customer_group"){
               $retailer_customer_group=explode('##',$val);
               $queryrcg = "select DISTINCT c.* from vtiger_xretailercf r left join vtiger_xcustomergroup c on r.cf_xretailer_customer_group = c.xcustomergroupid where c.xcustomergroupid in (".implode(',',$retailer_customer_group).")";
                $resultrcg = $adb->pquery($queryrcg);
                $rcg = "";
		for ($index = 0; $index < $adb->num_rows($resultrcg); $index++) {                    
	            $rcg .= $adb->query_result($resultrcg,$index,'customergroupname').',';
		}
                $value = rtrim($rcg, ",");  
            }else if($filedname == "retailer_potential_classification"){
               $retailer_potential_classification=explode('##',$val);
               $queryrpc = "select DISTINCT c.* from vtiger_xretailercf r left join vtiger_xpotentialclassification c on r.cf_xretailer_potential = c.xpotentialclassificationid where c.xpotentialclassificationid in (".implode(',',$retailer_potential_classification).")";
                $resultrpc = $adb->pquery($queryrpc);
                $rpc = "";
		for ($index = 0; $index < $adb->num_rows($resultrpc); $index++) {                   
	            $rpc .= $adb->query_result($resultrpc,$index,'potentialclassdesc').',';
		}
                $value = rtrim($rpc, ",");  
            }else if($filedname == "print_format"){
               $print_format=explode('##',$val);
               $querypfdist = "select DISTINCT pf.* from vtiger_xdistributor dist left join vtiger_printbilltemplate pf on pf.templateid = dist.print_format where pf.templateid in (".implode(',',$print_format).")";
                $resultpfdist = $adb->pquery($querypfdist);
                $pfdist = "";
		for ($index = 0; $index < $adb->num_rows($resultpfdist); $index++) {                   
	            $pfdist .= $adb->query_result($resultpfdist,$index,'templatename').',';
		}
                $value = rtrim($pfdist, ",");  
            }
        return $value;
       }
function updateDebitnote($modName,$tansId)
       {
           global $adb;
		   $recordInfo = $collectInfo = $status = $penalty = $mismatchcheque = array();
		   if($tansId!=''){
				$sql = "SELECT group_concat(cd.recordid,'###',cd.xcollectionid,'###',cd.status,'###',cd.penalty) as chqdocinfo 
					FROM vtiger_xchequemanagementdoc cd 
					LEFT JOIN vtiger_xchequemanagement c ON c.xchequemanagementid = cd.xchequemanagementid
					WHERE cd.xchequemanagementid = ? 
					AND c.status IN ('Created','Publish') 
					group by cd.xchequemanagementid";
					
			/*$sql = "SELECT group_concat(recordid,'###',xcollectionid,'###',status,'###',penalty) as chqdocinfo
FROM vtiger_xchequemanagementdoc WHERE xchequemanagementid = ?
group by xchequemanagementid"; */
			$result = $adb->mquery($sql,array($tansId));
			if($adb->num_rows($result)>0){
				$chqdocinfo = $adb->query_result($result,0,'chqdocinfo');
				$chqdocinfos = explode(',',$chqdocinfo);
				foreach($chqdocinfos as $key=>$value){
					$splitchqdocinfos = explode('###',$value);
					if($splitchqdocinfos[0] != 0) {
						array_push($recordInfo,$splitchqdocinfos[0]);
						$id=$splitchqdocinfos[0];
					}
					if($splitchqdocinfos[1] != 0) {
						array_push($collectInfo,$splitchqdocinfos[1]);
						$id=$splitchqdocinfos[1];
					}
					if($splitchqdocinfos[2] != '') {
						$status[$id] = $splitchqdocinfos[2];
					}
					if($splitchqdocinfos[3] != '') {
						$penalty[$id] = $splitchqdocinfos[3];
					}
				}
				$appendwhere = '';
				if(count($recordInfo)!=0 && count($collectInfo)!=0)
				{
					$appendwhere = " cdoc.recordid in (".implode(',',$recordInfo).") or cdoc.xcollectionid in (".implode(',',$collectInfo).") ";
				} else if(count($recordInfo)!=0) {
					$appendwhere = " cdoc.recordid in (".implode(',',$recordInfo).") ";
				} else if(count($collectInfo)!=0) {
					$appendwhere = " cdoc.xcollectionid in (".implode(',',$collectInfo).") ";
				}
					$chequeStatusSql = "select group_concat(cdoc.recordid,'###',cdoc.xcollectionid,'###',cdoc.status,'###'
					,cdoc.penalty,'###',cdoc.cheque_number) as chqdocinfo from (select max(chqdoc.xchequemanagementid) as chqmgmtid,max(chqdoc.recordid) as recordid
	from vtiger_xchequemanagementdoc chqdoc
	join vtiger_xchequemanagement cmgmt on cmgmt.xchequemanagementid = chqdoc.xchequemanagementid
	join vtiger_crmentity crm on chqdoc.xchequemanagementid = crm.crmid
	where crm.deleted = 0 and cmgmt.status IN ('Created','Publish')
	group by recordid
	order by createdtime asc) as a
	join vtiger_xchequemanagementdoc cdoc on cdoc.xchequemanagementid = a.chqmgmtid and a.recordid = cdoc.recordid
	where $appendwhere
	group by xchequemanagementid ";
					$chequeresult = $adb->pquery($chequeStatusSql,array());
					$chequeinfo = $adb->query_result($chequeresult,0,'chqdocinfo');
					 $chequeinfos = explode(',',$chequeinfo);
					if(count($chequeinfos)>0){
						foreach($chequeinfos as $key=>$value){
							$splitchequedocinfos = explode('###',$value);
							if($splitchequedocinfos[0] !=0){
								$id = $splitchequedocinfos[0];
							} else {
								$id = $splitchequedocinfos[1];
							}
							if($splitchequedocinfos[2] == 'Bounced' && $splitchequedocinfos[3] == ''){
								array_push($mismatchcheque, "Already the Cheque No. {$splitchequedocinfos[4]} is ".$splitchequedocinfos[2]);
							} else if($splitchequedocinfos[2] != $status[$id]) {
								array_push($mismatchcheque, "Already the Cheque No. {$splitchequedocinfos[4]} is ".$splitchequedocinfos[2]);
							}
						}
					}else{
						for($index = 0; $index < $adb->num_rows($chequeresult); $index++){
							$chequeinfos = $adb->query_result($chequeresult,$index,'chqdocinfo');
							//foreach($chequeinfos as $key=>$value){
								$splitchequedocinfos = explode('###',$chequeinfos);
								if($splitchequedocinfos[0] !=0){
									$id = $splitchequedocinfos[0];
								} else {
									$id = $splitchequedocinfos[1];
								}
								if($splitchequedocinfos[2] == 'Bounced' && $splitchequedocinfos[3] == ''){
									array_push($mismatchcheque, "Already the Cheque No. {$splitchequedocinfos[4]} is ".$splitchequedocinfos[2]);
								} else if($splitchequedocinfos[2] != $status[$id]) {
									array_push($mismatchcheque, "Already the Cheque No. {$splitchequedocinfos[4]} is ".$splitchequedocinfos[2]);
								}
							//}
						}
					}
				 if(count($mismatchcheque)>0){
				  return array(0=>FALSE,1=>'CHEQUE_STATUS_DIFF',2=>implode('<br>',$mismatchcheque));
				 }
			}
		   }
		   $totrows = $_REQUEST['totrows'];
		   for($i=0;$i<$totrows;$i++){	   
			   if($_REQUEST['penalty'.$i] != $_REQUEST['hidepenalty'.$i] && $_REQUEST['cheque_status'.$i] == 'Bounced'){
					$Qry = "SELECT * FROM vtiger_xchequemanagementdoc 
						LEFT JOIN vtiger_crmentity ct ON vtiger_xchequemanagementdoc.xchequemanagementid=ct.crmid 
						WHERE ct.deleted=0 AND vtiger_xchequemanagementdoc.penalty!='' AND vtiger_xchequemanagementdoc.xchequemanagementid=".$tansId;           
				   $result = $adb->pquery($Qry);
				   $num = $adb->num_rows($result);
					if($num > 0){
						for ($index = 0; $index < $adb->num_rows($result); $index++) {
							 $amt = $adb->query_result($result,$index,'cheque_amount');
								if($amt!=""){
									require_once("modules/xDebitNote/xDebitNote.php");
									require_once('include/TransactionSeries.php');
									$focus1=new xDebitNote();
									$check = $adb->pquery("select cur_id,prefix from vtiger_modentity_num where semodule='xDebitNote' and active=1");
									$prefix = $adb->query_result($check,0,'prefix');
									$curid = $adb->query_result($check,0,'cur_id');
						
									$focus1->column_fields['amount'] = $adb->query_result($result,$index,'penalty');
									$focus1->column_fields['cf_xdebitnote_debit_note_type'] = "Retailer";
									$focus1->column_fields['partyname'] = $adb->query_result($result,$index,'xretailerid');                            
									$focus1->column_fields['cf_xdebitnote_debit_note_date'] = date('Y-m-d');
									$focus1->column_fields['debitnotecode'] = $prefix.$curid;
									$focus1->column_fields['cf_xdebitnote_status'] = "Active";
									$focus1->column_fields['cf_xdebitnote_remarks'] = $adb->query_result($result,$index,'cheque_number').' '.$adb->query_result($result,$index,'cheque_date').' Bounced';
									$dist =  getDistrIDbyUserID();
									$focus1->column_fields['distributor_id']=$dist['id'];
									
//									$defaultTrans = getDefaultTransactionSeries("Debit Note");
//									$focus1->column_fields['cf_xdebitnote_transaction_series'] = $defaultTrans['xtransactionseriesid'];
//									$focus1->column_fields['cf_xdebitnote_transaction_series_display'] = $defaultTrans['transactionseriesname'];
									$tArr = generateUniqueSeries($transactionseriesname='',"Debit Note");
									$focus1->column_fields['debitnoteno'] = $tArr['uniqueSeries'];
                           $focus1->column_fields['cf_xdebitnote_transaction_series'] = $tArr['xtransactionseriesid'];
									$focus1->column_fields['cf_xdebitnote_transaction_series_display'] = $tArr['transactionseriesname'];
									$focus1->save('xDebitNote');                           
									$adb->pquery("update vtiger_xdebitnote set status='Created', debitnoteno = '".$tArr['uniqueSeries']."', distributor_id=".$dist['id']." where xdebitnoteid=".$focus1->id);
									$adb->pquery("update vtiger_modentity_num set cur_id=".($curid+1)." where semodule='xDebitNote'");
									//echo "========>".$focus1->id;cf_xdebitnote_remarks
								}
							 
						  }
					} else {
						$amt = $_REQUEST['chkamt'.$i];
						if($amt!=""){
							require_once("modules/xDebitNote/xDebitNote.php");
							require_once('include/TransactionSeries.php');
							$focus1=new xDebitNote();
							$check = $adb->pquery("select cur_id,prefix from vtiger_modentity_num where semodule='xDebitNote' and active=1");
							$prefix = $adb->query_result($check,0,'prefix');
							$curid = $adb->query_result($check,0,'cur_id');
				
							$focus1->column_fields['amount'] = $_REQUEST['penalty'.$i];
							$focus1->column_fields['cf_xdebitnote_debit_note_type'] = "Retailer";
							$focus1->column_fields['partyname'] = $_REQUEST['retailerid'.$i];                            
							$focus1->column_fields['cf_xdebitnote_debit_note_date'] = date('Y-m-d');
							$focus1->column_fields['debitnotecode'] = $prefix.$curid;
							$focus1->column_fields['cf_xdebitnote_status'] = "Active";
							$focus1->column_fields['cf_xdebitnote_remarks'] = $_REQUEST['chknum'.$i].' '.$_REQUEST['chkdate'.$i].' Bounced';
							$dist =  getDistrIDbyUserID();
							$focus1->column_fields['distributor_id']=$dist['id'];
							
//							$defaultTrans = getDefaultTransactionSeries("Debit Note");
//							$focus1->column_fields['cf_xdebitnote_transaction_series'] = $defaultTrans['xtransactionseriesid'];
//							$focus1->column_fields['cf_xdebitnote_transaction_series_display'] = $defaultTrans['transactionseriesname'];
							$tArr = generateUniqueSeries($transactionseriesname='',"Debit Note");
							$focus1->column_fields['debitnoteno'] = $tArr['uniqueSeries'];
                     $focus1->column_fields['cf_xdebitnote_transaction_series'] = $tArr['xtransactionseriesid'];
							$focus1->column_fields['cf_xdebitnote_transaction_series_display'] = $tArr['transactionseriesname'];
							$focus1->save('xDebitNote');                           
							$adb->pquery("update vtiger_xdebitnote set status='Created', debitnoteno = '".$tArr['uniqueSeries']."', distributor_id=".$dist['id']." where xdebitnoteid=".$focus1->id);
							$adb->pquery("update vtiger_modentity_num set cur_id=".($curid+1)." where semodule='xDebitNote'");
							//echo "========>".$focus1->id;cf_xdebitnote_remarks
						}
					}
				}
			}
			return array(0=>TRUE);
       }
       
function createCreditNoteSalesReturn($modName,$return_id){
                
                global $adb,$ALLOW_GST_TRANSACTION;
                
                if($ALLOW_GST_TRANSACTION){
                    require_once("modules/xCreditNote/xCreditNote.php");
                    require_once('include/TransactionSeries.php');
                    
                    $salesreturn = $adb->mquery("select cf_xsalesreturn_customer,cf_xsalesreturn_remarks,cf_xsalesreturn_amount from vtiger_xsalesreturn join vtiger_xsalesreturncf on vtiger_xsalesreturncf.xsalesreturnid=vtiger_xsalesreturn.xsalesreturnid where vtiger_xsalesreturn.xsalesreturnid='$return_id'");
                    
                    $focus1=new xCreditNote();
                    $check  = $adb->pquery("select cur_id,prefix from vtiger_modentity_num where semodule='xCreditNote' and active=1");
                    $prefix = $adb->query_result($check,0,'prefix');
					
                    $curidquery  = $adb->mquery("SELECT count(*)+1 as count FROM `vtiger_crmentity` WHERE `setype` = 'xCreditNote'");
                    $curid = $adb->query_result($curidquery,0,'count');
					
                    $focus1->column_fields['xsalesreturnid']=$return_id;
                    $focus1->column_fields['status'] = "Created";
                    $focus1->column_fields['cf_xcreditnote_amount'] = $adb->query_result($salesreturn,0,'cf_xsalesreturn_amount');
                    $focus1->column_fields['cf_xcreditnote_adjustable_amount'] = $adb->query_result($salesreturn,0,'cf_xsalesreturn_amount');
                    $focus1->column_fields['cf_xcreditnote_credit_note_adjusted'] = $adb->query_result($salesreturn,0,'cf_xsalesreturn_amount');
                    $focus1->column_fields['creditnote_type'] = "A";
                    $focus1->column_fields['cf_xcreditnote_credit_note_type'] = "Retailer";
                    $focus1->column_fields['cf_xcreditnote_retailer'] = $adb->query_result($salesreturn,0,'cf_xsalesreturn_customer');
                    $focus1->column_fields['cf_xcreditnote_credit_note_date'] = date('Y-m-d');
                    $focus1->column_fields['creditnotecode'] = $prefix.$curid;
					$focus1->column_fields['description'] = "Sales Return";
                    $focus1->column_fields['cf_xcreditnote_status'] = "Active";
                    $focus1->column_fields['cf_xcreditnote_remarks'] = $adb->query_result($salesreturn,0,'cf_xsalesreturn_remarks'); 
                    $dist =  getDistrIDbyUserID();
                    $focus1->column_fields['cf_xcreditnote_distributor_id']=$dist['id'];
                    $tArr = generateUniqueSeries($transactionseriesname='',"Credit Note");
                    $focus1->column_fields['cf_xcreditnote_credit_note_series'] 	   = $tArr['xtransactionseriesid'];
                    $focus1->column_fields['cf_xcreditnote_credit_note_series_number'] = '"'.$tArr['uniqueSeries'].'"';
					
                    $focus1->save('xCreditNote');
                    $credit_id = $focus1->id;
                    $adb->pquery("update vtiger_xcreditnotecf  join vtiger_xcreditnote on vtiger_xcreditnote.xcreditnoteid=vtiger_xcreditnotecf.xcreditnoteid  set xsalesreturnid='".$return_id."',cf_xcreditnote_credit_note_series_number='".$tArr['uniqueSeries']."' where vtiger_xcreditnotecf.xcreditnoteid='$credit_id'");
                    $adb->pquery("update vtiger_modentity_num set cur_id=".($curid)." where semodule='xCreditNote'");
                    $wherUpdateColumn = 'xcreditid="'.$credit_id.'"';
                    $adb->pquery("UPDATE vtiger_xsalesreturn SET $wherUpdateColumn where xsalesreturnid=?",array($return_id));  
                }
                
                
}

function createDebitNotePurchaseReturn($modName,$return_id){

	global $adb,$ALLOW_GST_TRANSACTION;

	if($ALLOW_GST_TRANSACTION){
		require_once("modules/xDebitNote/xDebitNote.php");
		require_once('include/TransactionSeries.php');
		
		$purchasereturn = $adb->mquery("select cf_xpurchasereturn_amount,cf_xpurchasereturn_vendor,cf_xpurchasereturn_internal_ref from vtiger_xpurchasereturn join vtiger_xpurchasereturncf on vtiger_xpurchasereturncf.xpurchasereturnid=vtiger_xpurchasereturn.xpurchasereturnid where vtiger_xpurchasereturn .xpurchasereturnid='$return_id'");

		$check  = $adb->pquery("select cur_id,prefix from vtiger_modentity_num where semodule='xDebitNote' and active=1");
		$prefix = $adb->query_result($check,0,'prefix');
		//$curid  = $adb->query_result($check,0,'cur_id');
		
		$curidquery  = $adb->mquery("SELECT count(*)+1 as count FROM `vtiger_crmentity` WHERE `setype` = 'xDebitNote'");
		$curid = $adb->query_result($curidquery,0,'count');
		
		$focus2=new xDebitNote();
		$focus2->column_fields['xpurchasereturnid']=$return_id;
		$focus2->column_fields['amount'] = $adb->query_result($purchasereturn,0,'cf_xpurchasereturn_amount');
		$focus2->column_fields['cf_xdebitnote_debit_note_adjusted'] = $adb->query_result($purchasereturn,0,'cf_xpurchasereturn_amount');
		$focus2->column_fields['cf_xdebitnote_debit_note_type'] = "Vendor";
		$focus2->column_fields['partyname'] = $adb->query_result($purchasereturn,0,'cf_xpurchasereturn_vendor');
		$focus2->column_fields['cf_xdebitnote_debit_note_date'] = date('Y-m-d');
		$focus2->column_fields['debitnotecode'] = $prefix.$curid;
		$focus2->column_fields['cf_xdebitnote_status'] = "Active";
		$focus2->column_fields['cf_xdebitnote_remarks'] = $adb->query_result($purchasereturn,0,'cf_xpurchasereturn_internal_ref'); 
		$dist =  getDistrIDbyUserID();
		$focus2->column_fields['distributor_id']=$dist['id'];
		$tArr = generateUniqueSeries($transactionseriesname='',"Debit Note");
		$focus2->column_fields['cf_xdebitnote_transaction_series'] 	   = $tArr['xtransactionseriesid'];
		$focus2->column_fields['debitnoteno'] = '"'.$tArr['uniqueSeries'].'"';
		$focus2->save('xDebitNote');

		$debit_id = $focus2->id;

		$adb->pquery("update vtiger_xdebitnotecf  join vtiger_xdebitnote on vtiger_xdebitnote.	xdebitnoteid=vtiger_xdebitnotecf.xdebitnoteid  set status='Created',xpurchasereturnid='".$return_id."',debitnoteno='".$tArr['uniqueSeries']."', distributor_id=".$dist['id']." where vtiger_xdebitnotecf.xdebitnoteid='$debit_id'");
		
		$adb->pquery("update vtiger_modentity_num set cur_id=".($curid)." where semodule='xDebitNote'");
		$wherUpdateColumn = 'xdebitid="'.$debit_id.'"';
		
		$adb->pquery("UPDATE vtiger_xpurchasereturn  SET $wherUpdateColumn where xpurchasereturnid=?",array($return_id));  
	}                
}

function getParameterInfo($rid){
      global $adb;
//       $Qry = "SELECT vtiger_xincentiveparameter.*,vtiger_xincentivesalesman.xcategorygroupid,vtiger_xcategorygroup.categorygroupname,vtiger_xproduct.productname
//                FROM vtiger_xincentiveparameter 
//                LEFT JOIN vtiger_crmentity ct ON vtiger_xincentiveparameter.xincentiveparameterid=ct.crmid 
// LEFT JOIN vtiger_xincentivesalesman vtiger_xincentivesalesman ON vtiger_xincentivesalesman.xincentivesettingid=vtiger_xincentiveparameter.xincentivesettingid
//LEFT JOIN vtiger_xcategorygroup ON vtiger_xcategorygroup.xcategorygroupid = vtiger_xincentivesalesman.xcategorygroupid
//LEFT JOIN vtiger_xproduct ON vtiger_xproduct.xproductid = vtiger_xincentiveparameter.xproductid
//WHERE ct.deleted=0 AND vtiger_xincentiveparameter.xincentivesettingid=".$rid." group by vtiger_xincentiveparameter.xincentiveparameterid";            
$Qry = "SELECT vtiger_xincentiveparameter.*,vtiger_xincentivesalesman.xcategorygroupid,vtiger_xcategorygroup.categorygroupname,vtiger_xproduct.productname
FROM vtiger_xincentiveparameter
LEFT JOIN vtiger_crmentity ct ON vtiger_xincentiveparameter.xincentiveparameterid=ct.crmid 
LEFT JOIN vtiger_xincentivesalesman ON vtiger_xincentivesalesman.xincentivesalesmanid= vtiger_xincentiveparameter.xincentivesalesmanid
LEFT JOIN vtiger_xcategorygroup ON vtiger_xincentivesalesman.xcategorygroupid = vtiger_xcategorygroup.xcategorygroupid
LEFT JOIN vtiger_xproduct ON vtiger_xproduct.xproductid = vtiger_xincentiveparameter.xproductid
WHERE ct.deleted=0  
AND vtiger_xincentivesalesman.xincentivesettingid = vtiger_xincentiveparameter.xincentivesettingid
AND vtiger_xincentiveparameter.xincentivesettingid = ".$rid." ORDER BY vtiger_xincentivesalesman.xcategorygroupid";
     //return  $Qry;
      $result = $adb->pquery($Qry );
           $num = $adb->num_rows($result);
           $output = array();
            if($num > 0){
                for ($index = 0; $index < $adb->num_rows($result); $index++) { $i =0;
                        //$catid[]=$adb->query_result($result,$index,'xcategorygroupid');
               
                     $incentive_param[] = $adb->query_result($result,$index,'incentive_param');  
                     $xincentivesalesmanid[] = $adb->query_result($result,$index,'xincentivesalesmanid');  
                     $xproductid[] = $adb->query_result($result,$index,'xproductid');  
                     $incentive_description[] = $adb->query_result($result,$index,'incentive_description');  
                     //$prodmax_value[] = $adb->query_result($result,$index,'prodmax_value');  
                     $xcategorygroupid[] = $adb->query_result($result,$index,'xcategorygroupid');  
                     $categorygroupname[] = $adb->query_result($result,$index,'categorygroupname');  
                     $xincentiveparameterid[] = $adb->query_result($result,$index,'xincentiveparameterid');  
					 $tprodlist = $adb->query_result($result,$index,'xproductid');
					 $sel_prod_res =  $adb->pquery("SELECT productname,xproductid FROM vtiger_xproduct WHERE xproductid IN ($tprodlist)");
					 $tprodcount = $adb->num_rows($sel_prod_res); $productname = '';
					 if($tprodcount >0){ 
					 $presult = $adb->query_result($sel_prod_res);
					 for($p=0;$p<$tprodcount; $p++){
						//$productname .= "<a href=''>".$adb->query_result($sel_prod_res,$p,'productname').",";
						$pname = $adb->query_result($sel_prod_res,$p,'productname');$pid = $adb->query_result($sel_prod_res,$p,'xproductid');
						//$productname .= "<a href=index.php?module=xProduct&parenttab=ProductManagement&action=DetailView&record=$pid>$pname</a>".",\n";
						$productname .= "$pname".",";
                     }
					 }
					 $productname = substr($productname,0,-1);
					//for($index1=0;$index1<count($catid);$index1++){
					$prodmax_value =  $adb->query_result($result,$index,'prodmax_value');
					if($prodmax_value==0.00){
						$prodmax_value = '';
					}
                     $catArray = array('inc_parm'=>$adb->query_result($result,$index,'incentive_param'),
                    // $output[$index1] = array( 'productlines'=> array(array('inc_parm'=>$adb->query_result($result,$index,'incentive_param'),
                                        'prod'=>$adb->query_result($result,$index,'xproductid'),
                                        'prodname'=> $productname ,//$adb->query_result($result,$index,'productname'),
                                        'incdesc'=>$adb->query_result($result,$index,'incentive_description'),
                                        'prodmax'=>$prodmax_value,
                                        'catid'=>$adb->query_result($result,$index,'xcategorygroupid'),
                                        'cargpname'=>$adb->query_result($result,$index,'categorygroupname'),
                                        'incparamid'=>$adb->query_result($result,$index,'xincentiveparameterid'));
                       $output[] = $catArray;
                      
                }
                 for ($f = 0; $f < count($output); $f++) { //echo $output[$f]['xincentiveparameterid']."<br>";
                         
                         if(!array_key_exists($output[$f]['catid'],$d)){
                             $d[$output[$f]['catid']] = array();
                         }
                         $paramdata = $d[$output[$f]['catid']];
                         array_push($paramdata,$output[$f]);
                         $d[$output[$f]['catid']] = $paramdata;
                         
                   }
            
            }
           return $d;
}
function getFreqInfo($rid){
    global $adb;
//     echo $Qry = "SELECT vtiger_xincentivefrequency.*,vtiger_xincentiveparameter.incentive_param
//FROM vtiger_xincentivefrequency LEFT JOIN vtiger_crmentity ct ON 
//vtiger_xincentivefrequency.xincentivefrequencyid=ct.crmid 
//LEFT JOIN vtiger_xincentiveparameter  ON 
//vtiger_xincentiveparameter.xincentivesettingid=vtiger_xincentivefrequency.xincentivesettingid
//WHERE ct.deleted=0 AND vtiger_xincentivefrequency.xincentivesettingid = ".$rid." AND vtiger_xincentivefrequency.xincentiveparameterid IN 
//(SELECT xincentiveparameterid FROM vtiger_xincentiveparameter WHERE xincentivesettingid = ".$rid." )";    
$Qry = "SELECT vtiger_xincentivefrequency.*,vtiger_xincentiveparameter.incentive_param,vtiger_xincentiveparameter.xincentivesalesmanid
FROM vtiger_xincentivefrequency 
LEFT JOIN vtiger_crmentity ct ON vtiger_xincentivefrequency.xincentivefrequencyid=ct.crmid 
LEFT JOIN vtiger_xincentiveparameter ON vtiger_xincentiveparameter.xincentivesettingid= vtiger_xincentivefrequency.xincentivesettingid
WHERE  ct.deleted=0 AND  vtiger_xincentivefrequency.xincentivesettingid = ".$rid."
AND vtiger_xincentiveparameter.xincentiveparameterid  = vtiger_xincentivefrequency.xincentiveparameterid ORDER BY vtiger_xincentivefrequency.xincentivefrequencyid asc";//xincentiveparameterid
           $result = $adb->pquery($Qry);
           $num = $adb->num_rows($result);
           $output = array();$output1 =array();
            if($num > 0){
                for ($index = 0; $index < $adb->num_rows($result); $index++) {
                    $freq =  $adb->query_result($result,$index,'frequency'); 
                    $frm_val =  $adb->query_result($result,$index,'incentive_from_val');
                    $to_val =  $adb->query_result($result,$index,'incentive_to_val');
                    $for_every =  $adb->query_result($result,$index,'incentive_for_every');;
                    $achiev_percent = $adb->query_result($result,$index,'incentive_achiev_percent');
                    $inc_value =  $adb->query_result($result,$index,'incentive_value');
                    $inc_points =  $adb->query_result($result,$index,'incentive_points');
                    $inc_maxcap =  $adb->query_result($result,$index,'incentive_maxcap');
                    $inc_param =  $adb->query_result($result,$index,'incentive_param');
                    $inc_paramidval =  $adb->query_result($result,$index,'xincentiveparameterid');
                   /* $frequency['frequency'] = $freq;
                    $incentive_from_val['incentive_from_val'] = $frm_val;
                    $incentive_to_val['incentive_to_val'] = $to_val;
                    $incentive_for_every['incentive_for_every'] = $for_every;
                    $incentive_achiev_percent['incentive_achiev_percent'] = $achiev_percent;
                    $incentive_points['incentive_points'] = $inc_points;
                    $incentive_maxcap['incentive_maxcap'] = $inc_maxcap;
                    $incentive_value['incentive_value'] = $inc_value;  
                    $inc_param['inc_param'] = $inc_param;  
                    $inc_paramid['xincentiveparameterid'] = $inc_paramidval; */
					
					$from_value =  $adb->query_result($result,$index,'incentive_from_val');
					if($from_value==0.00){	$from_value = '';}
					if($inc_param==0.00){$inc_param = '';}
					if($to_val==0.00){$to_val = '';}
					if($for_every==0.00){$for_every = '';}
					if($achiev_percent==0.00){$achiev_percent = '';}
					if($inc_value==0.00){$inc_value = '';}
					if($inc_points==0.00){$inc_points = '';}
					if($inc_maxcap==0.00){$inc_maxcap = '';}
					
                    $freqt = array('freq'=>$adb->query_result($result,$index,'frequency'),
                                  'frm_val'=>$from_value,
                                 'to_val'=>$to_val,
                                 'for_every'=>$for_every,
                                 'inc_achiev_percent'=>$achiev_percent,
                                 'inc_val'=>$inc_value,
                                 'inc_points'=>$inc_points,
                                 'inc_maxcap'=>$inc_maxcap,
                                 'inc_param'=>$adb->query_result($result,$index,'incentive_param'),
                                 'xincentiveparameterid'=>$adb->query_result($result,$index,'xincentiveparameterid'),
                                 'xincentivesalesmanid' =>$adb->query_result($result,$index,'xincentivesalesmanid'));
                    
                    $output[] = $freqt;
                 
                }
                $param_id = array_unique($inc_paramidval);
                $c = array();$d = array();
                  for ($f = 0; $f < count($output); $f++) { //echo $output[$f]['xincentiveparameterid']."<br>";
                      
                        if(!array_key_exists($output[$f]['xincentiveparameterid'],$d)){
                             $d[$output[$f]['xincentiveparameterid']] = array();
                         }
                       $paramdata = $d[$output[$f]['xincentiveparameterid']];
                         array_push($paramdata,$output[$f]);
                         $d[$output[$f]['xincentiveparameterid']] = $paramdata;
                   }
                 
         
            } 
          
            return $d;
}
/* 
	get Sales Return , Collection, Credit Note info/.
*/
function getSRCNCR($salesid, $distid) {
global $adb;
$sqlSRCNCR = "select CONCAT(sr.xsalesreturnid,'::',srcf.cf_xsalesreturn_transaction_no,'::',srcf.cf_xsalesreturn_intiated_date,'::',sr.salesreturncode,'::',srcf.cf_xsalesreturn_amount,'::',srcf.cf_xsalesreturn_amount,'::',srcf.cf_xsalesreturn_adjustable_amount) as `srdata` from vtiger_xsalesreturn as sr INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=sr.xsalesreturnid left join vtiger_xsalesreturncf as srcf on srcf.xsalesreturnid=sr.xsalesreturnid where vtiger_crmentity.deleted=0 and (CAST(srcf.cf_xsalesreturn_amount AS UNSIGNED) > CAST(srcf.cf_xsalesreturn_adjustable_amount AS UNSIGNED)) and cf_xsalesreturn_status in ('Created','Publish') and srcf.cf_xsalesreturn_customer= ?
			union
			select CONCAT(sr.xcreditnoteid,'::',srcf.cf_xcreditnote_credit_note_series_number,'::',srcf.cf_xcreditnote_credit_note_date,'::',sr.creditnotecode,'::',srcf.cf_xcreditnote_amount,'::',srcf.cf_xcreditnote_amount,'::',srcf.cf_xcreditnote_adjustable_amount) as `srdata` from vtiger_xcreditnote as sr INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=sr.xcreditnoteid left join vtiger_xcreditnotecf as srcf on srcf.xcreditnoteid=sr.xcreditnoteid where vtiger_crmentity.deleted=0 and srcf.cf_xcreditnote_status='Active' and (CAST(srcf.cf_xcreditnote_amount AS UNSIGNED) > CAST(srcf.cf_xcreditnote_adjustable_amount AS UNSIGNED)) and srcf.cf_xcreditnote_distributor_id=? and srcf.cf_xcreditnote_retailer=?
			union
			select CONCAT(sr.xcollectionid,'::',srcf.cf_xcollection_transaction_number,'::',srcf.cf_xcollection_collection_date,'::',sr.collectioncode,'::',srcf.cf_xcollection_amount_received,'::',srcf.cf_xcollection_cheque_no,'::',srcf.cf_xcollection_recieved_balance) as `crdata` from vtiger_xcollection as sr INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=sr.xcollectionid left join vtiger_xcollectioncf as srcf on srcf.xcollectionid=sr.xcollectionid where vtiger_crmentity.deleted=0 and srcf.cf_xcollection_status NOT LIKE '%Draft%' and (CAST(srcf.cf_xcollection_amount_received AS UNSIGNED) > CAST(srcf.cf_xcollection_recieved_balance AS UNSIGNED)) and srcf.cf_xcollection_customer_name=?";
	$execSRCNCR = $adb->pquery($sqlSRCNCR,array($salesid,$distid,$salesid,$salesid));
	for($j=0;$j<$adb->num_rows($execSRCNCR);$j++){
	$amountAdjustment[]=$adb->query_result($execSRCNCR,$j,0);
	}
	$amountAdjustment=array_filter($amountAdjustment);
	$returncount = count($amountAdjustment);
	$amountAdjustmentData = @implode(",", $amountAdjustment);
return $amountAdjustmentData;
}

//Get The Default Address for Retailer or Distributor
function getdefaultaddress($addtype, $id) // $addtype - Billing or Shipping , $id - Distributor Id or Retailer Id
{
    global $adb;
    $addquery = "SELECT vtiger_xaddresscf.*,
            vtiger_xaddress.addresscode,vtiger_xaddress.gstinno,vtiger_xstate.statename
     FROM vtiger_xaddresscf
     LEFT JOIN vtiger_xaddress ON vtiger_xaddress.xaddressid = vtiger_xaddresscf.xaddressid
     LEFT JOIN vtiger_crmentityrel ON vtiger_crmentityrel.relcrmid = vtiger_xaddresscf.xaddressid
     LEFT JOIN vtiger_xstate on vtiger_xstate.xstateid=vtiger_xaddress.xstateid
     WHERE vtiger_xaddresscf.cf_xaddress_mark_as_default = 1
       AND vtiger_xaddresscf.cf_xaddress_active = 1
       AND vtiger_xaddresscf.cf_xaddress_address_type='".$addtype."'
       AND vtiger_crmentityrel.crmid = '".$id."'";
    $addresult = $adb->pquery($addquery);
   
    if ($adb->num_rows($addresult) != "0")
        $retaddresult = $adb->raw_query_result_rowdata($addresult,0);
    
    return $retaddresult;        
}
function array_has_dupes($array) { 
   // streamline per @Felix
        return count($array) !== count(array_unique($array));
}
function getPTRConfigFields(){
    global $adb;
    $result =  $adb->pquery("SELECT vtiger_field.* FROM vtiger_field where vtiger_field.columnname 
IN(SELECT config_values FROM vtiger_ptrconfigvalues)");
    
    if ($adb->num_rows($result) != "0")
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $retaddresult[] = $adb->raw_query_result_rowdata($result,$index);
        }
     return $retaddresult;  
}
function getPTRConfigvalue($id){
    global $adb,$LBL_ALLOW_PTR_CONVERSION;
	include 'config.ptr.php';
    $result =  $adb->pquery("SELECT config_values FROM vtiger_ptrconfigvalues");

    if ($adb->num_rows($result) != "0") {
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $retaddresult[] = strtolower($adb->query_result($result,$index));
        }
        $fieldlist = implode(",",$retaddresult);//echo $LBL_ALLOW_PTR_CONVERSION;
		if($LBL_ALLOW_PTR_CONVERSION =='True'){
		   $res = $adb->pquery("SELECT $fieldlist FROM vtiger_xpricecalculationsetting WHERE xpricecalculationsettingid = $id"); 
			if ($adb->num_rows($res) != "0"){
			   $resval =  $adb->raw_query_result_rowdata($res,0); 
			   foreach($retaddresult as $key=>$val){  
					$val = strtolower($val);
				   $retptrval[$val] =  $resval[$val]; 
				}
			}else{
			   //$retptrval = '';
			}
		}
    }    
    return $retptrval;  
}

function MerchandiseMappingDistrevokesave($clusid,$merchandisemappid) {
		global $adb;
		$ret = array();
                $focus=CRMEntity::getInstance('DPMDistributorRevoke');
                
		 //$query = "SELECT ft.*,st.distributorname,st.distributorcode,st.xdistributorid,tt.distributorclustername,tt.distributorclustercode FROM vtiger_xdistributorclusterrel ft,vtiger_xdistributor st,vtiger_xdistributorcluster tt  WHERE ft.distclusterid=".$clusid." and ft.distributorid=st.xdistributorid and ft.distclusterid=tt.xdistributorclusterid";
/* $query = "SELECT dcr.distributorid
            FROM vtiger_xdistributorclusterrel dcr
            LEFT JOIN vtiger_xdistributorcluster dc on dc.xdistributorclusterid=dcr.distclusterid
            WHERE dc.active='1' and dcr.distclusterid in (".$clusid.") group by dcr.distributorid";     */           
$query = "SELECT ft.*,st.distributorname,st.distributorcode,tt.distributorclustername,tt.distributorclustercode 
                     FROM vtiger_xdistributorclusterrel ft,vtiger_xdistributor st,vtiger_xdistributorcluster tt  
                     WHERE ft.distclusterid IN ($clusid) and ft.distributorid=st.xdistributorid and ft.distclusterid=tt.xdistributorclusterid"; 
//echo 'Quer:<br>'.$query; die;
		$result = $adb->pquery($query);//echo "map=".$merchandisemappid." clus ".$clusid."=".$adb->num_rows($result);
		for ($index = 0; $index < $adb->num_rows($result); $index++) {
                    $focus->column_fields['distributorid'] = $adb->query_result($result,$index,'distributorid');
                    $focus->column_fields['xdistributorproductsmappingid'] = $merchandisemappid; 
		    $focus->column_fields['distributorclusterid'] = $clusid;   		 
                    $focus->column_fields['revoke_date'] = '';
                    $x=new CRMEntity();
                    $revoke_code= $x->seModSeqNumber('increment','DPMDistributorRevoke');                   
                    $focus->column_fields['distributorrevokecode'] = $revoke_code;  
                   //echo "<pre>"; print_r($focus->column_fields);exit;
                    $focus->save('DPMDistributorRevoke');
                    $distname=$adb->query_result($result,$index,'distributorname');
                    $distcode=$adb->query_result($result,$index,'distributorcode');
                    $return_id = $focus->id;
                    $updateQry = "UPDATE vtiger_dpmdistributorrevoke SET xdistributorproductsmappingid ='$merchandisemappid',distributorname ='$distname',distributorcode ='$distcode' where dpmdistributorrevokeid='$return_id'";    
                    $adb->pquery($updateQry);                    
                   // echo '<br>'.'retur id'.$return_id.'<br>';
                     //exit;
                    if($merchandisemappid!="" && $return_id!=""){
                    $insert = "insert into vtiger_crmentityrel(crmid,module,relcrmid,relmodule) values('".$merchandisemappid."','xMerchandiseProductsMapping','".$return_id."','DPMDistributorRevoke')";
                    $adb->pquery($insert);
                    }
		}
             // exit;
              
              //  return $ret;
	}
function checkPTRDisply(){
	global $adb;
	$result = $adb->pquery("SELECT value FROM sify_inv_mgt_config WHERE `key` = 'ALLOW_PTR_CONVERSION'");
	$value = $adb->query_result($result,0);
	return $value;
}

function formatQtyDecimals($qty)
{
    global $LBL_QUANTITY_DECIMAL;
        
    return number_format($qty, $LBL_QUANTITY_DECIMAL, '.','');
}

function formatCurrencyDecimals($amount)
{
    global $LBL_CURRENCY_DECIMAL;
    
    return number_format($amount, $LBL_CURRENCY_DECIMAL, '.','');
}

function rsitosi($rsiArr,$rsi_product_id,$si_line_id ,$rsi_line_id,$return_id){
                global $adb,$distArr;
                $distid = $distArr['id'];
                $total_rs = count($rsiArr);
                if($total_rs > 0){
                    for($rs=0;$rs < $total_rs;$rs++){
                        
                        $schemecode = $rsiArr[$rs]['scheme_code'];
                    
                        if($schemecode != ''){
                            $schemeQuery = "SELECT vtiger_xscheme.xschemeid,cf_xscheme_scheme_name AS scheme_name,cf_xscheme_scheme_definition FROM vtiger_xscheme INNER JOIN vtiger_xschemecf ON (vtiger_xscheme.xschemeid = vtiger_xschemecf.xschemeid) WHERE schemecode = ? ";
                            $schemeResult = $adb->pquery($schemeQuery, array($schemecode));
                //            print_R($schemeResult);die;
                            $scheme_num_rows=$adb->num_rows($schemeResult);
                            if($scheme_num_rows > 0){
                                $schemeName = $adb->query_result($schemeResult,0,'scheme_name');
                                $schemeid = $adb->query_result($schemeResult,0,'xschemeid');
                                $scheme_definition = $adb->query_result($schemeResult,0,'cf_xscheme_scheme_definition');
                            }
                        }
                        
                        $scheme_applied = strtolower($rsiArr[$rs]['scheme_applied']);
                        $disc_value = $rsiArr[$rs]['disc_value'];
                        $disc_every = mysql_real_escape_string($rsiArr[$rs]['disc_every']);
                        $disc_amount = $rsiArr[$rs]['disc_amount'];
                        $lineitem_id = $rsiArr[$rs]['lineitem_id'];
                        
                        
                        if($rsi_line_id != $lineitem_id)continue;
                        
                        if($scheme_applied == 'free'){
                            $productid = $rsiArr[$rs]['fproductcode'];
                            $free_qty = $rsiArr[$rs]['fqty'];
                            $fbaseqty = $rsiArr[$rs]['fbaseqty'];
                            $lineitem_id = $rsiArr[$rs]['lineitem_id'];
                            $ftuom = $rsiArr[$rs]['ftuom'];
                            $flistprice = $rsiArr[$rs]['flistprice'];
                            $batchcode = $rsiArr[$rs]['fbatchnumber'];
                            $pkg = $rsiArr[$rs]['fpkd'];
                            $expiry = $rsiArr[$rs]['fexpiry'];
                            $ptr = $rsiArr[$rs]['fptr'];
                            $pts = $rsiArr[$rs]['fpts'];
                            $ecp = $rsiArr[$rs]['fecp'];
                            $mrp = $rsiArr[$rs]['fmrp'];
                            $stocktype = $rsiArr[$rs]['stock_type'];

                            $discount_percent = 0;
                            $discount_amount = 0;
                            $sch_disc_amount = 0;
                            $comment = '';
                            $description = '';
                            $tax1 = $tax2 = $tax3 = 0;
                            $qty = $dam_qty = 0;
                            $net_price = 0;
                            $incrementondel = 0;
                            $flistprice = 0;
                            if($productid != ''){
                                    $freeQuery = "SELECT * FROM vtiger_xproduct WHERE xproductid = ? ";
                                    $freeResult = $adb->pquery($freeQuery, array($productid));
                                    $free_num_rows=$adb->num_rows($freeResult);
                                    if($free_num_rows > 0){
                                        $productName = $adb->query_result($freeResult,0,'productname');
                                        $track_serial = $adb->query_result($freeResult,0,'track_serial_number');
                                    }
                                }

                            $seq_no = $rs + 1;
                            
                            if($stocktype == '0'){
                               $schemetype = 'Scheme_Salable';
                            }else{
                               $schemetype = 'Scheme'; 
                            }
                            if( $stocktype == '0'){
                                $si_sqty = $fbaseqty;    
                            }else{
                                $si_sfqty = $fbaseqty;             
                            }
                            
                            
                            
                            $insert_qry = $adb->pquery("insert into vtiger_siproductrel (id, productid, productcode, product_type, sequence_no, quantity, 
                            baseqty, dispatchqty, refid, reflineid, reftrantype, tuom, listprice, discount_percent, discount_amount, sch_disc_amount, comment, 
                            description, incrementondel, tax1, tax2, tax3, free_qty, dam_qty, net_price) values ('$return_id', '$productid', '$productcode', '$schemetype', '$seq_no', '$free_qty', 
                            '$fbaseqty', '$dispatchqty', '$id', '$lineitem_id', 'xrSalesInvoice', '$ftuom', '$flistprice', '$discount_percent', '$discount_amount', 
                            '$sch_disc_amount', '$comment', '$description', '$incrementondel', '$tax1', '$tax2', '$tax3', '$free_qty', '$dam_qty', '$net_price')");
                            $lineitem_id_val = $adb->getLastInsertID();

                            $query ="insert into vtiger_xsalestransaction_batchinfo(transaction_id, trans_line_id, product_id, batch_no, pkd, expiry, transaction_type,sqty,sfqty, ptr, pts, mrp, ecp,ptr_type, distributor_id,track_serial) values(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                            $qparams = array($return_id,$lineitem_id_val,$productid,"$batchcode","$pkg","$expiry","SI","$si_sqty","$si_sfqty","$ptr","$pts","$mrp","$ecp",'',"$distid", "$track_serial");
                            $adb->pquery($query,$qparams);

                            $paraArray=array(
                                $return_id,$productid,
                                'Free',$schemeName,
                                $schemeid,$free_qty,
                                'NULL',$lineitem_id_val,$free_qty,$disc_every,'NULL'
                            );
                        
                        
                        }else{
                            
                            if($scheme_applied == 'points'){
                                
                                $paraArray=array(
                                    $return_id,$rsi_product_id,
                                    $scheme_applied,$schemeName,
                                    $schemeid,'NULL',
                                    'NULL',$si_line_id,$disc_amount,$disc_every,'NULL',
                                );
                            }else{
                               $paraArray=array(
                                    $return_id,$rsi_product_id,
                                    $scheme_applied,$schemeName,
                                    $schemeid,$disc_value,
                                    'NULL',$si_line_id,'NULL',$disc_every,
                                    $disc_amount
                                ); 
                            }
                             
                             
                              
                        }
                        $queryFreeSch="INSERT INTO `vtiger_itemwise_scheme` (`transaction_id`, `lineItemId`, `scheme_applied`, `scheme_name`, `scheme_id`, `value`,pricevalue,lineitem_id,disc_qty,disc_every,disc_amount) VALUES (?, ?, ?, ?, ?, ?,?,?,?,?,?)";
                            $adb->pquery($queryFreeSch,$paraArray); 
                        
                            
                }//XYZ
                
            }
}

function convert_so_to_si($ids)
{    
    global $current_user,$adb,$SI_SOTOSI_PRICE_EDITABLE,$ALLOW_GST_TRANSACTION,$SO_PRO_CATE_BASED, $SO_PRO_LIST_OR_BY_STOCK, $SO_PRO_LIST_OR_BY_PRICE;    
    $ids_arr = explode(",", $ids);   
    
    $success_cnt = 0;
    $fail_cnt = 0;
    $success_record=array();
    $posAction = $_REQUEST['stage_v'];
    if($tot_conv == 'Yes')
        $posAction = 'Create SI';
    $ns1 = getNextstageByPosAction('xSalesOrder',$posAction);
    $conv_to_si = false;
    if($ns1['cf_workflowstage_business_logic'] == 'Forward to SI')
    {
        $conv_to_si = true;
    }
    $fails=$successive=0;
    $success=count($ids_arr);
    //echo "Hi :".$ns1['cf_workflowstage_next_stage'].", ".$ns1['cf_workflowstage_next_content_status'];exit;
    for($i=0; $i<count($ids_arr); $i++)
    { 
        $godown_def_name = getGodownDefualtName();
        $distArr = getDistrIDbyUserID();
        $Retailer = $Salesman = $Stock = TRUE;
        $stock_any = FALSE;
        $stock_bulk = getDistrInvConfig('STOCK_BULK', $distArr['id']);
        $salesman_bulk = getDistrInvConfig('SALESMAN_BULK', $distArr['id']);
        $retailer_bulk = getDistrInvConfig('RETAILER_BULK', $distArr['id']);
        $amount_query= $adb->mquery('select so.total,col.cf_xcollectionmethod_collection_type from vtiger_xsalesorder so '
                . 'inner join vtiger_xsalesordercf socf on socf.salesorderid=so.salesorderid'
                . ' left join vtiger_xretailercf retcf on retcf.xretailerid=socf.cf_xsalesorder_buyer_id'
                . ' left join vtiger_xcollectionmethod col on col.xcollectionmethodid=retcf.cf_xpayment_payment_mode '
                . ' where so.salesorderid='.$ids_arr[$i].' limit 1');
        $amount_query_value=$adb->query_result($amount_query,0,'total');
        $customer_paymode_id=$adb->query_result($amount_query,0,'cf_xcollectionmethod_collection_type');
        if($salesman_bulk[0]['value']==1){
            $rsi_result_salesman = $adb->mquery("select cf_xsalesorder_sales_man from vtiger_xsalesordercf where salesorderid=".$ids_arr[$i]);
            $getsalesmanid=$adb->query_result($rsi_result_salesman,0,'cf_xsalesorder_sales_man');
            $qRes = $adb->mquery("SELECT mt.xsalesmanid AS id,st.cf_xsalesman_creditamount AS tamount,st.cf_xsalesman_creditbills AS tbills,
    ctcf.cf_xcreditterm_number_of_days	AS tdays
    FROM vtiger_xsalesman  mt
    LEFT JOIN vtiger_crmentity ct on ct.crmid=mt.xsalesmanid
    LEFT JOIN vtiger_xsalesmancf st on st.xsalesmanid=mt.xsalesmanid 
    LEFT JOIN vtiger_xcredittermcf ctcf on ctcf.xcredittermid=st.cf_xsalesman_creditdays
    WHERE  ct.deleted=0 AND mt.xsalesmanid ='$getsalesmanid'");
            
            for ($index = 0; $index < $adb->num_rows($qRes); $index++) {
    $res['tamount'] = $adb->query_result($qRes,$index,'tamount');
    $res['tbills'] = $adb->query_result($qRes,$index,'tbills');
    $res['tdays'] = $adb->query_result($qRes,$index,'tdays');   
    $saltotamt = $adb->query_result($qRes,$index,'tamount');
    $saltotbill = $adb->query_result($qRes,$index,'tbills');
    $saltotdays = $adb->query_result($qRes,$index,'tdays');     
    
}
$Qry2="select count(*) as paymode from vtiger_salesinvoice as si 
            INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=si.salesinvoiceid 
            INNER join vtiger_salesinvoicecf as sicf on sicf.salesinvoiceid=si.salesinvoiceid 
            where vtiger_crmentity.deleted=0 and si.status='Created' and sicf.cf_salesinvoice_outstanding > 0 
            and sicf.cf_salesinvoice_sales_man='$getsalesmanid'";

        $qRes2 = $adb->mquery($Qry2,array());
for ($index = 0; $index < $adb->num_rows($qRes2); $index++) {
    $res['paymode'] = round($adb->query_result($qRes2,$index,'paymode'));
    $limitedbill = round($adb->query_result($qRes2,$index,'paymode'));
}
        if($limitedbill=='' || $limitedbill==null)
        {
            $limitedbill=0;
        } 
        $limitedbill=$limitedbill;

$querysaleinvoicechk="select sum(sicf.cf_salesinvoice_outstanding) as tot from vtiger_salesinvoice as si 
            INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=si.salesinvoiceid 
            INNER join vtiger_salesinvoicecf as sicf on sicf.salesinvoiceid=si.salesinvoiceid 
            where vtiger_crmentity.deleted=0 and si.status='Created' and sicf.cf_salesinvoice_outstanding > 0 
            and sicf.cf_salesinvoice_sales_man=".$getsalesmanid." group by si.vendorid";
        $resultsaleinvoicechk = $adb->mquery($querysaleinvoicechk);
        $limitedamt = round($adb->query_result($resultsaleinvoicechk,0,'tot')) + $amount_query_value;
        
            $Qry4 = "select date(sicf.cf_salesinvoice_sales_invoice_date) as limitdate from vtiger_salesinvoice as si 
            INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=si.salesinvoiceid 
            INNER join vtiger_salesinvoicecf as sicf on sicf.salesinvoiceid=si.salesinvoiceid 
            where vtiger_crmentity.deleted=0 and si.status='Created' and sicf.cf_salesinvoice_outstanding > 0 
            and sicf.cf_salesinvoice_sales_man='$getsalesmanid' order by vtiger_crmentity.createdtime asc limit 1";
//echo 'query 4:::'.$Qry4 ;
//exit;
//echo '<br>'.$Qry3;
        $qRes4 = $adb->mquery($Qry4,array());
for ($index = 0; $index < $adb->num_rows($qRes4); $index++) {
    $getdate = $adb->query_result($qRes4,$index,'limitdate');
}
$finaldatediff=0;
if($getdate!='' && $getdate!=null)
{
$now = strtotime(date("Y-m-d")); // or your date as well   
$your_date = strtotime($getdate);
$datediff = $now - $your_date;
$finaldatediff=floor($datediff/(60*60*24));
}
$res['limiteddays']=$finaldatediff;
$limiteddays=$finaldatediff;

    if($saltotamt > 0)
    {
        if($limitedamt<$saltotamt){ 
            $res['climit'] = 'y';
        }else { $res['climit'] = 'n'; }
    }else { $res['climit'] = 'n'; }
    if($saltotbill > 0)
    {
        if($limitedbill<$saltotbill){              
            $res['bill'] = 'y';
        }else { $res['bill'] = 'n'; }
    }else { $res['bill'] = 'n'; }
    $res['limitdays'] = $limiteddays;$res['saltotday'] = $saltotdays;
    
    if($saltotdays > 0)
    {
        if($limiteddays<=$saltotdays){              
            $res['days'] = 'y';
        }else { $res['days'] = 'n'; }
    }else { $res['days'] = 'n'; }
           if($res['climit']=='n' || $res['days'] == 'n' || $res['bill'] == 'n'){
             $Salesman=FALSE; 
           } 
        }
            //echo $limitedbill.$saltotbill;print_r($res);die;
    if($retailer_bulk[0]['value']==1){ 
        $rsi_result_retailer = $adb->mquery("select cf_xsalesorder_buyer_id from vtiger_xsalesordercf where salesorderid=".$ids_arr[$i]);
            $entity_id=$adb->query_result($rsi_result_retailer,0,'cf_xsalesorder_buyer_id');
        $querysaleinvoicechk="select sum(sicf.cf_salesinvoice_outstanding) as tot from vtiger_salesinvoice as si 
            INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=si.salesinvoiceid 
            INNER join vtiger_salesinvoicecf as sicf on sicf.salesinvoiceid=si.salesinvoiceid 
            where vtiger_crmentity.deleted=0 and si.status='Created' and sicf.cf_salesinvoice_outstanding > 0 
            and si.vendorid=".$entity_id." group by si.vendorid";
        $resultsaleinvoicechk = $adb->mquery($querysaleinvoicechk);
        $Rclimit = round($adb->query_result($resultsaleinvoicechk,0,'tot'));
        
       
        if($Rclimit=='' || $Rclimit==null)
        {
            $Rclimit=0;
        }
        $Rclimit=$Rclimit+$amount_query_value;
        $querysaleinvoicechk1="select count(*) as billcount from vtiger_salesinvoice as si 
            INNER join vtiger_salesinvoicecf as sicf on sicf.salesinvoiceid=si.salesinvoiceid 
            where si.status='Created' and sicf.cf_salesinvoice_outstanding > 0 
            and si.vendorid='$entity_id'";       
        $resultsaleinvoicechk1 = $adb->mquery($querysaleinvoicechk1);
        $Rbillno=round($adb->query_result($resultsaleinvoicechk1,0,'billcount'));
        if($Rbillno=='' || $Rbillno==null)
        {
            $Rbillno=0;
        } 
        $Rbillno=$Rbillno+$addchkbill;
        
        $retailer="select rcf.cf_xretailer_creditbills,rcf.cf_xretailer_creditamount,ctcf.cf_xcreditterm_number_of_days,rcf.cf_xpayment_payment_mode,vtiger_xcollectionmethod.cf_xcollectionmethod_collection_type,cf_xretailer_tin_number 
            from vtiger_xretailercf rcf
            LEFT JOIN vtiger_crmentity ct on ct.crmid=rcf.xretailerid
            LEFT JOIN vtiger_xcredittermcf ctcf on ctcf.xcredittermid=rcf.cf_xretailer_creditdays
            LEFT JOIN vtiger_xcollectionmethod ON 
vtiger_xcollectionmethod.xcollectionmethodid = rcf.cf_xpayment_payment_mode
            where ct.deleted=0 AND xretailerid=".$entity_id;
        $retailerchk1 = $adb->pquery($retailer);      
                  
        
        $Rchklimit = $adb->query_result($retailerchk1,0,'cf_xretailer_creditamount');
        $Rchktinnumber = $adb->query_result($retailerchk1,0,'cf_xretailer_tin_number');
        $Rchkbillno = $adb->query_result($retailerchk1,0,'cf_xretailer_creditbills');
        $Rchkdays = $adb->query_result($retailerchk1,0,'cf_xcreditterm_number_of_days');
        $Rchkdid = $adb->query_result($retailerchk1,0,'cf_xpayment_payment_mode');
        $Rchkcollection = $adb->query_result($retailerchk1,0,'cf_xcollectionmethod_collection_type');
        $res['collection_id'] = $Rchkdid;
	$res['collection_type'] = $Rchkcollection;
		$dist_id = getDistrIDbyUserID();
		$where = '';
		if(!empty($dist_id)) {
			$cmpny_detail = getDistributorCompany($dist_id['id']);
			$cmpny_user_id = $cmpny_detail['reports_to_id'];
			$where .= " AND  (ts.xdistributorid = '".$dist_id['id']."')"; 
		}
                if($cmpny_user_id=='' || $cmpny_user_id==null)
                    $cmpny_user_id="''";
        $rettransactionseries="select ts.xtransactionseriesid,ts.transactionseriesname 
                                from vtiger_xtransactionseries ts
                                inner join vtiger_xtransactionseriescf tscf on  tscf.xtransactionseriesid=ts.xtransactionseriesid
                                where ts.tinnumber=1 
                                and tscf.cf_xtransactionseries_transaction_type='Sales Invoice' $where";
        $rettransactionseriesresult = $adb->pquery($rettransactionseries); 
        $transactionseriesid = $adb->query_result($rettransactionseriesresult,0,'xtransactionseriesid');
        $transactionseriesname = $adb->query_result($rettransactionseriesresult,0,'transactionseriesname');
        //transseries from company toall distributor
		
        $defaulttransactionseries="select ts.xtransactionseriesid,ts.transactionseriesname 
                                from vtiger_xtransactionseries ts
                                inner join vtiger_xtransactionseriescf tscf on  tscf.xtransactionseriesid=ts.xtransactionseriesid
                                where tscf.cf_xtransactionseries_mark_as_default=1 
                                and tscf.cf_xtransactionseries_transaction_type='Sales Invoice' $where";
        $defaulttransactionseriesresult = $adb->pquery($defaulttransactionseries); 
		if($adb->num_rows($defaulttransactionseriesresult)==0)
		{
				$defaulttransactionseries="select ts.xtransactionseriesid,ts.transactionseriesname 
                                from vtiger_xtransactionseries ts
                                inner join vtiger_xtransactionseriescf tscf on  tscf.xtransactionseriesid=ts.xtransactionseriesid
                                where tscf.cf_xtransactionseries_mark_as_default=1 
                                and tscf.cf_xtransactionseries_transaction_type='Sales Invoice' AND  (ts.cf_xtransactionseries_user_id = ".$cmpny_user_id." AND ts.xdistributorid=0 OR (ts.xdistributorid = ".$dist_id['id']."))";
				$defaulttransactionseriesresult = $adb->pquery($defaulttransactionseries); 
		}
		$dftransactionseriesid = $adb->query_result($defaulttransactionseriesresult,0,'xtransactionseriesid');
		$dftransactionseriesname = $adb->query_result($defaulttransactionseriesresult,0,'transactionseriesname'); 
        //echo "Hi :".$Rchklimit.", ".$Rclimit;exit;
        $Qry4 = "select date(sicf.cf_salesinvoice_sales_invoice_date) as limitdate from vtiger_salesinvoice as si 
                    INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=si.salesinvoiceid 
                    left join vtiger_salesinvoicecf as sicf on sicf.salesinvoiceid=si.salesinvoiceid 
                    where vtiger_crmentity.deleted=0 and si.status='Created' and sicf.cf_salesinvoice_outstanding > 0 
                    and si.vendorid=".$entity_id." order by vtiger_crmentity.createdtime asc limit 1";
                $qRes4 = $adb->pquery($Qry4,array());
        for ($index = 0; $index < $adb->num_rows($qRes4); $index++) {
            $getdate = $adb->query_result($qRes4,$index,'limitdate');
        }
        $finaldatediff=0;
        if($getdate!='' && $getdate!=null)
        {
        $now = strtotime(date("Y-m-d")); // or your date as well
        $your_date = strtotime($getdate);
        $datediff = $now - $your_date;
        $finaldatediff=floor($datediff/(60*60*24));  
        }      
         if($Rchklimit > 0)
         {
            if($Rchklimit>$Rclimit){ 
                $res['climit'] = 'y';
            }else { $res['climit'] = 'n'; }
         }else { $res['climit'] = 'n'; }
         
         if($Rchkbillno > 0)
         {
            if($Rchkbillno>$Rbillno){              
                $res['bill'] = 'y';
            }else { $res['bill'] = 'n'; }
         }else { $res['bill'] = 'n'; }
         
         if($Rchkdays > 0)
         {
            if($Rchkdays>=$finaldatediff){              
                $res['days'] = 'y';
            }else { $res['days'] = 'n'; } 
         }else { $res['days'] = 'n'; }  
        if($res['climit']=='n' || $res['days'] == 'n' || $res['bill'] == 'n'){
             $Salesman=FALSE; 
           }
    }    
        //print_r($res);die;
        //if($stock_bulk[0]['value']==1){ 

            $log =& LoggerManager::getLogger('index');
            $pro_cate_qry = $adb->mquery("select so_lbl_save_pro_cate from vtiger_xsalesorder where salesorderid = '".$ids_arr[$i]."'");
            $so_lbl_save_pro_cate = $adb->query_result($pro_cate_qry,0,'so_lbl_save_pro_cate');
            
            if(isset($SO_PRO_CATE_BASED) && $SO_PRO_CATE_BASED == 'True' && $so_lbl_save_pro_cate == 'True') {
                $rsi_result_stock = $adb->mquery("SELECT pcf.xproductid, spr.xprodhierid, SUM(spr.baseqty) as baseqty
                                                  FROM vtiger_xsalesorderproductrel spr
                                                  LEFT JOIN vtiger_xproductcf pcf ON pcf.cf_xproduct_category = spr.xprodhierid
                                                  WHERE spr.id = '".$ids_arr[$i]."'
                                                  AND pcf.cf_xproduct_active = 1 AND pcf.deleted = 0 AND spr.product_type = 'Main'
                                                  AND spr.baseqty > spr.siqty GROUP BY spr.xprodhierid, pcf.xproductid ORDER BY spr.xprodhierid");

                $result_set = $adb->getResultSet($rsi_result_stock);

                $result_set_modified = array();
                foreach($result_set as $key => $set) {
                    $result_set_modified[$set['xprodhierid']]['baseqty'] = $set['baseqty'];
                    $result_set_modified[$set['xprodhierid']]['xproductid'][] = $set['xproductid'];
                }
                $result_stock_num_rows = count($result_set_modified);
                
                foreach($result_set_modified as $xproductid => $result_set) {
                    $xproductids = implode(',', $result_set['xproductid']);
                    $so_stocklots = $adb->pquery("SELECT sum(iq.salable_qty) as salable_qty FROM (SELECT id, IFNULL(batchnumber,'') as `batchnumber`, pkg, expiry, IFNULL(SUM(salable_qty),0.0)-IFNULL(SUM(sold_salable_qty),0.0) as salable_qty, IFNULL(SUM(free_qty),0.0)-IFNULL(SUM(sold_free_qty),0.0) as free_qty, sum(damaged_qty) as damaged_qty, sum(damaged_free_qty) as damaged_free_qty, pts, ptr, mrp, ecp , ptr_type from vtiger_stocklots 
                                                  where productid IN (".$xproductids.") AND distributorcode='".$distArr['id']."'  AND location_id='".$godown_def_name['xgodownid']."'  group by distributorcode,productid,batchnumber,pkg,expiry,ptr,mrp,ecp ORDER BY batchnumber,expiry,pkg,ptr,ptr_type) as iq WHERE salable_qty > 0");

                    $available_qty = $adb->query_result($so_stocklots,0,'salable_qty');
                    $qty_base = $result_set['baseqty'];
                    if($available_qty != 0){
                        $stock_any = TRUE;
                    }
                    if($stock_bulk[0]['value'] == 1 && ($available_qty < $qty_base)){
                      $Stock = FALSE;
                    }
                    if($result_stock_num_rows == 1 && ($available_qty < $qty_base) && $stock_bulk[0]['value'] == 1) {
                        $stock_any = FALSE;
                    }
                }
                
                $log->debug('Stock: '.print_r($Stock, true));
                $log->debug('Stock_any: '.print_r($stock_any, true));
               
            } else {
                $rsi_result_stock = $adb->mquery("select productid,baseqty from vtiger_xsalesorderproductrel where id='".$ids_arr[$i]."' and product_type = 'Main' order by sequence_no");

                for($f=0;$f<$adb->num_rows($rsi_result_stock);$f++){
                    $so_stocklots = $adb->pquery("SELECT sum(iq.salable_qty) as salable_qty FROM (SELECT id, IFNULL(batchnumber,'') as `batchnumber`, pkg, expiry, IFNULL(SUM(salable_qty),0.0)-IFNULL(SUM(sold_salable_qty),0.0) as salable_qty, IFNULL(SUM(free_qty),0.0)-IFNULL(SUM(sold_free_qty),0.0) as free_qty, sum(damaged_qty) as damaged_qty, sum(damaged_free_qty) as damaged_free_qty, pts, ptr, mrp, ecp , ptr_type from vtiger_stocklots 
                                                  where productid='".$adb->query_result($rsi_result_stock,$f,'productid')."' AND distributorcode='".$distArr['id']."'  AND location_id='".$godown_def_name['xgodownid']."'  group by distributorcode,productid,batchnumber,pkg,expiry,ptr,mrp,ecp ORDER BY batchnumber,expiry,pkg,ptr,ptr_type) as iq WHERE salable_qty > 0");

                    $available_qty = $adb->query_result($so_stocklots,0,'salable_qty');
                    $qty_base = $adb->query_result($rsi_result_stock,$f,'baseqty');
                    if($available_qty!=0){
                        $stock_any=TRUE;
                        //break;
                    }
                    if($stock_bulk[0]['value']==1 && $available_qty<$qty_base){
                      $Stock=FALSE;
                    }
                    if($adb->num_rows($rsi_result_stock)==1 && $qty_base>$available_qty && $stock_bulk[0]['value']==1){
                        $stock_any=FALSE;
                    }
                }
                $log->debug('Stock: '.print_r($Stock, true));
                $log->debug('Stock_any: '.print_r($stock_any, true));
                
            }
        //}
        if($Retailer==FALSE || $Salesman==FALSE || $stock_any==FALSE)
            $fails=$fails+1;

        if(is_numeric($ids_arr[$i]) && $Retailer==TRUE && $Salesman==TRUE && $stock_any==TRUE && $Stock==TRUE)
        { 
            
            require_once('modules/xSalesOrder/xSalesOrder.php');
            require_once('include/utils/utils.php');
            require_once('modules/SalesInvoice/utils/EditViewUtils.php');
            require_once('modules/SalesInvoice/utils/SalesInvoiceUtils.php');
            require_once('modules/SalesInvoice/SalesInvoice.php');
            require_once('include/database/PearDatabase.php');
            require_once('include/TransactionSeries.php');
            require_once('include/WorkflowBase.php');
            require_once('config.salesinvoice.php');
            //require_once('data/CRMEntity.php');
            
            $soid = $ids_arr[$i];
            
            $module = 'SalesInvoice';
            $status_flag = true;
            //$distArr = getDistrIDbyUserID();
            $so_focus = new xSalesOrder();
            $focus = new SalesInvoice();
            $so_focus->id = $soid;
            $so_focus->retrieve_entity_info($soid, "xSalesOrder"); //echo "<pre> ";print_r($so_focus);exit;
            
            $focus = getConvertSoToInvoice($focus, $so_focus, $soid,'si');
            
            
            if($so_focus->column_fields['status'] == 'Processed' || $so_focus->column_fields['status'] == 'Rejected')
            {
                $skip_cnt++;
//                continue;
            }
            if(!$conv_to_si)
            {
                $adb->pquery("UPDATE vtiger_xsalesordercf set cf_xsalesorder_next_stage_name=? where salesorderid=?",array($ns1['cf_workflowstage_next_stage'],$soid));
                $adb->pquery("UPDATE vtiger_xsalesorder set status=? where salesorderid=?",array($ns1['cf_workflowstage_next_content_status'],$soid));
//                continue;
            }
            // Reset the value w.r.t SalesOrder Selected
            $result = $adb->pquery("SELECT cf_xpayment_payment_mode,cf_xretailer_creditdays,cf_xretailer_tin_number FROM vtiger_xretailercf WHERE `xretailerid` = '".$so_focus->column_fields['buyer_id']."'");
            $value = $adb->query_result($result,0,'cf_xpayment_payment_mode');
            $days = $adb->query_result($result,0,'cf_xretailer_creditdays');
            $xretailer_tin_number = $adb->query_result($result,0,'cf_xretailer_tin_number');
            
            $currencyid = $so_focus->column_fields['currency_id']; 
            $rate = $so_focus->column_fields['conversion_rate']; 
            $focus->column_fields['cf_salesinvoice_pay_mode'] = $value;
            $focus->column_fields['requisition_no'] = $so_focus->column_fields['salesorder_no'];
            $focus->column_fields['tracking_no'] = $so_focus->column_fields['cf_salesorder_transaction_number'];
            $focus->column_fields['adjustment'] = $so_focus->column_fields['adjustment'];
            $focus->column_fields['salescommission'] = '0.000000';
            $focus->column_fields['exciseduty'] = 0.000;
            $focus->column_fields['cf_salesinvoice_credit_term'] = $days;
            $focus->column_fields['total'] = (($so_focus->column_fields['total'] != '') ? numberformat($so_focus->column_fields['total'],6) : '0.000000');
            $focus->column_fields['subtotal'] = (($so_focus->column_fields['subtotal'] != '') ? numberformat($so_focus->column_fields['subtotal'],6) : '0.000000');
            $focus->column_fields['taxtype'] = $so_focus->column_fields['taxtype'];
            $focus->column_fields['hdnDiscountPercent'] = (($so_focus->column_fields['hdnDiscountPercent'] != '') ? numberformat($so_focus->column_fields['hdnDiscountPercent'],6) : '0.000000');
            $focus->column_fields['hdnDiscountAmount'] = (($so_focus->column_fields['hdnDiscountAmount'] != '') ? numberformat($so_focus->column_fields['hdnDiscountAmount'],6) : '0.000000');
            $focus->column_fields['hdnS_H_Amount'] = (($so_focus->column_fields['hdnS_H_Amount'] != '') ? numberformat($so_focus->column_fields['hdnS_H_Amount'],6) : '0.000000');
            
            
            $focus->column_fields['discount_percent'] = $so_focus->column_fields['hdnDiscountPercent'];
            $focus->column_fields['discount_amount'] = $so_focus->column_fields['hdnDiscountAmount'];
            $focus->column_fields['s_h_amount'] = $so_focus->column_fields['hdnS_H_Amount'];
            
            $focus->column_fields['cf_salesinvoice_reason'] = $so_focus->column_fields['cf_xrsalesinvoice_reason'];
            //$defaultTrans = getDefaultTransactionSeries("Sales Invoice"); 
            
            $defaultTrans = getDefaultTransactionSeriesbasedtin("Sales Invoice",$xretailer_tin_number); 
            
            $focus->column_fields['cf_salesinvoice_transaction_series'] = $defaultTrans['xtransactionseriesid'];
            $focus->column_fields['cf_salesinvoice_transaction_series_display'] = $defaultTrans['transactionseriesname'];
            $tinnumber = $defaultTrans['tinnumber'];
            if($tinnumber ==1){
                $tinnumber = $tinnumber;
            }else{
                $tinnumber=0;
            }
              
            //added to set the PO number and terms and conditions
            $focus->column_fields['terms_conditions'] = $so_focus->column_fields['terms_conditions'];
            $focus->column_fields['vendor_id'] = $so_focus->column_fields['buyer_id'];
            $focus->column_fields['cf_salesinvoice_beat'] = $so_focus->column_fields['cf_xsalesorder_beat'];
            $focus->column_fields['cf_salesinvoice_sales_man'] = $so_focus->column_fields['cf_xsalesorder_sales_man'];
            $focus->column_fields['carrier'] = '';
            $focus->column_fields['cf_salesinvoice_reason'] = $so_focus->column_fields['cf_xsalesorder_reason'];
            $focus->column_fields['cf_salesinvoice_billing_address_pick'] = $so_focus->column_fields['cf_xsalesorder_billing_address_pick'];
            $focus->column_fields['cf_salesinvoice_shipping_address_pick'] = $so_focus->column_fields['cf_xsalesorder_shipping_address_pick'];
            //$godown_def_name = getGodownDefualtName();                    
            $focus->column_fields['si_location'] = $godown_def_name['xgodownid'];
            $so_focus->column_fields['cf_xrsalesinvoice_sales_invoice_date'] = str_replace('/', '-', $so_focus->column_fields['cf_salesorder_sales_order_date']);
            if($so_focus->column_fields['cf_salesorder_sales_order_date'] != '')
            {
                $focus->column_fields['cf_salesinvoice_sales_invoice_date'] = date("d-m-Y");
                $focus->column_fields['duedate'] = date("d-m-Y");
                $focus->column_fields['cf_salesinvoice_payment_date'] = date("d-m-Y");
				
				    //FRPRDINXT-6222 
				    $credit_term = $so_focus->column_fields['cf_xsalesorder_credit_term'];
					if(is_numeric($credit_term) && $credit_term >= 1){
						$credit_sql = $adb->pquery("SELECT cf_xcreditterm_number_of_days FROM vtiger_xcredittermcf WHERE xcredittermid=?", array($credit_term));
						$credit_days = $adb->query_result($credit_sql, 0, 'cf_xcreditterm_number_of_days');
						$focus->column_fields['cf_salesinvoice_payment_date'] = date("d-m-Y", strtotime("+$credit_days days"));
					}
            }

            $focus->column_fields['description'] = $so_focus->column_fields['description'];


            $BillAdd = getdefaultaddress('Billing',$so_focus->column_fields['cf_xsalesorder_buyer_id']);
            if (!empty($BillAdd))
            {
                $focus->column_fields['cf_salesinvoice_billing_address_pick'] = $BillAdd['xaddressid'];
                $focus->column_fields['bill_street'] = $BillAdd['cf_xaddress_address'];
                $focus->column_fields['bill_pobox'] = $BillAdd['cf_xaddress_po_box'];
                $focus->column_fields['bill_city'] = $BillAdd['cf_xaddress_city'];
                //$focus->column_fields['bill_state'] = "";
                $focus->column_fields['bill_code'] = $BillAdd['cf_xaddress_postal_code'];
                $focus->column_fields['bill_country'] = $BillAdd['cf_xaddress_country'];
            }

            $ShipAdd = getdefaultaddress('Shipping',$so_focus->column_fields['cf_xsalesorder_buyer_id']);
            if (!empty($ShipAdd))
            {
                $focus->column_fields['cf_salesinvoice_shipping_address_pick'] = $ShipAdd['xaddressid'];
                $focus->column_fields['ship_street'] = $ShipAdd['cf_xaddress_address'];
                $focus->column_fields['ship_pobox'] = $ShipAdd['cf_xaddress_po_box'];
                $focus->column_fields['ship_city'] = $ShipAdd['cf_xaddress_city'];
                //$focus->column_fields['gstinno'] = $ShipAdd['gstinno'];
                //$focus->column_fields['ship_state'] = "";
                $focus->column_fields['ship_code'] = $ShipAdd['cf_xaddress_postal_code'];
                $focus->column_fields['ship_country'] = $ShipAdd['cf_xaddress_country'];
            }
            
            $query_so_gstno = "SELECT gstinno FROM `vtiger_xaddress` WHERE `xaddressid` = '".$so_focus->column_fields['cf_xsalesorder_shipping_address_pick']."' ";
            $result_so_gstno = $adb->pquery($query_so_gstno,array());
            $gstinno_so_gstno = $adb->query_result($result_so_gstno, 0, 'gstinno');
            $focus->column_fields['gstinno'] = $gstinno_so_gstno;
            //Added to display the SalesOrder's associated vtiger_products -- when we create vtiger_invoice from SO DetailView
            $txtTax = (($so_focus->column_fields['txtTax'] != '') ? $so_focus->column_fields['txtTax'] : '0.000000');
            $txtAdj = (($so_focus->column_fields['txtAdjustment'] != '') ? $so_focus->column_fields['txtAdjustment'] : '0.000000');

            setObjectValuesFromRequest($focus);
            $focus->update_prod_stock='';
            if($focus->column_fields['status'] == 'Received Shipment')
            {
                $prev_postatus=getPoStatus($focus->id);
                if($focus->column_fields['status'] != $prev_postatus)
                {
                        $focus->update_prod_stock='true';
                }
            }

            $focus->column_fields['currency_id'] = $so_focus->column_fields['currency_id'];
            $cur_sym_rate = getCurrencySymbolandCRate($so_focus->column_fields['currency_id']);
            $focus->column_fields['conversion_rate'] = $cur_sym_rate['rate'];

            $posAction = "Submit";
            $ns = getNextstageByPosAction($module,$posAction);
            $focus->column_fields['cf_salesinvoice_next_stage_name'] = $ns['cf_workflowstage_next_stage'];
            $focus->column_fields['status'] = $ns['cf_workflowstage_next_content_status'];

            $focus->column_fields['assigned_user_id'] = '';

//            $transQry = "SELECT mt.xtransactionseriesid,mt.transactionseriesname,rt.xtransactionseriesid,rt.cf_xtransactionseries_transaction_type,rt.cf_xtransactionseries_user_id FROM vtiger_xtransactionseries mt LEFT JOIN vtiger_xtransactionseriescf rt ON mt.xtransactionseriesid=rt.xtransactionseriesid LEFT JOIN vtiger_crmentity ct ON mt.xtransactionseriesid=ct.crmid WHERE 
//ct.deleted=0 AND rt.cf_xtransactionseries_transaction_type='Sales Invoice' LIMIT 1";
//            $traArr = $adb->pquery($transQry);
            
             //$tArr = generateUniqueSeries($transactionseriesname='',"Sales Invoice"); 
             //$tArr = generateUniqueSeries($transactionseriesname='',"Sales Invoice",$increment=TRUE,$focus->column_fields['cf_salesinvoice_transaction_series']); 
             $focus->column_fields['cf_salesinvoice_transaction_number'] = 'Draft_'.$so_focus->column_fields['subject'];;//$tArr['uniqueSeries'];
             $focus->column_fields['cf_salesinvoice_seller_id'] = $distArr['id'];

            //$focus->column_fields['discount_amount'] = (($so_focus->column_fields['hdnDiscountAmount'] != '') ? numberformat($so_focus->column_fields['hdnDiscountAmount'],6) : '0.000000');
            //$focus->column_fields['hdnDiscountAmount'] = (($so_focus->column_fields['hdnDiscountAmount'] != '') ? numberformat($so_focus->column_fields['hdnDiscountAmount'],6) : '0.000000');
            $focus->column_fields['cf_salesinvoice_buyer_id']=$so_focus->column_fields['cf_xsalesorder_buyer_id'];
            $focus->column_fields['cf_salesinvoice_outstanding']=(($so_focus->column_fields['total'] != '') ? numberformat($so_focus->column_fields['total'],6) : '0.000000');
            
            $focus->save("SalesInvoice");
            $return_id = $focus->id;
			
            $is_taxfiled	=  0; 
            $trntaxtype		= ($ALLOW_GST_TRANSACTION) ? 'GST' : 'VAT'; 	
            $wherUpdateColumn ='';
            if($ALLOW_GST_TRANSACTION){

                    $dis_location = $godown_def_name['xgodownid'];
                    $ret_shipping_address_pick = $so_focus->column_fields['cf_xsalesorder_shipping_address_pick'];	
                    $ret_id = $so_focus->column_fields['buyer_id'];

                    $ret_shipping_gstno = $adb->pquery("SELECT xAdd.gstinno,xState.statecode from vtiger_xaddress xAdd INNER JOIN vtiger_xstate xState on xState.xstateid=xAdd.xstateid where xAdd.xaddressid=?",array($ret_shipping_address_pick));

                    if($adb->num_rows($ret_shipping_gstno)>0) {
                            $buyer_gstinno = $adb->query_result($ret_shipping_gstno, 0, 'gstinno');
                            $buyer_state = $adb->query_result($ret_shipping_gstno, 0, 'statecode');
                            $wherUpdateColumn.= 'buyer_gstinno="'.$buyer_gstinno.'" , buyer_state="'.$buyer_state.'",';
                    }else{
                            $ret_gstno=$adb->pquery("SELECT vtiger_xretailer.gstinno,xState.statecode FROM vtiger_xretailer INNER JOIN vtiger_xretailercf ON vtiger_xretailercf.xretailerid=vtiger_xretailer.xretailerid INNER JOIN vtiger_xstate xState on xState.xstateid=vtiger_xretailercf.cf_xretailer_state where vtiger_xretailer.xretailerid=?",array($ret_id));
                            if($adb->num_rows($ret_gstno)>0) {
                                    $buyer_gstinno = $adb->query_result($ret_gstno, 0, 'gstinno');
                                    $buyer_state = $adb->query_result($ret_gstno, 0, 'statecode');
                                    $wherUpdateColumn.= 'buyer_gstinno="'.$buyer_gstinno.'" , buyer_state="'.$buyer_state.'",';
                            }
                    }

                    $xdis_godown_gstno = $adb->pquery("SELECT xgodown.gstinno,xState.statecode from vtiger_xgodown xgodown INNER JOIN vtiger_xstate xState on xState.xstateid=xgodown.xstateid where xgodown.gstinno!='' AND xgodown.xgodownid=".$dis_location);

                     if($adb->num_rows($xdis_godown_gstno)>0) {
                            $seller_gstinno = $adb->query_result($xdis_godown_gstno, 0, 'gstinno');
                            $seller_state = $adb->query_result($xdis_godown_gstno, 0, 'statecode');
                            $wherUpdateColumn.= 'seller_gstinno="'.$seller_gstinno.'" , seller_state="'.$seller_state.'",';
                     }else{
                             $xdis_gstno=$adb->pquery("SELECT vtiger_xdistributor.gstinno,xState.statecode FROM vtiger_xdistributor INNER JOIN vtiger_xdistributorcf ON vtiger_xdistributorcf.xdistributorid=vtiger_xdistributor.xdistributorid INNER JOIN vtiger_xstate xState on xState.xstateid=vtiger_xdistributorcf.cf_xdistributor_state where vtiger_xdistributor.xdistributorid=?",array($distArr['id']));
                             if($adb->num_rows($xdis_gstno)>0) {
                                    $seller_gstinno = $adb->query_result($xdis_gstno, 0, 'gstinno');
                                    $seller_state = $adb->query_result($xdis_gstno, 0, 'statecode');
                                    $wherUpdateColumn.= 'seller_gstinno="'.$seller_gstinno.'" , seller_state="'.$seller_state.'",';
                             }
                     }				
            }

            $wherUpdateColumn.= 'is_taxfiled="'.$is_taxfiled.'" , trntaxtype="'.$trntaxtype.'",';

            $adb->pquery("UPDATE vtiger_salesinvoice set $wherUpdateColumn status=? where salesinvoiceid=?",array($focus->column_fields['status'],$return_id));
            $adb->pquery("UPDATE vtiger_salesinvoicecf set cf_salesinvoice_next_stage_name=?,cf_salesinvoice_transaction_number=? where salesinvoiceid=?",array($focus->column_fields['cf_salesinvoice_next_stage_name'],$focus->column_fields['cf_salesinvoice_transaction_number'],$return_id));
            if($SI_LBL_CURRENCY_OPTION_ENABLE!="True") {
                $updateQry = "UPDATE `vtiger_salesinvoice` SET `currency_id` = '1' WHERE `salesinvoiceid` = ".$return_id;
                $adb->pquery($updateQry);
            }

            if($SI_LBL_TAX_OPTION_ENABLE!="True") {
                $updateQry2 = "UPDATE `vtiger_salesinvoice` SET `taxtype` = 'individual' WHERE `salesinvoiceid` = ".$return_id;
                $adb->pquery($updateQry2);
            }
            
            if(isset($SO_PRO_CATE_BASED) && $SO_PRO_CATE_BASED == 'True' && $so_lbl_save_pro_cate == 'True') {

                if(isset($SO_PRO_LIST_OR_BY_PRICE) && $SO_PRO_LIST_OR_BY_PRICE == 'True' && isset($SO_PRO_LIST_OR_BY_STOCK) && $SO_PRO_LIST_OR_BY_STOCK == 'False') {
                    $order_by_fld = 'sl.mrp';
                } else if(isset($SO_PRO_LIST_OR_BY_STOCK) && $SO_PRO_LIST_OR_BY_STOCK == 'True' && isset($SO_PRO_LIST_OR_BY_PRICE) && $SO_PRO_LIST_OR_BY_PRICE == 'True') {
                    $order_by_fld = 'sl.mrp, qty_in_stock';
                } else {
                    $order_by_fld = 'sl.id';
                }
                
                $query = "SELECT iq.* FROM 
                            (SELECT p.xproductid,p.productcode,
                             spr.id,spr.productid,spr.productcode as product_code,spr.product_type,spr.sequence_no,spr.quantity,spr.baseqty,spr.dispatchqty,spr.siqty,pcf.cf_xproduct_base_uom as tuom,spr.discount_percent,spr.discount_amount,spr.sch_disc_amount,spr.description,spr.lineitem_id,spr.comment,spr.incrementondel,spr.tax1,spr.tax2,spr.tax3,spr.billing_at,spr.created_at,spr.modified_at,spr.xprodhierid,
                             sl.id as batch_id, IFNULL(sl.batchnumber,'') AS `batchnumber`, sl.pkg, sl.expiry, IFNULL(sl.salable_qty,0.0)-IFNULL(sl.sold_salable_qty,0.0) AS qty_in_stock, sl.pts, sl.ptr, sl.ptr as listprice, sl.mrp, sl.ecp
                             FROM vtiger_stocklots sl
                             LEFT JOIN vtiger_xproduct p ON p.xproductid = sl.productid
                             LEFT JOIN vtiger_xproductcf pcf ON pcf.xproductid = p.xproductid
                             INNER JOIN vtiger_xsalesorderproductrel spr ON spr.id = '".$so_focus->id."' AND spr.xprodhierid = pcf.cf_xproduct_category
                             WHERE sl.productid IN (SELECT pcf_inner.xproductid
                                                                    FROM vtiger_xsalesorderproductrel spr_inner
                                                                    LEFT JOIN vtiger_xproductcf pcf_inner ON pcf_inner.cf_xproduct_category = spr_inner.xprodhierid 
                                                                    WHERE spr_inner.id = '".$so_focus->id."'
                                                                    AND pcf_inner.cf_xproduct_active = 1 AND pcf_inner.deleted = 0 AND spr_inner.product_type = 'Main' 
                                                                    AND spr_inner.baseqty > spr_inner.siqty GROUP BY pcf_inner.xproductid) 
                             AND distributorcode='".$distArr['id']."'  AND location_id='".$godown_def_name['xgodownid']."'
                         GROUP BY sl.id
                         ORDER BY {$order_by_fld}) AS iq WHERE qty_in_stock > 0.0";
                
                if(isset($SO_PRO_LIST_OR_BY_STOCK) && $SO_PRO_LIST_OR_BY_STOCK == 'True' && isset($SO_PRO_LIST_OR_BY_PRICE) && $SO_PRO_LIST_OR_BY_PRICE == 'False') {

                    $query = "SELECT iq.*,SUM(qty_in_stock_inner) as qty_in_stock FROM 
                                (SELECT p.xproductid,p.productcode,
                                 spr.id,spr.productid,spr.productcode as product_code,spr.product_type,spr.sequence_no,spr.quantity,spr.baseqty,spr.dispatchqty,spr.siqty,pcf.cf_xproduct_base_uom as tuom,spr.discount_percent,spr.discount_amount,spr.sch_disc_amount,spr.description,spr.lineitem_id,spr.comment,spr.incrementondel,spr.tax1,spr.tax2,spr.tax3,spr.billing_at,spr.created_at,spr.modified_at,spr.xprodhierid, 
                                 sl.id as batch_id, IFNULL(sl.batchnumber,'') AS `batchnumber`, sl.pkg, sl.expiry, IFNULL(sl.salable_qty,0.0)-IFNULL(sl.sold_salable_qty,0.0) AS qty_in_stock_inner, sl.pts, sl.ptr, sl.ptr as listprice, sl.mrp, sl.ecp
                                 FROM vtiger_stocklots sl
                                 LEFT JOIN vtiger_xproduct p ON p.xproductid = sl.productid
                                 LEFT JOIN vtiger_xproductcf pcf ON pcf.xproductid = p.xproductid
                                 INNER JOIN vtiger_xsalesorderproductrel spr ON spr.id = '".$so_focus->id."' AND spr.xprodhierid = pcf.cf_xproduct_category
                                 WHERE sl.productid IN (SELECT pcf_inner.xproductid
                                                                        FROM vtiger_xsalesorderproductrel spr_inner
                                                                        LEFT JOIN vtiger_xproductcf pcf_inner ON pcf_inner.cf_xproduct_category = spr_inner.xprodhierid 
                                                                        WHERE spr_inner.id = '".$so_focus->id."'
                                                                        AND pcf_inner.cf_xproduct_active = 1 AND pcf_inner.deleted = 0 AND spr_inner.product_type = 'Main' 
                                                                        AND spr_inner.baseqty > spr_inner.siqty GROUP BY pcf_inner.xproductid) 
                                 AND distributorcode='".$distArr['id']."'  AND location_id='".$godown_def_name['xgodownid']."' AND (IFNULL(sl.salable_qty,0.0)-IFNULL(sl.sold_salable_qty,0.0)) > 0.0
                                 GROUP BY sl.id,p.xproductid) AS iq GROUP BY xproductid ORDER BY qty_in_stock";
                }
                
                $rsi_result_stock = $adb->mquery($query);
                $num_rows=$adb->num_rows($rsi_result_stock);
                $result_set = $adb->getResultSet($rsi_result_stock);
               
                $query = "SELECT xprodhierid, SUM(quantity) as quantity, SUM(baseqty) as baseqty, SUM(siqty) as siqty FROM vtiger_xsalesorderproductrel spr WHERE spr.id = '".$so_focus->id."' GROUP BY xprodhierid";
                $quantity_result = $adb->mquery($query);
                $quantity_result_set = $adb->getResultSet($quantity_result);
                
                $result_set_modified = array();
                foreach($result_set as $key => $set) {
                    foreach($quantity_result_set as $quantity_result) {
                        if($quantity_result['xprodhierid'] == $set['xprodhierid']) {
                            $set['quantity'] = $quantity_result['quantity'];
                            $set['baseqty'] = $quantity_result['baseqty'];
                            $set['siqty'] = $quantity_result['siqty'];
                            $set['productid'] = $set['xproductid'];
                            $set['proportionate_discount_amount'] = $set['discount_amount'] / $quantity_result['baseqty'];
                            $result_set_modified[$set['xprodhierid']][] = $set;
                        }
                    }
                }
                
                $log->debug('Result set modified: '.print_r($result_set_modified, true));

                $result_set = array();
                foreach($result_set_modified as $xprodhierid => $set) {

                    $total_qty_in_stock_fld = 0;
                    $baseqty_fld  = $baseqty_fld_temp = $set[0]['baseqty'];
                    $quantity_fld = $quantity_fld_temp = $set[0]['quantity'];
                    $conversion_value = $baseqty_fld / $quantity_fld;
                    
                    $quantity_fld_temp = $quantity_fld * $conversion_value;
                    for($row = 0;$row < $result_num_rows; $row++) {

                        $qty_in_stock_fld = $set[$row]['qty_in_stock'];
                        $total_qty_in_stock_fld += $qty_in_stock_fld;
                        if($baseqty_fld_temp <= $qty_in_stock_fld) {
                            $set[$row]['baseqty'] = $baseqty_fld_temp;
                            $set[$row]['quantity'] = $quantity_fld_temp;
                        } else {
                            $baseqty_fld_temp -= $qty_in_stock_fld;
                            $quantity_fld_temp -= $qty_in_stock_fld;
                            $set[$row]['baseqty'] = $qty_in_stock_fld;
                            $set[$row]['quantity'] = $qty_in_stock_fld;
                        }

                        $result_set[] = $set[$row];
                        if($baseqty_fld <= $total_qty_in_stock_fld) {
                            break;
                        }
                    }
                }

                $log->debug('Final_result: '.print_r($result_set, true));
                $log->debug('Num_rows: '.print_r(count($result_set), true));
                
            } else {
                $rsi_result = $adb->mquery("select * from vtiger_xsalesorderproductrel where id='".$so_focus->id."' and product_type = 'Main' order by sequence_no");
                $result_set = $adb->getResultSet($rsi_result);
                $log->debug('Final_result: '.print_r($result_set, true));
                $log->debug('Num_rows: '.print_r(count($result_set), true));
            }
            
            $net_total_val = 0.0;          
            if(count($result_set) > 0)
            {
                $reason_str = '';
                $zro_slect = 0;
                for ($index = 0; $index < count($result_set); $index++) 
                {
                    
                    $id = $result_set[$index]['id'];
                    $productid = $result_set[$index]['productid'];
                    $productcode = $result_set[$index]['productcode'];
                    $product_type = $result_set[$index]['product_type'];
                    
                    $quantity = $result_set[$index]['quantity'];
                    $baseqty = $result_set[$index]['baseqty'];
                    $dispatchqty = $result_set[$index]['dispatchqty'];
                    $tuom = $result_set[$index]['tuom'];
                    $listprice = $result_set[$index]['listprice'];
                    $discount_percent = $result_set[$index]['discount_percent'];
                    $discount_amount = $result_set[$index]['discount_amount'];
                    $sch_disc_amount = $result_set[$index]['sch_disc_amount'];
                    $comment = $result_set[$index]['comment'];
                    $description = $result_set[$index]['description'];
                    $incrementondel = $result_set[$index]['incrementondel'];
                    $tax1 = $result_set[$index]['tax1'];
                    $tax2 = $result_set[$index]['tax2'];
                    $tax3 = $result_set[$index]['tax3'];
                    $free_qty = $result_set[$index]['free_qty'];
                    $dam_qty = $result_set[$index]['dam_qty'];
                    $net_price = $result_set[$index]['net_price'];
                    $lineitem_id = $result_set[$index]['lineitem_id'];
                    $batchcode = $result_set[$index]['batchcode'];
                    $pkg = trim($result_set[$index]['pkg']);
                    $expiry = trim($result_set[$index]['expiry']);
                    $scheme_code = $result_set[$index]['scheme_code'];
                    $scheme_points = $result_set[$index]['points'];
                    
                    $pts = $result_set[$index]['pts'];
                    $ptrM = $result_set[$index]['ptr'];
                    $mrp = $result_set[$index]['mrp'];
                    $ecp = $result_set[$index]['ecp'];
                    
                    $seq_no = ($index+1);
                   
                    if($tax1 == null || $tax1 == "")
                        $tax1 == 0.00;

                    if($quantity == '' || $quantity == null)
                        $quantity = 0.00;
                    
                    $listprice = (($listprice != '') ? numberformat($listprice,6) : '0.000000');
                    $discount_percent = (($discount_percent != '') ? numberformat($discount_percent,6) : '0.000000');
                    if($discount_percent >= 100){
                        $product_type = 'Dist_Free';
                    }
                    $discount_amount = (($discount_amount != '') ? numberformat($discount_amount,6) : '0.000000');
                    if(isset($SO_PRO_CATE_BASED) && $SO_PRO_CATE_BASED == 'True' && $so_lbl_save_pro_cate == 'True') {
                       $proportionated_discount_amount = $result_set[$i-1]['proportionate_discount_amount'] * $quantity;
                       $discount_amount = formatCurrencyDecimals($proportionated_discount_amount);
                    }
                    
                    $sch_disc_amount = 0.000000;
                    $free_qty = (($free_qty != '') ? numberformat($free_qty,6) : '0.000000');
                    $dam_qty = (($dam_qty != '') ? numberformat($dam_qty,6) : '0.000000');
                    $net_price = (($net_price != '') ? numberformat($net_price,6) : '0.000000');
                    $tax1 = (($tax1 != '') ? numberformat($tax1,6) : '0.000000');
                    $tax2 = (($tax2 != '') ? numberformat($tax2,6) : '0.000000');
                    $tax3 = (($tax3 != '') ? numberformat($tax3,6) : '0.000000');
                    
                    $prod_serial_qry = $adb->pquery("SELECT if(track_serial_number='Yes', 1, 0) as track_serial FROM vtiger_xproduct where 
                        xproductid='$productid'");
                    $track_serial = $adb->query_result($prod_serial_qry,0,'track_serial');

                    $salable_qty_tot = 0;
                    $batch_str = '';
                    if($batchcode == '' || $batchcode == "-" || $batchcode == null)
                        $batch_str .= " and (batchnumber = '' or batchnumber = '--NoBatch--'  or batchnumber = '-') ";
                    else 
                        $batch_str .= " and batchnumber = '$batchcode' ";
                    if($pkg == '' || $pkg == "-" || $pkg == null)
                        $batch_str .= " and (pkg = '' or pkg = '-') ";
                    else 
                        $batch_str .= " and pkg = '$pkg' ";
                    if($expiry == '' || $expiry == "-" || $expiry == null)
                        $batch_str .= " and (expiry = '' or expiry = '-') ";
                    else 
                        $batch_str .= " and expiry = '$expiry' ";
                    
					
                    if($product_type == 'Main' || $product_type == 'Dist_Free'){
                        $checkqty = 'iq.salable_qty';
                    }else{
                        $checkqty = 'iq.free_qty';
                        $listprice = $net_price = 0;
                    }
                    
                    $so_stocklots = $adb->mquery("SELECT sum(iq.salable_qty) as salable_qty FROM (SELECT id, IFNULL(batchnumber,'') as `batchnumber`, pkg, expiry, IFNULL(SUM(salable_qty),0.0)-IFNULL(SUM(sold_salable_qty),0.0) as salable_qty, IFNULL(SUM(free_qty),0.0)-IFNULL(SUM(sold_free_qty),0.0) as free_qty, sum(damaged_qty) as damaged_qty, sum(damaged_free_qty) as damaged_free_qty, pts, ptr, mrp, ecp , ptr_type from vtiger_stocklots 
                                                  where productid='$productid' AND distributorcode='".$distArr['id']."'  AND location_id='".$godown_def_name['xgodownid']."'  group by distributorcode,productid,batchnumber,pkg,expiry,ptr,mrp,ecp ORDER BY batchnumber,expiry,pkg,ptr,ptr_type) as iq WHERE salable_qty > 0");
                    $available_qty=$adb->query_result($so_stocklots,0,'salable_qty');
                    
                    if($baseqty>$available_qty && $stock_bulk[0]['value']==1){
                        continue;
                    }
                    
                    if($ptrM!='')                    
                     $batch_str .=" and ptr=$ptrM and mrp=$mrp";
//			echo "SELECT iq.* FROM (SELECT id, IFNULL(batchnumber,'') as `batchnumber`, pkg, expiry, IFNULL(SUM(salable_qty),0.0)-IFNULL(SUM(sold_salable_qty),0.0) as salable_qty, IFNULL(SUM(free_qty),0.0)-IFNULL(SUM(sold_free_qty),0.0) as free_qty, sum(damaged_qty) as damaged_qty, sum(damaged_free_qty) as damaged_free_qty, pts, ptr, mrp, ecp , ptr_type from vtiger_stocklots 
//where productid='$productid' AND distributorcode='".$distArr['id']."'  AND location_id='".$godown_def_name['xgodownid']."' and ptr=$ptr  group by distributorcode,productid,batchnumber,pkg,expiry,ptr,mrp,ecp ORDER BY batchnumber,expiry,pkg,ptr,ptr_type) as iq WHERE $checkqty != 0 union SELECT iq.* FROM (SELECT id, IFNULL(batchnumber,'') as `batchnumber`, pkg, expiry, IFNULL(SUM(salable_qty),0.0)-IFNULL(SUM(sold_salable_qty),0.0) as salable_qty, IFNULL(SUM(free_qty),0.0)-IFNULL(SUM(sold_free_qty),0.0) as free_qty, sum(damaged_qty) as damaged_qty, sum(damaged_free_qty) as damaged_free_qty, pts, ptr, mrp, ecp , ptr_type from vtiger_stocklots 
//where productid='$productid' AND distributorcode='".$distArr['id']."'  AND location_id='".$godown_def_name['xgodownid']."' and ptr!=$ptr group by distributorcode,productid,batchnumber,pkg,expiry,ptr,mrp,ecp ORDER BY batchnumber,expiry,pkg,ptr,ptr_type) as iq WHERE $checkqty != 0 ";		
//                die;    

                        if(isset($SO_PRO_CATE_BASED) && $SO_PRO_CATE_BASED == 'True' && $so_lbl_save_pro_cate == 'True') {

                            if(isset($SO_PRO_LIST_OR_BY_PRICE) && $SO_PRO_LIST_OR_BY_PRICE == 'True' && isset($SO_PRO_LIST_OR_BY_STOCK) && $SO_PRO_LIST_OR_BY_STOCK == 'False') {
                                $batch_order_by_fld = 'mrp';
                            } else if(isset($SO_PRO_LIST_OR_BY_STOCK) && $SO_PRO_LIST_OR_BY_STOCK == 'True' && isset($SO_PRO_LIST_OR_BY_PRICE) && $SO_PRO_LIST_OR_BY_PRICE == 'True') {
                                $batch_order_by_fld = 'mrp, salable_qty';
                            } else {
                                $batch_order_by_fld = 'id';
                            }
                            
                            $batch_query = "SELECT iq.* FROM (SELECT id, IFNULL(batchnumber,'') as `batchnumber`, pkg, expiry,
                                            IFNULL(salable_qty,0.0)-IFNULL(sold_salable_qty,0.0) as salable_qty, IFNULL(free_qty,0.0)-IFNULL(sold_free_qty,0.0) as free_qty, 
                                            damaged_qty as damaged_qty, damaged_free_qty as damaged_free_qty, pts, ptr, mrp, ecp from vtiger_stocklots 
                                            where productid='".$productid."' AND id = '".$result_set[$index]['batch_id']."'
                                            ORDER BY {$batch_order_by_fld}) as iq WHERE iq.salable_qty > 0.0";
                            
                            if(isset($SO_PRO_LIST_OR_BY_STOCK) && $SO_PRO_LIST_OR_BY_STOCK == 'True' && isset($SO_PRO_LIST_OR_BY_PRICE) && $SO_PRO_LIST_OR_BY_PRICE == 'False') {
                                $batch_query = "SELECT iq.* FROM (SELECT id, IFNULL(batchnumber,'') as `batchnumber`, pkg, expiry,
                                                IFNULL(salable_qty,0.0)-IFNULL(sold_salable_qty,0.0) as salable_qty, IFNULL(free_qty,0.0)-IFNULL(sold_free_qty,0.0) as free_qty, 
                                                damaged_qty as damaged_qty, damaged_free_qty as damaged_free_qty, pts, ptr, mrp, ecp from vtiger_stocklots 
                                                where productid='".$productid."' AND distributorcode='".$dist['id']."' AND location_id='".$si_location."'
                                                ORDER BY salable_qty) as iq WHERE iq.salable_qty > 0.0";
                            }
                            $batch_dtl_qry  = $adb->mquery($batch_query);
                            
                        } else {
                    
                            $batch_dtl_qry  = $adb->mquery("SELECT iq.* FROM (SELECT id, IFNULL(batchnumber,'') as `batchnumber`, pkg, expiry, IFNULL(SUM(salable_qty),0.0)-IFNULL(SUM(sold_salable_qty),0.0) as salable_qty, IFNULL(SUM(free_qty),0.0)-IFNULL(SUM(sold_free_qty),0.0) as free_qty, sum(damaged_qty) as damaged_qty, sum(damaged_free_qty) as damaged_free_qty, pts, ptr, mrp, ecp , ptr_type from vtiger_stocklots 
                                                where productid='$productid' AND distributorcode='".$distArr['id']."'  AND location_id='".$godown_def_name['xgodownid']."' and ptr=$ptrM  group by distributorcode,productid,batchnumber,pkg,expiry,pts,ptr,mrp,ecp ORDER BY batchnumber,expiry,pkg,ptr,ptr_type) as iq WHERE $checkqty > 0 union SELECT iq.* FROM (SELECT id, IFNULL(batchnumber,'') as `batchnumber`, pkg, expiry, IFNULL(SUM(salable_qty),0.0)-IFNULL(SUM(sold_salable_qty),0.0) as salable_qty, IFNULL(SUM(free_qty),0.0)-IFNULL(SUM(sold_free_qty),0.0) as free_qty, sum(damaged_qty) as damaged_qty, sum(damaged_free_qty) as damaged_free_qty, pts, ptr, mrp, ecp , ptr_type from vtiger_stocklots 
                                                where productid='$productid' AND distributorcode='".$distArr['id']."'  AND location_id='".$godown_def_name['xgodownid']."' and ptr!=$ptrM group by distributorcode,productid,batchnumber,pkg,expiry,pts,ptr,mrp,ecp ORDER BY batchnumber,expiry,pkg,ptr,ptr_type) as iq WHERE $checkqty > 0 ");
                        }                    
                    //echo '<pre>';print_r($batch_dtl_qry);die;
                    $log->debug('Batch_num_rows: '.print_r($adb->num_rows($batch_dtl_qry), true));                         
                    $log->debug('Batch_result: '.print_r($batch_dtl_qry, true));
                    
                    if(!($adb->num_rows($batch_dtl_qry) > 0))
                    { 
                        $prod_dtl_qry = $adb->pquery("SELECT productname FROM vtiger_xproduct where xproductid=$productid");
                        $prod_name = $adb->query_result($prod_dtl_qry,0,'productname');
                        $reason_str .= "Stock not enough for this Product $prod_name and $batchcode\n";
                        //$status_flag = false;
                    }//echo '<pre>';print_r($batch_dtl_qry);
                    $quantity = (($quantity != '') ? numberformat($quantity,6) : '0.000000');
                    $baseqty = (($baseqty != '') ? numberformat($baseqty,6) : '0.000000');
                    
                    for($j=0; $j<$adb->num_rows($batch_dtl_qry); $j++)
                    {
                        $salable_qty = $adb->query_result($batch_dtl_qry,$j,'salable_qty');
                        $free_qty = $adb->query_result($batch_dtl_qry,$j,'free_qty');
                        $pts = $adb->query_result($batch_dtl_qry,$j,'pts');
                        $ptr = $adb->query_result($batch_dtl_qry,$j,'ptr');
                        $mrp = $adb->query_result($batch_dtl_qry,$j,'mrp');
                        $ecp = $adb->query_result($batch_dtl_qry,$j,'ecp');
                        $pkg = $adb->query_result($batch_dtl_qry,$j,'pkg');
                        $expiry = $adb->query_result($batch_dtl_qry,$j,'expiry');
                        $batchnumber = $adb->query_result($batch_dtl_qry,$j,'batchnumber');
                        
                        $salable_qty = (($salable_qty != '') ? numberformat($salable_qty,6) : '0.000000');
                        $free_qty = (($free_qty != '') ? numberformat($free_qty,6) : '0.000000');
                        $pts = (($pts != '') ? numberformat($pts,6) : '0.000000');
                        $ptr = (($ptr != '') ? numberformat($ptr,6) : '0.000000');
                        $mrp = (($mrp != '') ? numberformat($mrp,6) : '0.000000');
                        $ecp = (($ecp != '') ? numberformat($ecp,6) : '0.000000');

                
                        //If product category is enabled & to order by stock
                        if(isset($SO_PRO_CATE_BASED) && $SO_PRO_CATE_BASED == 'True' && $so_lbl_save_pro_cate == 'True' && isset($SO_PRO_LIST_OR_BY_STOCK) && $SO_PRO_LIST_OR_BY_STOCK == 'True' && isset($SO_PRO_LIST_OR_BY_PRICE) && $SO_PRO_LIST_OR_BY_PRICE == 'False') {
                            $listprice = $ptr;
                        }
                        
                        if($pkg == '' || $pkg == NULL)
                            $pkg = '-';
                        if($expiry == '' || $expiry == NULL)
                            $expiry = '-';
                        if($batchnumber == '' || $batchnumber == NULL)
                            $batchnumber = '-';
                        $current_uom=$result_set[$index]['baseqty']/$result_set[$index]['quantity'];
                        $conversionVal=$current_uom;
                        $orgConv=$conversionVal;
                        /* Insert Line Items */
                        $salable_qtyTC=$salable_qty;
                        if($baseqty > $salable_qtyTC){
                            
                         
                            $decimalStockInBaseUOM=1;                            
                            $mainQty=$salable_qty;
                            $qtyForCheck=$salable_qty/$conversionVal;
                            if($conversionVal>1)
                            {
                                $decCheck=$qtyForCheck-floor($qtyForCheck);
                                if($decCheck>0.0)
                                  $decimalStockInBaseUOM=2;  
                            }                                                 


                            for($testM=0;$testM<$decimalStockInBaseUOM;$testM++)
                            { 
                                /*
                                *  For Handling Decimal Stock
                                */
								
                               if($conversionVal>1)
                                  $qty=floor($qtyForCheck);
                               else
                                  $qty=$qtyForCheck; 
                              
                                if($qty==0 && $testM==0)
                                    continue;	
							  
                               $tUomToLI=$tuom;
                                
                               if($testM==1)
                               {
                                   $qty=$mainQty-(floor($qtyForCheck)*$orgConv);
                                   $conversionVal=1;//$current_uom=1;
                                   $bUOMRes=$adb->pquery("SELECT cf_xproduct_base_uom as `baseuom` FROM vtiger_xproductcf where xproductid=?",array($productid));
                                   $tUOM1=0;
                                   if($adb->num_rows($bUOMRes)>0)
                                       $tUOM1=$adb->query_result($bUOMRes,0,'baseuom');
                                   $tUomToLI=$tUOM1;
                                   //$tUOMpdf=$tUOM1;
                               } 
                               
                               $listpriceTouse=$listprice;
                               
                               $salable_qty=$qty*$conversionVal;
                               
                               if($SI_SOTOSI_PRICE_EDITABLE=='False'){
                                   $listpriceTouse=$ptr*$current_uom;                                   
                               }
                               
                               if($testM==1)
                                        $listpriceTouse=ROUND($listprice/$orgConv,6);


                                $current_qty=$salable_qty/$conversionVal; $net_price=$listpriceTouse*$current_qty;
                                $insert_qry = $adb->pquery("insert into vtiger_siproductrel (id, productid, productcode, product_type, sequence_no, quantity, 
                                baseqty, dispatchqty, refid, reflineid, reftrantype, tuom, listprice, discount_percent, discount_amount, sch_disc_amount, comment, 
                                description, incrementondel, tax1, tax2, tax3, free_qty, dam_qty, net_price) values ('$return_id', '$productid', '$productcode', '$product_type', '$seq_no', '$current_qty', 
                                '$salable_qty', '$dispatchqty', '$id', '$lineitem_id', 'xSalesOrder', '$tUomToLI', '$listpriceTouse', '$discount_percent', '$discount_amount', 
                                '$sch_disc_amount', '$comment', '$description', '$incrementondel', '$tax1', '$tax2', '$tax3', '$free_qty', '$dam_qty', '$net_price')");
                                $lineitem_id_val = $adb->getLastInsertID();
                                $si_sqty = 0;
                                $si_sfqty = 0;
                                if($product_type == 'Main' || $product_type == 'Dist_Free')
                                {
                                    $si_sqty = numberformat($salable_qty,6);
                                }
                                else 
                                {
                                    $si_sfqty = numberformat($salable_qty,6);
                                }

                                /* Insert date in vtiger_xsalestransaction_batchinfo table (Batch Detail) */

                                $query ="insert into vtiger_xsalestransaction_batchinfo(transaction_id, trans_line_id, product_id, batch_no, pkd, expiry, transaction_type,sqty,sfqty, ptr, pts, mrp, ecp,ptr_type, distributor_id,track_serial) values(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                                $qparams = array($focus->id,$lineitem_id_val,$productid,"$batchnumber","$pkg","$expiry","SI","$si_sqty","$si_sfqty","$ptr","$pts","$mrp","$ecp",'',$distArr['id'], '$track_serial');
                                $adb->pquery($query,$qparams);

                                $price = (($price != '') ? numberformat($price,6) : '0.000000');
                                $disc_every = (($disc_every != '') ? numberformat($disc_every,6) : '0.000000');

                                

                            }
                            
                        $baseqty=$baseqty-$salable_qtyTC;
                        $quantity1=$quantity;
                        $quantity=$quantity-$current_qty;
                        $quantity1=$quantity1-$quantity;
                        }
                        else
                        {
//                            if($SI_SOTOSI_PRICE_EDITABLE=='False')
//                                 $listprice=$ptr*$orgConv;
                            
                            $quantity1=$baseqty/$orgConv;
                            $flags=TRUE; $net_price=$listprice*$quantity;
                            
                            $decimalStockInBaseUOM=1;                            
                            $mainQty=$baseqty;
                            $qtyForCheck=$quantity1;
                            $conversionVal=$orgConv;
                            if($conversionVal>1)
                            {
                                $decCheck=$qtyForCheck-floor($qtyForCheck);
                                if($decCheck>0.0)
                                  $decimalStockInBaseUOM=2;  
                            }                                                 


                            for($testM=0;$testM<$decimalStockInBaseUOM;$testM++)
                            {
                            
                               if($conversionVal>1)
                               { 
                                    $qty=floor($qtyForCheck);
                               } 
                               else
                                  $qty=$qtyForCheck;
                               
                               if($qty==0 && $testM==0)
                                    continue;
                                
								$tUomToLI=$tuom;
								
                               if($testM==1)
                               {
                                   $qty=$mainQty-(floor($qtyForCheck)*$orgConv);
                                   $conversionVal=1;//$current_uom=1;
                                   $bUOMRes=$adb->pquery("SELECT cf_xproduct_base_uom as `baseuom` FROM vtiger_xproductcf where xproductid=?",array($productid));
                                   $tUOM1=0;
                                   if($adb->num_rows($bUOMRes)>0)
                                       $tUOM1=$adb->query_result($bUOMRes,0,'baseuom');
                                   $tUomToLI=$tUOM1;
                                   //$tUOMpdf=$tUOM1;
                               } 
                               
                               $listpriceTouse=$listprice;
                               
                               $salable_qty=$qty*$conversionVal;
                               
                               if($SI_SOTOSI_PRICE_EDITABLE=='False'){
                                   $listpriceTouse=$ptr*$current_uom;
                                   
                               }
                               
                               if($testM==1)
                                        $listpriceTouse=ROUND($listprice/$orgConv,6);
                                
                                
                            $current_qty=$salable_qty/$conversionVal; $net_price=$listpriceTouse*$current_qty;
                            $insert_qry = $adb->pquery("insert into vtiger_siproductrel (id, productid, productcode, product_type, sequence_no, quantity, 
                            baseqty, dispatchqty, refid, reflineid, reftrantype, tuom, listprice, discount_percent, discount_amount, sch_disc_amount, comment, 
                            description, incrementondel, tax1, tax2, tax3, free_qty, dam_qty, net_price) values ('$return_id', '$productid', '$productcode', '$product_type', '$seq_no', '$current_qty', 
                            '$salable_qty', '$dispatchqty', '$id', '$lineitem_id', 'xSalesOrder', '$tUomToLI', '$listpriceTouse', '$discount_percent', '$discount_amount', 
                            '$sch_disc_amount', '$comment', '$description', '$incrementondel', '$tax1', '$tax2', '$tax3', '$free_qty', '$dam_qty', '$net_price')");
                            $lineitem_id_val = $adb->getLastInsertID();
                            $si_sqty = 0;
                            $si_sfqty = 0;
                            if($product_type == 'Main' || $product_type=='Dist_Free')
                            {
                                $si_sqty = numberformat($salable_qty,6);
                            }
                            else 
                            {
                                $si_sfqty = numberformat($salable_qty,6);
                            }

                            /* Insert date in vtiger_xsalestransaction_batchinfo table (Batch Detail) */

                            $query ="insert into vtiger_xsalestransaction_batchinfo(transaction_id, trans_line_id, product_id, batch_no, pkd, expiry, transaction_type,sqty,sfqty, ptr, pts, mrp, ecp,ptr_type, distributor_id,track_serial) values(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                            $qparams = array($focus->id,$lineitem_id_val,$productid,"$batchnumber","$pkg","$expiry","SI","$si_sqty","$si_sfqty","$ptr","$pts","$mrp","$ecp",'',$distArr['id'], '$track_serial');
                            $adb->pquery($query,$qparams);

                            $price = (($price != '') ? numberformat($price,6) : '0.000000');
                            $disc_every = (($disc_every != '') ? numberformat($disc_every,6) : '0.000000');
                            
                            }

                        }
						
						
                        //$taxes_for_product = getTaxForRSI($id, $focus->column_fields['cf_xsalesorder_seller_id'], $productid, $lineitem_id, $focus->column_fields['cf_salesinvoice_buyer_id'],'xSalesOrder');
//                        $taxes_for_product = getTaxDetailsForProduct($productid,'all','SalesInvoice',$focus->column_fields['cf_xsalesorder_seller_id'],$focus->column_fields['cf_salesinvoice_buyer_id']);
 //                       $taxes_for_product = getTaxDetailsForProduct($productid,'all','SalesInvoice',$distArr['id'],$focus->column_fields['vendor_id'],'','','');
//                        echo '<pre>';print_r($taxes_for_product);die;
//                        $taxPerToApply=0.0;
//                        $tax_total = 0.0;
//                        for($tax_count=0;$tax_count<count($taxes_for_product);$tax_count++)
//                        {
//                                $tax_name = $taxes_for_product[$tax_count]['taxname'];
//                                $tax_label = $taxes_for_product[$tax_count]['taxlabel'];
//                                $request_tax_name = $taxes_for_product[$tax_count]['percentage'];
//                                $percentage_display = $taxes_for_product[$tax_count]['percentage_display'];
//                                $taxAmt = $taxes_for_product[$tax_count]['taxAmt'];
//                                $type = $taxes_for_product[$tax_count]['taxType'];
//                                
//                                $tax_value=$request_tax_name;
//                                $tax_value = (($tax_value != '') ? numberformat($tax_value,6) : '0.000000');
//                                
//                                $prodTotal = $listprice*$quantity1;                               
//                                if($sch_disc_amount > 0 && $prodTotal >= $sch_disc_amount)
//                                $prodTotal = $prodTotal - $sch_disc_amount;
//                    
//                                if($discount_amount > 0 && $prodTotal >= $discount_amount)
//                                    $prodTotal = $prodTotal - $discount_amount;
//                                
//                                if($discount_percent > 0 && $prodTotal >= $discount_percent)
//                                   $prodTotal = $prodTotal - (($prodTotal)*$discount_percent/100);
//                                
//                                if($discount_percent > 0 && $discount_percent >= 100)
//                                   $prodTotal = $prodTotal - (($prodTotal)*$discount_percent/100);
//                                
//                                if($type != ''){
//                                    $taxAmt = (($prodTotal)*$tax_value/100);
//                                }
//                               
////                                if($taxes_for_product[$tax_count]['percentage'] == $taxes_for_product[$tax_count]['percentage_display']){
////                                    $tax_amount = (($listprice*$quantity)*$tax_value/100);
////                                }else{
////                                    $tax_amount = ($taxes_for_product[0]['percentage']*$tax_value/100);    
////                                }
//                                $tax_total += $taxAmt;
//                                
//                                if($percentage_display != $request_tax_name){
//                                   $tax_value = (($percentage_display != '') ? numberformat($percentage_display,6) : '0.000000');
//                                }
//                                $createQuery="INSERT INTO sify_xtransaction_tax_rel (`transaction_id`,`lineitem_id`,`transaction_name`,`tax_type`,`tax_label`,`tax_percentage`,`transaction_line_id`,`tax_amt`) VALUES(?,?,?,?,?,?,?,?)";   
//                                $adb->pquery($createQuery,array($focus->id,$productid,'SalesInvoice',$tax_name,$tax_label,$tax_value,$lineitem_id_val,$taxAmt));
//                        }
//				
//                       if($tax_total > 0){
//                                //$net_price_updated = $net_price+$tax_total;
//                                $adb->pquery("UPDATE vtiger_siproductrel set tax1=? where lineitem_id=?",array($tax_total,$lineitem_id_val));
//                        }
//                        $net_total = 0;
//                    if(($listprice > 0) && $quantity > 0)
//                        $net_total = $prodTotal;
//                    
//                    if($sch_disc_amount > 0 && $net_total >= $sch_disc_amount)
//                        $net_total = $net_total - $sch_disc_amount;
//                    
//                    if($discount_amount > 0 && $net_total >= $discount_amount)
//                        $net_total = $net_total - $discount_amount;
//                    
//                    if($discount_percent > 0 && $net_total >= $discount_percent && $discount_percent < 100){
//                        $t = $net_total * ($discount_percent/100);
//                        $net_total = $net_total - $t;
//                    }elseif($discount_percent > 0 && $discount_percent >= 100){
//                        $t = $net_total * ($discount_percent/100);
//                        $net_total = $net_total - $t;
//                    }
//                    
//                    if($tax_total > 0 && $net_total >= $tax_total){
//                        
//                        $net_total = $net_total + $tax_total;
//                    }
                    
                     if($flags==TRUE){
                         break;
                     }   
                     
                     //$net_total_val += $net_total;
                        
                    }
                    
                    
                    
                    
                    
                  
                    //$a = array($net_total,$quantity,$listprice,$sch_disc_amount,$discount_amount,$discount_percent,$tax_total);
//                    echo '<pre>';
//                    print_r($a);
                    
                    /*
                    if($listprice > 0 && $tax_total > 0 && $quantity > 0)
                        $net_total = ($quantity*$listprice) + (($quantity*$listprice)*($tax_total/100));
                    elseif(($listprice > 0) && $quantity > 0)
                        $net_total = $quantity*$listprice;
                    */
                    
                    
//                    echo $net_total.'--'.$quantity.'--'.$listprice.'--'.$tax_total.'--'.$discount_amount;
//                    echo '<br/>';
                }
              
                $AUTO_SCHEME_TRANSACTION = getDistrInvConfig('AUTO_SCHEME_TRANSACTION', $distArr['id']);
                if($AUTO_SCHEME_TRANSACTION[0]['value'] ==1){
                 $_SESSION['schemebatches']=array();
                 $output=array();
                 $shipping_address_pick = $so_focus->column_fields['cf_xsalesorder_shipping_address_pick'];
                 $trans_date	= $focus->column_fields['cf_salesinvoice_sales_invoice_date'];
                 $output=applyschemes($focus->id,$value,$godown_def_name['xgodownid'],$so_focus->column_fields['cf_xsalesorder_buyer_id'],$shipping_address_pick,$trans_date);
                 echo '<pre>';print_r($output);
                 
                 $seq_no=$index;
                 $invSchemeDesc = array();
                 $invSchemeDiscount ='';
                 $invSchemeApplied =0;
				 $invSchemePoints =0;
                 for($ij=0;$ij<count($output)-1;$ij++){
                    
                 if($output[$ij]['userselection']=='1')
                 {
                     continue;
                 }
                 
                 if($output[$ij]['inlevel']=='TRUE')
                 {
                     // $invSchemeDesc[] = $output[$ij]['dTitle'].'##'.$output[$ij]['value'];
					 // if ($output[$ij]['type']!='Points') {
						// $invSchemeDiscount += numberformat($output[$ij]['value'],6);
					 // } else {
						 // $invSchemePoints += $output[$ij]['value'];
					 // }
                     // $invSchemeApplied=1;
                     //continue;
                 }
                 
                 
                     
                 if($output[$ij]['type']=='Free') {
                     for($kate=0;$kate<count($output[$ij]['schemes']);$kate++)
                     {
                     $resultset=$output[$ij]['schemes'][$kate];
                     $productid=$resultset['freeProdId'];
                     if($resultset['line_type']=="Main_Free")                     
                     $product_type='Scheme_Salable';
                     else
                     $product_type='Scheme';
                     $seq_no=$seq_no+$kate+1;
                     $quantity =$resultset['freeBaseQty'];  
                     $quantityvalue =$resultset['freeQty'];
                     $batchnumber=$resultset['batchnumber'];
                     $pkg=$resultset['pkg'];
                     $expiry=$resultset['expiry'];
                     $tuom=$resultset['uomid'];
                     
                     $lineitem_id_val = $adb->getLastInsertID()+1;
                     $insert_qry = $adb->pquery("insert into vtiger_siproductrel (id, productid, product_type, sequence_no, quantity, 
                     baseqty, refid, reflineid, reftrantype, tuom) values ('$focus->id', '$productid', '$product_type', '$seq_no', '$quantityvalue', 
                     '$quantity', '$so_focus->id', '$lineitem_id_val', 'xSalesOrder', '$tuom')");
                     $lineitem_id_val = $adb->getLastInsertID();
                        
                     $query ="insert into vtiger_xsalestransaction_batchinfo(transaction_id, trans_line_id, product_id, batch_no, pkd, expiry, transaction_type,sfqty, ptr, pts, mrp, ecp, distributor_id,track_serial) values(?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                     $qparams = array($focus->id,$lineitem_id_val,$productid,"$batchnumber","$pkg","$expiry","SI",$quantity,$resultset['ptr'],$resultset['pts'],$resultset['mrp'],$resultset['ecp'],$distArr['id'],$track_serial);
                     $adb->pquery($query,$qparams);
                     
                     $queryFreeSch="INSERT INTO `vtiger_itemwise_scheme` (`transaction_id`, `lineItemId`, `scheme_applied`, `scheme_name`, `scheme_id`, `value`,pricevalue,lineitem_id,disc_qty,disc_every,disc_amount) VALUES (?, ?, ?, ?, ?, ?,?,?,?,?,?)";
                     $paraArray = array($focus->id,$productid, $output[$ij]['type'], $resultset['scheme_name'], $resultset['scheme_id'], $resultset['freeBaseQty'], $resultset['ptr'],$lineitem_id_val ,$resultset['freeBaseQty'],0 , $resultset['ptr']);  
                        $adb->pquery($queryFreeSch,$paraArray);
                     }
                 }   
                  elseif($output[$ij]['type']=='Discount' || $output[$ij]['type']=='Points') {    
                    $resultset=$output[$ij];
                    
                    if($resultset['value']<=0.0)
                        continue;
                    
                    $totSBQty=0.0;$totSBAmt=0.0;
                    
                    print_r($resultset['allRows']);
                    
                    foreach($resultset['allRows'] as $proId => $proObj) {
                        print_r($proId);
                        print_r($proObj);
                        
                        print_r($proObj["saBQTY"]);
                        
                    $totSBQty+=$proObj["saBQTY"];
                    $totSBAmt+=$proObj["saBAMT"];
                    }
                    
                    echo $totSBQty;
                    echo "::".$totSBAmt;
                    
					$schemableBy="GA";
					
					if(isset($resultset['schmeableBy']))
					{
						$schemableBy=$resultset['schmeableBy'];
					}	
					
					echo "..SBY".$schemableBy;
					
                    foreach($resultset['allRows'] as $rId => $proObj) {
                        
                        if($proObj["saBQTY"]<=0.0 && $proObj["saBAMT"]<=0.0)
                            continue;
                        
						$proId=$proObj["id"];
						
                        $allowedPTRList=array();
                        $allowedMRPList=array();
                        $rowsInList = array();
                        
                        $proItrObj;
                        
                         if(isset($proObj["ptr_mrp"]))
                         {
                             $ptrString="";
                             $mrpString="";
                             
                             foreach($proObj["ptr_mrp"] as $xKey => $xObj)
                             {
                                 $ptrMrpKeyVal=split(":::", $xObj);
                             }
                             
                             $proItrObj=$adb->mquery("SELECT * FROM vtiger_siproductrel WHERE id=? AND productid=?",array($focus->id,$proId));
                         }
                         else if($schemableBy=='MRP')
                         {
                             $proItrObj=$adb->mquery("SELECT vtiger_siproductrel.*,vtiger_xsalestransaction_batchinfo.mrp FROM vtiger_siproductrel 
                                                        INNER JOIN vtiger_xsalestransaction_batchinfo ON transaction_id=vtiger_siproductrel.id AND trans_line_id=vtiger_siproductrel.lineitem_id AND product_id=vtiger_siproductrel.productid
                                                        WHERE vtiger_siproductrel.id=? AND vtiger_siproductrel.productid=?",array($focus->id,$proId));
                         }
                         else
                         {
                             $proItrObj=$adb->mquery("SELECT * FROM vtiger_siproductrel WHERE id=? AND productid=?",array($focus->id,$proId));
                         }    
                        
                         $prodConrtributon=0.0;
                             
                         if ($resultset['scheme_type'] == 'Item By value')
                         {
                            $prodConrtributon=($totSBAmt/$proObj["saBAMT"])*$resultset['value'];
                         }   
                         else
                         {
                            $prodConrtributon=($totSBQty/$proObj["saBQTY"])*$resultset['value'];
                         }   
                         
                         echo "PC:".$prodConrtributon;
                         
                         $totalAppUom=$proObj["soldInAppUOM"];
                         $totaBaseUom=$proObj["saBQTY"];
                         
                         for($t=0;$t<$adb->num_rows($proItrObj);$t++)
                         {
                             $proType=$adb->query_result($proItrObj,$t,'product_type');
                             $lineitem_id=$adb->query_result($proItrObj,$t,'lineitem_id');
                             $quantity=$adb->query_result($proItrObj,$t,'quantity');
                             $listprice=$adb->query_result($proItrObj,$t,'listprice');
                             $sch_disc_amount=$adb->query_result($proItrObj,$t,'sch_disc_amount');
                             $discount_amount=$adb->query_result($proItrObj,$t,'discount_amount');
                             $discount_percent=$adb->query_result($proItrObj,$t,'discount_percent');
							 if($schemableBy=='MRP'){
								 $mrp=$adb->query_result($proItrObj,$t,'mrp');
							 }
                             
							 $schTitle=$resultset['dTitle'];
							 
                             if($proType!='Main')
                                 continue;                                                          
                             
                             $thisLineContri=0.0;
                             
                            if($resultset['dType'] == 'Amount' || $resultset['dType']=='points')
                            {
                                    if($resultset['apportion_by']!='Amount')
                                    {
                                            echo "Inside Non Amount APP";
                                            print_r($proObj["lines"]);
                                            if($proObj["lines"]!=undefined && count($proObj["lines"]) > 0)
                                            {	 
                                                    $lineObj=$proObj["lines"][$lineitem_id];
                                                    echo "Lines";
                                                    if($lineObj!=undefined && count($lineObj) > 0){
                                                            if($resultset['apportion_by']=='Base UOM'){
                                                                   echo "BUOM";
                                                                   $thisLineContri=($lineObj["soldInAppUOM"]/$totaBaseUom)*$prodConrtributon;
                                                            }else{
                                                                   echo "".$resultset['apportion_by']; 
                                                                   $thisLineContri=($lineObj["soldInAppUOM"]/$totalAppUom)*$prodConrtributon;
                                                            }	
                                                    }
                                            }
                                            else
                                            {
                                                   if($resultset['apportion_by']=='Base UOM')
                                                           $thisLineContri=($proObj["saBQTY"]/$totaBaseUom)*$prodConrtributon;
                                                   else
                                                           $thisLineContri=($proObj["soldInAppUOM"]/$totalAppUom)*$prodConrtributon;								 
                                            }
                                    }
                                    else
                                    {
                                            echo "Inside Amount App";
                                            $thisLineRate=$quantity*$listprice;
                                            echo "<br/>".$thisLineRate;
                                            $thisLineContri=($thisLineRate/$proObj["saBAMT"])*$prodConrtributon;
                                    }
                             }
                            else
                            {
                                   $thisLineContri=$resultset['value'];	
                            }
							 
                             echo "<br/>TLC".$t."::".$thisLineContri;
                             $thisSchDisc=$thisLineContri;
							 
                             if($thisLineContri>0.0)
                             {
                                 
                                    $grossAmount=$quantity*$listprice;
                                    $taxableAmount=$grossAmount;
                                    
                                 if($sch_disc_amount=='')
                                         $sch_disc_amount=0.0;
                                 
                                 if ($resultset['dType'] == 'Amount' || $resultset['dType']=='points'){                                     
                                    $thisSchDisc=$thisLineContri;
									if ($resultset['dType']!='points') {
										$sch_disc_amount=$sch_disc_amount+$thisLineContri;
									}
                                 }
                                 else
                                 {
                                    $amtToCalPerc=$grossAmount;

                                    if($schemableBy=="AMD" || $schemableBy=="AMDWT")
                                    {
                                           $manDisc=0.0;
                                           if($discount_amount > 0.0)
                                           {
                                                   echo "MD1";
                                                   $manDisc=$discount_amount;
                                           }
                                           if($discount_percent > 0.0)
                                           {
                                                   echo "MD2";
                                                   $manDisc=$grossAmount*$discount_percent/100;
                                           }	
                                           echo "::ManD:".$manDisc;
                                           $amtToCalPerc=$grossAmount-$manDisc; 	

                                           if($schemableBy=="AMDWT")
                                           {
                                                   $taxPerc=0.0;
                                                   $shipping_address_pick = $so_focus->column_fields['cf_xsalesorder_shipping_address_pick'];
                                                   $trans_date	= $focus->column_fields['cf_salesinvoice_sales_invoice_date'];
                                                   $taxPerc=getTaxPercentageForSKU($proId,$distArr['id'],$focus->column_fields['vendor_id'],$shipping_address_pick,$si_location,$trans_date);
//											$tQuery=$adb->mquery("select SUM(tax_percentage) as `perc` from sify_xtransaction_tax_rel WHERE transaction_line_id=? AND transaction_id=?",array($lineitem_id,$focus->id));
//											if($adb->num_rows($tQuery)>0)
//												$taxPerc=$adb->query_result($tQuery,0,'perc');

                                                   $taxValue=$amtToCalPerc*$taxPerc/100;
                                                   $amtToCalPerc=$amtToCalPerc+$taxValue;
                                           }											
                                    }
                                    else if($schemableBy=="GAWT")
                                    {
                                                   $taxPerc=0.0;
                                                   $shipping_address_pick = $so_focus->column_fields['cf_xsalesorder_shipping_address_pick'];
                                                   $trans_date	= $focus->column_fields['cf_salesinvoice_sales_invoice_date'];
                                                   $taxPerc=getTaxPercentageForSKU($proId,$distArr['id'],$focus->column_fields['vendor_id'],$shipping_address_pick,$si_location,$trans_date);
//											$tQuery=$adb->mquery("select SUM(tax_percentage) as `perc` from sify_xtransaction_tax_rel WHERE transaction_line_id=? AND transaction_id=?",array($lineitem_id,$focus->id));
//											if($adb->num_rows($tQuery)>0)
//												$taxPerc=$adb->query_result($tQuery,0,'perc');

                                                   $taxValue=$grossAmount*$taxPerc/100;
                                                   $amtToCalPerc=$grossAmount+$taxValue;
                                    }
                                    else if($schemableBy=="MRP")
                                    {
                                 		$amtToCalPerc = $quantity*$mrp;
                                    }                                    
                                    else
                                     $amtToCalPerc=$grossAmount; 	 


                                    $thisSchDisc=($amtToCalPerc*$resultset['value']/100);
                                    $sch_disc_amount=$sch_disc_amount+$thisSchDisc;
                                    $schTitle=$resultset['value']." ".$resultset['dTitle'];
                                 }   
                                                                  
                                 $taxableAmount=$taxableAmount-$sch_disc_amount;
                                    
                                if($discount_amount>0.0)
                                {
                                    $taxableAmount=$taxableAmount-$discount_amount;
                                }    

                                if($discount_percent > 0.0)
                                {
                                    $discAmount=$taxableAmount*$discount_percent/100;
                                    $taxableAmount=$taxableAmount-$discAmount;
                                }
                                    
                                echo "<br/>GRS:".$grossAmount;
                                echo "<br/>SCH:".$sch_disc_amount;
                                echo "<br/>TAXable:".$taxableAmount;
                                
                                 $adb->pquery('update vtiger_siproductrel set sch_disc_amount= '.$sch_disc_amount.' where lineitem_id='.$lineitem_id.' and id='.$focus->id);
                                 $adb->pquery('update sify_xtransaction_tax_rel set tax_amt=(('.$taxableAmount.' * tax_percentage)/100) where transaction_line_id='.$lineitem_id.' and transaction_id='.$focus->id);
                                 $adb->pquery('update sify_xtransaction_tax_rel_si set tax_amt=(('.$taxableAmount.' * tax_percentage)/100) where transaction_line_id='.$lineitem_id.' and transaction_id='.$focus->id);
                                 
                                 $queryFreeSch="INSERT INTO `vtiger_itemwise_scheme` (`transaction_id`, `lineItemId`, `scheme_applied`, `scheme_name`, `scheme_id`, `value`,lineitem_id,disc_qty,disc_every,disc_amount) VALUES (?, ?, ?, ?, ?, ?,?,?,?,?)";
                                 
                                if($resultset['dType']=='points')
                                {                                
                                    $paraArray = array($focus->id,$proId,strtolower($resultset['dType']), $resultset['scheme_name'], $resultset['scheme_id'],0.0,$lineitem_id ,$thisSchDisc,$schTitle, 0.0);                                                                         
                                }
                                else
                                {
                                    $paraArray = array($focus->id,$proId,strtolower($resultset['dType']), $resultset['scheme_name'], $resultset['scheme_id'],$thisSchDisc,$lineitem_id ,'',$schTitle, $thisSchDisc);    
                                }   
                                 
                                $adb->pquery($queryFreeSch,$paraArray);
                             }
                         }
                    }
                    
                    //exit;
                    
                    
//                    $productids=array_keys($resultset['allRows']);
//                    $productid=$productids[0];
//                    $product_type=  strtolower($resultset['dType']);
//                    $totalqty=$resultset['allRows'][$productid]['soldBaseUOM'];
//                    
//                    $select_value=$adb->mquery("SELECT net_price,baseqty,lineitem_id FROM vtiger_siproductrel where id=".$focus->id." and productid=".$productid);
//                    for($k=0;$k<$adb->num_rows($select_value);$k++){
//                     $lineitem_id= $adb->query_result($select_value, $k,'lineitem_id'); 
//                     $schemeamount=($resultset['value']/$totalqty) * $adb->query_result($select_value, $k,'baseqty');
//                     if($resultset['dType']=='Percentage'){
//                     $schemeamount=($adb->query_result($select_value, $k,'net_price') * $resultset['value'])/100;
//                     }
//                    //Below query modified for FRPRDINXT - 10193
//                    $adb->pquery('update vtiger_siproductrel set sch_disc_amount= sch_disc_amount+'.$schemeamount.' where lineitem_id='.$lineitem_id.' and id='.$focus->id);
//                    $value_netprice=$adb->mquery('select net_price,(sch_disc_amount+discount_amount) as discount_amount,(((net_price-sch_disc_amount)*discount_percent)/100) as discount_percent from vtiger_siproductrel where lineitem_id='.$lineitem_id.' and id='.$focus->id);
//                    $totalvalue=$adb->query_result($value_netprice, 0,'net_price') - $adb->query_result($value_netprice, 0,'discount_amount') - $adb->query_result($value_netprice, 0,'discount_percent');
//                    $adb->pquery('update sify_xtransaction_tax_rel set tax_amt=(('.$totalvalue.' * tax_percentage)/100) where transaction_line_id='.$lineitem_id.' and transaction_id='.$focus->id);  
//                    $queryFreeSch="INSERT INTO `vtiger_itemwise_scheme` (`transaction_id`, `lineItemId`, `scheme_applied`, `scheme_name`, `scheme_id`, `value`,lineitem_id,disc_qty,disc_every,disc_amount) VALUES (?, ?, ?, ?, ?, ?,?,?,?,?)";
//                    $paraArray = array($focus->id,$productid, $product_type, $resultset['scheme_name'], $resultset['scheme_id'], $schemeamount,$lineitem_id ,'',$resultset['dTitle'] , $schemeamount);  
//                    $adb->pquery($queryFreeSch,$paraArray);
//                    }
                    
                     }
                     else{
                       $resultset=$output[$ij];
                    $productids=array_keys($resultset['allRows']);
                    $productid=$productids[0];
                    $product_type=  strtolower($resultset['dType']);
                    $totalqty=$resultset['allRows'][$productid]['soldBaseUOM'];
                    
                    if(!empty($productid)){
                    $select_value=$adb->mquery("SELECT baseqty,lineitem_id FROM vtiger_siproductrel where id=".$focus->id." and productid=".$productid);
                    for($k=0;$k<$adb->num_rows($select_value);$k++){
                     $lineitem_id= $adb->query_result($select_value, $k,'lineitem_id'); 
                     $schemeamount=($resultset['value']/$totalqty) * $adb->query_result($select_value, $k,'baseqty');
                    $queryFreeSch="INSERT INTO `vtiger_itemwise_scheme` (`transaction_id`, `lineItemId`, `scheme_applied`, `scheme_name`, `scheme_id`, `value`,lineitem_id,disc_qty,disc_every,disc_amount) VALUES (?, ?, ?, ?, ?, ?,?,?,?,?)";
                    $paraArray = array($focus->id,$productid, $product_type, $resultset['scheme_name'], $resultset['scheme_id'], '',$lineitem_id ,$resultset['value'],$resultset['dTitle'] , '');  
                    $adb->pquery($queryFreeSch,$paraArray);  
                    }
                     }
                 }
                 }
                 //if($invSchemeApplied==1){
                    //$invSchemeDesc = htmlentities(implode("$$",$invSchemeDesc));
                    //$Invquery ="update vtiger_salesinvoice set invoice_scheme_discount=?, invoice_scheme_description =?, //invoice_scheme_points =? where salesinvoiceid=?";
                    //$qparams = array($invSchemeDiscount,$invSchemeDesc,$invSchemePoints, $focus->id);
                    //$adb->pquery($Invquery,$qparams);
                 //}
                }
//            echo '<br/>';
            
                $product_query=$adb->mquery('select listprice,quantity,(sch_disc_amount+discount_amount) as discount_amount,(((net_price-sch_disc_amount)*discount_percent)/100) as discount_percent,productid,lineitem_id from vtiger_siproductrel where product_type="Main" and id='.$focus->id);
                for($ii=0;$ii<$adb->num_rows($product_query);$ii++){
                    $productid=$adb->query_result($product_query, $ii,'productid');
                    $lineitem_id_val=$adb->query_result($product_query, $ii,'lineitem_id');
					$si_location = $godown_def_name['xgodownid'];
					$shipping_address_pick = $so_focus->column_fields['cf_xsalesorder_shipping_address_pick'];
					//$trans_date	= $so_focus->column_fields['cf_salesorder_sales_order_date'];
                                        $trans_date	= $focus->column_fields['cf_salesinvoice_sales_invoice_date'];
                $value_netprice=$adb->mquery('select net_price,(sch_disc_amount+discount_amount) as discount_amount,(((net_price-sch_disc_amount)*discount_percent)/100) as discount_percent from vtiger_siproductrel where lineitem_id='.$lineitem_id_val.' and id='.$focus->id);
                $totalaferDisc=$adb->query_result($value_netprice, 0,'net_price') - $adb->query_result($value_netprice, 0,'discount_amount') - $adb->query_result($value_netprice, 0,'discount_percent');
                $adb->pquery('update vtiger_siproductrel set total_after_discount='.$totalaferDisc.' where lineitem_id='.$lineitem_id_val.' and id='.$focus->id); 
			
               $taxes_for_product = getTaxDetailsForProduct($productid,'all','SalesInvoice',$distArr['id'],$focus->column_fields['vendor_id'],'','','',$shipping_address_pick,$si_location,0,$trans_date);
                        //echo '<pre>';print_r($taxes_for_product);die;
                        $taxPerToApply=0.0;
                        $tax_total = 0.0;
                        for($tax_count=0;$tax_count<count($taxes_for_product);$tax_count++)
                        {
                                $tax_name = $taxes_for_product[$tax_count]['taxname'];
                                $tax_id = $taxes_for_product[$tax_count]['taxid'];
                                $taxgrouptype = $taxes_for_product[$tax_count]['taxgrouptype'];
                                $tax_label = $taxes_for_product[$tax_count]['taxlabel'];
                                $request_tax_name = $taxes_for_product[$tax_count]['percentage'];
                                $percentage_display = $taxes_for_product[$tax_count]['percentage_display'];
								$xtaxapplicableon	= ($taxes_for_product[$tax_count]['xtaxapplicableon']) ? $taxes_for_product[$tax_count]['xtaxapplicableon'] : '';
                                $taxAmt = $taxes_for_product[$tax_count]['taxAmt'];
                                $type = $taxes_for_product[$tax_count]['taxType'];
                                $listprice=$adb->query_result($product_query, $ii,'listprice');
                                $quantity1=$adb->query_result($product_query, $ii,'quantity');
                                $discount_amount=$adb->query_result($product_query, $ii,'discount_amount');
                                $discount_percent=$adb->query_result($product_query, $ii,'discount_percent');
                                $tax_value=$request_tax_name;
                                $tax_value = (($tax_value != '') ? numberformat($tax_value,6) : '0.000000');
                                
                                $prodTotal = $listprice*$quantity1;                               
                                
                                $prodTotal=$prodTotal-$discount_amount-$discount_percent;
                                
                                if($type != ''){
                                    $taxAmt = (($prodTotal)*$tax_value/100);
                                }
                               
//                                if($taxes_for_product[$tax_count]['percentage'] == $taxes_for_product[$tax_count]['percentage_display']){
//                                    $tax_amount = (($listprice*$quantity)*$tax_value/100);
//                                }else{
//                                    $tax_amount = ($taxes_for_product[0]['percentage']*$tax_value/100);    
//                                }
                                $tax_total += $taxAmt;
                                
                                if($percentage_display != $request_tax_name){
                                   $tax_value = (($percentage_display != '') ? numberformat($percentage_display,6) : '0.000000');
                                }
								if($xtaxapplicableon=='Tax on Tax'){
									$tax_value = $request_tax_name;
									$prodTotal = $taxAmt*100/$percentage_display;
								}
                                /*$createQuery="INSERT INTO sify_xtransaction_tax_rel (`transaction_id`,`lineitem_id`,`transaction_name`,`tax_type`,`tax_label`,`tax_percentage`,`transaction_line_id`,`tax_amt`) VALUES(?,?,?,?,?,?,?,?)";   
                                $adb->pquery($createQuery,array($focus->id,$productid,'SalesInvoice',$tax_name,$tax_label,$tax_value,$lineitem_id_val,$taxAmt));*/
								
								if($ALLOW_GST_TRANSACTION){
									insertXTransactionTaxInfo($focus->id,$productid,'SalesInvoice',$tax_name,$tax_label,$tax_value,$taxAmt,$prodTotal,$lineitem_id_val,'sify_xtransaction_tax_rel_si',$tax_id,$taxgrouptype);
								}	
								
                        }
				
                       if($tax_total > 0){
                                //$net_price_updated = $net_price+$tax_total;
                                $adb->pquery("UPDATE vtiger_siproductrel set tax1=? where lineitem_id=?",array($tax_total,$lineitem_id_val));
                        } 
                }
            //die;
            $insertrel="insert into vtiger_crmentityrel(crmid, module, relcrmid, relmodule) values(?,?,?,?)";
            $qparams = array($so_focus->column_fields['record_id'],'xSalesOrder',$focus->id,$module);
            $adb->pquery($insertrel,$qparams);
            //echo "Hi123 :".print_r($_REQUEST['button']);exit;
            
            global $SI_LBL_SALESMAN_PROD_ALLOW, $SI_LBL_PRODCATGRP_MAND;
            $update_str = "";
            if($SI_LBL_SALESMAN_PROD_ALLOW == 'True' && $SI_LBL_PRODCATGRP_MAND == 'True')
            {
                $cf_salesinvoice_sales_man = $focus->column_fields['cf_salesinvoice_sales_man'];
                if($cf_salesinvoice_sales_man > 0)
                {
                    $cg_id_qry =$adb->pquery("select cf_xsalesman_product_category_group from vtiger_xsalesmancf where xsalesmanid=$cf_salesinvoice_sales_man");
                    $cg_id = $adb->query_result($cg_id_qry, 0, "cf_xsalesman_product_category_group");
                    $cg_id = str_replace(" |##| ", ",", $cg_id);
                    
                    if($cg_id != '')
                    {
                       /* $pcg_id_query = $adb->pquery("SELECT vtiger_xcategorygroup.xcategorygroupid FROM vtiger_xcategorygroup
                            INNER JOIN vtiger_xcategorygroupcf ON vtiger_xcategorygroupcf.xcategorygroupid = vtiger_xcategorygroup.xcategorygroupid 
                            INNER JOIN vtiger_xproductcategorygroupmapping ON vtiger_xproductcategorygroupmapping.productcategorygroup = vtiger_xcategorygroup.xcategorygroupid 
                            INNER JOIN vtiger_xproductcategorygroupmappingcf ON vtiger_xproductcategorygroupmappingcf.xproductcategorygroupmappingid = vtiger_xproductcategorygroupmapping.xproductcategorygroupmappingid 
                            INNER JOIN vtiger_xdistributorclusterrel ON vtiger_xdistributorclusterrel.distclusterid = vtiger_xproductcategorygroupmapping.distributorcluster 
                            INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xcategorygroup.xcategorygroupid
                            LEFT JOIN vtiger_crmentityrel ON (vtiger_crmentityrel.crmid = vtiger_xproductcategorygroupmapping.xproductcategorygroupmappingid 
                                AND vtiger_crmentityrel.module='xProductCategoryGroupMapping' AND vtiger_crmentityrel.relmodule='PGDistributorRevoke')
                            LEFT JOIN vtiger_pgdistributorrevoke ON (vtiger_pgdistributorrevoke.pgdistributorrevokeid = vtiger_crmentityrel.relcrmid 
                                AND vtiger_pgdistributorrevoke.active=1)
                            where vtiger_xcategorygroup.xcategorygroupid IN ($cg_id) AND vtiger_crmentity.deleted=0 AND vtiger_xcategorygroup.active=1 
                            AND vtiger_xproductcategorygroupmappingcf.cf_xproductcategorygroupmapping_effective_from_date <= CURDATE() 
                            AND vtiger_xproductcategorygroupmapping.active=1  
                            AND if(vtiger_pgdistributorrevoke.revokedate is null || vtiger_pgdistributorrevoke.revokedate = '',CURDATE(), vtiger_pgdistributorrevoke.revokedate) >= CURDATE() 
                            group by vtiger_xcategorygroup.xcategorygroupid order by vtiger_xcategorygroup.xcategorygroupid limit 0,1");*/
                        $pcg_id_query = $adb->pquery("SELECT vtiger_xcategorygroup.xcategorygroupid FROM vtiger_xcategorygroup
                            INNER JOIN vtiger_xcategorygroupcf ON vtiger_xcategorygroupcf.xcategorygroupid = vtiger_xcategorygroup.xcategorygroupid 
                            INNER JOIN vtiger_xproductcategorygroupmapping ON vtiger_xproductcategorygroupmapping.productcategorygroup = vtiger_xcategorygroup.xcategorygroupid 
                            INNER JOIN vtiger_xproductcategorygroupmappingcf ON vtiger_xproductcategorygroupmappingcf.xproductcategorygroupmappingid = vtiger_xproductcategorygroupmapping.xproductcategorygroupmappingid 
                            INNER JOIN vtiger_xdistributorclusterrel ON vtiger_xdistributorclusterrel.distclusterid = vtiger_xproductcategorygroupmapping.distributorcluster 
                            INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xcategorygroup.xcategorygroupid
                            where vtiger_xcategorygroup.xcategorygroupid IN ($cg_id) AND vtiger_crmentity.deleted=0 AND vtiger_xcategorygroup.active=1 
                            AND vtiger_xproductcategorygroupmappingcf.cf_xproductcategorygroupmapping_effective_from_date <= CURDATE() 
                            AND vtiger_xproductcategorygroupmapping.active=1  
                            group by vtiger_xcategorygroup.xcategorygroupid order by vtiger_xcategorygroup.xcategorygroupid limit 0,1");                        
                        
                        $pcg_id = $adb->query_result($pcg_id_query, 0, "xcategorygroupid");
                        
                        if($pcg_id > 0)
                            $update_str = ", xproductgroupid=$pcg_id";
                    }
                    
                }
            }
            $value_netprice=$adb->mquery('select lineitem_id,net_price as total,(sch_disc_amount+discount_amount) as discount_amount,(((net_price-sch_disc_amount)*discount_percent)/100) as discount_percent,tax1 from vtiger_siproductrel where product_type="Main" and id='.$focus->id);
            //$value_total=$adb->mquery('select sum(tax_amt)as tax_total from sify_xtransaction_tax_rel where transaction_id='.$focus->id);
            $netamount=$percentvalue=0;
            for($f=0;$f<$adb->num_rows($value_netprice);$f++){
                $discountvalue=$prices=0;
                $prices=$adb->query_result($value_netprice, $f, "total")- $adb->query_result($value_netprice, $f, "discount_amount")-$adb->query_result($value_netprice, $f, "discount_percent") + $adb->query_result($value_netprice, $f, "tax1");
                $adb->pquery("UPDATE vtiger_siproductrel set net_price=? where product_type='Main' and lineitem_id=?",array($prices,$adb->query_result($value_netprice, $f, "lineitem_id")));
                $netamount= $netamount +$prices;
                
                }
			// Invoice level scheme update START
			$invSchemeDesc = array();
			$invSchemeDiscount = 0;
			$invSchemePoints = 0;
			if($AUTO_SCHEME_TRANSACTION[0]['value'] ==1){
				for($ij=0;$ij<count($output)-1;$ij++){
					if($output[$ij]['userselection']=='1')
					{
						continue;
					}
					if($output[$ij]['inlevel']=='TRUE')
					{
					 if ($output[$ij]['type']!='Points') {
						 if ($output[$ij]['dType']=='Amount') {
							$invSchemeDesc[] = $output[$ij]['dTitle'].'##'.$output[$ij]['value'];
							$invSchemeDiscount += numberformat($output[$ij]['value'],6);
						 } elseif($output[$ij]['dType']=='Percentage') {
							$perSchemeDiscount = ($netamount * $output[$ij]['value'])/100;
							$invSchemeDesc[] = $output[$ij]['value'] . $output[$ij]['dTitle'].'##' . $perSchemeDiscount;
							$invSchemeDiscount += numberformat($perSchemeDiscount,6);
						 }
					 } else {
						 $invSchemeDesc[] = $output[$ij]['dTitle'].'##'.$output[$ij]['value'];
						 $invSchemePoints += $output[$ij]['value'];
					 }
					 $invSchemeApplied=1;
					 //continue;
					}
				}
				//echo "Invoice level scheme applied - " . $invSchemeApplied;
				if($invSchemeApplied==1){
					$invSchemeDesc = htmlentities(implode("$$",$invSchemeDesc));
					$Invquery ="update vtiger_salesinvoice set invoice_scheme_discount=?, invoice_scheme_description =?, invoice_scheme_points =? where salesinvoiceid=?";
					$qparams = array($invSchemeDiscount,$invSchemeDesc,$invSchemePoints, $focus->id);
					$adb->pquery($Invquery,$qparams);
				}
				//echo "Invoice level discount " . $invSchemeDiscount . "<br />";
			}
			// Invoice level scheme update END
            //$netamount = $netamount + $adb->query_result($value_total, 0, "tax_total");
            $so_focus->column_fields['hdnDiscountAmount'];
            if($so_focus->column_fields['hdnDiscountPercent']!=0)
                $percentvalue=($netamount*$so_focus->column_fields['hdnDiscountPercent'])/100;
            
            $net_total_val_without_roundoff = $netamount-$percentvalue-$so_focus->column_fields['hdnDiscountAmount']+$so_focus->column_fields['hdnS_H_Amount']-$invSchemeDiscount;
            $upd_net_total_qry="update vtiger_salesinvoice set total=?, subtotal=?, grand_roundoff=? $update_str where salesinvoiceid=?";
			$net_total_val = getsetRoundOff('SalesInvoice',$net_total_val_without_roundoff);
            $roundOff = (abs($net_total_val - $net_total_val_without_roundoff));
            $qparams = array($net_total_val, $netamount, $roundOff, $focus->id);
            $adb->pquery($upd_net_total_qry,$qparams);
            $upd_cf_si_outstanding="update vtiger_salesinvoicecf set  cf_salesinvoice_outstanding=?  where salesinvoiceid=?";
            $qparams1 = array($net_total_val, $focus->id);
            $adb->pquery($upd_cf_si_outstanding,$qparams1);
            $bst_product_query=$adb->mquery('select id from vtiger_siproductrel where id='.$return_id,array());
            $zerolines = 0;
            if($adb->num_rows($bst_product_query)){
                $zerolines = 1;
            }
            if($status_flag && $zerolines)
            { 
                /* Workflow Concepts */
                $moduleWf='salesinvoice';
                $posAction = "Submit";

                $ns = getNextstageByPosAction($moduleWf,$posAction);
                $statusa = $ns['cf_workflowstage_next_stage'];
                $nextStage = $ns['cf_workflowstage_next_content_status'];    
                $businessLogic = $ns['cf_workflowstage_business_logic'];
                $SAtype='';
                $fromType='Save';
                $so_record = '';
                $redirect = 'no';
                array_push($success_record,$return_id);
                $stock_result= workflowBisLogicMain_SI($return_id,$moduleWf,$distArr['id'],$statusa,$nextStage,$businessLogic,$SAtype,$fromType, $so_record, $redirect);
                
                if($stock_result[0] != FALSE){
                    //$tArr = generateUniqueSeries($transactionseriesname='',"Sales Invoice",$increment=TRUE,$focus->column_fields['cf_salesinvoice_transaction_series']); //Workflow based update
                    $adb->pquery("UPDATE vtiger_salesinvoicecf set cf_salesinvoice_next_stage_name=? where salesinvoiceid=?",array('Publish',$return_id));
                    $adb->pquery("UPDATE vtiger_salesinvoice set status=? where salesinvoiceid=?",array('Created',$return_id));
                }
                else
                {
                    $adb->pquery("UPDATE vtiger_salesinvoicecf set cf_salesinvoice_next_stage_name=? where salesinvoiceid=?",array('Creation',$return_id));
                    $adb->pquery("UPDATE vtiger_salesinvoice set status=? where salesinvoiceid=?",array('Draft',$return_id));
                }
                $adb->pquery("UPDATE vtiger_xsalesordercf set cf_xsalesorder_next_stage_name=? where salesorderid=?",array('',$so_focus->id));
                $adb->pquery("UPDATE vtiger_xsalesorder set status=? where salesorderid=?",array('Published',$so_focus->id));
                $adb->pquery("UPDATE sify_bulk_ord_conv_tmp set order_status=? where salesorderid=?",array('Converted',$so_focus->id));

                $success_cnt++;
            }
            else 
            {   
                if($zerolines == 0){
                    $adb->pquery("UPDATE vtiger_salesinvoicecf set cf_salesinvoice_next_stage_name=? where salesinvoiceid=?",array('',$return_id));
                    $adb->pquery("UPDATE vtiger_salesinvoice set status=? where salesinvoiceid=?",array('Cancel',$return_id));
                }else{
                    $adb->pquery("UPDATE vtiger_salesinvoicecf set cf_salesinvoice_next_stage_name=? where salesinvoiceid=?",array('Creation',$return_id));
                    $adb->pquery("UPDATE vtiger_salesinvoice set status=? where salesinvoiceid=?",array('Draft',$return_id));

                    $adb->pquery("UPDATE vtiger_xsalesordercf set cf_xsalesorder_next_stage_name=?, cf_xsalesorder_reason=?
                        where salesorderid=?",array('', $reason_str, $so_focus->id));
                    $adb->pquery("UPDATE vtiger_xsalesorder set status=? where salesorderid=?",array('Rejected',$so_focus->id));
                }
                $fail_cnt++;
            }
        }
    }
    
    }
        
    if($success>0)
    {
        $PRINT_TYPE = getDistrInvConfig('PRINT_TYPE', $distArr['id']);
        echo '<script type="text/javascript">'
        . 'var array1=['.implode(',', $success_record).']; var array2=["111","212"];'
                . 'console.log(array1);  console.log(array1.length);'
                . 'if(confirm("Conversion Order Status \n\n'.$success_cnt.' out of '.$success.' selected orders processed\n\n Do you want to print the sales invoices")){'
                . ' for(var index = 0; index < array1.length; index++){'
                . ' window.open("index.php?module=SalesInvoice&ajax=true&action=Print&pname='.$PRINT_TYPE[0]['value'].'&record="+array1[index],"_blank" ); '
                . '} '                
                . ' }else{  '
                . '}'
                . 'window.location="index.php?module=BulkOrderConversion&action=index&parenttab=SalesManagement";  </script>';   
        exit;
    }
}

function convert_rvs_to_si($ids, $tot_conv = 'No',$auto_rsitosi = 0)
{
   // echo "<PRE>";print_r($ids);die;
    global $current_user,$adb,$LBL_USE_RECEIVED_TRANSACTION_NUMBER,$root_directory,$ALLOW_GST_TRANSACTION;
    $Resulrpatth1_dir = $root_directory.'storage/log/rlog';
    if(!is_dir($Resulrpatth1_dir))
        mkdir($Resulrpatth1_dir, 0700);
    
    if($auto_rsitosi){
        $Resulrpatth1 = $root_directory.'storage/log/rlog/log_XMLAUTORSITOSI_'.$ids.'_'.date("Ymd_H_i_s").'.txt';
        
        $logco = "------------Auto Convert RSI to SI---------".PHP_EOL;
        $logco .= "Inserted ID ".$ids.PHP_EOL;
        $logco .= "Total Conv ".$tot_conv.PHP_EOL;
        $logco .= "Auto config value ".$auto_rsitosi.PHP_EOL;
        file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
    }
    
    $ids_arr = explode(";", $ids);
    if($auto_rsitosi){
        $logco = "------------Inserted ID Aray---------".PHP_EOL;
        $logco .= "ids_arr ".$ids_arr.PHP_EOL;
        file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
    }
    
    $skip_cnt = 0;
    $success_cnt = 0;
    $fail_cnt = 0;
    $posAction = $_REQUEST['stage_v'];
    if($tot_conv == 'Yes')
        $posAction = 'Create SI';
    $ns1 = getNextstageByPosAction('xrSalesInvoice',$posAction);
    $conv_to_si = false;
    if($auto_rsitosi){
        $logco = "------------BL---------".PHP_EOL;
        $logco .= "BL Array : ".  print_r($ns1, TRUE).PHP_EOL;
        $logco .= "BLFTSI : ".$ns1['cf_workflowstage_business_logic'].PHP_EOL;
        file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
    }
    if($ns1['cf_workflowstage_business_logic'] == 'Forward to SI')
    {
        $conv_to_si = true;
    }
    //echo "Hi :".$ns1['cf_workflowstage_next_stage'].", ".$ns1['cf_workflowstage_next_content_status'];exit;
    if($auto_rsitosi){
        $logco = "------------Count Inserted ID Aray---------".PHP_EOL;
        $logco .= "count Inserted ID ".count($ids_arr).PHP_EOL;
        file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
    }
    for($i=0; $i<count($ids_arr); $i++)
    {
        if($auto_rsitosi){
            $logco = "------------Array Inserted ID---------".PHP_EOL;
            $logco .= "Array Inserted ID ".$ids_arr[$i].PHP_EOL;
            $logco .= "Numeric Array Inserted ID ".is_numeric($ids_arr[$i]).PHP_EOL;
            file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
        }
        if(is_numeric($ids_arr[$i]))
        { 
            require_once('modules/xrSalesInvoice/xrSalesInvoice.php');
            require_once('include/utils/utils.php');
            require_once('modules/SalesInvoice/utils/EditViewUtils.php');
            require_once('modules/SalesInvoice/utils/SalesInvoiceUtils.php');
            require_once('modules/SalesInvoice/SalesInvoice.php');
            require_once('include/database/PearDatabase.php');
            require_once('include/TransactionSeries.php');
            require_once('include/WorkflowBase.php');
            require_once('config.salesinvoice.php');
            //require_once('data/CRMEntity.php');
            
            $soid = $ids_arr[$i];
            $module = 'SalesInvoice';
            $status_flag = 1;
            $distArr = getDistrIDbyUserID();
            $so_focus = new xrSalesInvoice();
            $focus = new SalesInvoice();
            $so_focus->id = $soid;
            $so_focus->retrieve_entity_info($soid, "xrSalesInvoice"); //echo "Hi ".print_r($so_focus);exit;
			$is_converted = toCheckIsConverted($soid, "xrSalesInvoice");
			if($is_converted){
                unset($ids_arr[$i]);
                $skip_cnt++;
                $adb->pquery("UPDATE vtiger_xrsalesinvoicecf set cf_xrsalesinvoice_next_stage_name=? where xrsalesinvoiceid=?",array('',$so_focus->id));
                $adb->pquery("UPDATE vtiger_xrsalesinvoice set status=?,is_processed=? where xrsalesinvoiceid=?",array('Processed',2,$so_focus->id));
                continue;
            }
          
            $focus = getConvertRsiTosi($focus, $so_focus, $soid,'si',$auto_rsitosi);
            if($auto_rsitosi){
                $logco = "RSI Insert : ".PHP_EOL;
                $logco .= "RSI Array : ".  print_r($so_focus->column_fields, TRUE).PHP_EOL;
                $logco .= "DISTARR : ". print_r($distArr, TRUE).PHP_EOL;
                file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
            }
            if(empty($distArr) && $auto_rsitosi == 1)
            {
                $dist_code = $so_focus->column_fields['cf_xrsalesinvoice_seller_id'];
                $qry = $adb->pquery("SELECT xdistributorid FROM `vtiger_xdistributor` WHERE `distributorcode` = ?",array($dist_code));
                $distArr['id'] = $adb->query_result($qry, 0, "xdistributorid");
            }
            
            if($so_focus->column_fields['status'] == 'Processed' || $so_focus->column_fields['status'] == 'Rejected')
            {
                $skip_cnt++;
//                continue;
            }
            if(!$conv_to_si)
            {
                $adb->pquery("UPDATE vtiger_xrsalesinvoicecf set cf_xrsalesinvoice_next_stage_name=? where xrsalesinvoiceid=?",array($ns1['cf_workflowstage_next_stage'],$soid));
                $adb->pquery("UPDATE vtiger_xrsalesinvoice set status=? where xrsalesinvoiceid=?",array($ns1['cf_workflowstage_next_content_status'],$soid));
//                continue;
            }
            // Reset the value w.r.t SalesOrder Selected
            $result = $adb->pquery("SELECT cf_xpayment_payment_mode FROM vtiger_xretailercf WHERE `xretailerid` = '".$so_focus->column_fields['vendor_id']."'");
            $value = $adb->query_result($result,0);
            
            $currencyid = $so_focus->column_fields['currency_id'];
            $rate = $so_focus->column_fields['conversion_rate'];
            $focus->column_fields['cf_salesinvoice_pay_mode'] = $value;
            $focus->column_fields['requisition_no'] = $so_focus->column_fields['requisition_no'];
            $focus->column_fields['tracking_no'] = $so_focus->column_fields['tracking_no'];
            $focus->column_fields['adjustment'] = $so_focus->column_fields['adjustment'];
            $focus->column_fields['salescommission'] = $so_focus->column_fields['salescommission'];
            $focus->column_fields['exciseduty'] = $so_focus->column_fields['exciseduty'];
            $focus->column_fields['total'] = (($so_focus->column_fields['total'] != '') ? numberformat($so_focus->column_fields['total'],6) : '0.000000');
            $focus->column_fields['subtotal'] = (($so_focus->column_fields['subtotal'] != '') ? numberformat($so_focus->column_fields['subtotal'],6) : '0.000000');
            $focus->column_fields['taxtype'] = $so_focus->column_fields['taxtype'];
            $focus->column_fields['hdnDiscountPercent'] = (($so_focus->column_fields['discount_percent'] != '') ? numberformat($so_focus->column_fields['discount_percent'],6) : '0.000000');
            $focus->column_fields['hdnDiscountAmount'] = (($so_focus->column_fields['discount_amount'] != '') ? numberformat($so_focus->column_fields['discount_amount'],6) : '0.000000');
            $focus->column_fields['hdnS_H_Amount'] = (($so_focus->column_fields['s_h_amount'] != '') ? numberformat($so_focus->column_fields['s_h_amount'],6) : '0.000000');
            
            
            $focus->column_fields['discount_percent'] = (($so_focus->column_fields['discount_percent'] != '') ? numberformat($so_focus->column_fields['discount_percent'],6) : '0.000000');
            $focus->column_fields['discount_amount'] = (($so_focus->column_fields['discount_amount'] != '') ? numberformat($so_focus->column_fields['discount_amount'],6) : '0.000000');
            $focus->column_fields['s_h_amount'] = (($so_focus->column_fields['s_h_amount'] != '') ? numberformat($so_focus->column_fields['s_h_amount'],6) : '0.000000');
            
            $focus->column_fields['cf_salesinvoice_reason'] = $so_focus->column_fields['cf_xrsalesinvoice_reason'];
			
			$whereUpdateCond='';
			$is_taxfiled = 0;
			$trntaxtype = ($ALLOW_GST_TRANSACTION) ? 'GST' : 'VAT';
			
			if($ALLOW_GST_TRANSACTION){ //Added by prasanth save received GSTN NO
				/*$buyer_gstinno	= $so_focus->column_fields['buyer_gstinno'];
				$seller_gstinno = $so_focus->column_fields['seller_gstinno'];
				$buyer_state	= $so_focus->column_fields['buyer_state'];
				$seller_state	= $so_focus->column_fields['seller_state'];*/
				
				$dis_location = $so_focus->column_fields['si_location'];
				$ret_shipping_address_pick = $so_focus->column_fields['cf_xrsalesinvoice_shipping_address_pick'];	
				$ret_id = $so_focus->column_fields['vendor_id'];

				$ret_shipping_gstno = $adb->pquery("SELECT xAdd.gstinno,xState.statecode from vtiger_xaddress xAdd INNER JOIN vtiger_xstate xState on xState.xstateid=xAdd.xstateid where xAdd.xaddressid=?",array($ret_shipping_address_pick));

				if($adb->num_rows($ret_shipping_gstno)>0) {
					$buyer_gstinno = $adb->query_result($ret_shipping_gstno, 0, 'gstinno');
					$buyer_state = $adb->query_result($ret_shipping_gstno, 0, 'statecode');
					$whereUpdateCond.= 'buyer_gstinno="'.$buyer_gstinno.'" , buyer_state="'.$buyer_state.'",';
				}else{
					$ret_gstno=$adb->pquery("SELECT vtiger_xretailer.gstinno,xState.statecode FROM vtiger_xretailer INNER JOIN vtiger_xretailercf ON vtiger_xretailercf.xretailerid=vtiger_xretailer.xretailerid INNER JOIN vtiger_xstate xState on xState.xstateid=vtiger_xretailercf.cf_xretailer_state where vtiger_xretailer.xretailerid=?",array($ret_id));
					if($adb->num_rows($ret_gstno)>0) {
						$buyer_gstinno = $adb->query_result($ret_gstno, 0, 'gstinno');
						$buyer_state = $adb->query_result($ret_gstno, 0, 'statecode');
						$whereUpdateCond.= 'buyer_gstinno="'.$buyer_gstinno.'" , buyer_state="'.$buyer_state.'",';
					}
				}

				$xdis_godown_gstno = $adb->pquery("SELECT xgodown.gstinno,xState.statecode from vtiger_xgodown xgodown INNER JOIN vtiger_xstate xState on xState.xstateid=xgodown.xstateid where xgodown.gstinno!='' AND xgodown.xgodownid=".$dis_location);
						
				 if($adb->num_rows($xdis_godown_gstno)>0) {
					$seller_gstinno = $adb->query_result($xdis_godown_gstno, 0, 'gstinno');
					$seller_state = $adb->query_result($xdis_godown_gstno, 0, 'statecode');
					$whereUpdateCond.= 'seller_gstinno="'.$seller_gstinno.'" , seller_state="'.$seller_state.'",';
				 }else{
					 $xdis_gstno=$adb->pquery("SELECT vtiger_xdistributor.gstinno,xState.statecode FROM vtiger_xdistributor INNER JOIN vtiger_xdistributorcf ON vtiger_xdistributorcf.xdistributorid=vtiger_xdistributor.xdistributorid INNER JOIN vtiger_xstate xState on xState.xstateid=vtiger_xdistributorcf.cf_xdistributor_state where vtiger_xdistributor.xdistributorid=?",array($distArr['id']));
					 if($adb->num_rows($xdis_gstno)>0) {
						$seller_gstinno = $adb->query_result($xdis_gstno, 0, 'gstinno');
						$seller_state = $adb->query_result($xdis_gstno, 0, 'statecode');
						$whereUpdateCond.= 'seller_gstinno="'.$seller_gstinno.'" , seller_state="'.$seller_state.'",';
					 }
				 }	
			}
			
			$whereUpdateCond.= 'is_taxfiled="'.$is_taxfiled.'" , trntaxtype="'.$trntaxtype.'",';
			$generateUniqueSeries = TRUE;
			$generateDefaultTrans = TRUE;
			$increment = TRUE;
			$transaction_series=$so_focus->column_fields['cf_xrsalesinvoice_transaction_series'];
			$transaction_number=$so_focus->column_fields['cf_xrsalesinvoice_transaction_number'];
                        $focus->column_fields['cf_salesinvoice_transaction_number'] = 'Draft_'.$so_focus->column_fields['cf_xrsalesinvoice_transaction_number'];
                        $focus->column_fields['cf_salesinvoice_transaction_series'] = 0;
                        if($auto_rsitosi){
                        file_put_contents($Resulrpatth1, 'Before Transaction Series Distributor:'.$focus->column_fields['cf_salesinvoice_transaction_number'].PHP_EOL, FILE_APPEND);
                    }
			/*if(!empty($transaction_series)){
				$checkTransQuery	= 	"SELECT en.crmid FROM vtiger_xtransactionseries mt 
							LEFT JOIN vtiger_xtransactionseriescf rt ON mt.xtransactionseriesid = rt.xtransactionseriesid 
							LEFT JOIN vtiger_crmentity en ON mt.xtransactionseriesid = en.crmid 
							WHERE mt.transactionseriescode='".$transaction_series."' 
							AND rt.cf_xtransactionseries_transaction_type='Sales Invoice' 
							AND en.deleted=0 AND (mt.xdistributorid = '".$distArr['id']."' OR mt.xdistributorid =0)";
				$checkTransSeries = $adb->mquery($checkTransQuery);
				if($adb->num_rows($checkTransSeries)>0){
					$focus->column_fields['cf_salesinvoice_transaction_series'] = $transaction_series;
					$generateDefaultTrans = FALSE;
				}else{
					 $transaction_series   =  '';
				}
			}/* else{
				$generateDefaultTrans = FALSE;
			} 
			if(!empty($transaction_number)){
				if($LBL_USE_RECEIVED_TRANSACTION_NUMBER=='True'){
					$focus->column_fields['cf_salesinvoice_transaction_number'] = $transaction_number;
					$increment = FALSE;
					$generateUniqueSeries =FALSE;
				}
			}  
			if($auto_rsitosi){
                
                file_put_contents($Resulrpatth1, 'Transaction Series:'.$transaction_series.PHP_EOL, FILE_APPEND);
            }
                         if(empty($transaction_series))
                         {
                            $dist_code          = $so_focus->column_fields['cf_xrsalesinvoice_seller_id'];
                            $qry                = $adb->mquery("SELECT xdistributorid FROM `vtiger_xdistributor` WHERE `distributorcode` = ?",array($dist_code));
                            $distid             = $adb->query_result($qry, 0, "xdistributorid");
                             $transQry	="SELECT 
					mt.xtransactionseriesid,
					mt.transactionseriesname,
					rt.xtransactionseriesid,
					rt.cf_xtransactionseries_transaction_type,
					rt.cf_xtransactionseries_user_id 
					FROM vtiger_xtransactionseries mt 
					LEFT JOIN vtiger_xtransactionseriescf rt ON mt.xtransactionseriesid=rt.xtransactionseriesid
					LEFT JOIN vtiger_crmentity ct ON mt.xtransactionseriesid=ct.crmid 
					WHERE ct.deleted=0 
					AND rt.cf_xtransactionseries_transaction_type='Sales Invoice'";
                                        if(!empty($distArr['id'])){ 
                                           $transQry .= " AND mt.xdistributorid = '".$distid."'";
                                        }
                                        $transQry .= " ORDER BY rt.cf_xtransactionseries_mark_as_default DESC LIMIT 1";

                                $traArr                = $adb->mquery($transQry);
                                $transaction_series    = $traArr->fields['xtransactionseriesid'];
                             
                         }
						 if($auto_rsitosi){
                
                file_put_contents($Resulrpatth1, 'Transaction Series Distributor:'.$transaction_series.PHP_EOL, FILE_APPEND);
            }
		//	if($generateUniqueSeries){
				$tArr = generateUniqueSeries($transaction_series, "Sales Invoice",$increment);
				if($generateUniqueSeries)
					$focus->column_fields['cf_salesinvoice_transaction_number'] = $tArr['uniqueSeries'];
				
				$focus->column_fields['cf_salesinvoice_transaction_series'] = $tArr['xtransactionseriesid'];
		//}	
			/* if($generateDefaultTrans){
				$defaultTrans = getDefaultTransactionSeries("Sales Invoice");                
				$focus->column_fields['cf_salesinvoice_transaction_series'] = $defaultTrans['xtransactionseriesid'];
			} */
			//ECHO '<pre>'; print_r($focus->column_fields); exit;*/

            //added to set the PO number and terms and conditions
            $focus->column_fields['terms_conditions'] = $so_focus->column_fields['terms_conditions'];
            $customer_id = $so_focus->column_fields['vendor_id'];
            if( $so_focus->column_fields['customer_type'] == 1){
                $receivedcus_id = getReceivedCusId($customer_id);
                $customer_id    = $receivedcus_id['cus_id'];  
            }
            $focus->column_fields['vendor_id'] = $customer_id;
            $focus->column_fields['cf_salesinvoice_beat'] = $so_focus->column_fields['cf_xrsalesinvoice_beat'];
            $focus->column_fields['cf_salesinvoice_sales_man'] = $so_focus->column_fields['cf_xrsalesinvoice_sales_man'];
            $focus->column_fields['carrier'] = $so_focus->column_fields['carrier'];
            $focus->column_fields['cf_salesinvoice_reason'] = $so_focus->column_fields['cf_xrsalesinvoice_reason'];
            $focus->column_fields['cf_salesinvoice_billing_address_pick'] = $so_focus->column_fields['cf_xrsalesinvoice_billing_address_pick'];
            $focus->column_fields['cf_salesinvoice_shipping_address_pick'] = $so_focus->column_fields['cf_xrsalesinvoice_shipping_address_pick'];
            $focus->column_fields['si_location'] = $so_focus->column_fields['si_location'];
            $so_focus->column_fields['cf_xrsalesinvoice_sales_invoice_date'] = str_replace('/', '-', $so_focus->column_fields['cf_xrsalesinvoice_sales_invoice_date']);
            if($so_focus->column_fields['cf_xrsalesinvoice_sales_invoice_date'] != '')
            {
//                $focus->column_fields['cf_salesinvoice_sales_invoice_date'] = date("d-m-Y", strtotime($so_focus->column_fields['cf_xrsalesinvoice_sales_invoice_date']));
                $focus->column_fields['cf_salesinvoice_sales_invoice_date'] = $so_focus->column_fields['cf_xrsalesinvoice_sales_invoice_date'];
//                $focus->column_fields['duedate'] = date("d-m-Y", strtotime($so_focus->column_fields['cf_xrsalesinvoice_sales_invoice_date']));
                $focus->column_fields['duedate'] = $so_focus->column_fields['cf_xrsalesinvoice_sales_invoice_date'];
//                $focus->column_fields['cf_salesinvoice_payment_date'] = date("d-m-Y", strtotime($so_focus->column_fields['cf_xrsalesinvoice_sales_invoice_date']));
                $focus->column_fields['cf_salesinvoice_payment_date'] = $so_focus->column_fields['cf_xrsalesinvoice_sales_invoice_date'];
            }

            $focus->column_fields['description'] = $so_focus->column_fields['description'];


            $BillAdd = getdefaultaddress('Billing',$customer_id);
            if (!empty($BillAdd))
            {
                $focus->column_fields['cf_salesinvoice_billing_address_pick'] = $BillAdd['xaddressid'];
                $focus->column_fields['bill_street'] = $BillAdd['cf_xaddress_address'];
                $focus->column_fields['bill_pobox'] = $BillAdd['cf_xaddress_po_box'];
                $focus->column_fields['bill_city'] = $BillAdd['cf_xaddress_city'];
                //$focus->column_fields['bill_state'] = "";
                $focus->column_fields['bill_code'] = $BillAdd['cf_xaddress_postal_code'];
                $focus->column_fields['bill_country'] = $BillAdd['cf_xaddress_country'];
            }

            $ShipAdd = getdefaultaddress('Shipping',$customer_id);
            if (!empty($ShipAdd))
            {
                $focus->column_fields['cf_salesinvoice_shipping_address_pick'] = $ShipAdd['xaddressid'];
                $focus->column_fields['ship_street'] = $ShipAdd['cf_xaddress_address'];
                $focus->column_fields['ship_pobox'] = $ShipAdd['cf_xaddress_po_box'];
                $focus->column_fields['ship_city'] = $ShipAdd['cf_xaddress_city'];
				$focus->column_fields['gstinno'] = $ShipAdd['gstinno'];
                //$focus->column_fields['ship_state'] = "";
                $focus->column_fields['ship_code'] = $ShipAdd['cf_xaddress_postal_code'];
                $focus->column_fields['ship_country'] = $ShipAdd['cf_xaddress_country'];
            }
            //Added to display the SalesOrder's associated vtiger_products -- when we create vtiger_invoice from SO DetailView
            $txtTax = (($so_focus->column_fields['txtTax'] != '') ? $so_focus->column_fields['txtTax'] : '0.000000');
            $txtAdj = (($so_focus->column_fields['txtAdjustment'] != '') ? $so_focus->column_fields['txtAdjustment'] : '0.000000');

            setObjectValuesFromRequest($focus);
            $focus->update_prod_stock='';
            if($focus->column_fields['status'] == 'Received Shipment')
            {
                $prev_postatus=getPoStatus($focus->id);
                if($focus->column_fields['status'] != $prev_postatus)
                {
                        $focus->update_prod_stock='true';
                }
            }

            $focus->column_fields['currency_id'] = $so_focus->column_fields['currency_id'];
            $cur_sym_rate = getCurrencySymbolandCRate($so_focus->column_fields['currency_id']);
            $focus->column_fields['conversion_rate'] = $cur_sym_rate['rate'];

            $posAction = "Submit";
            $ns = getNextstageByPosAction($module,$posAction);
            $focus->column_fields['cf_salesinvoice_next_stage_name'] = $ns['cf_workflowstage_next_stage'];
            $focus->column_fields['status'] = $ns['cf_workflowstage_next_content_status'];

            $focus->column_fields['assigned_user_id'] = $so_focus->column_fields['assigned_user_id'];

//            $transQry = "SELECT mt.xtransactionseriesid,mt.transactionseriesname,rt.xtransactionseriesid,rt.cf_xtransactionseries_transaction_type,rt.cf_xtransactionseries_user_id FROM vtiger_xtransactionseries mt LEFT JOIN vtiger_xtransactionseriescf rt ON mt.xtransactionseriesid=rt.xtransactionseriesid LEFT JOIN vtiger_crmentity ct ON mt.xtransactionseriesid=ct.crmid WHERE 
//ct.deleted=0 AND rt.cf_xtransactionseries_transaction_type='Sales Invoice' LIMIT 1";
//            $traArr = $adb->pquery($transQry);
         //   $tArr = generateUniqueSeries($transactionseriesname='',"Sales Invoice");
         //   $focus->column_fields['cf_salesinvoice_transaction_number'] = $tArr['uniqueSeries'];

            $focus->column_fields['cf_salesinvoice_seller_id'] = $distArr['id'];
            
            //$focus->column_fields['discount_amount'] = (($so_focus->column_fields['hdnDiscountAmount'] != '') ? numberformat($so_focus->column_fields['hdnDiscountAmount'],6) : '0.000000');
            //$focus->column_fields['hdnDiscountAmount'] = (($so_focus->column_fields['hdnDiscountAmount'] != '') ? numberformat($so_focus->column_fields['hdnDiscountAmount'],6) : '0.000000');
            $focus->column_fields['cf_salesinvoice_buyer_id']=$so_focus->column_fields['vendor_id'];
            $focus->column_fields['cf_salesinvoice_outstanding']=(($so_focus->column_fields['cf_xrsalesinvoice_outstanding'] != '') ? numberformat($so_focus->column_fields['cf_xrsalesinvoice_outstanding'],6) : '0.000000');
			$BS_is_converted = toCheckIsConverted($soid, "xrSalesInvoice");
            if(!empty($BS_is_converted)){
                $adb->pquery("UPDATE vtiger_xrsalesinvoicecf set cf_xrsalesinvoice_next_stage_name=? where xrsalesinvoiceid=?",array('',$so_focus->id));
                $adb->pquery("UPDATE vtiger_xrsalesinvoice set status=?,is_processed=? where xrsalesinvoiceid=?",array('Processed',2,$so_focus->id));
                continue;
            }
           $focus->save("SalesInvoice");
            $return_id = $focus->id;
            if($auto_rsitosi){
                $logco = "SI Insert : ".$return_id.PHP_EOL;
                $logco .= "SI Array : ".  print_r($focus->column_fields, TRUE).PHP_EOL;
                $logco .= "DISTARR : ". print_r($distArr, TRUE).PHP_EOL;
                file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
            }
            $adb->pquery("UPDATE vtiger_salesinvoice set $whereUpdateCond status=? where salesinvoiceid=?",array($focus->column_fields['status'],$return_id));
            $adb->pquery("UPDATE vtiger_salesinvoicecf set cf_salesinvoice_next_stage_name=?,cf_salesinvoice_transaction_number=? where salesinvoiceid=?",array($focus->column_fields['cf_salesinvoice_next_stage_name'],$focus->column_fields['cf_salesinvoice_transaction_number'],$return_id));
            if($SI_LBL_CURRENCY_OPTION_ENABLE!="True") {
                $updateQry = "UPDATE `vtiger_salesinvoice` SET `currency_id` = '1' WHERE `salesinvoiceid` = ".$return_id;
                $adb->pquery($updateQry);
            }

            if($SI_LBL_TAX_OPTION_ENABLE!="True") {
                $updateQry2 = "UPDATE `vtiger_salesinvoice` SET `taxtype` = 'individual' WHERE `salesinvoiceid` = ".$return_id;
                $adb->pquery($updateQry2);
            }

            /*$lineItemIDArr = getLineItemID("xrSalesInvoice",$so_focus->id);
            $lineItemID = '';
            echo "Hi123 :".print_r($lineItemIDArr);exit;
            foreach($lineItemIDArr as $line) {
                $lineItemID .= $line['lineitem_id']."#";
            }

            $lineItemID = substr($lineItemID,0,-1);
            $reftrantype = "xrSalesInvoice"; */
            $pro_relqy = "select * from vtiger_rsiproductrel where id=? order by sequence_no";
            $rsi_result = $adb->mquery($pro_relqy,array($so_focus->id));
            $net_total_val = 0.0;
            $free_scheme_product = array();
            $product_cnt = $adb->num_rows($rsi_result);
            $free_scheme = $scheme_free_details = array();
            if($auto_rsitosi){
                $logco = "product details qy : ".$pro_relqy.PHP_EOL;
                $logco .= "RSI id : ".  print_r($so_focus->id, TRUE).PHP_EOL;
                $logco .= "product details count : ". $product_cnt.PHP_EOL;
                file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
            }
            if($product_cnt){
            for ($k = 0; $k < $product_cnt; $k++) 
            {
                $productid = $adb->query_result($rsi_result,$k,'productid');
                $scheme_code = $adb->query_result($rsi_result,$k,'scheme_code');
                $product_type = $adb->query_result($rsi_result,$k,'product_type');
                $quantity = $adb->query_result($rsi_result,$k,'quantity');
                
                //if($product_type == 'Main'){
                    $scheme_code = explode("~", $scheme_code);
                    
                    foreach($scheme_code as $code){
                       $code = trim($code);
                       if($code != 'null')
                        array_push($free_scheme, array('SchemeCode'  => $code));
                    }
                //}else{
                //    array_push($free_scheme_product, $productid);
                    
                //}
                
            }
            
            
            $scheme_free_details = array();
            
            
            foreach($free_scheme as $scheme){
                
               $schDetailsQuery="SELECT vtiger_xscheme.xschemeid,cf_xscheme_scheme_name FROM vtiger_xscheme                           
                            INNER JOIN vtiger_xschemecf ON (vtiger_xscheme.xschemeid= vtiger_xschemecf.xschemeid)
                            INNER JOIN vtiger_crmentity ON (vtiger_crmentity.crmid= vtiger_xscheme.xschemeid)
                            WHERE vtiger_xscheme.schemecode = ? AND vtiger_crmentity.deleted = 0";
               
               $schDet   = $adb->pquery($schDetailsQuery,array($scheme['SchemeCode']));
               $schemeid = $adb->query_result($schDet,0,'xschemeid');
               $schemename = $adb->query_result($schDet,0,'cf_xscheme_scheme_name');
               
               
               if($schemeid != ''){
               
               $schfreeitemqry = "SELECT vtiger_xschemeslabfreerelcf.cf_xschemeslabfreerel_productcode
                FROM vtiger_xschemeslabfreerel
                LEFT JOIN vtiger_xschemeslabfreerelcf ON vtiger_xschemeslabfreerelcf.xschemeslabfreerelid = vtiger_xschemeslabfreerel.xschemeslabfreerelid
                INNER JOIN vtiger_crmentityrel ON vtiger_crmentityrel.relcrmid = vtiger_xschemeslabfreerel.slabid
                        AND vtiger_crmentityrel.relmodule = 'xSchemeslabrel'
                WHERE vtiger_crmentityrel.crmid = ?";
               
                        
                    $schfreeitem = $adb->pquery($schfreeitemqry,array($schemeid));
                    $tot_rows = $adb->num_rows($schfreeitem);
                    for ($indsch = 0; $indsch < $adb->num_rows($schfreeitem) ; $indsch++) {
                        $productcode=$adb->query_result($schfreeitem,$indsch,'cf_xschemeslabfreerel_productcode');
                        $scheme_free_details[$productcode] = array($scheme['SchemeCode'] , $schemeid, $schemename);
                    }
                    if($tot_rows == 0){
                        $scheme_free_details['NoFree'] = array($scheme['SchemeCode'] , $schemeid, $schemename);
                    }
               }
            }
        }    
            if($product_cnt > 0)
            {
                $reason_str = '';
                for ($index = 0; $index < $product_cnt; $index++) 
                {
                    
                    
                    
                                            
                    $id = $adb->query_result($rsi_result,$index,'id');
                    $productid = $adb->query_result($rsi_result,$index,'productid');
                    $productcode = $adb->query_result($rsi_result,$index,'productcode');
                    $product_type = $adb->query_result($rsi_result,$index,'product_type');
                    
                    $quantity = $adb->query_result($rsi_result,$index,'quantity');
                    $baseqty = $adb->query_result($rsi_result,$index,'baseqty');
                    $dispatchqty = $adb->query_result($rsi_result,$index,'dispatchqty');
                    $tuom = $adb->query_result($rsi_result,$index,'tuom');
                    $listprice = $adb->query_result($rsi_result,$index,'listprice');
                    $discount_percent = $adb->query_result($rsi_result,$index,'discount_percent');
                    $discount_amount = $adb->query_result($rsi_result,$index,'discount_amount');
                    $sch_disc_amount = $adb->query_result($rsi_result,$index,'sch_disc_amount');
                    $comment = $adb->query_result($rsi_result,$index,'comment');
                    $description = $adb->query_result($rsi_result,$index,'description');
                    $incrementondel = $adb->query_result($rsi_result,$index,'incrementondel');
                    $tax1 = $adb->query_result($rsi_result,$index,'tax1');
                    $tax2 = $adb->query_result($rsi_result,$index,'tax2');
                    $tax3 = $adb->query_result($rsi_result,$index,'tax3');
                    $free_qty = $adb->query_result($rsi_result,$index,'free_qty');
                    $dam_qty = $adb->query_result($rsi_result,$index,'dam_qty');
                    $net_price = $adb->query_result($rsi_result,$index,'net_price');
                    $lineitem_id = $adb->query_result($rsi_result,$index,'lineitem_id');
                    $batchcode = $adb->query_result($rsi_result,$index,'batchcode');
                    $pkg = trim($adb->query_result($rsi_result,$index,'pkg'));
                    $expiry = trim($adb->query_result($rsi_result,$index,'expiry'));
                    $scheme_code = $adb->query_result($rsi_result,$index,'scheme_code');
                    $scheme_points = $adb->query_result($rsi_result,$index,'points');
                    
                    $pts = $adb->query_result($rsi_result,$index,'pts');
                    $ptr = $adb->query_result($rsi_result,$index,'ptr');
                    $mrp = $adb->query_result($rsi_result,$index,'mrp');
                    $ecp = $adb->query_result($rsi_result,$index,'ecp');
                    
                    
                    $schemecodes = explode('~',$scheme_code);
                    $scheme_detail = array();
                    
                    if(($product_type == 'Main' || $product_type == 'Dist_Free') && ($sch_disc_amount > 0 || $scheme_points > 0)){
                        foreach($schemecodes as $value){


                            $value = trim($value);

                            $schDetailsQuery="SELECT * FROM vtiger_xscheme 
                                INNER JOIN vtiger_xschemecf ON (vtiger_xschemecf.xschemeid = vtiger_xscheme.xschemeid)
                                INNER JOIN vtiger_xschemeslabrel ON (vtiger_xschemeslabrel.schemecode = vtiger_xscheme.xschemeid)
                                INNER JOIN vtiger_xschemeslabrelcf ON (vtiger_xschemeslabrel.xschemeslabrelid = vtiger_xschemeslabrelcf.xschemeslabrelid)
                                INNER JOIN vtiger_crmentity ON (vtiger_crmentity.crmid= vtiger_xschemeslabrel.xschemeslabrelid)
                                WHERE vtiger_xscheme.schemecode = ? AND vtiger_crmentity.deleted = 0 ";

                            $slabDet=$adb->pquery($schDetailsQuery,array($value));
                            for ($index1 = 0; $index1 < $adb->num_rows($slabDet) ; $index1++) {
                                $schType=$adb->query_result($slabDet,$index1,'cf_xschemeslabrel_benefit_type');                                                    
                                //$forEvery=$adb->query_result($slabDet,$index1,'cf_xschemeslabrel_for_every');                        
                                //$benvalue=$adb->query_result($slabDet,$index1,'cf_xschemeslabrel_value');   
                                $scheme_name=$adb->query_result($slabDet,$index1,'cf_xscheme_scheme_name');   
                                $scheme_definition=$adb->query_result($slabDet,$index1,'cf_xscheme_scheme_definition');   
                                $schemeid=$adb->query_result($slabDet,$index1,'xschemeid'); 
                                $schType = strtolower($schType);

                                $sch1 = array('SchemeId' => $schemeid,'SchemeName'=>$scheme_name);
                                if($schType == 'points'){
                                    $sch2 =  array('SchemeType' => $schType,'Value'=>$scheme_points);
                                }elseif($schType == 'percentage'){
                                    $sch2 =  array('SchemeType' => $schType,'Value'=>$sch_disc_amount);
                                }elseif($schType == 'amount'){
                                    $sch2 =  array('SchemeType' => $schType,'Value'=>$sch_disc_amount);
                                }
                                array_push($scheme_detail, array_merge($sch1 , $sch2));
                            }
                        }
                    }

                    if($product_type != 'Main' && $product_type != 'Dist_Free'){
                        
                        if($scheme_free_details[$productid]){
                            array_push($scheme_detail, 
                                    array(
                                        'SchemeId' => $scheme_free_details[$productid][1],
                                        'SchemeName'=>$scheme_free_details[$productid][2],
                                        'SchemeType' => 'Free','Value'=>$quantity
                                    )
                            );
                        }else{
                            array_push($scheme_detail, 
                                    array(
                                        'SchemeId' => $scheme_free_details['NoFree'][1],
                                        'SchemeName'=>$scheme_free_details['NoFree'][2],
                                        'SchemeType' => 'Free','Value'=>$quantity
                                    )
                            );
                        }
                         
                    }
                    
                    $seq_no = ($index+1);
                   

                    if($tax1 == null || $tax1 == "")
                        $tax1 == 0.00;

                    if($quantity == '' || $quantity == null)
                        $quantity = 0.00;
                    
                    $listprice = (($listprice != '') ? numberformat($listprice,6) : '0.000000');
                    $discount_percent = (($discount_percent != '') ? numberformat($discount_percent,6) : '0.000000');
                    $discount_amount = (($discount_amount != '') ? numberformat($discount_amount,6) : '0.000000');
                    $sch_disc_amount = (($sch_disc_amount != '') ? numberformat($sch_disc_amount,6) : '0.000000');
                    $free_qty = (($free_qty != '') ? numberformat($free_qty,6) : '0.000000');
                    $dam_qty = (($dam_qty != '') ? numberformat($dam_qty,6) : '0.000000');
                    $net_price = (($net_price != '') ? numberformat($net_price,6) : '0.000000');
                    $tax1 = (($tax1 != '') ? numberformat($tax1,6) : '0.000000');
                    $tax2 = (($tax2 != '') ? numberformat($tax2,6) : '0.000000');
                    $tax3 = (($tax3 != '') ? numberformat($tax3,6) : '0.000000');
                    
                    $prod_serial_qry = $adb->pquery("SELECT if(track_serial_number='Yes', 1, 0) as track_serial FROM vtiger_xproduct where 
                        xproductid='$productid'");
                    $track_serial = $adb->query_result($prod_serial_qry,0,'track_serial');

                    $salable_qty_tot = 0;
                    $batch_str = '';
                    if($batchcode == '' || $batchcode == "-" || $batchcode == null)
                        $batch_str .= " and (batchnumber = '' or batchnumber = '--NoBatch--'  or batchnumber = '-') ";
                    else 
                        $batch_str .= " and batchnumber = '$batchcode' ";
                    if($pkg == '' || $pkg == "-" || $pkg == null)
                        $batch_str .= " and (pkg = '' or pkg = '-') ";
                    else 
                        $batch_str .= " and pkg = '$pkg' ";
                    if($expiry == '' || $expiry == "-" || $expiry == null)
                        $batch_str .= " and (expiry = '' or expiry = '-') ";
                    else 
                        $batch_str .= " and expiry = '$expiry' ";
                    
					
                    if($product_type == 'Main' || $product_type == 'Dist_Free'){
                        $checkqty = 'iq.salable_qty';
                    }else{
                        $checkqty = 'iq.free_qty';
                        $listprice = $net_price = 0;
                    }
                    
                    if($ptr!='')                    
                     $batch_str .=" and pts=$pts and ptr=$ptr and mrp=$mrp and ecp=$ecp";
                    $batchgetqy = "SELECT iq.* FROM (SELECT id, IFNULL(batchnumber,'') as `batchnumber`, pkg, expiry, IFNULL(SUM(salable_qty),0.0)-IFNULL(SUM(sold_salable_qty),0.0) as salable_qty, IFNULL(SUM(free_qty),0.0)-IFNULL(SUM(sold_free_qty),0.0) as free_qty, sum(damaged_qty) as damaged_qty, sum(damaged_free_qty) as damaged_free_qty, pts, ptr, mrp, ecp , ptr_type from vtiger_stocklots where productid='$productid' AND distributorcode='".$distArr['id']."'  AND location_id='".$so_focus->column_fields['si_location']."' $batch_str group by distributorcode,productid,batchnumber,pkg,expiry,pts,ptr,mrp,ecp ORDER BY batchnumber,expiry,pkg,ptr,ptr_type) as iq WHERE $checkqty >= $baseqty";
		
                    $batch_dtl_qry  = $adb->mquery($batchgetqy,array());
                    if($auto_rsitosi){
                        $logco = "batch qy : ".$batchgetqy.PHP_EOL;
                        file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                    }
                    if(!($adb->num_rows($batch_dtl_qry) > 0))
                    {
                        $prod_dtl_qry = $adb->pquery("SELECT productname FROM vtiger_xproduct where xproductid=$productid");
                        $prod_name = $adb->query_result($prod_dtl_qry,0,'productname');
                        $reason_str .= "Stock not enough for this Product $prod_name and $batchcode\n";
                        $status_flag = 0;
                    }
                    if($auto_rsitosi){
                        $logco = "batch num_rows : ".$adb->num_rows($batch_dtl_qry).PHP_EOL;
                        $logco .= "status_flag : ".$status_flag.PHP_EOL;
                        file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                    }
                    $quantity = (($quantity != '') ? numberformat($quantity,6) : '0.000000');
                    $baseqty = (($baseqty != '') ? numberformat($baseqty,6) : '0.000000');
                    
                    for($j=0; $j<1; $j++)
                    {
                        $salable_qty = $adb->query_result($batch_dtl_qry,$j,'salable_qty');
                        $free_qty = $adb->query_result($batch_dtl_qry,$j,'free_qty');
                        if(($adb->num_rows($batch_dtl_qry) > 0)){
                            $pts = $adb->query_result($batch_dtl_qry,$j,'pts');
                            $ptr = $adb->query_result($batch_dtl_qry,$j,'ptr');
                            $mrp = $adb->query_result($batch_dtl_qry,$j,'mrp');
                            $ecp = $adb->query_result($batch_dtl_qry,$j,'ecp');  
                            $pkg = $adb->query_result($batch_dtl_qry,$j,'pkg');
                            $expiry = $adb->query_result($batch_dtl_qry,$j,'expiry');
                            $batchnumber = $adb->query_result($batch_dtl_qry,$j,'batchnumber');
                        }
                        
                        
                        
                        $salable_qty = (($salable_qty != '') ? numberformat($salable_qty,6) : '0.000000');
                        $free_qty = (($free_qty != '') ? numberformat($free_qty,6) : '0.000000');
                        $pts = (($pts != '') ? numberformat($pts,6) : '0.000000');
                        $ptr = (($ptr != '') ? numberformat($ptr,6) : '0.000000');
                        $mrp = (($mrp != '') ? numberformat($mrp,6) : '0.000000');
                        $ecp = (($ecp != '') ? numberformat($ecp,6) : '0.000000');

                        if($pkg == '' || $pkg == NULL)
                            $pkg = '-';
                        if($expiry == '' || $expiry == NULL)
                            $expiry = '-';
                        if($batchnumber == '' || $batchnumber == NULL)
                            $batchnumber = '-';

                        /* Insert Line Items */
                 
                        $net_value= $quantity * $listprice;
                        $discount_percent_amount=0;
                        if($discount_percent!='' && $discount_percent!=NULL && $discount_percent!=0)
                            $discount_percent_amount = ($net_value * $discount_percent) /100;
                            
                        $net_price = $net_value - $discount_percent_amount - $discount_amount - $sch_disc_amount + $tax1 + $tax2 + $tax3;
                        
                        
                        
                        $insert_qry = $adb->pquery("insert into vtiger_siproductrel (id, productid, productcode, product_type, sequence_no, quantity, 
                        baseqty, dispatchqty, refid, reflineid, reftrantype, tuom, listprice, discount_percent, discount_amount, sch_disc_amount, comment, 
                        description, incrementondel, tax1, tax2, tax3, free_qty, dam_qty, net_price) values ('$return_id', '$productid', '$productcode', '$product_type', '$seq_no', '$quantity', 
                        '$baseqty', '$dispatchqty', '$id', '$lineitem_id', 'xrSalesInvoice', '$tuom', '$listprice', '$discount_percent', '$discount_amount', 
                        '$sch_disc_amount', '$comment', '$description', '$incrementondel', '$tax1', '$tax2', '$tax3', '$free_qty', '$dam_qty', '$net_price')");
                        $lineitem_id_val = $adb->getLastInsertID();

                        
                        $query = "SELECT * FROM vtiger_itemwise_scheme_receive WHERE transaction_id = ? AND lineitem_id = ?";
                        $rsiresult = $adb->mquery($query, array($so_focus->id, $lineitem_id));

//                        echo '<pre>';print_r($rsiresult);
                        $rsi_total_rows = $adb->num_rows($rsiresult);
                        $rsiArr = array();
                        if($rsi_total_rows > 0){
                            for($ri = 0;$ri < $rsi_total_rows; $ri++)
                            {
                                $rsiArr[$ri]        = $adb->raw_query_result_rowdata($rsiresult,$ri); 
                            }
                        }
                        
                        
                        $si_sqty = 0;
                        $si_sfqty = 0;
                        if($product_type == 'Main' || $product_type == 'Dist_Free')
                        {
                            $si_sqty = numberformat($baseqty,6);
                        }
                        else 
                        {
                            $si_sfqty = numberformat($baseqty,6);
                        }
                        
                        /* Insert date in vtiger_xsalestransaction_batchinfo table (Batch Detail) */

                        $query ="insert into vtiger_xsalestransaction_batchinfo(transaction_id, trans_line_id, product_id, batch_no, pkd, expiry, transaction_type,sqty,sfqty, ptr, pts, mrp, ecp,ptr_type, distributor_id,track_serial) values(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                        $qparams = array($focus->id,$lineitem_id_val,$productid,"$batchcode","$pkg","$expiry","SI","$si_sqty","$si_sfqty","$ptr","$pts","$mrp","$ecp",'',$distArr['id'], '$track_serial');
                        $adb->pquery($query,$qparams);
                        
                        $price = (($price != '') ? numberformat($price,6) : '0.000000');
                        $disc_every = (($disc_every != '') ? numberformat($disc_every,6) : '0.000000');

                        if($rsi_total_rows > 0){
                            rsitosi($rsiArr,$productid, $lineitem_id_val, $lineitem_id, $focus->id);  
                            $sch_disc_amount = 0;
                            foreach($rsiArr as $res){
                                if($res['lineitem_id'] == $lineitem_id){
                                    if($res['scheme_applied'] != 'points')    
                                        $sch_disc_amount += $res['disc_amount'];
                                }
                            }
                            $query ="update vtiger_siproductrel set sch_disc_amount = '$sch_disc_amount' where refid = '$id' and reflineid = '$lineitem_id'";
                            $adb->pquery($query);
                        }
                        foreach($scheme_detail as $key => $value){
                                    //$value['SchemeId']
                                    //$value['SchemeName']
                                    //$value['SchemeType']
                                    //$value['Value']
                            $points = '';
                            if($value['SchemeType'] == 'points'){
                                
                                $paraArray=array(
                                    $focus->id,$productid,
                                    $value['SchemeType'],$value['SchemeName'],
                                    $value['SchemeId'],'NULL',
                                    'NULL',$lineitem_id_val,$value['Value'],$disc_every,'NULL',
                                );
                            }elseif($value['SchemeType'] == 'Free'){
                                $paraArray=array(
                                    $focus->id,$productid,
                                    $value['SchemeType'],$value['SchemeName'],
                                    $value['SchemeId'],$value['Value'],
                                    'NULL',$lineitem_id_val,$value['Value'],$disc_every,'NULL',
                                );
                            }else{
                               $paraArray=array(
                                    $focus->id,$productid,
                                    $value['SchemeType'],$value['SchemeName'],
                                    $value['SchemeId'],$value['Value'],
                                    'NULL',$lineitem_id_val,'NULL',$disc_every,
                                    $value['Value']
                                ); 
                            }
                            
                            //points - disc_qty
                            //amount - value,disc_amount
                            //free - value,disc_qty
//                            
                        $queryFreeSch="INSERT INTO `vtiger_itemwise_scheme` (`transaction_id`, `lineItemId`, `scheme_applied`, `scheme_name`, `scheme_id`, `value`,pricevalue,lineitem_id,disc_qty,disc_every,disc_amount) VALUES (?, ?, ?, ?, ?, ?,?,?,?,?,?)";
                        
                        $adb->pquery($queryFreeSch,$paraArray);
                       
                        }
                        
                        

                        //$adb->pquery($queryFreeSch,$paraArray);

                        /* Insert Tax Detail */
                        //$rt = array($focus->id, $distArr['id'], $productid, $lineitem_id_val, $focus->column_fields['cf_salesinvoice_buyer_id']);
                        
                        $taxes_for_product = getTaxForRSI($id, $focus->column_fields['cf_xrsalesinvoice_seller_id'], $productid, $lineitem_id, $focus->column_fields['cf_salesinvoice_buyer_id'],'xrSalesInvoice');
                        //$taxes_for_product = getTaxDetailsForProduct($productid,'all','SalesInvoice',$distArr['id'],$focus->column_fields['cf_salesinvoice_buyer_id']);
//                        echo '<pre>';print_r($taxes_for_product);die;
                        $taxPerToApply=0.0;
                        $tax_total = 0.0;
                        for($tax_count=0;$tax_count<count($taxes_for_product);$tax_count++)
                        {
                                $tax_name = $taxes_for_product[$tax_count]['taxname'];
                                $tax_label = $taxes_for_product[$tax_count]['taxlabel'];
                                $request_tax_name = $taxes_for_product[$tax_count]['percentage'];
                                $percentage_display = $taxes_for_product[$tax_count]['percentage_display'];
                                $taxAmt = $taxAmtRSITOSI = $taxes_for_product[$tax_count]['taxAmt'];
                                $type = $taxes_for_product[$tax_count]['type'];
								$tax_id 	= ($taxes_for_product[$tax_count]['taxid']) ? $taxes_for_product[$tax_count]['taxid'] : '';
								$taxgrouptype	= ($taxes_for_product[$tax_count]['taxgrouptype']) ? $taxes_for_product[$tax_count]['taxgrouptype'] : '';
								$xtaxapplicableon	= ($taxes_for_product[$tax_count]['xtaxapplicableon']) ? $taxes_for_product[$tax_count]['xtaxapplicableon'] : '';
								$tax_value=$request_tax_name;
                                $tax_value = (($tax_value != '') ? numberformat($tax_value,6) : '0.000000');
                                
                                $prodTotal = $listprice*$quantity;
                                
                                if($sch_disc_amount > 0 && $prodTotal >= $sch_disc_amount)
                                $prodTotal = $prodTotal - $sch_disc_amount;
                    
                                if($discount_amount > 0 && $prodTotal >= $discount_amount)
                                    $prodTotal = $prodTotal - $discount_amount;
                                
                                if($discount_percent > 0 && $prodTotal >= $discount_percent)
                                   $prodTotal = $prodTotal - (($prodTotal)*$discount_percent/100);
                                
                                if($type == 1){
                                    $taxAmt = (($prodTotal)*$tax_value/100);
                                }
								if($_REQUEST['conversion'][$soid] == 'rsitosi'){
									$taxAmt = $taxAmtRSITOSI;
								}
                               
//                                if($taxes_for_product[$tax_count]['percentage'] == $taxes_for_product[$tax_count]['percentage_display']){
//                                    $tax_amount = (($listprice*$quantity)*$tax_value/100);
//                                }else{
//                                    $tax_amount = ($taxes_for_product[0]['percentage']*$tax_value/100);    
//                                }
                                $tax_total += $taxAmt;
                                
                                if($percentage_display != $request_tax_name){
                                   $tax_value = (($percentage_display != '') ? numberformat($percentage_display,6) : '0.000000');
                                }
                               /* $createQuery="INSERT INTO sify_xtransaction_tax_rel (`transaction_id`,`lineitem_id`,`transaction_name`,`tax_type`,`tax_label`,`tax_percentage`,`transaction_line_id`,`tax_amt`) VALUES(?,?,?,?,?,?,?,?)";   
                                $adb->pquery($createQuery,array($focus->id,$productid,'SalesInvoice',"$tax_name","$tax_label","$tax_value",$lineitem_id_val,$taxAmt));*/
								
								if($ALLOW_GST_TRANSACTION){
									insertXTransactionTaxInfo($focus->id,$productid,'SalesInvoice',$tax_name,$tax_label,$request_tax_name,$taxAmt,$prodTotal,$lineitem_id_val,'sify_xtransaction_tax_rel_si',$tax_id,$taxgrouptype);
								}	
                        }
                        
                        
                        
                    }
                    
                    
                    
                    
                    $net_total = 0;
                    if(($listprice > 0) && $quantity > 0)
                        $net_total = $quantity*$listprice;
                    
                    if($sch_disc_amount > 0 && $net_total >= $sch_disc_amount)
                        $net_total = $net_total - $sch_disc_amount;
                    
                    if($discount_amount > 0 && $net_total >= $discount_amount)
                        $net_total = $net_total - $discount_amount;
                    
                    if($discount_percent > 0 && $net_total >= $discount_percent && $product_type !='Dist_Free'){
                        $t = $net_total * ($discount_percent/100);
                        $net_total = $net_total - $t;
                    }elseif($discount_percent >0 &&  $product_type =='Dist_Free'){
                        $t = $net_total * ($discount_percent/100);
                        $net_total = $net_total - $t;
                    }
                    
                    if($tax_total > 0 && $net_total >= $tax_total){
                        
                        $net_total = $net_total + $tax_total;
                    }
                  
                    //$a = array($net_total,$quantity,$listprice,$sch_disc_amount,$discount_amount,$discount_percent,$tax_total);
//                    echo '<pre>';
//                    print_r($a);
                    
                    /*
                    if($listprice > 0 && $tax_total > 0 && $quantity > 0)
                        $net_total = ($quantity*$listprice) + (($quantity*$listprice)*($tax_total/100));
                    elseif(($listprice > 0) && $quantity > 0)
                        $net_total = $quantity*$listprice;
                    */
                    
                    $net_total_val += $net_total;
//                    echo $net_total.'--'.$quantity.'--'.$listprice.'--'.$tax_total.'--'.$discount_amount;
//                    echo '<br/>';
                }
                
                
            
//            echo '<br/>';
            
//            die;
            $insertrel="insert into vtiger_crmentityrel(crmid, module, relcrmid, relmodule) values(?,?,?,?)";
            $qparams = array($so_focus->column_fields['record_id'],'xrSalesInvoice',$focus->id,$module);
            $adb->pquery($insertrel,$qparams);
            //echo "Hi123 :".print_r($_REQUEST['button']);exit;
            
            global $SI_LBL_SALESMAN_PROD_ALLOW, $SI_LBL_PRODCATGRP_MAND;
            $update_str = "";
            if($SI_LBL_SALESMAN_PROD_ALLOW == 'True' && $SI_LBL_PRODCATGRP_MAND == 'True')
            {
                $cf_salesinvoice_sales_man = $focus->column_fields['cf_salesinvoice_sales_man'];
                if($cf_salesinvoice_sales_man > 0)
                {
                    $cg_id_qry =$adb->pquery("select cf_xsalesman_product_category_group from vtiger_xsalesmancf where xsalesmanid=$cf_salesinvoice_sales_man");
                    $cg_id = $adb->query_result($cg_id_qry, 0, "cf_xsalesman_product_category_group");
                    $cg_id = str_replace(" |##| ", ",", $cg_id);
                    
                    if($cg_id != '')
                    {
                       /* $pcg_id_query = $adb->pquery("SELECT vtiger_xcategorygroup.xcategorygroupid FROM vtiger_xcategorygroup
                            INNER JOIN vtiger_xcategorygroupcf ON vtiger_xcategorygroupcf.xcategorygroupid = vtiger_xcategorygroup.xcategorygroupid 
                            INNER JOIN vtiger_xproductcategorygroupmapping ON vtiger_xproductcategorygroupmapping.productcategorygroup = vtiger_xcategorygroup.xcategorygroupid 
                            INNER JOIN vtiger_xproductcategorygroupmappingcf ON vtiger_xproductcategorygroupmappingcf.xproductcategorygroupmappingid = vtiger_xproductcategorygroupmapping.xproductcategorygroupmappingid 
                            INNER JOIN vtiger_xdistributorclusterrel ON vtiger_xdistributorclusterrel.distclusterid = vtiger_xproductcategorygroupmapping.distributorcluster 
                            INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xcategorygroup.xcategorygroupid
                            LEFT JOIN vtiger_crmentityrel ON (vtiger_crmentityrel.crmid = vtiger_xproductcategorygroupmapping.xproductcategorygroupmappingid 
                                AND vtiger_crmentityrel.module='xProductCategoryGroupMapping' AND vtiger_crmentityrel.relmodule='PGDistributorRevoke')
                            LEFT JOIN vtiger_pgdistributorrevoke ON (vtiger_pgdistributorrevoke.pgdistributorrevokeid = vtiger_crmentityrel.relcrmid 
                                AND vtiger_pgdistributorrevoke.active=1)
                            where vtiger_xcategorygroup.xcategorygroupid IN ($cg_id) AND vtiger_crmentity.deleted=0 AND vtiger_xcategorygroup.active=1 
                            AND vtiger_xproductcategorygroupmappingcf.cf_xproductcategorygroupmapping_effective_from_date <= CURDATE() 
                            AND vtiger_xproductcategorygroupmapping.active=1  
                            AND if(vtiger_pgdistributorrevoke.revokedate is null || vtiger_pgdistributorrevoke.revokedate = '',CURDATE(), vtiger_pgdistributorrevoke.revokedate) >= CURDATE() 
                            group by vtiger_xcategorygroup.xcategorygroupid order by vtiger_xcategorygroup.xcategorygroupid limit 0,1");*/
                        $pcg_id_query = $adb->pquery("SELECT vtiger_xcategorygroup.xcategorygroupid FROM vtiger_xcategorygroup
                            INNER JOIN vtiger_xcategorygroupcf ON vtiger_xcategorygroupcf.xcategorygroupid = vtiger_xcategorygroup.xcategorygroupid 
                            INNER JOIN vtiger_xproductcategorygroupmapping ON vtiger_xproductcategorygroupmapping.productcategorygroup = vtiger_xcategorygroup.xcategorygroupid 
                            INNER JOIN vtiger_xproductcategorygroupmappingcf ON vtiger_xproductcategorygroupmappingcf.xproductcategorygroupmappingid = vtiger_xproductcategorygroupmapping.xproductcategorygroupmappingid 
                            INNER JOIN vtiger_xdistributorclusterrel ON vtiger_xdistributorclusterrel.distclusterid = vtiger_xproductcategorygroupmapping.distributorcluster 
                            INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xcategorygroup.xcategorygroupid
                            where vtiger_xcategorygroup.xcategorygroupid IN ($cg_id) AND vtiger_crmentity.deleted=0 AND vtiger_xcategorygroup.active=1 
                            AND vtiger_xproductcategorygroupmappingcf.cf_xproductcategorygroupmapping_effective_from_date <= CURDATE() 
                            AND vtiger_xproductcategorygroupmapping.active=1  
                            group by vtiger_xcategorygroup.xcategorygroupid order by vtiger_xcategorygroup.xcategorygroupid limit 0,1");                        
                        
                        $pcg_id = $adb->query_result($pcg_id_query, 0, "xcategorygroupid");
                        
                        if($pcg_id > 0)
                            $update_str = ", xproductgroupid=$pcg_id";
                    }
                    
                }
            }
            $upd_net_total_qry="update vtiger_salesinvoice set total=?, subtotal=? $update_str where salesinvoiceid=?";
            $qparams = array($net_total_val, $net_total_val, $focus->id);
            $adb->pquery($upd_net_total_qry,$qparams);
            
            
            $upd_cf_si_outstanding="update vtiger_salesinvoicecf set  cf_salesinvoice_outstanding=?  where salesinvoiceid=?";
            $qparams1 = array($net_total_val, $focus->id);
            $adb->pquery($upd_cf_si_outstanding,$qparams1); 
            
                if($status_flag)
                {
                    /* Workflow Concepts */
                    $moduleWf='salesinvoice';
                    $posAction = "Submit";

                    $ns = getNextstageByPosAction($moduleWf,$posAction);
                    $statusa = $ns['cf_workflowstage_next_stage'];
                    $nextStage = $ns['cf_workflowstage_next_content_status'];    
                    $businessLogic = $ns['cf_workflowstage_business_logic'];
                    $SAtype='';
                    $fromType='Save';
                    $so_record = '';
                    $redirect = 'no';

                    workflowBisLogicMain_SI($return_id,$moduleWf,$distArr['id'],$statusa,$nextStage,$businessLogic,$SAtype,$fromType, $so_record, $redirect);
                    $transaction_series=$so_focus->column_fields['cf_xrsalesinvoice_transaction_series'];
                    $transaction_number=$so_focus->column_fields['cf_xrsalesinvoice_transaction_number'];
                    if(!empty($transaction_series)){
                        $checkTransQuery =  "SELECT en.crmid FROM vtiger_xtransactionseries mt 
                                            LEFT JOIN vtiger_xtransactionseriescf rt ON mt.xtransactionseriesid = rt.xtransactionseriesid 
                                            LEFT JOIN vtiger_crmentity en ON mt.xtransactionseriesid = en.crmid 
                                            WHERE mt.transactionseriescode='".$transaction_series."' 
                                            AND rt.cf_xtransactionseries_transaction_type='Sales Invoice' 
                                            AND en.deleted=0 AND (mt.xdistributorid = '".$distArr['id']."' OR mt.xdistributorid =0)";
                        $checkTransSeries = $adb->mquery($checkTransQuery);
                        if($adb->num_rows($checkTransSeries)>0){
                                $focus->column_fields['cf_salesinvoice_transaction_series'] = $transaction_series;
                                $generateDefaultTrans = FALSE;
                                
                        }else{
                            $transaction_series   =  '';
			}
                    } 
                    if(!empty($transaction_number)){
                        if($LBL_USE_RECEIVED_TRANSACTION_NUMBER=='True'){
                            $focus->column_fields['cf_salesinvoice_transaction_number'] = $transaction_number;
                            $increment = FALSE;
                            $generateUniqueSeries =FALSE;
                        }
                    }
                    if($auto_rsitosi){
                        file_put_contents($Resulrpatth1, 'Transaction Series:'.$transaction_series.PHP_EOL, FILE_APPEND);
                    }
                    if(empty($transaction_series))
                    {
                        $dist_code          = $so_focus->column_fields['cf_xrsalesinvoice_seller_id'];
                        $qry                = $adb->mquery("SELECT xdistributorid FROM `vtiger_xdistributor` WHERE `distributorcode` = ?",array($dist_code));
                        $distid             = $adb->query_result($qry, 0, "xdistributorid");
                        $transQry	="SELECT 
                                            mt.xtransactionseriesid,
                                            mt.transactionseriesname,
                                            rt.xtransactionseriesid,
                                            rt.cf_xtransactionseries_transaction_type,
                                            rt.cf_xtransactionseries_user_id 
                                            FROM vtiger_xtransactionseries mt 
                                            LEFT JOIN vtiger_xtransactionseriescf rt ON mt.xtransactionseriesid=rt.xtransactionseriesid
                                            LEFT JOIN vtiger_crmentity ct ON mt.xtransactionseriesid=ct.crmid 
                                            WHERE ct.deleted=0 
                                            AND rt.cf_xtransactionseries_transaction_type='Sales Invoice'";
                        if(!empty($distArr['id'])){ 
                           $transQry .= " AND mt.xdistributorid = '".$distid."'";
                        }
                        $transQry .= " ORDER BY rt.cf_xtransactionseries_mark_as_default DESC LIMIT 1";

                        $traArr                = $adb->mquery($transQry);
                        $transaction_series    = $traArr->fields['xtransactionseriesid'];

                    }
                    if($auto_rsitosi){
                        file_put_contents($Resulrpatth1, 'Transaction Series Distributor:'.$transaction_series.PHP_EOL, FILE_APPEND);
                    }
                    $tArr = generateUniqueSeries($transaction_series, "Sales Invoice",$increment);
                    if($generateUniqueSeries){
                        $transaction_number = $tArr['uniqueSeries'];
                        $transaction_series = $tArr['xtransactionseriesid'];
                    }
                    $adb->pquery("UPDATE vtiger_salesinvoicecf set cf_salesinvoice_next_stage_name=?,cf_salesinvoice_transaction_series=?,cf_salesinvoice_transaction_number=? where salesinvoiceid=?",array('Publish',$transaction_series,$transaction_number,$return_id));
                    $adb->pquery("UPDATE vtiger_salesinvoice set status=? where salesinvoiceid=?",array('Created',$return_id));

                    $adb->pquery("UPDATE vtiger_xrsalesinvoicecf set cf_xrsalesinvoice_next_stage_name=? where xrsalesinvoiceid=?",array('',$so_focus->id));
                    $adb->pquery("UPDATE vtiger_xrsalesinvoice set status=?,is_processed=? where xrsalesinvoiceid=?",array('Processed',2,$so_focus->id));

                    $success_cnt++;
                    if($auto_rsitosi){
                        $logco = "success_cnt : ".$success_cnt.PHP_EOL;
                        $logco .= "statusa : ".$statusa.PHP_EOL;
                        $logco .= "nextStage : ".$nextStage.PHP_EOL;
                        file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                    }
                }
                else{ 
//                    if($auto_rsitosi != 1)
//                    {   
                        $adb->pquery("UPDATE vtiger_salesinvoicecf set cf_salesinvoice_next_stage_name=? where salesinvoiceid=?",array('Creation',$return_id));
                        $adb->pquery("UPDATE vtiger_salesinvoice set status=? where salesinvoiceid=?",array('Draft',$return_id));

                        $adb->pquery("UPDATE vtiger_xrsalesinvoicecf set cf_xrsalesinvoice_next_stage_name=?, cf_xrsalesinvoice_reason=?
                            where xrsalesinvoiceid=?",array('', $reason_str, $so_focus->id));
                        $adb->pquery("UPDATE vtiger_xrsalesinvoice set status=?,is_processed=? where xrsalesinvoiceid=?",array('Rejected',3,$so_focus->id));
                        $fail_cnt++;
                        if($auto_rsitosi){
                            $logco = "failcount : ".$fail_cnt.PHP_EOL;
                            $logco .= "RSIstatusa : Rejected:".$statusa.PHP_EOL;
                            $logco .= "SI Stage Creation: ".$nextStage.PHP_EOL;
                            file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                        }
//                    }
//                    else
//                    {   
//                        $adb->pquery("UPDATE `vtiger_crmentity` SET `deleted` = '1' WHERE `setype` = 'SalesInvoice' AND `crmid` = $return_id");
//                        $fail_cnt++;
//                    }
                }
            }else{
                $adb->pquery("UPDATE vtiger_salesinvoicecf set cf_salesinvoice_next_stage_name=? where salesinvoiceid=?",array('',$return_id));
                $adb->pquery("UPDATE vtiger_salesinvoice set status=?, deleted=? where salesinvoiceid=?  ",array('Cancel',1,$return_id));
                $adb->pquery("UPDATE `vtiger_crmentity` SET `deleted` = '1' WHERE `setype` = 'SalesInvoice' AND `crmid` = $return_id",array());

                $adb->pquery("UPDATE vtiger_xrsalesinvoicecf set cf_xrsalesinvoice_next_stage_name=?, cf_xrsalesinvoice_reason=?
                    where xrsalesinvoiceid=?",array('', $reason_str, $so_focus->id));
                $adb->pquery("UPDATE vtiger_xrsalesinvoice set status=?,is_processed=? where xrsalesinvoiceid=?",array('Creation',0,$so_focus->id));
                $fail_cnt++;
                if($auto_rsitosi){
                    $logco = "failcount : ".$fail_cnt.PHP_EOL;
                    $logco .= "line item zero : Rejected:".$statusa.PHP_EOL;
                    file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                }
            }
            
            
        }
        
    }
    if($tot_conv == 'No')
    {
        echo '<script type="text/javascript">$j.jAlert("<h3>Conversion Order Status</h3>Conversion Success : '.$success_cnt.'<br/>Conversion Fialure &nbsp&nbsp: '.$fail_cnt.'<br/>Conversion Skip &nbsp&nbsp&nbsp&nbsp&nbsp&nbsp: '.$skip_cnt.'<br/>","message",function(){window.location="index.php?action=ListView&type=VanSales&module=xrSalesInvoice&parenttab=MobileIntegration"}); </script>';   
        exit;
    }

}

function convert_rso_to_so($ids, $tot_conv = 'No',$auto_rsotoso = 0)
{
    global $current_user,$adb,$LBL_SET_NETRATE,$ALLOW_GST_TRANSACTION,$LBL_USE_RECEIVED_TRANSACTION_NUMBER,$root_directory;
    
    $Resulrpatth1_dir = $root_directory.'storage/log/rlog';
    if(!is_dir($Resulrpatth1_dir)){
        mkdir($Resulrpatth1_dir, 0700);
    }
    
    if($auto_rsotoso){
        $Resulrpatth1 = $root_directory.'storage/log/rlog/log_XMLAUTORSOTOSO_'.$ids.'_'.date("Ymd_H_i_s").'.txt';
        
        $logco = "------------Auto Convert RSO to SO---------".PHP_EOL;
        $logco .= "Inserted ID ".$ids.PHP_EOL;
        $logco .= "Total Conv ".$tot_conv.PHP_EOL;
        $logco .= "Auto config value ".$auto_rsotoso.PHP_EOL;
        file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
        $conQuery = "select CON.key as lablename,CON.value as lablevalue FROM sify_inv_mgt_config CON  WHERE CON.key in ('LBL_SET_NETRATE','ALLOW_GST_TRANSACTION','LBL_USE_RECEIVED_TRANSACTION_NUMBER')";
        $config_query = $adb->pquery($conQuery,array());
        $config_data = array();
        for ($mc = 0; $mc < $adb->num_rows($config_query); $mc++) {
                $config_data[] = $adb->raw_query_result_rowdata($config_query,$mc);			
        }
        if(!empty($config_data)){
                foreach($config_data as $key => $configData){
                        $$configData['lablename'] = $configData['lablevalue'];				
                }
        }
    }
    
    $ids_arr = explode(";", $ids);
    if($auto_rsotoso){
        $logco = "------------RSO Inserted ID Array---------".PHP_EOL;
        $logco .= "ids_arr ".$ids_arr.PHP_EOL;
        file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
    }
    
    $skip_cnt = 0;
    $success_cnt = 0;
    $fail_cnt = 0;
    $posAction = $_REQUEST['stage_v'];
    if($tot_conv == 'Yes')
        $posAction = 'Create SO';
    $ns1 = getNextstageByPosAction('xrsalesorder',$posAction);
    $conv_to_si = false;
    if($auto_rsotoso){
        $logco = "------------RSO BL---------".PHP_EOL;
        $logco .= "BL Array : ".  print_r($ns1, TRUE).PHP_EOL;
        $logco .= "BLFTSI : ".$ns1['cf_workflowstage_business_logic'].PHP_EOL;
        file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
    }
    if($ns1['cf_workflowstage_business_logic'] == 'Forward to SO')
    {
        $conv_to_si = true;
    }    
    if($auto_rsotoso){
        $logco = "------------Count RSO Inserted ID Array---------".PHP_EOL;
        $logco .= "count Inserted ID ".count($ids_arr).PHP_EOL;
        file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
    }
    //echo "Hi :".$ns1['cf_workflowstage_next_stage'].", ".$ns1['cf_workflowstage_next_content_status'];
    
    for($i=0; $i<count($ids_arr); $i++)
    {
        if($auto_rsotoso){
            $logco = "------------RSO Array Inserted ID---------".PHP_EOL;
            $logco .= "Array Inserted ID ".$ids_arr[$i].PHP_EOL;
            $logco .= "Numeric Array Inserted ID ".is_numeric($ids_arr[$i]).PHP_EOL;
            file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
        }
        if(is_numeric($ids_arr[$i]))
        {
            require_once('modules/xrSalesOrder/xrSalesOrder.php');
            require_once('include/utils/utils.php');
            require_once('modules/xSalesOrder/utils/EditViewUtils.php');
            require_once('modules/xSalesOrder/utils/SalesOrderUtils.php');
            require_once('modules/xSalesOrder/xSalesOrder.php');
            require_once('include/database/PearDatabase.php');
            require_once('include/TransactionSeries.php');
            require_once('include/WorkflowBase.php');
            require_once('config.salesorder.php');
            //require_once('data/CRMEntity.php');
            
            $soid = $ids_arr[$i];
            $module = 'xSalesOrder';
            $status_flag = true;
            $distArr = getDistrIDbyUserID();
            $so_focus = new xrSalesOrder();
            $focus = new xSalesOrder();
            $so_focus->id = $soid;
            $so_focus->retrieve_entity_info($soid, "xrSalesOrder"); //echo "Hi ".print_r($so_focus->column_fields['status']);exit;
            $is_converted = toCheckIsConverted($soid, "xrSalesOrder");
            if($is_converted){
                unset($ids_arr[$i]);
                $skip_cnt++;
                $adb->mquery("UPDATE vtiger_xrsocf set cf_xrso_next_stage_name=? where salesorderid=?",array('',$so_focus->id));
                $adb->mquery("UPDATE vtiger_xrso set status=?,is_processed=? where salesorderid=?",array('Processed',2,$so_focus->id));
                continue;
            }
            $focus = getConvertRSoToso($focus, $so_focus, $soid,'so',$auto_rsotoso);
            if($auto_rsotoso){
                $logco = "RSO Insert : ".PHP_EOL;
                $logco .= "RSO Array : ".  print_r($so_focus->column_fields, TRUE).PHP_EOL;
                $logco .= "DISTARR : ". print_r($distArr, TRUE).PHP_EOL;
                file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
            }
            if(empty($distArr) && $auto_rsotoso == 1)
            {
                $dist_code = $so_focus->column_fields['cf_xrso_seller_id'];
                $qry = $adb->pquery("SELECT xdistributorid FROM `vtiger_xdistributor` WHERE `distributorcode` = ?",array($dist_code));
                $distArr['id'] = $adb->query_result($qry, 0, "xdistributorid");
            }
            
            if($so_focus->column_fields['status'] == 'Processed' || $so_focus->column_fields['status'] == 'Rejected')
            {
                $skip_cnt++;
                continue;
            }
            if(!$conv_to_si)
            {
                $adb->mquery("UPDATE vtiger_xrsocf set cf_xrso_next_stage_name=? where salesorderid=?",array($ns1['cf_workflowstage_next_stage'],$soid));
                $adb->mquery("UPDATE vtiger_xrso set status=? where salesorderid=?",array($ns1['cf_workflowstage_next_content_status'],$soid));
                continue;
            }
            
            $focus->column_fields['requisition_no'] = $so_focus->column_fields['requisition_no'];
            $focus->column_fields['tracking_no'] = $so_focus->column_fields['tracking_no'];
            $focus->column_fields['adjustment'] = $so_focus->column_fields['adjustment'];
            $focus->column_fields['salescommission'] = $so_focus->column_fields['salescommission'];
            $focus->column_fields['exciseduty'] = $so_focus->column_fields['exciseduty'];
            $focus->column_fields['total'] = (($so_focus->column_fields['total'] != '') ? numberformat($so_focus->column_fields['total'],6) : '0.000000');
            $focus->column_fields['subtotal'] = (($so_focus->column_fields['subtotal'] != '') ? numberformat($so_focus->column_fields['subtotal'],6) : '0.000000');
            $focus->column_fields['type'] = $so_focus->column_fields['type'];
            $focus->column_fields['taxtype'] = $so_focus->column_fields['taxtype'];
            $focus->column_fields['discount_percent'] = (($so_focus->column_fields['discount_percent'] != '') ? numberformat($so_focus->column_fields['discount_percent'],6) : '0.000000');
            $focus->column_fields['discount_amount'] = (($so_focus->column_fields['discount_amount'] != '') ? numberformat($so_focus->column_fields['discount_amount'],6) : '0.000000');
            $focus->column_fields['s_h_amount'] = (($so_focus->column_fields['s_h_amount'] != '') ? numberformat($so_focus->column_fields['s_h_amount'],6) : '0.000000');
            $focus->column_fields['cf_xsalesorder_reason'] = $so_focus->column_fields['cf_xrsalesorder_reason'];
//            $defaultTrans = getDefaultTransactionSeries("Sales Order");                
//            $focus->column_fields['cf_salesorder_transaction_series'] = $defaultTrans['xtransactionseriesid'];
            
            //added to set the PO number and terms and conditions
            $focus->column_fields['terms_conditions'] = $so_focus->column_fields['terms_conditions'];
            $customer_id = $so_focus->column_fields['buyerid'];
            if( $so_focus->column_fields['customer_type'] == 1){
                $receivedcus_id = getReceivedCusId($customer_id);
                $customer_id    = $receivedcus_id['cus_id'];  
            }
            $focus->column_fields['buyerid'] = $customer_id;
            $salesmanBeat = getSalesmanBeat($customer_id);
            $focus->column_fields['cf_xsalesorder_beat'] = $salesmanBeat['beat_id'];
            if($so_focus->column_fields['cf_xrso_beat'] !='' || $so_focus->column_fields['cf_xrso_beat'] != null){
                $focus->column_fields['cf_xsalesorder_beat'] = $so_focus->column_fields['cf_xrso_beat'];
            }
            $focus->column_fields['cf_xsalesorder_sales_man'] = $salesmanBeat['salesman_id'];
            if($so_focus->column_fields['cf_xrso_sales_man'] !='' || $so_focus->column_fields['cf_xrso_sales_man'] != null){
                $focus->column_fields['cf_xsalesorder_sales_man'] = $so_focus->column_fields['cf_xrso_sales_man'];
            }
            
            if($so_focus->column_fields['cf_xrso_credit_term'] == ''){
                $focus->column_fields['cf_xsalesorder_credit_term'] = $focus->column_fields['cf_xsalesorder_credit_term'];
            }else{
                $focus->column_fields['cf_xsalesorder_credit_term'] = $so_focus->column_fields['cf_xrso_credit_term'];
            }
            
            //$focus->column_fields['cf_xsalesorder_credit_term'] = $so_focus->column_fields['cf_xrso_credit_term'];
            $focus->column_fields['carrier'] = $so_focus->column_fields['carrier'];
            $focus->column_fields['cf_xsalesorder_billing_address_pick'] = $so_focus->column_fields['cf_xrsalesorder_billing_address_pick'];
            $focus->column_fields['cf_xsalesorder_shipping_address_pick'] = $so_focus->column_fields['cf_xrsalesorder_shipping_address_pick'];
            $so_focus->column_fields['cf_salesorder_sales_order_date'] = str_replace('/', '-', $so_focus->column_fields['cf_salesorder_sales_order_date']);
            $focus->column_fields['description'] = $so_focus->column_fields['description'];

            $BillAdd = getdefaultaddress('Billing',$customer_id);
            if (!empty($BillAdd))
            {
                $focus->column_fields['cf_xsalesorder_billing_address_pick'] = $BillAdd['xaddressid'];
                $focus->column_fields['bill_street'] = $BillAdd['cf_xaddress_address'];
                $focus->column_fields['bill_pobox'] = $BillAdd['cf_xaddress_po_box'];
                $focus->column_fields['bill_city'] = $BillAdd['cf_xaddress_city'];
                $focus->column_fields['bill_state'] = $BillAdd['statename'];
                $focus->column_fields['bill_code'] = $BillAdd['cf_xaddress_postal_code'];
                $focus->column_fields['bill_country'] = $BillAdd['cf_xaddress_country'];
            }

            $ShipAdd = getdefaultaddress('Shipping',$customer_id);
            if (!empty($ShipAdd))
            {
                $focus->column_fields['cf_xsalesorder_shipping_address_pick'] = $ShipAdd['xaddressid'];
                $focus->column_fields['ship_street'] = $ShipAdd['cf_xaddress_address'];
                $focus->column_fields['ship_pobox'] = $ShipAdd['cf_xaddress_po_box'];
                $focus->column_fields['ship_city'] = $ShipAdd['cf_xaddress_city'];
                $focus->column_fields['ship_state'] = $ShipAdd['statename'];
                $focus->column_fields['ship_code'] = $ShipAdd['cf_xaddress_postal_code'];
                $focus->column_fields['ship_country'] = $ShipAdd['cf_xaddress_country'];
                $focus->column_fields['gstinno'] = $ShipAdd['gstinno'];
                
            }
            //Added to display the SalesOrder's associated vtiger_products -- when we create vtiger_invoice from SO DetailView
            $txtTax = (($so_focus->column_fields['txtTax'] != '') ? numberformat($so_focus->column_fields['txtTax'],6) : '0.000000');
            $txtAdj = (($so_focus->column_fields['txtAdjustment'] != '') ? numberformat($so_focus->column_fields['txtAdjustment'],6) : '0.000000');

            setObjectValuesFromRequest($focus);
            $focus->update_prod_stock='';
            if($focus->column_fields['status'] == 'Received Shipment')
            {
                $prev_postatus=getPoStatus($focus->id);
        	if($focus->column_fields['status'] != $prev_postatus)
        	{
        	        $focus->update_prod_stock='true';
        	}
            }

            $focus->column_fields['currency_id'] = $so_focus->column_fields['currency_id'];
            $cur_sym_rate = getCurrencySymbolandCRate($so_focus->column_fields['currency_id']);
            $focus->column_fields['conversion_rate'] = $cur_sym_rate['rate'];

            $posAction = "Submit";
            $ns = getNextstageByPosAction($module,$posAction);
            $focus->column_fields['cf_xsalesorder_next_stage_name'] = $ns['cf_workflowstage_next_stage'];
            $focus->column_fields['status'] = $ns['cf_workflowstage_next_content_status'];

            $focus->column_fields['assigned_user_id'] = $so_focus->column_fields['assigned_user_id'];

//            $transQry = "SELECT mt.xtransactionseriesid,mt.transactionseriesname,rt.xtransactionseriesid,rt.cf_xtransactionseries_transaction_type,rt.cf_xtransactionseries_user_id FROM vtiger_xtransactionseries mt LEFT JOIN vtiger_xtransactionseriescf rt ON mt.xtransactionseriesid=rt.xtransactionseriesid LEFT JOIN vtiger_crmentity ct ON mt.xtransactionseriesid=ct.crmid WHERE 
//ct.deleted=0 AND rt.cf_xtransactionseries_transaction_type='Sales Order' LIMIT 1";
//            $traArr = $adb->pquery($transQry);
//            $tArr = generateUniqueSeries($transactionseriesname='',"Sales Order");
//            $focus->column_fields['cf_salesorder_transaction_number'] = $tArr['uniqueSeries'];

            $focus->column_fields['cf_xsalesorder_seller_id'] = $distArr['id'];

            $focus->column_fields['discount_amount'] = (($so_focus->column_fields['hdnDiscountAmount'] != '') ? numberformat($so_focus->column_fields['hdnDiscountAmount'],6) : '0.000000');
            $focus->column_fields['hdnDiscountAmount'] = (($so_focus->column_fields['hdnDiscountAmount'] != '') ? numberformat($so_focus->column_fields['hdnDiscountAmount'],6) : '0.000000');
            $focus->column_fields['cf_xsalesorder_buyer_id'] = $customer_id;
            
            $rsi_result = $adb->mquery("select * from vtiger_xrsoproductrel where id='".$so_focus->id."' order by sequence_no");
            
            /*
             *  If No Detail rows then skipping that order
             */
            
            if($adb->num_rows($rsi_result)<=0)
            {
                continue;
            }
           
            /*
             *  If Customer is not there then skip that order
             */
            
            if($focus->column_fields['cf_xsalesorder_buyer_id']=='' || $focus->column_fields['cf_xsalesorder_buyer_id']<=0)
            {
                continue;
            }
            
            $BS_is_converted = toCheckIsConverted($soid, "xrSalesOrder");
            if(!empty($BS_is_converted)){
                $adb->mquery("UPDATE vtiger_xrsocf set cf_xrso_next_stage_name=? where salesorderid=?",array('',$so_focus->id));
                $adb->mquery("UPDATE vtiger_xrso set status=?,is_processed=? where salesorderid=?",array('Processed',2,$so_focus->id));
                continue;
            }
            
            
                      
                      
            $xrso_tracking_no = $adb->mquery("select tracking_no,so.salesorderid from vtiger_xsalesorder so inner join vtiger_xsalesordercf socf on so.salesorderid=socf.salesorderid  where so.tracking_no='".$so_focus->column_fields['tracking_no']."' AND socf.cf_xsalesorder_seller_id='".$so_focus->column_fields['cf_xsalesorder_seller_id']."' ");
            //$adb->query_result($xrso_tracking_no,0,'tracking_no');
            if($adb->num_rows($xrso_tracking_no) > 0){
                
               $salesOrderId = $adb->query_result($xrso_tracking_no,0,'salesorderid');
               if($salesOrderId!=''){ 
                    $adb->mquery('UPDATE vtiger_xrso so 
                         INNER JOIN  vtiger_xrsocf socf ON so.salesorderid= socf.salesorderid 
                         SET so.status="Published",socf.cf_xrso_next_stage_name="",socf.cf_xrsalesorder_reason="'.$salesOrderId.' This receive SO already converted"
                         WHERE so.salesorderid=?', array($so_focus->id)); 
               }
               return;
            }
                  
            
            $focus->save("xSalesOrder");
            $return_id = $focus->id;

            $adb->mquery("UPDATE vtiger_xsalesorder set salesorder_status=?,status=? where salesorderid=?",array('Open Order',$focus->column_fields['status'],$return_id));
            
            $upquery1 = "UPDATE vtiger_crmentity set modifiedtime=now() WHERE setype = 'xSalesOrder' AND crmid IN (".$return_id.")";
        	$adb->mquery($upquery1);

            $adb->mquery("UPDATE vtiger_xsalesordercf set cf_xsalesorder_next_stage_name=?,cf_salesorder_transaction_number=? where salesorderid=?",array($focus->column_fields['cf_xsalesorder_next_stage_name'],$focus->column_fields['cf_salesorder_transaction_number'],$return_id));
            if($SO_LBL_CURRENCY_OPTION_ENABLE!="True") {
                $updateQry = "UPDATE `vtiger_xsalesorder` SET `currency_id` = '1' WHERE `salesorderid` = ".$return_id;
                $adb->mquery($updateQry);
            }

            if($SO_LBL_TAX_OPTION_ENABLE!="True") {
                $updateQry2 = "UPDATE `vtiger_xsalesorder` SET `taxtype` = 'individual' WHERE `salesorderid` = ".$return_id;
                $adb->mquery($updateQry2);
            }

            /*
             *  Below line commented & copied to before saving the object
             *  @kami
             */    
            
            //$rsi_result = $adb->pquery("select * from vtiger_xrsoproductrel where id='".$so_focus->id."' order by sequence_no");
            
            $net_total_val = 0.0;
            if($adb->num_rows($rsi_result) > 0)
            {
                for ($index = 0; $index < $adb->num_rows($rsi_result); $index++) 
                {
                    $id = $adb->query_result($rsi_result,$index,'id');
                    $productid = $adb->query_result($rsi_result,$index,'productid');
                    $productcode = $adb->query_result($rsi_result,$index,'productcode');
                    
                    $xprodhierid = $adb->query_result($rsi_result,$index,'xprodhierid');
                    
                    $shipping_address_pick = $so_focus->column_fields['cf_xrsalesorder_shipping_address_pick'];
					$transaction_date = $so_focus->column_fields['cf_salesorder_sales_order_date'];
					
                    if($LBL_SET_NETRATE == 'True'){
                        if($_REQUEST['return_module'] != 'xrSalesInvoice'){
                            $tax_details1 = getTaxDetailsForProduct($productid,'all','xSalesOrder',$distArr['id'],$customer_id,'','','',$shipping_address_pick,'',0,$transaction_date);
                                            
                        }
                        $tax_percentage=0.0;
                        $discInfo=$invDiscInfo=$amountBeforeTax=array();

                        for($is=0;$is<count($tax_details1);$is++)
                        { 
                           $tax_percentage = $tax_percentage + $tax_details1[$is]['percentage'];
                        }

                        $prod_uom = $adb->pquery("select cf_xproduct_base_uom from vtiger_xproductcf where xproductid='".$productid."' limit 1");
                        if($adb->query_result($rsi_result,$index,'tuom')!=$adb->query_result($prod_uom,0,'cf_xproduct_base_uom')){    
                        $uomconv = getuomconversion($productid,$adb->query_result($rsi_result,$index,'tuom'));
                        $slistprice=$adb->query_result($rsi_result,$index,'ptr')*$uomconv;
                        }
                        else{
                            $slistprice= $adb->query_result($rsi_result,$index,'ptr');
                        }
                        $productTotals = $adb->query_result($rsi_result,$index,'quantity') * $slistprice;                
                        $discount_percent=    $adb->query_result($rsi_result,$index,'discount_percent');
                        $discount_amount=   $adb->query_result($rsi_result,$index,'discount_amount');

                        if($discount_percent != 0.0 || $discount_amount!= 0.0){
                                            $discInfo['type'] = 'amount';
                                            $discInfo['value'] = $discount_amount;
                                            if($discount_percent != 0.0){
                                                $discInfo['type'] = 'percentage';
                                            $discInfo['value'] = $discount_percent;
                                            }                   
                         }
                         else
                             $discInfo['type'] = 'zero';

                             $taxPerc=$tax_percentage;
                             $amountBeforeTax[1] = $productTotals;
                             $netPrice = $productTotals;
                             $netPrices[1] = $productTotals;
                             $baseQty = $adb->query_result($rsi_result,$index,'quantity');
                             $invDiscInfo['type'] = 'zero';
                             $rowNo = $i;

                        $toSend=getPtrpricefromNetprice($netPrice,$baseQty,$taxPerc,$discInfo,$invDiscInfo,'','',$amountBeforeTax,$netPrices,$rowNo);  
                    }                    
                    $quantity = $adb->query_result($rsi_result,$index,'quantity');
                    $baseqty = $adb->query_result($rsi_result,$index,'baseqty');
                    $dispatchqty = $adb->query_result($rsi_result,$index,'dispatchqty');
                    $tuom = $adb->query_result($rsi_result,$index,'tuom');
                    if($baseqty=='' || $baseqty==NULL || $baseqty==0){
                        $uomconv = getuomconversion($productid,$tuom);
                       
                       if($uomconv=='' || $uomconv==NULL || $uomconv==0)
                           $uomconv=1;
                                                  
                     $baseqty=$quantity*$uomconv;
                    }
                    if($toSend['PTR'])
                        $listprice = $toSend['PTR'];
                        else
                    $listprice = $adb->query_result($rsi_result,$index,'listprice');
                    $discount_percent = $adb->query_result($rsi_result,$index,'discount_percent');
                    $discount_amount = $adb->query_result($rsi_result,$index,'discount_amount');
                    $comment = $adb->query_result($rsi_result,$index,'comment');
                    $description = $adb->query_result($rsi_result,$index,'description');
                    $incrementondel = $adb->query_result($rsi_result,$index,'incrementondel');
                    $tax1 = $adb->query_result($rsi_result,$index,'tax1');
                    $tax2 = $adb->query_result($rsi_result,$index,'tax2');
                    $tax3 = $adb->query_result($rsi_result,$index,'tax3');
                    $lineitem_id = $adb->query_result($rsi_result,$index,'lineitem_id');
                    $seq_no = ($index+1);
                    $basepriceptr=$adb->query_result($rsi_result,$index,'ptr');
                    $basepricemrp=$adb->query_result($rsi_result,$index,'mrp');
                    
                    if($tax1 == null || $tax1 == "")
                        $tax1 == 0.00;

                    if($quantity == '' || $quantity == null)
                        $quantity = 0.00;
                    
                    $quantity = (($quantity != '') ? numberformat($quantity,6) : '0.000000');
                    $baseqty = (($baseqty != '') ? numberformat($baseqty,6) : '0.000000');
                    $dispatchqty = (($dispatchqty != '') ? numberformat($dispatchqty,6) : '0.000000');
                    $listprice = (($listprice != '') ? numberformat($listprice,6) : '0.000000');
                    $discount_percent = (($discount_percent != '') ? numberformat($discount_percent,6) : '0.000000');
                    $discount_amount = (($discount_amount != '') ? numberformat($discount_amount,6) : '0.000000');
                    $tax1 = (($tax1 != '') ? numberformat($tax1,6) : '0.000000');
                    $tax2 = (($tax2 != '') ? numberformat($tax2,6) : '0.000000');
                    $tax3 = (($tax3 != '') ? numberformat($tax3,6) : '0.000000');
                    
                    $prod_serial_qry = $adb->pquery("SELECT if(track_serial_number='Yes', 1, 0) as track_serial FROM vtiger_xproduct where xproductid='$productid'");
                    $track_serial = $adb->query_result($prod_serial_qry,0,'track_serial');
                    
                    $pts = 0.000000;
                    $ptr = $listprice;
                    $mrp = 0.000000;
                    $ecp = 0.000000;
                    $si_sfqty = 0.000000;
                    $si_sqty = $quantity;
                    $pkg = '';
                    $expiry = '';
                    $batchnumber = '';

                    /* Insert Line Items */
                    $insert_qry = $adb->mquery("insert into vtiger_xsalesorderproductrel (id, productid, productcode, sequence_no, quantity, 
                    baseqty, dispatchqty, tuom, listprice, discount_percent, discount_amount, comment, description, incrementondel, tax1, tax2, tax3, siqty, ptr, mrp,xprodhierid) 
                    values ('$return_id', '$productid', '$productcode', '$seq_no', '$quantity', '$baseqty', '$dispatchqty', '$tuom', '$listprice', 
                    '$discount_percent', '$discount_amount', '$comment', '$description', '$incrementondel', '$tax1', '$tax2', '$tax3', '0.000000','$basepriceptr','$basepricemrp','$xprodhierid')");
                    $lineitem_id_val = $adb->getLastInsertID();
                    

                    /* Insert date in vtiger_xsalestransaction_batchinfo table (Batch Detail) */

                    $query ="insert into vtiger_xsalestransaction_batchinfo(transaction_id, trans_line_id, product_id, batch_no, pkd, expiry, transaction_type,sqty,sfqty, ptr, pts, mrp, ecp,ptr_type, distributor_id,track_serial) values(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                    $qparams = array($focus->id,$lineitem_id_val,$productid,"$batchcode","$pkg","$expiry","SO","$si_sqty","$si_sfqty","$ptr","$pts","$mrp","$ecp",'',$distArr['id'], '$track_serial');
                    $adb->mquery($query,$qparams);
					$shipping_address_pick = $so_focus->column_fields['cf_xrsalesorder_shipping_address_pick'];
					$transaction_date = $so_focus->column_fields['cf_salesorder_sales_order_date'];
					$wherUpdateColumn = '';

					if($ALLOW_GST_TRANSACTION){			
						$ret_shipping_gstno = $adb->pquery("SELECT xAdd.gstinno,xState.statecode from vtiger_xaddress xAdd INNER JOIN vtiger_xstate xState on xState.xstateid=xAdd.xstateid where xAdd.xaddressid=?",array($shipping_address_pick));

						if($adb->num_rows($ret_shipping_gstno)>0) {
							$buyer_gstinno = $adb->query_result($ret_shipping_gstno, 0, 'gstinno');
							$buyer_state = $adb->query_result($ret_shipping_gstno, 0, 'statecode');
							$wherUpdateColumn.= 'buyer_gstinno="'.$buyer_gstinno.'" , buyer_state="'.$buyer_state.'",';
						}else{
							$retaileState1=$adb->pquery('SELECT vtiger_xretailer.gstinno,xState.statecode FROM vtiger_xretailer INNER JOIN vtiger_xretailercf on vtiger_xretailercf.xretailerid=vtiger_xretailer.xretailerid LEFT JOIN vtiger_xstate xState on xState.xstateid=vtiger_xretailercf.cf_xretailer_state  where vtiger_xretailercf.xretailerid=?',array($focus->column_fields['buyerid']));
							$buyer_gstinno = $adb->query_result($retaileState1, 0, 'gstinno');
							$buyer_state = $adb->query_result($retaileState1, 0, 'statecode');
							$wherUpdateColumn.= 'buyer_gstinno="'.$buyer_gstinno.'" , buyer_state="'.$buyer_state.'",';
						}
						
						$xdis_gstno = $adb->pquery("SELECT xDis.gstinno,xState.statecode from vtiger_xdistributor xDis 
						INNER JOIN vtiger_xdistributorcf xDiscf on xDiscf.xdistributorid=xDis.xdistributorid
						INNER JOIN vtiger_xstate xState on xState.xstateid=xDiscf.cf_xdistributor_state where xDis.xdistributorid=".$distArr['id']);

						if($adb->num_rows($xdis_gstno)>0) {
							$seller_gstinno = $adb->query_result($xdis_gstno, 0, 'gstinno');
							$seller_state = $adb->query_result($xdis_gstno, 0, 'statecode');
							$wherUpdateColumn.= 'seller_gstinno="'.$seller_gstinno.'" , seller_state="'.$seller_state.'",';
						}
					}
					$is_taxfiled	= 0; 
					$trntaxtype		= ($ALLOW_GST_TRANSACTION) ? 'GST' : 'VAT'; 
					$wherUpdateColumn.= 'is_taxfiled="'.$is_taxfiled.'" , trntaxtype="'.$trntaxtype.'",';

					
					
					/* */
					 $net_total = 0;
                    if($listprice > 0 && $quantity > 0)
                        $net_total = $quantity*$listprice;
                    if($discount_amount > 0 && $net_total >= $discount_amount)
                        $net_total = $net_total - $discount_amount;
                    if($discount_percent > 0 && $net_total >= $discount_percent &&  $discount_percent <100){
                        $net_total = $net_total  - ($net_total * ($discount_percent / 100));
                    }elseif($discount_percent >0 &&  $discount_percent >=100){
                        $t = $net_total * ($discount_percent/100);
                        $net_total = $net_total - $t;
                    }
                   
					/* */
                    /* Insert Tax Detail */
	
                    $taxes_for_product = getTaxDetailsForProduct($productid,'all','xSalesOrder',$distArr['id'],$customer_id,'','','',$shipping_address_pick,'',0,$transaction_date);
					
					
                    $taxPerToApply=0.0;
                    $tax_total = 0.00;
                    for($tax_count=0;$tax_count<count($taxes_for_product);$tax_count++)
                    {
                            $tax_name = $taxes_for_product[$tax_count]['taxname'];
                            $tax_label = $taxes_for_product[$tax_count]['taxlabel'];
                            $request_tax_name = $taxes_for_product[$tax_count]['percentage'];
                            $tax_value=$request_tax_name;
							$tax_id 	= ($taxes_for_product[$tax_count]['taxid']) ? $taxes_for_product[$tax_count]['taxid'] : '';
							$taxgrouptype	= ($taxes_for_product[$tax_count]['taxgrouptype']) ? $taxes_for_product[$tax_count]['taxgrouptype'] : '';
							$xtaxapplicableon	= ($taxes_for_product[$tax_count]['xtaxapplicableon']) ? $taxes_for_product[$tax_count]['xtaxapplicableon'] : '';
                            $tax_value = (($tax_value != '') ? numberformat($tax_value,6) : '0.000000');
                            $tax_total += $tax_value;
                            
                            /*$createQuery="INSERT INTO sify_xtransaction_tax_rel (`transaction_id`,`lineitem_id`,`transaction_name`,`tax_type`,`tax_label`,`tax_percentage`,`transaction_line_id`) VALUES(?,?,?,?,?,?,?)";   
                            $adb->mquery($createQuery,array($focus->id,$productid,'xSalesOrder',"$tax_name","$tax_label","$tax_value",$lineitem_id_val));*/
							
							 if($ALLOW_GST_TRANSACTION){
							//		$tax_value	= $_REQUEST[$request_tax_name];
									$tax_amt =  ($net_total*$tax_value/100);
									$taxable_amt = $net_total; 
									insertXTransactionTaxInfo($focus->id,$productid,'xSalesOrder',$tax_name,$tax_label,$tax_value,$tax_amt,$taxable_amt,$lineitem_id_val,'sify_xtransaction_tax_rel_so',$tax_id,$taxgrouptype);
								}
                    }
                    
                    if($tax_total > 0)
                        $net_total = $net_total  + ($net_total * ($tax_total / 100));
                    
					$net_total = formatCurrencyDecimals($net_total);
                    //echo '<pre>';
                    //print_r(array($listprice,$quantity,$tax_total,$discount_amount,$discount_percent,$net_total,$net_total_val));
                    
                    $net_total_val += $net_total;
                }
            }//echo "Hi :".$quantity.", ".$listprice.", ".$tax_total;exit;
//            echo $net_total_val;
//            
//            die;
            
            
           
            global $SO_LBL_SALESMAN_PROD_ALLOW, $SO_LBL_PRODCATGRP_MAND;
            $update_str = "";
            if($SO_LBL_SALESMAN_PROD_ALLOW == 'True' && $SO_LBL_PRODCATGRP_MAND == 'True')
            {
                $cf_xsalesorder_sales_man = $focus->column_fields['cf_xsalesorder_sales_man'];
                if($cf_xsalesorder_sales_man > 0)
                {
                    $cg_id_qry =$adb->pquery("select cf_xsalesman_product_category_group from vtiger_xsalesmancf where xsalesmanid=$cf_xsalesorder_sales_man");
                    $cg_id = $adb->query_result($cg_id_qry, 0, "cf_xsalesman_product_category_group");
                    $cg_id = str_replace(" |##| ", ",", $cg_id);
                    
                    if($cg_id != '')
                    {
                        /*$pcg_id_query = $adb->pquery("SELECT vtiger_xcategorygroup.xcategorygroupid FROM vtiger_xcategorygroup
                            INNER JOIN vtiger_xcategorygroupcf ON vtiger_xcategorygroupcf.xcategorygroupid = vtiger_xcategorygroup.xcategorygroupid 
                            INNER JOIN vtiger_xproductcategorygroupmapping ON vtiger_xproductcategorygroupmapping.productcategorygroup = vtiger_xcategorygroup.xcategorygroupid 
                            INNER JOIN vtiger_xproductcategorygroupmappingcf ON vtiger_xproductcategorygroupmappingcf.xproductcategorygroupmappingid = vtiger_xproductcategorygroupmapping.xproductcategorygroupmappingid 
                            INNER JOIN vtiger_xdistributorclusterrel ON vtiger_xdistributorclusterrel.distclusterid = vtiger_xproductcategorygroupmapping.distributorcluster 
                            INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xcategorygroup.xcategorygroupid
                            LEFT JOIN vtiger_crmentityrel ON (vtiger_crmentityrel.crmid = vtiger_xproductcategorygroupmapping.xproductcategorygroupmappingid 
                                AND vtiger_crmentityrel.module='xProductCategoryGroupMapping' AND vtiger_crmentityrel.relmodule='PGDistributorRevoke')
                            LEFT JOIN vtiger_pgdistributorrevoke ON (vtiger_pgdistributorrevoke.pgdistributorrevokeid = vtiger_crmentityrel.relcrmid 
                                AND vtiger_pgdistributorrevoke.active=1)
                            where vtiger_xcategorygroup.xcategorygroupid IN ($cg_id) AND vtiger_crmentity.deleted=0 AND vtiger_xcategorygroup.active=1 
                            AND vtiger_xproductcategorygroupmappingcf.cf_xproductcategorygroupmapping_effective_from_date <= CURDATE() 
                            AND vtiger_xproductcategorygroupmapping.active=1  
                            AND if(vtiger_pgdistributorrevoke.revokedate is null || vtiger_pgdistributorrevoke.revokedate = '',CURDATE(), vtiger_pgdistributorrevoke.revokedate) >= CURDATE() 
                            group by vtiger_xcategorygroup.xcategorygroupid order by vtiger_xcategorygroup.xcategorygroupid limit 0,1");*/
                        
                        $pcg_id_query = $adb->pquery("SELECT vtiger_xcategorygroup.xcategorygroupid FROM vtiger_xcategorygroup
                            INNER JOIN vtiger_xcategorygroupcf ON vtiger_xcategorygroupcf.xcategorygroupid = vtiger_xcategorygroup.xcategorygroupid 
                            INNER JOIN vtiger_xproductcategorygroupmapping ON vtiger_xproductcategorygroupmapping.productcategorygroup = vtiger_xcategorygroup.xcategorygroupid 
                            INNER JOIN vtiger_xproductcategorygroupmappingcf ON vtiger_xproductcategorygroupmappingcf.xproductcategorygroupmappingid = vtiger_xproductcategorygroupmapping.xproductcategorygroupmappingid 
                            INNER JOIN vtiger_xdistributorclusterrel ON vtiger_xdistributorclusterrel.distclusterid = vtiger_xproductcategorygroupmapping.distributorcluster 
                            INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xcategorygroup.xcategorygroupid 
                            where vtiger_xcategorygroup.xcategorygroupid IN ($cg_id) AND vtiger_crmentity.deleted=0 AND vtiger_xcategorygroup.active=1 
                            AND vtiger_xproductcategorygroupmappingcf.cf_xproductcategorygroupmapping_effective_from_date <= CURDATE() 
                            AND vtiger_xproductcategorygroupmapping.active=1  
                            group by vtiger_xcategorygroup.xcategorygroupid order by vtiger_xcategorygroup.xcategorygroupid limit 0,1");                        
                        $pcg_id = $adb->query_result($pcg_id_query, 0, "xcategorygroupid");
                        if($pcg_id > 0)
                            $update_str = ", xproductgroupid=$pcg_id";
                    }
                    
                }
            }
            $upd_net_total_qry="update vtiger_xsalesorder set $wherUpdateColumn total=?, subtotal=? $update_str where salesorderid=?";
            $qparams = array($net_total_val, $net_total_val, $focus->id);
            $adb->mquery($upd_net_total_qry,$qparams);
            
            $insertrel="insert into vtiger_crmentityrel(crmid, module, relcrmid, relmodule) values(?,?,?,?)";
            $qparams = array($soid,'xrSalesOrder',$focus->id,$module);
            $adb->mquery($insertrel,$qparams);
            //echo "Hi123 :".print_r($_REQUEST['button']);exit;
            if($status_flag)
            {
                /* Workflow Concepts */
                $moduleWf='SalesOrder';
                $posAction = "Submit";

                $ns = getNextstageByPosAction($moduleWf,$posAction);
                $statusa = $ns['cf_workflowstage_next_stage'];
                $nextStage = $ns['cf_workflowstage_next_content_status'];    
                $businessLogic = $ns['cf_workflowstage_business_logic'];
                $SAtype='';
                $fromType='Save';
                $so_record = '';
                $redirect = 'no';

                workflowBisLogicMain($return_id,$moduleWf,$distArr['id'],$statusa,$nextStage,$businessLogic,$SAtype,$fromType, $redirect);
                $generateUniqueSeries = TRUE;
				$generateDefaultTrans = TRUE;
			    $increment = TRUE;
                $transaction_series=$so_focus->column_fields['cf_salesorder_transaction_series'];
                $transaction_number=$so_focus->column_fields['cf_salesorder_transaction_number'];
                if(!empty($transaction_series)){
                    $checkTransQuery =  "SELECT en.crmid FROM vtiger_xtransactionseries mt 
                                        LEFT JOIN vtiger_xtransactionseriescf rt ON mt.xtransactionseriesid = rt.xtransactionseriesid 
                                        LEFT JOIN vtiger_crmentity en ON mt.xtransactionseriesid = en.crmid 
                                        WHERE mt.transactionseriescode='".$transaction_series."' 
                                        AND rt.cf_xtransactionseries_transaction_type='Sales Order' 
                                        AND en.deleted=0 AND (mt.xdistributorid = '".$distArr['id']."' OR mt.xdistributorid =0)";
                    $checkTransSeries = $adb->mquery($checkTransQuery);
					 if($auto_rsotoso){
						file_put_contents($Resulrpatth1, 'Transaction qy:'.$checkTransQuery.PHP_EOL, FILE_APPEND);
					}
                    if($adb->num_rows($checkTransSeries)>0){
                            $focus->column_fields['cf_salesorder_transaction_series'] = $transaction_series;
                            $generateDefaultTrans = FALSE;

                    }else{
                        $transaction_series   =  '';
                    }
                } 
                if(!empty($transaction_number)){
                    if($LBL_USE_RECEIVED_TRANSACTION_NUMBER=='True'){
                        $focus->column_fields['cf_salesorder_transaction_number'] = $transaction_number;
                        $increment = FALSE;
                        $generateUniqueSeries =FALSE;
                    }
                }
                if($auto_rsotoso){
                    file_put_contents($Resulrpatth1, 'Transaction Series:'.$transaction_series.PHP_EOL, FILE_APPEND);
                }
                if(empty($transaction_series))
                {
                    $dist_code          = $so_focus->column_fields['cf_xrso_seller_id'];
                    $qry                = $adb->mquery("SELECT xdistributorid FROM `vtiger_xdistributor` WHERE `distributorcode` = ?",array($dist_code));
                    $distid             = $adb->query_result($qry, 0, "xdistributorid");
                    $transQry	="SELECT 
                                        mt.xtransactionseriesid,
                                        mt.transactionseriesname,
                                        rt.xtransactionseriesid,
                                        rt.cf_xtransactionseries_transaction_type,
                                        rt.cf_xtransactionseries_user_id 
                                        FROM vtiger_xtransactionseries mt 
                                        LEFT JOIN vtiger_xtransactionseriescf rt ON mt.xtransactionseriesid=rt.xtransactionseriesid
                                        LEFT JOIN vtiger_crmentity ct ON mt.xtransactionseriesid=ct.crmid 
                                        WHERE ct.deleted=0 
                                        AND rt.cf_xtransactionseries_transaction_type='Sales Order'";
                    if(!empty($distArr['id'])){ 
                       $transQry .= " AND mt.xdistributorid = '".$distid."'";
                    }
                    $transQry .= " ORDER BY rt.cf_xtransactionseries_mark_as_default DESC LIMIT 1";

                    $traArr                = $adb->mquery($transQry);
                    $transaction_series    = $traArr->fields['xtransactionseriesid'];

                }
                if($auto_rsotoso){
                    file_put_contents($Resulrpatth1, 'Transaction Series Distributor:'.$transaction_series.PHP_EOL, FILE_APPEND);
                }
                $tArr = generateUniqueSeries($transaction_series, "Sales Order",$increment);
                if($generateUniqueSeries){
                    $transaction_number = $tArr['uniqueSeries'];
                    $transaction_series = $tArr['xtransactionseriesid'];
                }
                if($auto_rsotoso){
                    file_put_contents($Resulrpatth1, 'Transaction Series transaction_number:'.$transaction_number.PHP_EOL, FILE_APPEND);
                }
                $adb->mquery("UPDATE vtiger_xsalesordercf set cf_xsalesorder_next_stage_name=?,cf_salesorder_transaction_series=?,cf_salesorder_transaction_number=? where salesorderid=?",array('Publish',$transaction_series,$transaction_number,$return_id));
                $adb->mquery("UPDATE vtiger_xsalesorder set status=? where salesorderid=?",array('Created',$return_id));

                $adb->mquery("UPDATE vtiger_xrsocf set cf_xrso_next_stage_name=? where salesorderid=?",array('',$so_focus->id));
                $adb->mquery("UPDATE vtiger_xrso set status=?,is_processed=? where salesorderid=?",array('Processed',2,$so_focus->id));
                
                $success_cnt++;
                if($auto_rsotoso){
                    $logco = "RSO_success_cnt : ".$success_cnt.PHP_EOL;
                    $logco .= "RSO_statusa : ".$statusa.PHP_EOL;
                    $logco .= "RSO_nextStage : ".$nextStage.PHP_EOL;
                    file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                }
            }
            else 
            {   
                $adb->mquery("UPDATE vtiger_xsalesordercf set cf_xsalesorder_next_stage_name=? where salesorderid=?",array('Creation',$return_id));
                $adb->mquery("UPDATE vtiger_xsalesorder set status=? where salesorderid=?",array('Draft',$return_id));

                $adb->mquery("UPDATE vtiger_xrsocf set cf_xrso_next_stage_name=? where salesorderid=?",array('',$so_focus->id));
                $adb->mquery("UPDATE vtiger_xrso set status=?,is_processed=? where salesorderid=?",array('Rejected',3,$so_focus->id));

                $fail_cnt++;
                if($auto_rsotoso){
                    $logco = "RSO_failcount : ".$fail_cnt.PHP_EOL;
                    $logco .= "RSO_line item zero : Rejected:".$statusa.PHP_EOL;
                    file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
               }
            }
                 
        }
        
    }
    if($tot_conv == 'No')
    {
        echo '<script type="text/javascript">$j.jAlert("<h3>Conversion Order Status</h3>Conversion Success : '.$success_cnt.'<br/>Conversion Fialure &nbsp&nbsp: '.$fail_cnt.'<br/>Conversion Skip &nbsp&nbsp&nbsp&nbsp&nbsp&nbsp: '.$skip_cnt.'<br/>","message",function(){window.location="index.php?action=ListView&module=xrSalesOrder&parenttab=MobileIntegration"}); </script>';   
        exit;
    }
}


function convert_rcol_to_col($ids, $tot_conv = 'No')
{
    global $current_user,$adb,$LBL_USE_RECEIVED_TRANSACTION_NUMBER;
    
    $ids_arr = explode(";", $ids);
    $skip_cnt = 0;
    $success_cnt = 0;
    $fail_cnt = 0;
    $posAction = $_REQUEST['stage_v'];
    if($tot_conv == 'Yes')
        $posAction = 'Create COLLECTION';
    $ns1 = getNextstageByPosAction('xrcollection',$posAction);
    $conv_to_si = false;
    if($ns1['cf_workflowstage_business_logic'] == 'Forward to Collection')
    {
        $conv_to_si = true;
    }
    //echo "Hi :".$ns1['cf_workflowstage_next_stage'].", ".$ns1['cf_workflowstage_business_logic'].", ".$posAction;exit;
    for($i=0; $i<count($ids_arr); $i++)
    {
        if(is_numeric($ids_arr[$i]))
        {
            require_once('modules/xrCollection/xrCollection.php');
            require_once('include/utils/utils.php');
            require_once('modules/xCollection/xCollection.php');
            require_once('include/database/PearDatabase.php');
            require_once('include/TransactionSeries.php');
            require_once('include/WorkflowBase.php');
             
            $soid = $ids_arr[$i];
            
            $module = 'xCollection';
            $status_flag = true;
            $distArr = getDistrIDbyUserID();
            $so_focus = new xrCollection();
            $focus = new xCollection();
            $so_focus->id = $soid;
            $so_focus->retrieve_entity_info($soid, "xrCollection"); //echo "Hi ".print_r($so_focus->column_fields['cf_xrco_status']);exit;
			$is_converted = toCheckIsConverted($soid, "xrCollection");
			if($is_converted){
                unset($ids_arr[$i]);
                $skip_cnt++;
                $adb->pquery("UPDATE vtiger_xrcocf set cf_xrco_next_stage_name='', cf_xrco_status=? where xrcoid=?",array('Processed',$so_focus->id));
                $adb->pquery("UPDATE vtiger_xrco SET is_processed=? WHERE xrcoid=?",array(2, $soid));
				continue;
            }
            $focus = getConvertRCOToCO($focus, $so_focus, $soid,'so');
            if($so_focus->column_fields['cf_xrco_status'] == 'Processed' || $so_focus->column_fields['cf_xrco_status'] == 'Rejected')
            {
                $skip_cnt++;
                continue;
            }
            if(!$conv_to_si)
            {
                $adb->pquery("UPDATE vtiger_xrcocf set cf_xrco_next_stage_name=?, cf_xrco_status=? where xrcoid=?",array($ns1['cf_workflowstage_next_stage'], $ns1['cf_workflowstage_next_content_status'],$soid));
                continue;
            }
           if($focus->column_fields['cf_xcollection_recieved_balance'] == 0 || $focus->column_fields['cf_xcollection_recieved_balance'] == NULL){
               $check_received_amount=$adb->mquery("SELECT loadtype,recordid,amount_adjust,addl_adjust,discount FROM vtiger_xrcodocrel WHERE xrcoid=$soid");
               if($adb->num_rows($check_received_amount)>0){
                   continue;
               }
           }
            $focus->column_fields['cf_xcollection_reason'] = '';
          //  $defaultTrans = getDefaultTransactionSeries("Collection");                
          //  $focus->column_fields['cf_xcollection_transaction_series'] = $defaultTrans['xtransactionseriesid'];
            
            $so_focus->column_fields['cf_xcollection_collection_date'] = str_replace('/', '-', $so_focus->column_fields['cf_xrco_collection_date']);
            $focus->column_fields['description'] = $so_focus->column_fields['description'];

            $posAction = "Submit";
            $ns = getNextstageByPosAction($module,$posAction);
            $focus->column_fields['cf_xcollection_next_stage_name'] = $ns['cf_workflowstage_next_stage'];
            $focus->column_fields['cf_xcollection_status'] = $ns['cf_workflowstage_next_content_status'];

            $focus->column_fields['assigned_user_id'] = $so_focus->column_fields['assigned_user_id'];

//            $transQry = "SELECT mt.xtransactionseriesid,mt.transactionseriesname,rt.xtransactionseriesid,rt.cf_xtransactionseries_transaction_type,rt.cf_xtransactionseries_user_id FROM vtiger_xtransactionseries mt LEFT JOIN vtiger_xtransactionseriescf rt ON mt.xtransactionseriesid=rt.xtransactionseriesid LEFT JOIN vtiger_crmentity ct ON mt.xtransactionseriesid=ct.crmid WHERE 
//ct.deleted=0 AND rt.cf_xtransactionseries_transaction_type='Collection' LIMIT 1";
//            $traArr = $adb->pquery($transQry);  
          //  $tArr = generateUniqueSeries($transactionseriesname='',"Collection");
         //   $focus->column_fields['cf_xcollection_transaction_number'] = $tArr['uniqueSeries'];
			$generateUniqueSeries = TRUE;
			$generateDefaultTrans = TRUE;
			$increment = TRUE;
			$transaction_series=$so_focus->column_fields['cf_xrco_transaction_series'];
			$transaction_number=$so_focus->column_fields['cf_xrco_transaction_number'];
			if(!empty($transaction_series)){
				$checkTransQuery	= 	"SELECT en.crmid FROM vtiger_xtransactionseries mt 
							LEFT JOIN vtiger_xtransactionseriescf rt ON mt.xtransactionseriesid = rt.xtransactionseriesid 
							LEFT JOIN vtiger_crmentity en ON mt.xtransactionseriesid = en.crmid 
							WHERE mt.transactionseriescode='".$transaction_series."' 
							AND rt.cf_xtransactionseries_transaction_type='Collection' 
							AND en.deleted=0 AND (mt.xdistributorid = '".$distArr['id']."' OR mt.xdistributorid =0)";
				$checkTransSeries = $adb->pquery($checkTransQuery);
				if($adb->num_rows($checkTransSeries)>0){
					$focus->column_fields['cf_xcollection_transaction_series'] = $transaction_series;
					$generateDefaultTrans = FALSE;
				}else{
					 $transaction_series   =  '';
				}
			}/* else{
				$generateDefaultTrans = FALSE;
			} */
			if(!empty($transaction_number)){
				if($LBL_USE_RECEIVED_TRANSACTION_NUMBER=='True'){
					$focus->column_fields['cf_xcollection_transaction_number'] = $transaction_number;
					$increment = FALSE;
					$generateUniqueSeries= FALSE;
				}
			}  
			
			
				$tArr = generateUniqueSeries($transaction_series='', "Collection",$increment);
				if($generateUniqueSeries)
					$focus->column_fields['cf_xcollection_transaction_number'] = $tArr['uniqueSeries'];
				
				$focus->column_fields['cf_xcollection_transaction_series'] = $tArr['xtransactionseriesid'];
			
		/* 	if($generateDefaultTrans){
				$defaultTrans = getDefaultTransactionSeries("Collection");                
				$focus->column_fields['cf_xcollection_transaction_series'] = $defaultTrans['xtransactionseriesid'];
			} */
            $focus->column_fields['cf_xcollection_distributor'] = $distArr['id'];
			$BS_is_converted = toCheckIsConverted($soid, "xrCollection");
            if(!empty($BS_is_converted)){
                 $adb->pquery("UPDATE vtiger_xrcocf set cf_xrco_next_stage_name='', cf_xrco_status=? where xrcoid=?",array('Processed',$so_focus->id));
                $adb->pquery("UPDATE vtiger_xrco SET is_processed=? WHERE xrcoid=?",array(2, $soid));
                continue;
            }
            $focus->save("xCollection");
            $return_id = $focus->id;
            
            $updateQry = "UPDATE vtiger_xcollectioncf SET cf_xcollection_transaction_number='".$focus->column_fields['cf_xcollection_transaction_number']."',cf_xcollection_distributor='".$focus->column_fields['cf_xcollection_distributor']."' WHERE xcollectionid=".$return_id;
            $adb->pquery($updateQry);
            
            
            
            
            
//            //pk salesinvoice data get
//            $saleinvoice = array();
//            $distid = getDistrIDbyUserID();
//            $querysaleinvoice="select CONCAT(si.salesinvoiceid,'::Pending') as `sidata` from vtiger_salesinvoice as si INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=si.salesinvoiceid left join vtiger_salesinvoicecf as sicf on sicf.salesinvoiceid=si.salesinvoiceid where vtiger_crmentity.deleted=0 and si.status NOT LIKE '%Draft%' and sicf.cf_salesinvoice_outstanding > 0 and si.vendorid=".$focus->column_fields['cf_xcollection_customer_name'];
//            $resultsaleinvoice = $adb->pquery($querysaleinvoice);
//            $salescount = $adb->num_rows($resultsaleinvoice);
//            for($j=0;$j<$adb->num_rows($resultsaleinvoice);$j++){
//                $saleinvoice[]=$adb->query_result($resultsaleinvoice,$j,0);
//            }
//            $querydebitnote="select CONCAT(sr.xdebitnoteid,'::Debit Note') as `srdata` from vtiger_xdebitnote as sr INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=sr.xdebitnoteid left join vtiger_xdebitnotecf as srcf on srcf.xdebitnoteid=sr.xdebitnoteid where vtiger_crmentity.deleted=0 and srcf.cf_xdebitnote_status='Active' and (CAST(sr.amount AS UNSIGNED) > CAST(srcf.cf_xdebitnote_debit_note_adjusted AS UNSIGNED)) and sr.distributor_id=".$distid['id']." and sr.partyname=".$focus->column_fields['cf_xcollection_customer_name'];
//            $resultdebitnote = $adb->pquery($querydebitnote);
//            for($j=0;$j<$adb->num_rows($resultdebitnote);$j++){
//                $saleinvoice[]=$adb->query_result($resultdebitnote,$j,0);
//            }
//            
//            //pk salesreturn data get
//            $querysalereturn="select CONCAT(sr.xsalesreturnid,'::Return') as `srdata` from vtiger_xsalesreturn as sr INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=sr.xsalesreturnid left join vtiger_xsalesreturncf as srcf on srcf.xsalesreturnid=sr.xsalesreturnid where vtiger_crmentity.deleted=0 and (CAST(srcf.cf_xsalesreturn_amount AS UNSIGNED) > CAST(srcf.cf_xsalesreturn_adjustable_amount AS UNSIGNED)) and srcf.cf_xsalesreturn_customer=".$focus->column_fields['cf_xcollection_customer_name'];
//            $resultsalereturn = $adb->pquery($querysalereturn);
//
//            for($j=0;$j<$adb->num_rows($resultsalereturn);$j++){
//                $saleinvoice[]=$adb->query_result($resultsalereturn,$j,0);
//            }
//            $distid = getDistrIDbyUserID();
//            $querycreditnote="select CONCAT(sr.xcreditnoteid,'::Creditnote') as `srdata` from vtiger_xcreditnote as sr INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=sr.xcreditnoteid left join vtiger_xcreditnotecf as srcf on srcf.xcreditnoteid=sr.xcreditnoteid where vtiger_crmentity.deleted=0 and srcf.cf_xcreditnote_status='Active' and (CAST(srcf.cf_xcreditnote_amount AS UNSIGNED) > CAST(srcf.cf_xcreditnote_adjustable_amount AS UNSIGNED)) and srcf.cf_xcreditnote_distributor_id=".$distid['id']." and srcf.cf_xcreditnote_retailer=".$focus->column_fields['cf_xcollection_customer_name'];
//            $resultcreditnote = $adb->pquery($querycreditnote);
//            for($j=0;$j<$adb->num_rows($resultcreditnote);$j++){
//                $saleinvoice[]=$adb->query_result($resultcreditnote,$j,0);
//            }
//
//            $querycollreturn="select CONCAT(sr.xcollectionid,'::Collection') as `crdata` from vtiger_xcollection as sr INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=sr.xcollectionid left join vtiger_xcollectioncf as srcf on srcf.xcollectionid=sr.xcollectionid where vtiger_crmentity.deleted=0 and srcf.cf_xcollection_status NOT LIKE '%Draft%' and (CAST(srcf.cf_xcollection_amount_received AS UNSIGNED) > CAST(srcf.cf_xcollection_recieved_balance AS UNSIGNED)) and srcf.cf_xcollection_customer_name=".$focus->column_fields['cf_xcollection_customer_name']; 
//            $resultcollreturn = $adb->pquery($querycollreturn);
//            for($j=0;$j<$adb->num_rows($resultcollreturn);$j++){
//                $saleinvoice[]=$adb->query_result($resultcollreturn,$j,0);
//            }
//
//            $returncount = count($saleinvoice);
//            
//            for($j=0; $j<$returncount; $j++)
//            {   
//                $saleinvoicedata = explode("::", $saleinvoice[$j]);
//                $recordid = $saleinvoicedata[0];
//                $loadtype = $saleinvoicedata[1];
//                $amountadjustment = '0.000000';
//                $addladjustment = '0.000000';
//                $discountp = '0.000000';
//                //echo "Hi :".$recordid.", ".$loadtype.", ".$saleinvoicedata;exit;
//                $query ="insert into vtiger_xcollectiondocrel(xcollectionid, loadtype, recordid, amount_adjust,addl_adjust,discount) values(?,?,?,?,?,?)";
//                $qparams = array($return_id,$loadtype,$recordid,$amountadjustment,$addladjustment,$discountp);
//                $adb->pquery($query,$qparams);
//               
//            }
            
            $query ="insert into vtiger_xcollectiondocrel(xcollectionid, loadtype, recordid, amount_adjust,addl_adjust,discount)
                    SELECT $return_id,loadtype,recordid,amount_adjust,addl_adjust,discount FROM vtiger_xrcodocrel WHERE xrcoid=$soid";
            //$qparams = array($return_id,$loadtype,$recordid,$amountadjustment,$addladjustment,$discountp);
             $adb->pquery($query,array());
            $insertrel="insert into vtiger_crmentityrel(crmid, module, relcrmid, relmodule) values(?,?,?,?)";
            $qparams = array($soid,'xrCollection',$focus->id,$module);
            $adb->pquery($insertrel,$qparams);
            
            if($status_flag)
            {
                $adb->pquery("UPDATE vtiger_xcollectioncf set cf_xcollection_next_stage_name=?, cf_xcollection_status=? where xcollectionid=?",array('Publish', 'Created',$return_id));
                
                $adb->pquery("UPDATE vtiger_xrcocf set cf_xrco_next_stage_name='', cf_xrco_status=? where xrcoid=?",array('Processed',$so_focus->id));
                $adb->pquery("UPDATE vtiger_xrco SET is_processed=? WHERE xrcoid=?",array(2, $soid));
           
                $success_cnt++;
            }
            else 
            {   
                $adb->pquery("UPDATE vtiger_xcollectioncf set cf_xcollection_next_stage_name=?, cf_xcollection_status=? where salesorderid=?",array('Creation', 'Draft',$return_id));
                
                $adb->pquery("UPDATE vtiger_xrcocf set cf_xrco_next_stage_name='', cf_xrco_status=? where xrcoid=?",array('Rejected',$so_focus->id));
                $adb->pquery("UPDATE vtiger_xrco SET is_processed=? WHERE xrcoid=?",array(3, $soid));
                $fail_cnt++;
            }
            
            updateOutstanding('collection',$return_id);
              
        }
        
    }
    if($tot_conv == 'No')
    {
        echo '<script type="text/javascript">$j.jAlert("<h3>Conversion Order Status</h3>Conversion Success : '.$success_cnt.'<br/>Conversion Fialure &nbsp&nbsp: '.$fail_cnt.'<br/>Conversion Skip &nbsp&nbsp&nbsp&nbsp&nbsp&nbsp: '.$skip_cnt.'<br/>","message",function(){window.location="index.php?action=ListView&module=xrCollection&parenttab=MobileIntegration"}); </script>';   
        exit;
    }
}

function convert_rcus_to_cus($ids, $tot_conv = 'No',$mode){
    
    global $current_user,$adb;
    
    if($mode == 'Transaction'){
        $ids_arr = explode(";", $ids);
        $count   = count($ids_arr);
    }else{
        $ids_arr = $ids;
        $count = 1;
    }    
    $skip_cnt = 0;
    $success_cnt = 0;
    $fail_cnt = 0;
    $posAction = $_REQUEST['stage_v'];
    if($tot_conv == 'Yes')
        $posAction = 'Create Retailer';
    $ns1 = getNextstageByPosAction('xReceiveCustomerMaster',$posAction);
    $conv_to_cus = false;
    if($ns1['cf_workflowstage_business_logic'] == 'Forward to Retailer')
    {
        $conv_to_cus = true;
    }
    
    for($i=0; $i<$count; $i++)
    {
        ($mode == 'Transaction') ? $recid = $ids_arr[$i] : $recid = $ids_arr;
        
        if(is_numeric($recid))
        {
            require_once('modules/xReceiveCustomerMaster/xReceiveCustomerMaster.php');
            require_once('include/utils/utils.php');
            require_once('modules/xRetailer/xRetailer.php');
            require_once('include/database/PearDatabase.php');
            require_once('include/TransactionSeries.php');
            require_once('include/WorkflowBase.php');
            
            $module = 'xRetailer';
            $status_flag = true;
            $distArr = getDistrIDbyUserID();
            $rec_focus = new xReceiveCustomerMaster();
            $currentModule = 'xRetailer';
            $focus = CRMEntity::getInstance('xRetailer');
            $currentModule = 'TransactionProcess';
            $focus = new xRetailer();
            $rec_focus->id = $recid;
            $rec_focus->retrieve_entity_info($recid, "xReceiveCustomerMaster"); //echo "Hi ".print_r($so_focus->column_fields['cf_xrco_status']);exit;
            $InsertFlag = '';
            
            //Validation for Customer Channel 
            if($rec_focus->column_fields['flag'] > 0 && $rec_focus->column_fields['xretailer_channel_type'] != '') {
                $focusChennal = CRMEntity::getInstance('xChannelHierarchy');
                $query = $focusChennal->getComboPopUpListQuery('xChannelHierarchy', 'xRetailer',$rec_focus->column_fields['distributor_id']);
                $channelID = $rec_focus->column_fields['xretailer_channel_type'];
                if(($channelID > 0) && ($channelID != '')){
                    $query.=" AND vtiger_xchannelhierarchy.xchannelhierarchyid=".$channelID;
                }
                //print_r($query);die;
                $sql   = $adb->pquery($query);
                ($adb->num_rows($sql) > 0) ? $InsertFlag = TRUE : $InsertFlag = FALSE;
            }
            
            if($InsertFlag){
                $focus = getConvertRCUStoCUS($focus, $rec_focus, $recid,'recus',$mode); 
                $RetailerId = $focus->id;
                if($RetailerId > 0){
                    $custrealign_detailsid = 0;
                    $receivecust_result = $adb->pquery("SELECT flag FROM vtiger_xreceivecustomermaster WHERE `xreceivecustomermasterid` = '".$recid."' "); //   
                    $custrealign_detailsid = $adb->query_result($receivecust_result,0,'flag');
                    
                    $updateQry1 = "UPDATE vtiger_xreceivecustomermaster SET reference_id='".$RetailerId."',xretailer_status='Published',xretailer_next_stage_name='' WHERE xreceivecustomermasterid=".$recid;
                    $adb->pquery($updateQry1); 
                    
                    updateafterRealign($custrealign_detailsid,$RetailerId);
                }
            }
            // Beat Mapping to the Retailer
            if($rec_focus->column_fields['flag'] > 0 && $rec_focus->column_fields['xretailer_beat'] != '' && $RetailerId > 0) {
                $BeatCode = $rec_focus->column_fields['xretailer_beat'];
                $beat     = $adb->pquery("SELECT xbeatid FROM `vtiger_xbeat` WHERE `beatcode` = '".$BeatCode."'");   
                $BeatId   = $adb->query_result($beat,0,'xbeatid');
                $beat_qry = $adb->pquery("INSERT INTO `vtiger_crmentityrel` (`crmid`, `module`, `relcrmid`, `relmodule`) VALUES ($RetailerId,'xRetailer',$BeatId,'xBeat')");
            }
        }
    }
    
}
function updateafterRealign($custrealign_detailsid,$RetailerId){
    global $current_user,$adb;
    
    if($custrealign_detailsid > 0) {
        $oldcustid_result = $adb->pquery("SELECT cust_id,from_dist,to_dist FROM vtiger_xcustomerrealignmentdetails WHERE `xcustomerrealignmentdetailsid` = '".$custrealign_detailsid."' "); //   
        $oldcustid = $adb->query_result($oldcustid_result,0,'cust_id');
        $fromdistid = $adb->query_result($oldcustid_result,0,'from_dist');
        $todistid = $adb->query_result($oldcustid_result,0,'to_dist');

        $updateQry5 = "UPDATE `vtiger_xretailer` xret JOIN vtiger_xretailercf xretcf ON xret.xretailerid = xretcf.xretailerid  SET xret.realignment_flag ='2',xretcf.cf_xretailer_active='0' where xret.xretailerid='$oldcustid'";    
        $adb->pquery($updateQry5);

        $universalid_result = $adb->pquery("SELECT unique_retailer_code FROM vtiger_xretailer WHERE `xretailerid` = '".$oldcustid."' "); //   
        $universalid = $adb->query_result($universalid_result,0,'unique_retailer_code');

        $updateQry11 = "UPDATE `vtiger_xretailer` SET unique_retailer_code ='".$universalid."' where xretailerid='".$RetailerId."' ";    
        $adb->pquery($updateQry11);

        $updateQr7 = "UPDATE `vtiger_xcustomerrealignmentdetails` SET new_cust_id='".$RetailerId."' where xcustomerrealignmentdetailsid = '".$custrealign_detailsid."' ";    
        $adb->pquery($updateQr7);
    }
    
    $scheme_result = $adb->pquery("SELECT * from vtiger_xschemepoints  where xretailerid = ?  and xdistributorid =?  order by xschemepointsid desc limit 1", array($oldcustid,$fromdistid) );

    $schemeCnt     =  $adb->num_rows($scheme_result); 

    if($schemeCnt>0){
        $currentModule2 = 'xSchemePoints';
        checkFileAccess("modules/$currentModule2/$currentModule2.php");
        require_once("modules/$currentModule2/$currentModule2.php");
        $focus2 = new $currentModule2();
        setObjectValuesFromRequest($focus2);
         //for($b=0; $b<$schemeCnt; $b++){

             //$focus2->column_fields['xschemepointsid']      = $adb->query_result($scheme_result,$b,'xschemepointsid');
             //$focus2->column_fields['code']                 = $adb->query_result($scheme_result,0,'code');
             //$focus2->column_fields['xretailerid']          = $adb->query_result($scheme_result,$b,'xretailerid');
             $focus2->column_fields['code']                 = $adb->query_result($scheme_result,0,'code');
             $focus2->column_fields['xretailerid']          = $RetailerId;
             $focus2->column_fields['xdistributorid']       = $todistid;
             $focus2->column_fields['salesinvoiceid']       = $adb->query_result($scheme_result,0,'salesinvoiceid');
             $focus2->column_fields['opening_balance']      = $adb->query_result($scheme_result,0,'opening_balance');
             $focus2->column_fields['earned']               = $adb->query_result($scheme_result,0,'earned');
             $focus2->column_fields['claimed']              = $adb->query_result($scheme_result,0,'claimed');
             $focus2->column_fields['xclaimid']             = $adb->query_result($scheme_result,0,'xclaimid');
             $focus2->column_fields['remarks']              = $adb->query_result($scheme_result,0,'remarks');
             $focus2->column_fields['scheme_points_type']   = $adb->query_result($scheme_result,0,'scheme_points_type');
             $focus2->column_fields['next_stage_name']      = $adb->query_result($scheme_result,0,'next_stage_name');
             $focus2->column_fields['status']               = "Published";
             //print_r($focus2);die;
             $focus2->save($currentModule2);

             $updateCode = "UPDATE `vtiger_xschemepoints` SET status = 'Published',code='".$adb->query_result($scheme_result,0,'code')."' where xschemepointsid = '".$focus2->id."' ";    
             $adb->mquery($updateCode);
             //print_r($focus2);die;
        //} 
    }
}
/** Get Authentication User Date Format **/
function getUserDateFormat($user_id)
{
    global $adb;
    $Qry = "SELECT date_format from vtiger_users WHERE id=$user_id";
    
    $result = $adb->pquery($Qry);
    $user_date_fromat = $adb->query_result($result,$index, 'date_format');
    if($user_date_fromat == 'dd-mm-yyyy')
        $user_date_fromat = 'dd-mm-yy %d-%m-%Y d-m-Y';
    elseif($user_date_fromat == 'mm-dd-yyyy')
        $user_date_fromat = 'mm-dd-yy %m-%d-%Y m-d-Y';
    else
        $user_date_fromat = 'yy-mm-dd %Y-%m-%d Y-m-d';
    
    return $user_date_fromat;
}

/** Get Authentication User Date Format Display date field **/
function getUserDateFormatDisplay($argDate) {
	if($argDate=='' || $argDate == NULL || empty($argDate)){
		return '-';
	}
    $user_date_fromat = explode(" ", getUserDateFormat($_SESSION['authenticated_user_id']));
    
	if(!empty($user_date_fromat)){ 
            $varDateDisplayFormat= date($user_date_fromat[2], strtotime($argDate)); 
	}else{
		$varDateDisplayFormat= date('d-m-Y', strtotime($argDate));
	}
	
	return $varDateDisplayFormat;
}

function getIncentiveCluster(){
    global $adb;
    $Qry = "SELECT vtiger_crmentity.*,
       vtiger_xdistributorcluster.*,
       vtiger_xdistributorclustercf.*
    FROM vtiger_xdistributorcluster
    INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xdistributorcluster.xdistributorclusterid
    INNER JOIN vtiger_xdistributorclustercf ON vtiger_xdistributorclustercf.xdistributorclusterid = vtiger_xdistributorcluster.xdistributorclusterid
    LEFT JOIN vtiger_users ON vtiger_users.id = vtiger_crmentity.smownerid
    LEFT JOIN vtiger_groups ON vtiger_groups.groupid = vtiger_crmentity.smownerid
    WHERE vtiger_xdistributorcluster.xdistributorclusterid > 0
    AND vtiger_crmentity.deleted = 0
    AND vtiger_xdistributorclustercf.cf_xdistributorcluster_status = 'Approved'
    AND vtiger_xdistributorcluster.active=1
    ORDER BY createdtime DESC LIMIT 0,50";
    if ($adb->num_rows($Qry) != "0"){
           $resval =  $adb->raw_query_result_rowdata($Qry,0); 
           foreach($retaddresult as $key=>$val){  
                $val = strtolower($val);
               $retptrval[$val] =  $resval[$val]; 
            }
        }
        return $retptrval;
}
function getProductCategoryId($productid){
	global $adb;
	$producthierid='';
	$producthier_query =" 	SELECT vtiger_xproductcf.cf_xproduct_category FROM vtiger_xproduct 
							INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xproduct.xproductid 
							INNER JOIN vtiger_xproductcf ON vtiger_xproduct.xproductid = vtiger_xproductcf.xproductid 
							WHERE vtiger_xproduct.xproductid = $productid
							AND vtiger_crmentity.deleted = 0 LIMIT 1"; 
	$result = $adb->pquery($producthier_query);
    $producthierid = $adb->query_result($result,0, 'cf_xproduct_category');
	return $producthierid;
	
}
function getselectedIncentiveCluster($id){
   /* global $adb;
     $Qry = "SELECT xs.xdistributorclusterid,xs.xcategorygroupid
            FROM vtiger_xincentivesetting xs,vtiger_xincentivesettingcf xcf 
            WHERE xs.xincentivesettingid='$id'
            and xs.xincentivesettingid=xcf.xincentivesettingid";
          // echo $Qry;
          // exit;
           $result = $adb->pquery($Qry);
          // $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;*/
}

function getModernComboGridForModule($moduleName,$fieldId,$extraParam,$massedit=0)
{ 
    global $adb,$PI_LBL_ALLOW_SELECT_OF_VENDOR,$LBL_ALLOW_SELECT_OF_VENDOR,$LBL_ALLOW_SELECT_OF_DEPOT,$ALLOW_GST_TRANSACTION,$LBL_PI_DRAFT_DISABLE_VALUES,$LBL_SI_DRAFT_DISABLE_VALUES;
    if($fieldId=='si_location' || $fieldId=='godown_id' || $fieldId=='grn_godown' || $fieldId=='pr_godown' || $fieldId=='sr_godown' || $fieldId=='ds_godown' || $fieldId=='sadj_godown' || $fieldId=='godown')
    {
        //$moduleName='xGodown';
    }
    elseif($fieldId=='cf_purchaseinvoice_billing_address_pick' || $fieldId=='cf_purchaseorder_billing_address_pick' || $fieldId=='cf_xsalesorder_billing_address_pick' || $fieldId=='cf_salesinvoice_billing_address_pick' || $fieldId=='billing_address_pick')
    {
        $extraParam='Billing';
    }
    elseif($fieldId=='cf_purchaseinvoice_shipping_address_pick' || $fieldId=='cf_purchaseorder_shipping_address_pick' || $fieldId =='cf_xsalesorder_shipping_address_pick' || $fieldId =='cf_salesinvoice_shipping_address_pick' || $fieldId=='xdispatch_shipping_address_pick' || $fieldId=='salesreturn_shipping_address_pick' || $fieldId=='shipping_address_pick')
    {
        $extraParam='Shipping';
    }
    elseif(($fieldId == 'cf_xcreditnote_retailer' || $fieldId == 'partyname') && $moduleName == '')
    {
        $moduleName='Vendors';
    }
    elseif($fieldId == 'cf_xattendanceregister_salesman_or_staff_name' && $moduleName == '')
    {
        $moduleName='xSupportingstaff';
    }
    elseif($fieldId == 'ref_customer' && $moduleName == 'xRetailer')
    {
        $extraParam='refretailer';
    }  
    elseif($fieldId == 'print_format' && $moduleName == '')
    {
        $moduleName='xDistributor';
        $extraParam='printformat';
    }
    
    if($extraParam == 'xVan')
    {
        $moduleName='xVan';
    }
    elseif($extraParam == 'godown')
    {
        $moduleName='xGodown';
    }
    if($_REQUEST['module']=='xDistributorRevoke' && $_REQUEST['return_module']=='xSpecialPriceSetting'){
     $extraParam=$_REQUEST['return_id'];
    }
    if($_REQUEST['module']=='xRetailer' && $fieldId=='cf_xretailer_channel_type'  ){
    $distributor = getDistrIDbyUserID();
    $extraParam=$distributor['id'];
    }
    if($_REQUEST['module']=='DPMDistributorRevoke' && $_REQUEST['return_module']=='xDistributorProductsMapping'){
        $extraParam=$_REQUEST['return_id'];
    }
     $current_mod_name = $_REQUEST['module'];
    if($_REQUEST['convertmode'] == 'rsotoso'  && $current_mod_name == 'xSalesOrder' && ($fieldId == 'cf_xsalesorder_sales_man' || $fieldId == 'cf_xsalesorder_beat' || $fieldId == 'buyer_id'))
        return "";
    elseif($_REQUEST['convertmode'] == 'rsitosi'  && $current_mod_name == 'SalesInvoice' && ($fieldId == 'cf_salesinvoice_sales_man' || $fieldId == 'cf_salesinvoice_beat' || $fieldId == 'vendor_id' || $fieldId == 'si_location'))
        return "";
    elseif($_REQUEST['convertmode'] == 'sotoinvoice'  && $current_mod_name == 'SalesInvoice' && ($fieldId == 'cf_salesinvoice_sales_man' || $fieldId == 'cf_salesinvoice_beat' || $fieldId == 'vendor_id'))
        return "";
    elseif(($_REQUEST['convertmode'] == 'sotodc' || $_REQUEST['convertmode'] == 'sitodc') && $current_mod_name == 'xDispatch' && ($fieldId == 'vendor_id' || $fieldId == 'ds_godown'))
        return "";
    elseif($_REQUEST['convertmode'] == 'rcotoco' && $current_mod_name == 'xCollection' && ($fieldId == 'cf_xcollection_salesman' || $fieldId == 'cf_xcollection_beat' || $fieldId == 'cf_xcollection_customer_name'))
        return "";
    elseif(($_REQUEST['convertmode'] == 'potoinvoice') && $current_mod_name == 'PurchaseInvoice' && ($fieldId == 'vendor_id'))
        return "";
    elseif(($_REQUEST['convertmode'] == 'xrpitopi') && $current_mod_name == 'PurchaseInvoice' && ($fieldId == 'vendor_id'))
        return "";
    elseif(($_REQUEST['convertmode'] == 'xrpotopo') && $current_mod_name == 'PurchaseOrder' && ($fieldId == 'vendor_id'))
        return "";
    elseif(($_REQUEST['convertmode'] == 'pitogrn' || $_REQUEST['convertmode'] == 'potogrn') && $current_mod_name == 'xGRN' && ($fieldId == 'vendor_id' || $fieldId == 'grn_godown'))
        return "";
    elseif($_REQUEST['module']=='xDistributorRevoke' && $_REQUEST['return_module']=='xSpecialPriceSetting' && $fieldId=='xdistributorclusterid')
        return "";
    elseif($_REQUEST['module']=='xClaimNormDistributorRevoke' && $_REQUEST['return_module']=='xClaimNorm' )
        return "";
    elseif($_REQUEST['module']=='xSalesmanGpMapping' && !empty($_REQUEST['return_id']) )
        return "";
    elseif($_REQUEST['module']=='PurchaseInvoice' && $fieldId=='vendor_id' && $PI_LBL_ALLOW_SELECT_OF_VENDOR == "False")
        return "";
    elseif($_REQUEST['module']=='PurchaseOrder' && $fieldId=='vendor_id' && $LBL_ALLOW_SELECT_OF_VENDOR == "False")
        return "";
     elseif($_REQUEST['module']=='PurchaseOrder' && $fieldId=='cf_purchaseorder_depot' && $LBL_ALLOW_SELECT_OF_DEPOT == "False")
        return "";
    $focus=  CRMEntity::getInstance($moduleName);
    //echo "Hi :".$_REQUEST['module'];
    /*if($_REQUEST['module'] == 'xFocusProductMapping' && $moduleName == "xDistributorCluster")
    {
        $focus->list_link_field='distributorclustercode';
    }*/

    
	//Enable channel based pricing
    $configKey      = array('CHANNEL_LEVEL','CHANNEL_BASE_PRICE');
    $arrChannel     = getConfig($configKey);
    $channel_id     = $pigodowncond = $grngodowncond = $godown_load ='';
    if($arrChannel['CHANNEL_LEVEL'] && $arrChannel['CHANNEL_BASE_PRICE']){
        if($current_mod_name == 'PurchaseInvoice')
        {
            if($fieldId == 'pi_godown'){
                $pigodowncond = "if('$fieldId' == 'pi_godown'){
                   combogridafterloadsuccess('$fieldId', data);
                }";
            }
            $channel_id = "&channelid=";
        }
        if($current_mod_name == 'xGRN')
        {
            if($fieldId == 'grn_godown'){
                $grngodowncond = "if('$fieldId' == 'grn_godown'){
                   combogridafterloadsuccess('$fieldId', data);
                }";
            }
            $channel_id = "&channelid=";
            $varname = "channelid";
        }
        if($current_mod_name == 'PurchaseInvoice' ){
            $godown_load = "if('$current_mod_name' == 'PurchaseInvoice'){
                combogridDependencyCheck('$fieldId');
            }";
        }
        if($current_mod_name == 'xGRN' ){
            $godown_load = "if('$current_mod_name' == 'xGRN'){
                combogridDependencyCheck('$fieldId');
            }";
        }
        //$channel_id = " channelid = jQuery('#xchannelhierarchyid').val();";
    }    

    
    $claimConfig = 0;
    if( $current_mod_name ==  'xClaimTopSheet'){
        $getConfValue = $adb->pquery("SELECT `sify_inv_mgt_config`.`value` FROM sify_inv_mgt_config WHERE  `sify_inv_mgt_config`.`key` = 'CLAIM_CONF_MONTHLY_ONCE'");
        if($adb->num_rows($getConfValue) > 0){
            $claimConfig = $adb->query_result($getConfValue,0,'value');
        }
    }
    
    if($_REQUEST['module'] == "xCdkeyDistMapping"){
        $distid = $_REQUEST['parent_id'];
    }else{
        $distid = 0;
    }
    
    $noComboName=$fieldId."_display_combo_no";
    
    //print_r($focus->list_fields_name);
    //echo $query;
//    echo 'field::'.$fieldId.'moduleName.;'.$moduleName.'<br>';
    if($massedit==0){
        $popUpString="<script> ";
        $popUpString.="jQuery(document).ready(function(){";
    }
    $popUpString.="
                    var count = 1;
                    
                    var noCombo=get_cookie('$noComboName');
                     
                    if(noCombo=='TRUE')
                        return;

                    jQuery('#".$fieldId."_display').combogrid({
                    panelWidth: 600,";
    
    if($fieldId == 'print_format' && $moduleName == 'xDistributor'){
        $popUpString.="idField: '".$focus->table_index_template."',  
                       textField: '".$focus->list_link_field_template."',   ";
    }else{
        $popUpString.="idField: '".$focus->table_index."',  
                       textField: '".$focus->list_link_field."',   ";
    }
    
    $popUpString.=" url: 'index.php?module=Home&action=getModernComboGridForModuleResults&ajax=true&moduleName=".$moduleName."&current_mod_name=".$current_mod_name."&extraParam=".$extraParam."&distid=".$distid."',
                    mode:'remote',
                    pagination : 'true',
                    pageSize : 5,
                    pageList : [5,10,25],
                    ";
    
    if($fieldId == 'print_format' && $moduleName == 'xDistributor'){
        $popUpString.=" columns: [[
                    {field:'".$focus->table_index_template."',title:'ID',hidden:true,width:60} ";
        foreach ($focus->list_fields_template_name as $name => $nameKey) {
            $popUpString.=",{field:'".$nameKey."',title:'".$name."',width:60}";
        }
    }else{
        $popUpString.=" columns: [[
                    {field:'".$focus->table_index."',title:'ID',hidden:true,width:60} ";
        foreach ($focus->list_fields_name as $name => $nameKey) {
            $popUpString.=",{field:'".$nameKey."',title:'".$name."',width:60}";
        }
    }
    
     $popUpString.="]],                    
                    onSelect : function(index,row)
                    {
						
						if(('$current_mod_name' ==  'PaymentDetail') && ('$fieldId' == 'sku')){
							
							var  skucode=(row.sku_code);
							var  skudue=parseInt(row.duration);
							var  skuamt=(row.amount);
							var  desc=(row.skudesc);
							jQuery('#skucode').val(skucode);
							jQuery('#sku_duration').val('');
							jQuery('#sku_duration').val(skudue);
							jQuery('#sku_desc').val(desc);
							jQuery('#sku_amount').val('');
							jQuery('#sku_amount').val(skuamt);
							jQuery('input[name = quantity]').val('');
							jQuery('#total_amount').val('');
							
							var startdate = new Date();
							var endDate = new Date(startdate);
							endDate.setDate(endDate.getDate() +skudue);
							var day = endDate.getDate();
							var month = endDate.getMonth()+1;
							var year = endDate.getFullYear();
							if(day<10){
								day='0'+day;
							}
							if(month<10){
								month='0'+month;
							}
							var endDates = day + '-' + month + '-' + year;
							jQuery('#service_enddate').val(endDates);
 
							
						}
						if(('$current_mod_name' ==  'PurchaseInvoice') && ('$fieldId' == 'cf_purchaseinvoice_credit_term')){
							var noofdays=parseInt(row.cf_xcreditterm_number_of_days);
							var date2 = jQuery('#jscal_field_cf_purchaseinvoice_bill_date').datepicker('getDate', '+1d'); 
							date2.setDate(date2.getDate()+noofdays); 
							jQuery('#jscal_field_duedate').datepicker('setDate', date2);
							jQuery('#jscal_field_cf_purchaseinvoice_payment_date').datepicker('setDate', date2);
						}						
                        var prText=jQuery('#".$fieldId."_display').val();
                        var prValue=jQuery('#".$fieldId."').val();
						
						if( ('".$fieldId."'=='ship_state' || '".$fieldId."'=='bill_state') && ('$current_mod_name' ==  'SalesInvoice' || '$current_mod_name' ==  'PurchaseOrder'|| '$current_mod_name' ==  'xSalesOrder'|| '$current_mod_name' ==  'PurchaseInvoice')){
							 var dis_state=jQuery('#".$fieldId."_display').next('span.combo').children('input.combo-text').val();
							 jQuery('input[name=".$fieldId."]').val(dis_state);
							 return false;
						}
                        else if('".$fieldId."'!='cf_salesinvoice_pay_mode'){
							jQuery(jQuery('#".$fieldId."_display').next('span').children('input.combo-text')[0]).val('');
                        }
						
                        if(typeof combogridDependencyBeforeSelect !== 'undefined' && jQuery.isFunction(combogridDependencyBeforeSelect))
                        {
                            if(!combogridDependencyBeforeSelect('$fieldId'))
                            {
                                setTimeout(function(){jQuery('#".$fieldId."_display').next('span').children('input.combo-text').val(prText)},500);                                
                                setTimeout(function(){jQuery('#".$fieldId."').val(prValue)},500);                                
                                return false;
                            }    
                        } ";
                        
                        if($fieldId == 'print_format' && $moduleName == 'xDistributor'){                        
                            $popUpString.=   " 
                                if(typeof combogridBeforeSelect !== 'undefined' && jQuery.isFunction(combogridBeforeSelect))
                                {
                                    if(!combogridBeforeSelect('$fieldId', row.".$focus->table_index_template."))
                                    {
                                        setTimeout(function(){jQuery('#".$fieldId."_display').next('span').children('input.combo-text').val(prText)},500);                                
                                        setTimeout(function(){jQuery('#".$fieldId."').val(prValue)},500);
                                        jQuery('#".$fieldId."').next().next().find('input[type=\"text\"]').focus();
                                        return false;
                                    }    
                                }

                                jQuery('#".$fieldId."').val(row.".$focus->table_index_template.");
                                jQuery('#".$fieldId."_display').val(row.".$focus->list_link_field_template.");
                            "; 
                        }else{
                            $popUpString.= "
                                if(typeof combogridBeforeSelect !== 'undefined' && jQuery.isFunction(combogridBeforeSelect))
                                {
                                    if(!combogridBeforeSelect('$fieldId', row.".$focus->table_index."))
                                    {
                                        setTimeout(function(){jQuery('#".$fieldId."_display').next('span').children('input.combo-text').val(prText)},500);                                
                                        setTimeout(function(){jQuery('#".$fieldId."').val(prValue)},500);
                                        jQuery('#".$fieldId."').next().next().find('input[type=\"text\"]').focus();
                                        return false;
                                    }    
                                }

                                jQuery('#".$fieldId."').val(row.".$focus->table_index.");
                                jQuery('#".$fieldId."_display').val(row.".$focus->list_link_field.");
                            ";
                        }
                        
                         if( $fieldId == "xcustomersalesmanid"){
                             $popUpString.= "jQuery('#".$fieldId."').trigger('change')";
                         }
                         if( $fieldId == "xdistributorclusterid"){
                             $popUpString.= "jQuery('#".$fieldId."').trigger('change')";
                         }
                     $popUpString.=   "
																	
			if(typeof combogridDependencyCheck !== 'undefined' && jQuery.isFunction(combogridDependencyCheck) && count>0 && count<4 )
                        {
                            combogridDependencyCheck('$fieldId');														
			}
                        if(typeof combogridDependencyCheckPayment !== 'undefined' && jQuery.isFunction(combogridDependencyCheckPayment))
                        {
                            combogridDependencyCheckPayment('$fieldId', row);
                        }
                        if('$current_mod_name' == 'xSalesOrder' || '$current_mod_name' == 'SalesInvoice')
                        {
                            if(jQuery('#".$fieldId."').val() != '')
                            {
                                jQuery('#".$fieldId."').next().next().find('input[type=\"text\"]').focus();
                            }
                        }
                        if( '$current_mod_name' ==  'xClaimTopSheet'){
                            //xClaimTopSheet,xClaimScheme,xManualClaim
                            
                            claimclear('$fieldId',$claimConfig);
                        }
						if('$current_mod_name' ==  'xSpecialPriceSetting'){
                            get_retailer();
                        }
						if('$fieldId' == 'vendor_id' || '$fieldId' == 'xservicecodeid'){ //Decode Html Special Character
							setTimeout(function() {
									var decodetext=jQuery(jQuery('#".$fieldId."_display').next('span').children('input.combo-text')[0]).val();
									if(decodetext!=''){
										var decodedtext = jQuery('<div/>').html(decodetext).text();
										jQuery(jQuery('#".$fieldId."_display').next('span').children('input.combo-text')[0]).val(decodedtext);
									}
							} ,1000);
						}
						
						if( ('".$fieldId."'=='cf_xsalesorder_shipping_address_pick' || '".$fieldId."'=='cf_xsalesorder_billing_address_pick' || '".$fieldId."'=='cf_salesinvoice_shipping_address_pick' || '".$fieldId."'=='cf_salesinvoice_billing_address_pick' || '".$fieldId."'=='cf_purchaseorder_billing_address_pick' || '".$fieldId."'=='cf_purchaseorder_shipping_address_pick' || '".$fieldId."'=='cf_purchaseinvoice_billing_address_pick' || '".$fieldId."'=='cf_purchaseinvoice_shipping_address_pick' || '".$fieldId."'=='xdispatch_shipping_address_pick' || '".$fieldId."'=='salesreturn_shipping_address_pick' || '".$fieldId."'=='shipping_address_pick' || '".$fieldId."'=='billing_address_pick') && row.statecode==null  && '".$ALLOW_GST_TRANSACTION."'=='1'){
							setTimeout(function(){ 
								jQuery('#".$fieldId."_display').next('span.combo').children('input.combo-text').val(row.addresscode); 
							},1000);
						}	
						
                    },
                    onLoadSuccess: function(data)
                    { 
			if(jQuery('#".$fieldId."_display').next('span.combo').children('input.combo-text').val()=='')
                        {   
                            if('".$fieldId."' == 'user_reports_to_id'){
                                var temp = jQuery('#".$fieldId."_display').val();
                                var myString = temp;
                                var arr = myString.split('::: ');    
                                temp = arr[2];
                                //console.log(arr[2]);    
                            }else{
                                var temp=jQuery('#".$fieldId."_display').val();  
                            }
                            jQuery('#".$fieldId."_display').next('span.combo').children('input.combo-text').val(temp);
                           
                        }
                        if(typeof combogridafterloadsuccess !== 'undefined' && jQuery.isFunction(combogridafterloadsuccess))
                        {
                            combogridafterloadsuccess('$fieldId');
                        }
                        if('$current_mod_name' == 'xCollection' || '$current_mod_name' == 'xBasePrice' || '$current_mod_name' == 'xDistributorProductsMapping' || '$current_mod_name' == 'xMargin' || '$current_mod_name' == 'xPriceCalculationMapping' || '$current_mod_name' == 'xSpecialPriceSetting' || '$current_mod_name' == 'xPriceCorrection')
                        {
                           if(typeof combogridafterloadsuccess !== 'undefined' && jQuery.isFunction(combogridafterloadsuccess))
                             {
                                combogridafterloadsuccess('$fieldId', data);
                             }
                        }
                        if('$current_mod_name' == 'SalesInvoice' || '$current_mod_name' == 'xSalesReturn')
                        {
                            if(typeof combogridafterloadsuccess !== 'undefined' && jQuery.isFunction(combogridafterloadsuccess))
                            {
                               combogridafterloadsuccess('$fieldId', data);
                            }
                            if('$fieldId' == 'cf_salesinvoice_shipping_address_pick')
                            {
                                if(jQuery('#scheme_applied_properly').length > 0)
                                {
                                   if('$current_mod_name' == 'SalesInvoice' && '$LBL_SI_DRAFT_DISABLE_VALUES' == 'False'){
                                       jQuery('input[title=\"Draft\"]').show();
                                    }else if('$current_mod_name' == 'xSalesReturn'){
                                      jQuery('input[title=\"Draft\"]').show();
                                    }
                                    if(jQuery('#scheme_applied_properly').val() == 1)
                                    {
                                        jQuery('input[title=\"Save [Alt+S]\"]').show();
                                    }
                                }
                                else
                                {
                                    if('$current_mod_name' == 'SalesInvoice' && '$LBL_SI_DRAFT_DISABLE_VALUES' == 'False'){
                                       jQuery('input[title=\"Draft\"]').show();
                                    }else if('$current_mod_name' == 'xSalesReturn'){
                                       jQuery('input[title=\"Draft\"]').show();
                                    }
                                    jQuery('input[title=\"Save [Alt+S]\"]').show();
                                }
                            }
                        }
                        if('$current_mod_name' == 'xSalesOrder' || '$current_mod_name' == 'xDispatch')
                        {
                           if(typeof combogridafterloadsuccess !== 'undefined' && jQuery.isFunction(combogridafterloadsuccess) && '$current_mod_name' == 'xSalesOrder')
                           {
                               combogridafterloadsuccess('$fieldId', data);
                           }
                           if('$fieldId' == 'cf_xsalesorder_shipping_address_pick' && '$current_mod_name' == 'xSalesOrder')
                           {
                                jQuery('input[title=\"Save [Alt+S]\"]').show();
                                jQuery('input[title=\"Draft\"]').show();
                           }
                           if('$fieldId' == 'xdispatch_shipping_address_pick' && '$current_mod_name' == 'xDispatch')
                           {
                                jQuery('input[title=\"Save [Alt+S]\"]').show();
                                jQuery('input[title=\"Draft\"]').show();
                                
                           }
                           if('$fieldId' == 'cf_xdispatch_transaction_series' && '$current_mod_name' == 'xDispatch')
                           {
                                jQuery('input[title=\"Save [Alt+S]\"]').show();
                                jQuery('input[title=\"Draft\"]').show();
                           }
                        }
                        
                        if('$current_mod_name' == 'PurchaseInvoice')
                        {
                            if('$fieldId' == 'cf_purchaseinvoice_shipping_address_pick')
                            {
                                if(jQuery('.redtd').length > 0 || jQuery('.redprice').length > 0)
                                {
                                    jQuery('.save').hide();
                                }
                                else
                                {
                                    if('$LBL_PI_DRAFT_DISABLE_VALUES' == 'False'){
                                        jQuery('input[title=\"Draft\"]').show();
                                    }
                                    jQuery('input[title=\"Save [Alt+S]\"]').show();
                                }
                            }
                            $pigodowncond
                                
                        }
                        if('$current_mod_name' == 'xGRN')
                        {
                            $grngodowncond
                        }
                        $godown_load
                     },
                    onBeforeLoad : function(param)
                    {
                        
                        if(param.proceed!=undefined && param.proceed=='TRUE')
                            return true;

                        if(param.q=='')
                        {
                            jQuery('#".$fieldId."').val('');
                            combogridDependencyCheck('$fieldId');
                        }    

                        if(typeof combogridPreRequisiteCheck !== 'undefined' && jQuery.isFunction(combogridPreRequisiteCheck))
                        {
                            return combogridPreRequisiteCheck('$fieldId',param.q);
                                
                        }
                        else
                        {                            
                            return true;
                        }
                        
                    },
                    fitColumns: true,
                    autoSizeColumn : true
            });";
    if($massedit==0){ 
        $popUpString.="});";
        $popUpString.="</script> ";

        echo $popUpString;
    }else{
        return $popUpString;
    }
}
function getPaymentPendingInvAdjReturn()
{
    global $adb;
    $distributorid = getDistrIDbyUserID();
    $where = "";
    if($distributorid['id'] > 0)
       $where = " and (vtiger_vendor.distributor_id='".$distributorid['id']."' or vtiger_vendor.distributor_id is null or vtiger_vendor.distributor_id = '')"; 

    $query = "SELECT vtiger_vendor.vendorid  
        FROM vtiger_vendor INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_vendor.vendorid 
        INNER JOIN vtiger_vendorcf ON vtiger_vendor.vendorid = vtiger_vendorcf.vendorid 
        LEFT JOIN vtiger_xstate ON vtiger_xstate.xstateid = vtiger_vendor.state  
        WHERE vtiger_vendor.vendorid > 0 AND vtiger_crmentity.deleted = 0 AND vtiger_vendorcf.cf_vendors_active=1 
        $where ";
    
    $data=$adb->pquery($query);
    for($i=0; $i<$adb->num_rows($data); $i++)        
    {
        $entity_id = $adb->query_result($data, $i, 'vendorid');
        $saleinvoice = array();
        $querysaleinvoice="select CONCAT(si.purchaseinvoiceid,'::',sicf.cf_purchaseinvoice_transaction_number,'::',sicf.cf_purchaseinvoice_purchase_invoice_date,'::',si.purchaseinvoice_no,'::',si.total,'::',sicf.cf_purchaseinvoice_outstanding) as `sidata` from vtiger_purchaseinvoice as si INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=si.purchaseinvoiceid INNER join vtiger_purchaseinvoicecf as sicf on sicf.purchaseinvoiceid=si.purchaseinvoiceid where vtiger_crmentity.deleted=0 and si.status NOT IN ('Draft', 'Cancel') and sicf.cf_purchaseinvoice_outstanding > 0 and sicf.cf_purchaseinvoice_buyer_id='".$distributorid['id']."' and si.vendorid=".$entity_id;
        //echo "Hi :".$querysaleinvoice."<br/>";exit;
        $resultsaleinvoice = $adb->pquery($querysaleinvoice);
        $salescount = $adb->num_rows($resultsaleinvoice);
        for($j=0;$j<$adb->num_rows($resultsaleinvoice);$j++){
                $saleinvoice[]=$adb->query_result($resultsaleinvoice,$j,0);
        }
        $saleinvoice=array_filter($saleinvoice);
        $saleinvoicedata = @implode(",", $saleinvoice);

        //pk salesreturn data get
        $salereturn = array();
        $querysalereturn="select CONCAT(sr.xpurchasereturnid,'::',srcf.cf_xpurchasereturn_transaction_no,'::',srcf.cf_xpurchasereturn_intiated_date,'::',sr.purchasereturncode,'::',srcf.cf_xpurchasereturn_amount,'::',srcf.cf_xpurchasereturn_amount,'::',srcf.cf_xpurchasereturn_adjustable_amount,'::PR') as `srdata` from vtiger_xpurchasereturn as sr INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=sr.xpurchasereturnid INNER join vtiger_xpurchasereturncf as srcf on srcf.xpurchasereturnid=sr.xpurchasereturnid where vtiger_crmentity.deleted=0 and (CAST(srcf.cf_xpurchasereturn_amount AS UNSIGNED) > CAST(srcf.cf_xpurchasereturn_adjustable_amount AS UNSIGNED)) and sr.xdistributorid='".$distributorid['id']."' and srcf.cf_xpurchasereturn_vendor=".$entity_id." and srcf.cf_xpurchasereturn_status in ('Created','Publish')";
        $resultsalereturn = $adb->pquery($querysalereturn);

        for($j=0;$j<$adb->num_rows($resultsalereturn);$j++){
                $salereturn[]=$adb->query_result($resultsalereturn,$j,0);
        }
        $querydebitnote="select CONCAT(sr.xdebitnoteid,'::',sr.debitnoteno,'::',srcf.cf_xdebitnote_debit_note_date,'::',sr.debitnotecode,'::',sr.amount,'::',sr.amount,'::',srcf.cf_xdebitnote_debit_note_adjusted,'::DN') as `srdata` from vtiger_xdebitnote as sr INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=sr.xdebitnoteid left join vtiger_xdebitnotecf as srcf on srcf.xdebitnoteid=sr.xdebitnoteid where vtiger_crmentity.deleted=0 and srcf.cf_xdebitnote_status='Active' AND sr.status = 'Created' and (CAST(sr.amount AS UNSIGNED) > CAST(srcf.cf_xdebitnote_debit_note_adjusted AS UNSIGNED)) and sr.distributor_id=".$distributorid['id']." and sr.partyname=".$entity_id;
        $resultdebitnote = $adb->pquery($querydebitnote);
        for($j=0;$j<$adb->num_rows($resultdebitnote);$j++){
                $salereturn[]=$adb->query_result($resultdebitnote,$j,0);
        }

        $querycollreturn="select CONCAT(sr.xpaymentid,'::',srcf.cf_xpayment_transaction_number,'::',srcf.cf_xpayment_payment_date,'::',sr.paymentcode,'::',srcf.cf_xpayment_amount_received,'::',srcf.cf_xpayment_cheque_no,'::',srcf.cf_xpayment_recieved_balance,'::') as `crdata` from vtiger_xpayment as sr INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=sr.xpaymentid left join vtiger_xpaymentcf as srcf on srcf.xpaymentid=sr.xpaymentid where vtiger_crmentity.deleted=0 and srcf.cf_xpayment_status  NOT IN ('Draft', 'Cancel') and (CAST(srcf.cf_xpayment_amount_received AS UNSIGNED) > CAST(srcf.cf_xpayment_recieved_balance AS UNSIGNED)) and srcf.cf_xpayment_distributor='".$distributorid['id']."' and srcf.cf_xpayment_customer_name=".$entity_id; 
        $resultcollreturn = $adb->pquery($querycollreturn);
        for($j=0;$j<$adb->num_rows($resultcollreturn);$j++){
                $salereturn[]=$adb->query_result($resultcollreturn,$j,0);
        }                                                            

        $salereturn=array_filter($salereturn);
        $returncount = count($salereturn);
        $salereturndata = @implode(",", $salereturn);
        
        if($saleinvoicedata != '' || $salereturndata != '')
        {
            echo "<input type='hidden' name='si_$entity_id' id='si_$entity_id' value='$saleinvoicedata' />";
            echo "<input type='hidden' name='sr_$entity_id' id='sr_$entity_id' value='$salereturndata' />";
        }
    }
    
}
function getCollectionPendingInvAdjReturn()
{
    global $adb,$SET_RETAILER_INACTIVE ;
    $distid = getDistrIDbyUserID();
    $retailer_type = explode('@', $SET_RETAILER_INACTIVE);
    $key = "Collection Module";
    if(in_array($key, $retailer_type)){
        $active  = '0,1';
    }else
        $active  = '1';
    
    $where = "";
    if($distid['id'] > 0)
       $where = " and vtiger_xretailer.distributor_id='".$distid['id']."' "; 

    $query = "SELECT vtiger_xretailer.xretailerid  
        FROM vtiger_xretailer INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xretailer.xretailerid 
        INNER JOIN vtiger_xretailercf ON vtiger_xretailercf.xretailerid = vtiger_xretailer.xretailerid 
        WHERE vtiger_xretailer.xretailerid > 0 AND vtiger_crmentity.deleted = 0 AND  vtiger_xretailercf.cf_xretailer_active IN ($active)  
        $where ";
    
    /* Salesinvoice amendment  impact by kayal start */
        $asql = "SELECT group_concat(amend_id) as amendinv FROM vtiger_salesinvoice where status = 'Draft' AND amend_id>0";
        $amend_result = $adb->pquery($asql);
        $aresult_val = $adb->query_result($amend_result,0,'amendinv');
        $amendment_inv = explode(",",$aresult_val);//echo $aresult_val."<br>";
        /* Salesinvoice amendment  impact by kayal end */
        //$querysaleinvoice="select CONCAT(si.salesinvoiceid,'::',sicf.cf_salesinvoice_transaction_number,'::',sicf.cf_salesinvoice_sales_invoice_date,'::',si.salesinvoice_no,'::',si.total,'::',sicf.cf_salesinvoice_outstanding,'::') as `sidata` from vtiger_salesinvoice as si INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=si.salesinvoiceid left join vtiger_salesinvoicecf as sicf on sicf.salesinvoiceid=si.salesinvoiceid where vtiger_crmentity.deleted=0 and si.status NOT LIKE '%Draft%' and si.status NOT LIKE '%Cancel%' and sicf.cf_salesinvoice_outstanding > 0 and si.vendorid=".$entity_id;
        $amend_condition ='';
        if($aresult_val!=''){
                $amend_condition =" AND si.salesinvoiceid NOT IN($aresult_val)";
        }
        
   $data=$adb->pquery($query);
   
   /*
    *   New Logic for Retailer In condition by kami
    */
            
   
   $retInStr="";
   for($i=0; $i<$adb->num_rows($data); $i++)        
    {
        $entity_id = $adb->query_result($data, $i, 'xretailerid');
       
        if($i>0)
            $retInStr.=",";
        
        $retInStr.=$entity_id;
    }
                
    
    /*
     *  SI & SR fetching pulling out of the loop
     */
    
    $SI_DATA=array();
    $SR_DATA=array();
    
    
        
    $querysaleinvoice="select si.vendorid,CONCAT(si.salesinvoiceid,'::',sicf.cf_salesinvoice_transaction_number,'::',sicf.cf_salesinvoice_sales_invoice_date,'::',Replace(si.subject, ',', ' '),'::',si.total,'::',sicf.cf_salesinvoice_outstanding,'::') as `sidata` from vtiger_salesinvoice as si INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=si.salesinvoiceid left join vtiger_salesinvoicecf as sicf on sicf.salesinvoiceid=si.salesinvoiceid where vtiger_crmentity.deleted=0 and si.status NOT LIKE '%Draft%' and si.status NOT LIKE '%Cancel%' and sicf.cf_salesinvoice_outstanding > 0 and sicf.cf_salesinvoice_seller_id='".$distid['id']."' and si.vendorid IN (".$retInStr.") ".$amend_condition;
    $resultsaleinvoice = $adb->pquery( $querysaleinvoice);//salesinvoice amendment result
    $salescount = $adb->num_rows($resultsaleinvoice);
    for($j=0;$j<$adb->num_rows($resultsaleinvoice);$j++){
            $entId=$adb->query_result($resultsaleinvoice,$j,0);
            $saleinvoice=$SI_DATA[$entId];
            if($saleinvoice==undefined)
                $saleinvoice=array();
            $saleinvoice[]=$adb->query_result($resultsaleinvoice,$j,1);
            $SI_DATA[$entId]=$saleinvoice;
    }
    
    $querydebitnote="select sr.partyname,CONCAT(sr.xdebitnoteid,'::',sr.debitnoteno,'::',srcf.cf_xdebitnote_debit_note_date,'::',sr.debitnotecode,'::',sr.amount,'::',srcf.cf_xdebitnote_debit_note_adjusted,'::DN') as `srdata` from vtiger_xdebitnote as sr INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=sr.xdebitnoteid left join vtiger_xdebitnotecf as srcf on srcf.xdebitnoteid=sr.xdebitnoteid where vtiger_crmentity.deleted=0 and srcf.cf_xdebitnote_status='Active' and (CAST(sr.amount AS UNSIGNED) > CAST(srcf.cf_xdebitnote_debit_note_adjusted AS UNSIGNED)) and sr.distributor_id=".$distid['id']." and sr.partyname IN (".$retInStr.") and sr.status in ('Created','Publish')";
    $resultdebitnote = $adb->pquery($querydebitnote);
    for($j=0;$j<$adb->num_rows($resultdebitnote);$j++){
        $entId=$adb->query_result($resultdebitnote,$j,0);
        $saleinvoice=$SI_DATA[$entId];
            if($saleinvoice==undefined)
                $saleinvoice=array();
        $saleinvoice[]=$adb->query_result($resultdebitnote,$j,1);
        $SI_DATA[$entId]=$saleinvoice;
    }
        
    
    $querysalereturn="select srcf.cf_xsalesreturn_customer,CONCAT(sr.xsalesreturnid,'::',srcf.cf_xsalesreturn_transaction_no,'::',srcf.cf_xsalesreturn_intiated_date,'::',sr.salesreturncode,'::',srcf.cf_xsalesreturn_amount,'::',srcf.cf_xsalesreturn_amount,'::',srcf.cf_xsalesreturn_adjustable_amount,'::SRE') as `srdata` from vtiger_xsalesreturn as sr INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=sr.xsalesreturnid left join vtiger_xsalesreturncf as srcf on srcf.xsalesreturnid=sr.xsalesreturnid where vtiger_crmentity.deleted=0 and (CAST(srcf.cf_xsalesreturn_amount AS UNSIGNED) > CAST(srcf.cf_xsalesreturn_adjustable_amount AS UNSIGNED)) and srcf.cf_xsalesreturn_customer IN (".$retInStr.") and srcf.cf_xsalesreturn_status in ('Created','Publish')";
    $resultsalereturn = $adb->pquery($querysalereturn); 
              
    for($j=0;$j<$adb->num_rows($resultsalereturn);$j++){
            $entId=$adb->query_result($resultsalereturn,$j,0);
            $salereturn=$SR_DATA[$entId];
            if($salereturn==undefined)
                $salereturn=array();
            $salereturn[]=$adb->query_result($resultsalereturn,$j,1);
            $SR_DATA[$entId]=$salereturn;
    }
    
    $querycreditnote="select srcf.cf_xcreditnote_retailer,CONCAT(sr.xcreditnoteid,'::',srcf.cf_xcreditnote_credit_note_series_number,'::',srcf.cf_xcreditnote_credit_note_date,'::',sr.creditnotecode,'::',srcf.cf_xcreditnote_amount,'::',srcf.cf_xcreditnote_amount,'::',srcf.cf_xcreditnote_adjustable_amount,'::CN') as `srdata` from vtiger_xcreditnote as sr INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=sr.xcreditnoteid left join vtiger_xcreditnotecf as srcf on srcf.xcreditnoteid=sr.xcreditnoteid where vtiger_crmentity.deleted=0 and srcf.cf_xcreditnote_status='Active' and (CAST(srcf.cf_xcreditnote_amount AS UNSIGNED) > CAST(srcf.cf_xcreditnote_adjustable_amount AS UNSIGNED)) and srcf.cf_xcreditnote_distributor_id='".$distid['id']."' and srcf.cf_xcreditnote_retailer IN (".$retInStr.") and sr.status in ('Created','Publish') AND `xsalesreturnid` IS NULL";
    $resultcreditnote = $adb->pquery($querycreditnote);
    for($j=0;$j<$adb->num_rows($resultcreditnote);$j++){
            $entId=$adb->query_result($resultcreditnote,$j,0);
            $salereturn=$SR_DATA[$entId];
            if($salereturn==undefined)
                $salereturn=array();
            $salereturn[]=$adb->query_result($resultcreditnote,$j,1);
            $SR_DATA[$entId]=$salereturn;
    }
    
    $querycollreturn="select srcf.cf_xcollection_customer_name,CONCAT(sr.xcollectionid,'::',srcf.cf_xcollection_transaction_number,'::',srcf.cf_xcollection_collection_date,'::',sr.collectioncode,'::',srcf.cf_xcollection_amount_received,'::',srcf.cf_xcollection_cheque_no,'::',srcf.cf_xcollection_recieved_balance,'::') as `crdata` from vtiger_xcollection as sr INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=sr.xcollectionid left join vtiger_xcollectioncf as srcf on srcf.xcollectionid=sr.xcollectionid where vtiger_crmentity.deleted=0 and srcf.cf_xcollection_status NOT LIKE '%Draft%' and (CAST(srcf.cf_xcollection_amount_received AS UNSIGNED) > CAST(srcf.cf_xcollection_recieved_balance AS UNSIGNED)) and srcf.cf_xcollection_distributor='".$distid['id']."' and srcf.cf_xcollection_customer_name IN (".$retInStr.")"; 
    $resultcollreturn = $adb->pquery($querycollreturn);   
    for($j=0;$j<$adb->num_rows($resultcollreturn);$j++){
            $entId=$adb->query_result($resultcollreturn,$j,0);
            $salereturn=$SR_DATA[$entId];
            if($salereturn==undefined)
                $salereturn=array();
            $salereturn[]=$adb->query_result($resultcollreturn,$j,1);
            $SR_DATA[$entId]=$salereturn;
    }
    
    for($i=0; $i<$adb->num_rows($data); $i++)        
    {
        $entity_id = $adb->query_result($data, $i, 'xretailerid');
//        $query="select vtiger_xretailercf.cf_xretailer_sales_man,vtiger_xretailercf.cf_xretailer_beat,vtiger_xsalesman.salesman,vtiger_xbeat.beatname,vtiger_xretailer.customercode from vtiger_xretailercf left join vtiger_xretailer on vtiger_xretailer.xretailerid=vtiger_xretailercf.xretailerid left join vtiger_xsalesman on vtiger_xsalesman.xsalesmanid=vtiger_xretailercf.cf_xretailer_sales_man left join vtiger_xbeat on vtiger_xbeat.xbeatid=vtiger_xretailercf.cf_xretailer_beat where vtiger_xretailercf.xretailerid=".$entity_id;
//        $result = $adb->pquery($query);
//        $beat=$adb->query_result($result,0,'beatname');
//        $beatid=$adb->query_result($result,0,'cf_xretailer_beat');
//        $salesman=$adb->query_result($result,0,'salesman');
//        $salesmanid=$adb->query_result($result,0,'cf_xretailer_sales_man');
//        $cuscode =  $adb->query_result($result,0,'customercode');
        //pk salesinvoice data get
        
        $saleinvoice = $SI_DATA[$entity_id];
        $salereturn = $SR_DATA[$entity_id];
        
        
//        Commented By Kami
//        $querysaleinvoice="select CONCAT(si.salesinvoiceid,'::',sicf.cf_salesinvoice_transaction_number,'::',sicf.cf_salesinvoice_sales_invoice_date,'::',si.salesinvoice_no,'::',si.total,'::',sicf.cf_salesinvoice_outstanding,'::') as `sidata` from vtiger_salesinvoice as si INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=si.salesinvoiceid left join vtiger_salesinvoicecf as sicf on sicf.salesinvoiceid=si.salesinvoiceid where vtiger_crmentity.deleted=0 and si.status NOT LIKE '%Draft%' and si.status NOT LIKE '%Cancel%' and sicf.cf_salesinvoice_outstanding > 0 and sicf.cf_salesinvoice_seller_id='".$distid['id']."' and si.vendorid=".$entity_id."".$amend_condition;
//        $resultsaleinvoice = $adb->pquery( $querysaleinvoice);//salesinvoice amendment result
//        $salescount = $adb->num_rows($resultsaleinvoice);
//        for($j=0;$j<$adb->num_rows($resultsaleinvoice);$j++){
//                $saleinvoice[]=$adb->query_result($resultsaleinvoice,$j,0);
//        }
//        
//        $querydebitnote="select CONCAT(sr.xdebitnoteid,'::',sr.debitnoteno,'::',srcf.cf_xdebitnote_debit_note_date,'::',sr.debitnotecode,'::',sr.amount,'::',sr.amount,'::',srcf.cf_xdebitnote_debit_note_adjusted,'::DN') as `srdata` from vtiger_xdebitnote as sr INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=sr.xdebitnoteid left join vtiger_xdebitnotecf as srcf on srcf.xdebitnoteid=sr.xdebitnoteid where vtiger_crmentity.deleted=0 and srcf.cf_xdebitnote_status='Active' and (CAST(sr.amount AS UNSIGNED) > CAST(srcf.cf_xdebitnote_debit_note_adjusted AS UNSIGNED)) and sr.distributor_id=".$distid['id']." and sr.partyname=".$entity_id;
//        $resultdebitnote = $adb->pquery($querydebitnote);
//        for($j=0;$j<$adb->num_rows($resultdebitnote);$j++){
//                $saleinvoice[]=$adb->query_result($resultdebitnote,$j,0);
//        }
        
        $saleinvoice=array_filter($saleinvoice);
        $saleinvoicedata = @implode(",", $saleinvoice);

//        Commented By Kami
//        //pk salesreturn data get
//        $salereturn = array();
//        $querysalereturn="select CONCAT(sr.xsalesreturnid,'::',srcf.cf_xsalesreturn_transaction_no,'::',srcf.cf_xsalesreturn_intiated_date,'::',sr.salesreturncode,'::',srcf.cf_xsalesreturn_amount,'::',srcf.cf_xsalesreturn_amount,'::',srcf.cf_xsalesreturn_adjustable_amount,'::SRE') as `srdata` from vtiger_xsalesreturn as sr INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=sr.xsalesreturnid left join vtiger_xsalesreturncf as srcf on srcf.xsalesreturnid=sr.xsalesreturnid where vtiger_crmentity.deleted=0 and (CAST(srcf.cf_xsalesreturn_amount AS UNSIGNED) > CAST(srcf.cf_xsalesreturn_adjustable_amount AS UNSIGNED)) and srcf.cf_xsalesreturn_customer=".$entity_id;
//        $resultsalereturn = $adb->pquery($querysalereturn);
//
//        for($j=0;$j<$adb->num_rows($resultsalereturn);$j++){
//                $salereturn[]=$adb->query_result($resultsalereturn,$j,0);
//        }
//        $distid = getDistrIDbyUserID();
//        $querycreditnote="select CONCAT(sr.xcreditnoteid,'::',srcf.cf_xcreditnote_credit_note_series_number,'::',srcf.cf_xcreditnote_credit_note_date,'::',sr.creditnotecode,'::',srcf.cf_xcreditnote_amount,'::',srcf.cf_xcreditnote_amount,'::',srcf.cf_xcreditnote_adjustable_amount,'::CN') as `srdata` from vtiger_xcreditnote as sr INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=sr.xcreditnoteid left join vtiger_xcreditnotecf as srcf on srcf.xcreditnoteid=sr.xcreditnoteid where vtiger_crmentity.deleted=0 and srcf.cf_xcreditnote_status='Active' and (CAST(srcf.cf_xcreditnote_amount AS UNSIGNED) > CAST(srcf.cf_xcreditnote_adjustable_amount AS UNSIGNED)) and srcf.cf_xcreditnote_distributor_id='".$distid['id']."' and srcf.cf_xcreditnote_retailer=".$entity_id;
//        $resultcreditnote = $adb->pquery($querycreditnote);
//        for($j=0;$j<$adb->num_rows($resultcreditnote);$j++){
//                $salereturn[]=$adb->query_result($resultcreditnote,$j,0);
//        }
//
//        $querycollreturn="select CONCAT(sr.xcollectionid,'::',srcf.cf_xcollection_transaction_number,'::',srcf.cf_xcollection_collection_date,'::',sr.collectioncode,'::',srcf.cf_xcollection_amount_received,'::',srcf.cf_xcollection_cheque_no,'::',srcf.cf_xcollection_recieved_balance,'::') as `crdata` from vtiger_xcollection as sr INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=sr.xcollectionid left join vtiger_xcollectioncf as srcf on srcf.xcollectionid=sr.xcollectionid where vtiger_crmentity.deleted=0 and srcf.cf_xcollection_status NOT LIKE '%Draft%' and (CAST(srcf.cf_xcollection_amount_received AS UNSIGNED) > CAST(srcf.cf_xcollection_recieved_balance AS UNSIGNED)) and srcf.cf_xcollection_distributor='".$distid['id']."' and srcf.cf_xcollection_customer_name=".$entity_id; 
//        $resultcollreturn = $adb->pquery($querycollreturn);
//        for($j=0;$j<$adb->num_rows($resultcollreturn);$j++){
//                $salereturn[]=$adb->query_result($resultcollreturn,$j,0);
//        }

        $salereturn=array_filter($salereturn);
        $salereturndata = @implode(",", $salereturn);
        
        if($saleinvoicedata != '')
        {
            echo "<input type='hidden' name='si_$entity_id' id='si_$entity_id' value='$saleinvoicedata' />";
        }
        if($salereturndata != '')
        {
            echo "<input type='hidden' name='sr_$entity_id' id='sr_$entity_id' value='$salereturndata' />";
        }
    }
}
//User login distributor list filter
//MODIFIED ON 23rd MAR,2016 - getDistlist() CHECK WITH vtiger_crmentity TABLE AND DELETE CONDITION
function getDistlist(){
    global $adb,$current_user;
	
	$pqry = "SELECT vtiger_xcpdpmappingcf.cf_xcpdpmapping_distributor as distid
FROM vtiger_xcpdpmappingcf INNER JOIN vtiger_xcpdpmapping ON
vtiger_xcpdpmappingcf.xcpdpmappingid = vtiger_xcpdpmapping.xcpdpmappingid
INNER JOIN vtiger_crmentity ON vtiger_xcpdpmappingcf.xcpdpmappingid = vtiger_crmentity.crmid
where vtiger_xcpdpmapping.cpusers=".$current_user->id." and vtiger_crmentity.deleted=0";
	$result = $adb->pquery($pqry);
	$ret = array();
	for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret[] = $dis_ids = $adb->query_result($result,$index,'distid');
		}
		$distids = implode(',',array_reverse($ret));  
        
 return $distids;
}
function getDistClusterlist($dis_ids){
    global $adb,$current_user;
	if($dis_ids==''){
		$dis_ids = 0;
	}
	/*$clusterquery.="SELECT distclusterid  as clusterid FROM vtiger_xdistributorclusterrel 
	WHERE distributorid IN (".$dis_ids.") ";*/
        $clusterquery.="SELECT crel.distclusterid  as clusterid FROM vtiger_xdistributorclusterrel crel
                        LEFT JOIN vtiger_xdistributorcluster cl ON cl.xdistributorclusterid=crel.distclusterid
                        LEFT JOIN vtiger_xdistributorclustercf clcf ON clcf.xdistributorclusterid=crel.distclusterid
                        WHERE clcf.cf_xdistributorcluster_status='Approved' AND cl.active='1' AND distributorid IN (".$dis_ids.") ";
	$result = $adb->pquery($clusterquery);
	$ret = array();
	for ($index = 0; $index < $adb->num_rows($result); $index++) {
	            $ret[] = $dis_ids = $adb->query_result($result,$index,'clusterid');
		}
	$clusterid = implode(',',array_reverse($ret));  
				
 return $clusterid;
}
function getRetailerChannelBilling($retid){
  global $adb,$current_user;

	$ret_chanl_bill_sql = "SELECT vtiger_xretailercf.*,	vtiger_xchannelhierarchy.*	FROM vtiger_xchannelhierarchy
                                INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xchannelhierarchy.xchannelhierarchyid
                                INNER JOIN vtiger_xchannelhierarchycf ON vtiger_xchannelhierarchycf.xchannelhierarchyid = vtiger_xchannelhierarchy.xchannelhierarchyid
                                LEFT JOIN vtiger_xretailercf ON vtiger_xretailercf.cf_xretailer_channel_type= vtiger_xchannelhierarchy.xchannelhierarchyid
                                WHERE vtiger_crmentity.deleted = 0 AND vtiger_xchannelhierarchycf.cf_xchannelhierarchy_active=1 AND vtiger_xretailercf.xretailerid=?";
	$result= $adb->pquery($ret_chanl_bill_sql,array($retid));//print_r($result);
	$billing_at= $adb->query_result($result,0,'billing_at');
        
	if($billing_at == ''){$billing_at =' Default';}
	return $billing_at;

}
function getSchemePointsType($id) {
     global $adb;
     $ret = '';
     $query = "SELECT scheme_points_type FROM vtiger_xschemepoints WHERE xschemepointsid=".$id;
	 $result = $adb->pquery($query);
	 for ($index = 0; $index < $adb->num_rows($result); $index++) {
	 	$ret = $adb->raw_query_result_rowdata($result,$index);
	 }
     return $ret['scheme_points_type'];
}
// Dynamic uom list
function getUomField(){
  global $adb,$current_user;
  
$prod_uom_qry ="SELECT group_concat(tablename,'.',columnname order by columnname) as flduomlist FROM vtiger_field WHERE tablename IN ('vtiger_xproduct','vtiger_xproductcf')
and typeofdata LIKE '%UOM%' AND presence In (0,2)";
	$result = $adb->pquery($prod_uom_qry);
	 $produomfldlist = $adb->query_result($result,0,'flduomlist');
	return $produomfldlist;
}

//Product Dynamic uom values fetch 
function getProductUOMList($pid){
	global $adb,$current_user;
	$dynamicuom = getUomField();//print_r($dynamicuom);
	//$pid = 43019;
	$sql = "SELECT ".$dynamicuom." FROM vtiger_xproduct LEFT JOIN vtiger_xproductcf ON vtiger_xproduct.xproductid = vtiger_xproductcf.xproductid where vtiger_xproduct.xproductid = $pid";
	$result = $adb->pquery($sql);//print_R($result);
	$ret = array();
	$text_chk = array("vtiger_xproduct.", "vtiger_xproductcf.");
	$txt_replace   = array("", "");
	$newphrase = str_replace($text_chk, $txt_replace, $dynamicuom);
	$uomlist = explode(",",$newphrase);//echo "<pre>";print_r($uomlist);
	
	for ($index = 0; $index < $adb->num_rows($result); $index++) {
		for($j=0;$j<count($uomlist); $j++){
			$ret[$uomlist[$j]] = $adb->query_result($result,$index,$uomlist[$j]);
		}
	           
	}
	return $ret;
}

function getUomFieldList(){
    global $adb,$current_user;
  
    $prod_uom_qry ="SELECT columnname FROM vtiger_field WHERE tablename IN ('vtiger_xproduct','vtiger_xproductcf')
    and typeofdata LIKE '%UOM%' AND uitype=10 AND presence In (0,2)";
    $result = $adb->pquery($prod_uom_qry);
    $produomfldlist = array();
    for ($index = 0; $index < $adb->num_rows($result); $index++) {
        $produomfldlist[$index] = $adb->query_result($result,$index,"columnname");
    }
    return $produomfldlist;
}

function getUomJoins($proudct, $proudct_cf)
{
    global $adb,$current_user;
    
    $prod_uom_qry ="SELECT tablename,columnname FROM vtiger_field WHERE tablename IN ('vtiger_xproduct','vtiger_xproductcf')
and typeofdata LIKE '%UOM%' AND uitype=10 AND presence In (0,2)";
    $result = $adb->pquery($prod_uom_qry);
    $join_str = "";
    for ($index = 0; $index < $adb->num_rows($result); $index++) {
        $columnname = $adb->query_result($result,$index,"columnname");
        $tablename = $adb->query_result($result,$index,"tablename");
        if($adb->query_result($result,$index,"tablename") == 'vtiger_xproduct')
        {
            $join_str .= " LEFT JOIN vtiger_uom vu_uom".($index+1)." ON vu_uom".($index+1).".uomid=$proudct.$columnname ";
            $join_str .= " LEFT JOIN vtiger_uomcf vucf_uom".($index+1)." ON vucf_uom".($index+1).".uomid=vu_uom".($index+1).".uomid";
        }
        elseif($adb->query_result($result,$index,"tablename") == 'vtiger_xproductcf')
        {
            $join_str .= " LEFT JOIN vtiger_uom vu_uom".($index+1)." ON vu_uom".($index+1).".uomid=$proudct_cf.$columnname ";
            $join_str .= " LEFT JOIN vtiger_uomcf vucf_uom".($index+1)." ON vucf_uom".($index+1).".uomid=vu_uom".($index+1).".uomid";
        }
    }
    return $join_str;
    
}

function getUomSelectQuery($proudct, $proudct_cf, $uom, $uom_cf)
{
    global $adb,$current_user;
    
    $prod_uom_qry ="SELECT tablename,columnname FROM vtiger_field WHERE tablename IN ('vtiger_xproduct','vtiger_xproductcf')
and typeofdata LIKE '%UOM%' AND uitype=10 AND presence In (0,2)";
    
    $prod_uom_conv_qry ="SELECT tablename,columnname FROM vtiger_field WHERE tablename IN ('vtiger_xproduct','vtiger_xproductcf')
and typeofdata LIKE '%UOM%' AND uitype in (1,7) AND presence In (0,2)";
    $result = $adb->pquery($prod_uom_qry);
    $result_conv = $adb->pquery($prod_uom_conv_qry);
    $join_str = "";
    for ($index = 0; $index < $adb->num_rows($result); $index++) {
        $columnname = $adb->query_result($result,$index,"columnname");
        $tablename = $adb->query_result($result,$index,"tablename");
        
        $columnname_conv = $adb->query_result($result_conv,$index,"columnname");
        $tablename_conv = $adb->query_result($result_conv,$index,"tablename");
        
        if($adb->query_result($result,$index,"tablename") == 'vtiger_xproduct')
        {
            $join_str .= " $proudct.$columnname uom_id".($index+1).",$uom".($index+1).".uomname AS uom_name".($index+1).",$proudct.$columnname_conv uom_cf".($index+1).",$uom_cf".($index+1).".cf_uom_active uom_act".($index+1).", ";
        }
        elseif($adb->query_result($result,$index,"tablename") == 'vtiger_xproductcf')
        {
            $join_str .= " $proudct_cf.$columnname uom_id".($index+1).",$uom".($index+1).".uomname AS uom_name".($index+1).",$proudct_cf.$columnname_conv uom_cf".($index+1).",$uom_cf".($index+1).".cf_uom_active uom_act".($index+1).", ";
        }
    }
    return $join_str;
    
}

function getUomSelectQuery_new($proudct, $proudct_cf, $uom, $uom_cf)
{
    global $adb,$current_user;
    // Block the UOM based on configuration in Masters - Added By Thiru 24/07/2017
    $mod_name = $_REQUEST['mod_name'];
    $mod_name_array = array('PurchaseOrder','PurchaseInvoice','xGRN','PurchaseReturn','xrPurchaseInvoice','xSalesOrder','xrSalesOrder','SalesInvoice', 'xrSalesInvoice', 'xDispatch', 'xVanSales', 'xSalesReturn', 'StockAdjustment', 'xStockDestruction', 'xVanloadMaster', 'xVanLoading', 'xVanUnloading', 'xVanAllocation');
    if (in_array($mod_name, $mod_name_array)) {
        return getUomSelectQueryConfigured($proudct, $proudct_cf, $uom, $uom_cf);
    }
    $prod_uom_qry ="SELECT tablename,columnname FROM vtiger_field WHERE tablename IN ('vtiger_xproduct','vtiger_xproductcf')
and typeofdata LIKE '%UOM%' AND uitype=10 AND presence In (0,2) ORDER BY tablename DESC";// Order by added - Thiru 16/8/2017
    
    $prod_uom_conv_qry ="SELECT tablename,columnname FROM vtiger_field WHERE tablename IN ('vtiger_xproduct','vtiger_xproductcf')
and typeofdata LIKE '%UOM%' AND uitype in (1,7) AND presence In (0,2) ORDER BY tablename DESC";// Order by added - Thiru 16/8/2017
    $result = $adb->pquery($prod_uom_qry);
    $result_conv = $adb->pquery($prod_uom_conv_qry);
    $join_str = "";
    for ($index = 0; $index < $adb->num_rows($result); $index++) {
        $columnname = $adb->query_result($result,$index,"columnname");
        $tablename = $adb->query_result($result,$index,"tablename");
        
        $columnname_conv = $adb->query_result($result_conv,$index,"columnname");
        $tablename_conv = $adb->query_result($result_conv,$index,"tablename");
        
        if($adb->query_result($result,$index,"tablename") == 'vtiger_xproduct')
        {
            $join_str .= " $proudct.$columnname uom_id".($index+1).",$proudct.$columnname_conv uom_cf".($index+1).", ";
        }
        elseif($adb->query_result($result,$index,"tablename") == 'vtiger_xproductcf')
        {
            $join_str .= " $proudct_cf.$columnname uom_id".($index+1).",$proudct_cf.$columnname_conv uom_cf".($index+1).", ";
        }
    }
    return $join_str;
    
}

// Block the UOM based on configuration in Masters - Added By Thiru 24/07/2017
function getUomSelectQueryConfigured($proudct, $proudct_cf, $uom, $uom_cf)
{
    global $adb,$current_user, $currentModule, $MS_HIDED_UOMS;
    if ($MS_HIDED_UOMS == '0') $MS_HIDED_UOMS = '';
    $uom_define_array = array('UOM1' => 'cf_xproduct_uom1', 'UOM2' => 'cf_xproduct_uom2','UOM3' => 'uom3', 'UOM4' => 'uom4', 'UOM5' => 'uom5', 'UOM6' => 'uom6', 'UOM7' => 'uom7');
    //$uom_conv_define_array = array('UOM1' => 'cf_xproduct_uom1_conversion', 'UOM2' => 'cf_xproduct_uom2_conversion','UOM3' => 'uom3_conversion', 'UOM4' => 'uom4_conversion', 'UOM5' => 'uom5_conversion', 'UOM6' => 'uom6_conversion', 'UOM7' => 'uom7_conversion');

    $prod_uom_qry ="SELECT tablename,columnname FROM vtiger_field WHERE tablename IN ('vtiger_xproduct','vtiger_xproductcf')
and typeofdata LIKE '%UOM%' AND uitype=10 AND presence In (0,2) ORDER BY tablename DESC";
    
    $prod_uom_conv_qry ="SELECT tablename,columnname FROM vtiger_field WHERE tablename IN ('vtiger_xproduct','vtiger_xproductcf')
and typeofdata LIKE '%UOM%' AND uitype in (1,7) AND presence In (0,2) ORDER BY tablename DESC";
    $result = $adb->pquery($prod_uom_qry);
    $result_conv = $adb->pquery($prod_uom_conv_qry);
    $join_str = "";
    for ($index = 0; $index < $adb->num_rows($result); $index++) {
        $columnname = $adb->query_result($result,$index,"columnname");
        $tablename = $adb->query_result($result,$index,"tablename");
        
        $columnname_conv = $adb->query_result($result_conv,$index,"columnname");
        $tablename_conv = $adb->query_result($result_conv,$index,"tablename");
        
        if($adb->query_result($result,$index,"tablename") == 'vtiger_xproduct')
        {
            if ($uom_define_array[$MS_HIDED_UOMS] != $columnname) {
                $join_str .= " $proudct.$columnname uom_id".($index+1).",$proudct.$columnname_conv uom_cf".($index+1).", ";
            }
        }
        elseif($adb->query_result($result,$index,"tablename") == 'vtiger_xproductcf')
        {
            if ($uom_define_array[$MS_HIDED_UOMS] != $columnname) {
                $join_str .= " $proudct_cf.$columnname uom_id".($index+1).",$proudct_cf.$columnname_conv uom_cf".($index+1).", ";
            }
        }
    }
    return $join_str;
    
}

function getTotalUom()
{
    //return 3;
    global $adb,$current_user;
    
    $prod_uom_qry ="SELECT tablename,columnname FROM vtiger_field WHERE tablename IN ('vtiger_xproduct','vtiger_xproductcf')
and typeofdata LIKE '%UOM%' AND uitype=10 AND presence In (0,2)";
    $result = $adb->pquery($prod_uom_qry);
    
    return $adb->num_rows($result);
    
}

function getAllUom()
{
    global $adb,$current_user;
    
    $uom_qry = "select vtiger_uom.uomid, vtiger_uom.uomname, vtiger_uomcf.cf_uom_active 
             from vtiger_uom Inner Join vtiger_uomcf on vtiger_uom.uomid=vtiger_uomcf.uomid 
             Inner Join vtiger_crmentity on vtiger_crmentity.crmid=vtiger_uom.uomid 
             where vtiger_crmentity.deleted=0 AND vtiger_uomcf.cf_uom_active";
    $result = $adb->pquery($uom_qry);
    
    $arr = array();
    for ($index = 0; $index < $adb->num_rows($result); $index++) {
        $uomid = $adb->query_result($result,$index,"uomid");
        $uomname = $adb->query_result($result,$index,"uomname");
        $cf_uom_active = $adb->query_result($result,$index,"cf_uom_active");
        
        $arr[$uomid] = array("uom_name"=>$uomname, "uom_active"=>$cf_uom_active);
    }
    return $arr;
    
}
function getSalesmanProduct($smid){
	global $adb,$current_user;
	
/*	$ssmprod_qry = "SELECT vtiger_xsalesman.xsalesmanid, vtiger_xsalesman.salesman, vtiger_xsalesmangpmappingcf.saleman_catgp_code,
vtiger_xsalesmangpmappingcf.saleman_catgp_name,vtiger_xsalesmancf.cf_xsalesman_salesman_category_group ,
vtiger_xsalesmangpmappingcf.product_catgp_name,vtiger_xsalesmangpmappingcf.product_catgp_code,
vtiger_xsalesmancf.cf_xsalesman_salesman_category_group,vtiger_xsalesmancf.cf_xsalesman_product_category_group
FROM vtiger_xsalesman 
LEFT JOIN vtiger_xsalesmancf ON vtiger_xsalesman.xsalesmanid =  vtiger_xsalesmancf.xsalesmanid 
LEFT JOIN vtiger_xsalesmangpmapping ON vtiger_xsalesmangpmapping.xsalesmangpmappingid =vtiger_xsalesmancf.cf_xsalesman_salesman_category_group
LEFT JOIN vtiger_xsalesmangpmappingcf ON vtiger_xsalesmangpmapping.xsalesmangpmappingid =vtiger_xsalesmangpmappingcf.xsalesmangpmappingid
LEFT JOIN  vtiger_xcategorygroup ON vtiger_xcategorygroup.xcategorygroupid = vtiger_xsalesmangpmappingcf.saleman_catgp_code
LEFT JOIN vtiger_xproductcategorygroupmapping ON
vtiger_xproductcategorygroupmapping.productcategorygroup = vtiger_xsalesmancf.cf_xsalesman_product_category_group
LEFT JOIN vtiger_xproductgroupmappingrel ON 
vtiger_xproductgroupmappingrel.xproductcategorygroupmappingid = vtiger_xproductcategorygroupmapping.xproductcategorygroupmappingid
WHERE vtiger_xsalesman.xsalesmanid = 59141
;
SELECT vtiger_xproductcategorygroupmapping.productcategorygroup, vtiger_xsalesmancf.cf_xsalesman_product_category_group,
vtiger_xsalesman.salesman,vtiger_xsalesman.xsalesmanid,vtiger_xproductgroupmappingrel.xproductcategorygroupmappingid,
vtiger_xproductgroupmappingrel.productid
FROM vtiger_xsalesman
LEFT JOIN vtiger_xsalesmancf ON vtiger_xsalesman.xsalesmanid = vtiger_xsalesmancf.xsalesmanid
LEFT JOIN vtiger_xproductcategorygroupmapping ON
vtiger_xproductcategorygroupmapping.productcategorygroup = vtiger_xsalesmancf.cf_xsalesman_product_category_group
LEFT JOIN vtiger_xproductgroupmappingrel ON 
vtiger_xproductgroupmappingrel.xproductcategorygroupmappingid = vtiger_xproductcategorygroupmapping.xproductcategorygroupmappingid
WHERE vtiger_xsalesman.xsalesmanid = 59141";*/

$sqlprod = "SELECT vpcf.cf_xproduct_base_uom base_uom_id, '1' as base_uom_cf, vpcf.cf_xproduct_uom1 uom_id1,vpcf.cf_xproduct_uom1_conversion uom_cf1,
			vpcf.cf_xproduct_uom2 uom_id2,vpcf.cf_xproduct_uom2_conversion uom_cf2, vp.uom3 uom_id3,vp.uom3_conversion uom_cf3, vp.uom4 uom_id4,
			vp.uom4_conversion uom_cf4, vp.uom5 uom_id5,vp.uom5_conversion uom_cf5, vp.uom6 uom_id6,vp.uom6_conversion uom_cf6, vp.uom7 uom_id7,
			vp.uom7_conversion uom_cf7, vp.xproductid, vp.productname, vp.length_of_serial_number,vp.type_of_serial_number,vp.track_serial_number,
			vp.productcode, vpcf.cf_xproduct_mrp, vpcf.cf_xproduct_ptr, vpcf.cf_xproduct_pts, vpcf.cf_xproduct_vat, vpcf.cf_xproduct_description,
			if(vpcf.cf_xproduct_active=1, 'Yes', 'No') AS cf_xproduct_active, vp.track_refresh_cycle,vp.track_refresh_noofdays,
			vtiger_xproductcategorygroupmapping.productcategorygroup, vtiger_xsalesmancf.cf_xsalesman_product_category_group,
			vtiger_xsalesman.salesman,vtiger_xsalesman.xsalesmanid,vtiger_xproductgroupmappingrel.xproductcategorygroupmappingid,
			vtiger_xproductgroupmappingrel.productid FROM vtiger_xsalesman
			LEFT JOIN vtiger_xsalesmancf ON vtiger_xsalesman.xsalesmanid = vtiger_xsalesmancf.xsalesmanid
			LEFT JOIN vtiger_xproductcategorygroupmapping ON vtiger_xproductcategorygroupmapping.productcategorygroup = vtiger_xsalesmancf.cf_xsalesman_product_category_group
			LEFT JOIN vtiger_xproductgroupmappingrel ON vtiger_xproductgroupmappingrel.xproductcategorygroupmappingid = vtiger_xproductcategorygroupmapping.xproductcategorygroupmappingid
			INNER JOIN vtiger_Xproduct vp on vp.xproductid = vtiger_xproductgroupmappingrel.productid
			INNER JOIN vtiger_xproductcf vpcf ON vp.xproductid=vpcf.xproductid
			LEFT JOIN vtiger_stocklots stk_lot on stk_lot.productid=vp.xproductid
			INNER JOIN vtiger_crmentity vc ON vc.crmid=vpcf.xproductid 
			INNER JOIN vtiger_xdistributorproductsmappingrel DPMR ON DPMR.productid = vp.xproductid 
			INNER JOIN vtiger_xdistributorproductsmapping DPM ON DPM.xdistributorproductsmappingid = DPMR.xdistributorproductsmappingid
			INNER JOIN vtiger_xdistributorclusterrel DC ON DC.distclusterid = DPM.distributor_cluster_code 
			LEFT JOIN vtiger_xdistributorproductmapping DPMNAME ON DPMNAME.distributorname = DC.distributorid
			LEFT JOIN vtiger_xdistributorproductmappingcf DPMNAMECF ON DPMNAMECF.xdistributorproductmappingid = DPMNAME.xdistributorproductmappingid
			WHERE vp.xproductid > 0 AND vc.deleted=0 AND vpcf.cf_xproduct_active=1 AND vpcf.cf_xproduct_status='Created'
			AND vtiger_xsalesman.xsalesmanid = 59141 AND DC.distributorid = '41994' AND DPM.active = '1' group by vp.xproductid";
}
function getIncentiveSalesman($incentiveid){
	global $adb,$current_user;
		$sql_sm = "SELECT vtiger_xincentivesalesman.xincentivesalesmanid,vtiger_xincentivesalesman.xincentivesettingid,vtiger_xincentivesalesman.xcategorygroupid,vtiger_xsalesman.salesman,vtiger_xsalesmangpmappingcf.saleman_catgp_code,vtiger_xsalesmancf.cf_xsalesman_salesman_category_group
			FROM vtiger_xsalesman 
			LEFT JOIN vtiger_xsalesmancf ON vtiger_xsalesman.xsalesmanid =  vtiger_xsalesmancf.xsalesmanid 
			LEFT JOIN vtiger_xsalesmangpmapping ON vtiger_xsalesmangpmapping.xsalesmangpmappingid =vtiger_xsalesmancf.cf_xsalesman_salesman_category_group
			LEFT JOIN vtiger_xsalesmangpmappingcf ON vtiger_xsalesmangpmapping.xsalesmangpmappingid =vtiger_xsalesmangpmappingcf.xsalesmangpmappingid
			LEFT JOIN vtiger_xincentivesalesman ON vtiger_xincentivesalesman.xcategorygroupid =vtiger_xsalesmangpmappingcf.saleman_catgp_code
			LEFT JOIN vtiger_xincentivesetting ON vtiger_xincentivesetting.xincentivesettingid = vtiger_xincentivesalesman.xincentivesalesmanid
			where vtiger_xincentivesalesman.xincentivesettingid=56749
			group by vtiger_xincentivesalesman.xincentivesalesmanid";
	}

	
	/*
	SELECT vtiger_xincentivesalesman.xincentivesalesmanid,
vtiger_xincentivesalesman.xincentivesettingid,
vtiger_xincentivesalesman.xcategorygroupid,
vtiger_xsalesman.salesman,
vtiger_xsalesman.salesmancode,
vtiger_xsalesmangpmappingcf.saleman_catgp_code,
vtiger_xsalesmancf.cf_xsalesman_salesman_category_group,
vtiger_crmentity.modifiedtime,
vtiger_xincentivesetting.incentive_setting_code AS incentive_setting_code,
vtiger_xincentiveparameter.incentive_description AS incentive_setting_description,
vtiger_xcategorygroup.categorygroupname,
vtiger_xincentiveparameter.incentive_param,
vtiger_xincentiveparameter.xproductid AS product,
vtiger_xincentivefrequency.frequency,
vtiger_xincentivefrequency.incentive_maxcap,
vtiger_xincentivefrequency.incentive_points,
vtiger_xincentivefrequency.incentive_value,
vtiger_crmentity.crmid,
vtiger_crmentity.modifiedtime
FROM vtiger_xincentivesetting
INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=vtiger_xincentivesetting.xincentivesettingid
LEFT JOIN vtiger_xincentivesalesman ON vtiger_xincentivesalesman.xincentivesettingid= vtiger_xincentivesetting.xincentivesettingid
LEFT JOIN vtiger_xsalesmangpmappingcf ON vtiger_xincentivesalesman.xcategorygroupid =vtiger_xsalesmangpmappingcf.saleman_catgp_code
LEFT JOIN vtiger_xsalesmancf ON vtiger_xsalesmangpmappingcf.saleman_catgp_code =vtiger_xsalesmancf.cf_xsalesman_salesman_category_group
LEFT JOIN vtiger_xsalesman ON vtiger_xsalesman.xsalesmanid = vtiger_xsalesmancf.xsalesmanid
LEFT JOIN vtiger_xincentiveparameter ON vtiger_xincentiveparameter.xincentivesettingid=vtiger_xincentivesetting.xincentivesettingid
LEFT JOIN vtiger_xincentivefrequency ON vtiger_xincentivefrequency.xincentivesettingid=vtiger_xincentivesetting.xincentivesettingid
LEFT JOIN vtiger_xcategorygroup ON vtiger_xcategorygroup.xcategorygroupid = vtiger_xincentivesalesman.xcategorygroupid
WHERE vtiger_crmentity.deleted=0
AND vtiger_xincentivesalesman.xincentivesettingid='65633'
AND vtiger_crmentity.modifiedtime BETWEEN '2014-11-01 00:00:00' AND '2014-11-06 23:59:59'
GROUP BY vtiger_xincentivesalesman.xincentivesalesmanid;

Final qry
SELECT vtiger_xincentivesalesman.xincentivesalesmanid, vtiger_xincentivesalesman.xincentivesettingid,
vtiger_xincentivesalesman.xcategorygroupid, vtiger_xsalesman.salesman,
vtiger_xsalesmangpmappingcf.saleman_catgp_code, vtiger_xsalesmancf.cf_xsalesman_salesman_category_group,
vtiger_crmentity.modifiedtime, vtiger_xincentivesetting.incentive_setting_code as incentive_setting_code,
vtiger_xincentiveparameter.incentive_description as incentive_setting_description,
vtiger_xcategorygroup.categorygroupname, vtiger_xincentiveparameter.incentive_param,
vtiger_xincentiveparameter.xproductid as product, vtiger_xincentivefrequency.frequency,
vtiger_xincentivefrequency.incentive_maxcap, vtiger_xincentivefrequency.incentive_points,
vtiger_xincentivefrequency.incentive_value,vtiger_crmentity.crmid,vtiger_crmentity.modifiedtime
FROM vtiger_xincentivesetting
INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=vtiger_xincentivesetting.xincentivesettingid
LEFT JOIN vtiger_xincentivesalesman ON vtiger_xincentivesalesman.xincentivesettingid= vtiger_xincentivesetting.xincentivesettingid
LEFT JOIN vtiger_xsalesmangpmappingcf ON vtiger_xincentivesalesman.xcategorygroupid =vtiger_xsalesmangpmappingcf.saleman_catgp_code
LEFT JOIN vtiger_xsalesmancf ON vtiger_xsalesmangpmappingcf.saleman_catgp_code =vtiger_xsalesmancf.cf_xsalesman_salesman_category_group
LEFT JOIN vtiger_xsalesman ON vtiger_xsalesman.xsalesmanid = vtiger_xsalesmancf.xsalesmanid
LEFT JOIN vtiger_xincentiveparameter ON vtiger_xincentiveparameter.xincentivesettingid=vtiger_xincentivesetting.xincentivesettingid
LEFT JOIN vtiger_xincentivefrequency ON vtiger_xincentivefrequency.xincentivesettingid=vtiger_xincentivesetting.xincentivesettingid
LEFT JOIN vtiger_xcategorygroup ON vtiger_xcategorygroup.xcategorygroupid = vtiger_xincentivesalesman.xcategorygroupid
where vtiger_crmentity.deleted=0 AND vtiger_xincentivesalesman.xincentivesettingid=65337
AND vtiger_crmentity.modifiedtime BETWEEN '2014-11-01 17:46:18' AND '2014-11-06 17:46:18'
group by vtiger_xincentivesalesman.xincentivesalesmanid;

SELECT  vtiger_xincentivesalesman.xcategorygroupid
FROM vtiger_xincentivesalesman,vtiger_xincentivesetting 
where vtiger_xincentivesalesman.xincentivesettingid='65633';

select a.* from a where a.col in (select b.col from b )

SELECT concate(invsm.xcategorygroupid)

	*/
        
function getAllProdInHidden()
{
    global $adb,$current_user;
    $query = "SELECT vtiger_xproduct.xproductid, cf_xproduct_track_pkd, productname FROM vtiger_xproduct 
        Inner join vtiger_xproductcf on vtiger_xproductcf.xproductid=vtiger_xproduct.xproductid 
        Inner join vtiger_crmentity on vtiger_crmentity.crmid=vtiger_xproduct.xproductid 
        where vtiger_crmentity.deleted=0 group by vtiger_xproduct.xproductid";

    $result = $adb->pquery($query);
    $str = "";
    if($adb->num_rows($result) > 0)
    {   
        $num_rows = $adb->num_rows($result);
        for($i=0; $i<$num_rows; $i++)
        {
            $xproductid_v = $adb->query_result($result, $i, 'xproductid');
            $cf_xproduct_track_pkd_v = $adb->query_result($result, $i, 'cf_xproduct_track_pkd');
            $productname_v = $adb->query_result($result, $i, 'productname');

            $str .= '<input type="hidden" name="'.$xproductid_v.'" id="'.$xproductid_v.'" value="'.$cf_xproduct_track_pkd_v.'|##|'.$productname_v.'">';
        }
    }
    return $str;
}

function delete_edit_func($module, $record) {
    global $adb;
     
    if($module == 'xWindowDisplayScheme')
    {
     $addquery = "select count(*) as cnt from vtiger_xwindowdisplayscheme where xwindowdisplayschemeid = $record AND date(form_date) > date(NOW())";
     $addresult = $adb->pquery($addquery);
     //echo "<pre>";print_r($addresult);
     $count=1;
     $retaddresult = $adb->raw_query_result_rowdata($addresult,0); 
     $count=$retaddresult['cnt'];
     //echo '--'.$count;
        if($count>0){
            return true;
        }
        else {
            return false;
        }    
     }
    else 
    {
        return true;    
    }    
    
}
function CheckFunction($myArr, $allowedElements) 
{
    $check = count(array_intersect($myArr, $allowedElements)) == count($myArr);

    if($check) {
        return "Input array contains only allowed elements";
    } else {
        return "Input array contains invalid elements";
    }
}
function getDistributorCompany($distId){
	global $adb;
	 $cmpquery = "SELECT U.reports_to_id, U.user_name, U.is_admin,U.is_sadmin, U.status FROM vtiger_xdistributor D
			    LEFT JOIN vtiger_users U ON  U.id = D.user_reports_to_id
			    WHERE D.xdistributorid = '".$distId."'";
				$cmpresult = $adb->pquery($cmpquery);
				$cmpresultval = array();
				$cmpresultval['reports_to_id'] = $adb->query_result($cmpresult,0,'reports_to_id');
				$cmpresultval['user_name'] = $adb->query_result($cmpresult,0,'user_name');
				$cmpresultval['is_admin'] = $adb->query_result($cmpresult,0,'is_admin');
				$cmpresultval['is_sadmin'] = $adb->query_result($cmpresult,0,'is_sadmin');
				$cmpresultval['status'] = $adb->query_result($cmpresult,0,'status');
		return 	$cmpresultval;
}
function getProductDetail($prodid){

global $adb;

 $query = "SELECT p.xproductid  AS `id`, p.productname AS `name`,p.productcode AS `code` 
			FROM vtiger_xproduct p
			LEFT JOIN vtiger_crmentity ct ON p.xproductid=ct.crmid
			LEFT JOIN vtiger_xproductcf cf ON p.xproductid=cf.xproductid 
			WHERE ct.deleted=0 AND ct.setype='xProduct' AND cf.cf_xproduct_active=1 AND p.xproductid=$prodid";
        $result = $adb->pquery($query);
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $ret = $adb->raw_query_result_rowdata($result,$index);
        }
        return $ret;
}
function getVanLoadMasterRel($id) {
           global $adb;
           $Qry = "SELECT * FROM vtiger_xvanloadmasterprodrel INNER JOIN vtiger_xproduct ON vtiger_xvanloadmasterprodrel.xproductid = vtiger_xproduct.xproductid LEFT JOIN vtiger_xvanloadmasterprodrelcf ON vtiger_xvanloadmasterprodrel.xvanloadmasterprodrelid = vtiger_xvanloadmasterprodrelcf.xvanloadmasterprodrelid LEFT JOIN vtiger_uom ON vtiger_uom.uomid = vtiger_xvanloadmasterprodrel.uomid WHERE vtiger_xvanloadmasterprodrel.xvanloadmasterid=$id";
           //echo $Qry;
           //exit;
           $result = $adb->pquery($Qry);
           $ret = $resultss= array();
		   $productidarr = $line_item_idarr = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
			 $productid = $adb->query_result($result,$index,'xproductid');
			 $line_item_id = $adb->query_result($result,$index,'xvanloadmasterprodrelid');
			 array_push($productidarr,$productid);
			 array_push($line_item_idarr,$line_item_id);
	        $ret[$index] = $adb->raw_query_result_rowdata($result,$index);
			
	     }
		 $combineprodid = implode(',',array_unique($productidarr));
		 $comblineid = implode(',',array_unique($line_item_idarr));
//			$res_ssi = $adb->pquery('select ssi.serialnumber, ssi.stock_type, ssi.trans_line_id from vtiger_xsalestransaction_serialinfo ssi 
//			 join vtiger_xbatch_transfer_info bt on(bt.trans_lineid = ssi.trans_line_id and bt.product_id = ssi.product_id) 
//			 where ssi.trans_line_id  in ('.$comblineid.')  and ssi.product_id in ('.$combineprodid.') and bt.transaction_type = ? and ssi.transaction_type=?' ,array('Van Loading','VL'));			 
//			while($results = $adb->fetch_array($res_ssi)) :
//				$serialkey[$results['trans_line_id']][] = array($results['serialnumber'], $results['stock_type'], $results['trans_line_id']);
//			endwhile;
			//$ret['serialkey'] = $serialkey;
                 //echo '<pre>'; print_r($ret); die;
            return $ret;
       }
function getSerialProductDetail($serialno){
	global $adb;
	$SerialProductDetail=array();
	$select_query = "SELECT ssi.serialnumber, 
					st.productid, 
					p.productname, 
					ci.date_of_purchase, 
					pum.xproductusagemasterid 
					FROM vtiger_stocklots st 
					LEFT JOIN vtiger_stockserialinfo ssi on ssi.stocklots_id=st.id
					LEFT JOIN vtiger_xproduct p on p.xproductid= st.productid
					LEFT JOIN vtiger_xconsumerinfo ci ON ci.serial_no=ssi.serialnumber
					LEFT JOIN vtiger_xproductusagemaster pum on pum.xproductusagemasterid = ci.xproductusagemasterid
					WHERE ssi.serialnumber = '$serialno' AND ssi.serialnumber!='' LIMIT 1";
		
	$serialResult=$adb->pquery($select_query);
	$num_rows = $adb->num_rows($serialResult);

	for($x=0;$x<$num_rows;$x++){
		$ProductDetail['productid']			= $adb->query_result($serialResult,$x,'productid');
		$ProductDetail['date_of_purchase']	= $adb->query_result($serialResult,$x,'date_of_purchase');
		$ProductDetail['xproductusageid']	= $adb->query_result($serialResult,$x,'xproductusagemasterid');
	}
	return $ProductDetail;		
}
function getSerialWarrantyDetail($product_id,$consumer_data, $si_date,$usageid){
	global $adb;
	$purchasedate = $consumer_data;
	$discount =0;
	if(empty($purchasedate)){
		$retailer_conf_days = 0;
		if(file_exists('trackfile/refreshTracking.config.php')) {
			require_once('trackfile/refreshTracking.config.php');
			if($warrenty_noofdays !='')
				$retailer_conf_days = $warrenty_noofdays;
		}
		$salesinvoice_data = array_keys($_SESSION['salesinvoice_data']);
		$purchasedate_SI = strip_tags($si_date);
		
		$purchasedate = date('Y-m-d',strtotime($purchasedate_SI) + (24*3600*$retailer_conf_days));
	}
	if($product_id >0){
		$ProductHirId  = getProductCategoryId($product_id);
	} else {
		$ProductHirId = -1;
	}
	$sel_war_query = "	SELECT 
							WPM.effective_from_date,
							WPM.effective_to_date,
							WPM.revoke_date,
							WP.warranty_policy_description,
							CONCAT(WP.from_month,'-', WP.to_month) AS warranty_period,
							WP.from_month,
							WP.to_month,
							WP.treatment,
							WP.discount,
							WP.apply_on,
							'' as warranty_applicable
						FROM vtiger_xwarrantypolicymapping AS WPM
						INNER JOIN vtiger_crmentity CRM ON CRM.crmid = WPM.xwarrantypolicymappingid 
						INNER JOIN vtiger_xproductusagemaster  PUM ON PUM.xproductusagemasterid = WPM.xproductusagemasterid 
						INNER JOIN vtiger_xwarrantypolicygrouping WPG ON WPG.xwarrantypolicygroupingid = WPM.xwarrantypolicygroupingid 
						INNER JOIN vtiger_xwarrantypolicygroupingrel WPGR ON WPGR.xwarrantypolicygroupingid = WPM.xwarrantypolicygroupingid 						
						INNER JOIN vtiger_xwarrantypolicy WP ON WP.xwarrantypolicyid = WPGR.xwarrantypolicyid 
						WHERE WPM.xprodhierid = $ProductHirId
						AND CRM.deleted = 0";
	if($usageid!=''){
		$sel_war_query .=" AND WPM.xproductusagemasterid = $usageid ";
	}
	$sel_war_pro_query = " AND WPM.xproductid=$product_id"; // If warranty is available for particular product will use this condition else remove it.
	$query = $sel_war_query.$sel_war_pro_query;
	$productResult	= $adb->pquery($query);
	$num_rows 		= $adb->num_rows($productResult);
	if($num_rows == 0 ){
		$query = $sel_war_query;
	}
	$queryResult	= $adb->pquery($query);
	
	foreach($queryResult as $key=>$value){
		$effective_from_date = $value['effective_from_date'];
		$revoke_date = $value['revoke_date'];
		$from_month = $value['from_month'];
		$to_month = $value['to_month'];
		if($effective_from_date <= $revoke_date	|| ($effective_from_date !='' && $revoke_date =='')){
         $currentDate = DATE('Y-m-d');
         if($currentDate>=$purchasedate){
			if($effective_from_date<=$purchasedate && $revoke_date>=$purchasedate ){
				$currentDate = DATE('Y-m-d');
				$months 	 = ceil(abs( strtotime($purchasedate) - strtotime($currentDate) ) / (60*60*24*30));
				if($from_month<=$months && $to_month>=$months ){
					$discount = strip_tags($value['discount']);					
				} 
			}
		}
      }
	}
	return $discount;
}

function chkMandForServerSide($blocks, $req_arr)
{
    //echo '<pre>'; print_r($_REQUEST); die;
    global $adb;
    $mand_fld_arr = array();
    $mand_related_fld_arr = array();
    $module = $_REQUEST['module'];
    
    foreach($blocks as $k=>$v)
    {
        foreach($v as $k1=>$v1)
        {
            foreach($v1 as $k2=>$v2)
            {
                foreach($v2 as $k3=>$v3)
                {
                    //echo "<pre>";print_r($v3);exit;
                    if($v3[4] == "M" || $v3[4] == "MU")
                    {
                        if($v3[2][0] != '' && $v3[1][0]['displaylabel'] != '')
                        {
                            $mand_fld_arr['field_name'][] = $v3[2][0];
                            if(is_array($v3[1][0]))
                                $mand_fld_arr['field_label'][] = $v3[1][0]['displaylabel'];
                            else
                                $mand_fld_arr['field_label'][] = $v3[1][0];
                        }
                    }
                    if($v3[0][0] == 10)
                    {
                        $mand_related_fld_arr['field_name'][] = $v3[2][0];
                        if(is_array($v3[1][0]))
                            $mand_related_fld_arr['field_label'][] = $v3[1][0]['displaylabel'];
                        else
                            $mand_related_fld_arr['field_label'][] = $v3[1][0];
                    }
                }
            }
        }
    }
    //echo '<pre>'; print_r($_REQUEST);
    //echo "<pre>";print_r($mand_related_fld_arr);exit;
    $errror_msg = "";
    for($i=0; $i<count($mand_fld_arr['field_name']); $i++)
    {
        //echo "Hi $i:".trim($req_arr[$mand_fld_arr['field_name'][$i]])."<br/>";
        if(trim($req_arr[$mand_fld_arr['field_name'][$i]]) == '')
        {
            $errror_msg .= $mand_fld_arr['field_label'][$i]." cannot be empty<br/>";
        }
    }
    for($i=0; $i<count($mand_related_fld_arr['field_name']); $i++)
    {
        //echo "Hi $i:".trim($req_arr[$mand_fld_arr['field_name'][$i]])."<br/>";
        if(trim($req_arr[$mand_related_fld_arr['field_name'][$i]]) > 0 && is_numeric($req_arr[$mand_related_fld_arr['field_name'][$i]]))
        {
            if($mand_related_fld_arr['field_name'][$i] != '' && $module != '')
            {
                $rel_module_qry = $adb->pquery("select vtiger_fieldmodulerel.relmodule from vtiger_field Inner Join vtiger_tab on vtiger_field.tabid = vtiger_tab.tabid 
                    Inner Join vtiger_fieldmodulerel on vtiger_field.fieldid = vtiger_fieldmodulerel.fieldid 
                    where vtiger_tab.name='$module' AND vtiger_field.columnname = '{$mand_related_fld_arr['field_name'][$i]}'");
                //echo '<pre>'; print_r($rel_module_qry); 
                
                if($adb->num_rows($rel_module_qry) > 0)
                {
                    $rel_module = $adb->query_result($rel_module_qry, 0, 'relmodule');
                    if($rel_module != '')
                    {
                        $rel_tbl_qry = $adb->pquery("select tablename,entityidfield from vtiger_entityname where modulename = '$rel_module'");
                        
                        if($adb->num_rows($rel_tbl_qry) > 0)
                        {
                            $rel_table = $adb->query_result($rel_tbl_qry, 0, 'tablename');
                            $rel_fld_id = $adb->query_result($rel_tbl_qry, 0, 'entityidfield');
                            if($rel_module != '' && $rel_fld_id != '')
                            {
                                //echo "Hi :"."select count(*) as cnt from $rel_table where $rel_fld_id={$req_arr[$mand_related_fld_arr['field_name'][$i]]}<br/>";
                                if($_REQUEST['si_location_type'] == 'xVan' && $mand_related_fld_arr['field_name'][$i] == 'si_location')
                                {
                                    $ch_data_qry = $adb->pquery("select count(*) as cnt from vtiger_xvan where xvanid={$req_arr[$mand_related_fld_arr['field_name'][$i]]}");
                                }elseif($_REQUEST['cf_xvanloading_from_type'] == 'Van' && $mand_related_fld_arr['field_name'][$i] == 'fromgv'){
                                    $ch_data_qry = $adb->pquery("select count(*) as cnt from vtiger_xvan where xvanid={$req_arr[$mand_related_fld_arr['field_name'][$i]]}");
                                }
                                else
                                {
                                    $ch_data_qry = $adb->pquery("select count(*) as cnt from $rel_table where $rel_fld_id={$req_arr[$mand_related_fld_arr['field_name'][$i]]}");
                                }
                                $data_cnt = $adb->query_result($ch_data_qry, 0, 'cnt');
                                if($data_cnt == 0)
                                {
                                    $_REQUEST[$mand_related_fld_arr['field_name'][$i]] = "";
                                    $errror_msg .= $mand_related_fld_arr['field_label'][$i]." Invalid data<br/>";
                                }
                            }
                        }
                    }
                }
            }
        }
        
    }
    //echo $errror_msg;exit;
    return $errror_msg;
}

function chkMandForSubmit($blocks, $module, $tbl_for_module)
{
    global $adb;
    global $xrsales_location;    
    
    $mand_fld_arr = array();
    $mand_related_fld_arr1 = array();
    $mand_related_fld_arr = array();
    $module = $_REQUEST['module'];
    
    $col_name_arr = array();
    if(count($tbl_for_module) > 0)
    {
        $tbls_for_mod = array();
        foreach($tbl_for_module as $k=>$v)
        {
            $tbls_for_mod[] = "'".$k."'";
        }
        if(count($tbls_for_mod) > 0)
        {
            $tbls_for_mod_str = implode(",", $tbls_for_mod);
            $col_name_qry = $adb->pquery("select columnname, fieldname from vtiger_field where tablename IN ($tbls_for_mod_str)");
            if($adb->num_rows($col_name_qry) > 0)
            {
                for($i=0; $i<$adb->num_rows($col_name_qry); $i++)
                {
                    $columnname_v = $adb->query_result($col_name_qry, $i, 'columnname');
                    $fieldname_v = $adb->query_result($col_name_qry, $i, 'fieldname');
                    if($columnname_v != '' && $fieldname_v != '')
                    {
                        $col_name_arr[$fieldname_v] = $columnname_v;
                    }
                }
            }
        }
    }
    foreach($blocks as $k=>$v)
    {
        foreach($v as $k1=>$v1)
        {
            foreach($v1 as $k2=>$v2)
            {
                foreach($v2 as $k3=>$v3)
                {
                    //echo "<pre>";print_r($v3);exit;
                    if($v3[4] == "M" || $v3[4] == "MU")
                    {
                        if($v3[2][0] != '' && $v3[1][0]['displaylabel'] != '')
                        {
                            $mand_fld_arr['field_name'][] = $v3[2][0];
                            if(is_array($v3[1][0]))
                                $mand_fld_arr['field_label'][] = $v3[1][0]['displaylabel'];
                            else
                                $mand_fld_arr['field_label'][] = $v3[1][0];
                        }
                        if($v3[0][0] == 10)
                        {
                            $mand_related_fld_arr1['field_name'][] = $v3[2][0];
                            if(is_array($v3[1][0]))
                                $mand_related_fld_arr1['field_label'][] = $v3[1][0]['displaylabel'];
                            else
                                $mand_related_fld_arr1['field_label'][] = $v3[1][0];
                        }
                    }
                    if($v3[0][0] == 10)
                    {
                        $mand_related_fld_arr['field_name'][] = $v3[2][0];
                        if(is_array($v3[1][0]))
                            $mand_related_fld_arr['field_label'][] = $v3[1][0]['displaylabel'];
                        else
                            $mand_related_fld_arr['field_label'][] = $v3[1][0];
                    }
                }
            }
        }
    }
    if(count($tbl_for_module) > 0)
    {
        $cnt = 0;
        $query_str = "";
        foreach($tbl_for_module as $k=>$v)
        {
           if($k != '' && $v != '')
           {
                if($cnt == 0)
                    $query_str .= " select * from $k ";
                else
                    $query_str .= " Inner Join $k on $k.$v = $prev_tbl.$prev_tbl_id";
                
                $prev_tbl = $k;
                $prev_tbl_id = $v;
                $cnt++;
           }
        }
        //echo "Hi :".$query_str;exit;
        $record = $_REQUEST['record'];
        if($record > 0)
        {
            $data_qry = $adb->pquery("$query_str where $prev_tbl.$prev_tbl_id = '$record'");
            $num_rows = $adb->num_rows($data_qry);
            if($num_rows > 0)
            {
                $errror_msg = "";
                for($i=0; $i<count($mand_fld_arr['field_name']); $i++)
                {
                    $fld_value = $adb->query_result($data_qry, 0, $col_name_arr[$mand_fld_arr['field_name'][$i]]);
                    if($fld_value == "")
                    {
                        //echo  $mand_fld_arr['field_name'][$i]." - ".$fld_value."<br/>";
                        $errror_msg .= $mand_fld_arr['field_label'][$i]." cannot be empty<br/>";
                    }
                }
                
                /*for($i=0; $i<count($mand_related_fld_arr1['field_name']); $i++)
                {
                    $fld_value = $adb->query_result($data_qry, 0, $mand_related_fld_arr1['field_name'][$i]);
                    if($fld_value == "" || $fld_value == 0)
                        $errror_msg .= $mand_related_fld_arr1['field_label'][$i]." cannot be empty<br/>";
                }*/
                
                for($i=0; $i<count($mand_related_fld_arr['field_name']); $i++)
                {
                    //echo "Hi $i:".trim($req_arr[$mand_fld_arr['field_name'][$i]])."<br/>";
                    $fld_value = $adb->query_result($data_qry, 0, $col_name_arr[$mand_related_fld_arr['field_name'][$i]]);
                    if($fld_value > 0 && is_numeric($fld_value))
                    {
                        if($mand_related_fld_arr['field_name'][$i] != '' && $module != '')
                        {
                            $rel_module_qry = $adb->pquery("select vtiger_fieldmodulerel.relmodule from vtiger_field Inner Join vtiger_tab on vtiger_field.tabid = vtiger_tab.tabid 
                                Inner Join vtiger_fieldmodulerel on vtiger_field.fieldid = vtiger_fieldmodulerel.fieldid 
                                where vtiger_tab.name='$module' AND vtiger_field.columnname = '{$col_name_arr[$mand_related_fld_arr['field_name'][$i]]}'");
                            if($adb->num_rows($rel_module_qry) > 0)
                            {
                                $rel_module = $adb->query_result($rel_module_qry, 0, 'relmodule');
                                if($rel_module != '')
                                {
                                    $rel_tbl_qry = $adb->pquery("select tablename,entityidfield from vtiger_entityname where modulename = '$rel_module'");

                                    if($adb->num_rows($rel_tbl_qry) > 0)
                                    {
                                        $rel_table = $adb->query_result($rel_tbl_qry, 0, 'tablename');
                                        $rel_fld_id = $adb->query_result($rel_tbl_qry, 0, 'entityidfield');
                                        if($rel_module != '' && $rel_fld_id != '')
                                        {
                                            //echo "Hi :"."select count(*) as cnt from $rel_table where $rel_fld_id={$req_arr[$mand_related_fld_arr['field_name'][$i]]}<br/>";
                                            $bool_val = false;
                                            if($module == "SalesInvoice" && $fld_value > 0 && $mand_related_fld_arr['field_name'][$i] == 'si_location')
                                            {
                                                $chk_van_loc = $adb->pquery("select count(*) as cnt from vtiger_xvan where xvanid=$fld_value");
                                                $data_cnt_van_loc = $adb->query_result($chk_van_loc, 0, 'cnt');
                                                if($data_cnt_van_loc >= 1)
                                                {
                                                    $bool_val = true;
                                                }
                                                //echo "Hi :".$bool_val;exit;
                                            }
                                            $is_flag = 0;
                                            if(($_REQUEST['type'] == 'VanSales' || $module == 'xrSalesInvoice' || $bool_val) && $mand_related_fld_arr['field_name'][$i] == 'si_location')
                                            {
                                                if($xrsales_location == 'xGodown'){
                                                    $ch_data_qry = $adb->pquery("select count(*) as cnt from vtiger_xgodown where xgodownid=$fld_value");
                                                }else{
                                                    $ch_data_qry = $adb->pquery("select count(*) as cnt from vtiger_xvan where xvanid=$fld_value");
                                                }
                                            }else
                                            {   
												if($module == 'xrSalesOrder' && $rel_table == 'vtiger_xretailer'){
													$is_flag = 1;
													$data_cnt= getCusIdReceivedOrCheck($fld_value);
												}elseif($module == 'xrCollection' && $rel_table == 'vtiger_xretailer'){
													$is_flag = 1;
													$data_cnt= getCusIdReceivedOrCheck($fld_value);
												}else{													
													$ch_data_qry = $adb->pquery("select count(*) as cnt from $rel_table where $rel_fld_id=$fld_value");
												}
												
                                            }
                                            if(!empty($is_flag)){												
												 if($data_cnt == 0){
													 $errror_msg = "Distributor is not Approved the Customer<br/>";
												 }
											}else{
												$data_cnt = $adb->query_result($ch_data_qry, 0, 'cnt');
												 if($data_cnt == 0){
													 $errror_msg .= $mand_related_fld_arr['field_label'][$i]." Invalid data<br/>";
												 }
											}
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                if($errror_msg != "")
                {
                    echo "<input type='hidden' name='err_msg' id='err_msg' value='$errror_msg' />";
                }
            }
        }
    }
    
}


function syncFilesInServer()
{
    global $SYNC_FILES_IN_WEB_SERVERS,$SYNC_FILES_IN_WEB_SERVERS_SHELL_FILE,$adb;
    
    if($SYNC_FILES_IN_WEB_SERVERS==true)
       {
           if(file_exists($SYNC_FILES_IN_WEB_SERVERS_SHELL_FILE))
           {
               $compResult=$adb->pquery("SELECT organizationcode FROM vtiger_organizationdetails");
               $compCode='';
               
               if($adb->num_rows($compResult)>0)
                   $compCode=$adb->query_result($compResult,0,'organizationcode');
               
               shell_exec("sh ".$SYNC_FILES_IN_WEB_SERVERS_SHELL_FILE." ".$compCode);
           }
       }
}

function freshSIserial($serial, $logic){
    global $adb;
    $sql = "select cf_salesinvoice_transaction_number,serialnumber,return_serialnumber from vtiger_xsalestransaction_serialinfo 
                       Inner Join vtiger_siproductrel on vtiger_siproductrel.lineitem_id = vtiger_xsalestransaction_serialinfo.trans_line_id 
                       Inner Join vtiger_salesinvoicecf on vtiger_salesinvoicecf.salesinvoiceid = vtiger_siproductrel.id ";
    if($logic != 'pdf'){
        $sql .= " where vtiger_xsalestransaction_serialinfo.return_serialnumber='{$serial}' ";
    }else{
        $sql .= " where vtiger_xsalestransaction_serialinfo.serialnumber='{$serial}' ";
    }
    $sql .= " AND vtiger_xsalestransaction_serialinfo.transaction_type='SI'";
    $serial_txn_no = $adb->pquery($sql);
    if($adb->num_rows($serial_txn_no)>0){
        $compCode['transaction_number'] = $adb->query_result($serial_txn_no, 0, 'cf_salesinvoice_transaction_number');
        $compCode['newserial'] = $adb->query_result($serial_txn_no, 0, 'serialnumber');
        $compCode['oldserial'] = $adb->query_result($serial_txn_no, 0, 'return_serialnumber');
    }               
    return $compCode;                   
}
  
    function getAllChannelLevel() {
        global $adb;
       
            $Qry = "SELECT cf_xchannellevel_level_name,vtiger_xchannellevel.xchannellevelid FROM vtiger_xchannellevel"
                    . " INNER JOIN vtiger_xchannellevelcf ON (vtiger_xchannellevel.xchannellevelid = vtiger_xchannellevelcf.xchannellevelid)"
                    . " INNER JOIN vtiger_crmentity ON (vtiger_crmentity.crmid = vtiger_xchannellevelcf.xchannellevelid)"
                    . " WHERE vtiger_crmentity.deleted = 0 AND vtiger_xchannellevelcf.cf_xchannellevel_active = 1"; 
       
           //echo $Qry;
           $result = $adb->pquery($Qry);
           $ret = array();
            for ($index = 0; $index < $adb->num_rows($result); $index++) {
				$ret[] = $adb->raw_query_result_rowdata($result,$index);
			}
        return $ret;
    }

    function getChannelChild($chn_level,$qry =''){
        global $adb;
        
        $query = "SELECT cf_xchannelhierarchy_code_path FROM vtiger_xchannelhierarchy 
            INNER JOIN vtiger_xchannelhierarchycf ON (vtiger_xchannelhierarchy.xchannelhierarchyid = vtiger_xchannelhierarchycf.xchannelhierarchyid)
            INNER JOIN vtiger_crmentity ON (vtiger_crmentity.crmid = vtiger_xchannelhierarchycf.xchannelhierarchyid)
            WHERE vtiger_crmentity.deleted = 0 AND vtiger_xchannelhierarchycf.cf_xchannelhierarchy_active = 1 AND Cf_xchannelhierarchy_level = $chn_level";
        
        $result = $adb->pquery($query);
        $chnl_path = array();
        for ($index = 0; $index < $adb->num_rows($result); $index++) {
            $chnl_path[] = $adb->query_result($result, $index, 'cf_xchannelhierarchy_code_path');
        }
        $index = 0;
        if(empty($chnl_path)){
            return;
            die;
        }
        $total = count($chnl_path);
        $likeqry = '';
        foreach($chnl_path as $value){
            
            if($index < $total - 1){
                $likeqry .= " vtiger_xchannelhierarchycf.cf_xchannelhierarchy_code_path LIKE '%$value%' OR ";
            }else{
                $likeqry .= " vtiger_xchannelhierarchycf.cf_xchannelhierarchy_code_path LIKE '%$value%' ";
            }
            
            $index++;
        }
        
        $chnl_query = "SELECT vtiger_xchannelhierarchy.*,vtiger_xchannelhierarchycf.*,vtiger_crmentity.* FROM vtiger_xchannelhierarchy 
                INNER JOIN vtiger_xchannelhierarchycf ON (vtiger_xchannelhierarchy.xchannelhierarchyid = vtiger_xchannelhierarchycf.xchannelhierarchyid)
                INNER JOIN vtiger_crmentity ON (vtiger_crmentity.crmid = vtiger_xchannelhierarchycf.xchannelhierarchyid)
                WHERE vtiger_crmentity.deleted = 0 AND vtiger_xchannelhierarchycf.cf_xchannelhierarchy_active = 1
                AND ( $likeqry )";
        $chnl_ids = array();
        if($qry == 1){
            return $chnl_query;
            die;
        }
        
        $chn_result = $adb->pquery($chnl_query);
        
        for ($index = 0; $index < $adb->num_rows($chn_result); $index++) {
            $chnl_ids[] = $adb->query_result($chn_result, $index, 'xchannelhierarchyid');
        }
        
        return $chnl_ids;
        
    }
    
    function getRetailerChannelGodown($distid, $retailerid){
        global $adb;
        $retailerChannelQry = "SELECT xgodownid,godown_name FROM vtiger_xretailer 
            INNER JOIN vtiger_xretailercf ON (vtiger_xretailer.xretailerid = vtiger_xretailercf.xretailerid )
            INNER JOIN vtiger_xgodown ON (vtiger_xretailercf.cf_xretailer_channel_type = vtiger_xgodown.xchannelhierarchyid )
            INNER JOIN vtiger_crmentity ON (vtiger_crmentity.crmid = vtiger_xretailer.xretailerid)
            WHERE vtiger_crmentity.deleted = 0 AND vtiger_xretailer.xretailerid = $retailerid
            AND vtiger_xretailer.distributor_id = $distid AND vtiger_xgodown.xgodown_active = 1 AND vtiger_xgodown.xgodown_distributor = $distid";
        
            $result = $adb->pquery($retailerChannelQry);
          
            
            $retailchangodown = array();
            
            if($adb->num_rows($result) > 0){
                $retailchangodown[0][] = $adb->query_result($result, 0, 'xgodownid');
                $retailchangodown[0][] = $adb->query_result($result, 0, 'godown_name');
            }
            
            
        return $retailchangodown;
    }
    
    
    function validateChannel($modName,$statusa,$nextStage,$transID){
        global $adb;
        //Channel Based Pricing
        $configKey      = array('CHANNEL_LEVEL','CHANNEL_BASE_PRICE');
        $arrChannel     = getConfig($configKey);

        if($arrChannel['CHANNEL_LEVEL'] && $arrChannel['CHANNEL_BASE_PRICE']){
            if($modName == 'distributor'){
                $query = "SELECT * FROM vtiger_crmentity INNER JOIN vtiger_crmentityrel
                ON (vtiger_crmentity.crmid = vtiger_crmentityrel.crmid) 
                WHERE vtiger_crmentity.deleted = 0 AND vtiger_crmentityrel.relmodule = 'xChannelHierarchy'
                AND vtiger_crmentity.crmid = $transID";
                $result = $adb->mquery($query);


                if($adb->num_rows($result) > 0){
                    updateTransactionStatus($modName,$statusa,$nextStage,$transID);
                    return json_encode(array('status'=>true, 'channelexist'=>true));
                }else{
                    return json_encode(array('status'=>true, 'channelexist'=>false));
                }

            }
        }
    }
    
    //get ClaimHeadType for hiding the button
    function getclaimHeadTypevalue($claimHeadId)
    {
        global $adb;
        $getClaimHeadType_qry = "SELECT `claim_head_type` FROM `vtiger_xclaimhead` WHERE 1=1 AND xclaimheadid='$claimHeadId'";
        $getClaimHeadType_res = $adb->pquery($getClaimHeadType_qry);
        return $adb->query_result($getClaimHeadType_res, 0, 'claim_head_type');
    }
    /*
     * Get retailer based on the Distributor Cluster
     */
    function getRetaileBasedOnDC(){
        
    }
    
    function prodDpMapping($modName,$statusa,$nextStage,$transID){

        global $adb,$DIST_CLUST_CODE,$current_user; //$MS_LBL_ALLOW_AUDIT_LOG;
        
        $distClsGrpId = $DIST_CLUST_CODE;

        if(!empty($distClsGrpId)){
           // if($MS_LBL_ALLOW_AUDIT_LOG == 'True'){
            $arr = new importmig();
            $arr->module = 'xDistributorProductsMapping';
            $arr->action = 'WorkFlowInsert';
            $arr->currenttime = date("Y-m-d H:i:s");
            
            //Audit Insert 
            $trialId = $arr->getTriallogId();
            $trialId = $trialId + 1;
            $fieldIdSql = "insert into vtiger_audit_trial (auditid,userid,module,action,recordid,actiondate) values('" . $trialId . "','" . $current_user->id . "','" . $arr->module . "','" . $arr->action . "','','" . $arr->currenttime . "')";
            $res = $adb->query($fieldIdSql);
            //}
            $sql = "select xdistributorproductsmappingid from vtiger_xdistributorproductsmapping where distributor_cluster_code = ? order by xdistributorproductsmappingid desc limit 0,1";
            $res = $adb->pquery($sql, array($DIST_CLUST_CODE));
            $dpmid = $adb->query_result($res, 0, "xdistributorproductsmappingid");
            
            if(empty($dpmid)){
                
                $xdm=  CRMEntity::getInstance('xDistributorProductsMapping');
                
                $xdm->column_fields['productmappingcode']=$prdDpcode;
                $xdm->column_fields['effectivefrom_date']=date("Y-m-d");
                $xdm->column_fields['distributor_cluster_code']=$DIST_CLUST_CODE;
                $xdm->column_fields['active']="1";
                
//                //Crm Insert
//                $crmId = $arr->getCrmlastId();
//                $crmId = $crmId + 1;
//                $fieldIdSql = "insert into vtiger_crmentity (crmid,smcreatorid,smownerid,setype,description,createdtime,modifiedtime) 
//                          values('" . $crmId . "','" . $current_user->id . "','','" . $arr->module . "','','" . $arr->currenttime . "','" . $arr->currenttime . "')";
//                $adb->query($fieldIdSql);
//                
//                $maincrmid = $arr->getCrmlastId();
//                $focus = CRMEntity::getInstance($arr->module);
//                $prdDpcode = $focus->seModSeqNumber('increment', $arr->module);
//
//                $sql = "insert into vtiger_xdistributorproductsmapping(xdistributorproductsmappingid,productmappingcode,effectivefrom_date,distributor_cluster_code,active) values(?,?,?,?,?)";
//                $adb->pquery($sql, array($maincrmid, $prdDpcode, date("Y-m-d"), $DIST_CLUST_CODE, '1'));
//
//                $sqlcf = "insert into vtiger_xdistributorproductsmappingcf(xdistributorproductsmappingid) values(?)";
//                $adb->pquery($sqlcf, array($maincrmid));
//                
//                $dpmid = $maincrmid;
                
                $xdm->save("xDistributorProductsMapping");
                $dpmid=$xdm->id;
            }
            
            $sqlhier = "select cf_xproduct_category from vtiger_xproductcf where `xproductid` = ?";
            $res = $adb->mquery($sqlhier, array($transID));
            $hierid = $adb->query_result($res, 0, "cf_xproduct_category");

            $dpmsrel= CRMEntity::getInstance('xDistributorProductsMappingrel');
            
            $dpmsrel->column_fields['producthierid']=$hierid;
            $dpmsrel->column_fields['productid']=$transID;
            $dpmsrel->column_fields['effective_from_date']= date("Y-m-d");
            $dpmsrel->column_fields['xdistributorproductsmappingid']=$dpmid;
            $dpmsrel->column_fields['active']="1";
            
            $dpmsrel->save("xDistributorProductsMappingrel"); 
            
            $sql = "UPDATE vtiger_xdistributorproductsmappingrel SET xdistributorproductsmappingid = ? WHERE xdistributorproductsmappingrelid = ?";
            $adb->pquery($sql, array($dpmid,$dpmsrel->id));
//            //Crm Insert
//            $crmId = $arr->getCrmlastId();
//            $crmId = $crmId + 1;
//            $fieldIdSql = "insert into vtiger_crmentity (crmid,smcreatorid,smownerid,setype,description,createdtime,modifiedtime) 
//                      values('" . $crmId . "','" . $current_user->id . "','','xDistributorProductsMappingrel','','" . $arr->currenttime . "','" . $arr->currenttime . "')";
//            $adb->query($fieldIdSql);
//
//            $prmap = "INSERT INTO vtiger_xdistributorproductsmappingrel (xdistributorproductsmappingrelid,producthierid,productid,effective_from_date,xdistributorproductsmappingid,active) VALUES (?,?,?,?,?,?)";
//            $adb->pquery($prmap, array($crmId, $hierid, $transID, date("Y-m-d"), $dpmid, '1'));
//
//            $prmapcf = "INSERT INTO vtiger_xdistributorproductsmappingrelcf (xdistributorproductsmappingrelid) VALUES (?)";
//            $adb->pquery($prmapcf, array($crmId));
//            
//            //Audit Insert 
//            $trialId = $arr->getTriallogId();
//            $trialId = $trialId + 1;
//            $fieldIdSql = "insert into vtiger_audit_trial (auditid,userid,module,action,recordid,actiondate) values('" . $trialId . "','" . $current_user->id . "','xProduct','DetailView','" . $crmId . "','" . $arr->currenttime . "')";
//            $res = $adb->query($fieldIdSql);
            
        }
        return true;
        
    }
    function CheckColumnName($tableName = '' , $clumnName = ''){
        if(!empty($tableName) && !empty($clumnName)){
          global $adb;
          $findclumnQry ="SELECT count(COLUMN_NAME) as colunmcount FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `COLUMN_NAME`=?  AND `TABLE_NAME`=?";
          $findclumnQryexe = $adb->pquery($findclumnQry , array($clumnName,$tableName));
          if($findclumnQryexe){
              return $adb->query_result($findclumnQryexe,0,'colunmcount');
          }
        }
        return 0;
    }
    function UpdateAPI($modName,$transID){
        global $adb,$Arr_Parent;
        
        if($modName=='Scheme')
            $modName='xScheme';
        if($modName=='distributor')
            $modName='xDistributor';
        $resultcount = 0;
        $sql="SELECT * FROM tbl_meta_data WHERE module_name='".$modName."' order by id";
        $result = $adb->pquery($sql);
        if($result){
             $resultcount = $adb->num_rows($result);
        }
        
        if($modName == 'xChannelHierarchy'){
            $xgeo_focus           = new xChannelHierarchy();
            $xgeo_focus->id       = $transID;
            $xgeo_focus->retrieve_entity_info($transID, "xChannelHierarchy");
            $geography_hierarchy_path       = $xgeo_focus->column_fields['cf_xchannelhierarchy_channel_hierarchy_path'];
            $geohier_code_path              = $xgeo_focus->column_fields['cf_xchannelhierarchy_code_path'];
            $xgeo_level_id                  = $xgeo_focus->column_fields['cf_xchannelhierarchy_level'];
            $xgeo_parent_id                 = $xgeo_focus->column_fields['cf_xchannelhierarchy_parent'];
            $geohiercode                    = $xgeo_focus->column_fields['channelhierarchycode'];
            $geohiername                    = $xgeo_focus->column_fields['channel_hierarchy'];
          
            if(!empty($xgeo_parent_id)){
                $Arr_Parent     = array();
                $resGeoHierarchy= getParentChannelHierarchy($xgeo_parent_id);
                $HierName       = array_reverse(array_values($resGeoHierarchy));
                $HierCode       = array_reverse(array_keys($resGeoHierarchy));
                array_push($HierCode,$geohiercode);
                array_push($HierName,$geohiername);
               
                $geography_hierarchy_path_parent   = implode("//",$HierName);
                $geohier_code_path_parent          = implode("//",$HierCode);
            }else{
                $geography_hierarchy_path_parent   = $geography_hierarchy_path;
                $geohier_code_path_parent          = $geohier_code_path;
            }
            
            
            $sql_geolevel                   = "SELECT vtiger_xchannellevelcf.cf_xchannellevel_hierarchy_level AS level FROM vtiger_xchannellevel INNER JOIN vtiger_xchannellevelcf ON vtiger_xchannellevelcf.xchannellevelid = vtiger_xchannellevel.xchannellevelid WHERE vtiger_xchannellevel.xchannellevelid=?";
            $res_geolevel                   = $adb->pquery($sql_geolevel,array($xgeo_level_id));
            $position_level                 = $adb->query_result($res_geolevel, 0); 
            
            if(!empty($geography_hierarchy_path_parent)){
                $geography_hierarchy_path_array = array_map('trim',explode("//",$geography_hierarchy_path_parent));
            }
           
            if(!empty($geohier_code_path_parent)){
                $geohier_code_path_array        = array_map('trim',explode("//",$geohier_code_path_parent));
            }
            
            
            
            if(count($geography_hierarchy_path_array)==count($geohier_code_path_array)){
                $sql_columnchk        =   "select COLUMN_NAME from information_schema.columns where table_name='tbl_xchannelhierarchy'";
                $res_columnchk        =   $adb->pquery($sql_columnchk);
                if($adb->num_rows($res_columnchk)>0){
                    for ($index = 0; $index < $adb->num_rows($res_columnchk); $index++) {
                        $columnname[]   =   $adb->query_result($res_columnchk, $index);  
                    }
                }
                $AlterQuery     = '';
                $ArrayValue     = '';
                
                for ($j = 1; $j <= count($geography_hierarchy_path_array); $j++) {
                    
                    $key                    = $j-1;
                    $sql_geohierid          = "SELECT channel_hierarchy,xchannelhierarchyid FROM vtiger_xchannelhierarchy WHERE channelhierarchycode=?";
                    $res_geohierid          = $adb->pquery($sql_geohierid,array($geohier_code_path_array[$key]));
                    
                    $xgeohier_level_id      = $adb->query_result($res_geohierid, 0,'xchannelhierarchyid');  
                    $xgeohier_level_name    = $adb->query_result($res_geohierid, 0,'channel_hierarchy'); 
                    
                    $InsQuery           .= "channelhiername_level$j ='$geography_hierarchy_path_array[$key]',channelhiercode_level$j ='$geohier_code_path_array[$key]',channelhier_id_level$j='$xgeohier_level_id',";
                    
                    if(!in_array("channelhiername_level".$j, $columnname)){
                        $AlterQuery     .= "ADD channelhiername_level$j varchar(100) COLLATE 'utf8_general_ci' NOT NULL,";
                    }
                    if(!in_array("channelhiercode_level".$j, $columnname)){
                        $AlterQuery     .= "ADD channelhiercode_level$j varchar(100) COLLATE 'utf8_general_ci' NOT NULL,";
                    }
                    if(!in_array("channelhier_id_level".$j, $columnname)){
                        $AlterQuery     .= "ADD channelhier_id_level$j varchar(100) COLLATE 'utf8_general_ci' NOT NULL,";
                    }
                    
                    $Update_Previous_Name   = "channelhiername_level$j = '$xgeohier_level_name'";
                    $Update_Previous_Code   = "channelhiercode_level$j = '$geohier_code_path_array[$key]'";
                }
                if(!empty($AlterQuery)){
                   $AlterTable      = " ALTER TABLE `tbl_xchannelhierarchy` ";
                   $AleterQueryAll  = $AlterTable.trim($AlterQuery,",");
                   $adb->pquery($AleterQueryAll);
                   
                }
                
                $adb->pquery("UPDATE tbl_xchannelhierarchy SET $Update_Previous_Name  WHERE $Update_Previous_Code",array());
                
                $adb->pquery("DELETE FROM tbl_xchannelhierarchy WHERE xchannelhierarchyid=?",array($transID));
                $relQuery   = "INSERT INTO tbl_xchannelhierarchy SET xchannelhierarchyid          =?,"
                                                        . "status               =?,"
                                                        . "level_key            =?,"
                                                        . "level_id             =?,"
                                                        . "channel_hierarchy          =?,"
                                                        . "parent_id            =?,"
                                                        . "channelhierarchycode          =?,"
                                                        . "$InsQuery"
                                                        . "created_date         =NOW(),"
                                                        . "modified_date        =NOW()";
                //$ArrayValue     = trim($ArrayValue,",");
                $res = $adb->pquery($relQuery,array($transID,$xgeo_focus->column_fields['cf_xchannelhierarchy_active'],$position_level,$xgeo_level_id,$geohiername,$xgeo_parent_id,$geohiercode));
                #PRINT_R($res);EXIT;
                
                
            }
            
        }
        
        if($modName == 'xGeoHier'){
            $xgeo_focus           = new xGeoHier();
            $xgeo_focus->id       = $transID;
            $xgeo_focus->retrieve_entity_info($transID, "xGeoHier");
            $geography_hierarchy_path       = $xgeo_focus->column_fields['cf_xgeohier_geography_hierarchy_path'];
            $geohier_code_path              = $xgeo_focus->column_fields['cf_xgeohier_code_path'];
            $xgeo_level_id                  = $xgeo_focus->column_fields['cf_xgeohier_level'];
            $xgeo_parent_id                 = $xgeo_focus->column_fields['cf_xgeohier_parent'];
            $geohiercode                    = $xgeo_focus->column_fields['geohiercode'];
            $geohiername                    = $xgeo_focus->column_fields['geohiername'];
           
            if(!empty($xgeo_parent_id)){
                $Arr_Parent     = array();
                $resGeoHierarchy= getParentGeoHierarchy($xgeo_parent_id);
                $HierName       = array_reverse(array_values($resGeoHierarchy));
                $HierCode       = array_reverse(array_keys($resGeoHierarchy));
                array_push($HierCode,$geohiercode);
                array_push($HierName,$geohiername);
                
                $geography_hierarchy_path_parent   = implode("//",$HierName);
                $geohier_code_path_parent          = implode("//",$HierCode);
            }else{
                $geography_hierarchy_path_parent   = $geography_hierarchy_path;
                $geohier_code_path_parent          = $geohier_code_path;
            }
            
            
            $sql_geolevel                   = "SELECT vtiger_xgeohiermetacf.cf_xgeohiermeta_hierarchy_level AS level FROM vtiger_xgeohiermeta INNER JOIN vtiger_xgeohiermetacf ON vtiger_xgeohiermetacf.xgeohiermetaid = vtiger_xgeohiermeta.xgeohiermetaid WHERE vtiger_xgeohiermeta.xgeohiermetaid=?";
            $res_geolevel                   = $adb->pquery($sql_geolevel,array($xgeo_level_id));
            $position_level                 = $adb->query_result($res_geolevel, 0); 
            
            if(!empty($geography_hierarchy_path_parent)){
                $geography_hierarchy_path_array = array_map('trim',explode("//",$geography_hierarchy_path_parent));
            }
           
            if(!empty($geohier_code_path_parent)){
                $geohier_code_path_array        = array_map('trim',explode("//",$geohier_code_path_parent));
            }
            
            if(count($geography_hierarchy_path_array)==count($geohier_code_path_array)){
                $sql_columnchk        =   "select COLUMN_NAME from information_schema.columns where table_name='tbl_xgeohier'";
                $res_columnchk        =   $adb->pquery($sql_columnchk);
                if($adb->num_rows($res_columnchk)>0){
                    for ($index = 0; $index < $adb->num_rows($res_columnchk); $index++) {
                        $columnname[]   =   $adb->query_result($res_columnchk, $index);  
                    }
                }
                $AlterQuery     = '';
                $ArrayValue     = '';
                for ($j = 1; $j <= count($geography_hierarchy_path_array); $j++) {
                    
                    $key                    = $j-1;
                    $sql_geohierid          = "SELECT geohiername,xgeohierid FROM vtiger_xgeohier WHERE geohiercode=?";
                    $res_geohierid          = $adb->pquery($sql_geohierid,array($geohier_code_path_array[$key]));
                    
                    $xgeohier_level_id      = $adb->query_result($res_geohierid, 0,'xgeohierid');  
                    $xgeohier_level_name    = $adb->query_result($res_geohierid, 0,'geohiername'); 
                    
                    $InsQuery           .= "geohiername_level$j ='$geography_hierarchy_path_array[$key]',geohiercode_level$j ='$geohier_code_path_array[$key]',geohier_id_level$j='$xgeohier_level_id',";
                    
                    if(!in_array("geohiername_level".$j, $columnname)){
                        $AlterQuery     .= "ADD geohiername_level$j varchar(100) COLLATE 'utf8_general_ci' NOT NULL,";
                    }
                    if(!in_array("geohiercode_level".$j, $columnname)){
                        $AlterQuery     .= "ADD geohiercode_level$j varchar(100) COLLATE 'utf8_general_ci' NOT NULL,";
                    }
                    if(!in_array("geohier_id_level".$j, $columnname)){
                        $AlterQuery     .= "ADD geohier_id_level$j varchar(100) COLLATE 'utf8_general_ci' NOT NULL,";
                    }
                    
                    $Update_Previous_Name   = "geohiername_level$j = '$xgeohier_level_name'";
                    $Update_Previous_Code   = "geohiercode_level$j = '$geohier_code_path_array[$key]'";
                }
                if(!empty($AlterQuery)){
                   $AlterTable      = " ALTER TABLE `tbl_xgeohier` ";
                   $AleterQueryAll  = $AlterTable.trim($AlterQuery,",");
                   $adb->pquery($AleterQueryAll);
                }
                
                $adb->pquery("UPDATE tbl_xgeohier SET $Update_Previous_Name  WHERE $Update_Previous_Code",array());
                
                $adb->pquery("DELETE FROM tbl_xgeohier WHERE xgeohierid=?",array($transID));
                $relQuery   = "INSERT INTO tbl_xgeohier SET xgeohierid          =?,"
                                                        . "status               =?,"
                                                        . "level_key            =?,"
                                                        . "level_id             =?,"
                                                        . "geohiername          =?,"
                                                        . "parent_id            =?,"
                                                        . "geohiercode          =?,"
                                                        . "$InsQuery"
                                                        . "created_date         =NOW(),"
                                                        . "modified_date        =NOW()";
                //$ArrayValue     = trim($ArrayValue,",");
                $res = $adb->pquery($relQuery,array($transID,$xgeo_focus->column_fields['cf_xgeohier_active'],$position_level,$xgeo_level_id,$geohiername,$xgeo_parent_id,$geohiercode));
                #PRINT_R($res);EXIT;
                
                
            }
            
        }
        if($modName == 'xProdHier'){
            $xpro_focus           = new xProdHier();
            $xpro_focus->id       = $transID;
            $xpro_focus->retrieve_entity_info($transID, "xProdHier");
            
            $prography_hierarchy_path       = $xpro_focus->column_fields['cf_xprodhier_product_hierarchy_path'];
            $prohier_code_path              = $xpro_focus->column_fields['cf_xprodhier_code_path'];
            $xpro_level_id                  = $xpro_focus->column_fields['cf_xprodhier_level'];
            $xpro_parent_id                 = $xpro_focus->column_fields['cf_xprodhier_parent'];
            $prodhiercode                   = $xpro_focus->column_fields['prodhiercode'];
            $prodhiername                   = $xpro_focus->column_fields['prodhiername'];
           
            if(!empty($xpro_parent_id)){
                $Arr_Parent     = array();
                $resProHierarchy= getParentProHierarchy($xpro_parent_id);
                $HierName       = array_reverse(array_values($resProHierarchy));
                $HierCode       = array_reverse(array_keys($resProHierarchy));
                array_push($HierCode,$prodhiercode);
                array_push($HierName,$prodhiername);
                
                $prography_hierarchy_path_parent   = implode("//",$HierName);
                $prohier_code_path_parent          = implode("//",$HierCode);
            }else{
                $prography_hierarchy_path_parent   = $prography_hierarchy_path;
                $prohier_code_path_parent          = $prohier_code_path;
            }
            
            
            $sql_prolevel                   = "SELECT vtiger_xprodhiermetacf.cf_xprodhiermeta_hierarchy_level AS level FROM vtiger_xprodhiermeta INNER JOIN vtiger_xprodhiermetacf ON vtiger_xprodhiermetacf.xprodhiermetaid = vtiger_xprodhiermeta.xprodhiermetaid WHERE vtiger_xprodhiermeta.xprodhiermetaid=?";
            $res_prolevel                   = $adb->pquery($sql_prolevel,array($xpro_level_id));
            $position_level                 = $adb->query_result($res_prolevel, 0); 
            
            if(!empty($prography_hierarchy_path_parent)){
                $prography_hierarchy_path_array = array_map('trim',explode("//",$prography_hierarchy_path_parent));
            }
           
            if(!empty($prohier_code_path_parent)){
                $prohier_code_path_array        = array_map('trim',explode("//",$prohier_code_path_parent));
            }
            
            if(count($prography_hierarchy_path_array)==count($prohier_code_path_array)){
                $sql_columnchk        =   "select COLUMN_NAME from information_schema.columns where table_name='tbl_xprodhier'";
                $res_columnchk        =   $adb->pquery($sql_columnchk);
                if($adb->num_rows($res_columnchk)>0){
                    for ($index = 0; $index < $adb->num_rows($res_columnchk); $index++) {
                        $columnname[]   =   $adb->query_result($res_columnchk, $index);  
                    }
                }
                $AlterQuery     = '';
                $ArrayValue     = '';
                for ($j = 1; $j <= count($prography_hierarchy_path_array); $j++) {
                    
                    $key                    = $j-1;
                    $sql_prohierid          = "SELECT prodhiername,xprodhierid FROM vtiger_xprodhier WHERE prodhiercode=?";
                    $res_prohierid          = $adb->pquery($sql_prohierid,array($prohier_code_path_array[$key]));
                    
                    $xprohier_level_id      = $adb->query_result($res_prohierid, 0,'xprodhierid');  
                    $xprohier_level_name    = $adb->query_result($res_prohierid, 0,'prodhiername'); 
                    
                    $InsQuery           .= "prohiername_level$j ='$prography_hierarchy_path_array[$key]',prohiercode_level$j ='$prohier_code_path_array[$key]',prohier_id_level$j='$xprohier_level_id',";
                    
                    if(!in_array("prohiername_level".$j, $columnname)){
                        $AlterQuery     .= "ADD prohiername_level$j varchar(100) COLLATE 'utf8_general_ci' NOT NULL,";
                    }
                    if(!in_array("prohiercode_level".$j, $columnname)){
                        $AlterQuery     .= "ADD prohiercode_level$j varchar(100) COLLATE 'utf8_general_ci' NOT NULL,";
                    }
                    if(!in_array("prohier_id_level".$j, $columnname)){
                        $AlterQuery     .= "ADD prohier_id_level$j varchar(100) COLLATE 'utf8_general_ci' NOT NULL,";
                    }
                    
                    $Update_Previous_Name   = "prohiername_level$j = '$xprohier_level_name'";
                    $Update_Previous_Code   = "prohiercode_level$j = '$prohier_code_path_array[$key]'";
                }
                if(!empty($AlterQuery)){
                   $AlterTable      = " ALTER TABLE `tbl_xprodhier` ";
                   $AleterQueryAll  = $AlterTable.trim($AlterQuery,",");
                   $adb->pquery($AleterQueryAll);
                }
                
                $adb->pquery("UPDATE tbl_xprodhier SET $Update_Previous_Name  WHERE $Update_Previous_Code",array());
                
                $adb->pquery("DELETE FROM tbl_xprodhier WHERE xprodhierid=?",array($transID));
                $relQuery   = "INSERT INTO tbl_xprodhier SET xprodhierid          =?,"
                                                        . "status               =?,"
                                                        . "level_key            =?,"
                                                        . "level_id             =?,"
                                                        . "prodhiername          =?,"
                                                        . "parent_id            =?,"
                                                        . "prodhiercode          =?,"
                                                        . "$InsQuery"
                                                        . "created_date         =NOW(),"
                                                        . "modified_date        =NOW()";
                //$ArrayValue     = trim($ArrayValue,",");
                $res = $adb->pquery($relQuery,array($transID,$xpro_focus->column_fields['cf_xprodhier_active'],$position_level,$xpro_level_id,$prodhiername,$xpro_parent_id,$prodhiercode));
                #PRINT_R($res);EXIT;
                
                
            }
            
        }
        if($modName == 'UOM' && !empty($resultcount)){
            $paramValues = array($transID);
            $fieldcountqry =  "SELECT count(tabid) as fieldcount FROM `vtiger_field` WHERE (`tablename` = 'vtiger_xproduct' OR `tablename` = 'vtiger_xproductcf') AND `typeofdata` LIKE '%uom%' AND `uitype` = '10'";
            $fieldcountqryexe = $adb->pquery($fieldcountqry,array());
            if($fieldcountqryexe){
               $fieldcount = $adb->query_result($fieldcountqryexe,0,'fieldcount');
            }
            if(!empty($fieldcount)){
                for($ui = 0; $ui <=$fieldcount+1; $ui++ ){
                    $updatequery = '';
                    if($ui == 0){
                        $updatequery = "UPDATE tbl_xproduct_mo AS b INNER JOIN vtiger_uom g ON b.base_uom_id = g.uomid
                        SET b.base_uom = g.uomname ,b.creatdate = now(),incrementflag = 2  WHERE b.base_uom_id = ? ";
                    }else{
                       $ishas = CheckColumnName('tbl_xproduct_mo' ,"uom".$ui."_id");
                       if(!empty($ishas)){
                           $updatequery = "UPDATE tbl_xproduct_mo AS b INNER JOIN vtiger_uom g ON b.uom".$ui."_id = g.uomid
                            SET b.uom".$ui." = g.uomname ,b.creatdate = now(),incrementflag = 2  WHERE b.uom".$ui."_id = ? ";
                       } 
                    }
                    if(!empty($updatequery)){                        
                        $insertv = $adb->pquery($updatequery,$paramValues);
                    }
                }  
            }
        }else{
            for ($index = 0; $index < $resultcount; $index++) {
                $res = $adb->raw_query_result_rowdata($result,$index);        
                if($res['is_insert']==1){
                    //JIRA 5719
                    $paramValues = array($transID);
                    $deleteParamValues = array($transID);
                    if($modName=='StocklotsMonth')
                        $deleteParamValues = array();
                    $delete = "DELETE FROM ".$res['dest_table_name']." WHERE ".$res['dest_key_ref'].""; 
                    $resultDelete = $adb->pquery($delete,$deleteParamValues);
                    $insert = $res['update_insert_sql'].$res['view_where_sql'];
                    $insertQry= htmlspecialchars_decode($insert,ENT_QUOTES);
                    $insertQry = str_replace('\"\"','""',$insertQry);
                    if($modName=='StocklotsMonth')
                        $paramValues = array();
                    $resultInsert = $adb->pquery($insertQry,$paramValues);
                }
                elseif($res['is_update']==1){
                    $paramValues    = array($transID);
                    $inserte        = $res['update_insert_sql'];
                    $inserte        = htmlspecialchars_decode($inserte,ENT_QUOTES);
                    $insertv        = $adb->pquery($inserte,$paramValues); 
                    //print_r($insertv);exit;

                }elseif($res['is_procedure']==1){
					require_once 'include/basicmethod.php'; 
					$paramValues    = array();
                    $inserte        = $res['update_insert_sql'];
					$basicboject = new basicmethod();
					$argumentval = explode(',',$res['dest_key_ref']);
					$argumentval = array(
						'##TRANSID##' => $transID
					);
					$inserte = $basicboject->findAndReplace($inserte,$argumentval);
                    $inserte        = htmlspecialchars_decode($inserte,ENT_QUOTES);
                    $insertv        = $adb->pquery($inserte,array()); 
				}
            }
        }
        return true;
    }
    function getParentGeoHierarchy($xgeo_parent_id){
        global $adb,$Arr_Parent;
        $sql_parentgeo                   = "SELECT vtiger_xgeohier.*,vtiger_xgeohiercf.cf_xgeohier_parent FROM vtiger_xgeohier INNER JOIN vtiger_xgeohiercf ON vtiger_xgeohiercf.xgeohierid = vtiger_xgeohier.xgeohierid WHERE vtiger_xgeohier.xgeohierid=?";
        $res_parentgeo                   = $adb->pquery($sql_parentgeo,array($xgeo_parent_id));
        $cf_xgeohier_parent              = $adb->query_result($res_parentgeo, 0,'cf_xgeohier_parent');  
        $geohiername                     = $adb->query_result($res_parentgeo, 0,'geohiername');
        $geohiercode                     = $adb->query_result($res_parentgeo, 0,'geohiercode');
        $Arr_Parent[$geohiercode]        = $geohiername;
        if(!empty($cf_xgeohier_parent)){
            getParentGeoHierarchy($cf_xgeohier_parent);
        }
        return $Arr_Parent;
        
    }
    function getParentChannelHierarchy($xcha_parent_id){
        global $adb,$Arr_Parent;
        $sql_parentgeo                   = "SELECT vtiger_xchannelhierarchy.*,vtiger_xchannelhierarchycf.cf_xchannelhierarchy_parent FROM vtiger_xchannelhierarchy INNER JOIN vtiger_xchannelhierarchycf ON vtiger_xchannelhierarchycf.xchannelhierarchyid = vtiger_xchannelhierarchy.xchannelhierarchyid WHERE vtiger_xchannelhierarchy.xchannelhierarchyid=?";
        $res_parentgeo                   = $adb->pquery($sql_parentgeo,array($xcha_parent_id));
        $cf_xgeohier_parent              = $adb->query_result($res_parentgeo, 0,'cf_xchannelhierarchy_parent');  
        $geohiername                     = $adb->query_result($res_parentgeo, 0,'channel_hierarchy');
        $geohiercode                     = $adb->query_result($res_parentgeo, 0,'channelhierarchycode');
        $Arr_Parent[$geohiercode]        = $geohiername;
        if(!empty($cf_xgeohier_parent)){
            getParentChannelHierarchy($cf_xgeohier_parent);
        }
        return $Arr_Parent;
        
    }
    function getParentProHierarchy($xpro_parent_id){
        global $adb,$Arr_Parent;
        $sql_parent                   = "SELECT vtiger_xprodhier.*,vtiger_xprodhiercf.cf_xprodhier_parent FROM vtiger_xprodhier INNER JOIN vtiger_xprodhiercf ON vtiger_xprodhiercf.xprodhierid = vtiger_xprodhier.xprodhierid WHERE vtiger_xprodhier.xprodhierid=?";
        $res_parent                   = $adb->pquery($sql_parent,array($xpro_parent_id));
        $cf_xprodhier_parent              = $adb->query_result($res_parent, 0,'cf_xprodhier_parent');  
        $prodhiername                     = $adb->query_result($res_parent, 0,'prodhiername');
        $prodhiercode                     = $adb->query_result($res_parent, 0,'prodhiercode');
        $Arr_Parent[$prodhiercode]        = $prodhiername;
        if(!empty($cf_xprodhier_parent)){
            getParentProHierarchy($cf_xprodhier_parent);
        }
        return $Arr_Parent;
        
    }
    function UpdateAPI1($modName,$transID){
        
        global $adb,$DIST_CLUST_CODE,$current_user;
        
            if($modName=='Scheme'){ 
            schemeupdate($transID);
            }
            elseif($modName=='Product' || $modName=='xProduct'){
                $sql="SELECT xschemeid FROM tbl_xscheme_mo_org WHERE `xproductid`='".$transID."'";
                $res=$adb->pquery($sql);
                for ($index = 0; $index < $adb->num_rows($res); $index++) {
                schemeupdate($adb->query_result($res, $index, 'xschemeid'));
                  }
            }
            elseif($modName=='xProductGroup' ){
                $sql="SELECT xschemeid FROM tbl_xscheme_mo_org WHERE `xproductgroupid`='".$transID."'";
                $res=$adb->pquery($sql);
                for ($index = 0; $index < $adb->num_rows($res); $index++) {
                schemeupdate($adb->query_result($res, $index, 'xschemeid'));
                  }
            }
            elseif($modName=='xCategoryGroup' ){
                $sql="SELECT xschemeid FROM tbl_xscheme_mo_org WHERE `xcategorygroupid`='".$transID."'";
                $res=$adb->pquery($sql);
                for ($index = 0; $index < $adb->num_rows($res); $index++) {
                schemeupdate($adb->query_result($res, $index, 'xschemeid'));
                  }
            }
            elseif($modName=='xProdHier' ){
                $sql="SELECT xschemeid FROM tbl_xscheme_mo_org WHERE `xprodhierid`='".$transID."'";
                $res=$adb->pquery($sql);
                for ($index = 0; $index < $adb->num_rows($res); $index++) {
                schemeupdate($adb->query_result($res, $index, 'xschemeid'));
                  }
            }
            
       return true;
    }
    
    
    
    function schemeupdate($transID){
        global $adb,$DIST_CLUST_CODE,$current_user;
        $sql = "DELETE FROM `tbl_xscheme_mo_org` WHERE `xschemeid`='".$transID."'";
                $res = $adb->pquery($sql);
         $sql = "INSERT INTO `tbl_xscheme_mo_org`(`xschemeid`, `xschemeidcf`, `xschemeslabrelidcf`, `xschemeslabrelid`, `xprodhierid`, `xproductid`, `xcategorygroupid`, `xproductgroupid`, `propertyid`, `xschemeproductrelid`, `schemecode`, `scheme_name`, `effective_from_date`, `effective_to_date`, `schemedescription`, `re_apply_excess_achieved`, `schemetype`, `scheme_level`, `scheme_definition`, `apply_schemes_on`, `applicable_on`, `combination_scheme`, `roundoff_rule`, `benefit_type`, `slab_id`, `slab_start`, `slab_min_uom`, `slab_max_uom`, `free_criteria`, `slab_for_every_applicable_on`, `slab_start_uom`, `slab_end`, `slab_end_uom`, `for_every`, `free_product_name`, `free_product_uom`, `free_product_qty`, `discount_amount`, `discount_percentage`, `point`, `minimum`, `maximum`, `product_hierarchy_Path`, `product_name`, `categorygroup`, `productgroup`, `product_property`, `uom_type`, `minimum_qty`, `retailer_channel_hierarchy`, `retailer_value_classification`, `retailer_customer_group`, `retailer_general_classification`, `retailer_potential_classification`, `retailer_billing_mode`, `retailer_billing_type`, `dist_code`, `datetime`, `incrementflag`, `is_active`, `creatdate`, `modified_at`, `is_archived`)
SELECT
    SCH.xschemeid AS  xschemeid, SCHCF.xschemeid AS xschemeidcf,	SCHSLCF.xschemeslabrelid AS xschemeslabrelidcf,    SCHSL.xschemeslabrelid AS xschemeslabrelid,    PRHCF.xprodhierid AS xprodhierid,   MPRO.xproductid AS xproductid,    CG.xcategorygroupid AS xcategorygroupid, PG.xproductgroupid AS xproductgroupid,    PP.propertyid AS propertyid,    SCHPRO.xschemeproductrelid AS xschemeproductrelid,    SCH.schemecode AS `schemecode`, SCHCF.cf_xscheme_scheme_name AS `scheme_name`,    SCHCF.cf_xscheme_effective_from AS `effective_from_date`,
    SCHCF.cf_xscheme_effective_to AS `effective_to_date`,SCH.schemedescription AS `schemedescription`,SCH.is_reapply_excess AS `re_apply_excess_achieved`,    'Consumer Scheme OR Sales Promo' AS `schemetype`,
    CASE (ISNULL(SCH.scheme_level) OR SCH.scheme_level = '') WHEN 1 THEN 0 ELSE SCH.scheme_level END AS `scheme_level`,
    CASE (ISNULL(SCHCF.cf_xscheme_scheme_definition) OR SCHCF.cf_xscheme_scheme_definition = '') WHEN 1 THEN 0 ELSE SCHCF.cf_xscheme_scheme_definition END AS `scheme_definition`,
    CASE (ISNULL(SCHCF.cf_xscheme_apply_schemes_on) OR SCHCF.cf_xscheme_apply_schemes_on = '') WHEN 1 THEN 0 ELSE SCHCF.cf_xscheme_apply_schemes_on END  AS `apply_schemes_on`,
    'Product Level / Invoice Level' AS `applicable_on`,
    IF(SCH.is_combination_scheme = '0', 'NO', 'YES') AS `combination_scheme`,
    CASE (ISNULL(SCH.roundoff_rule) OR SCH.roundoff_rule = '') WHEN 1 THEN 0 ELSE SCH.roundoff_rule END AS `roundoff_rule`,
    CASE (ISNULL(SCHSLCF.cf_xschemeslabrel_benefit_type) OR SCHSLCF.cf_xschemeslabrel_benefit_type = '') WHEN 1 THEN 0 ELSE SCHSLCF.cf_xschemeslabrel_benefit_type END AS `benefit_type`,    SCHSLCF.cf_xschemeslabrel_slab_id AS `slab_id`,    SCHSLCF.cf_xschemeslabrel_slab_start AS `slab_start`, SCHSL.min_uom AS `slab_min_uom`,    SCHSL.max_uom AS `slab_max_uom`,    SCHSL.free_criteria AS `free_criteria`,   SCHSLCF.cf_xschemeslabrel_for_every_applicable_on AS `slab_for_every_applicable_on`,
    CASE (ISNULL(SCHSL.slab_start_uom) OR SCHSL.slab_start_uom = '') WHEN 1 THEN 0 ELSE SCHSL.slab_start_uom END AS `slab_start_uom`,
    SCHSLCF.cf_xschemeslabrel_slab_end AS `slab_end`,
    CASE (ISNULL(SCHSL.slab_end_uom) OR SCHSL.slab_end_uom = '') WHEN 1 THEN 0 ELSE SCHSL.slab_end_uom END AS `slab_end_uom`,
    SCHSLCF.cf_xschemeslabrel_for_every AS `for_every`,
    (SELECT GROUP_CONCAT(FPRO.productcode separator '|') FROM vtiger_xschemeslabfreerel SCHFPR
		LEFT JOIN vtiger_xschemeslabfreerelcf SCHFPRCF ON SCHFPRCF.xschemeslabfreerelid = SCHFPR.xschemeslabfreerelid
		LEFT JOIN vtiger_xproduct FPRO ON FPRO.xproductid = SCHFPRCF.cf_xschemeslabfreerel_productcode
		LEFT JOIN vtiger_crmentity FCRM1 ON FCRM1.crmid = SCHFPRCF.xschemeslabfreerelid
		WHERE SCHFPR.slabid = SCHSL.xschemeslabrelid AND FCRM1.deleted = 0) AS `free_product_name`,
    (SELECT GROUP_CONCAT(SCHFPRCF1.cf_xschemeslabfreerel_uom separator '|') FROM vtiger_xschemeslabfreerel SCHFPR1
		LEFT JOIN vtiger_xschemeslabfreerelcf SCHFPRCF1 ON SCHFPRCF1.xschemeslabfreerelid = SCHFPR1.xschemeslabfreerelid
		LEFT JOIN vtiger_crmentity FCRM2 ON FCRM2.crmid = SCHFPRCF1.xschemeslabfreerelid
		WHERE SCHFPR1.slabid = SCHSL.xschemeslabrelid AND FCRM2.deleted = 0) AS `free_product_uom`,
    (SELECT GROUP_CONCAT(SCHFPRCF2.cf_xschemeslabfreerel_total_quantity separator '|') FROM vtiger_xschemeslabfreerel SCHFPR2
		LEFT JOIN vtiger_xschemeslabfreerelcf SCHFPRCF2 ON SCHFPRCF2.xschemeslabfreerelid = SCHFPR2.xschemeslabfreerelid
		LEFT JOIN vtiger_crmentity FCRM ON FCRM.crmid = SCHFPRCF2.xschemeslabfreerelid
		WHERE SCHFPR2.slabid = SCHSL.xschemeslabrelid AND FCRM.deleted = 0) AS `free_product_qty`,
    SCHSLCF.cf_xschemeslabrel_value AS `discount_amount`,
    SCHSLCF.cf_xschemeslabrel_value AS `discount_percentage`,
    SCHSLCF.cf_xschemeslabrel_value AS `point`,
    SCHSLCF.cf_xschemeslabrel_minimum AS `minimum`,
    SCHSLCF.cf_xschemeslabrel_maximum AS `maximum`,
    CASE (ISNULL(PRHCF.cf_xprodhier_code_path) OR PRHCF.cf_xprodhier_code_path = '') WHEN 1 THEN 0 ELSE PRHCF.cf_xprodhier_code_path END  AS `product_hierarchy_Path`,
    CASE (ISNULL(MPRO.productcode) OR MPRO.productcode = '') WHEN 1 THEN 0 ELSE MPRO.productcode END AS `product_name`,
    CASE (ISNULL(CG.categorygroupcode) OR CG.categorygroupcode = '') WHEN 1 THEN 0 ELSE CG.categorygroupcode END AS `categorygroup`,
    CASE (ISNULL(PG.productgroupcode) OR PG.productgroupcode = '') WHEN 1 THEN 0 ELSE PG.productgroupcode END  AS `productgroup`,
    CASE (ISNULL(PP.propertycode) OR PP.propertycode = '') WHEN 1 THEN 0 ELSE PP.propertycode END AS `product_property`,
    CASE (ISNULL(SCHPRO.uom_type) OR SCHPRO.uom_type = '') WHEN 1 THEN 0 ELSE SCHPRO.uom_type END AS `uom_type`,
    CASE (ISNULL(SCHPRO.minimum_qty) OR SCHPRO.minimum_qty = '') WHEN 1 THEN 0 ELSE SCHPRO.minimum_qty END  AS `minimum_qty`,
    (SELECT GROUP_CONCAT(CHCF.cf_xchannelhierarchy_code_path separator '|') FROM vtiger_xchannelhierarchycf CHCF WHERE FIND_IN_SET(CHCF.xchannelhierarchyid, SCHCF.retailer_channel_hierarchy)) AS `retailer_channel_hierarchy`,
    (SELECT GROUP_CONCAT(VC.valueclasscode separator '|') FROM vtiger_xvalueclassification VC WHERE FIND_IN_SET(VC.xvalueclassificationid, SCHCF.retailer_value_classification)) AS `retailer_value_classification`,
    (SELECT GROUP_CONCAT(CGS.customergroupcode separator '|') FROM vtiger_xcustomergroup CGS WHERE FIND_IN_SET(CGS.xcustomergroupid, SCHCF.retailer_customer_group)) AS `retailer_customer_group`,
    (SELECT GROUP_CONCAT(GC.generalclasscode separator '|') FROM vtiger_xgeneralclassification GC WHERE FIND_IN_SET(GC.xgeneralclassificationid, SCHCF.retailer_general_classification)) AS `retailer_general_classification`,
    (SELECT GROUP_CONCAT(PC.potentialclasscode separator '|') FROM vtiger_xpotentialclassification PC WHERE FIND_IN_SET(PC.xpotentialclassificationid, SCHCF.retailer_potential_classification)) AS `retailer_potential_classification`,
    (SELECT GROUP_CONCAT(CM.collectionmethodcode separator '|') FROM vtiger_xcollectionmethod CM WHERE FIND_IN_SET(CM.xcollectionmethodid, SCHCF.retailer_billing_mode)) AS `retailer_billing_mode`,
    'Van Sales / Counter Sales / Normal' AS `retailer_billing_type`,
    (SELECT GROUP_CONCAT(DIS.distributorcode separator ',') FROM vtiger_xdistributor DIS WHERE DIS.xdistributorid IN (
        SELECT DCM.distributorid FROM vtiger_xdistributorclusterrel DCM
        INNER JOIN vi_xschemedistrevoke_mo SDR ON SDR.xditsributorid = DCM.distributorid
        WHERE SDR.xschemeid = SCH.xschemeid AND FIND_IN_SET(DCM.distclusterid, SCHCF.scheme_distributor_cluster))) AS `dist_code`,
    NOW() AS `datetime`,
    CASE WHEN SCHCF.cf_xscheme_active = 0 THEN 0 WHEN CRM.createdtime <> CRM.modifiedtime THEN 2 ELSE 1 END AS `incrementflag`,
    SCHCF.cf_xscheme_active AS `is_active`,
    IF (CRM.createdtime <= CRM.modifiedtime, CRM.modifiedtime, CRM.createdtime) AS `creatdate`,
	'' AS modified_at,
	0 AS is_archived
	
FROM vtiger_xscheme SCH
INNER JOIN vtiger_xschemecf SCHCF ON SCHCF.xschemeid = SCH.xschemeid
INNER JOIN vtiger_crmentity CRM ON CRM.crmid = SCH.xschemeid
LEFT JOIN vtiger_crmentityrel CRMREL ON (CRMREL.crmid = SCH.xschemeid AND CRMREL.module = 'xScheme' AND CRMREL.relmodule = 'xSchemeslabrel')
LEFT JOIN vtiger_xschemeslabrel SCHSL ON SCHSL.xschemeslabrelid = CRMREL.relcrmid
LEFT JOIN vtiger_xschemeslabrelcf SCHSLCF ON SCHSLCF.xschemeslabrelid = SCHSL.xschemeslabrelid
LEFT JOIN vtiger_crmentity CRMSL ON CRMSL.crmid = SCHSL.xschemeslabrelid
LEFT JOIN vtiger_crmentityrel CRMRELPR ON (CRMRELPR.crmid = SCH.xschemeid AND CRMRELPR.module = 'xScheme' AND CRMRELPR.relmodule = 'xSchemeproductrel')
LEFT JOIN vtiger_xschemeproductrel SCHPRO ON SCHPRO.xschemeproductrelid = CRMRELPR.relcrmid
LEFT JOIN vtiger_xschemeproductrelcf SCHPROCF ON SCHPROCF.xschemeproductrelid = SCHPRO.xschemeproductrelid
LEFT JOIN vtiger_crmentity CRMPR ON CRMPR.crmid = SCHPRO.xschemeproductrelid
LEFT JOIN vtiger_xprodhiercf PRHCF ON PRHCF.xprodhierid = SCHPRO.xprodhierid
LEFT JOIN vtiger_xproduct MPRO ON MPRO.xproductid = SCHPRO.productcode
LEFT JOIN vtiger_xcategorygroup CG ON CG.xcategorygroupid = SCHPRO.xcategorygroupid
LEFT JOIN vtiger_xproductgroup PG ON PG.xproductgroupid = SCHPRO.xproductgroupid
LEFT JOIN vi_product_property PP ON PP.propertyid = SCHPRO.product_property
WHERE CRM.deleted = 0 AND CRMSL.deleted = 0 AND CRMPR.deleted = 0 AND  DATE(SCHCF.cf_xscheme_effective_from) <= CURDATE() AND DATE(SCHCF.cf_xscheme_effective_to) >= CURDATE() AND DATE(SCH.revoke_date) >= CURDATE() AND SCHCF.cf_xscheme_offtake = 0 AND SCH.xschemeid='".$transID."'" ;
               $res = $adb->pquery($sql);
    }
    
    
    function distributorClsterMapping($modName,$transID){
        
        global $adb,$DIST_CLUST_CODE,$current_user,$MS_AUTO_DCCREATION;
        
        $defdistcluscode = $adb->pquery("SELECT `value` FROM `sify_inv_mgt_config` WHERE `treatment` = 'masters' AND `key` = 'DIST_CLUST_CODE' LIMIT 1");
        $resDefDistClusCode = $adb->query_result($defdistcluscode,0,'value');
        $DIST_CLUST_CODE = $resDefDistClusCode;
        
        $autodistclurelquery = $adb->pquery("SELECT `value` FROM `sify_inv_mgt_config` WHERE `treatment` = 'masters' AND `key` = 'MS_AUTO_DCCREATION' LIMIT 1");
        $resAutoDistClusRel = $adb->query_result($autodistclurelquery,0,'value');
        $MS_AUTO_DCCREATION = $resAutoDistClusRel;
        
        $distClsGrpId = $DIST_CLUST_CODE;
          
        //New cluster creation for distributor Creation  
        if($MS_AUTO_DCCREATION == 1){
            $distributor=$adb->mquery('select * from vtiger_xdistributorclusterrel where distributorid='.$transID);
            if($adb->num_rows($distributor)==0){
                $currentModule='xDistributorCluster';
                checkFileAccess("modules/xDistributorCluster/xDistributorCluster.php");
                require_once("modules/xDistributorCluster/xDistributorCluster.php");  
                $focus = new $currentModule();
                setObjectValuesFromRequest($focus);
                $ns = getNextstageByPosAction('Distributor Cluster',"Submit");
                $focus->column_fields['cf_xdistributorcluster_next_stage_name'] = $ns['cf_workflowstage_next_stage'];
                $focus->column_fields['cf_xdistributorcluster_status'] = $ns['cf_workflowstage_next_content_status'];
                $distributor=$adb->pquery('select distributorname,distributorcode from vtiger_xdistributor where xdistributorid='.$transID);
                $distributorclustername=$adb->query_result($distributor, 0, 'distributorcode').'-'.$adb->query_result($distributor, 0, 'distributorname');
                $focus->column_fields['distributorclustername'] = $distributorclustername;
//                $focus->column_fields['distributorclustercode'] = $focus->seModSeqNumber('increment',$currentModule);
                $focus->column_fields['distributorclustercode'] = $adb->query_result($distributor, 0, 'distributorcode');
                $focus->column_fields['createddate'] = date('d-m-Y');
                $focus->column_fields['active'] = 'on';
                $focus->column_fields['cf_xdistributorcluster_no_of_distributors'] = 1; 


                $focus->save('xDistributorCluster');
                $update_status= $adb->pquery('update vtiger_xdistributorclustercf set cf_xdistributorcluster_status="Approved" , cf_xdistributorcluster_next_stage_name = "Publish" where xdistributorclusterid='.$focus->id);
                $relQuery = "INSERT INTO vtiger_xdistributorclusterrel (distclusterid,distributorid,addition_date) VALUES (".$focus->id.",".$transID.",'".date('Y-m-d H:i:s')."')";
                $adb->pquery($relQuery);
         
            }
         
        }
        if(!empty($distClsGrpId)){
            $sql = "SELECT count(distributorid) as cnt FROM vtiger_xdistributorclusterrel WHERE distributorid =? AND distclusterid =?";
            $res = $adb->pquery($sql,array($transID,$distClsGrpId));
            $distClscnt = $adb->query_result($res, 0, "cnt");

            if($distClscnt<=0){
                $sql = "INSERT INTO vtiger_xdistributorclusterrel (distclusterid,distributorid,addition_date) VALUES
                            ('".$distClsGrpId."','".$transID."','now()')";
                $res = $adb->pquery($sql);

                $sql = "SELECT count(distributorid) as cnt FROM vtiger_xdistributorclusterrel WHERE distclusterid =?";
                $res = $adb->pquery($sql,array($distClsGrpId));
                $distcnt = $adb->query_result($res, 0, "cnt");

                $sql = "UPDATE vtiger_xdistributorclustercf SET cf_xdistributorcluster_no_of_distributors=? WHERE xdistributorclusterid=?";
                $adb->pquery($sql, array($distcnt,$distClsGrpId));
                
                //For price calculation mapping cluster update start.
                $pricecalmapidresult = $adb->pquery("SELECT xpricecalculationmappingid FROM vtiger_xpricecalculationmapping WHERE distributor_cluster_code = '$distClsGrpId'");
                $pricecalmapidcnt =  $adb->num_rows($pricecalmapidresult);  
                if($pricecalmapidcnt > 0){
                    $focus5=CRMEntity::getInstance('xPcmDistributorMapping');
                    for($i=0; $i < $pricecalmapidcnt; $i++){
                        $pricecalmapid = $adb->query_result($pricecalmapidresult,$i,'xpricecalculationmappingid'); 
                        $focus5->column_fields['distributor_name'] = $transID;
                        $focus5->column_fields['revoke_date'] = '';
                        $focus5->column_fields['active'] = 1;
                        $focus5->save('xPcmDistributorMapping');
                        $return_id = $focus5->id;
                        if($pricecalmapid!="" && $return_id!=""){
                            $insert = "insert into vtiger_crmentityrel(crmid,module,relcrmid,relmodule) values('".$pricecalmapid."','xPriceCalculationMapping','".$return_id."','xPcmDistributorMapping')";
                            $adb->pquery($insert);
                        }
                    }   
                }
                //For price calculation mapping cluster update end.
            }
        }
        return true;
        
    }
    /*********************************************************************************************************
    * CREATED ON 25th Nov,2015
    * THIS FUNCTION CREATED PURPOSE FOR CUSTOMER(xRetailer) MODULE ANY UPDATES ON WORKFLOW BASED APPROVE STAGE
    * CURRENTLY WE UPDATE ONLY ON CUSTOMER(xRetailer) ACTIVE STATUS
    * FUNCTION PARAM $xretailerID PASS THE CUSTOMER(xRetailer) RECORD ID 	
    ***/	
    function retailerUpdate($xretailerID) {
            global $adb;
            if (!empty($xretailerID)) {
                    $updateQry = "UPDATE vtiger_xretailercf SET cf_xretailer_active=1 WHERE xretailerid=?";
                    if($adb->pquery($updateQry, array($xretailerID)))
                            return true;
                    else
                            return false;
            } else {
                    return false;
            }
    }
    /*********************************************************************************************************
    * CREATED ON 27th Sep,2016
    * THIS FUNCTION CREATED PURPOSE FOR CREATING UNIQUE CODE FOR CUSTOMER(xRetailer) WHEN THEY GOT APPROVED
    ***/
    function retailerUCUpdate($xretailerID,$modName) {
            global $adb,$MS_GUI_CUS,$MS_GUI_CUS_TEXT;
            
            if (!empty($xretailerID) & $MS_GUI_CUS==1) {
                $x="select unique_retailer_code from vtiger_xretailer WHERE xretailerid=?";
                $uni_code_avail= $adb->mquery($x, array($xretailerID));
                $uni_code_avail=$adb->query_result($uni_code_avail,0,'unique_retailer_code');
                if($uni_code_avail=='')
                {
                    if($MS_GUI_CUS_TEXT !='' && $MS_GUI_CUS_TEXT > 0 )
						$y="select max(unique_retailer_code) as unique_retailer_code  from vtiger_xretailer where unique_retailer_code > $MS_GUI_CUS_TEXT";
					else
						$y="select max(unique_retailer_code) as unique_retailer_code  from vtiger_xretailer";
			
                    $uni_code_curr= $adb->pquery($y);
                    $uni_code_curr=$adb->query_result($uni_code_curr,0,'unique_retailer_code');
                    if($uni_code_curr == '' || $uni_code_curr==NULL){
                        if($MS_GUI_CUS_TEXT !='' && $MS_GUI_CUS_TEXT > 0 )
                        {
                            $uni_code=$MS_GUI_CUS_TEXT;
                        }else{
                            $uni_code=$uni_code_curr;
                        }
                        $updateQry = "UPDATE vtiger_xretailer SET unique_retailer_code='".$uni_code."' WHERE xretailerid=?";
                    }else{
                        $uni_code=$uni_code_curr+1;
                        $updateQry = "UPDATE vtiger_xretailer SET unique_retailer_code='".$uni_code."' WHERE xretailerid=?";
                    }
                    if($adb->pquery($updateQry, array($xretailerID)))
                        return true;
                    else
                        return false;
                }
            } else {
                return false;
            }
    }

    function getTaxForRSI($id, $sellerid, $productid, $lineitem_id = '', $retailerid, $sourcefrom = '', $table_name = ''){
        $lineitem_cond = '';
        if($lineitem_id != ''){
            $lineitem_cond = " and transaction_line_id IN ($lineitem_id) ";
        }

        global $adb,$ALLOW_GST_TRANSACTION;
        $taxDetails = array();
        $distid = $sellerid;
        
        if($sourcefrom == 'xrSalesInvoice'){
            $distrest = $adb->pquery("SELECT xdistributorid FROM vtiger_xdistributor where distributorcode=?",array($sellerid));
            if($adb->num_rows($distrest)>0) {
                $distid = $adb->query_result($distrest,0,'xdistributorid');
            }
         }
        
        $stateRes = $adb->pquery("SELECT cf_xdistributor_state FROM vtiger_xdistributorcf where xdistributorid = ? ", array($distid));
        
        $distState = '';
        if($adb->num_rows($stateRes)>0) {
            $distState = $adb->query_result($stateRes,0,'cf_xdistributor_state'); 
        }
        
        $retaileState = $adb->pquery('SELECT cf_xretailer_state FROM vtiger_xretailercf where xretailerid = ? ',array($retailerid));
        $buyerStateName = '';
        if($adb->num_rows($retaileState)>0) {
            $buyerStateName = $adb->query_result($retaileState,0,'cf_xretailer_state');
        }
        
        $taxingType  = 'CST';
        if($distState == $buyerStateName) {
            $taxingType = 'LST';
        }
        
        // Modified for Tally Tax - To Identify Main and Component Tax
        if($sourcefrom == 'Tally~~Tax') {
            $table_name = !empty($table_name)? $table_name : 'sify_xtransaction_tax_rel'; 
            $taxQuery = "select group_concat(transaction_line_id) as lineitem_id, transaction_id, tax_type,
                         tax_percentage, sum(tax_amt) as tax_amt from $table_name
                         where transaction_id = $id $lineitem_cond and lineitem_id IN ($productid) group by tax_type desc";
            
            if($ALLOW_GST_TRANSACTION && $sourcefrom == 'xrSalesInvoice') {
                $taxQuery = "select group_concat(transaction_line_id) as lineitem_id, transaction_id, tax_type,
                             tax_percentage,sum(tax_amt) as tax_amt from sify_xtransaction_tax_rel_rsi
                             where transaction_id = $id  $lineitem_cond and lineitem_id IN ($productid) group by tax_type desc";
            }
        } else {
            $taxQuery = "select * from sify_xtransaction_tax_rel where transaction_id = $id $lineitem_cond and lineitem_id IN ($productid) ";
            
            if($ALLOW_GST_TRANSACTION && $sourcefrom == 'xrSalesInvoice') {
                $taxQuery = "select * from sify_xtransaction_tax_rel_rsi where transaction_id = $id $lineitem_cond and lineitem_id IN ($productid) ";
            }
        } 
         
        $taxResult = $adb->mquery($taxQuery);
        $taxRows = $adb->num_rows($taxResult);
        
        if($taxRows > 0) {
            $taxDetails = getProductTaxDetails($taxRows, $taxResult);
        }
                
        return $taxDetails;
    }

    function getTaxForPurchase($id, $sellerid, $productid, $lineitem_id = '', $buyerid, $sourcefrom = '', $table_name = ''){

        global $adb,$ALLOW_GST_TRANSACTION;
        $taxDetails = array();
        
        $lineitem_cond = '';
        if($lineitem_id != ''){
            $lineitem_cond = " and transaction_line_id IN ($lineitem_id) ";
        }
        
        $stateRes = $adb->pquery("SELECT cf_xdistributor_state FROM vtiger_xdistributorcf where xdistributorid = ? ", array($buyerid));
        $distState = '';
        if($adb->num_rows($stateRes)>0) {
            $distState = $adb->query_result($stateRes,0,'cf_xdistributor_state'); 
        }
        
        $stateRes = $adb->pquery('SELECT state FROM vtiger_vendor where vendorid = ? ',array($sellerid));
        $vendorState = '';
        if($adb->num_rows($stateRes)>0) {
            $vendorState = $adb->query_result($stateRes,0,'cf_xretailer_state');
        }
        
        $taxingType  = 'CST';
        if($distState == $vendorState) {
            $taxingType = 'LST';
        }
        
        $table_name = !empty($table_name)? $table_name : 'sify_xtransaction_tax_rel_pi'; 
        if($sourcefrom == 'Tally~~Tax') {
            $taxQuery = "select group_concat(transaction_line_id) as lineitem_id, transaction_id, tax_type,
                         tax_percentage, sum(tax_amt) as tax_amt from $table_name
                         where transaction_id = $id $lineitem_cond and lineitem_id IN ($productid) group by tax_type desc";
        } else {
            $taxQuery = "select * from $table_name where transaction_id = $id $lineitem_cond and lineitem_id IN ($productid) ";
        } 
         
        $taxResult = $adb->pquery($taxQuery);
        $taxRows = $adb->num_rows($taxResult);
        
        if($taxRows > 0) {
            $taxDetails = getProductTaxDetails($taxRows, $taxResult);
        }
                
        return $taxDetails;
    }
    
    function getProductTaxDetails($taxRows, $taxResult) {
        global $adb;
        for($j=0;$j<$taxRows;$j++)
        {
            $tax_type = $adb->query_result($taxResult,$j,'tax_type');
            $lineitem_id = $adb->query_result($taxResult,$j,'lineitem_id');
            $percentage = $adb->query_result($taxResult,$j,'tax_percentage');
            $tax_amt = $adb->query_result($taxResult,$j,'tax_amt');
            $taxable_amt = $adb->query_result($taxResult,$j,'taxable_amt');
            $tax_group_type = $adb->query_result($taxResult,$j,'tax_group_type');

            $taxQry = "select vtiger_xtax.xtaxid,taxcode,taxdescription,cf_xtax_lst_percentage,cf_xtax_cst_percentage,lst_tax_group,cst_tax_group from vtiger_xtax
                       inner join vtiger_xtaxcf on (vtiger_xtax.xtaxid = vtiger_xtaxcf.xtaxid)
                       inner join vtiger_crmentity on(vtiger_crmentity.crmid = vtiger_xtax.xtaxid)
                       where taxcode = '$tax_type' and vtiger_crmentity.deleted = 0";

            $taxRes = $adb->pquery($taxQry);
            $taxRow = $adb->num_rows($taxRes);
            if($taxRow > 0)
            {
                $code = $adb->query_result($taxRes,0,'taxcode');
                $id = $adb->query_result($taxRes,0,'xtaxid');
                $name = $adb->query_result($taxRes,0,'taxdescription');
                $lst = (float) numberformat($adb->query_result($taxRes,0,'cf_xtax_lst_percentage'),2);
                $cst = (float) numberformat($adb->query_result($taxRes,0,'cf_xtax_cst_percentage'),2);

                $inter_group = $adb->query_result($taxRes,0,'cst_tax_group');
                $intra_group = $adb->query_result($taxRes,0,'lst_tax_group');

                $deleted = $adb->query_result($taxRes,0,'deleted');

                $type = 'CST';
                $taxPercentage = $cst;
                if(!empty($lst)){
                    $type = 'LST';
                    $taxPercentage = $lst;
                }

                if(empty($tax_group_type)) {
                    $tax_group_type = ($type == 'CST') ? $inter_group : $intra_group;
                }

                //if($type == $taxingType){
                $taxDetails[] = array(
                    'productid' => $lineitem_id,
                    'taxid' => $id,
                    'taxname' => $code,
                    'taxlabel' => $name,
                    'percentage' => $percentage,
                    'percentage_display' => $percentage,
                    'taxPercentage' => $taxPercentage,
                    'deleted' => $deleted,
                    'taxType' => $type,
                    'taxAmt' => $tax_amt,
                    'taxgrouptype' => $tax_group_type,
                    'taxable_amt' => $taxable_amt,
                    'type'=> 1
                );
                //}
            } else {

                $componentQry = "select vtiger_xcomponent.xcomponentid,componentcode,componentdescription,cf_xcomponent_component_percentage,cf_xcomponent_applicable_on,cf_xcomponent_component_for, vtiger_xcomponentcf.comp_tax_group from vtiger_xcomponent
                                 inner join vtiger_xcomponentcf on (vtiger_xcomponent.xcomponentid = vtiger_xcomponentcf.xcomponentid)
                                 inner join vtiger_crmentity on(vtiger_crmentity.crmid = vtiger_xcomponent.xcomponentid)
                                 where componentcode = '$tax_type' and vtiger_crmentity.deleted = 0";

                $compRes = $adb->pquery($componentQry);
                $compRow = $adb->num_rows($compRes);

                if($compRow > 0){
                    $id = $adb->query_result($compRes,0,'xcomponentid');
                    $code = $adb->query_result($compRes,0,'componentcode');
                    $name = $adb->query_result($compRes,0,'componentdescription');
                    $percentage_d = $adb->query_result($compRes,0,'cf_xcomponent_component_percentage');
                    $appon = $adb->query_result($compRes,0,'cf_xcomponent_applicable_on');
                    $type = $adb->query_result($compRes,0,'cf_xcomponent_component_for');
                    $deleted = $adb->query_result($compRes,0,'deleted');
                    $component_tax_group = $adb->query_result($compRes,0,'comp_tax_group');

                    if(empty($tax_group_type)) {
                        $tax_group_type = $component_tax_group;
                    }

                    /*
                    if($appon == 'Tax on Tax')
                    {
                       $percentage_d = ($taxDetails[0]['percentage']*$percentage/100);
                    }else{
                        $percentage_d = $percentage;
                    }
                    */
                    
                    //if($type == $taxingType){
                    $taxDetails[] = array(
                        'productid' => $lineitem_id,
                        'taxid' => $id,
                        'taxname' => $code,
                        'taxlabel' => $name,
                        'percentage' => $percentage_d,
                        'percentage_display' => $percentage,
                        'taxPercentage' => $percentage_d,
                        'deleted' => $deleted,
                        'taxType' => $type,
                        'taxAmt' => $tax_amt,
                        'taxgrouptype' => $tax_group_type,
                        'taxable_amt' => $taxable_amt,
                        'type'=> 2
                    );
                    //}
                }
            }
        } //End of For
                
        return $taxDetails;
    }
    
function getuomconversion($productid, $uomid)
{
    $uomlist = getProductUOMList($productid);
    
//    echo '<pre>';print_r($uomlist);die;
    
    $_i =1;
        foreach($uomlist as $k => $v)
    {	
            if($_i%2==0)
                            $uomvalue[] = $v;
                            else
                            $uomkey[] = $v;
    $_i++;			

    }



    for($_j=0;$_j< count($uomvalue);$_j++){
        $uomlistnew[$uomkey[$_j]]= $uomvalue[$_j];
    }
    return $uomlistnew[$uomid];
}
function getTaxtypeforsi($si_id,$prdtid,$lineitmid){
		global $adb;
		//echo $si_id.'- - '.$prdtid.'- - '.$lineitmid;exit;
                $taxResult=$adb->pquery("SELECT tax_type,tax_label,tax_percentage,tax_amt FROM sify_xtransaction_tax_rel_si join vtiger_xtax on vtiger_xtax.taxcode = sify_xtransaction_tax_rel_si.tax_type where sify_xtransaction_tax_rel_si.transaction_id=? and lineitem_id=? and transaction_line_id =? ",array($si_id,$prdtid,$lineitmid));
                $main_tax_values=array();
                 for ($mt = 0; $mt < $adb->num_rows($taxResult); $mt++) {
                $main_tax_values['taxtype']=$adb->query_result($taxResult,$mt,'tax_type');
                $main_tax_values['taxlabel']=$adb->query_result($taxResult,$mt,'tax_label');
                $main_tax_values['taxvalue']=$adb->query_result($taxResult,$mt,'tax_percentage');
                $main_tax_values['taxamt']=$adb->query_result($taxResult,$mt,'tax_amt');
                
            }
            return $main_tax_values;
                
	}
        
        
function getDistChannel($distid){
    global $adb;
    $distQuery = "SELECT xchannelhierarchyid,channelhierarchycode FROM
    vtiger_xdistributor INNER JOIN vtiger_crmentityrel ON (	vtiger_xdistributor.xdistributorid = vtiger_crmentityrel.crmid)
    INNER JOIN  vtiger_crmentity ON (vtiger_crmentity.crmid = vtiger_xdistributor.xdistributorid)
    INNER JOIN  vtiger_xchannelhierarchy ON (vtiger_xchannelhierarchy.xchannelhierarchyid = vtiger_crmentityrel.relcrmid)
    WHERE relmodule = 'xChannelHierarchy' AND vtiger_xdistributor.xdistributorid = $distid";
    $distResult=$adb->pquery($distQuery);
    $distrows = $adb->num_rows($distResult);
    $distchannel = array();
    for($dt = 0; $dt < $distrows; $dt++) {
        $distchannel[$dt]['channelhierarchyid'] = $adb->query_result($distResult,$dt,'xchannelhierarchyid');
        $distchannel[$dt]['channelhierarchycode'] = $adb->query_result($distResult,$dt,'channelhierarchycode');
    }
    
    return $distchannel;
}

function getSalesmanBeat($cusid){
    
    global $adb;  
    $pr_results = array();
    
    if($cusid != ''){
        $salesmanbeatQuery = $adb->pquery("select beat.xbeatid, beat.beatname, sal.xsalesmanid, sal.salesman from vtiger_xbeat as beat
                INNER JOIN vtiger_crmentityrel crmrel ON crmrel.relcrmid = beat.xbeatid 
                INNER JOIN vtiger_crmentityrel crmrel1 ON crmrel1.relcrmid = crmrel.relcrmid and crmrel1.module = 'xSalesman'
                INNER JOIN vtiger_xsalesman sal ON sal.xsalesmanid = crmrel1.crmid
                INNER JOIN vtiger_xbeatcf beatcf ON beatcf.xbeatid = beat.xbeatid
                INNER JOIN vtiger_xsalesmancf salcf ON salcf.xsalesmanid = sal.xsalesmanid
        where crmrel.crmid = ".$cusid." and beatcf.cf_xbeat_active = '1' and salcf.cf_xsalesman_active = '1'
        order by sal.salesman,beat.beatname limit 1");
        
        if($adb->num_rows($salesmanbeatQuery) > 0)
        {
            $pr_results['salesman_id'] = $adb->query_result($salesmanbeatQuery,0,'xsalesmanid'); 
            $pr_results['beat_id'] = $adb->query_result($salesmanbeatQuery,0,'xbeatid'); 
        }
    }
    
    return $pr_results;
}

function getReceivedCusId($cusid = ''){
    global $adb;  
    $cus_results = array();
    if($cusid != ''){
        $receivedCusQuery = $adb->pquery("SELECT reference_id FROM vtiger_xreceivecustomermaster WHERE xreceivecustomermasterid = '".$cusid."'");
        if($adb->num_rows($receivedCusQuery) > 0){
            $cus_results['cus_id'] = $adb->query_result($receivedCusQuery,0,'reference_id'); 
        }
    }
    return $cus_results;
}
function getCusIdReceivedOrCheck($cusid = ''){
    global $adb;  
    $is_convert_cr = 1;
    if($cusid != ''){		
        $receivedCusQuery = $adb->pquery("SELECT xretailerid FROM vtiger_xretailer WHERE xretailerid = '".$cusid."'");
		$adb->num_rows($receivedCusQuery);
        if($adb->num_rows($receivedCusQuery) == 0){
            $is_convert_ay = getReceivedCusId($cusid);
			if(!empty($is_convert_ay) && empty($is_convert_ay['cus_id'])){
				$is_convert_cr = 0;
			}
        }
    }
    return $is_convert_cr;
}
     

function updatePriceCorrection($modName,$xpcId){
	 global $adb;

	if($xpcId){
		$selQuery	=  "SELECT vtiger_xpricecorrectionrel.xpricecorrectionrelid,vtiger_xpricecorrection.xproductid,vtiger_xpricecorrection.xdistributorclusterid
		FROM vtiger_xpricecorrection
		INNER JOIN vtiger_xpricecorrectionrel ON vtiger_xpricecorrectionrel.xpricecorrectionid=vtiger_xpricecorrection.xpricecorrectionid
		WHERE vtiger_xpricecorrection.xpricecorrectionid=$xpcId";
		
		$resultPrice = $adb->mquery($selQuery);
		$ret2 = $adb->raw_query_result_rowdata($resultPrice,0);
			$productid 					 =  $ret2['xproductid'];
			$xpricecorrectionrelid 		 =  $ret2['xpricecorrectionrelid'];
			$xdistributorclusterid 		 =  $ret2['xdistributorclusterid'];
			$user=$_SESSION["authenticated_user_id"];
			$distclusters= getAllDistributorClusterRel($xdistributorclusterid);
			foreach($distclusters as $distcode){
				$dist[] = $distcode['id'];
			}
			$dist	= implode(',',$dist);
                        if(empty($user)){
                            $user       = 0;
                        }
		$INNERJOIN="INNER  JOIN vtiger_xpricecorrectionrel PC
						ON  SL.batchnumber  = PC.batchnumber  AND
						SL.pkg 			= PC.pkg AND
						SL.expiry 		= PC.expiry AND 
						SL.pts		    = PC.pts AND 
						SL.ptr 			= PC.ptr AND
						SL.mrp		    = PC.mrp AND 
						SL.ecp 			= PC.ecp AND 
						PC.xpricecorrectionid=$xpcId  ";
						
	    $wherecondition="where SL.productid=$productid  and
						SL.distributorcode IN($dist) and 
						SL.batchnumber!=''  and 
						SL.location_id IN (SELECT xgodownid FROM vtiger_xgodown where xgodown_distributor IN($dist))  ";
						
		$InsertStockLogQuery="INSERT INTO `sify_stocklot_update_log`(`xpricecorrectionid`, `xpricecorrectionrelid`, `stocklot_id`, `user_id`, `old_ptr`, `old_pts`, `old_mrp`, `old_ecp`, `new_ptr`, `new_pts`, `new_mrp`, `new_ecp`) 
								SELECT 	PC.xpricecorrectionid,
										PC.xpricecorrectionrelid,
										SL.id,
										$user,
										PC.ptr,
										PC.pts,
										PC.mrp,
										PC.ecp,
										CASE WHEN PC.new_ptr!='' OR PC.new_ptr!=0 THEN PC.new_ptr ELSE SL.ptr END,
										CASE WHEN PC.new_pts!='' OR PC.new_pts!=0 THEN PC.new_pts ELSE SL.pts END, 
										CASE WHEN PC.new_mrp!='' OR PC.new_mrp!=0 THEN PC.new_mrp ELSE SL.mrp END, 
										CASE WHEN PC.new_ecp!='' OR PC.new_ecp!=0 THEN PC.new_ecp ELSE SL.ecp END 
										FROM vtiger_stocklots SL 
										$INNERJOIN 
										$wherecondition";
		$insertResult = $adb->pquery($InsertStockLogQuery);
			
		$updateQry="UPDATE  vtiger_stocklots SL 
						$INNERJOIN 
						SET SL.ptr = CASE WHEN PC.new_ptr!='' OR PC.new_ptr!=0 THEN PC.new_ptr ELSE SL.ptr END,
							SL.pts = CASE WHEN PC.new_pts!='' OR PC.new_pts!=0 THEN PC.new_pts ELSE SL.pts END, 
							SL.mrp = CASE WHEN PC.new_mrp!='' OR PC.new_mrp!=0 THEN PC.new_mrp ELSE SL.mrp END, 
							SL.ecp = CASE WHEN PC.new_ecp!='' OR PC.new_ecp!=0 THEN PC.new_ecp ELSE SL.ecp END 
						$wherecondition";
              
		$updateResult = $adb->pquery($updateQry);
		}	
	}
        
        function getPtrpricefromNetprice($modified_netprice,$getQty,$taxpercent,$discountObj,$invDiscountObj,$invDiscountFlag,$schemeInfo,$amountBeforeTax,$netPrices,$rowNo){
            global $adb,$CL_LBL_NUMBER_OF_DECIMAL; 
        $decimalval = $CL_LBL_NUMBER_OF_DECIMAL;
        
        if($taxpercent > 0){

            /*
            *  Invoice Level Discount Logic
            */
                
            if($invDiscountFlag==0)
            {
                
                $invDiscType=$invDiscountObj['type'];
                
                if($invDiscType=='percentage' || $invDiscType=='amount')
                {
                    $otherDiscAmounts=0.0;
                    //$otherNetPrices=0.0;
                    $invDiscount=0.0;
                    $totalNetPrice=0.0;
                    
                                        
                    $invDiscount=$invDiscountObj['value'];
                    foreach ($amountBeforeTax as $discRowNo => $discVal){
                        if($discRowNo==$rowNo)
                            continue;

                        $otherDiscAmounts+=$discVal;
                    }

                    $z=$modified_netprice;
                    $y=$invDiscount;
                    $q=$otherDiscAmounts;
                    $p=$taxpercent;
                    
                    $temp1=0.0;

                    //print_r(array($z,$y,$q,$p));
                    
                    if($invDiscType=='percentage')
                    {
                        $temp1=0-((10000*$z)/($p*$y-100*$p-10000));
                    }
                    else 
                    {
                        $temp1=(sqrt(10000*$z*$z+(200*$p*$y+(200*$p+20000)*$q)*$z+$p*$p+$y*$y+(-2*($p*$p)-200*$p)*$q*$y+($p*$p+200*$p+10000)*($q*$q))+100*$z+$p*$y+(-$p-100)*$q)/(2*$p+200);    
                    }

                    //echo $temp1;

                    $getGrossAmountBeforeTax=$temp1;
                }    
                else
                {
                    // Same Price
                    $getGrossAmountBeforeTax = $modified_netprice /(1+($taxpercent/100));
                }    
                
            }
            else
            { 
                $getGrossAmountBeforeTax = $modified_netprice /(1+($taxpercent/100));
            }    
        }
        else{
            $getGrossAmountBeforeTax = $modified_netprice ;
        }
        
        
        /*
         *  Discount Logic
         */
        
        $getAmountAfterSchemeDiscount=$getGrossAmountBeforeTax;
        
        if($discountObj['value'] !=''){
            
            $discount_type=$discountObj['type'];
            $discount_val=$discountObj['value'];
            
            if($discount_type == "percentage"){
                $getAmountAfterSchemeDiscount = $getGrossAmountBeforeTax/ (1-($discount_val/100));
            }else if($getGrossAmountBeforeTax>0){
                $getAmountAfterSchemeDiscount = $getGrossAmountBeforeTax + $discount_val;
            }
            
        }else{
            $getAmountAfterSchemeDiscount = $getGrossAmountBeforeTax;
        }
        
        //$getAmountAfterSchemeDiscount=  numberformat($getAmountAfterSchemeDiscount, 6);
        
        //echo '<br> Amount After Scheme Discount  = '.$getAmountAfterSchemeDiscount;
                
        /*
         *   Scheme Discount Logic   
         */
        
        
        $grossAmount=$getAmountAfterSchemeDiscount;
        
        $percTotal=0.0;
        $amountTotal=0.0;
        
        foreach ($schemeInfo as $schemeVal){
           
           //print_r($schemeVal);
            
           $schemeType = $schemeVal['type']; 
           $schemeValue = $schemeVal['value']; 
           
           if($schemeType=='percentage')
           {
               $percTotal+=$schemeValue; 
           }
           else if($schemeType=='amount')
           {
               $amountTotal+=$schemeValue;
           }
           else {}                     
        }
        
        $grossAmountRemovingAmounts=$grossAmount+$amountTotal;
        $grossAmount=$grossAmountRemovingAmounts/(1-($percTotal/100));
        
        
        //echo "Final Gross Amount :".$grossAmount;
        
//        //echo 'AmountAfterSchemeDiscount  = '.$getAmountAfterSchemeDiscount;
//        //echo '<br>';
        
        $getPtr = $grossAmount / $getQty;
        
        $getPtr=  numberformat($getPtr, 6);
        
        //echo 'getPtr  = '.$getPtr;
        $toSend=array('PTR'=>$getPtr);
        return $toSend;
       }
    
    //Configuration based on Multiselect Mandatory/Non-Mandatory fields in Customer Master Imports
    function getCusmandatoryfields($mod_strings=''){
        $Customer = array('Credit Norm Amounts*' => 'Credit Norm Amounts','Number of Credit Norm Bills*' => 'Number of Credit Norm Bills','Credit Norm Days*' => 'Credit Norm Days','City*' => 'City','Mobile No*' => 'Mobile No','Address1*' => 'Address1','Email*'=>'Email','District*'=>'District',getLaguageDisplayName($mod_strings,'Location/Area').'*'=>getLaguageDisplayName($mod_strings,'Location/Area'),'Payment Mode*' => 'Payment Mode',
                        'Retailshop Name*'=>'Retailshop Name','Customer First Name*'=>'Customer First Name','Customer Middle Name*'=>'Customer Middle Name',
                        'Customer Last Name*'=>'Customer Last Name','Gender*'=>'Gender','Area Name*'=>'Area Name','Street Name*'=>'Street Name',
                        'Market Name*'=>'Market Name','Type Shop/Firm*'=>'Type Shop/Firm','ID CARD Number*'=>'ID CARD Number',
                        'Plot Number*'=>'Plot Number','LUBE Territory Code*'=>'LUBE Territory Code','LUBE Territory Name*'=>'LUBE Territory Name','Territory Code*'=>'Territory Code','Territory Name*'=>'Territory Name',
                        'Tehsil/Taluka Name*'=>'Tehsil/Taluka Name','ID Card Type*'=>'ID Card Type','Product Category Group*'=>'Product Category Group','Active*' => 'Active','Branded Shop Board Presence*' => 'Branded Shop Board Presence','Premium Edible Oil Presence*' => 'Premium Edible Oil Presence','Branded Rice Presence*' => 'Branded Rice Presence','Branded Soya Chunks Presence*' => 'Branded Soya Chunks Presence','Branded Atta Presence*' => 'Branded Atta Presence','Branded Sugar*' => 'Branded Sugar','No of Helpers*'=>'No of Helpers', 'Store Size*'=>'Store Size');
        return $Customer;
    }

 // Custom CSV header format configuration -- Justy 17-04-2018

   function getCustomcsvHeader(){
	global $adb;
   $customformat =$adb->pquery("SELECT `value` from sify_inv_mgt_config WHERE `key`='LBL_CUSTOM_IMPORT_CSV_HEADER'", array());
    if($adb->query_result($customformat,'0','value')!='0')
        $Content = $adb->query_result($customformat,'0','value'); 
    	return $Content;
   }


 function getAllDistributorCluster() {
           global $adb,$current_user;
           $Qry = "SELECT mt.xdistributorclusterid,mt.distributorclustername,mt.distributorclustercode FROM vtiger_xdistributorcluster  mt 
		   LEFT JOIN vtiger_crmentity ct ON mt.xdistributorclusterid=ct.crmid 
		   INNER JOIN vtiger_xdistributorclustercf ON vtiger_xdistributorclustercf.xdistributorclusterid = mt.xdistributorclusterid
			INNER JOIN vtiger_xdistributorclusterrel ON vtiger_xdistributorclusterrel.distclusterid = mt.xdistributorclusterid 
			WHERE ct.deleted=0 AND mt.active=1";
           $Qry.=" AND vtiger_xdistributorclustercf.cf_xdistributorcluster_status = 'Approved'";
           $Qry.=" GROUP BY mt.xdistributorclusterid";
                        
           $result = $adb->pquery($Qry);
           $ret = array();
             for ($index = 0; $index < $adb->num_rows($result); $index++) {
	        $ret[] = $adb->raw_query_result_rowdata($result,$index);
	     }
             return $ret;
 }
 function getCreditTermDisp($idVal) {
	  global $adb;
	 
	  $Qrydisp = "SELECT vtiger_xcreditterm.credittermdescription, vtiger_xcreditterm.credittermcode, vtiger_xcredittermcf.cf_xcreditterm_number_of_days, vtiger_xcredittermcf.cf_xcreditterm_status, vtiger_xcreditterm.xcredittermid FROM vtiger_xcreditterm INNER JOIN vtiger_crmentity ON vtiger_xcreditterm.xcredittermid = vtiger_crmentity.crmid INNER JOIN vtiger_xcredittermcf ON vtiger_xcreditterm.xcredittermid = vtiger_xcredittermcf.xcredittermid WHERE vtiger_crmentity.deleted=0 and vtiger_xcreditterm.cf_xcreditterm_distid =$idVal ORDER BY createdtime desc";
	 $resultdisp = $adb->pquery($Qrydisp);
	 $result_row_disp = $adb->fetch_row($resultdisp);
	 if(empty($result_row_disp)){
		 $retutnValue = 0;
	 }
	 else{
		$retutnValue = 1; 
	 }
	return $retutnValue;
}  
/*
SELECT vtiger_xcreditterm.cf_xcreditterm_distid,vtiger_xcreditterm.credittermcode FROM vtiger_xcreditterm INNER JOIN vtiger_crmentity ON vtiger_xcreditterm.xcredittermid = vtiger_crmentity.crmid INNER JOIN vtiger_xcredittermcf ON vtiger_xcreditterm.xcredittermid = vtiger_xcredittermcf.xcredittermid WHERE vtiger_crmentity.deleted=0 AND  vtiger_xcreditterm.xcredittermid=121937
*/
function getCreditTermVal($ValueId) {
	  global $adb;
	   $QryCheck = "SELECT vtiger_xcreditterm.cf_xcreditterm_distid,vtiger_xcreditterm.credittermcode FROM vtiger_xcreditterm INNER JOIN vtiger_crmentity ON vtiger_xcreditterm.xcredittermid = vtiger_crmentity.crmid INNER JOIN vtiger_xcredittermcf ON vtiger_xcreditterm.xcredittermid = vtiger_xcredittermcf.xcredittermid WHERE vtiger_crmentity.deleted=0 AND  vtiger_xcreditterm.xcredittermid=$ValueId";
	  $resultVal = $adb->pquery($QryCheck);
	 $result_row_Credit = $adb->fetch_row($resultVal);
	 $CreditDistVAlue = $result_row_Credit['cf_xcreditterm_distid'];
	if(empty($CreditDistVAlue)){
		 $retutn_credit_Value = 'N';
	 }
	 else{
		$retutn_credit_Value = 'Y'; 
	 }
	return $retutn_credit_Value;
} 

function sendSMSToCustomer($modName,$status,$nextStage,$transID,$amendId){
    global $adb,$default_charset,$SMS_SERVICE_URL,$root_directory,$SMS_TOKENSERVICE_URL,$SMS_CLIENT_ID,$SMS_CLIENT_SECRET,$SMS_GRANT_TYPE;
    $res = array();

    $Resulrpatth1_dir = $root_directory.'storage/log/rlog';
    if(!is_dir($Resulrpatth1_dir)){
            mkdir($Resulrpatth1_dir, 0700);
    }
    $Resulrpatth1 = $root_directory.'storage/log/rlog/log_SMSCHECK_'.$transID.'_'.date("Ymd_H_i_s").'.txt';

    require_once 'include/basicmethod.php'; 
    $basicboject = new basicmethod();

    $smsProKeyTokenUrlPostData = array(
        'client_id' => $SMS_CLIENT_ID,//'1817d806-f164-4aa6-b3ea-32d185349da7',
        'client_secret' => $SMS_CLIENT_SECRET,//'xj4VQ2Gf1ix66lk/kNvFnp74eCuv20C1yhKXJqaA0xmWxiTE9X/TUqMMwGkhZIOKp+qVxnBJXtyo4g3OYfVn5g==',
        'grant_type' => $SMS_GRANT_TYPE,//client_credentials
    );

    $resultData = $basicboject->getPostDataCurl($SMS_TOKENSERVICE_URL,'formurlencodedsmstoken',$smsProKeyTokenUrlPostData,1);
    $retValue = json_decode($resultData,true);

    $logco = "------------SMS Check Token URL---------".PHP_EOL;
    $logco .= "SMS Token URL ".$SMS_TOKENSERVICE_URL.PHP_EOL;
    $logco .= "SMS Client Id ".$SMS_CLIENT_ID.PHP_EOL;
    $logco .= "SMS Client Secret ".$SMS_CLIENT_SECRET.PHP_EOL;
    $logco .= "SMS Grant Type ".$SMS_GRANT_TYPE.PHP_EOL;
    $logco .= "Array Form: ".  print_r($smsProKeyTokenUrlPostData, TRUE).PHP_EOL;
    $logco .= "Query Array : ".  print_r($resultData, TRUE).PHP_EOL;
    $logco .= "Res Array : ".  print_r($retValue, TRUE).PHP_EOL;
    file_put_contents($Resulrpatth1, $logco, FILE_APPEND);

    if(!empty($retValue)){
        $invoicedet = $adb->mquery("SELECT si.total,DATE_FORMAT(cf_salesinvoice_sales_invoice_date,'%d-%m-%Y') as date,cf_salesinvoice_transaction_number as trans_num,cf_salesinvoice_buyer_id,unique_retailer_code as customercode,
        cf_xretailer_mobile_no as mobile_no
        FROM vtiger_salesinvoicecf as sicf
        INNER JOIN vtiger_salesinvoice as si ON si.salesinvoiceid = sicf.salesinvoiceid
        INNER JOIN vtiger_xretailer as ret ON sicf.cf_salesinvoice_buyer_id = ret.xretailerid
        INNER JOIN vtiger_crmentity as crm ON crm.crmid = ret.xretailerid
        INNER JOIN vtiger_xretailercf as retcf ON ret.xretailerid = retcf.xretailerid
        WHERE crm.deleted = 0 AND retcf.cf_xretailer_active = 1 AND retcf.cf_xretailer_status ='Approved' AND sicf.salesinvoiceid =".$transID."");

        $logco = "------------SMS Check---------".PHP_EOL;
        $logco .= "SMS URL ".$SMS_SERVICE_URL.PHP_EOL;
        $logco .= "Query Array : ".  print_r($invoicedet, TRUE).PHP_EOL;
        file_put_contents($Resulrpatth1, $logco, FILE_APPEND);

        if($adb->num_rows($invoicedet)>0){
            $mobilenum = $adb->query_result($invoicedet, 0, 'mobile_no');

            if(!empty($mobilenum)){
                $msg   = 'Created'; 
                $total = number_format($adb->query_result($invoicedet, 0, 'total'),2);
                if($amendId > 0){$msg = 'Modified';} else if ($status == 'Cancel'){$msg = 'Cancelled'; $total = 0.00;}
                $message = "Dear Reseller(".$adb->query_result($invoicedet, 0, 'customercode').") Invoice no ".$adb->query_result($invoicedet, 0, 'trans_num')." $msg for your order on  ".$adb->query_result($invoicedet, 0, 'date')." Net value Rs ".$total." MAK Dist";

                $logco = "------------SMS Params---------".PHP_EOL;
                $logco .= "Mob No : ".$mobilenum.PHP_EOL;
                $logco .= "Msg : ".$message.PHP_EOL;
                file_put_contents($Resulrpatth1, $logco, FILE_APPEND);

                //'applicationId' =>'772',
                $retAccessTokenVal = $retValue['access_token'];
                $params = array('smsAccessToken'=>$retAccessTokenVal, 'Mobile'=>$mobilenum, 'Message'=>$message);
                
                $logco = "------------SMS Params Passed---------".PHP_EOL;
                 $logco .= "Array Params: ".  print_r($params, TRUE).PHP_EOL;
                file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                
                $resultData = $basicboject->getPostDataCurl($SMS_SERVICE_URL,'formurlencodedsms',$params,1);

                $logco = "------------SMS Params---------".PHP_EOL;
                $logco .= "Final Result: ".  print_r($res, TRUE).PHP_EOL;
                $logco .= "Res : ".$res.PHP_EOL;
                file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                
            }
        }
    }
    return $resultData;
}

function checkproductuom($pid,$uomid){
	global $adb;
	$dynamicuom = getUomField();
        $dynamicuom = $dynamicuom. ",vtiger_xproductcf.cf_xproduct_base_uom";
	
	$sql = "SELECT ".$dynamicuom." FROM vtiger_xproduct LEFT JOIN vtiger_xproductcf ON vtiger_xproduct.xproductid = vtiger_xproductcf.xproductid where vtiger_xproduct.xproductid = $pid";
	$result = $adb->pquery($sql);
	$ret = array();
	$text_chk = array("vtiger_xproduct.", "vtiger_xproductcf.");
	$txt_replace   = array("", "");
	$newphrase = str_replace($text_chk, $txt_replace, $dynamicuom);
	$uomlist = explode(",",$newphrase);
	
	for ($index = 0; $index < $adb->num_rows($result); $index++) {
		for($j=0;$j<count($uomlist); $j++){
			$ret[$uomlist[$j]] = $adb->query_result($result,$index,$uomlist[$j]);
		}
	           
	}
        $uom_ret = array_search($uomid,$ret);
        
	return $uom_ret;
}
function reportsingconfig($re_id = ''){    
   global $adb;
   $ret = array();
   if(!empty($re_id)){
       $se_query = "select * from vtiger_report where reportid =".$re_id;
       $result = $adb->pquery($se_query);
       $ret = $adb->query_result_rowdata($result);
   }
   return $ret;
}

function insertXTransactionTaxInfo($id,$prod_id,$transType,$tax_name,$tax_label,$taxpercentageCnt,$tax_amt,$taxable_amt,$lineitem_id_val,$tablename,$tax_id='',$taxgrouptype=''){
	global $adb, $log;
	
	$createQuery="INSERT INTO $tablename (`transaction_id`,`lineitem_id`,`transaction_name`,`tax_type`,`tax_label`,`tax_percentage`,`tax_amt`,`taxable_amt`,`transaction_line_id`,`xtaxid`,`tax_group_type`,`created_at`,`modified_at`) VALUES('".$id."','".$prod_id."','".$transType."','".$tax_name."','".$tax_label."','".$taxpercentageCnt."','".$tax_amt."','".$taxable_amt."','".$lineitem_id_val."','".$tax_id."','".$taxgrouptype."',now(),'')";  
	$adb->pquery($createQuery,array());
}

function sendOTPtoSalesman($salesmanCode, $status, $otphash_key='',$mobileNoinput = '',$customer_code = ''){
	require_once 'include/basicmethod.php'; 
	global $adb, $log,$logfilepath;
	$smsstatus = 200;
	
    $logvl = "User: ".$_SERVER['REMOTE_ADDR'].' - '.date("Ymd_H_i_s_").microtime(true).PHP_EOL;
    file_put_contents($logfilepath, $logvl, FILE_APPEND);
    $basicboject = new basicmethod();
	if($status ==2){                           /* ------ OTP VERIFIED PROCESS ------ */	
     $verifyQry = $adb->pquery("SELECT id,salesman_id,salesman_code,status FROM st_otp_details WHERE otp_hashvalue='$otphash_key'");
     if($verifyQry->fields['id'] >0){
        $status = 1;
		 $smsstatus = 200;
         $adb->query("UPDATE vtiger_xdistdeviceregistration SET status=$status WHERE salesmanid='$salesmanCode'");
         $adb->query("UPDATE st_otp_details SET status=2 WHERE id='".$verifyQry->fields['id']."'");
     }else{
		$smsstatus = 405;
	 }
	} else if($status ==1){     
	/* ------ SEND NEW OTP TO USER ------ */	
	
		$configQuery = $adb->pquery("SELECT id,sify_inv_mgt_config.key,value FROM sify_inv_mgt_config WHERE treatment='serviceUrl' AND sify_inv_mgt_config.key IN('SMS_OTP_API_SECRET','SMS_OTP_API_KEY','SMS_SERVICE_URL','LBL_Otplengthchar','SMS_OTP_ALLOW_MINUTES','SMS_SIGNATURE','LBL_OTP_DELIVERY_TXT')",array());
		$configKey = $adb->getResultSet($configQuery);

		//echo '<PRE>'; print_r($configKey); exit;
		$logvl = "SEND NEW OTP TO USER ".PHP_EOL;
		file_put_contents($logfilepath, $logvl, FILE_APPEND);
		foreach($configKey as $config){
			if($config['key'] == 'SMS_SERVICE_URL'){
				$serviceUrl = htmlspecialchars_decode($config['value']);
			}
                        
                        if($config['key'] == 'LBL_OTP_DELIVERY_TXT'){
                            $deliverActualTxt = $config['value'];
                        }

			if($config['key'] == 'SMS_OTP_API_SECRET'){
				$apiSecret = $config['value'];
			}

			if($config['key'] == 'SMS_OTP_API_KEY'){
				$apiKey = $config['value'];
			}

			if($config['key'] == 'LBL_Otplengthchar'){
				$otpLength = $config['value'];
			}

			if($config['key'] == 'SMS_OTP_ALLOW_MINUTES'){
				$expireMinutes = $config['value'];
			}
			if($config['key'] == 'SMS_SIGNATURE'){
				$smssignature = $config['value'];
			}
		}
                
                
                if($deliverActualTxt !=''){
                    date_default_timezone_set("Asia/Kolkata");
                    $validTime = date('d-M-Y h:i:sa', strtotime("+$expireMinutes minutes"));
                    $digits = $otpLength;                                                                   /* ------- OTP LENGTH DECLARE --------- */
                    $smanotp = '';
                    $smanotp = str_pad(rand(1, pow(10, $digits)-1), $digits, 1, STR_PAD_LEFT);
                    settype($smanotp, "string"); 
                    $deliveryWithVal = array('###OTPVAL###'=>$smanotp,'###TRANSACTION###'=>'Registration','###COMPANY###'=>'FORUMNXT Application','###DATETIME###'=>$validTime);
                    $deliverReplTxt = $basicboject->findAndReplace($deliverActualTxt,$deliveryWithVal);
                }
		$salemancount = 0;
		if($salesmanCode !=''){    
		//    $salesmanCode = 'SLM169';
			if($_REQUEST['salesId'] >0){
				$smanid = " AND sman.xsalesmanid="."'".$_REQUEST['salesId']."'";
			} else {
				$smanid = "";
			}
			if($salesmanCode !=''){
				$smancode = " AND sman.salesmancode="."'".$salesmanCode."'";
			} else {
				$smancode = "";
			}

			$smanQuery = $adb->pquery("SELECT sman.xsalesmanid,sman.salesman,sman.salesmancode,sman.sman_mobile,xdistributorid,distributorcode,distributorname FROM vtiger_xsalesman sman INNER JOIN vtiger_xsalesmancf smancf ON sman.xsalesmanid = smancf.xsalesmanid
						INNER JOIN vtiger_xdistributor dist ON smancf.cf_xsalesman_distributor = dist.xdistributorid 
						WHERE sman.deleted=0 $smancode $smanid");
			
			if(!$smanQuery->fields['xsalesmanid'] || is_null($smanQuery->fields['xsalesmanid'])){
			   $smanQuery = $adb->pquery("SELECT xcustomersalesmanid AS xsalesmanid, salesmanname AS salesman, salesmancode, salesman_mobile AS sman_mobile FROM vtiger_xcustomersalesman sman WHERE active=1 $smancode"); 
			}
			$salemancount = $adb->num_rows($smanQuery);
		}
		if(!empty($salemancount)){
			$salesmanid = $smanQuery->fields['xsalesmanid'];
			$salesmancode = $smanQuery->fields['salesmancode'];
			$salesman = $smanQuery->fields['salesman'];
			$xdistributorid = $smanQuery->fields['xdistributorid'];
			$distributorcode = $smanQuery->fields['distributorcode'];
			$distributorname = $smanQuery->fields['distributorname'];
			$sman_mobile = $smanQuery->fields['sman_mobile'];
		}
		if(!empty($mobileNoinput)){
			$sman_mobile = $mobileNoinput;
		}
		if(!empty($customer_code)){
			$customer_code = $customer_code;
		}
		$in_qy = '';
		$logvl = "otp xsalesmanid count :".$smanQuery->fields['xsalesmanid'].PHP_EOL;
		file_put_contents($logfilepath, $logvl, FILE_APPEND);
		if($smanQuery->fields['xsalesmanid'] >0){
			$distname = mysql_real_escape_string($smanQuery->fields['distributorname']);
			$distname = addslashes($distname);
				$in_qy = "INSERT INTO st_otp_details(salesman_id, salesman_code, salesman_name, distributor_id, distributor_code, distributor_name, customer_id, customer_code, customer_name, mobile_number, otp_value, otp_hashvalue, expire_minutes, is_expire, expire_update, uid, createdate, updatedate) VALUES(".$salesmanid.", '".$salesmancode."', '".$salesman."','".$xdistributorid."', '".$distributorcode."', '".$distname."',0,'".$customer_code."','', '".$sman_mobile."', '".$smanotp."', md5($smanotp),$expireMinutes,0,'',0, NOW(), NOW())";
				$adb->query($in_qy,array());
				$autoId = $adb->getLastInsertID();
		}
		$logvl .= "otp in qy:".$in_qy.PHP_EOL;
		file_put_contents($logfilepath, $logvl, FILE_APPEND);
		$mobileNo = '';

		if(strlen($sman_mobile)==10){
			$mobileNo = '91'.$sman_mobile;
		}
		if(strlen($sman_mobile)==12){
			$mobileNo = $sman_mobile;
		}
		if(strlen($sman_mobile) <10){
			$mobileNo = '';
		}

		if($mobileNo !=''){                                                                    /* ------- BUILD SMS ARRAY TO SEND --------- */
		//$url = 'https://rest.nexmo.com/sms/json?';
		$paramArr = array('##APIKEY##' =>  $apiKey,
			  '##APISECRET##' => $apiSecret,
			  '##SIGNATURE##' => $smssignature,
			 'from' => $smssignature,
			  'text' => $deliverReplTxt,
			  'mnumber' => $mobileNo,
			  'to' => $mobileNo,
			  'message' => $deliverReplTxt,
			  );
		$serviceUrl = $basicboject->findAndReplace($serviceUrl,$paramArr);
		/* $paramArr = array('api_key' =>  $apiKey,
			  'api_secret' => $apiSecret,
			  'to' => $mobileNo,
			  'from' => '917904216448',
			  'text' => "Your Verification OTP is: $smanotp"  ); */ //hide by sabari for qc testing
		$data = http_build_query($paramArr);
		$smsstatus = 200;
		//echo '<PRE>'; print_r($paramArr); die;
		$logvl = "serviceUrl:".$serviceUrl.PHP_EOL;
		$logvl .= "Data:".print_r($data,TRUE).PHP_EOL;
		file_put_contents($logfilepath, $logvl, FILE_APPEND);
		$overAll = sendMobileSMS($serviceUrl, $data);   
        $logvl = "Responce:".print_r($overAll,TRUE).PHP_EOL;
		file_put_contents($logfilepath, $logvl, FILE_APPEND);
                $adb->query("UPDATE vtiger_xdistdeviceregistration SET app_otp='$smanotp',updatedate=NOW() WHERE salesmanid='$salesmanCode'");
		/*    ------- CALL: SEND SMS FUNCTION ------- */
		//echo '<PRE>'; print_r($overAll); exit;
			foreach($overAll->messages as $response){
				//echo $response->status.'<PRE>';
				//echo $response->network;
				if($response->status==0){
					//echo "We sent OTP to your Mobile: ".$smanQuery->fields['sman_mobile'];
					//echo $response->remaining-balance;
					$smsstatus = 200;
				}
			}
                        /* ----------- LOG TABLE INSERT HERE ----------- */
                        $adb->query("INSERT INTO st_otp_log(st_otp_id, salesman_id, salesman_code, distributor_id, distributor_code, mobile_number, customer_id, customer_code, message, otp_value, createdate) VALUES($autoId,".$smanQuery->fields['xsalesmanid'].", '".$smanQuery->fields['salesmancode']."','".$smanQuery->fields['xdistributorid']."', '".$smanQuery->fields['distributorcode']."', '".$smanQuery->fields['sman_mobile']."',0,'','".$deliverReplTxt."',$smanotp,NOW())", array());
		}
	}
	return $smsstatus;
}

function sendMobileSMS($sendUrl, $datas){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $sendUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    // Edit: prior variable $postFields should be $postfields;
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);

    $result = curl_exec($ch);
    return json_decode($result);
}
function toCheckIsConverted($dataid,$moulename){
	global  $adb;
    $modulelist = array(
        'xrSalesOrder' => 'xSalesOrder',
        'xrSalesInvoice' => 'SalesInvoice',
        'xrCollection' => 'xCollection',
    );
    $returnvalue = 0;
    $relmolname = $modulelist[trim($moulename)];
    if(!empty($dataid)){
        $relselectqy = "SELECT relcrmid FROM `vtiger_crmentityrel` WHERE `module` = '".$moulename."' AND `relmodule` = '".$relmolname."' AND `crmid` = '".$dataid."' ";
        $relselectqyexe = $adb->mquery($relselectqy,array());
        $returnvalue = $adb->num_rows($relselectqyexe);
    }
    return $returnvalue;
}

//Added by Prasanth 19/09/2017 - Report Generate and download get with fiter paramter
function GenerateFilterParam($fp){
	global $adb;
	if($_REQUEST['filterparam']){
		if(isset($_REQUEST['repid']) && $_REQUEST['repid']>0){
			$reportbuttons="select filterparamtocsv from vtiger_report where vtiger_report.reporttype in ('STATIC','tabular','summary') AND vtiger_report.reportid=?";
			$reportbu=$adb->pquery($reportbuttons,array($_REQUEST['repid']));
                        $reportbnres = $adb->query_result_rowdata($reportbu,0);
                        if($reportbnres['filterparamtocsv']==0){
				return false;
			}
		}
		$content = '';
		$filterparam = json_decode($_REQUEST['filterparam'],true);
		if(is_array($filterparam) && count($filterparam)>0){
			$filterarraysplit = array_chunk($filterparam,2,true);
			foreach ($filterarraysplit as $result){
				$vals=array();
				foreach($result as $key => $val){
					$string = trim(preg_replace('/\s\s+/', ' ', $val));
					$keyr = trim(preg_replace('/\s\s+/', ' ', $key));
					$vals= array_merge($vals,array('','',$keyr,' ',$string,' '));
					//$content.= ',,,'.$keyr.', ,'.$string.', '; 
				}
				fputcsv($fp, $vals);
			//	$content.= PHP_EOL;
			//	$content.= PHP_EOL;
			}
			fputcsv($fp, array(''));
		}
		//return $content;
	}
	//return '';
}

function getTaxPercentageForSKU($productid,$distId,$vendorId,$shipping_address_pick,$si_location,$trans_date) 
{   
    print_r(array($productid,'all','SalesInvoice',$distId,$vendorId,'','','',$shipping_address_pick,$si_location,0,$trans_date));
    $taxes_for_product=getTaxDetailsForProduct($productid,'all','SalesInvoice',$distId,$vendorId,'','','',$shipping_address_pick,$si_location,0,$trans_date);
     
    $taxPerToApply=0.0;
    for($tax_count=0;$tax_count<count($taxes_for_product);$tax_count++)
    {
        $request_tax_name = $taxes_for_product[$tax_count]['percentage'];
        if($request_tax_name>0.0)
           $taxPerToApply+=$request_tax_name;
    }
    echo "Tax:".$taxPerToApply;
    return $taxPerToApply;
}

function convert_rmi_to_mi($ids){
    global $adb;
    //$ids = array(129093,129094,129101);
    require_once('modules/xMerchandiseIssue/xMerchandiseIssue.php');
    require_once('modules/xrMerchandiseIssue/xrMerchandiseIssue.php');
    require_once('modules/xMerchandiseIssueProduct/xMerchandiseIssueProduct.php');
    
    $mi_focus = new xMerchandiseIssue();
    $rmi_focus = new xrMerchandiseIssue();
    foreach($ids as $merchandId){  
        $rmi_focus->id = $merchandId;
        $rmi_focus->retrieve_entity_info($merchandId, "xrMerchandiseIssue");
        
        $mi_focus->column_fields['salesman'] = $rmi_focus->column_fields['salesman'];
        $mi_focus->column_fields['beat'] = $rmi_focus->column_fields['beat'];
        $mi_focus->column_fields['retailer'] = $rmi_focus->column_fields['retailer'];
        $mi_focus->column_fields['retailer'] = $rmi_focus->column_fields['retailer'];
        $mi_focus->column_fields['godown_id'] = $rmi_focus->column_fields['godown_id'];
        $mi_focus->column_fields['reason'] = $rmi_focus->column_fields['reason'];
        $mi_focus->column_fields['remark'] = $rmi_focus->column_fields['remark'];
        $mi_focus->column_fields['xdistributorid'] = $rmi_focus->column_fields['xdistributorid'];
        
        $mi_focus->column_fields['created_date'] = date('Y-m-d', strtotime($rmi_focus->column_fields['created_at']));
        
        $receiveId = $rmi_focus->column_fields['xrmerchandiseissueid'];
        
        $mi_focus->column_fields['cf_xmerchandiseissue_status'] = 'Created';
        $mi_focus->column_fields['cf_xmerchandiseissue_next_stage_name'] = 'Publish';
        /* -------------- SAVE THE PRODUCT xMerchandiseIssue TABLE --------------- */
        $returnId = $mi_focus->save('xMerchandiseIssue');
        $returnId = $mi_focus->id;
        
        $relquery = "SELECT * FROM vtiger_xrmerchandiseissuerel WHERE xmerchandiseissueid=$merchandId";
        $relResult = $adb->pquery($relquery, array());
        $resultSet = $adb->getResultSet($relResult);
            $rel_focus = new xMerchandiseIssueProduct();
            date_default_timezone_set('Asia/Calcutta'); 
            $currentTime = date('Y-m-d H:i:s');
        foreach($resultSet as $relArr){
            $rel_focus->column_fields['merchandise_product'] = $relArr['merchandise_product']; 
            $rel_focus->column_fields['merchandise_type'] = $relArr['merchandise_type']; 
            $rel_focus->column_fields['display_type'] = $relArr['display_type']; 
            $rel_focus->column_fields['to_be_return'] = $relArr['to_be_return']; 
            $rel_focus->column_fields['date_of_return'] = $relArr['date_of_return']; 
            $rel_focus->column_fields['avl_qty'] = $relArr['avl_qty']; 
            $rel_focus->column_fields['issue_qty'] = $relArr['issue_qty']; 
            $rel_focus->column_fields['xmerchandiseissueid'] = $returnId; 
            $rel_focus->column_fields['created_at'] = $currentTime;
            $rel_focus->column_fields['modified_at'] = $currentTime;
            /* -------------- SAVE THE PRODUCT xMerchandiseIssueProduct TABLE --------------- */

            $rel_focus->save('xMerchandiseIssueProduct');
        }
        
        /* ------------------- STATUS UPDATE AS 'Processed' ------------------- */
        $statusQry = "UPDATE vtiger_xrmerchandiseissue SET xmerchandiseissue_status='Processed',xmerchandiseissue_next_stage_name='' WHERE xrmerchandiseissueid=$merchandId";
        $adb->pquery($statusQry,array());
    }
}


function generateTransactionSeries($modName,$transID){
    global $adb,$LBL_USE_RECEIVED_TRANSACTION_NUMBER;
    if ($modName == 'SalesInvoice'){
        require_once('modules/SalesInvoice/SalesInvoice.php');
        require_once('include/TransactionSeries.php');
        $log =& LoggerManager::getLogger('index');
        $focus = new SalesInvoice();
        $return_id = $transID;//$focus->id;
        $log->debug("Module: ".$modName." ________Return id: ".$return_id. " ______focus id: ".$focus->id );
        $Status_Query = $adb->mquery("SELECT sicf.salesinvoiceid,sicf.cf_salesinvoice_transaction_series,sicf.cf_salesinvoice_transaction_number AS transaction_number,si.status AS status FROM vtiger_salesinvoice si 
                                    INNER JOIN vtiger_salesinvoicecf sicf ON si.salesinvoiceid = sicf.salesinvoiceid 
                                    WHERE si.salesinvoiceid=? LIMIT 1", array($return_id));
        //$sales_invoice_data = $adb->getResultSet($Status_Query);
        //$log->debug("Trans number Before (14898) : " . $sales_invoice_data[0]['cf_salesinvoice_transaction_number']."_____  cf salesinvoiceid: ".$sales_invoice_data[0]['salesinvoiceid']);
        if($adb->num_rows($Status_Query)>0) {       
            $Trans_Status   = $adb->query_result($Status_Query,0,'transaction_number');
            $SI_Status      = $adb->query_result($Status_Query,0,'status');
            $transaction_series = $adb->query_result($Status_Query,0,'cf_salesinvoice_transaction_series');
			
//            SITransNumberLogResult($Trans_Status,$SI_Status,$return_id);
            $increment = TRUE;
                if ($LBL_USE_RECEIVED_TRANSACTION_NUMBER == 'True' && !empty($focus->column_fields['tracking_no']) && $_REQUEST['convertmode'] == 'rsitosi') {
                    $increment = FALSE;
                }
            //if($Trans_Status=='Draft' && $SI_Status!='Draft'){
            /* FRPRDINXT-15953 */
            if(preg_match('/Draft/', $Trans_Status) || $Trans_Status==''){ // FRPRDINXT-17967
                // Removed to Prevent Transaction Number as Draft Issue
                $tArr = generateUniqueSeries($transaction_series, "Sales Invoice",$increment);
                $transaction_number = $tArr['uniqueSeries']; 
                $log->debug("Trans number After Generated (14906) : " . $transaction_number);
                $focus->column_fields['cf_salesinvoice_transaction_number'] = $transaction_number;
                SITransNumberLogResult($Trans_Status,$SI_Status,$return_id,$transaction_number);
				//$adb->mquery("UPDATE vtiger_salesinvoicecf SET cf_salesinvoice_transaction_number=? WHERE salesinvoiceid=?", array($transaction_number, $return_id));
				$adb->mquery("UPDATE vtiger_salesinvoicecf AS sicf INNER JOIN vtiger_salesinvoice AS si ON si.salesinvoiceid = sicf.salesinvoiceid
INNER JOIN vtiger_crmentity AS CRM ON CRM.crmid = si.salesinvoiceid SET sicf.cf_salesinvoice_transaction_number=?,sicf.created_at = NOW(),si.created_at=NOW(),CRM.createdtime=NOW(),CRM.modifiedtime=NOW() WHERE sicf.salesinvoiceid=?",array($transaction_number, $return_id));
            }
        }else{
            SITransNumberLogResult('Query Result Skip',$focus->column_fields['status'],$return_id);
        }
    }else if($modName == 'xSalesReturn'){
		
		$adb->mquery("UPDATE vtiger_xsalesreturncf AS srcf 
		INNER JOIN vtiger_xsalesreturn AS sr ON sr.xsalesreturnid = srcf.xsalesreturnid 
		INNER JOIN vtiger_crmentity AS CRM ON CRM.crmid = sr.xsalesreturnid SET srcf.created_at= NOW(),sr.created_at=NOW(),CRM.createdtime=NOW(),CRM.modifiedtime=NOW() WHERE srcf.xsalesreturnid=?",array($transID));
	
	}else if($modName == 'PurchaseInvoice' || strtolower($modName)=='purchaseinvoice'){
		
		$adb->mquery("UPDATE vtiger_purchaseinvoicecf AS picf 
		INNER JOIN vtiger_purchaseinvoice AS pi ON pi.purchaseinvoiceid = picf.purchaseinvoiceid 
		INNER JOIN vtiger_crmentity AS CRM ON CRM.crmid = pi.purchaseinvoiceid SET picf.created_at= NOW(),pi.created_at=NOW(),CRM.createdtime=NOW(),CRM.modifiedtime=NOW() WHERE picf.purchaseinvoiceid=?",array($transID));
		
	}else if($modName == 'xPurchaseReturn'){
		
		$adb->mquery("UPDATE vtiger_xpurchasereturncf AS prcf 
		INNER JOIN vtiger_xpurchasereturn AS pr ON pr.xpurchasereturnid = prcf.xpurchasereturnid 
		INNER JOIN vtiger_crmentity AS CRM ON CRM.crmid = pr.xpurchasereturnid SET prcf.created_at= NOW(),pr.created_at=NOW(),CRM.createdtime=NOW(),CRM.modifiedtime=NOW() WHERE prcf.xpurchasereturnid=?",array($transID));
	
	}else if($modName == 'xvanloading'){
		
		$adb->mquery("UPDATE vtiger_xvanloadingcf AS xvancf 
		INNER JOIN vtiger_xvanloading AS xvan ON xvan.xvanloadingid = xvancf.xvanloadingid 
		INNER JOIN vtiger_crmentity AS CRM ON CRM.crmid = xvan.xvanloadingid SET xvancf.created_at= NOW(),xvan.created_at=NOW(),CRM.createdtime=NOW(),CRM.modifiedtime=NOW() WHERE xvancf.xvanloadingid=?",array($transID));
	
	}else if($modName == 'xvanunloading'){
		
		$adb->mquery("UPDATE vtiger_xvanunloadingcf AS xvanulcf 
		INNER JOIN vtiger_xvanunloading AS xvanul ON xvanul.xvanunloadingid = xvanulcf.xvanunloadingid 
		INNER JOIN vtiger_crmentity AS CRM ON CRM.crmid = xvanul.xvanunloadingid SET xvanulcf.created_at= NOW(),xvanul.created_at=NOW(),CRM.createdtime=NOW(),CRM.modifiedtime=NOW() WHERE xvanulcf.xvanunloadingid=?",array($transID));
	
	}else if($modName == 'xGodownTfrStockConv'){
		
		$adb->mquery("UPDATE vtiger_xgodowntfrstockconvcf AS xgodowncf 
		INNER JOIN vtiger_xgodowntfrstockconv AS xgodown ON xgodown.xgodowntfrstockconvid = xgodowncf.xgodowntfrstockconvid 
		INNER JOIN vtiger_crmentity AS CRM ON CRM.crmid = xgodown.xgodowntfrstockconvid SET xgodowncf.created_at= NOW(),xgodown.created_at=NOW(),CRM.createdtime=NOW(),CRM.modifiedtime=NOW() WHERE xgodowncf.xgodowntfrstockconvid=?",array($transID));
	
    }
}

function SITransNumberLogResult($trans_no_status,$status,$recordid,$trans_no=0){

        global $root_directory;
        $filePath   = $root_directory."/storage/log/si/";
        $fileName   = $filePath."/si_transaction_".date("Y_m_d").".txt";

        if (!file_exists($filePath)) {       
            mkdir($filePath, 0777, true);
        }
        $fp                 = fopen($fileName, 'r+');
        $resulst            = "+++++++ $funName - Log Time - ".date("Y-m-d H:i:s")." +++++++".PHP_EOL;
        $resulst           .= "SI Tranaction No Status:-".$trans_no_status."; SI Status:-".$status."; SI Transaction ID:-".$recordid."Tranaction No:-".$trans_no;
        file_put_contents($fileName, $resulst.PHP_EOL , FILE_APPEND | LOCK_EX); 
}

/**
 * FRPRDINXT-12912
 * 
 * @global type $adb, $ALLOW_GST_TRANSACTION
 * @param type $modName
 * @param type $return_id
 * @return type
 */
function createRPI($modName,$return_id)
{
    global $adb,$ALLOW_GST_TRANSACTION;
    $log =& LoggerManager::getLogger('index');

    if($ALLOW_GST_TRANSACTION){
        require_once('modules/xrPurchaseInvoice/xrPurchaseInvoice.php');
        require_once('include/TransactionSeries.php');

        $focus1 = new xrPurchaseInvoice();
        
        $result = $adb->mquery("SELECT si.salesinvoiceid, si.total, si.subtotal, si.si_location, si.buyer_gstinno, si.seller_gstinno, si.buyer_state, si.seller_state, si.trntaxtype, 
                                sicf.cf_salesinvoice_transaction_number, sicf.cf_salesinvoice_sales_invoice_date, sicf.cf_salesinvoice_buyer_id, sicf.cf_salesinvoice_seller_id,
                                siba.bill_city,siba.bill_code,siba.bill_country,siba.bill_state,siba.bill_street,siba.bill_pobox,
                                sisa.ship_city,sisa.ship_code,sisa.ship_country,sisa.ship_state,sisa.ship_street,sisa.ship_pobox
                                FROM vtiger_salesinvoice si
                                INNER JOIN vtiger_salesinvoicecf sicf ON si.salesinvoiceid = sicf.salesinvoiceid
                                INNER JOIN vtiger_sibillads siba ON siba.sibilladdressid = sicf.salesinvoiceid
                                INNER JOIN vtiger_sishipads sisa ON sisa.sishipaddressid = sicf.salesinvoiceid
                                where si.salesinvoiceid = '$return_id'");
        
        $sales_invoice_data = $adb->getResultSet($result);

        $log->debug('sales_invoice_data: '.print_r($sales_invoice_data, true));
        
        $buyer_retailer_id = $sales_invoice_data[0]['cf_salesinvoice_buyer_id']; //Sub-Dist linked Retailer ID
        $buyer_code_result  = $adb->mquery("SELECT d.xdistributorid, d.distributorcode FROM vtiger_xdistributor d INNER JOIN vtiger_xretailer r ON d.xdistributorid = r.posting_xdistributorid WHERE r.xretailerid = '$buyer_retailer_id'");
        
        $buyer_id = $adb->query_result($buyer_code_result,0,'xdistributorid'); //Sub-Dist ID
        $buyer_code = $adb->query_result($buyer_code_result,0,'distributorcode'); //Sub-Dist Code

        $vendor_result  = $adb->mquery("SELECT vendorid, vendor_no, vendorname FROM vtiger_vendor WHERE `distributor_id` = '$buyer_id'");
        $vendor_id = $adb->query_result($vendor_result,0,'vendorid');  //Main Dist linked Vendor ID
        $seller_code = $adb->query_result($vendor_result,0,'vendor_no');  //Main Dist linked Vendor Code
        
        $default_depot_result = $adb->mquery("SELECT xdistributorid, cf_xdistributor_default_depot as depotid,dt.supplylocation FROM vtiger_xdistributorcf cf LEFT JOIN vtiger_xdepot dt ON dt.xdepotid=cf.cf_xdistributor_default_depot WHERE cf.xdistributorid = '$buyer_id'");
        $default_depot = $adb->query_result($default_depot_result,0,'depotid');  //Sub-Dist linked Vendor Default Depot

        $focus1->column_fields['status'] = "Draft";
        $focus1->column_fields['requisition_no'] = $sales_invoice_data[0]['salesinvoiceid'];
        $focus1->column_fields['subject'] = $sales_invoice_data[0]['cf_salesinvoice_transaction_number'];
        $focus1->column_fields['purchaseinvoice_no'] = $sales_invoice_data[0]['cf_salesinvoice_transaction_number'];
        $focus1->column_fields['tracking_no'] = $sales_invoice_data[0]['cf_salesinvoice_transaction_number'];
        $focus1->column_fields['vendorid'] = $vendor_id;
        $focus1->column_fields['buyer_gstinno'] = $sales_invoice_data[0]['buyer_gstinno'];
        $focus1->column_fields['seller_gstinno'] = $sales_invoice_data[0]['seller_gstinno'];
        $focus1->column_fields['buyer_state'] = $sales_invoice_data[0]['buyer_state'];
        $focus1->column_fields['seller_state'] = $sales_invoice_data[0]['seller_state'];
        $focus1->column_fields['trntaxtype'] = $sales_invoice_data[0]['trntaxtype'];
        $focus1->column_fields['taxtype'] = 'individual';
        
        $focus1->column_fields['cf_purchaseinvoice_purchase_invoice_date'] = $sales_invoice_data[0]['cf_salesinvoice_sales_invoice_date'];
        $focus1->column_fields['cf_purchaseinvoice_transaction_number'] = $sales_invoice_data[0]['cf_salesinvoice_transaction_number'];; //Need to save Transaction number for RPI if exists and needed
        $focus1->column_fields['cf_purchaseinvoice_next_stage_name'] = "Creation";
        $focus1->column_fields['cf_purchaseinvoice_buyer_id'] = $buyer_code;
        $focus1->column_fields['cf_purchaseinvoice_seller_id'] = $seller_code;
        $focus1->column_fields['cf_purchaseinvoice_bill_date'] = $sales_invoice_data[0]['cf_salesinvoice_sales_invoice_date'];
        $focus1->column_fields['cf_purchaseinvoice_depot'] = $default_depot;

        $focus1->column_fields['bill_city'] = $sales_invoice_data[0]['bill_city'];
        $focus1->column_fields['bill_code'] = $sales_invoice_data[0]['bill_code'];
        $focus1->column_fields['bill_country'] = $sales_invoice_data[0]['bill_country'];
        $focus1->column_fields['bill_state'] = $sales_invoice_data[0]['bill_state'];
        $focus1->column_fields['bill_street'] = $sales_invoice_data[0]['bill_street'];
        $focus1->column_fields['bill_pobox'] = $sales_invoice_data[0]['bill_pobox'];

        $focus1->column_fields['ship_city'] = $sales_invoice_data[0]['ship_city'];
        $focus1->column_fields['ship_code'] = $sales_invoice_data[0]['ship_code'];
        $focus1->column_fields['ship_country'] = $sales_invoice_data[0]['ship_country'];
        $focus1->column_fields['ship_state'] = $sales_invoice_data[0]['ship_state'];
        $focus1->column_fields['ship_street'] = $sales_invoice_data[0]['ship_street'];
        $focus1->column_fields['ship_pobox'] = $sales_invoice_data[0]['ship_pobox'];
        
        $log->debug('Focus->column_fields: '.print_r($focus1->column_fields, true));
        
        $focus1->save('xrPurchaseInvoice');
        $xrPurchaseInvoice_id = $focus1->id;
    }
}
/**
 * 
 * @global type $adb
 * @param type $modName
 * @param type $return_id
 * @return type
 */
function create_salesman($modName,$return_id)
{
    global $adb,$CREATE_IMPLICIT_SALESMAN,$LBL_SALESMAN_STAGENAME,$LBL_SALESMAN_NEXTSTAGENAME;

    if($CREATE_IMPLICIT_SALESMAN != 'Enable')
        return false;
    
    $query              = " SELECT count(vtiger_xsalesman.xsalesmanid) as slm_cnt 
                            FROM vtiger_xsalesman 
                            INNER JOIN vtiger_crmentity     ON vtiger_crmentity.crmid = vtiger_xsalesman.xsalesmanid 
                            INNER JOIN vtiger_xsalesmancf   ON vtiger_xsalesmancf.xsalesmanid = vtiger_xsalesman.xsalesmanid 
                            WHERE vtiger_crmentity.deleted = 0 AND vtiger_xsalesmancf.cf_xsalesman_distributor=?";
    $result             = $adb->pquery($query,array($return_id));
    $actual_slm_cnt     = $adb->query_result($result,0,'slm_cnt');

    $query              = "SELECT salesman_count AS salesman_count FROM vtiger_xdistributor WHERE xdistributorid=?";
    $result             = $adb->pquery($query,array($return_id));
    $new_slm_count      = $adb->query_result($result,0,'salesman_count');
    
    if($new_slm_count>$actual_slm_cnt){
            checkFileAccess("modules/xSalesman/xSalesman.php");
            require_once("modules/xSalesman/xSalesman.php");
            $focus          = new xSalesman();
            $salesman_seq   = new CRMEntity();

            $cnt            = $new_slm_count - $actual_slm_cnt;
           
            for($i=0;$i<$cnt;$i++){
                    $focus->column_fields['salesman']                   = 'Salesman'.($actual_slm_cnt+$i+1);
                    $focus->column_fields['salesmancode']               = $salesman_seq->seModSeqNumber('increment', 'xSalesman');
                    $focus->column_fields['sman_password']              = 'password';
                    $focus->column_fields['sman_conf_password']         = 'password';
                    $focus->column_fields['cf_xsalesman_distributor']   = $return_id;
                    $focus->column_fields['cf_xsalesman_active']        = 1;
                    $focus->column_fields['status']                     = $LBL_SALESMAN_STAGENAME;
                    $focus->column_fields['next_stage_name']            = $LBL_SALESMAN_NEXTSTAGENAME;
                    $focus->save('xSalesman');
            }
    }
}
/**
 * FRPRDINXT-13778
 * 
 * @global type $adb
 * @param type $modName
 * @param type $return_id
 * @return type
 */
function EmailSend($modName,$return_id)
{
    global $adb,$site_URL;
    $distArr        = getDistrIDbyUserID();

    $result         = $adb->mquery("SELECT users.first_name as first_name,users.last_name as last_name,users.email1 as email1,users.email2 as email2
    FROM vtiger_xcpdpmapping xcp
    INNER JOIN vtiger_xcpdpmappingcf xcpcf ON xcpcf.xcpdpmappingid = xcp.xcpdpmappingid
    INNER JOIN vtiger_crmentity crm ON crm.crmid = xcp.xcpdpmappingid
    INNER JOIN vtiger_users users ON users.id = xcp.cpusers
    WHERE xcpcf.cf_xcpdpmapping_distributor=? AND users.status='Active' AND xcpcf.cf_xcpdpmapping_active=1 AND crm.deleted=0 AND (users.email1!='' OR users.email2!='') ",array($distArr['id']));
        
    $noofrows       = $adb->num_rows($result);
    if($noofrows>0){
        
        $user_email     = '';
        while ($user_info = $adb->fetch_array($result))
        {
            $email      = $user_info['email1'];
            if($email == '' || $email == 'NULL')
            {
                $email = $user_info['email2'];

            }
            if($email != '' && $email != 'NULL'){
                if($user_email==''){
                    //$user_email .= $user_info['first_name']." ".$user_info['last_name']."<".$email.">";
                    $user_email .= $email;
                }else{
                    //$user_email .= ",".$user_info['first_name']." ".$user_info['last_name']."<".$email.">";
                    $user_email .= ",".$email;
                }
            }
            $email      ='';
        }
        
        $EX_EMAIL_RES           = $adb->mquery("SELECT value AS email1 FROM sify_inv_mgt_config WHERE treatment='EMAIL' ");
        $Email_noofrows         = $adb->num_rows($EX_EMAIL_RES);
        if($Email_noofrows>0){
            while ($user_info = $adb->fetch_array($EX_EMAIL_RES))
            {
                $email      = $user_info['email1'];
                if($email != '' && $email != 'NULL'){
                    $emailArr   = implode(",",explode(";",$email));
                    if($user_email==''){
                        $user_email .= $email;
                    }else{
                        $user_email .= ",".$email;
                    }
                }
                $email      = '';
            }
        }
        
        $to_email               = $user_email; 

		if(empty($to_email))
			return false;
		
		$to_email               = implode(",",array_unique(explode(",",$to_email)));
		
        $RES_FROM_EMAIL         = $adb->mquery("SELECT from_email_field FROM `vtiger_systems` LIMIT 1 ",array());
        $from_email             = $adb->query_result($RES_FROM_EMAIL,0,"from_email_field");


        $RES_EMAIL_CONT         = $adb->mquery("SELECT * FROM `vtiger_emailtemplates` WHERE deleted=0 AND templatename=? LIMIT 1",array($modName));
        $description            = $adb->query_result($RES_EMAIL_CONT,0,"body");
        $subject                = $adb->query_result($RES_EMAIL_CONT,0,"subject");

        $DESCRIPTION_MER	= getMergedDescription($description,$return_id,$modName);
        $SUBJECT_MER            = getMergedDescription($subject,$return_id,$modName);
        
        $SUBJECT_MER            = getMergedDescription($SUBJECT_MER,$distArr['id'],'xDistributor');
        $DESCRIPTION_MER	= getMergedDescription($DESCRIPTION_MER,$distArr['id'],'xDistributor');
        
        $DESCRIPTION_MER        = str_replace("###SITE_URL###",$site_URL,$DESCRIPTION_MER); 
		$DESCRIPTION_MER        = str_replace("###RECORD_ID###",$return_id,$DESCRIPTION_MER); 
        
        $arrPODetails 			= getEmail_DetailAssociatedProducts_PO($return_id);
		$TOTAL_ORDER            = $arrPODetails['TotalOrderInKG'];
                
		unset($arrPODetails['TotalOrderInKG']);
		$i=0;
		foreach($arrPODetails as $poDetails){
			if($i==0)
				$PRODUCT_LIST .= '</td></tr>';

			$PRODUCT_LIST .= '<tr valign="top">
			<td>'.($i+1).'</td>
			<td>'.$poDetails['ProductName'].'</td>
			<td>'.$poDetails['SuggestedOrderQty'].'</td>
			<td>'.$poDetails['SuggestedUOM'].'</td>
			<td>'.$poDetails['ActualOrderQty'].'</td>
			<td>'.$poDetails['OrderUOM'].'</td>
			<td>'.$poDetails['OrderQtyInKG'].'</td>
			</tr>';
			
			$i++;
		}
		$PRODUCT_LIST .= '<tr style="display:none"><td>';
		
		$DESCRIPTION_MER        = str_replace("###PRODUCT_LIST###",$PRODUCT_LIST,$DESCRIPTION_MER); 
		$DESCRIPTION_MER        = str_replace("###TOTAL_ORDER###",$TOTAL_ORDER,$DESCRIPTION_MER); 


        if($to_email != '')
        {
			require_once("modules/Emails/mail.php");
			
            $mail_status = send_mail('Emails',"$to_email",'Administrator',$from_email,$SUBJECT_MER,$DESCRIPTION_MER);
        }
		
    }
}

function getEmail_DetailAssociatedProducts_PO($varTransId){
	global $adb; 
	$query="select case when vtiger_xproduct.xproductid != '' then vtiger_xproduct.productname else vtiger_service.servicename end as productname," .
	" case when vtiger_xproduct.xproductid != '' then 'xProduct' else 'Services' end as entitytype," . 
	" case when vtiger_xproduct.xproductid != '' then vtiger_xproduct.qtyinstock else 'NA' end as qtyinstock, vtiger_purchaseorderproductrel.*,vtiger_xproduct.*,vtiger_xproductcf.*, " .
	" CASE WHEN vtiger_purchaseorderproductrel.suggested_reorder_qty != '0.00000' THEN vtiger_purchaseorderproductrel.suggested_reorder_qty ELSE 'NA' END AS  suggested_reorder_qty, " .
	" CASE WHEN vtiger_purchaseorderproductrel.suggestes_uomid != '' THEN vtiger_uom.uomname ELSE 'NA' END AS sugguom " .
	" from vtiger_purchaseorderproductrel" .
	" left join vtiger_xproduct on vtiger_xproduct.xproductid=vtiger_purchaseorderproductrel.productid " .
	" left join vtiger_xproductcf on vtiger_xproductcf.xproductid=vtiger_purchaseorderproductrel.productid " .
	" left join vtiger_service on vtiger_service.serviceid=vtiger_purchaseorderproductrel.productid " .
	" LEFT JOIN vtiger_uom ON vtiger_uom.uomid = vtiger_purchaseorderproductrel.suggestes_uomid ".
	" where id=? ORDER BY sequence_no";

	$result 		= $adb->mquery($query, array($varTransId));
	$num_rows 		= $adb->num_rows($result);
	$arrPODetails 	= array();
	$varTotalOrderKg = 0;
	for($i=1,$j=0;$i<=$num_rows;$i++,$j++)
	{
		$productname			=$adb->query_result($result,$i-1,'productname');
		$suggested_reorder_qty	=$adb->query_result($result,$i-1,'suggested_reorder_qty');
		$suggestes_uom			=$adb->query_result($result,$i-1,'sugguom');
					 
		$uom1					=$adb->query_result($result,$i-1,'tuom');
		$uomquery				="select uomname from vtiger_uom where uomid = ? or uomname = ?";
		$resultuom				=$adb->pquery($uomquery, array($uom1,$uom1));
		$uom					=$adb->query_result($resultuom,0,"uomname");

		$varConUOM1				=$adb->query_result($result,$i-1,'cf_xproduct_uom1');
		$uomConQuery			="select uomname from vtiger_uom where uomid = ? or uomname = ?";
		$resultConUOM1			=$adb->pquery($uomConQuery, array($varConUOM1,$varConUOM1));
		$varUOM1Name			= $adb->query_result($resultConUOM1,0,"uomname");

		$qty					=$adb->query_result($result,$i-1,'quantity');
		$qtyB					=$adb->query_result($result,$i-1,'baseqty');
				  
		$tUOMcon2				=$adb->query_result($result,$i-1,'cf_xproduct_uom1_conversion');
		$varQtyDecimals 		= formatQtyDecimals($qty);
		$varUOMName 			= $uom;
		$varQtyUOM1 			= ($qtyB/$tUOMcon2);
		$varTotalOrderKg+=$varQtyUOM1;
		$arrPODetails[$j]['ProductName'] 		= $productname;
		$arrPODetails[$j]['SuggestedOrderQty'] 	= $suggested_reorder_qty;
		$arrPODetails[$j]['SuggestedUOM'] 		= $suggestes_uom;
		$arrPODetails[$j]['ActualOrderQty'] 	= $varQtyDecimals;
		$arrPODetails[$j]['OrderUOM'] 			= $varUOMName;
		$arrPODetails[$j]['OrderQtyInKG'] 		= formatCurrencyDecimals($varQtyUOM1);
	} 
	$arrPODetails['TotalOrderInKG'] = formatCurrencyDecimals($varTotalOrderKg);
	return $arrPODetails;
}

/**
 * FRPRDINXT-13116
 * 
 * @global type $adb
 * @param type $CurrentModule
 * @param type $ConfigKey
 * @param type $subStockType
 * @return type
 */
function GetSubStockTypeConfiguration($CurrentModule, $ConfigKey = "SUB_STOCK_TYPE", $subStockType = null) {

    global $adb;

    $subStockTypeEx = $subStockType ? $subStockType : "S,SF,D,DF"; // Primary Sub Stock Types
    $subStockTypeIm = "'" . implode("','", explode(',', $subStockTypeEx)) . "'";
    $subStockType = !$subStockType ? $subStockTypeIm : $subStockType;
    $sQuery = "SELECT from_stock_type,to_stock_type FROM `sify_inv_mgt_config` WHERE `key` =? AND transfer_mode=? AND from_stock_type IN ($subStockType)";
    $sQuery = $adb->mquery($sQuery, array($ConfigKey, $CurrentModule));
    $subSTockTypeArray = array();
    $subStockTypeLoop = explode(",", $subStockTypeEx); // Array ( [0] => S [1] => SF [2] => D [3] => DF )
    foreach ($subStockTypeLoop as $key => $value) {
        $m = 0;
        for ($s = 0; $s < $adb->num_rows($sQuery); $s++) {
            $fromStockType = $adb->query_result($sQuery, $s, 'from_stock_type');
            $toStockType = $adb->query_result($sQuery, $s, 'to_stock_type');
            if ($value == $fromStockType) {
                $m += 1;
                $subSTockTypeArray[$value]['Enable_' . $value] = ($m > 1) ? 1 : 0; // Type 1
                //$subSTockTypeArray[$value][$fromStockType] .= (($m > 1) ? "," : "") . $toStockType; // Type 2
                // Type 2
                $SLabel = getAbbrSLabel($toStockType);
                $subSTockTypeArray[$value][$fromStockType][($SLabel ? $SLabel : $toStockType)] = $toStockType; // 1A
                // $subSTockTypeArray[$value][$fromStockType][$toStockType] = $SLabel ? $SLabel : $toStockType; // 2A
                // Type 3
//                $subSTockTypeArray[$value][$toStockType][$toStockType.'_Type'] = $toStockType;
//                $SLabel = getAbbrSLabel($toStockType);
//                $subSTockTypeArray[$value][$toStockType][$toStockType.'_Label'] = $SLabel ? $SLabel : $toStockType;
            }
        }
    } return $subSTockTypeArray;
}

/**
 * FRPRDINXT-13116
 * 
 * @param type $StockType
 * @return string
 */
function getAbbrSLabel($StockType) {

    if ($StockType == "S") {
        return "Salable";
    }

    if ($StockType == "SF") {
        return "Salable Free";
    }

    if ($StockType == "D") {
        return "Damaged";
    }

    if ($StockType == "DF") {
        return "Damaged Free";
    }
}


/*******PaymentDetail activation flow********March 12 2018************/


function activation_workflow($module, $moduleid, $sku, $skucode, $quantity,$paymentId){
	global $adb;
	
	$statupdate	=  "update vtiger_paymentdetail set activation_flag=1 where paymentdetailid=".$paymentId." AND module_name='".$module."' and module_id=$moduleid and sku=$sku and skucode='".$skucode."'";
	$adb->pquery($statupdate);
	
	 $selQuery	=  "SELECT * from vtiger_paymentsku where paymentskuid=$sku and sku_code='".$skucode."'";
	 
	 $result = $adb->pquery($selQuery);
	 $activationflag= $adb->query_result($result,0,'activation_workflow');  
	 
	 switch($activationflag){
		case 'DMS User Activation':
			DmsUserActivation($module, $moduleid, $sku, $skucode, $quantity,$paymentId);
			break;
		case "Distributor Salesman Activation" :
			DistributorSalesmanActivation($module, $moduleid, $sku, $skucode, $quantity,$paymentId);
			break;
		default:
			
	}
		
	}
	
/*******PaymentDetail deactivation flow********March 12 2018************/
	
function deactivation_workflow($module, $moduleid, $sku, $skucode, $quantity,$paymentId){
	global $adb;

	$statupdate	=  "update vtiger_paymentdetail set deactivation_flag=1 where paymentdetailid=".$paymentId." AND module_name='".$module."' and module_id=$moduleid and sku=$sku and skucode='".$skucode."'";
	$adb->pquery($statupdate);
	$selQuery	=  "SELECT * from vtiger_paymentsku where paymentskuid=$sku and sku_code='".$skucode."'";
	 
	 $result = $adb->pquery($selQuery);
	 $deactivationflag= $adb->query_result($result,0,'deactivation_workflow'); 
	 switch($deactivationflag){
		case "DMS User De-Activation" :
			DmsUserDeactivation($module, $moduleid, $sku, $skucode, $quantity,$paymentId);
			break;
		case "Distributor Salesman De-Activation" :
			DistributorSalesmanDeActivation($module, $moduleid, $sku, $skucode, $quantity,$paymentId);
			break;
		default:
		
	}
	}
	
/**********13 March 2018***********/
function DmsUserActivation($module, $moduleid, $sku, $skucode, $quantity,$paymentId){ 
	global $adb;
	
	$query = "select cf_xdistributorusermapping_supporting_staff from vtiger_xdistributorusermappingcf where cf_xdistributorusermapping_distributor='".$moduleid."'"; 
	$result = $adb->pquery($query);
	
	if($adb->num_rows($result)!=0){
		for ($index = 0; $index <$quantity; $index++) {
			$dat= $adb->raw_query_result_rowdata($result,$index);
			$dmsuser = $dat['cf_xdistributorusermapping_supporting_staff'];
			$adb->pquery("UPDATE vtiger_users set status = 'Active' where id=$dmsuser"); 
		}
		$statupdate	=  "update vtiger_paymentdetail set activation_flag=2, modified_at=NOW() where paymentdetailid=".$paymentId." AND module_name='".$module."' and module_id=$moduleid and sku=$sku and skucode='".$skucode."'";
		$adb->pquery($statupdate);
	}
}

function DmsUserDeActivation($module, $moduleid, $sku, $skucode, $quantity,$paymentId){ 
	global $adb;
	
	$query = "select cf_xdistributorusermapping_supporting_staff from vtiger_xdistributorusermappingcf where cf_xdistributorusermapping_distributor='".$moduleid."'"; 
	$result = $adb->pquery($query);
	if($adb->num_rows($result)!=0){
		for ($index = 0; $index <$quantity; $index++) {
			$dat= $adb->raw_query_result_rowdata($result,$index);
			$dmsuser = $dat['cf_xdistributorusermapping_supporting_staff'];
			$adb->pquery("UPDATE vtiger_users set status = 'In-Active' where id=$dmsuser"); 
		}
		$statupdate	=  "update vtiger_paymentdetail set deactivation_flag=2, modified_at=NOW() where paymentdetailid=".$paymentId." AND module_name='".$module."' and module_id=$moduleid and sku=$sku and skucode='".$skucode."'";
		$adb->pquery($statupdate);
	}
}

function DistributorSalesmanActivation($module, $moduleid, $sku, $skucode, $quantity,$paymentId){ 
	global $adb;
	$removal='';
	
	$removeRetailer=$adb->pquery('select group_concat(cf_xcdkeydistmapping_salesman) as retailer from vtiger_xcdkeydistmapping JOIN vtiger_crmentity on vtiger_crmentity.crmid=vtiger_xcdkeydistmapping.xcdkeydistmappingid Join vtiger_xcdkeydistmappingcf on vtiger_xcdkeydistmappingcf.xcdkeydistmappingid=vtiger_xcdkeydistmapping.xcdkeydistmappingid AND (vtiger_xcdkeydistmappingcf.cf_xcdkeydistmapping_salesman !=NULL OR vtiger_xcdkeydistmappingcf.cf_xcdkeydistmapping_salesman !="") where vtiger_crmentity.deleted=0 and vtiger_xcdkeydistmapping.distributor='.$moduleid);
		
	$RemooveRetailer=$adb->query_result($removeRetailer,0,retailer);         
	if($RemooveRetailer!='' || $RemooveRetailer!=null)
		$removal=" AND vtiger_xsalesman.xsalesmanid not in ($RemooveRetailer)";
	
	$salesmanquery = $adb->pquery("SELECT vtiger_crmentity.*, vtiger_xsalesman.*, vtiger_xsalesmancf.* FROM vtiger_xsalesman INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xsalesman.xsalesmanid INNER JOIN vtiger_xsalesmancf ON vtiger_xsalesmancf.xsalesmanid = vtiger_xsalesman.xsalesmanid LEFT JOIN vtiger_users ON vtiger_users.id = vtiger_crmentity.smownerid LEFT JOIN vtiger_groups ON vtiger_groups.groupid = vtiger_crmentity.smownerid LEFT JOIN vtiger_xsalesmangpmapping ON vtiger_xsalesmangpmapping.xsalesmangpmappingid = vtiger_xsalesmancf.cf_xsalesman_salesman_category_group LEFT JOIN vtiger_xcreditterm ON vtiger_xcreditterm.xcredittermid = vtiger_xsalesmancf.cf_xsalesman_creditdays WHERE vtiger_xsalesman.xsalesmanid > 0 AND vtiger_crmentity.deleted = 0 AND vtiger_xsalesmancf.cf_xsalesman_active=1 AND vtiger_xsalesmancf.cf_xsalesman_distributor = $moduleid.$removal"); 
	
		
	for ($index = 0; $index <$quantity; $index++) {
		$salesmanlist = $adb->raw_query_result_rowdata($salesmanquery,$index);
		
		$salesman = $salesmanlist['salesman'];
		$salesmanid = $salesmanlist['xsalesmanid']; 
		
		$cdkeyquery = $adb->pquery("SELECT vtiger_crmentity.*, vtiger_xcdkey.*, vtiger_xcdkeycf.* FROM vtiger_xcdkey INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xcdkey.xcdkeyid INNER JOIN vtiger_xcdkeycf ON vtiger_xcdkeycf.xcdkeyid = vtiger_xcdkey.xcdkeyid LEFT JOIN vtiger_users ON vtiger_users.id = vtiger_crmentity.smownerid LEFT JOIN vtiger_groups ON vtiger_groups.groupid = vtiger_crmentity.smownerid WHERE vtiger_xcdkey.xcdkeyid > 0 AND vtiger_crmentity.deleted = 0 AND vtiger_crmentity.crmid not in (SELECT cdkey FROM vtiger_xcdkeydistmapping left join vtiger_crmentity on vtiger_xcdkeydistmapping.xcdkeydistmappingid=vtiger_crmentity.crmid WHERE vtiger_crmentity.deleted=0 ) AND vtiger_xcdkeycf.cf_xcdkey_status=1");
		
		$cdkeynumrows = $adb->num_rows($cdkeyquery);  
		
		if($cdkeynumrows>0){  
		
			$cdkey = $adb->query_result($cdkeyquery,0,'cf_xcdkey_cd_key_field1'); 
			
			$cdkeyid = $adb->query_result($cdkeyquery,0,'xcdkeyid');
			
			
		}else{
			
			$orgCode='';
	 
			$orgResult=$adb->pquery("SELECT organizationcode FROM vtiger_organizationdetails LIMIT 0,1");
			if($adb->num_rows($orgResult)>0)
				$orgCode=$adb->query_result($orgResult,0,'organizationcode');
			
			$crmqueryKey = $adb->pquery("SELECT id+1 as id FROM vtiger_crmentity_seq limit 1");
		
			$crmentityKey = $adb->query_result($crmqueryKey,0,'id'); 
			
			$dIqnu=edoCesneciLetareneg('1');
			
			$hsaHyranib=yeKeuqinUhsah('1',$orgCode);
			
			$keysql = "SELECT * FROM vtiger_xcdkey";
			
			$keysqlres = $adb->pquery($keysql);
			
			$keycount = $adb->num_rows($keysqlres)+1; 
			
			$cdkey = Mkeygen(25); // Manual Key generation 
			
			$cdkeyid = $crmentityKey;
			
			$adb->pquery("UPDATE vtiger_crmentity_seq SET id=$crmentityKey");
			
			$adb->pquery("DELETE FROM sify_esnecil");
			
			$adb->pquery("INSERT INTO vtiger_crmentity(crmid, smcreatorid, smownerid, setype, setype_id, createdtime, modifiedtime, version, presence, deleted, sendstatus)VALUES($crmentityKey, 0, 0, 'xCdkey', 98, NOW(), NOW(),0, 1, 0, 0)");
			 
			$adb->pquery("INSERT INTO sify_esnecil(id,diynapmoc,yekeuqinu,hsahyranib,resudetaerc,etad_detaerc,etad_deifidom,evitca) VALUES(NULL,?,?,?,?,NOW(),NOW(),1)",array($orgCode,$dIqnu,$hsaHyranib,'1'));
			
			$adb->pquery("INSERT INTO vtiger_xcdkey(xcdkeyid, cdkeycode)VALUES($cdkeyid, 'CD".$keycount."')");
			
			$adb->pquery("INSERT INTO vtiger_xcdkeycf(xcdkeyid, cf_xcdkey_cd_key_field1, cf_xcdkey_status)VALUES($cdkeyid, '".$cdkey."', 1)");
			
			
		}
		
		
		$crmquery = $adb->pquery("SELECT id+1 as id FROM vtiger_crmentity_seq limit 1");
		
		$crmentity = $adb->query_result($crmquery,0,'id'); 
		
	
		$adb->pquery("UPDATE vtiger_crmentity_seq SET id=$crmentity");
	
		$adb->pquery("INSERT INTO vtiger_crmentityrel(crmid,module,relcrmid,relmodule)VALUES($moduleid, '".$module."', $crmentity, 'xCdkeyDistMapping')");
	
		$adb->pquery("INSERT INTO vtiger_crmentity(crmid, smcreatorid, smownerid, setype, setype_id, createdtime, modifiedtime, version, presence, deleted, sendstatus)VALUES($crmentity, 1, 0, 'xCdkeyDistMapping', 99, NOW(), NOW(),0, 1, 0, 0)");
		
		$adb->pquery("INSERT INTO vtiger_xcdkeydistmappingcf(xcdkeydistmappingid, cf_xcdkeydistmapping_data_mapping_required, cf_xcdkeydistmapping_integration_type, cf_xcdkeydistmapping_salesman, created_at, modified_at, deleted)VALUES($crmentity, 0, 'mobile', $salesmanid, NOW(), NOW(), 0)");
		
		$adb->pquery("INSERT INTO vtiger_xcdkeydistmapping (xcdkeydistmappingid, distributor, cdkey, created_at, modified_at, deleted)VALUES($crmentity, $moduleid, $cdkeyid, NOW(), NOW(), 0)");
		
		$adb->pquery("INSERT INTO vtiger_xdistdeviceregistration (distributorid,salesmanid,clientid,cdkey,deviceuniquekey,client_version) SELECT DI.distributorcode,SM.salesmancode,(SELECT organizationcode FROM vtiger_organizationdetails LIMIT 1),CDKCF.cf_xcdkey_cd_key_field1,'','' FROM vtiger_xcdkeydistmapping CD INNER JOIN vtiger_xcdkeydistmappingcf CDCF ON CDCF.xcdkeydistmappingid = CD.xcdkeydistmappingid INNER JOIN vtiger_xdistributor DI ON DI.xdistributorid = CD.distributor INNER JOIN vtiger_xcdkeycf CDKCF ON CDKCF.xcdkeyid = CD.cdkey LEFT JOIN vtiger_xsalesman SM ON SM.xsalesmanid = CDCF.cf_xcdkeydistmapping_salesman WHERE CDCF.xcdkeydistmappingid = $crmentity ");
		
	}
	$statupdate	=  "update vtiger_paymentdetail set activation_flag=2, modified_at=NOW() where paymentdetailid=".$paymentId." AND module_name='".$module."' and module_id=$moduleid and sku=$sku and skucode='".$skucode."'";
	$adb->pquery($statupdate);
	
	
}


function DistributorSalesmanDeActivation($module, $moduleid, $sku, $skucode, $quantity,$paymentId){ 
global $adb;
	$removal='';
	
	$removeRetailer=$adb->pquery('select group_concat(cf_xcdkeydistmapping_salesman) as retailer from vtiger_xcdkeydistmapping JOIN vtiger_crmentity on vtiger_crmentity.crmid=vtiger_xcdkeydistmapping.xcdkeydistmappingid Join vtiger_xcdkeydistmappingcf on vtiger_xcdkeydistmappingcf.xcdkeydistmappingid=vtiger_xcdkeydistmapping.xcdkeydistmappingid AND (vtiger_xcdkeydistmappingcf.cf_xcdkeydistmapping_salesman !=NULL OR vtiger_xcdkeydistmappingcf.cf_xcdkeydistmapping_salesman !="") where vtiger_crmentity.deleted=0 and vtiger_xcdkeydistmapping.distributor='.$moduleid);
	
	$RemooveRetailer=$adb->query_result($removeRetailer,0,retailer);         
	if($RemooveRetailer!='' || $RemooveRetailer!=null)
		$removal=" AND vtiger_xsalesman.xsalesmanid in ($RemooveRetailer)";
	
	$salesmanquery = $adb->pquery("SELECT vtiger_crmentity.*, vtiger_xsalesman.*, vtiger_xsalesmancf.* FROM vtiger_xsalesman INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xsalesman.xsalesmanid INNER JOIN vtiger_xsalesmancf ON vtiger_xsalesmancf.xsalesmanid = vtiger_xsalesman.xsalesmanid LEFT JOIN vtiger_users ON vtiger_users.id = vtiger_crmentity.smownerid LEFT JOIN vtiger_groups ON vtiger_groups.groupid = vtiger_crmentity.smownerid LEFT JOIN vtiger_xsalesmangpmapping ON vtiger_xsalesmangpmapping.xsalesmangpmappingid = vtiger_xsalesmancf.cf_xsalesman_salesman_category_group LEFT JOIN vtiger_xcreditterm ON vtiger_xcreditterm.xcredittermid = vtiger_xsalesmancf.cf_xsalesman_creditdays WHERE vtiger_xsalesman.xsalesmanid > 0 AND vtiger_crmentity.deleted = 0 AND vtiger_xsalesmancf.cf_xsalesman_active=1 AND vtiger_xsalesmancf.cf_xsalesman_distributor = $moduleid.$removal"); 
	for ($index = 0; $index <$quantity; $index++) {
		$salesmanlist = $adb->raw_query_result_rowdata($salesmanquery,$index);
		$salesmanid = $salesmanlist['xsalesmanid']; 
		$salesmamcode = $salesmanlist['salesmancode']; 
		
		$mappingcf = $adb->pquery("SELECT * FROM vtiger_xcdkeydistmappingcf WHERE cf_xcdkeydistmapping_salesman=$salesmanid AND deleted=0");
		$mappingsalesmen = $adb->query_result($mappingcf,0,'xcdkeydistmappingid');
		
		$adb->pquery("UPDATE vtiger_crmentity set deleted=1 WHERE crmid=$mappingsalesmen");
		
		$adb->pquery("UPDATE vtiger_xcdkeydistmapping set deleted=1 WHERE xcdkeydistmappingid=$mappingsalesmen");
		
		$adb->pquery("UPDATE vtiger_xcdkeydistmappingcf set deleted=1, modified_at=NOW() WHERE xcdkeydistmappingid=$mappingsalesmen AND cf_xcdkeydistmapping_salesman=$salesmanid");
		
		$adb->pquery("DELETE FROM vtiger_xdistdeviceregistration WHERE salesmanid='".$salesmamcode."'");
		
	}
	$statupdate	=  "update vtiger_paymentdetail set deactivation_flag=2, modified_at=NOW() where paymentdetailid=".$paymentId." AND module_name='".$module."' and module_id=$moduleid and sku=$sku and skucode='".$skucode."'";
	$adb->pquery($statupdate);
	
}

function Mkeygen($length=10)
{
	$key = '';
	list($usec, $sec) = explode(' ', microtime());
	mt_srand((float) $sec + ((float) $usec * 100000));
	
   	//$inputs = array_merge(range('z','a'),range(0,9),range('A','Z'));
   	$inputs = array_merge(range('Z','A'),range(0,9),range('A','Z'));

   	for($i=0; $i<$length; $i++)
	{
   	    if($i%5==0 && $i>0)
                $key .="-";
            
            $key .= $inputs{mt_rand(0,61)};
	}
	return $key;
}	

// Workflow function 20-03-2018 

function deactivateMod($modName, $transId){
	global $adb,$Arr_Parent;
	$module='';
	//echo $modName; exit;
	if($modName == 'Product'){
		$adb->pquery("UPDATE vtiger_xproductcf SET cf_xproduct_active=0 WHERE xproductid=$transId");
	}elseif($modName == 'Tax'){ 
		$adb->pquery("UPDATE vtiger_xtaxcf SET cf_xtax_active=0 WHERE xtaxid=$transId");
	}elseif($modName== 'xRetailer'){
		$adb->pquery("UPDATE vtiger_xretailercf SET cf_xretailer_active=0 WHERE xretailerid=$transId");
	}elseif($modName== 'Distributor Cluster'){
		$adb->pquery("UPDATE vtiger_xdistributorcluster SET active=0 WHERE 	xdistributorclusterid=$transId");
	}elseif($modName== 'distributor'){
		$adb->pquery("UPDATE vtiger_xdistributorcf SET cf_xdistributor_active=0 WHERE 	xdistributorid=$transId");
	}
        
	if($modName == 'Product'){
		$module = 'xProduct';
	}elseif($modName == 'Tax'){
		$module = 'xTax';
	}elseif($modName == 'distributor'){
		$module = 'xDistributor';
	}else{
		$module =$modName;
	}
	
	$adb->pquery("CALL prc_createat_updateat_data(?,?,?)",array($module,$transId,2));
	$adb->pquery("UPDATE vtiger_crmentity SET modifiedtime=NOW() where crmid=$transId");
	
}

function activateMod($modName, $transId){
	global $adb,$Arr_Parent;
	$module='';
	if($modName == 'Product'){
		$adb->pquery("UPDATE vtiger_xproductcf SET cf_xproduct_active=1 WHERE xproductid=$transId");
	}elseif($modName == 'Tax'){
		$adb->pquery("UPDATE vtiger_xtaxcf SET cf_xtax_active=1 WHERE xtaxid=$transId");
	}elseif($modName== 'xRetailer'){
		$adb->pquery("UPDATE vtiger_xretailercf SET cf_xretailer_active=1 WHERE xretailerid=$transId");
	}elseif($modName== 'Distributor Cluster'){
		$adb->pquery("UPDATE vtiger_xdistributorcluster SET active=1 WHERE 	xdistributorclusterid=$transId");
	}elseif($modName== 'distributor'){
		$adb->pquery("UPDATE vtiger_xdistributorcf SET cf_xdistributor_active=1 WHERE 	xdistributorid=$transId");
	}
	if($modName == 'Product'){
		$module = 'xProduct';
	}elseif($modName == 'Tax'){
		$module = 'xTax';
	}elseif($modName == 'distributor'){
		$module = 'xDistributor';
	}else{
		$module =$modName;
	}
	
	$adb->pquery("CALL prc_createat_updateat_data(?,?,?)",array($module,$transId,2));
	$adb->pquery("UPDATE vtiger_crmentity SET modifiedtime=NOW() where crmid=$transId");
	
}

// Business logic for Distributor CP User Mapping

function distCpUserMapping($transId){ 
	global $adb,$Arr_Parent;
	
	$report_to ="SELECT user_reports_to_id FROM vtiger_xdistributor WHERE xdistributorid=$transId";
	$result = $adb->pquery($report_to);
	$report_to_id= $adb->query_result($result,0,'user_reports_to_id'); 
	$adb->pquery("CALL prc_cpdpmapping($transId, $report_to_id)"); 
}

function allowEditTranaction($currentModule,$recordid,$Record_Dist_ID,$next_stage_name){
    global $adb;
    if(!empty($recordid)){
        $buyer          = getDistrIDbyUserID();
        $Buyer_ID       = $Record_Dist_ID;
        $Dist_ID        = $buyer['id'];
        $AllowUser      = 0;
        if($Dist_ID ==$Buyer_ID){
            $AllowUser = 1;
        }else{
            $result         = $adb->pquery("SELECT xcpcf.cf_xcpdpmapping_distributor as distid
                                FROM vtiger_xcpdpmapping xcp
                                INNER JOIN vtiger_xcpdpmappingcf xcpcf ON xcpcf.xcpdpmappingid = xcp.xcpdpmappingid
                                INNER JOIN vtiger_crmentity crm ON crm.crmid = xcp.xcpdpmappingid
                                INNER JOIN vtiger_users users ON users.id = xcp.cpusers
                                WHERE users.id=? AND crm.deleted=0 ",array($_SESSION["authenticated_user_id"]));
            
            for ($index = 0; $index < $adb->num_rows($result); $index++) {
                $ret[] = $adb->query_result($result,$index,'distid');
            }  

            if (!empty($ret)) {
                
               if (in_array($Buyer_ID, $ret)){
                   $AllowUser = 1;
               }
            }
        }
        
        if($AllowUser==0){
            return 0;
        }
        
        if($AllowUser ==1){
            require_once('include/WorkflowBase.php');
            $workflow   = workflow($currentModule,$next_stage_name);
            $Allow_Edit = 0;
            foreach ($workflow as $wf) {

                $label  = $wf['cf_workflowstage_possible_action'];
                if ($wf['cf_workflowstage_next_content_status'] != "") {
                    if (strpos($wf['cf_workflowstage_user_role'], "|##|") != '') {
                        $sepr = ' |##| ';
                    } else {
                        $sepr = ',';
                    }

                    $wfUsrRoleArr = explode($sepr, trim($wf['cf_workflowstage_user_role']));

                    if (in_array($current_user_role_name, $wfUsrRoleArr) && strtolower(trim($label))=='edit') {
                        $Allow_Edit = 1;
                    }
                }
            }
            if($Allow_Edit==0 && isPermitted($currentModule,"EditView",$recordid)=='no'){
                return 0;
            }
        }
        return 1;
    }
}
function beatRULog($beat=0,$distID=0,$transID=0){
	if($beat==0 || $distID==0 || $transID==0){return false;}
    global $adb;
    $ruQry = "SELECT IFNULL(COUNT(DISTINCT xret.xretailerid),0) as retailer_count,xs.xsalesmanid from vtiger_xbeat beat LEFT JOIN vtiger_crmentityrel xrcmrel on xrcmrel.relcrmid=beat.xbeatid LEFT JOIN vtiger_xretailer xret on xret.xretailerid=xrcmrel.crmid LEFT JOIN vtiger_xretailercf xretcf on xretcf.xretailerid=xret.xretailerid LEFT JOIN vtiger_crmentityrel xrcmrel2 on xrcmrel2.relcrmid=beat.xbeatid LEFT JOIN vtiger_xsalesman xs on xs.xsalesmanid=xrcmrel2.crmid LEFT JOIN vtiger_xsalesmancf xscf on xscf.xsalesmanid=xs.xsalesmanid WHERE beat.xbeatid=".$beat." AND xret.deleted=0 AND xretcf.cf_xretailer_active=1 AND xretcf.deleted=0 AND xs.deleted=0 AND xscf.cf_xsalesman_active=1 AND xscf.deleted=0";
    $ruRes = $adb->pquery($ruQry);
    $retCount = $adb->query_result($ruRes,0,'retailer_count');
    $salesman = $adb->query_result($ruRes,0,'xsalesmanid');
    $dayWiseBeatDataQry = "INSERT INTO sify_daywisebeat_data (`crmid`,`xdistributorid`,`xsalesmanid`,`xbeatid`,`retailer_count`,`date_on`,`latest`,`created_at`,`modified_at`,`deleted`) VALUES (?,?,?,?,?,NOW(),'1',NOW(),NOW(),0)";
    $adb->pquery($dayWiseBeatDataQry,array($transID,$distID,$salesman,$beat,$retCount));
    $id = $adb->getLastInsertID();
    $updateQry = "UPDATE sify_daywisebeat_data AS M INNER JOIN sify_daywisebeat_data AS S ON DATE_FORMAT(S.date_on, '%Y-%m')=DATE_FORMAT(M.date_on, '%Y-%m') AND S.id=? SET M.latest='0' WHERE M.id!=? AND M.xbeatid=?";
    $adb->pquery($updateQry,array($id,$id,$beat));
	return true;
}
?>