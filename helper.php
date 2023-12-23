<?php 
/**
 * @copyright   Copyright (C) 2014. All rights reserved.
 * @license     GNU General Public License version 2 or later.
 * @author      Magnus Hasselquist <magnus.hasselquist@gmail.com> - http://mintekniskasida.blogspot.se/ till version 2.1.2
 * @author     Tazzios 2021
 */
 
// No direct access
defined('_JEXEC') or die;


require_once( dirname(__FILE__) . '/cblisthelper.php' );






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
			$rules[$i]['usergroups']= $value->usergroups;
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
		$query .=  " order by FIELD(type,'image') desc";
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


		
		$cblistqueryArray = createcblistquerymod($list_id,null);
		$fetch_sql = $cblistqueryArray['cblistselect'];
		$cblistsortby = $cblistqueryArray['cblistsortby'];
		$cblistsortorder = $cblistqueryArray['cblistsortorder'];
		
		
	if ($list_orderby=='list_default' or $list_orderby=='')  {
		$list_orderby = $cblistsortby; 
	}
	
	

		// Sort order 
	   switch  ($list_sortorder) {
		   case "desc":
		   case "asc":
			$userlistorder = " ORDER BY ". $list_orderby . " " . $list_sortorder;
			break;
		   case "random":
			$userlistorder = ' ORDER BY rand()';
			break;
		default:
			// Default way to order
			$userlistorder = " ORDER BY ". $list_orderby . " " . $cblistsortorder;
			break;
	   }

	// echo $fetch_sql . "<br>";
	//$fetch_sql .= ' GROUP BY u.id';
	//Add ordering if list is configured for that
	if ($userlistorder <>'') { $fetch_sql .= " ".$userlistorder; }
		
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
	
	$debug_text= '';				
	if ($list_debug == 1) {  $debug_text .= "<p>DEBUG: <pre>".$fetch_sql."</pre></p>"; }
	$db->setQuery($fetch_sql);
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
			$fieldtype = $field['type'];


			/*set value to insert for normal fields*/ 
			$datatoinsert = null;
			//check if the fieldtouse exist (or is null)
			if (isset($person[$fieldtouse]) ) {
				$datatoinsert = $person[$fieldtouse];
			} 
			

			/*set value to insert for images */ 
			// if it is an image check the approved and create full url
			$show = 'yes';			
			if ($fieldtype=='image') {
				 			
				if ( $person[$fieldtouse.'approved']==0 or (empty($datatoinsert)) ) {
					$datatoinsert = 'nofoto';
				} else {
					//url to the default canvas images are incorrect in stored in the database 
					if ($fieldtouse=='canvas') {
						$datatoinsert = str_ireplace('Gallery/', 'gallery/canvas/', $datatoinsert);
					} 
					//create the full image path
					$datatoinsert =  JURI::base(). "images/comprofiler/" .$datatoinsert;
				}
			}
			
			
			/*set value to insert for multiple value fields */ 
			//Fieldtypes with a label name in the comprofiler_field_values
			// normal checkbox currently returns a 0 or 1
			if ( !empty($datatoinsert) and ($fieldtype=='multicheckbox' or $fieldtype=='multiselect' or $fieldtype=='select' or $fieldtype=='radio')) {
				
				$values= explode("|*|", $datatoinsert);				
				// clear unexploded data from data to insert
				$datatoinsert= '';
					
				foreach ($values as $value)	{
					
					//Get label from value
					$dblabel = JFactory::getDbo();
					$query = "select fieldlabel from #__comprofiler_field_values WHERE fieldtitle ='". addslashes($value) . "'";
					$dblabel->setQuery($query);
					$labels = (array) $dblabel->loadAssoc();
				
					if (is_iterable($labels)) {
						foreach ($labels as $label) {			
							if(empty($label)) { 
							$datatoinsert .= $value. " " ;
							}
							else{
								$datatoinsert .= $label. " " ;
							}
						}
					}
					else  {
						print("Can't iterate array\n");					
					}
				}
			}
			
			 
			//check if there is a rule for this field
			if ((array_search($fieldtouse,array_column($rules,'tag_name'))) == true ) {
				
				$rule_id = array_search($fieldtouse,array_column($rules,'tag_name'));
					
					// If the rule is found:
					if (strtolower($rules[$rule_id]['tag_name']) == $fieldtouse) {
									
						/* Check if the rule has an usergroup set for autorisation*/
						
						// get usergroups from loggedin user
						$usergroups = JAccess::getGroupsByUser(JFactory::getUser()->get('id'), $recursive = true);
						
						$autorised = false;
						if (empty($rules[$rule_id]['usergroups']) or array_sum(array_count_values(array_intersect($usergroups, $rules[$rule_id]['usergroups'])))>0 ) { // if not set show the data
							$autorised = true;	
						}
						
						if ($autorised == true) {
							// check if show image is true and data is not empty or that it is a custom tag created in the module.				
							if ( (!empty($datatoinsert)) or $fieldtype=='custom' ) {
								$datatoinsert = str_ireplace($paramtofind, ($datatoinsert ?? ''), $rules[$rule_id]['htmlcode']);						
							} else {	
								$datatoinsert = str_ireplace($paramtofind, ($datatoinsert ?? ''), $rules[$rule_id]['htmlcode_no']);	
							}
						}else {
							//Set to empty when not autorised
							$datatoinsert= null;
						}	
					} 	
			} 
				 
			$after_str = str_ireplace($paramtofind, ($datatoinsert ?? ''), $after_str); // replace the param name with '' if not found.
					
		} // end for each fields
	}//  end while
	
	return $after_str;
	
}	
?>
