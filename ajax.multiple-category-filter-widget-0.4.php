<?php
/**
Plugin Name: Multiple Category Filter Widget
Plugin URI: http://usability-idealist.de/
Description: Custom widget to display articles filtered by a specific single or related parent category.
Author: Fabian Wolf
Version: 0.4
Author URI: http://usability-idealist.de/
*/
/**
 * struct:
 * - array('level' => array('label' => 'title', 'categories' => array(1,2,3,...) ) )
 * - id = level
 */

// init
$_ui_MultiCatFilter = new MultipleCategoryFilter();

//if(is_admin() && basename(__FILE__) != 'admin-ajax.php') {
	//$_ui_ligaArtikelFilterAdmin = new LigaFilterAdmin();
//}

// function library
if(!function_exists('fetch_category_children')) {
	
	/**
	 * Fetches the DIRECT children (= 1 level) of the given category
	 * 
	 * @param int $category_id	The ID of the category to retrieve children from
	 * @return array $return	Returns an empty array if no ID is given or no result was found, else the complete category objects.
	 * 
	 * term_id = parent
	 * 
	 * Subselect or group anyone?
	 * 
	 * SELECT * FROM `wp331_term_taxonomy` WHERE parent = 7035
	 * SELECT * FROM wp331_terms WHERE term_id IN ( SELECT term_id FROM wp331_term_taxonomy WHERE parent = 7035)
	 * 
	 */
	
	function fetch_category_children( $category_id = 0) {
		global $wpdb;
		$return = array();
		
		if(!empty($category_id)) {
			$strSQLQuery = 'SELECT term_id AS ID, name, slug FROM ' . $wpdb->terms . ' WHERE term_id IN ( SELECT term_id FROM ' . $wpdb->term_taxonomy . ' WHERE parent = ' . intval($category_id) . ')';
			
			$result = $wpdb->get_results( $strSQLQuery );
			
			if(!empty($result)) {
				$return = $result;
			}
		}
		
		return $return;
	}
	
	if(!function_exists('get_category_tree')) {
	/**
	 * Builds the category tree using a multidimendional array
	 * 
	 * @depends fetch_category_children
	 * @requires fetch_category_children
	 * 
	 * @param int $category_id	Required value. The ID of the category of which we want to retreive all children.
	 * @return array $tree		Either returns empty array if nothing (or the category) was not found, or the complete tree as mentioned in the function description
	 */
		/*function get_category_tree(	$root_category_id = 0 ) {
			$return = array();
			
			if(!empty($root_category_id) ) {
				// initial loop
				$result = fetch_category_children
				
			}
			
			return $return;
		}*/
		
	} // endif
	
}


// classes

class MultipleCategoryFilter {
	var $pluginName = 'Multiple Category Filter',
		$pluginVersion = 0.4,
		$pluginPrefix = '_ui_multiple_category_filter_';
		
