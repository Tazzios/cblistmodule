<?php

defined('_JEXEC') or die;

/* this file will build the query to get the profiles in the same way as the cblist.
* By seperating it it can be easier used in other applications.
* @copyright   2022
* @author      Tazzios 
* @license     GNU General Public License version 2 or later; see LICENSE.txt
*/


	
function createcblistquerymod($cblistid,$cblistname) {	
	$where = '' ;
	if (!empty($cblistid)) {
		$where = 'listid = '. $cblistid ;
	}
	else {
		$where = 'title = \''. $cblistname . '\'';
	}


		
	// Obtain a database connection
	$db = JFactory::getDbo();
	// Retrieve the selected list
	$query = $db->getQuery(true)
	->select('params')
	->select('usergroupids')		
	->from('#__comprofiler_lists')
	->where($where . ' AND published=1');
		//->order('ordering ASC');
	$db->setQuery($query);

	// Load the List row.
	$row = $db->loadAssoc();
	$select_sql_raw = $row['params'];
	$select_sql =""; //declare variable	

	// Process the filterfields to make it usefull for SQL query
	$json_a=json_decode($select_sql_raw,true);
	if (isset($json_a['filter_basic'])) $filters_basic = $json_a['filter_basic'];		

	if ($json_a['filter_mode'] == 0) {
		$i = 0;
		foreach ($filters_basic as $filter) {
		if ($filter['column']<>'') {	
				// If it is not the first filter add AND 
				if ($i>0)  {
					$select_sql .= " AND " ;
				}

				// add qoutes if value is text.
				if (!is_numeric($filter['value'])) {
					$value = "'".$filter['value']."'";
				} else {
					$value = $filter['value'];	
				}

				// Replace operators from json if needed else default
			   switch  ($filter['operator']) {
					case "<>||ISNULL": // CB Not equal to
						$select_sql .=  "(".$filter['column'] . "<> ".$value ." OR ". $filter['column'] . " IS NULL)";
						break;

					case "NOT REGEXP||ISNULL": // CB  is not regexp
						$select_sql .=  "(".$filter['column'] . " NOT REGEXP ".$value ." OR ". $filter['column'] . " IS NULL)";
						break;

					case "NOT LIKE||ISNULL"; //CB Does not contain	
						$value = "'%" . trim($value,'\'"') . "%'"; // any combination of ' and "
						$select_sql .=  "(".$filter['column'] . " NOT LIKE ".$value ." OR ". $filter['column'] . " IS NULL)";
						break;
					   
					case "LIKE"; //CB Does contain	
						$value = "'%" . trim($value,'\'"') . "%'"; // any combination of ' and "
						$select_sql =  "(".$filter['column'] . " LIKE " . $value . ")"	; 		
						break;

					case "IN"; //CB IN	
						$i = 0;
						$include = "";
						//loop al the values from the in filter value. Fetch original value so no aurrounding qoutes are present
						foreach ((explode(",",$filter['value'])) as $value) {						
							// Start with separator is not first one.
							if ($i>0)  {
								$include .= ", " ;
							}

							// place quotes if text
							if (!is_numeric($value)) {
								$value = "'".$filter['value']."'";
							} 

							$include .= "".$value."";
							$i++; 
						}
						$select_sql .=  "".$filter['column'] . " IN (". $include .") ";
						break;

					default:
						// Default way to process json values to query
						$select_sql .=  "(".$filter['column']." ".$filter['operator']." ".$value.")";
						break;
				}
			$i++; 
			}
		}
	}
	
	else if ($json_a['filter_mode'] == 1) {			
		$select_sql = $json_a['filter_advanced'];
	}


	// Set a base-sql for connecting users, fields and lists
	$usergroupids = str_replace("|*|", ",", $row['usergroupids']); //CMJ ADDED
	$usergroupids = trim($usergroupids,','); // prevent that the range starts (or ends) with a comma if you also have selected '--- Select User group (CTR/CMD-Click: multiple)---' at the usergroups

	$list_show_unapproved = $json_a['list_show_unapproved'];
	$list_show_blocked = $json_a['list_show_blocked'];
	$list_show_unconfirmed = $json_a['list_show_unconfirmed'];
	$fetch_sql = "SELECT DISTINCT ue.id FROM #__users u JOIN #__user_usergroup_map g ON g.`user_id` = u.`id` JOIN #__comprofiler ue ON ue.`id` = u.`id` WHERE g.group_id IN (".$usergroupids.")";
	if ($list_show_blocked == 0) {$fetch_sql.=" AND u.block = 0 ";}
	if ($list_show_unapproved == 0) {$fetch_sql.=" AND ue.approved = 1 ";} 
	if ($list_show_unconfirmed == 0) {$fetch_sql.=" AND ue.confirmed = 1 ";}


	// add CB list filters only if there are any
	 if ($select_sql <>'') $fetch_sql = $fetch_sql . " AND (" . $select_sql . ")";
	$cblistquery['cblistselect'] = $fetch_sql;

	//Add ordering if list is configured for that
	// order will be given seperates so it can be overwritten
	$cblistquery['cblistsortby'] =  $json_a['sort_basic'][0]['column'];
	$cblistquery['cblistsortorder'] =  $json_a['sort_basic'][0]['direction'];	

	
	return $cblistquery;
	
}
?>
	
