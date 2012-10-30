
-------------------------------
	HOW TO USE THIS PLUGIN
-------------------------------


-- SIMPLE GET WATCH LIST --

bq_get_watch_list( query, skip, limit )

The simple get_watch_list() method uses the more advanced get_filtered_watch_list() 
method to retrieve a list of watches based on the input string.

Accepted query strings are:
'men' / 'women' - Get all men's and women's watches.
'Rolex' (or any other brand name, starting with a capital letter) - get all
	watches for that brand.

The 'skip' and 'limit' params are integers and specify the amount of records
	to be skipped, and the total amount of records returned. They are both optional, 
	to be used for paging.
	
	
	
-- COMPLETE GET WATCH LIST --

bq_get_filtered_watch_list( filter, skip, limit )

Accepts an array of filters to specify what results are to be retrieved.
Instead of a long explanation, here's an example filter that returns a list of watches
from the manufacturers with the IDs of 6 and 10, with bracelet IDs of 1 and 11 
and in the third price range (discussed later).

array(
	'price' => array(2),
	'manufacturer' => array(6, 10),
	'bracelet' => array(1, 11),
);

The other two parameters are same as above.



-- SEARCH --

bq_get_search_results ( query, skip, limit )

Retrieves search results for the given query. The search is performed on the
'TextDescription' field, since it contains all relevant information.



-- GETTING THE RESULTS --

The above 3 functions return a multi-dimensional array of watch records.

To improve performance, the returned records are limited to the ones found in the
a_basic_fields array of the class. 
If you need any other records from the DB, just call 

bq_expand_basic_fields( new_fields )

before getting the data from the DB. new_fields is an array of strings. The ones that are returned now should be enough though.

Also, the watch array by default does not contain some fields, like Manufacturer - they need to be 'expanded'. 
The list of expanded fields is contained in the a_basic_expanded_fields array.

You can expand the array of expanded fields in a similar fashion, by calling

bq_expand_basic_exoabded_fields( new_fields )

These expanded fields were originally an array of one object, but have run through
a simplification function, so now they can be easily accessed as a record of the main
array like this:

$watches[0]['Manufacturer']

To get the total count of items that match the specified filter, regardless of the limit
and skip parameters simply use

$watches['count']



-- FILTER ITEMS --

bq_get_filter_item_list( item )

This function should be used to build the list of filter items. It accepts a single
string parameter and returns an array of items to be filtered by. These items are
in a format that is accepted by the get_filtered_watch_list() method.
The following parameters are accepted:

'price' - Returns an array of price ranges in a user friendly format. The array keys
	should be used as filter values and the values are to be displayed on the front-end.
	The price ranges are defined in the a_price_ranges class variable and, if needed,
	should be modified in the constructor.
'gender' - Similar to price ranges. In case a new gender is introduced, change the
	$a_genders initialisation in the constructor.
'manufacturer', 'bracelet', 'material', 'dial'
	- These get the list of manufacturers/bracelets/materials/dials from the database.
	Similarly to gender and price, the key should be used as a filter parameter and
	and the value should be displayed on the front-end.