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
use Symfony\Component\HttpFoundation\Response;

/**
 * OpenRAUserAccounts main controller.
 */
class main
{
	private $core;
	private $db;
	private $config;
	private $table_prefix;

	/**
	 * Constructor
	 *
	 * @param \openra\openrauseraccounts\core\core $core
	 * @param \phpbb\db\driver\driver_interface $db
	 * @param \phpbb\config\config $config
	 */
	public function __construct(\openra\openrauseraccounts\core\core $core, \phpbb\db\driver\driver_interface $db, \phpbb\config\config $config)
	{
		global $phpbb_container;
		$this->core = $core;
		$this->db = $db;
		$this->config = $config;
		$this->table_prefix = $phpbb_container->getParameter('core.table_prefix');
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
				$yaml .= "\tJoined: " . gmdate("Y-m-d H:i:s", $data['user_regdate']) . "\n";
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

	public function get_response($content)
	{
		$response = new Response($content);
		$response->headers->set('Content-Type', 'Content-type: text/plain; charset=utf-8');
		return $response;
	}
}