	function __construct() {
		// actions
		add_action('wp_ajax_nopriv_ui_mcf_ajax_request', array(&$this, 'ajax_request') ); // 'guest' = regular user
		if(is_admin() ) {
			add_action('wp_ajax_ui_mcf_ajax_request', array(&$this, 'ajax_request') ); // user is logged in
		}
		
		// register widget
		add_action( 'widgets_init', array(&$this, 'init_widget' ) );
		
		
		// add frontend AJAX handling
		//if(!is_admin() ) {
		add_action('wp_enqueue_scripts', array(&$this, 'init_frontend_js' ) );	
		//}
			
		
		//if(is_admin() ) {
			//add_action('wp_init_scripts', array(&$this, 'admin_init_js') );
		//}
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
		wp_localize_script( $this->pluginPrefix . 'lib', 'mcf_ajax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
	}
	
	/**
	 * Wrapper for get_term_children, as fetch_category_children is deprecated and might be dropped entirely from WP 
	 */
	/*
	public function fetch_category_children( $parent_id ) {
		$return = array();
		
		
		 * "Merge all term children into a single array."
		 * @link http://codex.wordpress.org/Function_Reference/get_term_children
		 *
		
		$children = get_term_children();
		if( !empty($children) ) {
			
		}
		
		return $return;
	}*/
	
	/**
	 * Handles the AJAX request
	 * 
	 * @param int $widget_id 		Required value to get the settings of the widget
	 * @param int $category_id 		This is the category, of which we'd like to know whether it got children or not, and if so, return those children
	 * 
	 */
	
	public function ajax_request() {
		$return = array('result' => false, 'message' => 'Nothing to do' );
		
		//$iWidgetID = intval($_GET['widget_id']);
		$iCategoryID = (!empty($_GET['category_id']) ? intval($_GET['category_id']) : 0); /** NOTE: CURRENT category_id - NOT the one set as BASE id in the widget settings! */
		
		$arrCategoryChildren = array();
		
		if(!empty($iCategoryID ) ) {
			$arrCategoryChildren = fetch_category_children( $iCategoryID );

		//$this->fetch_category_children(
		
		/*if(!empty($iWidgetID)) {
			$arrWidgets = get_option('widget' . strtolower('_ui_LigaFilterWidget') . '_options', array());
			$arrWidgetSettings = $arrWidgets[$iWidgetID];
			//$arrCatParams = array('child_of' => $arrWidgetSettings['category_id']);
			
			
			
					
			/*if ( false === ( $arrCategoryTree = get_transient( '_ui_ligafilter_category_tree' ) ) ) {
				 // this code runs when there is no valid transient set
				 $arrCategoryTree = get_categories( $arrCatParams );
				 set_transient('_ui_ligafilter_category_tree', $arrCategoryTree, ($cache_timeout * 60) );
			}*/
			$return['result'] = array();
			
			if(!empty($arrCategoryChildren)) {
				unset($return);
			
				$return = array(
					'result' => $arrCategoryChildren
				);
			}
		}	
		
		
		// .. end of story
		exit( json_encode($return) );
	}
	
	
	/*public function get_category_tree() {
		$return = array( 'success' => false, 'error' => true, 'message' => 'Nothing to do' );
		
		$iWidgetID = (!empty($_GET['id']) ? $_GET['id'] : 0);
		
		if(!empty($iWidgetID)) {
			$arrWidgets = get_option('widget' . strtolower('_ui_LigaFilterWidget') . '_options', array());
			$arrWidgetSettings = $arrWidgets[$iWidgetID];
			$arrCatParams = array('child_of' => $arrWidgetSettings['category_id']);
			
					
			if ( false === ( $arrCategoryTree = get_transient( '_ui_ligafilter_category_tree' ) ) ) {
				 // this code runs when there is no valid transient set
				 $arrCategoryTree = get_categories( $arrCatParams );
				 set_transient('_ui_ligafilter_category_tree', $arrCategoryTree, ($cache_timeout * 60) );
			}
			
			$return = array(
				'success' => true,
				'error' => false,
				'message' => 'Succesfully retrieved the category tree',
				'data' => $arrCategoryTree
			);
		}
		
		// .. end of story
		exit( json_encode($return) );
	}*/
	
}

// widget

class _ui_MultipleCategoryFilterWidget extends WP_Widget {

	function __construct() {
		$widget_ops = array( 'description' => 'Custom widget to display articles filtered by a specific single or related parent category.' );
		parent::__construct( '_ui_MultipleCategoryFilterWidget', __('Multi-Kategorie-Artikelfilter'), $widget_ops );
		
		// add required JS (yes, we could output the JS code directly, but that a) would fuck up any caching systems and b) also lead to ugly page loading pauses which we'd like to avoid, don't we? ;))
		//add_action('wp_enqueue_scripts', array(&$this, 'init_frontend_js') );
		
		
	}
	
	
	/**
	 * struct:
	 * - category_id is the container for all other categories
	 * - the container category is _never_ being displayed
	 * - thus, the direct children of category_id are the top categories and thus the first select box
	 * - depth sets both the maximum number of select boxes to create AND to populate
	 * - the submit button is acailable regardless of the currently selected category
	 * 
	 * + JS: 
	 * 
	 * 	- the currently selected category is determined cycling through each (chained) select and saving the last not-empty value into a global current_cat_id
	 * 	- which by itself is put into front of location.href (?cat=); DO NOT forget removing the class-placeholder-shit from jquery.remoteChain
	 *  - also see tests/form-value-overwrite.php and @link http://codex.wordpress.org/Function_Reference/wp_dropdown_categories#Dropdown_without_a_Submit_Button_using_JavaScript
	 */
	
	
	function widget($args, $instance) {
		//global $_ui_ligaArtikelFilter;
		
		$title = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );
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
		
		//$cache_timeout = (!empty($instance['cache_timeout']) ? $instance['cache_timeout'] : 10); // in minutes


		//$arrCategoryTree = $_ui_ligaArtikelFilter->get_category_tree(); // prepare all required selects


		// widget_output_main.start
			echo $args['before_widget'];
			
			
			if ( !empty($title) ) {
				echo $args['before_title'] . $title . $args['after_title'];
			}
			
