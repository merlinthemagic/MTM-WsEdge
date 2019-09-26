<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsEdge\Models\Nodes;

class Base
{
	protected $_id=null;
	protected $_guid=null;
	protected $_sockIp=null;
	protected $_sockPort=null;
	protected $_isInit=false;
	protected $_isTerm=false;
	protected $_initTime=null;
	protected $_sockObj=null;
	protected $_aSyncObj=null;
	protected $_ingressCb=null;
	protected $_errorCb=null;

	public function setConfiguration($id, $ip, $port)
	{
		$id	= trim($id);
		if (is_string($id) === false || strlen($id) < 1) {
			throw new \Exception("Invalid Id");
		}
		if (is_object($ip) === false) {
			$ip   		= \MTM\Network\Factories::getIp()->getIpFromString($ip);
		}
		$this->_id			= $id;
		$this->_guid		= \MTM\Utilities\Factories::getGuids()->getV4()->get(false);
		$this->_sockIp		= $ip;
		$this->_sockPort	= intval($port);
		return $this;
	}
	public function getId()
	{
		return $this->_id;
	}
	public function getGuid()
	{
		return $this->_guid;
	}
	public function getIp()
	{
		return $this->_sockIp;
	}
	public function getPort()
	{
		return $this->_sockPort;
	}
	public function isRunning()
	{
		return \MTM\WsSocket\Factories::getSockets()->getApi()->testConnect($this->getIp()->getAsString("std", false), $this->getPort(), "tcp", 1000);
	}
	public function getSocket()
	{
		return $this->_sockObj;
	}
	public function setIngressCb($obj=null, $method=null)
	{
		if (is_object($obj) === true && is_string($method) === true) {
			//set ingress call back, any client sending us a message will
			//have that message passed to this function
			$this->_ingressCb	= array($obj, $method);
		}
		return $this;
	}
	public function setErrorCb($obj=null, $method=null)
	{
		if (is_object($obj) === true && is_string($method) === true) {
			//set error call back, any uncaught exception will be sent here
			$this->_errorCb	= array($obj, $method);
		}
		return $this;
	}
	public function getAsync()
	{
		if ($this->_aSyncObj === null) {
			$loopObj			= \MTM\Async\Factories::getServices()->getLoop();
			$this->_aSyncObj	= $loopObj->getSubscription()->setCallback($this, "ingressLoop");
		}
		return $this->_aSyncObj;
	}
	protected function callIngress($reqObj)
	{
		try {
			call_user_func_array($this->_ingressCb, array($reqObj));
		} catch (\Exception $e) {
			$this->callError($e);
		}
	}
	protected function callError($e)
	{
		if ($this->_errorCb !== null) {
			try {
				call_user_func_array($this->_errorCb, array($e));
			} catch (\Exception $e) {
			}
		}
	}
	//commands
	public function getIngress($msgObj=null)
	{
		$reqObj	= new \MTM\WsEdge\Models\Messages\Requests\Ingress();
		$reqObj->setParent($this);
		if ($msgObj !== null) {
			$reqObj->setFromObj($msgObj);
		}
		return $reqObj;
	}
	public function getException($e=null)
	{
		$reqObj	= new \MTM\WsEdge\Models\Messages\Errors\Exception();
		$reqObj->setParent($this)->setEvent("error")->setError($e);
		return $reqObj;
	}
}