<?php
/**
Plugin Name: MFC No AJAX Widget
Plugin URI: http://usability-idealist.de/
Description: Custom widget to display articles filtered by a specific single or related parent category. This version does NOT use ajax, instead all the data is being inserted directly from start.
Author: Fabian Wolf
Version: 0.6
Author URI: http://usability-idealist.de/
*/
/**
 * struct:
 * - array('level' => array('label' => 'title', 'categories' => array(1,2,3,...) ) )
 * - id = level
 */

// test
/*
function list_hooked_functions($tag=false){
	 global $wp_filter;
	if ($tag) {
		$hook[$tag] = $wp_filter[$tag];
		
		if (!is_array($hook[$tag])) {
			trigger_error("Nothing found for '$tag' hook", E_USER_WARNING);
			return;
		}
	} else {
		$hook = $wp_filter;
		//ksort($hook);
	}
	
	echo '<pre>';
	foreach($hook as $tag => $priority){
		echo "<br />&gt;&gt;&gt;&gt;&gt;\t<strong>$tag</strong><br />";
		//ksort($priority);
		foreach($priority as $priority => $function) {
			echo $priority;
			foreach($function as $name => $properties) echo "\t$name<br />";
		}
	}
	echo '</pre>';
	return;
}*/


// init
$_ui_MultiCatFilter = new MultipleCategoryFilter();

// function library

	/**
	 *  Listing 1.2, recursive function example begins
	 * I used a static variable and function for reusability.
	 * actually the getTree function is not necessary
	 * to be in the class. 
	 * it can be implemented in an inline php script
	 */
	 
	 
	class TreeStructure {
		private static $tree = array();
		/**
		* return tree view structure
		*
		* @param Integer $root_id
		*/

		public static function getTree($root_id) {
			global $wpdb;
			### simple query to fetch the childnodes 
			### of the passed in $root_id
			
			/*
			 * $strSQLQuery = 'SELECT term_id AS ID, name, slug FROM ' . $wpdb->terms . ' WHERE term_id IN ( SELECT term_id FROM ' . $wpdb->term_taxonomy . ' WHERE parent = ' . intval($category_id) . ')';*/
			
			//$strSQLQuery = 'SELECT term_id AS ID, name, slug FROM ' . $wpdb->terms . ' WHERE term_id IN (SELECT term_id FROM ' . $wpdb->term_taxonomy . ' WHERE parent = ' . $root_id . ')';
			
			$strSQLQuery = 	'SELECT terms.term_id AS ID, name, slug, description, parent AS parent_id FROM ' . $wpdb->terms . ' AS terms ' .
							'LEFT JOIN ' .$wpdb->term_taxonomy. ' AS taxonomies ' .
							'ON terms.term_id = taxonomies.term_id ' .
							'WHERE taxonomies.parent = ' . (int) $root_id;
			
			/*
			$sql = 'SELECT id as node_id, name FROM '
				. 'parent_child_tree where parent_node_id = '
				. (int)$root_id ;*/

			### fetch result sets into array $results using 
			### a dbaccess layer class (see my other post for 
			### the db access adstraction layer class      
			//$results = DbAccess::getInstance()->query($sql);
			$results = $wpdb->get_results( $strSQLQuery, ARRAY_A );
			//echo '<pre>debug getTree: ' . print_r(array('results' => $results, 'query' => $strSQLQuery), true) . '</pre>';

			### loop through result sets
			foreach($results as $result) {
			## if the node is not found in the static array variable
			## then, we will store it in the self::$tree
				if(empty(self::$tree['ID']) || 
				!in_array($result['ID'], self::$tree['ID'])) {
						
					### assignment
					self::$tree['ID'][] = $result['ID'];
					self::$tree['name'][] = $result['name'];
					self::$tree['slug'][] = $result['slug'];
					self::$tree['cat_level'][0][$result['ID']] = $result;
					
					self::$tree['parent_id'][] = $result[$root_id];
					self::$tree['level'][] = 0;
					
					### call the getChildren function
					### which contains the recursive function call
					self::getChildren($result['ID'], 1);
				}
			}
			return self::$tree;
		}


		/**
		* recursive function returning children of the passed nodeId
		*
		* @param Integer $id_camp_group
		* @param level $level this one will denote the level of depth
		*/
		public static function getChildren($child_node_id, $level) {
			global $wpdb;
			
			$level++;
			/*
			$sql = 'SELECT id as node_id, name FROM '
				. 'parent_child_tree where parent_node_id = '
				. (int)$id_node;*/
				
			//$strSQLQuery = 'SELECT term_id AS ID, name, slug FROM ' . $wpdb->terms . ' WHERE term_id IN (SELECT term_id FROM ' . $wpdb->term_taxonomy . ' WHERE parent = ' . $child_node_id . ')';
			$strSQLQuery = 	'SELECT terms.term_id AS ID, name, slug, description, parent AS parent_id FROM ' . $wpdb->terms . ' AS terms ' .
							'LEFT JOIN ' .$wpdb->term_taxonomy. ' AS taxonomies ' .
							'ON terms.term_id = taxonomies.term_id ' .
							'WHERE taxonomies.parent = ' . (int) $child_node_id;


			//$child_results = DbAccess::getInstance()->query($sql);
			$child_results = $wpdb->get_results( $strSQLQuery, ARRAY_A );

			if(!empty($child_results)) {
				foreach($child_results as $child) {
					
					if(empty(self::$tree['ID']) || 
					!in_array($child['ID'], self::$tree['ID'])) {
						self::$tree['ID'][] = $child['ID'];
						//self::$tree['data'] = $child;
						self::$tree['cat_level'][$level-1][$child['ID']] = $child;
						self::$tree['name'][] = $child['name'];
						self::$tree['slug'][] = $child['slug'];
						self::$tree['parent_id'][] = $child['ID'];
						self::$tree['level'][] = $level;
						// recursive calls here
						self::getChildren($child['ID'], $level);
					}
				}
			}
		  
			
			return;
		}
	}


