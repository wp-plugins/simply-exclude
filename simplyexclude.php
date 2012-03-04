<?php
/*
Plugin Name: Simply Exclude New
Plugin URI: http://www.codehooligans.com/projects/wordpress/simply-exclude/
Description: Provides an interface to selectively exclude/include categories, tags and page from the 4 actions used by WordPress. is_front, is_archive, is_search, is_feed.
Author: Paul Menard
Version: 2.0.1
Author URI: http://www.codehooligans.com

Revision history
1.0 - 2007-11-20: Initial release
1.1 - 2008-12-15: Added logic to work with WP version greater than 2.2
1.5 - 20008-04-27 Fixed display issues. Changes 'List' to 'Archive'. Added tags inclusion/exclusion login. Works only with WP 2.3 and greater.
1.6 - 2008-05-22 Fixed various items. Added format display for Categories and Pages to reveal heirarchy, Disable plugin functions when searching in admin. This also corrected a display exclusion bug when showing categories and pages. 
1.7 - 2008-05-29 Added Author to the Include/Exclude logic. Now you can exclude Author's Posts from Search, Home, RSS, Archive.
1.7.1 - 2008-07-16 Fixed an issue with WP 2.6 where it automatically decided to unserialize the option data structure. 
1.7.2 - 2009-02-05 Fixed some PHP warning by checking variable is set. Also added style to 2.7 interface. 
1.7.2.1 - 2009-07-01 Fixed some PHP warning by checking variable is set. Also added style for 2.8 interface. Very minor changes. 
1.7.5 - 2009-07015 Fixed some PHP warning by checking variable is set. Also added style for 2.8 interface. Very minor changes. 
1.7.6 - 2009-11-14 Fixes: Issue with the Pages exclusion. Many users reporting a permissions issue. Additions: Added handler logic to interface with two other plugins. One of the often used Google XML Sitemaps. When setting Page or Category exclusions you now have the option to update the Google XML Sitemaps exclude pages and categories automatically. The other plugin is Search Unleashed. 
2.0 - 2012-01-23 Full rewrite of the plugin to support custom Taxonomies and Custom Post Types. Added support for managing items within the Taxonomy/Post Type panels. Added support for exclude/include on Post instead of just Categories and Tags. Dropped support for third-party plugins like Google XML Sitemaps and Seach Unleashed (Sorry!). Now correctly supporting Pages seach excludes. Yah!.

*/

define('SIMPLY_EXCLUDE_I18N_DOMAIN', 'simplyexclude');

class SimplyExcludeNew
{
	public $wp_version;
	public $se_version;
	public $admin_menu_label;
	public $options_key;
		
	public $se_taxonomies_exclude = array();
	public $se_post_types_exclude = array();
	
	private $plugindir_url;

	private $current_taxonomy;
	private $current_post_type;
	private $current_se_type;
	
	private $page_hooks;
	
	public function __construct() {
		
		global $wp_version;
		$this->wp_version = $wp_version;
		
		$this->se_version	= "2.0";
		
		$this->admin_menu_label	= __("Simply Exclude", SIMPLY_EXCLUDE_I18N_DOMAIN);
		$this->options_key		= "simplyexclude_v2";
		
		$plugindir_node 		= dirname(plugin_basename(__FILE__));	
		$this->plugindir_url 	= WP_PLUGIN_URL . "/". $plugindir_node;
		
		$this->se_taxonomies_exclude = array('media-tags', 'post_format', 'link_category', 'nav_menu');
		$this->se_post_types_exclude = array('revision', 'nav_menu_item', 'attachment');		
		$this->page_hooks = array();
		
		add_action( 'init', array(&$this,'init_proc') );
		add_action( 'admin_init', array(&$this,'admin_init_proc') );
		add_action( 'admin_menu', array(&$this,'se_add_nav') );
		add_action( 'admin_footer', array(&$this,'se_admin_footer') );				
		add_action( 'wp_ajax_se_update', array(&$this, 'se_ajax_update') );

		// Used to limit the categories displayed on the home page. Simple
		add_filter('pre_get_posts', array(&$this,'se_filters'));
	}

	function admin_init_proc()
	{
		$this->se_load_config();
						
		if ( ($this->check_url('wp-admin/edit-tags.php'))		
		  || ($this->check_url('wp-admin/edit.php'))
		  || ($this->check_url('wp-admin/users.php')) )
		{			
			wp_enqueue_style( 'simplyexclude-stylesheet', $this->plugindir_url .'/simplyexclude_style_admin.css', false, $this->se_version);

			wp_enqueue_script('jquery'); 
			wp_enqueue_script('jquery-ui-core'); 
			wp_enqueue_script('jquery-ui-dialog');
			
			wp_enqueue_style( 'simplyexclude-jquery-ui', 
				$this->plugindir_url .'/js/jquery-ui/css/flick/jquery-ui-1.8.17.custom.css',
				null, $this->se_version );
			
			wp_enqueue_script('simplyexclude-admin-dialog-js', $this->plugindir_url .'/js/simplyexclude_admin_dialog.js',
				array('jquery', 'jquery-ui-core', 'jquery-ui-dialog'), $this->se_version);			

			wp_enqueue_script('simplyexclude-admin-ajax-js', $this->plugindir_url .'/js/simplyexclude_admin_ajax.js',
				array('jquery', 'jquery-ui-core'), $this->se_version);			
		}
						
		add_action('edit_category_form_fields', array(&$this,'se_show_taxonomy_term_fields'), 99, 1);
		add_action('edit_tag_form_fields', array(&$this,'se_show_taxonomy_term_fields'), 99, 1);
		add_action('edited_term', array(&$this,'se_save_taxonomy_term_fields'), 99, 3);
		
		add_action( 'show_user_profile', array(&$this,'se_show_user_profile'), 99 );
		add_action( 'edit_user_profile', array(&$this,'se_show_user_profile'), 99 );
		
		add_action( 'profile_update', array(&$this,'se_save_user_profile'), 99);
		
		add_filter( 'plugin_action_links_'. basename( dirname( __FILE__ ) ). '/' .basename( __FILE__ ), array(&$this,'plugin_settings'));
		
	}
	
