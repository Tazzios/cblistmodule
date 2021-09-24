<?php
/**
 * @copyright   Copyright (C) 2014. All rights reserved.
 * @license     GNU General Public License version 2 or later.
 * @author      Magnus Hasselquist <magnus.hasselquist@gmail.com> - http://mintekniskasida.blogspot.se/ till version 2.1.2
 * @author     Tazzios 2021
 */
 
// No direct access
defined('_JEXEC') or die;


function checkString(array $arr, $str) {

  $str = preg_replace( array('/[^ \w]+/', '/\s+/'), ' ', strtolower($str) ); // Remove Special Characters and extra spaces -or- convert to LowerCase

  $matchedString = array_intersect( explode(' ', $str), $arr);

  if ( count($matchedString) > 0 ) {
    return true;
  }
  return false;
}


function db_field_replace($before_str, $user_id,$rules,$fields,$search_paramtofind) {
	
	//Get data from current user
	$db = JFactory::getDbo();
	$query = "select * from #__users inner join #__comprofiler on #__users.id = #__comprofiler.user_id WHERE #__users.id =".$user_id;
	// echo $query;
	$db->setQuery($query);
	$person = $db->loadAssoc();
	
	$after_str = $before_str;


	// The while will only run multiple times if you have complex rules like using [canvas] in your avatar htmlcode.
	// With this while loop we are certain that all paramtofind will be replaced. 
	$i=0; 
	while ((str_replace($search_paramtofind, '', $after_str) !== $after_str) and $i<>5){	
		$i++; // safety count to stop the loop if the user created one. While will run expected once or twice to replace everything.
		
		foreach ($fields as $field) { //for every field that may be in the before_str
			$paramtofind = "[".$field['name']."]";
			$fieldtouse = $field['name'];

			$datatoinsert = '';
			//check if the fieldtouse exist (or is null)
			if (isset($person[$fieldtouse]) ) {
				$datatoinsert = $person[$fieldtouse];
			} 
			

					
			// if it is an image check the approved and create full url
			$show = 'yes';
			if ($field['type']=='image') {
				
				if ( $person[$fieldtouse.'approved']==0 or (empty($datatoinsert)) ) {
					$show = 'no';
				} else {
					//url to the default canvas images are incorrect in stored in the database 
					if ($fieldtouse=='canvas') {
						$datatoinsert = str_ireplace('Gallery/', 'gallery/canvas/', $datatoinsert);
					} 
					//create the full image path
					$datatoinsert =  JURI::base(). "images/comprofiler/" .$datatoinsert;
				}
			}
			

			//check if there is a rule for this field
			if (null !==(array_search($fieldtouse,array_column($rules,'tag_name'))) ) {
				
				//loop through the rules to find the rule	
				foreach ($rules as $rule)	{
				
					// If the rule is found:
					if (strtolower($rule['tag_name']) == $fieldtouse) {
						
						// check if show still true and data is not empty or that it is a custom tag created in the module.
						if ($show == 'yes' and ((!empty($datatoinsert)) or $field['type']=='custom') ) {
							$datatoinsert = str_ireplace($paramtofind, $datatoinsert, $rule['htmlcode']);
							
						} else {	
							$datatoinsert = str_ireplace($paramtofind, $datatoinsert, $rule['htmlcode_no']);
							
						}
					} 
				}  	
			} 
				 
			$after_str = str_ireplace($paramtofind, $datatoinsert, $after_str); // replace the param name with '' if not found.
					
		} // end for each fields
	}//  end while
	
	return $after_str;
	
}

class modcbListHelper
{
    /**
     * Retrieves the Result
     *
     * @param array $params An object containing the module parameters
     * @access public
     */
	 