			if(!empty($category_id) ) {
				
				// there is NO such thing as remore = outworldish sperm; the one related is HIGHT PRIES MASTER
				
			?>
			<form method="get" action="" id="<?php echo $widget->id_base; ?>-form">
				
			<?php for($n = 0; $n < $depth; $n++) {
				$strHTMLID = $this->id_base . '-cat' . $n;
				//echo 'strHTMLID = ' . $strHTMLID . ', ';
				
				if($n+1 < $depth) { // prepare chaining
					//echo $n+1 . ' < ' . $depth . ', ';
					$arrChain[$strHTMLID] = $this->id_base . '-cat' . ($n+1);
					//echo 'arrChain[' .$strHTMLID.'] = ' .$arrChain[$strHTMLID];
				}
				
				$strBefore = ''; $strAfter = '';
				
				if(isset($arrLabels[$n]) != false) {
					$strBefore = '<label><span>'.$arrLabels[$n].'</span> ';
					$strAfter = '</label>';
				} 
				
				echo $strBefore; 
				
				
				if($n == 0) { // insert initial values
					$arrInitialCats = fetch_category_children( $category_id ); ?>		
				<?php } ?>
				
				<div class="category-select-block">
					
					<select name="category" size="1" id="<?php echo $strHTMLID; ?>">
						<option value=""><?php _e('Select Category'); ?></option>
				<?php
				if(!empty($arrInitialCats) && $n == 0) {
					foreach($arrInitialCats as $cat) { ?>
						<option value="<?php echo $cat->ID; ?>"<?php
						// check if current category is active!
						?>><?php echo $cat->name; ?></option>
				<?php }
				} else {
						// nothing to do ... yet
				}
				?>			
					</select>
				</div>
				
				<?php echo $strAfter; ?>
			<?php } ?>
				
				<?php //wp_dropdown_categories( array('child_of' => $category_id,  'hierarchical' => true, 'depth' => 1) ); ?>
			
				<p class="form-controls">
					<button type="submit" class="category-tree-button button-submit">Filtern</button> 
					<label><input type="checkbox" name="remember_current_setting" class="multi-cat-filter-save" value="1" /> Auswahl speichern</label>
				</p>
				
				<input type="hidden" name="cat" value="" />
			
			</form>
			<script type="text/javascript">
				jQuery(function() {
					// init chained selects
				<?php 
				if(!empty($arrChain) ) {
					//foreach($arrChain as $strKeyFrom => $strChainTo) {
					foreach($arrChain as $strChainTo => $strKeyFrom) { // reverse => second element is assigned to FIRST element
						 ?>
					
					jQuery('#<?php echo $strKeyFrom; ?>').remoteChainedTo('<?php echo $strChainTo; ?>', mcf_ajax.ajax_url);
				<?php }
				} ?>
				
					
					/**
					 * TODO: Adapt this for the chained selects widget
					 */
					
					jQuery('#<?php echo $widget->id_base; ?>-form').submit(function() {
						that = this;
						window.submitCatID = '';
						
						jQuery(this).find('select[name=cat]').each(function() {
							var thisEmpty = '';
							if(jQuery(this).val() ) {
								var thisEmpty = 'not';
								
								window.submitCatID = jQuery(this).val()
							}
							<?php if(is_user_logged_in() ) { ?>
							console.log(jQuery(this).attr('id') + ': ' + jQuery(this).val() + ', value is ' + thisEmpty + ' empty, current_cat_id = ' + window.submitCatID  )
							<?php } ?>
						})
						
						if(window.submitCatID != '') {
							jQuery(that).find('input[name=cat_id]').val( window.submitCatID )

							window.location.href = '<?php echo $_SERVER['PHP_SELF']; ?>?cat_id=' + window.submitCatID + '&action=directed'

						}
						
						
						<?php if(is_user_logged_in() ) { ?>
						console.log( window.submitCatID + ' ?= ' + jQuery(this).find('input[name=cat_id]').val() );
						<?php } ?>


					})
					
					jQuery('.multi-cat-filter-save').click(function() {
						// save / remove settings via cookie
					})
					
				})
				
			
			</script>
			<?php
			} // no widget content if no category_id is given!

			echo $args['after_widget'];	
		
			// widget_output_main.end
	

			
			// custom css.end
	
	}

	function update( $new_instance, $old_instance ) {
		$instance['title'] = strip_tags( stripslashes($new_instance['title']) );
		$instance['category_id'] = $new_instance['category_id'];
		$instance['depth'] = $new_instance['depth'] > 0 ? 5 : $new_instance['depth'];
					
		// depth is ALWAYS bigger than zero!
		if( (stripos($new_instance['level_labels'], ';') !== false && $instance['depth'] > 1 ) != false 
			|| ($instance['depth'] == 1 && stripos($new_instance['level_labels'], ';') === false) != false ) {
			$instance['level_labels'] = $new_instance['level_labels'];
		}
		

		return $instance;
	}

	function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : '';
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
		
		<?php
	}
}