	// Adds a 'Settings' link on the Plugins listing row. Cool!
	function plugin_settings( $links ) {
		$settings_link = '<a href="admin.php?page=se_manage_settings">'.__( 'Settings', SIMPLY_EXCLUDE_I18N_DOMAIN ).'</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}
	
	function init_proc()
	{
		if (!is_admin())
		{
			add_filter('widget_pages_args', array(&$this, 'se_widget_pages_args_proc'));	
			
			// Not needed since wp_list_pages is user managable. 
			//aad_filter('wp_list_pages_excludes', array(&$this, 'se_wp_list_pages_excludes_proc'));
			
			// Suport for the Category list/dropdown widget
			add_filter('widget_categories_dropdown_args', array(&$this, 'se_widget_categories_dropdown_args_proc'));	
			add_filter('widget_categories_args', array(&$this, 'se_widget_categories_dropdown_args_proc'));	

			// Support for the Tag Clod widget. This widget supports both the post_tag and category taxonomies.
			add_filter('widget_tag_cloud_args', array(&$this, 'se_widget_tag_cloud_args_proc'));	
		}
	}
	
	function se_add_nav() 
	{
		// Add the Main Nav item to the WP menu
		add_menu_page( 'Simply Exclude', 'Simply Exclude', 'manage_options', 'se_manage_settings', 
			array(&$this, 'se_manage_settings'));

		// Add our Options sub menu.
		$this->pagehooks['se_manage_settings'] = add_submenu_page( 'se_manage_settings', 'Settings', 'Settings', 'manage_options', 
			'se_manage_settings', array(&$this, 'se_manage_settings'));
		add_action('load-'. $this->pagehooks['se_manage_settings'], array(&$this, 'on_load_settings_page'));


		// Add our Help sub menu.
		$this->pagehooks['se_manage_help'] = add_submenu_page( 'se_manage_settings', 'Help', 'Help', 'manage_options', 
			'se_manage_help', array(&$this, 'se_manage_help'));
		add_action('load-'. $this->pagehooks['se_manage_help'], array(&$this, 'on_load_help_page'));
		
		if ( !current_user_can('manage_options') )
			return;
			
		$this->se_load_config();
		//echo "se_cfg<pre>"; print_r($this->se_cfg); echo "</pre>";
		
		// Now add a submenu for each registered taxonomy
		$se_taxonomies = $this->se_load_taxonomy_list();
		if ($se_taxonomies)
		{
			foreach($se_taxonomies as $t_item)
			{
				if ((isset($this->se_cfg['data']['taxonomies'][$t_item->name]['options']['showhide']))
				 && ($this->se_cfg['data']['taxonomies'][$t_item->name]['options']['showhide'] == 'show'))
				{
					add_filter( "manage_edit-". $t_item->name ."_columns", array( &$this, 'se_manage_taxonomy_columns' ), 99 );
					add_filter( "manage_". $t_item->name. "_custom_column", array(&$this, 'se_display_taxonomy_column_actions'), 99, 3);										
				}
			}
			//add_action("delete_term", array(&$this, 'se_delete_taxonomy_term'), 99, 3);			
		}

		// Now add a submenu for each registered post_type
		$se_post_types = $this->se_load_post_type_list();
		if ($se_post_types)
		{
			foreach($se_post_types as $t_item)
			{
				if ((isset($this->se_cfg['data']['post_types'][$t_item->name]['options']['showhide']))
				 && ($this->se_cfg['data']['post_types'][$t_item->name]['options']['showhide'] == 'show'))
				{
					add_filter( "manage_". $t_item->name ."_posts_columns", array( &$this, 'se_manage_post_type_columns' ), 99 );
					add_filter( "manage_". $t_item->name ."_posts_custom_column", array(&$this, 'se_display_post_type_column_actions'), 99, 3); 
			
					add_meta_box($this->options_key, $this->admin_menu_label, array(&$this,'show_post_type_exclude_sidebar_dbx'), $t_item->name, 'side');								
					add_action('save_post', array(&$this,'save_post_type_exclude_sidebar_dbx'));				
				}
			}
		}
		
		// Users table
		if ((isset($this->se_cfg['data']['se_types']['users']['options']['showhide']))
		 && ($this->se_cfg['data']['se_types']['users']['options']['showhide'] == 'show'))
		{
			add_filter( "manage_users_columns", array( &$this, 'se_manage_user_columns' ), 99 );
			add_filter( 'manage_users_custom_column', array(&$this, 'se_display_user_column_actions'), 99, 3);
		}
	}

/*		
	function se_delete_taxonomy_term($term, $tt_id, $taxonomy)
	{
		echo "term<pre>"; print_r($term); echo "</pre>";
		echo "tt_id=[". $tt_id ."]<br />";
		echo "taxonomy=[". $taxonomy ."]<br />";
		
		exit;
	}
*/
		
	/****************************************************************************************************************************/
	/*																															*/
	/*												ON LOAD PAGES																*/
	/*																															*/
	/****************************************************************************************************************************/
		
	function on_load_settings_page() {

		wp_enqueue_style( 'simplyexclude-stylesheet', $this->plugindir_url .'/simplyexclude_style_admin.css', false, $this->se_version);
		wp_enqueue_script('simplyexclude-admin-ajax-js', $this->plugindir_url .'/js/simplyexclude_admin_ajax.js',
			array('jquery', 'jquery-ui-core'), $this->se_version);			

		wp_enqueue_script('common');
		wp_enqueue_script('wp-lists');
		wp_enqueue_script('postbox');

		//add several metaboxes now, all metaboxes registered during load page can be switched off/on at "Screen Options" automatically, nothing special to do therefore
		add_meta_box('se_settings_about_sidebar', 'About this Plugin', array(&$this, 'se_settings_about_sidebar'),
			$this->pagehooks['se_manage_settings'], 'side', 'core');
		add_meta_box('se_settings_donate_sidebar', 'Make a Donation', array(&$this, 'se_settings_donate_sidebar'),
			$this->pagehooks['se_manage_settings'], 'side', 'core');

		add_meta_box('se_display_options_taxonomy_actions_panel', 'Taxonomies Actions', array(&$this, 'se_display_options_taxonomy_actions_panel'), 
			$this->pagehooks['se_manage_settings'], 'normal', 'core');

		add_meta_box('se_display_options_post_type_actions_panel', 'Post Types Actions', array(&$this, 'se_display_options_post_type_actions_panel'), 
			$this->pagehooks['se_manage_settings'], 'normal', 'core');

		add_meta_box('se_display_options_user_actions_panel', 'Users Actions', array(&$this, 'se_display_options_user_actions_panel'),
		 	$this->pagehooks['se_manage_settings'], 'normal', 'core');

//		add_meta_box('se_options_thirdparty_panel', 'Other Plugins', array(&$this, 'se_options_thirdparty_panel'), 
//			$this->pagehooks['se_manage_settings'], 'normal', 'core');
	}

	function on_load_help_page()
	{
		global $wp_version; 
		
		wp_enqueue_style( 'simplyexclude-stylesheet', $this->plugindir_url .'/simplyexclude_style_admin.css', false, $this->se_version);
		
		wp_enqueue_script('jquery'); 
		wp_enqueue_script('jquery-ui-core'); 

	    if ( version_compare( $wp_version, '3.3', '<' ) ) {
			wp_register_script( 'jquery-ui-widget-se', $this->plugindir_url .'/js/jquery-ui/jquery.ui.widget.min.js', 
				array('jquery', 'jquery-ui-core'), $this->se_version);
		    wp_enqueue_script( 'jquery-ui-widget-se' );
			wp_register_script( 'jquery-ui-accordion-se', $this->plugindir_url .'/js/jquery-ui/jquery.ui.accordion.min.js', 
				array('jquery', 'jquery-ui-core', 'jquery-ui-widget-se'), $this->se_version);
		    wp_enqueue_script( 'jquery-ui-accordion-se' );
		
		} else {
			wp_enqueue_script('jquery-ui-widget');				
			wp_enqueue_script('jquery-ui-accordion');							
		}

		add_meta_box('se_settings_about_sidebar', 'About this Plugin', array(&$this, 'se_settings_about_sidebar'),
			$this->pagehooks['se_manage_help'], 'side', 'core');
		add_meta_box('se_settings_donate_sidebar', 'Make a Donation', array(&$this, 'se_settings_donate_sidebar'),
			$this->pagehooks['se_manage_help'], 'side', 'core');		
	}
	
	/****************************************************************************************************************************/
	/*																															*/
	/*												ACTIONS PANELS																*/
	/*																															*/
	/****************************************************************************************************************************/
	
	function se_show_taxonomy_actions_panel($taxonomy)
	{
		if (!$taxonomy)	return;

		if (!isset($this->se_cfg['data']['taxonomies'][$taxonomy]))
			return;

		$this->current_taxonomy = $this->se_cfg['data']['taxonomies'][$taxonomy];

		?>
		<table class="widefat simply-exclude-settings-postbox simplyexclude-actions-panel" cellpadding="3" cellspacing="3" border="0">
		<thead>
        <tr>
        	<th class="action"><?php _e('Action Name') ?></th>
        	<th class="description"><?php _e('Description ') ?></th>
        	<th class="inc-excl"><?php _e('Inclusion/Exclusion') ?></th>
        </tr>
		</thead>
		<tbody>
		<?php
		$class="";

		foreach ($this->current_taxonomy['actions'] as $action_key => $action_val)
		{
			$class = ('alternate' == $class) ? '' : 'alternate';
			?>
			<tr <?php if (strlen($class)) echo "class='".$class."'" ?>>
				<td class="action"><?php echo $this->get_taxonomy_action_label($taxonomy, $action_key, 'name'); //$action_val['name'] ?></td>
				<td class="description"><?php echo $this->get_taxonomy_action_label($taxonomy, $action_key, 'description');//$action_val['description'] ?></td>
				<td class="inc-excl">
					<input type="radio" name="se_cfg[<?php echo $taxonomy; ?>][actions][<?php echo $action_key ?>]" value="i" 
						<?php if ($action_val['action'] == 'i') echo "checked='checked'"; ?> /> <?php _e('Include only', SIMPLY_EXCLUDE_I18N_DOMAIN); ?><br />
					<input type="radio" name="se_cfg[<?php echo $taxonomy; ?>][actions][<?php echo $action_key ?>]" value="e" 
						<?php if ($action_val['action'] == 'e') echo "checked='checked'"; ?> /> <?php _e('Exclude', SIMPLY_EXCLUDE_I18N_DOMAIN); ?>
				</td>
			<tr>
			<?php
		}
		?>				
		</tbody>
		</table>
		<?php
	}
		
	function se_show_post_type_actions_panel($post_type='')
	{
		if (!$post_type) return;

		if (!isset($this->se_cfg['data']['post_types'][$post_type]))
			return;

		$this->current_post_type = $this->se_cfg['data']['post_types'][$post_type];
		?>
		<table class="widefat simply-exclude-settings-postbox simplyexclude-actions-panel" cellpadding="3" cellspacing="3" border="0">
		<thead>
        <tr>
        	<th class="action"><?php _e('Action Name') ?></th>
        	<th class="description"><?php _e('Description ') ?></th>
        	<th class="inc-excl"><?php _e('Inclusion/Exclusion') ?></th>
        </tr>
		</thead>
		<tbody>
		<?php
		$class="";

		foreach ($this->current_post_type['actions'] as $action_key => $action_val)
		{
			$class = ('alternate' == $class) ? '' : 'alternate';
			?>
			<tr <?php if (strlen($class)) echo "class='".$class."'" ?>>
				<td class="action"><?php echo $this->get_post_type_action_label($post_type, $action_key, 'name'); ?></td>
				<td class="description"><?php echo $this->get_post_type_action_label($post_type, $action_key, 'description');  ?></td>
				<td class="inc-excl">
					<input type="radio" name="se_cfg[<?php echo $post_type; ?>][actions][<?php echo $action_key ?>]" value="i" 
						<?php if ($action_val['action'] == 'i') echo "checked='checked'"; ?> /> <?php _e('Include only', SIMPLY_EXCLUDE_I18N_DOMAIN); ?><br />
					<input type="radio" name="se_cfg[<?php echo $post_type; ?>][actions][<?php echo $action_key ?>]" value="e" 
						<?php if ($action_val['action'] == 'e') echo "checked='checked'"; ?> /> <?php _e('Exclude', SIMPLY_EXCLUDE_I18N_DOMAIN); ?>
					<?php
						if (($action_key == "is_home") 
						 && ((isset($this->current_post_type['options']['capability_type'])) && ($this->current_post_type['options']['capability_type'] == "post")))
						{
							?><br />
							<input type="radio" name="se_cfg[<?php echo $post_type; ?>][actions][<?php echo $action_key ?>]" value="a" 
								<?php if ($action_val['action'] == 'a') echo "checked='checked'"; ?> /> <?php _e('Include All', SIMPLY_EXCLUDE_I18N_DOMAIN); ?>
							<?php
						}

						else if (($action_key == "is_feed") 
						 && ((isset($this->current_post_type['options']['capability_type'])) && ($this->current_post_type['options']['capability_type'] == "post")))
						{
							?><br />
							<input type="radio" name="se_cfg[<?php echo $post_type; ?>][actions][<?php echo $action_key ?>]" value="a" 
								<?php if ($action_val['action'] == 'a') echo "checked='checked'"; ?> /> <?php _e('Include All', SIMPLY_EXCLUDE_I18N_DOMAIN); ?>
							<?php
						}
					?>
				</td>
			<tr>
			<?php
		}
		?>
		</tbody>
		</table>
		<?php
	}
	
	function se_show_se_type_actions_panel($se_type='')
	{
		if (!$se_type) return;

		if (!isset($this->se_cfg['data']['se_types'][$se_type]))
			return;
			
		$this->current_se_type = $this->se_cfg['data']['se_types'][$se_type];
		?>
		<table class="widefat simply-exclude-settings-postbox simplyexclude-actions-panel" cellpadding="3" cellspacing="3" border="0">
		<thead>
        <tr>
        	<th class="action"><?php _e('Action Name') ?></th>
        	<th class="description"><?php _e('Description ') ?></th>
        	<th class="inc-excl"><?php _e('Inclusion/Exclusion') ?></th>
        </tr>
		</thead>
		<tbody>
		<?php
		$class="";

		foreach ($this->current_se_type['actions'] as $action_key => $action_val)
		{
			$class = ('alternate' == $class) ? '' : 'alternate';
			?>
			<tr <?php if (strlen($class)) echo "class='".$class."'" ?>>
				<td class="action"><?php echo $this->get_se_type_action_label($se_type, $action_key, 'name'); ?></td>
				<td class="description"><?php echo $this->get_se_type_action_label($se_type, $action_key, 'description');  ?></td>
				<td class="inc-excl">
					<input type="radio" name="se_cfg[<?php echo $se_type; ?>][actions][<?php echo $action_key ?>]" value="i" 
						<?php if ($action_val['action'] == 'i') echo "checked='checked'"; ?> /> <?php _e('Include only', SIMPLY_EXCLUDE_I18N_DOMAIN); ?><br />
					<input type="radio" name="se_cfg[<?php echo $se_type; ?>][actions][<?php echo $action_key ?>]" value="e" 
						<?php if ($action_val['action'] == 'e') echo "checked='checked'"; ?> /> <?php _e('Exclude', SIMPLY_EXCLUDE_I18N_DOMAIN); ?>
				</td>
			<tr>
			<?php
		}
		?>
		</tbody>
		</table>
		<?php
	}
	
	
	/****************************************************************************************************************************/
	/*																															*/
	/*												ACTIVE PANELS																*/
	/*																															*/
	/****************************************************************************************************************************/
	
	function se_show_taxonomy_active_panel($taxonomy)
	{
		if (!$taxonomy)	return;

		if (!isset($this->se_cfg['data']['taxonomies'][$taxonomy]))
			return;

		$this->current_taxonomy = $this->se_cfg['data']['taxonomies'][$taxonomy];
		if (!isset($this->current_taxonomy['options']['active']))
			$this->current_taxonomy['options']['active'] = "yes";
		?>
		<table class="widefat simply-exclude-settings-postbox simplyexclude-active-panel" cellpadding="3" cellspacing="3" border="0">
		<thead>
        <tr>
        	<th class="action" colspan="2"><?php _e('Active', SIMPLY_EXCLUDE_I18N_DOMAIN) ?></th>
        </tr>
		</thead>
		<tbody>
		<tr>
			<td class="description"><?php _e("Is this Taxonomy active? This is sometimes convenient instead of unsetting all Taxonomy terms.", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></td>
			<td class="inc-excl">
				<input type="radio" name="se_cfg[<?php echo $taxonomy; ?>][options][active]" value="yes" 
					<?php if ($this->current_taxonomy['options']['active'] == 'yes') 
						echo "checked='checked'"; ?> /> <?php _e('Active', SIMPLY_EXCLUDE_I18N_DOMAIN); ?><br />
				<input type="radio" name="se_cfg[<?php echo $taxonomy; ?>][options][active]" value="no" 
					<?php if ($this->current_taxonomy['options']['active'] == 'no') 
						echo "checked='checked'"; ?> /> <?php _e('Disabled', SIMPLY_EXCLUDE_I18N_DOMAIN); ?>
			</td>
		</tr>
		</tbody>
		</table>
		<?php
	}

	function se_show_post_type_active_panel($post_type)
	{
		if (!$post_type)	return;

		if (!isset($this->se_cfg['data']['post_types'][$post_type]))
			return;

		$this->current_post_type = $this->se_cfg['data']['post_types'][$post_type];
		if (!isset($this->current_post_type['options']['active']))
			$this->current_post_type['options']['active'] = "yes";
		?>
		<table class="widefat simply-exclude-settings-postbox simplyexclude-active-panel" cellpadding="3" cellspacing="3" border="0">
		<thead>
        <tr>
        	<th class="action" colspan="2"><?php _e('Active', SIMPLY_EXCLUDE_I18N_DOMAIN) ?></th>
        </tr>
		</thead>
		<tbody>
		<tr>
			<td class="description"><?php _e("Is this Post Type Active? This is sometimes convenient instead of unsetting all Post Type items.", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></td>
			<td class="inc-excl">
				<input type="radio" name="se_cfg[<?php echo $post_type; ?>][options][active]" value="yes" 
					<?php if ($this->current_post_type['options']['active'] == 'yes') 
						echo "checked='checked'"; ?> /> <?php _e('Active', SIMPLY_EXCLUDE_I18N_DOMAIN); ?><br />
				<input type="radio" name="se_cfg[<?php echo $post_type; ?>][options][active]" value="no" 
					<?php if ($this->current_post_type['options']['active'] == 'no') 
						echo "checked='checked'"; ?> /> <?php _e('Disabled', SIMPLY_EXCLUDE_I18N_DOMAIN); ?>
			</td>
		</tr>
		</tbody>
		</table>
		<?php
	}

	function se_show_se_type_active_panel($se_type)
	{
		if (!$se_type)	return;

		if (!isset($this->se_cfg['data']['se_types'][$se_type]))
			return;

		$this->current_se_type = $this->se_cfg['data']['se_types'][$se_type];
		if (!isset($this->current_se_type['options']['active']))
			$this->current_se_type['options']['active'] = "yes";
		?>
		<table class="widefat simply-exclude-settings-postbox simplyexclude-active-panel" cellpadding="3" cellspacing="3" border="0">
		<thead>
        <tr>
        	<th class="action" colspan="2"><?php _e('Active', SIMPLY_EXCLUDE_I18N_DOMAIN) ?></th>
        </tr>
		</thead>
		<tbody>
		<tr>
			<td class="description"><?php _e("Active?", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></td>
			<td class="inc-excl">
				<input type="radio" name="se_cfg[<?php echo $se_type; ?>][options][active]" value="yes" 
					<?php if ($this->current_se_type['options']['active'] == 'yes') 
						echo "checked='checked'"; ?> /> <?php _e('Active', SIMPLY_EXCLUDE_I18N_DOMAIN); ?><br />
				<input type="radio" name="se_cfg[<?php echo $se_type; ?>][options][active]" value="no" 
					<?php if ($this->current_se_type['options']['active'] == 'no') 
						echo "checked='checked'"; ?> /> <?php _e('Disabled', SIMPLY_EXCLUDE_I18N_DOMAIN); ?>
			</td>
		</tr>
		</tbody>
		</table>
		<?php
	}



	/****************************************************************************************************************************/
	/*																															*/
	/*												SHOW/HIDE PANELS															*/
	/*																															*/
	/****************************************************************************************************************************/

	function se_show_taxonomy_showhide_panel($taxonomy)
	{
		if (!$taxonomy)	return;

		if (!isset($this->se_cfg['data']['taxonomies'][$taxonomy]))
			return;

		$this->current_taxonomy = $this->se_cfg['data']['taxonomies'][$taxonomy];

		?>
		<table class="widefat simply-exclude-settings-postbox simplyexclude-active-panel" cellpadding="3" cellspacing="3" border="0">
		<thead>
        <tr>
        	<th class="action" colspan="2"><?php _e('Show/Hide', SIMPLY_EXCLUDE_I18N_DOMAIN) ?></th>
        </tr>
		</thead>
		<tbody>
		<tr>
			<td class="description"><?php _e("Show the extra columns on the Taxonomy listing and the Taxonomy edit form?", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></td>
			<td class="inc-excl">
				<input type="radio" name="se_cfg[<?php echo $taxonomy; ?>][options][showhide]" value="show" 
					<?php if ($this->current_taxonomy['options']['showhide'] == 'show') 
						echo "checked='checked'"; ?> /> <?php _e('Show', SIMPLY_EXCLUDE_I18N_DOMAIN); ?><br />
				<input type="radio" name="se_cfg[<?php echo $taxonomy; ?>][options][showhide]" value="hide" 
					<?php if ($this->current_taxonomy['options']['showhide'] == 'hide') 
						echo "checked='checked'"; ?> /> <?php _e('Hide', SIMPLY_EXCLUDE_I18N_DOMAIN); ?>
			</td>
		</tr>
		</table>
		<?php
	}

	function se_show_post_type_showhide_panel($post_type)
	{
		if (!$post_type)	return;

		if (!isset($this->se_cfg['data']['post_types'][$post_type]))
			return;

		$this->current_post_type = $this->se_cfg['data']['post_types'][$post_type];
		?>
		<table class="widefat simply-exclude-settings-postbox simplyexclude-active-panel" cellpadding="3" cellspacing="3" border="0">
		<thead>
        <tr>
        	<th class="action" colspan="2"><?php _e('Show/Hide', SIMPLY_EXCLUDE_I18N_DOMAIN) ?></th>
        </tr>
		</thead>
		<tbody>
		<tr>
			<td class="description"><?php _e("Show the extra columns on the Post Type listing and the Post Type edit form?", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></td>
			<td class="inc-excl">
				<input type="radio" name="se_cfg[<?php echo $post_type; ?>][options][showhide]" value="show" 
					<?php if ($this->current_post_type['options']['showhide'] == 'show') 
						echo "checked='checked'"; ?> /> <?php _e('Show', SIMPLY_EXCLUDE_I18N_DOMAIN); ?><br />
				<input type="radio" name="se_cfg[<?php echo $post_type; ?>][options][showhide]" value="hide" 
					<?php if ($this->current_post_type['options']['showhide'] == 'hide') 
						echo "checked='checked'"; ?> /> <?php _e('Hide', SIMPLY_EXCLUDE_I18N_DOMAIN); ?>
			</td>
		</tr>
		</table>
		<?php
	}

	function se_show_se_type_showhide_panel($se_type)
	{
		if (!$se_type)	return;

		if (!isset($this->se_cfg['data']['se_types'][$se_type]))
			return;

		$this->current_se_type = $this->se_cfg['data']['se_types'][$se_type];
		?>
		<table class="widefat simply-exclude-settings-postbox simplyexclude-active-panel" cellpadding="3" cellspacing="3" border="0">
		<thead>
        <tr>
        	<th class="action" colspan="2"><?php _e('Show/Hide', SIMPLY_EXCLUDE_I18N_DOMAIN) ?></th>
        </tr>
		</thead>
		<tbody>
		<tr>
			<td class="description"><?php _e("Show the extra columns on the listing and the edit form?", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></td>
			<td class="inc-excl">
				<input type="radio" name="se_cfg[<?php echo $se_type; ?>][options][showhide]" value="show" 
					<?php if ($this->current_se_type['options']['showhide'] == 'show') 
						echo "checked='checked'"; ?> /> <?php _e('Show', SIMPLY_EXCLUDE_I18N_DOMAIN); ?><br />
				<input type="radio" name="se_cfg[<?php echo $se_type; ?>][options][showhide]" value="hide" 
					<?php if ($this->current_se_type['options']['showhide'] == 'hide') 
						echo "checked='checked'"; ?> /> <?php _e('Hide', SIMPLY_EXCLUDE_I18N_DOMAIN); ?>
			</td>
		</tr>
		</table>
		<?php
	}
	
	
	
	/****************************************************************************************************************************/
	/*																															*/
	/*												COLUMNS (HEADERS)															*/
	/*																															*/
	/****************************************************************************************************************************/
		
	function se_manage_taxonomy_columns($columns)
	{
		if (!isset($columns['se-actions']))	
			$columns['se-actions'] = 'Simply Exclude <a id="se-show-actions-panel" href="#">show</a>';
		return $columns;
	}

	function se_manage_post_type_columns($columns)
	{
		//echo "columns<pre>"; print_r($columns); echo "</pre>";
		if (!isset($columns['se_actions']))	
			$columns['se-actions'] = 'Simply Exclude <a id="se-show-actions-panel" href="#">show</a>';
		return $columns;
	}

	function se_manage_user_columns($columns)
	{
		//echo "columns<pre>"; print_r($columns); echo "</pre>";
		if (!isset($columns['se_actions']))	
			$columns['se-actions'] = 'Simply Exclude <a id="se-show-actions-panel" href="#">show</a>';
		
		return $columns;
	}


	/****************************************************************************************************************************/
	/*																															*/
	/*												COLUMNS (ACTIONS)															*/
	/*																															*/
	/****************************************************************************************************************************/

	function se_display_taxonomy_column_actions($junk, $column_name, $term_id)
	{
		global $taxonomy, $post_type;
		
		if ($column_name == "se-actions")
		{
			if ($taxonomy)
			{
				$term = get_term( $term_id, $taxonomy );
				if ($term)
				{
					$this->current_taxonomy = $this->se_cfg['data']['taxonomies'][$taxonomy];
					$this->se_display_taxonomy_term_action_row($taxonomy, $term);
				}
			}
		}
	}
	
	function se_display_post_type_column_actions($column_name, $post_id)
	{
		global $post_type;

		if ($column_name == "se-actions")
		{
			$this->current_post_type = $this->se_cfg['data']['post_types'][$post_type];

			if ($post_id)
			{
				$p_item = get_post( $post_id );
				if ($p_item)
				{				
					$this->se_display_post_type_action_row($post_type, $p_item);
				}
			}
		}
	}
	
	function se_display_user_column_actions($junk, $column_name, $user_id )
	{		
		if ($column_name == "se-actions")
		{
			$se_type = "users";
			if (isset($this->se_cfg['data']['se_types'][$se_type]))
			{
				ob_start();		

				$this->current_se_type = $this->se_cfg['data']['se_types'][$se_type];
				if ($user_id)
				{
					$user = get_userdata($user_id);
					if ($user)
					{				
						$this->se_display_user_action_row($se_type, $user);
					}
				}

				$out = ob_get_contents();
				ob_end_clean();
				return $out;
			}
		}
	}
	
	
	/****************************************************************************************************************************/
	/*																															*/
	/*												COLUMNS ACTION ROW															*/
	/*																															*/
	/****************************************************************************************************************************/
	
	function se_display_taxonomy_term_action_row($taxonomy='', $term='')
	{
		if (!$taxonomy) return;
		if (!$term) return;
		
		if ((isset($this->current_taxonomy['actions'])) && (count($this->current_taxonomy['actions'])))
		{
			foreach ($this->current_taxonomy['actions'] as $action_key => $action_val)
			{
				?>
				<input type="checkbox" 
					name="se_cfg[<?php echo $taxonomy; ?>][terms][<?php echo $action_key ?>][<?php echo $term->term_id ?>]"
					id="<?php echo $taxonomy; ?>-<?php echo $action_key ?>-<?php echo $term->term_id ?>" class="se-term-input"
					<?php
						if ((isset($this->current_taxonomy['terms'][$action_key][$term->term_id])) 
					 	 && ($this->current_taxonomy['terms'][$action_key][$term->term_id] == "on"))
							echo "checked='checked' ";
					?> />&nbsp;<label for="<?php echo $taxonomy; ?>-<?php echo $action_key ?>-<?php echo $term->term_id ?>" 
						class="se-term-label"><?php echo $this->get_taxonomy_action_label($taxonomy, $action_key, 'name'); ?></label><br />
										
				<?php
			}
		}
	}

	function se_display_post_type_action_row($post_type='', $p_item='')
	{
		if (!$post_type) return;
		if (!$p_item) return;

		if ((isset($this->current_post_type['actions'])) && (count($this->current_post_type['actions'])))
		{
			foreach ($this->current_post_type['actions'] as $action_key => $action_val)
			{
				?>
				<input type="checkbox" 
					name="se_cfg[<?php echo $post_type; ?>][terms][<?php echo $action_key ?>][<?php echo $p_item->ID ?>]"
					id="<?php echo $post_type; ?>-<?php echo $action_key ?>-<?php echo $p_item->ID ?>" class="se-term-input"
					<?php

					if ((isset($this->current_post_type['terms'][$action_key][$p_item->ID])) 
					 && ($this->current_post_type['terms'][$action_key][$p_item->ID] == "on"))
						echo "checked='checked' ";
					?> />&nbsp;<label for="<?php echo $post_type; ?>-<?php echo $action_key ?>-<?php echo $p_item->ID ?>">
							<?php echo $this->get_post_type_action_label($post_type, $action_key, 'name') ?></label><br />

				<?php
			}
		}
	}
	
	function se_display_user_action_row($se_type, $user)
	{
		if (!$se_type) return;
		if (!$user) return;

		// Ths current_se_type is set in the caller.
		if ((isset($this->current_se_type['actions'])) && (count($this->current_se_type['actions'])))
		{
			foreach ($this->current_se_type['actions'] as $action_key => $action_val)
			{
				?>
				<input type="checkbox" 
					name="se_cfg[<?php echo $se_type; ?>][terms][<?php echo $action_key ?>][<?php echo $user->ID ?>]"
					id="<?php echo $se_type; ?>-<?php echo $action_key ?>-<?php echo $user->ID ?>" class="se-term-input"
					<?php

					if ((isset($this->current_se_type['terms'][$action_key][$user->ID])) 
					 && ($this->current_se_type['terms'][$action_key][$user->ID] == "on"))
						echo "checked='checked' ";
					?> />&nbsp;<label for="<?php echo $se_type; ?>-<?php echo $action_key ?>-<?php echo $user->ID ?>">
							<?php echo $this->get_se_type_action_label($se_type, $action_key, 'name') ?></label><br />

				<?php
			}
		}
	}
	
	
	
	/****************************************************************************************************************************/
	/*																															*/
	/*												USER PROFILE																*/
	/*																															*/
	/****************************************************************************************************************************/
	
	function se_show_user_profile($profileuser)
	{
		$se_type = "users";
		$this->current_se_type = $this->se_cfg['data']['se_types'][$se_type];
		?>
		<table class="form-table">
		<tr>
			<th><label for="simply-exclude">Simply-Exclude</label></th>
			<td class="cat-action"><?php $this->se_display_user_action_row('users', $profileuser) ?></td>
		</tr>
		</table>
		
		<?php
		
	}
	
	function se_save_user_profile($user_id)
	{
		if (!$user_id)
			return;
						
		$this->se_load_config();				
		
		// First remove all traces of the post item in the actions
		$se_type = "users";
		
		if ((isset($this->se_cfg['data']['se_types'][$se_type]['terms'])) && (count($this->se_cfg['data']['se_types'][$se_type]['terms'])))
		{
			foreach($this->se_cfg['data']['se_types'][$se_type]['terms'] as $cfg_action => $cfg_action_items)
			{
				foreach($cfg_action_items as $action_id => $action_val)
				{
					if ($action_id == $_REQUEST['user_id'])
					{
						unset($this->se_cfg['data']['se_types'][$se_type]['terms'][$cfg_action][$action_id]);
					}
				}
			}
		}

		if (isset($_REQUEST['se_cfg']))
		{
			$se_cfg = $_REQUEST['se_cfg'];				

			// Now add back the items which were checked. 
			foreach($se_cfg as $se_type => $se_type_items)
			{				
				foreach($se_type_items as $term => $se_items)
				{
					foreach($se_items as $action => $action_items)
					{						
						foreach($action_items as $action_id => $action_val)
						{
							$this->se_cfg['data']['se_types'][$se_type][$term][$action][$action_id] = $action_val;
						}
					}
				}
			}
		}
		$this->se_save_config();				
	}


	

	/****************************************************************************************************************************/
	/*																															*/
	/*												POST DBX SIDEBAR FOR POST TYPES												*/
	/*																															*/
	/****************************************************************************************************************************/
			
	function show_post_type_exclude_sidebar_dbx()
	{
		global $post;
		
		if (isset($this->se_cfg['data']['post_types'][$post->post_type]))
		{
			$this->current_post_type = $this->se_cfg['data']['post_types'][$post->post_type];
			$this->se_display_post_type_action_row($post->post_type, $post);
		}
	}

	function save_post_type_exclude_sidebar_dbx($post_id)
	{
		if (!$post_id)
			return;
			
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
			return;

		if ( wp_is_post_revision( $post_id ) ) 
			return;
			
		$this->se_load_config();				
		
		if( (isset($_REQUEST['post_type'])) && (isset($_REQUEST['post_ID'])))
		{
			// First remove all traces of the post item in the actions
			$post_type = $_REQUEST['post_type'];
			if (isset($this->se_cfg['data']['post_types'][$post_type]['terms']))
			{
				foreach($this->se_cfg['data']['post_types'][$post_type]['terms'] as $cfg_action => $cfg_action_items)
				{
					foreach($cfg_action_items as $action_id => $action_val)
					{
						if ($action_id == $_REQUEST['post_ID'])
						{
							unset($this->se_cfg['data']['post_types'][$post_type]['terms'][$cfg_action][$action_id]);
						}
					}
				}
			}

			if (isset($_REQUEST['se_cfg']))
			{
				$se_cfg = $_REQUEST['se_cfg'];				
				
				// Now add back the items which were checked. 
				foreach($se_cfg as $post_type => $post_type_items)
				{				
					foreach($post_type_items as $term => $term_items)
					{
						foreach($term_items as $action => $action_items)
						{						
							foreach($action_items as $action_id => $action_val)
							{
								$this->se_cfg['data']['post_types'][$post_type][$term][$action][$action_id] = $action_val;
							}
						}
					}
				}
			}
		}
		$this->se_save_config();				
	}





	/****************************************************************************************************************************/
	/*																															*/
	/*												TAXONOMY EXTRA FIELDS														*/
	/*																															*/
	/****************************************************************************************************************************/

	function se_show_taxonomy_term_fields()
	{
		if (!isset($_REQUEST['tag_ID']))
			return;
		
		if (!isset($_REQUEST['taxonomy']))	
			return;
		
		$this->se_load_config();
			
		$this->current_taxonomy = $this->se_cfg['data']['taxonomies'][$_REQUEST['taxonomy']];
		$term = get_term_by("ID", $_REQUEST['tag_ID'], $_REQUEST['taxonomy']);
		?>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="description"><br />
			<?php _ex('Simply Exclude', 'Simply Exclude'); ?></label></th>
			<td class="cat-action"><?php $this->se_display_taxonomy_term_action_row($_REQUEST['taxonomy'], $term) ?></td>
		</tr>
		<?php
	}

	function se_save_taxonomy_term_fields($term_id, $tt_id, $taxonomy)
	{
		$this->se_load_config();				
		
		if ((isset($taxonomy)) && (isset($term_id)))
		{
			// First remove all traces of the taxonomy item in the actions
			foreach($this->se_cfg['data']['taxonomies'][$taxonomy]['terms'] as $cfg_action => $cfg_action_items)
			{
				foreach($cfg_action_items as $action_id => $action_val)
				{
					if ($action_id == $term_id)
					{
						unset($this->se_cfg['data']['taxonomies'][$taxonomy]['terms'][$cfg_action][$action_id]);
					}
				}
			}
		
			if (isset($_REQUEST['se_cfg']))
			{
				$se_cfg = $_REQUEST['se_cfg'];

				// Now add back the items which were checked. 
				foreach($se_cfg as $taxonomy => $taxonomy_items)
				{				
					foreach($taxonomy_items as $term => $term_items)
					{
						foreach($term_items as $action => $action_items)
						{						
							foreach($action_items as $action_id => $action_val)
							{
								$this->se_cfg['data']['taxonomies'][$taxonomy][$term][$action][$action_id] = $action_val;
							}
						}
					}
				}
			}
			$this->se_save_config();				
		}
	}


	/****************************************************************************************************************************/
	/*																															*/
	/*												LOAD CONFIG																	*/
	/*																															*/
	/****************************************************************************************************************************/

	function se_load_config()
	{		
		$tmp_se_cfg = get_option($this->options_key);
		//echo "se_cfg<pre>"; print_r($tmp_se_cfg); echo "</pre>";
		//die();
		
		//$tmp_se_cfg = '';
		if (!$tmp_se_cfg)
		{
			// If we don't find the main SE option then assume we are upgrading to grab via the old 'key'
			$tmp_se_cfg = get_option('simplyexclude');
			if (is_serialized($tmp_se_cfg))
				$this->se_cfg = unserialize($tmp_se_cfg);
			else
				$this->se_cfg = $tmp_se_cfg;
			
			if (isset($this->se_cfg['cfg']['myurl']))
				unset($this->se_cfg['cfg']['myurl']);
								
			// Here assumed older data structure. Need to convert the old array elements to match the new Taxonomy tags
			if (isset($this->se_cfg['cats']))
			{
				$this->se_cfg['data']['taxonomies']['category'] = $this->se_cfg['cats'];
				unset($this->se_cfg['cats']);
			}
			if (isset($this->se_cfg['tags']))
			{
				$this->se_cfg['data']['taxonomies']['post_tag'] = $this->se_cfg['tags'];
				unset($this->se_cfg['tags']);
			}
			if (isset($this->se_cfg['authors']))
			{
				$this->se_cfg['data']['se_types']['users'] = $this->se_cfg['authors'];
				unset($this->se_cfg['authors']);
			}
			if (isset($this->se_cfg['pages']))
			{
				$this->se_cfg['data']['post_types']['pages'] = $this->se_cfg['pages'];
				unset($this->se_cfg['pages']);
			}
/*			
			if (isset($this->se_cfg['options']['google-sitemap-generator']))
			{
				$this->se_cfg['options']['plugins']['google-sitemap-generator'] = $this->se_cfg['options']['google-sitemap-generator'];
				unset($this->se_cfg['options']['google-sitemap-generator']);

				if (!isset($this->se_cfg['options']['plugins']['google-sitemap-generator']['plugin_key']))
					$this->se_cfg['options']['plugins']['google-sitemap-generator']['plugin_key'] 	= "google-sitemap-generator/sitemap.php";				
			}

			if (isset($this->se_cfg['options']['search-unleashed']))
			{
				$this->se_cfg['options']['plugins']['search-unleashed'] = $this->se_cfg['options']['search-unleashed'];
				unset($this->se_cfg['options']['search-unleashed']);

				if (!isset($this->se_cfg['options']['plugins']['search-unleashed']['plugin_key']))
					$this->se_cfg['options']['plugins']['search-unleashed']['plugin_key'] 	= "search-unleashed/search-unleashed.php";
			}
*/
		}
		else if ($tmp_se_cfg)
		{
			if (is_serialized($tmp_se_cfg))
				$this->se_cfg = unserialize($tmp_se_cfg);
			else
				$this->se_cfg = $tmp_se_cfg;

			if (!isset($this->se_cfg['cfg']['version']))
				$this->se_cfg['cfg']['version'] = $this->se_version;
		}	

		$se_taxonomies = $this->se_load_taxonomy_list();
		if ($se_taxonomies)
		{
			foreach($se_taxonomies as $t_idx => $t_item)
			{
				if (!isset($this->se_cfg['data']['taxonomies'][$t_item->name]['actions']))
					$this->se_cfg['data']['taxonomies'][$t_item->name]['actions'] = array();
					
				$actions = $this->se_load_taxonomy_default_actions($t_item->name);
				if ($actions)
				{
					$this->se_cfg['data']['taxonomies'][$t_item->name]['actions'] = array_merge($actions, 
						$this->se_cfg['data']['taxonomies'][$t_item->name]['actions']);
				}

				ksort($this->se_cfg['data']['taxonomies'][$t_item->name]['actions']);
				
				if (!isset($this->se_cfg['data']['taxonomies'][$t_item->name]['options']))
					$this->se_cfg['data']['taxonomies'][$t_item->name]['options'] = array();
					
				if (!isset($this->se_cfg['data']['taxonomies'][$t_item->name]['options']['active']))
					$this->se_cfg['data']['taxonomies'][$t_item->name]['options']['active'] = 'yes';

				if (!isset($this->se_cfg['data']['taxonomies'][$t_item->name]['options']['showhide']))
					$this->se_cfg['data']['taxonomies'][$t_item->name]['options']['showhide'] = 'show';				

				if ($this->se_cfg['data']['taxonomies'][$t_item->name]['options']['showhide'] == "yes")
					$this->se_cfg['data']['taxonomies'][$t_item->name]['options']['showhide'] = 'show';				
				else if ($this->se_cfg['data']['taxonomies'][$t_item->name]['options']['showhide'] == "no")
					$this->se_cfg['data']['taxonomies'][$t_item->name]['options']['showhide'] = 'hide';
				
				$this->se_cfg['data']['taxonomies'][$t_item->name]['options']['hierarchical'] = $t_item->hierarchical;
					
			}
		}

		$se_post_types = $this->se_load_post_type_list();
		if ($se_post_types)
		{
			foreach($se_post_types as $t_idx => $t_item)
			{
				if (!isset($this->se_cfg['data']['post_types'][$t_item->name]['actions']))
					$this->se_cfg['data']['post_types'][$t_item->name]['actions'] = array();
					
				$actions = $this->se_load_post_type_default_actions($t_item->name);
				if ($actions)
				{
					$this->se_cfg['data']['post_types'][$t_item->name]['actions'] = array_merge($actions, 
						$this->se_cfg['data']['post_types'][$t_item->name]['actions']);
				}

				ksort($this->se_cfg['data']['post_types'][$t_item->name]['actions']);

				if (!isset($this->se_cfg['data']['post_types'][$t_item->name]['options']))
					$this->se_cfg['data']['post_types'][$t_item->name]['options'] = array();

				if (!isset($this->se_cfg['data']['post_types'][$t_item->name]['options']['active']))
					$this->se_cfg['data']['post_types'][$t_item->name]['options']['active'] = 'yes';
				if (!isset($this->se_cfg['data']['post_types'][$t_item->name]['options']['showhide']))
					$this->se_cfg['data']['post_types'][$t_item->name]['options']['showhide'] = 'show';						

				if ($this->se_cfg['data']['post_types'][$t_item->name]['options']['showhide'] == "yes")
					$this->se_cfg['data']['post_types'][$t_item->name]['options']['showhide'] = 'show';				
				else if ($this->se_cfg['data']['post_types'][$t_item->name]['options']['showhide'] == "no")
					$this->se_cfg['data']['post_types'][$t_item->name]['options']['showhide'] = 'hide';
				
				$this->se_cfg['data']['post_types'][$t_item->name]['options']['capability_type'] = $t_item->capability_type;
			}
		}

		$se_types = $this->se_load_se_type_list();
		if ($se_types)
		{
			foreach($se_types as $t_idx => $t_item)
			{
				if (!isset($this->se_cfg['data']['se_types'][$t_item['name']]['actions']))
					$this->se_cfg['data']['se_types'][$t_item['name']]['actions'] = array();
					
				$actions = $this->se_load_se_type_default_actions($t_item['name']);
				if ($actions)
				{
					$this->se_cfg['data']['se_types'][$t_item['name']]['actions'] = array_merge($actions, 
						$this->se_cfg['data']['se_types'][$t_item['name']]['actions']);
				}

				if (!isset($this->se_cfg['data']['se_types'][$t_item['name']]['options']))
					$this->se_cfg['data']['se_types'][$t_item['name']]['options'] = array();

				if (!isset($this->se_cfg['data']['se_types'][$t_item['name']]['options']['active']))
					$this->se_cfg['data']['se_types'][$t_item['name']]['options']['active'] = 'yes';

				if (!isset($this->se_cfg['data']['se_types'][$t_item['name']]['options']['showhide']))
					$this->se_cfg['data']['se_types'][$t_item['name']]['options']['showhide'] = 'show';						

				if ($this->se_cfg['data']['se_types'][$t_item['name']]['options']['showhide'] == "yes")
					$this->se_cfg['data']['se_types'][$t_item['name']]['options']['showhide'] = 'show';				
				else if ($this->se_cfg['data']['se_types'][$t_item['name']]['options']['showhide'] == "no")
					$this->se_cfg['data']['se_types'][$t_item['name']]['options']['showhide'] = 'hide';
			}
		}


		if (!isset($this->se_cfg['options']))
			$this->se_cfg['options'] 									= array();
/*
		$this->se_cfg['options']['plugins'] 							= array();
		
		$this->se_cfg['options']['plugins']['google-sitemap-generator'] 				= array();
		$this->se_cfg['options']['plugins']['google-sitemap-generator']['name'] 		= __("Google XML Sitemaps", SIMPLY_EXCLUDE_I18N_DOMAIN);
		$this->se_cfg['options']['plugins']['google-sitemap-generator']['url'] 			= "http://wordpress.org/extend/plugins/google-sitemap-generator/";
		$this->se_cfg['options']['plugins']['google-sitemap-generator']['desc'] 		= __("Warning: Page ID listed in the Sitemap plugin will be removed and replaced with Page ID from the Simply Exclude plugin. Post ID values will be ignored", SIMPLY_EXCLUDE_I18N_DOMAIN);
		$this->se_cfg['options']['plugins']['google-sitemap-generator']['version'] 		= "3.1.6";
		$this->se_cfg['options']['plugins']['google-sitemap-generator']['active'] 		= false;
		$this->se_cfg['options']['plugins']['google-sitemap-generator']['plugin_key'] 	= "google-sitemap-generator/sitemap.php";
			
		$this->se_cfg['options']['plugins']['google-sitemap-generator']['actions'] 							= array();
		$this->se_cfg['options']['plugins']['google-sitemap-generator']['actions']['pages']['desc'] 		= __("Update Excluded Pages", SIMPLY_EXCLUDE_I18N_DOMAIN);		
		$this->se_cfg['options']['plugins']['google-sitemap-generator']['actions']['pages']['update'] 		= false;		
		$this->se_cfg['options']['plugins']['google-sitemap-generator']['actions']['categories']['desc'] 	= __("Update Excluded Categories", SIMPLY_EXCLUDE_I18N_DOMAIN);		
		$this->se_cfg['options']['plugins']['google-sitemap-generator']['actions']['categories']['update'] 	= false;		
		
		
		$this->se_cfg['options']['plugins']['search-unleashed']						= array();
		$this->se_cfg['options']['plugins']['search-unleashed']['name']				= __("Search Unleashed", SIMPLY_EXCLUDE_I18N_DOMAIN);
		$this->se_cfg['options']['plugins']['search-unleashed']['url']				= "http://wordpress.org/extend/plugins/search-unleashed/";
		$this->se_cfg['options']['plugins']['search-unleashed']['desc']				= __("Warning: Page ID listed in the Search Unleashed plugin will be removed and replaced with Page ID from the Simply Exclude plugin. Post ID values will be ignored", SIMPLY_EXCLUDE_I18N_DOMAIN);
		$this->se_cfg['options']['plugins']['search-unleashed']['version']			= "1.0.5";
		$this->se_cfg['options']['plugins']['search-unleashed']['active']			= false;
		$this->se_cfg['options']['plugins']['search-unleashed']['plugin_key'] 		= "search-unleashed/search-unleashed.php";
			
		$this->se_cfg['options']['plugins']['search-unleashed']['actions'] 							= array();
		$this->se_cfg['options']['plugins']['search-unleashed']['actions']['pages']['desc'] 		= __("Update Excluded Pages", SIMPLY_EXCLUDE_I18N_DOMAIN);
		$this->se_cfg['options']['plugins']['search-unleashed']['actions']['pages']['update'] 		= false;
		$this->se_cfg['options']['plugins']['search-unleashed']['actions']['categories']['desc'] 	= __("Update Excluded Categories", SIMPLY_EXCLUDE_I18N_DOMAIN);
		$this->se_cfg['options']['plugins']['search-unleashed']['actions']['categories']['update'] 	= false;				
		$this->se_cfg['options']['plugins']['search-unleashed']['desc']								= __("Warning: Page ID listed in the Search Unleashed plugin will be removed and replaced with Page ID from the Simply Exclude plugin. Post ID values will be ignored", SIMPLY_EXCLUDE_I18N_DOMAIN);
		
		foreach($this->se_cfg['options']['plugins'] as $plugin => $plugin_data)
		{
			if ((isset($plugin_data['plugin_key'])) && (strlen($plugin_data['plugin_key'])))
			{
				if ($this->is_plugin_active($plugin_data['plugin_key'])) 
				{
					$this->se_cfg['options']['plugins'][$plugin]['active'] = true;
				}
			}
		}
*/		
//		echo "se_cfg<pre>"; print_r($this->se_cfg); echo "</pre>";		
	}	
	
	function is_plugin_active( $plugin ) {
		if (is_admin())
		{
			return is_plugin_active($plugin);
		}
		else
		{
			return in_array( $plugin, (array) get_option( 'active_plugins', array() ) ) || is_plugin_active_for_network( $plugin );
		}
	}
	

	function se_save_config()
	{
		$ret = update_option($this->options_key, serialize($this->se_cfg));
	}

/*
	function se_load_options()
	{
		$check_plugins = get_option('active_plugins');
		if ($check_plugins)
		{
			foreach($check_plugins as $plugin_item)
			{
				$plugin_path_prefix = explode('/', $plugin_item);
				if (isset($this->se_cfg['options'][$plugin_path_prefix[0]]))
					$this->se_cfg['options'][$plugin_path_prefix[0]]['status'] = true;
			}
		}
	}
*/	
	
	/****************************************************************************************************************************/
	/*																															*/
	/*												LOAD TYPES (TAX, POST, SE)													*/
	/*																															*/
	/****************************************************************************************************************************/
	
	function se_load_taxonomy_list()
	{
		$se_taxonomies = array();
		
		$taxonomies = get_taxonomies();
		if ( $taxonomies )
		{
			foreach($taxonomies as $tax_item)
			{
				if (array_search($tax_item, $this->se_taxonomies_exclude) !== false)
					continue;
				
				$tax_struct =  get_taxonomy($tax_item);
				if ($tax_struct)
				{
					$se_taxonomies[$tax_struct->labels->name] = $tax_struct;
				}				
			}
		}
		ksort($se_taxonomies);
		return $se_taxonomies;
	}
	
	function se_load_post_type_list()
	{
		$se_post_types = array();

		$post_types = get_post_types();
		if ( $post_types )
		{
			foreach($post_types as $post_type_item)
			{
				if (array_search($post_type_item, $this->se_post_types_exclude) !== false)
					continue;

				$post_type_struct =  get_post_type_object($post_type_item);
				if ($post_type_struct)
				{
					$se_post_types[$post_type_struct->labels->name] = $post_type_struct;
				}				
			}
		}
		ksort($se_post_types);
		return $se_post_types;
	}
	
	function se_load_se_type_list()
	{
		$se_types['users'] = array(
			'name'	=> 'users'
		);

		ksort($se_types);		
		return $se_types;
	}
	
	
	/****************************************************************************************************************************/
	/*																															*/
	/*												LOAD TYPE DEFAULT ACTIONS													*/
	/*																															*/
	/****************************************************************************************************************************/
	
	function se_load_taxonomy_default_actions($taxonomy)
	{
		if (!$taxonomy) return;
		
		$taxonomy_actions['is_home']['action'] 				= "i";
		$taxonomy_actions['is_archive']['action'] 			= "e";
		$taxonomy_actions['is_search']['action'] 			= "e";
		$taxonomy_actions['is_feed']['action'] 				= "e";
		
		if ($taxonomy == "category")
		{
			$taxonomy_actions['widget_category']['action'] 				= "e";			
			$taxonomy_actions['widget_tag_cloud']['action'] 			= "e";			
		}

		if ($taxonomy == "post_tag")
		{
			$taxonomy_actions['widget_tag_cloud']['action'] 			= "e";			
		}

		return $taxonomy_actions;
	}	


	function se_load_post_type_default_actions($post_type)
	{
		if (!$post_type) return;
		
		$taxonomy_actions = array();
		
		$post_type_object = get_post_type_object($post_type);

		if ($post_type_object->capability_type == "post")
		{			
			$taxonomy_actions['is_home']['action'] 				= "i";
			$taxonomy_actions['is_archive']['action'] 			= "e";
			$taxonomy_actions['is_search']['action'] 			= "e";
			$taxonomy_actions['is_feed']['action'] 				= "e";
		}
		else if ($post_type == "page")
		{			
			$taxonomy_actions['is_search']['action'] 			= "e";
			$taxonomy_actions['widget_pages']['action'] 		= "e";
		}
		else
		{		
			$taxonomy_actions['is_search']['action'] 			= "e";
		}
		return $taxonomy_actions;
	}	


	function se_load_se_type_default_actions()
	{
		$taxonomy_actions['is_home']['action'] 					= "i";
		$taxonomy_actions['is_archive']['action'] 				= "e";
		$taxonomy_actions['is_search']['action'] 				= "e";
		$taxonomy_actions['is_feed']['action'] 					= "e";

		return $taxonomy_actions;
	}
	
	
	
	/****************************************************************************************************************************/
	/*																															*/
	/*												ACTION LABELS																*/
	/*																															*/
	/****************************************************************************************************************************/
	
		function get_taxonomy_action_label($taxonomy, $action, $key)
	{
//		echo "taxonomy=[". $taxonomy ."]<br />";
//		echo "action=[". $action ."]<br />";
//		echo "key=[". $key ."]<br />";

		switch($action)
		{
			case 'is_home':

				switch($key)
				{
					case 'name':
						return __("Front/Home", SIMPLY_EXCLUDE_I18N_DOMAIN);;
						break;

					case 'description':
						return __("Visibility on the front/main page.", SIMPLY_EXCLUDE_I18N_DOMAIN);

					default:
						return;
				}
				return;
				break;

			case 'is_archive':

				switch($key)
				{
					case 'name':
						return __("Archives", SIMPLY_EXCLUDE_I18N_DOMAIN);
						break;

					case 'description':
						return __("Visibility on the archive of categories on the sidebar.", SIMPLY_EXCLUDE_I18N_DOMAIN);

					default:
						return;
				}
				return;
				break;


			case 'is_search':

				switch($key)
				{
					case 'name':
						return __("Searches", SIMPLY_EXCLUDE_I18N_DOMAIN);
						break;

					case 'description':
						return __("Visibility in search results.", SIMPLY_EXCLUDE_I18N_DOMAIN);

					default:
						return;
				}
				return;
				break;


			case 'is_feed':

				switch($key)
				{
					case 'name':
						return __("Feeds", SIMPLY_EXCLUDE_I18N_DOMAIN);
						break;

					case 'description':
						return __("Visibility in RSS/RSS2/Atom feeds.", SIMPLY_EXCLUDE_I18N_DOMAIN);

					default:
						return;
				}
				return;
				break;

			case 'widget_category':
				if ($taxonomy == "category")
				{
					switch($key)
					{
						case 'name':
							return __("Widget: Categories", SIMPLY_EXCLUDE_I18N_DOMAIN);
							break;

						case 'description':
							return __("Exclude from WordPress List Category Widget.", SIMPLY_EXCLUDE_I18N_DOMAIN);
							break;

						default:
							return;
							break;
					}
				}
				return;
				break;

			case 'widget_tag_cloud':
				if (($taxonomy == "post_tag") || ($taxonomy == "category"))
				{
					switch($key)
					{
						case 'name':
							return __("Widget: Tag Cloud", SIMPLY_EXCLUDE_I18N_DOMAIN);
							break;

						case 'description':
							return __("Exclude from WordPress Tag Cloud Widget.", SIMPLY_EXCLUDE_I18N_DOMAIN);
							break;

						default:
							return;
							break;
					}
				}
				return;
				break;

			default:
				return;
				break;
		}		
	}

	function get_post_type_action_label($post_type, $action, $key)
	{
//		echo "post_type=[". $post_type ."]<br />";
//		echo "action=[". $action ."]<br />";
//		echo "key=[". $key ."]<br />";

		switch($action)
		{
			case 'is_home':

				switch($key)
				{
					case 'name':
						return __("Front/Home", SIMPLY_EXCLUDE_I18N_DOMAIN);
						break;

					case 'description':
						return __("Visibility on the front/main page. Set 'Include All' to have this Post Type displayed in addition to the normal Posts.", SIMPLY_EXCLUDE_I18N_DOMAIN);

					default:
						return;
				}
				return;
				break;

			case 'is_archive':

				switch($key)
				{
					case 'name':
						return __("Archives", SIMPLY_EXCLUDE_I18N_DOMAIN);
						break;

					case 'description':
						return __("Visibility on the Post Type archive.", SIMPLY_EXCLUDE_I18N_DOMAIN);

					default:
						return;
				}
				return;
				break;

			case 'is_feed':

				switch($key)
				{
					case 'name':
						return __("Feeds", SIMPLY_EXCLUDE_I18N_DOMAIN);
						break;

					case 'description':
						return __("Visibility in RSS/RSS2/Atom feeds.", SIMPLY_EXCLUDE_I18N_DOMAIN);

					default:
						return;
				}
				return;
				break;

			case 'is_search':

				switch($key)
				{
					case 'name':
						return __("Searches", SIMPLY_EXCLUDE_I18N_DOMAIN);
						break;

					case 'description':
						return __("Visibility in search results.", SIMPLY_EXCLUDE_I18N_DOMAIN);

					default:
						return;
				}
				return;
				break;

			case 'widget_pages':
				switch($key)
				{
					case 'name':
						return __("Widget: Pages", SIMPLY_EXCLUDE_I18N_DOMAIN);
						break;

					case 'description':
						return __("Exclude from WordPress Pages Widget. Child pages are automatically excluded if the parent is Excluded.", SIMPLY_EXCLUDE_I18N_DOMAIN);

					default:
						return;
				}
				return;
				break;

			default:
				return;
				break;

		}
	}
	
	function get_se_type_action_label($se_type, $action, $key)
	{		
		switch($action)
		{
			case 'is_home':

				switch($key)
				{
					case 'name':
						return __("Front/Home", SIMPLY_EXCLUDE_I18N_DOMAIN);
						break;

					case 'description':
						return __("Visibility on the front/main page.", SIMPLY_EXCLUDE_I18N_DOMAIN);
						break;

					default:
						return;
				}
				return;
				break;

			case 'is_archive':

				switch($key)
				{
					case 'name':
						return __("Archives", SIMPLY_EXCLUDE_I18N_DOMAIN);
						break;

					case 'description':
						return __("Visibility on the archive of categories on the sidebar.", SIMPLY_EXCLUDE_I18N_DOMAIN);
						break;

					default:
						return;
				}
				return;
				break;

			case 'is_search':

				switch($key)
				{
					case 'name':
						return __("Searches", SIMPLY_EXCLUDE_I18N_DOMAIN);
						break;

					case 'description':
						return __("Visibility in search results.", SIMPLY_EXCLUDE_I18N_DOMAIN);
						break;

					default:
						return;
				}
				return;
				break;

			case 'is_feed':

				switch($key)
				{					
					case 'name':
						return __("Feeds", SIMPLY_EXCLUDE_I18N_DOMAIN);
						break;

					case 'description':
						return __("Visibility in RSS/RSS2/Atom feeds.", SIMPLY_EXCLUDE_I18N_DOMAIN);
						break;

					default:
						return;
				}
				return;
				break;

			default:
				return;
				break;
		}
	}


	/****************************************************************************************************************************/
	/*																															*/
	/*												SE FILTER LOGIC																*/
	/*																															*/
	/****************************************************************************************************************************/
	
	function se_filters($query) 
	{
		global $wp_query;

		if ($wp_query != $query)
			return $query;

		// Ignore all queries from within wp-admin. 
		if ($query->is_admin)
			return $query;

		$this->se_load_config();

//		echo "se_cfg<pre>"; print_r($this->se_cfg); echo "</pre>";
//		echo "query<pre>"; print_r($query); echo "</pre>";
//		echo "wp_query<pre>"; print_r($wp_query); echo "</pre>";
//		exit;
						
		$action_data = array();
		// Only filter on our actions.
		if (($query->is_home) || ($query->is_posts_page))
		{
			$action_data = $this->se_get_action_data('is_home');
		} 
		else if ($query->is_search) 
		{
			$action_data = $this->se_get_action_data('is_search');
		}
		else if ($query->is_archive)  
		{
			$action_data = $this->se_get_action_data('is_archive');			
		}
		else if ($query->is_feed)  
		{
			$action_data = $this->se_get_action_data('is_feed');
		}

		//echo "action_data<pre>"; print_r($action_data); echo "</pre>";
		if ($action_data)
		{
			$tax_query = array();
			$tax_query_relation = array();
//			echo "action_data<pre>"; print_r($action_data); echo "</pre>";
			foreach($action_data as $key => $key_data)
			{
//				echo "key=[". $key ."]<br />";
//				echo "key_data<pre>"; print_r($key_data); echo "</pre>";
				if ($key == "taxonomies")
				{
					foreach($key_data as $key_key => $key_key_data)
					{
						//echo "key_key=[". $key_key ."]<br />";
						//echo "key_key_data<pre>"; print_r($key_key_data); echo "</pre>";

						$tax_args = array(
							'taxonomy' 	=> $key_key,
							'field' 	=> 'id',
							'terms' 	=> $key_key_data['terms']
						);

						if ($key_key_data['actions'] == "e")
						{
							$tax_args['operator'] = "NOT IN";
							$tax_query_relation['NOT IN'] = "NOT IN";
						}
						else if ($key_key_data['actions'] == "i")
						{
							$tax_args['operator'] = "IN";
							$tax_query_relation['IN'] = "IN";
						}

						$tax_query[] = $tax_args;
					}
				}
				else if ($key == "post_types")
				{
					$post_types_array = array();
					$post__in = array();
					$post__not_in = array();
					foreach($key_data as $key_key => $key_key_data)
					{
						//echo "key_key=[". $key_key ."]<br />";
						//echo "key_key_data<pre>"; print_r($key_key_data); echo "</pre>";

						$post_types_array[] = $key_key;
						if ($key_key_data['actions'] == 'e')
							$post__not_in = array_merge($post__not_in, $key_key_data['terms']);
						else if ($key_key_data['actions'] == 'i')
							$post__in = array_merge($post__in, $key_key_data['terms']);								
					}
//					echo "post_types_array<pre>"; print_r($post_types_array); echo "</pre>";
//					echo "post__not_in<pre>"; print_r($post__not_in); echo "</pre>";
//					echo "post__in<pre>"; print_r($post__in); echo "</pre>";

					if (count($post__not_in))
					{
						//echo "PROCESSING: POST__NOT_IN<br />";
						//echo "post__not_in<pre>"; print_r($post__not_in); echo "</pre>";
						$query->set('post__not_in', $post__not_in);
					}
					else if (count($post__in))
					{
						//echo "PROCESSING: POST__IN<br />";
						//echo "post__in<pre>"; print_r($post__in); echo "</pre>";
						$query->set('post__in', $post__in);
					}

					$query_post_type = $query->get('post_type');
					if (!$query_post_type) $query_post_type = array();
					else if (!is_array($query_post_type))
					{
						$query_post_type[] = $query_post_type;
					}
					if ($post_types_array)
					{
						$query_post_type = array_unique(array_merge($post_types_array, $post_types_array));
						$query->set('post_type', $query_post_type);
					}
				}
				else if ($key == "se_types")
				{
					foreach($key_data as $key_key => $key_key_data)
					{
//						echo "key_key=[". $key_key ."]<br />";
//						echo "key_key_data<pre>"; print_r($key_key_data); echo "</pre>";

						if ($key_key == "users")
						{
							$user_ids = $this->se_listify_ids($key_key_data['terms'], $key_key_data['actions']);
							//echo "user_ids=[". $user_ids ."]<br />";
							if ($user_ids)
							{
								$query->set('author', $user_ids);
							}
						}
					}					
				}
			}
			if (count($tax_query))
			{
				if (count($tax_query_relation) == 2)
					$tax_query['relation'] = "OR";
				else
					$tax_query['relation'] = "AND";

				$query->set('tax_query', $tax_query);
			}
			//echo "query<pre>"; print_r($query); echo "</pre>";
		}

		return $query;
	}


	function se_get_action_data($action='')
	{
		if (!$action) return;

		$action_data = array();
		
		//echo "se_cfg taxonomies<pre>"; print_r($this->se_cfg['data']['taxonomies']); echo "</pre>";
		foreach($this->se_cfg['data']['taxonomies'] as $key => $data)
		{
			if ((isset($data['options']['active'])) && ($data['options']['active'] == 'yes'))
			{
				if ((!isset($data['terms'][$action])) || (!count($data['terms'][$action])))
					continue;

				foreach($data['terms'][$action] as $id => $val)
				{
					$action_data['taxonomies'][$key]['terms'][] 		= $id;				
				}
				$action_data['taxonomies'][$key]['actions'] 	= $data['actions'][$action]['action'];			
			}
		}

		//echo "se_cfg post_types<pre>"; print_r($this->se_cfg['data']['post_types']); echo "</pre>";
		foreach($this->se_cfg['data']['post_types'] as $key => $data)
		{
			if ((isset($data['options']['active'])) && ($data['options']['active'] == 'yes'))
			{
				if ((!isset($data['terms'][$action])) || (!count($data['terms'][$action])))
				{
					if ((isset($data['actions'][$action]['action'])) && ($data['actions'][$action]['action'] == 'a'))
					{
						$action_data['post_types'][$key]['actions'] 	= $data['actions'][$action]['action'];						
					}
					continue;
				}

				foreach($data['terms'][$action] as $id => $val)
				{
					$action_data['post_types'][$key]['terms'][] 		= $id;				
				}

				$action_data['post_types'][$key]['actions'] 	= $data['actions'][$action]['action'];			
			}
		}

		//echo "se_cfg se_types<pre>"; print_r($this->se_cfg['data']['se_types']); echo "</pre>";
		foreach($this->se_cfg['data']['se_types'] as $key => $data)
		{
			if ((isset($data['options']['active'])) && ($data['options']['active'] == 'yes'))
			{
				if ((!isset($data['terms'][$action])) || (!count($data['terms'][$action])))
					continue;

				foreach($data['terms'][$action] as $id => $val)
				{
					$action_data['se_types'][$key]['terms'][] 		= $id;				
				}

				$action_data['se_types'][$key]['actions'] 	= $data['actions'][$action]['action'];			
			}
			
		}
		//echo "action_data<pre>"; print_r($action_data); echo "</pre>";		
		return $action_data;
	}
	
	
	
	
	
	
	
	
	/****************************************************************************************************************************/
	/*																															*/
	/*												WIDGET FILTER LOGIC															*/
	/*																															*/
	/****************************************************************************************************************************/

	function se_widget_pages_args_proc($args)
	{
		//echo "args<pre>"; print_r($args); echo "</pre>";
		
		if (isset($args['exclude']))
		{
			if ((is_array($args['exclude'])) && (count($args['exclude'])))
				return $args;
			else if (strlen($args['exclude']))
				return $args;
		}

		if (isset($args['include']))
		{
			if ((is_array($args['include'])) && (count($args['include'])))
				return $args;
			else if (strlen($args['include']))
				return $args;
		}
					
		$this->se_load_config();

//		echo "se_cfg<pre>"; print_r($this->se_cfg['data']['post_types']['page']); echo "</pre>";
		if ( (isset($this->se_cfg['data']['post_types']['page']['terms']['widget_pages']))
		  && (count($this->se_cfg['data']['post_types']['page']['terms']['widget_pages'])) )
		{
			//echo "se_cfg<pre>"; print_r($this->se_cfg['data']['post_types']['page']); echo "</pre>";

			$action = $this->se_cfg['data']['post_types']['page']['actions']['widget_pages']['action'];
			$terms 	= $this->se_listify_ids(array_keys($this->se_cfg['data']['post_types']['page']['terms']['widget_pages']), 'i');
			//echo "terms<pre>"; print_r($terms); echo "</pre>";
			if ($action == "e")
				$args['exclude'] = $terms;
			else if ($action == 'i')
				$args['include'] = $terms;			
		}
		//echo "args AFTER<pre>"; print_r($args); echo "</pre>";
		return $args;
	}
		
	function se_widget_categories_dropdown_args_proc($args)
	{
		if ((isset($args['include'])) || (isset($args['exclude'])))
			return;
			
		$this->se_load_config();
		
		if ( (isset($this->se_cfg['data']['taxonomies']['category']['terms']['widget_category']))
		  && (count($this->se_cfg['data']['taxonomies']['category']['terms']['widget_category'])) )
		{
			$action = $this->se_cfg['data']['taxonomies']['category']['actions']['widget_category']['action'];
			$terms 	= $this->se_cfg['data']['taxonomies']['category']['terms']['widget_category'];

			$all_cat_ids = array();

			if ($action == 'e') 
			{
				$all_cat_ids = array_keys($terms);
			}
			else if ($action == 'i') 
			{
				$all_cat_ids = get_all_category_ids();
				if (!$all_cat_ids)
					$all_cat_ids = array();

				foreach($terms as $c_idx => $c_item)
				{
					$item_idx = array_search($c_idx, $all_cat_ids);
					if ($item_idx !== false)
						unset($all_cat_ids[$item_idx]);
				}
			}
			
			if ((isset($all_cat_ids)) && (count($all_cat_ids)))
			{
				$args['exclude'] = implode(',', $all_cat_ids);
			}
		}
		return $args;
	}
	
	// The tag Cloud widget now supports using either taxonomy (post_tag or category). 
	function se_widget_tag_cloud_args_proc($args)
	{
		if ((isset($args['include'])) || (isset($args['exclude'])))
			return $args;
		
		if (!isset($args['taxonomy']))
			return $args;
			
		if ( ($args['taxonomy'] != "category") && ($args['taxonomy'] != "post_tag") ) 
			return $args;
			
		$this->se_load_config();
		
		switch ($args['taxonomy'])
		{
			case 'post_tag':
			
				if ( (isset($this->se_cfg['data']['taxonomies']['post_tag']['terms']['widget_tag_cloud']))
				  && (count($this->se_cfg['data']['taxonomies']['post_tag']['terms']['widget_tag_cloud'])) )
				{
					$action = $this->se_cfg['data']['taxonomies']['post_tag']['actions']['widget_tag_cloud']['action'];
					$terms 	= $this->se_cfg['data']['taxonomies']['post_tag']['terms']['widget_tag_cloud'];

					$all_tags = get_tags('hide_empty=0&orderby=name&order=ASC');			
					$all_tag_ids = array();
					if ($all_tags)
					{
						foreach($all_tags as $t_item)
						{
							$all_tag_ids[] = $t_item->term_id;
						}
					}			

					if (count($all_tag_ids))
					{
						if ($action == 'e') 
						{
							$all_tag_ids = array_keys($terms);
						}
						else if ($action == 'i') 
						{
							foreach($terms as $c_idx => $c_item)
							{
								$item_idx = array_search($c_idx, $all_tag_ids);
								if ($item_idx !== false)
									unset($all_tag_ids[$item_idx]);
							}
						}

						if ((isset($all_tag_ids)) && (count($all_tag_ids)))
						{
							$args['exclude'] = implode(',', $all_tag_ids);
						}
					}
				}				
				break;
				
			case 'category':
				if ( (isset($this->se_cfg['data']['taxonomies']['category']['terms']['widget_tag_cloud']))
				  && (count($this->se_cfg['data']['taxonomies']['category']['terms']['widget_tag_cloud'])) )
				{
					$action = $this->se_cfg['data']['taxonomies']['category']['actions']['widget_tag_cloud']['action'];
					$terms 	= $this->se_cfg['data']['taxonomies']['category']['terms']['widget_tag_cloud'];

					$all_cat_ids = array();

					if ($action == 'e') 
					{
						$all_cat_ids = array_keys($terms);
					}
					else if ($action == 'i') 
					{
						$all_cat_ids = get_all_category_ids();
						if (!$all_cat_ids)
							$all_cat_ids = array();

						foreach($terms as $c_idx => $c_item)
						{
							$item_idx = array_search($c_idx, $all_cat_ids);
							if ($item_idx !== false)
								unset($all_cat_ids[$item_idx]);
						}
					}

					if ((isset($all_cat_ids)) && (count($all_cat_ids)))
					{
						$args['exclude'] = implode(',', $all_cat_ids);
					}
				}
				
				break;
				
			default:
				break;
				
		}
		return $args;
	}
	
	
	
		
	function se_admin_footer()
	{
		if ( !current_user_can('manage_options') )
			return;

		if ($this->check_url('wp-admin/edit-tags.php'))		
		{
			global $taxonomy;
			?>
			<div id="se-actions-panel" style="display: none">
				<?php
					if (isset($taxonomy))
					{
						$this->display_instructions('taxonomy');
						$this->se_show_taxonomy_actions_panel($taxonomy);
					}
				?>
			</div><?php
		}
		else if ($this->check_url('wp-admin/edit.php'))		
		{
			global $post_type;
			?>
			<div id="se-actions-panel" style="display: none">
				<?php
					if (isset($post_type))
					{
						$this->display_instructions('post_type');
						$this->se_show_post_type_actions_panel($post_type);
					}
				?>
			</div>
			<?php
		}
		else if ($this->check_url('wp-admin/users.php'))
		{
			?>
			<div id="se-actions-panel" style="display: none">
				<?php
					$this->display_instructions('users');
					$this->se_show_se_type_actions_panel('users');
				?>
			</div>
			<?php
		}
	}
	
	function se_ajax_update() {

		//echo "_REQUEST<pre>"; print_r($_REQUEST); echo "</pre>";
		//die();
		if ( !current_user_can('manage_options') )
			die();
			
		if ((isset($_REQUEST['se_action'])) && ($_REQUEST['se_action'] == "se_update_terms"))
		{
			if (!isset($_REQUEST['is_checked'])) die();
			if (($_REQUEST['is_checked'] != 'yes') && ($_REQUEST['is_checked'] != 'no')) die();

			$is_checked = $_REQUEST['is_checked'];
			
			if (isset($_REQUEST['se_cfg']))
			{
				$this->se_load_config();
				$se_cfg = $_REQUEST['se_cfg'];
				
				$arg_parts = explode('[', $se_cfg);
				foreach($arg_parts as $idx => $val)
				{
					$arg_parts[$idx] = str_replace(']', '', $val);
				}
				//echo "arg_parts<pre>"; print_r($arg_parts); echo "</pre>";
				if ($arg_parts[1] == "users") // Is a User
				{
					$se_type 	= $arg_parts[1];
					$action		= $arg_parts[3];
					$user_id	= $arg_parts[4];

					//echo "se_cfg BEFORE<pre>"; print_r($this->se_cfg['data']['se_types'][$se_type]); echo "</pre>";
					if ($is_checked == 'yes')
					{
						if (!isset($this->se_cfg['data']['se_types'][$se_type]['terms'][$action]))
							$this->se_cfg['data']['se_types'][$post_type]['terms'][$action] = array();
						$this->se_cfg['data']['se_types'][$se_type]['terms'][$action][$user_id] = "on";
					}
					else
					{
						if (isset($this->se_cfg['data']['se_types'][$se_type]['terms'][$action][$user_id]))
							unset($this->se_cfg['data']['se_types'][$se_type]['terms'][$action][$user_id]);
					}
					//echo "se_cfg AFTER<pre>"; print_r($this->se_cfg['data']['se_types'][$se_type]); echo "</pre>";
					$this->se_save_config();
					echo "SUCCESS";
					die();
				}
				else if ($arg_parts[1] == "plugins")
				{					
					if ($is_checked == "yes")
						$this->se_cfg['options'][$arg_parts[1]][$arg_parts[2]]['actions'][$arg_parts[3]][$arg_parts[4]] = true;
					else
						$this->se_cfg['options'][$arg_parts[1]][$arg_parts[2]]['actions'][$arg_parts[3]][$arg_parts[4]] = false;
						
					$this->se_save_config();
					echo "SUCCESS";
					die();
				}
				else if (post_type_exists($arg_parts[1])) // Is a Post Type
				{
					$post_type 	= $arg_parts[1];
					$action		= $arg_parts[3];
					$term_id	= $arg_parts[4];
					
					if ($is_checked == 'yes')
					{
						if (!isset($this->se_cfg['data']['post_types'][$post_type]['terms'][$action]))
							$this->se_cfg['data']['post_types'][$post_type]['terms'][$action] = array();
						$this->se_cfg['data']['post_types'][$post_type]['terms'][$action][$term_id] = "on";
					}
					else
					{
						if (isset($this->se_cfg['data']['post_types'][$post_type]['terms'][$action][$term_id]))
							unset($this->se_cfg['data']['post_types'][$post_type]['terms'][$action][$term_id]);
					}
					$this->se_save_config();
					echo "SUCCESS";
					die();
				}
				else if (taxonomy_exists($arg_parts[1])) // Else is a Taxonomy
				{
					$taxonomy 	= $arg_parts[1];
					$action		= $arg_parts[3];
					$term_id	= $arg_parts[4];

					if ($is_checked == 'yes')
					{
						if (!isset($this->se_cfg['data']['taxonomies'][$taxonomy]['terms'][$action]))
							$this->se_cfg['data']['taxonomies'][$taxonomy]['terms'][$action] = array();
						$this->se_cfg['data']['taxonomies'][$taxonomy]['terms'][$action][$term_id] = "on";
					}
					else
					{
						if (isset($this->se_cfg['data']['taxonomies'][$taxonomy]['terms'][$action][$term_id]))
							unset($this->se_cfg['data']['taxonomies'][$taxonomy]['terms'][$action][$term_id]);
					}
					$this->se_save_config();
					echo "SUCCESS";
					die();
				}
			}
		}
		else if ((isset($_REQUEST['se_action'])) && ($_REQUEST['se_action'] == "se_update_actions"))
		{			
			if (!isset($_REQUEST['is_checked']))
				die();
				
			//if (($_REQUEST['is_checked'] != 'i') && ($_REQUEST['is_checked'] != 'e'))
			//	die();
				
			$is_checked = $_REQUEST['is_checked'];
			
			if (isset($_REQUEST['se_cfg']))
			{
				$this->se_load_config();
				$se_cfg = $_REQUEST['se_cfg'];
				$arg_parts = explode('[', $se_cfg);

				foreach($arg_parts as $idx => $val)
				{
					$arg_parts[$idx] = str_replace(']', '', $val);
				}
				//echo "arg_parts<pre>"; print_r($arg_parts); echo "</pre>";
				
				if ($arg_parts[1] == "users")
				{
					$se_type 	= $arg_parts[1];
					$option 	= $arg_parts[2];
					$action		= $arg_parts[3];

					if ($option == "actions")
					{
						if (!isset($this->se_cfg['data']['se_types'][$se_type][$option]))
							$this->se_cfg['data']['se_types'][$se_type][$option] = array();

						if (!isset($this->se_cfg['data']['se_types'][$se_type][$option][$action]))
							$this->se_cfg['data']['se_types'][$se_type][$option][$action] = array();
					
						$this->se_cfg['data']['se_types'][$se_type][$option][$action]['action'] = $is_checked;
					}
					else if ($option == "options")
					{
						$this->se_cfg['data']['se_types'][$se_type][$option][$action] = $is_checked;
					}
					
					$this->se_save_config();
					echo "SUCCESS";
					die();
				}
				else if (post_type_exists($arg_parts[1])) // Is a Post Type
				{
					$post_type 	= $arg_parts[1];
					$option 	= $arg_parts[2];
					$action		= $arg_parts[3];

					if ($option == "actions")
					{
						if (!isset($this->se_cfg['data']['post_types'][$post_type][$option]))
							$this->se_cfg['data']['post_types'][$post_type][$option] = array();

						if (!isset($this->se_cfg['data']['post_types'][$post_type][$option][$action]))
							$this->se_cfg['data']['post_types'][$post_type][$option][$action] = array();
					
						$this->se_cfg['data']['post_types'][$post_type][$option][$action]['action'] = $is_checked;
					}
					else if ($option == "options")
					{
						$this->se_cfg['data']['post_types'][$post_type][$option][$action] = $is_checked;
					}
					$this->se_save_config();
					echo "SUCCESS";
					die();
				}
				else if (taxonomy_exists($arg_parts[1])) // Else is a Taxonomy
				{
					$taxonomy 	= $arg_parts[1];
					$option 	= $arg_parts[2];
					$action		= $arg_parts[3];

					if ($option == "actions")
					{
						if (!isset($this->se_cfg['data']['taxonomies'][$taxonomy][$option]))
							$this->se_cfg['data']['taxonomies'][$taxonomy][$option] = array();

						if (!isset($this->se_cfg['data']['taxonomies'][$taxonomy][$option][$action]))
							$this->se_cfg['data']['taxonomies'][$taxonomy][$option][$action] = array();
					
						$this->se_cfg['data']['taxonomies'][$taxonomy][$option][$action]['action'] = $is_checked;
					}
					else if ($option == "options")
					{
						$this->se_cfg['data']['taxonomies'][$taxonomy][$option][$action] = $is_checked;
					}
					$this->se_save_config();
					echo "SUCCESS";
					die();
				}
			}
		}
		die(); // this is required to return a proper result
	}





		

	function display_instructions($item)
	{
		switch ($item) {
			case 'users':
				?>
				<p><?php _e("Set the checkbox to exclude the User items from the action", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></p>
				<p><?php _e("So what is the difference between <strong>Include only</strong> and <strong>Exclude</strong>?", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></p>
				<p><strong><?php _e("Include only", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></strong>: <?php _e("For example you have 3 Users but always want to show Posts for only 1  specific User in the Archives. New Users are automatically hidden.", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></p>
				<p><strong><?php _e("Exclude", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></strong>: <?php _e("For example you have 3 Users but want to hide Posts from one User in the Archives. New Users will by visible.", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></p>
				
				<?php
				break;
				
			case 'taxonomy':
				?>
				<p><?php _e("Set the checkbox to exclude the Taxonomy items from the action", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></p>
				<p><?php _e("So what is the difference between <strong>Include only</strong> and <strong>Exclude</strong>?", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></p>
				<p><strong><?php _e("Include only", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></strong>: <?php _e("For example you have 100 tags but only always want 3 to be used on the home page. New tags added are automatically hidden.", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></p>
				<p><strong><?php _e("Exclude", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></strong>: <?php _e("For example you have 100 categories but want to hide 3 from being seen. New tags will be visible.", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></p>
				<?php
				break;
				
			case 'post_type':
				?>
				<p><?php _e("Set the checkbox to exclude the Post Type items from the action", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></p>
				<p><?php _e("So what is the difference between <strong>Include only</strong> and <strong>Exclude</strong>?", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></p>
				<p><strong><?php _e("Include only", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></strong>: <?php _e("For example you have 10 Pages but only always want to include only 4 Pages shown in a Search. New Pages are automatically hidden.", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></p>
				<p><strong><?php _e("Exclude", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></strong>: <?php _e("For example you have 10 Pages and want to hide 3 specific Pages from Search. New Pages will be visible.", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></p>
				<?php
				break;
				
			default:
				break;
		}
	}

	//executed to show the plugins complete admin page
	function se_manage_settings()
	{
		?>
		<div id="howto-se-manage-settings-metaboxes-general" class="wrap">
		<?php screen_icon('options-general'); ?>
		<h2><?php _ex("Simply Exclude Manage Settings", "Options Page Title", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></h2>
		
			<div id="poststuff" class="metabox-holder has-right-sidebar simnplyexclude-metabox-holder-right-sidebar">
				<div id="side-info-column" class="inner-sidebar">
					<?php do_meta_boxes($this->pagehooks['se_manage_settings'], 'side', ''); ?>
				</div>
				<div id="post-body" class="has-sidebar ">
					<div id="post-body-content" class="has-sidebar-content simnplyexclude-metabox-holder-main">
						<?php do_meta_boxes($this->pagehooks['se_manage_settings'], 'normal', ''); ?>
					</div>
				</div>
			</div>	
		</div>
		<script type="text/javascript">
			//<![CDATA[
			jQuery(document).ready( function($) {
				// close postboxes that should be closed
				$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
				// postboxes setup
				postboxes.add_postbox_toggles('<?php echo $this->pagehooks['se_manage_settings']; ?>');
			});
			//]]>
		</script>		
		<?php
	}

	function se_display_options_taxonomy_actions_panel()
	{
		$this->display_instructions('taxonomy'); 
		$se_taxonomies = $this->se_load_taxonomy_list();
		if ($se_taxonomies)
		{
			foreach($se_taxonomies as $taxonomy)
			{
				?><h4 class="simplyexclude-section-title"><?php echo $taxonomy->labels->name; ?></h4><?php
				$this->se_show_taxonomy_active_panel($taxonomy->name);
				$this->se_show_taxonomy_actions_panel($taxonomy->name);
				$this->se_show_taxonomy_showhide_panel($taxonomy->name);				
			}
		}
	}

	function se_display_options_post_type_actions_panel()
	{
		$this->display_instructions('post_type');
		$se_post_types = $this->se_load_post_type_list();
		if ($se_post_types)
		{
			foreach($se_post_types as $post_type)
			{
				?><h4 class="simplyexclude-section-title"><?php echo $post_type->labels->name; ?></h4><?php
				$this->se_show_post_type_active_panel($post_type->name);
				$this->se_show_post_type_actions_panel($post_type->name);
				$this->se_show_post_type_showhide_panel($post_type->name);
			}
		}				
	}
	
	function se_display_options_user_actions_panel()
	{
		$this->display_instructions('users');
		?><h4 class="simplyexclude-section-title">Users</h4><?php
		$this->se_show_se_type_active_panel('users');
		$this->se_show_se_type_actions_panel('users');
		$this->se_show_se_type_showhide_panel('users');
	}
	
	function se_options_thirdparty_panel()
	{
		?>
		<p><?php _e('The Simply Exclude plugin now works with a few other plugins. Check the box for support of the listed third party plugins options below', SIMPLY_EXCLUDE_I18N_DOMAIN); ?></p>
		<p><?php _e('When you update this section you will then also need to go back into the Simply Exclude Category or Pages section and re-save the settings. This re-save will then update the third-party plugin settings with the update excluded values. On the respective Category or Pages sections of Simply Exclude you can use either include or exclude action.', SIMPLY_EXCLUDE_I18N_DOMAIN); ?></p>
		<p style="color: #ff0000"><?php _e('Warning: Once enabled it is suggested you make edits to the exclusion/inclusion via Simply Exclude. Any Page or Category exclusion made in the third-party plugins will be over written by changed from Simply Exclude.', SIMPLY_EXCLUDE_I18N_DOMAIN); ?></p>

		<table id="simplyexclude-options-thirdparty-panel" class="widefat simplyexclude-actions-panel" cellpadding="3" cellspacing="3" border="0">
		<thead>
        <tr>
        	<th class="action"><?php _e('Plugin Name', SIMPLY_EXCLUDE_I18N_DOMAIN) ?></th>
        	<th class="description"><?php _e('Description of Functionality', SIMPLY_EXCLUDE_I18N_DOMAIN) ?></th>
        	<th class="inc-excl"><?php _e('Actions', SIMPLY_EXCLUDE_I18N_DOMAIN) ?></th>
        </tr>
		</thead>
		<tbody>
		<?php
			$class = "";
			//echo "se_cfg<pre>"; print_r($this->se_cfg['options']); echo "</pre>";
			foreach ($this->se_cfg['options']['plugins'] as $option_key => $options_set)
			{
				//echo "option_key=[". $option_key ."]<br />";
				//echo "options_set<pre>"; print_r($options_set); echo "</pre>";

				$class = ('alternate' == $class) ? '' : 'alternate';
				?>
				<tr <?php if (strlen($class)) echo "class='".$class."'" ?>>
					<td class="action"><a href="<?php echo $options_set['url'] ?>"><?php echo $options_set['name'] ?></a></td>
					<td class="description"><?php echo $options_set['desc'] ?></td>
					<td class="inc-excl column-se-actions" nowrap="nowrap">
						<?php
						if ($options_set['active'] !== true)
						{
							?>This plugin is not installed or not active.<?php
						}
						else
						{
							if (count($options_set['actions']))
							{
								foreach($options_set['actions'] as $option_actions_idx => $option_actions_set)
								{
									?>
									<input type="checkbox" class="se-term-input"
										name="se_cfg[plugins][<?php echo $option_key; ?>][<?php 
											echo $option_actions_idx; ?>][update]"
										<?php if ($option_actions_set['update'] === true) 
											echo "checked='checked'"; ?> /> <?php echo $option_actions_set['desc']?><br />
									<?php
								}
							}
						}
						?>

					</td>
				</tr>					
				<?php
			}
		?>
		</tbody>
		</table>		

		<?php
	}
	
	function se_settings_about_sidebar()
	{
		?><p><a class="" target="_blank" href="http://www.codehooligans.com/projects/wordpress/simply-exclude/"><?php 
			_e('Plugin Homepage', SIMPLY_EXCLUDE_I18N_DOMAIN); ?></a></p><?php
		
	}
	function se_settings_donate_sidebar()
	{
		?>
		<p><?php _e('Show your support of this plugin by making a small donation to support future development. No donation amount too small.',
		 	SIMPLY_EXCLUDE_I18N_DOMAIN); ?></p>
		<p><a class="" target="_blank" href="http://www.codehooligans.com/donations/"><?php 
			_e('Make a donation today', SIMPLY_EXCLUDE_I18N_DOMAIN); ?></a></p>
		<?php
	}
	
	function se_manage_help()
	{
		?>
		<div id="se-manage-help-metaboxes-general" class="wrap">
		<?php screen_icon('options-general'); ?>
		<h2><?php _ex("Simply Exclude Help", "Options Page Title", SIMPLY_EXCLUDE_I18N_DOMAIN); ?></h2>
		
			<div id="poststuff" class="metabox-holder has-right-sidebar simnplyexclude-metabox-holder-right-sidebar">
				<div id="side-info-column" class="inner-sidebar">
					<?php do_meta_boxes($this->pagehooks['se_manage_help'], 'side', ''); ?>
				</div>
				<div id="post-body" class="has-sidebar ">
					<div id="post-body-content" class="has-sidebar-content simnplyexclude-metabox-holder-main">
						<?php //do_meta_boxes($this->pagehooks['se_manage_help'], 'normal', ''); ?>
						<?php $this->se_settings_help_faq_topics(); ?>
					</div>
				</div>
			</div>	
		</div>
		
		<script>
			jQuery(document).ready(function() {
				jQuery( "#se-accordion" ).accordion();
			});
		</script>
		<?php
	}

	function se_settings_help_faq_topics()
	{
		?>
		<div id="se-accordion">
			<h3><a href="#">How do I setup Simply Exclude Plugin to Exclude/Include Category items from the Archive?</a></h3>
			<div>
				<p>Simply follow these steps</p>
				<ol>
					<li>Go to the Simply Exclude <a href="admin.php?page=se_manage_settings">Settings panel</a>. Location the Category you want to manage</li>
					<li>On the sub-panel ensure the Category is <strong>Active</strong>.</li>
					<li>Next, find the row for <strong>Archives</strong>. Ensure the selection is set to <strong>Exclude</strong></li>
					<li>Finally, ensure the <strong>Show/Hide</strong> option is set to <strong>Show</strong>.</li>
					<li>Now go to the WordPress <a href="edit-tags.php?taxonomy=category">Category panel</a>. Locate the row of the Category term you want to exclude. Within the <strong>Simply Exclude</strong> column  set the checkbox for <strong>Archives</strong></li>
					<li>Done. At this point you have set the selected Category to exclude from the Archives listing.</li>
				</ol>
			</div>
			<h3><a href="#">Under Settings what does the Active/Disabled options do?</a></h3>
			<div>
				<p>On the Simply Exclude <a href="admin.php?page=se_manage_settings">Settings panel</a> you will see a listing of all Taxonomies and Post Types as well as Users and supported plugins. For each item you will see a set of Active/Disabled radio buttons.<p>
				<p><strong>Active</strong>: If you set the state to <strong>Active</strong> you are telling the plugin code you want to allow filtering of this Taxonomy/Post Type.</p>
				<p><strong>Disabled</strong>: If you set the state to <strong>Disabled</strong> you are instead telling the plugin to not filter this Taxonomy/Post Type. </p>
			</div>

			<h3><a href="#">Under Settings what does the Show/Hide options do?</a></h3>
			<div>
				<p>On the Simply Exclude <a href="admin.php?page=se_manage_settings">Settings panel</a> you will see a listing of all Taxonomies and Post Types as well as Users and supported plugins. For each item you will see a set of Show/Hide radio buttons.<p>
				<p><strong>Show</strong>: If you set the state to <strong>Show</strong> the plugin code will add a new column to the Taxonomy/Post Type listing tables. The plug will also add a new set of fields to the Taxonomy/Post Type editor form.</p>
				<p><strong>Disabled</strong>: If you set the state to <strong>Disabled</strong> the plugin will not add a new column to the Taxonomy/Post Type listing tables. Nor will it add a new set of fields to the Taxonomy/Post Type editor form.</p>
			</div>
			
			<h3><a href="#">I'm still confuses on the concepts of 'Include only' vs. 'Exclude' vs. 'Include all' action states</a></h3>
			<div>
				<p><strong>Include only</strong>: This setting tells the plugin to include only these selected items within the filtering logic. Let us take a example. Assume you have 100 Category terms and you want to only show 3 of these Category terms on the Front/Home post listing. You could setup the logic to exclude the other 97 Category terms. The problem with this is when you are more categories which you also don't want show on the Front/Home listing you will need to remember to exclude these. Instead the <strong>Include only</strong> lets you set only the 3 Categories to be included. Now as more Categories are added they will automatically be excluded from the Front/Home listing.</p>
								
				<p><strong>Exclude</strong>: This action state is the simpler to understand. Quite simply you set this to Exclude Taxonomy or Post Type items from the available actions, Archive, Feeds, Searches, etc.</p>
				<p><strong>Include all</strong>: This is a special action state only available to Post Types compatible with <strong>Post</strong>. When using this special action state you can include the Post Type into the output of the other Post Types. For example in the default WordPress setup a site will display the latest Posts on the front page. Now assume you have a Custom Post Type 'Books' you also want to show on the front page. To accomplish this you would set both <strong>Posts</strong> and <strong>Books</strong> Post Types to the <strong>Include All</strong> action state.</p>
			</div>
			
			<h3><a href="#">Can I use the Simply Exclude plugin to include other Post Types on my front page?</a></h3>
			<div>
				<p>Short answer, YES! Longer answer. This can be done but you need to be careful with the setup.</p>

				<p>First, some assumptions about your WordPress setup. You MUST be able to answer <strong>YES</strong> to the following</p>
				<ol>
					<li>The first assumption is you have your WordPress system setup to show your latest posts on your Home page and not a static Page.</li>
					<li>Next, it is assumed you have setup a Custom Post Type. And this Post Type is compatible with the legacy <strong>Post</strong> type and not <strong>Page</strong> type.</li>
				</ol>
				
				<p>Here is the setup</p>
				<ol>
					<li>Go to the Simply Exclude <a href="admin.php?page=se_manage_settings">Settings panel</a>. Location the Post Type you want to manage</li>
					<li>On the sub-panel ensure the Post Type is <strong>Active</strong>.</li>
					<li>Next, find the row for <strong>Front/Home</strong>. Ensure the selection is set to <strong>Include All</strong></li>
					<li>Finally, ensure the <strong>Show/Hide</strong> option is set to <strong>Show</strong>.</li>
					<li>Now navigate to the Post Type listing. This is important. You must ensure no items are set to <strong>Include Only</strong> or <strong>Exclude</strong>.</li> 
				</ol>
				
				<p>Note you can also use the <strong>Include all</strong> on the Feeds actions for Post Types</p>
			</div>

			<h3><a href="#">I've configured the plugin to Include/Exclude a combination of Taxonomies and Post Types. Now my site is all messed up. How do I reset things to the default?</a></h3>
			<div>
				<p>My suggestion is to start on the Simply Exclude <a href="admin.php?page=se_manage_settings">Settings panel</a>. For each Taxonomy and Post Type set the Active state to <strong>Disabled</strong>. Then one at a time enable a Taxonomy and check your site. Depending on the number of Taxonomies and Custom Post Types you may be introducing a conflict my including one set of items then excluding them at the same time.</p>
			</div>
			
			<h3><a href="#">I've excluded all my categories and all tags. Why am I seeing a 404 page?</a></h3>
			<div>
				<p>Well you need to be careful when excluding both categories and tags. Since a post can be associated with both Categories and Tags there is potential that you have excluded all your posts because they are either members of excluded categories and/or members or excluded tags.</p>
			</div>
			
			<h3><a href="#">I've excluded Pages but attachments (images) for those pages are showing up. Why?</a></h3>
			<div>
				<p>Only the parent Page itself is excluded from searches. By default WordPress does not yet include Pages in search. Make sure you have other search plugins correctly configured to not search attachments.</p>
			</div>

			<h3><a href="#">I've excluded a Category/Tag via the plugin but it still shows up in my sidebar Widgets. Why?</a></h3>
			<div>
				<p>The Simply Exclude plugin can filter for the default WordPress Categories and Tag Cloud widgets. Note the newest version of WordPress (3.3) supports using either Tags or Categories for the Tag Cloud widget.</p>
				<p>To set the Exclude/Include actions to work with these widgets look for the <strong>Widget: Categories</strong> and <strong>Widget: Tag Cloud</strong> actions. Then set them accordingly</p>
				<p>If you have set these up and your widgets are still showing excluded terms then double check your theme. If you are not using the WordPress version of the widget of widgets which are included as part of your theme then there is a chance they are bypassing the filtering logic required for using the Simply Exclude plugin. Sorry to say this is beyond my control since I cannot maintain support for the many available Category or Tag Cloud widgets other developers create.</p>
			</div>

			<h3><a href="#">I've installed your plugin and now my site will not load. How to I remove the plugin?</a></h3>
			<div>
				<p>First, I'm sorry to hear my plugin effected your site. I try to test for most combinations but that is no substitute for a <strong>real</strong> system. To remove the plugin you can delete the plugin via the WordPress admin plugins panel. Or it for some reason your site is down you can FTP to your site and simply remove the plugins folder named '<?php echo dirname(plugin_basename(__FILE__)) ?>'. The Simply Exclude plugin does not add any permanent hack to any WordPress core file. Only one entry is added to the options table.</p>
			</div>

			<h3><a href="#">I'm trying to include some specific Posts and exclude some other Post Type items but it does not seem to be working. Any advice?</a></h3>
			<div>
				<p>One important piece of information is to understand how the WP_Query system works within WordPress. If you have one Post Types where you are setting the <strong>Include only</strong>. This will execute before any excludes. This is just the way WordPress works and not something the plugin can control.</p>
				<p>Let's consider an example. Assume you have the default Post Type <strong>Posts</strong> setup to <strong>Include only</strong> for the Front/Home. Now also assume you have a custom Post Type name <strong>Books</strong> and this action state is set to 'Include All'. When you view the Front/Home page you only see the Posts but not the Books? Why? Because WordPress see the <strong>Include only</strong> as a higher request. If both are included in your setup the <strong>Include only</strong> will be processed while the additional <strong>Exclude</strong> or <strong>Include all</strong> will be ignored.</p>
				<p>To summarize. You can combine <strong>Exclude</strong> and <strong>Include all</strong> options with in the same Taxonomy or Post Type. But if you combine <strong>Include only</strong> with other action states like <strong>Exclude</strong> or <strong>Include all</strong> only the <strong>Include only</strong> information will be used.</p>
					
			</div>
		</div>
		<?php
	}
	
	
	/****************************************************************************************************************************/
	/*																															*/
	/*												UTILITY FUNCTIONS															*/
	/*																															*/
	/****************************************************************************************************************************/

	function check_url($url='')
	{
		if (!$url) return;

		$_REQUEST_URI = explode('?', $_SERVER['REQUEST_URI']);
		$url_len 	= strlen($url);
		$url_offset = $url_len * -1;

		// If out test string ($url) is longer than the page URL. skip
		if (strlen($_REQUEST_URI[0]) < $url_len) return;

		if ($url == substr($_REQUEST_URI[0], $url_offset, $url_len))
				return true;
	}

	function se_listify_ids($ids_array, $action)
	{
		//echo "ids_array<pre>"; print_r($ids_array); echo "</pre>";
		//echo "action=[". $action ."]<br />";
		
		$id_list = "";
		
		if ($action == "e")
			$action_value = "-";
		else
			$action_value = "";
			
		foreach($ids_array as $id_key => $id_val)
		{
			if (strlen($id_list))
				$id_list .= ",";
			$id_list .= $action_value.$id_val;
		}
		return $id_list;
	}
}

$simplyexclude_new = new SimplyExcludeNew();
