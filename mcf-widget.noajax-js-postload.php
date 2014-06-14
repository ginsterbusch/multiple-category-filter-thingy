<?php
/**
Plugin Name: MFC No AJAX Postload Widget
Plugin URI: https://github.com/ginsterbusch/multiple-category-filter-thingy
Description: Custom widget to display articles filtered by a specific single or related parent category. This version does NOT use ajax, instead all the data is being inserted directly from start. Also JS data is all being load / processed seperatedly.
Author: Fabian Wolf
Version: 0.8.1
Author URI: http://usability-idealist.de/
*/

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
	 
if( !class_exists('TreeStructure') ) {
	
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

} // endif 

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
		$pluginVersion = 0.8,
		$pluginPrefix = '_ui_multiple_category_filter_noajax_',
		$widgetIdentifier = '_ui_MultipleCategoryFilterWidget';
		
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
		
		/**
		 * NOTE: Fixes the misbehaviour of is_admin() - is_admin() also returns true if its being called in admin-ajax, although the file is being used for frontend ajax as well. Of corpse, we don't want exposure of admin-only stuff to the rest of the world, would we? ;)
		 */
		if(is_admin() && basename(__FILE__, '.php') != 'admin-ajax' ) {
			add_action('admin_menu', array(&$this, 'add_admin_pages') );
			//add_action('admin_enqueue_scripts', array(&$this, 'init_admin_js') );
		}
	
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
		
			'wp' => $wp,
			'wp_query' => $wp_query,
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
			$iRedirCategoryID = intval( $_COOKIE[MCF_COOKIE_KEY] );
			/**
			 * NOTE: fires ONLY when we are in the correct category - do NOT get lured into this as showing the WHOLE categories!
			 */
			
			//if( !empty($wp_query->query_vars['category__in']) != false && in_array( $iRedirCategoryID, $wp_query->query_vars['category__in']) != false ) {
			$result = get_category( $iRedirCategoryID );
			$arrQuery['result'] = $result; // just for debugging purposes
			
			
			//exit('<pre>' . print_r( $arrQuery, true) . '</pre>');
			
			
			if( !empty($result) ) {
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
	
	/**
	 * Mostly being used for calling the AJAX function which regenerates the category tree
	 */
	
	public function init_admin_js() {
		wp_enqueue_script( $this->pluginPrefix . 'admin_lib', plugin_dir_url(__FILE__) . 'admin.js', array('jquery') );
		
		/**
		 * Not required - wp is doing that already => var is "ajaxurl"
		 */
		
		//wp_localize_script( $this->pluginPrefix . 'admin_lib', 'mcf_ajax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
	}
	
	public function init_frontend_js() {
		// adds ajax communication
		
		wp_enqueue_script($this->pluginPrefix . 'lib', plugin_dir_url(__FILE__) .'mcf-postload.js', array('jquery') );
		
		// embed the javascript file that makes the AJAX request
		//wp_enqueue_script( 'my-ajax-request', plugin_dir_url( __FILE__ ) . 'js/ajax.js', array( 'jquery' ) );
 
		// declare the URL to the file that handles the AJAX request (wp-admin/admin-ajax.php)
		//wp_localize_script( $this->pluginPrefix . 'lib', 'mcf_ajax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
	}
	
	public function add_admin_pages() {
		add_theme_page( $this->pluginName, $this->pluginName, 'manage_options', $this->pluginPrefix . 'admin', array(&$this, 'admin_page' ));
	}
	
	
	public function admin_page() {
		// init
		$arrWidgets = get_option( 'widget_' . $this->widgetIdentifier );
		if(isset($arrWidgets['_multiwidget']) != false) {
			unset($arrWidgets['_multiwidget']);
		}
		
		// action handling
		switch($_POST['action']) {
			case 'refresh':
			case 'refresh_category_tree':
				$msg = array('type' => 'error', 'message' => 'Fehler: Kein(e) Widget(s) ausgewählt.' );
				
				echo '<h2>Parsing Category Trees:</h2>'; flush();
				
				foreach($arrWidgets as $iWidgetID => $iWidgetItem) {
				
			
					if( !empty($arrWidgets[$iWidgetID]['category_id']) ) {
						$parsedTree = TreeStructure::getTree( $arrWidgets[$iWidgetID]['category_id'] );
						$arrWidgets[$iWidgetID]['category_tree'] = $parsedTree;
						$arrWidgets[$iWidgetID]['category_tree_expiration'] = time() + ($arrWidgets[$iWidgetID]['cache_timeout'] * 60 * 60);
						echo '<br />Parsed Widget ID ' . $iWidgetID . '.'; flush();
					}
					
				}
				
				// update all widgets
				$bUpdateResult = update_option( 'widget_' . $this->widgetIdentifier, $arrWidgets);
				
				if($bUpdateResult != false) {
					$msg = array(
						'type' => 'updated', 'message' => (sizeof($arrWidgets) > 1 ? sizeof($arrWidgets) . ' Widget-Kategoriebäume' : 'Widget-Kategoriebaum') .' aktualisiert.'
					);
				} else {
					$msg = array('message' => __("Error: Could not update widget settings. Either the category trees didn't change (needless refresh) or an unknown error occured while writing the settings into the database.", 'mcf_widget');
				}
				
				// reset
				unset($iWidgetID);
				
				
				break;
			case 'update':
			case 'update_settings':
			
				foreach($_POST['widget_cache_timeout'] as $iFormWidgetID => $iFormCacheTimeout) {
					if( $arrWidgets[$iFormWidgetID]['cache_timeout'] != $iFormCacheTimeout && is_numeric($iFormCacheTimeout) != false) {
						$arrWidgets[$iFormWidgetID]['cache_timeout'] = $iFormCacheTimeout;
					}
	
				}
				
				$bUpdateResult = update_option( 'widget_' . $this->widgetIdentifier, $arrWidgets);
				
				if($bUpdateResult != false) {
					$msg = array(
						'type' => 'updated', 
						'message' => sizeof($arrWidgets) . ' Widget(s) aktualisiert.'
					);
				} else {
					$msg = array(
						'type' => 'error',
						'message' => __("Error: Could not update widget settings. Either the category trees didn't change (needless refresh) or an unknown error occured while writing the settings into the database.", 'mcf_widget');
					);
				}
				break;
		}
		

		
		
		// main
		
		?>
		<div class="wrap">
			<h2><?php echo $this->pluginName; ?>:</h2>
			
			<?php if( isset($msg) != false) { ?>
				<div id="message" class="message<?php echo (!empty($msg['type']) ? ' ' .$msg['type'] : ''); ?>"><p><strong><?php echo $msg['message']; ?></strong></p></div>
			<?php } ?>
		
			<h3><?php_e('Active Widgets:', 'mcf_widget'); ?></h3>
			
			
			<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=<?php echo $_GET['page']; ?>" id="form-category-tree-refresh">
			
				<input type="hidden" name="action" value="refresh_category_tree" />
				
				<?php submit_button(__('Update category trees', 'mcf_widget'), 'secondary' ); ?>
				
				<p class="description"><?php _e('<strong>Note:</strong> The regeneration of the category trees may take some time, so please refrain from refreshing the page!', 'mcf_widget'); ?></p>
				
			</form>	
			
			<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=<?php echo $_GET['page']; ?>" id="form-main">
				<input type="hidden" name="action" value="update" />
				
				<?php submit_button(__('Save changes', 'mcf_widget'), 'primary' ); ?>
				
				<table class="widefat" style="width: 70%">
					<thead>
						<tr>
							<th class="manage-column column-id" id="id" scope="col"><?php _e('ID', 'mcf_widget'); ?></th>
							<th class="manage-column column-category" scope="col"><?php _e('Root category', 'mcf_widget'); ?></th>
							<th class="manage-column column-depth" id="depth" scope="col"><?php _e('Tree depth (levels', 'mcf_widget'); ?></th>
							<th class="manage-column column-date" id="date" scope="col"><?php _e('Next update at', 'mcf_widget'); ?></th>
							<th class="manage-column column-date" id="date" scope="col"><?php _e('Cache-Timeout (in hours)', 'mcf_widget'); ?></th>
						</tr>
					</thead>
					<tfoot>
						<tr>
							<!--<th class="manage-column column-cb check-column" id="cb" scope="col"><input type="checkbox" /></th>-->
							<th class="manage-column column-id" id="id" scope="col"><?php _e('ID', 'mcf_widget'); ?></th>
							<th class="manage-column column-category" scope="col"><?php _e('Root category', 'mcf_widget'); ?></th>
							<th class="manage-column column-depth" id="depth" scope="col"><?php _e('Tree depth (levels', 'mcf_widget'); ?></th>
							<th class="manage-column column-date" id="date" scope="col"><?php _e('Next update at', 'mcf_widget'); ?></th>
							<th class="manage-column column-date" id="date" scope="col"><?php _e('Cache-Timeout (in hours)', 'mcf_widget'); ?></th>
						</tr>
					</tfoot>
					<tbody>
					<?php 
					if(!empty($arrWidgets) ) {
						$iRowCount = 0;
						foreach($arrWidgets as $iWidgetID => $widgetData) {
							
							$iRowCount++; ?>
						<tr class="<?php echo ($iRowCount % 2) ? 'alt' : ''; ?>">
							<!--<th class="column-cb check-column" scope="row"><input type="checkbox" name="widget_id[]" value="<?php echo $iWidgetID; ?>" /></th>-->
							<td class="id column-id"><?php echo $iWidgetID; ?></td>
							<td class="category column-category"><?php echo get_cat_name( $widgetData['category_id'] ) . ' (#' . $widgetData['category_id'] . ')'; ?></td>
							<td class="depth column-depth"><?php echo $widgetData['depth']; ?></td>
							<td class="date column-date"><?php echo date('Y-m-d H:i:s', $widgetData['category_tree_expiration']); ?></td>
							<td class="cache-timeout column-cache-timeout"><input type="text" name="widget_cache_timeout[<?php echo $iWidgetID; ?>]" value="<?php echo (!empty($widgetData['cache_timeout']) ? $widgetData['cache_timeout'] : ''); ?>" size="3" maxlength="5" data-value="<?php echo (!empty($widgetData['cache_timeout']) ? $widgetData['cache_timeout'] : ''); ?>" /></td>
						</tr>
					<?php }
					} ?>
					</tbody>
				</table>
				
			</form>
		</div>
		
		<?php
	}
	
}

// widget

class _ui_MultipleCategoryFilterWidget extends WP_Widget {
	function __construct() {
		$strIdentifier = '_ui_MultipleCategoryFilterWidget';
		
		$widget_ops = array( 'description' => 'Custom widget to display articles filtered by a specific single or related parent category.' );
		$control_ops = array('width' => 400);
		
		parent::__construct( $strIdentifier, __('Multi-Kategorie-Artikelfilter'), $widget_ops, $control_ops );
		$this->strIdentifier = $strIdentifier;
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
	
	
	public function get_widget_options() {
		$return = array();
		
		$arrWidgets = get_option($this->option_name, array());
		if(!empty($arrWidgets) && isset( $arrWidgets[$this->number]) != false) {
			$return = $arrWidgets[$this->number];
		}
		
		return $return;
	}
	
	public function get_widget_option( $strFieldName = null, $default = false ) {
		$return = $default;
		
		$arrWidgets = get_option($this->option_name, array());
		
		if(!empty($strFieldName) && array_key_exists($strFieldName, $arrWidgets[$this->number]) != false) {
			$return = $arrWidgets[$this->number][$strFieldName];
		}

		return $return;
	}
	
	public function set_widget_option( $strFieldName, $value) {
		$return = false;
		
		// fetch all widgets
		$arrAllWidgets = get_option($this->option_name, array());
		
		if(!empty($arrAllWidgets)) {
		
			// fetch current options
			$arrCurrentOptions = $this->get_widget_options();
			
			// update if existing / add new option if not existing
			$arrCurrentOptions[$strFieldName] = $value;
			
			// update widget options in widget array
			$arrAllWidgets[$this->number] = $arrCurrentOptions;
			
			// update all widgets by update_option
			$return = update_option( $this->option_name, $arrAllWidgets);
		}
		
		return $return;
	}
	
	public function delete_widget_option( $strFieldName ) {
		$return = false;
		
		// fetch all widgets
		$arrAllWidgets = get_option($this->option_name, array());
		
		if(!empty($arrAllWidgets)) {
		
			// fetch current options
			$arrCurrentOptions = $this->get_widget_options();
			
			// update if existing / add new option if not existing
			unset($arrCurrentOptions[$strFieldName]);
			
			// update widget options in widget array
			$arrAllWidgets[$this->number] = $arrCurrentOptions;
			
			// update all widgets by update_option
			$return = update_option( $this->option_name, $arrAllWidgets);
		}
		
		return $return;
	}
	
	/**
	 * Cached category tree fetching
	 * 
	 * @param int $category_id		The root ID to built the category tree from.
	 * @param int $cache_timeout	Cache timeout in hours, after which the tree is being rebuild directly from the DB. Defaults to 48 hours.
	 * @return arrray $tree			Always returns array. Empty array if nothing found, or else the found categories.
	 * 
	 * New option structure:
	 * 
	 * widget_id = array(
	 * 		category_trees = array( $this->id_base => $tree, ... )
	 * 		category_tree_expirations = array ( $this->id_base => timestamp, ...)
	 * )
	 */
	
	public function fetch_category_tree( $category_id = 0, $cache_timeout = 48 ) {
		global $wpdb;
		$return = array();
		
		
		$iCacheTimeout = (60 * 60 * $cache_timeout);
		
		if(!empty($category_id)) {
			// check if data has already been cached
			//$this->id_base
			
			
			//$arrCacheTimestamps = get_option( MCF_PREFIX . 'category_tree_expirations', array() );
			
			
			// get_widget_option()
			
			$result = $this->get_widget_option( 'category_tree', false );
			$iCurrentCacheTimestamp = $this->get_widget_option( 'category_tree_expiration', time() );
			
			//$result = get_option( MCF_PREFIX . 'category_tree', false);			
			//$iCurrentCacheTimestamp = get_option(MCF_PREFIX  . 'category_tree_expires', time() );
			
			/**
			 * @see http://de3.php.net/empty#refsect1-function.empty-returnvalues
			 */
			
			
			/**
			 * NOTE: Disabled automatic refresh - category tree will be only regenerated, IF there is no tree data found in the widget options
			 */
			
			if ( !empty($result)/* && $iCurrentCacheTimestamp > time()*/ ) { 
				$return = $result;
				//echo '<!-- built from option -->';
			} else {
				// built category tree + cache data afterwards
				$return = TreeStructure::getTree( $category_id );
				//echo '<!-- built from direct parsing -->';
				
				if(!empty($return)) {
					$this->set_widget_option( 'category_tree', $return);
					$this->set_widget_option( 'category_tree_expiration', time() + $iCacheTimeout );
					
					//update_option(MCF_PREFIX . 'category_tree', $return );
					//update_option(MCF_PREFIX . 'category_tree_expires', time() + $iCacheTimeout );
					
					// check if transient has actually been saved
				}
			}
		}
		
		return $return;
	}
	
	
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
				
				
				<input type="hidden" name="cat" class="mcf-cat-id" value="" />
				<input type="hidden" name="widget_id" class="mfc-widget-id" value="#<?php echo substr($this->id_base, 1); ?>" />
				<input type="hidden" name="widget_form_id" class="mfc-widget-form-id" value="<?php echo substr($this->id_base, 1); ?>-form" />
				
			
			</form>
			<?php
			
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
		
		// check cache_timeout and refresh the cache, if the new expiration point is going to right now or back in the past
		
		if($old_instance['cache_timeout'] != $new_instance['cache_timeout'] ) {
		
			$old_expiration_point = $old_instance['category_tree_expiration'];	
			$original_timestamp = $old_expiration_point - ($old_instance['cache_timeout'] * 60 * 60);
			
			$new_expiration_point = $original_timestamp + ($new_instance['cache_timeout'] * 60 * 60);
		
			/**
			 * Cache gets refreshed if the expiration date is lower or equal to the current date
			 */
		
			$instance['category_tree_expiration'] = $new_expiration_point;
		}
		
		// update cache timeout
		
		$instance['cache_timeout'] = ( intval($new_instance['cache_timeout']) > 0 ? $new_instance['cache_timeout'] : 48 );
		
		
		$instance['depth'] = ( intval($new_instance['depth']) > 0 ) ? $new_instance['depth'] : 5;
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
		$cache_timeout = ( !empty($instance['cache_timeout']) ? $instance['cache_timeout'] : 48 );
		
		$depth = $instance['depth'];
		$level_labels = $instance['level_labels'];
	
		?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'mcf_widget') ?></label>
			<input type="text" class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $title; ?>" />
		</p>
		
		<!-- category_id -->
		<p>
			<label for="<?php echo $this->get_field_id('category_id'); ?>"><?php _e('Category') ?>:</label>
			<?php 
			$arrCatParams = array( 'name' => $this->get_field_name('category_id'), 'id' => $this->get_field_id('category_id'), 'hierarchical' => true, 'show_option_none' => 'Select category!', 'depth' => 10 );
			
			if(!empty($category_id)) {
				$arrCatParams['selected'] = $category_id;
			}
			
			wp_dropdown_categories( $arrCatParams ); 
			?>
		</p>
		
		<!-- cache_timeout -->
		<p>
			<label for="<?php echo $this->get_field_id('cache_timeout'); ?>"><?php _e('Cache Timeout:', 'mcf_widget') ?></label>
			<input type="text" size="3" id="<?php echo $this->get_field_id('cache_timeout'); ?>" name="<?php echo $this->get_field_name('cache_timeout'); ?>" value="<?php echo $cache_timeout; ?>" /><br />
			<small><?php _e('After how many <strong>hours</strong> shall the selected category tree be refreshed? Will be automatically be set to 48 hours if no value is entered.', 'mcf_widget'); ?></small>
		</p>
		
		
		<!-- depth -->
		<p>
			<label for="<?php echo $this->get_field_id('depth'); ?>"><?php _e('Depth:', 'mcf_widget') ?></label>
			<input type="text" size="3" id="<?php echo $this->get_field_id('depth'); ?>" name="<?php echo $this->get_field_name('depth'); ?>" value="<?php echo $depth; ?>" /><br />
			<small></small>
		</p>
		
		
		<!-- level_labels -->
		<p>
			<label for="<?php echo $this->get_field_id('level_labels'); ?>"><?php _e('Labels:', 'mcf_widget') ?></label>
			<textarea class="widefat" id="<?php echo $this->get_field_id('level_labels'); ?>" name="<?php echo $this->get_field_name('level_labels'); ?>" cols="40" rows="3"><?php echo $level_labels; ?></textarea><br />
			<small><?php _e('Label per category level = depth. Their number must match the depth value in the field above, or else they are not going to be saved. Seperate each label with a semicolon.', 'mcf_widget'); ?></small>
		</p>
		
		<!-- button_text -->
		<p>
			<label for="<?php echo $this->get_field_id('button_text'); ?>"><?php _e('Submit button text:', 'mcf_widget') ?></label>
			<input type="text" class="widefat" id="<?php echo $this->get_field_id('button_text'); ?>" name="<?php echo $this->get_field_name('button_text'); ?>" value="<?php echo $button_text; ?>" /><br />
			<small><?php _e('Text of the submit button. If none is set here, the default text (&quot;Filtern&quot;) will be used.', 'mcf_widget'); ?></small>
		</p>
		
		<?php
	}
}


