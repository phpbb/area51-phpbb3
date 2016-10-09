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
	'ACP_FOUNDER_MANAGE_ONLY'				=> 'You are not authorised to access these settings. Only a board founder can access these settings.',
	'ACP_TEAM_SECURITY_SETTINGS'			=> 'Team Security Settings',
	'ACP_TEAM_SECURITY_SETTINGS_EXPLAIN'	=> 'From this page you can manage various security options to watch over and help identify possible attacks against team member accounts.',
	'ACP_LOGIN_EMAIL'						=> 'Enable ACP login notifications',
	'ACP_LOGIN_EMAIL_EXPLAIN'				=> 'Send an email when an admin logs into the ACP.',
	'ACP_CONTACT_EMAIL'						=> 'Security contact',
	'ACP_CONTACT_EMAIL_EXPLAIN'				=> 'Email will be delivered to this address. If left blank, email will be delivered to the board contact defined in email settings.',
	'ACP_CONTACT_NAME'						=> 'Security contact name',
	'ACP_CONTACT_NAME_EXPLAIN'				=> 'This is the security contact name that email recipients will see in some of the outgoing email notifications. This field can be left blank.',
	'ACP_GROUP_SECURITY_OPTIONS'			=> 'Group security options',
	'ACP_LOGIN_ATTEMPTS'					=> 'Log failed login attempts',
	'ACP_LOGIN_ATTEMPTS_EXPLAIN'			=> 'Log failed login attempts for selected groups to the User logs.',
	'ACP_EMAIL_CHANGES'						=> 'Notify email address changes',
	'ACP_EMAIL_CHANGES_EXPLAIN'				=> 'Send an email notification when an email address is changed for a member of the selected groups.',
	'ACP_STRONG_PASS'						=> 'Require complex passwords',
	'ACP_STRONG_PASS_EXPLAIN'				=> 'Require complex passwords for selected groups (mixed case, numbers and symbols).',
	'ACP_MIN_PASS_CHARS'					=> 'Minimum password length',
	'ACP_MIN_PASS_CHARS_EXPLAIN'			=> 'Minimum password length required for selected groups.',
	'ACP_GROUPS_EXPLAIN'					=> 'Select the groups the enabled security options will be applied to. Select multiple groups by holding <samp>CTRL</samp> (or <samp>&#8984;CMD</samp> on Mac) and clicking.',
	'CHARS'									=> 'Characters',
));
