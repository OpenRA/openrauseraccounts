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
	protected $db;
	protected $user;
	protected $template;
	protected $request;
	protected $config;
	protected $phpbb_log;
	protected $table_prefix;
	protected $core;
	protected $path_helper;
	public $u_action;

	function __construct()
	{
		global $phpbb_container;
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
		$this_user_id = (int)$this->user->data['user_id'];

		// Request vars via GET. Actions define a context for POST events or trigger an event via GET.
		$u_id = $this->request->variable('id', 0);
		$action = $this->request->variable('action', '');

		// Request vars via POST.
		$maxbadges = $this->request->variable('maxbadges', 0, false, \phpbb\request\request_interface::POST) ? $this->request->variable('maxbadges', 0) : 0;
		$typename = $this->request->variable('typename', '', false, \phpbb\request\request_interface::POST) ? $this->request->variable('typename','') : '';
		$badge_label = $this->request->variable('label', '', false, \phpbb\request\request_interface::POST) ? $this->request->variable('label','') : '';
		$badge_icon_url = $this->request->variable('icon24', '', false, \phpbb\request\request_interface::POST) ? $this->request->variable('icon24','') : '';
		$badge_type = $this->request->variable('typeid', 0, false, \phpbb\request\request_interface::POST) ? $this->request->variable('typeid', 0) : '';
		$badge_default = $this->request->variable('default', 0, false, \phpbb\request\request_interface::POST);
		$usernames = $this->request->variable('usernames', '', true, \phpbb\request\request_interface::POST) ? array_unique(explode("\n", $this->request->variable('usernames', '', true))) : '';
		$marked = $this->request->variable('mark', array(0), false, \phpbb\request\request_interface::POST) ? $this->request->variable('mark', array(0)) : '';

		// Submits
		$submit = $this->request->variable('submit', false, false, \phpbb\request\request_interface::POST);
		$removemarked = $this->request->variable('rem_mark', false, false, \phpbb\request\request_interface::POST);
		$removeall = $this->request->variable('rem_all', false, false, \phpbb\request\request_interface::POST);

		// General template vars.
		$this->template->assign_vars(array(
			'S_MODE_' . strtoupper($mode) => true,
			'L_MODE_TITLE' => $this->user->lang('ACP_BADGES_' . strtoupper($mode)),
			'L_MODE_EXPLAIN' => $this->user->lang('ACP_BADGES_' . strtoupper($mode) . '_EXPLAIN'),
			'U_ACTION' => $this->u_action
		));

		// General references.
		$phpbb_root_path = $this->path_helper->get_phpbb_root_path();
		$phpEx = $this->path_helper->get_php_ext();
		$formid = strtolower(str_replace(' ', '', $this->user->lang(strtoupper($mode))));
		$this->tpl_name = 'acp_badges';
		$this->page_title = 'ACP_BADGES';
		$form_key = 'openra/openrauseraccounts';
		add_form_key($form_key);

		switch ($mode)
		{
			case 'settings':
			{
				if ($submit)
				{
					$this->check_form($form_key);
					$this->config->set('max_profile_badges', $maxbadges);
					$this->acp_error('CONFIG_UPDATED');
				}

				$this->template->assign_vars(array(
					'SET_MAX_BADGES' => $this->config['max_profile_badges']
				));

				break;
			}

			case 'types':
			{
				switch ($action)
				{
					case 'add':
					case 'edit':
					{
						if ($submit && $typename)
						{
							// Save added or update edited badge type.
							$this->check_form($form_key, '&amp;action=' . $action . ($u_id ? '&amp;id=' . $u_id : ''));

							$this->db->sql_query(
								'SELECT COUNT(*) AS tcount
								FROM '. $badge_type_table . '
								WHERE badge_type_name = "' . $this->db->sql_escape($typename) . '"'
							);

							if ((int)$this->db->sql_fetchfield('tcount'))
							{
								$this->acp_error('DUPLICATE_TYPE', '&amp;action=' . $action . ($u_id ? '&amp;id=' . $u_id : ''), E_USER_WARNING);
							}

							$data = array(
								'badge_type_name' => $typename
							);

							if ($u_id)
							{
								$sql = 'UPDATE ' . $badge_type_table . '
									SET ' . $this->db->sql_build_array('UPDATE', $data) . '
									WHERE badge_type_id = ' . (int)$u_id;
								$message = 'TYPE_UPDATED';
							}
							else
							{
								$sql = 'INSERT INTO ' . $badge_type_table . ' ' . $this->db->sql_build_array('INSERT', $data);
								$message = 'TYPE_ADDED';
							}

							$this->db->sql_query($sql);
							$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_' . $message, false, array($typename));
							$this->acp_error($message);
						}

						// Form for adding or editing badge type.
						$result = $this->db->sql_query("SELECT * FROM $badge_type_table");
						$type = array();

						while ($row = $this->db->sql_fetchrow($result))
						{
							if ($action == 'edit' && $u_id == $row['badge_type_id'])
							{
								$type = $row;
							}
						}

						$this->template->assign_vars(array(
							'S_' . strtoupper($action) => true,
							'U_BACK' => $this->u_action,
							'U_ACTION' => $this->u_action . '&amp;action=' . $action . ($u_id ? '&amp;id=' . $u_id : ''),
							'TYPE_NAME' => isset($type['badge_type_name']) ? $type['badge_type_name'] : '',
						));

						break;
					}

					case 'delete':
					{
						if (!confirm_box(true))
						{
							confirm_box(false, $this->user->lang['CONFIRM_OPERATION'], build_hidden_fields(array(
								'i' => $id,
								'mode' => $mode,
								'badge_type_id' => $u_id,
								'action' => $action
							)));
						}
						else
						{
							$this->db->sql_query(
								'SELECT COUNT(*) AS tcount
								FROM ' . $badge_table . '
								WHERE badge_type_id = ' . (int)$u_id
							);

							if ((int)$this->db->sql_fetchfield('tcount'))
							{
								$this->acp_error('TYPE_USED', false, E_USER_WARNING);
							}

							$this->db->sql_query(
								'SELECT badge_type_name
								FROM ' . $badge_type_table . '
								WHERE badge_type_id = ' . (int)$u_id
							);

							$typename = $this->db->sql_fetchfield('badge_type_name');

							$this->db->sql_query(
								'DELETE FROM ' . $badge_type_table . '
								WHERE badge_type_id = ' . (int)$u_id
							);

							$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_TYPE_DELETED', false, array($typename));

							if ($this->request->is_ajax())
							{
								$json_response = new \phpbb\json_response;
								$json_response->send(array(
									'MESSAGE_TITLE' => $this->user->lang['INFORMATION'],
									'MESSAGE_TEXT' => $this->user->lang['TYPE_DELETED'],
									'REFRESH_DATA' => array('time' => 3)
								));
							}
						}

						break;
					}
				}

				// Query types for the badge types table view.
				$result = $this->db->sql_query("SELECT * FROM $badge_type_table");
				$typecount = 0;

				while ($row = $this->db->sql_fetchrow($result))
				{
					$typecount++;

					// Check how often the type is used.
					$this->db->sql_query(
						'SELECT COUNT(*) AS ncount
						FROM ' . $badge_table . '
						WHERE badge_type_id = ' . $row['badge_type_id']
					);

					$badgetypecount = (int)$this->db->sql_fetchfield('ncount');

					$typerow = array(
						'TYPE_NAME' => $row['badge_type_name'],
						'TYPE_USED_BY' => $badgetypecount,
						'U_EDIT' => $this->u_action . '&amp;action=edit&amp;id=' . $row['badge_type_id'],
						'U_DELETE' => $this->u_action . '&amp;action=delete&amp;id=' . $row['badge_type_id']
					);

					$this->template->assign_block_vars('typerow', $typerow);
				}

				$this->template->assign_vars(array(
					'U_ADD' => $this->u_action . '&amp;action=add',
					'S_TYPES' => $typecount > 0
				));

				break;
			}

			case 'badges':
			{
				switch ($action)
				{
					case 'users':
					{
						// Query users for the badge availability table view.
						$result = $this->db->sql_query(
							'SELECT u.username, u.user_id
							FROM ' . USERS_TABLE . ' AS u, ' . $badge_avail_table . ' AS ba
							WHERE ba.badge_id = ' . (int)$u_id . '
							AND ba.user_id = u.user_id'
						);

						$avail_count = 0;

						while ($row = $this->db->sql_fetchrow($result))
						{
							$avail_count++;
							$availrow = array(
								'USER_NAME' => $row['username'],
								'ID' => $row['user_id']
							);

							$this->template->assign_block_vars('availrow', $availrow);
						}

						$this->template->assign_vars(array(
							'S_' . strtoupper($action) => true,
							'U_BACK' => $this->u_action,
							'U_ACTION' => $this->u_action . '&amp;action=' . $action . '&amp;id=' . $u_id,
							'U_FIND_USERNAME' => append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=searchuser&amp;form=' . $formid . '&amp;field=usernames'),
							'S_AVAILS' => $avail_count > 0
						));

						if (($removemarked && $marked) || ($removeall && $avail_count))
						{
							// Delete availabilities if requested.
							if (!confirm_box(true))
							{
								confirm_box(false, $this->user->lang['CONFIRM_OPERATION'], build_hidden_fields(array(
									'i' => $id,
									'mode' => $mode,
									'mark' => $marked,
									'rem_mark' => $removemarked,
									'rem_all' => $removeall,
									'action' => $action
									))
								);
							}
							else
							{
								$badge_label = $this->core->get_badge_label_by_id($u_id);
								$marked_users_sql = '';

								if ($removemarked)
								{
									$marked_users_sql = ' AND ' . $this->db->sql_in_set('ba.user_id', $marked);
								}

								// Query for which users to remove availabilities.
								$sql = 'SELECT u.username, ba.user_id
									FROM ' . USERS_TABLE . ' AS u, ' . $badge_avail_table . ' AS ba
									WHERE u.user_id = ba.user_id AND ba.badge_id = ' . (int)$u_id . "
									$marked_users_sql";

								$result = $this->db->sql_query($sql);

								while ($row = $this->db->sql_fetchrow($result))
								{
									// Delete availabilities.
									$this->db->sql_query(
										'DELETE FROM ' . $badge_avail_table . '
										WHERE badge_id = ' . (int)$u_id . '
										AND user_id = ' . $row['user_id']
									);

									$message = 'AVAIL_DELETED';
									$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_AVAIL_DELETED', false, array($badge_label, $row['username']));

									// Delete existing user badges.
									$this->db->sql_query(
										'DELETE FROM ' . $user_badge_table . '
										WHERE badge_id = ' . (int)$u_id . '
										AND user_id = ' . $row['user_id']
									);

									if ($this->db->sql_affectedrows())
									{
										$message = 'UBADGE_' . $message;
										$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_UBADGE_DELETED', false, array($badge_label, $row['username']));
									}
								}

								$this->acp_error($message, '&amp;action=' . $action . '&amp;id=' . $u_id);
							}
						}

						if ($submit && $usernames)
						{
							// Add the badge availabilities for the usernames entered in the textbox.
							$this->check_form($form_key, '&amp;action=' . $action . '&amp;id=' . $u_id);
							$badge_label = $this->core->get_badge_label_by_id($u_id);
							$added = $rejected = array();

							foreach ($usernames as $username)
							{
								$sql_array = array(
									'SELECT' => 'u.user_id',
									'FROM' => array(
										USERS_TABLE => 'u'
									),
									'LEFT_JOIN' => array(
										array(
											'FROM' => array($badge_avail_table => 'ba'),
											'ON' => 'ba.user_id = u.user_id AND ba.badge_id = ' . (int)$u_id
										)
									),
									// User IDs for given usernames are valid if the badge has not been awarded to the player (ba.user_id is null).
									'WHERE' => 'u.username =  "' . $this->db->sql_escape($username) . '" AND ba.user_id IS NULL'
								);

								$this->db->sql_query($this->db->sql_build_query('SELECT', $sql_array));
								$user_id = (int)$this->db->sql_fetchfield('user_id');

								if(!$user_id)
								{
									$rejected[] = $username;
								}
								else
								{
									$added[] = $username;

									$data = array(
										'user_id' => $user_id,
										'badge_id' => $u_id
									);

									$this->db->sql_query('INSERT INTO ' . $badge_avail_table . $this->db->sql_build_array('INSERT', $data));
									$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_AVAIL_ADDED', false, array($badge_label, $username));
								}
							}

							$message = '';
							$message .= ($added ? $this->user->lang('AVAIL_ADDED', $badge_label, implode(', ', $added)) : '');
							$message .= ($rejected ? $this->user->lang('AVAIL_NOT_ADDED', implode(', ', $rejected)) : '');
							$this->acp_error($message, '&amp;action=' . $action . '&amp;id=' . $u_id);
						}

						break;
					}

					case 'add':
					case 'edit':
					{
						if ($submit)
						{
							// Save added or update edited badge.
							$this->check_form($form_key, '&amp;action=' . $action . ($u_id ? '&amp;id=' . $u_id : ''));

							if ((!$badge_label || !$badge_icon_url || !$badge_type))
							{
								$this->acp_error('INPUT_MISSING', '&amp;action=' . $action . ($u_id ? '&amp;id=' . $u_id : ''), E_USER_WARNING);
							}

							$data = array(
								'badge_label' => $badge_label,
								'badge_icon_24' => $badge_icon_url,
								'badge_type_id' => $badge_type,
							);

							if ($u_id)
							{
								$this->db->sql_query(
									'UPDATE ' . $badge_table . '
									SET ' . $this->db->sql_build_array('UPDATE', $data) . '
									WHERE badge_id = ' . (int)$u_id
								);

								$message = 'BADGE_UPDATED';
								$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_' . $message, false, array($badge_label));
							}
							else
							{
								$data['badge_default'] = $badge_default;
								$this->db->sql_query('INSERT INTO ' . $badge_table . ' ' . $this->db->sql_build_array('INSERT', $data));
								$message = 'BADGE_ADDED';
								$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_' . $message, false, array($badge_label));
							}

							$this->acp_error($message);
						}

						// Form for adding or editing badges.
						$result = $this->db->sql_query("SELECT * FROM $badge_table");
						$badge = array();

						while ($row = $this->db->sql_fetchrow($result))
						{
							if ($action == 'edit' && $u_id == $row['badge_id'])
							{
								// Set badge data for the badge being edited.
								$badge = $row;
							}
						}

						// Query type options.
						$result = $this->db->sql_query("SELECT * FROM $badge_type_table");
						$typelist = '';

						while ($row = $this->db->sql_fetchrow($result))
						{
							// Set the type option for the badge being edited as selected.
							if (isset($badge['badge_type_id']) && $badge['badge_type_id'] == $row['badge_type_id'])
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

						$typelist = '<option value=""' . ($selected == '' ? ' selected="selected"' : '') . '>----------</option>' . $typelist;
						$this->template->assign_vars(array(
							'S_' . strtoupper($action) => true,
							'U_BACK' => $this->u_action,
							'U_ACTION' => $this->u_action . '&amp;action=' . $action . ($u_id ? '&amp;id=' . $u_id : ''),
							'BADGE_LABEL' => isset($badge['badge_label']) ? $badge['badge_label'] : '',
							'BADGE_ICON_URL' => isset($badge['badge_icon_24']) ? $badge['badge_icon_24'] : '',
							'S_TYPE_LIST' => $typelist,
							'S_DEFAULT' => isset($badge['badge_default']) && $badge['badge_default']
						));

						break;
					}

					case 'delete':
					{
						// Delete badge, its availabilities and user badges.
						$this->db->sql_query(
							'SELECT COUNT(*) AS tcount
							FROM ' . $user_badge_table . '
							WHERE badge_id = ' . (int)$u_id
						);

						$selects = (int)$this->db->sql_fetchfield('tcount');

						$this->db->sql_query(
							'SELECT COUNT(*) AS tcount
							FROM ' . $badge_avail_table . '
							WHERE badge_id = ' . (int)$u_id
						);

						$avails = (int)$this->db->sql_fetchfield('tcount');
						$message = ($selects ? 'UBADGE_' : '') . ($avails ? 'AVAILS_' : '') . 'CONFIRM_OPERATION';

						if (!confirm_box(true))
						{
							// Prepare the delete action before it is confirmed.
							confirm_box(false, $this->user->lang[$message], build_hidden_fields(array(
								'i' => $id,
								'mode' => $mode,
								'badge_id' => $u_id,
								'action' => $action
							)));
						}
						else
						{
							$badge_label = $this->core->get_badge_label_by_id($u_id);

							$this->db->sql_query(
								'DELETE FROM ' . $badge_table . '
								WHERE ' . $this->db->sql_in_set('badge_id', (int)$u_id)
							);

							$this->db->sql_query(
								'DELETE FROM ' . $user_badge_table . '
								WHERE ' . $this->db->sql_in_set('badge_id', (int)$u_id)
							);

							$this->db->sql_query(
								'DELETE FROM ' . $badge_avail_table . '
								WHERE ' . $this->db->sql_in_set('badge_id', (int)$u_id)
							);

							$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_BADGE_DELETED', false, array($badge_label));

							if ($this->request->is_ajax())
							{
								$json_response = new \phpbb\json_response;
								$json_response->send(array(
									'MESSAGE_TITLE' => $this->user->lang['INFORMATION'],
									'MESSAGE_TEXT' => $this->user->lang['BADGE_DELETED'],
									'REFRESH_DATA' => array('time' => 3)
								));
							}
						}

						break;
					}
				}

				// Query badges for the badges table view.
				$result = $this->db->sql_query("SELECT * FROM $badge_table ORDER BY badge_type_id");
				$typelist = array();
				$badgecount = 0;

				while ($row = $this->db->sql_fetchrow($result))
				{
					$badgecount++;

					// Check how often the badge is used.
					$this->db->sql_query(
						'SELECT COUNT(*) AS ncount
						FROM ' . $user_badge_table . '
						WHERE badge_id = ' . $row['badge_id'] . '
						AND badge_order <= ' . $this->config['max_profile_badges']
					);

					$ubadgecount = (int)$this->db->sql_fetchfield('ncount');

					$badgerow = array(
						'BADGE_LABEL' => $row['badge_label'],
						'BADGE_ICON_URL' => $row['badge_icon_24'],
						'S_DEFAULT' => $row['badge_default'],
						'BADGE_DEFAULT' => $row['badge_default'] ? $this->user->lang['BADGE_DEFAULT'] : "",
						'BADGE_USED_BY' => $ubadgecount,
						'U_EDIT' => $this->u_action . '&amp;action=edit&amp;id=' . $row['badge_id'],
						'U_USERS' => !$row['badge_default'] ? $this->u_action . '&amp;action=users&amp;id=' . $row['badge_id'] : '',
						'U_DELETE' => $this->u_action . '&amp;action=delete&amp;id=' . $row['badge_id']
					);

					// Group badges by types.
					if (!in_array($row['badge_type_id'], $typelist))
					{
						$typelist[] = $row['badge_type_id'];

						$this->db->sql_query(
							'SELECT badge_type_name AS typename
							FROM ' . $badge_type_table . '
							WHERE badge_type_id = ' . $row['badge_type_id']
						);

						$badgerow['BADGE_TYPE'] = $this->db->sql_fetchfield('typename');
					}

					$this->template->assign_block_vars('badgerow', $badgerow);
				}

				$this->template->assign_vars(array(
					'S_BADGES' => $badgecount > 0,
					'U_ADD' => $this->u_action . '&amp;action=add',
					'ICON_USERS' => '<img src="' . $this->core->get_ext_img_path() . 'add_user.png" alt="' . $this->user->lang['BADGE_AVAIL'] . '" title="' . $this->user->lang['BADGE_AVAIL'] . '" />'
				));

				break;
			}
		}
	}

	public function check_form($form_key, $append = false)
	{
		if (!check_form_key($form_key))
		{
			$this->acp_error('FORM_INVALID', $append, E_USER_WARNING);
		}
	}

	public function acp_error($error, $append = false, $warning = E_USER_NOTICE)
	{
		trigger_error($this->user->lang($error) . adm_back_link($this->u_action . ($append ? $append : '')), $warning);
	}
}
