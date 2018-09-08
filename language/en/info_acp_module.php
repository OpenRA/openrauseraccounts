<?php
/**
 *
 * OpenRAUserAccounts. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2018, The OpenRAUserAccounts authors
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
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
	'ACP_BADGES'					=> 'OpenRA badges',
	'ACP_BADGES_SETTINGS'				=> 'Settings',
	'ACP_BADGES_SETTINGS_EXPLAIN'			=> 'General settings for badges',
	'ACP_BADGES_TYPES'				=> 'Manage badge types',
	'ACP_BADGES_TYPES_EXPLAIN'			=> 'Using this form you can add, edit, and delete badge types.',
	'ACP_BADGES_BADGES'				=> 'Manage badges',
	'ACP_BADGES_BADGES_EXPLAIN'			=> 'Using this form you can add, edit, and delete badges. Availabilities can only be added to badges that are not defined as default.',
	'INPUT_MISSING'					=> 'Please fill out all fields.',
	'BADGE_UPDATED'					=> 'The badge has been successfully updated.',
	'BADGE_ADDED'					=> 'The badge has been successfully added.',
	'BADGE_DELETED'					=> 'The badge has been successfully deleted.',
	'LOG_BADGE_UPDATED'				=> '<strong>Updated badge</strong><br>» %s',
	'LOG_BADGE_ADDED'				=> '<strong>Added new badge</strong><br>» %s',
	'LOG_BADGE_DELETED'				=> '<strong>Removed badge</strong><br>» %s',
	'ADD_BADGE'					=> 'Add badge',
	'BADGE_LABEL'					=> 'Badge label',
	'BADGE_ICON'					=> 'Badge icon',
	'BADGE_USED_BY'					=> 'Badge used by users',
	'TYPE_USED_BY'					=> 'Type used by badges',
	'INPUT_URL_EXPLAIN'				=> 'Enter the URL of the badge icon.',
	'SELECT_BADGE_TYPE'				=> 'Select badge type',
	'BADGE_SET_DEFAULT'				=> 'Set as default badge',
	'BADGE_DEFAULT'					=> '<br>(Default badge)',
	'NO_BADGES'					=> 'No badges',
	'TYPE_NAME'					=> 'Type name',
	'NO_TYPES'					=> 'No badge types',
	'ADD_TYPE'					=> 'Add badge type',
	'DUPLICATE_TYPE'				=> 'This badge type is already in use',
	'TYPE_UPDATED'					=> 'The badge type was successfully updated.',
	'LOG_TYPE_UPDATED'				=> '<strong>Updated badge type</strong><br>» %s',
	'TYPE_ADDED'					=> 'The badge type was successfully added.',
	'LOG_TYPE_ADDED'				=> '<strong>Added new badge type</strong><br>» %s',
	'TYPE_USED'					=> 'Can not delete badge type. You have to remove associated badges first.',
	'LOG_TYPE_DELETED'				=> '<strong>Removed badge type</strong><br>» %s',
	'TYPE_DELETED'					=> 'The badge type was successfully deleted.',
	'UBADGE_CONFIRM_OPERATION'			=> 'Found associated user badges. Deleting the selected badge will delete the user badges too. Do you want to continue?',
	'UBADGE_AVAILS_CONFIRM_OPERATION'		=> 'Found associated user badges and badge availabilities. Deleting the selected badge will delete the user badges and availabilities too. Do you want to continue?',
	'AVAILS_CONFIRM_OPERATION'			=> 'Found badge availabilities. Deleting the selected badge will delete the availabilities too. Do you want to continue?',
	'SET_MAX_BADGES'				=> 'Maximum profile badges',
	'SET_MAX_BADGES_EXPLAIN'			=> 'Set the maximum number of badges to be transmitted in the info response and shown in the player\'s profile information.',
	'BADGE_AVAIL'					=> 'Badge availability',
	'AVAIL_NOT_ADDED'				=> 'Could not add availabilities for user "%s". The availabilities already exist or the username could not be found.',
	'AVAIL_ADDED'					=> 'Availabilities for "%s" have been added for "%s".<br>',
	'LOG_AVAIL_ADDED'				=> '<strong>Added badge availability</strong><br>» %s for %s',
	'LOG_AVAIL_DELETED'				=> '<strong>Deleted badge availability</strong><br>» %s from %s',
	'LOG_UBADGE_DELETED'				=> '<strong>Deleted user badge</strong><br>» %s from %s',
	'AVAIL_DELETED'					=> 'The badge availabilities have been deleted successfully.',
	'UBADGE_AVAIL_DELETED'				=> 'The badge availabilities and user badges have been deleted successfully.',
	'ACP_REMOVE_ALL'				=> 'Remove all',
	'ACP_REMOVE_MARKED'				=> 'Remove marked',
	'NO_AVAILS'					=> 'No availabilities for badge.',
	'USERNAME'					=> 'Username',
));
