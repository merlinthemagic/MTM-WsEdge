<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsEdge\Models\Connections;

abstract class Base
{
	protected $_guid=null;
	protected $_isTerm=false;
	protected $_role=null;
	protected $_parentObj=null;
	protected $_sockObj=null;
	
	//this attribute is controlled by the
	//user and provides a way to have i.e. 
	//access or auth data follow a connection
	protected $_userData=null;
	
	public function setGuid($guid)
	{
		$this->_guid	= $guid;
		return $this;
	}
	public function getGuid()
	{
		if ($this->_guid === null) {
			$this->_guid	= \MTM\Utilities\Factories::getGuids()->getV4()->get(false);
		}
		return $this->_guid;
	}
	public function setTerminated()
	{
		$this->_isTerm	= true;
		return $this;
	}
	public function getTerminated()
	{
		return $this->_isTerm;
	}
	public function setSocket($sockObj)
	{
		$this->_sockObj		= $sockObj;
		$this->_sockObj->setTerminationCb($this, "terminate");
		return $this;
	}
	public function getSocket()
	{
		return $this->_sockObj;
	}
	public function setParent($nodeObj)
	{
		$this->_parentObj		= $nodeObj;
		return $this;
	}
	public function getParent()
	{
		return $this->_parentObj;
	}
	public function getRole()
	{
		return $this->_role;
	}
	public function setRole($name)
	{
		$this->_role	= $name;
		return $this;
	}
	public function send($msgObj, $throw=true)
	{
		try {
			if ($this->getSocket()->getTermStatus() === false) {
				$this->getSocket()->sendMessage(json_encode($msgObj, JSON_PRETTY_PRINT));
			} else {
				throw new \Exception("Edge socket has been terminated: " . $this->getSocket()->getUuid());
			}
		} catch (\Exception $e) {
			$this->terminate();
			if ($throw === true) {
				throw $e;
			}
		}
		return $this;
	}
	public function setUserData($data)
	{
		//for end user exclusive use
		$this->_userData	= $data;
		return $this;
	}
	public function getUserData()
	{
		//for end user exclusive use
		return $this->_userData;
	}
	public function terminate()
	{
		//nothing yet, but child may not have method.. need interface
	}
}