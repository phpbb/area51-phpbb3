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

require_once __DIR__ . '/submit_post_base.php';

class phpbb_notification_submit_post_type_topic_test extends phpbb_notification_submit_post_base
{
	protected $item_type = 'notification.type.topic';

	protected function setUp(): void
	{
		global $auth, $cache, $config, $db, $phpbb_container, $phpbb_dispatcher, $lang, $user, $request, $phpEx, $phpbb_root_path, $user_loader, $phpbb_log;

		parent::setUp();

		// Add additional permissions
		$auth->expects($this->any())
			->method('acl_get_list')
			->with($this->anything(),
				$this->stringContains('_'),
				$this->greaterThan(0))
			->will($this->returnValueMap(array(
				array(
					array(6, 7, 8),
					'f_read',
					1,
					array(
						1 => array(
							'f_read' => array(7, 8),
						),
					),
				),
			)));
	}

	/**
	* submit_post() Notifications test
	*
	* submit_post() $mode = 'post'
	* Notification item_type = 'topic'
	*/
	public static function submit_post_data()
	{
		return array(
			/**
			* Normal post
			*
			* User => State description
			*	2	=> Poster, should NOT receive a notification
			*	6	=> Forum subscribed, but no-auth reading, should NOT receive a notification
			*	7	=> Forum subscribed, should receive a notification
			*	8	=> Forum subscribed, but already notified, should NOT receive a new notification
			*/
			array(
				array(),
				array(
					array('user_id' => 8, 'item_id' => 1, 'item_parent_id' => 1),
				),
				array(
					array('user_id' => 7, 'item_id' => 2, 'item_parent_id' => 1),
					array('user_id' => 8, 'item_id' => 1, 'item_parent_id' => 1),
					array('user_id' => 8, 'item_id' => 2, 'item_parent_id' => 1),
				),
			),

			/**
			* Unapproved post
			*
			* No new notifications
			*/
			array(
				array('force_approved_state' => false),
				array(
					array('user_id' => 8, 'item_id' => 1, 'item_parent_id' => 1),
				),
				array(
					array('user_id' => 8, 'item_id' => 1, 'item_parent_id' => 1),
				),
			),
		);
	}

	/**
	 * @dataProvider submit_post_data
	 */
	public function test_submit_post($additional_post_data, $expected_before, $expected_after)
	{
		global $auth, $cache, $config, $db, $phpbb_container, $phpbb_dispatcher, $lang, $user, $request, $phpEx, $phpbb_root_path, $user_loader, $phpbb_log;

		$sql = 'SELECT user_id, item_id, item_parent_id
			FROM ' . NOTIFICATIONS_TABLE . ' n, ' . NOTIFICATION_TYPES_TABLE . " nt
			WHERE nt.notification_type_name = '" . $this->item_type . "'
				AND n.notification_type_id = nt.notification_type_id
			ORDER BY user_id ASC, item_id ASC";
		$result = $this->db->sql_query($sql);
		$this->assertEquals($expected_before, $this->db->sql_fetchrowset($result));
		$this->db->sql_freeresult($result);

		$poll_data = array();
		$post_data = array_merge($this->post_data, $additional_post_data);
		submit_post('post', '', 'poster-name', POST_NORMAL, $poll_data, $post_data, false, false);

		// Check whether the notifications got added successfully
		$result = $this->db->sql_query($sql);
		$this->assertEquals($expected_after, $this->db->sql_fetchrowset($result),
			'Check whether the notifications got added successfully');
		$this->db->sql_freeresult($result);

		if (isset($additional_post_data['force_approved_state']) && $additional_post_data['force_approved_state'] === false)
		{
			return;
		}

		$reply_data = array_merge($this->post_data, array(
			'topic_id'		=> 2,
		));
		$url = submit_post('reply', '', 'poster-name', POST_NORMAL, $poll_data, $reply_data, false, false);
		$reply_id = 3;
		$this->assertStringEndsWith('p' . $reply_id, $url, 'Post ID of reply is not ' . $reply_id);

		// Check whether the notifications are still correct after a reply has been added
		$result = $this->db->sql_query($sql);
		$this->assertEquals($expected_after, $this->db->sql_fetchrowset($result),
			'Check whether the notifications are still correct after a reply has been added');
		$this->db->sql_freeresult($result);

		$result = $this->db->sql_query(
			'SELECT p.*, t.topic_posts_approved, t.topic_posts_unapproved, t.topic_posts_softdeleted, t.topic_first_post_id, t.topic_last_post_id
			FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . ' t
			WHERE p.topic_id = t.topic_id
				AND p.post_id = ' . $reply_id);
		$reply_edit_data = array_merge($this->post_data, $this->db->sql_fetchrow($result), array(
			'force_approved_state'	=> false,
			'post_edit_reason'		=> 'PHPBB3-12370',
		));
		submit_post('edit', '', 'poster-name', POST_NORMAL, $poll_data, $reply_edit_data, false, false);

		// Check whether the notifications are still correct after the reply has been edit
		$result = $this->db->sql_query($sql);
		$this->assertEquals($expected_after, $this->db->sql_fetchrowset($result),
			'Check whether the notifications are still correct after the reply has been edit');
		$this->db->sql_freeresult($result);
	}
}
