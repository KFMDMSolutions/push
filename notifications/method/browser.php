<?php
/**
 *
 * @package       Push Notifications
 * @copyright (c) 2017 - 2018 LavIgor
 * @license       http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace imkingdavid\push\notifications\method;

class browser extends \phpbb\notification\method\base
{
	/** @var \phpbb\user_loader */
	protected $user_loader;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\log\log_interface */
	protected $log;

	/** @var \phpbb\filesystem\filesystem_interface */
	protected $filesystem;

	/** @var string */
	protected $phpbb_root_path;

	/** @var string */
	protected $php_ext;

	/** @var string */
	protected $subscriptions_table;

	/** @var array */
	protected $user_subscriptions = [];

	public function __construct(\phpbb\user_loader $user_loader, \phpbb\user $user, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\log\log_interface $log, \phpbb\filesystem\filesystem_interface $filesystem, $phpbb_root_path, $php_ext, $subscriptions_table)
	{
		$this->user_loader = $user_loader;
		$this->user = $user;
		$this->config = $config;
		$this->db = $db;
		$this->log = $log;
		$this->filesystem = $filesystem;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
		$this->subscriptions_table = $subscriptions_table;
	}

	/**
	 * Get notification method name
	 *
	 * @return string
	 */
	public function get_type()
	{
		return 'push.notification.method.browser';
	}

	/**
	 * Is the method enabled by default?
	 *
	 * @return bool
	 */
	public function is_enabled_by_default()
	{
		return true;
	}

	/**
	 * Is this method available for the user?
	 * This is checked on the notifications options
	 *
	 * @return bool
	 */
	public function is_available()
	{
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function notify()
	{
		// Do not send anything if there is nothing to send
		if (empty($this->queue))
		{
			return;
		}

		$user_ids = $this->setup_users();

		// We do not send notifications to banned users
		if (!function_exists('phpbb_get_banned_user_ids'))
		{
			include($this->phpbb_root_path . 'includes/functions_user.' . $this->php_ext);
		}
		$banned_users = phpbb_get_banned_user_ids($user_ids);

		/** @var \phpbb\notification\type\type_interface $notification */
		foreach ($this->queue as $notification)
		{
			$user = $this->user_loader->get_user($notification->user_id);

			if ($this->check_user_inactive($user) || in_array($notification->user_id, $banned_users))
			{
				continue;
			}

			$display_data = $notification->prepare_for_display();

			$message = html_entity_decode(truncate_string(strip_tags($display_data['FORMATTED_TITLE'] . ' ' . $display_data['REFERENCE'] . $display_data['FORUM'] . $display_data['REASON']), 1000, 1000), ENT_COMPAT);

			$this->prepare_push_notifications($notification->user_id, [
				'title'   => $this->config['sitename'],
				'message' => $message,
				'url'     => $this->make_clean_url(str_replace('&amp;', '&', $display_data['URL'])),
				'time'    => $notification->notification_time,
				'badge'   => $this->config['push_badge_url'],
				'avatar'  => $this->get_avatar_url($notification->get_avatar()),
			]);
		}

		// We're done, empty the queue
		$this->empty_queue();
	}

	/**
	 * Loads user data for the recipients
	 *
	 * @return array Array of user IDs of the recipients
	 */
	protected function setup_users()
	{
		// Load all users we want to notify
		$user_ids = array();
		foreach ($this->queue as $notification)
		{
			$user_ids[] = $notification->user_id;
		}

		// Load all the users we need
		$this->user_loader->load_users($user_ids);

		// Load browsers' data
		$this->setup_subscriptions($user_ids);

		return $user_ids;
	}


	/**
	 * Checks whether the specified user is currently inactive
	 *
	 * @param array $user User row from the database
	 * @return bool
	 */
	protected function check_user_inactive($user)
	{
		return $user['user_type'] == USER_IGNORE || ($user['user_type'] == USER_INACTIVE && $user['user_inactive_reason'] == INACTIVE_MANUAL);
	}

	/**
	 * Retrieves information about registered user subscriptions
	 *
	 * @param array $user_ids User IDs of the recipients
	 */
	protected function setup_subscriptions(array $user_ids)
	{
		$this->user_subscriptions = [];

		$sql = $this->db->sql_build_query('SELECT', [
			'SELECT' => 's.*',
			'FROM'   => [
				$this->subscriptions_table => 's',
			],
			'WHERE'  => $this->db->sql_in_set('s.user_id', $user_ids),
		]);
		$res = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($res))
		{
			$subscription = $row['token'];
			$this->user_subscriptions[$row['user_id']][] = $subscription;
		}
	}

	/**
	 * Sets up notifications to be sent for each subscription
	 *
	 * @param WebPush $WebPush           WebPush object
	 * @param int     $user_id           User ID of the recipient
	 * @param array   $notification_data Array of notification's data
	 */
	protected function prepare_push_notifications($user_id, array $notification_data)
	{
		if (empty($this->user_subscriptions[$user_id]))
		{
			return;
		}

		$subscriptions = $this->user_subscriptions[$user_id];
		/* @var subscription $subscription */
		foreach ($subscriptions as $subscription)
		{
			$url = 'https://fcm.googleapis.com/fcm/send';

			// Compile headers in one variable
			$headers = array (
			'Authorization:key=' . $this->config['push_firebase_server_key'],
			'Content-Type:application/json'
			);

			// Add notification content to a variable for easy reference
			$notifData = [
				'title' => $notification_data['title'],
				'body' => $notification_data['message'],
			];

			// Create the api body
			$apiBody = [
				'notification' => $notifData,
				'data' => $notifData,
				'to' => $subscription
			];
			
			error_log("sub=".$subscription);

			// Initialize curl with the prepared headers and body
			$ch = curl_init();
			curl_setopt ($ch, CURLOPT_URL, $url );
			curl_setopt ($ch, CURLOPT_POST, true );
			curl_setopt ($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt ($ch, CURLOPT_POSTFIELDS, json_encode($apiBody));

			// Execute call and save result
			$result = curl_exec ( $ch );

			error_log($result);
			// Close curl after call
			curl_close ( $ch );

			return $result;
		}
	}

	/**
	 * Returns absolute URLs needed for Push notifications
	 *
	 * @param string $url URL relative to phpBB root path (other URLs will be returned unparsed)
	 * @return string
	 */
	protected function make_clean_url($url)
	{
		if (strpos($url, $this->phpbb_root_path) !== 0)
		{
			return $url;
		}

		$filesystem = new \Symfony\Component\Filesystem\Filesystem();
		return generate_board_url() . '/' . rtrim($filesystem->makePathRelative($url, $this->phpbb_root_path), '/');
	}

	/**
	 * Generates correct URL string for user's avatar
	 *
	 * @param string $avatar User's avatar image HTML string
	 * @return string
	 */
	protected function get_avatar_url($avatar)
	{
		// There's no data field for src - e.g. in Gravatar driver.
		// We need to parse the result HTML string.
		if (!$avatar)
		{
			return generate_board_url() . '/ext/lavigor/notifications/styles/all/template/js/images/no_avatar.gif';
		}

		// Handle "lazy-loaded" case.
		if (preg_match('#data-src="([^"]+)"#im', $avatar, $matches))
		{
			$avatar = $matches[1];
		}
		else
		{
			preg_match('#src="([^"]+)"#im', $avatar, $matches);
			$avatar = $matches[1];
		}

		return $this->make_clean_url($avatar);
	}
}
