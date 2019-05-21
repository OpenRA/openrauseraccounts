<?php
/**
 *
 * OpenRAUserAccounts. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2018, The OpenRAUserAccounts authors
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace openra\openrauseraccounts\acp;

/**
 * OpenRAUserAccounts ACP module.
 */
class badges_module
{
	private $db;
	private $user;
	private $template;
	private $request;
	private $config;
	private $phpbb_log;
	private $table_prefix;
	private $core;
	private $path_helper;
	public $u_action;

	function __construct()
	{
		global $phpbb_container;

		// Encapsulate certain phpBB objects inside this class to minimize security issues.
		$this->db = $phpbb_container->get('dbal.conn');
		$this->user = $phpbb_container->get('user');
		$this->template = $phpbb_container->get('template');
		$this->request = $phpbb_container->get('request');
		$this->config = $phpbb_container->get('config');
		$this->phpbb_log = $phpbb_container->get('log');
		$this->table_prefix = $phpbb_container->getParameter('core.table_prefix');
		$this->core = $phpbb_container->get('openra.openrauseraccounts.core');
		$this->path_helper = $phpbb_container->get('path_helper');
	}

	public function main($id, $mode)
	{
		// Set up general vars.
		$badge_table = $this->table_prefix . 'openra_badges';
		$user_badge_table = $this->table_prefix . 'openra_user_badges';
		$badge_type_table = $this->table_prefix . 'openra_badge_types';
		$badge_avail_table = $this->table_prefix . 'openra_badge_availability';
		$action = $this->request->variable('action', '');

		// Generalized actions and requests.
		$add = $this->request->is_set_post('add') ? true : false;
		$edit = $action == 'edit' ? true : false;
		$delete = $action == 'delete' ? true : false;

		// Assign general template vars.
		$this->template->assign_vars(array(
			'L_ACP_MODE_TITLE' => $this->user->lang('ACP_BADGES_' . strtoupper($mode)),
			'L_ACP_MODE_EXPLAIN' => $this->user->lang('ACP_BADGES_' . strtoupper($mode) . '_EXPLAIN'),
			'U_ACTION' => $this->u_action
		));

		// General references.
		$phpbb_root_path = $this->path_helper->get_phpbb_root_path();
		$phpEx = $this->path_helper->get_php_ext();
		$formid = strtolower(str_replace(' ', '', $this->user->lang('ACP_' . strtoupper($mode))));
		$this->tpl_name = 'acp_badges';
		$this->page_title = 'ACP_BADGES';
		$form_key = 'openra/openrauseraccounts';
		add_form_key($form_key);

		switch ($mode)
		{
			case 'settings':
			{
				// Set up vars for mode.
				$maxbadges = $this->request->variable('maxbadges', 0);
				$save_settings = $this->request->is_set_post('maxbadges') && $action == 'save_settings' ? true : false;

				if ($save_settings)
				{
					// Check form key.
					if (!check_form_key($form_key))
					{
						trigger_error($this->user->lang['FORM_INVALID']. adm_back_link($this->u_action), E_USER_WARNING);
					}

					// Apply settings.
					$this->config->set('max_profile_badges', $maxbadges);

					trigger_error($this->user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
				}

				// Assign template vars for mode.
				$this->template->assign_vars(array(
					'S_ACP_MODE_SETTINGS' => true,
					'SET_MAX_BADGES' => $this->config['max_profile_badges']
				));

				break;
			}

			case 'types':
			{
				// Set up vars for mode.
				$type_id = $this->request->variable('id', 0);
				$typename = $this->request->variable('typename', '');
				$save_type = $this->request->is_set_post('typename') && $action == 'save_type' ? true : false;

				// Save added or update edited badge type.
				if ($typename && $save_type) // Don't do anything if type name is not set.
				{
					// Check form key.
					if (!check_form_key($form_key))
					{
						trigger_error($this->user->lang['FORM_INVALID']. adm_back_link($this->u_action), E_USER_WARNING);
					}

					// Reject duplicates.
					$sql = 'SELECT COUNT(*) as namecount
						FROM '. $badge_type_table . '
						WHERE badge_type_name = "' . $this->db->sql_escape($typename) . '"';
					if (!($result = $this->db->sql_query($sql)))
					{
						trigger_error($this->user->lang('ACP_DUPLICATE_TYPE_QUERY_FAILED') . adm_back_link($this->u_action), E_USER_WARNING);
					}
					$duplicates = (int) $this->db->sql_fetchfield('namecount');
					$this->db->sql_freeresult($result);
					if ($duplicates)
					{
						trigger_error($this->user->lang('ACP_DUPLICATE_TYPE') . adm_back_link($this->u_action), E_USER_WARNING);
					}

					// Prepare type data (we might want to add more at some time).
					$data = array(
						'badge_type_name' => $typename
					);

					if ($type_id) // Updating badge type.
					{
						$sql = 'UPDATE ' . $badge_type_table . '
							SET ' . $this->db->sql_build_array('UPDATE', $data) . '
							WHERE badge_type_id = ' . $type_id;
						if (!($result = $this->db->sql_query($sql)))
						{
							trigger_error($this->user->lang['ACP_TYPE_UPDATE_QUERY_FAILED'] . adm_back_link($this->u_action), E_USER_WARNING);
						}
						$message = $this->user->lang['ACP_BADGE_TYPE_UPDATED'];
						$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'ACP_LOG_BADGE_TYPE_UPDATED', false, array($typename));
					}
					else // Adding badge type.
					{
						// Insert badge type data.
						$sql = 'INSERT INTO ' . $badge_type_table . ' ' . $this->db->sql_build_array('INSERT', $data);
						if (!($result = $this->db->sql_query($sql)))
						{
							trigger_error($this->user->lang['ACP_TYPE_ADD_QUERY_FAILED'] . adm_back_link($this->u_action), E_USER_WARNING);
						}
						$message = $this->user->lang['ACP_TYPE_ADDED'];
						$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'ACP_LOG_TYPE_ADDED', false, array($typename));
					}
					// Show appropriate success message and exit.
					trigger_error($message . adm_back_link($this->u_action));
				}

				// Delete badge type.
				if ($type_id && $delete)
				{
					if (!confirm_box(true))
					{
						// Prepare the delete action before it is confirmed.
						confirm_box(false, $this->user->lang['CONFIRM_OPERATION'], build_hidden_fields(array(
							'i'		=> $id,
							'mode'		=> $mode,
							'badge_type_id'	=> $type_id,
							'action'	=> 'delete'
						)));
					}
					else
					{
						// Retrieve the name of the selected badge type for a log entry after deletion.
						$sql = "SELECT badge_type_name
							FROM $badge_type_table
							WHERE badge_type_id = $type_id";
						if (!($result = $this->db->sql_query($sql)))
						{
							trigger_error($this->user->lang['ACP_TYPE_SELECTED_QUERY_FAILED'] . adm_back_link($this->u_action), E_USER_WARNING);
						}
						$typename = (string) $this->db->sql_fetchfield('badge_type_name');
						$this->db->sql_freeresult($result);

						// Check if there are any badges associated with the badge type to delete.
						$sql = "SELECT COUNT(*) as assoccount
							FROM $badge_table
							WHERE badge_type_id = $type_id";
						if (!($result = $this->db->sql_query($sql)))
						{
							trigger_error($this->user->lang('ACP_ASSOC_TYPE_QUERY_FAILED') . adm_back_link($this->u_action), E_USER_WARNING);
						}
						$associated = (int) $this->db->sql_fetchfield('assoccount');
						$this->db->sql_freeresult($result);
						if ($associated)
						{
							trigger_error($this->user->lang('ACP_ASSOC_BADGES') . adm_back_link($this->u_action), E_USER_WARNING);
						}

						// Delete selected badge type.
						$sql = 'DELETE FROM ' . $badge_type_table . '
							WHERE ' . $this->db->sql_in_set('badge_type_id', $type_id);
						if (!($result = $this->db->sql_query($sql)))
						{
							trigger_error($this->user->lang['ACP_TYPE_DELETE_QUERY_FAILED'] . adm_back_link($this->u_action), E_USER_WARNING);
						}
						// Add a log entry about type deletion.
						$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'ACP_LOG_TYPE_DELETED', false, array($typename));

						// Show the success message.
						if ($this->request->is_ajax())
						{
							$json_response = new \phpbb\json_response;
							$json_response->send(array(
								'MESSAGE_TITLE'	=> $this->user->lang['INFORMATION'],
								'MESSAGE_TEXT'	=> $this->user->lang['ACP_TYPE_DELETED'],
								'REFRESH_DATA'	=> array('time' => 3)
							));
						}
					}
				}

				// Add or edit badge type.
				if ($add || $edit)
				{
					// Get data of existing badge types.
					$sql = "SELECT * FROM $badge_type_table";
					if (!($result = $this->db->sql_query($sql)))
					{
						trigger_error($this->user->lang['ACP_TYPE_QUERY_FAILED'] . adm_back_link($this->u_action), E_USER_WARNING);
					}
					// Loop over badge types.
					$types = array();
					while ($row = $this->db->sql_fetchrow($result))
					{
						if ($action == 'edit' && $type_id == $row['badge_type_id'])
						{
							// Set type data for the type being edited.
							$types = $row;
						}
					}
					$this->db->sql_freeresult($result);

					// Set up template vars for action
					$this->template->assign_vars(array(
						'S_EDIT'	=> true,
						'S_ADD'		=> empty($types) ? true : false,
						'U_BACK'	=> $this->u_action,
						'U_ACTION'	=> $this->u_action . '&amp;id=' . $type_id,
						'TYPE_NAME'	=> isset($types['badge_type_name']) ? $types['badge_type_name'] : '',
					));
				}

				// Count available badge types
				$sql = "SELECT COUNT(*) as typecount
					FROM $badge_type_table";
				if (!($result = $this->db->sql_query($sql)))
				{
					trigger_error($this->user->lang('ACP_TYPE_COUNT_QUERY_FAILED') . adm_back_link($this->u_action), E_USER_WARNING);
				}
				$typecount = (int) $this->db->sql_fetchfield('typecount');
				$this->db->sql_freeresult($result);

				if ($typecount)
				{
					// Retrieve type data.
					$sql = "SELECT * FROM $badge_type_table";
					if (!($result = $this->db->sql_query($sql)))
					{
						trigger_error($this->user->lang['ACP_TYPE_QUERY_FAILED'] . adm_back_link($this->u_action), E_USER_WARNING);
					}

					// Loop over retrieved type data.
					while ($row = $this->db->sql_fetchrow($result))
					{
						// Check how often the type is used.
						$sql = 'SELECT COUNT(*) AS badgetypecount
							FROM ' . $badge_table . '
							WHERE badge_type_id = ' . $row['badge_type_id'];
						if (!($countresult = $this->db->sql_query($sql)))
						{
							trigger_error($this->user->lang('ACP_BADGE_COUNT_QUERY_FAILED') . adm_back_link($this->u_action), E_USER_WARNING);
						}
						$badgetypecount = (int) $this->db->sql_fetchfield('badgetypecount');
						$this->db->sql_freeresult($countresult);

						$typerow = array(
							'TYPE_NAME'	=> $row['badge_type_name'],
							'TYPE_USED_BY'	=> $badgetypecount,
							'U_EDIT'	=> $this->u_action . '&amp;action=edit&amp;id=' . $row['badge_type_id'],
							'U_DELETE'	=> $this->u_action . '&amp;action=delete&amp;id=' . $row['badge_type_id']
						);
						$this->template->assign_block_vars('typerow', $typerow);
					}
					$this->db->sql_freeresult($result);
				}

				// Assign template vars for mode.
				$this->template->assign_vars(array(
					'S_ACP_MODE_TYPES'	=> true,
					'S_TYPES'		=> ($typecount > 0),
				));

				break;
			}

