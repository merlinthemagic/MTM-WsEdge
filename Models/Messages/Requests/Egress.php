<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsEdge\Models\Messages\Requests;

class Egress extends \MTM\WsEdge\Models\Messages\Base
{
	protected $_type="edge-egress-request";
	protected $_timeout=60000; //0 means dont wait for a response
	protected $_txTime=null;
	protected $_rxData=null;
	
	public function getAge()
	{
		//returns in ms
		if ($this->_txTime !== null) {
			return ceil((\MTM\Utilities\Factories::getTime()->getMicroEpoch() - $this->_txTime) * 1000);
		} else {
			return 0;
		}
	}
	public function setTimeout($ms)
	{
		$this->_timeout	= $ms;
		return $this;
	}
	public function getTimeout()
	{
		return $this->_timeout;
	}
	public function setRxData($data)
	{
		$this->_rxData	= $data;
		return $this;
	}
	public function getRxData()
	{
		return $this->_rxData;
	}
	public function exec($throw=true)
	{
		if ($this->_txTime === null) {

			//egress requests are only done by clients, there is only one socket
			$stdObj				= new \stdClass();
			$stdObj->guid		= $this->getGuid();
			$stdObj->time		= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
			$stdObj->type		= "edge-egress-request";
			$stdObj->srcConn	= $this->getConnection()->getGuid();
			$stdObj->event		= $this->getEvent();
			$stdObj->rsvp		= false;
			if ($this->getTimeout() > 0) {
				$stdObj->rsvp		= true;
			}
			$stdObj->data		= base64_encode(json_encode($this->getTxData()));
			
			try {
				
				$this->getConnection()->send($stdObj);
				$this->_txTime			= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
				if ($this->getTimeout() > 0) {
					$this->getParent()->addPending($this);
				} else {
					$this->setDone();
				}
				
			} catch (\Exception $e) {
				$this->setError($e)->setDone();
				if ($throw === true) {
					throw $e;
				}
			}
		}
		return $this;
	}
	public function get($throw=true)
	{
		$this->exec();
		while(true) {
			if ($this->getIsDone() === false) {
				\MTM\Async\Factories::getServices()->getLoop()->runOnce();
			} elseif (is_object($this->getError()) === true && $throw === true) {
				throw $this->getError();
			} else {
				return $this->getRxData();
			}
		}
	}
}