/*
### to use the class
$tree_structure = TreeStructure::getTree(1);
$cnt = 0;
### loop thru the tree structure as following
### this will handle dynamically on the tree structure
foreach($tree_structure['node_id'] as $node) {
   ### processing view logic here
   ### each $node
   ### parent_id : $tree_structure['parent_id'][$cnt]
   ### name : $tree_structure['name'][$cnt]
   ### level : $tree_structure['level'][$cnt]
   $cnt++;
}
#### recursive function example ends ####
*/

// classes

class MultipleCategoryFilter {
	var $pluginName = 'Multiple Category Filter',
		$pluginVersion = 0.6,
		$pluginPrefix = '_ui_multiple_category_filter_noajax_';
		
	function __construct() {	
		// define constants
		
		define('MCF_COOKIE_KEY', $this->pluginPrefix . 'category');
		define('MCF_PREFIX', $this->pluginPrefix);
	
		// init for redirection
		add_action('get_header', array(&$this, 'redirect_category_selection') );
		
		// register widget
		add_action( 'widgets_init', array(&$this, 'init_widget' ) );
		
		// scripting stuff
		
		add_action('wp_enqueue_scripts', array(&$this, 'init_frontend_js' ) );	
	
	
		//add_action('', array(&$this, 'redirect_category_selection') );
	}
	
	/**
	 * Automatically redirects to specific category if in category templates
	 * 
	 * NOTE: is_category is already available at this point!
	 */
	
	/*
	function get_user_defined_vars($vars) {
		return array_diff($vars, array(array()));
	}*/
	
