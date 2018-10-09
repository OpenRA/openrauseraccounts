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
 * ACP badges module.
 */
class badges_module
{
	public $u_action;

	/**
	 * Constructor
	 *
	 * @return \openra\openrauseraccounts\acp\badges_module
	 */
	function __construct()
	{
		global $phpbb_container;
		$this->db = $phpbb_container->get('dbal.conn');
		$this->user = $phpbb_container->get('user');
		$this->language = $phpbb_container->get('language');
		$this->template = $phpbb_container->get('template');
		$this->request = $phpbb_container->get('request');
		$this->config = $phpbb_container->get('config');
		$this->path_helper = $phpbb_container->get('path_helper');
		$this->helper = $phpbb_container->get('openra.openrauseraccounts.helper');
		$this->badge_manager = $phpbb_container->get('openra.openrauseraccounts.badge_manager');
	}

	public function main($id, $mode)
	{
		// General request vars.
		$submit = $this->request->variable('submit', false, false, \phpbb\request\request_interface::POST);
		$mark = $this->request->variable('mark', [0], false, \phpbb\request\request_interface::POST);
		$delmark = $this->request->variable('delmark', false, false, \phpbb\request\request_interface::POST);
		$delall = $this->request->variable('delall', false, false, \phpbb\request\request_interface::POST);
		$url_id = $this->request->variable('id', 0, false, \phpbb\request\request_interface::GET);
		$action = $this->request->variable('action', '', false, \phpbb\request\request_interface::GET);

		// General references.
		$user_id = (int)$this->user->data['user_id'];
		$phpbb_root_path = $this->path_helper->get_phpbb_root_path();
		$phpEx = $this->path_helper->get_php_ext();
		$formid = strtolower(str_replace(' ', '', $this->language->lang(strtoupper($mode))));
		$this->tpl_name = 'acp_badges';
		$this->page_title = 'ACP_BADGES';
		$form_key = 'openra/openrauseraccounts';
		add_form_key($form_key);

		// General template vars.
		$this->template->assign_vars([
			'S_MODE_' . strtoupper($mode) => true,
			'L_MODE_TITLE' => $this->language->lang('ACP_BADGES_' . strtoupper($mode)),
			'L_MODE_EXPLAIN' => $this->language->lang('ACP_BADGES_' . strtoupper($mode) . '_EXPLAIN'),
			'U_ACTION' => $this->u_action
		]);

		switch ($mode)
		{
			case 'settings':
			{
				// Badge settings.
				$maxbadges = $this->request->variable('maxbadges', 0, false, \phpbb\request\request_interface::POST);

				if ($maxbadges && $submit)
				{
					$this->check_form($form_key);
					$this->config->set('max_profile_badges', $maxbadges);
					$this->acp_error('CONFIG_UPDATED');
				}

				$this->template->assign_vars(['SET_MAX_BADGES' => $this->config['max_profile_badges']]);

				break;
			}

			case 'types':
			{
				switch ($action)
				{
					case 'add':
					case 'edit':
					{
						// Save added or edited badge type.
						$typename = $this->request->variable('typename', '', false, \phpbb\request\request_interface::POST);

						if ($typename && $submit)
						{
							$this->check_form($form_key, '&amp;action=' . $action . ($url_id ? '&amp;id=' . $url_id : ''));

							if ($this->badge_manager->get_count('badge_type', ['badge_type_name' => $typename ]))
							{
								$this->acp_error('DUPLICATE_TYPE', '&amp;action=' . $action . ($url_id ? '&amp;id=' . $url_id : ''), E_USER_WARNING);
							}

							$data = ['badge_type_name' => $typename];
							$mssg = $this->badge_manager->make('badge_type', $data, $url_id);
							$this->acp_error($mssg);
						}

						// Form for adding or editing badge type.
						$result = $this->badge_manager->get_data('badge_type', ['badge_type_id' => $url_id]);
						$type = [];

						while ($row = $this->db->sql_fetchrow($result))
						{
							if ($action == 'edit' && $url_id == $row['badge_type_id'])
							{
								$type = $row;
							}
						}

						$this->db->sql_freeresult();
						$this->template->assign_vars([
							'S_' . strtoupper($action) => true,
							'U_BACK' => $this->u_action,
							'U_ACTION' => $this->u_action . '&amp;action=' . $action . ($url_id ? '&amp;id=' . $url_id : ''),
							'TYPE_NAME' => isset($type['badge_type_name']) ? $type['badge_type_name'] : '',
						]);

						break;
					}

					case 'delete':
					{
						// Delete badge type.
						if ($this->badge_manager->get_count('badge', ['badge_type_id' => $url_id]))
						{
							$this->acp_error('BADGE_TYPE_USED', false, E_USER_WARNING);
						}

						if (!confirm_box(true))
						{
							confirm_box(false, $this->language->lang('CONFIRM_OPERATION'), build_hidden_fields([
								'i' => $id,
								'mode' => $mode,
								'badge_type_id' => $url_id,
								'action' => $action
							]));
						}
						else
						{
							$mssg = $this->badge_manager->delete('badge_type', $url_id);

							if ($this->request->is_ajax())
							{
								$json_response = new \phpbb\json_response;
								$json_response->send([
									'MESSAGE_TITLE' => $this->language->lang('INFORMATION'),
									'MESSAGE_TEXT' => $this->language->lang($mssg),
									'REFRESH_DATA' => ['time' => 3]
								]);
							}
						}

						break;
					}
				}

				// Badge types table view.
				$result = $this->badge_manager->get_data('badge_type');
				$typecount = 0;

				while ($row = $this->db->sql_fetchrow($result))
				{
					++$typecount;
					$usedcount = $this->badge_manager->get_count('badge', ['badge_type_id' => (int)$row['badge_type_id']]);
					$typerow = [
						'TYPE_NAME' => $row['badge_type_name'],
						'TYPE_USED_BY' => $usedcount,
						'U_EDIT' => $this->u_action . '&amp;action=edit&amp;id=' . $row['badge_type_id'],
						'U_DELETE' => $this->u_action . '&amp;action=delete&amp;id=' . $row['badge_type_id']
					];

					$this->template->assign_block_vars('typerow', $typerow);
				}

				$this->db->sql_freeresult();
				$this->template->assign_vars(['U_ADD' => $this->u_action . '&amp;action=add', 'S_TYPES' => $typecount > 0]);

				break;
			}

			case 'manage':
			{
				switch ($action)
				{
					case 'users':
					{
						// Add users to badge availability.
						$usernames = array_unique(explode("\n", $this->request->variable('usernames', '', true, \phpbb\request\request_interface::POST)));

						if ($submit && $usernames)
						{
							$this->check_form($form_key, '&amp;action=' . $action . '&amp;id=' . $url_id);
							$mssg = $this->badge_manager->add_user_data('badge_availability', $usernames, [$url_id]);
							$this->acp_error($mssg, '&amp;action=' . $action . '&amp;id=' . $url_id);
						}

						// Delete users from badge availability.
						if (($delmark && $mark) || $delall)
						{
							if (!confirm_box(true))
							{
								confirm_box(false, $this->language->lang('CONFIRM_OPERATION'), build_hidden_fields([
									'i' => $id,
									'mode' => $mode,
									'action' => $action,
									'mark' => $mark,
									'delmark' => $delmark,
									'delall' => $delall,
								]));
							}
							else
							{
								$mssg = $this->badge_manager->remove_user_data('badge_availability', $mark, [$url_id]);
								$this->acp_error($mssg, '&amp;action=' . $action . '&amp;id=' . $url_id);
							}
						}

						// Badge availability table view.
						$result = $this->badge_manager->get_data('badge_availability', ['badge_id' => $url_id]);
						$avail_count = 0;

						while ($row = $this->db->sql_fetchrow($result))
						{
							++$avail_count;
							$availrow = array('USER_NAME' => $row['username'], 'ID' => $row['user_id']);
							$this->template->assign_block_vars('availrow', $availrow);
						}

						$this->db->sql_freeresult();
						$this->template->assign_vars([
							'S_' . strtoupper($action) => true,
							'U_BACK' => $this->u_action,
							'U_ACTION' => $this->u_action . '&amp;action=' . $action . '&amp;id=' . $url_id,
							'U_FIND_USERNAME' => append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=searchuser&amp;form=' . $formid . '&amp;field=usernames'),
							'S_AVAILS' => $avail_count > 0
						]);

						break;
					}

					case 'add':
					case 'edit':
					{
						// Save added or update edited badge.
						$badge_label = $this->request->variable('label', '', false, \phpbb\request\request_interface::POST);
						$badge_icon_url = $this->request->variable('icon24', '', false, \phpbb\request\request_interface::POST);
						$badge_type = $this->request->variable('typeid', 0, false, \phpbb\request\request_interface::POST);
						$badge_default = $this->request->variable('default', 0, false, \phpbb\request\request_interface::POST);

						if ($submit)
						{
							$this->check_form($form_key, '&amp;action=' . $action . ($url_id ? '&amp;id=' . $url_id : ''));

							if ((!$badge_label || !$badge_icon_url || !$badge_type))
							{
								$this->acp_error('INPUT_MISSING', '&amp;action=' . $action . ($url_id ? '&amp;id=' . $url_id : ''), E_USER_WARNING);
							}

							$data = ['badge_label' => $badge_label, 'badge_icon_24' => $badge_icon_url, 'badge_type_id' => $badge_type];

							if (!$url_id)
							{
								$data['badge_default'] = $badge_default; // Don't change this when updating a badge.
							}

							$mssg = $this->badge_manager->make('badge', $data, $url_id);
							$this->acp_error($mssg);
						}

						// Form for adding or editing badges.
						$result = $this->badge_manager->get_data('badge', ['badge_id' => $url_id]);
						$badge = [];

						while ($row = $this->db->sql_fetchrow($result))
						{
							if ($action == 'edit' && $url_id == $row['badge_id'])
							{
								$badge = $row;
							}
						}

						$result = $this->badge_manager->get_data('badge_type');
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

						$this->db->sql_freeresult();
						$typelist = '<option value=""' . ($selected == '' ? ' selected="selected"' : '') . '>----------</option>' . $typelist;
						$this->template->assign_vars([
							'S_' . strtoupper($action) => true,
							'U_BACK' => $this->u_action,
							'U_ACTION' => $this->u_action . '&amp;action=' . $action . ($url_id ? '&amp;id=' . $url_id : ''),
							'BADGE_LABEL' => $badge['badge_label'],
							'BADGE_ICON_URL' => $badge['badge_icon_24'],
							'S_TYPE_LIST' => $typelist,
							'S_DEFAULT' => $badge['badge_default']
						]);

						break;
					}

					case 'delete':
					{
						// Delete badge and related availability or user badge data.
						$selects = $this->badge_manager->get_count('badge_user', ['badge_id' => $url_id]);;
						$avails = $this->badge_manager->get_count('badge_availability', ['badge_id' => $url_id]);;
						$message = ($selects ? 'UBADGE_' : '') . ($avails ? 'AVAILS_' : '') . 'CONFIRM_OPERATION';

						if (!confirm_box(true))
						{
							confirm_box(false, $this->language->lang($message), build_hidden_fields([
								'i' => $id,
								'mode' => $mode,
								'badge_id' => $url_id,
								'action' => $action
							]));
						}
						else
						{
							$mssg = $this->badge_manager->delete('badge', $url_id);

							if ($this->request->is_ajax())
							{
								$json_response = new \phpbb\json_response;
								$json_response->send([
									'MESSAGE_TITLE' => $this->language->lang('INFORMATION'),
									'MESSAGE_TEXT' => $this->language->lang($mssg),
									'REFRESH_DATA' => ['time' => 3]
								]);
							}
						}

						break;
					}
				}

				//Manage badges table view.
				$result = $this->badge_manager->get_data('badge');
				$badgecount = 0;
				$typelist = [];

				while ($row = $this->db->sql_fetchrow($result))
				{
					++$badgecount;
					$usedcount = $this->badge_manager->get_count('badge_user', ['badge_id' => (int)$row['badge_id'], 'badge_order' => (int)$this->config['max_profile_badges']]);
					$badgerow = [
						'BADGE_LABEL' => $row['badge_label'],
						'BADGE_ICON_URL' => $row['badge_icon_24'],
						'S_DEFAULT' => $row['badge_default'],
						'BADGE_DEFAULT' => $row['badge_default'] ? $this->language->lang('BADGE_DEFAULT') : '',
						'BADGE_USED_BY' => $usedcount,
						'U_EDIT' => $this->u_action . '&amp;action=edit&amp;id=' . $row['badge_id'],
						'U_USERS' => !$row['badge_default'] ? $this->u_action . '&amp;action=users&amp;id=' . $row['badge_id'] : '',
						'U_DELETE' => $this->u_action . '&amp;action=delete&amp;id=' . $row['badge_id']
					];

					// Group badges by types.
					if (!in_array($row['badge_type_id'], $typelist))
					{
						$typelist[] = $row['badge_type_id'];
						$badgerow['BADGE_TYPE'] = $this->db->sql_fetchfield('badge_type_name', false, $this->badge_manager->get_data('badge_type', ['badge_type_id' => (int)$row['badge_type_id']]));
					}

					$this->template->assign_block_vars('badgerow', $badgerow);
				}

				$this->db->sql_freeresult();
				$this->template->assign_vars([
					'S_BADGES' => $badgecount > 0,
					'U_ADD' => $this->u_action . '&amp;action=add',
					'ICON_USERS' => '<img src="' . $this->helper->get_ext_img_path() . 'add_user.png" alt="' . $this->language->lang('BADGE_AVAIL') . '" title="' . $this->language->lang('BADGE_AVAIL') . '" />'
				]);

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
		trigger_error($this->language->lang($error) . adm_back_link($this->u_action . ($append ? $append : '')), $warning);
	}
}
