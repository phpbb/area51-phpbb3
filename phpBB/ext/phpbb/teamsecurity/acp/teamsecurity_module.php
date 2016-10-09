<?php
/**
 *
 * Team Security Measures extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2014 phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbb\teamsecurity\acp;

class teamsecurity_module
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\log\log */
	protected $log;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var string */
	public $u_action;

	public function __construct()
	{
		global $config, $db, $phpbb_log, $request, $template, $user;

		$this->config = $config;
		$this->db = $db;
		$this->log = $phpbb_log;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;

		$this->user->add_lang_ext('phpbb/teamsecurity', 'acp_teamsecurity');
	}

	/**
	 * Main ACP module
	 *
	 * @return void
	 * @access public
	 */
	public function main()
	{
		$this->tpl_name = 'acp_teamsecurity';
		$this->page_title = $this->user->lang('ACP_TEAM_SECURITY_SETTINGS');

		// Only allow founders to view/manage these settings
		if ($this->user->data['user_type'] != USER_FOUNDER)
		{
			trigger_error($this->user->lang('ACP_FOUNDER_MANAGE_ONLY'), E_USER_WARNING);
		}

		$form_key = 'acp_teamsecurity';
		add_form_key($form_key);

		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error($this->user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			// Validate the email address submitted by the user
			$sec_contact = $this->request->variable('sec_contact', '');
			if ($sec_contact != '' && !preg_match('/^' . get_preg_expression('email') . '$/i', $sec_contact))
			{
				trigger_error($this->user->lang('EMAIL_INVALID_EMAIL') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$this->config->set('sec_contact', $sec_contact);
			$this->config->set('sec_contact_name', $this->request->variable('sec_contact_name', '', true));
			$this->config->set('sec_login_email', $this->request->variable('sec_login_email', 0));
			$this->config->set('sec_login_attempts', $this->request->variable('sec_login_attempts', 0));
			$this->config->set('sec_email_changes', $this->request->variable('sec_email_changes', 0));
			$this->config->set('sec_strong_pass', $this->request->variable('sec_strong_pass', 0));
			$this->config->set('sec_min_pass_chars', $this->request->variable('sec_min_pass_chars', 0));
			$this->config->set('sec_usergroups', json_encode($this->request->variable('sec_usergroups', array(0))));

			$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_TEAM_SEC_UPDATED');
			trigger_error($this->user->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		// Set template vars for usergroups multi-select box
		$group_id_ary = (!$this->config['sec_usergroups']) ? array() : json_decode(trim($this->config['sec_usergroups']), true);
		$this->get_group_options($group_id_ary);

		// Set output vars for display in the template
		$this->template->assign_vars(array(
			'S_ACP_LOGIN_EMAIL'		=> $this->config['sec_login_email'],
			'ACP_CONTACT_EMAIL'		=> $this->config['sec_contact'],
			'ACP_CONTACT_NAME'		=> $this->config['sec_contact_name'],
			'S_ACP_LOGIN_ATTEMPTS'	=> $this->config['sec_login_attempts'],
			'S_ACP_EMAIL_CHANGES'	=> $this->config['sec_email_changes'],
			'S_ACP_STRONG_PASS'		=> $this->config['sec_strong_pass'],
			'ACP_MIN_PASS_CHARS'	=> $this->config['sec_min_pass_chars'],
			'U_ACTION'				=> $this->u_action,
		));
	}

	/**
	 * Get group options for multi-select box
	 *
	 * @param array $selected_id Currently selected group identifiers
	 * @return void
	 * @access protected
	 */
	protected function get_group_options($selected_id)
	{
		// Get all groups except bots and guests
		$sql = 'SELECT group_id, group_name, group_type
			FROM ' . GROUPS_TABLE . '
			WHERE ' . $this->db->sql_in_set('group_name', array('BOTS', 'GUESTS'), true, true) . '
			ORDER BY group_name ASC';
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$this->template->assign_block_vars('group_options', array(
				'VALUE'			=> $row['group_id'],
				'LABEL'			=> ($row['group_type'] == GROUP_SPECIAL) ? $this->user->lang('G_' . $row['group_name']) : $row['group_name'],
				'S_SELECTED'	=> in_array($row['group_id'], $selected_id),
			));
		}
		$this->db->sql_freeresult($result);
	}
}
