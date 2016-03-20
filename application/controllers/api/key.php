<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Keys Controller
 *
 * This is a basic Key Management REST controller to make and delete keys.
 *
 * @package		CodeIgniter
 * @subpackage	Rest Server
 * @category	Controller
 * @author		Phil Sturgeon
 * @link		http://philsturgeon.co.uk/code/
*/

// This can be removed if you use __autoload() in config.php
require(APPPATH.'/libraries/REST_Controller.php');

class Key extends REST_Controller
{
	protected $methods = array(
		'index_put' => array('level' => 10, 'limit' => 10),
		'index_delete' => array('level' => 10),
		'level_post' => array('level' => 10),
		'regenerate_post' => array('level' => 10),
		'list_post' => array('level' => 10),
		'info_post' => array('level' => 10)
	);

	public function __construct()
	{
		parent::__construct();

		if(!isset($this->rest->db))
		{
			$this->rest = new stdClass;
			$this->rest->db = new MongoClient('mongodb://userid:password@localhost:27017');
		}
	}

	/**
	 * Key Create
	 *
	 * Insert a key into the database.
	 *
	 * @access	public
	 * @return	void
	 */
	public function index_put()
	{
		// Build a new key
		$key = self::_generate_key();

		// If no key level provided, give them a rubbish one
		$level = $this->put('level') ? $this->put('level') : 1;
		$ignore_limits = $this->put('ignore_limits') ? $this->put('ignore_limits') : 1;
		$api_status = $this->put('api_status') ? $this->put('api_status') : 'standby';
		$site_name = $this->put('site_name');
		if(!$site_name)
		{
			$this->response(array('status' => 0, 'error' => 'Could not save the key. (Site Name is empty)'), 500); // 500 = Internal Server Error
		}

		$site_url = $this->put('site_url');
		if(!$site_url)
		{
			$this->response(array('status' => 0, 'error' => 'Could not save the key. (Site URL is empty)'), 500); // 500 = Internal Server Error
		}

		$api_status_list = array('standby' => 1, 'postponed' => 1, 'accepted' => 1);
		if(!isset($api_status_list[$api_status]))
		{
			$api_status = 'standby';
		}

		// Insert the new key
		if (self::_insert_key($key, array('level' => $level, 'ignore_limits' => $ignore_limits, 'api_status' => $api_status, 'site_name' => $site_name, 'site_url' => $site_url)))
		{
			$this->response(array('status' => 1, 'key' => $key), 201); // 201 = Created
		}

		else
		{
			$this->response(array('status' => 0, 'error' => 'Could not save the key.'), 500); // 500 = Internal Server Error
		}
    }

	// --------------------------------------------------------------------

	/**
	 * Key Delete
	 *
	 * Remove a key from the database to stop it working.
	 *
	 * @access	public
	 * @return	void
	 */
	public function index_delete()
    {
		$key = $this->delete('key');

		// Does this key even exist?
		if ( ! self::_key_exists($key))
		{
			// NOOOOOOOOO!
			$this->response(array('status' => 0, 'error' => 'Invalid API Key.'), 400);
		}

		// Kill it
		self::_delete_key($key);

		// Tell em we killed it
		$this->response(array('status' => 1, 'success' => 'API Key was deleted.'), 200);
    }

