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
	'ACP_BADGES_BADGES_EXPLAIN'			=> 'Using this form you can add, edit, and delete badges.',
	'ACP_NO_BADGE_LABEL'				=> 'You have to set a badge title.',
	'ACP_NO_BADGE_ICON'				=> 'You have to set a badge icon.',
	'ACP_NO_BADGE_TYPE'				=> 'You have to select a badge type.',
	'ACP_BADGE_UPDATED'				=> 'The badge has been successfully updated.',
	'ACP_BADGE_ADDED'				=> 'The badge has been successfully added.',
	'ACP_BADGE_DELETED'				=> 'The badge has been successfully deleted.',
	'ACP_LOG_BADGE_UPDATED'				=> '<strong>Updated badge</strong><br />» %s',
	'ACP_LOG_BADGE_ADDED'				=> '<strong>Added new badge</strong><br />» %s',
	'ACP_LOG_BADGE_DELETED'				=> '<strong>Removed badge</strong><br />» %s',
	'ACP_BADGE_UPDATE_QUERY_FAILED'			=> 'Failed to update badge data.',
	'ACP_BADGE_ADD_QUERY_FAILED'			=> 'Failed to add badge.',
	'ACP_BADGE_DELETE_QUERY_FAILED'			=> 'Failed to delete badge.',
	'ACP_BADGE_DATA_QUERY_FAILED'			=> 'Failed to query badge data.',
	'ACP_ADD_BADGE'					=> 'Add badge',
	'ACP_BADGE_LABEL'				=> 'Badge label',
	'ACP_BADGE_ICON'				=> 'Badge icon',
	'ACP_BADGE_USED_BY'				=> 'Badge used by users',
	'ACP_TYPE_USED_BY'				=> 'Type used by badges',
	'ACP_INPUT_URL_EXPLAIN'				=> 'Enter the URL of the badge icon.',
	'ACP_BADGE_TYPE'				=> 'Select badge type',
	'ACP_BADGE_SET_DEFAULT'				=> 'Set as default badge',
	'ACP_BADGE_DEFAULT'				=> '<br>(Default badge)',
	'ACP_NO_BADGES'					=> 'No badges',
	'ACP_TYPE_NAME'					=> 'Type name',
	'ACP_NO_TYPES'					=> 'No badge types',
	'ACP_ADD_TYPE'					=> 'Add badge type',
	'ACP_DUPLICATE_TYPE_QUERY_FAILED'		=> 'Failed to query type duplicates.',
	'ACP_DUPLICATE_TYPE'				=> 'This badge type is already in use',
	'ACP_TYPE_UPDATE_QUERY_FAILED'			=> 'Failed to update badge type',
	'ACP_BADGE_TYPE_UPDATED'			=> 'The badge type was successfully updated.',
	'ACP_LOG_BADGE_TYPE_UPDATED'			=> '<strong>Updated badge type</strong><br />» %s',
	'ACP_TYPE_ADD_QUERY_FAILED'			=> 'Failed to add type.',
	'ACP_TYPE_ADDED'				=> 'The badge type was successfully added.',
	'ACP_LOG_TYPE_ADDED'				=> '<strong>Added new badge type</strong><br />» %s',
	'ACP_TYPE_SELECTED_QUERY_FAILED'		=> 'Failed to select badge type.',
	'ACP_ASSOC_TYPE_QUERY_FAILED'			=> 'Failed to count associated badges before type deletion.',
	'ACP_ASSOC_BADGES'				=> 'Can not delete badge type. You have to remove associated badges first.',
	'ACP_TYPE_DELETE_QUERY_FAILED'			=> 'Failed to delete badge type.',
	'ACP_LOG_TYPE_DELETED'				=> '<strong>Removed badge type</strong><br />» %s',
	'ACP_TYPE_DELETED'				=> 'The badge type was successfully deleted.',
	'ACP_TYPE_COUNT_QUERY_FAILED'			=> 'Failed to query badge type count.',
	'ACP_TYPE_QUERY_FAILED'				=> 'Failed to query badge types.',
	'ACP_ASSOC_UBADGE_COUNT_QUERY_FAILED'		=> 'Failed to count associated user badges for badge to delete.',
	'ACP_ASSOC_UBADGE_CONFIRM'			=> 'Found associated user badges. Deleting the selected badge will delete the user badges too. Do you want to continue?',
	'ACP_ASSOC_UBADGE_AVAILS_CONFIRM'		=> 'Found associated user badges and badge availabilities. Deleting the selected badge will delete the user badges and availabilities too. Do you want to continue?',
	'ACP_AVAILS_CONFIRM'				=> 'Found badge availabilities. Deleting the selected badge will delete the availabilities too. Do you want to continue?',
	'ACP_BADGE_COUNT_QUERY_FAILED'			=> 'Failed to query badge count.',
	'ACP_TYPE_NAME_QUERY_FAILED'			=> 'Failed to query type name.',
	'ACP_SET_MAX_BADGES'				=> 'Maximum profile badges',
	'ACP_SET_MAX_BADGES_EXPLAIN'			=> 'Set the maximum number of badges to be transmitted in the info response and shown in the player\'s profile information.',
	'ACP_BADGE_AVAIL'				=> 'Badge availability',
	'ACP_USER_ID_QUERY_FAILED'			=> 'Failed to query user ids.',
	'ACP_USER_NOT_FOUND'				=> 'User "%s" could not be found. The operation was cancelled and no data was changed.',
	'ACP_BADGE_AVAIL_COUNT_QUERY_FAILED'		=> 'Failed to count badge availabilities.',
	'ACP_BADGE_AVAIL_QUERY_FAILED'			=> 'Failed to query badge availabilities',
	'ACP_BADGE_AVAIL_ADD_QUERY_FAILED'		=> 'Failed to add badge availability',
	'ACP_BADGE_LABEL_QUERY_FAILED'			=> 'Failed to query badge label.',
	'ACP_USERNAME_QUERY_FAILED'			=> 'Failed to query username.',
	'ACP_LOG_BADGE_AVAILABILITY_ADDED'		=> '<strong>Added badge availability</strong><br />» %s for %s',
	'ACP_BADGE_AVAIL_ADDED'				=> 'The badge availabilities have been successfully added.',
	'ACP_NO_NEW_USERS'				=> 'There are no new users to add.',
	'ACP_BADGE_AVAIL_DELETE_QUERY_FAILED'		=> 'Failed to delete badge availabilities.',
	'ACP_USER_BADGE_DELETE_QUERY_FAILED'		=> 'Failed to delete user badges.',
	'ACP_LOG_BADGE_AVAIL_DELETED'			=> '<strong>Deleted badge availability</strong><br />» %s from %s',
	'ACP_LOG_USER_BADGE_DELETED'			=> '<strong>Deleted user badge</strong><br />» %s from %s',
	'ACP_AVAIL_DELETED'				=> 'The badge availabilities have been deleted successfully.',
	'ACP_AVAIL_AND_UBAGDE_DELETED'			=> 'The badge availabilities and user badges have been deleted successfully.',
	'ACP_REMOVE_ALL'				=> 'Remove all',
	'ACP_REMOVE_MARKED'				=> 'Remove marked',
	'ACP_NO_AVAILS'					=> 'No availabilities for badge.',
	'ACP_USER_BADGE_COUNT_QUERY_FAILED'		=> 'Failed to count user badges.',
	'ACP_USERNAME'					=> 'Username',
));
