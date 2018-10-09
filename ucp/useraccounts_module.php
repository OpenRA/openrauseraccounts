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
 * UCP accounts module.
 */
class useraccounts_module
{
	public $u_action;

	/**
	 * Constructor
	 *
	 * @return \openra\openrauseraccounts\ucp\useraccounts_module
	 */
	function __construct()
	{
		global $phpbb_container;
		$this->db = $phpbb_container->get('dbal.conn');
		$this->request = $phpbb_container->get('request');
		$this->template = $phpbb_container->get('template');
		$this->user = $phpbb_container->get('user');
		$this->language = $phpbb_container->get('language');
		$this->pagination = $phpbb_container->get('pagination');
		$this->config = $phpbb_container->get('config');
		$this->path_helper = $phpbb_container->get('path_helper');
		$this->helper = $phpbb_container->get('openra.openrauseraccounts.helper');
		$this->key = $phpbb_container->get('openra.openrauseraccounts.key');
		$this->badge_manager = $phpbb_container->get('openra.openrauseraccounts.badge_manager');
		$this->bu_tbl = $phpbb_container->getParameter('tables.openra.bu');
	}

	function main($id, $mode)
	{
		// General request vars.
		$submit = $this->request->variable('submit', false, false, \phpbb\request\request_interface::POST);
		$mark = $this->request->variable('mark', [0], false, \phpbb\request\request_interface::POST);
		$move = $this->request->variable('move', '', false, \phpbb\request\request_interface::GET);
		$url_id = $this->request->variable('id', 0, false, \phpbb\request\request_interface::GET);
		$start = $this->request->variable('start', 0, false, \phpbb\request\request_interface::GET);

		// General references.
		$user_id = (int)$this->user->data['user_id'];
		$this->tpl_name = 'ucp_useraccounts';
		$this->page_title = $this->language->lang('UCP_ACCOUNTS');
		$form_key = 'openra/openrauseraccounts';
		add_form_key($form_key);

		// General template vars.
		$this->template->assign_vars([
			'S_MODE_' . strtoupper($mode) => true,
			'L_MODE_TITLE' => $this->language->lang('UCP_ACCOUNTS_' . strtoupper($mode)),
			'L_MODE_EXPLAIN' => $this->language->lang('UCP_ACCOUNTS_' . strtoupper($mode) . '_EXPLAIN'),
			'U_POST_ACTION' => $this->u_action
		]);

		switch ($mode)
		{
			case 'add_key':
			{
				// Add authentication key.
				$pubkey = $this->request->variable('pubkey', '', false, \phpbb\request\request_interface::POST);

				if ($pubkey && $submit)
				{
					$keycount = $this->key->count_keys(['user_id' => $user_id]);

					if ($keycount)
					{
						$this->check_form($form_key);
					}
					else
					{
						confirm_box(false, $this->language->lang('AGREEMENT'), build_hidden_fields([
							'i' => $id,
							'mode' => $mode,
							'pubkey' => $pubkey,
							'submit' => $submit,
						]));
					}

					if ($keycount || confirm_box(true))
					{
						try
						{
							$mssg = $this->key->add_key($pubkey, $user_id);
						}
						catch (\InvalidArgumentException $e)
						{
							$this->ucp_error($this->language->lang($e->getMessage()), '', E_USER_WARNING);
						}

						meta_refresh(3, $this->u_action);
						$this->ucp_error($this->language->lang($mssg));
					}
				}

				break;
			}

			case 'manage_keys':
			{
				// Revoke keys if requested.
				$revmark = $this->request->variable('revmark', false, false, \phpbb\request\request_interface::POST);
				$revall = $this->request->variable('revall', false, false, \phpbb\request\request_interface::POST);

				if (($mark && $revmark) || $revall)
				{
					if (!confirm_box(true))
					{
						confirm_box(false, $this->language->lang('CONFIRM_OPERATION'), build_hidden_fields([
							'start' => $start,
							'mark' => $mark,
							'i' => $id,
							'mode' => $mode,
							'revmark' => $revmark,
							'revall' => $revall,
						]));
					}
					else
					{
						try
						{
							$mssg = $this->key->revoke_keys($user_id, $revall ? [] : $mark); // Revoke all regardless of marked.
						}
						catch (\InvalidArgumentException $e)
						{
							$this->ucp_error($this->language->lang($e->getMessage()), '', E_USER_WARNING);
						}

						meta_refresh(3, $this->u_action);
						$this->ucp_error($mssg);
					}
				}

				// Manage keys table view.
				$keycount = $this->key->count_keys(['user_id' => $user_id, 'revoked' => false]);
				$this->pagination->generate_template_pagination($this->u_action, 'pagination', 'start', $keycount, $this->config['topics_per_page'], $start);
				$result = $this->key->get_key_data(['user_id' => $user_id, 'revoked' => false], $this->config['topics_per_page'], $start);

				while ($row = $this->db->sql_fetchrow($result))
				{
					$keyrow = [
						'FINGERPRINT' => $row['fingerprint'],
						'REGISTERED' => $this->user->format_date($row['registered']),
						'LAST_ACCESSED' => $row['last_accessed'] == 0 ? $this->language->lang('KEY_NOT_ACCESSED') : $this->user->format_date($row['last_accessed']),
						'ID' => $row['item_id']
					];

					$this->template->assign_block_vars('keyrow', $keyrow);
				}

				$this->db->sql_freeresult();
				$this->template->assign_vars(['TOTAL' => $this->language->lang('TOTAL_KEYS', $keycount), 'S_KEYS' => $keycount > 0]);

				break;
			}

			case 'select_badges':
			{
				// Add and remove user badges.
				if ($submit)
				{
					$this->check_form($form_key);
					$mssg = $this->badge_manager->remove_user_data('badge_user', [$user_id], $mark, true);

					if (!empty($mark))
					{
						$mssg = $this->badge_manager->add_user_data('badge_user', [$user_id], $mark);
					}

					meta_refresh(3, $this->u_action);
					$this->ucp_error($mssg);
				}

				// Select badges table view.
				$result = $this->badge_manager->get_data('badge', ['user_id' => $user_id]);
				$types = [];

				while ($row = $this->db->sql_fetchrow($result))
				{
					$s_badgerow = [
						'S_SELECTED' => $row['badge_selected'],
						'BADGE_ICON_URL' => $row['badge_icon_24'],
						'BADGE_LABEL' => $row['badge_label'],
						'ID' => $row['badge_id']
					];

					if (!in_array($row['badge_type_name'], $types))
					{
						$types[] = $row['badge_type_name'];
						$s_badgerow['BADGE_TYPE'] = $row['badge_type_name'];
					}

					$this->template->assign_block_vars('s_badgerow', $s_badgerow);
				}

				$this->db->sql_freeresult();
				$this->template->assign_vars(['S_AVAIL_BADGES' => count($types) > 0]);

				break;
			}

			case 'order_badges':
			{
				// Move user badge up or down.
				if ($move)
				{
					if (!check_link_hash($this->request->variable('hash', ''), 'useraccounts_module'))
					{
						$this->ucp_error('FORM_INVALID', '', E_USER_WARNING);
					}

					try
					{
						$move_executed = $this->helper->move_item($this->bu_tbl, 'badge_order', $move, $url_id, $user_id);
					}
					catch (\InvalidArgumentException $e)
					{
						$this->ucp_error($this->language->lang($e->getMessage()), '', E_USER_WARNING);
					}

					if ($this->request->is_ajax())
					{
						$json_response = new \phpbb\json_response;
						$json_response->send(['success' => $move_executed]);
					}
				}

				// Order badges table view.
				$this->helper->fix_item_order($this->bu_tbl, 'badge_order', $user_id);
				$result = $this->badge_manager->get_data('badge_user', ['user_id' => $user_id]);
				$spacer = false;
				$ubadgecount = 0;

				while ($row = $this->db->sql_fetchrow($result))
				{
					++$ubadgecount;
					$u_badgerow = [
						'S_SPACER' => !$spacer && ($ubadgecount > $this->config['max_profile_badges']) ? true : false,
						'BADGE_ICON_URL' => $row['badge_icon_24'],
						'BADGE_LABEL' => $row['badge_label'],
						'U_MOVE_UP' => $this->u_action . '&amp;move=move_up&amp;id=' . $row['item_id'] . '&amp;hash=' . generate_link_hash('useraccounts_module'),
						'U_MOVE_DOWN' => $this->u_action . '&amp;move=move_down&amp;id=' . $row['item_id'] . '&amp;hash=' . generate_link_hash('useraccounts_module')
					];

					if (!$spacer && ($ubadgecount > $this->config['max_profile_badges']))
					{
						$spacer = true;
					}

					$this->template->assign_block_vars('u_badgerow', $u_badgerow);
				}

				$this->db->sql_freeresult();
				$this->template->assign_vars([
					'S_BADGES' => $ubadgecount > 0,
					'ICON_MOVE_UP' => '<img src="' . $this->path_helper->get_adm_relative_path() . 'images/icon_up.gif" alt="' . $this->language->lang('MOVE_UP') . '" title="' . $this->language->lang('MOVE_UP') . '" />',
					'ICON_MOVE_UP_DISABLED' => '<img src="' . $this->path_helper->get_adm_relative_path() . 'images/icon_up_disabled.gif" alt="' . $this->language->lang('MOVE_UP') . '" title="' . $this->language->lang('MOVE_UP') . '" />',
					'ICON_MOVE_DOWN' => '<img src="' . $this->path_helper->get_adm_relative_path() . 'images/icon_down.gif" alt="' . $this->language->lang('MOVE_DOWN') . '" title="' . $this->language->lang('MOVE_DOWN') . '" />',
					'ICON_MOVE_DOWN_DISABLED' => '<img src="' . $this->path_helper->get_adm_relative_path() . 'images/icon_down_disabled.gif" alt="' . $this->language->lang('MOVE_DOWN') . '" title="' . $this->language->lang('MOVE_DOWN') . '" />'
				]);

				break;
			}
		}
	}

	public function check_form($form_key, $append = '')
	{
		if (!check_form_key($form_key))
		{
			$this->ucp_error('FORM_INVALID', $append, E_USER_WARNING);
		}
	}

	public function ucp_error($error, $append = '', $warning = E_USER_NOTICE)
	{
		trigger_error($this->language->lang($error) . '<br /><br />' . $this->language->lang('RETURN_UCP', '<a href="' . $this->u_action . $append . '">', '</a>'), $warning);
	}
}
