<?php

namespace Model\Services;

use Nette;
use Facebook;
use BaseFacebook;
use Nette\Utils\Arrays;
use ReflectionException;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;


/**
 * @method BaseFacebook setAppId(string $appId)
 * @method BaseFacebook setAppSecret(string $apiSecret)
 * @method BaseFacebook setFileUploadSupport(bool $fileUploadSupport)
 * @method BaseFacebook setAccessToken(string $access_token)
 * @method void setExtendedAccessToken()
 * @method string getAppId()
 * @method string getAppSecret()
 * @method bool getFileUploadSupport()
 * @method string getAccessToken()
 * @method string getUserAccessToken()
 * @method string getSignedRequest()
 * @method string getLoginUrl(array $params = array())
 * @method string getLogoutUrl(array $params = array())
 * @method string getLoginStatusUrl(array $params = array())
 * @method mixed api()
 * @method void destroySession()
 */
class FacebookFacade extends Nette\Object
{

	/** @var Facebook */
	protected $fb;

	/** @var Cache */
	protected $cache;



	/**
	 * @param  int
	 * @param  string
	 * @param  IStorage
	 */
	function __construct($appID, $secret, IStorage $storage)
	{
		$this->fb = new Facebook(array(
			'appId' => $appID,
			'secret' => $secret,
		));

		$this->cache = new Cache($storage, __CLASS__);
	}



	/**
	 * @param  string|NULL
	 * @return array|NULL
	 */
	function getUser($fbID = NULL)
	{
		if ($fbID === NULL) {
			if ($this->fb->getUser()) {
				return $this->fb->api('/me');
			}

			return NULL;

		} else {
			return $this->fb->api("/$fbID");
		}
	}



	/** @return array */
	function getFriends()
	{
		return Arrays::get( $this->fb->api('/me/friends'), 'data' );
	}



	/**
	 * API:
	 * - $this->getProfilePictureUrl() -> gets square profile picture of currently logged in user
	 * - $this->getProfilePictureUrl('square') -> same as above
	 * - $this->getProfilePictureUrl('[fbID]')
	 * - $this->getProfilePictureUrl(40, 40) -> gets 40×40 profile picture of currently logged in user
	 * - $this->getProfilePictureUrl('[fbID]', 'square')
	 * - $this->getProfilePictureUrl('[fbID]', 40, 40)
	 *
	 * @param  string
	 * @param  string|int
	 * @param  int
	 * @return string
	 */
	function getProfilePictureUrl($fbID = NULL, $type = 'square', $height = NULL)
	{
		switch (func_num_args()) {
			case 0: // square image of logged user
				$user = $this->getUser();
				$fbID = $user['id'];
				$query = "type=$type";

				break;

			case 1:
				if (is_string($fbID)) {
					if (is_numeric($fbID)) { // square image of user [fbID]
						$query = "type=$type";

					} else { // $type image of logged user
						$query = "type=$fbID";

						$user = $this->getUser();
						$fbID = $user['id'];
					}

				} else {
					throw new \InvalidArgumentException;
				}

				break;

			case 2:
				if (is_int($fbID) && is_int($type)) {
					$query = "width=$fbID&height=$type";
					$user = $this->getUser();
					$fbID = $user['id'];

				} elseif (is_string($fbID) && is_string($type)) {
					$query = "type=$type";

				} else {
					throw new \InvalidArgumentException;
				}

				break;

			default:
				$query .= "width=$type&height=$height";
				break;
		}


		return $this->loadProfilePicture("https://graph.facebook.com/$fbID/picture", $query);
	}



	/**
	 * @param  string
	 * @param  string
	 * @return string
	 */
	protected function loadProfilePicture($url, $query)
	{
		$key = func_get_args();
		$img = $this->cache->load($key);
		if ($img === NULL) {
			$context = stream_context_create(array(
				'http' => array(
					'method' => 'GET',
					'header' => 'Content-type: application/x-www-form-urlencoded',
					'content' => $query,
				),
			));

			$fp = fopen($url, 'r', FALSE, $context);
			$content = stream_get_contents($fp);
			fclose($fp);

			$img = $this->cache->save($key, $content, array(
				Cache::EXPIRE => '+ 1 hour',
			));
		}

		return Nette\Templating\Helpers::dataStream($img);
	}



	/**
	 * Calls internal facebook SDK task
	 *
	 * @param  string
	 * @param  array
	 * @return mixed
	 */
	function __call($name, $args)
	{
		try {
			return Nette\Reflection\Method::from( $this->fb, $name )->invokeArgs( $this->fb, $args );

		} catch (ReflectionException $e) { // method does not exist
			return parent::__call($name, $args);
		}
	}

}
