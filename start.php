<?php
/**
 * Blogs
 *
 * @package Blog
 *
 * @todo
 * - Either drop support for "publish date" or duplicate more entity getter
 * functions to work with a non-standard time_created.
 * - Pingbacks
 * - Notifications
 * - River entry for posts saved as drafts and later published
 */

elgg_register_event_handler('init', 'system', 'khan_exercise_init');

/**
 * Init blog plugin.
 */
function khan_exercise_init() {

	elgg_register_library('elgg:khan_exercise', elgg_get_plugins_path() . 'genghis/lib/khan_exercise.php');

	// add a site navigation item
	$item = new ElggMenuItem('khan_exercise', elgg_echo('genghis:khanexercises'), 'khan_exercise/all', pri);
	$item->setPriority(1);
	elgg_register_menu_item('site', $item);

	//elgg_register_event_handler('upgrade', 'upgrade', 'blog_run_upgrades');

	// add to the main css
	elgg_extend_view('css/elgg', 'khan_exercise/css');

	// register the blog's JavaScript
	$blog_js = elgg_get_simplecache_url('js', 'khan_exercise/save_draft');
	elgg_register_simplecache_view('js/khan_exercise/save_draft');
	elgg_register_js('elgg.khan_exercise', $blog_js);

	// routing of urls
	elgg_register_page_handler('khan_exercise', 'khan_exercise_page_handler');

	// override the default url to view a blog object
	elgg_register_entity_url_handler('object', 'khan_exercise', 'khan_exercise_url_handler');

	// notifications
//	elgg_register_notification_event('object', 'blog', array('publish'));
//	elgg_register_plugin_hook_handler('prepare', 'notification:publish:object:blog', 'blog_prepare_notification');

	// add blog link to
	//elgg_register_plugin_hook_handler('register', 'menu:owner_block', 'blog_owner_block_menu');

	// pingbacks
	//elgg_register_event_handler('create', 'object', 'blog_incoming_ping');
	//elgg_register_plugin_hook_handler('pingback:object:subtypes', 'object', 'blog_pingback_subtypes');

	// Register for search.
	elgg_register_entity_type('object', 'khan_exercise');

	// Add group option
	add_group_tool_option('khan_exercise', elgg_echo('genghis:enablegenghis'), true);
	elgg_extend_view('groups/tool_latest', 'khan_exercise/group_module');

	// add a blog widget
	//elgg_register_widget_type('blog', elgg_echo('blog'), elgg_echo('blog:widget:description'));

	// register actions
	$action_path = elgg_get_plugins_path() . 'genghis/actions/khan_exercise';
	elgg_register_action('khan_exercise/save', "$action_path/save.php");
	//elgg_register_action('blog/auto_save_revision', "$action_path/auto_save_revision.php");
	//elgg_register_action('blog/delete', "$action_path/delete.php");
	elgg_register_action('khan_exercise/delete', "$action_path/delete.php");
	// entity menu
	//elgg_register_plugin_hook_handler('register', 'menu:entity', 'khan_exercise_entity_menu_setup');

	// ecml
	//elgg_register_plugin_hook_handler('get_views', 'ecml', 'blog_ecml_views_hook');
}
/**
 * Dispatches blog pages.
 * URLs take the form of
 *  All blogs:       blog/all
 *  User's blogs:    blog/owner/<username>
 *  Friends' blog:   blog/friends/<username>
 *  User's archives: blog/archives/<username>/<time_start>/<time_stop>
 *  Blog post:       blog/view/<guid>/<title>
 *  New post:        blog/add/<guid>
 *  Edit post:       blog/edit/<guid>/<revision>
 *  Preview post:    blog/preview/<guid>
 *  Group blog:      blog/group/<guid>/all
 *
 * Title is ignored
 *
 * @todo no archives for all blogs or friends
 *
 * @param array $page
 * @return bool
 */
