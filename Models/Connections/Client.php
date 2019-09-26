<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsEdge\Models\Connections;

class Client extends Base
{
	protected $_notifyObjs=array();
	
	public function newRequest($data=null)
	{
		if ($this->_isTerm === false) {
			$rObj	= $this->getParent()->getRequest("transit");
			$rObj->setTxData($data)->setConnection($this);
			return $rObj;
		} else {
			throw new \Exception("Connection has been terminated");
		}
	}
	public function getNotifiers()
	{
		return $this->_notifyObjs;
	}
	public function addNotify($obj=null)
	{
		$nObj	= \MTM\Notify\Factories::getNotifications()->getSingle($this, $obj);
		$this->_notifyObjs[$nObj->getGuid()]	= $nObj;
		return $nObj;
	}
	public function removeNotifier($nObj)
	{
		if (array_key_exists($nObj->getGuid(), $this->_notifyObjs) === true) {
			unset($this->_notifyObjs[$nObj->getGuid()]);
		}
	}
	public function terminate()
	{
		if ($this->getTerminated() === false) {
			$this->setTerminated();
			foreach ($this->getNotifiers() as $notifyObj) {
				$notifyObj->newEvent("termination", false);
			}
			if (is_object($this->getParent()) === true) {
				$this->getParent()->removeConnection($this);
			}
			parent::terminate();
		}
	}
}