	public function redirect_category_selection() {
		global $wp_query, $wp, $cat, $id, $posts;
		
			
		$arrQuery = array(
		
			'wp' => &$wp,
			'wp_query' => &$wp_query,
			'wp_query->query_vars' => $wp_query->query_vars,
			'cookie' => $_COOKIE[MCF_COOKIE_KEY],
			'is_category' => is_category() ? 'yep' : 'nope',
		);
		//list_hooked_functions();
		
		//exit('<pre>' . print_r( $arrQuery, true) . '</pre>');
		
		
		/*if(is_category() && $wp_query->query_vars['cat'] != 4 ) {// test redirect
			wp_safe_redirect('?cat=4');
			exit;
		}*/
		
		
		
		if(is_home() && !empty($_COOKIE[MCF_COOKIE_KEY]) && $wp_query->query_vars['cat'] != $_COOKIE[MCF_COOKIE_KEY] ) {
			// check if this category actually does exist
			$iRedirCategoryID = (int)$_COOKIE[MCF_COOKIE_KEY];
			/**
			 * NOTE: fires ONLY when we are in the correct category - do NOT get lured into this as showing the WHOLE categories!
			 */
			
			//if( !empty($wp_query->query_vars['category__in']) != false && in_array( $iRedirCategoryID, $wp_query->query_vars['category__in']) != false ) {
			$result = get_category( $iRedirCategoryID );
			$arrQuery['result'] = $result;
			
			
			//exit('<pre>' . print_r( $arrQuery, true) . '</pre>');
			
			
			if(!empty($result) ) {
			// redirect to the category
				//exit('<pre>' . print_r( $arrQuery, true) . '</pre>');
			/**
			 * @see http://wordpress.org/support/topic/rewriting-wp_redirect-problem
			 */
			
			
			//if ($wp_query->query_vars['post_type']!='yclad') return $query;
			//if ($query->query_vars['yclads_subscription']) return $query;
				wp_redirect( esc_url( get_category_link( $iRedirCategoryID ) ) );
				
				//wp_safe_redirect('?cat=4');
				exit;
				//header('Location: ' . esc_url( get_category_link( $iRedirCategoryID ) ) );
				//exit;
			}
			return;
		}
	}
	
	
	public function init_widget() {
		// register widget
		register_widget('_ui_MultipleCategoryFilterWidget');
	}
	
	public function init_frontend_js() {
		// adds ajax communication
		
		wp_enqueue_script($this->pluginPrefix . 'lib', plugin_dir_url(__FILE__) .'mcf-lib.js', array('jquery') );
		
		// embed the javascript file that makes the AJAX request
		//wp_enqueue_script( 'my-ajax-request', plugin_dir_url( __FILE__ ) . 'js/ajax.js', array( 'jquery' ) );
 
		// declare the URL to the file that handles the AJAX request (wp-admin/admin-ajax.php)
		//wp_localize_script( $this->pluginPrefix . 'lib', 'mcf_ajax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
	}
	
}

// widget

class _ui_MultipleCategoryFilterWidget extends WP_Widget {

	function __construct() {
		$widget_ops = array( 'description' => 'Custom widget to display articles filtered by a specific single or related parent category.' );
		$control_ops = array('width' => 400);
		parent::__construct( '_ui_MultipleCategoryFilterWidget', __('Multi-Kategorie-Artikelfilter'), $widget_ops, $control_ops );		
	}
	
	function get_category_parents_hackish( $id, $visited = array() ) {
		$chain = '';
		$delimiter = '|';
		
		$parent = &get_category( $id );
		if ( is_wp_error( $parent ) )
			return $parent;


		if ( $parent->parent && ( $parent->parent != $parent->term_id ) && !in_array( $parent->parent, $visited ) ) {
			$visited[] = $parent->parent;
			//$chain = $this->get_category_parents_hackish( $parent->parent, $chain, $visited, $count );
			$chain .= $this->get_category_parents_hackish( $parent->parent, $visited );
		}

		$chain .= $parent->term_id.$delimiter;
		
		return $chain;
	}
	
	function get_category_parents_array( $id ) {
		$return = array();
		
		$result = $this->get_category_parents_hackish( $id );
		if(is_string($result) != false) {
		
			if(!empty($result) && stripos($result, '|') !== false ) {
				$return = explode('|', substr($result, 0, strlen($result)-2) );
				$iLastKey = sizeof($return)-1;
				
				if( empty($return[$iLastKey]) != false) { // drop empty key
					unset($return[$iLastKey]);
				}
			}
		}
		
		//$return[] = array('original_data' => $result);
		
		return $return;
	}
	
