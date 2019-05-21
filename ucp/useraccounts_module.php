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
		// Set up general vars.
		$key_table = $this->table_prefix . 'openra_keys';
		$badge_table = $this->table_prefix . 'openra_badges';
		$user_badge_table = $this->table_prefix . 'openra_user_badges';
		$badge_type_table = $this->table_prefix . 'openra_badge_types';
		$badge_avail_table = $this->table_prefix . 'openra_badge_availability';
		$this_user_id = (int)$this->user->data['user_id']; // String if not cast.

		// Filter the name attribute from template markup:
		// Example: <input name="action[foo]" value="Foobar">.
		// - $action = array(1) {["foo"]=>string(6) "Foobar"}
		// - Return only the key for the current key and value pair as string.
		$action = $this->request->variable('action', array('' => ''));
		list($action, ) = each($action);

		// Generalized actions:
		$submit = $action == 'submit';

		// Assign general template vars.
		$this->template->assign_vars(array(
			'L_UCP_MODE_TITLE' => $this->user->lang('UCP_ACCOUNT_' . strtoupper($mode)),
			'L_UCP_MODE_EXPLAIN' => $this->user->lang('UCP_ACCOUNT_' . strtoupper($mode) . '_EXPLAIN'),
			'U_POST_ACTION' => $this->u_action
		));

		// General references.
		$this->tpl_name = 'ucp_useraccounts';
		$this->page_title = $this->user->lang('UCP_ACCOUNT');
		$form_key = 'openra/openrauseraccounts';
		add_form_key($form_key);

		switch ($mode)
		{
			case 'add_key':
			{
				// Set up vars for mode.
				$pubkey = $this->request->variable('pubkey', '');
				$fingerprint = '';

				if ($this->request->is_set_post('pubkey') && $submit)
				{
					// Check if there are any keys for this user.
					$sql = 'SELECT COUNT(*) AS count
						FROM ' . $key_table . '
						WHERE user_id = ' . (int)$this_user_id;
					$result = $this->db->sql_query($sql);
					$keys = (int)$this->db->sql_fetchfield('count');
					$this->db->sql_freeresult($result);

					// On submitting the first key use a confirm box, otherwise check the form key.
					if ($keys)
					{
						if (!check_form_key($form_key))
						{
							trigger_error($this->user->lang('FORM_INVALID') . '<br><br>' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
						}
					}
					else
					{
						confirm_box(false, $this->user->lang['UCP_AGREEMENT'], build_hidden_fields(array(
							'i' => $id,
							'mode' => $mode,
							'pubkey' => $pubkey,
							'action' => $this->request->variable('action', array('' => ''))
						)));
					}

					// Sanity check the public key and calculate the fingerprint.
					$pubkey_resource = openssl_pkey_get_public($pubkey);
					if ($pubkey_resource)
					{
						$details = openssl_pkey_get_details($pubkey_resource);
						if (array_key_exists('rsa', $details))
						{
							$fingerprint = sha1($details['rsa']['n'] . $details['rsa']['e']);
						}
					}

					// Exit when given an invalid public key.
					if (!$fingerprint)
					{
						trigger_error($this->user->lang('UCP_KEY_INVALID') . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
					}

					// Reject key duplicates.
					$sql = 'SELECT COUNT(*) AS count
						FROM ' . $key_table . '
						WHERE fingerprint = "' . $this->db->sql_escape($fingerprint) . '"
						OR public_key = "' . $this->db->sql_escape($pubkey) . '"';
					$result = $this->db->sql_query($sql);
					$duplicates = (int)$this->db->sql_fetchfield('count');
					$this->db->sql_freeresult($result);

					if ($duplicates)
					{
						$sql = 'SELECT revoked AS keyrevoked
							FROM ' . $key_table . '
							WHERE fingerprint = "' . $this->db->sql_escape($fingerprint) . '"';
						$result = $this->db->sql_query($sql);
						$revoked = (bool)$this->db->sql_fetchfield('keyrevoked');
						$this->db->sql_freeresult($result);

						// Show a different error message when the duplicate key is revoked and not visible for the user.
						$message = $revoked ? 'UCP_DUPLICATE_KEY_REVOKED' : 'UCP_DUPLICATE_KEY';
						trigger_error($this->user->lang($message) . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
					}

					if ($keys || confirm_box(true))
					{
						// Prepare an array for inserting key data.
						$data = array(
							'user_id' => (int)$this_user_id,
							'public_key' => $pubkey,
							'fingerprint' => $fingerprint,
							'registered' => time()
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
				$start = $this->request->variable('start', 0); // Used for pagination, automatically cast to an integer.
				$markedkeys = $this->request->variable('markedkeys', array(0)); // Items IDs, automatically cast to an array of integers.
				$revokemark = $action == 'rev_marked';
				$revokeall = $action == 'rev_all';

				// Revoke keys if requested.
				if (($this->request->is_set_post('markedkeys') && $revokemark) || $revokeall)
				{
					if (!confirm_box(true))
					{
						confirm_box(false, $this->user->lang['CONFIRM_OPERATION'], build_hidden_fields(array(
							'start' => $start,
							'markedkeys' => $markedkeys,
							'i' => $id,
							'mode' => $mode,
							'action' => $this->request->variable('action', array('' => ''))
						)));
					}
					else
					{
						// Prepare optional SQL parameters.
						$marked_keys_sql = '';

						if ($revokemark && $markedkeys)
						{
							foreach ($markedkeys as $markedkey)
							{
								$sql_in[] = (int)$markedkey;
							}
							$marked_keys_sql = ' AND ' . $this->db->sql_in_set('item_id', $sql_in);
							unset($sql_in);
						}

						// Update revoke status for either all user keys or only for marked keys.
						if ($marked_keys_sql || $revokeall)
						{
							$sql = 'UPDATE ' . $key_table . '
								SET revoked = TRUE
								WHERE user_id = ' . (int)$this_user_id . "
								$marked_keys_sql";
							$this->db->sql_query($sql);
						}
					}
				}

				// Pagination for the manage keys table view.
				$sql = 'SELECT COUNT(*) AS keycount
					FROM ' . $key_table . '
					WHERE revoked = FALSE
					AND user_id = ' . (int)$this_user_id;

				$result = $this->db->sql_query($sql);
				$keycount = (int)$this->db->sql_fetchfield('keycount');
				$this->db->sql_freeresult($result);

				$base_url = $this->u_action;
				$this->pagination->generate_template_pagination($base_url, 'pagination', 'start', $keycount, $this->config['topics_per_page'], $start);

				// Query key data.
				$sql = 'SELECT item_id, user_id, fingerprint, registered, last_accessed, revoked
					FROM ' . $key_table . '
					WHERE user_id = ' . (int)$this_user_id . '
					AND revoked = FALSE
					ORDER BY registered DESC';
				$result = $this->db->sql_query_limit($sql, $this->config['topics_per_page'], $start);

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
				$this->db->sql_freeresult($result);

				// Assign template vars for mode.
				$this->template->assign_vars(array(
					'S_UCP_MODE_MANAGE_KEYS' => true,
					'TOTAL' => $this->user->lang('UCP_TOTAL_KEYS', $keycount),
					'S_KEYS' => $keycount > 0
				));

				break;
			}

			case 'select_badges':
			{
				// Set up vars for mode.
				$markedbadges = $this->request->variable('markedbadges', array(0)); // Badge IDs, automatically cast to an array of integers.

				if (($this->request->is_set_post('markedbadges') || empty($markedbadges)) && $submit)
				{
					if (!check_form_key($form_key))
					{
						trigger_error($this->language->lang('FORM_INVALID') . '<br><br>' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
					}

					// Prepare optional SQL parameters.
					$delete_sql = empty($markedbadges) ? '' : ' AND ' . $this->db->sql_in_set('badge_id', $markedbadges, true); // True for NOT IN, false (default) for IN.

					// Either delete all user badges or only those that are NOT IN the array of marked badges.
					$sql = 'DELETE FROM ' . $user_badge_table . '
						WHERE user_id = ' . (int)$this_user_id . "
						$delete_sql";
					$this->db->sql_query($sql);

					if ($markedbadges)
					{
						// Query valid badges.
						$sql_array = array(
							'SELECT' => 'b.badge_id',

							'FROM' => array(
								$badge_table => 'b'
							),

							'LEFT_JOIN' => array(
								array(
									'FROM' => array($user_badge_table => 'bu'),
									'ON' => 'b.badge_id = bu.badge_id AND bu.user_id = ' . (int)$this_user_id
								),
								array(
									'FROM' => array($badge_avail_table => 'ba'),
									'ON' => 'b.badge_id = ba.badge_id AND ba.user_id = ' . (int)$this_user_id
								)
							),

							// Badges are valid if they are not already selected (bu.badge_id is null) and are either:
							//  - a default badge (b.badge_default is true)
							//  - have been awarded to the user (ba.user_id is not null)
							'WHERE' => 'bu.badge_id IS NULL AND (b.badge_default = TRUE OR ba.user_id = ' . (int)$this_user_id . ')'
						);

						$sql = $this->db->sql_build_query('SELECT', $sql_array);
						$result = $this->db->sql_query($sql);

						// Prepare an array to store valid badges.
						$valid_badges = array();

						while ($row = $this->db->sql_fetchrow($result))
						{
							$valid_badges[] = (int)$row['badge_id'];
						}
						$this->db->sql_freeresult($result);

						// Prepare an array for inserting badge data.
						$data = array();

						foreach ($markedbadges as $markedbadge)
						{
							// Validate requested badges.
							if (in_array($markedbadge, $valid_badges))
							{
								$data[] = array(
									'user_id' => (int)$this_user_id,
									'badge_id' => (int)$markedbadge,
									'badge_order' => null // Order value will be validated after loop.
								);
							}
						}

						$this->db->sql_multi_insert($user_badge_table, $data);
						$this->core->validate_badge_order($this_user_id);
						$message = 'UCP_SELECTED_BADGES_SAVED';
					}
					else
					{
						$message = 'UCP_ALL_USER_BADGES_REMOVED';
					}

					meta_refresh(5, $this->u_action);
					trigger_error($this->user->lang($message) . '<br /><br />' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
				}

				// Query badges for the select badges table view.
				$sql_array = array(
					'SELECT' => 'b.badge_id, b.badge_label, b.badge_icon_24, bt.badge_type_name, bu.badge_id IS NOT NULL as badge_selected',

					'FROM' => array(
						$badge_table => 'b',
						$badge_type_table  => 'bt'
					),

					'LEFT_JOIN' => array(
						array(
							'FROM' => array($user_badge_table => 'bu'),
							'ON' => 'b.badge_id = bu.badge_id AND bu.user_id = ' . (int)$this_user_id
						),
						array(
							'FROM' => array($badge_avail_table => 'ba'),
							'ON' => 'b.badge_id = ba.badge_id AND ba.user_id = ' . (int)$this_user_id
						)
					),

					// Badges are available if either:
					//  - a default badge (b.badge_default)
					//  - have been awarded to the user (ba.user_id)
					'WHERE'	=> 'b.badge_type_id = bt.badge_type_id AND (b.badge_default = TRUE OR ba.user_id = ' . (int)$this_user_id . ')',

					'ORDER_BY' => 'bt.badge_type_name'
				);

				$sql = $this->db->sql_build_query('SELECT', $sql_array);
				$result = $this->db->sql_query($sql);

				// Prepare an array to group badges by types.
				$typelist = array();

				while ($row = $this->db->sql_fetchrow($result))
				{
					$s_badgerow = array(
						'S_SELECTED' => $row['badge_selected'],
						'BADGE_ICON_URL' => $row['badge_icon_24'],
						'BADGE_LABEL' => $row['badge_label'],
						'ID' => $row['badge_id']
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

				// Assign template vars for mode.
				$this->template->assign_vars(array(
					'S_UCP_MODE_SELECT_BADGES' => true,
					'S_AVAIL_BADGES' => sizeof($typelist) > 0
				));

				break;
			}

			case 'order_badges':
			{
				// Set up vars for mode.
				$action = $this->request->variable('action', ''); // Needs to be requested as string to match URL paramter.
				$item_id = $this->request->variable('id', 0); // Item ID of selected element when reordering, automatically cast to an integer. Retrieved from URL.
				$moveup = $action == 'move_up';
				$movedown = $action == 'move_down';

				if ($moveup || $movedown)
				{
					if (!check_link_hash($this->request->variable('hash', ''), 'useraccounts_module'))
					{
						trigger_error($this->language->lang('FORM_INVALID') . '<br><br>' . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
					}

					// Get current item order value.
					$sql = 'SELECT badge_order AS current_order
						FROM ' . $user_badge_table . '
						WHERE item_id = ' . (int)$item_id;
					$result = $this->db->sql_query($sql);
					$current_order = (int)$this->db->sql_fetchfield('current_order');
					$this->db->sql_freeresult($result);

					if (!($current_order == 0 && $action == 'move_up'))
					{
						// Either increment order value on move_down or decrement order value on move_up.
						$switch_order_val = $action == 'move_down' ? $current_order + 1 : $current_order - 1;

						// Updating the next/previous item's order value.
						$sql = 'UPDATE ' . $user_badge_table . '
							SET badge_order = ' . $current_order . '
							WHERE badge_order = ' . $switch_order_val . '
							AND item_id <> ' . (int)$item_id . '
							AND user_id = ' . (int)$this_user_id;
						$this->db->sql_query($sql);
						$move_executed = (bool)$this->db->sql_affectedrows();

						// Check if the prior update was successful, then update the order value of the item we wanted to move.
						if ($move_executed)
						{
							$sql = 'UPDATE ' . $user_badge_table . '
								SET badge_order = ' . $switch_order_val . '
								WHERE badge_order = ' . $current_order . '
								AND item_id = ' . (int)$item_id;
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

				// Query badges for the order badges table view.
				$this->core->validate_badge_order($this_user_id);
				$sql = $this->core->get_ubadge_sql_by_id($this_user_id);
				$result = $this->db->sql_query($sql);
				$spacer = false;
				$count = 0;

				while ($row = $this->db->sql_fetchrow($result))
				{
					++$count;
					$u_badgerow = array(
						'S_SPACER' => !$spacer && ($count > $this->config['max_profile_badges']) ? true : false,
						'BADGE_ICON_URL' => $row['badge_icon_24'],
						'BADGE_LABEL' => $row['badge_label'],
						'U_MOVE_UP' => $this->u_action . '&amp;action=move_up&amp;id=' . $row['item_id'] . '&amp;hash=' . generate_link_hash('useraccounts_module'),
						'U_MOVE_DOWN' => $this->u_action . '&amp;action=move_down&amp;id=' . $row['item_id'] . '&amp;hash=' . generate_link_hash('useraccounts_module')
					);

					if (!$spacer && ($count > $this->config['max_profile_badges']))
					{
						$spacer = true;
					}

					$this->template->assign_block_vars('u_badgerow', $u_badgerow);
				}
				$this->db->sql_freeresult($result);

				// Assign template vars for mode.
				$this->template->assign_vars(array(
					'S_UCP_MODE_ORDER_BADGES' => true,
					'S_BADGES' => $count > 0,
					'ICON_MOVE_UP' => '<img src="' . $this->path_helper->get_adm_relative_path() . 'images/icon_up.gif" alt="' . $this->user->lang['MOVE_UP'] . '" title="' . $this->user->lang['MOVE_UP'] . '" />',
					'ICON_MOVE_UP_DISABLED' => '<img src="' . $this->path_helper->get_adm_relative_path() . 'images/icon_up_disabled.gif" alt="' . $this->user->lang['MOVE_UP'] . '" title="' . $this->user->lang['MOVE_UP'] . '" />',
					'ICON_MOVE_DOWN' => '<img src="' . $this->path_helper->get_adm_relative_path() . 'images/icon_down.gif" alt="' . $this->user->lang['MOVE_DOWN'] . '" title="' . $this->user->lang['MOVE_DOWN'] . '" />',
					'ICON_MOVE_DOWN_DISABLED' => '<img src="' . $this->path_helper->get_adm_relative_path() . 'images/icon_down_disabled.gif" alt="' . $this->user->lang['MOVE_DOWN'] . '" title="' . $this->user->lang['MOVE_DOWN'] . '" />'
				));

				break;
        		}
		}
	}
}
