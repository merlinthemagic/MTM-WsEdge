<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsEdge\Models\Messages;

class Base
{
	protected $_guid=null;
	protected $_event=null;
	protected $_txData=null;
	protected $_isDone=false;
	protected $_parentObj=null;
	protected $_connObj=null;
	protected $_error=null;
	
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
	public function setEvent($str)
	{
		$this->_event	= $str;
		return $this;
	}
	public function getEvent()
	{
		return $this->_event;
	}
	public function getType()
	{
		return $this->_type;
	}
	public function setTxData($data)
	{
		$this->_txData	= $data;
		return $this;
	}
	public function getTxData()
	{
		return $this->_txData;
	}
	public function setError($e)
	{
		$this->_error	= $e;
		return $this;
	}
	public function getError()
	{
		return $this->_error;
	}
	public function setDone()
	{
		$this->_isDone	= true;
		return $this;
	}
	public function getIsDone()
	{
		return $this->_isDone;
	}
	public function setParent($obj)
	{
		$this->_parentObj	= $obj;
		return $this;
	}
	public function getParent()
	{
		return $this->_parentObj;
	}
	public function setConnection($obj)
	{
		$this->_connObj	= $obj;
		return $this;
	}
	public function getConnection()
	{
		return $this->_connObj;
	}
}