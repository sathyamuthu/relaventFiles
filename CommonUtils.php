<?php
/*********************************************************************************
 * The contents of this file are subject to the SugarCRM Public License Version 1.1.2
 * ("License"); You may not use this file except in compliance with the
 * License. You may obtain a copy of the License at http://www.sugarcrm.com/SPL
 * Software distributed under the License is distributed on an  "AS IS"  basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License for
 * the specific language governing rights and limitations under the License.
 * The Original Code is:  SugarCRM Open Source
 * The Initial Developer of the Original Code is SugarCRM, Inc.
 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc.;
 * All Rights Reserved.
 * Contributor(s): ______________________________________.
 ********************************************************************************/
/*********************************************************************************
 * $Header$
 * Description:  Includes generic helper functions used throughout the application.
 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc.
 * All Rights Reserved.
 * Contributor(s): ______________________________________..
 ********************************************************************************/

  require_once('include/utils/utils.php'); //new
  require_once('include/utils/RecurringType.php');
  require_once('include/utils/EmailTemplate.php');
  require_once 'include/QueryGenerator/QueryGenerator.php';
  require_once 'include/ListView/ListViewController.php';
  require_once('include/common.php');
  require_once('config.all.php');

array_map("htmlspecialchars",$_REQUEST);    
  
/**
 * Check if user id belongs to a system admin.
 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc.
 * All Rights Reserved.
 * Contributor(s): ______________________________________..
 */
function is_admin($user) {
	global $log;
	$log->debug("Entering is_admin(".$user->user_name.") method ...");
	
	if ($user->is_admin == 'on')
	{
		$log->debug("Exiting is_admin method ..."); 
		return true;
	}
	else
	{
		$log->debug("Exiting is_admin method ...");
		 return false;
	}
}

/**
 * THIS FUNCTION IS DEPRECATED AND SHOULD NOT BE USED; USE get_select_options_with_id()
 * Create HTML to display select options in a dropdown list.  To be used inside
 * of a select statement in a form.
 * param $option_list - the array of strings to that contains the option list
 * param $selected - the string which contains the default value
 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc.
 * All Rights Reserved.
 * Contributor(s): ______________________________________..
 */
function get_select_options (&$option_list, $selected, $advsearch='false') {
	global $log;
	$log->debug("Entering get_select_options (".$option_list.",".$selected.",".$advsearch.") method ...");
	$log->debug("Exiting get_select_options  method ...");
	return get_select_options_with_id($option_list, $selected, $advsearch);
}

/**
 * Create HTML to display select options in a dropdown list.  To be used inside
 * of a select statement in a form.   This method expects the option list to have keys and values.  The keys are the ids.  The values is an array of the datas 
 * param $option_list - the array of strings to that contains the option list
 * param $selected - the string which contains the default value
 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc.
 * All Rights Reserved.
 * Contributor(s): ______________________________________..
 */
function get_select_options_with_id (&$option_list, $selected_key, $advsearch='false') {
	global $log;
	$log->debug("Entering get_select_options_with_id (".$option_list.",".$selected_key.",".$advsearch.") method ...");
	$log->debug("Exiting get_select_options_with_id  method ...");
	return get_select_options_with_id_separate_key($option_list, $option_list, $selected_key, $advsearch);
}
function get_select_options_with_value (&$option_list, $selected_key, $advsearch='false') {
	global $log;
	$log->debug("Entering get_select_options_with_id (".$option_list.",".$selected_key.",".$advsearch.") method ...");
	$log->debug("Exiting get_select_options_with_id  method ...");
	return get_select_options_with_value_separate_key($option_list, $option_list, $selected_key, $advsearch);
}
/**
 * Create HTML to display select options in a dropdown list.  To be used inside
 * of a select statement in a form.   This method expects the option list to have keys and values.  The keys are the ids.
 * The values are the display strings.
 */
function get_select_options_array (&$option_list, $selected_key, $advsearch='false') {
	global $log;
	$log->debug("Entering get_select_options_array (".$option_list.",".$selected_key.",".$advsearch.") method ...");
	$log->debug("Exiting get_select_options_array  method ...");
        return get_options_array_seperate_key($option_list, $option_list, $selected_key, $advsearch);
}

/**
 * Create HTML to display select options in a dropdown list.  To be used inside
 * of a select statement in a form.   This method expects the option list to have keys and values.  The keys are the ids.  The value is an array of data
 * param $label_list - the array of strings to that contains the option list
 * param $key_list - the array of strings to that contains the values list
 * param $selected - the string which contains the default value
 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc.
 * All Rights Reserved.
 * Contributor(s): ______________________________________..
 */
function get_options_array_seperate_key (&$label_list, &$key_list, $selected_key, $advsearch='false') {
	global $log;
	$log->debug("Entering get_options_array_seperate_key (".$label_list.",".$key_list.",".$selected_key.",".$advsearch.") method ...");
	global $app_strings;
	if($advsearch=='true')
	$select_options = "\n<OPTION value=''>--NA--</OPTION>";
	else
	$select_options = "";

	//for setting null selection values to human readable --None--
	$pattern = "/'0?'></";
	$replacement = "''>".$app_strings['LBL_NONE']."<";
	if (!is_array($selected_key)) $selected_key = array($selected_key);

	//create the type dropdown domain and set the selected value if $opp value already exists
	foreach ($key_list as $option_key=>$option_value) {
		$selected_string = '';
		// the system is evaluating $selected_key == 0 || '' to true.  Be very careful when changing this.  Test all cases.
		// The vtiger_reported bug was only happening with one of the vtiger_users in the drop down.  It was being replaced by none.
		if (($option_key != '' && $selected_key == $option_key) || ($selected_key == '' && $option_key == '') || (in_array($option_key, $selected_key)))
		{
			$selected_string = 'selected';
		}

		$html_value = $option_key;

		$select_options .= "\n<OPTION ".$selected_string."value='$html_value'>$label_list[$option_key]</OPTION>";
		$options[$html_value]=array($label_list[$option_key]=>$selected_string);
	}
	$select_options = preg_replace($pattern, $replacement, $select_options);

	$log->debug("Exiting get_options_array_seperate_key  method ...");
	return $options;
}

/**
 * Create HTML to display select options in a dropdown list.  To be used inside
 * of a select statement in a form.   This method expects the option list to have keys and values.  The keys are the ids.
 * The values are the display strings.
 */

function get_select_options_with_id_separate_key(&$label_list, &$key_list, $selected_key, $advsearch='false')
{
	global $log;
    $log->debug("Entering get_select_options_with_id_separate_key(".$label_list.",".$key_list.",".$selected_key.",".$advsearch.") method ...");
    global $app_strings;
    if($advsearch=='true')
    $select_options = "\n<OPTION value=''>--NA--</OPTION>";
    else
    $select_options = "";

    $pattern = "/'0?'></";
    $replacement = "''>".$app_strings['LBL_NONE']."<";
    if (!is_array($selected_key)) $selected_key = array($selected_key);

    foreach ($key_list as $option_key=>$option_value) {
        $selected_string = '';
        if (($option_key != '' && $selected_key == $option_key) || ($selected_key == '' && $option_key == '') || (in_array($option_key, $selected_key)))
        {
            $selected_string = 'selected ';
        }

        $html_value = $option_key;

        $select_options .= "\n<OPTION ".$selected_string."value='$html_value'>$label_list[$option_key]</OPTION>";
    }
    $select_options = preg_replace($pattern, $replacement, $select_options);
    $log->debug("Exiting get_select_options_with_id_separate_key method ...");
    return $select_options;

}

function get_select_options_with_value_separate_key(&$label_list, &$key_list, $selected_key, $advsearch='false')
{
	global $log;
    $log->debug("Entering get_select_options_with_id_separate_key(".$label_list.",".$key_list.",".$selected_key.",".$advsearch.") method ...");
    global $app_strings;
    if($advsearch=='true')
    $select_options = "\n<OPTION value=''>--NA--</OPTION>";
    else
    $select_options = "";

    $pattern = "/'0?'></";
    $replacement = "''>".$app_strings['LBL_NONE']."<";
    if (!is_array($selected_key)) $selected_key = array($selected_key);

    foreach ($key_list as $option_key=>$option_value) {
        $selected_string = '';
        if (($option_key != '' && $selected_key == $option_key) || ($selected_key == '' && $option_key == '') || (in_array($option_key, $selected_key)))
        {
            $selected_string = 'selected ';
        }

        $html_value = $option_key;

        $select_options .= "\n<OPTION ".$selected_string."value='$label_list[$option_key]'>$label_list[$option_key]</OPTION>";
    }
    $select_options = preg_replace($pattern, $replacement, $select_options);
    $log->debug("Exiting get_select_options_with_id_separate_key method ...");
    return $select_options;

}

/**
 * Converts localized date format string to jscalendar format
 * Example: $array = array_csort($array,'town','age',SORT_DESC,'name');
 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc.
 * All Rights Reserved.
 * Contributor(s): ______________________________________..
 */
function parse_calendardate($local_format) {
	global $log;
	$log->debug("Entering parse_calendardate(".$local_format.") method ...");
	global $current_user;
	if($current_user->date_format == 'dd-mm-yyyy')
	{
		$dt_popup_fmt = "%d-%m-%Y";
	}
	elseif($current_user->date_format == 'mm-dd-yyyy')
	{
		$dt_popup_fmt = "%m-%d-%Y";
	}
	elseif($current_user->date_format == 'yyyy-mm-dd')
	{
		$dt_popup_fmt = "%Y-%m-%d";
	}
	$log->debug("Exiting parse_calendardate method ...");
	return $dt_popup_fmt;
}

/**
 * Decodes the given set of special character 
 * input values $string - string to be converted, $encode - flag to decode
 * returns the decoded value in string fromat
 */

function from_html($string, $encode=true){
	global $log;
	//$log->debug("Entering from_html(".$string.",".$encode.") method ...");
        global $toHtml;
        //if($encode && is_string($string))$string = html_entity_decode($string, ENT_QUOTES);
	if(is_string($string)){
		if(preg_match('/(script).*(\/script)/i',$string))
			$string=preg_replace(array('/</', '/>/', '/"/'), array('&lt;', '&gt;', '&quot;'), $string);
		//$string = str_replace(array_values($toHtml), array_keys($toHtml), $string);
	}
	//$log->debug("Exiting from_html method ...");
        return $string;
}

function fck_from_html($string)
{
	if(is_string($string)){
		if(preg_match('/(script).*(\/script)/i',$string))
			$string=str_replace('script', '', $string);
	}
	return $string;
}

/**
 *	Function used to decodes the given single quote and double quote only. This function used for popup selection 
 *	@param string $string - string to be converted, $encode - flag to decode
 *	@return string $string - the decoded value in string fromat where as only single and double quotes will be decoded
 */

function popup_from_html($string, $encode=true)
{
	global $log;
	$log->debug("Entering popup_from_html(".$string.",".$encode.") method ...");

	$popup_toHtml = array(
        			'"' => '&quot;',
			        "'" =>  '&#039;',
			     );

        //if($encode && is_string($string))$string = html_entity_decode($string, ENT_QUOTES);
        if($encode && is_string($string))
	{
                $string = addslashes(str_replace(array_values($popup_toHtml), array_keys($popup_toHtml), $string));
        }

	$log->debug("Exiting popup_from_html method ...");
        return $string;
}


/** To get the Currency of the specified user
  * @param $id -- The user Id:: Type integer
  * @returns  vtiger_currencyid :: Type integer
 */
function fetchCurrency($id)
{
	global $log;
	$log->debug("Entering fetchCurrency(".$id.") method ...");
	
	// Lookup the information in cache
	$currencyinfo = VTCacheUtils::lookupUserCurrenyId($id);
	
	if($currencyinfo === false) {
        global $adb;
        $sql = "select currency_id from vtiger_users where id=?";
        $result = $adb->mquery($sql, array($id));
        $currencyid=  $adb->query_result($result,0,"currency_id");
        
        VTCacheUtils::updateUserCurrencyId($id, $currencyid);
        
        // Re-look at the cache for consistency
        $currencyinfo = VTCacheUtils::lookupUserCurrenyId($id);
	}
	
	$currencyid = $currencyinfo['currencyid'];
	$log->debug("Exiting fetchCurrency method ...");
	return $currencyid;
}

/** Function to get the Currency name from the vtiger_currency_info
  * @param $currencyid -- vtiger_currencyid:: Type integer
  * @returns $currencyname -- Currency Name:: Type varchar
  *
 */
function getCurrencyName($currencyid, $show_symbol=true)
{
	global $log;
	$log->debug("Entering getCurrencyName(".$currencyid.") method ...");
	
	// Look at cache first
	$currencyinfo = VTCacheUtils::lookupCurrencyInfo($currencyid);
	
	if($currencyinfo === false) {
    	global $adb;
    	$sql1 = "select * from vtiger_currency_info where id= ?";
    	$result = $adb->mquery($sql1, array($currencyid));

    	$resultinfo = $adb->fetch_array($result);
    
    	// Update cache
    	VTCacheUtils::updateCurrencyInfo($currencyid, 
    		$resultinfo['currency_name'], $resultinfo['currency_code'],
    		$resultinfo['currency_symbol'], $resultinfo['conversion_rate']  
    	);
    	
    	// Re-look at the cache now
    	$currencyinfo = VTCacheUtils::lookupCurrencyInfo($currencyid);
	}
	
	$currencyname = $currencyinfo['name'];
	$curr_symbol  = $currencyinfo['symbol'];
    	
	$log->debug("Exiting getCurrencyName method ...");
	if($show_symbol) return getTranslatedCurrencyString($currencyname).' : '.$curr_symbol;
	else return $currencyname;
	// NOTE: Without symbol the value could be used for filtering/lookup hence avoiding the translation
}


/**
 * Function to fetch the list of vtiger_groups from group vtiger_table 
 * Takes no value as input 
 * returns the query result set object
 */

function get_group_options()
{
	global $log;
	$log->debug("Entering get_group_options() method ...");
	global $adb,$noof_group_rows;;
	$sql = "select groupname,groupid from vtiger_groups";
	$result = $adb->mquery($sql, array());
	$noof_group_rows=$adb->num_rows($result);
	$log->debug("Exiting get_group_options method ...");
	return $result;
}

/**
 * Function to get the tabid 
 * Takes the input as $module - module name
 * returns the tabid, integer type
 */

function getTabid($module)
{
	global $log;
	$log->debug("Entering getTabid(".$module.") method ...");
	
	// Lookup information in cache first	
	$tabid = VTCacheUtils::lookupTabid($module);
	if($tabid === false) {
		
		if(file_exists('tabdata.php') && (filesize('tabdata.php') != 0)) {
			include('tabdata.php');
			$tabid= $tab_info_array[$module];
			
			// Update information to cache for re-use
			VTCacheUtils::updateTabidInfo($tabid, $module);
			
		} else {	
	        $log->info("module  is ".$module);
    	    global $adb;
			$sql = "select tabid from vtiger_tab where name=?";
			$result = $adb->mquery($sql, array($module));
			$tabid=  $adb->query_result($result,0,"tabid");
			
			// Update information to cache for re-use
			VTCacheUtils::updateTabidInfo($tabid, $module);
		}
	}
	
	$log->debug("Exiting getTabid method ...");
	return $tabid;

}

/**
 * Function to get the fieldid
 *
 * @param Integer $tabid
 * @param Boolean $onlyactive
 */
function getFieldid($tabid, $fieldname, $onlyactive = true) {
	global $adb;
	
	// Look up information at cache first	
	$fieldinfo = VTCacheUtils::lookupFieldInfo($tabid, $fieldname);
	if($fieldinfo === false) {
		$query  = "SELECT fieldid, fieldlabel, columnname, tablename, uitype, typeofdata, presence 
			FROM vtiger_field WHERE tabid=? AND fieldname=?";
		$result = $adb->mquery($query, array($tabid, $fieldname));
		
		if($adb->num_rows($result)) {
			
			$resultrow = $adb->fetch_array($result);
			
			// Update information to cache for re-use		
			VTCacheUtils::updateFieldInfo(
				$tabid, $fieldname,$resultrow['fieldid'],
				$resultrow['fieldlabel'], $resultrow['columnname'], $resultrow['tablename'],
				$resultrow['uitype'], $resultrow['typeofdata'], $resultrow['presence']);
			
			$fieldinfo = VTCacheUtils::lookupFieldInfo($tabid, $fieldname);
		}
	}
	
	// Get the field id based on required criteria
	$fieldid = false;
	
	if($fieldinfo) {
		$fieldid = $fieldinfo['fieldid'];
		if($onlyactive && !in_array($fieldinfo['presence'], array('0', '2'))) {
			$fieldid = false;
		}
	}
	return $fieldid;
}

/**
 * Function to get the CustomViewName
 * Takes the input as $cvid - customviewid
 * returns the cvname string fromat
 */

function getCVname($cvid)
{
        global $log;
        $log->debug("Entering getCVname method ...");

        global $adb;
        $sql = "select viewname from vtiger_customview where cvid=?";
        $result = $adb->mquery($sql, array($cvid));
        $cvname =  $adb->query_result($result,0,"viewname");

        $log->debug("Exiting getCVname method ...");
        return $cvname;

}



/**
 * Function to get the ownedby value for the specified module 
 * Takes the input as $module - module name
 * returns the tabid, integer type
 */

function getTabOwnedBy($module)
{
	global $log;
	$log->debug("Entering getTabid(".$module.") method ...");

	$tabid=getTabid($module);
	
	if (file_exists('tabdata.php') && (filesize('tabdata.php') != 0)) 
	{
		include('tabdata.php');
		$tab_ownedby= $tab_ownedby_array[$tabid];
	}
	else
	{	

        	$log->info("module  is ".$module);
        	global $adb;
		$sql = "select ownedby from vtiger_tab where name=?";
		$result = $adb->mquery($sql, array($module));
		$tab_ownedby=  $adb->query_result($result,0,"ownedby");
	}
	$log->debug("Exiting getTabid method ...");
	return $tab_ownedby;

}




/**
 * Function to get the tabid 
 * Takes the input as $module - module name
 * returns the tabid, integer type
 */

function getSalesEntityType($crmid)
{
	global $log;
	$log->debug("Entering getSalesEntityType(".$crmid.") method ...");
	$log->info("in getSalesEntityType ".$crmid);
	global $adb;
	$sql = "select setype from vtiger_crmentity where crmid=?";
        $result = $adb->mquery($sql, array($crmid));
	$parent_module='';
        if($adb->num_rows($result)>0)
        {
            $parent_module = $adb->query_result($result,0,"setype");
        }
        else
        {
            $parent_module="Users";
        }    
        
	$log->debug("Exiting getSalesEntityType method ...");
	return $parent_module;
}


function getUsersFromGroup($grpId)
{
	if($grpId != '')
	{
		$sql = "SELECT userid from vtiger_users2group where groupid in (select groupid from vtiger_groups where groupname=".$grpId.")";
		$res = $adb->mquery($sql, array());
		$email = $adb->query_result($res,0,'userid');
		return $email;
	}
	else
	{
		$adb->println("User id is empty. so return value is ''");
		return '';
	}
}


/**
 * Function to get the AccountName when a vtiger_account id is given 
 * Takes the input as $acount_id - vtiger_account id
 * returns the vtiger_account name in string format.
 */

function getAccountName($account_id)
{
	global $log;
	$log->debug("Entering getAccountName(".$account_id.") method ...");
	$log->info("in getAccountName ".$account_id);

	global $adb;
	if($account_id != ''){
		$sql = "select accountname from vtiger_account where accountid=?";
        $result = $adb->mquery($sql, array($account_id));
		$accountname = $adb->query_result($result,0,"accountname");
	}
	$log->debug("Exiting getAccountName method ...");
	return $accountname;
}

/**
 * Function to get the ProductName when a product id is given 
 * Takes the input as $product_id - product id
 * returns the product name in string format.
 */

function getProductName($product_id)
{
	global $log;
	$log->debug("Entering getProductName(".$product_id.") method ...");

	$log->info("in getproductname ".$product_id);

	global $adb;
	$sql = "select productname from vtiger_products where productid=?";
        $result = $adb->mquery($sql, array($product_id));
	$productname = $adb->query_result($result,0,"productname");
	$log->debug("Exiting getProductName method ...");
	return $productname;
}

/**
 * Function to get the Potentail Name when a vtiger_potential id is given 
 * Takes the input as $potential_id - vtiger_potential id
 * returns the vtiger_potential name in string format.
 */

function getPotentialName($potential_id)
{
	global $log;
	$log->debug("Entering getPotentialName(".$potential_id.") method ...");
	$log->info("in getPotentialName ".$potential_id);

	global $adb;
	$potentialname = '';
	if($potential_id != '')
	{
		$sql = "select potentialname from vtiger_potential where potentialid=?";
        $result = $adb->mquery($sql, array($potential_id));
		$potentialname = $adb->query_result($result,0,"potentialname");
	}
	$log->debug("Exiting getPotentialName method ...");
	return $potentialname;
}

/**
 * Function to get the Contact Name when a contact id is given 
 * Takes the input as $contact_id - contact id
 * returns the Contact Name in string format.
 */

function getContactName($contact_id)
{
	global $log;
	$log->debug("Entering getContactName(".$contact_id.") method ...");
	$log->info("in getContactName ".$contact_id);

	global $adb, $current_user;
	$contact_name = '';
	if($contact_id != '')
	{
        	$sql = "select * from vtiger_contactdetails where contactid=?";
        	$result = $adb->mquery($sql, array($contact_id));
        	$firstname = $adb->query_result($result,0,"firstname");
        	$lastname = $adb->query_result($result,0,"lastname");
        	$contact_name = $lastname;
			// Asha: Check added for ticket 4788
			if (getFieldVisibilityPermission("Contacts", $current_user->id,'firstname') == '0') {
				$contact_name .= ' '.$firstname;
			}
	}
	$log->debug("Exiting getContactName method ...");
        return $contact_name;
}

/**
 * Function to get the Contact Name when a contact id is given 
 * Takes the input as $contact_id - contact id
 * returns the Contact Name in string format.
 */

function getLeadName($lead_id)
{
	global $log;
	$log->debug("Entering getLeadName(".$lead_id.") method ...");
	$log->info("in getLeadName ".$lead_id);

    	global $adb, $current_user;
	$lead_name = '';
	if($lead_id != '')
	{
        	$sql = "select * from vtiger_leaddetails where leadid=?";
        	$result = $adb->mquery($sql, array($lead_id));
        	$firstname = $adb->query_result($result,0,"firstname");
        	$lastname = $adb->query_result($result,0,"lastname");
        	$lead_name = $lastname;
			// Asha: Check added for ticket 4788
			if (getFieldVisibilityPermission("Leads", $current_user->id,'firstname') == '0') {
				$lead_name .= ' '.$firstname;
			}
	}
	$log->debug("Exiting getLeadName method ...");
        return $lead_name;
}

/**
 * Function to get the Full Name of a Contact/Lead when a query result and the row count are given 
 * Takes the input as $result - Query Result, $row_count - Count of the Row, $module - module name
 * returns the Contact Name in string format.
 */

function getFullNameFromQResult($result, $row_count, $module)
{
	global $log, $adb, $current_user;
	$log->info("In getFullNameFromQResult(". print_r($result, true) . " - " . $row_count . "-".$module.") method ...");
    
	$rowdata = $adb->query_result_rowdata($result,$row_count);
	
	$name = '';
	if($rowdata != '' && count($rowdata) > 0)
	{
        	$firstname = $rowdata["firstname"];
        	$lastname = $rowdata["lastname"];
        	$name = $lastname;
			// Asha: Check added for ticket 4788
			if (getFieldVisibilityPermission($module, $current_user->id,'firstname') == '0') {
				$name .= ' '.$firstname;
			}
	}
	$nam = textlength_check($name);
	
	return $nam;
}

/**
 * Function to get the Campaign Name when a campaign id is given
 * Takes the input as $campaign_id - campaign id
 * returns the Campaign Name in string format.
 */

function getCampaignName($campaign_id)
{
	global $log;
	$log->debug("Entering getCampaignName(".$campaign_id.") method ...");
	$log->info("in getCampaignName ".$campaign_id);

	global $adb;
	$sql = "select * from vtiger_campaign where campaignid=?";
	$result = $adb->mquery($sql, array($campaign_id));
	$campaign_name = $adb->query_result($result,0,"campaignname");
	$log->debug("Exiting getCampaignName method ...");
	return $campaign_name;
}


/**
 * Function to get the Vendor Name when a vtiger_vendor id is given 
 * Takes the input as $vendor_id - vtiger_vendor id
 * returns the Vendor Name in string format.
 */

function getVendorName($vendor_id)
{
	global $log;
	$log->debug("Entering getVendorName(".$vendor_id.") method ...");
	$log->info("in getVendorName ".$vendor_id);
        global $adb;
        $sql = "select * from vtiger_vendor where vendorid=?";
        $result = $adb->mquery($sql, array($vendor_id));
        $vendor_name = $adb->query_result($result,0,"vendorname");
	$log->debug("Exiting getVendorName method ...");
        return $vendor_name;
}

/**
 * Function to get the Quote Name when a vtiger_vendor id is given 
 * Takes the input as $quote_id - quote id
 * returns the Quote Name in string format.
 */

function getQuoteName($quote_id)
{
	global $log;
	$log->debug("Entering getQuoteName(".$quote_id.") method ...");
	$log->info("in getQuoteName ".$quote_id);
        global $adb;
	if($quote_id != NULL && $quote_id != '')
	{
        	$sql = "select * from vtiger_quotes where quoteid=?";
        	$result = $adb->mquery($sql, array($quote_id));
        	$quote_name = $adb->query_result($result,0,"subject");
	}
	else
	{
		$log->debug("Quote Id is empty.");
		$quote_name = '';
	}
	$log->debug("Exiting getQuoteName method ...");
        return $quote_name;
}

/**
 * Function to get the PriceBook Name when a vtiger_pricebook id is given 
 * Takes the input as $pricebook_id - vtiger_pricebook id
 * returns the PriceBook Name in string format.
 */

function getPriceBookName($pricebookid)
{
	global $log;
	$log->debug("Entering getPriceBookName(".$pricebookid.") method ...");
	$log->info("in getPriceBookName ".$pricebookid);
        global $adb;
        $sql = "select * from vtiger_pricebook where pricebookid=?";
        $result = $adb->mquery($sql, array($pricebookid));
        $pricebook_name = $adb->query_result($result,0,"bookname");
	$log->debug("Exiting getPriceBookName method ...");
        return $pricebook_name;
}

/** This Function returns the  Purchase Order Name.
  * The following is the input parameter for the function
  *  $po_id --> Purchase Order Id, Type:Integer
  */
function getPoName($po_id)
{
	global $log;
	$log->debug("Entering getPoName(".$po_id.") method ...");
        $log->info("in getPoName ".$po_id);
        global $adb;
        $sql = "select * from vtiger_purchaseorder where purchaseorderid=?";
        $result = $adb->mquery($sql, array($po_id));
        $po_name = $adb->query_result($result,0,"subject");
	$log->debug("Exiting getPoName method ...");
        return $po_name;
}
/**
 * Function to get the Sales Order Name when a vtiger_salesorder id is given 
 * Takes the input as $salesorder_id - vtiger_salesorder id
 * returns the Salesorder Name in string format.
 */

function getSoName($so_id)
{
	global $log;
	$log->debug("Entering getSoName(".$so_id.") method ...");
	$log->info("in getSoName ".$so_id);
	global $adb;
        $sql = "select * from vtiger_salesorder where salesorderid=?";
        $result = $adb->mquery($sql, array($so_id));
        $so_name = $adb->query_result($result,0,"subject");
	$log->debug("Exiting getSoName method ...");
        return $so_name;
}

/**
 * Function to get the Group Information for a given groupid  
 * Takes the input $id - group id and $module - module name
 * returns the group information in an array format.
 */

function getGroupName($groupid)
{
	global $adb, $log;
	$log->debug("Entering getGroupName(".$groupid.") method ...");
	$group_info = Array();
    $log->info("in getGroupName, entityid is ".$groupid);
    if($groupid != '')
	{
		$sql = "select groupname,groupid from vtiger_groups where groupid = ?";
		$result = $adb->mquery($sql, array($groupid));
        $group_info[] = decode_html($adb->query_result($result,0,"groupname"));
        $group_info[] = $adb->query_result($result,0,"groupid");
	}
		$log->debug("Exiting getGroupName method ...");
        return $group_info;
}

/**
 * Get the username by giving the user id.   This method expects the user id
 */
     
function getUserName($userid)
{
	global $adb, $log;
	$log->debug("Entering getUserName(".$userid.") method ...");
	$log->info("in getUserName ".$userid);

	if($userid != '')
	{
		$sql = "select user_name from vtiger_users where id=?";
		$result = $adb->mquery($sql, array($userid));
		$user_name = $adb->query_result($result,0,"user_name");
	}
	$log->debug("Exiting getUserName method ...");
	return $user_name;	
}

/**
* Get the user full name by giving the user id.   This method expects the user id
* DG 30 Aug 2006
*/

function getUserFullName($userid)
{
	global $log;
	$log->debug("Entering getUserFullName(".$userid.") method ...");
	$log->info("in getUserFullName ".$userid);
	global $adb;
	if($userid != '')
	{
		$sql = "select first_name, last_name from vtiger_users where id=?";
		$result = $adb->mquery($sql, array($userid));
		$first_name = $adb->query_result($result,0,"first_name");
		$last_name = $adb->query_result($result,0,"last_name");
		$user_name = $first_name." ".$last_name;
	}
        $log->debug("Exiting getUserFullName method ...");
        return $user_name;
}

function getUserLastName($userid)
{
	global $log;
	$log->debug("Entering getUserFullName(".$userid.") method ...");
	$log->info("in getUserFullName ".$userid);
	global $adb;
	if($userid != '')
	{
		$sql = "select first_name, last_name from vtiger_users where id=?";
		$result = $adb->mquery($sql, array($userid));
		//$first_name = $adb->query_result($result,0,"first_name");
		$last_name = $adb->query_result($result,0,"last_name");
		$user_name = $last_name;
	}
        $log->debug("Exiting getUserFullName method ...");
        return $user_name;
}

function getUserFirstName($userid)
{
	global $log;
	$log->debug("Entering getUserFullName(".$userid.") method ...");
	$log->info("in getUserFullName ".$userid);
	global $adb;
	if($userid != '')
	{
		$sql = "select first_name, last_name from vtiger_users where id=?";
		$result = $adb->mquery($sql, array($userid));
		$first_name = $adb->query_result($result,0,"first_name");
                //$last_name = $adb->query_result($result,0,"last_name");
		$user_name = $first_name;
	}
        $log->debug("Exiting getUserFullName method ...");
        return $user_name;
}

/** Fucntion to get related To name with id */
function getParentName($parent_id)
{
	global $adb;
	if ($parent_id == 0)
		return "";
	$sql="select setype from vtiger_crmentity where crmid=?";
	$result=$adb->mquery($sql,array($parent_id));
	//For now i have conditions only for accounts and contacts, if needed can add more
	if($adb->query_result($result,'setype') == 'Accounts')
		$sql1="select accountname name from vtiger_account where accountid=?";
	else if($adb->query_result($result,'setype') == 'Contacts')
		$sql1="select concat( firstname, ' ', lastname ) name from vtiger_contactdetails where contactid=?";
	$result1=$adb->mquery($sql1,array($parent_id));
	$asd=$adb->query_result($result1,'name');
	return $asd;
}

/**
 * Creates and returns database query. To be used for search and other text links.   This method expects the module object.
 * param $focus - the module object contains the column vtiger_fields
 */
   
function getURLstring($focus)
{
	global $log;
	$log->debug("Entering getURLstring(".get_class($focus).") method ...");
	$qry = "";
	foreach($focus->column_fields as $fldname=>$val)
	{
		if(isset($_REQUEST[$fldname]) && $_REQUEST[$fldname] != '')
		{
			if($qry == '')
			$qry = "&".$fldname."=".vtlib_purify($_REQUEST[$fldname]);
			else
			$qry .="&".$fldname."=".vtlib_purify($_REQUEST[$fldname]);
		}
	}
	if(isset($_REQUEST['current_user_only']) && $_REQUEST['current_user_only'] !='')
	{
		$qry .="&current_user_only=".vtlib_purify($_REQUEST['current_user_only']);
	}
	if(isset($_REQUEST['advanced']) && $_REQUEST['advanced'] =='true')
	{
		$qry .="&advanced=true";
	}

	if($qry !='')
	{
		$qry .="&query=true";
	}
	$log->debug("Exiting getURLstring method ...");
	return $qry;

}

/** This function returns the date in user specified format.
  * param $cur_date_val - the default date format
 */
    
function getDisplayDate($cur_date_val)
{
	global $log;
	$log->debug("Entering getDisplayDate(".$cur_date_val.") method ...");
	global $current_user;
	$dat_fmt = $current_user->date_format;
	if($dat_fmt == '')
	{
		$dat_fmt = 'dd-mm-yyyy';
	}

		$date_value = explode(' ',$cur_date_val);
		list($y,$m,$d) = explode('-',$date_value[0]);
		if($dat_fmt == 'dd-mm-yyyy')
		{
			$display_date = $d.'-'.$m.'-'.$y;
		}
		elseif($dat_fmt == 'mm-dd-yyyy')
		{

			$display_date = $m.'-'.$d.'-'.$y;
		}
		elseif($dat_fmt == 'yyyy-mm-dd')
		{

			$display_date = $y.'-'.$m.'-'.$d;
		}

		if($date_value[1] != '')
		{
			$display_date = $display_date.' '.$date_value[1];
		}
	$log->debug("Exiting getDisplayDate method ...");
	return $display_date;
 			
}

/**
 * This function returns the date in user specified format.
 * limitation is that mm-dd-yyyy and dd-mm-yyyy will be considered same by this API.
 * As in the date value is on mm-dd-yyyy and user date format is dd-mm-yyyy then the mm-dd-yyyy
 * value will be return as the API will be considered as considered as in same format.
 * this due to the fact that this API tries to consider the where given date is in user date
 * format. we need a better gauge for this case.
 * @global Users $current_user
 * @param Date $cur_date_val the date which should a changed to user date format.
 * @return Date
 */
function getValidDisplayDate($cur_date_val) {
	global $current_user;
	$dat_fmt = $current_user->date_format;
	if($dat_fmt == '') {
		$dat_fmt = 'dd-mm-yyyy';
	}
	$date_value = explode(' ',$cur_date_val);
	list($y,$m,$d) = explode('-',$date_value[0]);
	list($fy, $fm, $fd) = explode('-', $dat_fmt);
	if((strlen($fy) == 4 && strlen($y) == 4) || (strlen($fd) == 4 && strlen($d) == 4)) {
		return $cur_date_val;
	}
	return getDisplayDate($cur_date_val);
}

/** This function returns the date in user specified format.
  * Takes no param, receives the date format from current user object
  */
    
function getNewDisplayDate()
{
	global $log;
	$log->debug("Entering getNewDisplayDate() method ...");
        $log->info("in getNewDisplayDate ");

	global $current_user;
	$dat_fmt = $current_user->date_format;
	if($dat_fmt == '')
        {
                $dat_fmt = 'dd-mm-yyyy';
        }
	$display_date='';
	if($dat_fmt == 'dd-mm-yyyy')
	{
		$display_date = date('d-m-Y');
	}
	elseif($dat_fmt == 'mm-dd-yyyy')
	{
		$display_date = date('m-d-Y');
	}
	elseif($dat_fmt == 'yyyy-mm-dd')
	{
		$display_date = date('Y-m-d');
	}
		
	$log->debug("Exiting getNewDisplayDate method ...");
	return $display_date;
}

/** This function returns the default vtiger_currency information.
  * Takes no param, return type array.
    */
    
function getDisplayCurrency()
{
	global $log;
	global $adb;
	$log->debug("Entering getDisplayCurrency() method ...");
        $curr_array = Array();
        $sql1 = "select * from vtiger_currency_info where currency_status=? and deleted=0";
        $result = $adb->mquery($sql1, array('Active'));
        $num_rows=$adb->num_rows($result);
        for($i=0; $i<$num_rows;$i++)
        {
                $curr_id = $adb->query_result($result,$i,"id");
                $curr_name = $adb->query_result($result,$i,"currency_name");
                $curr_symbol = $adb->query_result($result,$i,"currency_symbol");
                $curr_array[$curr_id] = $curr_name.' : '.$curr_symbol;
        }
	$log->debug("Exiting getDisplayCurrency method ...");
        return $curr_array;
}

/** This function returns the amount converted to dollar.
  * param $amount - amount to be converted.
    * param $crate - conversion rate.
      */
      
function convertToDollar($amount,$crate){
	global $log;
	$log->debug("Entering convertToDollar(".$amount.",".$crate.") method ...");
	$log->debug("Exiting convertToDollar method ...");
    return $amount / $crate;
}

/** This function returns the amount converted from dollar.
  * param $amount - amount to be converted.
    * param $crate - conversion rate.
      */
function convertFromDollar($amount,$crate){
	global $log;
	$log->debug("Entering convertFromDollar(".$amount.",".$crate.") method ...");
	$log->debug("Exiting convertFromDollar method ...");
	return round($amount * $crate, 2);
}

/** This function returns the amount converted from master currency.
  * param $amount - amount to be converted.
  * param $crate - conversion rate.
  */
function convertFromMasterCurrency($amount,$crate){
	global $log;
	$log->debug("Entering convertFromDollar(".$amount.",".$crate.") method ...");
	$log->debug("Exiting convertFromDollar method ...");
        return $amount * $crate;
}

/** This function returns the conversion rate and vtiger_currency symbol
  * in array format for a given id.
  * param $id - vtiger_currency id.
  */
      
function getCurrencySymbolandCRate($id)
{
	global $log;
	$log->debug("Entering getCurrencySymbolandCRate(".$id.") method ...");

	// To initialize the currency information in cache
	getCurrencyName($id);
	
	$currencyinfo = VTCacheUtils::lookupCurrencyInfo($id);
	
	$rate_symbol['rate']  = $currencyinfo['rate'];
	$rate_symbol['symbol']= $currencyinfo['symbol'];
	
	$log->debug("Exiting getCurrencySymbolandCRate method ...");
	return $rate_symbol;
}

/** This function returns the terms and condition from the database.
  * Takes no param and the return type is text.
  */
	    
function getTermsandConditions()
{
	global $log;
	$log->debug("Entering getTermsandConditions() method ...");
        global $adb;
        $sql1 = "select * from vtiger_inventory_tandc";
        $result = $adb->mquery($sql1, array());
        $tandc = $adb->query_result($result,0,"tandc");
	$log->debug("Exiting getTermsandConditions method ...");
        return $tandc;
}

/**
 * Create select options in a dropdown list.  To be used inside
  *  a reminder select statement in a vtiger_activity form. 
   * param $start - start value
   * param $end - end value
   * param $fldname - vtiger_field name 
   * param $selvalue - selected value 
   */
    
function getReminderSelectOption($start,$end,$fldname,$selvalue='')
{
	global $log;
	$log->debug("Entering getReminderSelectOption(".$start.",".$end.",".$fldname.",".$selvalue=''.") method ...");
	global $mod_strings;
	global $app_strings;
	
	$def_sel ="";
	$OPTION_FLD = "<SELECT name=".$fldname.">";
	for($i=$start;$i<=$end;$i++)
	{
		if($i==$selvalue)
		$def_sel = "SELECTED";
		$OPTION_FLD .= "<OPTION VALUE=".$i." ".$def_sel.">".$i."</OPTION>\n";
		$def_sel = "";
	}
	$OPTION_FLD .="</SELECT>";
	$log->debug("Exiting getReminderSelectOption method ...");
	return $OPTION_FLD;
}

/** This function returns the List price of a given product in a given price book.
  * param $productid - product id.
  * param $pbid - vtiger_pricebook id.
  */
  
function getListPrice($productid,$pbid)
{
	global $log;
	$log->debug("Entering getListPrice(".$productid.",".$pbid.") method ...");
        $log->info("in getListPrice productid ".$productid);

	global $adb;
	$query = "select listprice from vtiger_pricebookproductrel where pricebookid=? and productid=?";
	$result = $adb->mquery($query, array($pbid, $productid));
	$lp = $adb->query_result($result,0,'listprice');
	$log->debug("Exiting getListPrice method ...");
	return $lp;
}

/** This function returns a string with removed new line character, single quote, and back slash double quoute.
  * param $str - string to be converted.
  */
      
function br2nl($str) {
   global $log;
   $log->debug("Entering br2nl(".$str.") method ...");
   $str = preg_replace("/(\r\n)/", "\\r\\n", $str);
   $str = preg_replace("/'/", " ", $str);
   $str = preg_replace("/\"/", " ", $str);
   $log->debug("Exiting br2nl method ...");
   return $str;
}

/** This function returns a text, which escapes the html encode for link tag/ a href tag
*param $text - string/text
*/

function make_clickable($text)
{
   global $log;
   $log->debug("Entering make_clickable(".$text.") method ...");
   $text = preg_replace('#(script|about|applet|activex|chrome):#is', "\\1&#058;", $text);
   // pad it with a space so we can match things at the start of the 1st line.
   $ret = ' ' . $text;

   // matches an "xxxx://yyyy" URL at the start of a line, or after a space.
   // xxxx can only be alpha characters.
   // yyyy is anything up to the first space, newline, comma, double quote or <
   $ret = preg_replace("#(^|[\n ])([\w]+?://.*?[^ \"\n\r\t<]*)#is", "\\1<a href=\"\\2\" target=\"_blank\">\\2</a>", $ret);

   // matches a "www|ftp.xxxx.yyyy[/zzzz]" kinda lazy URL thing
   // Must contain at least 2 dots. xxxx contains either alphanum, or "-"
   // zzzz is optional.. will contain everything up to the first space, newline,
   // comma, double quote or <.
   $ret = preg_replace("#(^|[\n ])((www|ftp)\.[\w\-]+\.[\w\-.\~]+(?:/[^ \"\t\n\r<]*)?)#is", "\\1<a href=\"http://\\2\" target=\"_blank\">\\2</a>", $ret);

   // matches an email@domain type address at the start of a line, or after a space.
   // Note: Only the followed chars are valid; alphanums, "-", "_" and or ".".
   $ret = preg_replace("#(^|[\n ])([a-z0-9&\-_.]+?)@([\w\-]+\.([\w\-\.]+\.)*[\w]+)#i", "\\1<a href=\"mailto:\\2@\\3\">\\2@\\3</a>", $ret);

   // Remove our padding..
   $ret = substr($ret, 1);

   //remove comma, fullstop at the end of url
   $ret = preg_replace("#,\"|\.\"|\)\"|\)\.\"|\.\)\"#", "\"", $ret);

   $log->debug("Exiting make_clickable method ...");
   return($ret);
}
/**
 * This function returns the vtiger_blocks and its related information for given module.
 * Input Parameter are $module - module name, $disp_view = display view (edit,detail or create),$mode - edit, $col_fields - * column vtiger_fields/
 * This function returns an array
 */

function getBlocks($module,$disp_view,$mode,$col_fields='',$info_type='')
{ 
	global $log;
	$log->debug("Entering getBlocks(".$module.",".$disp_view.",".$mode.",".$col_fields.",".$info_type.") method ...");
        global $adb,$current_user;
        global $mod_strings;
        $tabid = getTabid($module);
        $block_detail = Array();
        $getBlockinfo = "";
        
        //MODIFIED ON 26th Feb,2016 - CONDITION UPLOAD IMAGE CAN REMOVE ON UNIVERSAL MASTER EDIT VIEW ONLY DISTRIBUTOR LOGIN
        $distArr        = getDistrIDbyUserID();
        if(trim($distArr["id"])!='' && $disp_view=='edit_view' && $tabid==147)
            $query          = "select blockid,blocklabel,show_title,display_status from vtiger_blocks where tabid=? and $disp_view=0 and visible = 0 and blockid!=486 order by sequence";
        else
            $query          = "select blockid,blocklabel,show_title,display_status from vtiger_blocks where tabid=? and $disp_view=0 and visible = 0 order by sequence";
        
        $result = $adb->mquery($query, array($tabid));
        $noofrows = $adb->num_rows($result);
        $prev_header = "";
	$blockid_list = array();
	for($i=0; $i<$noofrows; $i++)
	{
		$blockid = $adb->query_result($result,$i,"blockid");
		array_push($blockid_list,$blockid);
		$block_label[$blockid] = $adb->query_result($result,$i,"blocklabel");
		
		$sLabelVal = getTranslatedString($block_label[$blockid], $module);
		$aBlockStatus[$sLabelVal] = $adb->query_result($result,$i,"display_status");
	}                        
	if($mode == 'edit')	
	{
		$display_type_check = 'vtiger_field.displaytype = 1';
	}elseif($mode == 'mass_edit')	
	{
		$display_type_check = 'vtiger_field.displaytype = 1 AND vtiger_field.masseditable NOT IN (0,2)';
	}else
	{
		$display_type_check = 'vtiger_field.displaytype in (1,4)';
	}
	
	/*if($non_mass_edit_fields!='' && sizeof($non_mass_edit_fields)!=0){
		$mass_edit_query = "AND vtiger_field.fieldname NOT IN (". generateQuestionMarks($non_mass_edit_fields) .")";
	}*/
	
	//retreive the vtiger_profileList from database
	require('user_privileges/user_privileges_'.$current_user->id.'.php');
	if($disp_view == "detail_view")
	{
		if($is_admin == true || $profileGlobalPermission[1] == 0 || $profileGlobalPermission[2] == 0 || $module == "Users" || $module == "Emails")
  		{
 			$sql = "SELECT vtiger_field.* FROM vtiger_field WHERE vtiger_field.tabid=? AND vtiger_field.block IN (". generateQuestionMarks($blockid_list) .") AND vtiger_field.displaytype IN (1,2,4) and vtiger_field.presence in (0,2) ORDER BY block,sequence";
  			$params = array($tabid, $blockid_list);
		}
  		else
  		{
  			$profileList = getCurrentUserProfileList();
 			$sql = "SELECT vtiger_field.*,vtiger_profile2field.* FROM vtiger_field INNER JOIN vtiger_profile2field ON vtiger_profile2field.fieldid=vtiger_field.fieldid INNER JOIN vtiger_def_org_field ON vtiger_def_org_field.fieldid=vtiger_field.fieldid WHERE vtiger_field.tabid=? AND vtiger_field.block IN (". generateQuestionMarks($blockid_list) .") AND vtiger_field.displaytype IN (1,2,4) and vtiger_field.presence in (0,2) AND vtiger_profile2field.visible=0 AND vtiger_def_org_field.visible=0 AND vtiger_profile2field.profileid IN (". generateQuestionMarks($profileList) .") GROUP BY vtiger_field.fieldid ORDER BY block,sequence";
 			
                        $params = array($tabid, $blockid_list, $profileList);
			//Postgres 8 fixes
 			if( $adb->dbType == "pgsql")
 			    $sql = fixPostgresQuery( $sql, $log, 0);
  		}
		$result = $adb->mquery($sql, $params);

		// Added to unset the previous record's related listview session values
		if(isset($_SESSION['rlvs']))
			unset($_SESSION['rlvs']);

		$getBlockInfo=getDetailBlockInformation($module,$result,$col_fields,$tabid,$block_label);
	}
	else
	{
		if ($info_type != '')
		{
			if($is_admin==true || $profileGlobalPermission[1] == 0 || $profileGlobalPermission[2]== 0 || $module == 'Users' || $module == "Emails")
  			{
 				$sql = "SELECT vtiger_field.* FROM vtiger_field WHERE vtiger_field.tabid=? AND vtiger_field.block IN (". generateQuestionMarks($blockid_list) .") AND $display_type_check AND info_type = ? and vtiger_field.presence in (0,2) ORDER BY block,sequence";
  				$params = array($tabid, $blockid_list, $info_type);
			}
  			else
  			{
  				$profileList = getCurrentUserProfileList();
 				$sql = "SELECT vtiger_field.*,vtiger_profile2field.* FROM vtiger_field INNER JOIN vtiger_profile2field ON vtiger_profile2field.fieldid=vtiger_field.fieldid INNER JOIN vtiger_def_org_field ON vtiger_def_org_field.fieldid=vtiger_field.fieldid  WHERE vtiger_field.tabid=? AND vtiger_field.block IN (". generateQuestionMarks($blockid_list) .") AND $display_type_check AND info_type = ? AND vtiger_profile2field.visible=0 AND vtiger_def_org_field.visible=0 AND vtiger_profile2field.profileid IN (". generateQuestionMarks($profileList) .") and vtiger_field.presence in (0,2) GROUP BY vtiger_field.fieldid ORDER BY block,sequence";
 				                                
                                $params = array($tabid, $blockid_list, $info_type, $profileList);
				//Postgres 8 fixes
 				if( $adb->dbType == "pgsql")
 				    $sql = fixPostgresQuery( $sql, $log, 0);
  			}
		}
		else
		{
			if($is_admin==true || $profileGlobalPermission[1] == 0 || $profileGlobalPermission[2] == 0 || $module == 'Users' || $module == "Emails")
  			{
 				$sql = "SELECT vtiger_field.* FROM vtiger_field WHERE vtiger_field.tabid=? AND vtiger_field.block IN (". generateQuestionMarks($blockid_list).") AND $display_type_check  and vtiger_field.presence in (0,2) ORDER BY block,sequence";
				$params = array($tabid, $blockid_list);
			}
  			else
  			{
  				$profileList = getCurrentUserProfileList();
 				$sql = "SELECT vtiger_field.*,vtiger_profile2field.* FROM vtiger_field INNER JOIN vtiger_profile2field ON vtiger_profile2field.fieldid=vtiger_field.fieldid INNER JOIN vtiger_def_org_field ON vtiger_def_org_field.fieldid=vtiger_field.fieldid  WHERE vtiger_field.tabid=? AND vtiger_field.block IN (". generateQuestionMarks($blockid_list).") AND $display_type_check AND vtiger_profile2field.visible=0 AND vtiger_def_org_field.visible=0 AND vtiger_profile2field.profileid IN (". generateQuestionMarks($profileList).") and vtiger_field.presence in (0,2) GROUP BY vtiger_field.fieldid ORDER BY block,sequence";
				$params = array($tabid, $blockid_list, $profileList);
 				//Postgres 8 fixes
 				if( $adb->dbType == "pgsql")
 				    $sql = fixPostgresQuery( $sql, $log, 0);
  			}	
		}
		$result = $adb->mquery($sql, $params);
        $getBlockInfo=getBlockInformation($module,$result,$col_fields,$tabid,$block_label,$mode);
	}
	$log->debug("Exiting getBlocks method ...");
	if(count($getBlockInfo) > 0)
	{
		foreach($getBlockInfo as $label=>$contents)
		{
			if(empty($getBlockInfo[$label]))
			{
				unset($getBlockInfo[$label]);
			}
		}
	}
	$_SESSION['BLOCKINITIALSTATUS'] = $aBlockStatus;
	return $getBlockInfo;
}	
/**
 * This function is used to get the display type.
 * Takes the input parameter as $mode - edit  (mostly)
 * This returns string type value
 */

function getView($mode)
{
	global $log;
	$log->debug("Entering getView(".$mode.") method ...");
        if($mode=="edit")
	        $disp_view = "edit_view";
        else
	        $disp_view = "create_view";
	$log->debug("Exiting getView method ...");
        return $disp_view;
}
/**
 * This function is used to get the blockid of the customblock for a given module.
 * Takes the input parameter as $tabid - module tabid and $label - custom label
 * This returns string type value
 */

function getBlockId($tabid,$label)
{
	global $log;
	$log->debug("Entering getBlockId(".$tabid.",".$label.") method ...");
    global $adb;
    $blockid = '';
    $query = "select blockid from vtiger_blocks where tabid=? and blocklabel = ?";
    $result = $adb->mquery($query, array($tabid, $label));
    $noofrows = $adb->num_rows($result);
    if($noofrows == 1)
    {
		$blockid = $adb->query_result($result,0,"blockid");
    }
	$log->debug("Exiting getBlockId method ...");
	return $blockid;
}

/**
 * This function is used to get the Parent and Child vtiger_tab relation array.
 * Takes no parameter and get the data from parent_tabdata.php and vtiger_tabdata.php
 * This returns array type value
 */

function getHeaderArray()
{
	global $log;
	$log->debug("Entering getHeaderArray() method ...");
	global $adb;
	global $current_user;
	require('user_privileges/user_privileges_'.$current_user->id.'.php');
	include('parent_tabdata.php');
	include('tabdata.php');
	$noofrows = count($parent_tab_info_array);
        
        if(!$is_admin)
        {
            $profilesString='';

            for ($index = 0; $index < count($current_user_profiles); $index++) {

                if($index>0)
                    $profilesString.=',';

                $profilesString.=$current_user_profiles[$index];
            }

            $result=$adb->mquery("SELECT parenttab_id FROM vtiger_parenttab_permssions where visible=1 and profile_id in({$profilesString})");
            
            $allowedTabs=array();
            
            for ($index1 = 0; $index1 < $adb->num_rows($result); $index1++) {
                $tabId=$adb->query_result($result,$index1,0);
                
                array_push($allowedTabs, $tabId);
            }
        }
        
	foreach($parent_tab_info_array as $parid=>$parval)
	{
		$subtabs = Array();
		$tablist=$parent_child_tab_rel_array[$parid];
		$noofsubtabs = count($tablist);
                
                if(!in_array($parid,$allowedTabs) && !$is_admin)
                    continue;
                
		foreach($tablist as $childTabId)
		{
			$module = array_search($childTabId,$tab_info_array);
			
			if($is_admin)
			{
				$subtabs[] = $module;
			}	
			elseif($profileGlobalPermission[2]==0 ||$profileGlobalPermission[1]==0 || $profileTabsPermission[$childTabId]==0) 
			{
				$subtabs[] = $module;
			}	
		}

		$parenttab = getParentTabName($parid);
		if($parenttab == 'Settings' && $is_admin)
                {
                        $subtabs[] = 'Settings';
                }

		if($parenttab != 'Settings' ||($parenttab == 'Settings' && $is_admin))
		{
			if(!empty($subtabs))
				$relatedtabs[$parenttab] = $subtabs;
		}
	}
	$log->debug("Exiting getHeaderArray method ...");        
        
	return $relatedtabs;
}

/**
 * This function is used to get the Parent Tab name for a given parent vtiger_tab id.
 * Takes the input parameter as $parenttabid - Parent vtiger_tab id
 * This returns value string type 
 */

function getParentTabName($parenttabid)
{
	global $log;
	$log->debug("Entering getParentTabName(".$parenttabid.") method ...");
	global $adb;
	if (file_exists('parent_tabdata.php') && (filesize('parent_tabdata.php') != 0))
	{
		include('parent_tabdata.php');
		$parent_tabname= $parent_tab_info_array[$parenttabid];
	}
	else
	{
		$sql = "select parenttab_label from vtiger_parenttab where parenttabid=?";
		$result = $adb->mquery($sql, array($parenttabid));
		$parent_tabname=  $adb->query_result($result,0,"parenttab_label");
	}
	$log->debug("Exiting getParentTabName method ...");
	return $parent_tabname;
}

/**
 * This function is used to get the Parent Tab name for a given module.
 * Takes the input parameter as $module - module name
 * This returns value string type 
 */


function getParentTabFromModule($module)
{
	global $log;
	$log->debug("Entering getParentTabFromModule(".$module.") method ...");
	global $adb;
	if (file_exists('tabdata.php') && (filesize('tabdata.php') != 0) && file_exists('parent_tabdata.php') && (filesize('parent_tabdata.php') != 0))
	{
		include('tabdata.php');
		include('parent_tabdata.php');
		$tabid=$tab_info_array[$module];
		foreach($parent_child_tab_rel_array as $parid=>$childArr)
		{
			if(in_array($tabid,$childArr))
			{
				$parent_tabname= $parent_tab_info_array[$parid];
				break;
			}
		}
		$log->debug("Exiting getParentTabFromModule method ...");
		return $parent_tabname;
	}
	else
	{
		$sql = "select vtiger_parenttab.* from vtiger_parenttab inner join vtiger_parenttabrel on vtiger_parenttabrel.parenttabid=vtiger_parenttab.parenttabid inner join vtiger_tab on vtiger_tab.tabid=vtiger_parenttabrel.tabid where vtiger_tab.name=?";
		$result = $adb->mquery($sql, array($module));
		$tab =  $adb->query_result($result,0,"parenttab_label");
		$log->debug("Exiting getParentTabFromModule method ...");
		return $tab;
	}
}

/**
 * This function is used to get the Parent Tab name for a given module.
 * Takes no parameter but gets the vtiger_parenttab value from form request
 * This returns value string type 
 */
function getParentTab() {
    global $log, $default_charset;	
    $log->debug("Entering getParentTab() method ...");
    if(!empty($_REQUEST['parenttab'])) {
		$log->debug("Exiting getParentTab method ...");
        if(checkParentTabExists($_REQUEST['parenttab'])) {
		return vtlib_purify($_REQUEST['parenttab']);
        } else {
            return getParentTabFromModule($_REQUEST['module']);
        }
    } else {
		$log->debug("Exiting getParentTab method ...");
		return getParentTabFromModule($_REQUEST['module']);
    }
}

function checkParentTabExists($parenttab) {
    global $adb;

    if (file_exists('parent_tabdata.php') && (filesize('parent_tabdata.php') != 0)) {
        include('parent_tabdata.php');
        if(in_array($parenttab,$parent_tab_info_array))
            return true;
        else
            return false;
    } else {

        $result = "select 1 from vtiger_parenttab where parenttab_label = ?";
        $noofrows = $adb->num_rows($result);
        if($noofrows > 0)
            return true;
        else
            return false;
    }
         
}
/**
 * This function is used to get the days in between the current time and the modified time of an entity .
 * Takes the input parameter as $id - crmid  it will calculate the number of days in between the
 * the current time and the modified time from the vtiger_crmentity vtiger_table and return the result as a string.
 * The return format is updated <No of Days> day ago <(date when updated)>
 */

function updateInfo($id)
{
    global $log;
    $log->debug("Entering updateInfo(".$id.") method ...");

    global $adb;
    global $app_strings;
    
//    $query='select modifiedtime from vtiger_crmentity where crmid = ?';
//    $result = $adb->mquery($query, array($id));
//    $modifiedtime = $adb->query_result($result,0,'modifiedtime');
//    $values=explode(' ',$modifiedtime);
//    $date_info=explode('-',$values[0]);
//    $time_info=explode(':',$values[1]);
//    $date = $date_info[2].' '.$app_strings[date("M", mktime(0, 0, 0, $date_info[1], $date_info[2],$date_info[0]))].' '.$date_info[0];
//    $time_modified = mktime($time_info[0], $time_info[1], $time_info[2], $date_info[1], $date_info[2],$date_info[0]);
//    $time_now = time();
//    $days_diff = (int)(($time_now - $time_modified) / (60 * 60 * 24));
    
      $query='select DATEDIFF(NOW(),modifiedtime) as diff,modifiedtime from vtiger_crmentity where crmid = ?'; 
      $result = $adb->mquery($query, array($id));
      $days_diff = $adb->query_result($result,0,'diff');
      $modifiedtime = $adb->query_result($result,0,'modifiedtime');
      $values=explode(' ',$modifiedtime);
      $date_info=explode('-',$values[0]);
      $date = $date_info[2].' '.$app_strings[date("M", mktime(0, 0, 0, $date_info[1], $date_info[2],$date_info[0]))].' '.$date_info[0];
    
    if($days_diff == 0)
        $update_info = $app_strings['LBL_UPDATED_TODAY']." (".$date.")";
    elseif($days_diff == 1)
        $update_info = $app_strings['LBL_UPDATED']." ".$days_diff." ".$app_strings['LBL_DAY_AGO']." (".$date.")";
    else
        $update_info = $app_strings['LBL_UPDATED']." ".$days_diff." ".$app_strings['LBL_DAYS_AGO']." (".$date.")";

    $log->debug("Exiting updateInfo method ...");
    return $update_info;
}


/**
 * This function is used to get the Product Images for the given Product  .
 * It accepts the product id as argument and returns the Images with the script for 
 * rotating the product Images
 */

function getProductImages($id)
{
	global $log;
	$log->debug("Entering getProductImages(".$id.") method ...");
	global $adb;
	$image_lists=array();
	$script_images=array();
	$script = '<script>var ProductImages = new Array(';
   	$i=0;
	$query='select imagename from vtiger_products where productid=?';
	$result = $adb->mquery($query, array($id));
	$imagename=$adb->query_result($result,0,'imagename');
	$image_lists=explode('###',$imagename);
	for($i=0;$i<count($image_lists);$i++)
	{
		$script_images[] = '"'.$image_lists[$i].'"';
	}
	$script .=implode(',',$script_images).');</script>';
	if($imagename != '')
	{
		$log->debug("Exiting getProductImages method ...");
		return $script;
	}
}	

/**
 * This function is used to save the Images .
 * It acceps the File lists,modulename,id and the mode as arguments  
 * It returns the array details of the upload
 */

function SaveImage($_FILES,$module,$id,$mode)
{
	global $log, $root_directory;
	$log->debug("Entering SaveImage(".$_FILES.",".$module.",".$id.",".$mode.") method ...");
	global $adb;
	$uploaddir = $root_directory."test/".$module."/" ;//set this to which location you need to give the contact image
	$log->info("The Location to Save the Contact Image is ".$uploaddir);
	$file_path_name = $_FILES['imagename']['name'];
	if (isset($_REQUEST['imagename_hidden'])) {
		$file_name = vtlib_purify($_REQUEST['imagename_hidden']);
	} else {
		//allowed filename like UTF-8 Character 
		$file_name = ltrim(basename(" ".$file_path_name)); // basename($file_path_name);
	}
	$image_error="false";
	$saveimage="true";
        
	if($file_name!="")
	{

		$log->debug("Contact Image is given for uploading");
		$image_name_val=file_exist_fn($file_name,0);

		$encode_field_values="";
		$errormessage="";

		$move_upload_status=move_uploaded_file($_FILES["imagename"]["tmp_name"],$uploaddir.$image_name_val);
		$image_error="false";

		//if there is an error in the uploading of image

		$filetype= $_FILES['imagename']['type'];
		$filesize = $_FILES['imagename']['size'];

		$filetype_array=explode("/",$filetype);

		$file_type_val_image=strtolower($filetype_array[0]);
		$file_type_val=strtolower($filetype_array[1]);
		$log->info("The File type of the Contact Image is :: ".$file_type_val);
		//checking the uploaded image is if an image type or not
		if(!$move_upload_status) //if any error during file uploading
		{
			$log->debug("Error is present in uploading Contact Image.");
			$errorCode =  $_FILES['imagename']['error'];
			if($errorCode == 4)
			{
				$errorcode="no-image";
				$saveimage="false";
				$image_error="true";
			}
			else if($errorCode == 2)
			{
				$errormessage = 2;
				$saveimage="false";
				$image_error="true";
			}
			else if($errorCode == 3 )
			{
				$errormessage = 3;
				$saveimage="false";
				$image_error="true";
			}
		}
		else
		{
			$log->debug("Successfully uploaded the Contact Image.");
			if($filesize != 0)
			{
				if (($file_type_val == "jpeg" ) || ($file_type_val == "png") || ($file_type_val == "jpg" ) || ($file_type_val == "pjpeg" ) || ($file_type_val == "x-png") || ($file_type_val == "gif") ) //Checking whether the file is an image or not
				{
					$saveimage="true";
					$image_error="false";
				}
				else
				{
					$savelogo="false";
					$image_error="true";
					$errormessage = "image";
				}
			}
			else
			{       
				$savelogo="false";
				$image_error="true";
				$errormessage = "invalid";
			}

		}
	}
	else //if image is not given
	{
		$log->debug("Contact Image is not given for uploading.");
		if($mode=="edit" && $image_error=="false" )
		{
			if($module='contact')
			$image_name_val=getContactImageName($id);
			elseif($module='user')
			$image_name_val=getUserImageName($id);
			elseif($module='Account')
			$image_name_val=getAccountImageName($id);
			
                        $saveimage="true";
		}
		else
		{
			$image_name_val="";
		}
	}
	$return_value=array('imagename'=>$image_name_val,
	'imageerror'=>$image_error,
	'errormessage'=>$errormessage,
	'saveimage'=>$saveimage,
	'mode'=>$mode);
	$log->debug("Exiting SaveImage method ...");
	return $return_value;
}

function SaveAccImage($_FILES,$module,$id,$mode)
{
	global $log, $root_directory;
	$log->debug("Entering SaveImage(".$_FILES.",".$module.",".$id.",".$mode.") method ...");
	global $adb;
	$uploaddir = $root_directory."test/".$module."/" ;//set this to which location you need to give the contact image
	$log->info("The Location to Save the Contact Image is ".$uploaddir);
	$file_path_name = $_FILES['cf_accounts_accounts_image']['name'];
	if (isset($_REQUEST['cf_accounts_accounts_image_hidden'])) {
		$file_name = vtlib_purify($_REQUEST['cf_accounts_accounts_image_hidden']);
	} else {
		//allowed filename like UTF-8 Character 
		$file_name = ltrim(basename(" ".$file_path_name)); // basename($file_path_name);
	}
	$image_error="false";
	$saveimage="true";
        echo "FN-".$file_name;
        
	if($file_name!="")
	{

		$log->debug("Contact Image is given for uploading");
		$image_name_val=file_exist_fn($file_name,0);

		$encode_field_values="";
		$errormessage="";

		$move_upload_status=move_uploaded_file($_FILES["cf_accounts_accounts_image"]["tmp_name"],$uploaddir.$image_name_val);
		$image_error="false";

		//if there is an error in the uploading of image

		$filetype= $_FILES['cf_accounts_accounts_image']['type'];
		$filesize = $_FILES['cf_accounts_accounts_image']['size'];

		$filetype_array=explode("/",$filetype);

		$file_type_val_image=strtolower($filetype_array[0]);
		$file_type_val=strtolower($filetype_array[1]);
		$log->info("The File type of the Contact Image is :: ".$file_type_val);
                echo $file_type_val;
		//checking the uploaded image is if an image type or not
		if(!$move_upload_status) //if any error during file uploading
		{
			$log->debug("Error is present in uploading Contact Image.");
			$errorCode =  $_FILES['cf_accounts_accounts_image']['error'];
			if($errorCode == 4)
			{
				$errorcode="no-image";
				$saveimage="false";
				$image_error="true";
			}
			else if($errorCode == 2)
			{
				$errormessage = 2;
				$saveimage="false";
				$image_error="true";
			}
			else if($errorCode == 3 )
			{
				$errormessage = 3;
				$saveimage="false";
				$image_error="true";
			}
		}
		else
		{
			$log->debug("Successfully uploaded the Contact Image.");
			if($filesize != 0)
			{
				if (($file_type_val == "jpeg" ) || ($file_type_val == "png") || ($file_type_val == "jpg" ) || ($file_type_val == "pjpeg" ) || ($file_type_val == "x-png") || ($file_type_val == "gif") ) //Checking whether the file is an image or not
				{
					$saveimage="true";
					$image_error="false";
				}
				else
				{
					$savelogo="false";
					$image_error="true";
					$errormessage = "image";
				}
			}
			else
			{       
				$savelogo="false";
				$image_error="true";
				$errormessage = "invalid";
			}

		}
	}
	else //if image is not given
	{
		$log->debug("Contact Image is not given for uploading.");
		if($mode=="edit" && $image_error=="false" )
		{
			if($module='contact')
			$image_name_val=getContactImageName($id);
			elseif($module='user')
			$image_name_val=getUserImageName($id);
			elseif($module='Account')
			$image_name_val=getAccountImageName($id);
			
                        $saveimage="true";
		}
		else
		{
			$image_name_val="";
		}
	}
	$return_value=array('imagename'=>$image_name_val,
	'imageerror'=>$image_error,
	'errormessage'=>$errormessage,
	'saveimage'=>$saveimage,
	'mode'=>$mode);
	$log->debug("Exiting SaveImage method ...");
	return $return_value;
}

function SaveComImage($_FILES,$module,$id,$mode)
{
	global $log, $root_directory;
	$log->debug("Entering SaveImage(".$_FILES.",".$module.",".$id.",".$mode.") method ...");
	global $adb;
	$uploaddir = $root_directory."test/".$module."/" ;//set this to which location you need to give the contact image
	$log->info("The Location to Save the Contact Image is ".$uploaddir);
	$file_path_name = $_FILES['cf_company_logo']['name'];
	if (isset($_REQUEST['cf_company_logo_hidden'])) {
		$file_name = vtlib_purify($_REQUEST['cf_company_logo_hidden']);
	} else {
		//allowed filename like UTF-8 Character 
		$file_name = ltrim(basename(" ".$file_path_name)); // basename($file_path_name);
	}
	$image_error="false";
	$saveimage="true";
        echo "FN-".$file_name;
        
	if($file_name!="")
	{

		$log->debug("Contact Image is given for uploading");
		$image_name_val=file_exist_fn($file_name,0);

		$encode_field_values="";
		$errormessage="";

		$move_upload_status=move_uploaded_file($_FILES["cf_company_logo"]["tmp_name"],$uploaddir.$image_name_val);
		$image_error="false";

		//if there is an error in the uploading of image

		$filetype= $_FILES['cf_company_logo']['type'];
		$filesize = $_FILES['cf_company_logo']['size'];

		$filetype_array=explode("/",$filetype);

		$file_type_val_image=strtolower($filetype_array[0]);
		$file_type_val=strtolower($filetype_array[1]);
		$log->info("The File type of the Contact Image is :: ".$file_type_val);
                echo $file_type_val;
		//checking the uploaded image is if an image type or not
		if(!$move_upload_status) //if any error during file uploading
		{
			$log->debug("Error is present in uploading Contact Image.");
			$errorCode =  $_FILES['cf_accounts_accounts_image']['error'];
			if($errorCode == 4)
			{
				$errorcode="no-image";
				$saveimage="false";
				$image_error="true";
			}
			else if($errorCode == 2)
			{
				$errormessage = 2;
				$saveimage="false";
				$image_error="true";
			}
			else if($errorCode == 3 )
			{
				$errormessage = 3;
				$saveimage="false";
				$image_error="true";
			}
		}
		else
		{
			$log->debug("Successfully uploaded the Contact Image.");
			if($filesize != 0)
			{
				if (($file_type_val == "jpeg" ) || ($file_type_val == "png") || ($file_type_val == "jpg" ) || ($file_type_val == "pjpeg" ) || ($file_type_val == "x-png") || ($file_type_val == "gif") ) //Checking whether the file is an image or not
				{
					$saveimage="true";
					$image_error="false";
				}
				else
				{
					$savelogo="false";
					$image_error="true";
					$errormessage = "image";
				}
			}
			else
			{       
				$savelogo="false";
				$image_error="true";
				$errormessage = "invalid";
			}

		}
	}
	else //if image is not given
	{
		$log->debug("Contact Image is not given for uploading.");
		if($mode=="edit" && $image_error=="false" )
		{
			
			if($module='Company')
				$image_name_val=getCompanyImageName($id);
             
			$saveimage="true";
		}
		else
		{
			$image_name_val="";
		}
	}
	$return_value=array('imagename'=>$image_name_val,
	'imageerror'=>$image_error,
	'errormessage'=>$errormessage,
	'saveimage'=>$saveimage,
	'mode'=>$mode);
	$log->debug("Exiting SaveImage method ...");
	return $return_value;
}

 /**
 * This function is used to generate file name if more than one image with same name is added to a given Product.
 * Param $filename - product file name
 * Param $exist - number time the file name is repeated.
 */

function file_exist_fn($filename,$exist)
{
	global $log;
	$log->debug("Entering file_exist_fn(".$filename.",".$exist.") method ...");
	global $uploaddir;

	if(!isset($exist))
	{
		$exist=0;
	}
	$filename_path=$uploaddir.$filename;
	if (file_exists($filename_path)) //Checking if the file name already exists in the directory
	{
		if($exist!=0)
		{
			$previous=$exist-1;
			$next=$exist+1;
			$explode_name=explode("_",$filename);
			$implode_array=array();
			for($j=0;$j<count($explode_name); $j++)
			{
				if($j!=0)
				{
					$implode_array[]=$explode_name[$j];
				}
			}
			$implode_name=implode("_", $implode_array);
			$test_name=$implode_name;
		}
		else
		{
			$implode_name=$filename;
		}
		$exist++;
		$filename_val=$exist."_".$implode_name;
		$testfilename = file_exist_fn($filename_val,$exist);
		if($testfilename!="")
		{
			$log->debug("Exiting file_exist_fn method ...");
			return $testfilename;
		}
	}	
	else
	{
		$log->debug("Exiting file_exist_fn method ...");
		return $filename;
	}
}

/**
 * This function is used get the User Count.
 * It returns the array which has the total vtiger_users ,admin vtiger_users,and the non admin vtiger_users 
 */

function UserCount()
{
	global $log;
	$log->debug("Entering UserCount() method ...");
	global $adb;
	$result=$adb->mquery("select * from vtiger_users where deleted =0", array());
	$user_count=$adb->num_rows($result);
	$result=$adb->mquery("select * from vtiger_users where deleted =0 AND is_admin != 'on'", array());
	$nonadmin_count = $adb->num_rows($result);
	$admin_count = $user_count-$nonadmin_count;
	$count=array('user'=>$user_count,'admin'=>$admin_count,'nonadmin'=>$nonadmin_count);
	$log->debug("Exiting UserCount method ...");
	return $count;
}

/**
 * This function is used to create folders recursively.
 * Param $dir - directory name
 * Param $mode - directory access mode
 * Param $recursive - create directory recursive, default true
 */

function mkdirs($dir, $mode = 0777, $recursive = true)
{
	global $log;
	$log->debug("Entering mkdirs(".$dir.",".$mode.",".$recursive.") method ...");
	if( is_null($dir) || $dir === "" ){
		$log->debug("Exiting mkdirs method ...");
		return FALSE;
	}
	if( is_dir($dir) || $dir === "/" ){
		$log->debug("Exiting mkdirs method ...");
		return TRUE;
	}
	if( mkdirs(dirname($dir), $mode, $recursive) ){
		$log->debug("Exiting mkdirs method ...");
		return mkdir($dir, $mode);
	}
	$log->debug("Exiting mkdirs method ...");
	return FALSE;
}

/**
 * This function is used to set the Object values from the REQUEST values.
 * @param  object reference $focus - reference of the object
 */
function setObjectValuesFromRequest($focus)
{
	global $log;
	$log->debug("Entering setObjectValuesFromRequest(".get_class($focus).") method ...");
	global $current_user;
	$currencyid=fetchCurrency($current_user->id);
	$rate_symbol = getCurrencySymbolandCRate($currencyid);
	$rate = $rate_symbol['rate'];
	if(isset($_REQUEST['record']))
	{
		$focus->id = $_REQUEST['record'];
	}
	if(isset($_REQUEST['mode']))
	{
		$focus->mode = $_REQUEST['mode'];
	}
	foreach($focus->column_fields as $fieldname => $val)
	{
		if(isset($_REQUEST[$fieldname]))
		{
			if(is_array($_REQUEST[$fieldname]))
				$value = $_REQUEST[$fieldname];
			else
				$value = trim($_REQUEST[$fieldname]);
			$focus->column_fields[$fieldname] = $value;
		}
	}
        
        //print_r($focus);exit;
        
	$log->debug("Exiting setObjectValuesFromRequest method ...");
}

function workflowstage($transID){
    global $adb;
                $sql="SELECT crmid FROM vtiger_crmentityrel WHERE `relcrmid`='".$transID."'";
                $res=$adb->mquery($sql);
                $sql="UPDATE vtiger_xschemecf SET cf_xscheme_status='Created',cf_xscheme_next_stage_name='Approval' WHERE `xschemeid`='".$adb->query_result($res, 0, 'crmid')."'";
                $res1=$adb->mquery($sql);
                        
    return $adb->query_result($res, 0, 'crmid');
}

function workflowstage2($transID){
    global $adb;
                $sql="SELECT crmid FROM vtiger_crmentityrel WHERE `relcrmid`='".$transID."'";
                $res=$adb->mquery($sql);
                $sql="SELECT crmid FROM vtiger_crmentityrel WHERE `relcrmid`='".$adb->query_result($res, 0, 'crmid')."'";
                $res=$adb->mquery($sql);
                $sql="UPDATE vtiger_xschemecf SET cf_xscheme_status='Created',cf_xscheme_next_stage_name='Approval' WHERE `xschemeid`='".$adb->query_result($res, 0, 'crmid')."'";
                $res1=$adb->mquery($sql);
                        
    return $adb->query_result($res, 0, 'crmid');
}


 /**
 * Function to write the tabid and name to a flat file vtiger_tabdata.txt so that the data
 * is obtained from the file instead of repeated queries
 * returns null
 */

function create_tab_data_file()
{
	global $log;
	$log->debug("Entering create_tab_data_file() method ...");
        $log->info("creating vtiger_tabdata file");
        global $adb;
		//$sql = "select * from vtiger_tab";
		// vtlib customization: Disabling the tab item based on presence
        $sql = "select * from vtiger_tab where presence in (0,2)";
		// END

        $result = $adb->mquery($sql, array());
        $num_rows=$adb->num_rows($result);
        $result_array=Array();
	$seq_array=Array();
	$ownedby_array=Array();
	
        for($i=0;$i<$num_rows;$i++)
        {
                $tabid=$adb->query_result($result,$i,'tabid');
                $tabname=$adb->query_result($result,$i,'name');
		$presence=$adb->query_result($result,$i,'presence');
		$ownedby=$adb->query_result($result,$i,'ownedby');
                $result_array[$tabname]=$tabid;
		$seq_array[$tabid]=$presence;
		$ownedby_array[$tabid]=$ownedby;

        }

	//Constructing the actionname=>actionid array
	$actionid_array=Array();
	$sql1="select * from vtiger_actionmapping";
	$result1=$adb->mquery($sql1, array());
	$num_seq1=$adb->num_rows($result1);
	for($i=0;$i<$num_seq1;$i++)
	{
		$actionname=$adb->query_result($result1,$i,'actionname');
		$actionid=$adb->query_result($result1,$i,'actionid');
		$actionid_array[$actionname]=$actionid;
	}		

	//Constructing the actionid=>actionname array with securitycheck=0
	$actionname_array=Array();
	$sql2="select * from vtiger_actionmapping where securitycheck=0";
	$result2=$adb->mquery($sql2, array());
	$num_seq2=$adb->num_rows($result2);
	for($i=0;$i<$num_seq2;$i++)
	{
		$actionname=$adb->query_result($result2,$i,'actionname');
		$actionid=$adb->query_result($result2,$i,'actionid');
		$actionname_array[$actionid]=$actionname;
	}

        $filename = 'tabdata.php';
	
	
if (file_exists($filename)) {

        if (is_writable($filename))
        {

                if (!$handle = fopen($filename, 'w+')) {
                        echo "Cannot open file ($filename)";
                        exit;
                }
	require_once('modules/Users/CreateUserPrivilegeFile.php');
                $newbuf='';
                $newbuf .="<?php\n\n";
                $newbuf .="\n";
                $newbuf .= "//This file contains the commonly used variables \n";
                $newbuf .= "\n";
                $newbuf .= "\$tab_info_array=".constructArray($result_array).";\n";
                $newbuf .= "\n";
                $newbuf .= "\$tab_seq_array=".constructArray($seq_array).";\n";
		$newbuf .= "\n";
		$newbuf .= "\$tab_ownedby_array=".constructArray($ownedby_array).";\n";
		$newbuf .= "\n";
                $newbuf .= "\$action_id_array=".constructSingleStringKeyAndValueArray($actionid_array).";\n";
		$newbuf .= "\n";
                $newbuf .= "\$action_name_array=".constructSingleStringValueArray($actionname_array).";\n";
                $newbuf .= "?>";
                fputs($handle, $newbuf);
                fclose($handle);

        }
        else
        {
                echo "The file $filename is not writable";
        }

}
else
{
	echo "The file $filename does not exist";
	$log->debug("Exiting create_tab_data_file method ...");
	return;
}

        /*
         *      Content Sync Code
         */
        
        syncFilesInServer();
}


 /**
 * Function to write the vtiger_parenttabid and name to a flat file parent_tabdata.txt so that the data
 * is obtained from the file instead of repeated queries
 * returns null
 */

function create_parenttab_data_file()
{
	global $log;
	$log->debug("Entering create_parenttab_data_file() method ...");
	$log->info("creating parent_tabdata file");
	global $adb;
	$sql = "select parenttabid,parenttab_label from vtiger_parenttab where visible=0 order by sequence";
	$result = $adb->mquery($sql, array());
	$num_rows=$adb->num_rows($result);
	$result_array=Array();
	for($i=0;$i<$num_rows;$i++)
	{
		$parenttabid=$adb->query_result($result,$i,'parenttabid');
		$parenttab_label=$adb->query_result($result,$i,'parenttab_label');
		$result_array[$parenttabid]=$parenttab_label;

	}

	$filename = 'parent_tabdata.php';


	if (file_exists($filename)) {

		if (is_writable($filename))
		{

			if (!$handle = fopen($filename, 'w+'))
			{
				echo "Cannot open file ($filename)";
				exit;
			}
			require_once('modules/Users/CreateUserPrivilegeFile.php');
			$newbuf='';
			$newbuf .="<?php\n\n";
			$newbuf .="\n";
			$newbuf .= "//This file contains the commonly used variables \n";
			$newbuf .= "\n";
			$newbuf .= "\$parent_tab_info_array=".constructSingleStringValueArray($result_array).";\n";
			$newbuf .="\n";
			

			$parChildTabRelArray=Array();

			foreach($result_array as $parid=>$parvalue)
			{
				$childArray=Array();
				//$sql = "select * from vtiger_parenttabrel where parenttabid=? order by sequence";
				// vtlib customization: Disabling the tab item based on presence
				$sql = "select * from vtiger_parenttabrel where parenttabid=? 
					and tabid in (select tabid from vtiger_tab where presence in (0,2)) order by sequence";
				// END
				$result = $adb->mquery($sql, array($parid));
				$num_rows=$adb->num_rows($result);
				$result_array=Array();
				for($i=0;$i<$num_rows;$i++)
				{
					$tabid=$adb->query_result($result,$i,'tabid');
					$childArray[]=$tabid;
				}
				$parChildTabRelArray[$parid]=$childArray;

			}
			$newbuf .= "\n";
			$newbuf .= "\$parent_child_tab_rel_array=".constructTwoDimensionalValueArray($parChildTabRelArray).";\n";
			$newbuf .="\n";
			 $newbuf .="\n";
                        $newbuf .="\n";
                        $newbuf .= "?>";
                        fputs($handle, $newbuf);
                        fclose($handle);

		}
		else
		{
			echo "The file $filename is not writable";
		}

	}
	else
	{
		echo "The file $filename does not exist";
		$log->debug("Exiting create_parenttab_data_file method ...");
		return;
	}
        
        /*
         *      Content Sync Code
         */
        
        syncFilesInServer();
}

/**
 * This function is used to get the all the modules that have Quick Create Feature.
 * Returns Tab Name and Tablabel.
 */

function getQuickCreateModules()
{
	global $log;
	$log->debug("Entering getQuickCreateModules() method ...");
	global $adb;
	global $mod_strings;

	// vtlib customization: Ignore disabled modules.
	//$qc_query = "select distinct vtiger_tab.tablabel,vtiger_tab.name from vtiger_field inner join vtiger_tab on vtiger_tab.tabid = vtiger_field.tabid where quickcreate=0 order by vtiger_tab.tablabel";
	$qc_query = "select distinct vtiger_tab.tablabel,vtiger_tab.name from vtiger_field inner join vtiger_tab on vtiger_tab.tabid = vtiger_field.tabid where quickcreate=0 and vtiger_tab.presence != 1 order by vtiger_tab.tablabel";
	// END

	$result = $adb->mquery($qc_query, array());
	$noofrows = $adb->num_rows($result);
	$return_qcmodule = Array();
	for($i = 0; $i < $noofrows; $i++)
	{
		$tablabel = $adb->query_result($result,$i,'tablabel');
	
		$tabname = $adb->query_result($result,$i,'name');
	 	$tablabel = getTranslatedString("SINGLE_$tabname", $tabname);
	 	if(isPermitted($tabname,'EditView','') == 'yes')
	 	{
         	$return_qcmodule[] = $tablabel;
	        $return_qcmodule[] = $tabname;
		}	
	}
	if(sizeof($return_qcmodule >0))
	{
		$return_qcmodule = array_chunk($return_qcmodule,2);
	}
	
	$log->debug("Exiting getQuickCreateModules method ...");
	return $return_qcmodule;
}
																					   
/**
 * This function is used to get the Quick create form vtiger_field parameters for a given module.
 * Param $module - module name 
 * returns the value in array format
 */


function QuickCreate($module)
{
	global $log;
	$log->debug("Entering QuickCreate(".$module.") method ...");
	global $adb;
	global $current_user;
	global $mod_strings;

	$tabid = getTabid($module);

	//Adding Security Check
	require('user_privileges/user_privileges_'.$current_user->id.'.php');
	if($is_admin == true || $profileGlobalPermission[1] == 0 || $profileGlobalPermission[2] == 0)
	{
		$quickcreate_query = "select * from vtiger_field where quickcreate in (0,2) and tabid = ? and vtiger_field.presence in (0,2) and displaytype != 2 order by quickcreatesequence";
		$params = array($tabid);
	}
	else
	{
		$profileList = getCurrentUserProfileList();
		$quickcreate_query = "SELECT vtiger_field.*,vtiger_profile2field.* FROM vtiger_field INNER JOIN vtiger_profile2field ON vtiger_profile2field.fieldid=vtiger_field.fieldid INNER JOIN vtiger_def_org_field ON vtiger_def_org_field.fieldid=vtiger_field.fieldid WHERE vtiger_field.tabid=? AND quickcreate in (0,2) AND vtiger_profile2field.visible=0 AND vtiger_def_org_field.visible=0  AND vtiger_profile2field.profileid IN (". generateQuestionMarks($profileList) .") and vtiger_field.presence in (0,2) and displaytype != 2 GROUP BY vtiger_field.fieldid ORDER BY quickcreatesequence";
		$params = array($tabid, $profileList);
		//Postgres 8 fixes
		if( $adb->dbType == "pgsql")
			$quickcreate_query = fixPostgresQuery( $quickcreate_query, $log, 0); 
	}
	$category = getParentTab();
	$result = $adb->mquery($quickcreate_query, $params);
	$noofrows = $adb->num_rows($result);
	$fieldName_array = Array();
	for($i=0; $i<$noofrows; $i++)
	{
		$fieldtablename = $adb->query_result($result,$i,'tablename');
		$uitype = $adb->query_result($result,$i,"uitype");
		$fieldname = $adb->query_result($result,$i,"fieldname");
		$fieldlabel = $adb->query_result($result,$i,"fieldlabel");
		$maxlength = $adb->query_result($result,$i,"maximumlength");
		$generatedtype = $adb->query_result($result,$i,"generatedtype");
		$typeofdata = $adb->query_result($result,$i,"typeofdata");

		//to get validationdata
		$fldLabel_array = Array();
		$fldLabel_array[getTranslatedString($fieldlabel)] = $typeofdata;
		$fieldName_array[$fieldname] = $fldLabel_array;
		$custfld = getOutputHtml($uitype, $fieldname, $fieldlabel, $maxlength, $col_fields,$generatedtype,$module,'',$typeofdata);
		$qcreate_arr[]=$custfld;
	}
	for ($i=0,$j=0;$i<count($qcreate_arr);$i=$i+2,$j++)
	{
		$key1=$qcreate_arr[$i];
		if(is_array($qcreate_arr[$i+1]))
		{
			$key2=$qcreate_arr[$i+1];
		}
		else
		{
			$key2 =array();
		}
		$return_data[$j]=array(0 => $key1,1 => $key2);
	}
	$form_data['form'] = $return_data;
	$form_data['data'] = $fieldName_array;
	$log->debug("Exiting QuickCreate method ...".print_r($form_data,true));
	return $form_data;
}

/**	Function to send the Notification mail to the assigned to owner about the entity creation or updation
  *	@param string $module -- module name
  *	@param object $focus  -- reference of the object
**/
function sendNotificationToOwner($module,$focus)
{
	global $adb,$log,$app_strings;
	$log->debug("Entering sendNotificationToOwner(".$module.",".get_class($focus).") method ...");
	require_once("modules/Emails/mail.php");
	global $current_user;

	$ownername = getUserFullName($focus->column_fields['assigned_user_id']);
	$assName = getUserFullName($current_user->id);
	$ownermailid = getUserEmailId('id',$focus->column_fields['assigned_user_id']);
	$recseqnumber = '';

	if($module == 'Contacts')
	{
		$objectname = $focus->column_fields['lastname'].' '.$focus->column_fields['firstname'];
		$recseqnumber=$focus->column_fields['contact_no'];
		$mod_name = 'Contact';
		$object_column_fields = array(
						'lastname'=>'Last Name',
						'firstname'=>'First Name',
						'leadsource'=>'Lead Source',
						'department'=>'Department',
						'description'=>'Description',
					     );
	}
	if($module == 'Accounts')
	{
		$objectname = $focus->column_fields['accountname'];
		$recseqnumber=$focus->column_fields['account_no'];
		$mod_name = 'Account';
		$object_column_fields = array(
						'accountname'=>'Account Name',
						'rating'=>'Rating',
						'industry'=>'Industry',
						'accounttype'=>'Account Type',
						'description'=>'Description',
					     );
	}
	if($module == 'Potentials')
	{
		$objectname = $focus->column_fields['potentialname'];
		$recseqnumber=$focus->column_fields['potential_no'];
		$mod_name = 'Potential';
		$object_column_fields = array(
						'potentialname'=>'Potential Name',
						'amount'=>'Amount',
						'closingdate'=>'Expected Close Date',
						'opportunity_type'=>'Type',
						'description'=>'Description',
			      		     );
	}	
	if($module == 'Leads')
	{
		$objectname = $focus->column_fields['lastname'].' '.$focus->column_fields['firstname'];
		$recseqnumber=$focus->column_fields['lead_no'];
		$mod_name = 'Leads';
		$object_column_fields = array(
						'lastname'=>'Last Name',
						'firstname'=>'First Name',
						'leadsource'=>'Lead Source',
						'leadstatus'=>'Lead Status',
						'description'=>'Description',
					     );
	}	
	
        $conRes=$adb->mquery("SELECT subject,body FROM vtiger_emailtemplates where templatename='General'","");
        
        $email_body = $adb->query_result($conRes,0,'body');

        
	if($module == "Accounts" || $module == "Potentials" || $module == "Contacts" || $module == "Leads")
	{
		$description = $app_strings['MSG_DEAR'].' '.$ownername.',<br><br>';
		
		if(!empty($recseqnumber)) $recseqnumber = "[$recseqnumber]";
		
		if($focus->mode == 'edit')
		{
			$subject = $app_strings['MSG_REGARDING'].' '.$mod_name.' '.$app_strings['MSG_UPDATION']." $recseqnumber ".$objectname;
			$description .= $app_strings['MSG_THE'].' '.$mod_name.' '.$app_strings['MSG_HAS_BEEN_UPDATED'].getUserFullName($current_user->id).'.';
		}
		else
		{
			$subject = $app_strings['MSG_REGARDING'].' '.$mod_name.' '.$app_strings['MSG_ASSIGNMENT']." $recseqnumber ".$objectname;
		        $description .= $app_strings['MSG_THE'].' '.$mod_name.' '.$app_strings['MSG_HAS_BEEN_ASSIGNED_TO_YOU'].getUserFullName($current_user->id).'.';
		}
                
                //$description .= $assName ."<br/>";
                
		$description .= '<br>'.$app_strings['MSG_THE'].' '.$mod_name.' '.$app_strings['MSG_DETAILS_ARE'].':<br><br>';
                $description .= $mod_name.' '.$app_strings['MSG_ID'].' '.'<b>'.$recseqnumber.'</b><br>';
		foreach($object_column_fields as $fieldname => $fieldlabel)
		{
			//Get the translated string
			$temp_label = isset($app_strings[$fieldlabel])?$app_strings[$fieldlabel]:(isset($mod_strings[$fieldlabel])?$mod_strings[$fieldlabel]:$fieldlabel);

			$description .= $temp_label.' : <b>'.$focus->column_fields[$fieldname].'</b><br>';
		}
		
                //$description .=" <br/><br/> <i> Assigned By </i> : <b>".$current_user->user_name."</b><br/>";
		
		//$description .= '<br><br>'.$app_strings['MSG_THANKS'].',<br>'.$current_user->user_name.'.<br>';
                
                $description=str_replace("###content###",$description,$email_body); 
                
		$status = send_mail($module,$ownermailid,$current_user->user_name,'',$subject,$description);

		$log->debug("Exiting sendNotificationToOwner method ...");
		return $status;
	}
}

//Function to send notification to the users of a group
function sendNotificationToGroups($groupid,$crmid,$module)
{
       global $adb,$app_strings,$current_user;
       $returnEntity=Array();
       $returnEntity=getEntityName($module,Array($crmid));
       $mycrmid=$groupid;
       require_once('include/utils/GetGroupUsers.php');
       $getGroupObj=new GetGroupUsers();
       $getGroupObj->getAllUsersInGroup($mycrmid);
       $userIds=$getGroupObj->group_users;
	   if (count($userIds) > 0) {
	       $groupqry="select email1,id,user_name from vtiger_users WHERE status='Active' AND id in(". generateQuestionMarks($userIds) .")";
	       $groupqry_res=$adb->mquery($groupqry, array($userIds));
	       for($z=0;$z < $adb->num_rows($groupqry_res);$z++)
	       {
	               //handle the mail send to vtiger_users
	               $emailadd = $adb->query_result($groupqry_res,$z,'email1');
	               $curr_userid = $adb->query_result($groupqry_res,$z,'id');
	               $tosender=$adb->query_result($groupqry_res,$z,'user_name');
	               $pmodule = 'Users';
		       	   $description = $app_strings['MSG_DEAR']." ".$tosender.",<br>".$returnEntity[$crmid]." ".$app_strings['MSG_HAS_BEEN_CREATED_FOR']." ".$module."<br><br>".$app_strings['MSG_THANKS'].",<br>".$app_strings['MSG_VTIGERTEAM'];
	               require_once('modules/Emails/mail.php');
	               $mail_status = send_mail($module,$emailadd,$current_user->user_name,'','Record created-Sify Team',$description,'','','all',$crmid);
	               $all_to_emailids []= $emailadd;
	               $mail_status_str .= $emailadd."=".$mail_status."&&&";
	        }
		}
}

function sendNotificationToGroupsforHelpDesk($groupid,$focus,$module)
{
       global $log,$adb,$app_strings,$current_user;
	  $crmid=$focus->id;
	   $returnEntity=Array();
       $returnEntity=getEntityName($module,Array($crmid));
       $mycrmid=$groupid;
	   
	   // Code By Kami
	   $log->debug("-- Step 1 --");
	   $objectname = $focus->column_fields['lastname'].' '.$focus->column_fields['firstname'];
		//$recseqnumber=$focus->column_fields['contact_no'];
		$log->debug("-- Step 2 --");
		$object_column_fields = array(
						'ticket_no'=>'Ticket No',
						'ticket_title'=>'Ticket Title',
						'cust_cf_helpdesk_distributor'=>'Distributor',
						'cust_product_name'=>'Product Name',
						'description'=>'Description',
						'createdtime'=>'Created On',
						'ticketseverities'=>'Severiry',
						'ticketstatus'=>'Status',
						'solution'=>'Solution',
					     );
	   $subEmail="A Trouble ticket has been Created/Updated to you on Sify CRM<br/><br/>Details of the Trouble ticket are :";
	   foreach($object_column_fields as $fieldname => $fieldlabel)
		{
			//Get the translated string
			$temp_label = isset($app_strings[$fieldlabel])?$app_strings[$fieldlabel]:(isset($mod_strings[$fieldlabel])?$mod_strings[$fieldlabel]:$fieldlabel);
			
			
				if($fieldname=="cust_cf_helpdesk_distributor")
				{
					$distQuery="SELECT cf_helpdesk_distributor FROM vtiger_ticketcf WHERE ticketid=?";
					$distQuery_res=$adb->mquery($distQuery, array($crmid));
					if($adb->num_rows($distQuery_res)>0)
					{
						$subEmail .= $temp_label.' : <b>'.$adb->query_result($distQuery_res,0,'cf_helpdesk_distributor').'</b><br>';
					}
				}
				else if($fieldname=="cust_product_name")
				{
					$procuctQuery="SELECT productname FROM vtiger_products WHERE productid=?";
					$procuctQuery_res=$adb->mquery($procuctQuery, array($focus->column_fields['product_id']));
					if($adb->num_rows($procuctQuery_res)>0)
					{
						$subEmail .= $temp_label.' : <b>'.$adb->query_result($procuctQuery_res,0,'productname').'</b><br>';
					}
				}
			
			else
			{
				if($focus->column_fields[$fieldname]!="")
					$subEmail .= $temp_label.' : <b>'.$focus->column_fields[$fieldname].'</b><br>';
				else
					$subEmail .= $temp_label.' : <b>'.$_REQUEST[$fieldname].'</b><br>';
			}
		}
		
		$log->debug("-- Step 3 --");
		
	    $subject = $reply."  ".$focus->column_fields['ticket_no'] ."  ".$_REQUEST['ticket_title'];
	   
       require_once('include/utils/GetGroupUsers.php');
       $getGroupObj=new GetGroupUsers();
       $getGroupObj->getAllUsersInGroup($mycrmid);
       $userIdsOld=$getGroupObj->group_users;
       
       $userIds=array();
      
       //echo "SELECT userid FROM sify_supportuser_account_mapping where accountid=".$focus->column_fields['parent_id']." and userid in (". generateQuestionMarks($userIdsOld) .")";
       
       $groupUsersQuery=$adb->mquery("SELECT userid FROM sify_supportuser_account_mapping where accountid=".$focus->column_fields['parent_id']." and userid in (". generateQuestionMarks($userIdsOld) .")",array($userIdsOld));
       for($i=0;$i<$adb->num_rows($groupUsersQuery);$i++)
       {
           $userIds[$i]=$adb->query_result($groupUsersQuery,$i,"userid");
       }
	
       
       //print_r($userIds);
       
	   $log->debug("-- Step 4 --");
	   
	   if (count($userIds) > 0) {
	       $groupqry="select email1,id,user_name from vtiger_users WHERE status='Active' AND id in(". generateQuestionMarks($userIds) .")";
	       $groupqry_res=$adb->mquery($groupqry, array($userIds));
	       for($z=0;$z < $adb->num_rows($groupqry_res);$z++)
	       {
	               //handle the mail send to vtiger_users
	               $emailadd = $adb->query_result($groupqry_res,$z,'email1');
	               $curr_userid = $adb->query_result($groupqry_res,$z,'id');
	               $tosender=$adb->query_result($groupqry_res,$z,'user_name');
	               $pmodule = 'Users';
		       	   $description = $app_strings['MSG_DEAR']." ".$tosender.",<br>";
	               
				   $description.=$subEmail;
				   
				   $description .= '<br><br>'.$app_strings['MSG_THANK_YOU'].',<br>'.$current_user->user_name.'.<br>';
				   
				  
				   require_once('modules/Emails/mail.php');
				   
				   $log->debug("-- Step 6 --".$emailadd);
				   
                                   
	               $mail_status = send_mail('HelpDesk',$emailadd,$current_user->user_name,'',$subject,$description,'','','all',$crmid);
	               $all_to_emailids []= $emailadd;
	               $mail_status_str .= $emailadd."=".$mail_status."&&&";
	        }
		}
		
                
                echo "23".$mail_status_str;
		$log->debug("-- Step 5 --");
}

// added by kami

function sendNotificationToGroupsforHelpDeskWithDescription($groupid,$focus,$description,$username="")
{
	 global $log,$adb;
	 $mycrmid=$groupid;
	   
	   // Code By Kami
	   $log->debug("-- Step 1 --");
	
	   $subject ="".$focus->column_fields['ticket_no'] ."  ".$_REQUEST['ticket_title'];
	   
       require_once('include/utils/GetGroupUsers.php');
	   
       $getGroupObj=new GetGroupUsers();
       $getGroupObj->getAllUsersInGroup($mycrmid);
       $userIdsOld=$getGroupObj->group_users;
       
      $userIds=array();
       
       $groupUsersQuery=$adb->mquery("SELECT userid FROM sify_supportuser_account_mapping where accountid=".$focus->column_fields['parent_id']." and userid in (". generateQuestionMarks($userIdsOld) .")",array($userIdsOld));
       for($i=0;$i<$adb->num_rows($groupUsersQuery);$i++)
       {
           $userIds[$i]=$adb->query_result($groupUsersQuery,$i,"userid");
       }
	   
	   $log->debug("-- Step 4 --");
	   
//       $myFile = "C:/1.txt";
//        $fh = fopen($myFile, 'w');      
//        fwrite($fh, "\n1.--".count($userIds));
           
	   if (count($userIds) > 0) {
               $groupqry="select distinct email1,id,user_name from vtiger_users WHERE status='Active' AND id in(";
               
               for($x=0;$x< count($userIds);$x++)
               {
                   if($x>0)
                      $groupqry.=",";
                   
                   $groupqry.="?";
               }
               
               $groupqry.=")";
               
//               fwrite($fh, "\n1.1-".$groupqry);              
               
               $groupqry_res=$adb->mquery($groupqry,array($userIds));

               for($z=0;$z < $adb->num_rows($groupqry_res);$z++)
	       {
	               //handle the mail send to vtiger_users
	               $emailadd = $adb->query_result($groupqry_res,$z,'email1');
	               $curr_userid = $adb->query_result($groupqry_res,$z,'id');
	               $tosender=$adb->query_result($groupqry_res,$z,'user_name');
	               $pmodule = 'Users';
		       	   //$description = $app_strings['MSG_DEAR']." ".$tosender.",<br>";
	               
				   $descriptionToSend=str_replace("%sender%",$tosender,$description);
		  
				   require_once('modules/Emails/mail.php');
                        
//                        fwrite($fh, "\n2.1".$tosender);           
                                   
                        if($current_user->user_name!="")
                            $username=$current_user->user_name;
                       
//                        fwrite($fh, "\n2.2".$emailadd);   
                        
	               //$mail_status = send_mail('HelpDesk',$emailadd,$username,'',$subject,$descriptionToSend);
                       
//                       fwrite($fh, "\n2.3--".$z);
                       
	               $all_to_emailids []= $emailadd;
	               
                       if($z>0)
                           $mail_status_str.=",";
                       
                       $mail_status_str.= $emailadd;
	        }
                
//                fwrite($fh, "\n2.2".$mail_status_str);
                
                send_mail('HelpDesk',$mail_status_str,$username,'',$subject,$descriptionToSend);
                
		}
}


function getUserslist($setdefval=true)
{
	global $log,$current_user,$module,$adb,$assigned_user_id;
	$log->debug("Entering getUserslist() method ...");
	require('user_privileges/user_privileges_'.$current_user->id.'.php');
	require('user_privileges/sharing_privileges_'.$current_user->id.'.php');
	
	if($is_admin==false && $profileGlobalPermission[2] == 1 && ($defaultOrgSharingPermission[getTabid($module)] == 3 or $defaultOrgSharingPermission[getTabid($module)] == 0))
	{
		$users_combo = get_select_options_array(get_user_array(FALSE, "Active", $current_user->id,'private'), $current_user->id);
	}
	else
	{
		$users_combo = get_select_options_array(get_user_array(FALSE, "Active", $current_user->id),$current_user->id);
	}
	foreach($users_combo as $userid=>$value)	
	{

		foreach($value as $username=>$selected)
		{
			if ($setdefval == false) {
				$change_owner .= "<option value=$userid>".$username."</option>";
			} else {
				$change_owner .= "<option value=$userid $selected>".$username."</option>";
			}
		}
	}
	
	$log->debug("Exiting getUserslist method ...");
	return $change_owner;
}


function getGroupslist()
{
	global $log,$adb,$module,$current_user;
	$log->debug("Entering getGroupslist() method ...");
	require('user_privileges/user_privileges_'.$current_user->id.'.php');
	require('user_privileges/sharing_privileges_'.$current_user->id.'.php');
	
	//Commented to avoid security check for groups
	if($is_admin==false && $profileGlobalPermission[2] == 1 && ($defaultOrgSharingPermission[getTabid($module)] == 3 or $defaultOrgSharingPermission[getTabid($module)] == 0))
	{
		$result=get_current_user_access_groups($module);
	}
	else
	{
		$result = get_group_options();
	}

	if($result) $nameArray = $adb->fetch_array($result);
	if(!empty($nameArray))
	{
		if($is_admin==false && $profileGlobalPermission[2] == 1 && ($defaultOrgSharingPermission[getTabid($module)] == 3 or $defaultOrgSharingPermission[getTabid($module)] == 0))
		{
			$groups_combo = get_select_options_array(get_group_array(FALSE, "Active", $current_user->id,'private'), $current_user->id);
		}
		else
		{
			$groups_combo = get_select_options_array(get_group_array(FALSE, "Active", $current_user->id), $current_user->id);
		}
	}
	if(count($groups_combo) > 0) {
		foreach($groups_combo as $groupid=>$value)  
		{ 
			foreach($value as $groupname=>$selected) 
			{
				$change_groups_owner .= "<option value=$groupid $selected >".$groupname."</option>";  
			}	 
		}
	}
	$log->debug("Exiting getGroupslist method ...");
	return $change_groups_owner;
}


/**
  *	Function to Check for Security whether the Buttons are permitted in List/Edit/Detail View of all Modules
  *	@param string $module -- module name
  *	Returns an array with permission as Yes or No
**/
function Button_Check($module)
{
	global $log;
	$log->debug("Entering Button_Check(".$module.") method ...");
	$permit_arr = array ('EditView' => '',
						'index' => '',
						'Import' => '',
                      	'Export' => '',
						'Merge' => '',
						'DuplicatesHandling' => '' );

	foreach($permit_arr as $action => $perr){
		$tempPer=isPermitted($module,$action,'');
		$permit_arr[$action] = $tempPer;
	}
	$permit_arr["Calendar"] = isPermitted("Calendar","index",'');
	$permit_arr["moduleSettings"] = isModuleSettingPermitted($module);
	$log->debug("Exiting Button_Check method ...");
	  return $permit_arr;

}

/**
  *	Function to Check whether the User is allowed to delete a particular record from listview of each module using   
  *	mass delete button.
  *	@param string $module -- module name
  *	@param array $ids_list -- Record id 
  *	Returns the Record Names of each module that is not permitted to delete
**/
function getEntityName($module, $ids_list)
{
	global $adb;
	global $log;
	$log->debug("Entering getEntityName(".$module.") method ...");
	
	if($module != '')
	{
            $query = "select fieldname,tablename,entityidfield from vtiger_entityname where modulename = ?";
            $result = $adb->mquery($query, array($module));
            $fieldsname = $adb->query_result($result,0,'fieldname');
            $tablename = $adb->query_result($result,0,'tablename'); 
            $entityidfield = $adb->query_result($result,0,'entityidfield');
        }else{
            $fieldsname = "templatename";
            $tablename = "vtiger_printbilltemplate";
            $entityidfield = "templateid";
        }
        
        if($module == 'Users' && $_REQUEST['module'] == 'xCpDpMapping'){
            $fieldsname = explode(',',$fieldsname);
            $fieldsname = $fieldsname[2];
        }

        if(!(strpos($fieldsname,',') === false))
        {
                $fieldlists = explode(',',$fieldsname);
                $fieldsname = "concat(";
                $fieldsname = $fieldsname.implode(",' ::: ',",$fieldlists);
                $fieldsname = $fieldsname.")";
        }	
        if (count($ids_list) <= 0) {
               return array();
        }

        $query1 = "select $fieldsname as entityname,$entityidfield from $tablename where ".
               "$entityidfield in (". generateQuestionMarks($ids_list) .")";
        if($tablename == "vtiger_users")
           $ids_list = explode(",", $ids_list);

        $params1 = array($ids_list);
        $result = $adb->mquery($query1, $params1);
        $numrows = $adb->num_rows($result);
        $account_name = array();
        $entity_info = array();
        for ($i = 0; $i < $numrows; $i++)
        {
               $entity_id = $adb->query_result($result,$i,$entityidfield);
               $entity_info[$entity_id] = $adb->query_result($result,$i,'entityname');
        }
        return $entity_info;
	
	$log->debug("Exiting getEntityName method ...");
}

/**Function to get all permitted modules for a user with their parent
*/

function getAllParenttabmoduleslist()
{
	global $adb;
	global $current_user;
	$resultant_array = Array();

	//$query = 'select name,tablabel,parenttab_label,vtiger_tab.tabid from vtiger_parenttabrel inner join vtiger_tab on vtiger_parenttabrel.tabid = vtiger_tab.tabid inner join vtiger_parenttab on vtiger_parenttabrel.parenttabid = vtiger_parenttab.parenttabid and vtiger_tab.presence order by vtiger_parenttab.sequence, vtiger_parenttabrel.sequence';

	// vtlib customization: Disabling the tab item based on presence		
	//$query = 'select name,tablabel,parenttab_label,vtiger_tab.tabid from vtiger_parenttabrel inner join vtiger_tab on vtiger_parenttabrel.tabid = vtiger_tab.tabid inner join vtiger_parenttab on vtiger_parenttabrel.parenttabid = vtiger_parenttab.parenttabid and vtiger_tab.presence in (0,2) order by vtiger_parenttab.sequence, vtiger_parenttabrel.sequence'; //pk
        
        $profId=getUserProfile($current_user->id);
        
	$query = 'SELECT NAME
	,CASE WHEN vtiger_parenttabrel.label is NOT NULL THEN vtiger_parenttabrel.label ELSE tablabel END as `tablabel`
	,parenttab_label
	,vtiger_tab.tabid
        FROM vtiger_parenttabrel
        INNER JOIN vtiger_tab ON vtiger_parenttabrel.tabid = vtiger_tab.tabid
        INNER JOIN vtiger_parenttab ON vtiger_parenttabrel.parenttabid = vtiger_parenttab.parenttabid AND vtiger_tab.presence IN (0,2)
        inner join vtiger_parenttab_permssions on vtiger_parenttab_permssions.parenttab_id=vtiger_parenttabrel.parenttabid
        where vtiger_parenttab_permssions.profile_id in (?) and vtiger_parenttab_permssions.visible=1
        ORDER BY vtiger_parenttab.sequence
                ,vtiger_parenttabrel.sequence';
	// END
	$result = $adb->mquery($query, array(implode(',',$profId)));
        
        //echo "<pre>";
        //echo "Hi :".print_r($result);
        
	require('user_privileges/user_privileges_'.$current_user->id.'.php');
	for($i=0;$i<$adb->num_rows($result);$i++)
	{
		$parenttabname = $adb->query_result($result,$i,'parenttab_label');
		$modulename = $adb->query_result($result,$i,'name');
		$tablabel = $adb->query_result($result,$i,'tablabel');
		$tabid = $adb->query_result($result,$i,'tabid');
		if($is_admin){
			$resultant_array[$parenttabname][] = Array($modulename,$tablabel);
		}	
		elseif($profileGlobalPermission[2]==0 || $profileGlobalPermission[1]==0 || $profileTabsPermission[$tabid]==0)		     {
			$resultant_array[$parenttabname][] = Array($modulename,$tablabel);
		}
	}
	
	if($is_admin){
		$resultant_array['Settings'][] = Array('Settings','Settings');
		$resultant_array['Settings'][] = Array('Settings',getTranslatedString('VTLIB_LBL_MODULE_MANAGER', 'Settings'), 'ModuleManager');
                $resultant_array['Settings'][] = Array('xIncentiveSetting',getTranslatedString('Salesman Incentive Setting', 'Settings'), 'index');
	}	
        //echo "<pre>"; print_r($resultant_array1); echo "</pre>";
	return $resultant_array;
}

/**
 * 	This function is used to decide the File Storage Path in where we will upload the file in the server.
 * 	return string $filepath  - filepath inwhere the file should be stored in the server will be return
*/
function decideFilePath()
{
	global $log, $adb;
	$log->debug("Entering into decideFilePath() method ...");

	$filepath = 'storage/';

	$year  = date('Y');
	$month = date('F');
	$day  = date('j');
	$week   = '';

	if(!is_dir($filepath.$year))
	{
		//create new folder
		mkdir($filepath.$year);
	}

	if(!is_dir($filepath.$year."/".$month))
	{
		//create new folder
		mkdir($filepath."$year/$month");
	}

	if($day > 0 && $day <= 7)
		$week = 'week1';
	elseif($day > 7 && $day <= 14)
		$week = 'week2';
	elseif($day > 14 && $day <= 21)
		$week = 'week3';
	elseif($day > 21 && $day <= 28 )
		$week = 'week4';
	else
		$week = 'week5';

	if(!is_dir($filepath.$year."/".$month."/".$week))
	{
		//create new folder
		mkdir($filepath."$year/$month/$week");
	}

	$filepath = $filepath.$year."/".$month."/".$week."/";

	$log->debug("Year=$year & Month=$month & week=$week && filepath=\"$filepath\"");
	$log->debug("Exiting from decideFilePath() method ...");
	
	return $filepath;
}
function decideXSLFilePath($folderName)
{
	global $log, $adb;
	$log->debug("Entering into decideFilePath() method ...");

	$filepath = 'storage/';

	if(!is_dir($filepath.$folderName))
	{
		//create new folder
		mkdir($filepath.$folderName);
	}


	$filepath = $filepath.$folderName."/";
        
	return $filepath;
}

/**
 * 	This function is used to get the Path in where we store the vtiger_files based on the module.
 *	@param string $module   - module name
 * 	return string $storage_path - path inwhere the file will be uploaded (also where it was stored) will be return based on the module
*/
function getModuleFileStoragePath($module)
{
	global $log;
	$log->debug("Entering into getModuleFileStoragePath($module) method ...");
	
	$storage_path = "test/";

	if($module == 'Products')
	{
		$storage_path .= 'product/';
	}
	if($module == 'Contacts')
	{
		$storage_path .= 'contact/';
	}

	$log->debug("Exiting from getModuleFileStoragePath($module) method. return storage_path = \"$storage_path\"");
	return $storage_path;
}

/**
 * 	This function is used to check whether the attached file is a image file or not
 *	@param string $file_details  - vtiger_files array which contains all the uploaded file details
 * 	return string $save_image - true or false. if the image can be uploaded then true will return otherwise false.
*/
function validateImageFile($file_details)
{
	global $adb, $log,$app_strings;
	$log->debug("Entering into validateImageFile($file_details) method.");
	
	$savefile = 'true';
	$file_type_details = explode("/",$file_details['type']);
	$filetype = $file_type_details['1'];

	if(!empty($filetype)) $filetype = strtolower($filetype);
	if (($filetype == "jpeg" ) || ($filetype == "png") || ($filetype == "jpg" ) || ($filetype == "pjpeg" ) || ($filetype == "x-png") || ($filetype == "gif") || ($filetype == 'bmp') )
	{
		$saveimage = 'true';
	}
	else
	{
		$saveimage = 'false';
		$_SESSION['image_type_error'] .= "<br> &nbsp;&nbsp;<b>".$file_details[name]."</b>".$app_strings['MSG_IS_NOT_UPLOADED'];
		$log->debug("Invalid Image type == $filetype");
	}

	$log->debug("Exiting from validateImageFile($file_details) method. return saveimage=$saveimage");
	return $saveimage;
}

function validatePDFFile($file_details) {
    global $adb, $log, $app_strings;
    //$log->debug("Entering into validateImageFile($file_details) method.");

    $save_file = 'True';
    $file_type_details = explode("/", $file_details['type']);
    $file_size = $file_details['size'];
    //echo  $file_size;die;             
    $filetype = $file_type_details['1'];
//        echo"<pre>"; print_r($file_details) ;die;               
    if (!empty($filetype))
        $filetype = strtolower($filetype);
    if ($filetype == "pdf" && $file_size < 2000000) {
        $save_file = 'True';
    } else {
        //echo $filetype;die;

        echo '<script>alert("File Size Exceeded");window.location="' . $_SERVER['HTTP_REFERER'] . '"</script>';
        $save_file = 'False';
        exit;
    }

    //$log->debug("Exiting from validatePDFFile($file_details) method. return saveimage=$saveimage");
    return $save_file;
}

/**
 * 	This function is used to get the Email Template Details like subject and content for particular template. 
 *	@param integer $templateid  - Template Id for an Email Template
 * 	return array $returndata - Returns Subject, Body of Template of the the particular email template.
*/

function getTemplateDetails($templateid)
{
        global $adb,$log;
        $log->debug("Entering into getTemplateDetails($templateid) method ...");
        $returndata =  Array();
        $result = $adb->mquery("select * from vtiger_emailtemplates where templateid=?", array($templateid));
        $returndata[] = $templateid;
        $returndata[] = $adb->query_result($result,0,'body');
        $returndata[] = $adb->query_result($result,0,'subject');
        $log->debug("Exiting from getTemplateDetails($templateid) method ...");
        return $returndata;
}
/**
 * 	This function is used to merge the Template Details with the email description  
 *  @param string $description  -body of the mail(ie template)
 *	@param integer $tid  - Id of the entity
 *  @param string $parent_type - module of the entity
 * 	return string $description - Returns description, merged with the input template.
*/
									
function getMergedDescription($description,$id,$parent_type)
{
	global $adb,$log;
    $log->debug("Entering getMergedDescription ...");
	$token_data_pair = explode('$',$description);
	global $current_user;
 	$emailTemplate = new EmailTemplate($parent_type, $description, $id, $current_user);
 	$description = $emailTemplate->getProcessedDescription();
 	$templateVariablePair = explode('$',$description);
 	$tokenDataPair = explode('$',$description);
	$fields = Array();
	for($i=1;$i < count($token_data_pair);$i+=2)
	{

		$module = explode('-',$tokenDataPair[$i]);
		$fields[$module[0]][] = $module[1];
	}
    if(is_array($fields['custom']) && count($fields['custom']) > 0){
    //Puneeth : Added for custom date & time fields
    $description = getMergedDescriptionCustomVars($fields, $description);
    }
    $log->debug("Exiting from getMergedDescription ...");
	return $description;
}

/* Function to merge the custom date & time fields in email templates */
function getMergedDescriptionCustomVars($fields, $description) {
	foreach($fields['custom'] as $columnname)
	{
		$token_data = '$custom-'.$columnname.'$';
		$token_value = '';
		switch($columnname) {
			case 'currentdate': $token_value = date("F j, Y"); break;
			case 'currenttime': $token_value = date("G:i:s T"); break;
		}
		$description = str_replace($token_data, $token_value, $description);
	}
	return $description;
}
//End : Custom date & time fields

/**	Function used to retrieve a single field value from database
 *	@param string $tablename - tablename from which we will retrieve the field value
 *	@param string $fieldname - fieldname to which we want to get the value from database
 *	@param string $idname	 - idname which is the name of the entity id in the table like, inoviceid, quoteid, etc.,
 *	@param int    $id	 - entity id
 *	return string $fieldval  - field value of the needed fieldname from database will be returned
 */
function getSingleFieldValue($tablename, $fieldname, $idname, $id)
{
	global $log, $adb;
	$log->debug("Entering into function getSingleFieldValue($tablename, $fieldname, $idname, $id)");

	$fieldval = $adb->query_result($adb->mquery("select $fieldname from $tablename where $idname = ?", array($id)),0,$fieldname);

	$log->debug("Exit from function getSingleFieldValue. return value ==> \"$fieldval\"");

	return $fieldval;
}

/**	Function used to retrieve the announcements from database
 *	The function accepts no argument and returns the announcements
 *	return string $announcement  - List of announments for the CRM users 
 */

function get_announcements()
{
	global $adb;
	$sql=" select * from vtiger_announcement inner join vtiger_users on vtiger_announcement.creatorid=vtiger_users.id";
	$sql.=" AND vtiger_users.is_admin='on' AND vtiger_users.status='Active' AND vtiger_users.deleted = 0";
	$result=$adb->mquery($sql, array());
	for($i=0;$i<$adb->num_rows($result);$i++)
	{
		$announce = getUserName($adb->query_result($result,$i,'creatorid')).' :  '.$adb->query_result($result,$i,'announcement').'   ';
		if($adb->query_result($result,$i,'announcement')!='')
			$announcement.=$announce;
	}
	return $announcement;
}

/**	Function used to retrieve the rate converted into dollar tobe saved into database
 *	The function accepts the price in the current currency
 *	return integer $conv_price  - 
 */
 function getConvertedPrice($price) 
 {
	 global $current_user;
	 $currencyid=fetchCurrency($current_user->id);
	 $rate_symbol = getCurrencySymbolandCRate($currencyid);
	 $conv_price = convertToDollar($price,$rate_symbol['rate']);
	 return $conv_price;
 }


/**	Function used to get the converted amount from dollar which will be showed to the user
 *	@param float $price - amount in dollor which we want to convert to the user configured amount
 *	@return float $conv_price  - amount in user configured currency
 */
function getConvertedPriceFromDollar($price) 
{
	global $current_user;
	$currencyid=fetchCurrency($current_user->id);
	$rate_symbol = getCurrencySymbolandCRate($currencyid);
	$conv_price = convertFromDollar($price,$rate_symbol['rate']);
	return $conv_price;
}


/**
 *  Function to get recurring info depending on the recurring type
 *  return  $recurObj       - Object of class RecurringType
 */
 
function getrecurringObjValue()
{
	$recurring_data = array();
	if(isset($_REQUEST['recurringtype']) && $_REQUEST['recurringtype'] != null &&  $_REQUEST['recurringtype'] != '--None--' )
	{
		if(isset($_REQUEST['date_start']) && $_REQUEST['date_start'] != null)
		{
			$recurring_data['startdate'] = $_REQUEST['date_start'];
		}
		if(isset($_REQUEST['due_date']) && $_REQUEST['due_date'] != null)
		{
			$recurring_data['enddate'] = $_REQUEST['due_date'];
		}
		$recurring_data['type'] = $_REQUEST['recurringtype'];
		if($_REQUEST['recurringtype'] == 'Weekly')
		{
			if(isset($_REQUEST['sun_flag']) && $_REQUEST['sun_flag'] != null)
				$recurring_data['sun_flag'] = true;
			if(isset($_REQUEST['mon_flag']) && $_REQUEST['mon_flag'] != null)
				$recurring_data['mon_flag'] = true;
			if(isset($_REQUEST['tue_flag']) && $_REQUEST['tue_flag'] != null)
				$recurring_data['tue_flag'] = true;
			if(isset($_REQUEST['wed_flag']) && $_REQUEST['wed_flag'] != null)
				$recurring_data['wed_flag'] = true;
			if(isset($_REQUEST['thu_flag']) && $_REQUEST['thu_flag'] != null)
				$recurring_data['thu_flag'] = true;
			if(isset($_REQUEST['fri_flag']) && $_REQUEST['fri_flag'] != null)
				$recurring_data['fri_flag'] = true;
			if(isset($_REQUEST['sat_flag']) && $_REQUEST['sat_flag'] != null)
				$recurring_data['sat_flag'] = true;
		}
		elseif($_REQUEST['recurringtype'] == 'Monthly')
		{
			if(isset($_REQUEST['repeatMonth']) && $_REQUEST['repeatMonth'] != null)
				$recurring_data['repeatmonth_type'] = $_REQUEST['repeatMonth'];
			if($recurring_data['repeatmonth_type'] == 'date')
			{
				if(isset($_REQUEST['repeatMonth_date']) && $_REQUEST['repeatMonth_date'] != null)
					$recurring_data['repeatmonth_date'] = $_REQUEST['repeatMonth_date'];
				else
					$recurring_data['repeatmonth_date'] = 1;
			}
			elseif($recurring_data['repeatmonth_type'] == 'day')
			{
				$recurring_data['repeatmonth_daytype'] = $_REQUEST['repeatMonth_daytype'];
				switch($_REQUEST['repeatMonth_day'])
				{
					case 0 :
						$recurring_data['sun_flag'] = true;
						break;
					case 1 :
						$recurring_data['mon_flag'] = true;
						break;
					case 2 :
						$recurring_data['tue_flag'] = true;
						break;
					case 3 :
						$recurring_data['wed_flag'] = true;
						break;
					case 4 :
						$recurring_data['thu_flag'] = true;
						break;
					case 5 :
						$recurring_data['fri_flag'] = true;
						break;
					case 6 :
						$recurring_data['sat_flag'] = true;
						break;
				}
			}
		}
		if(isset($_REQUEST['repeat_frequency']) && $_REQUEST['repeat_frequency'] != null)
			$recurring_data['repeat_frequency'] = $_REQUEST['repeat_frequency'];
		$recurObj = new RecurringType($recurring_data);
		return $recurObj;
	}
	
}

/**	Function used to get the translated string to the input string
 *	@param string $str - input string which we want to translate
 *	@return string $str - translated string, if the translated string is available then the translated string other wise original string will be returned
 */
function getTranslatedString($str,$module='')
{
	//echo $str;
        global $app_strings, $mod_strings, $log,$current_language;
	$temp_mod_strings = ($module != '' )?return_module_language($current_language,$module):$mod_strings;
	$trans_str = ($temp_mod_strings[$str] != '')?$temp_mod_strings[$str]:(($app_strings[$str] != '')?$app_strings[$str]:$str);
	$log->debug("function getTranslatedString($str) - translated to ($trans_str)");
	return $trans_str;
}

/**
 * Get translated currency name string.
 * @param String $str - input currency name
 * @return String $str - translated currency name
 */
function getTranslatedCurrencyString($str) {
	global $app_currency_strings;
	if(isset($app_currency_strings) && isset($app_currency_strings[$str])) {
		return $app_currency_strings[$str];
	}
	return $str;
}

/**	function used to get the list of importable fields
 *	@param string $module - module name
 *	@return array $fieldslist - array with list of fieldnames and the corresponding translated fieldlabels. The return array will be in the format of [fieldname]=>[fieldlabel] where as the fieldlabel will be translated
 */
function getImportFieldsList($module)
{
	global $adb, $log;
	$log->debug("Entering into function getImportFieldsList($module)");
	
	$tabid = getTabid($module);

	//Here we can add special cases for module basis, ie., if we want the fields of display type 3, we can add
	$displaytype = " displaytype=1 and vtiger_field.presence in (0,2) ";

	$fieldnames = "";
	//For module basis we can add the list of fields for Import mapping
	if($module == "Leads" || $module == "Contacts")
	{
		$fieldnames = " fieldname='salutationtype' ";
	}

	//Form the where condition based on tabid , displaytype and extra fields
	$where = " WHERE tabid=? and ( $displaytype ";
	$params = array($tabid);
	if($fieldnames != "")
	{
		$where .= " or $fieldnames ";
	}
	$where .= ")";

	//Get the list of fields and form as array with [fieldname] => [fieldlabel]
	$query = "SELECT fieldname, fieldlabel FROM vtiger_field $where";
	$result = $adb->mquery($query, $params);
	for($i=0;$i<$adb->num_rows($result);$i++)
	{
		$fieldname = $adb->query_result($result,$i,'fieldname');
		$fieldlabel = $adb->query_result($result,$i,'fieldlabel');
		$fieldslist[$fieldname] = getTranslatedString($fieldlabel, $module);
	}

	$log->debug("Exit from function getImportFieldsList($module)");

	return $fieldslist;
}
/**     Function to get all the comments for a troubleticket
  *     @param int $ticketid -- troubleticket id
  *     return all the comments as a sequencial string which are related to this ticket
**/
function getTicketComments($ticketid)
{
        global $log;
        $log->debug("Entering getTicketComments(".$ticketid.") method ...");
        global $adb;

        $commentlist = '';
        $sql = "select * from vtiger_ticketcomments where ticketid=?";
        $result = $adb->mquery($sql, array($ticketid));
        for($i=0;$i<$adb->num_rows($result);$i++)
        {
                $comment = $adb->query_result($result,$i,'comments');
                if($comment != '')
                {
                        $commentlist .= '<br><br>'.$comment;
                }
        }
        if($commentlist != '')
                $commentlist = '<br><br> The comments are : '.$commentlist;

        $log->debug("Exiting getTicketComments method ...");
        return $commentlist;
}

function getTicketDetails($id,$whole_date)
{
	 global $adb,$mod_strings;
	 if($whole_date['mode'] == 'edit')
	 {
		$reply = $mod_strings["replied"];
		$temp = "Re : ";
	 }
	 else	
	 {
		$reply = $mod_strings["created"];
		$temp = " ";
	 }
	
	 $desc = $mod_strings['Ticket ID'] .' : '.$id.'<br> Ticket Title : '. $temp .' '.$whole_date['sub'];
	 $desc .= "<br><br>".$mod_strings['Hi']." ". $whole_date['parent_name'].",<br><br>".$mod_strings['LBL_PORTAL_BODY_MAILINFO']." ".$reply." ".$mod_strings['LBL_DETAIL']."<br>";
	 $desc .= "<br>".$mod_strings['Ticket No']." : ".$whole_date['ticketno'];
	 $desc .= "<br>".$mod_strings['Status']." : ".$whole_date['status'];
	 $desc .= "<br>".$mod_strings['Category']." : ".$whole_date['category'];
	 $desc .= "<br>".$mod_strings['Severity']." : ".$whole_date['severity'];
	 $desc .= "<br>".$mod_strings['Priority']." : ".$whole_date['priority'];
	 $desc .= "<br><br>".$mod_strings['Description']." : <br>".$whole_date['description'];
	 $desc .= "<br><br>".$mod_strings['Solution']." : <br>".$whole_date['solution'];
	 $desc .= getTicketComments($id);

	 $sql = "SELECT * FROM vtiger_ticketcf WHERE ticketid = ?";
	 $result = $adb->mquery($sql, array($id));
	 $cffields = $adb->getFieldsArray($result);
	 foreach ($cffields as $cfOneField)
	 {
		 if ($cfOneField != 'ticketid')
		 {
			 $cfData = $adb->query_result($result,0,$cfOneField);
			 $sql = "SELECT fieldlabel FROM vtiger_field WHERE columnname = ? and vtiger_field.presence in (0,2)";
			 $cfLabel = $adb->query_result($adb->mquery($sql,array($cfOneField)),0,'fieldlabel');
			 $desc .= '<br><br>'.$cfLabel.' : <br>'.$cfData;
		 }
	 }
	 // end of contribution
	 $desc .= '<br><br><br>';
	 $desc .= '<br>'.$mod_strings["LBL_REGARDS"].',<br>'.$mod_strings["LBL_TEAM"].'.<br>';
	 return $desc;

}

function getPortalInfo_Ticket($id,$title,$contactname,$portal_url)
{
	global $mod_strings;
	$bodydetails =$mod_strings['Dear']." ".$contactname.",<br><br>";
        $bodydetails .= $mod_strings['reply'].' <b>'.$title.'</b>'.$mod_strings['customer_portal'];
        $bodydetails .= $mod_strings["link"].'<br>';
        $bodydetails .= $portal_url;
        $bodydetails .= '<br><br>'.$mod_strings["Thanks"].'<br><br>'.$mod_strings["Support_team"];
	return $bodydetails;
}

/**
 * This function is used to get a random password.
 * @return a random password with alpha numeric chanreters of length 8
 */
function makeRandomPassword()
{
	global $log;
	$log->debug("Entering makeRandomPassword() method ...");
	$salt = "abcdefghijklmnopqrstuvwxyz0123456789";
	srand((double)microtime()*1000000);
	$i = 0;
	while ($i <= 7)
	{
		$num = rand() % 33;
		$tmp = substr($salt, $num, 1);
		$pass = $pass . $tmp;
		$i++;
	}
	$log->debug("Exiting makeRandomPassword method ...");
	return $pass;
}

//added to get mail info for portal user
//type argument included when when addin customizable tempalte for sending portal login details
function getmail_contents_portalUser($request_array,$password,$type='')
{
	global $mod_strings ,$adb;

	$subject = $mod_strings['Customer Portal Login Details'];

	//here id is hardcoded with 5. it is for support start notification in vtiger_notificationscheduler

	$query='select vtiger_emailtemplates.subject,vtiger_emailtemplates.body from vtiger_notificationscheduler inner join vtiger_emailtemplates on vtiger_emailtemplates.templateid=vtiger_notificationscheduler.notificationbody where schedulednotificationid=5';

	$result = $adb->mquery($query, array());
	$body=$adb->query_result($result,0,'body');
	$contents=$body;
	$contents = str_replace('$contact_name$',$request_array['first_name']." ".$request_array['last_name'],$contents);
	$contents = str_replace('$login_name$',$request_array['email'],$contents);
	$contents = str_replace('$password$',$password,$contents);
	$contents = str_replace('$URL$',$request_array['portal_url'],$contents);
	$contents = str_replace('$support_team$',$mod_strings['Support Team'],$contents);
	$contents = str_replace('$logo$','<img src="cid:logo" />',$contents);

	if($type == "LoginDetails")
	{
		$temp=$contents;
		$value["subject"]=$adb->query_result($result,0,'subject');
		$value["body"]=$temp;
		return $value;
	}

	return $contents;

}

/**
 * Function to get the UItype for a field.
 * Takes the input as $module - module name,and columnname of the field
 * returns the uitype, integer type
 */
function getUItype($module,$columnname)
{
        global $log;
        $log->debug("Entering getUItype(".$module.") method ...");
		$tabIdList = array();
		//To find tabid for this module
		$tabIdList[] = getTabid($module);
        global $adb;
		if($module == 'Calendar') {
			$tabIdList[] = getTabid('Events');
		}
        $sql = "select uitype from vtiger_field where tabid IN (".generateQuestionMarks($tabIdList).
				") and columnname=?";
        $result = $adb->mquery($sql, array($tabIdList, $columnname));
        $uitype =  $adb->query_result($result,0,"uitype");
        $log->debug("Exiting getUItype method ...");
        return $uitype;

}

// This function looks like not used anymore. May have to be removed
function is_emailId($entity_id)
{
	global $log,$adb;
	$log->debug("Entering is_EmailId(".$entity_id.") method");

	$module = getSalesEntityType($entity_id);
	if($module == 'Contacts')
	{
		$sql = "select email,yahooid from vtiger_contactdetails inner join vtiger_crmentity on vtiger_crmentity.crmid = vtiger_contactdetails.contactid where contactid = ?";
		$result = $adb->mquery($sql, array($entity_id));
		$email1 = $adb->query_result($result,0,"email");
		$email2 = $adb->query_result($result,0,"yahooid");
		if($email1 != "" || $email2 != "") {
			$check_mailids = "true";
		} else {
			$check_mailids = "false";
		}
	}
	elseif($module == 'Leads')
	{
		$sql = "select email,yahooid from vtiger_leaddetails inner join vtiger_crmentity on vtiger_crmentity.crmid = vtiger_leaddetails.leadid where leadid = ?";
		$result = $adb->mquery($sql, array($entity_id));
		$email1 = $adb->query_result($result,0,"email");
		$email2 = $adb->query_result($result,0,"yahooid");
		if($email1 != "" || $email2 != "") {
			$check_mailids = "true";
		} else {
			$check_mailids = "false";
		}
	}
	$log->debug("Exiting is_EmailId() method ...");
	return $check_mailids;
}

/**
 * This function is used to get cvid of default "all" view for any module.
 * @return a cvid of a module
 */
function getCvIdOfAll($module)
{
	global $adb,$log;
	$log->debug("Entering getCvIdOfAll($module)");
	$qry_res = $adb->mquery("select cvid from vtiger_customview where viewname='All' and entitytype=?", array($module));
	$cvid = $adb->query_result($qry_res,0,"cvid");
	$log->debug("Exiting getCvIdOfAll($module)");
	return $cvid;


}

/** gives the option  to display  the tagclouds or not for the current user
 ** @param $id -- user id:: Type integer
 ** @returns true or false in $tag_cloud_view
 ** Added to provide User based Tagcloud
 **/

function getTagCloudView($id=""){
	global $log;
	global $adb;
	$log->debug("Entering in function getTagCloudView($id)");
	if($id == ''){
		$tag_cloud_status =1;
	}else{
		$query = "select visible from vtiger_homestuff where userid=? and stufftype='Tag Cloud'";
		$tag_cloud_status = $adb->query_result($adb->mquery($query, array($id)),0,'visible');
	}

	if($tag_cloud_status == 0){
		$tag_cloud_view='true';
	}else{
		$tag_cloud_view='false';
	}
	
	$log->debug("Exiting from function getTagCloudView($id)");
	return $tag_cloud_view;
}

/** Stores the option in database to display  the tagclouds or not for the current user
 ** @param $id -- user id:: Type integer
 ** Added to provide User based Tagcloud
 **/
function SaveTagCloudView($id="")
{
	global $log;
	global $adb;
	$log->debug("Entering in function SaveTagCloudView($id)");
	$tag_cloud_status=$_REQUEST['tagcloudview'];

	if($tag_cloud_status == "true"){
		$tag_cloud_view = 0;
	}else{
		$tag_cloud_view = 1;
	}

	if($id == ''){
		$tag_cloud_view =1;
	}else{
		$query = "update vtiger_homestuff set visible = ? where userid=? and stufftype='Tag Cloud'";
		$adb->mquery($query, array($tag_cloud_view,$id));
	}

	$log->debug("Exiting from function SaveTagCloudView($id)");
}

/**     function used to change the Type of Data for advanced filters in custom view and Reports
 **     @param string $table_name - tablename value from field table
 **     @param string $column_nametable_name - columnname value from field table
 **     @param string $type_of_data - current type of data of the field. It is to return the same TypeofData 
 **            if the  field is not matched with the $new_field_details array.
 **     return string $type_of_data - If the string matched with the $new_field_details array then the Changed
 **	       typeofdata will return, else the same typeofdata will return.
 **
 **     EXAMPLE: If you have a field entry like this:
 **
 ** 		fieldlabel         | typeofdata | tablename            | columnname       |
 **	        -------------------+------------+----------------------+------------------+
 **		Potential Name     | I~O        | vtiger_quotes        | potentialid      |
 **
 **     Then put an entry in $new_field_details  like this: 
 **	
 **				"vtiger_quotes:potentialid"=>"V",
 **
 **	Now in customview and report's advance filter this field's criteria will be show like string.
 **
 **/
function ChangeTypeOfData_Filter($table_name,$column_name,$type_of_data)
{
	global $adb,$log;
	//$log->debug("Entering function ChangeTypeOfData_Filter($table_name,$column_name,$type_of_data)");
	$field=$table_name.":".$column_name;
	//Add the field details in this array if you want to change the advance filter field details

	$new_field_details = Array(
		
		//Contacts Related Fields
		"vtiger_contactdetails:accountid"=>"V",
		"vtiger_contactsubdetails:birthday"=>"D",
		"vtiger_contactdetails:email"=>"V",
		"vtiger_contactdetails:yahooid"=>"V",
		
		//Potential Related Fields
		"vtiger_potential:campaignid"=>"V",

		//Account Related Fields
		"vtiger_account:parentid"=>"V",
		"vtiger_account:email1"=>"V",
		"vtiger_account:email2"=>"V",

		//Lead Related Fields
		"vtiger_leaddetails:email"=>"V",
		"vtiger_leaddetails:yahooid"=>"V",

		//Documents Related Fields
		"vtiger_senotesrel:crmid"=>"V",

		//Calendar Related Fields
		"vtiger_seactivityrel:crmid"=>"V",
		"vtiger_seactivityrel:contactid"=>"V",
		"vtiger_recurringevents:recurringtype"=>"V",
	
		//HelpDesk Related Fields
		"vtiger_troubletickets:parent_id"=>"V",
		"vtiger_troubletickets:product_id"=>"V",
		
		//Product Related Fields
		"vtiger_products:discontinued"=>"C",
		"vtiger_products:vendor_id"=>"V",
		"vtiger_products:handler"=>"V",
		"vtiger_products:parentid"=>"V",
		
		//Faq Related Fields
		"vtiger_faq:product_id"=>"V",
		
		//Vendor Related Fields
		"vtiger_vendor:email"=>"V",

		//Quotes Related Fields
		"vtiger_quotes:potentialid"=>"V",
		"vtiger_quotes:inventorymanager"=>"V",
		"vtiger_quotes:accountid"=>"V",
		
		//Purchase Order Related Fields
		"vtiger_purchaseorder:vendorid"=>"V",
		"vtiger_purchaseorder:contactid"=>"V",
		
		//SalesOrder Related Fields
		"vtiger_salesorder:potentialid"=>"V",
		"vtiger_salesorder:quoteid"=>"V",
		"vtiger_salesorder:contactid"=>"V",
		"vtiger_salesorder:accountid"=>"V",
		
		//Invoice Related Fields
		"vtiger_invoice:salesorderid"=>"V",
		"vtiger_invoice:contactid"=>"V",
		"vtiger_invoice:accountid"=>"V",
		
		//Campaign Related Fields
		"vtiger_campaign:product_id"=>"V",

		//Related List Entries(For Report Module)
		"vtiger_activityproductrel:activityid"=>"V",
		"vtiger_activityproductrel:productid"=>"V",

		"vtiger_campaigncontrel:campaignid"=>"V",
		"vtiger_campaigncontrel:contactid"=>"V",

		"vtiger_campaignleadrel:campaignid"=>"V",
		"vtiger_campaignleadrel:leadid"=>"V",

		"vtiger_cntactivityrel:contactid"=>"V",
		"vtiger_cntactivityrel:activityid"=>"V",

		"vtiger_contpotentialrel:contactid"=>"V",
		"vtiger_contpotentialrel:potentialid"=>"V",

		"vtiger_crmentitynotesrel:crmid"=>"V",
		"vtiger_crmentitynotesrel:notesid"=>"V",

		"vtiger_leadacctrel:leadid"=>"V",
		"vtiger_leadacctrel:accountid"=>"V",
		
		"vtiger_leadcontrel:leadid"=>"V",
		"vtiger_leadcontrel:contactid"=>"V",
		
		"vtiger_leadpotrel:leadid"=>"V",
		"vtiger_leadpotrel:potentialid"=>"V",
		
		"vtiger_pricebookproductrel:pricebookid"=>"V",
		"vtiger_pricebookproductrel:productid"=>"V",
		
		"vtiger_seactivityrel:crmid"=>"V",
		"vtiger_seactivityrel:activityid"=>"V",
		
		"vtiger_senotesrel:crmid"=>"V",
		"vtiger_senotesrel:notesid"=>"V",
		
		"vtiger_seproductsrel:crmid"=>"V",
		"vtiger_seproductsrel:productid"=>"V",
		
		"vtiger_seticketsrel:crmid"=>"V",
		"vtiger_seticketsrel:ticketid"=>"V",
		
		"vtiger_vendorcontactrel:vendorid"=>"V",
		"vtiger_vendorcontactrel:contactid"=>"V",
		
		"vtiger_pricebook:currency_id"=>"V",
        "vtiger_service:handler"=>"V",		
	);

	//If the Fields details does not match with the array, then we return the same typeofdata
	if(isset($new_field_details[$field]))
	{
		$type_of_data = $new_field_details[$field];
	}
	//$log->debug("Exiting function with the typeofdata: $type_of_data ");
	return $type_of_data;
}


/** Returns the URL for Basic and Advance Search
 ** Added to fix the issue 4600
 **/
function getBasic_Advance_SearchURL()
{

	$url = '';
	if($_REQUEST['searchtype'] == 'BasicSearch')
	{
		$url .= (isset($_REQUEST['query']))?'&query='.vtlib_purify($_REQUEST['query']):'';
		$url .= (isset($_REQUEST['search_field']))?'&search_field='.vtlib_purify($_REQUEST['search_field']):'';
		$url .= (isset($_REQUEST['search_text']))?'&search_text='.to_html(vtlib_purify($_REQUEST['search_text'])):'';
		$url .= (isset($_REQUEST['searchtype']))?'&searchtype='.vtlib_purify($_REQUEST['searchtype']):'';
		$url .= (isset($_REQUEST['type']))?'&type='.vtlib_purify($_REQUEST['type']):'';
	}
	if ($_REQUEST['searchtype'] == 'advance')
	{
		$url .= (isset($_REQUEST['query']))?'&query='.vtlib_purify($_REQUEST['query']):'';
		$count=$_REQUEST['search_cnt'];
		for($i=0;$i<$count;$i++)
		{
			$url .= (isset($_REQUEST['Fields'.$i]))?'&Fields'.$i.'='.stripslashes(str_replace("'","",vtlib_purify($_REQUEST['Fields'.$i]))):'';
			$url .= (isset($_REQUEST['Condition'.$i]))?'&Condition'.$i.'='.vtlib_purify($_REQUEST['Condition'.$i]):'';
			$url .= (isset($_REQUEST['Srch_value'.$i]))?'&Srch_value'.$i.'='.to_html(vtlib_purify($_REQUEST['Srch_value'.$i])):'';
		}
		$url .= (isset($_REQUEST['searchtype']))?'&searchtype='.vtlib_purify($_REQUEST['searchtype']):'';
		$url .= (isset($_REQUEST['search_cnt']))?'&search_cnt='.vtlib_purify($_REQUEST['search_cnt']):'';
		$url .= (isset($_REQUEST['matchtype']))?'&matchtype='.vtlib_purify($_REQUEST['matchtype']):'';
	}
	return $url;

}

/** Clear the Smarty cache files(in Smarty/smarty_c)
 ** This function will called after migration.
 **/
function clear_smarty_cache($path=null) {

	global $root_directory;
	if($path == null) {
		$path=$root_directory.'Smarty/templates_c/';
	}
	$mydir = @opendir($path);
	while(false !== ($file = readdir($mydir))) {
		if($file != "." && $file != ".." && $file != ".svn") {
			//chmod($path.$file, 0777);
			if(is_dir($path.$file)) {
				chdir('.');
				clear_smarty_cache($path.$file.'/');
				//rmdir($path.$file) or DIE("couldn't delete $path$file<br />"); // No need to delete the directories.
			}
			else {
				// Delete only files ending with .tpl.php
				if(strripos($file, '.tpl.php') == (strlen($file)-strlen('.tpl.php'))) {
					unlink($path.$file) or DIE("couldn't delete $path$file<br />");
				}
			}
		}
	}
	@closedir($mydir);
}

/** Get Smarty compiled file for the specified template filename.
 ** @param $template_file Template filename for which the compiled file has to be returned.
 ** @return Compiled file for the specified template file.
 **/
function get_smarty_compiled_file($template_file, $path=null) {

	global $root_directory;
	if($path == null) {
		$path=$root_directory.'Smarty/templates_c/';
	}
	$mydir = @opendir($path);
	$compiled_file = null;
	while(false !== ($file = readdir($mydir)) && $compiled_file == null) {
		if($file != "." && $file != ".." && $file != ".svn") {
			//chmod($path.$file, 0777);
			if(is_dir($path.$file)) {
				chdir('.');
				$compiled_file = get_smarty_compiled_file($template_file, $path.$file.'/');
				//rmdir($path.$file) or DIE("couldn't delete $path$file<br />"); // No need to delete the directories.
			}
			else {
				// Check if the file name matches the required template fiel name
				if(strripos($file, $template_file.'.php') == (strlen($file)-strlen($template_file.'.php'))) {
					$compiled_file = $path.$file;
				}
			}
		}
	}
	@closedir($mydir);	
	return $compiled_file;
}

/** Function to carry out all the necessary actions after migration */
function perform_post_migration_activities() {
	//After applying all the DB Changes,Here we clear the Smarty cache files
	clear_smarty_cache();
	//Writing tab data in flat file
	create_tab_data_file();
	create_parenttab_data_file();
}

/** Function To create Email template variables dynamically -- Pavani */
function getEmailTemplateVariables(){
	global $adb;
	$modules_list = array('Accounts', 'Contacts', 'Leads', 'Users');
	$allOptions = array();
	foreach($modules_list as $index=>$module){
		if($module == 'Calendar') {
			$focus = new Activity();
		} else {
			$focus = new $module();
		}
		$field=array();
		$tabid=getTabid($module);
		//many to many relation information field campaignrelstatus(this is the column name of the
		//field) has block set to '0', which should be ignored.
		$result=$adb->mquery("select fieldlabel,columnname,displaytype from vtiger_field where tabid=? and vtiger_field.presence in (0,2) and displaytype in (1,2,3) and block !=0",array($tabid));
		$norows = $adb->num_rows($result);
		if($norows > 0){
			for($i=0;$i<$norows;$i++){
				$field =$adb->query_result($result,$i,'fieldlabel');
				$columnname=$adb->query_result($result,$i,'columnname');
				if($columnname=='support_start_date' || $columnname=='support_end_date'){
						$tabname='vtiger_customerdetails';
				}
				$option=array(getTranslatedString($module).': '.getTranslatedString($adb->query_result($result,$i,'fieldlabel')),"$".strtolower($module)."-".$columnname."$");
				$allFields = array(); 
				$allFields[] = $option;
			}
		}
		
		$allOptions[] = $allFields;
		$allFields="";
	}
	$option=array('Current Date','$custom-currentdate$');
	$allFields = array(); 
	$allFields[] = $option;
	$option=array('Current Time','$custom-currenttime$');
	$allFields = array(); //CL: 3.1.150
	$allFields[] = $option;
	$allOptions[] = $allFields;
	return $allOptions;
}

/** Function to get picklist values for the given field that are accessible for the given role.
 *  @ param $tablename picklist fieldname.
 *  It gets the picklist values for the given fieldname
 *  	$fldVal = Array(0=>value,1=>value1,-------------,n=>valuen)
 *  @return Array of picklist values accessible by the user.	
 */
function getPickListValues($tablename,$roleid)
{
	global $adb;
	$query = "select $tablename from vtiger_$tablename inner join vtiger_role2picklist on vtiger_role2picklist.picklistvalueid = vtiger_$tablename.picklist_valueid where roleid=? and picklistid in (select picklistid from vtiger_picklist) order by sortid";
	$result = $adb->mquery($query, array($roleid));
	$fldVal = Array();
	while($row = $adb->fetch_array($result))
	{
		$fldVal []= $row[$tablename];
	}
	return $fldVal;
}

/** Function to check the file access is made within web root directory. */
function checkFileAccess($filepath) {
	global $root_directory;
	// Set the base directory to compare with
	$use_root_directory = $root_directory;
	if(empty($use_root_directory)) {
		$use_root_directory = realpath(dirname(__FILE__).'/../../.');
	}

	$realfilepath = realpath($filepath);

	/** Replace all \\ with \ first */
	$realfilepath = str_replace('\\\\', '\\', $realfilepath);
	$rootdirpath  = str_replace('\\\\', '\\', $use_root_directory);

	/** Replace all \ with / now */
	$realfilepath = str_replace('\\', '/', $realfilepath);
	$rootdirpath  = str_replace('\\', '/', $rootdirpath);
	
	/* if(stripos($realfilepath, $rootdirpath) !== 0) {
		die("Sorry! Attempt to access restricted file.");
	} */
}

/** Function to get the ActivityType for the given entity id
 *  @param entityid : Type Integer
 *  return the activity type for the given id
 */
function getActivityType($id)
{
	global $adb;
	$quer = "select activitytype from vtiger_activity where activityid=?";
	$res = $adb->mquery($quer, array($id));
	$acti_type = $adb->query_result($res,0,"activitytype");
	return $acti_type;
}

/** Function to get owner name either user or group */
function getOwnerName($id)
{
	global $adb, $log;
	$log->debug("Entering getOwnerName(".$id.") method ...");
	$log->info("in getOwnerName ".$id);

	$ownerList = getOwnerNameList(array($id));
	return $ownerList[$id];
}

/** Function to get owner name either user or group */
function getOwnerNameList($idList) {
	global $log;

	if(!is_array($idList) || count($idList) == 0) {
		return array();
	}

	$nameList = array();
	$db = PearDatabase::getInstance();
	$sql = "select user_name,id from vtiger_users where id in (".generateQuestionMarks($idList).")";
	$result = $db->mquery($sql, $idList);
	$it = new SqlResultIterator($db, $result);
	foreach ($it as $row) {
		$nameList[$row->id] = $row->user_name;
	}
	$groupIdList = array_diff($idList, array_keys($nameList));
	if(count($groupIdList) > 0) {
		$sql = "select groupname,groupid from vtiger_groups where groupid in (".
				generateQuestionMarks($groupIdList).")";
		$result = $db->mquery($sql, $groupIdList);
		$it = new SqlResultIterator($db, $result);
		foreach ($it as $row) {
			$nameList[$row->groupid] = $row->groupname;
		}
	}
	return $nameList;
}

/**
 * This function is used to get the blockid of the settings block for a given label.
 * @param $label - settings label
 * @return string type value
 */
function getSettingsBlockId($label) {
	global $log, $adb;
	$log->debug("Entering getSettingsBlockId(".$label.") method ...");
	
	$blockid = '';
	$query = "select blockid from vtiger_settings_blocks where label = ?";
	$result = $adb->mquery($query, array($label));
	$noofrows = $adb->num_rows($result);
	if($noofrows == 1) {
		$blockid = $adb->query_result($result,0,"blockid");
	}
	$log->debug("Exiting getSettingsBlockId method ...");
	return $blockid;
}

// Function to check if the logged in user is admin
// and if the module is an entity module
// and the module has a Settings.php file within it
function isModuleSettingPermitted($module){
	if (file_exists("modules/$module/Settings.php") &&
		isPermitted('Settings','index','') == 'yes') {
			
			return 'yes';
	}
	return 'no';
}

/**
 * this function returns the entity field name for a given module; for e.g. for Contacts module it return concat(lastname, ' ', firstname)
 * @param string $module - the module name
 * @return string $fieldsname - the entity field name for the module
 */
function getEntityField($module){
	global $adb;
	$data = array();	
	if(!empty($module)){
		 $query = "select fieldname,tablename,entityidfield from vtiger_entityname where modulename = ?";
		 $result = $adb->mquery($query, array($module));
		 $fieldsname = $adb->query_result($result,0,'fieldname');
		 $tablename = $adb->query_result($result,0,'tablename'); 
		 $entityidfield = $adb->query_result($result,0,'entityidfield'); 
		 if(!(strpos($fieldsname,',') === false)){
			 $fieldlists = explode(',',$fieldsname);
			 $fieldsname = "concat(";
			 $fieldsname = $fieldsname.implode(",' ',",$fieldlists);
			 $fieldsname = $fieldsname.")";
		 }
	}
	$data = array("tablename"=>$tablename, "fieldname"=>$fieldsname);
	return $data;
}

// vtiger cache utility
require_once('include/utils/VTCacheUtils.php');

// vtlib customization: Extended vtiger CRM utlitiy functions
require_once('include/utils/VtlibUtils.php');
// END

function vt_suppressHTMLTags($string){
	return preg_replace(array('/</', '/>/', '/"/'), array('&lt;', '&gt;', '&quot;'), $string);
}

function vt_hasRTE() {
	global $FCKEDITOR_DISPLAY, $USE_RTE;
	return ((!empty($FCKEDITOR_DISPLAY) && $FCKEDITOR_DISPLAY == 'true') ||
			(!empty($USE_RTE) && $USE_RTE == 'true'));
}


function getXMLString($moduleX,$rec=false,$idstr,$xmlrelmod,$recId='-1')
{   
//        if($idstr!='')
//            $fpx = fopen('C:\wamp\www\3.txt', 'w');
//        fwrite($fpx, '1'.$idstr.'-');
        global $adb;
        if ($xmlrelmod != "1")
            global $XML_PAGINATION_COUNT;
        else
            $XML_PAGINATION_COUNT = "10000";
        
        $tabRes=$adb->mquery("SELECT tabid,tablename,entityidfield FROM vtiger_entityname where modulename=?",array($moduleX));
        
        $tabid=$adb->query_result($tabRes,0,'tabid');
        $moduletablename=$adb->query_result($tabRes,0,'tablename');
        $moduletableid=$adb->query_result($tabRes,0,'entityidfield');
        
//        fwrite($fpx, $idstr);
        
        //$query1="SELECT CONCAT('crmid',',',GROUP_CONCAT(columnname)) FROM vtiger_field WHERE tablename in ('".$moduletablename."cf','".$moduletablename."') AND xmlsendtable = '1' group by tabid ";
        $query1="SELECT 'crmid' UNION SELECT CONCAT(tablename,'.',columnname) FROM vtiger_field WHERE tablename in ('".$moduletablename."cf','".$moduletablename."') AND xmlsendtable = '1'";
        
	$params = array();
	$idleResult = $adb->mquery($query1,$params);
	$noofdata = $adb->num_rows($idleResult);
        
        $Trname=$adb->mquery("SELECT transaction_rel_table,transaction_name,billaddtable,shipaddtable,profirldname,relid,uom FROM sify_tr_rel WHERE transaction_name =?",array($moduleX));
        if($adb->num_rows($Trname)>0)
        {
            $billaddtable=$adb->query_result($Trname,0,'billaddtable');
            $shipaddtable=$adb->query_result($Trname,0,'shipaddtable');
            
            $col_Name=$adb->mquery("SELECT GROUP_CONCAT(columnname) as 'columnname' FROM vtiger_field WHERE tablename in ('".$billaddtable."','".$shipaddtable."') AND xmlsendtable = '1'",array());
            $colName=$adb->query_result($col_Name,0,'columnname');
            
            if($billaddtable != "" || $shipaddtable != "")
            {
                $Bill_Essfield=$adb->mquery("SELECT relfieldname FROM sify_tr_relid WHERE reltable = ?",array($billaddtable));
                $BillEssfield=$adb->query_result($Bill_Essfield,0,'relfieldname');
                $ship_Essfield=$adb->mquery("SELECT relfieldname FROM sify_tr_relid WHERE reltable = ?",array($shipaddtable));
                $shipEssfield=$adb->query_result($ship_Essfield,0,'relfieldname');
            }
        }
	              
        if($noofdata>0){
            //$output_Field=$adb->query_result($idleResult,0,0);
            $outputField_dum = "";
            for ($index3 = 0; $index3 < $noofdata; $index3++) {
                $outputField=$adb->query_result($idleResult,$index3,0);
                if ($outputField_dum=="")
                    $outputField_dum = $outputField;
                else
                    $outputField_dum = $outputField.','.$outputField_dum;   
            }
            $output_Field = $outputField_dum;
            
            if($adb->num_rows($Trname)>0)
            {
                if($billaddtable != "" || $shipaddtable != "")
                {
                    $output_Field .= ",".$colName;
                }
            }
            $fieldarr = split(",", $output_Field);
            $fieldarrlen = sizeof($fieldarr);
        }
      
      if($output_Field=='')
         return '';
        
      $query="SELECT  
                $output_Field
                FROM $moduletablename
                INNER JOIN  ".$moduletablename."cf on ".$moduletablename."cf.".$moduletableid."=".$moduletablename.".".$moduletableid." 
                INNER JOIN vtiger_crmentity on vtiger_crmentity.crmid=".$moduletablename.".".$moduletableid." AND vtiger_crmentity.deleted=0";
      
      if($adb->num_rows($Trname)>0)
      {
          if($billaddtable != "" || $shipaddtable != "")
          {
            $query.= " INNER JOIN ".$billaddtable." on ".$moduletablename.".".$moduletableid."=".$billaddtable.".".$BillEssfield."
                  INNER JOIN ".$shipaddtable." on ".$moduletablename.".".$moduletableid."=".$shipaddtable.".".$shipEssfield."";
          }
      }
      if($recId!='-1')
      {
          $query.=" and {$moduletablename}.{$moduletableid}=".$recId;
      } 
      if($idstr!='')
      {
          $query.=" and {$moduletablename}.{$moduletableid} in (".$idstr.")";
      } 
          
//      echo $query."<br>";
//           fwrite($fpx, $query."-----<br>");
	$params = array();
	$idleResult = $adb->mquery($query,$params);
	$noofdataco = $adb->num_rows($idleResult);
        
        $doc = new DOMDocument( );
        
        $xmlstring="";$xmlstringarray = array();
        
        //$xmlstring.=$query;
        //$xmlstring.="---".$recId."----";
        
//        fwrite($fpx, $noofdataco);
        $k=0;
        for ($i=0;$i<$noofdataco; $i++)
        {
           if($i%$XML_PAGINATION_COUNT==0 && $i>0){
               $xmlstringarray[$k]=$xmlstring;
               $k++;
               $xmlstring = "";
           }
            if($rec)
                $xmlstring.='<'.$moduletablename.'>';
            
            for ($j=0; $j<$fieldarrlen; $j++)
            {
                $field_name = explode(".", $fieldarr[$j]);
                $field_namelen = sizeof($field_name);
                if ($field_namelen != 1)
                    $fieldname = $field_name[1];
                else
                    $fieldname = $field_name[0];
                
//                echo $field_namelen."   ==>  ".$fieldarr[$j]."  ==>  ".$fieldname."<br>";
                
                //$noofdatavalue = $adb->query_result($idleResult,$i,$fieldarr[$j]);
                $noofdatavalue = $adb->query_result($idleResult,$i,$fieldname);
                
                //$xmlstring.="SELECT fieldid,uitype FROM vtiger_field where fieldname='{$fieldarr[$j]}' and tabid=$tabid";
                
                $fTypeRes;
                
                //$_SESSION['1']='1';

                $fTypeRes=$adb->mquery("SELECT fieldid,uitype FROM vtiger_field where columnname=?",array($fieldname));
                
                $fieldId='';
                $uiType='';
                
                if($adb->num_rows($fTypeRes)>0)
                {
                    $fieldId=$adb->query_result($fTypeRes,0,'fieldid');
                    $uiType=$adb->query_result($fTypeRes,0,'uitype');
                }  
                
                //$xmlstring.=$fieldarr[$j].':'.$fieldId.":".$uiType;
                
                if(($uiType=='10' || $uiType=='81') && $rec==true)
                {
                    $fmrelRes=$adb->mquery("SELECT relmodule FROM vtiger_fieldmodulerel where fieldid=?",array($fieldId));
                    
                    if($adb->num_rows($fmrelRes)>0 && $noofdatavalue!='')
                    {
                        $relModule=$adb->query_result($fmrelRes,0,'relmodule');
                        $innString=getXMLString($relModule,false,'',$xmlrelmod,$noofdatavalue);
                        //$xmlstring.="<".$fieldarr[$j].">".$innString[0]."</".$fieldarr[$j].">";
                        $xmlstring.="<".$fieldname.">".$innString[0]."</".$fieldname.">";
                    }
                    else
                    {
                        //$xmlstring.="<".$fieldarr[$j].">".$noofdatavalue."</".$fieldarr[$j].">";
                        $xmlstring.="<".$fieldname.">".$noofdatavalue."</".$fieldname.">";
                    }    
                    
                }
                elseif($uiType=='117' && $rec==true)
                {
                    $currquery = "SELECT id,currency_code FROM vtiger_currency_info WHERE id =".$noofdatavalue;
                    $params = array();
                    $currResult = $adb->mquery($currquery,$params);
                    $relCurr=$adb->query_result($currResult,0,'currency_code');
                    $currency_id=$adb->query_result($currResult,0,'id');
                        $xmlstring.="<currency_id>";
                        $xmlstring.="<id>".$currency_id."</id>";
                        $xmlstring.="<currency_code>".$relCurr."</currency_code>";
                        $xmlstring.="</currency_id>";
                    
                }   
                else
                {
                    //$xmlstring.="<".$fieldarr[$j].">".$noofdatavalue."</".$fieldarr[$j].">";
                    $xmlstring.="<".$fieldname.">".$noofdatavalue."</".$fieldname.">";
                }    
                    
            }
            
            //$Trname=$adb->mquery("SELECT transaction_rel_table,transaction_name FROM sify_tr_rel WHERE transaction_name =?",array($moduleX));
            
            if($adb->num_rows($Trname)>0)
            {
                $TranTblName=$adb->query_result($Trname,0,'transaction_rel_table');
                $TraName=$adb->query_result($Trname,0,'transaction_name');
                $profirldname=$adb->query_result($Trname,0,'profirldname');
                $relid=$adb->query_result($Trname,0,'relid');
                $Refuom=$adb->query_result($Trname,0,'uom');
                
                $Trprorel=$adb->mquery("SELECT columnname FROM sify_tr_grid_field WHERE tablename = ? AND xmlsendtable = '1'",array($TranTblName));
                
                if($adb->num_rows($Trprorel)>0)
                {
                    $trfields = array();
                    for ($index4 = 0; $index4 < $adb->num_rows($Trprorel); $index4++) 
                    {
                        $tr_fields=$adb->query_result($Trprorel,$index4,0);
                        $trfields[] = $tr_fields;
                    }
                }
                    $piId=$adb->query_result($idleResult,$i,'crmid');
                    $piFieldsArray=$trfields;
                    $liQuery = "select ";
                    if ($profirldname != "")
                        $liQuery .= " case when vtiger_xproduct.xproductid != '' then vtiger_xproduct.qtyinstock else 'NA' end as qtyinstock,";
                    $liQuery .= $TranTblName.".* ";
                    if ($Refuom != "")
                        $liQuery .= " ,vtiger_uom.uomname";    
                    $liQuery .= " from ".$TranTblName."";
                    if($profirldname != "")
                    {
                        $liQuery .= " left join vtiger_xproduct on vtiger_xproduct.xproductid=".$TranTblName.".".$profirldname."";
                        $liQuery .= " left join vtiger_service on vtiger_service.serviceid=".$TranTblName.".".$profirldname.""; 
                    }
                    if ($Refuom != "")
                        $liQuery .= " left join vtiger_uom on vtiger_uom.uomid=".$TranTblName.".".$Refuom."";
                    $liQuery .= " where ".$relid."=? ";
                    if ($relid == "id")
                        $liQuery .= " ORDER BY sequence_no";
                    
//                    $fpx = fopen('C:\wamp\www\13.txt', 'w');
//                    fwrite($fpx, $liQuery);
//                    fwrite($fpx, $piId);
//                    fclose($fpx);
                    
                    $xmlstring.="<lineitems>";

                    $poLIResults=$adb->mquery($liQuery,array($piId));

                    for ($index = 0; $index < $adb->num_rows($poLIResults); $index++) {
                         $xmlstring.="<".$TranTblName.">";                   

                         for ($index1 = 0; $index1 < count($piFieldsArray); $index1++) {
                             $fName=$piFieldsArray[$index1];
                             $fValue=$adb->query_result($poLIResults,$index,$fName);
                             //echo $fName.":".$fValue."<br/>";
                             //echo is_numeric($fValue);

                             $xmlstring.="<".$fName.">";

                             if(($fName=='tuom' && is_numeric($fValue)) || ($fName=='uom' && is_numeric($fValue)))
                             {
                                 $is=getXMLString('UOM',false,'',$xmlrelmod,$fValue);
                                 $xmlstring.=$is[0];
                             }
                             elseif(($fName=='productid' && is_numeric($fValue)) || ($fName=='stockid' && is_numeric($fValue)))
                             {
                                 $Product=getXMLString('xProduct',false,'',$xmlrelmod,$fValue);
                                 $xmlstring.=$Product[0];
                             }
                             elseif($fName=='refid' && is_numeric($fValue))
                             {
                                 $refidquery=$adb->mquery("SELECT relmodule,reltable,GROUP_CONCAT(relfieldname) as 'relfieldname' FROM sify_tr_relid WHERE `module` = ? and relmodule <> ''",array($moduleX));
                                 
                                 if($adb->num_rows($refidquery)>0)
                                 {
                                     $relmodule=$adb->query_result($refidquery,0,relmodule);
                                     $reltable=$adb->query_result($refidquery,0,reltable);
                                     $relfieldname=$adb->query_result($refidquery,0,relfieldname);
                                     
                                     $entityidfieldquery = $adb->mquery("SELECT tabid,tablename,entityidfield FROM vtiger_entityname where modulename = ?",array($relmodule));
                                     $entityidfield=$adb->query_result($entityidfieldquery,0,2);
                                 
                                    $poquery = "SELECT ".$relfieldname." FROM ".$reltable." WHERE ".$entityidfield." =".$fValue;
                                    $params = array();
                                    $POResult = $adb->mquery($poquery,$params);
                                    
                                    $fieldname = explode(",", $relfieldname);
                                                                       
                                    for ($index5 = 0; $index5 < count($fieldname); $index5++) {
                                        $fieldna=$adb->query_result($POResult,0,$fieldname[$index5]);
                                        $xmlstring.="<".$fieldname[$index5].">".$fieldna."</".$fieldname[$index5].">";
                                    }
                                 }
                             }
                             else
                             {
                                 $xmlstring.=$fValue;                        
                             }  

                             $xmlstring.="</".$fName.">";
                         }

                         //$xmlstring.="<tax>";

                         $liId=$adb->query_result($poLIResults,$index,'productid');
                            if($moduleX=='xSalesOrder'){
                                    $taxTable = 'sify_xtransaction_tax_rel_so AS sify_xtransaction_tax_rel ';
                            }elseif($moduleX=='SalesInvoice'){
                                    $taxTable = 'sify_xtransaction_tax_rel_si AS sify_xtransaction_tax_rel ';
                            }elseif($moduleX=='xSalesReturn'){
                                    $taxTable = 'sify_xtransaction_tax_rel_sr AS sify_xtransaction_tax_rel ';
                            }elseif($moduleX=='PurchaseOrder'){
                                    $taxTable = 'sify_xtransaction_tax_rel_po AS sify_xtransaction_tax_rel ';
                            }elseif($moduleX=='PurchaseInvoice'){
                                    $taxTable = 'sify_xtransaction_tax_rel_pi AS sify_xtransaction_tax_rel ';
                            }elseif($moduleX=='xrPurchaseInvoice'){
                                    $taxTable = 'sify_xtransaction_tax_rel_rpi AS sify_xtransaction_tax_rel ';
                            }elseif($moduleX=='xPurchaseReturn'){
                                    $taxTable = 'sify_xtransaction_tax_rel_pr AS sify_xtransaction_tax_rel ';
                            }elseif($moduleX=='xServiceInvoice'){
                                    $taxTable = 'sify_xtransaction_tax_rel_service AS sify_xtransaction_tax_rel ';
                            }elseif($moduleX=='xrSalesInvoice'){
                                    $taxTable = ' sify_xtransaction_tax_rel_rsi AS sify_xtransaction_tax_rel ';
                            }else{
                                 $taxTable = ' sify_xtransaction_tax_rel AS sify_xtransaction_tax_rel ';
                            }
                         $taxQuery="SELECT 
                                    CASE WHEN vtiger_xtax.xtaxid is NOT NULL THEN 'xtax' ELSE 'xcomponent' END AS `taxtype`,
                                    CASE WHEN vtiger_xtax.xtaxid is NOT NULL THEN vtiger_xtax.xtaxid ELSE vtiger_xcomponent.xcomponentid END AS `taxid`,
                                    CASE WHEN vtiger_xtax.xtaxid is NOT NULL THEN vtiger_xtax.taxcode ELSE vtiger_xcomponent.componentcode END AS `taxcode`,
                                    CASE WHEN vtiger_xtax.xtaxid is NOT NULL THEN vtiger_xtax.taxdescription ELSE vtiger_xcomponent.componentdescription END AS `taxdescription`,
                                    sify_xtransaction_tax_rel.tax_percentage as `taxpercentage`,
                                    CASE WHEN vtiger_xtax.xtaxid is NOT NULL THEN '' ELSE vtiger_xcomponentcf.cf_xcomponent_applicable_on END AS `tax_appliableon`,
                                    CASE WHEN vtiger_xtax.xtaxid is NOT NULL THEN '' ELSE vtiger_xcomponentcf.cf_xcomponent_component_for END AS `tax_compfor`
                                    FROM  $taxTable 
                                    left join vtiger_xtax on vtiger_xtax.taxcode=sify_xtransaction_tax_rel.tax_type
                                    left join  vtiger_xcomponent on vtiger_xcomponent.componentcode=sify_xtransaction_tax_rel.tax_type
                                    left join vtiger_xcomponentcf on vtiger_xcomponentcf.xcomponentid=vtiger_xcomponent.xcomponentid
                                    where transaction_id=? and lineitem_id=?";

                         $taxResult=$adb->mquery($taxQuery,array($piId,$liId));



                         for ($index2 = 1; $index2 <= 3; $index2++) {
                             $xmlstring.="<tax".$index2.">";

                                if($adb->num_rows($taxResult)>=$index2)
                                {
                                   $xmlstring.="<taxcode>".$adb->query_result($taxResult,$index2-1,2)."</taxcode>";
                                   $xmlstring.="<taxdescription>".$adb->query_result($taxResult,$index2-1,3)."</taxdescription>";
                                   $xmlstring.="<taxpercentage>".$adb->query_result($taxResult,$index2-1,4)."</taxpercentage>";
                                   $xmlstring.="<taxappliableon>".$adb->query_result($taxResult,$index2-1,5)."</taxappliableon>";
                                   $xmlstring.="<taxcomponentfor>".$adb->query_result($taxResult,$index2-1,6)."</taxcomponentfor>";
                                }    
                             $xmlstring.="</tax".$index2.">";
                         }

                         //$xmlstring.="</tax>";

                         $xmlstring.="</".$TranTblName.">";                   
                    }

                    $xmlstring.="</lineitems>";
//                }
            
            }
            if($rec)
                $xmlstring.='</'.$moduletablename.'>';            
        }
        $xmlstringarray[$k]=$xmlstring;
        
        $query = "UPDATE vtiger_crmentity SET sendstatus = '0' WHERE setype = '".$moduleX."' AND sendstatus = '1'";
        $params = array();
        $adb->mquery($query,$params);
//        if($idstr!='')
//            fclose($fpx);
        return $xmlstringarray;
}

function getObjFrXML($modname,$doc,$xpath,$parent='',$tempfilename='')
{
    global $adb,$LBL_XMLReceiveAllMasters,$root_directory,$SENDRECEIVELOG,$LBL_AUTO_RSI_TO_SI,$ALLOW_GST_TRANSACTION,$LBL_AUTO_RSO_TO_SO,$LBL_XMLReceiveUpdate,$LBL_RSO_SAVE_PRO_CATE,$LBL_RPI_VENDOR_VALID,$LBL_VALIDATE_RPI_PROD_CODE;
    
    $autorsitosiquery = $adb->mquery("SELECT `value` FROM `sify_inv_mgt_config` WHERE `treatment` = 'sap' AND `key` = 'LBL_AUTO_RSI_TO_SI' LIMIT 1");
    $resConfigRSItoSI = $adb->query_result($autorsitosiquery,0,'value');
    $LBL_AUTO_RSI_TO_SI = $resConfigRSItoSI;
	
    $rpi_vendor = $adb->mquery("SELECT `value` FROM `sify_inv_mgt_config` WHERE `treatment` = 'sap' AND `key` = 'LBL_RPI_VENDOR_VALID' LIMIT 1");
    $resConfigRPIVendor = $adb->query_result($rpi_vendor,0,'value');
    $LBL_RPI_VENDOR_VALID = $resConfigRPIVendor;
    
	 $subRetailerConfigquery = $adb->mquery("SELECT `value` FROM `sify_inv_mgt_config` WHERE `treatment` = 'sap' AND `key` = 'LBL_VALIDATE_RPI_PROD_CODE' LIMIT 1");
     $subRetConfigRSOtoSO = $adb->query_result($subRetailerConfigquery,0,'value');
     $LBL_VALIDATE_RPI_PROD_CODE = $subRetConfigRSOtoSO;
    
    $autorsotosoquery = $adb->mquery("SELECT `key` as lablename,`value` as lablevalue FROM `sify_inv_mgt_config` WHERE `treatment` = 'sap' AND `key` in('LBL_AUTO_RSO_TO_SO','LBL_RSO_SAVE_PRO_CATE') ");
	$configcont = $adb->num_rows($autorsotosoquery);
	$config_data = array();
	if($configcont){
		for ($mc = 0; $mc < $configcont; $mc++) {
			$config_data[] = $adb->raw_query_result_rowdata($autorsotosoquery,$mc);  
		}
	}$outstanding = 1;
	if(!empty($config_data)){
		foreach($config_data as $key => $configData){
			${$configData['lablename']} = $configData['lablevalue']; // FRPRDINXT-14729								
		}
	}
    $gsttransactionquery = $adb->mquery("SELECT `value` FROM `sify_inv_mgt_config` WHERE `treatment` = 'VersionConfiguration' AND `key` = 'ALLOW_GST_TRANSACTION' LIMIT 1");
    $resConfigGST= $adb->query_result($gsttransactionquery,0,'value');
    $ALLOW_GST_TRANSACTION = $resConfigGST;
    
    $LBL_XMLReceiveUpdate_query = $adb->mquery("SELECT `value` FROM `sify_inv_mgt_config` WHERE `treatment` = 'sap' AND `key` = 'LBL_XMLReceiveUpdate' LIMIT 1");
    $XMLReceiveUpdate_rel= $adb->query_result($LBL_XMLReceiveUpdate_query,0,'value');
    $LBL_XMLReceiveUpdate = $XMLReceiveUpdate_rel;
	
    $Resulrpatth1_dir = $root_directory.'storage/log/rlog';
    if(!is_dir($Resulrpatth1_dir))
        mkdir($Resulrpatth1_dir, 0700);
    
    if ($SENDRECEIVELOG == 'True')
        $Resulrpatth1 = $root_directory.'storage/log/rlog/log_XMLReceive_'.date("Ymd_H_i_s_").microtime(true).'.txt';
    
    $log = "User: ".$_SERVER['REMOTE_ADDR'].' - '.date("Ymd_H_i_s_").microtime(true).PHP_EOL;
    file_put_contents($Resulrpatth1, $log, FILE_APPEND);
   
    $Resulrpatth ='';
    
    if($tempfilename!='')
        $Resulrpatth=$tempfilename;
    else        
        $Resulrpatth = $root_directory."storage/receiveresult.txt";
    
    $fpx = fopen($Resulrpatth, 'w');
    
    $tabRes=$adb->mquery("SELECT tabid,tablename,entityidfield,modulename FROM vtiger_entityname where modulename=?",array($modname));
    $tabcount = $adb->num_rows($tabRes);
    
    if($tabcount>0)
    {
        $moduletablename=$adb->query_result($tabRes,0,'tablename');
        $modulename=$adb->query_result($tabRes,0,'modulename');
        
        $modname = $modulename;
    
        $focus=  CRMEntity::getInstance($modname);
    
        $fieldQuery = "SELECT fieldid,columnname,uitype,typeofdata,vtiger_tab.`name` FROM vtiger_field 
                        INNER JOIN vtiger_tab on vtiger_tab.tabid=vtiger_field.tabid AND vtiger_field.xmlreceivetable = '1'
                        WHERE tablename in ('".$moduletablename."','".$moduletablename."cf')";
    
        $logmn = "modulename : ".$modulename.PHP_EOL;
        $logmn .= "moduletablename : ".$moduletablename.PHP_EOL;
        file_put_contents($Resulrpatth1, $logmn, FILE_APPEND);
        //$log .= "fieldQuery : ".$fieldQuery.PHP_EOL;
    
        $params = array();
        $fieldResult = $adb->mquery($fieldQuery,$params);
        $fieldcount = $adb->num_rows($fieldResult);
        $tabNameM='';
     
        $xmlLength=1;
   
        if($parent==''){
            $xmllen = 'collections/'.$moduletablename;
             $xmlentries=$xpath->query($xmllen,$doc);
             $xmlLength = $xmlentries->length;
        }
   
        $logxl = "xmlLength : ".$xmlLength.PHP_EOL;
        file_put_contents($Resulrpatth1, $logxl, FILE_APPEND);

        // Stert For Log Purpose, getting fromid and transactionid
        $docidpath = 'collections/'.$moduletablename.'docinfo/transactionid';
        $entries1=$xpath->query($docidpath,$doc);
        foreach ($entries1 as $entry) {
            $docid = $entry->nodeValue;
        }
        $fromidpath = 'collections/'.$moduletablename.'docinfo/fromid';
        $entries_fromid=$xpath->query($fromidpath,$doc);
        foreach ($entries_fromid as $entry) {
            $fromid = $entry->nodeValue;
        }
		if($fromid == 'EVER1301'){
			$fromidpath = 'collections/'.$moduletablename.'docinfo/clientid';
			$entries_fromid=$xpath->query($fromidpath,$doc);
			foreach ($entries_fromid as $entry) {
				$fromid = $entry->nodeValue;
			}
		}
        $doccreateddatepath = 'collections/'.$moduletablename.'docinfo/createddate';
        $entries_doccreateddate=$xpath->query($doccreateddatepath,$doc);
        foreach ($entries_doccreateddate as $entry) {
            $doccreated_date = $entry->nodeValue;
            $doccreateddate = date("Y-m-d", strtotime($doccreated_date));
        }
        $sourceapplicationpath = 'collections/'.$moduletablename.'docinfo/sourceapplication';
        $entries_sourceapplication=$xpath->query($sourceapplicationpath,$doc);
        foreach ($entries_sourceapplication as $entry) {
            $sourceapplication = $entry->nodeValue;
        }
        $destapplicationpath = 'collections/'.$moduletablename.'docinfo/destapplication';
        $entries_destapplication=$xpath->query($destapplicationpath,$doc);
        foreach ($entries_destapplication as $entry) {
            $destapplication = $entry->nodeValue;
        }
        $prkeypath = 'collections/prkey';
        $entries_prkey=$xpath->query($prkeypath,$doc);
        foreach ($entries_prkey as $entry) {
            $prkey = $entry->nodeValue;
        }
		
        $logprk = "prkey : ".$prkey.PHP_EOL;
		$logprk .= "XML Fromid Value : ".$fromid.PHP_EOL;
        $logprk .= "End For Log Purpose, getting fromid and transactionid".PHP_EOL;
        file_put_contents($Resulrpatth1, $logprk, FILE_APPEND);

        for ($index = 1; $index <= $xmlLength; $index++) {
			$distid_mod = array('xrSalesOrder','xrCollection','xrSalesReturn');
			if(in_array($modname,$distid_mod)){
				$logco = "XML Fromid Value : ".$fromid.PHP_EOL;
				$fromid = '';
				$moduldistfield = array('xrSalesOrder' => 'cf_xrso_seller_id','xrCollection' => 'cf_xrco_distributor','xrSalesReturn' => 'xdistributorid');
				$dist_colname = $moduldistfield[$modname];
				$doccreateddatedistpath = 'collections/'.$moduletablename.'['.$index.']/'.$dist_colname;
				$entries_fromid=$xpath->query($doccreateddatedistpath,$doc);
				foreach ($entries_fromid as $entry) {
					$fromid = $entry->nodeValue;
				}
				$logco .= "new dist path : ".$doccreateddatedistpath.PHP_EOL;
				$logco .= "columnname : ".$dist_colname.PHP_EOL;
				$logco .= "Node Value : ".$fromid.PHP_EOL;
				file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
			}

           if($fieldcount>0){
               for ($index1 = 0; $index1 < $fieldcount; $index1++) {
                   $fieldid=$adb->query_result($fieldResult,$index1,'fieldid');
                   $columnname=$adb->query_result($fieldResult,$index1,'columnname');
                   $uitype=$adb->query_result($fieldResult,$index1,'uitype');
                   $tabName=$adb->query_result($fieldResult,$index1,'name');
                   $typeofdata=$adb->query_result($fieldResult,$index1,'typeofdata');
                   $mandarray = explode("~", $typeofdata);
                   $mand = $mandarray['1'];

                   $tabNameM=$tabName;
                   $columnname = trim($columnname, " ");

                   $query='collections/';

                       if($parent!='')
                       {
                          $query.=$parent.'/'.$columnname; 
                       }
                       else
                       {
                           $RPIquery =$query.$moduletablename.'['.$index.']';
                           $query.=$moduletablename.'['.$index.']/'.$columnname;
                       }    

                       $subparentName=$parent.'/'.$moduletablename.'['.$index.']/'.$columnname;

                   if($columnname=='crmid')
                   {
                       continue;
                   }
                   $logco = "XMLPath : ".$query.PHP_EOL;
                   $logco .= "columnname : ".$columnname.PHP_EOL;
                   $logco .= "uitype : ".$uitype.PHP_EOL;
                   $logco .= "Mandi : ".$mand.PHP_EOL;
                   file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                   
                   if($modname == 'xrPurchaseInvoice' || $modname == 'xrSalesInvoice' || $modname == 'xrSalesOrder' || $modname == 'xRetailer' || $modname == 'xrSalesReturn' || $modname == 'xrCollection')
                   {
                       if ($columnname == 'subject')
                       {
                           $quer = 'collections/';
                            $entries=$xpath->query($query,$doc);
                            foreach ($entries as $entry)
                            {
                                $nodevalueX = $entry->nodeValue;
                            }
                            $logco = "-----Transaction Detail----------".PHP_EOL;
                            $logco .= "modname : ".$modname.PHP_EOL;
                            $logco .= "XML Path : ".$RPIquery.PHP_EOL;
                            $logco .= "columnname : ".$columnname.PHP_EOL;
                            $logco .= "Node Value : ".$nodevalueX.PHP_EOL;
                            $logco .= "-----End Transaction Detail----------".PHP_EOL;
                            file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                            
                            $subjectVal = $nodevalueX;
							
							$xdepotid ='';
							$vendorid='';
							$cf_purchaseinvoice_purchase_invoice_date='';
							$seller_id='';
                            if($LBL_RPI_VENDOR_VALID == 'True')
							{
								if($modname == 'xrPurchaseInvoice')
								{   

									$depotcodepath = $RPIquery.'/cf_purchaseinvoice_depot/depotcode';
									$entries_depotcode=$xpath->query($depotcodepath,$doc);
									foreach ($entries_depotcode as $entry) {
										$depotcode = $entry->nodeValue;
										
									}
									
									
									$vendornamepath = $RPIquery.'/vendorid/vendorname';
									$entries_vendorname=$xpath->query($vendornamepath,$doc);
									foreach ($entries_vendorname as $entry) {
										$vendorname = $entry->nodeValue;
										
									}
									
									$vendorRes =$adb->mquery("SELECT vendorid FROM `vtiger_vendor` where vendor_no=?",array($vendorname));
									$vendorid=$adb->query_result($vendorRes,0,'vendorid');
									
									$depotcodeRes =$adb->mquery("SELECT xdepotid FROM `vtiger_xdepot` WHERE `depotcode`=?",array($depotcode));
									$xdepotid=$adb->query_result($depotcodeRes,0,'xdepotid');
									
                                    $pidatepath = $RPIquery.'/cf_purchaseinvoice_purchase_invoice_date';
									$entries_pidatepath=$xpath->query($pidatepath,$doc);
									foreach ($entries_pidatepath as $entry) {
										$cf_purchaseinvoice_purchase_invoice_date = $entry->nodeValue;
										
									}
									
									
									$sellerpath = $RPIquery.'/cf_purchaseinvoice_buyer_id';
									$entries_sellerpath=$xpath->query($sellerpath,$doc);
									foreach ($entries_sellerpath as $entry) {
										$seller_id = $entry->nodeValue;
										
									}
									//$logco = "depotcode : ".$depotcode1.PHP_EOL;
									$logco = "depotcode : ".$xdepotid.PHP_EOL;
									$logco .= "vendorname : ".$vendorid.PHP_EOL;
                                    $logco .= "pi date : ".$cf_purchaseinvoice_purchase_invoice_date.PHP_EOL;
									$logco .= "seller_id : ".$seller_id.PHP_EOL;
                                                                        
									file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
							
								}
						 }
                            
                           $exisObjIds = getExistingObjectValue($modname, $RPIquery, $doc, $xpath, $EOVparent='1','subject',$fromid,'','',$Resulrpatth1,$xdepotid,$vendorid,$cf_purchaseinvoice_purchase_invoice_date,$seller_id);
                            
                           $logco = "Transaction Subject Available :".$exisObjIds.PHP_EOL;
                            file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                           
                            if ($exisObjIds != '')
                           {
                                if ($modname == 'xrPurchaseInvoice')
                                {
                                    $dist_id_query = $RPIquery.'/cf_purchaseinvoice_buyer_id';
                                    $distid=$xpath->query($dist_id_query,$doc);
                                    foreach ($distid as $entry)
                                    {
                                        $nodevalueX_distid = $entry->nodeValue;
                                    }
                                    
                                    $fromid = $nodevalueX_distid;
                                    
                                    $forum_code_query = $RPIquery.'/forum_code';
                                    $forum_code=$xpath->query($forum_code_query,$doc);
                                    foreach ($forum_code as $entry)
                                    {
                                        $nodevalueX_forum_code = $entry->nodeValue;
                                    }
                                    if($nodevalueX_forum_code != '')
                                    {
                                        $distdet = GetDistributorDetailFromForumCode($nodevalueX_forum_code);
                                        if($distdet != '')
                                            $fromid = $distdet['distributorcode'];
                                    }
                                }
                               $FailReason = $nodevalueX." Already Available In Application (".$query.")";
                               $insertstatus = '200';
                               $statuscode = 'FN2010';
                               $statusmsg = 'Rejected since Order already available';
                               if(empty($subjectVal)){
                                   $statuscode = 'FN8211';
                                   $statusmsg = 'Invalid Reference Number / Empty Reference Number';
                               }
                               $logco = $modname.PHP_EOL;
                               $logco .= $statusmsg.PHP_EOL;
                               file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                               sendreceiveaudit($docid, 'Receive', 'Failed', $FailReason, '', $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                               fwrite($fpx, $insertstatus."\n");
                               continue 2;
                           }
                           
                       }
                       if ($columnname == 'internal_ref_no')
                       {
                           $quer = 'collections/';
                            $entries=$xpath->query($query,$doc);
                            foreach ($entries as $entry)
                            {
                                $nodevalueX = $entry->nodeValue;
                            }
                            $logco = "-----xRetailer----------".PHP_EOL;
                            $logco .= "modname : ".$modname.PHP_EOL;
                            $logco .= "XML Path : ".$RPIquery.PHP_EOL;
                            $logco .= "columnname : ".$columnname.PHP_EOL;
                            $logco .= "Node Value : ".$nodevalueX.PHP_EOL;
                            $logco .= "-----End xRetailer----------".PHP_EOL;
                            file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                            
                            if($nodevalueX != '')
                            {
                                $exisObjIds = getExistingObjectValue($modname, $RPIquery, $doc, $xpath, $EOVparent='1','internal_ref_no',$fromid,'','',$Resulrpatth1);

                                $logco = "Retailer Available :".$exisObjIds.PHP_EOL;
                                file_put_contents($Resulrpatth1, $logco, FILE_APPEND);

                                if ($exisObjIds != '')
                                {
                                    $FailReason = $nodevalueX." Already Available In Application (".$query.")";
                                    $insertstatus = '200';
                                    $statuscode = 'FN2010';
                                    $statusmsg = 'Rejected since already available';
                                    sendreceiveaudit($docid, 'Receive', 'Failed', $FailReason, '', $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                                    fwrite($fpx, $insertstatus."\n");
                                    continue 2;
                                }
                            }
                       }
                       if ($columnname == 'rsrcode')
                       {
                           $quer = 'collections/';
                            $entries=$xpath->query($query,$doc);
                            foreach ($entries as $entry)
                            {
                                $nodevalueX = $entry->nodeValue;
                            }
                            $logco = "-----xrSalesReturn----------".PHP_EOL;
                            $logco .= "modname : ".$modname.PHP_EOL;
                            $logco .= "XML Path : ".$RPIquery.PHP_EOL;
                            $logco .= "columnname : ".$columnname.PHP_EOL;
                            $logco .= "Node Value : ".$nodevalueX.PHP_EOL;
                            $logco .= "-----End xrSalesReturn----------".PHP_EOL;
                            file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                            
                            if($nodevalueX != '')
                            {
                                $exisObjIds = getExistingObjectValue($modname, $RPIquery, $doc, $xpath, $EOVparent='1','rsrcode',$fromid,'','',$Resulrpatth1);

                                $logco = "SalesReturn Available :".$exisObjIds.PHP_EOL;
                                file_put_contents($Resulrpatth1, $logco, FILE_APPEND);

                                if ($exisObjIds != '')
                                {
                                    $insertstatus = '200';
                                    $statuscode = 'FN2010';
                                    $statusmsg = 'Rejected since Order already available';
                                    $FailReason = $nodevalueX." Already Available In Application (".$query.")";
                                    sendreceiveaudit($docid, 'Receive', 'Failed', $FailReason, '', $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                                    fwrite($fpx, $insertstatus."\n");
                                    continue 2;
                                }
                            }
                       }
                       if ($columnname == 'collectioncode')
                       {
                           $quer = 'collections/';
                            $entries=$xpath->query($query,$doc);
                            foreach ($entries as $entry)
                            {
                                $nodevalueX = $entry->nodeValue;
                            }
                            $logco = "-----xrCollection----------".PHP_EOL;
                            $logco .= "modname : ".$modname.PHP_EOL;
                            $logco .= "XML Path : ".$RPIquery.PHP_EOL;
                            $logco .= "columnname : ".$columnname.PHP_EOL;
                            $logco .= "Node Value : ".$nodevalueX.PHP_EOL;
                            $logco .= "-----End xrCollection----------".PHP_EOL;
                            file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                            
                            if($nodevalueX != '')
                            {
                                $exisObjIds = getExistingObjectValue($modname, $RPIquery, $doc, $xpath, $EOVparent='1','collectioncode',$fromid,'','',$Resulrpatth1);

                                $logco = "Collection Available :".$exisObjIds.PHP_EOL;
                                file_put_contents($Resulrpatth1, $logco, FILE_APPEND);

                                if ($exisObjIds != '')
                                {
                                    $FailReason = $nodevalueX." Already Available In Application (".$query.")";
                                    $statuscode = 'FN2010';
                                    $statusmsg = 'Rejected since Order already available';
                                    sendreceiveaudit($docid, 'Receive', 'Failed', $FailReason, '', $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$statuscode,$statusmsg);
                                    $insertstatus = '200';
                                    fwrite($fpx, $insertstatus."\n");
                                    continue 2;
                                }
                            }
                       }
                   }
                   
                   if ($modname == 'xTransactionSeries')
                   {
                       
                        $logco = "-----xTransactionSeries----------".PHP_EOL;
                        $logco .= "modname : ".$modname.PHP_EOL;
                        $logco .= "XML Path : ".$RPIquery.PHP_EOL;
                        $logco .= "columnname : ".$columnname.PHP_EOL;
                        $logco .= "Node Value : ".$nodevalueX.PHP_EOL;
                         
                       $transactionseriesid_query = $RPIquery.'/xtransactionseriesid';
                       $transactionseriesid=$xpath->query($transactionseriesid_query,$doc);
                       foreach ($transactionseriesid as $entry)
                       {
                           $nodevalueX_transactionseriesid = $entry->nodeValue;
                       }
                       
                       $logco .= "xtransactionseriesid : ".$nodevalueX_transactionseriesid.PHP_EOL;
                       
                       $current_value_query = $RPIquery.'/cf_xtransactionseries_reset_current_value';
                       $current_value=$xpath->query($current_value_query,$doc);
                       foreach ($current_value as $entry)
                       {
                           $nodevalueX_current_value = $entry->nodeValue;
                       }
                       
                       $logco .= "current_value : ".$nodevalueX_current_value.PHP_EOL;
                       
                       if ($nodevalueX_current_value != '')
                       {
                           $Tupdate = "UPDATE vtiger_xtransactionseriescf SET cf_xtransactionseries_current_value = ? WHERE xtransactionseriesid = ?";
                            $params1 = array($nodevalueX_current_value,$nodevalueX_transactionseriesid);
                            $adb->mquery($Tupdate,$params1);
                            $logco .= "UPDATE : ".$Tupdate.PHP_EOL;
                            $logco .= "Input Array : ".  print_r($params1, true).PHP_EOL;
                       }
                       
                       $logco .= "-----End xTransactionSeries----------".PHP_EOL;
                       file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                       
                       $FailReason = $current_value_query ." Transaction Successes-Update ";
                       $statuscode = 'FN2010';
                       $statusmsg = 'Rejected since Order already available';
                       sendreceiveaudit($docid, 'Receive', 'Successes-Update', $FailReason, $nodevalueX_transactionseriesid, $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                       $insertstatus = '200';
                       fwrite($fpx, $insertstatus."\n");
                       continue 2;
                   }
                   
                   if($uitype!= '10' && $uitype!='81')
                   {
                       //if($LBL_XMLReceiveUpdate == 'True')
                       //{    
                            if($mand == 'MU')
                            {
                                $mainexisObjId = getExistingObjectValue($modname, $query, $doc, $xpath, $EOVparent='',$prkey,$fromid,$mand,$columnname,$Resulrpatth1);
                                
                                $entries=$xpath->query($query,$doc);
                                foreach ($entries as $entry)
                                {
                                    $MUnodevalueX = $entry->nodeValue;
                                }

                                $logco = "Existing Data value : ".$mainexisObjId.PHP_EOL;
                                $logco .= "Config_Cal : ".$LBL_XMLReceiveUpdate.PHP_EOL;
                                 file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                            }
                            if($mainexisObjId == "")
                            {
                                $focus->mode = '';
                                $focus->id = '';
                                $mod = '';
                            }
                            ELSE
                            {
                                if($LBL_XMLReceiveUpdate == 'True')
                                {
                                    //$focus->retrieve_entity_info($mainexisObjId,$modname);
                                    $focus->id = $mainexisObjId;
                                    $focus->mode = 'edit';
                                    $mod = 'edit';
                                }
                                else
                                {
                                    
                                    $FailReason = $MUnodevalueX." Already Available In Application (".$query.")";
                                    $insertstatus = '200';
                                    $statuscode = 'FN2010';
                                    $statusmsg = 'Rejected since master already available';
                                                                                                          
                                    $logco = "Already Available In Application (".$query.")".PHP_EOL;
                                    file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                                                                        
                                    sendreceiveaudit($docid, 'Receive', 'Failed', $FailReason, $mainexisObjId, $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                                    fwrite($fpx, $insertstatus."\n");
                                    continue 2;
                                }
                            }
                       //}
                       //else
                       //{
                       //    $mainexisObjId = getExistingObjectValue($modname, $query, $doc, $xpath, $EOVparent='',$prkey,$fromid,$mand,$columnname);
                       //}
                        
                       //if($mainexisObjId == "")
                       //{
                           if ($uitype!= '117'){
                               $currquery = 'collections/'.$moduletablename.'['.$index.']'.'/currency_id/currency_code';
                               $Currentries=$xpath->query($currquery,$doc);
                               foreach ($Currentries as $entry) {
                                   $currency_code = $entry->nodeValue;
                               }

                               $currencyQuery = "SELECT id,currency_code FROM vtiger_currency_info WHERE currency_code ='".$currency_code."'";

                               $params = array();
                               $currResult = $adb->mquery($currencyQuery,$params);
                               $relCurr=$adb->query_result($currResult,0,'id');

                               $focus->column_fields[''.$columnname.'']=$relCurr;
                           }                        

                           $entries=$xpath->query($query,$doc);
                           foreach ($entries as $entry) {
                               $valueX = $entry->nodeValue;
                               $lognv = "nodeValue : ".$valueX.PHP_EOL;
                               file_put_contents($Resulrpatth1, $lognv, FILE_APPEND);
                               
                               if($valueX == "")
                               {
                                    if ($mand == 'M' || $mand == 'MU')
                                    {
                                        if ($modname == 'xrPurchaseInvoice')
                                        {
                                            $dist_id_query = $RPIquery.'/cf_purchaseinvoice_buyer_id';
                                            $distid=$xpath->query($dist_id_query,$doc);
                                            foreach ($distid as $entry)
                                            {
                                                $nodevalueX_distid = $entry->nodeValue;
                                            }

                                            $fromid = $nodevalueX_distid;
                                            
                                            $forum_code_query = $RPIquery.'/forum_code';
                                            $forum_code=$xpath->query($forum_code_query,$doc);
                                            foreach ($forum_code as $entry)
                                            {
                                                $nodevalueX_forum_code = $entry->nodeValue;
                                            }
                                            if($nodevalueX_forum_code != '')
                                            {
                                                $distdet = GetDistributorDetailFromForumCode($nodevalueX_forum_code);
                                                if($distdet != '')
                                                    $fromid = $distdet['distributorcode'];
                                            }
                                        }
                                        $FailReason = $valueX." Manditory Field Should Not Empty (".$query.")";
                                        $statuscode = 'FN2010';
                                        $statusmsg = 'Rejected since Order already available';
                                        if(empty($subjectVal)){
                                            $statuscode = 'FN8211';
                                            $statusmsg = 'Invalid Reference Number / Empty Reference Number';
                                        }
                                        sendreceiveaudit($docid, 'Receive', 'Failed', $FailReason, '', $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                                        $insertstatus = '100';
                                        fwrite($fpx, $insertstatus."\n");
                                        continue 3;
                                    }
                                    else
                                    {
                                        $focus->column_fields[''.$columnname.'']=$valueX;
                                    }
                               }
                               else
                               {
                                   $focus->column_fields[''.$columnname.'']=$valueX;
                               }
                           }
                       }
                   else {
                       $logui = "if UIType 10".PHP_EOL;
                       $logui .= "fieldid : ".$fieldid.PHP_EOL;
                       file_put_contents($Resulrpatth1, $logui, FILE_APPEND);
                       $relRes=$adb->mquery("SELECT vtiger_fieldmodulerel.relmodule FROM 
                               vtiger_fieldmodulerel where fieldid=".$fieldid);
                       $relModule=$adb->query_result($relRes,0,0);
                       
                       if($sourceapplication == 'merp' && $relModule == 'xVan')
                           $relModule = 'xGodown';

                       if($modname==$relModule)
                       {
                           if($modname!='xProdHier' && $modname!='xOrganisationHier'){
							continue;
						   }
                       } 
                       $logXP = "XMLPath : ".$query.PHP_EOL;
                       file_put_contents($Resulrpatth1, $logXP, FILE_APPEND);
                       
                       if($modname == 'xrSalesOrder' && $columnname == 'buyerid')
                       {
                            $customer_type_query = $RPIquery.'/customer_type';
                            $customer_type=$xpath->query($customer_type_query,$doc);
                            foreach ($customer_type as $entry)
                            {
                                $nodevalueX_customertype = $entry->nodeValue;
                            }

                            if($nodevalueX_customertype == '1')
                                $relModule = 'xReceiveCustomerMaster';
                            if($nodevalueX_customertype == '2')
                                $relModule = 'xsubretailer';

                            $logg = "RSO Customer Type Value : ".$nodevalueX_customertype.PHP_EOL;
                            $logg .= "Related Module : ".$relModule.PHP_EOL;
                            file_put_contents($Resulrpatth1, $logg, FILE_APPEND);
                       }
                       
                       if($modname == 'xrSalesReturn' && $columnname == 'cf_xrsr_customer')
                       {
                            $customer_type_query = $RPIquery.'/customer_type';
                            $customer_type=$xpath->query($customer_type_query,$doc);
                            foreach ($customer_type as $entry)
                            {
                                $nodevalueX_customertype = $entry->nodeValue;
                            }

                            if($nodevalueX_customertype == '1')
                                $relModule = 'xReceiveCustomerMaster';
                            if($nodevalueX_customertype == '2')
                                $relModule = 'xsubretailer';

                            $logg = "RSR Customer Type Value : ".$nodevalueX_customertype.PHP_EOL;
                            $logg .= "Related Module : ".$relModule.PHP_EOL;
                            file_put_contents($Resulrpatth1, $logg, FILE_APPEND);
                       }
                       
                       if($modname == 'xrCollection' && ($columnname == 'cf_xrco_customer_id' || $columnname == 'cf_xrco_customer_name'))
                       {
                            $customer_type_query = $RPIquery.'/customer_type';
                            $customer_type=$xpath->query($customer_type_query,$doc);
                            foreach ($customer_type as $entry)
                            {
                                $nodevalueX_customertype = $entry->nodeValue;
                            }

                            if($nodevalueX_customertype == '1')
                                $relModule = 'xReceiveCustomerMaster';
                            if($nodevalueX_customertype == '2')
                                $relModule = 'xsubretailer';

                            $logg = "RCO Customer Type Value : ".$nodevalueX_customertype.PHP_EOL;
                            $logg .= "Related Module : ".$relModule.PHP_EOL;
                            file_put_contents($Resulrpatth1, $logg, FILE_APPEND);
                       }
                       
                       if($modname == 'xrSalesInvoice' && $columnname == 'vendor_id')
                       {
                            $customer_type_query = $RPIquery.'/customer_type';
                            $customer_type=$xpath->query($customer_type_query,$doc);
                            foreach ($customer_type as $entry)
                            {
                                $nodevalueX_customertype = $entry->nodeValue;
                            }

                            if($nodevalueX_customertype == '1')
                                $relModule = 'xReceiveCustomerMaster';
                            if($nodevalueX_customertype == '2')
                                $relModule = 'xsubretailer';

                            $logg = "RSI Customer Type Value : ".$nodevalueX_customertype.PHP_EOL;
                            $logg .= "Related Module : ".$relModule.PHP_EOL;
                            file_put_contents($Resulrpatth1, $logg, FILE_APPEND);
                       }
                       
                       if($modname == 'xSurvey' && $columnname == 'cf_retailer')
                       {
                            $customer_type_query = $RPIquery.'/customer_type';
                            $customer_type=$xpath->query($customer_type_query,$doc);
                            foreach ($customer_type as $entry)
                            {
                                $nodevalueX_customertype = $entry->nodeValue;
                            }

                            if($nodevalueX_customertype == '1')
                                $relModule = 'xReceiveCustomerMaster';
                            if($nodevalueX_customertype == '2')
                                $relModule = 'xsubretailer';

                            $logg = "Survey Customer Type Value : ".$nodevalueX_customertype.PHP_EOL;
                            $logg .= "Related Module : ".$relModule.PHP_EOL;
                            file_put_contents($Resulrpatth1, $logg, FILE_APPEND);
                       }
                       
                       if($modname == 'xStockEntry' && $columnname == 'vendorid')
                       {
                            $customer_type_query = $RPIquery.'/customer_type';
                            $customer_type=$xpath->query($customer_type_query,$doc);
                            foreach ($customer_type as $entry)
                            {
                                $nodevalueX_customertype = $entry->nodeValue;
                            }

                            if($nodevalueX_customertype == '1')
                                $relModule = 'xReceiveCustomerMaster';
                            if($nodevalueX_customertype == '2')
                                $relModule = 'xsubretailer';

                            $logg = "StockEntry Customer Type Value : ".$nodevalueX_customertype.PHP_EOL;
                            $logg .= "Related Module : ".$relModule.PHP_EOL;
                            file_put_contents($Resulrpatth1, $logg, FILE_APPEND);
                       }
                       
                       if($modname == 'xReasonNotTake' && $columnname == 'xretailerid')
                       {
                            $customer_type_query = $RPIquery.'/customer_type';
                            $customer_type=$xpath->query($customer_type_query,$doc);
                            foreach ($customer_type as $entry)
                            {
                                $nodevalueX_customertype = $entry->nodeValue;
                            }
                            
                            if($nodevalueX_customertype == '0')
                            {
                                $retObjId = getExistingObjectValue('xRetailer', $query, $doc, $xpath, $EOVparent='1',$prkey,$fromid,'','',$Resulrpatth1);
                                if ($retObjId == '')
                                    $nodevalueX_customertype = 1;
                            }
                            elseif($nodevalueX_customertype == '1')
                            {
                                $retObjId = getExistingObjectValue('xRetailer', $query, $doc, $xpath, $EOVparent='1',$prkey,$fromid,'','',$Resulrpatth1);
                                if ($retObjId != '')
                                    $nodevalueX_customertype = 0;
                            }elseif($nodevalueX_customertype == '2')
                            {
                                $retObjId = getExistingObjectValue('xsubretailer', $query, $doc, $xpath, $EOVparent='1',$prkey,$fromid,'','',$Resulrpatth1);
                                if ($retObjId != '')
                                    $nodevalueX_customertype = 0;
                            }

                            if($nodevalueX_customertype == '1')
                                $relModule = 'xReceiveCustomerMaster';
                            if($nodevalueX_customertype == '2')
                                $relModule = 'xsubretailer';

                            $logg = "ReasonNotTake Customer Type Value : ".$nodevalueX_customertype.PHP_EOL;
                            $logg .= "Related Module : ".$relModule.PHP_EOL;
                            file_put_contents($Resulrpatth1, $logg, FILE_APPEND);
                       }
					   $customer_type_query = $RPIquery.'/customer_type';
					   $customer_type=$xpath->query($customer_type_query,$doc);
					   foreach ($customer_type as $entry)
					   {
						   $nodevalueX_customertype = $entry->nodeValue;
					   }
					   if($relModule == 'xRetailer' && !empty($nodevalueX_customertype)){
						   if($nodevalueX_customertype == '1')
                                $relModule = 'xReceiveCustomerMaster';
                            if($nodevalueX_customertype == '2')
                                $relModule = 'xsubretailer';
					   }
					   
                       $exisObjId = getExistingObjectValue($relModule, $query, $doc, $xpath, $EOVparent='1',$prkey,$fromid,'','',$Resulrpatth1);

                       $logg = "getExistingObjectValue : ".$exisObjId.PHP_EOL;
                       file_put_contents($Resulrpatth1, $logg, FILE_APPEND);

                       if ($exisObjId == ""){
                           if ($mand == 'M' || $mand == 'MU')
                           {
                               if ($LBL_XMLReceiveAllMasters == 'True')
                               {
                                   $object=getObjFrXML($relModule, $doc, $xpath, $subparentName);
                                   $focus->column_fields[''.$columnname.'']=$object->id;
                                   $logAM = "if LBL_XMLReceiveAllMasters = True, Insert the Related Module Data".$object->id.PHP_EOL;
                                   file_put_contents($Resulrpatth1, $logAM, FILE_APPEND);
                               }
                               else
                               {
                                   $log .= "Related Module Data Not Available".PHP_EOL;
                                   $log .= "Related Module : ".$relModule.PHP_EOL;
                                   global $adb;
                                   $tabRes=$adb->mquery("SELECT tabid,tablename,entityidfield FROM vtiger_entityname where modulename=?",array($relModule));

                                   $moduletable_name=$adb->query_result($tabRes,0,'tablename');

                                   $logTN = "Related Module Table Name : ".$moduletable_name.PHP_EOL;
                                   file_put_contents($Resulrpatth1, $logTN, FILE_APPEND);

                                   $fieldQuery = "SELECT fieldid,columnname,uitype,vtiger_tab.`name` FROM vtiger_field 
                                                   INNER JOIN vtiger_tab on vtiger_tab.tabid=vtiger_field.tabid AND vtiger_field.xmlreceivetable = '1'
                                                   WHERE tablename in ('".$moduletable_name."','".$moduletable_name."cf')";

                                   $logsql = "Related Module SQL Query : ".$fieldQuery.PHP_EOL;
                                   file_put_contents($Resulrpatth1, $logsql, FILE_APPEND);

                                   $params = array();
                                   $field_Result = $adb->mquery($fieldQuery,$params);
                                   $field_count = $adb->num_rows($field_Result);

                                   if($field_count>0)
                                           $excolumn_name=$adb->query_result($field_Result,0,'columnname');

                                   $logrm = "Related Module excolumn_name : ".$excolumn_name.PHP_EOL;

                                   $Resquery = $query.'/'.$excolumn_name;

                                   $logrm .= "Related Module XML Path : ".$Resquery.PHP_EOL;
                                   file_put_contents($Resulrpatth1, $logrm, FILE_APPEND);

                                   $entries=$xpath->query($Resquery,$doc);
								   $valueX = '';
                                   foreach ($entries as $entry) {
                                       $valueX = $entry->nodeValue;
                                   }

                                   $logRMV = "Related Module XML Value : ".$valueX.PHP_EOL;
                                   file_put_contents($Resulrpatth1, $logRMV, FILE_APPEND);
                                   
                                   if ($modname == 'xrPurchaseInvoice')
                                    {
                                        $dist_id_query = $RPIquery.'/cf_purchaseinvoice_buyer_id';
                                        $distid=$xpath->query($dist_id_query,$doc);
                                        foreach ($distid as $entry)
                                        {
                                            $nodevalueX_distid = $entry->nodeValue;
                                        }

                                        $fromid = $nodevalueX_distid;
                                        
                                        $forum_code_query = $RPIquery.'/forum_code';
                                        $forum_code=$xpath->query($forum_code_query,$doc);
                                        foreach ($forum_code as $entry)
                                        {
                                            $nodevalueX_forum_code = $entry->nodeValue;
                                        }
                                        if($nodevalueX_forum_code != '')
                                        {
                                            $distdet = GetDistributorDetailFromForumCode($nodevalueX_forum_code);
                                            if($distdet != '')
                                                $fromid = $distdet['distributorcode'];
                                        }
                                    }

                                   $FailReason = $valueX." Related Module Is Not Available (".$Resquery.")";
                                   if( $excolumn_name == 'salesman' ){
                                       $statuscode = 'FN8210';
                                       $statusmsg = 'Invalid Salesman code';
                                   }elseif( $excolumn_name == 'customername' ){
                                       $statuscode = 'FN8215';
                                       $statusmsg = 'Invalid Customer';
                                   }elseif( $excolumn_name == 'beatname' ){
                                       $statuscode = 'FN8216';
                                       $statusmsg = 'Invalid Beat';
                                   }else{
                                       $statuscode = 'FN8200';
                                       $statusmsg = 'Invalid Data';
                                   }
                                   sendreceiveaudit($docid, 'Receive', 'Failed', $FailReason, '', $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                                   $insertstatus = '100';
                                   fwrite($fpx, $insertstatus."\n");
                                   //echo "<B>Related Module Is Not Availabale(".$Resquery.")</B><br>";
                                   continue 2;
                               }
                           }
                           else
                           {
                               $focus->column_fields[''.$columnname.'']="";
                           }
                       }
                       else
                       {
                           $logDA = "Related Module Data Available".PHP_EOL;
                           $logDA .= "Related Module columnname Name : ".$columnname.PHP_EOL;
                           $logDA .= "Related Module Value : ".$exisObjId.PHP_EOL;
                           file_put_contents($Resulrpatth1, $logDA, FILE_APPEND);
                           $focus->column_fields[''.$columnname.'']=$exisObjId;
                       }
                   }
                   if ($columnname == 'code')
                       $focus->column_fields[''.$columnname.''] = $docid."-".$index;
                   //echo "<br/>";
               }
           }
           if($modulename == 'xrSalesOrder' && $LBL_RSO_SAVE_PRO_CATE){
			   $focus->column_fields['lbl_rso_save_pro_cate'] = $LBL_RSO_SAVE_PRO_CATE;
		   }
           //print_r($focus->column_fields);
           $headervalus = $focus->column_fields;
           $logFV = "All the Fileds are getting the value".PHP_EOL;
           $logFV .= "Value : ".  print_r($focus->column_fields, true).PHP_EOL;
           file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);
		   
           if($LBL_XMLReceiveUpdate == 'True' && $mod == 'edit' && !empty($focus->id))
			{
				$updateTBLarray = array($moduletablename,$moduletablename."cf");
				$updatetime = 0;
				foreach ($updateTBLarray as $updateTBLarrayval){
					$fieldQuery_rec = "SELECT fieldid,columnname,uitype,vtiger_tab.`name` FROM vtiger_field 
								   INNER JOIN vtiger_tab on vtiger_tab.tabid=vtiger_field.tabid AND vtiger_field.xmlsendtable = '1'
								   WHERE tablename in ('".$updateTBLarrayval."')";

				   $logsql = "Related Module SQL Query : ".$fieldQuery_rec.PHP_EOL;
				   $fieldQueryResult = $adb->mquery($fieldQuery_rec,array());
				   $fieldQuerycount = $adb->num_rows($fieldQueryResult);
				   $update_qy = '';
				   $update_ary = array();
				   if(!empty($fieldQuerycount)){
					   $update_qy = ' update '.$updateTBLarrayval .' set ';
					   for ($j1 = 0; $j1 < $fieldQuerycount; $j1++) {
						   $un_columnname=$adb->query_result($fieldQueryResult,$j1,'columnname');
						   if(!empty($un_columnname)){
							   if(isset($focus->column_fields[''.$un_columnname.''])){
								  $update_qy .= $un_columnname. " = ? , ";
								  $update_ary[] = $focus->column_fields[''.$un_columnname.''];
							   }
						   }
					   }
					   if(count($update_ary) > 0){
						   $update_qy = trim($update_qy,', ');
						   $update_qy .= " where ".$focus->table_index. " = ?";
						   $update_ary[] = $focus->id;
						   $adb->pquery($update_qy,$update_ary);
						   if($updatetime == 0){
							    $crm_up_qy = "UPDATE vtiger_crmentity CRM INNER JOIN ".$updateTBLarrayval." UPMD ON CRM.crmid =  UPMD.".$focus->table_index. " SET CRM.modifiedtime = now(),UPMD.modified_at = now() WHERE UPMD.".$focus->table_index. " = ? ";
								$adb->pquery($crm_up_qy,array($focus->id));
								$updatetime = 1;
								$logFV = "crm_up_qy : ".  $crm_up_qy.PHP_EOL;
								$logFV .= "crm_up_qy : ".  print_r($focus->id, true).PHP_EOL;
								file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);
						   }
						   
						   $logFV = "Only select data update".PHP_EOL;
						   $logFV .= "Only select Qry : ".$update_qy.PHP_EOL;
						   $logFV .= "Value : ".  print_r($update_ary, true).PHP_EOL;
						   file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);
					   }
					   
				   }
				}
			}else{
				$focus->save($tabNameM,$focus->id);
			}
                      
           $log = "Header Data Save Successes".PHP_EOL;
           file_put_contents($Resulrpatth1, $log, FILE_APPEND);

           $inserted_Id = $focus->id; //For Log purpose getting the inserted objected id.
           $logid = "Header id : ".$inserted_Id.PHP_EOL;
           file_put_contents($Resulrpatth1, $logid, FILE_APPEND);
           
            if($tabNameM == 'xAddress')
           {
                $log = "Address master related table insert".PHP_EOL;
                file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                
                $adddistcode = $focus->column_fields['cf_xaddress_distributor'];
                
                $distid = "SELECT xdistributorid FROM vtiger_xdistributor
                    INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xdistributor.xdistributorid
                    WHERE vtiger_crmentity.deleted = 0 AND vtiger_xdistributor.distributorcode = ?";
                $params = array($adddistcode);
                $distid_Res = $adb->mquery($distid,$params);
                $dist_id = $adb->query_result($distid_Res,0,0);
                
                $log = "Distributor Code : ".$adddistcode.PHP_EOL;
                $log .= "Disr id Query : ".$distid.PHP_EOL;
                $log .= "Disr id : ".$dist_id.PHP_EOL;
                $log .= "Query_Result : ".print_r($distid_Res,TRUE).PHP_EOL;
                file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                
                if($dist_id != '')
                {
                    $logval = "Address Distributor ID : ".$dist_id.PHP_EOL;
                    file_put_contents($Resulrpatth2, $logval, FILE_APPEND);
                    
                    $upddistidinaddarray = array();
                    $upddistidinadd = "UPDATE vtiger_xaddresscf SET cf_xaddress_distributor = ? WHERE xaddressid = ?";
                    
                    $upddistidinaddarray[] =  $dist_id;
                    $upddistidinaddarray[] = $inserted_Id;
                    $adb->mquery($upddistidinadd,$upddistidinaddarray);
                    
                    $log = "Update Distributor Id query : ".$upddistidinadd.PHP_EOL;
                    $log .= "Remove the default Values : ".print_r($upddistidinaddarray,TRUE).PHP_EOL;
                    file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                    
                    $insertrelationarray = array();
                    
                    $insertrelationarray[] = $dist_id;
                    $insertrelationarray[] = 'xDistributor';
                    $insertrelationarray[] = $inserted_Id;
                    $insertrelationarray[] = 'xAddress';
                    
                    $relcrmverify = "SELECT * FROM vtiger_crmentityrel
                        WHERE crmid = ? AND module = ? AND relcrmid = ? AND relmodule = ?";
                    
                    $relcrmverify_Res = $adb->mquery($relcrmverify,$insertrelationarray);
                    $relcrmverify_count = $adb->num_rows($relcrmverify_Res);
                    
                    $log = "Get the Existing Rel : ".$relcrmverify.PHP_EOL;
                    $log .= "Get the Existing Rel Values : ".print_r($insertrelationarray,TRUE).PHP_EOL;
                    $log .= "Get the Existing Rel Cpunt : ".$relcrmverify_count.PHP_EOL;
                    file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                    
                    if ($relcrmverify_count == 0)
                    {
                        $insertrelation = "INSERT INTO vtiger_crmentityrel (crmid,module,relcrmid,relmodule) VALUES (?,?,?,?)";
                        $adb->mquery($insertrelation,$insertrelationarray);
                        
                        $log = "Rel Insert query : ".$insertrelation.PHP_EOL;
                        $log .= "Rel Insert Values : ".print_r($insertrelationarray,TRUE).PHP_EOL;
                        file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                    }
                }
                ELSE
                {
                    
                }
           }
           
           if($tabNameM == 'xProdHier')
            {
                $prolevel = 'collections/'.$moduletablename.'['.$index.']'.'/cf_xprodhier_level/levelcode';
                $latitude=$xpath->query($prolevel,$doc);
                foreach ($latitude as $entry) {
                    $prolevelVal = $entry->nodeValue;
                }

               if($prolevelVal == '')
               {
                    $log = "Product Hierarchy Level Insert".PHP_EOL;
                    file_put_contents($Resulrpatth1, $log, FILE_APPEND);

                    $lat = 'collections/'.$moduletablename.'['.$index.']'.'/cf_xprodhier_parent/prodhiercode';
                    $latitude=$xpath->query($lat,$doc);
                    foreach ($latitude as $entry) {
                        $parentVal = $entry->nodeValue;
                    }
                    $xprodhiermetaid ='';

                    $log = "Product Hierarchy Pasrent Value : ".$parentVal.PHP_EOL;
                    file_put_contents($Resulrpatth1, $log, FILE_APPEND);

                    if($parentVal != '')
                    {
                         $hierlevel = "SELECT
                                  PHL.xprodhiermetaid,PHL.levelname
                              FROM vtiger_xprodhiermetacf PHLCF
                              INNER JOIN vtiger_xprodhiermeta PHL ON PHL.xprodhiermetaid = PHLCF.xprodhiermetaid
                              INNER JOIN vtiger_crmentity PHLCRM ON PHLCRM.crmid = PHL.xprodhiermetaid
                              WHERE PHLCF.cf_xprodhiermeta_hierarchy_level IN (
                              SELECT
                                  PLCF.cf_xprodhiermeta_hierarchy_level+1 AS `level`
                              FROM vtiger_xprodhier PH
                              INNER JOIN vtiger_xprodhiercf PHCF ON PH.xprodhierid = PHCF.xprodhierid
                              INNER JOIN vtiger_xprodhiermetacf PLCF ON PLCF.xprodhiermetaid = PHCF.cf_xprodhier_level
                              INNER JOIN vtiger_crmentity CRM ON CRM.crmid = PH.xprodhierid
                              WHERE CRM.deleted = 0 AND PH.prodhiercode = ?) AND PHLCRM.deleted = 0";

                          $params = array($parentVal);
                          $prolevel_Res = $adb->mquery($hierlevel,$params);
                          $xprodhiermetaid = $adb->query_result($prolevel_Res,0,0);

                        $log = "--Product Hierarchy Parent is not NULL--".PHP_EOL;
                        $log .= "Product Hierarchy Get Level Value Query : ".$hierlevel.PHP_EOL;
                        $log .= "Product Hierarchy Get Level Value array : ".print_r($params,TRUE).PHP_EOL;
                        $log .= "Product Hierarchy Get Level Value id : ".$xprodhiermetaid.PHP_EOL;
                        file_put_contents($Resulrpatth1, $log, FILE_APPEND);

                          if ($xprodhiermetaid != '')
                          {
                              $updatephlvaluearray = array();

                              $phlvupdate = "UPDATE vtiger_xprodhiercf SET cf_xprodhier_level = ? WHERE xprodhierid = ?";

                              $updatephlvaluearray[] = $xprodhiermetaid;
                              $updatephlvaluearray[] = $inserted_Id;
                              $adb->mquery($phlvupdate,$updatephlvaluearray);

                              $log = "--Product Hierarchy Level Value is success--".PHP_EOL;
                              $log .= "Product Hierarchy Level Update Query : ".$phlvupdate.PHP_EOL;
                              $log .= "Product Hierarchy Level Update array : ".  print_r($updatephlvaluearray, TRUE).PHP_EOL;
                              file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                          }
                          else
                          {
                              $prohierdata_revertarray = array();
                              $prohierdata_revert = "UPDATE vtiger_crmentity SET deleted = 1 WHERE `setype` = 'xProdHier' AND `deleted` = '0' AND crmid = ?";
                              $prohierdata_revertarray[] = $inserted_Id;
                              $adb->mquery($prohierdata_revert,$updatephlvaluearray);

                              $prohierdata_logdelarray= array();
                              $prohierdata_logdel = "DELETE FROM sify_send_receive_audit WHERE sen_rec_documenttype = 'xProdHier' AND sen_rec_recordid = ?";
                              $prohierdata_logdelarray[] = $inserted_Id;
                              $adb->mquery($prohierdata_logdel,$prohierdata_logdelarray);

                              $log = "--Product Hierarchy Level Value is Fail--".PHP_EOL;
                              $log .= "CRM Endity revert Query : ".$prohierdata_revert.PHP_EOL;
                              $log .= "CRM Endity revert array : ".  print_r($updatephlvaluearray, TRUE).PHP_EOL;
                              $log .= "Delete Audit Query : ".$prohierdata_logdel.PHP_EOL;
                              $log .= "Delete Audit array : ".  print_r($prohierdata_logdelarray, TRUE).PHP_EOL;
                              file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                              $statuscode = 'FN2010';
                              $statusmsg = 'Hierarchy can not be Created';
                              sendreceiveaudit($docid, 'Receive', 'Failed', 'Hierarchy can not be Created : Hierarchy Level not found', $customer_code, $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                              $insertstatus = '102';
                              fwrite($fpx, $insertstatus."\n");
                          }
                    }
                    ELSE
                    {
                        $hierlevel = "SELECT
                                  PHL.xprodhiermetaid,PHL.levelname
                              FROM vtiger_xprodhiermetacf PHLCF
                              INNER JOIN vtiger_xprodhiermeta PHL ON PHL.xprodhiermetaid = PHLCF.xprodhiermetaid
                              INNER JOIN vtiger_crmentity PHLCRM ON PHLCRM.crmid = PHL.xprodhiermetaid
                              WHERE PHLCF.cf_xprodhiermeta_hierarchy_level = 1";

                         $params = array();
                         $prolevel_Res = $adb->mquery($hierlevel,$params);
                         $xprodhiermetaid = $adb->query_result($prolevel_Res,0,0);

                        $log = "--Product Hierarchy Parent is NULL--".PHP_EOL;
                        $log .= "Product Hierarchy Get Level Value Query : ".$hierlevel.PHP_EOL;
                        $log .= "Product Hierarchy Get Level Value array : ".print_r($params,TRUE).PHP_EOL;
                        $log .= "Product Hierarchy Get Level Value id : ".$xprodhiermetaid.PHP_EOL;
                        file_put_contents($Resulrpatth1, $log, FILE_APPEND);

                        $updatephlvaluearray = array();

                         $phlvupdate = "UPDATE vtiger_xprodhiercf SET cf_xprodhier_level = ? WHERE xprodhierid = ?";

                         $updatephlvaluearray[] = $xprodhiermetaid;
                         $updatephlvaluearray[] = $inserted_Id;
                         $adb->mquery($phlvupdate,$updatephlvaluearray);

                        $log = "Product Hierarchy Level Update Query : ".$phlvupdate.PHP_EOL;
                        $log .= "Product Hierarchy Level Update array : ".  print_r($updatephlvaluearray, TRUE).PHP_EOL;
                        file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                    }
               }
            }
            
            if($tabNameM == 'xDistributor')
            {
                if ($mod == '')
                {
                    /*Connect the Channel Type*/
                    $dist_channel_query = 'collections/'.$moduletablename.'['.$index.']'.'/cf_xdistributor_channel';
                    $dist_channel = 'collections/'.$moduletablename.'['.$index.']'.'/cf_xdistributor_channel/channelhierarchycode';
                    $distchannel=$xpath->query($dist_channel,$doc);
                    foreach ($distchannel as $entry) {
                        $dist_chann = $entry->nodeValue;
                    }
                    $channels = explode("#",$dist_chann);
                    $channel = '';
                    for ($index12 = 0; $index12 < count($channels); $index12++)
                    {
                        $channel = '';
                        $channel = $channels[$index12];
                                                
                        $fieldQuery = "SELECT fieldid,columnname,uitype,vtiger_tab.`name` FROM vtiger_field 
                        INNER JOIN vtiger_tab on vtiger_tab.tabid=vtiger_field.tabid AND vtiger_field.xmlreceivetable = '1'
                        WHERE tablename in ('vtiger_xchannelhierarchy','vtiger_xchannelhierarchycf') AND FIND_IN_SET(columnname, '".$prkey."')";
                        
                        $fieldResult = $adb->mquery($fieldQuery,$params);
                        $fieldcount = $adb->num_rows($fieldResult);
                        if($fieldcount>0)
                        {
                            $excolumnname=$adb->query_result($fieldResult,0,'columnname');
                            $channelid = "SELECT CH.xchannelhierarchyid FROM vtiger_xchannelhierarchy CH
                                    INNER JOIN vtiger_crmentity CRM ON CRM.crmid = CH.xchannelhierarchyid
                                    WHERE CRM.deleted = 0 AND CH.".$excolumnname." = ?";

                             $params = array($channel);
                             $channelid_Res = $adb->mquery($channelid,$params);
                             $channel_id = $adb->query_result($channelid_Res,0,0);
                             
                             $adb->mquery("INSERT INTO vtiger_crmentityrel(crmid, module, relcrmid, relmodule) VALUES(?,?,?,?)",
                                     Array($inserted_Id, 'xDistributor', $channel_id, 'xChannelHierarchy'));
                        }
                    }                    
                    $log = "---Channel Hierarchy---".PHP_EOL;
                    $log .= "Distributor Channel Value : ".  $dist_chann.PHP_EOL;
                    file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                    
                    /* For Distributor creation default 4 Stock Conversion created. Distibutor can change in Distributor Settings. */
                    $adb->mquery("insert into sify_inv_mgt_config (`key`, `value`, from_stock_type, to_stock_type, transfer_mode, rule_from_stock_type, 
                        rule_to_stock_type, claim_amt, treatment, dist_id) values 
                            ('AUTO_CONV_SALABLE_TO_FREE', '0', '', '', '', '', '', '', '', '$inserted_Id'), 
                            ('STOCK_TYPE_CONV', '1', 'S', 'SF', '', '', '', '', '', '$inserted_Id'), 
                            ('STOCK_TYPE_CONV', '1', 'SF', 'S', '', '', '', '', '', '$inserted_Id'), 
                            ('STOCK_TYPE_CONV', '1', 'S', 'D', '', '', '', '', '', '$inserted_Id'), 
                            ('STOCK_TYPE_CONV', '1', 'SF', 'DF', '', '', '', '', '', '$inserted_Id')");
                    
                    //Distributor creation default godwon
                    $xGodown =  CRMEntity::getInstance("xGodown");

                    $xGodown->column_fields['godown_name'] = $focus->column_fields['distributorcode'];
                    $xGodown->column_fields['godown_code'] = $focus->column_fields['distributorcode'].'G1';
                    $xGodown->column_fields['xgodown_active'] = '1';
                    $xGodown->column_fields['xgodown_default'] = '1';
                    $xGodown->save("xGodown");
                    
                    $updateQryarray = array();
                    $updateQryarray[] = $inserted_Id;
                    $updateQryarray[] = $xGodown->id;
                    
                    $updateQry = "UPDATE vtiger_xgodown SET xgodown_distributor = ? WHERE xgodownid = ?";
                    $adb->mquery($updateQry,$updateQryarray);
                    
                    $adb->mquery("Insert into sify_location (location_id, type, dist_id, active) values ('$xGodown->id', 'Godown', '$inserted_Id', '1')");
                    
                    sendreceiveaudit($docid, 'Receive', 'Successes', 'Successes', $xGodown->id, $fromid,$sourceapplication,$doccreateddate,'xGodown','',$destapplication,$subjectVal,$statuscode,$statusmsg);
                    
                    //Distributor creation default user & distributor User mapping
                    $userid = UserCreationReceive($inserted_Id);
                    sendreceiveaudit($docid, 'Receive', 'Successes', 'Successes', $userid, $fromid,$sourceapplication,$doccreateddate,'Users','',$destapplication,$subjectVal,$statuscode,$statusmsg);                   
                    
                    //Distributor creation Universial Master based on work flow
                    $ns = getNextstageByPosAction("distributor","Submit");
                    $businessLogic = $ns['cf_workflowstage_business_logic'];
                    $spl_businessLogic = explode("|##|",$businessLogic);
                    $spl_businessLogic_flip = array_flip($spl_businessLogic);
                    $log = "---Start Universal Master---".PHP_EOL;
                    $log .= "businessLogic : ". $businessLogic.PHP_EOL;
                    $log .= "businessLogic array : ".print_r($spl_businessLogic_flip, True).PHP_EOL;
                    $log .= "businessLogic Serch  : ".array_search('0',$spl_businessLogic_flip).PHP_EOL;
                    file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                    if (in_array('Update Universal',$spl_businessLogic_flip))
                    {
                        $universal = updateUniversal('distributor', $inserted_Id, '');
                        
                        $update = "UPDATE vtiger_xuniversal SET distributor_id = ? WHERE xuniversalid = ?";
                        $qparams = array($inserted_Id,$universal);
                        $adb->mquery($update,$qparams); 
                        sendreceiveaudit($docid, 'Receive', 'Successes', 'Successes', $universal, $fromid,$sourceapplication,$doccreateddate,'xUniversal','',$destapplication,$subjectVal,$statuscode,$statusmsg);
                    }
                    $log = "---End Universal Master---".PHP_EOL;
                    file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                    
                    //Distributor creation default tranaction series
                    TransactionSeriesReceive($inserted_Id);
                    sendreceiveaudit($docid, 'Receive', 'Successes', 'Successes', '', $fromid,$sourceapplication,$doccreateddate,'xTransactionSeries','',$destapplication,$subjectVal,$statuscode,$statusmsg);
                    
                    //Distributor Clustor Mapping
                    $nss = getNextstageByPosAction("distributor","Submit");
                    $businessLogics = $nss['cf_workflowstage_business_logic'];
                    $spl_businessLogics = explode("|##| ",$businessLogics);
                    $spl_businessLogics_flip = array_flip($spl_businessLogics);
                    $log = "---Start Distributor Clustor Mapping---".PHP_EOL;
                    $log .= "businessLogic : ". $businessLogics.PHP_EOL;
                    $log .= "businessLogic array : ".print_r($spl_businessLogics_flip, True).PHP_EOL;
                    $log .= "businessLogic Serch  : ".array_search('1',$spl_businessLogics_flip).PHP_EOL;
                    file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                    if (in_array('Other Settings',$spl_businessLogics_flip))
                    {
                        $log = "businessLogic is Other Settings ".PHP_EOL;
                        file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                    
                        distributorClsterMapping('distributor', $inserted_Id);
                    }
                    $log = "---End Distributor Clustor Mapping---".PHP_EOL;
                    file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                }
            }
            
            if($tabNameM == 'xProduct')
            {
                if ($mod == '')
                {
                    $ns = getNextstageByPosAction("Product","Submit");
                    $businessLogic = $ns['cf_workflowstage_business_logic'];
                    $log = "---Start Distributor Product Mapping---".PHP_EOL;
                    $log .= "businessLogic : ". $businessLogic.PHP_EOL;
                    file_put_contents($Resulrpatth1, $log, FILE_APPEND);

                    if($businessLogic == 'Distributor Product Mapping')
                        prodDpMapping('xProduct', '', '', $inserted_Id);

                    $log = "---End Distributor Product Mapping---".PHP_EOL;
                    file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                }
            }
            
            if($tabNameM == 'xRetailer')
            {
                if ($retailer_code != 'Retailer')
                {
                    if ($mod == '')
                    {
                        $custcode = $focus->seModSeqNumber('increment', 'xRetailer');
                        $update = "UPDATE vtiger_xretailer SET customercode = ? WHERE xretailerid = ?";
                        $qparams = array($custcode,$inserted_Id);
                        $adb->mquery($update,$qparams);
                        
                        $address_field = $focus->column_fields['cf_xretailer_address_1'].",".$focus->column_fields['cf_xretailer_address_2'];
                        $city = $focus->column_fields['cf_xretailer_city'];
                        $pin_code = $focus->column_fields['cf_xretailer_pin_code'];
                        
                        if($address_field != ',')
                        {
                            require_once 'modules/xAddress/xAddress.php';
                            $ShipAdd = new xAddress();
                            $BillAdd = new xAddress();
                            
                            $ShipAdd->column_fields['addresscode'] = $ShipAdd->seModSeqNumber('increment', 'xAddress');
                            $ShipAdd->column_fields['cf_xaddress_address_type'] = 'Shipping';
                            $ShipAdd->column_fields['cf_xaddress_address'] = $address_field;
                            $ShipAdd->column_fields['cf_xaddress_city'] = $city;
                            $ShipAdd->column_fields['cf_xaddress_postal_code'] = $pin_code;
                            $ShipAdd->column_fields['cf_xaddress_active'] = 1;
                            $ShipAdd->column_fields['cf_xaddress_mark_as_default'] = 1;
                            $ShipAdd->save('xAddress');
                            $shaddress_id = $ShipAdd->id;
                            $ShipAdd->save_related_module('xRetailer', $inserted_Id, 'xAddress', $shaddress_id);

                            sendreceiveaudit($docid, 'Receive', 'Successes', 'Successes', $shaddress_id, $fromid,$sourceapplication,$doccreateddate,'xAddress','',$destapplication,$subjectVal,$statuscode,$statusmsg);

                            $BillAdd->column_fields['addresscode'] = $BillAdd->seModSeqNumber('increment', 'xAddress');
                            $BillAdd->column_fields['cf_xaddress_address_type'] = 'Billing';
                            $BillAdd->column_fields['cf_xaddress_address'] = $address_field;
                            $BillAdd->column_fields['cf_xaddress_city'] = $city;
                            $BillAdd->column_fields['cf_xaddress_postal_code'] = $pin_code;
                            $BillAdd->column_fields['cf_xaddress_active'] = 1;
                            $BillAdd->column_fields['cf_xaddress_mark_as_default'] = 1;
                            $BillAdd->save('xAddress');
                            $address_id = $BillAdd->id;
                            $BillAdd->save_related_module('xRetailer', $inserted_Id, 'xAddress', $address_id);

                            sendreceiveaudit($docid, 'Receive', 'Successes', 'Successes', $address_id, $fromid,$sourceapplication,$doccreateddate,'xAddress','',$destapplication,$subjectVal,$statuscode,$statusmsg);
                        }
                        
                        $lat = 'collections/'.$moduletablename.'['.$index.']'.'/beatcode';
                        $latitude=$xpath->query($lat,$doc);
                        foreach ($latitude as $entry) {
                            $beatcode = $entry->nodeValue;
                        }
                        if($beatcode != '')
                        {
                            $lat = 'collections/'.$moduletablename.'['.$index.']'.'/distributor_id';
                            $latitude=$xpath->query($lat,$doc);
                            foreach ($latitude as $entry) {
                                $distributor_id = $entry->nodeValue;
                            }
                            
                            $beatsql = "SELECT BE.xbeatid, BE.beatcode FROM vtiger_xbeat BE
                                INNER JOIN vtiger_crmentity CRM ON CRM.crmid = BE.xbeatid
                                WHERE CRM.deleted = 0 AND BE.cf_xbeat_distirbutor_id = ? AND BE.beatcode = ?";
                            
                            $params = array($distributor_id,$beatcode);
                            $beatsql_Res = $adb->mquery($beatsql,$params);
                            $xbeatid = $adb->query_result($beatsql_Res,0,0);
                            
                            $BillAdd->save_related_module('xRetailer', $inserted_Id, 'xBeat', $xbeatid);
                            
                            sendreceiveaudit($docid, 'Receive', 'Beat Mapped Successes', 'Beat Mapped Successes', $xbeatid, $fromid,$sourceapplication,$doccreateddate,'xBeat','',$destapplication,$subjectVal,$statuscode,$statusmsg);
                            
                        }
                        
                        $logFV = "--Start Retailer Code Update--".PHP_EOL;
                        $logFV .= "Geneated Customer Code : ".$custcode.PHP_EOL;
                        $logFV .= "Customer ID : ".$inserted_Id.PHP_EOL;
                        $logFV .= "Beat Code : ".$beatcode.PHP_EOL;
                        $logFV .= "Distributor Id : ".$distributor_id.PHP_EOL;
                        $logFV .= "--End Retailer Code Update--".PHP_EOL;
                        file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);
                        
                        
                    }
                }
            }
            
           $Trname=$adb->mquery("SELECT transaction_rel_table,transaction_name,profirldname,relid,uom,categoryid,receive_pro_by_cate FROM sify_tr_rel WHERE transaction_name =?",array($modname));
           $logtrrel = "Number of Records in sify_tr_rel : ".$adb->num_rows($Trname).PHP_EOL;
           file_put_contents($Resulrpatth1, $logtrrel, FILE_APPEND);

           if($adb->num_rows($Trname)>0)
           {
               $TranTblName=$adb->query_result($Trname,0,'transaction_rel_table');
               $TraName=$adb->query_result($Trname,0,'transaction_name');
               $relid=$adb->query_result($Trname,0,'relid');
               $profirldname=$adb->query_result($Trname,0,'profirldname');
               $reluom=$adb->query_result($Trname,0,'uom');
               $categoryid=$adb->query_result($Trname,0,'categoryid');
               $receive_pro_by_cate=$adb->query_result($Trname,0,'receive_pro_by_cate');
			   $is_process = 1;
			   if(!empty($LBL_RSO_SAVE_PRO_CATE) && strtolower($LBL_RSO_SAVE_PRO_CATE) == 'true' && !empty($receive_pro_by_cate) && strtolower($receive_pro_by_cate) == 'true'){
				   $is_process = 0;
			   }
               $logtblna = "TranTblName : ".$TranTblName." , TraName :".$TraName." , relid : ".$relid." , profirldname : ".$profirldname." , reluom : ".$reluom.PHP_EOL;
               file_put_contents($Resulrpatth1, $logtblna, FILE_APPEND);
			   
			   $rel_table_qy = "SELECT columnname FROM sify_tr_grid_field WHERE tablename = '".$TranTblName."' AND xmlreceivetable = '1' ORDER BY columnname";
			   if($mod == 'edit'){
				   $rel_table_qy = "SELECT columnname FROM sify_tr_grid_field WHERE tablename = '".$TranTblName."' AND xmlsendtable = '1' ORDER BY columnname";
			   }
               $Trprorel=$adb->mquery($rel_table_qy,array());

               $loggrd = "Number of Records in sify_tr_grid_field : ".$adb->num_rows($Trprorel).PHP_EOL;
               file_put_contents($Resulrpatth1, $loggrd, FILE_APPEND);

               if($adb->num_rows($Trprorel)>0)
               {
                   $trfields = array();
                   $colum = '';
                   $colval = '';
                   for ($index4 = 0; $index4 < $adb->num_rows($Trprorel); $index4++) 
                   {
                       $tr_fields=$adb->query_result($Trprorel,$index4,0);
                       $trfields[] = $tr_fields;
                       if ($colum == "")
                       {
                           $colum = $tr_fields;
                           $colval = "?";
                       }   
                       else
                       {
                           $colum = $colum.",".$tr_fields;
                           $colval = $colval.",?";
                       }
                   }
               }
               $logco = "Colum : ".$colum." , Colum Valus : ".$colval.PHP_EOL;
               file_put_contents($Resulrpatth1, $logco, FILE_APPEND);

   //        if ($modname == 'PurchaseInvoice')
   //        {
               $focus1=  CRMEntity::getInstance($TraName);
               $piid = $inserted_Id;
               //$piFieldsArray=array('productid','sequence_no','quantity','listprice','tuom','baseqty','tax');
               $piFieldsArray = $trfields;

                if($TraName=='xSalesOrder'){
                        $taxTable = 'sify_xtransaction_tax_rel_so  ';
                }elseif($TraName=='SalesInvoice'){
                        $taxTable = 'sify_xtransaction_tax_rel_si  ';
                }elseif($TraName=='xSalesReturn'){
                        $taxTable = 'sify_xtransaction_tax_rel_sr  ';
                }elseif($TraName=='PurchaseOrder'){
                        $taxTable = 'sify_xtransaction_tax_rel_po  ';
                }elseif($TraName=='PurchaseInvoice'){
                        $taxTable = 'sify_xtransaction_tax_rel_pi  ';
                }elseif($TraName=='xrPurchaseInvoice'){
                        $taxTable = 'sify_xtransaction_tax_rel_rpi  ';
                }elseif($TraName=='xPurchaseReturn'){
                        $taxTable = 'sify_xtransaction_tax_rel_pr  ';
                }elseif($TraName=='xServiceInvoice'){
                        $taxTable = 'sify_xtransaction_tax_rel_service  ';
                }elseif($TraName=='xrSalesInvoice'){
                        $taxTable = ' sify_xtransaction_tax_rel_rsi  ';
                }else{
                     $taxTable = ' sify_xtransaction_tax_rel  ';
                }

               $adb->mquery("DELETE FROM sify_xtransaction_tax_rel WHERE transaction_id=?",array($inserted_Id));
                $adb->mquery("DELETE FROM  $taxTable WHERE transaction_id=?",array($inserted_Id));

               $prolen = 'collections/'.$moduletablename.'['.$index.']/lineitems/'.$TranTblName;

               $logpt = "XML lineitems Path : ".$prolen.PHP_EOL;
               file_put_contents($Resulrpatth1, $logpt, FILE_APPEND);

               $prolenentries=$xpath->query($prolen,$doc);
               $Procount = $prolenentries->length;

               $logLOL = "Length of lineitems : ".$Procount.PHP_EOL;
               $refStatusUp = 0;
               file_put_contents($Resulrpatth1, $logLOL, FILE_APPEND);
               for ($index2 = 1; $index2 <= $Procount; $index2++) {
                   for ($index1 = 0; $index1 < count($piFieldsArray); $index1++) {

                       //$pifocus->column_fields['id']=$piid;

                       $fName=$piFieldsArray[$index1];
                       $piquery = 'collections/'.$moduletablename.'['.$index.']/lineitems/'.$TranTblName.'['.$index2.']/';

                       $piquery .= $fName;

                       $logPFL = "XML lineitems Path Field Level : ".$piquery.PHP_EOL;
                       file_put_contents($Resulrpatth1, $logPFL, FILE_APPEND);

                       if ($fName == $relid){
                           $pifocus->column_fields[''.$relid.'']=$piid;
                       }elseif($fName == $categoryid){
                           $logPR = "If the line item as producthierachy".PHP_EOL;
                           file_put_contents($Resulrpatth1, $logPR, FILE_APPEND);
                           $proquery = $piquery.'/prodhiercode';
                           $prohid = getExistingObjectValue('xProdHier', $proquery, $doc, $xpath, $EOVparent='',$prkey,$fromid,'','',$Resulrpatth1);
						   $pifocus->column_fields[$fName]=$prohid;
						   $show_error = 0;
						   if(strtolower($LBL_RSO_SAVE_PRO_CATE) == 'false'){
							   $show_error = 0;
						   }
                           if ($prohid == "" && $show_error == 1){
                               $entries=$xpath->query($proquery,$doc);
                               foreach ($entries as $entry) {
                                   $priname = $entry->nodeValue;
                               }
                               $FailReason = $priname." Is Not Availabale (".$proquery.")";
                               if( $excolumn_name == 'prodhiercode'){
                                   $statuscode = 'FN8212';
                                   $statusmsg = 'Invalid xProdHier Code';
                               }else{
                                   $statuscode = 'FN8212';
                                   $statusmsg = 'Invalid xProdHier Code';
                               }
                               sendreceiveaudit($docid, 'Receive', 'Failed', $FailReason, '', $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                               $insertstatus = '101';
                               fwrite($fpx, $insertstatus."\n");
                               $focus1->trash($TraName, $inserted_Id);
                                updateSubject($moduletablename,'subject',$subjectVal,$focus->table_index,$inserted_Id,$Resulrpatth1);
                               continue 3;
                           }elseif(!empty($LBL_RSO_SAVE_PRO_CATE) && strtolower($LBL_RSO_SAVE_PRO_CATE) == 'true' && !empty($receive_pro_by_cate) && strtolower($receive_pro_by_cate) == 'true' && !empty($prohid)){
								$logPRID = "producthierachy Name : ".$prohid.PHP_EOL;
								$logPRID .= "category based : ".$LBL_RSO_SAVE_PRO_CATE.PHP_EOL;
								file_put_contents($Resulrpatth1, $logPRID, FILE_APPEND);
								$pifocus->column_fields[$fName]=$prohid;
								$catelevelquery = "select HIR.xprodhierid,HIR.prodhiercode,HRCF.cf_xprodhier_code_path as hpath from vtiger_xprodhier HIR INNER JOIN vtiger_xprodhiercf HRCF ON HIR .xprodhierid = HRCF.xprodhierid  Where HIR .xprodhierid = ?";
								  $catelevelqueryExe = $adb->pquery($catelevelquery,array($prohid));
								  $catelevelqueryExeCount = $adb->num_rows($catelevelqueryExe);
								 
								  $hpath = '';
								  if(!empty($catelevelqueryExeCount)){
									  $hpath = $adb->query_result($catelevelqueryExe,0,'hpath');
									  $catparent_qy = "select group_concat(HIR.xprodhierid) as cateids from vtiger_xprodhier HIR INNER JOIN vtiger_xprodhiercf HRCF ON HIR.xprodhierid = HRCF.xprodhierid where HIR.xprodhierid = ? or HRCF.cf_xprodhier_code_path like '$hpath -%'";
								  }
								
							    if(!empty($prohid)){
								   $catecode = '';
									$entries=$xpath->query($proquery,$doc);
								   foreach ($entries as $entry) {
									   $catecode = $entry->nodeValue;
								   }
								   $catparent_qy_exe = $adb->pquery($catparent_qy,array($prohid));
								   $catparent_qy_exeCount = $adb->num_rows($catparent_qy_exe);
								   if(!empty($catparent_qy_exeCount)){
									   $prohid = str_replace(",","','",$adb->query_result($catparent_qy_exe,0,'cateids'));
								   }
								   if(empty($prohid)){
									   $prohid = $pifocus->column_fields[$fName];
								   }
								   $product_qy = "select PRO.xproductid as proid,PRO.productcode from vtiger_xproduct PRO  inner Join vtiger_xproductcf PROCF on PRO.xproductid = PROCF.xproductid Where PRO.productcode = '$catecode' or PROCF.cf_xproduct_category in('$prohid') order by PRO.productcode = '$catecode' DESC limit 1";
								   $product_gt_qy = $adb->pquery($product_qy,array());
								   $product_id = $adb->query_result($product_gt_qy,0,'proid');
								   $productcode = $adb->query_result($product_gt_qy,0,'productcode');
								   if ($product_id == "" || empty($product_id)){
									   $entries=$xpath->query($proquery,$doc);
									   foreach ($entries as $entry) {
										   $priname = $entry->nodeValue;
									   }
									   $FailReason = $priname." Is Not Availabale (".$proquery.")";
									   if( $excolumn_name == 'productcode'){
										   $statuscode = 'FN8212';
										   $statusmsg = 'The catecode not mappied with product';
									   }else{
										   $statuscode = 'FN8212';
										   $statusmsg = 'Invalid Product Code';
									   }
									   sendreceiveaudit($docid, 'Receive', 'Failed', $FailReason, '', $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
									   $insertstatus = '101';
									   fwrite($fpx, $insertstatus."\n");
									   $focus1->trash($TraName, $inserted_Id);
									   updateSubject($moduletablename,'subject',$subjectVal,$focus->table_index,$inserted_Id,$Resulrpatth1);
									   continue 3;
								   }
                                   $pifocus->column_fields[$profirldname]=$product_id;
                                   $pifocus->column_fields['productcode']=$productcode;
                                   $productname_new = $product_id;
								   $logPRID = "product_qy : ".$product_qy.PHP_EOL;
								   file_put_contents($Resulrpatth1, $logPRID, FILE_APPEND);
								   $logUOM = "If the line item as UOM Name".PHP_EOL;
								   file_put_contents($Resulrpatth1, $logUOM, FILE_APPEND);
						           $uoquery = 'collections/'.$moduletablename.'['.$index.']/lineitems/'.$TranTblName.'['.$index2.']/';$uoquery .= $reluom;
								   $UOMquery = $uoquery.'/uomname';
								   $UOMid = getExistingObjectValue('UOM', $UOMquery, $doc, $xpath, $EOVparent='','uomname',$fromid,'','',$Resulrpatth1);
								   if ($UOMid == "")
								   {
									   $entries=$xpath->query($UOMquery,$doc);
									   foreach ($entries as $entry) {
										   $UOMname = $entry->nodeValue;
									   }
									   $FailReason = $UOMname." Is Not Availabale (".$UOMquery.")";
									   $statuscode = 'FN8213';
									   $statusmsg = 'Invalid UOM';
									   sendreceiveaudit($docid, 'Receive', 'Failed', $FailReason, '', $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
									   $insertstatus = '100';
									   fwrite($fpx, $insertstatus."\n");
									   $focus1->trash($TraName, $inserted_Id);
									   updateSubject($moduletablename,'subject',$subjectVal,$focus->table_index,$inserted_Id,$Resulrpatth1);
									   continue 3;
								   }
								   $logUOMID = "UOM ID : ".$UOMid.PHP_EOL;
								   file_put_contents($Resulrpatth1, $logUOMID, FILE_APPEND);
								   $pifocus->column_fields[''.$reluom.'']=$UOMid;
							   }
                           }elseif(!empty($LBL_RSO_SAVE_PRO_CATE) && strtolower($LBL_RSO_SAVE_PRO_CATE) == 'true' && !empty($receive_pro_by_cate) && strtolower($receive_pro_by_cate) == 'true' && empty($prohid)){
                              $entries=$xpath->query($proquery,$doc);
                               foreach ($entries as $entry) {
                                   $priname = $entry->nodeValue;
                               }
                               $FailReason = $priname." Is Not Availabale (".$proquery.")";
                               if( $excolumn_name == 'prodhiercode'){
                                   $statuscode = 'FN8212';
                                   $statusmsg = 'Invalid xProdHier Code';
                               }else{
                                   $statuscode = 'FN8212';
                                   $statusmsg = 'Invalid xProdHier Code';
                               }
                               sendreceiveaudit($docid, 'Receive', 'Failed', $FailReason, '', $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                               $insertstatus = '101';
                               fwrite($fpx, $insertstatus."\n");
                               $focus1->trash($TraName, $inserted_Id);
							   updateSubject($moduletablename,'subject',$subjectVal,$focus->table_index,$inserted_Id,$Resulrpatth1);
                               continue 3; 
                           }
                           else
                           {
                               $logPRID = "producthierachy Name : ".$prohid.PHP_EOL;
                               file_put_contents($Resulrpatth1, $logPRID, FILE_APPEND);
                               $pifocus->column_fields[$fName]= $prohid;
                           }
                       }
                       elseif($fName == $profirldname && !empty($is_process))
                       {
                           $logPR = "If the line item as productname".PHP_EOL;
                           file_put_contents($Resulrpatth1, $logPR, FILE_APPEND);
                           $proquery = $piquery.'/productcode';
						   $product_module = 'xproduct';
						   if($modulename == 'xrMerchandiseIssue'){
							   $product_module = 'xMerchandise';
						   }
                           $proid = getExistingObjectValue($product_module, $proquery, $doc, $xpath, $EOVparent='',$prkey,$fromid,'','',$Resulrpatth1);
							if($proid == ""){
                               $entries=$xpath->query($proquery,$doc);
                               foreach ($entries as $entry) {
                                   $priname = $entry->nodeValue;
                               }
                               $FailReason = $priname." Is Not Availabale (".$proquery.")";
                               if( $excolumn_name == 'productcode'){
                                   $statuscode = 'FN8212';
                                   $statusmsg = 'Invalid Product Code';
                               }else{
                                   $statuscode = 'FN8212';
                                   $statusmsg = 'Invalid Product Code';
                               }
                               sendreceiveaudit($docid, 'Receive', 'Failed', $FailReason, '', $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
							   if($LBL_VALIDATE_RPI_PROD_CODE == 'True'){
								   $insertstatus = '101';
								   fwrite($fpx, $insertstatus."\n");
								   $focus1->trash($TraName, $inserted_Id);
								   updateSubject($moduletablename,'subject',$subjectVal,$focus->table_index,$inserted_Id,$Resulrpatth1);
								   continue 3;
								}
								else{
									$pifocus->column_fields['productname']=0;
									$pifocus->column_fields['productcode']=$priname;
								}
							}
							else
							{
                               $logPRID = "Product Name : ".$proid.PHP_EOL;
                               file_put_contents($Resulrpatth1, $logPRID, FILE_APPEND);
                               $pifocus->column_fields['productname']=$proid;
							}
                       }
                       elseif ($fName == $reluom && !empty($is_process)) 
                       {
                           $logUOM = "If the line item as UOM Name".PHP_EOL;
                           file_put_contents($Resulrpatth1, $logUOM, FILE_APPEND);
                           $UOMquery = $piquery.'/uomname';
                           $UOMid = getExistingObjectValue('UOM', $UOMquery, $doc, $xpath, $EOVparent='','uomname',$fromid,'','',$Resulrpatth1);
                           if ($UOMid == "")
                           {
                               $entries=$xpath->query($UOMquery,$doc);
                               foreach ($entries as $entry) {
                                   $UOMname = $entry->nodeValue;
                               }
                               $FailReason = $UOMname." Is Not Availabale (".$UOMquery.")";
                               $statuscode = 'FN8213';
                               $statusmsg = 'Invalid UOM';
                               sendreceiveaudit($docid, 'Receive', 'Failed', $FailReason, '', $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                               $insertstatus = '100';
                               fwrite($fpx, $insertstatus."\n");
                               $focus1->trash($TraName, $inserted_Id);
							   updateSubject($moduletablename,'subject',$subjectVal,$focus->table_index,$inserted_Id,$Resulrpatth1);
                               continue 3;
                           }
                           else
                           {
                               //Product UOM validation
                               $PUV = checkproductuom($proid,$UOMid);
                               //$PUV = getProductUOMList_withbaseUOM($proid);
                               $logUOMID = "PUV : ".print_r($PUV,true).PHP_EOL;
                               file_put_contents($Resulrpatth1, $logUOMID, FILE_APPEND);
                               
                               if ($PUV == "")
                                {
                                   $entriesp=$xpath->query($proquery,$doc);
                                   foreach ($entriesp as $entry) {
                                      $priname = $entry->nodeValue;
                                   }
                               
                                   $entriesu=$xpath->query($UOMquery,$doc);
                                   foreach ($entriesu as $entry) {
                                       $UOMname = $entry->nodeValue;
                                   }
                               
                                   $FailReason = $priname." & ".$UOMname." are Not Mapped";
                                   $statuscode = 'FN8218';
                                   $statusmsg = $priname." & ".$UOMname." Not Mapped";
                                   sendreceiveaudit($docid, 'Receive', 'Failed', $FailReason, '', $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
								   
									if($LBL_VALIDATE_RPI_PROD_CODE == 'True'){
                                   $insertstatus = '100';
                                   fwrite($fpx, $insertstatus."\n");
                                   $focus1->trash($TraName, $inserted_Id);
								   updateSubject($moduletablename,'subject',$subjectVal,$focus->table_index,$inserted_Id,$Resulrpatth1);
                                   continue 3;
                                }
                                }
                               
                               $logUOMID = "UOM ID : ".$UOMid.PHP_EOL;
                               file_put_contents($Resulrpatth1, $logUOMID, FILE_APPEND);
                               $pifocus->column_fields[''.$reluom.'']=$UOMid;
                           }
                       }
                       elseif ($fName == "prodhierid") 
                       {
                           $logUOM = "If the line item as prodhierid Name".PHP_EOL;
                           file_put_contents($Resulrpatth1, $logUOM, FILE_APPEND);
                           $prodhierquery = $piquery.'/prodhiercode';
                           $prodhierid = getExistingObjectValue('xProdHier', $prodhierquery, $doc, $xpath, $EOVparent='','prodhiercode',$fromid,'','',$Resulrpatth1);
                           if ($prodhierid == "")
                           {
                               $entries=$xpath->query($prodhierquery,$doc);
                               foreach ($entries as $entry) {
                                   $prodhiername = $entry->nodeValue;
                               }
                               $FailReason = $prodhiername." Is Not Availabale (".$prodhierquery.")";
                               $statuscode = 'FN8213';
                               $statusmsg = 'Invalid prodhiercode';
                               sendreceiveaudit($docid, 'Receive', 'Failed', $FailReason, '', $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                               $insertstatus = '100';
                               fwrite($fpx, $insertstatus."\n");
                               $focus1->trash($TraName, $inserted_Id);
							   updateSubject($moduletablename,'subject',$subjectVal,$focus->table_index,$inserted_Id,$Resulrpatth1);
                               continue 3;
                           }
                           else
                           {
                               $logprodhierid = "prodhierid : ".$prodhierid.PHP_EOL;
                               file_put_contents($Resulrpatth1, $logprodhierid, FILE_APPEND);
                               $pifocus->column_fields['prodhierid']=$prodhierid;
                           }
                       }
                       elseif ($fName == "reason")
                       {
                            $reasonquery = $piquery.'/reasoncode';
                            $latitude=$xpath->query($reasonquery,$doc);
                            foreach ($latitude as $entry) {
                                $reasoncode = $entry->nodeValue;
                            }
                            if($reasoncode != '')
                            {
                                $reasonid = '';
                                $logUOM = "If the line item as Reason".PHP_EOL;
                                file_put_contents($Resulrpatth1, $logUOM, FILE_APPEND);
                                $reasonquery = $piquery.'/reasoncode';
                                $reasonid = getExistingObjectValue('xReason', $reasonquery, $doc, $xpath, $EOVparent='','reasoncode',$fromid,'','',$Resulrpatth1);
                                if ($reasonid == "")
                                {
                                    $entries=$xpath->query($reasonquery,$doc);
                                    foreach ($entries as $entry) {
                                        $UOMname = $entry->nodeValue;
                                    }
                                    $FailReason = $UOMname." Is Not Availabale (".$reasonquery.")";
                                    $statuscode = 'FN8213';
                                    $statusmsg = 'Invalid reasoncode';
                                    sendreceiveaudit($docid, 'Receive', 'Failed', $FailReason, '', $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                                    $insertstatus = '100';
                                    fwrite($fpx, $insertstatus."\n");
                                    $focus1->trash($TraName, $inserted_Id);
									updateSubject($moduletablename,'subject',$subjectVal,$focus->table_index,$inserted_Id,$Resulrpatth1);
                                    continue 3;
                                }
                                else
                                {
                                    $logUOMID = "Reason ID : ".$reasonid.PHP_EOL;
                                    file_put_contents($Resulrpatth1, $logUOMID, FILE_APPEND);
                                    $pifocus->column_fields['reason']=$reasonid;
                                }
                            }
                            else
                            {
                                $pifocus->column_fields['reason']='';
                            }
                       }
                       //elseif ($fName=='tax' || $fName=='tax1' || $fName=='tax2' || $fName=='tax3')
                       elseif ($fName=='tax1')
                       {
                           $logTAX = "If the line item as tax Name".PHP_EOL;
                           file_put_contents($Resulrpatth1, $logTAX, FILE_APPEND);
                           $tax1 = $piquery;
                           $taxapp1=$xpath->query($tax1,$doc);
                           foreach ($taxapp1 as $entry) {
                               $tax1amou = $entry->nodeValue;
                           }

                           $pifocus->column_fields['tax1']=$tax1amou;
                           $pifocus->column_fields['tax2']="0";
                           $pifocus->column_fields['tax3']="0";
                           continue 1;

                           for ($index3 = 1; $index3 <= 3; $index3++) {
                               $taxquery = $piquery.'/taxdescription';
                               $tax_appliableon = $piquery.'/taxappliableon';
                               $taxappliableonentries=$xpath->query($tax_appliableon,$doc);
                               foreach ($taxappliableonentries as $entry) {
                                   $taxappliableon = $entry->nodeValue;
                               } 

                               if ($taxappliableon == "")
                                   $taxid = getExistingObjectValue('xTax', $taxquery, $doc, $xpath, $EOVparent='','','','','',$Resulrpatth1);
                               else
                                   $taxid = getExistingObjectValue('xComponent', $taxquery, $doc, $xpath, $EOVparent='','','','','',$Resulrpatth1);

                               if ($taxid == "")
                               {
                                   $entries=$xpath->query($taxquery,$doc);
                                   foreach ($entries as $entry) {
                                       $Taxname = $entry->nodeValue;
                                   }
                                   $FailReason = $Taxname." Is Not Availabale (".$taxquery.")";
                                   $statuscode = 'FN8213';
                                   $statusmsg = 'Invalid xTax';
                                   sendreceiveaudit($docid, 'Receive', 'Failed', $FailReason, '', $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                                   $insertstatus = '100';
                                   fwrite($fpx, $insertstatus."\n");
                                   $adb->mquery("DELETE FROM sify_xtransaction_tax_rel WHERE transaction_id=?",array($inserted_Id));
                                   $adb->mquery("DELETE FROM  $taxTable  WHERE transaction_id=?",array($inserted_Id));
                                   $focus1->trash($TraName, $inserted_Id);
								   updateSubject($moduletablename,'subject',$subjectVal,$focus->table_index,$inserted_Id,$Resulrpatth1);
                                   continue 3;
                                   $pifocus->column_fields['tax'.$index3]="0";
                               }
                               else
                               {
                                   $taxpercentage = $piquery.'/taxpercentage';
                                   $entries=$xpath->query($taxpercentage,$doc);
                                   foreach ($entries as $entry) {
                                       $taxpercen = $entry->nodeValue;
                                   }                                
                                   //$pifocus->column_fields['tax'.$index3]=$taxpercen;

                                   $taxcode = $piquery.'/taxcode';
                                   $entries=$xpath->query($taxcode,$doc);
                                   foreach ($entries as $entry) {
                                       $taxcodeval = $entry->nodeValue;
                                   }

                                   $taxdescription = $piquery.'/taxdescription';
                                   $entries=$xpath->query($taxdescription,$doc);
                                   foreach ($entries as $entry) {
                                       $taxdescriptionval = $entry->nodeValue;
                                   }
                               }
                           }
                           insertTaxRel($piid, '', $TraName, $taxcodeval, $taxdescriptionval, $taxpercen);

                           $taxpercentage1 = $piquery.'/taxpercentage';
                           $entries1=$xpath->query($taxpercentage1,$doc);
                           foreach ($entries1 as $entry) {
                               $taxpercen1 = $entry->nodeValue;
                           }                                
                           $pifocus->column_fields[''.$fName.'']=$taxpercen1;
                       }
                       elseif ($fName=='salesinvoiceid' && $modname == 'xrSalesReturn'){
						   $sitransaction_number = '';
						   $salesinvoiceid = 0;
						   $pifocus->column_fields['salesinvoiceid'] = 0;
						   $siqy = $piquery.'/cf_salesinvoice_transaction_number';
						   $logIN = "siqy : ".$siqy.PHP_EOL;
                           file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
						   $entries=$xpath->query($siqy,$doc);
						   foreach ($entries as $entry) {
							   $sitransaction_number = $entry->nodeValue;
						   }
					   }
					   else
                       {
                           $entries=$xpath->query($piquery,$doc);
                           $ValueS = '';
                           foreach ($entries as $entry) {
                               $ValueS = $entry->nodeValue;
                           }
                           if($fName == 'quantity' && $modname == 'xrSalesOrder'){
                               if(empty($ValueS) or $ValueS <= 0){
                                    $FailReason = "quantity Is Not Availabale";
                                    $statuscode = 'FN8214';
                                    $statusmsg = 'Invalid Quantity';
                                    sendreceiveaudit($docid, 'Receive', 'Failed', $FailReason, '', $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                                    $insertstatus = '100';
                                    fwrite($fpx, $insertstatus."\n");
                                    $focus1->trash($TraName, $inserted_Id);
									updateSubject($moduletablename,'subject',$subjectVal,$focus->table_index,$inserted_Id,$Resulrpatth1);
                                    continue 3;
                               }
                           }elseif($fName == 'recordid' && $modname == 'xrCollection' && empty($ValueS)){
							   $piquery1 = 'collections/'.$moduletablename.'['.$index.']/lineitems/'.$TranTblName.'['.$index2.']/recorddoc';
							   $ValueS = '';
							   $entries=$xpath->query($piquery1,$doc);
							   foreach ($entries as $entry) {
								   $ValueS = $entry->nodeValue;
							   }
							   $logIN = "piquery1: ".$piquery1.PHP_EOL;
							   $logIN .= "xrCollection recorddoc: ".$ValueS.PHP_EOL;
							   file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
							   if(!empty($ValueS)){
								   if(empty($dist_id)){
									   $collectQry1 = "SELECT cf_xrco_distributor FROM vtiger_xrcocf WHERE xrcoid=? ";
									   $collectExec1 = $adb->mquery($collectQry1,array($inserted_Id));
									   $dist_id = $collectExec1->fields['cf_xrco_distributor'];
								   }
								   $collectQry = "SELECT SI.salesinvoiceid,SICF.cf_salesinvoice_sales_invoice_date FROM vtiger_salesinvoicecf SICF INNER JOIN vtiger_salesinvoice SI ON  SI.salesinvoiceid = SICF.salesinvoiceid AND SI.deleted = 0 WHERE SICF.cf_salesinvoice_transaction_number='".$ValueS."' and SICF.cf_salesinvoice_seller_id=? AND SI.status != 'Cancel'order by salesinvoiceid DESC limit 1";
								   $collectExec = $adb->pquery($collectQry,array($dist_id));
								   $recordId = $collectExec->fields['salesinvoiceid'];
								   $pifocus->column_fields['recordid'] = $recordId;
								   $ValueS = $recordId;
							   }
                               $logIN = "xrCollection collectQry : ".$collectQry.PHP_EOL;
                               $logIN .= "xrCollection distid : ".$dist_id.PHP_EOL;
                               $logIN .= "xrCollection recorddoc id: ".$ValueS.PHP_EOL;
							  file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
                           }
                           $pifocus->column_fields[''.$fName.'']=$ValueS;
                       }
                   }
                    
                   if(empty($pifocus->column_fields['baseqty']) && $modname == 'xrSalesOrder'){
                       $productid = $pifocus->column_fields['productname'];
                       $tuom = $pifocus->column_fields['tuom'];
					   if(empty($productid)){
                           $productid = $pifocus->column_fields['productid'];
                       }
                       $uomlist = getuomconversion($productid,$tuom);
                       if(empty($uomlist)){
                            $logIN = "Im in baseqty and conversion value empty".PHP_EOL;
                            file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
                            $pifocus->column_fields['baseqty'] = $pifocus->column_fields['quantity'];
                       }else{
                           $pifocus->column_fields['baseqty'] = $pifocus->column_fields['quantity']*$uomlist;
                           $logIN = "Im in baseqty empty and conversion value ".print_r($uomlist,TRUE).PHP_EOL;
                           file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
                       }
                   }
                   if(empty($pifocus->column_fields['baseqty']) && $modname == 'xrPurchaseInvoice'){
                       $productid = $pifocus->column_fields['productname'];
                       if(empty($productid)){
                           $productid = $pifocus->column_fields['productid'];
                       }
                       $tuom = $pifocus->column_fields['tuom'];
                       $uomlist = getuomconversion($productid,$tuom);
                       if(empty($uomlist)){
                            $logIN = "Im in baseqty and conversion value empty_CHECK".PHP_EOL;
                            file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
                            $pifocus->column_fields['baseqty'] = $pifocus->column_fields['quantity'];
                       }else{
                           $pifocus->column_fields['baseqty'] = $pifocus->column_fields['quantity']*$uomlist;
                           $logIN = "Im in baseqty empty and conversion value ".print_r($uomlist,TRUE).PHP_EOL;
                           file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
                       } 
                   }
				   if ($pifocus->column_fields['productname'] !='' && $modname == 'xrSalesReturn'){
					   
					   $fromidpath = 'collections/'.$moduletablename.'docinfo/fromid';
							$entries_fromid=$xpath->query($fromidpath,$doc);
							foreach ($entries_fromid as $entry) {
								$fromid = $entry->nodeValue;
							}
						   $distid = "SELECT xdistributorid FROM vtiger_xdistributor
								INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xdistributor.xdistributorid
								WHERE vtiger_crmentity.deleted = 0 AND vtiger_xdistributor.distributorcode = '$fromid'";
							$params = array($fromid);
							$distid_Res = $adb->mquery($distid,array());
							if($adb->num_rows($distid_Res)){
								$dist_id = $adb->query_result($distid_Res,0,'xdistributorid');
							}
					  		 $varProdId	= $pifocus->column_fields['productname'];
							 $varSalesinvoiceid = 0;
						   if(!empty($pifocus->column_fields['salesinvoice_no'])){
							   
							   $sigtqy = "SELECT SICF.salesinvoiceid as `salesinvoiceid` FROM vtiger_salesinvoicecf SICF
							   INNER JOIN vtiger_salesinvoice SI ON SI.salesinvoiceid=SICF.salesinvoiceid
							   INNER JOIN vtiger_siproductrel SIRL ON SIRL.id=SICF.salesinvoiceid
							   WHERE cf_salesinvoice_transaction_number = '".$sitransaction_number."' AND SICF.cf_salesinvoice_seller_id = '".$dist_id."' AND SIRL.productid='".$varProdId."' AND SI.status NOT IN('Cancel','Draft') LIMIT 1";
							   $sigtqyexe = $adb->mquery($sigtqy,array());
							   $sigtqycount = $adb->num_rows($sigtqyexe);
							   $varSalesinvoiceid = 0;
							   if($sigtqycount){
								  $varSalesinvoiceid = $adb->query_result($sigtqyexe,0,'salesinvoiceid');
								  if($varSalesinvoiceid !='' && $varSalesinvoiceid >0){
										$refStatusUp=1; 
								  }
							   }
							   $logIN = "sigtqy : ".$sigtqy.PHP_EOL;
							   file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
						   }
						   if(!empty($varSalesinvoiceid)){
							   $pifocus->column_fields['salesinvoiceid'] = $varSalesinvoiceid; 
						   }
						   
				   }
				 $autorsitoPIqy = $adb->mquery("SELECT `value` FROM `sify_inv_mgt_config` WHERE `treatment` = 'sap' AND `key` = 'LBL_CAL_LISTPR_FR_TA_SAP' LIMIT 1");
				 $resConfigRPIexe = $adb->query_result($autorsitoPIqy,0,'value');
				 $LBL_CAL_LISTPR_FR_TA_SAP = $resConfigRPIexe;
				  	 $rpiconverttobase = $adb->mquery("SELECT `value` FROM `sify_inv_mgt_config` WHERE `treatment` = 'sap' AND `key` = 'PI_CONVERT_TO_BASE_UOM' LIMIT 1");
					$PI_CONVERT_TO_BASE_UOM= $adb->query_result($rpiconverttobase,0,'value');
	
				    if($pifocus->column_fields['quantity'] != $pifocus->column_fields['baseqty'] && $PI_CONVERT_TO_BASE_UOM=='True'  && $modname == 'xrPurchaseInvoice'){
						$productid = $pifocus->column_fields['productname'];
						$sqls = "SELECT cf_xproduct_base_uom FROM vtiger_xproductcf where vtiger_xproductcf.xproductid = '".$productid."' LIMIT 1";
						$results 	= 	$adb->mquery($sqls,array());//print_R($result);
						$baseuomid  =   $adb->query_result($results,0,'cf_xproduct_base_uom');
						$tuom 		= $pifocus->column_fields['tuom'];
						$uomlist = getuomconversion($productid,$tuom);
						$pifocus->column_fields['quantity']	     = $pifocus->column_fields['quantity']*$uomlist;
						$pifocus->column_fields['recd_qty'] 	 = $pifocus->column_fields['recd_qty']*$uomlist;
						$pifocus->column_fields['recd_free']     = $pifocus->column_fields['recd_free']*$uomlist;
						$pifocus->column_fields['salable_qty']   = $pifocus->column_fields['salable_qty']*$uomlist;
						$pifocus->column_fields['salable_free']   = $pifocus->column_fields['salable_free']*$uomlist;
						$pifocus->column_fields['tuom']          = "$baseuomid";
				   }
				   
				   if($modname == 'xrPurchaseInvoice' && $PI_CONVERT_TO_BASE_UOM!='True'){
						$tUOMid = $pifocus->column_fields['tuom'];
						$productid = $pifocus->column_fields['productname'];
						$uomlist = getProductUOMList($productid);
						foreach($uomlist as $skey => $val){
							$strkey = str_replace("cf_xproduct_","",$skey);
							$mshideuomquery = $adb->mquery("SELECT `value` FROM `sify_inv_mgt_config` WHERE `treatment` = 'masters' AND `key` = 'MS_HIDED_UOMS' LIMIT 1");
						 	$MS_HIDED_UOMS= $adb->query_result($mshideuomquery,0,'value');
							if($strkey==strtolower($MS_HIDED_UOMS)){
								$sqls = "SELECT cf_xproduct_base_uom FROM vtiger_xproductcf where vtiger_xproductcf.xproductid = '".$productid."' LIMIT 1";
								$results 	= 	$adb->mquery($sqls,array());//print_R($result);
								$baseuomid  =   $adb->query_result($results,0,'cf_xproduct_base_uom');
								if($tUOMid == $val){
									$conv										= $uomlist[$skey."_conversion"];
									$pifocus->column_fields['quantity']	     = $pifocus->column_fields['quantity']*$conv;
									$pifocus->column_fields['recd_qty'] 	 = $pifocus->column_fields['recd_qty']*$conv;
									$pifocus->column_fields['recd_free']     = $pifocus->column_fields['recd_free']*$conv;
									$pifocus->column_fields['salable_qty']   = $pifocus->column_fields['salable_qty']*$conv;
									$pifocus->column_fields['salable_free']   = $pifocus->column_fields['salable_free']*$conv;
									$pifocus->column_fields['tuom']          = "$baseuomid";
									
								}
							}
						}	
				   }
				   
                   $qparams ='';
                   $query ="insert into ".$TranTblName."(".$colum.") values (".$colval.")";
                   $qparams = $pifocus->column_fields;
                   $logIN = "Line Item Insert : ".$query.PHP_EOL;
                   $logIN .= "Line Item Value : ".print_r($qparams,TRUE).PHP_EOL;
                   file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
				   if($TranTblName == 'vtiger_xmjpdetail' ){
					   if($mod != 'edit'){
					    $focus_1= CRMEntity::getInstance('xMJPDetail');
						$focus_1->column_fields['mjpdate'] = $pifocus->column_fields['mjpdate'];
						$focus_1->column_fields['mjpday'] = $pifocus->column_fields['mjpday'];
						$focus_1->column_fields['beatcode'] = $pifocus->column_fields['beatcode'];
						$focus_1->column_fields['beatname'] = $pifocus->column_fields['beatname'];
						$focus_1->column_fields['noofretailers'] = $pifocus->column_fields['noofretailers'];
						$focus_1->column_fields['cf_xmjpdetail_mjp'] = $pifocus->column_fields['cf_xmjpdetail_mjp'];
						$focus_1->save('xMJPDetail');
						$line_id = $focus_1->id;
						unset($focus_1);
					   }
						 $logIN = "IM in  : vtiger_xmjpdetail".PHP_EOL;
						 $logIN .= "line_id val  : ".$line_id.PHP_EOL;
						 file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
				   }else{
					   $adb->mquery($query,$qparams);
					   $line_id = $adb->getLastInsertID();
					   $logIN .= "line_id val  : ".$line_id.PHP_EOL;
						 file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
				   }				   //To get line id 
                   if(isset($productname_new) && !empty($productname_new)){
					   $qparams['productname'] = $productname_new;
				   }
                   if($qparams['productname'] != '')
                   {
                        $taxquerys = 'collections/'.$moduletablename.'['.$index.']/lineitems/'.$TranTblName.'['.$index2.']';
                        $multaxquerys = 'collections/'.$moduletablename.'['.$index.']/lineitems/'.$TranTblName.'['.$index2.']/taxs/tax';
                        $taxquerysentries=$xpath->query($multaxquerys,$doc);
                        $multaxlencount = $taxquerysentries->length;
                        $logIN = "multaxquerys : ".$multaxquerys.PHP_EOL;
                        $logIN .= "multaxlencount : ".print_r($multaxlencount,TRUE).PHP_EOL;
                        file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
                        if($multaxlencount){
							$updated = 1;
                            for ($indext1 = 1; $indext1 <= $multaxlencount; $indext1++) {
                                    $taxable_amt = $tax_typeval = $tax_amotype_val = $taxcomp_name_val = $tax_group_type = $taxcomp_percent_val = $tax_comp_amount_val = $xtaxid = $taxdescription_valq = '';
                                    $taxcode = $multaxquerys.'['.$indext1.']'.'/taxcode';
                                    $taxcode_query =$xpath->query($taxcode,$doc);
                                    foreach ($taxcode_query as $entry) {
                                            $taxcode_val = $entry->nodeValue;
                                    }
									if(!empty($taxcode_val)){
										$taxqury = "SELECT * FROM vtiger_xtax WHERE taxcode = '".$taxcode_val."' LIMIT 1";
										$taxqexe = $adb->mquery($taxqury,array());
										$taxqurycount = $adb->num_rows($fieldResult);
										if($taxqurycount){
											$xtaxid = $adb->query_result($taxqexe,0,'xtaxid');
											$taxdescription_valq = $adb->query_result($taxqexe,0,'taxdescription');
										}
									}
                                    $taxdescription = $multaxquerys.'['.$indext1.']'.'/taxdescription';
                                    $taxdescription_query =$xpath->query($taxdescription,$doc);
                                    foreach ($taxdescription_query as $entry) {
                                            $taxdescription_val = $entry->nodeValue;
                                    }
                                    $lst_per = $multaxquerys.'['.$indext1.']'.'/lst_per';
                                    $lst_per_query =$xpath->query($lst_per,$doc);
                                    foreach ($lst_per_query as $entry) {
                                            $lst_per_val = $entry->nodeValue;
                                    }
                                    $cst_per = $multaxquerys.'['.$indext1.']'.'/cst_per';
                                    $cst_per_query =$xpath->query($cst_per,$doc);
                                    foreach ($cst_per_query as $entry) {
                                            $cst_per_val = $entry->nodeValue;
                                    }

                                    if($lst_per_val == ''|| $lst_per_val == 0)
                                            $tax_percentage = $cst_per_val;
                                    else
                                            $tax_percentage = $lst_per_val;

                                    if($modulename == 'xrSalesInvoice' || $modulename == 'xrPurchaseInvoice')
                                    {
                                            $tax_percentag = $multaxquerys.'['.$indext1.']'.'/tax_percentage';
                                            $tax_percentage_query =$xpath->query($tax_percentag,$doc);
											$tax_percentage = 0;
                                            foreach ($tax_percentage_query as $entry) {
                                                    $tax_percentage = $entry->nodeValue;
                                            }
											if(empty($tax_percentage)){
												$tax_percentag = $multaxquerys.'['.$indext1.']'.'/cst_tax_percentage';
												$tax_percentage_query =$xpath->query($tax_percentag,$doc);
												$tax_percentage = 0;
												foreach ($tax_percentage_query as $entry) {
														$tax_percentage = $entry->nodeValue;
												}
											}
                                    }

                                    $tax_amo = $multaxquerys.'['.$indext1.']'.'/tax_amt';
                                    $tax_amo_query =$xpath->query($tax_amo,$doc);
                                    foreach ($tax_amo_query as $entry) {
                                            $tax_amount = $entry->nodeValue;
                                    }
                                    $tax_amotype = $multaxquerys.'['.$indext1.']'.'/tax_group_type';
                                    $tax_amotype_query =$xpath->query($tax_amotype,$doc);
                                    foreach ($tax_amotype_query as $entry) {
                                            $tax_amotype_val = $entry->nodeValue;
                                    }
                                    $tax_typexm = $multaxquerys.'['.$indext1.']'.'/tax_type';
                                    $tax_typexm_query =$xpath->query($tax_typexm,$doc);
                                    foreach ($tax_typexm_query as $entry) {
                                            $tax_typeval = $entry->nodeValue;
                                    }
                                    
                                    $taxable_amxm = $multaxquerys.'['.$indext1.']'.'/taxable_amt';
                                    $taxable_amxm_query =$xpath->query($taxable_amxm,$doc);
                                    foreach ($taxable_amxm_query as $entry) {
                                            $taxable_amt = $entry->nodeValue;
                                    }

                                    if($tax_amount == '')
                                            $tax_amount = 0;

                                    $taxqueryarray = array();
                                    $taxqueryarray['transaction_id'] = $qparams['id'];
                                    $taxqueryarray['transaction_line_id'] = $line_id;
                                    $taxqueryarray['lineitem_id'] = $qparams['productname'];
                                    $taxqueryarray['transaction_name'] = $modulename;
                                    $taxqueryarray['tax_percentage'] = $tax_percentage;
                                    $taxqueryarray['taxcode_val'] = $taxcode_val;
                                    //$taxqueryarray['taxdescription_val'] = $taxdescription_val;
                                    $taxqueryarray['tax_group_type'] = $tax_amotype_val;
                                    $taxqueryarray['tax_type'] = $taxcode_val;
                                    $taxqueryarray['tax_amount'] = $tax_amount;
                                    $taxqueryarray['taxable_amt'] = $taxable_amt;
									$taxqueryarray['tax_label'] = $taxdescription_valq;
									$taxqueryarray['xtaxid'] = $xtaxid;
                                    $headerTaxflag = 0;
                                    if($modulename == 'xrSalesInvoice'){
                                            $taxcomp_counr_query = $multaxquerys.'['.$indext1.']'.'/tax_components/tax_component';
                                            $taxcomp_counr_val=$xpath->query($taxcomp_counr_query,$doc);
                                            $taxcomp_counr = $taxcomp_counr_val->length;
                                            if($taxcomp_counr > 0 && $sourceapplication == 'merp' && $sourceapplication == 'mobile'){
                                                    $headerTaxflag = 1;
                                            }else{
                                                $tax_reduceamount = 0;
                                                for ($indextcant = 1; $indextcant <= $taxcomp_counr; $indextcant++){
                                                        $_tax_comp_amount = $multaxquerys.'['.$indext1.']'.'/tax_components/tax_component['.$indextcant.']/tax_comp_amount';
                                                        $_taxcomp_name_query =$xpath->query($_tax_comp_amount,$doc);
                                                        $taxcomp_name_val = 0;
                                                         foreach ($_taxcomp_name_query as $entry) {
                                                                $taxcomp_name_val = $entry->nodeValue;
                                                        }
                                                        if(empty($taxcomp_name_val)){
                                                                $taxcomp_name_val = 0;
                                                        }
                                                        $tax_reduceamount = $tax_reduceamount+$taxcomp_name_val;
                                                }
                                                $taxqueryarray['tax_amount'] = $tax_amount-$tax_reduceamount;
                                                $logIN = "Tax total amount : ".$tax_reduceamount.':'.$taxqueryarray['tax_amount'].PHP_EOL;
                                          file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
                                            }
                                    }
                                    if($headerTaxflag == 0){
                                            /*$taxquery = "INSERT INTO sify_xtransaction_tax_rel (transaction_id,transaction_line_id,lineitem_id,transaction_name,tax_percentage,tax_type,tax_label,tax_amt) VALUES ('". $taxqueryarray['transaction_id']."','". $taxqueryarray['transaction_line_id']."','". $taxqueryarray['lineitem_id']."','". $taxqueryarray['transaction_name']."','". $taxqueryarray['tax_percentage']."','". $taxqueryarray['tax_type']."','". $taxqueryarray['taxcode_val']."','". $taxqueryarray['tax_amount']."')";
                                            $adb->mquery($taxquery,array());*/
                                            if($ALLOW_GST_TRANSACTION && $modulename=='xrPurchaseInvoice') {
                                               $taxquery2 = "INSERT INTO sify_xtransaction_tax_rel_rpi (transaction_id,transaction_line_id,lineitem_id,transaction_name,tax_percentage,tax_type,tax_group_type,taxable_amt,tax_amt,tax_label,xtaxid) "
                                                       . "VALUES ('". $taxqueryarray['transaction_id']."','". $taxqueryarray['transaction_line_id']."','". $taxqueryarray['lineitem_id']."','". $taxqueryarray['transaction_name']."',". "'". $taxqueryarray['tax_percentage']."','". $taxqueryarray['taxcode_val']."','". $taxqueryarray['tax_group_type']."','". $taxqueryarray['taxable_amt']."','". $taxqueryarray['tax_amount']."','".$taxqueryarray['tax_label']."','".$taxqueryarray['xtaxid']."')";
                                               $adb->mquery($taxquery2,array());
                                            }
                                            if($ALLOW_GST_TRANSACTION && $modulename=='xrSalesInvoice') {
                                               $taxquery2 = "INSERT INTO sify_xtransaction_tax_rel_rsi (transaction_id,transaction_line_id,lineitem_id,transaction_name,tax_percentage,tax_type,tax_group_type,taxable_amt,tax_amt,tax_label,xtaxid) "
                                                       . "VALUES ('". $taxqueryarray['transaction_id']."','". $taxqueryarray['transaction_line_id']."','". $taxqueryarray['lineitem_id']."','". $taxqueryarray['transaction_name']."',". "'". $taxqueryarray['tax_percentage']."','". $taxqueryarray['taxcode_val']."','". $taxqueryarray['tax_group_type']."','". $taxqueryarray['taxable_amt']."','". $taxqueryarray['tax_amount']."','".$taxqueryarray['tax_label']."','".$taxqueryarray['xtaxid']."')";
                                               $adb->mquery($taxquery2,array());
                                                $logIN = "Tax Insert : ".$taxquery2.PHP_EOL;
                                                $logIN .= "Input Array : ".  print_r($taxqueryarray, TRUE).PHP_EOL;
                                            }
                                            $logIN .= "INside headerTaxflag : ".$headerTaxflag.PHP_EOL;
                                            if(!empty($pifocus->column_fields['listprice']) && $modname == 'xrPurchaseInvoice' && $LBL_CAL_LISTPR_FR_TA_SAP =='True' && !empty($taxable_amt) && !empty($updated)){
                                                $listprice = $pifocus->column_fields['listprice'];
                                                $pirqty   =  $pifocus->column_fields['baseqty'];
                                                $listprice = ($taxable_amt/$pirqty);
                                                if($listprice >0 ){
                                                    $updateqy_list = "update vtiger_xrpiproductrel set listprice = '".$listprice."' where id = '".$qparams['id']."' and lineitem_id = '".$line_id."'";
                                                    $adb->mquery($updateqy_list,array());
                                                    $logIN .= "list price qy : ".$updateqy_list.PHP_EOL;
													$updated = 0;
                                                }
                                                $logIN .= "list price update : ".$listprice.PHP_EOL;
                                            }
                                    }
                                    $logIN .= "headerTaxflag : ".$headerTaxflag.PHP_EOL;
                                    $logIN .= "taxcode Path : ".$taxcode.PHP_EOL;
                                    $logIN .= "Tax Insert : ".$taxquery.PHP_EOL;
                                    $logIN .= "Input Array : ".  print_r($taxqueryarray, TRUE).PHP_EOL;
                                    file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);

                                    if($modulename != 'xrSalesInvoice')
                                    {
										
                                            $taxcomp_counr_query = $multaxquerys.'['.$indext1.']'.'/tax_components/tax_component';

                                            $taxcomp_counr_val=$xpath->query($taxcomp_counr_query,$doc);
                                            $taxcomp_counr = $taxcomp_counr_val->length;

                                            $logIN = "Tax Component Count : ".$taxcomp_counr.PHP_EOL;
                                            $logIN .= "taxcomp_counr_query : ".$taxcomp_counr_query.PHP_EOL;
                                            file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);

                                            if ($taxcomp_counr != 0)
                                            {
                                                    for ($index13 = 1; $index13 <= $taxcomp_counr; $index13++)
                                                    {
                                                        $taxcomp_name_val = $taxcomp_percent_val = $tax_comp_amount_val = $tax_comp_amotype_val = $tax_comp_typeval = $taxable_comp_amt = $taxable_comp_amt_val = '';
                                                        $taxcomp_name = $multaxquerys.'['.$indext1.']'.'/tax_components/tax_component['.$index13.']/taxcode';
														$logIN = "Input Array : ".  print_r($taxcomp_name, TRUE).PHP_EOL;
                                                                file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
                                                        $taxcomp_name_query =$xpath->query($taxcomp_name,$doc);
                                                        foreach ($taxcomp_name_query as $entry) {
                                                            $taxcomp_name_val = $entry->nodeValue;
                                                        }

                                                        $taxcomp_percent = $multaxquerys.'['.$indext1.']'.'/tax_components/tax_component['.$index13.']/tax_percentage';
                                                        $taxcomp_percent_query =$xpath->query($taxcomp_percent,$doc);
                                                        foreach ($taxcomp_percent_query as $entry) {
                                                            $taxcomp_percent_val = $entry->nodeValue;
                                                        }

                                                        $tax_comp_amount = $multaxquerys.'['.$indext1.']'.'/tax_components/tax_component['.$index13.']/tax_amt';
                                                        $tax_comp_amount_query =$xpath->query($tax_comp_amount,$doc);
                                                        foreach ($tax_comp_amount_query as $entry) {
                                                            $tax_comp_amount_val = $entry->nodeValue;
                                                        }
														$tax_comp_amotype = $multaxquerys.'['.$indext1.']'.'/tax_components/tax_component['.$index13.']/tax_group_type';
														$tax_comp_amotype_query =$xpath->query($tax_comp_amotype,$doc);
														foreach ($tax_comp_amotype_query as $entry) {
																$tax_comp_amotype_val = $entry->nodeValue;
														}
														$logIN = "Input tax_comp_amotype : ".  print_r($tax_comp_amotype, TRUE).PHP_EOL;
														$logIN .= "Input tax_comp_amotype_val : ".  print_r($tax_comp_amotype_val, TRUE).PHP_EOL;
                                                        file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
														$tax_comp_typexm = $multaxquerys.'['.$indext1.']'.'/tax_components/tax_component['.$index13.']/tax_type';
														$tax_comp_typexm_query =$xpath->query($tax_comp_typexm,$doc);
														foreach ($tax_comp_typexm_query as $entry) {
																$tax_comp_typeval = $entry->nodeValue;
														}
														$logIN = "Input tax_comp_typexm : ".  print_r($tax_comp_typexm, TRUE).PHP_EOL;
														$logIN .= "Input tax_comp_typexm : ".  print_r($tax_comp_typeval, TRUE).PHP_EOL;
                                                        file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
														$taxable_comp_amxm = $multaxquerys.'['.$indext1.']'.'/tax_components/tax_component['.$index13.']/taxable_amt';
														$taxable_comp_amxm_query =$xpath->query($taxable_comp_amxm,$doc);
														foreach ($taxable_comp_amxm_query as $entry) {
																$taxable_comp_amt_val = $entry->nodeValue;
														}
														$logIN = "Input taxable_comp_amxm : ".  print_r($taxable_comp_amxm, TRUE).PHP_EOL;
														$logIN .= "Input taxable_comp_amt_val : ".  print_r($taxable_comp_amt_val, TRUE).PHP_EOL;
                                                        file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
                                                        $taxqueryarray1 = array();
                                                        if(!empty($taxcomp_percent_val)){
                                                                $taxqueryarray1['transaction_id'] = $qparams['id'];
                                                                $taxqueryarray1['transaction_line_id'] = $line_id;
                                                                $taxqueryarray1['lineitem_id'] = $qparams['productname'];
                                                                $taxqueryarray1['transaction_name'] = $modulename;
                                                                $taxqueryarray1['tax_percentage'] = $taxcomp_percent_val;
                                                                $taxqueryarray1['taxcode_val'] = $taxcomp_name_val;
                                                                $taxqueryarray1['tax_comp_amount'] = $tax_comp_amount_val;
                                                                $taxqueryarray1['tax_amt'] = $tax_comp_amount_val;
                                                                $taxqueryarray1['taxable_amt'] = $taxable_comp_amt_val;
                                                                $taxqueryarray1['tax_group_type'] = $tax_comp_amotype_val;
                                                        }
                                                        if($taxcomp_percent_val != '')
                                                        {
                                                                /*$taxquery1 = "INSERT INTO sify_xtransaction_tax_rel (transaction_id,transaction_line_id,lineitem_id,transaction_name,tax_percentage,tax_type,tax_amt) VALUES ('". $taxqueryarray1['transaction_id']."','". $taxqueryarray1['transaction_line_id']."','". $taxqueryarray1['lineitem_id']."','". $taxqueryarray1['transaction_name']."','". $taxqueryarray1['tax_percentage']."','". $taxqueryarray1['tax_group_type']."','". $taxqueryarray1['tax_amt']."')";
                                                                $adb->mquery($taxquery1,array());*/
                                                                if($ALLOW_GST_TRANSACTION && $modulename=='xrPurchaseInvoice') {
                                                                    $taxquery2 = "INSERT INTO sify_xtransaction_tax_rel_rpi (transaction_id,transaction_line_id,lineitem_id,transaction_name,tax_percentage,tax_type,tax_group_type,taxable_amt,tax_amt,tax_label,xtaxid) "
                                                       . "VALUES ('". $taxqueryarray1['transaction_id']."','". $taxqueryarray1['transaction_line_id']."','". $taxqueryarray1['lineitem_id']."','". $taxqueryarray1['transaction_name']."','". $taxqueryarray1['tax_percentage']."','". $taxqueryarray1['taxcode_val']."','". $taxqueryarray1['tax_group_type']."','". $taxqueryarray1['taxable_amt']."','". $taxqueryarray1['tax_amt']."','".$taxqueryarray1['tax_label']."','".$taxqueryarray1['xtaxid']."')";
																	
                                                                    $adb->mquery($taxquery2,array());
                                                                }
                                                                $logIN = "----xrSalesInvoice TAX Component---".$index13.PHP_EOL;
                                                                $logIN .= "----RSI TAX Component---".$index13.PHP_EOL;
                                                                $logIN .= "Tax Insert : ".$taxquery1.PHP_EOL;
                                                                $logIN .= "Tax component Insert : ".$taxquery2.PHP_EOL;
                                                                $logIN .= "Input Array : ".  print_r($taxqueryarray1, TRUE).PHP_EOL;
                                                                file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
                                                        }
                                                    }
                                            }
                                    }
                                    if($modulename == 'xrSalesInvoice')
                                    {
                                            $taxcomp_counr_query = $multaxquerys.'['.$indext1.']'.'/tax_components/tax_component';

                                            $taxcomp_counr_val=$xpath->query($taxcomp_counr_query,$doc);
                                            $taxcomp_counr = $taxcomp_counr_val->length;

                                            $logIN = "Tax Component Count : ".$taxcomp_counr.PHP_EOL;
                                            $logIN .= "taxcomp_counr_query : ".$taxcomp_counr_query.PHP_EOL;
                                            file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);

                                            if ($taxcomp_counr != 0)
                                            {
                                                    for ($index13 = 1; $index13 <= $taxcomp_counr; $index13++)
                                                    {
                                                        $taxcomp_name_val = $taxcomp_percent_val = $tax_comp_amount_val = $tax_comp_amotype_val = $tax_comp_typeval = $taxable_comp_amt = $taxable_comp_amt_val = '';
                                                        $taxcomp_name = $multaxquerys.'['.$indext1.']'.'/tax_components/tax_component['.$index13.']/taxcode';
														$logIN = "Input Array : ".  print_r($taxcomp_name, TRUE).PHP_EOL;
                                                                file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
                                                        $taxcomp_name_query =$xpath->query($taxcomp_name,$doc);
                                                        foreach ($taxcomp_name_query as $entry) {
                                                            $taxcomp_name_val = $entry->nodeValue;
                                                        }

                                                        $taxcomp_percent = $multaxquerys.'['.$indext1.']'.'/tax_components/tax_component['.$index13.']/tax_percentage';
                                                        $taxcomp_percent_query =$xpath->query($taxcomp_percent,$doc);
                                                        foreach ($taxcomp_percent_query as $entry) {
                                                            $taxcomp_percent_val = $entry->nodeValue;
                                                        }

                                                        $tax_comp_amount = $multaxquerys.'['.$indext1.']'.'/tax_components/tax_component['.$index13.']/tax_amt';
                                                        $tax_comp_amount_query =$xpath->query($tax_comp_amount,$doc);
                                                        foreach ($tax_comp_amount_query as $entry) {
                                                            $tax_comp_amount_val = $entry->nodeValue;
                                                        }
														$tax_comp_amotype = $multaxquerys.'['.$indext1.']'.'/tax_components/tax_component['.$index13.']/tax_group_type';
														$tax_comp_amotype_query =$xpath->query($tax_comp_amotype,$doc);
														foreach ($tax_comp_amotype_query as $entry) {
																$tax_comp_amotype_val = $entry->nodeValue;
														}
														$logIN = "Input tax_comp_amotype : ".  print_r($tax_comp_amotype, TRUE).PHP_EOL;
														$logIN .= "Input tax_comp_amotype_val : ".  print_r($tax_comp_amotype_val, TRUE).PHP_EOL;
                                                        file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
														$tax_comp_typexm = $multaxquerys.'['.$indext1.']'.'/tax_components/tax_component['.$index13.']/tax_type';
														$tax_comp_typexm_query =$xpath->query($tax_comp_typexm,$doc);
														foreach ($tax_comp_typexm_query as $entry) {
																$tax_comp_typeval = $entry->nodeValue;
														}
														$logIN = "Input tax_comp_typexm : ".  print_r($tax_comp_typexm, TRUE).PHP_EOL;
														$logIN .= "Input tax_comp_typexm : ".  print_r($tax_comp_typeval, TRUE).PHP_EOL;
                                                        file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
														$taxable_comp_amxm = $multaxquerys.'['.$indext1.']'.'/tax_components/tax_component['.$index13.']/taxable_amt';
														$taxable_comp_amxm_query =$xpath->query($taxable_comp_amxm,$doc);
														foreach ($taxable_comp_amxm_query as $entry) {
																$taxable_comp_amt_val = $entry->nodeValue;
														}
														$logIN = "Input taxable_comp_amxm : ".  print_r($taxable_comp_amxm, TRUE).PHP_EOL;
														$logIN .= "Input taxable_comp_amt_val : ".  print_r($taxable_comp_amt_val, TRUE).PHP_EOL;
                                                        file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
                                                        $taxqueryarray1 = array();
                                                        if(!empty($taxcomp_percent_val)){
                                                                $taxqueryarray1['transaction_id'] = $qparams['id'];
                                                                $taxqueryarray1['transaction_line_id'] = $line_id;
                                                                $taxqueryarray1['lineitem_id'] = $qparams['productname'];
                                                                $taxqueryarray1['transaction_name'] = $modulename;
                                                                $taxqueryarray1['tax_percentage'] = $taxcomp_percent_val;
                                                                $taxqueryarray1['taxcode_val'] = $taxcomp_name_val;
                                                                $taxqueryarray1['tax_comp_amount'] = $tax_comp_amount_val;
                                                                $taxqueryarray1['tax_amt'] = $tax_comp_amount_val;
                                                                $taxqueryarray1['taxable_amt'] = $taxable_comp_amt_val;
                                                                $taxqueryarray1['tax_group_type'] = $tax_comp_amotype_val;
                                                        }
                                                        if($taxcomp_percent_val != '')
                                                        {
                                                                /*$taxquery1 = "INSERT INTO sify_xtransaction_tax_rel (transaction_id,transaction_line_id,lineitem_id,transaction_name,tax_percentage,tax_type,tax_amt) VALUES ('". $taxqueryarray1['transaction_id']."','". $taxqueryarray1['transaction_line_id']."','". $taxqueryarray1['lineitem_id']."','". $taxqueryarray1['transaction_name']."','". $taxqueryarray1['tax_percentage']."','". $taxqueryarray1['tax_group_type']."','". $taxqueryarray1['tax_amt']."')";
                                                                $adb->mquery($taxquery1,array());*/
                                                                if($ALLOW_GST_TRANSACTION) {
                                                                    $taxquery2 = "INSERT INTO sify_xtransaction_tax_rel_rsi (transaction_id,transaction_line_id,lineitem_id,transaction_name,tax_percentage,tax_type,tax_group_type,taxable_amt,tax_amt,tax_label,xtaxid) "
                                                       . "VALUES ('". $taxqueryarray1['transaction_id']."','". $taxqueryarray1['transaction_line_id']."','". $taxqueryarray1['lineitem_id']."','". $taxqueryarray1['transaction_name']."','". $taxqueryarray1['tax_percentage']."','". $taxqueryarray1['taxcode_val']."','". $taxqueryarray1['tax_group_type']."','". $taxqueryarray1['taxable_amt']."','". $taxqueryarray1['tax_amt']."','".$taxqueryarray1['tax_label']."','".$taxqueryarray1['xtaxid']."')";
																	
                                                                    $adb->mquery($taxquery2,array());
                                                                }
                                                                $logIN = "----xrSalesInvoice TAX Component---".$index13.PHP_EOL;
                                                                $logIN .= "----RSI TAX Component---".$index13.PHP_EOL;
                                                                $logIN .= "Tax Insert : ".$taxquery1.PHP_EOL;
                                                                $logIN .= "Tax component Insert : ".$taxquery2.PHP_EOL;
                                                                $logIN .= "Input Array : ".  print_r($taxqueryarray1, TRUE).PHP_EOL;
                                                                file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
                                                        }
                                                    }
                                            }
                                }
                                }
                        }else{
                            $taxcomp_name_val = $taxcomp_percent_val = $tax_comp_amount_val = '';
						   $taxcode = $taxquerys.'/taxcode';
							$taxcode_query =$xpath->query($taxcode,$doc);
							foreach ($taxcode_query as $entry) {
								$taxcode_val = $entry->nodeValue;
							}
							$taxdescription = $taxquerys.'/taxdescription';
							$taxdescription_query =$xpath->query($taxdescription,$doc);
							foreach ($taxdescription_query as $entry) {
								$taxdescription_val = $entry->nodeValue;
							}
							$lst_per = $taxquerys.'/lst_per';
							$lst_per_query =$xpath->query($lst_per,$doc);
							foreach ($lst_per_query as $entry) {
								$lst_per_val = $entry->nodeValue;
							}
							$cst_per = $taxquerys.'/cst_per';
							$cst_per_query =$xpath->query($cst_per,$doc);
							foreach ($cst_per_query as $entry) {
								$cst_per_val = $entry->nodeValue;
							}
							
							if($lst_per_val == ''|| $lst_per_val == 0)
								$tax_percentage = $cst_per_val;
							else
								$tax_percentage = $lst_per_val;
							
							if($modulename == 'xrSalesInvoice')
							{
								$tax_percentag = $taxquerys.'/tax_percentage';
								$tax_percentage_query =$xpath->query($tax_percentag,$doc);
								foreach ($tax_percentage_query as $entry) {
									$tax_percentage = $entry->nodeValue;
								}
							}
							
							$tax_amo = $taxquerys.'/tax1';
							$tax_amo_query =$xpath->query($tax_amo,$doc);
							foreach ($tax_amo_query as $entry) {
								$tax_amount = $entry->nodeValue;
							}
							
							if($tax_amount == '')
								$tax_amount = 0;
							
							$taxqueryarray = array();
							$taxqueryarray['transaction_id'] = $qparams['id'];
							$taxqueryarray['transaction_line_id'] = $line_id;
							$taxqueryarray['lineitem_id'] = $qparams['productname'];
							$taxqueryarray['transaction_name'] = $modulename;
							$taxqueryarray['tax_percentage'] = $tax_percentage;
							$taxqueryarray['taxcode_val'] = $taxcode_val;
							$taxqueryarray['taxdescription_val'] = $taxdescription_val;
							$taxqueryarray['tax_amount'] = $tax_amount;
							$headerTaxflag = 0;
							if($modulename == 'xrSalesInvoice'){
								$taxcomp_counr_query = $taxquerys.'/tax_components/tax_component';
								$taxcomp_counr_val=$xpath->query($taxcomp_counr_query,$doc);
								$taxcomp_counr = $taxcomp_counr_val->length;
								if($taxcomp_counr > 0 && $sourceapplication == 'merp'){
									$headerTaxflag = 1;
								}else{
										$tax_reduceamount = 0;
										for ($indextcant = 1; $indextcant <= $taxcomp_counr; $indextcant++){
											$_tax_comp_amount = $taxquerys.'/tax_components/tax_component['.$indextcant.']/tax_comp_amount';
											$_taxcomp_name_query =$xpath->query($_tax_comp_amount,$doc);
											$taxcomp_name_val = 0;
											 foreach ($_taxcomp_name_query as $entry) {
												$taxcomp_name_val = $entry->nodeValue;
											}
											if(empty($taxcomp_name_val)){
												$taxcomp_name_val = 0;
											}
											$tax_reduceamount = $tax_reduceamount+$taxcomp_name_val;
										}
										$taxqueryarray['tax_amount'] = $tax_amount-$tax_reduceamount;
										$logIN = "Tax total amount : ".$tax_reduceamount.':'.$taxqueryarray['tax_amount'].PHP_EOL;
									  file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
								}
							}
							if($headerTaxflag == 0){
								/*$taxquery = "INSERT INTO sify_xtransaction_tax_rel (transaction_id,transaction_line_id,lineitem_id,transaction_name,tax_percentage,tax_type,tax_label,tax_amt) VALUES (?,?,?,?,?,?,?,?)";
								$adb->mquery($taxquery,$taxqueryarray);*/
								if($ALLOW_GST_TRANSACTION && $modulename=='xrPurchaseInvoice') {
								   $taxquery2 = "INSERT INTO sify_xtransaction_tax_rel_rpi (transaction_id,transaction_line_id,lineitem_id,transaction_name,tax_percentage,tax_type,tax_amt) VALUES (?,?,?,?,?,?,?)";
								   $adb->mquery($taxquery,$taxqueryarray);
								}
								$logIN = "INside headerTaxflag : ".$headerTaxflag.PHP_EOL;
							}
							$logIN = "headerTaxflag : ".$headerTaxflag.PHP_EOL;
							$logIN .= "taxcode Path : ".$taxcode.PHP_EOL;
							$logIN .= "Tax Insert : ".$taxquery.PHP_EOL;
							$logIN .= "Input Array : ".  print_r($taxqueryarray, TRUE).PHP_EOL;
							file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
							
							if($modulename != 'xrSalesInvoice')
							{
								$taxcomp_name = $taxquerys.'/taxcomp_name';
								$taxcomp_name_query =$xpath->query($taxcomp_name,$doc);
								foreach ($taxcomp_name_query as $entry) {
									$taxcomp_name_val = $entry->nodeValue;
								}
								$taxcomp_percent = $taxquerys.'/taxcomp_percent';
								$taxcomp_percent_query =$xpath->query($taxcomp_percent,$doc);
								foreach ($taxcomp_percent_query as $entry) {
									$taxcomp_percent_val = $entry->nodeValue;
								}

								$taxqueryarray1 = array();
								$taxqueryarray1['transaction_id'] = $qparams['id'];
								$taxqueryarray1['transaction_line_id'] = $line_id;
								$taxqueryarray1['lineitem_id'] = $qparams['productname'];
								$taxqueryarray1['transaction_name'] = $modulename;
								$taxqueryarray1['tax_percentage'] = $taxcomp_percent_val;
								$taxqueryarray1['taxcode_val'] = $taxcomp_name_val;
							}
							
							if($taxcomp_percent_val != '')
							{
								/*$taxquery1 = "INSERT INTO sify_xtransaction_tax_rel (transaction_id,transaction_line_id,lineitem_id,transaction_name,tax_percentage,tax_type,tax_amt) VALUES (?,?,?,?,?,?,?)";
								$adb->mquery($taxquery1,$taxqueryarray1);*/
								if($ALLOW_GST_TRANSACTION && $modulename=='xrPurchaseInvoice') {
									$taxquery2 = "INSERT INTO sify_xtransaction_tax_rel_rpi (transaction_id,transaction_line_id,lineitem_id,transaction_name,tax_percentage,tax_type,tax_amt) VALUES (?,?,?,?,?,?,?)";
									$adb->mquery($taxquery1,$taxqueryarray1);
								}
								$logIN = "Tax Insert : ".$taxquery1.PHP_EOL;
								$logIN .= "Input Array : ".  print_r($taxqueryarray1, TRUE).PHP_EOL;
								file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
							}
							
							if($modulename == 'xrSalesInvoice')
							{
								$taxcomp_counr_query = $taxquerys.'/tax_components/tax_component';
								
								$taxcomp_counr_val=$xpath->query($taxcomp_counr_query,$doc);
								$taxcomp_counr = $taxcomp_counr_val->length;
								
								$logIN = "Tax Component Count : ".$taxcomp_counr.PHP_EOL;
								file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
								
								if ($taxcomp_counr != 0)
								{
									for ($index13 = 1; $index13 <= $taxcomp_counr; $index13++)
									{
										$taxcomp_name_val = $taxcomp_percent_val = $tax_comp_amount_val = '';
										$taxcomp_name = $taxquerys.'/tax_components/tax_component['.$index13.']/taxcomp_name';
										$taxcomp_name_query =$xpath->query($taxcomp_name,$doc);
										foreach ($taxcomp_name_query as $entry) {
											$taxcomp_name_val = $entry->nodeValue;
										}

										$taxcomp_percent = $taxquerys.'/tax_components/tax_component['.$index13.']/taxcomp_percent';
										$taxcomp_percent_query =$xpath->query($taxcomp_percent,$doc);
										foreach ($taxcomp_percent_query as $entry) {
											$taxcomp_percent_val = $entry->nodeValue;
										}

										$tax_comp_amount = $taxquerys.'/tax_components/tax_component['.$index13.']/tax_comp_amount';
										$tax_comp_amount_query =$xpath->query($tax_comp_amount,$doc);
										foreach ($tax_comp_amount_query as $entry) {
											$tax_comp_amount_val = $entry->nodeValue;
										}

										$taxqueryarray1 = array();
										if(!empty($taxcomp_name_val)){
											$taxqueryarray1['transaction_id'] = $qparams['id'];
											$taxqueryarray1['transaction_line_id'] = $line_id;
											$taxqueryarray1['lineitem_id'] = $qparams['productname'];
											$taxqueryarray1['transaction_name'] = $modulename;
											$taxqueryarray1['tax_percentage'] = $taxcomp_percent_val;
											$taxqueryarray1['taxcode_val'] = $taxcomp_name_val;
											$taxqueryarray1['tax_comp_amount'] = $tax_comp_amount_val;
										}
										if($taxcomp_percent_val != '')
										{
											/*$taxquery1 = "INSERT INTO sify_xtransaction_tax_rel (transaction_id,transaction_line_id,lineitem_id,transaction_name,tax_percentage,tax_type,tax_amt) VALUES (?,?,?,?,?,?,?)";
											$adb->mquery($taxquery1,$taxqueryarray1);*/
											if($ALLOW_GST_TRANSACTION) {
													$taxquery2 = "INSERT INTO sify_xtransaction_tax_rel_rsi (transaction_id,transaction_line_id,lineitem_id,transaction_name,tax_percentage,tax_type,tax_amt) VALUES (?,?,?,?,?,?,?)";
													$adb->mquery($taxquery2,$taxqueryarray1);
											}
											$logIN = "----xrSalesInvoice TAX Component---".$index13.PHP_EOL;
											 $logIN = "----RSI TAX Component---".$index13.PHP_EOL;
											$logIN .= "Tax Insert : ".$taxquery1.PHP_EOL;
											$logIN .= "Input Array : ".  print_r($taxqueryarray1, TRUE).PHP_EOL;
											file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
										}
									}
								}
							}
						} 
                            $scheme_counr_query = $taxquerys.'/schemes/scheme';
                            
                            $scheme_counr_val=$xpath->query($scheme_counr_query,$doc);
                            $scheme_counr = $scheme_counr_val->length;
                            
                            if($scheme_counr != 0)
                            {
                                $schTrprorel=$adb->mquery("SELECT columnname FROM sify_tr_grid_field WHERE tablename = 'vtiger_itemwise_scheme_receive' AND xmlreceivetable = '1' ORDER BY columnname",array());

                                $loggrd = "Number of Records in sify_tr_grid_field For Scheme Table : ".$adb->num_rows($schTrprorel).PHP_EOL;
                                file_put_contents($Resulrpatth1, $loggrd, FILE_APPEND);

                                if($adb->num_rows($schTrprorel)>0)
                                {
                                    $schtrfields = array();
                                    $schcolum = '';
                                    $schcolval = '';
                                    
                                    for ($index15 = 0; $index15 < $adb->num_rows($schTrprorel); $index15++)
                                    {
                                        $schtr_fields=$adb->query_result($schTrprorel,$index15,0);
                                        $schtrfields[] = $schtr_fields;
                                        if ($schcolum == "")
                                        {
                                            $schcolum = $schtr_fields;
                                            $schcolval = "?";
                                        }   
                                        else
                                        {
                                            $schcolum = $schcolum.",".$schtr_fields;
                                            $schcolval = $schcolval.",?";
                                        }
                                    }
                                }
                                $logco = "-------------Scheme Start-------------".PHP_EOL;
                                $logco .= "No of Scheme : ".$scheme_counr.PHP_EOL;
                                $logco .= "Scheme Colum : ".$schcolum." , Schem Colum Valus : ".$schcolval.PHP_EOL;
                                file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                                                                              
                                for ($index14 = 1; $index14 <= $scheme_counr; $index14++)
                                {
                                    for ($index16 = 0; $index16 < count($schtrfields); $index16++)
                                    {
                                        
                                        $schfName=$schtrfields[$index16];
                                        
                                        $schquery = $taxquerys.'/schemes/scheme['.$index14.']/';
                                        
                                        $schquery .= $schfName;

                                        $logPFL = "XML Scheme Path Field Level : ".$schquery.PHP_EOL;
                                        file_put_contents($Resulrpatth1, $logPFL, FILE_APPEND);

                                        IF ($schfName == 'transaction_id')
                                           $schfocus->column_fields['transaction_id']=$qparams['id'];
                                        ELSEIF ($schfName == 'lineitem_id')
                                            $schfocus->column_fields['lineitem_id']=$line_id;
                                        ELSEIF ($schfName == 'fproductcode')
                                        {
                                            $scheme_query1 =$xpath->query($schquery,$doc);
                                            foreach ($scheme_query1 as $entry) {
                                                $fpro_val = $entry->nodeValue;
                                            }
                                            
                                            if($fpro_val != '')
                                            {
                                                $logPR = "If the Free item as productname".PHP_EOL;
                                                file_put_contents($Resulrpatth1, $logPR, FILE_APPEND);

                                                $fproid = getExistingObjectValue('xproduct', $schquery, $doc, $xpath, $EOVparent='',$prkey,$fromid,'','',$Resulrpatth1);
                                                if ($fproid == ""){
                                                    $entries=$xpath->query($schquery,$doc);
                                                    foreach ($entries as $entry) {
                                                        $priname = $entry->nodeValue;
                                                    }
                                                    $FailReason = $priname." Free Product Availabale (".$schquery.")";
                                                    if( $excolumn_name == 'productcode'){
                                                        $statuscode = 'FN8212';
                                                        $statusmsg = 'Invalid Product Code';
                                                    }else{
                                                        $statuscode = 'FN8212';
                                                        $statusmsg = 'Invalid Product Code';
                                                    }
                                                    sendreceiveaudit($docid, 'Receive', 'Failed', $FailReason, '', $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                                                    $insertstatus = '101';
                                                    fwrite($fpx, $insertstatus."\n");
                                                    $focus1->trash($TraName, $inserted_Id);
													updateSubject($moduletablename,'subject',$subjectVal,$focus->table_index,$inserted_Id,$Resulrpatth1);
                                                    continue 3;
                                                }
                                                else
                                                {
                                                    $logPRID = "Free Product Id : ".$fproid.PHP_EOL;
                                                    file_put_contents($Resulrpatth1, $logPRID, FILE_APPEND);
                                                    $schfocus->column_fields[''.$schfName.''] = $fproid;
                                                }
                                            }
                                            ELSE
                                            {
                                                $schfocus->column_fields[''.$schfName.''] = $fpro_val;
                                            }
                                        }
                                        ELSEIF ($schfName == 'ftuom')
                                        {
                                             $scheme_query2 =$xpath->query($schquery,$doc);
                                            foreach ($scheme_query2 as $entry) {
                                                $fuom = $entry->nodeValue;
                                            }
                                            
                                            if($fuom != '')
                                            {
                                                $logUOM = "If the Free item as UOM Name".PHP_EOL;
                                                file_put_contents($Resulrpatth1, $logUOM, FILE_APPEND);
                                                
                                                $fUOMid = getExistingObjectValue('UOM', $schquery, $doc, $xpath, $EOVparent='','uomname',$fromid,'','',$Resulrpatth1);
                                                if ($fUOMid == "")
                                                {
                                                    $entries=$xpath->query($schquery,$doc);
                                                    foreach ($entries as $entry) {
                                                        $UOMname = $entry->nodeValue;
                                                    }
                                                    $FailReason = $UOMname." Free UOM Availabale (".$schquery.")";
                                                    $statuscode = 'FN8213';
                                                    $statusmsg = 'Invalid Free UOM';
                                                    sendreceiveaudit($docid, 'Receive', 'Failed', $FailReason, '', $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                                                    $insertstatus = '100';
                                                    fwrite($fpx, $insertstatus."\n");
                                                    $focus1->trash($TraName, $inserted_Id);
													updateSubject($moduletablename,'subject',$subjectVal,$focus->table_index,$inserted_Id,$Resulrpatth1);
                                                    continue 3;
                                                }
                                                else
                                                {
                                                    $logUOMID = "Free UOM ID : ".$fUOMid.PHP_EOL;
                                                    file_put_contents($Resulrpatth1, $logUOMID, FILE_APPEND);
                                                     $schfocus->column_fields[''.$schfName.''] = $fUOMid;
                                                }
                                            }
                                            ELSE
                                            {
                                                $schfocus->column_fields[''.$schfName.''] = $fuom;
                                            }
                                        }
                                        ELSE
                                        {
                                            $scheme_query =$xpath->query($schquery,$doc);
                                            foreach ($scheme_query as $entry) {
                                                $scheme_val = $entry->nodeValue;
                                            }
                                            $schfocus->column_fields[''.$schfName.'']=$scheme_val;
                                        }
                                    }
                                    $schqparams ='';
                                    $schquery ="insert into vtiger_itemwise_scheme_receive (".$schcolum.") values (".$schcolval.")";
                                    $schqparams = $schfocus->column_fields;
                                    $logIN = "Scheme Insert : ".$schquery.PHP_EOL;
                                    $logIN .= "Scheme Value : ".print_r($schqparams,TRUE).PHP_EOL;
                                    $logIN .= "-------------Scheme End-------------".PHP_EOL;
                                    file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
                                    $adb->mquery($schquery,$schqparams);
                                    
//                                    if ($schfocus->column_fields['fproductcode'] != '')
//                                    {
//                                        $logIN = "-------------Free Product insert Start-------------".PHP_EOL;
//                                        $logIN .= "RSI Product Aray : ".print_r($piFieldsArray,TRUE).PHP_EOL;
//                                        file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
//                                        
//                                        for ($index17 = 0; $index17 < count($piFieldsArray); $index17++)
//                                        {
//                                            $freeline=$piFieldsArray[$index17];
//                                            
//                                            if ($freeline == 'id')
//                                                $fpifocus->column_fields['id']=$qparams['id'];
//                                            elseif($freeline == 'productid')
//                                                $fpifocus->column_fields['productid']=$schfocus->column_fields['fproductcode'];
//                                            elseif($freeline == 'productcode')
//                                                $fpifocus->column_fields['productcode']=$fpro_val;
//                                            elseif($freeline == 'product_type')
//                                                $fpifocus->column_fields['product_type']='Scheme';
//                                            elseif($freeline == 'sequence_no')
//                                                $fpifocus->column_fields['sequence_no']='';
//                                            elseif($freeline == 'quantity')
//                                                $fpifocus->column_fields['quantity']=$schfocus->column_fields['fqty'];
//                                            elseif($freeline == 'baseqty')
//                                                $fpifocus->column_fields['baseqty']=$schfocus->column_fields['fbaseqty'];
//                                            elseif($freeline == 'batchcode')
//                                                $fpifocus->column_fields['batchcode']=$schfocus->column_fields['fbatchnumber'];
//                                            elseif($freeline == 'discount_amount')
//                                                $fpifocus->column_fields['discount_amount']=$schfocus->column_fields['disc_amount'];
//                                            elseif($freeline == 'discount_percent')
//                                                $fpifocus->column_fields['discount_percent']=$schfocus->column_fields['disc_value'];
//                                            elseif($freeline == 'ecp')
//                                                $fpifocus->column_fields['ecp']=$schfocus->column_fields['fecp'];
//                                            elseif($freeline == 'expiry')
//                                                $fpifocus->column_fields['expiry']=$schfocus->column_fields['fexpiry'];
//                                            elseif($freeline == 'listprice')
//                                                $fpifocus->column_fields['listprice']=$schfocus->column_fields['flistprice'];
//                                            elseif($freeline == 'mrp')
//                                                $fpifocus->column_fields['mrp']=$schfocus->column_fields['fmrp'];
//                                            elseif($freeline == 'pkg')
//                                                $fpifocus->column_fields['pkg']=$schfocus->column_fields['fpkd'];
//                                            elseif($freeline == 'ptr')
//                                                $fpifocus->column_fields['ptr']=$schfocus->column_fields['fptr'];
//                                            elseif($freeline == 'pts')
//                                                $fpifocus->column_fields['pts']=$schfocus->column_fields['fpts'];
//                                            elseif($freeline == 'sale_to_free')
//                                                $fpifocus->column_fields['sale_to_free']=$schfocus->column_fields['stock_type'];
//                                            elseif($freeline == 'scheme_code')
//                                                $fpifocus->column_fields['scheme_code']=$schfocus->column_fields['scheme_code'];
//                                            elseif($freeline == 'tuom')
//                                                $fpifocus->column_fields['tuom']=$schfocus->column_fields['ftuom'];
//                                            elseif($freeline == 'points')
//                                                $fpifocus->column_fields['points']='';
//                                            elseif($freeline == 'free_qty')
//                                                $fpifocus->column_fields['free_qty']='';
//                                            elseif($freeline == 'dispatchqty')
//                                                $fpifocus->column_fields['dispatchqty']='';
//                                            elseif($freeline == 'description')
//                                                $fpifocus->column_fields['description']='';
//                                            elseif($freeline == 'dam_qty')
//                                                $fpifocus->column_fields['dam_qty']='';
//                                            elseif($freeline == 'comment')
//                                                $fpifocus->column_fields['comment']='';
//                                            elseif($freeline == 'reflineid')
//                                                $fpifocus->column_fields['reflineid']='';
//                                            elseif($freeline == 'reftrantype')
//                                                $fpifocus->column_fields['reftrantype']='';
//                                            elseif($freeline == 'sch_disc_amount')
//                                                $fpifocus->column_fields['sch_disc_amount']=0;
//                                            elseif($freeline == 'tax1')
//                                                $fpifocus->column_fields['tax1']=0;
//                                            elseif($freeline == 'tax2')
//                                                $fpifocus->column_fields['tax2']=0;
//                                            elseif($freeline == 'tax3')
//                                                $fpifocus->column_fields['tax3']=0;
//                                            
//                                        }
//                                        $freeval = $fpifocus->column_fields;
//                                        $logIN = "Free Item Insert : ".$query.PHP_EOL;
//                                        $logIN .= "Free Item Value : ".print_r($freeval,TRUE).PHP_EOL;
//                                        $adb->mquery($query,$freeval);
//                                        $logIN .= "-------------Free Product insert End-------------".PHP_EOL;
//                                        file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
//                                    }
                                    
                                }
                            }
                        
                    }
                   
                   if($modulename == 'xrPurchaseInvoice')
                   {
                       $tyupdate = "UPDATE vtiger_xrpi SET taxtype = 'individual' WHERE xrpiid = ?"; 
                       $adb->mquery($tyupdate,array($qparams['id']));
                       
                       $forum_code_query = 'collections/'.$moduletablename.'['.$index.']'.'/forum_code';
                        $forum_code=$xpath->query($forum_code_query,$doc);
                         foreach ($forum_code as $entry)
                         {
                             $nodevalueX_forumcode = $entry->nodeValue;
                         }
                         if($nodevalueX_forumcode != '')
                         {
                             $distdet = GetDistributorDetailFromForumCode($nodevalueX_forumcode);
                             if($distdet != '')
                             {
                                 $adb->mquery("UPDATE vtiger_xrpicf SET cf_purchaseinvoice_buyer_id = ? WHERE xrpiid = ? ",array($distdet['distributorcode'],$inserted_Id));

                                 $log = "Forum code based distributor code Update".PHP_EOL;
                                 $log .= "Updated Distributor Code : ".$distdet['distributorcode'].PHP_EOL;
                                 file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                             }
                         }
                       
                       $poidsql = "SELECT
                                        POCF.purchaseorderid,PI.xrpiid,PI.purchaseorder_no,PI.purchaseorder_date,D.xdistributorid
                                    FROM vtiger_xrpi PI
                                    INNER JOIN vtiger_purchaseordercf POCF ON POCF.cf_purchaseorder_transaction_number = PI.purchaseorder_no
                                    INNER JOIN vtiger_xrpicf PICF ON PICF.xrpiid = PI.xrpiid
                                    INNER JOIN vtiger_xdistributor D ON D.distributorcode = PICF.cf_purchaseinvoice_buyer_id
                                    WHERE POCF.cf_purchaseorder_buyer_id = D.xdistributorid AND PI.xrpiid = ?";
                       $poidsqlres = $adb->mquery($poidsql,array($qparams['id']));
                       
                       //$log = "PO Get Query : ".print_r($poidsqlres, true).PHP_EOL;
                       $log = "Related PO count : ".$adb->num_rows($poidsqlres).PHP_EOL;
                       $log .= "PO Get Query : ".$poidsql.PHP_EOL;
                       $log .= "RPI Id : ".$qparams['id'].PHP_EOL;
                       file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                       
                       if($adb->num_rows($poidsqlres)>0)
                       {
                           $purchaseorderid=$adb->query_result($poidsqlres,0,'purchaseorderid');    
                           $poupdate_status = "UPDATE vtiger_purchaseorder SET next_stage_name = 'Create PI' WHERE purchaseorderid = ?"; 
                           $adb->mquery($poupdate_status,array($purchaseorderid));
                           
                           $poupdate_statuscf = "UPDATE vtiger_purchaseordercf SET cf_purchaseorder_next_stage_name = 'Create PI' WHERE purchaseorderid = ?"; 
                           $adb->mquery($poupdate_statuscf,array($purchaseorderid));
                           
                           $log = "PO Id : ".$purchaseorderid.PHP_EOL;
                           $log .= "PO Update Query Main : ".$poupdate_status.PHP_EOL;
                           $log .= "PO Update Query cf : ".$poupdate_statuscf.PHP_EOL;
                           file_put_contents($Resulrpatth1, $log, FILE_APPEND);    
                       }
                   }
                   //Serial Number Insert
                   $piqueryserial = 'collections/'.$moduletablename.'['.$index.']/lineitems/'.$TranTblName.'['.$index2.']/serialnumber';
                   $serial=$xpath->query($piqueryserial,$doc);
                    foreach ($serial as $entry) {
                        $serialval = $entry->nodeValue;
                    }
                   if($serialval != '')
                   {
                        $logIN = "Serial Path :".$piqueryserial.PHP_EOL;
                        $logIN .= "Serial Val :".$serialval.PHP_EOL;
                        file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
                    
                        $stocktypequeryserial = 'collections/'.$moduletablename.'['.$index.']/lineitems/'.$TranTblName.'['.$index2.']/stock_type';
                        $serial=$xpath->query($stocktypequeryserial,$doc);
                        foreach ($serial as $entry) {
                            $stocktype = $entry->nodeValue;
                        }
                    
                        $serial = explode("#",$serialval);
                        for ($index9 = 0; $index9 < count($serial); $index9++)
                        {
                            $serialqueryarray = "";
                            $sserial = $serial[$index9];
                            $serialqueryarray['trans_line_id'] = $line_id;
                            $serialqueryarray['stock_type'] = $stocktype;
                            $serialqueryarray['product_id'] = $qparams['productname'];
                            $serialqueryarray['serialnumber'] = $sserial;
                            
                            $serialquery = "INSERT INTO vtiger_xrpi_serialinfo (trans_line_id,stock_type,product_id,serialnumber) VALUES (?,?,?,?)";
                            $adb->mquery($serialquery,$serialqueryarray);
                            
                            $logIN = "Serial No Insert : ".$serialquery.PHP_EOL;
                            $logIN .= "Input Array : ".  print_r($serialqueryarray, TRUE).PHP_EOL;
                            file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
                        }
                   }
                   
               }
               
               //sathya $modname == 'xrSalesReturn' || 
               if($modulename == 'xrSalesReturn'){
                   //vtiger_xrsritems,vtiger_xrsr,vtiger_xrsrcf
//                   $checkDetailQry = "SELECT salesinvoiceid FROM `vtiger_xrsritems` WHERE  xrsrid=?";
//                   $checkDetailRes = $adb->pquery($checkDetailQry,array($inserted_Id));
//                   $checkDetailCnt=$adb->query_result($checkDetailRes,0,'salesinvoiceid');
                   if($refStatusUp ==1){
                       
					   $checkDetailQry = "SELECT salesinvoiceid FROM `vtiger_xrsritems` xRSR 
					   WHERE xRSR.xrsrid=? AND xRSR.salesinvoiceid=0";
					   $checkDetailRes = $adb->mquery($checkDetailQry,array($inserted_Id));
					   $noofReords = $adb->num_rows($checkDetailRes);
					   
					   $varWithRef	= ($noofReords==0) ? 'With Ref' : 'Without Ref';
					   
					   $updateRefQry = "UPDATE `vtiger_xrsrcf` SET rsales_return_type = ? WHERE `xrsrid` = ?";
                       $updateRefRes = $adb->mquery($updateRefQry,array($varWithRef,$inserted_Id));
                       
                       $logco = "------------xrSalesReturn GST With Ref Status Change ---------".PHP_EOL;
                       $logco .= "Module : ".$modulename.PHP_EOL;
                       $logco .= "Flag : ".$refStatusUp.PHP_EOL;
                       $logco .= "xrSalesReturn ID : ".$inserted_Id.PHP_EOL;
                       file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                   }
                       $logco = "------------xrSalesReturn GST Without Ref Status maintain ---------".PHP_EOL;
                       $logco .= "Module".$modulename.PHP_EOL;
                       $logco .= "Flag".$refStatusUp.PHP_EOL;
                       $logco .= "xrSalesReturn ID".$inserted_Id.PHP_EOL;
                       file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
               }
               
           //}
               if($modulename == 'xrSalesInvoice' && $LBL_AUTO_RSI_TO_SI == 'True')
                {
                    $logco = "------------Auto Convert RSI to SI---------".PHP_EOL;
                    $logco .= "RSI Module".$modulename.PHP_EOL;
                    $logco .= "RSI ID".$inserted_Id.PHP_EOL;
                    file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                    
                    convert_rvs_to_si(trim($inserted_Id), 'Yes', 1);
                }
                if($modulename == 'xrSalesOrder' && $LBL_AUTO_RSO_TO_SO == 'True')
                {
                    $logco = "------------Auto Convert RSO to SO---------".PHP_EOL;
                    $logco .= "RSO Module".$modulename.PHP_EOL;
                    $logco .= "RSO ID".$inserted_Id.PHP_EOL;
                    file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                    
                    convert_rso_to_so(trim($inserted_Id), 'Yes', 1);
                }
                
                if($modulename == 'xrMerchandiseIssue' && $LBL_AUTO_RSO_TO_SO == 'True')
                {
                    $logco = "------------Auto Convert RMI to MI ---------".PHP_EOL;
                    $logco .= "RMI Module".$modulename.PHP_EOL;
                    $logco .= "RMI ID".$inserted_Id.PHP_EOL;
                    file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                    $merchanid = array(trim($inserted_Id));
                    convert_rso_to_so($merchanid, 'Yes', 1);
                }
           }
           if ($inserted_Id != "")
               $query = "SELECT * FROM vtiger_crmentity WHERE vtiger_crmentity.crmid = '".$inserted_Id."' AND vtiger_crmentity.deleted = '0'";
               $params = array();
               $field_Result = $adb->mquery($query,$params);
               $field_count = $adb->num_rows($field_Result);
               if ($field_count > 0 )
               {
                   if ($modname == 'xrPurchaseInvoice')
                    {
                        $dist_id_query = $RPIquery.'/cf_purchaseinvoice_buyer_id';
                        $distid=$xpath->query($dist_id_query,$doc);
                        foreach ($distid as $entry)
                        {
                            $nodevalueX_distid = $entry->nodeValue;
                        }

                        $fromid = $nodevalueX_distid;
                        
                        $forum_code_query = $RPIquery.'/forum_code';
                        $forum_code=$xpath->query($forum_code_query,$doc);
                        foreach ($forum_code as $entry)
                        {
                            $nodevalueX_forum_code = $entry->nodeValue;
                        }
                        if($nodevalueX_forum_code != '')
                        {
                            $distdet = GetDistributorDetailFromForumCode($nodevalueX_forum_code);
                            if($distdet != '')
                                $fromid = $distdet['distributorcode'];
                        }
                    }
                    $statuscode = 'FN1001';
                    $statusmsg = 'Order processed Successfully';

					$varSuccess ='Successes';
					$varSuccess1 ='Successes-Update';
					
					if($modname == 'xrSalesOrder'){
						$qryXsrso = "SELECT vtiger_xrso.subject AS `subject`,vtiger_xrsocf.cf_salesorder_sales_order_date AS `cf_salesorder_sales_order_date` FROM vtiger_xrso INNER JOIN vtiger_xrsocf ON vtiger_xrsocf.salesorderid=vtiger_xrso.salesorderid WHERE vtiger_xrsocf.salesorderid= '".$inserted_Id."' AND vtiger_xrso.deleted = '0'";
						$params = array();
						$qryXsrso_Result = $adb->mquery($qryXsrso,$params);
						$resSubject = $resDate = '';
						$noRecXRSO = $adb->num_rows($qryXsrso_Result);
						if($noRecXRSO>0) {
							$resSubject= $adb->query_result($qryXsrso_Result,0,'subject');
							$resDate= $adb->query_result($qryXsrso_Result,0,'cf_salesorder_sales_order_date');
						}
						
						if(empty($resSubject) || $resSubject==''){
							$statusmsg='';
							$varSuccess ='Failed';
							$varSuccess1 ='Failed';
							$statusmsg .= $subjectVal.' - Reference Number Empty in vtiger_xrso';
							$statuscode = 'FN8213';
						}
						if(empty($resDate) || $resDate==''){
							$statusmsg .= ' / Sales Order Date is Empty';
						}						
					}
				
				  if ($mod == '')
                    sendreceiveaudit($docid, 'Receive', $varSuccess, $varSuccess, $inserted_Id, $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                   elseif($mod == 'edit')
                    sendreceiveaudit($docid, 'Receive', $varSuccess1, $varSuccess, $inserted_Id, $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                   $insertstatus = '200';
                   fwrite($fpx, $insertstatus."\n");
               }
        }
        $logIN = "-----End Of Insert----".PHP_EOL;
        file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
    }
    else
    {
        //Staging Table Data Import.
        //Get The Module name from XML & servch in the tbale sify_tr_rel for getting the staging table name.
        $Trname=$adb->mquery("SELECT transaction_rel_table,transaction_name,billaddtable,shipaddtable,profirldname,relid,uom FROM sify_tr_rel WHERE transaction_name =? AND table_type = 'st'",array($modname));
        $logtrrel = "Module Name : ".$modname.PHP_EOL;
        $logtrrel .= "Number of Records in sify_tr_rel : ".$adb->num_rows($Trname).PHP_EOL;
        file_put_contents($Resulrpatth1, $logtrrel, FILE_APPEND);
        
        $configquery = $adb->mquery("SELECT `key`,`value` FROM sify_inv_mgt_config WHERE `key` = 'MIGRATION_DATA_UPDATE_SKIP'",array());
        if($adb->num_rows($configquery) > 0){
            $MIGRATION_DATA_UPDATE_SKIP=$adb->query_result($configquery,0,'value');
        }
        
        if($adb->num_rows($Trname)>0)
        {
            $TranTblName=$adb->query_result($Trname,0,'transaction_rel_table');
            $relid=$adb->query_result($Trname,0,'relid');
            
            //Get the fields of staging table from sify_tr_grid_field.
            $Trprorel=$adb->mquery("SELECT columnname FROM sify_tr_grid_field WHERE tablename = ? AND xmlsendtable = '1'",array($TranTblName));
            
            $loggrd = "Table Name : ".$TranTblName.PHP_EOL;
            $loggrd .= "Number of Records in sify_tr_grid_field : ".$adb->num_rows($Trprorel).PHP_EOL;
            file_put_contents($Resulrpatth1, $loggrd, FILE_APPEND);
            
            if($adb->num_rows($Trprorel)>0)
            {              
                $docidpath = 'collections/'.$TranTblName.'docinfo/transactionid';
                $entries1=$xpath->query($docidpath,$doc);
                foreach ($entries1 as $entry) {
                    $docid = $entry->nodeValue;
                }
                $fromidpath = 'collections/'.$TranTblName.'docinfo/fromid';
                $entries_fromid=$xpath->query($fromidpath,$doc);
                foreach ($entries_fromid as $entry) {
                    $fromid = $entry->nodeValue;
                }
                $doccreateddatepath = 'collections/'.$TranTblName.'docinfo/createddate';
                $entries_doccreateddate=$xpath->query($doccreateddatepath,$doc);
                foreach ($entries_doccreateddate as $entry) {
                    $doccreated_date = $entry->nodeValue;
                    $doccreateddate = date("Y-m-d", strtotime($doccreated_date));
                }
                $sourceapplicationpath = 'collections/'.$TranTblName.'docinfo/sourceapplication';
                $entries_sourceapplication=$xpath->query($sourceapplicationpath,$doc);
                foreach ($entries_sourceapplication as $entry) {
                    $sourceapplication = $entry->nodeValue;
                }
                $destapplicationpath = 'collections/'.$TranTblName.'docinfo/destapplication';
                $entries_destapplication=$xpath->query($destapplicationpath,$doc);
                foreach ($entries_destapplication as $entry) {
                    $destapplication = $entry->nodeValue;
                }
                
                $xmllen = 'collections/'.$TranTblName.'s/'.$TranTblName;
                $prolenentries=$xpath->query($xmllen,$doc);
                $xmlcount = $prolenentries->length;
                
                $logLOL = "Length of XML : ".$xmlcount.PHP_EOL;
                file_put_contents($Resulrpatth1, $logLOL, FILE_APPEND);
                
                if ($xmlcount > 0)
                {
                    for ($index6 = 1; $index6 <= $xmlcount; $index6++) //for Loop for n number of product in a xml
                    {
                        $colvals = $trfields = array();
                        $colum = '';
                        $colval = $updatequryStg = '';
                        $exequyery = $salesmanid = $distributorid = $deviceuniquekey = '' ;
                        $is_updaterow = $is_updateskiprow = 0;
                        $sen_rec_status = $sen_rec_reason = 'Successes';
                         $uid = '';
                        $multselectval = array();
                        for ($index5 = 0; $index5 < $adb->num_rows($Trprorel); $index5++)  // for loop for get the field wise value from XML
                        {   
                            $tr_fields=$adb->query_result($Trprorel,$index5,0);
                            $trfields[] = $tr_fields;
                            if ($colum == "")
                            {
                                $colum = $tr_fields;
                                $colval = "?";
                                $updatequryStg = $tr_fields." = ?";
                            }   
                            else
                            {
                                $colum = $colum.",".$tr_fields;
                                $colval = $colval.",?";
                                $updatequryStg = $updatequryStg .",".$tr_fields." = ?";
                            }
                            
                            $lat = 'collections/'.$TranTblName.'s/'.$TranTblName.'['.$index6.']/'.$tr_fields;
                            $latitude=$xpath->query($lat,$doc);
                            $latVal = '';
                            foreach ($latitude as $entry) {
                                $latVal = $entry->nodeValue;
                            }
                            $colvals[] = $latVal;
                            
                            @${$tr_fields} = $latVal;  // FRPRDINXT-14729
                            if($TranTblName =='st_otp_details'){
                                if($tr_fields =='salesman_code'){
                                    $salesman_code = $latVal;
                                }elseif($tr_fields =='salesman_id'){
                                    $salesman_id = $latVal;
                                }elseif($tr_fields =='distributor_code'){
                                    $distributor_code = $latVal;
                                }elseif($tr_fields =='status'){
                                    $status = $latVal;
                                }elseif($tr_fields =='otp_hashvalue'){
                                    $otp_hashvalue = $latVal;
                                }
                            }
                            
                            
                        }
                        $tablearrayfrNT = array('vtiger_xdistdeviceregistration','st_otp_details');
						$multselectval = array();
						if($TranTblName == 'vtiger_xdistdeviceregistration'){
							if(!empty($salesmanid) && empty($distributorid)){
								$selectquery = "SELECT salesmanid FROM ".$TranTblName." WHERE `distributorid` = '' AND `salesmanid` = ? AND `deviceuniquekey` = ? ";
								$multselectval = array($salesmanid,$deviceuniquekey);
							}elseif(!empty($salesmanid) && !empty($distributorid)){
								$selectquery = "SELECT salesmanid FROM ".$TranTblName." WHERE `distributorid` = ? AND `salesmanid` = ? AND `deviceuniquekey` = ? ";
								$multselectval = array($distributorid,$salesmanid,$deviceuniquekey);
							}elseif(empty($salesmanid) && !empty($distributorid) && !empty($retailerid)){
								$selectquery = "SELECT salesmanid FROM ".$TranTblName." WHERE `distributorid` = '".$distributorid."' AND `retailerid` = '".$retailerid."' AND `deviceuniquekey` = '".$deviceuniquekey."'";
								$multselectval = array($distributorid,$retailerid,$deviceuniquekey);
							}
							
						}elseif($TranTblName != 'vtiger_xdistdeviceregistration'){
							$multselectval[] = $uid;
							$selectquery = "SELECT uid FROM ".$TranTblName." WHERE uid = ?";
						}
						if(!empty($selectquery)){
							$selectqueryexe = $adb->mquery($selectquery,$multselectval);
							$selectquerynum = $adb->num_rows($selectqueryexe);
						}
						if(!empty($selectquery)){
							$logco .= "Select SQL : ".$selectquery.PHP_EOL;
							$logco .= "Count : ".$selectquerynum.PHP_EOL;
							$logco .= "Is Update Status : ".$is_updaterow.PHP_EOL;
							$logco .= "Config Val : ".$MIGRATION_DATA_UPDATE_SKIP.PHP_EOL;
							file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
						}
						if(!empty($selectquerynum))
						{
							$is_updaterow = 1;
							if($MIGRATION_DATA_UPDATE_SKIP && $TranTblName != 'vtiger_xdistdeviceregistration'){                                   
							   $is_updateskiprow = 1;
							   $sen_rec_status = 'Failed';
							   $sen_rec_reason = $latVal." Already Available In Application (".$lat.")";
							   break;
							}
						}
						
                        if($is_updaterow == 1 && $TranTblName != "st_otp_details")
                        {
                            $updatequryStg = trim($updatequryStg,',');
                            if(!empty($updatequryStg) && $TranTblName != 'vtiger_xdistdeviceregistration' && !empty($is_updateskiprow)){
                               $updatequryStg .= " WHERE uid = '".$uid."'";
                            }elseif(!empty($updatequryStg) && $TranTblName == 'vtiger_xdistdeviceregistration' && empty($retailerid)){
                                $updatequryStg .= " WHERE salesmanid = '$salesmanid' && distributorid = '$distributorid' && deviceuniquekey = '$deviceuniquekey'";
                            }elseif(!empty($updatequryStg) && $TranTblName == 'vtiger_xdistdeviceregistration' && !empty($retailerid)){
                                $updatequryStg .= " WHERE retailerid = '$retailerid' && distributorid = '$distributorid' && deviceuniquekey = '$deviceuniquekey'";
                            }
                            if(!empty($updatequryStg) ){
                                $exequery ="UPDATE ".$TranTblName." SET ".$updatequryStg; 
                                $sen_rec_status .= '- upddated';
                                
                                if ($TranTblName == 'st_sff_visit_session')
                                    $exequery = '';
                            }
                            $logco = "Is Update Field : ".$updatequryStg.PHP_EOL;
                            file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                        }elseif(!in_array($TranTblName,$tablearrayfrNT) && empty($is_updaterow)){
                            $exequery ="INSERT INTO ".$TranTblName."(".$colum.") VALUES (".$colval.")";
                        }elseif($TranTblName == "st_otp_details"){
                            $logIN = "- Im in  st_otp_details file -".PHP_EOL;
                            // for refer saleman id /code status,
							global $logfilepath;
							$logfilepath= $Resulrpatth1;
                            $insertstatus = sendOTPtoSalesman($salesman_code, $status, $otp_hashvalue,$mobile_number,$customer_code);
							$logIN .= "insertstatus:".$insertstatus.PHP_EOL;
                            file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
                        }else{
                            $exequery = '';
                            $sen_rec_status = 'Failed';
                            $sen_rec_reason = $latVal." Not Available In Application (".$lat.")";
                        }
                        $logco = "query : ".$exequery.PHP_EOL;
                        $logco .= "Value : ".print_r($colvals,true).PHP_EOL;
                        file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                        if(!empty($exequery)){
                           
                                $exequyery = $adb->mquery($exequery,$colvals);
                        }
                        if(empty($is_updaterow))
                        {
                            $count = $adb->mquery("SELECT MAX(".$relid.") FROM ".$TranTblName,array());
                            $counts = $adb->query_result($count,0,0);
                        }else{
                            $counts = 0;
                        }
                        sendreceiveaudit($docid, 'Receive', $sen_rec_status, $sen_rec_reason, $counts, $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
						if($TranTblName != "st_otp_details"){
							$insertstatus = '200';
						}
                        fwrite($fpx, $insertstatus."\n");
                        
                        $logIN = "-----Insert Successfully----".PHP_EOL;
                        file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
                        //Transaction Line Item Get & insert
                        $RelTrname=$adb->mquery("SELECT transaction_rel_table,transaction_name,billaddtable,shipaddtable,profirldname,relid,uom FROM sify_tr_rel WHERE transaction_name =? AND table_type = 'str'",array($modname));
                        $logreltb = "Number of Records in sify_tr_rel For Related Table : ".$adb->num_rows($RelTrname).PHP_EOL;
                        file_put_contents($Resulrpatth1, $logreltb, FILE_APPEND);
                        
                        if($adb->num_rows($RelTrname)>0)
                        {
                            $rTranTblName=$adb->query_result($RelTrname,0,'transaction_rel_table');
                            $relid=$adb->query_result($RelTrname,0,'relid');

                            //Get the fields of staging table from sify_tr_grid_field.
                            $Trprorelline=$adb->mquery("SELECT columnname FROM sify_tr_grid_field WHERE tablename = ? AND xmlsendtable = '1'",array($rTranTblName));

                            $loggrd = "Number of Records in sify_tr_grid_field For Line Item : ".$adb->num_rows($Trprorelline).PHP_EOL;
                            file_put_contents($Resulrpatth1, $loggrd, FILE_APPEND);

                            if($adb->num_rows($Trprorelline)>0)
                            {
                                $xmllen = 'collections/'.$modname.'s/'.$modname.'['.$index6.']/lineitems/'.$rTranTblName;
                                $prorellenentries=$xpath->query($xmllen,$doc);
                                $xmlrelcount = $prorellenentries->length;

                                $logLOL = "Length of Line Item XML : ".$xmlrelcount.PHP_EOL;
                                file_put_contents($Resulrpatth1, $logLOL, FILE_APPEND);

                                if ($xmlrelcount > 0)
                                {
                                    $trlfields = array();
                                    $lcolum = '';
                                    $lcolval = '';
                                    $colvalsl = array();
                                    for ($index7 = 0; $index7 < $adb->num_rows($Trprorelline); $index7++)
                                    {
                                        $trl_fields=$adb->query_result($Trprorelline,$index7,0);
                                        $trlfields[] = $trl_fields;
                                        if ($lcolum == "")
                                        {
                                            $lcolum = $trl_fields;
                                            $lcolval = "?";
                                        }   
                                        else
                                        {
                                            $lcolum = $lcolum.",".$trl_fields;
                                            $lcolval = $lcolval.",?";
                                        }

                                        $latl = 'collections/'.$modname.'s/'.$modname.'['.$index6.']/lineitems/'.$rTranTblName.'/'.$trl_fields;
                                        $latitudel=$xpath->query($latl,$doc);
                                        foreach ($latitudel as $entry) {
                                            $latVall = $entry->nodeValue;
                                        }
                                        $colvalsl[$trl_fields] = $latVall;
                                    }
                                                                        
                                    //Serial Number
                                    $serno = 'collections/'.$modname.'s/'.$modname.'['.$index6.']/lineitems/'.$rTranTblName.'/serial_number';
                                    $serialnumber=$xpath->query($serno,$doc);
                                    foreach ($serialnumber as $entry) {
                                        $serial_number = $entry->nodeValue;
                                    }
                                    $log_serial = "Serial Number :".$serial_number.PHP_EOL;
                                    file_put_contents($Resulrpatth1, $log_serial, FILE_APPEND);
                                    $serial = split('#', $serial_number);
                                    
                                    $serialno = '';
                                    for ($index8 = 0; $index8 < count($serial); $index8++)
                                    {
                                        $serialno = $serial[$index8];
                                        $querys = "INSERT INTO st_xpurchaseinvoiceserials (invoice_no,product_code,line_no,serial_no) VALUES (?,?,?,?)";
                                        $sernoval = array($colvalsl[invoice_no],$colvalsl[product_code],$index8+1,$serialno);
                                        $logse = "query : ".$querys.PHP_EOL;
                                        $logse .= "Values :".print_r($sernoval,true).PHP_EOL;
                                        $logse .= "-----Serial Number Insert Successfully----".PHP_EOL;
                                        file_put_contents($Resulrpatth1, $logse, FILE_APPEND);
                                        $adb->mquery($querys,$sernoval);
                                        
                                    }
                                    
                                    $query ="INSERT INTO ".$rTranTblName."(".$lcolum.") VALUES (".$lcolval.")";
                                    $logco = "query : ".$query.PHP_EOL;
                                    $logco .= "Values :".print_r($colvalsl,true).PHP_EOL;
                                    $logco .= "-----Line Item Insert Successfully----".PHP_EOL;
                                    file_put_contents($Resulrpatth1, $logco, FILE_APPEND);
                                    $adb->mquery($query,$colvalsl);
                                }
                                ELSE
                                {
                                    sendreceiveaudit($docid, 'Receive', 'Failed', 'XML Line Item Count is 0', $counts, $fromid,$sourceapplication,$doccreateddate,$modname,$xmllen,$destapplication,$subjectVal,$statuscode,$statusmsg);
                                    $insertstatus = '101';
                                    fwrite($fpx, $insertstatus."\n");

                                    $logIN = "XML Line Item Count is 0".PHP_EOL;
                                    file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
                                }

                            }
                        }
                    }
                }
                ELSE
                {
                    sendreceiveaudit($docid, 'Receive', 'Failed', 'XML Count is 0', $counts, $fromid,$sourceapplication,$doccreateddate,$modname,$xmllen,$destapplication,$subjectVal,$statuscode,$statusmsg);
                    $insertstatus = '101';
                    fwrite($fpx, $insertstatus."\n");

                    $logIN = "XML Count is 0".PHP_EOL;
                    file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
                }
            }
              
            $logIN = "-----End Of Insert----".PHP_EOL;
            file_put_contents($Resulrpatth1, $logIN, FILE_APPEND);
        }
        ELSEIF($modname == 'xRetailerUpdate')
        {
            //Retailer & Claim Update
            if($parent==''){
                $xmllen = 'collections/vtiger_xretailer';
                 $xmlentries=$xpath->query($xmllen,$doc);
                 $retailerxmlLength = $xmlentries->length;
            }

            $logFV = "----Retailer Update----".PHP_EOL;
            $logFV .= "Retailer Update Count : ".$retailerxmlLength.PHP_EOL;
            file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);

            if ($retailerxmlLength != 0)
            {
                for ($index10 = 1; $index10 <= $retailerxmlLength; $index10++)
                {
                    $logFV = "Index : ".$index10.PHP_EOL;
                    file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);

                    $reta = 'collections/vtiger_xretailer['.$index10.']'.'/retailer';
                    $retailer = $xpath->query($reta,$doc);
                    foreach ($retailer as $entry) {
                        $retailer_code = $entry->nodeValue;
                    }

                    $logFV = "retailer_code : ".$retailer_code.PHP_EOL;
                    file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);

                    if ($retailer_code == 'Retailer')
                    {
                        $logFV = "Retailer Master Update".PHP_EOL;
                        file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);

                        $lat = 'collections/vtiger_xretailer['.$index10.']'.'/latitude';
                        $latitude=$xpath->query($lat,$doc);
                        foreach ($latitude as $entry) {
                            $latVal = $entry->nodeValue;
                        }

                        $lon = 'collections/vtiger_xretailer['.$index10.']'.'/longitude';
                        $longitude=$xpath->query($lon,$doc);
                        foreach ($longitude as $entry) {
                            $longVal = $entry->nodeValue;
                        }

                        $ret = 'collections/vtiger_xretailer['.$index10.']'.'/customercode';
                        $customercode=$xpath->query($ret,$doc);
                        foreach ($customercode as $entry) {
                            $customer_code = $entry->nodeValue;
                        }

                        $dis = 'collections/vtiger_xretailer['.$index10.']'.'/distributorcode';
                        $distributorcode=$xpath->query($dis,$doc);
                        foreach ($distributorcode as $entry) {
                            $distributor_code = $entry->nodeValue;
                        }

                        $logFV = "Retailer Master Update Valie".PHP_EOL;
                        $logFV .= "latitude : ".$latVal.PHP_EOL;
                        $logFV .= "longitude : ".$longVal.PHP_EOL;
                        $logFV .= "customercode : ".$customer_code.PHP_EOL;
                        $logFV .= "distributorcode : ".$distributor_code.PHP_EOL;
                        file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);

                        updateRetInfo($latVal,$longVal,$customer_code,$distributor_code);    

                        sendreceiveaudit($docid, 'Receive', 'Successes-Update', 'Successes', $customer_code, $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                        $insertstatus = '200';
                        fwrite($fpx, $insertstatus."\n");
                    }
                }
            }
        }
        ELSEIF($modname == 'xClaimTopSheetUpdate')
        {
            if($parent==''){
                $xmllen = 'collections/vtiger_xclaimtopsheet';
                 $xmlentries=$xpath->query($xmllen,$doc);
                 $claimxmlLength = $xmlentries->length;
            }

            $logFV = "----Claim Top Sheet Update----".PHP_EOL;
            $logFV .= "Claim Update Count : ".$claimxmlLength.PHP_EOL;
            file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);

            if ($claimxmlLength != 0)
            {
                for ($index11 = 1; $index11 <= $claimxmlLength; $index11++)
                {
                    $logFV = "Index : ".$index11.PHP_EOL;
                    file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);

                    $reta = 'collections/vtiger_xclaimtopsheet['.$index11.']'.'/claim';
                    $retailer = $xpath->query($reta,$doc);
                    foreach ($retailer as $entry) {
                        $claim = $entry->nodeValue;
                    }

                    $logFV = "Claim : ".$claim.PHP_EOL;
                    file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);

                    if ($claim == 'ClaimUpdate')
                    {
                        $logFV = "Update Claim Status".PHP_EOL;
                        file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);

                        $lat = 'collections/vtiger_xclaimtopsheet['.$index11.']'.'/xdistributorid';
                        $distid=$xpath->query($lat,$doc);
                        foreach ($distid as $entry) {
                            $distributor_code = $entry->nodeValue;
                        }
    
                        $claimupdatevalarray = '';
                        $lat = 'collections/vtiger_xclaimtopsheet['.$index11.']'.'/claim_topsheet_reference_no';
                        $refno=$xpath->query($lat,$doc);
                        foreach ($refno as $entry) {
                            $reference_no = $entry->nodeValue;
                        }
                        $lat = 'collections/vtiger_xclaimtopsheet['.$index11.']'.'/claim_head_code';
                        $headcode=$xpath->query($lat,$doc);
                        foreach ($headcode as $entry) {
                            $claim_head_code = $entry->nodeValue;
                        }
                        $lat = 'collections/vtiger_xclaimtopsheet['.$index11.']'.'/note_ref';
                        $noteref=$xpath->query($lat,$doc);
                        foreach ($noteref as $entry) {
                            $note_ref = $entry->nodeValue;
                        }
        
                        $logFV = "Distributor : ". $distributor_code .PHP_EOL;
                        $logFV .= "reference_no : ". $reference_no .PHP_EOL;
                        $logFV .= "claim_head_code : ". $claim_head_code .PHP_EOL;
                        $logFV .= "note_ref : ". $note_ref .PHP_EOL;
                        file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);
                        update_claim_update($distributor_code,$reference_no,$claim_head_code,$note_ref);
    
                        sendreceiveaudit($docid, 'Receive', 'Successes-Update', 'Successes', $claim_head_code, $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                        $insertstatus = '200';
                        fwrite($fpx, $insertstatus."\n");
                    }
                }
            }
            
        }
        ELSEIF($modname == 'POUpdate')
        {
            //PurchaseOrder & Claim Update
            if($parent==''){
                $xmllen = 'collections/vtiger_purchaseordercf';
                 $xmlentries=$xpath->query($xmllen,$doc);
                 $retailerxmlLength = $xmlentries->length;
            }

            $logFV = "----PurchaseOrder Update----".PHP_EOL;
            $logFV .= "PurchaseOrder Update Count : ".$retailerxmlLength.PHP_EOL;
            file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);

            if ($retailerxmlLength != 0)
            {
                for ($index12 = 1; $index12 <= $retailerxmlLength; $index12++)
                {
                    $purchaseorder_code = $purchaseorder_noval = $distributor_code = $transaction_numberVal = $po_statusval = $remak = '';
                    $logFV = "Index : ".$index12.PHP_EOL;
                    file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);

                    $retaxml = 'collections/vtiger_purchaseordercf['.$index12.']'.'/purchaseorder';
                    $purchaseorder = $xpath->query($retaxml,$doc);
                    foreach ($purchaseorder as $entry) {
                        $purchaseorder_code = $entry->nodeValue;
                    }

                    $logFV = "purchaseorder_code : ".$purchaseorder_code.PHP_EOL;
                    file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);

                    if ($purchaseorder_code == 'PurchaseOrder')
                    {
                        $logFV = "PurchaseOrder Master Update".PHP_EOL;
                        file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);

                        $pono = 'collections/vtiger_purchaseordercf['.$index12.']'.'/purchaseorder_no';
                        $ponoRSt = $xpath->query($pono,$doc);
                        foreach ($ponoRSt as $entry) {
                            $purchaseorder_noval = $entry->nodeValue;
                        }

                        $transaction_numberQY = 'collections/vtiger_purchaseordercf['.$index12.']'.'/cf_purchaseorder_transaction_number';
                        $transaction_numberEX =$xpath->query($transaction_numberQY,$doc);
                        foreach ($transaction_numberEX as $entry) {
                            $transaction_numberVal = $entry->nodeValue;
                        }

                        $posqy = 'collections/vtiger_purchaseordercf['.$index12.']'.'/po_status';
                        $posEX=$xpath->query($posqy,$doc);
                        foreach ($posEX as $entry) {
                            $po_statusval = $entry->nodeValue;
                        }

                        $dis = 'collections/vtiger_purchaseordercf['.$index12.']'.'/distributorcode';
                        $distributorcode=$xpath->query($dis,$doc);
                        foreach ($distributorcode as $entry) {
                            $distributor_code = $entry->nodeValue;
                        }
                        $remakpath = 'collections/vtiger_purchaseordercf['.$index12.']'.'/po_remak';
                        $remakxml=$xpath->query($remakpath,$doc);
                        foreach ($remakxml as $entry) {
                            $remak = $entry->nodeValue;
                        }

                        $logFV = "Retailer Master Update Valie".PHP_EOL;
                        $logFV .= "transaction_number : ".$transaction_numberVal.PHP_EOL;
                        $logFV .= "purchaseorder_no : ".$purchaseorder_noval.PHP_EOL;
                        $logFV .= "po_status : ".$po_statusval.PHP_EOL;
                        $logFV .= "po_remak : ".$remak.PHP_EOL;
                        $logFV .= "distributorcode : ".$distributor_code.PHP_EOL;
                        file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);
                        $docid = $purchaseorder_noval;
                        if(empty($docid)){
                                $docid = $transaction_numberVal;
                        }
                        $reasonforfail = 'Failed';
                        if(!empty($distributor_code) && !empty($transaction_numberVal) && !empty($purchaseorder_noval)){
                                $upsatus = updatePoSattusInfo($distributor_code,$transaction_numberVal,$purchaseorder_noval,$po_statusval,$remak);
                        }else{
                                $reasonforfail = 'input value wrong';
                        }

                        if(!empty($upsatus)){
                                $logFV = "purchaseorderid : ".$upsatus.PHP_EOL;
                                file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);
                                sendreceiveaudit($docid, 'Receive', 'Successes-Update', 'Successes', $transaction_numberVal, $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                                $insertstatus = '200';
                        }else{
                                $logFV = "purchaseorderid : ".$upsatus.PHP_EOL;
                                file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);
                                sendreceiveaudit($docid, 'Receive', 'Update-Failer', $reasonforfail, $transaction_numberVal, $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
                                $insertstatus = '101';
                                fwrite($fpx, $insertstatus."\n");
                                break;
                        }

                        fwrite($fpx, $insertstatus."\n");
                    }
                }
            }
        }
		ELSEIF($modname == 'xUserValidate')
        {
            //Retailer & Claim Update
            if($parent==''){
                $xmllen = 'collections/vtiger_users';
                 $xmlentries=$xpath->query($xmllen,$doc);
                 $userxmlLength = $xmlentries->length;
            }

            $logFV = "----Vsm User login verification ----".PHP_EOL;
            $logFV .= "User login Count : ".$userxmlLength.PHP_EOL;
            file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);

            if ($userxmlLength != 0)
            {
                for ($index18 = 1; $index18 <= $userxmlLength; $index18++)
                {
                    $logFV = "Index : ".$index18.PHP_EOL;
                    file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);

                    $reta = 'collections/vtiger_users['.$index18.']'.'/validate';
                    $retailer = $xpath->query($reta,$doc);
                    foreach ($retailer as $entry) {
                        $validate_code = $entry->nodeValue;
                    }

                    $logFV = "validate_code : ".$validate_code.PHP_EOL;
                    file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);

                    if ($validate_code == 'UserValidate')
                    {
                        $logFV = "UserValidate Master Update".PHP_EOL;
                        file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);

                        $lat = 'collections/vtiger_users['.$index18.']'.'/user_name';
                        $latitude=$xpath->query($lat,$doc);
                        foreach ($latitude as $entry) {
                            $user_name = $entry->nodeValue;
                        }

                        $lon = 'collections/vtiger_users['.$index18.']'.'/user_password';
                        $longitude=$xpath->query($lon,$doc);
                        foreach ($longitude as $entry) {
                            $user_password = $entry->nodeValue;
                        }

                        $ret = 'collections/vtiger_users['.$index18.']'.'/salesmancode';
                        $customercode=$xpath->query($ret,$doc);
                        foreach ($customercode as $entry) {
                            $salesmancode = $entry->nodeValue;
                        }

                        $dis = 'collections/vtiger_users['.$index18.']'.'/distributorcode';
                        $distributorcode=$xpath->query($dis,$doc);
                        foreach ($distributorcode as $entry) {
                            $distributor_code = $entry->nodeValue;
                        }

                        $logFV = "Vsm User login verification details ".PHP_EOL;
                        $logFV .= "user_name : ".$user_name.PHP_EOL;
                        $logFV .= "user_password : ".$user_password.PHP_EOL;
                        $logFV .= "salesmancode : ".$salesmancode.PHP_EOL;
                        $logFV .= "distributorcode : ".$distributor_code.PHP_EOL;
                        file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);
						$reasonforfail = 'Failed';
						if(!empty($distributor_code) && !empty($user_name) && !empty($user_password)){
							$upsatus = validateUserLogin($distributor_code,$user_name,$user_password,$salesmancode);  
						}else{
							$reasonforfail = 'input value wrong';
						}
                        
						if(!empty($upsatus)){
							$logFV = "User Login Status: ".$upsatus.PHP_EOL;
							file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);
							sendreceiveaudit($docid, 'Receive', 'Validate', 'Successes', $transaction_numberVal, $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
							$insertstatus = '200';
						}else{
							$logFV = "User Login Status: ".$upsatus.PHP_EOL;
							file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);
							sendreceiveaudit($docid, 'Receive', 'Validate-Failer', $reasonforfail, $transaction_numberVal, $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
							$insertstatus = '101';
							fwrite($fpx, $insertstatus."\n");
							break;
						}
                        
                        fwrite($fpx, $insertstatus."\n");
                    }
                }
            }
        }
		ELSEIF($modname == 'xPriceBootstrap')
        {
            //Retailer & Claim Update
            if($parent==''){
                $xmllen = 'collections/vtiger_xpricebootstrap';
                 $xmlentries=$xpath->query($xmllen,$doc);
                 $userxmlLength = $xmlentries->length;
            }

            $logFV = "----Vsm User login verification ----".PHP_EOL;
            $logFV .= "User login Count : ".$userxmlLength.PHP_EOL;
            file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);

            if ($userxmlLength != 0)
            {
				$inboster =array();
                for ($index19 = 1; $index19 <= $userxmlLength; $index19++)
                {
                    $logFV = "Index : ".$index19.PHP_EOL;
                    file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);

                    $reta = 'collections/vtiger_xpricebootstrap['.$index19.']'.'/appname';
                    $retailer = $xpath->query($reta,$doc);
                    foreach ($retailer as $entry) {
                        $validate_code = $entry->nodeValue;
                    }

                    $logFV = "validate_code : ".$validate_code.PHP_EOL;
                    file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);

                    if ($validate_code == 'companyApp')
                    {
                        $logFV = "companyApp Booster Update".PHP_EOL;
                        file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);

                        $lat = 'collections/vtiger_xpricebootstrap['.$index19.']'.'/distids';
                        $latitude=$xpath->query($lat,$doc);
                        foreach ($latitude as $entry) {
                            $distids = $entry->nodeValue;
                        }
						$inboster['distids'] = @$distids;
                        $lon = 'collections/vtiger_xpricebootstrap['.$index19.']'.'/slmid';
                        $longitude=$xpath->query($lon,$doc);
                        foreach ($longitude as $entry) {
                            $slmid = $entry->nodeValue;
                        }
						$inboster['slmid'] = @$slmid;
                        $ret = 'collections/vtiger_xpricebootstrap['.$index19.']'.'/cslmid';
                        $customercode=$xpath->query($ret,$doc);
                        foreach ($customercode as $entry) {
                            $cslmid = $entry->nodeValue;
                        }
						$inboster['cslmid'] = @$cslmid;


                        $logFV = "booster details ".PHP_EOL;
						$logFV .= "Values :".print_r($inboster,true).PHP_EOL;
                        file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);
						$reasonforfail = 'Failed';
						if(!empty($inboster)){
							$upsatus = pricebootstrap($inboster);  
						}else{
							$reasonforfail = 'input value wrong';
						}
						$insertstatus = '200';
                        fwrite($fpx, $insertstatus."\n");
                    }
                }
            }
        }
        ELSE
        {
            $FailReason = $modname." Is Not Availabale";
            $statuscode = 'FN8213';
            $statusmsg = 'Invalid '.$modname;
            sendreceiveaudit($docid, 'Receive', 'Failed', $FailReason, '', $fromid,$sourceapplication,$doccreateddate,$modname,'',$destapplication,$subjectVal,$statuscode,$statusmsg);
            $insertstatus = '101';
            fwrite($fpx, $insertstatus."\n");

            $logs = $modname." Module Not Available".PHP_EOL;
            $logs .= "--------------- End ---------------".PHP_EOL;
            file_put_contents($Resulrpatth1, $logs, FILE_APPEND);
        }
    }
                    
    $log = "END TIME : ".$_SERVER['REMOTE_ADDR'].' - '.date("Ymd_H_i_s_").microtime(true).PHP_EOL;
    file_put_contents($Resulrpatth1, $log, FILE_APPEND);
    try{
        fclose($fpx);
    } catch (Exception $ex) {
        // do nothing
    }
    
    return $focus;
}

function getExistingObjectValue($EOVmodule, $query, $doce, $xpath, $EOVparent, $prkey, $distcode, $mand, $columnname,$tempfilename='',$depot='',$vendor='',$pidate='',$sellerid='')
{
    global $adb,$root_directory,$SENDRECEIVELOG,$LBL_RPI_VENDOR_VALID;
    $Resulrpatth2='';
    if($SENDRECEIVELOG == 'True' && 1==1)
    {
        $Resulrpatth2=$tempfilename;
    }   
    
        //$Resulrpatth2 = $root_directory.'storage/log/rlog/log_XMLReceive_'.date("jFY").'.txt';
    $log = "--- Start Related Module Value ---".PHP_EOL;
    $log .= "module : ".$EOVmodule.PHP_EOL;
    $log .= "XML Path : ".$query.PHP_EOL;
    $log .= "prkey : ".$prkey.PHP_EOL;
    $log .= "distcode : ".$distcode.PHP_EOL;
    $log .= "Mandi : ".$mand.PHP_EOL;
    $log .= "columnname : ".$columnname.PHP_EOL;
    file_put_contents($Resulrpatth2, $log, FILE_APPEND);
    
    $tabRes=$adb->mquery("SELECT tabid,tablename,entityidfield FROM vtiger_entityname where modulename=?",array($EOVmodule));
    
    $moduletablename=$adb->query_result($tabRes,0,'tablename');
    $entityidfield=$adb->query_result($tabRes,0,'entityidfield');
    
    $logRMT = "Related Module Tablename : ".$moduletablename.PHP_EOL;
    $logRMT .= "Related Module Entityidfield : ".$entityidfield.PHP_EOL;
    file_put_contents($Resulrpatth2, $logRMT, FILE_APPEND);
    
    if ($mand == '')
    {
        $fieldQuery = "SELECT fieldid,columnname,uitype,vtiger_tab.`name` FROM vtiger_field 
                        INNER JOIN vtiger_tab on vtiger_tab.tabid=vtiger_field.tabid AND vtiger_field.xmlreceivetable = '1'
                        WHERE tablename in ('".$moduletablename."','".$moduletablename."cf') AND FIND_IN_SET(columnname, '".trim($prkey)."')";
    }
    elseif ($mand == 'MU')
    {
        $fieldQuery = "SELECT fieldid,columnname,uitype,vtiger_tab.`name` FROM vtiger_field 
                        INNER JOIN vtiger_tab on vtiger_tab.tabid=vtiger_field.tabid AND vtiger_field.xmlreceivetable = '1'
                        WHERE tablename in ('".$moduletablename."','".$moduletablename."cf') AND columnname = '".$columnname."'";
    }
    
    $log = "FieldQuery : ".$fieldQuery.PHP_EOL;
    file_put_contents($Resulrpatth2, $log, FILE_APPEND);
    
    $params = array();
    $fieldResult = $adb->mquery($fieldQuery,$params);
    $fieldcount = $adb->num_rows($fieldResult);
    
    $log = "Count : ".$fieldcount.PHP_EOL;
    file_put_contents($Resulrpatth2, $log, FILE_APPEND);
    
    if($fieldcount>0){
            
            $excolumnname=$adb->query_result($fieldResult,0,'columnname');
                $logcol = "columnname : ".$excolumnname.PHP_EOL;
                file_put_contents($Resulrpatth2, $logcol, FILE_APPEND);
                
            if ($EOVparent != '')
                $query1 = $query.'/'.$excolumnname;
            else
                $query1 = $query;
                    
            $logpat = "XML Path : ".$query1.PHP_EOL;
            file_put_contents($Resulrpatth2, $logpat, FILE_APPEND);
    
            $entries = $xpath->query($query1,$doce);
            //echo "<br>".$query1."<br>";
            foreach ($entries as $entry) {
                $nodevalue = $entry->nodeValue;
            }
            
            $logval = "NodeValue : ".$nodevalue.PHP_EOL;
            file_put_contents($Resulrpatth2, $logval, FILE_APPEND);
            
            $distfieldname_tblname = "SELECT distfieldname_tblname FROM sify_tr_rel WHERE transaction_name = ?";
            $params = array($EOVmodule);
            $distfieldname_tblname_Res = $adb->mquery($distfieldname_tblname,$params);
            $distfieldname_tblname_val = $adb->query_result($distfieldname_tblname_Res,0,0);
            
            $distfieldname_tblname_value = explode("#",$distfieldname_tblname_val);
            $distfieldname = $distfieldname_tblname_value[0];
            $distfieldname_tabname = $distfieldname_tblname_value[1];
            
            $logval = "distfieldname_tblname : ".$distfieldname_tblname." - ".$EOVmodule.PHP_EOL;
            $logval .= "Distributor Field Name for the master : ".$distfieldname_tblname_val.PHP_EOL;
            $logval .= "Distributor Field Name : ".$distfieldname.PHP_EOL;
            $logval .= "Distributor Table Name : ".$distfieldname_tabname.PHP_EOL;
            $logval .= "EOVmodule : ".$EOVmodule.PHP_EOL;
            file_put_contents($Resulrpatth2, $logval, FILE_APPEND);
            
            if($distcode !="")
            {
                $distid = "SELECT xdistributorid FROM vtiger_xdistributor
                    INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xdistributor.xdistributorid
                    WHERE vtiger_crmentity.deleted = 0 AND vtiger_xdistributor.distributorcode = ?";
                $params = array($distcode);
                $distid_Res = $adb->mquery($distid,$params);
                $dist_id = $adb->query_result($distid_Res,0,0);
                
                $logval = "Distributor ID : ".$dist_id.PHP_EOL;
                file_put_contents($Resulrpatth2, $logval, FILE_APPEND);
            }
            if(empty($dist_id))
            {
                $dist_code_query = $query.'/dist_code';
                
                $logpat = "Distributor Code XML Path : ".$dist_code_query.PHP_EOL;
                file_put_contents($Resulrpatth2, $logpat, FILE_APPEND);

                $entries = $xpath->query($dist_code_query,$doce);
                //echo "<br>".$query1."<br>";
                foreach ($entries as $entry) {
                    $nodevalue = $entry->nodeValue;
                }

                $logval = "distcode : ".$nodevalue.PHP_EOL;
                file_put_contents($Resulrpatth2, $logval, FILE_APPEND);
                $distcode = $nodevalue;
                
                $distid = "SELECT xdistributorid FROM vtiger_xdistributor
                    INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xdistributor.xdistributorid
                    WHERE vtiger_crmentity.deleted = 0 AND vtiger_xdistributor.distributorcode = ?";
                $params = array($distcode);
                $distid_Res = $adb->mquery($distid,$params);
                $dist_id = $adb->query_result($distid_Res,0,0);
                
                $logval = "Distributor ID : ".$dist_id.PHP_EOL;
                file_put_contents($Resulrpatth2, $logval, FILE_APPEND);
            }
            
            $ExisObjQuery = "SELECT ".$moduletablename.".".$entityidfield." FROM ".$moduletablename."" ;
            $ExisObjQuery .= " INNER JOIN vtiger_crmentity on vtiger_crmentity.crmid= ".$moduletablename.".".$entityidfield." and vtiger_crmentity.deleted=0";
            if($distfieldname_tabname != "")
                $ExisObjQuery .= " INNER JOIN ".$distfieldname_tabname." ON ".$distfieldname_tabname.".".$entityidfield." = ".$moduletablename.".".$entityidfield ;
			
			if($LBL_RPI_VENDOR_VALID =='True' && $EOVmodule =='xrPurchaseInvoice')
				$ExisObjQuery .= " INNER JOIN vtiger_xrpicf ON vtiger_xrpicf.xrpiid=vtiger_xrpi.xrpiid " ;
                
             $ExisObjQuery .= " WHERE ".$moduletablename.".".$excolumnname." = '".$nodevalue."'";
            
            if ($distfieldname_tblname_val != "" && $dist_id != "")
            {
                IF($distfieldname_tabname != "" && $EOVmodule != "xrSalesInvoice")
                    $ExisObjQuery .= " AND ".$distfieldname_tabname.".".$distfieldname."= '".$dist_id."'";
                ELSEIF($EOVmodule == "xrSalesInvoice")
                    $ExisObjQuery .= " AND ".$distfieldname_tabname.".".$distfieldname."= '".$distcode."'";
                ELSE
                    $ExisObjQuery .= " AND ".$moduletablename.".".$distfieldname."= '".$dist_id."'";
            }
			
			if($LBL_RPI_VENDOR_VALID =='True' && $EOVmodule =='xrPurchaseInvoice'){ 
				$pidate = date("Y-m-d",strtotime($pidate));
				$ExisObjQuery .= " AND vtiger_xrpi.vendorid ='".$vendor."' AND vtiger_xrpicf.cf_purchaseinvoice_depot='".$depot."' AND vtiger_xrpicf.cf_purchaseinvoice_buyer_id='".$sellerid."' AND vtiger_xrpicf.cf_purchaseinvoice_purchase_invoice_date='".$pidate."' ";
            }
            $log = "Value Query : ".$ExisObjQuery.PHP_EOL;
            file_put_contents($Resulrpatth2, $log, FILE_APPEND);
            
            $params = array();
            $ExisObjResult = $adb->mquery($ExisObjQuery,$params);
            $ExisObjId=$adb->query_result($ExisObjResult,0,0);
            
            $logRMI = "Related Module Id : ".$ExisObjId.PHP_EOL;
            //$logRMI .= "----End Of Related Module----".PHP_EOL;
            file_put_contents($Resulrpatth2, $logRMI, FILE_APPEND);
    }
    $logRMI = "----End Of Related Module----".PHP_EOL;
    file_put_contents($Resulrpatth2, $logRMI, FILE_APPEND);
    return $ExisObjId;
}

function getExistingObjectValue_New($EOVmodule, $query, $doce, $xpath, $EOVparent, $prkey, $distcode, $mand, $columnname,$tempfilename='')
{
    global $adb,$root_directory,$SENDRECEIVELOG;
    $Resulrpatth2='';
    if($SENDRECEIVELOG == 'True' && 1==1)
    {
        $Resulrpatth2=$tempfilename;
    }   
    
        //$Resulrpatth2 = $root_directory.'storage/log/rlog/log_XMLReceive_'.date("jFY").'.txt';
    $log = "--- Start Related Module Value ---".PHP_EOL;
    $log .= "module : ".$EOVmodule.PHP_EOL;
    $log .= "XML Path : ".$query.PHP_EOL;
    $log .= "prkey : ".$prkey.PHP_EOL;
    $log .= "distcode : ".$distcode.PHP_EOL;
    $log .= "Mandi : ".$mand.PHP_EOL;
    $log .= "columnname : ".$columnname.PHP_EOL;
    file_put_contents($Resulrpatth2, $log, FILE_APPEND);
    
    $tabRes=$adb->mquery("SELECT tabid,tablename,entityidfield FROM vtiger_entityname where modulename=?",array($EOVmodule));
    
    
    
    $result_set = $adb->getResultSet($tabRes);
    
    foreach($result_set as $key=>$result){
        $moduletablename = $result['tablename'];
        $entityidfield = $result['entityidfield'];
    
    $logRMT = "Related Module Tablename : ".$moduletablename.PHP_EOL;
    $logRMT .= "Related Module Entityidfield : ".$entityidfield.PHP_EOL;
    file_put_contents($Resulrpatth2, $logRMT, FILE_APPEND);
    
    if ($mand == '')
    {
        $fieldQuery = "SELECT fieldid,columnname,uitype,vtiger_tab.`name` FROM vtiger_field 
                        INNER JOIN vtiger_tab on vtiger_tab.tabid=vtiger_field.tabid AND vtiger_field.xmlreceivetable = '1'
                        WHERE tablename in ('".$moduletablename."','".$moduletablename."cf') AND FIND_IN_SET(columnname, '".trim($prkey)."')";
    }
    elseif ($mand == 'MU')
    {
        $fieldQuery = "SELECT fieldid,columnname,uitype,vtiger_tab.`name` FROM vtiger_field 
                        INNER JOIN vtiger_tab on vtiger_tab.tabid=vtiger_field.tabid AND vtiger_field.xmlreceivetable = '1'
                        WHERE tablename in ('".$moduletablename."','".$moduletablename."cf') AND columnname = '".$columnname."'";
    }
    
    $log = "FieldQuery : ".$fieldQuery.PHP_EOL;
    file_put_contents($Resulrpatth2, $log, FILE_APPEND);
    
    $params = array();
    $fieldResult = $adb->mquery($fieldQuery,$params);
    $fieldcount = $adb->num_rows($fieldResult);
    
    $log = "Count : ".$fieldcount.PHP_EOL;
    file_put_contents($Resulrpatth2, $log, FILE_APPEND);
    
    if($fieldcount>0){
            
            $excolumnname=$adb->query_result($fieldResult,0,'columnname');
                $logcol = "columnname : ".$excolumnname.PHP_EOL;
                file_put_contents($Resulrpatth2, $logcol, FILE_APPEND);
                
            if ($EOVparent != '')
                $query1 = $query.'/'.$excolumnname;
            else
                $query1 = $query;
                    
            $logpat = "XML Path : ".$query1.PHP_EOL;
            file_put_contents($Resulrpatth2, $logpat, FILE_APPEND);
    
            $entries = $xpath->query($query1,$doce);
            //echo "<br>".$query1."<br>";
            foreach ($entries as $entry) {
                $nodevalue = $entry->nodeValue;
            }
            
            $logval = "NodeValue : ".$nodevalue.PHP_EOL;
            file_put_contents($Resulrpatth2, $logval, FILE_APPEND);
            
            $distfieldname_tblname = "SELECT distfieldname_tblname FROM sify_tr_rel WHERE transaction_name = ?";
            $params = array($EOVmodule);
            $distfieldname_tblname_Res = $adb->mquery($distfieldname_tblname,$params);
            $distfieldname_tblname_val = $adb->query_result($distfieldname_tblname_Res,0,0);
            
            $distfieldname_tblname_value = explode("#",$distfieldname_tblname_val);
            $distfieldname = $distfieldname_tblname_value[0];
            $distfieldname_tabname = $distfieldname_tblname_value[1];
            
            $logval = "distfieldname_tblname : ".$distfieldname_tblname." - ".$EOVmodule.PHP_EOL;
            $logval .= "Distributor Field Name for the master : ".$distfieldname_tblname_val.PHP_EOL;
            $logval .= "Distributor Field Name : ".$distfieldname.PHP_EOL;
            $logval .= "Distributor Table Name : ".$distfieldname_tabname.PHP_EOL;
            $logval .= "EOVmodule : ".$EOVmodule.PHP_EOL;
            file_put_contents($Resulrpatth2, $logval, FILE_APPEND);
            
            if($distcode !="")
            {
                $distid = "SELECT xdistributorid FROM vtiger_xdistributor
                    INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xdistributor.xdistributorid
                    WHERE vtiger_crmentity.deleted = 0 AND vtiger_xdistributor.distributorcode = ?";
                $params = array($distcode);
                $distid_Res = $adb->mquery($distid,$params);
                $dist_id = $adb->query_result($distid_Res,0,0);
                
                $logval = "Distributor ID : ".$dist_id.PHP_EOL;
                file_put_contents($Resulrpatth2, $logval, FILE_APPEND);
            }
            if(empty($dist_id))
            {
                $dist_code_query = $query.'/dist_code';
                
                $logpat = "Distributor Code XML Path : ".$dist_code_query.PHP_EOL;
                file_put_contents($Resulrpatth2, $logpat, FILE_APPEND);

                $entries = $xpath->query($dist_code_query,$doce);
                //echo "<br>".$query1."<br>";
                foreach ($entries as $entry) {
                    $nodevalue = $entry->nodeValue;
                }

                $logval = "distcode : ".$nodevalue.PHP_EOL;
                file_put_contents($Resulrpatth2, $logval, FILE_APPEND);
                $distcode = $nodevalue;
                
                $distid = "SELECT xdistributorid FROM vtiger_xdistributor
                    INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xdistributor.xdistributorid
                    WHERE vtiger_crmentity.deleted = 0 AND vtiger_xdistributor.distributorcode = ?";
                $params = array($distcode);
                $distid_Res = $adb->mquery($distid,$params);
                $dist_id = $adb->query_result($distid_Res,0,0);
                
                $logval = "Distributor ID : ".$dist_id.PHP_EOL;
                file_put_contents($Resulrpatth2, $logval, FILE_APPEND);
            }
            
            $ExisObjQuery = "SELECT ".$moduletablename.".".$entityidfield." FROM ".$moduletablename."" ;
            $ExisObjQuery .= " INNER JOIN vtiger_crmentity on vtiger_crmentity.crmid= ".$moduletablename.".".$entityidfield." and vtiger_crmentity.deleted=0";
            if($distfieldname_tabname != "")
                $ExisObjQuery .= " INNER JOIN ".$distfieldname_tabname." ON ".$distfieldname_tabname.".".$entityidfield." = ".$moduletablename.".".$entityidfield ;
                
             $ExisObjQuery .= " WHERE ".$moduletablename.".".$excolumnname." = '".$nodevalue."'";
            
            if ($distfieldname_tblname_val != "" && $dist_id != "")
            {
                IF($distfieldname_tabname != "" && $EOVmodule != "xrSalesInvoice")
                    $ExisObjQuery .= " AND ".$distfieldname_tabname.".".$distfieldname."= '".$dist_id."'";
                ELSEIF($EOVmodule == "xrSalesInvoice")
                    $ExisObjQuery .= " AND ".$distfieldname_tabname.".".$distfieldname."= '".$distcode."'";
                ELSE
                    $ExisObjQuery .= " AND ".$moduletablename.".".$distfieldname."= '".$dist_id."'";
            }
            
            $log = "Value Query : ".$ExisObjQuery.PHP_EOL;
            file_put_contents($Resulrpatth2, $log, FILE_APPEND);
            
            $params = array();
            $ExisObjResult = $adb->mquery($ExisObjQuery,$params);
            $ExisObjId = $adb->query_result($ExisObjResult,0,0);
			
			if(!empty($ExisObjId)){
					break;
			}
            
            $logRMI = "Related Module Id : ".$ExisObjId.PHP_EOL;
            //$logRMI .= "----End Of Related Module----".PHP_EOL;
            file_put_contents($Resulrpatth2, $logRMI, FILE_APPEND);
    }
    }
    $logRMI = "----End Of Related Module----".PHP_EOL;
    file_put_contents($Resulrpatth2, $logRMI, FILE_APPEND);
    return $ExisObjId;
}

//For Creatinf the Log for send and Receive
function sendreceiveaudit($sen_rec_doc_name,$sen_rec_options,$sen_rec_status,$sen_rec_reason,$sen_rec_recordid,$sen_rec_distcode,$sen_rec_sourceapplication,$sen_rec_doc_createddate,$sen_rec_documenttype,$sen_rec_rawurl,$sen_rec_destapplication,$subjectVal,$statuscode,$statusmsg)
{
    global $adb;
    $date = date('Y-m-d H:i:s');
    
    $InsertQuery = "INSERT INTO sify_send_receive_audit (sen_rec_doc_name,sen_rec_options,sen_rec_status,sen_rec_reason,sen_rec_recordid,sen_rec_createddate,sen_rec_distcode,sen_rec_sourceapplication,sen_rec_doc_createddate,sen_rec_documenttype,sen_rec_rawurl,sen_rec_destapplication)
        VALUES ('".$sen_rec_doc_name."','".$sen_rec_options."','".$sen_rec_status."','".$sen_rec_reason."','".$sen_rec_recordid."','".$date."','".$sen_rec_distcode."','".$sen_rec_sourceapplication."','".$sen_rec_doc_createddate."','".$sen_rec_documenttype."','".$sen_rec_rawurl."','".$sen_rec_destapplication."')";
    $params1 = array();
    $adb->mquery($InsertQuery,$params1);

        $InsertQuerylog = "INSERT INTO sify_receive_audit_log (rec_log_doc_name,rec_log_options,rec_log_status,rec_log_reason,rec_log_recordid,rec_log_createddate,rec_log_distcode,rec_log_sourceapplication,rec_log_doc_createddate,rec_log_documenttype,rec_log_rawurl,rec_log_destapplication,rec_log_subject,rec_log_status_code,rec_log_status_msg)
        VALUES ('".$sen_rec_doc_name."','".$sen_rec_options."','".$sen_rec_status."','".$sen_rec_reason."','".$sen_rec_recordid."','".$date."','".$sen_rec_distcode."','".$sen_rec_sourceapplication."','".$sen_rec_doc_createddate."','".$sen_rec_documenttype."','".$sen_rec_rawurl."','".$sen_rec_destapplication."','".$subjectVal."','".$statuscode."','".$statusmsg."')";
        $adb->mquery($InsertQuerylog,array());

    
}

function insertTaxRel($transaction_id,$lineitem_id,$transaction_name,$tax_type,$tax_label,$tax_percentage)
{
    global $adb;
    $query ="INSERT INTO sify_xtransaction_tax_rel (transaction_id,lineitem_id,transaction_name,tax_type,tax_label,tax_percentage)
        VALUES ('".$transaction_id."','".$lineitem_id."','".$transaction_name."','".$tax_type."','".$tax_label."','".$tax_percentage."')";
    $qparams = array();
    $adb->mquery($query,$qparams);
}

function getXMLStringview($moduleX,$distid,$datasendtype,$From_Date,$To_Date, $vi_id)
{
    global $adb,$root_directory,$SENDRECEIVELOG;
    if($SENDRECEIVELOG == 'True')
        $Resulrpatth1 = $root_directory.'storage/log/slog/log_XMLSend_'.date("jFY").'.txt';
    
    $log = "User: ".$_SERVER['REMOTE_ADDR'].' - '.date("F j, Y, g:i a").' - Distributor id : '.$distid.PHP_EOL;
    file_put_contents($Resulrpatth1, $log, FILE_APPEND);
    
    $xmlstringarray = array();
    $mainviewquery = "SELECT 
        SXQ.queryid,SXQ.module_name,SXQ.view_name,SXQ.query AS `maninquery`
        FROM sify_xml_query SXQ
        WHERE SXQ.module_name = '".$moduleX."'";
    
    $log = "mainviewquery : ".$mainviewquery.PHP_EOL;
    $log .= "--------------------------------------End mainviewquery--------------------------------------".PHP_EOL;
    file_put_contents($Resulrpatth1, $log, FILE_APPEND);
    
    $mainviewresult = $adb->mquery($mainviewquery,array());
    
    if ($adb->num_rows($mainviewresult) > 0)
    {
        $QueryId = $adb->query_result($mainviewresult,0,'queryid');
        $view_name = $adb->query_result($mainviewresult,0,'view_name');
        $module_name = $adb->query_result($mainviewresult,0,'module_name');
        $maninquery = $adb->query_result($mainviewresult,0,'maninquery');
        
        $subviewquery = "SELECT 
            SXAQ.rel_view_name,SXAQ.rel_module_name,SXAQ.query AS `relquery`
            FROM sify_xml_additional_query SXAQ
            WHERE SXAQ.queryid = '".$QueryId."'";
        
        $log = "subviewquery : ".$subviewquery.PHP_EOL;
        $log .= "--------------------------------------END subviewquery--------------------------------------".PHP_EOL;
        file_put_contents($Resulrpatth1, $log, FILE_APPEND);
        
        $subviewqueryresult = $adb->mquery($subviewquery,array());
        $subviewcount = $adb->num_rows($subviewqueryresult);
        
        if($subviewcount > 0)
        {
            for ($index = 0; $index < $subviewcount; $index++)
            {
                $rel_view_name = $adb->query_result($subviewqueryresult,$index,'rel_view_name');
                $rel_module_name = $adb->query_result($subviewqueryresult,$index,'rel_module_name');
                $relquery = $adb->query_result($subviewqueryresult,$index,'relquery');
                
                if ($relquery != "")
                {
                    $log = "relquery : ".$relquery.PHP_EOL;
                    file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                    
                    $reletquery = "";
                    $numoffield = explode(",",$relquery);
                    
                    $log = "numoffield : ".  print_r($numoffield, TRUE).PHP_EOL;
                    file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                    
                    for ($index1 = 0; $index1 < count($numoffield); $index1++)
                    {
                        $mainviewval = "";
                        $field = explode("#",$numoffield[$index1]);
                        
                        $log = "field : ".  print_r($field, TRUE).PHP_EOL;
                        file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                        
                        $confieldresult = $adb->mquery("SHOW COLUMNS FROM ".$rel_view_name." WHERE FIELD = '".$field[1]."'",array());
                        $confieldCount = $adb->num_rows($confieldresult);
                        
                        $log = "----------------------------------------------------------------".PHP_EOL;
                        $log .= "Query : "."SHOW COLUMNS FROM ".$rel_view_name." WHERE FIELD = '".$field[1]."'".PHP_EOL;
                        $log .= "---------------------------------------------------------------".PHP_EOL;
                        file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                        
                        $log = "confieldCount : ".$confieldCount.PHP_EOL;
                        file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                        
                        if($confieldCount > 0)
                        {
                            if ($field[3] != 'distributorid')
                                $mainviewval = getmainviewval($view_name,$field[3],$datasendtype,$From_Date,$To_Date);
                            else
                                $mainviewval = $distid;
                            
                            $log = $field[3]."confieldCount : ".$confieldCount.">0".PHP_EOL;
                            file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                        }
                        else
                        {
                            if ($field[3] != 'distributorid')
                                $mainviewval = $field[3];
                            else
                                $mainviewval = $distid;
                            
                            $log = $field[3]."-- ".$distid."  confieldCount : ".$confieldCount.">0".PHP_EOL;
                            file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                        }
                        
                        $log = "Distributor relatedviewval : ".$mainviewval.PHP_EOL;
                        file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                        
                        if ($mainviewval != "")
                        {
                            if ($field[2] == 'IN')
                                $reletquery .= $field[0]." ".$field[1]." ".$field[2]." (".$mainviewval.") ";
                            else
                                $reletquery .= $field[0]." ".$field[1]." ".$field[2]." '".$mainviewval."' ";
                            
                            $log = "if IN reletquery : ".$reletquery.PHP_EOL;
                            file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                        }
                    }
                    //echo $reletquery; exit;
                    $xmlstringarray['rel'][$index] = getviewvalue($rel_view_name,$rel_module_name,$datasendtype,$From_Date,$To_Date,"1",$reletquery);
                }
                else
                {
                    $xmlstringarray['rel'][$index] = getviewvalue($rel_view_name,$rel_module_name,$datasendtype,$From_Date,$To_Date,"1");
                }
                //$fpx = fopen('D:\wamp\www\3.txt', 'w');
                //fwrite($fpx, print_r($xmlstringarray,true));
                //fclose($fpx); 
                //exit;
            }
        }
        if ($maninquery != "")
        {
            $mainreletquery = "";
            $numoffield = explode(",",$maninquery);
            
            $log = "--------------------------------------Main Query--------------------------------------".PHP_EOL;
            $log .= "Main numoffield : ".print_r($numoffield,TRUE).PHP_EOL;
            file_put_contents($Resulrpatth1, $log, FILE_APPEND);
            
            for ($index2 = 0; $index2 < count($numoffield); $index2++)
            {
                $mainviewval = "";
                $field = explode("#",$numoffield[$index2]);
                
                $log = "field : ".print_r($field, TRUE).PHP_EOL;
                file_put_contents($Resulrpatth1, $log, FILE_APPEND);

                if ($field[3] != 'distributorid')
                    $mainviewval = $field[3];
                else
                    $mainviewval = $distid;
                
                $log = "Distributor mainviewval : ".$mainviewval.PHP_EOL;
                file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                
                if ($mainviewval != "")
                {
                    if ($field[2] == 'IN')
                        $mainreletquery .= $field[0]." ".$field[1]." ".$field[2]." (".$mainviewval.") ";
                    else
                        $mainreletquery .= $field[0]." ".$field[1]." ".$field[2]." '".$mainviewval."' ";
                    
                    $log = "mainreletquery : ".$mainreletquery.PHP_EOL;
                    file_put_contents($Resulrpatth1, $log, FILE_APPEND);
                }
            }
            $xmlstringarray['main'] = getviewvalue($view_name,$module_name,$datasendtype,$From_Date,$To_Date,"0",$mainreletquery,$vi_id);
        }
        else
            $xmlstringarray['main'] = getviewvalue($view_name,$module_name,$datasendtype,$From_Date,$To_Date,"0","",$vi_id);
    }
    else
    {
        echo "View Name Not Found.";
    }
    return $xmlstringarray;
}

function getviewvalue($view_name,$module_name,$datasendtype,$From_Date,$To_Date,$xmlrelmod,$reletquery,$vi_id)
{
    global $adb,$root_directory,$SENDRECEIVELOG;
    if($SENDRECEIVELOG == 'True')
        $Resulrpatth1 = $root_directory.'storage/log/slog/log_XMLSend_'.date("jFY").'.txt';
    
    if ($xmlrelmod != "1")
        global $XML_PAGINATION_COUNT;
    else
        $XML_PAGINATION_COUNT = "10000";
    
    $xmlstring = "";
    $xmlstringarray = array();
    
    $xmlstring .= "<".$module_name."s>";
    
    $ViewValQuery = "SELECT * FROM $view_name WHERE";
    if($vi_id != '')
        $ViewValQuery .= " vi_id in ($vi_id) AND ";
    
    if ($datasendtype == 'Mark' && $xmlrelmod == '0')
            $ViewValQuery .= "  sendstatus = '1' ".$reletquery;
    elseif ($datasendtype == 'Period Based' && $xmlrelmod == '0')
            $ViewValQuery .= "  createdtime BETWEEN '".$From_Date."' AND '".$To_Date."' ".$reletquery;
    elseif ($datasendtype == '' || $reletquery != '')
    {
        $reletquery1 = ltrim ($reletquery,'AND');
        $ViewValQuery .= "  ". $reletquery1;
    }
    //echo "Hi :".$ViewValQuery."<br/>";
    $chk_query = substr($ViewValQuery, -5);
    if($chk_query == 'WHERE' || $chk_query == ' AND ')
        $ViewValQuery = substr($ViewValQuery, 0, -5);
    $log = "--------------------------------------Get View Value--------------------------------------".PHP_EOL;
    $log .= "ViewValQuery : ".$ViewValQuery.PHP_EOL;
    file_put_contents($Resulrpatth1, $log, FILE_APPEND);
//echo $ViewValQuery . "<br>";
    $ViewValResult = $adb->mquery($ViewValQuery,array());
    $ViewValCount = $adb->num_rows($ViewValResult);
    
    if($ViewValCount > 0)
    {
        $k=0;
        for ($index = 0; $index < $ViewValCount; $index++)
        {
            if($index%$XML_PAGINATION_COUNT==0 && $index>0)
            {
                $xmlstringarray[$k]=$xmlstring . "</".$module_name."s>";
                $k++;
                $xmlstring = "<".$module_name."s>";
            }

            $xmlstring .= "<".$module_name.">";
            $ViewColdetResult = $adb->mquery("SHOW COLUMNS FROM ".$view_name,array()) ;
            $ViewColumCount = $adb->num_rows($ViewColdetResult);
            
            if ($ViewColumCount > 0)
            {
                for ($index1 = 0; $index1 < $ViewColumCount; $index1++)
                {
                    $subviewcolnme = $adb->query_result($ViewColdetResult,$index1,0);
                    //$sendxmlvalidres = $adb->mquery("SELECT * FROM vtiger_field F WHERE F.columnname = '' AND F.xmlsendtable = '1'",array()) ;
                    //if ($adb->num_rows($sendxmlvalidres) > 0)
                    //{
                        $ViewVal = $adb->query_result($ViewValResult,$index,$subviewcolnme);
                        $xmlstring .= "<".$subviewcolnme.">".$ViewVal."</".$subviewcolnme.">";
                    //}
                }
            }
            $xmlstring .= "</".$module_name.">";
        }
    }

    $xmlstring .= "</".$module_name."s>";
    
    $xmlstringarray[$k] = $xmlstring;
    
    return $xmlstringarray;
}

function getmainviewval($view_name,$colemname,$datasendtype,$From_Date,$To_Date)
{
    global $adb;
    
    $ViewValQuery = "SELECT GROUP_CONCAT(DISTINCT(".$colemname.")) FROM ".$view_name;
    if ($datasendtype == 'Mark')
        $ViewValQuery .= " WHERE sendstatus = '1'";
    elseif ($datasendtype == 'Period Based')
        $ViewValQuery .= " WHERE createdtime BETWEEN '".$From_Date."' AND '".$To_Date."'";
    
    $ViewValResult = $adb->mquery($ViewValQuery,array());
    $ViewVal = $adb->query_result($ViewValResult,0,0);
    
    return $ViewVal;
}

function updateRetInfo($latVal,$longVal,$customer_code,$distributor_code){   // Updating Latitude and Longitude In Retailer Master 
    global $adb;
        
    $pQuery2 = $adb->mquery("SELECT xdistributorid FROM vtiger_xdistributor INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xdistributor.xdistributorid WHERE vtiger_crmentity.deleted = 0 AND distributorcode = ?",array($distributor_code));
    $distId = $adb->query_result($pQuery2 ,0,0);
    
    $pQry1 = $adb->mquery("SELECT ret.xretailerid FROM  vtiger_xretailer ret
                        LEFT JOIN vtiger_crmentity ce ON ret.xretailerid=ce.crmid 
                        WHERE ce.deleted=0 AND customercode = ? AND ret.distributor_id = ?",array($customer_code,$distId));
    $retId = $adb->query_result($pQry1 ,0,"xretailerid");
    $adb->mquery("UPDATE vtiger_xretailer ret SET ret.latitude = ?,ret.longitude=?,ret.distributor_id=? WHERE ret.xretailerid=?",array($latVal,$longVal,$distId,$retId));
}

function update_claim_update($distributor_code,$reference_no,$claim_head_code,$note_ref)
{
    global $adb,$root_directory;

    $distidquery = "SELECT * FROM vtiger_xdistributor
        INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xdistributor.xdistributorid
        WHERE vtiger_crmentity.deleted = 0 AND distributorcode = ?";
    
    $distidqueryres = $adb->mquery($distidquery,array($distributor_code));
    $distId = $adb->query_result($distidqueryres ,0,"xdistributorid");
    
    $claimheadidquery = $adb->mquery("SELECT * FROM vtiger_xclaimhead INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xclaimhead.xclaimheadid WHERE vtiger_crmentity.deleted = 0 AND claim_head_code = ?",array($claim_head_code));
    $claim_head_id = $adb->query_result($claimheadidquery ,0,"xclaimheadid");
    
    $claimidqueryquery = "SELECT * FROM vtiger_xclaimtopsheet
        INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xclaimtopsheet.xclaimtopsheetid
        WHERE vtiger_crmentity.deleted = 0 AND vtiger_xclaimtopsheet.claim_topsheet_reference_no = ?";
    
    $claimidquery = $adb->mquery($claimidqueryquery,array($reference_no));
    $claim_id = $adb->query_result($claimidquery ,0,"xclaimtopsheetid");
    
    $updateclaimqueryquery = "UPDATE vtiger_xclaimtopsheet SET credit_not_ref = ?
        WHERE claim_topsheet_reference_no = ? AND xclaimheadid = ? AND xclaimtopsheetid = ? AND xdistributorid = ?";    
    $updateclaimquery = $adb->mquery($updateclaimqueryquery,array($note_ref,$reference_no,$claim_head_id,$claim_id,$distId));
    
//    $Resulrpatth1 = $root_directory.'storage/log/rlog/Test'.date("jFY").'.txt';
//    $logFV = "distId : ".$distId.PHP_EOL;
//    $logFV .= "claim_head_id : ".$claim_head_id.PHP_EOL;
//    $logFV .= "claim_id : ".$claim_id.PHP_EOL;
//    $logFV .= "note_ref : ".$note_ref.PHP_EOL;
//    file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);
    
}
function updatePoSattusInfo($distributor_code,$transaction_numberVal,$purchaseorder_noval,$po_statusval,$remak){   
// Updating PoSattus PurchaseOrder 
    global $adb,$Resulrpatth1;
    $distId = $POId = $upsatus = 0;
    if(!empty($distributor_code))
    {
        $pQuery2 = $adb->mquery("SELECT xdistributorid FROM vtiger_xdistributor INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_xdistributor.xdistributorid WHERE vtiger_crmentity.deleted = 0 AND distributorcode = ?",array($distributor_code));
        $distId = $adb->query_result($pQuery2 ,0,0);
    }
    if(!empty($distId) && (!empty($transaction_numberVal) || !empty($purchaseorder_noval)))
    {
        $pQry1 = $adb->mquery("SELECT PO.purchaseorderid FROM vtiger_purchaseorder PO INNER JOIN vtiger_purchaseordercf POCF ON PO.purchaseorderid = POCF.purchaseorderid WHERE  POCF.cf_purchaseorder_buyer_id = ?  AND PO.deleted = ?  AND ((PO.purchaseorder_no = '$purchaseorder_noval' OR PO.purchaseorderid = '$purchaseorder_noval') AND POCF.cf_purchaseorder_transaction_number = '$transaction_numberVal' )",array($distId,0));
        $POId = $adb->query_result($pQry1 ,0,"purchaseorderid");
    }
    if(!empty($POId)){
            file_put_contents($Resulrpatth1, $logFV, FILE_APPEND);
            $adb->mquery("UPDATE vtiger_purchaseorder PO SET PO.po_status = '$po_statusval',PO.po_remak = '$remak' WHERE PO.purchaseorderid = ? ",array($POId));
            $upsatus = $POId;
    }
return $upsatus;
}
function UserCreationReceive($distid)
{
    global $adb;
    
    $roleSql = "SELECT roleid FROM `vtiger_role` WHERE rolename = 'Distributor' ";
    $res = $adb->mquery($roleSql);
    $roleid = $adb->fetchByAssoc($res);
    
    $Qry = "SELECT dcf.*,d.* FROM vtiger_xdistributorcf dcf
        LEFT JOIN vtiger_xdistributor d on d.xdistributorid=dcf.xdistributorid
        WHERE dcf.xdistributorid=".$distid;
    $result = $adb->mquery($Qry);
        
    $user = CRMEntity::getInstance('Users');
    $user->column_fields['roleid'] = $roleid['roleid'];     //*mandatory
    $user->column_fields['email1'] = $adb->query_result($result,0,'cf_xdistributor_email');
    $user->column_fields['phone_mobile'] = $adb->query_result($result,0,'cf_xdistributor_phone'); //*mandatory
    $user->column_fields['email2'] = $adb->query_result($result,0,'cf_xdistributor_email');    //*mandatory
    $user->column_fields['phone_fax'] = $adb->query_result($result,0,'distributorcode'); //this field is for employee Id
    $user->column_fields['date_format'] =  $adb->query_result($result,0,'user_date_format');     //*mandatory
    $user->column_fields['signature'] = $adb->query_result($result,0,'distributorcode');      //*mandatory
    $user->column_fields['address_street'] = $adb->query_result($result,0,'cf_xdistributor_street');
    $user->column_fields['address_city'] = $adb->query_result($result,0,'cf_xdistributor_city');
    $user->column_fields['address_state'] = $adb->query_result($result,0,'cf_xdistributor_state');
    $user->column_fields['address_postalcode'] = $adb->query_result($result,0,'cf_xdistributor_pin_code');
    $user->column_fields['address_country'] = $adb->query_result($result,0,'cf_xdistributor_country');
    $user->column_fields['geography_hierarchy'] = $adb->query_result($result,0,'cf_xdistributor_geography'); //*mandatory
    $user->column_fields['reports_to_id'] = $adb->query_result($result,0,'user_reports_to_id');
    $user->column_fields['is_admin'] = 'off';
    $user->column_fields['is_sadmin'] = 'off';
    $user->column_fields['user_name'] = $adb->query_result($result,0,'distributorcode'); //*mandatory
    $user->column_fields['user_password'] = $adb->query_result($result,0,'distributorcode'); //*mandatory
    $user->column_fields['confirm_password'] = $adb->query_result($result,0,'distributorcode'); //*mandatory
    $user->column_fields['last_name'] = $adb->query_result($result,0,'distributorcode'); //*mandatory
    $user->column_fields['status'] = 'Active';
    $user->save('Users');
    
    $last_id = $adb->getLastInsertID();
    
    //These 2Files for generating files
    createUserPrivilegesfile($last_id);
    createUserSharingPrivilegesfile($last_id);
    
    $distMapp = CRMEntity::getInstance('xDistributorUserMapping');
    $distMapp->column_fields['distributorusermappingcode'] = $distMapp->seModSeqNumber('increment', 'xDistributorUserMapping');
    $distMapp->column_fields['cf_xdistributorusermapping_distributor'] = $distid;
    $distMapp->column_fields['cf_xdistributorusermapping_supporting_staff'] = $last_id;
    $distMapp->save('xDistributorUserMapping');

    $userrelSql = "INSERT INTO vtiger_xdistributoruserrel (distributorid,userid) VALUES ('".$distid."','".$last_id."')";
    $res = $adb->mquery($userrelSql);
    
    return $last_id;
}

function TransactionSeriesReceive($distid)
{
    global $adb, $current_user;

    $sql = "SELECT * FROM vtiger_xtransactionseries as tran 
            LEFT JOIN vtiger_xtransactionseriescf as trancf ON tran.xtransactionseriesid = trancf.xtransactionseriesid
            LEFT JOIN vtiger_crmentity on vtiger_crmentity.crmid=tran.xtransactionseriesid
            WHERE (tran.xdistributorid IS NULL OR tran.xdistributorid = '0') AND vtiger_crmentity.deleted=0";
    $res = $adb->mquery($sql);

    for ($x = 0; $x < $adb->num_rows($res); $x++)
    {
        $tsObj = $adb->fetchByAssoc($res, $x);
        $transcation = CRMEntity::getInstance('xTransactionSeries');
        foreach ($tsObj as $keyx => $valx)
        {
            if ($keyx == 'xtransactionseriesid')
                continue;
            $transcation->column_fields[$keyx] = $tsObj[$keyx];
        }
        $transcation->column_fields['cf_xtransactionseries_status'] = '1';
        $transcation->column_fields['xdistributorid'] = $distid;
        $transcation->column_fields['cf_xtransactionseries_user_id'] = '0';
                
        $transcation->save('xTransactionSeries');
    }
}


function getLatestPriceForProduct($distId,$prodId)
{
    global $adb;
    
    $pts=$ptr=$mrp=$ecp=0.0;
    
    $stLotsResult=$adb->mquery("SELECT productid,pts,ptr,mrp,ecp FROM vtiger_stocklots WHERE vtiger_stocklots.distributorcode=? AND vtiger_stocklots.productid=? ORDER BY vtiger_stocklots.id desc LIMIT 0,1",array($distId,$prodId));
    
    if($adb->num_rows($stLotsResult)>0)
    {
        $pts=$adb->query_result($stLotsResult,0,'pts');
        $ptr=$adb->query_result($stLotsResult,0,'ptr');
        $mrp=$adb->query_result($stLotsResult,0,'mrp');
        $ecp=$adb->query_result($stLotsResult,0,'ecp');
    }    
    
    return array("pts"=>$pts,"ptr"=>$ptr,"mrp"=>$mrp,"ecp"=>$ecp);
}

function checkHierarchyCreated($table,$idname)
{
    global $adb;
    
    $Primary_table      = $table;
    $CF_table           = $table.'cf';
    
    $HierarchyResult    = $adb->mquery("SELECT count(*) as recordcount FROM $Primary_table INNER JOIN $CF_table ON $Primary_table.$idname =$CF_table.$idname INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=$Primary_table.$idname  WHERE 1=1",array());
    
    if($adb->num_rows($HierarchyResult)>0)
    {
        $count = $adb->query_result($HierarchyResult,0,'recordcount');
    }    
    
    return $count;
}

function GetDistributorDetailFromForumCode($fcode)
{
    global $adb;
    
    $distdetail = $adb->mquery("SELECT D.distributorcode AS `distributorcode`, D.distributorname AS `distributorname`, D.xdistributorid AS `xdistributorid` FROM vtiger_xdistributor D INNER JOIN vtiger_xdistributorcf DCF ON D.xdistributorid = DCF.xdistributorid WHERE DCF.cf_xdistributor_forum_code = ? ",array($fcode));
    
    if($adb->num_rows($distdetail)>0)
    {
        for ($index = 0; $index < $adb->num_rows($distdetail); $index++) {
            $ret = $adb->raw_query_result_rowdata($distdetail,$index);
        }
    }
    return $ret;
}
/** This function returns the default vtiger_currency information.
  * Takes no param, return type array.
    */
    
function GetDistributorGSTDetail($distid)
{
    global $adb;
    
    $distdetail = $adb->mquery("SELECT gstinno,panno,typeofservices,"
            . "DCF.cf_xdistributor_city as cf_xaddress_city,DCF.cf_xdistributor_pin_code as cf_xaddress_postal_code, "
            . "DCF.cf_xdistributor_country as cf_xaddress_country,"
            . "CONCAT(DCF.cf_xdistributor_street,' ',DCF.cf_xdistributor_district,' ',DCF.cf_xdistributor_region) as cf_xaddress_address, "
            . "DCF.cf_xdistributor_state as cf_xretailer_state "
            . "FROM vtiger_xdistributor D "
            . "INNER JOIN vtiger_xdistributorcf DCF ON D.xdistributorid = DCF.xdistributorid "
            . "WHERE D.xdistributorid = ? ",array($distid));
    
    if($adb->num_rows($distdetail)>0)
    {
        for ($index = 0; $index < $adb->num_rows($distdetail); $index++) {
            $ret = $adb->raw_query_result_rowdata($distdetail,$index);
        }
    }
    return $ret;
}
function GetCustomerGSTDetail($customerid)
{
    global $adb;
    
    $customerdetail = $adb->mquery("SELECT gstinno,panno,typeofservices, "
            . "RCF.cf_xretailer_pin_code as cf_xaddress_postal_code, "
            . "RCF.cf_xretailer_city as cf_xaddress_city, "
            . "CONCAT(RCF.cf_xretailer_address_1,' ',RCF.cf_xretailer_city) AS cf_xaddress_address,"
            . "RCF.cf_xretailer_state "
            . "FROM vtiger_xretailer R "
            . "INNER JOIN vtiger_xretailercf RCF ON R.xretailerid = RCF.xretailerid "
            . "WHERE R.xretailerid = ? ",array($customerid));
    
    if($adb->num_rows($customerdetail)>0)
    {
        for ($index = 0; $index < $adb->num_rows($customerdetail); $index++) {
            $ret = $adb->raw_query_result_rowdata($customerdetail,$index);
        }
    }
    return $ret;
}
function updateSubject($tablename,$updated_field ='',$update_value = '',$updatedwhere = '',$updatedwhereval = '',$Resulrpatth1){
	global $adb;
	if(!empty($updatedwhereval)){
		echo $updateqy = "UPDATE ".$tablename." SET ".$updated_field." = ? WHERE ".$updatedwhere." = ?";
		$updated_val_array = array($update_value.'_'.$updatedwhereval,$updatedwhereval);
		$log = "updateqy: ".$updateqy.PHP_EOL;
		$log .= "values: ".print_r($updated_val_array,true).PHP_EOL;
		file_put_contents($Resulrpatth1, $log, FILE_APPEND);
		$result = $adb->mquery($updateqy,$updated_val_array);
	}
}
function validateUserLogin($distributor_code,$user_name,$user_password,$slm_code){   
// Validate user detilas
    global $adb,$Resulrpatth1;
	$distId = $userid = $upsatus = 0;
	if(!empty($user_name) && !empty($user_password)){
		$useerQuery = $adb->mquery("SELECT USR.id FROM vtiger_users USR  WHERE USR.status = 'Active' AND USR.deleted = 0 AND USR.user_name = ? AND USR.user_hash = ?",array($user_name,$user_password));
		$usercnt = $adb->num_rows($useerQuery);
		if(!empty($usercnt)){
			$userid =$adb->query_result($useerQuery,0,'id');
		}
		
	}
	if(!empty($userid)){
		$upsatus = $userid;
	}
    return $upsatus;
}
function pricebootstrap($inputarray = array()){   
// Boostrap
    global $adb,$site_URL;
	$distids = $slmid = $cslmid = $upsatus = 0;
	$conQuery = "SELECT `key` as lablename,`value` as lablevalue
FROM sify_inv_mgt_config
WHERE (
		`key` = 'SSF_PROHIER_SALES_HISTORY'	OR `key` = 'SSF_PROHIER_SI_DONEPAST'
		OR `key` = 'SSF_PROHIER_SI_DONEPAST_DAYS'	OR `key` = 'SSF_PROHIER_RETUEN_QTY_REDUCE'
		OR `key` = 'SSF_STOCK_NOT_AVAILABLE'	OR `key` = 'SSF_PROHIER_RETUEN_FULL_REDUCE'
		OR `key` = 'SSF_RETUEN_QTY_REDUCE' OR `key` = 'SSF_DISPLAY_PAST_SI_DAYS'
		OR `key` = 'SSF_DISPLAY_PAST_SI' OR `key` = 'SSF_SALES_HISTORY'
		OR `key` = 'SSF_DONT_PRODUCTS_VAL' OR `key` = 'SSF_DONT_OUTOFSTOCK_VAL'
		OR `key` = 'SSF_DONT_OUTOFSTOCK' OR `key` = 'SSF_DONT_PRODUCTS'
		OR `key` = 'SSF_CUSTEMER_OUTSTANDING' OR `key` = 'SSF_STOCK_AVAILABLE_VAL'
		OR `key` = 'SSF_STOCK_NOT_AVAILABLE_VAL' OR `key` = 'SSF_STOCK_AVAILABLE'
		OR `key` = 'SSF_STOCK_NOT_AVAILABLE' OR `key` = 'SSF_RESTRICT_PRODUCT_LISTING'
		OR `key` = 'SSF_DISPLAY_CATG_MONTHLY_DSBD' OR `key` = 'SSF_DISPLAY_CATG_LEVEL_DSBD'
		OR `key` = 'SSF_DISPLAY_CATG_DASHBOARD' OR `key` = 'SSF_DISPLAY_CATG_DAILY_DSBD'
		OR `key` = 'SSF_SUG_ORD_FET' OR `key` = 'SSF_SUG_FOL_OPT'
		OR `key` = 'SSF_LOD_SUG_ORD' OR `key` = 'SSF_SUG_SO'
		OR `key` = 'SSF_SUG_SO_WHI' OR `key` = 'SSF_SUG_SI'
		OR `key` = 'SSF_SUG_SI_WHI'
		)
	AND treatment = 'SFF'";
$config_query = $adb->query($conQuery);
$config_data = array();
$queryForTrack = '';
for ($mc = 0; $mc < $adb->num_rows($config_query); $mc++) {
	$lablename = $adb->query_result($config_query,$mc,'lablename');
	$lablevalue = $adb->query_result($config_query,$mc,'lablevalue');
	if(isset($lablename)){
		$config_data[$lablename] = $lablevalue ; 
	}
     
}

		require_once 'include/cpyBooster.php';
		$cpyBooster = new cpyBooster();
		$upsatus = $cpyBooster->currentStock($adb,$inputarray,$config_data);
	
	if(empty($userid)){
		$upsatus = 1;
	}else{
		$upsatus = 0;
	}
    return 1;
}
?>