	/**
	 * Cached category tree fetching
	 * 
	 * @param int $category_id		The root ID to built the category tree from.
	 * @param int $cache_timeout	Cache timeout in hours, after which the tree is being rebuild directly from the DB. Defaults to 48 hours.
	 * @return arrray $tree			Always returns array. Empty array if nothing found, or else the found categories.
	 */
	
	public function fetch_category_tree( $category_id = 0, $cache_timeout = 48 ) {
		global $wpdb;
		$return = array();
		$iCacheTimeout = (60 * 60 * $cache_timeout);
		
		if(!empty($category_id)) {
			// check if data has already been cached
			$result = get_option( MCF_PREFIX . 'category_tree', false);
			$iCurrentCacheTimestamp = get_option(MCF_PREFIX  . 'category_tree_expires', time() );
			
			/**
			 * @see http://de3.php.net/empty#refsect1-function.empty-returnvalues
			 */
			
			if ( !empty($result) && $iCurrentCacheTimestamp > time() ) { 
				$return = $result;
				echo '<!-- built from option -->';
			} else {
				// built category tree + cache data afterwards
				$return = TreeStructure::getTree( $category_id );
				echo '<!-- built from direct parsing -->';
				
				if(!empty($return)) {
					update_option(MCF_PREFIX . 'category_tree', $return );
					update_option(MCF_PREFIX . 'category_tree_expires', time() + $iCacheTimeout );
					
					// check if transient has actually been saved
				}
			}
		}
		
		return $return;
	}
	
	/*
	function get_category_parents_hackish( $id, $chain = array(), $visited = array(), $count = 0 ) {
		$delimiter = '|';
		
		$parent = &get_category( $id );
		if ( is_wp_error( $parent ) )
			return $parent;


		if ( $parent->parent && ( $parent->parent != $parent->term_id ) && !in_array( $parent->parent, $visited ) ) {
			$visited[] = $parent->parent;
			$count++;
			$chain[$count] = $this->get_category_parents_hackish( $parent->parent, $chain, $visited, $count );
			//$chain .= $this->get_category_parents_hackish( $parent->parent, $visited );
		}

		$chain[0] = $parent->term_id;

		//$chain .= $parent->term_id.$delimiter;
		
		return $chain;
	}*/
	
	/**
	 * struct:
	 * - category_id is the container for all other categories
	 * - the container category is _never_ being displayed
	 * - thus, the direct children of category_id are the top categories and thus the first select box
	 * - depth sets both the maximum number of select boxes to create AND to populate
	 * - the submit button is available regardless of the currently selected category
	 * 
	 * + JS: 
	 * 
	 * 	- the currently selected category is determined cycling through each (chained) select and saving the last not-empty value into a global current_cat_id
	 * 	- which by itself is put into front of location.href (?cat=); DO NOT forget removing the class-placeholder-shit from jquery.remoteChain
	 *  - also see tests/form-value-overwrite.php and @link http://codex.wordpress.org/Function_Reference/wp_dropdown_categories#Dropdown_without_a_Submit_Button_using_JavaScript
	 */
	
	
	function widget($args, $instance) {
		global $wp_query;
		timer_start();
		
		//global $_ui_ligaArtikelFilter;
		
		$title = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );
		$button_text = ( !empty($instance['button_text']) ? $instance['button_text'] : 'Filtern');
		
		//$custom_css = $instance['custom_css'];
		$category_id = $instance['category_id']; // top id
		$depth = $instance['depth'];

		if( empty($instance['depth']) != false) { // empty depth = no information on the categoryy tree depth
			echo 'No category tree depth given.';
			return 2;
		} 		
		//$depth = !empty($instance['depth']) ? $instance['depth'] : 5;
		
		if(!empty($instance['level_labels']) ) { // build label list
			if(stripos($instance['level_labels'], ';') !== false && $depth > 1) {
				$arrLabels = explode(';', $instance['level_labels']);
			} else {
				$arrLabels = array($instance['level_labels']);
			}
		}	
		
		
			
		/**
		 * NOTE: Fetch cat not from $_GET, but wp_query!)
		 */
		if( !empty($wp_query->query_vars['cat']) ) {
			$current_cat = $wp_query->query_vars['cat'];			
		}
		
