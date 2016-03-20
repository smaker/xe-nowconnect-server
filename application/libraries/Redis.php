<?
error_reporting(E_ERROR | E_WARNING | E_PARSE);

/**
* @author lovedv
* Redis Request Class
*/
class Redis{
    
	//------------------------------------
	// Class Constant
	//------------------------------------

	#Cariage return string
	const CRLF ="\r\n";

    #Cariage return string length
    const CRLF_LEN = 2;
	
	#redis auth command
	const AUTH_COMMAND = "AUTH";
	
	#redis auth result
	const AUTH_SUCCESS = "OK";
	
	#redis quit command
	const QUIT_COMMAND = "QUIT";
	
	#max stream block size	
	const MAX_BLOCK_SIZE = 4096;
	
	//----------------------------------------------------------
	// Class Properties
	//----------------------------------------------------------
		
	protected $connection;
	protected $response;
	private $host;
	private $port;
	private $passwd;
	
	//----------------------------------------------------------
	// Constructor
	//----------------------------------------------------------
	/**
	 * constructor
	 *
	 * @param string $host 
	 * @param string $port 
	 * @param string $passwd 
	 * @author lovedev
	 */
	public function __construct($host = 'localhost', $port = '6379', $passwd = null){
		$this->host = $host;
		$this->port = $port;
		$this->passwd = $passwd;
	}
	
    
	/**
	 * execute redis command
	 *
	 * @return String
	 * @author lovedev
	 * @usage 
	 * $r = new Redis("host", 6379, "passwd");
	 * $r->execute("zadd", "rank_class", 100, "lovedev");
	 * $r->execute("zadd", "rank_class", 101, "kim");
	 * $result = $r->execute("zrange", "rank_class", 0, -1);
	 *
	 * $r->execute("ping");
	 * $r->execute("sadd", "test", "hello");
	 * anything what you want command 
	 * //must close connection
 	 * $r->close();
	 * 
	 */
	public function execute(){
		if(!$this->connection){
			$this->connection = fsockopen($this->host, $this->port, $errno, $errstr, 10);
			
			if($this->doAuth() === false)
				return false;
		}
		
        if(!$this->connection){
			$this->throw_error("error : {$this->host} errno={$errno}, errstr={$errstr}");
            return false;
        }
				
		$command = $this->getCommand(func_get_args());
		return $this->sendQuery($command);
	}
	
	/**
	 * 
	 *
	 * @param string $cmds 
	 * @return String
	 * @author lovedev
	 */
	private function getCommand($cmds){
		$command = '*'.count($cmds).self::CRLF;

		foreach ($cmds as $cmd) 
			$command .= "$".strlen($cmd).self::CRLF.$cmd.self::CRLF;

		return $command;
	}

	/**
	 * invalidate response
	 *
	 * @return void
	 * @author lovedev
	 */
	protected function invalidateResponse(){
		$this->response = fgets($this->connection);
		$response = array();

		switch ($this->response[0]){
			case '-':
				$this->throw_error('error: '.$this->response);
				return false;
			case '+':
				return substr($this->response, 1);
			case '$':
				if ($this->response=='$-1') return null;
				$read = 0;
				$size = intval(substr($this->response, 1));
				
				if ($size > 0){
					do{
						$block_size = min($size-$read, self::MAX_BLOCK_SIZE);
						if ($block_size < 1) break;

						$data = fread($this->connection, $block_size);
						
						if ($data === false){
							$this->throw_error('Can not read');
							return false;
						}

						$response[] = $data;
						$read += $block_size;
					} while($read < $size);
				}

				fread($this->connection, self::CRLF_LEN);
				break;
			case '*':
				$count = substr($this->response, 1);
				if ($count=='-1') 
					return null;
				
				for ($i = 0; $i < $count; $i++){
					$response[] = $this->invalidateResponse();
				}
				break;
			case ':':
				return intval(substr($this->response, 1));
				break;
			default:
				$this->throw_error('not support type : '.print_r($this->response, 1));
				return false;
		}

		return $response;
	}
	
	
	/**
	 * authentication
	 *
	 * @return void
	 * @author lovedev
	 */
	protected function doAuth(){
		if($this->passwd){
			$command = $this->getCommand(array(self::AUTH_COMMAND, $this->passwd));
			if(trim($this->sendQuery($command)) != self::AUTH_SUCCESS){
				$this->throw_error('Not Authentication');
				return false;
			}
		}
		return true;
	}
	
	/**
	 * send command to redis
	 *
	 * @param string $command 
	 * @return void
	 * @author lovedev
	 */
	private function sendQuery($command){
		fwrite($this->connection, $command);
		return $this->invalidateResponse();
	}

	/**
	 * close socket
	 *
	 * @return void
	 * @author changhun
	 */
	public function close(){
		$this->execute(self::QUIT_COMMAND);
		fclose($this->connection);
		$this->connection = null;
	}
	
	/**
	 * error report
	 *
	 * @param string $msg 
	 * @return void
	 * @author lovedev
	 */
	protected function throw_error($msg){
		trigger_error($msg, E_USER_WARNING);
	}
	
	/**
	 * destructor
	 *
	 * @return void
	 * @author lovedev
	 */
	public function __destruct(){
		$this->close();
		$this->response = null;
		$this->host = null;
		$this->port = null;
		$this->passwd = null;
		unset($this->host);
		unset($this->port);
		unset($this->passwd);
		unset($this->response);
		unset($this->connection);
	}	
}
?>
