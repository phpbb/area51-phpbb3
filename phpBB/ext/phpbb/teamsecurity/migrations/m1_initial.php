<?php
/**
 *
 * Team Security Measures extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2014 phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbb\teamsecurity\migrations;

class m1_initial extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return array('\phpbb\db\migration\data\v31x\v313');
	}

	public function update_data()
	{
		return array(
			array('config.add', array('sec_login_email', 0)),
			array('config.add', array('sec_login_attempts', 0)),
			array('config.add', array('sec_strong_pass', 0)),
			array('config.add', array('sec_min_pass_chars', 13)),
			array('config.add', array('sec_usergroups', '')),

			array('module.add', array('acp', 'ACP_CAT_DOT_MODS', 'ACP_TEAM_SECURITY')),
			array('module.add', array(
				'acp', 'ACP_TEAM_SECURITY', array(
					'module_basename'	=> '\phpbb\teamsecurity\acp\teamsecurity_module',
					'modes'				=> array('settings'),
				),
			)),
		);
	}
}