		/**
		 * if not set, fetch from cookie
		 */
		 
		if( !empty($_COOKIE[MCF_COOKIE_KEY]) ) {
			$current_cat = $_COOKIE[MCF_COOKIE_KEY];
		}
		
		if(isset($current_cat) != false) {
			$arrCurCatParents = $this->get_category_parents_array( (int)$current_cat );
		}
		
		
		
		
		//$cache_timeout = (!empty($instance['cache_timeout']) ? $instance['cache_timeout'] : 10); // in minutes


		// widget_output_main.start
			echo $args['before_widget'];
			
			
			if ( !empty($title) ) {
				echo $args['before_title'] . $title . $args['after_title'];
			}
			
			if(!empty($category_id) ) {
				
				// there is NO such thing as remore = outworldish sperm; the one related is HIGHT PRIES MASTER
				/** NOTE: uh... yeah .. whatever. don't drink and code! :D */

				
				/**
				 * TODO: Implement caching via transients API
				 */
				
				// check if transient is set
				$arrChildParentTree = $this->fetch_category_tree( $category_id );
				
				
				//$arrChildParentTree = TreeStructure::getTree( $category_id );
				$arrCategoryTree = $arrChildParentTree['cat_level'];
				
				// after-sql processing
				
				
				$arrTreeLevel = array_keys( $arrCategoryTree );
				//$iMaxLevel = $arrTreeLevel[sizeof($arrTreeLevel)-1]+1;
				
				$iMaxLevel = sizeof($arrTreeLevel);
				$iMaxDepth = ($depth > $iMaxLevel) ? $iMaxLevel : $depth;
			
				//$arrDebug = array($_SERVER, 'tree' => $arrCategoryTree, 'current_parents' => $arrCurCatParents, 'query_vars->cat' => $wp_query->query_vars['cat'], 'cookie' => $_COOKIE[MCF_COOKIE_KEY] );
			
				/*
				if(isset($arrDebug) != false && stripos($_SERVER['SERVER_NAME'], 'debtoo') !== false) {?>
			<pre style="width: 600px; height: 800px; overflow: scroll; z-index: 1000; position: absolute; left: 50px; top: 600px"><?php print_r( $arrDebug ); ?></pre>
			<?php 
				}*/
				
				?>
			
			
			<form method="get" action="" id="<?php echo substr($this->id_base, 1); ?>-form">
				
			<?php for($n = 0; $n < $iMaxDepth; $n++) {
				$strHTMLID = substr($this->id_base, 1) . '-cat' . $n;
				//echo 'strHTMLID = ' . $strHTMLID . ', ';
				
				if($n+1 < $depth) { // prepare chaining
				
					//echo $n+1 . ' < ' . $depth . ', ';
					
					$arrChain[$strHTMLID] = substr($this->id_base, 1) . '-cat' . ($n+1);
					//echo 'arrChain[' .$strHTMLID.'] = ' .$arrChain[$strHTMLID];
				}
				
				$strBefore = ''; $strAfter = '';
				
				if(isset($arrLabels[$n]) != false) {
					$strBefore = '<label><span class="mcf-category-field">'. trim($arrLabels[$n]).'</span> ';
					$strAfter = '</label>';
				}
				
				echo $strBefore; 
				
				?>
				
				<div class="category-select-block">
					
					<select name="category" size="1" id="<?php echo $strHTMLID; ?>">
						<option value=""><?php _e('Select Category'); ?></option>
				<?php foreach($arrCategoryTree[$n] as $cat_ID => $singleCat) {
						$strSelected = '';
						/**
						 * NOTE: it has to be the _cat_ID_ we have to look for in the parents array, NOT the current_cat!
						 */
						//if($cat_ID == $current_cat || in_array($cat_ID, $arrCurCatParents) != false ) {
						if( isset($arrCurCatParents) != false && in_array($cat_ID, $arrCurCatParents) != false ) {
							$strSelected = ' selected="selected"';
						}
						
						if( $current_cat == $cat_ID && empty($strSelected) != false) {
							$strSelected = ' selected="selected"';
						}
						
					
					
					 ?>
						<option value="catid_<?php echo $cat_ID; ?>"<?php 
						if($n > 0) {
							?> class="catid_<?php echo $singleCat['parent_id']; ?>"<?php
						}
						
						echo $strSelected;
						
						
						/*
						if(isset($current_cat) != false && ($current_cat == $cat_ID || in_array($current_cat, $arrCurCatParents) != false ) ) {
							echo ' selected="selected"';
						}*/
						
						?>><?php
						
						echo $singleCat['name']; ?></option>
				<?php } ?>
					</select>
				</div>
				
				<?php echo $strAfter; 
				/* flush(); */
				?>
			<?php } ?>
					
				<p class="form-controls">
					<button type="submit" class="category-tree-button button-submit"><?php echo $button_text; ?></button> 
					<label class="mcf-save-container"><input type="checkbox" name="save_category" class="mcf-filter-save" value="<?php
					if(!empty($_COOKIE[MCF_COOKIE_KEY])) {
						echo $_COOKIE[MCF_COOKIE_KEY] . '" checked="checked';
					} ?>" /> <span>Auswahl speichern</span></label>
				</p>
				
				<?php /** NOTE: Probably obsolete
				<input type="hidden" name="cat" value="" />*/ ?>
				
			
			</form>
			<?php /*flush();*/
			
			
			?>
			<script type="text/javascript">
				jQuery(function() {
					// init global variables
					window.submitCatID = '';
					window.defaultCookieExpire = 7;
					
					
					// init chained selects
				<?php 
				if(!empty($arrChain) ) {
					//foreach($arrChain as $strKeyFrom => $strChainTo) {
					foreach($arrChain as $strChainTo => $strKeyFrom) { // reverse => second element is assigned to FIRST element
						 ?>
					
					jQuery('#<?php echo $strKeyFrom; ?>').chainedTo('#<?php echo $strChainTo; ?>');
				<?php }
				} ?>
				
					/**
					 * Fires when checkbox is clicked
					 */
					
					
					jQuery('#<?php echo substr($this->id_base, 1); ?>-form input.mcf-filter-save').click(function() {
						var myCheckbox = jQuery(this).attr('class');
						//console.log(myCheckbox + ' clicked');
						
						// detect the correct current category id
						jQuery('#<?php echo substr($this->id_base, 1); ?>-form select[name=category]').each(function() {
							
							// "last man standing" / fallthrough = current value overwrites the one before
							if( this.value.indexOf('catid_') != -1 ) { // not empty 
								window.submitCatID = str_replace('catid_', '', jQuery(this).val() )
							}
						})
						
						
						// save / remove settings via cookie
						
						if(jQuery(this).attr('checked') != 'checked') {
							//console.log(myCheckbox + ' UNchecked');
							
							if(jQuery.cookie('<?php echo MCF_COOKIE_KEY; ?>')) {
								//console.log('cookie DOES exist');
								
								jQuery.cookie('<?php echo MCF_COOKIE_KEY; ?>', null, {path:'/', expires: window.defaultCookieExpire})
							}
						} else {
							//console.log(myCheckbox + ' checked');
							if(window.submitCatID != '') {
								jQuery.cookie('<?php echo MCF_COOKIE_KEY; ?>', window.submitCatID, {path:'/', expires: window.defaultCookieExpire});
							}
						}
					})
					

					
					/**
					 * Fires at form submit
					 */
					
					jQuery('#<?php echo substr($this->id_base, 1); ?>-form').submit(function() {
						that = this;						
						
						jQuery(this).find('select[name=category]').each(function() {
							if(jQuery(this).val() ) { // not empty
								window.submitCatID = str_replace('catid_', '', jQuery(this).val() )
							}
							
					
						})

						
						if(window.submitCatID != '') {
							//jQuery(that).find('input[name=cat_id]').val( window.submitCatID )
						
							
							// set/check cookie/checked field
							if(jQuery('#<?php echo substr($this->id_base, 1); ?>-form input.mcf-filter-save').attr('checked') == 'checked') {
								// overwrite cookie
								if(jQuery.cookie('<?php echo MCF_COOKIE_KEY; ?>') != window.submitCatID) {
									jQuery.cookie('<?php echo MCF_COOKIE_KEY; ?>', window.submitCatID, {path:'/', expires: window.defaultCookieExpire});
								}
								
							} else { // check if cookie is set, and if so, remove it
								jQuery.cookie('<?php echo MCF_COOKIE_KEY; ?>', null, {path:'/', expires: window.defaultCookieExpire});
							}
							
							
							// ... then do the redirect
							window.location.href = '<?php echo $_SERVER['PHP_SELF']; ?>?cat=' + window.submitCatID;

						}
			
						return false

					})
					
					
				}) // end of jQuery
				
				
				
			
			</script>
			<?php
			} // no widget content if no category_id is given!

