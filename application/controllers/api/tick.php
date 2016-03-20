<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// This can be removed if you use __autoload() in config.php
require(APPPATH.'/libraries/REST_Controller.php');
require(APPPATH.'/libraries/Crypt.php');

class Tick extends REST_Controller
{
	protected $methods = array(
		'index_post' => array('level' => 1, 'limit' => 10000),
	);

	public function index_post()
	{
		/*
		if(!isset($this->rest))
		{
			$this->rest = new stdClass;

			// MongoDB connect
			try
			{
				$this->rest->db = new MongoClient('mongodb://localhost:27017');
			}
			catch(MongoConnectionException $e)
			{
			}
		}*/

		$url_info = parse_url($_SERVER['HTTP_REFERER']);
		if(!$url_info)
		{
			$this->response(array('status' => FALSE, 'error' => 'Invalid Request'), 400);
		}

		// [host] + [path]
		$hostStr = $url_info['host'];

		// https 접속이 아닌 경우 port 번호를 포함함
		if(isset($url_info['scheme']) && $url_info['scheme'] != 'https')
		{
			$hostStr .= (isset($url_info['port']) ? ':' . $url_info['port'] : '');
		}

		$fullHostStr = $hostStr . $url_info['path'];
		if(substr_compare($fullHostStr, '/', -1, 1) == 0)
		{
			$fullHostStr = substr($fullHostStr, 0, strlen($fullHostStr) - 1);
		}

		/*require APPPATH.'/libraries/Predis/Autoloader.php';

		Predis\Autoloader::register();

		// Redis connect
		try
		{
			$this->rest->redis = new Predis\Client();
		}
		catch (Exception $e)
		{
		}

		$_key = 'ips_' . $hostStr;
		$original_ips = $this->rest->redis->get($_key);*/
		if($original_ips)
		{
			$original_ips = explode('|@|', $original_ips);
		}
		else
		{
			$original_ips = implode('|@|', gethostbynamel($hostStr));

			//$this->rest->redis->set($_key, $original_ips);
			//$this->rest->redis->expire($_key, 60 * 60 * 24);
		}

		// 리퍼러 조작이 의심될 경우
		if(!in_array($_SERVER['REMOTE_ADDR'], $original_ips))
		{
			$this->response(array('status' => FALSE, 'error' => 'Server IP is incorrect'), 400);
		}

		$key = 'key:' . $fullHostStr;

		//$key_info = unserialize($this->rest->redis->get($key));
		if(!$key_info)
		{
			$query = array('site_url' => $fullHostStr);
			$columnList = array('key');
			$key_info = $this->db->select('key')->from(config_item('rest_keys_table'))->where($query)->get()->result();
			if(!$key_info)
			{
				$this->response(array('status' => FALSE, 'error' => 'Invalid Request'), 400);
			}

			//$this->rest->redis->set($key, serialize($key_info));
			//$this->rest->redis->expire($key, 60 * 60 * 24);
		}

		// 암호화 옵션
		$options = array(
			'key'		=>	$key_info->key,
			'mode'		=>	'ecb',
			'algorithm'	=>	'blowfish',
			'base64'	=>	true
		);

		$oCrypt = new Crypt($options);
		$user_info = unserialize($oCrypt->decrypt($this->input->post('user_info')));
		if(!$user_info)
		{
			$this->response(array('status' => FALSE, 'error' => 'Invalid Request'), 400);
		}


		if($user_info['user-agent'] == NULL || $user_info['user-agent'] == '')
		{
			$this->response(array('status' => FALSE, 'error' => 'Invalid Request'), 400);
		}

		require APPPATH.'/libraries/Mobile_Detect.php';


		$detectionKey = md5($user_info['user-agent']) . '_isBot';
		$detectionKey2 = md5($user_info['user-agent']) . '_isMobileBot';

		/*$isBot = $this->rest->redis->get($detectionKey);
		$isMobileBot = $this->rest->redis->get($detectionKey2);*/

		if($isBot == NULL || $isBot == '' || $isMobileBot == NULL || $isMobileBot == '')
		{
			$detect = new Mobile_Detect(null, $user_info['user-agent']);
			$detect->setDetectionType(Mobile_Detect::DETECTION_TYPE_EXTENDED);

			$isBot = $detect->is('Bot');
			$isMobileBot = $detect->is('MobileBot');
			/*$this->rest->redis->set($detectionKey, $isBot);
			$this->rest->redis->set($detectionKey2, $isMobileBot);
			$this->rest->redis->expire($detectionKey, 60 * 60 * 24 * 30);
			$this->rest->redis->expire($detectionKey2, 60 * 60 * 24 * 30);*/
		}

		if($isBot || $isMobileBot)
		{
			return;
		}

		$user_info['last_update'] = date('YmdHis');
		$user_info['location']['uri'] = mb_convert_encoding($user_info['location']['uri'], 'UTF-8');
		$user_info['user_agent'] = &$user_info['user-agent'];

		unset($user_info['user-agent']);

		$count = $this->db->where('_id', $user_info['_id'])->from($fullHostStr)->count_all_results();
		if($count > 0)
		{
			$this->db->where('_id', $user_info['_id'])->update($fullHostStr, $user_info);
		}
		else
		{
			$this->db->insert($fullHostStr, $user_info);
		}

//		$this->rest->db->nowconnect->{$fullHostStr}->save($user_info);
//		$this->rest->db->nowconnect->{$fullHostStr}->ensureIndex(array('last_update' => -1), array('expireAfterSeconds' => 180));

//		$this->rest->db->close();

		$this->response(array('status' => TRUE, 'message' => 'success'), 201);
	}
}
