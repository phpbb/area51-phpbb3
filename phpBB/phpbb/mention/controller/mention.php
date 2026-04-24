<?php
/**
 *
 * This file is part of the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 * For full copyright and license information, please see
 * the docs/CREDITS.txt file.
 *
 */

namespace phpbb\mention\controller;

use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\controller\helper;
use phpbb\db\driver\driver_interface;
use phpbb\di\service_collection;
use phpbb\mention\source\source_interface;
use phpbb\request\request_interface;
use phpbb\user;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class mention
{
	/** @var auth */
	protected $auth;

	/** @var config */
	protected $config;

	/** @var driver_interface */
	protected $db;

	/** @var service_collection */
	protected $mention_sources;

	/** @var  request_interface */
	protected $request;

	/** @var helper */
	protected $helper;

	/** @var user */
	protected $user;

	/** @var string */
	protected $phpbb_root_path;

	/** @var string */
	protected $php_ext;

	/**
	 * Constructor
	 *
	 * @param auth $auth
	 * @param config $config
	 * @param service_collection $mention_sources
	 * @param request_interface $request
	 * @param helper $helper
	 * @param string $phpbb_root_path
	 * @param string $phpEx
	 */
	public function __construct(auth $auth, config $config, driver_interface $db, service_collection $mention_sources, request_interface $request, helper $helper, string $phpbb_root_path, string $phpEx)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->mention_sources = $mention_sources;
		$this->request = $request;
		$this->helper = $helper;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $phpEx;
	}

	/**
	 * Handle requests to mention controller
	 *
	 * @return Response
	 */
	public function handle(): Response
	{
		$forum_id = $this->request->variable('forum_id', 0);
		$topic_id = $this->request->variable('topic_id', 0);

		if (!$this->request->is_ajax() || !$this->can_mention($forum_id, $topic_id))
		{
			return new RedirectResponse($this->helper->route('phpbb_index_controller'));
		}

		$keyword = $this->request->variable('keyword', '', true);
		$names = [];
		$has_names_remaining = false;

		/** @var source_interface $source */
		foreach ($this->mention_sources as $source)
		{
			$has_names_remaining = !$source->get($names, $keyword, $topic_id) || $has_names_remaining;
		}

		return new JsonResponse([
			'names' => array_values($names),
			'all' => !$has_names_remaining,
		]);
	}

	/**
	 * Check whether user is able to use mention
	 *
	 * @param int $forum_id Forum ID
	 * @param int $topic_id Topic IC
	 *
	 * @return bool True if user can mention, false if not
	 */
	protected function can_mention(int $forum_id, int $topic_id): bool
	{
		// No forum id is only valid if we know the topic ID
		if (!$forum_id && $topic_id)
		{
			$sql = 'SELECT forum_id FROM ' . TOPICS_TABLE . ' WHERE topic_id = ' . (int) $topic_id;
			$result = $this->db->sql_query($sql);
			$forum_id = $this->db->sql_fetchfield('forum_id');
			$this->db->sql_freeresult($result);
		}

		// No forum id, aborting here
		if (!$forum_id)
		{
			return false;
		}

		return $this->config['allow_mentions'] && $this->auth->acl_get('u_mention') && $this->auth->acl_get('f_mention', $forum_id);
	}
}