			case 'badges':
			{
				// Set up vars for mode.
				$badge_id = $this->request->variable('id', 0);
				$badge_label = $this->request->variable('label', '');
				$badge_icon_url = $this->request->variable('icon24', '');
				$badge_type = $this->request->variable('typeid', '');
				$badge_default = $this->request->variable('default', 1);
				$save_badge = $this->request->is_set_post('label') && $action == 'save_badge' ? true : false;

				// Badge availability
				$usernames = $this->request->variable('usernames', '', true); // 'true' for enabling 'multibyte' which automatically normalizes unicode to utf8.
				$users = $action == 'users' ? true : false;
				$add_users = $this->request->is_set_post('addusers') ? true : false;
				$markedusers = $this->request->variable('markedusers', array(0)); // user ids
				$removemark = $this->request->is_set_post('rem_marked') ? true : false;
				$removeall = $this->request->is_set_post('rem_all') ? true : false;

				// View for which users a selected (non-default) badge is available.
				if ($users)
				{
					// Count availabilities for the selected badge.
					$sql = "SELECT COUNT(*) AS availcount
						FROM $badge_avail_table
						WHERE badge_id = $badge_id";
					if (!($result = $this->db->sql_query($sql)))
					{
						trigger_error($this->user->lang('ACP_BADGE_AVAIL_COUNT_QUERY_FAILED') . adm_back_link($this->u_action), E_USER_WARNING);
					}
					$badge_avail_count = (int) $this->db->sql_fetchfield('availcount');
					$this->db->sql_freeresult($result);

					if ($badge_avail_count)
					{
						// Retrieve user ids of badge availabilities.
						$sql = "SELECT user_id
							FROM $badge_avail_table
							WHERE badge_id = $badge_id";
						if (!($result = $this->db->sql_query($sql)))
						{
							trigger_error($this->user->lang('ACP_BADGE_AVAIL_QUERY_FAILED') . adm_back_link($this->u_action), E_USER_WARNING);
						}
						while ($row = $this->db->sql_fetchrow($result))
						{
							// Get the username for the current user id.
							$sql = 'SELECT username as username
								FROM ' . USERS_TABLE . '
								WHERE user_id = ' . $row['user_id'];
							if (!($subresult = $this->db->sql_query($sql)))
							{
								trigger_error($this->user->lang('ACP_USERNAME_QUERY_FAILED') . adm_back_link($this->u_action), E_USER_WARNING);
							}
							$username = (string) $this->db->sql_fetchfield('username');
							$this->db->sql_freeresult($subresult);

							// We might want to add more data in the future.
							$availrow = array(
								'USER_NAME' => $username,
								'ID' =>  $row['user_id']
							);
							$this->template->assign_block_vars('availrow', $availrow);
						}
						$this->db->sql_freeresult($result);
					}

					// Set up template vars for action.
					$this->template->assign_vars(array(
						'S_USERS' => true,
						'U_BACK' => $this->u_action,
						'U_ACTION' => $this->u_action . '&amp;id=' . $badge_id,
						'U_FIND_USERNAME' => append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=searchuser&amp;form=' . $formid . '&amp;field=usernames'),
						'S_AVAILS' => ($badge_avail_count > 0)
					));
				}

				// Delete availabilities if requested.
				if ($removemark || $removeall)
				{
					// No need to check the form key when using confirm_box().
					if (!confirm_box(true))
					{
						// Prepare the delete action before it is confirmed.
						confirm_box(false, $this->user->lang['CONFIRM_OPERATION'], build_hidden_fields(array(
							'i' => $id,
							'mode' => $mode,
							'markedusers' => $markedusers,
							'rem_all' =>  $removeall, // Will be 'true' after this.
							'rem_marked' => $removemark // Will be 'true' after this.
							))
						);
					}
					else
					{
						// Get the badge label for the admin log entry. TODO: Duplicate query.
						$sql = "SELECT badge_label as label
							FROM $badge_table
							WHERE badge_id = $badge_id";
						if (!($result = $this->db->sql_query($sql)))
						{
							trigger_error($this->user->lang('ACP_BADGE_LABEL_QUERY_FAILED') . adm_back_link($this->u_action . '&amp;action=users&amp;id=' . $badge_id), E_USER_WARNING);
						}
						$badge_label = (string) $this->db->sql_fetchfield('label');
						$this->db->sql_freeresult($result);

						// Get the usernames for the log entry.
						$usernames = array();
						if ($removemark && $markedusers)
						{
							$sql = 'SELECT username
								FROM ' . USERS_TABLE . '
								WHERE ' . $this->db->sql_in_set('user_id', $markedusers);
						}
						else
						{
							$sql = 'SELECT username
								FROM ' . USERS_TABLE . ' AS u, ' . $badge_avail_table . ' AS ba
								WHERE u.user_id = ba.user_id
								AND ba.badge_id = ' . $badge_id;
						}
						if (!($result = $this->db->sql_query($sql)))
						{
							trigger_error($this->user->lang('ACP_USERNAME_QUERY_FAILED') . adm_back_link($this->u_action . '&amp;action=users&amp;id=' . $badge_id), E_USER_WARNING);
						}
						while ($row = $this->db->sql_fetchrow($result))
						{
							$usernames[] = $row['username'];
						}
						$this->db->sql_freeresult($result);

						// Store the marked item ids and prepare the sql clause.
						if ($removemark && $markedusers)
						{
							$marked_users_sql = ' AND ' . $this->db->sql_in_set('user_id', $markedusers);
						}

						// Delete either all or marked availabilities.
						if ($marked_users_sql || $removeall)
						{
							// Delete availabilities.
							$sql = 'DELETE FROM ' . $badge_avail_table . '
								WHERE badge_id = ' . $badge_id . "
								$marked_users_sql";
							if (!($result = $this->db->sql_query($sql)))
							{
								trigger_error($this->user->lang('ACP_BADGE_AVAIL_DELETE_QUERY_FAILED') . adm_back_link($this->u_action), E_USER_WARNING);
							}
							$this->db->sql_freeresult($result);
							$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'ACP_LOG_BADGE_AVAIL_DELETED', false, array($badge_label, implode(', ', $usernames)));
							$message = $this->user->lang('ACP_AVAIL_DELETED');

							// Check for existing user badges.
							$sql = 'SELECT COUNT(*) AS ubadgecount
								FROM ' . $user_badge_table . '
								WHERE badge_id = ' . $badge_id . "
								$marked_users_sql";
							if (!($result = $this->db->sql_query($sql)))
							{
								trigger_error($this->user->lang('ACP_USER_BADGE_COUNT_QUERY_FAILED') . adm_back_link($this->u_action), E_USER_WARNING);
							}
							$ubadgecount = (int) $this->db->sql_fetchfield('ubadgecount');

							if ($ubadgecount)
							{
								// Delete existing user badges.
								$sql = 'DELETE FROM ' . $user_badge_table . '
									WHERE badge_id = ' . $badge_id . "
								$marked_users_sql";
								if (!($result = $this->db->sql_query($sql)))
								{
									trigger_error($this->user->lang('ACP_USER_BADGE_DELETE_QUERY_FAILED') . adm_back_link($this->u_action), E_USER_WARNING);
								}
								$this->db->sql_freeresult($result);
								$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'ACP_LOG_USER_BADGE_DELETED', false, array($badge_label, implode(', ', $usernames)));
								$message = $this->user->lang('ACP_AVAIL_AND_UBAGDE_DELETED');
							}
							unset($markedusers, $usernames);
							trigger_error($this->user->lang($message) . adm_back_link($this->u_action . '&amp;action=users&amp;id=' . $badge_id));
						}
					}
				}

				// Add the selected badges to the user badge table for the usernames entered in the textbox.
				if ($add_users && $usernames && $badge_id) // Don't do anything if textarea is empty.
				{
					// Check form key.
					if (!check_form_key($form_key))
					{
						trigger_error($this->user->lang['FORM_INVALID']. adm_back_link($this->u_action), E_USER_WARNING);
					}

					$usernames = array_unique(explode("\n", $usernames)); // Remove duplicates from input and prepare the array.

					// Get the user id for each entered username or cancel the operation if the username is not in the user db.
					$userids = array(); // Prepare array for the ids.
					foreach ($usernames as $username)
					{
						$sql = 'SELECT user_id AS userid
							FROM ' . USERS_TABLE . '
							WHERE username = "' . $this->db->sql_escape($username) . '"';
						if (!($result = $this->db->sql_query($sql)))
						{
							trigger_error($this->user->lang('ACP_USER_ID_QUERY_FAILED') . adm_back_link($this->u_action . '&amp;action=users&amp;id=' . $badge_id), E_USER_WARNING);
						}
						$user_id = (int) $this->db->sql_fetchfield('userid');
						$this->db->sql_freeresult($result);

						if(!$user_id)
						{
							trigger_error($this->user->lang('ACP_USER_NOT_FOUND', $username) . adm_back_link($this->u_action . '&amp;action=users&amp;id=' . $badge_id), E_USER_WARNING);
						}
						// Filter duplicates.
						$sql = "SELECT COUNT(*) as idcount
							FROM $badge_avail_table
							WHERE user_id = $user_id
							AND badge_id = $badge_id";
						if (!($result = $this->db->sql_query($sql)))
						{
							trigger_error($this->user->lang('ACP_BADGE_AVAIL_COUNT_QUERY_FAILED') . adm_back_link($this->u_action . '&amp;action=users&amp;id=' . $badge_id), E_USER_WARNING);
						}
						$duplicates = (int) $this->db->sql_fetchfield('idcount');
						$this->db->sql_freeresult($result);
						if(!$duplicates)
						{
							$new_user_ids[] = $user_id;
						}
					}

					if (!$new_user_ids)
					{
						trigger_error($this->user->lang('ACP_NO_NEW_USERS') . adm_back_link($this->u_action . '&amp;action=users&amp;id=' . $badge_id), E_USER_WARNING);
					}

					// Get the badge label for the admin log entry.
					$sql = "SELECT badge_label as label
						FROM $badge_table
						WHERE badge_id = $badge_id";
					if (!($result = $this->db->sql_query($sql)))
					{
						trigger_error($this->user->lang('ACP_BADGE_LABEL_QUERY_FAILED') . adm_back_link($this->u_action . '&amp;action=users&amp;id=' . $badge_id), E_USER_WARNING);
					}
					$badge_label = (string) $this->db->sql_fetchfield('label');
					$this->db->sql_freeresult($result);

					foreach ($new_user_ids as $new_user_id)
					{
						// Get the username for the current user id.
						$sql = 'SELECT username as username
							FROM ' . USERS_TABLE . '
							WHERE user_id = ' . $new_user_id;
						if (!($result = $this->db->sql_query($sql)))
						{
							trigger_error($this->user->lang('ACP_USERNAME_QUERY_FAILED') . adm_back_link($this->u_action . '&amp;action=users&amp;id=' . $badge_id), E_USER_WARNING);
						}
						$username = (string) $this->db->sql_fetchfield('username');
						$this->db->sql_freeresult($result);

						$data = array(
							'user_id' => $new_user_id,
							'badge_id' => $badge_id
						);
						// Insert badge data.
						$sql = 'INSERT INTO ' . $badge_avail_table . $this->db->sql_build_array('INSERT', $data);
						if (!($result = $this->db->sql_query($sql)))
						{
							trigger_error($this->user->lang['ACP_BADGE_AVAIL_ADD_QUERY_FAILED'] . adm_back_link($this->u_action . '&amp;action=users&amp;id=' . $badge_id), E_USER_WARNING);
						}
						$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'ACP_LOG_BADGE_AVAILABILITY_ADDED', false, array($badge_label, $username));
						$this->db->sql_freeresult($result);
					}
					trigger_error($this->user->lang['ACP_BADGE_AVAIL_ADDED'] . adm_back_link($this->u_action . '&amp;action=users&amp;id=' . $badge_id));
				}

				// Save added or update edited badge.
				if ($save_badge) // Here we use errors if input data is missing.
				{
					// Check form key.
					if (!check_form_key($form_key))
					{
						trigger_error($this->user->lang['FORM_INVALID']. adm_back_link($this->u_action), E_USER_WARNING);
					}

					// Don't save if label is not set.
					if (!$badge_label)
					{
						trigger_error($this->user->lang['ACP_NO_BADGE_LABEL'] . adm_back_link($this->u_action), E_USER_WARNING);
					}
					// Don't save if icon url is not set.
					if (!$badge_icon_url)
					{
						trigger_error($this->user->lang['ACP_NO_BADGE_ICON'] . adm_back_link($this->u_action), E_USER_WARNING);
					}
					// Don't save if type is not selected.
					if (!$badge_type)
					{
						trigger_error($this->user->lang['ACP_NO_BADGE_TYPE'] . adm_back_link($this->u_action), E_USER_WARNING);
					}

					// TODO: Check here for duplicates but allow to change options for existing badges.

					// Prepare badge data.
					$data = array(
						'badge_label' => $badge_label,
						'badge_icon_24' => $badge_icon_url,
						'badge_type_id' => $badge_type,
					);

					if ($badge_id) // Updating badge.
					{
						$sql = 'UPDATE ' . $badge_table . '
							SET ' . $this->db->sql_build_array('UPDATE', $data) . '
							WHERE badge_id = ' . $badge_id;
						if (!($result = $this->db->sql_query($sql)))
						{
							trigger_error($this->user->lang['ACP_BADGE_UPDATE_QUERY_FAILED'] . adm_back_link($this->u_action), E_USER_WARNING);
						}
						$message = $this->user->lang['ACP_BADGE_UPDATED'];
						$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'ACP_LOG_BADGE_UPDATED', false, array($badge_label));
					}
					else // Adding badge.
					{
						// Add setting for badge_default to array. This may not be changed when updating bagdes.
						$data['badge_default'] = $badge_default;
						// Insert badge data.
						$sql = 'INSERT INTO ' . $badge_table . ' ' . $this->db->sql_build_array('INSERT', $data);
						if (!($result = $this->db->sql_query($sql)))
						{
							trigger_error($this->user->lang['ACP_BADGE_ADD_QUERY_FAILED'] . adm_back_link($this->u_action), E_USER_WARNING);
						}
						$message = $this->user->lang['ACP_BADGE_ADDED'];
						$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'ACP_LOG_BADGE_ADDED', false, array($badge_label));
					}
					// Show appropriate success message and exit.
					trigger_error($message . adm_back_link($this->u_action));
				}

				// Delete badge, it's availabilities and associated user badges.
				if ($badge_id && $delete)
				{
					// Count associated user badges of the badge to delete.
					$sql = "SELECT COUNT(*) as assoccount
						FROM $user_badge_table
						WHERE badge_id = $badge_id";
					if (!($result = $this->db->sql_query($sql)))
					{
						trigger_error($this->user->lang('ACP_ASSOC_UBADGE_COUNT_QUERY_FAILED') . adm_back_link($this->u_action), E_USER_WARNING);
					}
					$associated = (int) $this->db->sql_fetchfield('assoccount');
					$this->db->sql_freeresult($result);

					// Count availabilities for the selected badge. TODO: Duplicate query.
					$sql = "SELECT COUNT(*) AS availcount
						FROM $badge_avail_table
						WHERE badge_id = $badge_id";
					if (!($result = $this->db->sql_query($sql)))
					{
						trigger_error($this->user->lang('ACP_BADGE_AVAIL_COUNT_QUERY_FAILED') . adm_back_link($this->u_action), E_USER_WARNING);
					}
					$avails = (int) $this->db->sql_fetchfield('availcount');
					$this->db->sql_freeresult($result);
					// Set the appropriate warning for the confirm box.
					($associated && !$avails) ? $message = 'ACP_ASSOC_UBADGE_CONFIRM' : $message = 'CONFIRM_OPERATION';
					(!$associated && $avails) ? $message = 'ACP_AVAILS_CONFIRM' : $message = 'CONFIRM_OPERATION';
					($associated && $avails) ? $message = 'ACP_ASSOC_UBADGE_AVAILS_CONFIRM' : $message = 'CONFIRM_OPERATION';


					if (!confirm_box(true))
					{
						// Prepare the delete action before it is confirmed.
						confirm_box(false, $this->user->lang[$message], build_hidden_fields(array(
							'i'		=> $id,
							'mode'		=> $mode,
							'badge_id'	=> $badge_id,
							'action'	=> 'delete'
						)));
					}
					else
					{
						// Get the badge label for the admin log entry.
						$sql = "SELECT badge_label as label
							FROM $badge_table
							WHERE badge_id = $badge_id";
						if (!($result = $this->db->sql_query($sql)))
						{
							trigger_error($this->user->lang('ACP_BADGE_LABEL_QUERY_FAILED') . adm_back_link($this->u_action . '&amp;action=users&amp;id=' . $badge_id), E_USER_WARNING);
						}
						$badge_label = (string) $this->db->sql_fetchfield('label');
						$this->db->sql_freeresult($result);

						// Delete data for selected badge from the badge table.
						$sql = 'DELETE FROM ' . $badge_table . '
							WHERE ' . $this->db->sql_in_set('badge_id', $badge_id);
						if (!($result = $this->db->sql_query($sql)))
						{
							trigger_error($this->user->lang['ACP_BADGE_DELETE_QUERY_FAILED'] . adm_back_link($this->u_action), E_USER_WARNING);
						}

						// Delete data for selected badge from the user badge table.
						$sql = 'DELETE FROM ' . $user_badge_table . '
							WHERE ' . $this->db->sql_in_set('badge_id', $badge_id);
						if (!($result = $this->db->sql_query($sql)))
						{
							trigger_error($this->user->lang['ACP_BADGE_DELETE_QUERY_FAILED'] . adm_back_link($this->u_action), E_USER_WARNING);
						}

						// Delete data for selected badge from the badge availability table.
						$sql = 'DELETE FROM ' . $badge_avail_table . '
							WHERE ' . $this->db->sql_in_set('badge_id', $badge_id);
						if (!($result = $this->db->sql_query($sql)))
						{
							trigger_error($this->user->lang['ACP_BADGE_DELETE_QUERY_FAILED'] . adm_back_link($this->u_action), E_USER_WARNING);
						}

						// Add a log entry about badge deletion.
						$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'ACP_LOG_BADGE_DELETED', false, array($badge_label));

						// Show the success message.
						if ($this->request->is_ajax())
						{
							$json_response = new \phpbb\json_response;
							$json_response->send(array(
								'MESSAGE_TITLE'	=> $this->user->lang['INFORMATION'],
								'MESSAGE_TEXT'	=> $this->user->lang['ACP_BADGE_DELETED'],
								'REFRESH_DATA'	=> array('time' => 3)
							));
						}
					}
				}

				// Add or edit badge.
				if ($add || $edit)
				{
					// Get data of existing badges.
					$sql = "SELECT * FROM $badge_table";
					if (!($result = $this->db->sql_query($sql)))
					{
						trigger_error($this->user->lang['ACP_BADGE_DATA_QUERY_FAILED'] . adm_back_link($this->u_action), E_USER_WARNING);
					}
					// Loop over badges.
					$badges = array();
					while ($row = $this->db->sql_fetchrow($result))
					{
						if ($action == 'edit' && $badge_id == $row['badge_id'])
						{
							// Set badge data for the badge being edited.
							$badges = $row;
						}
					}
					$this->db->sql_freeresult($result);

					// Get data of existing badge types.
					$sql = "SELECT * FROM $badge_type_table";
					if (!($result = $this->db->sql_query($sql)))
					{
						trigger_error($this->user->lang['ACP_TYPE_QUERY_FAILED'] . adm_back_link($this->u_action), E_USER_WARNING);
					}
					// Prepare a string for the options in the select box.
					$typelist = '';
					// Loop over badge types.
					while ($row = $this->db->sql_fetchrow($result))
					{
						// Set the type option for the badge being edited as selected.
						if (isset($badges['badge_type_id']) && $badges['badge_type_id'] == $row['badge_type_id'])
						{
							$selected = ' selected="selected"';
						}
						else
						{
							$selected = '';
						}
						// Create the list of options for the type selection box.
						$typelist .= '<option value="' . $row['badge_type_id'] . '"' . $selected . '>' . $row['badge_type_name'] . '</option>';
					}
					$typelist = '<option value=""' . (($selected == '') ? ' selected="selected"' : '') . '>----------</option>' . $typelist;
					$this->db->sql_freeresult($result);

					// Set up template vars for action
					$this->template->assign_vars(array(
						'S_EDIT'		=> true,
						'S_ADD'			=> (empty($badges)) ? true : false,
						'U_BACK'		=> $this->u_action,
						'U_ACTION'		=> $this->u_action . '&amp;id=' . $badge_id,
						'BADGE_LABEL'		=> (isset($badges['badge_label'])) ? $badges['badge_label'] : '',
						'BADGE_ICON_URL'	=> (isset($badges['badge_icon_24'])) ? $badges['badge_icon_24'] : '',
						'S_TYPE_LIST'		=> $typelist,
						'S_DEFAULT'		=> (isset($badges['badge_default']) && $badges['badge_default']) ? true : false
					));
				}

				// Count available badges.
				$sql = "SELECT COUNT(*) as badgecount
					FROM $badge_table";
				if (!($result = $this->db->sql_query($sql)))
				{
					trigger_error($this->user->lang('ACP_BADGE_COUNT_QUERY_FAILED') . adm_back_link($this->u_action), E_USER_WARNING);
				}
				$badgecount = (int) $this->db->sql_fetchfield('badgecount');
				$this->db->sql_freeresult($result);

				if ($badgecount)
				{
					// Retrieve badge data.
					$sql = "SELECT * FROM $badge_table ORDER BY badge_type_id";
					if (!($result = $this->db->sql_query($sql)))
					{
						trigger_error($this->user->lang['ACP_BADGE_DATA_QUERY_FAILED'] . adm_back_link($this->u_action), E_USER_WARNING);
					}
					// Prepare an array to group badges by types.
					$typelist = array();
					// Loop over retrieved badge data
					while ($row = $this->db->sql_fetchrow($result))
					{
						// Check how often the badge is used.
						$sql = 'SELECT COUNT(*) AS ubadgecount
							FROM ' . $user_badge_table . '
							WHERE badge_id = ' . $row['badge_id'] . '
							AND badge_order <= ' . $this->config['max_profile_badges']; // Only consider those that are shown in the profile information.
						if (!($countresult = $this->db->sql_query($sql)))
						{
							trigger_error($this->user->lang('ACP_BADGE_COUNT_QUERY_FAILED') . adm_back_link($this->u_action), E_USER_WARNING);
						}
						$ubadgecount = (int) $this->db->sql_fetchfield('ubadgecount');
						$this->db->sql_freeresult($countresult);

						$badgerow = array(
							'BADGE_LABEL'		=> $row['badge_label'],
							'BADGE_ICON_URL'	=> $row['badge_icon_24'],
							'S_DEFAULT'		=> $row['badge_default'],
							'BADGE_DEFAULT'		=> $row['badge_default'] ? $this->user->lang['ACP_BADGE_DEFAULT'] : "",
							'BADGE_USED_BY'		=> $ubadgecount,
							'U_EDIT'		=> $this->u_action . '&amp;action=edit&amp;id=' . $row['badge_id'],
							'U_USERS'		=> !$row['badge_default'] ? $this->u_action . '&amp;action=users&amp;id=' . $row['badge_id'] : '',
							'U_DELETE'		=> $this->u_action . '&amp;action=delete&amp;id=' . $row['badge_id']
						);
						// Check if the type id is in the typelist, if not, add it to the list and its name to the blockvars for the current row.
						if (!in_array($row['badge_type_id'], $typelist))
						{
							$typelist[] = $row['badge_type_id'];
							$sql = 'SELECT badge_type_name AS typename
								FROM ' . $badge_type_table . '
								WHERE badge_type_id = ' . $row['badge_type_id'];
							if (!($subresult = $this->db->sql_query($sql)))
							{
								trigger_error($this->user->lang('ACP_TYPE_NAME_QUERY_FAILED') . adm_back_link($this->u_action), E_USER_WARNING);
							}
							$badgerow['BADGE_TYPE'] = $this->db->sql_fetchfield('typename');
							$this->db->sql_freeresult($subresult);
						}
						$this->template->assign_block_vars('badgerow', $badgerow);
					}
					$this->db->sql_freeresult($result);
				}

				// Assign template vars for mode.
				$this->template->assign_vars(array(
					'S_ACP_MODE_BADGES' => true,
					'S_BADGES' => ($badgecount > 0),
					'ICON_USERS' => '<img src="' . $this->core->get_ext_img_path() . 'add_user.png" alt="' . $this->user->lang['ACP_BADGE_AVAIL'] . '" title="' . $this->user->lang['ACP_BADGE_AVAIL'] . '" />'
				));

				break;
			}

		}
	}
}
