<?php
/**
 *
 * OpenRAUserAccounts. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2018, The OpenRAUserAccounts authors
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace openra\openrauseraccounts\controller;
use phpbb\request\request_interface;
use Symfony\Component\HttpFoundation\Response;

/**
 * OpenRAUserAccounts main controller.
 */
class main
{
	private $core;
	private $db;
	private $config;
	private $request;
	private $passwords_manager;
	private $table_prefix;

	/**
	 * Constructor
	 *
	 * @param \openra\openrauseraccounts\core\core $core
	 * @param \phpbb\db\driver\driver_interface $db
	 * @param \phpbb\config\config $config
	 * @param request_interface $request phpBB request object
	 * @param \phpbb\passwords\manager $passwords_manager
	 * @param string $table_prefix
	 */
	public function __construct(\openra\openrauseraccounts\core\core $core, \phpbb\db\driver\driver_interface $db, \phpbb\config\config $config, request_interface $request, \phpbb\passwords\manager $passwords_manager, $table_prefix)
	{
		global $phpbb_container;
		$this->core = $core;
		$this->db = $db;
		$this->config = $config;
		$this->request = $request;
		$this->passwords_manager = $passwords_manager;
		$this->table_prefix = $table_prefix;
	}

	/**
	 * Controller for route /openra/{$type}/{$fingerprint}
	 *
	 * @param string $type, $fingerprint
	 *
	 * @return \Symfony\Component\HttpFoundation\Response A Symfony Response object
	 */
	public function fetchinfo($type, $fingerprint)
	{
		switch ($type)
		{
			case 'info':
			{
				// Retrieve profile data
				$sql = $this->core->get_info_sql($fingerprint);
				if (!($result = $this->db->sql_query($sql)))
				{
					return $this->get_response("Error: Failed to query profile data");
				}
				$data = $this->db->sql_fetchrow($result);
				$this->db->sql_freeresult($result);
				if (!$data)
				{
					return $this->get_response("Error: No profile data");
				}

				// Retrieve badge data
				$sql = $this->core->get_ubadge_sql_by_key($fingerprint);
				if (!($result = $this->db->sql_query_limit($sql, $this->config['max_profile_badges'])))
				{
					return $this->get_response("Error: Failed to query badge data");
				}
				// Store all the badge data in an array to loop over it later
				$badges = array();
				while ($row = $this->db->sql_fetchrow($result))
				{
					$badges[] = $row;
				}
				$this->db->sql_freeresult($result);

				// Update last accessed time
				$sql = $this->core->get_update_sql($fingerprint);
				if (!($result = $this->db->sql_query($sql)))
				{
					return $this->get_response("Error: Failed to update last accessed time");
				}

				$yaml = "Player:\n";
				$yaml .= "\tFingerprint: " . $data['fingerprint'] . "\n";
				$yaml .=  "\tPublicKey: " . base64_encode($data['public_key']) . "\n";
				$yaml .=  "\tKeyRevoked: " . ($data['revoked'] ? 'true' : 'false') . "\n";
				$yaml .=  "\tProfileID: " . $data['user_id'] . "\n";
				$yaml .=  "\tProfileName: " . $data['username'] . "\n";
				$yaml .=  "\tProfileRank: Registered User\n";
				$yaml .=  "\tAvatar:\n";
				if ($avatar_data = $this->core->get_avatar_data($data))
				{
					$yaml .=  "\t\tSrc: " . $avatar_data['src'] . "\n";
					$yaml .=  "\t\tWidth: " . $avatar_data['width'] . "\n";
					$yaml .=  "\t\tHeight: " . $avatar_data['height'] . "\n";
				}
				
				$yaml .=  "\tBadges:\n";
				if ($badges)
				{
					$i = 0;
					foreach ($badges as $badge)
					{
						$yaml .=  "\t\tBadge@$i:\n";
						$yaml .=  "\t\t\tLabel: " . $badge['badge_label'] . "\n";
						$yaml .=  "\t\t\tIcon24: " . $badge['badge_icon_24'] . "\n";

						$badgelen = strlen($badge['badge_icon_24']);
						if ($badgelen > 10)
						{
							$prefix = substr($badge['badge_icon_24'], 0, $badgelen - 10);
							$yaml .=  "\t\t\tIcon48: " . $prefix . "_48x48.png\n";
							$yaml .=  "\t\t\tIcon72: " . $prefix . "_72x72.png\n";
						}

						$i++;
					}
				}

				return $this->get_response($yaml);

				break;
			}

			default:
			{
				return $this->get_response("Error: Unknown route");
			}
		}
	}

