<?php 

/*
	Plugin name: BQ Database Handler
	Description: Returns data from the BQWatches stock database using the OData SDK interface.		
	Author: Marian Cerny - London Creative
	Version: 1.0
*/

class bq_database_handler
{

// *******************************************************************
// ------------------------------------------------------------------
//					VARIABLES AND CONSTRUCTOR
// ------------------------------------------------------------------
// *******************************************************************

public $o_bqw;
private $a_basic_fields;
private $a_basic_expanded_fields;
private $a_complete_expanded_fields;
private $a_price_ranges;
private $a_genders;
private $i_limit_default;

function __construct()
{
	// INCLUDE AND CREATE PROXY OBJECT
	require_once 'ODataProxyClass.php';
	$this->o_bqw = new BQWATCHESEntities();
	
	// INIT BASIC FIELDS
	$this->a_basic_fields = array(
		'Ref', 'ID', 'TextDescription', 'StockType',
		'TradePrice', 'RetailPrice', 'Manufacturer'
	);
	
	// INIT BASIC EXPANDED FIELDS
	$this->a_basic_expanded_fields = array(
		'Manufacturer',
	);
	
	// INIT COMPLETE EXPANDED FIELDS
	$this->a_complete_expanded_fields = array(
		'Bracelet', 'Bezel', 'Manufacturer', 
		'Dial', 'Model', 'WatchMaterial'
	);
	
	// INIT PRICE RANGES
	$this->a_price_ranges = array(
		0 => array(
			'low' => 0,
			'high' => 2000
		),
		1 => array(
			'low' => 2001,
			'high' => 3000
		),
		2 => array(
			'low' => 3001,
			'high' => 5000
		),
		3 => array(
			'low' => 5001,
			'high' => 10000
		),
		4 => array(
			'low' => 10000,
			'high' => ''
		),
	);
	
	// INIT GENDERS
	$this->a_genders = array(
		'M' => 'mens watches',
		'W' => 'ladies watches'
	);
	
	// INIT DEFAULT LIMIT
	$this->i_limit_default = 20;
	
}

// *******************************************************************
// ------------------------------------------------------------------
// 					FRONT-END MAIN FUNCTIONS
// ------------------------------------------------------------------
// *******************************************************************

function get_filtered_watch_list( $a_filters, $i_skip = 0, $i_limit = 0 ) 
{
	// IF LIMIT NOT SET, GET DEFAULT VALUE
	if ( $i_limit == 0 )
		$i_limit = $this->i_limit_default;
	
	// INIT FILTER STRING
	$s_filter = '';
	// GET ARRAY KEYS
	$a_filter_keys = array_keys( $a_filters );
	
	// ITERATE ALL FILTERS
	foreach( $a_filter_keys as $s_filter_key )
	{
		// PRICE FILTER NEEDS A SPECIAL FORMAT
		if ( ( $s_filter_key == 'price' ) && ( !empty( $a_filters['price'] ) ) )
		{
			$s_price_filter = '(';
			$a_price_ranges = $this->a_price_ranges;
			
			// ITERATE ALL SET PRICE RANGES
			foreach( $a_filters[$s_filter_key] as $i_price_range )
			{
				// IF NOT FIRST FILTER, SEPARATE FILTERS WITH 'OR'
				if ( strlen( $s_price_filter ) > 1 )
					$s_price_filter .= ' or ';
				// GET LOW AND HIGH VALUES FOR CURRENT PRICE RANGE
				$i_low = $a_price_ranges[$i_price_range]['low'];
				$i_high = $a_price_ranges[$i_price_range]['high'];
				// GENERATE STRING FOR CURRENT PRICE RANGE
				$s_price_filter .= '(('.$this->get_table_col_name($s_filter_key).' gt '.$i_low .')';
				if ( !empty($i_high) )
					$s_price_filter .= ' and ('.$this->get_table_col_name($s_filter_key).' lt '.$i_high .')';
				$s_price_filter .= ')';
			}	
			// FINISH PRICE FILTER STRING AND APPEND TO MAIN FILTER
			$s_price_filter .= ')';
			$s_filter .= $s_price_filter;
		} else 
		{
			// OTHER FILTERS
			$s_new_filter = '(';
			
			foreach( $a_filters[$s_filter_key] as $m_filter_value )
			{
				if ( strlen( $s_new_filter ) > 1 )
					$s_new_filter .= ' or ';
				
				if ( $s_filter_key == 'gender' )
					$m_filter_value = '\'' . $m_filter_value . '\'';
				
				$s_new_filter .= '('.$this->get_table_col_name($s_filter_key). ' eq '.$m_filter_value.')';
			}	
			$s_new_filter .= ')';
			if ( strlen( $s_filter ) > 0 )
				$s_filter .= ' and ';
			$s_filter .= $s_new_filter;
		
		}
	}
	
	// GET QUERY OBJECT
	$o_query = $this->o_bqw
		->Stocks()
		->Filter( $s_filter )
		->Select( $this->get_select_string() )
		->Expand( $this->get_expand_string() )
		->Skip( $i_skip )
		->Top( $i_limit )
		->IncludeTotalCount()
		->Execute()
	;
	
	// CREATE NEW ARRAY AND INSERT SIMPLIFIED RESULTS
	$a_new_results = array();
	$a_results = $o_query->Result;
	foreach ( $a_results as $a_result )
		$a_new_results[] = $this->simplify_result( $a_result );
	
	// GET TOTAL ITEM COUNT
	$a_new_results['count'] = $o_query->TotalCount();
	
	return $a_new_results;
}

function get_watch_list( $s_filter, $i_skip = 0, $i_limit = 0 )
{		
	switch ( $s_filter )
	{
		case 'men' : 
			return $this->get_filtered_watch_list( array('gender'=>array('M')), $i_skip, $i_limit );
		case 'women' : 
			return $this->get_filtered_watch_list( array('gender'=>array('W')), $i_skip, $i_limit );
		default : 
			return $this->get_filtered_watch_list( array('manufacturer'=>array($this->get_manufacturer_id($s_filter))), $i_skip, $i_limit );		
	}
	
}

function get_search_results( $s_query, $i_skip = 0, $i_limit = 0 )
{
	// IF LIMIT NOT SET, GET DEFAULT VALUE
	if ( $i_limit == 0 )
		$i_limit = $this->i_limit_default;
	
	// CREATE FILTER STRING
	$s_filter = "substringof('$s_query',TextDescription)";
	
	// GET AND RETURN SEARCH RESULTS
	$o_query = $this->o_bqw
		->Stocks()
		->Filter( $s_filter )
		->Select( $this->get_select_string() )
		->Expand( $this->get_expand_string() )
		->Skip( $i_skip )
		->Top( $i_limit )
		->IncludeTotalCount()
		->Execute()
	;
	
	// CREATE NEW ARRAY AND INSERT SIMPLIFIED RESULTS
	$a_new_results = array();
	$a_results = $o_query->Result;
	foreach ( $a_results as $a_result )
		$a_new_results[] = $this->simplify_result( $a_result );
	
	// GET TOTAL ITEM COUNT
	$a_new_results['count'] = $o_query->TotalCount();
	
	return $a_new_results;

}

function get_single_watch( $i_id )
{
	$o_results = $this->o_bqw
		->Stocks()
		->Filter( "ID eq $i_id" )
		->Expand(  $this->get_expand_string( 'complete' ) )
		->Execute()
	;
	$a_result = $this->simplify_result( $o_results->Result[0], 'complete' );
	return $a_result;
}

function get_filter_item_list( $s_item )
{
	// INIT EMPTY RESULT ARRAY AND QUERY OBJECT
	$a_result = array();
	$o_query = '';

	switch ( $s_item )
	{
		// PRICE (STATIC, SET IN get_price_ranges FUNCTION)
		case 'price' : 
		{ 
			$a_price_ranges = $this->a_price_ranges; 
			$i = 0;
			foreach( $a_price_ranges as $a_price )
			{
				if ( empty( $a_price['high'] ) )
					$a_result [$i] = '£' . $a_price['low'] . '+';
				else
					$a_result [$i] = '£' . $a_price['low'] . ' to £' . $a_price['high'];
				$i++;
			}
			break;
		}
		// GENDER (STATIC)
		case 'gender' : 
		{
			$a_result = $this->a_genders;
			break;
		}
		// MANUFACTURER
		case 'manufacturer' : 
		{
			$o_query = $this->o_bqw->Manufacturers()->Execute();	
			break;
		}
		// BRACELET
		case 'bracelet' : 
		{
			$o_query = $this->o_bqw->Bracelets()->Execute();
			break;
		}
		// MATERIAL
		case 'material' : 
		{
			$o_query = $this->o_bqw->WatchMaterials()->Execute();
			break;
		}
		// DIAL
		case 'dial' : 
		{
			$o_query = $this->o_bqw->Dials()->Execute();
			break;
		}
		
	}
	
	if ( empty( $o_query ) && empty( $a_result ) )
		return false;
	else if ( !empty( $o_query ) )
	{
		// CREATE NEW ARRAY FROM QUERY RESULT
		foreach ( $o_query->Result as $o_result )
		{ 
			
			$a_result[ $o_result->ID ] = (empty( $o_result->Name ))
				? $o_result->Description
				: $o_result->Name;
		}
	}
	
	return $a_result;

}


function expand_basic_fields( $a_new_fields )
{
	$this->a_basic_fields = array_merge( $this->a_basic_fields, $a_new_fields );
}


function expand_basic_expanded_fields( $a_new_fields )
{
	$this->a_basic_expanded_fields = array_merge( $this->a_basic_expanded_fields, $a_new_fields );
}
	
	
// *******************************************************************
// ------------------------------------------------------------------
// 				BACK-END PRIVATE HELPER FUCTIONS
// ------------------------------------------------------------------
// *******************************************************************

private function get_table_col_name( $filter_key )
{
	switch ( $filter_key )
	{
		case 'price' :
			return 'RetailPrice';
		case 'manufacturer' :
			return 'ManufacturerFK';
		case 'brand' :
			return 'ManufacturerFK';
		case 'gender' :
			return 'Gender';
		case 'bracelet' :
			return 'BraceletType';
		case 'dial' :
			return 'DialType';
	}
	
}

private function simplify_result( $o_result, $s_type = 'basic' )
{
	// OBJECT TO ARRAY
	$o_new_result = get_object_vars( $o_result );

	// GET THE LIST OF FIELDS TO SIMPLIFY
	$a_fields = ( $s_type == 'basic' )
		? $this->a_basic_expanded_fields
		: $this->a_complete_expanded_fields;
		
	// ITERATE FIELDS
	foreach ( $a_fields as $s_field ) 
	{
		// IF NAME IS EMPTY, USE DESCRIPTION
		$s_new_field = ( empty( $o_new_result[$s_field][0]->Name) )
			? $o_new_result[$s_field][0]->Description
			: $o_new_result[$s_field][0]->Name;
		$o_new_result[$s_field] = $s_new_field;
	}
	return $o_new_result;
}

private function get_select_string()
{
	$a_basic_fields = $this->a_basic_fields;
	return implode( ',', $a_basic_fields );
}

private function get_expand_string( $s_type = 'basic' )
{
	$a_expand_fields = ( $s_type == 'basic' )
		? $this->a_basic_expanded_fields
		: $this->a_complete_expanded_fields;
	
	return implode( ',', $a_expand_fields );
}

private function get_manufacturer_name( $i_id )
{
	$a_manufacturers = $this->get_filter_item_list( 'manufacturer' );
	return $a_manufacturers[ $i_id ];
}

private function get_manufacturer_id( $s_name )
{
	$a_manufacturers = $this->get_filter_item_list( 'manufacturer' );
	return array_search( $s_name, $a_manufacturers );
}
	
	
}
	
	
// *******************************************************************
// ------------------------------------------------------------------
// 						FUNCTION SHORTCUTS
// ------------------------------------------------------------------
// *******************************************************************


