<?php
/**
 *
 * @package push
 * @copyright (c) 2017 David King (imkingdavid)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace imkingdavid\push\controller;

use phpbb\config\config;
use phpbb\exception\http_exception;
use phpbb\controller\helper;
use phpbb\request\request;
use phpbb\db\driver\factory;
use phpbb\user;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class push
{
	/**
	 * @var \Twig_Environment
	 */
	protected $template;

	/**
	 * @var request
	 */
	protected $request;

	/**
	 * @var config
	 */
	protected $config;
	
	/** @var user */
	protected $user;

	/**
	 * @var factory
	 */
	protected $db;
	
	/**
	 * @var helper
	 */
	protected $controller_helper;

	/**
	 * @var string
	 */
	protected $table_prefix;

	/**
	 * Constructor
	 *
	 * @param \Twig_Environment $twig
	 * @param request $request
	 * @param config $config
	 * @param factory $db
	 * @param user $user
	 * @param helper $controller_helper
	 */
	public function __construct(\Twig_Environment $twig, request $request, config $config, factory $db, user $user, helper $controller_helper, $table_prefix)
	{
		$this->request = $request;
		$this->config = $config;
		$this->template = $twig;
		$this->db = $db;
		$this->table_prefix = $table_prefix;
		$this->user = $user;
		$this->controller_helper = $controller_helper;
	}

	/**
	 * Controller for /push/service_worker.js route
	 *
	 * @return Response
	 */
	public function service_worker()
	{
		$content = $this->template->render('service_worker.js.twig', [
			'PUSH_FIREBASE_API_KEY' => $this->config['push_firebase_api_key'],
			'PUSH_FIREBASE_MESSAGING_SENDER_ID' => $this->config['push_firebase_messaging_sender_id'],	
			'PUSH_FIREBASE_AUTHDOMAIN' => $this->config['push_firebase_authDomain'],
			'PUSH_FIREBASE_PROJECTID' => $this->config['push_firebase_projectId'],
			'PUSH_FIREBASE_STORAGEBUCKET' => $this->config['push_firebase_storageBucket'],
			'PUSH_FIREBASE_APPID' => $this->config['push_firebase_appId'],
			'PUSH_FIREBASE_IS_USER_LOGGEDIN' => $this->controller_helper->route('push.is_user_loggedin'),
		]);

		$response = new Response($content);
		$response->headers->set('Content-Type', 'application/javascript');
		$response->setCharset('UTF-8');

		return $response;
	}

	// /**
	 // * Controller for /push/manifest.json route
	 // *
	 // * Manifest file setup is described here:
	 // * https://developers.google.com/web/fundamentals/engage-and-retain/web-app-manifest/
	 // *
	 // *
	 // * @return Response
	 // */
	public function manifest()
	{
		$content = $this->template->render('manifest.json.twig', [
			'PUSH_FIREBASE_MANIFEST_SHORT_NAME' => $this->config['push_firebase_manifest_short_name'],
			'PUSH_FIREBASE_MANIFEST_NAME' => $this->config['push_firebase_manifest_name'],
			'PUSH_FIREBASE_MANIFEST_ORIENTATION' => $this->config['push_firebase_manifest_orientation'],
			'PUSH_FIREBASE_MANIFEST_THEME_COLOR' => $this->config['push_firebase_manifest_theme_color'],
		]);

		// $response = new Response($content);
		// $response->headers->set('Content-Type', 'application/json');
		// $response->setCharset('UTF-8');

		return $response;
	}

	/**
	 * Controller for push/register_user route
	 *
	 * @return Response
	 */
	public function register_user()
	{
		$response = new Response();
		$user_id = $this->request->variable('user_id', 0);
		$token = $this->request->variable('firebase_token', '');

		if (empty($user_id) || empty($token) || $user_id == 1)
		{
			$response->setStatusCode(500);
			$response->setContent('User ID and Token must be provided.');
			return $response;
		}

		$sql = 'SELECT user_id,token FROM ' . $this->table_prefix . 'push_user_tokens WHERE user_id = ' . (int) $user_id . ' AND token = "' . $this->db->sql_escape($token) . '"';
		$result = $this->db->sql_query($sql);
		$tokens = [];
		while($rows = $this->db->sql_fetchrow($result))
		{
			$tokens[] = $rows['token'];
		}
		$this->db->sql_freeresult($result);

		if (in_array($token, $tokens))
		{
			$response->setContent('Already registered.');
			return $response;
		}

		$sql = 'INSERT INTO ' . $this->table_prefix . 'push_user_tokens
			(user_id, token, created) VALUES (' . (int) $user_id . ', "' . $this->db->sql_escape($token) . '", "' . microtime() . '");';
		$result = $this->db->sql_query($sql);

		$response->setContent('Registered.');
		return $response;
	}
	
	public function unregister_user()
	{
		$response = new Response();
		$user_id = $this->request->variable('user_id', 0);
		$token = $this->request->variable('firebase_token', '');

		if (empty($user_id) || empty($token))
		{
			$response->setStatusCode(500);
			$response->setContent('User ID and Token must be provided.');
			return $response;
		}

		$sql = 'SELECT user_id,token FROM ' . $this->table_prefix . 'push_user_tokens WHERE user_id = ' . (int) $user_id . ' AND token = "' . $this->db->sql_escape($token) . '"';
		$result = $this->db->sql_query($sql);
		$tokens = [];
		while($rows = $this->db->sql_fetchrow($result))
		{
			$tokens[] = $rows['token'];
		}
		$this->db->sql_freeresult($result);

		if (!in_array($token, $tokens))
		{
			$response->setContent('not registered.');
			return $response;
		}
		$sql = 'DELETE FROM ' . $this->table_prefix . 'push_user_tokens WHERE user_id = ' . (int) $user_id . ' AND token = "' . $this->db->sql_escape($token) . '"';
		$result = $this->db->sql_query($sql);

		$response->setContent('unregistered.');
		return $response;
	}
	
	
	public function is_user_loggedin()
	{
		$response = new Response();
		$user_id = $this->user->data['user_id'];

		if (empty($user_id) || $user_id == 1)
		{
			$response->setStatusCode(204);
			return $response;
		}

		$response->setStatusCode(200);
		$response->setContent($user_id);
		return $response;
	}	
	
	public function is_registered_user()
	{
		$response = new Response();
		$user_id = $this->request->variable('user_id', 0);
		$token = $this->request->variable('firebase_token', '');

		if (empty($user_id) || empty($token))
		{
			$response->setStatusCode(500);
			$response->setContent('User ID and Token must be provided.');
			return $response;
		}

		$sql = 'SELECT user_id,token FROM ' . $this->table_prefix . 'push_user_tokens WHERE user_id = ' . (int) $user_id . ' AND token = "' . $this->db->sql_escape($token) . '"';
		$result = $this->db->sql_query($sql);
		$tokens = [];
		while($rows = $this->db->sql_fetchrow($result))
		{
			$tokens[] = $rows['token'];
		}
		$this->db->sql_freeresult($result);

		if (!in_array($token, $tokens))
		{
			$response->setStatusCode(204);
			return $response;
		}
		$response->setStatusCode(200);
		$response->setContent('registered');
		return $response;
	}
}