			echo $args['after_widget'];	
		
			echo '<!-- widget proccessed in ' . timer_stop() . ' seconds -->';
		
			// widget_output_main.end
	

			
			// custom css.end
	
	}

	function update( $new_instance, $old_instance ) {
		$instance['title'] = strip_tags( stripslashes($new_instance['title']) );
		$instance['category_id'] = $new_instance['category_id'];
		$instance['depth'] = $new_instance['depth'] > 0 ? 5 : $new_instance['depth'];
		$instance['button_text'] = $new_instance['button_text'];
					
		// depth is ALWAYS bigger than zero!
		if( (stripos($new_instance['level_labels'], ';') !== false && $instance['depth'] > 1 ) != false 
			|| ($instance['depth'] == 1 && stripos($new_instance['level_labels'], ';') === false) != false ) {
			$instance['level_labels'] = $new_instance['level_labels'];
		}
		
		

		return $instance;
	}

	function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : '';
		$button_text = $instance['button_text'];
		$category_id = $instance['category_id'];
		
		$depth = $instance['depth'];
		$level_labels = $instance['level_labels'];
	
		?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:') ?></label>
			<input type="text" class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $title; ?>" />
		</p>
		
		<!-- category_id -->
		<p>
			<label for="<?php echo $this->get_field_id('category_id'); ?>"><?php _e('Category:') ?></label>
			<?php 
			$arrCatParams = array( 'name' => $this->get_field_name('category_id'), 'id' => $this->get_field_id('category_id'), 'hierarchical' => true, 'show_option_none' => 'Select category!', 'depth' => 10 );
			
			if(!empty($category_id)) {
				$arrCatParams['selected'] = $category_id;
			}
			
			wp_dropdown_categories( $arrCatParams ); ?>
		
		</p>
		
		<!-- depth -->
		<p>
			<label for="<?php echo $this->get_field_id('depth'); ?>"><?php _e('Depth:') ?></label>
			<input type="text" class="widefat" id="<?php echo $this->get_field_id('depth'); ?>" name="<?php echo $this->get_field_name('depth'); ?>" value="<?php echo $depth; ?>" /><br />
			<small></small>
		</p>
		
		
		<!-- level_labels -->
		<p>
			<label for="<?php echo $this->get_field_id('level_labels'); ?>"><?php _e('Labels:') ?></label>
			<textarea class="widefat" id="<?php echo $this->get_field_id('level_labels'); ?>" name="<?php echo $this->get_field_name('level_labels'); ?>" cols="40" rows="3"><?php echo $level_labels; ?></textarea><br />
			<small>Label per category level = depth. Their number must match the depth value in the field above, or else they are not going to be saved. Seperate each label with a semicolon.</small>
		</p>
		
		<!-- button_text -->
		<p>
			<label for="<?php echo $this->get_field_id('button_text'); ?>"><?php _e('Submit button text:') ?></label>
			<input type="text" class="widefat" id="<?php echo $this->get_field_id('button_text'); ?>" name="<?php echo $this->get_field_name('button_text'); ?>" value="<?php echo $button_text; ?>" /><br />
			<small>Text of the submit button. If none is set here, the default text (&quot;Filtern&quot;) will be used.</small>
		</p>
		
		<?php
	}
}


