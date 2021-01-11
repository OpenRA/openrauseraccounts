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
	 * Controller for route /openra/{$type}/{$fingerprint}/{format}
	 *
	 * @param string $type 
	 * @param string $fingerprint
	 * @param string $format Response format, default value is 'MiniYAML'
	 *
	 * @return \Symfony\Component\HttpFoundation\Response A Symfony Response object
	 */
	public function fetchinfo($type, $fingerprint, $format)
	{
		// Profile data
		$sql = $this->core->get_info_sql($fingerprint);
		$result = $this->db->sql_query($sql);
		$data = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		if (!$data)
			return $this->get_response("Error: No profile data", $format);

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
				if (strtolower($format) !== "json")
				{
					$content = "Player:\n";
					$content .= "\tFingerprint: " . $data['fingerprint'] . "\n";
					$content .=  "\tPublicKey: " . base64_encode($data['public_key']) . "\n";
					$content .=  "\tKeyRevoked: " . ($data['revoked'] ? 'true' : 'false') . "\n";
					$content .=  "\tProfileID: " . $data['user_id'] . "\n";
					$content .=  "\tProfileName: " . $data['username'] . "\n";
					$content .=  "\tProfileRank: Registered User\n";
					$content .=  "\tAvatar:\n";
					if ($avatar['src'])
					{
						$content .=  "\t\tSrc: " . $avatar['src'] . "\n";
						$content .=  "\t\tWidth: " . $avatar['width'] . "\n";
						$content .=  "\t\tHeight:" . $avatar['height'] . "\n";
					}
					
					$content .=  "\tBadges:\n";
					if ($badges)
					{
						$i = 0;
						foreach ($badges as $badge)
						{
							$content .=  "\t\tBadge@$i:\n";
							$content .=  "\t\t\tLabel: " . $badge['badge_label'] . "\n";
							$content .=  "\t\t\tIcon24: " . $badge['badge_icon_24'] . "\n";
							$i++;
						}
					}
				} else {
					$content = [
						'Player' => [
							'Fingerprint' => $data['fingerprint'],
							'PublicKey' => base64_encode($data['public_key']),
							'KeyRevoked' => ($data['revoked'] ? 'true' : 'false'),
							'ProfileID' => $data['user_id'],
							'ProfileName' => $data['username'],
							'ProfileRank' => 'Registered User',
							'Avatar' => $avatar,
							'Badges' => $badges,
						]
					];
				}

				return $this->get_response($content, $format);

				break;
			}

			default:
			{
				return $this->get_response("Error: Unknown route", $format);
			}
		}
	}

	public function get_response($content, $format)
	{
		if (strtolower($format) !== "json")
		{
			$response = new Response($content);
			$response->headers->set('Content-Type', 'Content-type: text/plain; charset=utf-8');
		} else {
			$response = new Response();
			$response->setContent(json_encode($content, JSON_UNESCAPED_SLASHES));
			$response->headers->set('Content-Type', 'application/json');
		}
		return $response;
	}
}
