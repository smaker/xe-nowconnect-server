<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// This can be removed if you use __autoload() in config.php
require(APPPATH.'/libraries/REST_Controller.php');
require(APPPATH.'/libraries/Crypt.php');

class Users extends REST_Controller
{
	protected $methods = array(
		'index_post' => array('level' => 1, 'limit' => 10000),
		'count_post' => array('level' => 1, 'limit' => 10000),
	);

	public function index_post()
	{
		// MongoDB connect
		try
		{
			$this->rest->db = new MongoClient('mongodb://localhost:27017', array('username' => 'ncxe_api', 'password' => 'ncxeapi_kdw1102'));
		}
		catch(MongoConnectionException $e)
		{
		}

		$url_info = parse_url($_SERVER['HTTP_REFERER']);
		if(!$url_info)
		{
			$this->response(array('status' => FALSE, 'error' => 'Invalid Request'), 400);
		}

		$host = $url_info['host'];
		$path = $url_info['path'];

		// [host] + [path]
		$hostStr = $host;

		// https 접속이 아닌 경우 port 번호를 포함함
		if(isset($url_info['scheme']) && $url_info['scheme'] != 'https')
		{
			$hostStr .= (isset($url_info['port']) ? ':' . $url_info['port'] : '');
		}

		$fullHostStr = $hostStr . $path;
		if(substr($fullHostStr, -1, 1) == '/')
		{
			$fullHostStr = substr($fullHostStr, 0, strlen($fullHostStr) - 1);
		}

		require APPPATH.'/libraries/Predis/Autoloader.php';

		Predis\Autoloader::register();

		// Redis connect
		try
		{
			$this->rest->redis = new Predis\Client();
		}
		catch (Exception $e)
		{
		}

		$_key = 'host_' . $hostStr;
		$original_ip = $this->rest->redis->get($_key);
		if(!$original_ip)
		{
			$original_ip = gethostbyname($hostStr);

			$this->rest->redis->set($_key, $original_ip);
			$this->rest->redis->expire($_key, 60 * 60 * 24);
		}

		// 리퍼러 조작이 의심될 경우
		if($original_ip != $_SERVER['REMOTE_ADDR'])
		{
			$this->response(array('status' => FALSE, 'error' => 'Server IP is incorrect'), 400);
		}

		$query = array('site_url' => $fullHostStr);

		// 피라미터
		$isPage = ($this->input->post('isPage') == 'Y');
		$listCount = (int)$this->input->post('listCount');
		$pageCount = (int)$this->input->post('pageCount');
		$page = (int)$this->input->post('page');
		$excludeAdmin = ($this->input->post('excludeAdmin') == 'Y');
		$target = $this->input->post('target');

		$skip = 0;

		// 페이징 기능 사용 시 페이징 처리를 함
		if($isPage)
		{
			(!$listCount || $listCount < 0) AND $listCount = 20;
			(!$pageCount || $pageCount < 0) AND $pageCount = 10;
			(!$page) AND $page = 1;

			$skip  = ($page - 1) * $listCount;
			$next  = ($page + 1);
			$prev  = ($page - 1);
		}

		// 전체 접속자 수 초기화
		$totalCount = 0;
		// 전체 페이지 수 초기화
		$totalPage = 1;

		$findQuery = array();

		if($excludeAdmin)
		{
			$findQuery['is_admin'] = 'N';
		}

		switch($target)
		{
			case 'all':
				break;
			case 'member':
				$findQuery['member_srl'] = array('$gt' => 0);
				break;
		}

		// 전체 접속자 수를 구함
		$totalCount = $this->rest->db->nowconnect->{$fullHostStr}->count($findQuery);
		// 전체 페이지 수를 구함
		if($totalCount && $isPage)
		{
			$totalPage = (int) (($totalCount - 1) / $listCount) + 1;
		}

		// 최근 업데이트 순으로 내림차순 정렬
		$sortQuery = array('last_update' => -1);

		$users = iterator_to_array($this->rest->db->nowconnect->{$fullHostStr}->find($findQuery)->sort($sortQuery)->skip($skip)->limit($listCount));

		/**
		 * DB 연결 닫기
		 */
		$this->rest->db->close();

		$this->response(array('status' => TRUE, 'message' => 'success', 'totalCount' => $totalCount, 'totalPage' => $totalPage, 'users' => $users), 201);
	}


	public function count_post()
	{
		// MongoDB connect
		try
		{
			$this->rest->db = new MongoClient('mongodb://localhost:27017', array('username' => 'ncxe_api', 'password' => 'ncxeapi_kdw1102'));
		}
		catch(MongoConnectionException $e)
		{
		}

		if(!$_SERVER['HTTP_REFERER'])
		{
			$this->response(array('status' => FALSE, 'error' => 'Invalid Request'), 400);
		}

		$url_info = parse_url($_SERVER['HTTP_REFERER']);
		if(!$url_info)
		{
			$this->response(array('status' => FALSE, 'error' => 'Invalid Request'), 400);
		}

		$host = $url_info['host'];
		if(isset($url_info['port']))
		{
			$port = $url_info['port'];
		}
		$path = $url_info['path'];
		$hostStr = $host . (isset($port) ? ':' . $port : '');
		$fullHostStr = $hostStr . $path;
		if(substr($fullHostStr, -1, 1) == '/')
		{
			$fullHostStr = substr($fullHostStr, 0, strlen($fullHostStr) - 1);
		}

		require APPPATH.'/libraries/Predis/Autoloader.php';

		Predis\Autoloader::register();

		// Redis connect
		try
		{
			$this->rest->redis = new Predis\Client();
		}
		catch (Exception $e)
		{
		}

		$_key = 'host_' . $hostStr;
		$original_ip = $this->rest->redis->get($_key);
		if(!$original_ip)
		{
			$original_ip = gethostbyname($hostStr);

			$this->rest->redis->set($_key, $original_ip);
			$this->rest->redis->expire($_key, 60 * 60 * 24);
		}

		// 리퍼러 조작이 의심될 경우
		if($original_ip != $_SERVER['REMOTE_ADDR'])
		{
			$this->response(array('status' => FALSE, 'error' => 'Server IP is incorrect'), 400);
		}

		$query = array('site_url' => $fullHostStr);

		// 피라미터
		$excludeAdmin = ($this->input->post('excludeAdmin') == 'Y');

		// 전체 접속자 수 초기화
		$totalCount = 0;

		$countQuery = array();

		if($excludeAdmin)
		{
			$countQuery['is_admin'] = 'N';
		}

		// 전체 접속자 수를 구함
		$totalCount = $this->rest->db->nowconnect->{$fullHostStr}->count($countQuery);

		/**
		 * DB 연결 닫기
		 */
		$this->rest->db->close();

		$this->response(array('status' => TRUE, 'message' => 'success', 'totalCount' => $totalCount), 201);
	}
}