	public static function getData( $params )
	{
		
		//retrieve $rules
		$subform = $params->get('rules');
		$arr = (array) $subform;
		
		$rules = array();
		$i=0;
		$additional_names = '';
		foreach ($arr as $value)
		{
			$rules[$i]['tag_name']= strtolower($value->tag_name);
			$rules[$i]['htmlcode'] = $value->htmlcode;
			$rules[$i]['htmlcode_no'] = $value->htmlcode_no;
			
			$additional_names .= " UNION SELECT '". strtolower($value->tag_name). "' AS name, 'custom' as type  ";
			
			$i++;
		}
		
		
		// get all the fields that could possibly be part of template to be replaced to get us something to loop through. Also add id and user_id as fields.
		$db = JFactory::getDbo();
		$query = "SELECT name, type FROM #__comprofiler_fields WHERE (#__comprofiler_fields.table = '#__users' OR #__comprofiler_fields.table = '#__comprofiler') and name not in ('password','params')
			UNION SELECT 'id' AS name, '' as type 
			UNION SELECT 'user_id' AS name, '' as type  ";
		// add additional names created in the parameters 
		$query .= $additional_names ;
		// retrieve fields from type images as first. this way other tags in the htmlcode then from the image will also be replaced without additional while loop
		$query .=  " order by FIELD(type,'datetime','image') desc";
		$db->setQuery($query);
		$fields = $db->loadAssocList();
		
		// create an one row array with paramtofind to use for the while check
		$search_paramtofind = array ();
		foreach ($fields as $field) {
			$search_paramtofind[] = "[".$field['name']."]";
		}

		
		

    	$result=''; //reset result
		// Get the parameters
		$list_id = $params->get('listid');
		$list_orderby = $params->get('orderby');
		$list_sortorder = $params->get('sortorder');
		$list_template = $params->get('template');
		$list_textabove = $params->get('text-above');
		$list_textbelow = $params->get('text-below');
		$list_debug = $params->get('debug');		

		// Obtain a database connection
		$db = JFactory::getDbo();
		// Lets make sure to support åäö
		// $query = "SET CHARACTER SET utf8";
		// $db->setQuery($query);

		// Retrieve the selected list
		$query = $db->getQuery(true)
		->select('params')
		->select('usergroupids')		
		->from('#__comprofiler_lists')
		->where('listid = '. $list_id . ' AND published=1')
			->order('ordering ASC');
		// echo $query;
		$db->setQuery($query);

		// Load the List row.
		$row = $db->loadAssoc();
		$select_sql_raw = $row['params'];
		$select_sql =""; //declare variable	

		// avoid  Notice Undefined variable:
		$debug_text ="";

		if ($list_debug == 1) { $debug_text .= "<p>DEBUG: <pre>".$select_sql_raw."</pre></p>"; }

		// Process the filterfields to make ut useful for next query
		// CB19 $select_sql = utf8_encode(substr(urldecode($select_sql_raw), 2, -1));
		$json_a=json_decode($select_sql_raw,true);
		$filters_basic = $json_a['filter_basic'];
		
		
		if (isset($person[$fieldtouse]) ) {
				$datatoinsert = $person[$fieldtouse];
			} 
		

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
							// Default wat to process json values to query
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

	if ($list_orderby=='list_default' or $list_orderby=='')  {
		$list_orderby = $json_a['sort_basic'][0]['column']; 
	}
	

		// Sort order 
	   switch  ($list_sortorder) {
		   case "desc":
		   case "asc":
			$userlistorder = $list_orderby . " " . $list_sortorder;
			break;
		   case "random":
			$userlistorder = 'rand()';
			break;
		default:
			// Default way to order
			$userlistorder = $list_orderby . " " . $json_a['sort_basic'][0]['direction'];
			break;
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

	// echo $fetch_sql . "<br>";
	//$fetch_sql .= ' GROUP BY u.id';
	//Add ordering if list is configured for that
	if ($userlistorder <>'') { $fetch_sql .= " ORDER BY ".$userlistorder; }
		
	//Apply limit
	$fetch_sql .= " LIMIT ".$params->get('user-limit');
	
	// autofit or fixed amount of columns
	if ($params->get('columns') == 0 ) { 
		$columns= "auto-fit";
	} 
	else {
		$columns= $params->get('columns');
	}
			
	$minwidth = "5" ;/* prevent errors and give default value when not is numeric*/
	if (is_numeric($params->get('Minwidth'))) {
		$minwidth = $params->get('Minwidth') ;
	}		
		
	$result .= " <div style=\" margin: 0 auto; display: grid; grid-gap: 0.2rem;grid-template-columns: repeat(". $columns .", minmax(".$minwidth."rem, 1fr));\" class=\"cblist\"> " ;

	// Now, lets use the final SQL to get all Users from Joomla/CB
	$query = $fetch_sql;
					
	if ($list_debug == 1) { $debug_text .= "<p>DEBUG: <pre>".$query."</pre></p>"; }
	$db->setQuery($query);
	$persons = $db->loadAssocList();
	if (!empty($persons)){
		foreach ($persons as $person) { //for every person that is a reciever, lets do an email.
		 	// $result .= $person['username']."<br/>";
		 	// Lets loop over the Users and create the output using the Template, replacing [fileds] in Template
			$result .= "<div style=\"padding: 5px;overflow-wrap: break-word;\" class=\"cblist-user\" >". db_field_replace($list_template, $person['id'],$rules,$fields,$search_paramtofind) ."</div >" ;
		}
	} else if ($list_debug == 1) { $debug_text .= "<p>DEBUG: Empty list?!</p>"; }
	
	$result .= " </div >";
			

	return $list_textabove . $debug_text . $result . $list_textbelow;

    	}
}
?>
