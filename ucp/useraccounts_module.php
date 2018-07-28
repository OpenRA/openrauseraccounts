<?php
/**
 *
 * OpenRAUserAccounts. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2018, The OpenRAUserAccounts authors
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace openra\openrauseraccounts\ucp;

/**
 * OpenRAUserAccounts UCP module.
 */
class useraccounts_module
{
	private $db;
	private $request;
	private $template;
	private $user;
	private $table_prefix;
	private $pagination;
	private $config;
	private $path_helper;
	private $core;
	public $u_action;

	function __construct()
	{
		global $phpbb_container;

		$this->db = $phpbb_container->get('dbal.conn');
		$this->request = $phpbb_container->get('request');
		$this->template = $phpbb_container->get('template');
		$this->user = $phpbb_container->get('user');
		$this->table_prefix = $phpbb_container->getParameter('core.table_prefix');
		$this->pagination = $phpbb_container->get('pagination');
		$this->config = $phpbb_container->get('config');
		$this->path_helper = $phpbb_container->get('path_helper');
		$this->core = $phpbb_container->get('openra.openrauseraccounts.core');
	}

	function main($id, $mode)
	{
		// Manage single and multiple actions.
		$action = $this->request->variable('action', array('' => ''));
		if (is_array($action)){list($action, ) = each($action);}
		else {$action = $this->request->variable('action', '');}

		// Set up general vars.
		$key_table = $this->table_prefix . 'openra_keys';
		$badge_table = $this->table_prefix . 'openra_badges';
		$user_badge_table = $this->table_prefix . 'openra_user_badges';
		$badge_type_table = $this->table_prefix . 'openra_badge_types';
		$badge_avail_table = $this->table_prefix . 'openra_badge_availability';
		$this_user_id = $this->user->data['user_id'];
		$this->tpl_name = 'ucp_useraccounts';
		$this->page_title = $this->user->lang('UCP_TITLE');

		// Assign general template vars.
		$this->template->assign_vars(array(
			'L_UCP_MODE_TITLE' => $this->user->lang('UCP_' . strtoupper($mode)),
			'L_UCP_MODE_EXPLAIN' => $this->user->lang('UCP_' . strtoupper($mode) . '_EXPLAIN'),
			'U_POST_ACTION' => $this->u_action
		));
		$form_key = 'openra/openrauseraccounts';
		add_form_key($form_key);

		switch ($mode)
		{
			case 'add_key':
			{
				// Set up vars for mode.
				$pubkey = $this->request->variable('pubkey', '');
				$submitkey = $this->request->is_set_post('pubkey') && $action == 'submit_key' ? true : false;

				// Submit keys.
				if ($pubkey && $submitkey) // Don't do anything if the textbox is empty.
				{
					// Check if there are any keys for this user. If not, we use a confirm box but can't use check_form_key() then.
					$sql = "SELECT COUNT(*) as count
						FROM $key_table
						WHERE user_id = $this_user_id";
					if (!($result = $this->db->sql_query($sql)))
					{
						trigger_error($this->user->lang('UCP_KEY_COUNT_QUERY_FAILED') . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
					}
					$keys = (int) $this->db->sql_fetchfield('count');
					$this->db->sql_freeresult($result);

					if ($keys)
					{
						// Check form key.
						if (!check_form_key($form_key))
						{
							trigger_error($this->user->lang('FORM_INVALID') . '<br><br>' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
						}
					}

					// Sanity check the public key and calculate the fingerprint
					$fingerprint = '';
					$pubkey_resource = openssl_pkey_get_public($pubkey);
					if ($pubkey_resource)
					{
						$details = openssl_pkey_get_details($pubkey_resource);
						if (array_key_exists('rsa', $details))
							$fingerprint = sha1($details['rsa']['n'] . $details['rsa']['e']);
					}

					if (!$fingerprint)
					{
						trigger_error($this->user->lang('UCP_KEY_INVALID') . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
					}

					// Reject key duplicates.
					$sql = 'SELECT COUNT(*) as count
						FROM ' . $key_table . '
						WHERE fingerprint = "' . $this->db->sql_escape($fingerprint) . '"
						OR public_key = "' . $this->db->sql_escape($pubkey) . '"';
					if (!($result = $this->db->sql_query($sql)))
					{
						trigger_error($this->user->lang('UCP_DUPLICATE_KEY_QUERY_FAILED') . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
					}
					$duplicates = (int) $this->db->sql_fetchfield('count');
					$this->db->sql_freeresult($result);
					if ($duplicates)
					{
						$sql = 'SELECT revoked AS keyrevoked
							FROM ' . $key_table . '
							WHERE fingerprint = "' . $this->db->sql_escape($fingerprint) . '"';
						if (!($result = $this->db->sql_query($sql)))
						{
							trigger_error($this->user->lang('UCP_CHECK_REVOKE_QUERY_FAILED') . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
						}
						$revoked = (bool) $this->db->sql_fetchfield('keyrevoked');

						if ($revoked)
						{
							$message = $this->user->lang('UCP_DUPLICATE_KEY_REVOKED') . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>');
						}
						else
						{
							$message = $this->user->lang('UCP_DUPLICATE_KEY') . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>');
						}
						trigger_error($message);
					}

					// Click-through agreement when adding the first key.
					if (!$keys)
					{
						if (!confirm_box(true))
						{
							confirm_box(false, $this->user->lang['UCP_AGREEMENT'], build_hidden_fields(array(
								'i' => $id,
								'mode' => $mode,
								'pubkey' => $pubkey,
								'action' => $this->request->variable('action', array('' => ''))
							)));
						}
						else
						{
							// Add key data.
							$data = array(
								'user_id' => $this_user_id,
								'public_key' => $pubkey,
								'fingerprint' => $fingerprint,
								'registered' => time(),
							);
							$sql = 'INSERT INTO ' . $key_table . $this->db->sql_build_array('INSERT', $data);
							$this->db->sql_query($sql);

							meta_refresh(3, $this->u_action);
							$message = $this->user->lang('UCP_INPUT_SAVED') . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>');
							trigger_error($message);
						}
					}
					else
					{
						// Add key data.
						$data = array(
							'user_id' => $this_user_id,
							'public_key' => $pubkey,
							'fingerprint' => $fingerprint,
							'registered' => time(),
						);
						$sql = 'INSERT INTO ' . $key_table . $this->db->sql_build_array('INSERT', $data);
						$this->db->sql_query($sql);

						meta_refresh(3, $this->u_action);
						trigger_error($this->user->lang('UCP_INPUT_SAVED') . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
					}
				}

				// Assign template vars for mode.
				$this->template->assign_vars(array(
					'S_UCP_MODE_ADD_KEY' => true
				));

				break;
			}

			case 'manage_keys':
			{
				// Set up vars for mode.
				$start = $this->request->variable('start', 0); // Used for pagination.
				$markedkeys = $this->request->variable('markedkeys', array(0)); // items ids.
				$revokemark = $this->request->is_set_post('markedkeys') && $action == 'rev_marked' ? true : false;
				$revokeall = $action == 'rev_all' ? true : false;

				// Revoke keys if requested.
				if ($revokemark || $revokeall)
				{
					// No need to check the form key when using confirm_box().
					if (!confirm_box(true))
					{
						// Prepare the revoke action before it is confirmed.
						confirm_box(false, $this->user->lang['CONFIRM_OPERATION'], build_hidden_fields(array(
							'start' => $start,
							'markedkeys' => $markedkeys,
							'i' => $id,
							'mode' => $mode,
							'action' => $this->request->variable('action', array('' => ''))
							))
						);
					}
					else
					{
						// Store the marked item ids and prepare the sql clause.
						if ($revokemark && $markedkeys)
						{
							$sql_in = array();
							foreach ($markedkeys as $marked)
							{
								$sql_in[] = $marked;
							}
							$marked_keys_sql = ' AND ' . $this->db->sql_in_set('item_id', $sql_in);
							unset($sql_in);
						}

						// Update revoke status for either all user keys or only for marked keys.
						if ($marked_keys_sql || $revokeall)
						{
							$sql = 'UPDATE ' . $key_table . '
								SET revoked = TRUE
								WHERE user_id = ' . $this_user_id . "
								$marked_keys_sql";
							if (!($result = $this->db->sql_query($sql)))
							{
								trigger_error($this->user->lang('UCP_KEY_REVOKE_QUERY_FAILED') . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
							}
						}
					}
				}

				// Count items for pagination.
				$sql = "SELECT COUNT(*) as keycount
					FROM $key_table
					WHERE user_id = $this_user_id
					AND revoked = FALSE";
				if (!($result = $this->db->sql_query($sql)))
				{
					trigger_error($this->user->lang('UCP_KEY_COUNT_QUERY_FAILED') . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
				}
				$keycount = (int)$this->db->sql_fetchfield('keycount');
				$this->db->sql_freeresult($result);

				// Set up pagination.
				$base_url = $this->u_action;
				$this->pagination->generate_template_pagination($base_url, 'pagination', 'start', $keycount, $this->config['topics_per_page'], $start);

				// Retrieve key data.
				$sql_array = array(
					'user_id' => $this_user_id,
					'revoked' => 'FALSE',
				);
				$sql = 'SELECT item_id, user_id, fingerprint, registered, last_accessed, revoked
					FROM ' . $key_table . '
					WHERE ' . $this->db->sql_build_array('SELECT', $sql_array) . '
					ORDER BY registered DESC';
				if (!($result = $this->db->sql_query_limit($sql, $this->config['topics_per_page'], $start)))
				{
					trigger_error($this->user->lang('UCP_KEY_DATA_QUERY_FAILED') . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
				}

				// Loop over retrieved key data.
				while ($row = $this->db->sql_fetchrow($result))
				{
					$keyrow = array(
						'FINGERPRINT' => $row['fingerprint'],
						'REGISTERED' => $this->user->format_date($row['registered']),
						'LAST_ACCESSED' => $row['last_accessed'] == 0 ? $this->user->lang('UCP_KEY_NOT_ACCESSED') : $this->user->format_date($row['last_accessed']),
						'ID' => $row['item_id']
					);
					$this->template->assign_block_vars('keyrow', $keyrow);
				}

				// Assign template vars for mode.
				$this->template->assign_vars(array(
					'S_UCP_MODE_MANAGE_KEYS' => true,
					'TOTAL' => $this->user->lang('UCP_TOTAL_KEYS', (int) $keycount),
					'S_KEYS' => ($keycount > 0)
				));

				break;
			}

			case 'select_badges':
			{
				// Set up vars for mode.
				$markedbadges = $this->request->variable('marked_badges', array(0)); // badge_ids
				$submitmarked = $this->request->is_set_post('marked_badges') || empty($markedbadges) && $action == 'submit_marked' ? true : false;

				if ($submitmarked)
				{
					// Check form key.
					if (!check_form_key($form_key))
					{
						trigger_error($this->language->lang('FORM_INVALID') . '<br><br>' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
					}

					$delete_sql = empty($markedbadges) ? '' : ' AND ' . $this->db->sql_in_set('badge_id', $markedbadges, true); // true for NOT IN, false (default) for IN

					// Delete all user badges that are NOT IN the array of marked badges or all if none is selected.
					$sql = 'DELETE FROM ' . $user_badge_table . '
						WHERE user_id = ' . $this_user_id . "
						$delete_sql";
					$this->db->sql_query($sql);

					foreach ($markedbadges as $markedbadge)
					{
						// Reject duplicates.
						$sql = "SELECT COUNT(*) as duplcount
							FROM $user_badge_table
							WHERE badge_id = $markedbadge
							AND user_id = $this_user_id";
						if (!($result = $this->db->sql_query($sql)))
						{
							trigger_error($this->user->lang('UCP_DUPLICATE_USER_BADGE_QUERY_FAILED') . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
						}
						$duplicates = (int) $this->db->sql_fetchfield('duplcount');
						$this->db->sql_freeresult($result);
						if (!$duplicates)
						{
							// Add a new user badge.
							$data = array(
								'user_id' => $this_user_id,
								'badge_id' => $markedbadge,
								'badge_order' => null // Order value will be validated after loop.
							);
							$sql = 'INSERT INTO ' . $user_badge_table . $this->db->sql_build_array('INSERT', $data);
							$this->db->sql_query($sql);
						}
					}
					if (!$this->core->validate_badge_order($this_user_id))
					{
						trigger_error($this->user->lang('UCP_BADGE_ORDER_QUERY_FAILED') . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
					}
					unset($markedbadges);
					meta_refresh(5, $this->u_action);
					trigger_error($this->user->lang('UCP_SELECTED_BADGES_SAVED') . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
				}

				// Retrieve badge_ids of existing user badges.
				$sql = "SELECT badge_id
					FROM $user_badge_table
					WHERE user_id = $this_user_id";
				if (!($result = $this->db->sql_query($sql)))
				{
					trigger_error($this->user->lang('UCP_SELECTED_BADGES_QUERY_FAILED') . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
				}

				// Loop over existing user badges and store ids in an array.
				$selected_badges = array();
				while ($row = $this->db->sql_fetchrow($result))
				{
					$selected_badges[] = $row;
				}
				$this->db->sql_freeresult($result);

				// Count all available badges. TODO: Count from both tables in one query.
				$sql = "SELECT COUNT(*) as defaultcount
					FROM $badge_table
					WHERE badge_default = TRUE";
				if (!($result = $this->db->sql_query($sql)))
				{
					trigger_error($this->user->lang('UCP_AVAIL_BADGE_COUNT_QUERY_FAILED') . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
				}
				$default_badge_count = (int) $this->db->sql_fetchfield('defaultcount');
				$this->db->sql_freeresult($result);

				$sql = "SELECT COUNT(*) AS availcount
					FROM $badge_avail_table
					WHERE user_id = $this_user_id";
				if (!($result = $this->db->sql_query($sql)))
				{
					trigger_error($this->user->lang('UCP_AVAIL_BADGE_COUNT_QUERY_FAILED') . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
				}
				$avail_badge_count = (int) $this->db->sql_fetchfield('availcount');
				$this->db->sql_freeresult($result);

				$total_avail_count = $default_badge_count + $avail_badge_count;

				// Retrieve available badges
				if ($total_avail_count)
				{
					$sql = "SELECT b.badge_id, b.badge_label, b.badge_icon_24, bt.badge_type_name
						FROM $badge_table AS b, $badge_type_table AS bt
						WHERE b.badge_default = TRUE
						AND b.badge_type_id = bt.badge_type_id
						UNION
						SELECT b.badge_id, b.badge_label, b.badge_icon_24, bt.badge_type_name
						FROM $badge_table AS b, $badge_type_table AS bt, $badge_avail_table AS ba
						WHERE (b.badge_id = ba.badge_id AND ba.user_id = $this_user_id)
						AND b.badge_type_id = bt.badge_type_id
						ORDER BY badge_type_name";
					if (!($result = $this->db->sql_query($sql)))
					{
						trigger_error($this->user->lang('UCP_AVAIL_BADGES_QUERY_FAILED') . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
					}
					// Prepare an array to group badges by types.
					$typelist = array();
					// Loop over available badges and highlight those that are selected by the user.
					while ($row = $this->db->sql_fetchrow($result))
					{
						$s_badgerow = array(
							'S_SELECTED' => $this->core->in_2d_array($row['badge_id'], $selected_badges),
							'BADGE_ICON_URL' => $row['badge_icon_24'],
							'BADGE_LABEL' => $row['badge_label'],
							'ID' => $row['badge_id'],
						);
						// Check if the type name is in the typelist, if not, add it to the list and to the blockvars for the current row.
						if (!in_array($row['badge_type_name'], $typelist))
						{
							$typelist[] = $row['badge_type_name'];
							$s_badgerow['BADGE_TYPE'] = $row['badge_type_name'];
						}
						$this->template->assign_block_vars('s_badgerow', $s_badgerow);
					}
					$this->db->sql_freeresult($result);
				}

				// Assign template vars for mode
				$this->template->assign_vars(array(
					'S_UCP_MODE_SELECT_BADGES' => true,
					'S_AVAIL_BADGES' => ($total_avail_count > 0)
				));

				break;
			}

			case 'order_badges':
			{
				// Set up vars for mode
				$action = $this->request->variable('action', ''); // Reordering does not work without requesting the action var here explictly as string.
				$item_id = $this->request->variable('id', 0); // 'item_id' of selected element when reordering.
				$moveup = $action == 'move_up' ? true : false;
				$movedown = $action == 'move_down' ? true : false;

				if ($moveup || $movedown)
				{
					if (!check_link_hash($this->request->variable('hash', ''), 'useraccounts_module'))
					{
						trigger_error($this->language->lang('FORM_INVALID') . '<br><br>' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
					}

					// Get current item order value
					$sql = "SELECT badge_order as current_order
						FROM $user_badge_table
						WHERE item_id = $item_id"; // No need to check for user_id, item_id is the table's primary key.
					$result = $this->db->sql_query($sql);
					$current_order = (int) $this->db->sql_fetchfield('current_order');
					$this->db->sql_freeresult($result);

					if (!($current_order == 0 && $action == 'move_up')) // Original code uses a switch for actions and breaks out when both conditions are true.
					{
						// Either increment order value on move_down or decrement order value on move_up.
						$switch_order_val = $action == 'move_down' ? $current_order + 1 : $current_order - 1;

						// Updating the next/previous item's order value.
						$sql = "UPDATE $user_badge_table
							SET badge_order = $current_order
							WHERE badge_order = $switch_order_val
							AND item_id <> $item_id
							AND user_id = $this_user_id";
						$this->db->sql_query($sql);
						$move_executed = (bool) $this->db->sql_affectedrows();

						// Check if prior update was successful, then update the order value of the item we wanted to move.
						if ($move_executed)
						{
							$sql = "UPDATE $user_badge_table
								SET badge_order = $switch_order_val
								WHERE badge_order = $current_order
								AND item_id = $item_id";
							$this->db->sql_query($sql);
						}

						// Response for the ajax callback that reorders items on the page.
						if ($this->request->is_ajax())
						{
							$json_response = new \phpbb\json_response;
							$json_response->send(array(
								'success' => $move_executed,
							));
						}
					}
				}

				// Count total user badges.
				$sql = "SELECT COUNT(*) AS ubadgecount
					FROM $user_badge_table
					WHERE user_id = $this_user_id";
				if (!($result = $this->db->sql_query($sql)))
				{
					trigger_error($this->user->lang('UCP_UBADGE_COUNT_QUERY_FAILED') . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
				}
				$ubadge_count = (int) $this->db->sql_fetchfield('ubadgecount');
				$this->db->sql_freeresult($result);

				if ($ubadge_count)
				{
					if (!$this->core->validate_badge_order($this_user_id))
					{
						trigger_error($this->user->lang('UCP_BADGE_ORDER_QUERY_FAILED') . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
					}

					// Retrieve user badge data
					$sql = $this->core->get_ubadge_sql_by_id($this_user_id);
					if (!($result = $this->db->sql_query($sql)))
					{
						trigger_error($this->user->lang('UCP_UBADGE_DATA_QUERY_FAILED') . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
					}

					$spacer = false;
					$count = 0; // Counter used for the spacer.

					// Loop over retrieved user badge data
					while ($row = $this->db->sql_fetchrow($result))
					{
						++$count;
						$u_badgerow = array(
							'S_SPACER' => !$spacer && ($count > $this->config['max_profile_badges']) ? true : false, // Check if $spacer is false for each each row.
							'BADGE_ICON_URL' => $row['badge_icon_24'],
							'BADGE_ICON_NAME' => $row['badge_icon_24'],
							'BADGE_LABEL' => $row['badge_label'],
							'U_MOVE_UP' => $this->u_action . '&amp;action=move_up&amp;id=' . $row['item_id'] . '&amp;hash=' . generate_link_hash('useraccounts_module'),
							'U_MOVE_DOWN' => $this->u_action . '&amp;action=move_down&amp;id=' . $row['item_id'] . '&amp;hash=' . generate_link_hash('useraccounts_module')
						);

						if (!$spacer && ($count > $this->config['max_profile_badges']))
						{
							$spacer = true; // Once the conditions for the spacer are met, don't show another one.
						}
						$this->template->assign_block_vars('u_badgerow', $u_badgerow);
					}
					$this->db->sql_freeresult($result);
				}

				// Assign template vars for mode
				$this->template->assign_vars(array(
					'S_UCP_MODE_ORDER_BADGES' => true,
					'S_BADGES' => ($ubadge_count > 0),
					'ICON_MOVE_UP' => '<img src="' . $this->path_helper->get_adm_relative_path() . 'images/icon_up.gif" alt="' . $this->user->lang['MOVE_UP'] . '" title="' . $this->user->lang['MOVE_UP'] . '" />',
					'ICON_MOVE_UP_DISABLED' => '<img src="' . $this->path_helper->get_adm_relative_path() . 'images/icon_up_disabled.gif" alt="' . $this->user->lang['MOVE_UP'] . '" title="' . $this->user->lang['MOVE_UP'] . '" />',
					'ICON_MOVE_DOWN' => '<img src="' . $this->path_helper->get_adm_relative_path() . 'images/icon_down.gif" alt="' . $this->user->lang['MOVE_DOWN'] . '" title="' . $this->user->lang['MOVE_DOWN'] . '" />',
					'ICON_MOVE_DOWN_DISABLED' => '<img src="' . $this->path_helper->get_adm_relative_path() . 'images/icon_down_disabled.gif" alt="' . $this->user->lang['MOVE_DOWN'] . '" title="' . $this->user->lang['MOVE_DOWN'] . '" />',
				));

				break;
        		}
		}
	}
}