global $bqdb;
$bqdb = new bq_database_handler();

function bq_get_filtered_watch_list( $a_filters, $i_skip = 0, $i_limit = 0 ) 
{
	global $bqdb;
	return $bqdb->get_filtered_watch_list( $a_filters, $i_skip, $i_limit );
}
function bq_get_watch_list( $s_filter, $i_skip = 0, $i_limit = 0 )
{
	global $bqdb;
	return $bqdb->get_watch_list( $s_filter, $i_skip, $i_limit );
}
function bq_get_search_results( $s_query, $i_skip = 0, $i_limit = 0 )
{
	global $bqdb;
	return $bqdb->get_search_results( $s_query, $i_skip, $i_limit );
}
function bq_get_single_watch( $i_id )
{
	global $bqdb;
	return $bqdb->get_single_watch( $i_id );
}
function bq_get_filter_item_list( $s_item )
{
	global $bqdb;
	return $bqdb->get_filter_item_list( $s_item );
}
function bq_expand_basic_fields( $a_new_fields )
{
	global $bqdb; 
	$bqdb->expand_basic_fields( $a_new_fields );
}
function bq_expand_basic_expanded_fields( $a_new_fields )
{
	global $bqdb; 
	$bqdb->expand_basic_expanded_fields( $a_new_fields );
}

?>