	/**
	 * Controller for route /openra/link
	 *
	 * @return \Symfony\Component\HttpFoundation\Response A Symfony Response object
	 */
	public function link()
	{
		$username = $this->request->variable('username', '', true);
		$password = $this->request->variable('password', '', true);
		$pubkey = $this->request->variable('pubkey', '');
		$key_table = $this->table_prefix . 'openra_keys';

		$username_clean = utf8_clean_string($username);

		$sql = 'SELECT *
			FROM ' . USERS_TABLE . "
			WHERE username_clean = '" . $this->db->sql_escape($username_clean) . "'";
		if (!($result = $this->db->sql_query($sql)))
		{
			return $this->get_response("Error: authentication failed");
		}

		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$ip = $this->get_request_ip($this->request);

		if ($ip)
		{
			$sql = 'SELECT COUNT(*) AS attempts
				FROM ' . LOGIN_ATTEMPT_TABLE . '
				WHERE attempt_time > ' . (time() - (int)$this->config['ip_login_limit_time']) . "
				AND attempt_ip = '" . $this->db->sql_escape($ip) . "' ";

			$result = $this->db->sql_query($sql);
			$attempts = (int)$this->db->sql_fetchfield('attempts');
			$this->db->sql_freeresult($result);

			$attempt_data = array(
				'attempt_ip'			=> $ip,
				'attempt_browser'		=> trim(substr($this->request->header('User-Agent'), 0, 149)),
				'attempt_time'			=> time(),
				'user_id'				=> ($row) ? (int)$row['user_id'] : 0,
				'username'				=> $username,
				'username_clean'		=> $username_clean,
			);

			$sql = 'INSERT INTO ' . LOGIN_ATTEMPT_TABLE . $this->db->sql_build_array('INSERT', $attempt_data);
			$this->db->sql_query($sql);
		}
		else
		{
			$attempts = 0;
		}

		// Invalid username
		if (!$row)
		{
			return $this->get_response("Error: authentication failed");
		}

		// Too many login attempts
		$ip_login_attempts = ($this->config['ip_login_limit_max'] && $attempts >= $this->config['ip_login_limit_max']);
		$user_login_attempts = (is_array($row) && $this->config['max_login_attempts'] && $row['user_login_attempts'] >= $this->config['max_login_attempts']);
		if ($ip_login_attempts || $user_login_attempts)
		{
			return $this->get_response("Error: too many login attempts");
		}

		// Inactive user
		if ($row['user_type'] == USER_INACTIVE || $row['user_type'] == USER_IGNORE)
		{
			return $this->get_response("Error: authentication failed");
		}

		if ($this->is_banned($row['user_id']))
		{
			return $this->get_response("Error: banned");
		}

		if (!$this->passwords_manager->check($password, $row['user_password'], $row))
		{
			// Password incorrect - increase login attempts
			$sql = 'UPDATE ' . USERS_TABLE . '
				SET user_login_attempts = user_login_attempts + 1
				WHERE user_id = ' . (int)$row['user_id'] . '
					AND user_login_attempts < ' . LOGIN_ATTEMPTS_MAX;
			$this->db->sql_query($sql);

			return $this->get_response("Error: authentication failed");
		}

		$sql = 'DELETE FROM ' . LOGIN_ATTEMPT_TABLE . '
			WHERE user_id = ' . $row['user_id'];
		$this->db->sql_query($sql);

		if ($row['user_login_attempts'] != 0)
		{
			// Successful, reset login attempts (the user passed all stages)
			$sql = 'UPDATE ' . USERS_TABLE . '
				SET user_login_attempts = 0
				WHERE user_id = ' . $row['user_id'];
			$this->db->sql_query($sql);
		}

		// Sanity check the public key and calculate the fingerprint.
		$fingerprint = '';
		$pubkey_resource = openssl_pkey_get_public($pubkey);
		if ($pubkey_resource)
		{
			$details = openssl_pkey_get_details($pubkey_resource);
			if (array_key_exists('rsa', $details))
			{
				$fingerprint = sha1($details['rsa']['n'] . $details['rsa']['e']);
			}
		}

		// Invalid public key.
		if (!$fingerprint)
		{
			return $this->get_response("Error: invalid key");
		}

		// Reject duplicates.
		$sql = 'SELECT COUNT(*) AS count
			FROM ' . $key_table . '
			WHERE fingerprint = "' . $this->db->sql_escape($fingerprint) . '"
			OR public_key = "' . $this->db->sql_escape($pubkey) . '"';
		$result = $this->db->sql_query($sql);
		$duplicates = (int)$this->db->sql_fetchfield('count');
		$this->db->sql_freeresult($result);

		if ($duplicates)
		{
			return $this->get_response("Error: key exists");
		}

		$data = array(
			'user_id' => (int)$row['user_id'],
			'public_key' => $pubkey,
			'fingerprint' => $fingerprint,
			'registered' => time()
		);

		$sql = 'INSERT INTO ' . $key_table . $this->db->sql_build_array('INSERT', $data);
		$this->db->sql_query($sql);

		return $this->get_response("Success");
	}

	public function get_response($content)
	{
		$response = new Response($content);
		$response->headers->set('Content-Type', 'Content-type: text/plain; charset=utf-8');
		return $response;
	}

	public function is_banned($user_id)
	{
		$sql = 'SELECT ban_exclude, ban_end
			FROM ' . BANLIST_TABLE . "
			WHERE ban_userid = " . $user_id;

		$result = $this->db->sql_query($sql);
		$banned = false;
		while ($row = $this->db->sql_fetchrow($result))
		{
			if ($row['ban_end'] && $row['ban_end'] < time())
			{
				continue;
			}

			if (!empty($row['ban_exclude']))
			{
				$banned = false;
				break;
			}

			$banned = true;
		}

		$this->db->sql_freeresult($result);
		return $banned;
	}

	public function get_request_ip($request)
	{
		// Copied from phpBB session_create function
		$ip = html_entity_decode($request->server('REMOTE_ADDR'), ENT_COMPAT);
		$ip = preg_replace('# {2,}#', ' ', str_replace(',', ' ', $ip));
		$ips = explode(' ', trim($ip));

		$ip = null;

		foreach ($ips as $ip)
		{
			// Normalise IP address
			$ip = phpbb_ip_normalise($ip);

			if ($ip === false)
			{
				// IP address is invalid.
				break;
			}

			// IP address is valid.
			$ip = $ip;
		}

		return $ip;
	}
}
