<?php
/**
 *
 * OpenRAUserAccounts. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2018, The OpenRAUserAccounts authors
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace openra\openrauseraccounts\core;

/**
 * OpenRAUserAccounts core service.
 */
class core
{
	private $config;
	private $db;
	private $table_prefix;
	private $path_helper;
	private $ext_manager;
	private $avatar_manager;

	public function __construct()
	{
		global $phpbb_container;
		$this->config = $phpbb_container->get('config');
		$this->db = $phpbb_container->get('dbal.conn');
		$this->table_prefix = $phpbb_container->getParameter('core.table_prefix');
		$this->path_helper = $phpbb_container->get('path_helper');
		$this->ext_manager = $phpbb_container->get('ext.manager');
		$this->avatar_manager = $phpbb_container->get('avatar.manager');
	}

	/**
	 * Returns the sql SELECT statement to fetch general player data.
	 *
	 * @param var $fingerprint
	 * @return string DBal SELECT statement
	 * @link https://wiki.phpbb.com/Dbal.sql_build_query
	 */
	public function get_info_sql($fingerprint)
	{
		$sql_array = array(
			'SELECT' => 'pubkey.item_id, pubkey.user_id, pubkey.public_key, pubkey.fingerprint, pubkey.revoked, user.username, user.user_avatar, user.user_avatar_type, user.user_avatar_width, user.user_avatar_height',

			'FROM' => array(
				USERS_TABLE => 'user',
				$this->table_prefix . 'openra_keys' => 'pubkey'
			),

			'WHERE' => 'pubkey.fingerprint = "' . $this->db->sql_escape($fingerprint) . '"
				AND pubkey.user_id = user.user_id'
		);

		$sql = $this->db->sql_build_query('SELECT', $sql_array);

		return $sql;
	}

	/**
	 * Returns the sql SELECT statement to fetch user badge data by a fingerprint.
	 *
	 * @param var $fingerprint
	 * @return string DBal SELECT statement
	 */
	public function get_ubadge_sql_by_key($fingerprint)
	{
		$sql_array = array(
			'SELECT' => 'badge.badge_label, badge.badge_icon_24',

			'FROM' => array(
				USERS_TABLE => 'user',
				$this->table_prefix . 'openra_keys' => 'pubkey',
				$this->table_prefix . 'openra_badges' => 'badge',
				$this->table_prefix . 'openra_user_badges' => 'ubadge',
			),

			'WHERE' => 'pubkey.fingerprint = "' . $this->db->sql_escape($fingerprint) . '"
				AND pubkey.user_id = user.user_id
				AND ubadge.user_id = user.user_id
				AND ubadge.badge_id = badge.badge_id',

			'ORDER_BY' => 'ubadge.badge_order'
		);

		$sql = $this->db->sql_build_query('SELECT', $sql_array);

		return $sql;
	}

	/**
	 * Returns the sql SELECT statement to fetch user badge data.
	 *
	 * @param var $user_id
	 * @return string DBal SELECT statement
	 */
	public function get_ubadge_sql_by_id($user_id)
	{
		$sql_array = array(
			'SELECT' => 'ubadge.item_id, ubadge.badge_order, badge.badge_label, badge.badge_icon_24',

			'FROM' => array(
				$this->table_prefix . 'openra_badges' => 'badge',
				$this->table_prefix . 'openra_user_badges' => 'ubadge',
			),

			'WHERE' => 'ubadge.user_id = ' . (int)$user_id . '
				AND ubadge.badge_id = badge.badge_id',

			'ORDER_BY' => 'ubadge.badge_order'
		);

		$sql = $this->db->sql_build_query('SELECT', $sql_array);

		return $sql;
	}

	/**
	 * Returns the sql UPDATE statement to update last accessed time.
	 *
	 * @param var $fingerprint
	 * @return string DBal UPDATE statement
	 */
	public function get_update_sql($fingerprint)
	{
		// Update last accessed time
		$timestamp = time();
		$sql_array = array(
			'last_accessed' => $timestamp
		);

		$sql = 'UPDATE ' . $this->table_prefix . 'openra_keys' . '
			SET ' . $this->db->sql_build_array('UPDATE', $sql_array) . '
			WHERE fingerprint = "' . $this->db->sql_escape($fingerprint) . '"';

		return $sql;
	}

	/**
	 * Returns the relative path for custom images used in the extension.
	 *
	 * @return string
	 */
	public function get_ext_img_path()
	{
		return $this->path_helper->get_phpbb_root_path() . $this->ext_manager->get_extension_path('openra/openrauseraccounts') . 'images/';
	}

	/**
	 * Validates the bade order and fixes it if necessary.
	 * Returns true on success.
	 *
	 * @return bool
	 */
	public function validate_badge_order($user_id)
	{
		$sql = 'SELECT item_id, badge_order
			FROM ' . $this->table_prefix . 'openra_user_badges' . '
			WHERE user_id = ' . (int)$user_id . '
			ORDER BY badge_order';

		if (!($result = $this->db->sql_query($sql)))
		{
			return false;
		}

		if ($row = $this->db->sql_fetchrow($result)) // Fetch first row to use it before while loop starts.
		{
			$order = 0; // Loop over user badges and check the order against this counter.
			do
			{
				++$order;
				if ($row['badge_order'] != $order)
				{
					$this->db->sql_query('UPDATE ' . $this->table_prefix . 'openra_user_badges' . '
					SET badge_order = ' . $order . '
					WHERE item_id = ' . $row['item_id']);
				}
			}
			while ($row = $this->db->sql_fetchrow($result)); // This will start again with the first row.
		}
		$this->db->sql_freeresult($result);

		return true;
	}

	/**
	 * Returns the absolute url to profile images (avatars) or an empty string if not found.
	 *
	 * @param string $user_avatar Filename or e-mail address
	 * @param string|int $avatar_type Internal "driver" name or legacy constants with integer values.
	 * @param int $width
	 * @param int $height
	 * @return string
	 */
	public function get_avatar_url($user_avatar, $avatar_type, $width, $height)
	{
		// Avatar not allowed or not set by user
		if (!$this->config['allow_avatar'] || !$user_avatar)
			return "";

		switch ($avatar_type)
		{
			// 1
			case AVATAR_UPLOAD:
			case 'avatar.driver.upload':
				return generate_board_url() . '/download/file.php' . '?avatar=' . $user_avatar;
			
			// 2
			case AVATAR_REMOTE:
			case 'avatar.driver.remote':
				return $user_avatar;
			
			// 3
			case AVATAR_GALLERY:
			case 'avatar.driver.local':
				return generate_board_url() . '/' . $this->config['avatar_gallery_path'] . '/' . $user_avatar;

			// No legacy value
			case 'avatar.driver.gravatar':
			{
				$url = 'https://secure.gravatar.com/avatar/' . md5(strtolower(trim($user_avatar)));
				if ($width || $height)
					$url .= '?s=' . max($width, $height);
				
				return $url;
			}

			// Invalid data
			default:
				return "";
		}
	}
}
