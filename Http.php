<?php
if(!defined("SHA")) die("Access denied!");
require_once 'dbc/dbc.php';
require_once 'Input.php';
require_once 'config.php';
class Http{ var $http_method; public $db; protected $route_url=[];
	public function Http(){ #set_error_handler('getError');
		$this->http_method = $_SERVER['REQUEST_METHOD'];
		try{
			$this->db = new dbc([
				'database_type' => DATABASE_TYPE,
				'database_name' => DATABASE,
				'server' => HOST,
				'username' => USERNAME,
				'password' => PASSWORD,
				'charset' => 'utf8'
			]);
		}catch(Exception $e){
			if($e){
				die(Http::json(["Database Connection failed"]));
			}
		}
		
		$this->input = new Input;
	}
	public function __call($name,$args){
		die('<p align="center">Error : '.$name.'() method is invalid');
	}
	public function routes($target=NULL,$callback=NULL){
		$pre = (filter_var($target, FILTER_SANITIZE_URL));
		if($pre!='' && is_array($callback)){ # multiple url calls
		 $splitter = explode("/",$pre); 
		 if(count($splitter)>=2){
			 $method = $splitter[0]; array_shift($splitter); $preUrl = '/'.implode("/",$splitter);
			 foreach($callback as $url=>$call_function){
				$subUrl = (filter_var($url, FILTER_SANITIZE_URL)); 				
				if($this->http_method == $method && $callback!=NULL) self::switchPage($preUrl.'/'.$subUrl,$call_function);
			 }
		 }else{
			 die($this->setHeader("500","Bad format of routes"));
		 }
		}else{ # It may string OR func($app){}
			$splitter = explode("/",$pre); 
			 if(count($splitter)>=2){
				 $method = $splitter[0]; array_shift($splitter); $preUrl = '/'.implode("/",$splitter);
				 if($this->http_method == $method && $callback!=NULL) self::switchPage($preUrl,$callback);
			 }
		}
		#die;
	}
	public function get($target=NULL,$callback=NULL){
		$argUrl = (filter_var($target, FILTER_SANITIZE_URL));
		if($this->http_method == 'GET' && $callback!=NULL) self::switchPage($argUrl,$callback);
	}
	public function post($target=NULL,$callback=NULL){
		$argUrl = (filter_var($target, FILTER_SANITIZE_URL));
		if($this->http_method == 'POST' && $callback!=NULL) self::switchPage($argUrl,$callback);
	}
	public function put($target=NULL,$callback=NULL){
		$argUrl = (filter_var($target, FILTER_SANITIZE_URL));
		if($this->http_method == 'PUT' && $callback!=NULL) self::switchPage($argUrl,$callback);
	}
	public function delete($target=NULL,$callback=NULL){
		$argUrl = (filter_var($target, FILTER_SANITIZE_URL));
		if($this->http_method == 'DELETE' && $callback!=NULL) self::switchPage($argUrl,$callback);
	}
	private function setHeader($status,$body=""){
		if($status!=""){
			header("HTTP/1.1 ".$status."");
			header("Content-Type: application/json");
			return json_encode(["status"=>$status,"body"=>$body]);
		}
	}
	private function response(){
		// set headder & status
		
	}
	public function file_save($file_path,$file_stream){
		if($file_path != "" && $file_stream != ""){
			$explode = explode(";base64,",$file_stream); # ;base64,
			$data	= explode("/",$explode[0]);
			$extension = $data[1];
			$output_file = $file_path.".".$extension;
			$ifp = fopen($output_file, "wb");	
			fwrite($ifp, base64_decode($explode[1])); 
			fclose($ifp);
		}
	}
	public function file_decode($file_stream){
		if($file_stream != ""){
			$explode = explode(";base64,",$file_stream); # ;base64,
			return base64_decode($explode[1]);
		}
	}
	public function body(){
		return json_decode(file_get_contents("php://input"));
	}
	public function json($content){
		// application/json
		return Http::setHeader("200",$content);
	}	
	private function error(){
		
	}
	private function getCurrentUri(){
		$basepath = implode('/', array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1)) . '/';
		$uri = substr($_SERVER['REQUEST_URI'], strlen($basepath));
		if (strstr($uri, '?')) $uri = substr($uri, 0, strpos($uri, '?'));
		$uri = '/' . trim($uri, '/');
		return $uri;
	}
	private function switchPage($argUrl,$callback){
		if(isset($this->route_url[$this->http_method]) && in_array($argUrl,$this->route_url[$this->http_method])){
			die($this->setHeader("500",'Duplicate URL called '.$argUrl.' '));
		}else{
			$this->route_url[$this->http_method][] = $argUrl;
			switch($argUrl){
				case self::getCurrentUri(): 
					if($callback!=NULL) $this->access = $callback;
				break;
				default:
				
			}
		}
	}
	
	public function run($sh=NULL){ 
		if(is_string($sh)){ $ext_file = EXT_PATH.$sh.'.php';
			if(file_exists($ext_file)) require_once $ext_file;
		}else if(is_array($sh)){
			foreach($sh as $sh_file){ $ext_file = EXT_PATH.$sh_file.'.php';
				if(file_exists($ext_file)) require_once $ext_file;
			}
		}
		switch($this->http_method){
			case ('GET' || 'POST' || 'PUT' || 'DELETE'):
				if(isset($this->route_url[$this->http_method]) && in_array(self::getCurrentUri(),$this->route_url[$this->http_method])){
				  if((isset($_SERVER['HTTP_'.SH_KEY]) && $_SERVER['HTTP_'.SH_KEY] == SH_VALUE) || SHA==FALSE){
						$call = $this->access;
						if(is_string($call)){ # Routes
							$splitC = explode('::',$call);
							if(count($splitC) == 2){ 
								$controller = $splitC[0]; $method = $splitC[1];
								if(file_exists(CONTROLLER_PATH.$controller.'.php')){
									require_once CONTROLLER_PATH.$controller.'.php';
									if (method_exists($controller,$method)){
										$obj = new $controller;
										$obj->$method();
										#call_user_func($call);
									}else{
										die($this->setHeader("500","Bad format of calling method"));
									}
								}else{
									die($this->setHeader("500","Bad format of calling controller"));
								}
							}else{
									die($this->setHeader("500","Bad format of calling controller"));
							}
						}else{ # Individual
							$call( new Http() );
						}
				  }else{
						die($this->setHeader("401","Unauthorized"));
					}
				}else{
						die($this->setHeader("400","Bad Request"));
				}
			break;
			default:
			
		}
	}
	public function model($model=NULL){
		if(file_exists(MODEL_PATH.$model.'.php')){
			require_once MODEL_PATH.$model.'.php';
			if(class_exists($model)) return new $model;
		}
	}
	public function view($file=NULL,$args=NULL){
			return self::html($file,$args);
	}
	public function html($file=NULL){ $args = func_get_args();
			if(count($args)>0 && $args[0]!=''){
				if(isset($args[1]) && $args[1]!=NULL){ 
					extract($args[1]);
				}
				$file = HTML_PATH.$args[0].'.php';
				if(file_exists($file)) require_once $file;
			} return new Http;
	}
	public function library($class=NULL,$object=true){ $args = func_get_args();
			if(count($args)>0 && $args[0]!=''){
				$file = LIBRARY_PATH.$args[0].'.php';
				if(file_exists($file)) require_once $file;
				if(class_exists($args[0]) && $object==true) return new $args[0];
			}
	}
	public function db(){
		return $this->db;
	}
}
function db(){
	$dbc = new Http();
	return $dbc->db;
}
function getError($number, $msg, $file, $line, $vars){
	   $error = debug_backtrace(); #var_dump($error);
	   $msg = '<pre><div style="margin:auto;"><p align="center">File : '.$error[0]['file'].'<br>';
	   $msg .= 'Line : '.$error[0]['line'].'<br>';
	   $msg .= 'Error : '.$error[0]['args'][1].'</div></p></pre>';
	   die($msg);
}