	/**
	 * Update Key
	 *
	 * Change the key info
	 *
	 * @access	public
	 * @return	void
	 */
	public function update_post()
	{
		$key = $this->post('key');
		$new_level = $this->post('level');
		$api_status = $this->post('api_status');
		$site_url = $this->post('site_url');
		$site_name = $this->post('site_name');

		// Does this key even exist?
		if ( ! self::_key_exists($key))
		{
			// NOOOOOOOOO!
			$this->response(array('error' => 'Invalid API Key.'), 400);
		}

		// Update the key level
		if (self::_update_key($key, array('key' => $key, 'level' => $new_level, 'api_status' => $api_status, 'site_url' => $site_url, 'site_name' => $site_name )))
		{
			$this->response(array('status' => 1, 'success' => 'API Key was updated.'), 200); // 200 = OK
		}

		else
		{
			$this->response(array('status' => 0, 'error' => 'Could not update the key level.'), 500); // 500 = Internal Server Error
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Update Key
	 *
	 * Change the level
	 *
	 * @access	public
	 * @return	void
	 */
	public function level_post()
    {
		$key = $this->post('key');
		$new_level = $this->post('level');

		// Does this key even exist?
		if ( ! self::_key_exists($key))
		{
			// NOOOOOOOOO!
			$this->response(array('error' => 'Invalid API Key.'), 400);
		}

		// Update the key level
		if (self::_update_key($key, array('level' => $new_level)))
		{
			$this->response(array('status' => 1, 'success' => 'API Key was updated.'), 200); // 200 = OK
		}

		else
		{
			$this->response(array('status' => 0, 'error' => 'Could not update the key level.'), 500); // 500 = Internal Server Error
		}
    }

	// --------------------------------------------------------------------

	/**
	 * Update Key
	 *
	 * Change the level
	 *
	 * @access	public
	 * @return	void
	 */
	public function suspend_post()
    {
		$key = $this->post('key');

		// Does this key even exist?
		if ( ! self::_key_exists($key))
		{
			// NOOOOOOOOO!
			$this->response(array('error' => 'Invalid API Key.'), 400);
		}

		// Update the key level
		if (self::_update_key($key, array('level' => 0)))
		{
			$this->response(array('status' => 1, 'success' => 'Key was suspended.'), 200); // 200 = OK
		}

		else
		{
			$this->response(array('status' => 0, 'error' => 'Could not suspend the user.'), 500); // 500 = Internal Server Error
		}
    }

	// --------------------------------------------------------------------

	/**
	 * Regenerate Key
	 *
	 * Remove a key from the database to stop it working.
	 *
	 * @access	public
	 * @return	void
	 */
	public function regenerate_post()
    {
		$old_key = $this->post('key');
		$key_details = self::_get_key($old_key);

		// The key wasnt found
		if ( ! $key_details)
		{
			// NOOOOOOOOO!
			$this->response(array('status' => 0, 'error' => 'Invalid API Key.'), 400);
		}

		// Build a new key
		$new_key = self::_generate_key();

		// Insert the new key
		if (self::_insert_key($new_key, array('level' => $key_details->level, 'ignore_limits' => $key_details->ignore_limits)))
		{
			// Suspend old key
			self::_update_key($old_key, array('level' => 0));

			$this->response(array('status' => 1, 'key' => $new_key), 201); // 201 = Created
		}

		else
		{
			$this->response(array('status' => 0, 'error' => 'Could not save the key.'), 500); // 500 = Internal Server Error
		}
    }

	// --------------------------------------------------------------------

	/* Helper Methods */
	
	private function _generate_key()
	{
		$this->load->helper('security');
		
		do
		{
			$salt = do_hash(time().mt_rand());
			$new_key = substr($salt, 0, config_item('rest_key_length'));
		}

		// Already in the DB? Fail. Try again
		while (self::_key_exists($new_key));

		return $new_key;
	}

	// --------------------------------------------------------------------

	/* Private Data Methods */

	private function _get_key($key)
	{
		$query= array(config_item('rest_key_column') => $key);
		return $this->rest->db->rest->{config_item('rest_keys_table')}->findOne($query);
	}

	// --------------------------------------------------------------------

	private function _key_exists($key)
	{
		$query = array(config_item('rest_key_column') => $key);
		return is_array($this->rest->db->rest->{config_item('rest_keys_table')}->findOne($query));
	}

	// --------------------------------------------------------------------

	private function _insert_key($key, $data)
	{
		$data['_id'] = new MongoId();
		$data[config_item('rest_key_column')] = $key;
		$data['date_created'] = function_exists('now') ? now() : time();
		if(!isset($data['ignore_limits']))
		{
			$data['ignore_limits'] = 0;
		}
		if(!isset($data['is_private_key']))
		{
			$data['is_private_key'] = 0;
		}

		return $this->rest->db->rest->{config_item('rest_keys_table')}->insert($data);
	}

	// --------------------------------------------------------------------

	private function _update_key($key, $data)
	{
		$where = array(config_item('rest_key_column') => $key);
		return $this->rest->db->rest->{config_item('rest_keys_table')}->update($where, array('$set' => $data));
	}

	// --------------------------------------------------------------------

	private function _delete_key($key)
	{
		return $this->db->where(config_item('rest_key_column'), $key)->delete(config_item('rest_keys_table'));
	}

	public function list_post()
	{
		$api_list = $this->rest->db->rest->{config_item('rest_keys_table')}->find();

		// 전체 API 키 개수
		$totalCount = $api_list->count();

		$api_list = iterator_to_array($api_list);

		$this->response(array('status' => 1, 'result' => $api_list, 'totalCount' => $totalCount), 201); // 201 = Created
	}

	public function info_post()
	{
		$key = $this->post('key');
		if(!$key)
		{
			$this->response(array('status' => 0, 'error' => 'Invalid Request'), 500); // 500 = Internal Server Error
		}

		$key_info = self::_get_key($key);
		if(!$key_info)
		{
			$this->response(array('status' => 0, 'error' => 'Could not get the key info.'), 500); // 500 = Internal Server Error
		}

		$this->response(array('status' => 1, 'result' => $key_info ), 201); // 201 = Created
	}
}
