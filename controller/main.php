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
		// Profile data
		$sql = $this->core->get_info_sql($fingerprint);
		$result = $this->db->sql_query($sql);
		$data = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		if (!$data)
			return $this->get_response("Error: No profile data");

		$avatar = [
			'src' => $this->core->get_avatar_url($data['user_avatar'], $data['user_avatar_type'], $data['user_avatar_width'], $data['user_avatar_height']),
			'width' => $data['user_avatar_width'],
			'height' => $data['user_avatar_height']
		];

		// Badge data
		$sql = $this->core->get_ubadge_sql_by_key($fingerprint);
		$result = $this->db->sql_query_limit($sql, $this->config['max_profile_badges']);
		$badges = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$badges[] = $row;
		}
		$this->db->sql_freeresult($result);

		// Update last accessed time
		$sql = $this->core->get_update_sql($fingerprint);
		$result = $this->db->sql_query($sql);

		switch ($type)
		{
			case 'info':
			{
				$yaml = "Player:\n";
				$yaml .= "\tFingerprint: " . $data['fingerprint'] . "\n";
				$yaml .=  "\tPublicKey: " . base64_encode($data['public_key']) . "\n";
				$yaml .=  "\tKeyRevoked: " . ($data['revoked'] ? 'true' : 'false') . "\n";
				$yaml .=  "\tProfileID: " . $data['user_id'] . "\n";
				$yaml .=  "\tProfileName: " . $data['username'] . "\n";
				$yaml .=  "\tProfileRank: Registered User\n";
				$yaml .=  "\tAvatar:\n";
				if ($avatar['src'])
				{
					$yaml .=  "\t\tSrc: " . $avatar['src'] . "\n";
					$yaml .=  "\t\tWidth: " . $avatar['width'] . "\n";
					$yaml .=  "\t\tHeight:" . $avatar['height'] . "\n";
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
