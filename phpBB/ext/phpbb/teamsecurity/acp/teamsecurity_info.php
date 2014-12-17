<?php
/**
 *
 * phpBB Team Security Measures
 *
 * @copyright (c) 2014 phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbb\teamsecurity\acp;

class teamsecurity_info
{
	function module()
	{
		return array(
			'filename'	=> '\phpbb\teamsecurity\acp\teamsecurity_module',
			'title'		=> 'ACP_TEAM_SECURITY',
			'modes'		=> array(
				'settings'	=> array(
					'title' => 'ACP_TEAM_SECURITY_SETTINGS',
					'auth' => 'ext_phpbb/teamsecurity && acl_a_board',
					'cat' => array('ACP_TEAM_SECURITY')
				),
			),
		);
	}
}
