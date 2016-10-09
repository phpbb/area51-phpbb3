<?php
/**
 *
 * Team Security Measures extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2014 phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

/**
 * DO NOT CHANGE
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	// ACP Module
	'ACP_TEAM_SECURITY'				=> 'Team Security',
	'ACP_TEAM_SECURITY_SETTINGS'	=> 'Settings',

	// ACP Logs
	'LOG_TEAM_AUTH_FAIL'			=> '<strong>Failed team member login attempt</strong>',
	'LOG_TEAM_SEC_UPDATED'			=> '<strong>Team Security extension settings updated</strong>',

	// ACP Log notifications
	'LOG_DELETE_ALL'				=> 'All logs',
	'LOG_DELETE_MARKED'				=> 'Logs by ID: %1$s',

	// EMAIL
	'ACP_CONTACT_ADMIN'				=> 'the board administrator or webmaster',
));
