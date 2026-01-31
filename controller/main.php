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

	/**
	 * Constructor
	 *
	 * @param \openra\openrauseraccounts\core\core $core
	 * @param \phpbb\db\driver\driver_interface $db
	 * @param \phpbb\config\config $config
	 * @param request_interface $request phpBB request object
	 */
	public function __construct(\openra\openrauseraccounts\core\core $core, \phpbb\db\driver\driver_interface $db, \phpbb\config\config $config, request_interface $request)
	{
		$this->core = $core;
		$this->db = $db;
		$this->config = $config;
		$this->request = $request;
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
				$yaml .= "\tPublicKey: " . base64_encode($data['public_key']) . "\n";
				$yaml .= "\tKeyRevoked: " . ($data['revoked'] ? 'true' : 'false') . "\n";
				$yaml .= "\tProfileID: " . $data['user_id'] . "\n";
				$yaml .= "\tProfileName: " . $data['username'] . "\n";
				$yaml .= "\tProfileRank: Registered User\n";
				$yaml .= "\tAvatar:\n";
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
		global $phpbb_container, $table_prefix;
		$username = $this->request->variable('username', '', true);
		$password = $this->request->variable('password', '', true);
		$pubkey = $this->request->variable('pubkey', '');
		$key_table = $table_prefix . 'openra_keys';

		$provider_collection = $phpbb_container->get('auth.provider_collection');
		$provider = $provider_collection->get_provider();
		if ($provider)
		{
			$login = $provider->login($username, $password);
			if ($login['status'] == LOGIN_SUCCESS)
			{
				if ($this->is_banned($login['user_row']['user_id']))
				{
					return $this->get_response("Error: banned");
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
					'user_id' => (int)$login['user_row']['user_id'],
					'public_key' => $pubkey,
					'fingerprint' => $fingerprint,
					'registered' => time()
				);

				$sql = 'INSERT INTO ' . $key_table . $this->db->sql_build_array('INSERT', $data);
				$this->db->sql_query($sql);

				return $this->get_response("Success");
			}
			else if ($login['status'] == LOGIN_ERROR_ATTEMPTS)
			{
				return $this->get_response("Error: too many login attempts");
			}
		}

		return $this->get_response("Error: authentication failed");
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
}