function khan_exercise_page_handler($page) {

	elgg_load_library('elgg:khan_exercise');

	// push all blogs breadcrumb
	elgg_push_breadcrumb(elgg_echo('genghis:khan_exercises'), "khan_exercise/all");

	if (!isset($page[0])) {
		$page[0] = 'all';
	}

	$page_type = $page[0];
	switch ($page_type) {
		case 'owner':
			$user = get_user_by_username($page[1]);
			$params = khan_exercise_get_page_content_list($user->guid);
			break;
		case 'friends':
			$user = get_user_by_username($page[1]);
			$params = khan_exercise_get_page_content_friends($user->guid);
			break;
//		case 'archive':
//			$user = get_user_by_username($page[1]);
//			$params = blog_get_page_content_archive($user->guid, $page[2], $page[3]);
//			break;
		case 'view':
			$params = khan_exercise_get_page_content_read($page[1]);
			break;
		case 'edit':
		case 'add':
			gatekeeper();
            if (end($page) == "autosave_work.php") {
                require('actions/khan_exercise/save.php');
            } elseif ($page[1] == "libs") {
                switch (end(explode(".",end($page)))) {
                	case 'php':
           				$phpfile = dirname(__file__) . "/Genghis/" . implode("/",array_slice($page, 1));
                	    require($phpfile);
                	    return true;
                	default:
						return false;
				}
            } else {
                $params = khan_exercise_get_page_content_edit($page_type, $page[1], NULL, NUlL);
            }
			break;
//		case 'group':
//			if ($page[2] == 'all') {
//				$params = blog_get_page_content_list($page[1]);
//			} else {
//				$params = blog_get_page_content_archive($page[1], $page[3], $page[4]);
//			}
//			break;
		case 'all':
			$params = khan_exercise_get_page_content_list();
			break;
		default:
			return false;
	}

	if (isset($params['sidebar'])) {
		$params['sidebar'] .= elgg_view('khan_exercise/sidebar', array('page' => $page_type));
	} else {
		$params['sidebar'] = elgg_view('khan_exercise/sidebar', array('page' => $page_type));
	}
	$params['title'] = '';
	$params['canvas_name'] = $params['canvas_name'] ? $params['canvas_name'] : 'content';
	$body = elgg_view_layout($params['canvas_name'], $params);

	echo elgg_view_page('', $body);
	return true;
}

/**
 * Format and return the URL for blogs.
 *
 * @param ElggObject $entity Blog object
 * @return string URL of blog.
 */
function khan_exercise_url_handler($entity) {
	if (!$entity->getOwnerEntity()) {
		// default to a standard view if no owner.
		return FALSE;
	}

	$friendly_title = elgg_get_friendly_title($entity->title);

	return "khan_exercise/view/{$entity->guid}/$friendly_title";
}

/**
 * Add a menu item to an ownerblock
 */
function khan_exercise_owner_block_menu($hook, $type, $return, $params) {
	if (elgg_instanceof($params['entity'], 'user')) {
		$url = "khan_exercise/owner/{$params['entity']->username}";
		$item = new ElggMenuItem('khan_exercise', elgg_echo('khan_exercise'), $url);
		$return[] = $item;
	} else {
		if ($params['entity']->genghis_enable != "no") {
			$url = "khan_exercise/group/{$params['entity']->guid}/all";
			$item = new ElggMenuItem('khan_exercise', elgg_echo('khan_exercise:group'), $url);
			$return[] = $item;
		}
	}

	return $return;
}

/**
 * Add particular blog links/info to entity menu
 */
function khan_exercise_entity_menu_setup($hook, $type, $return, $params) {
	if (elgg_in_context('widgets')) {
		return $return;
	}

	$entity = $params['entity'];
	$handler = elgg_extract('handler', $params, false);
	if ($handler != 'blog') {
		return $return;
	}

	if ($entity->status != 'published') {
		// draft status replaces access
		foreach ($return as $index => $item) {
			if ($item->getName() == 'access') {
				unset($return[$index]);
			}
		}

		$status_text = elgg_echo("khan_exercise:status:{$entity->status}");
		$options = array(
			'name' => 'published_status',
			'text' => "<span>$status_text</span>",
			'href' => false,
			'priority' => 150,
		);
		$return[] = ElggMenuItem::factory($options);
	}

	return $return;
}

/**
 * Prepare a notification message about a published blog
 * 
 * @param string                          $hook         Hook name
 * @param string                          $type         Hook type
 * @param Elgg_Notifications_Notification $notification The notification to prepare
 * @param array                           $params       Hook parameters
 * @return Elgg_Notifications_Notification
 */
function khan_exercise_prepare_notification($hook, $type, $notification, $params) {
	$entity = $params['event']->getObject();
	$owner = $params['event']->getActor();
	$recipient = $params['recipient'];
	$language = $params['language'];
	$method = $params['method'];

	$subject = elgg_echo('blog:newpost', array(), $language); 
	$body = elgg_echo('blog:notification', array(
		$owner->name,
		$entity->title,
		$entity->getExcerpt(),
		$entity->getURL()
	), $language);

	$notification->subject = $subject;
	$notification->body = $body;

	return $notification;
}

/**
 * Register blogs with ECML.
 */
//function blog_ecml_views_hook($hook, $entity_type, $return_value, $params) {
//	$return_value['object/blog'] = elgg_echo('blog:blogs');
//
//	return $return_value;
//}

/**
 * Upgrade from 1.7 to 1.8.
 */
//function blog_run_upgrades($event, $type, $details) {
//	$blog_upgrade_version = elgg_get_plugin_setting('upgrade_version', 'blogs');
//
//	if (!$blog_upgrade_version) {
//		 // When upgrading, check if the ElggBlog class has been registered as this
//		 // was added in Elgg 1.8
//		if (!update_subtype('object', 'blog', 'ElggBlog')) {
//			add_subtype('object', 'blog', 'ElggBlog');
//		}
//
//		elgg_set_plugin_setting('upgrade_version', 1, 'blogs');
//	}
//}
