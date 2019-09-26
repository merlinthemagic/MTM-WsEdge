<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsEdge\Models\Nodes;

class Server extends Base
{
	protected $_sslCertObj=null;
	protected $_regCb=null;
	protected $_connObjs=array();
	protected $_penMsgs=array();
	protected $_reqObjs=array();
	protected $_hbMax=30;

	public function setSsl($certObj)
	{
		$this->_sslCertObj			= $certObj;
		return $this;
	}
	public function start(
			$regObj=null, $regMethod=null,
			$inObj=null, $inMethod=null,
			$errObj=null, $errMethod=null
	) {
		$this->setRegistrationCb($regObj, $regMethod);
		$this->setIngressCb($inObj, $inMethod);
		$this->setErrorCb($errObj, $errMethod);
		$this->init();
		$this->getAsync();
		return $this;
	}
	public function setRegistrationCb($obj=null, $method=null)
	{
		if (is_object($obj) === true && is_string($method) === true) {
			//set registration call back, any new client trying to register will
			//have to pass though this method. Can be used to authenticate
			$this->_regCb	= array($obj, $method);
		}
		return $this;
	}
	protected function init()
	{
		if ($this->_isInit === false) {
			
			if ($this->isRunning() === true) {
				throw new \Exception("Port is in use");
			} elseif ($this->_ingressCb === null) {
				throw new \Exception("Must set Ingress call back");
			} elseif ($this->_errorCb === null) {
				throw new \Exception("Must set Error call back");
			}
			$socket				= \MTM\WsSocket\Factories::getSockets()->getNewServer();
			if (is_object($this->_sslCertObj) === true) {
				$socket->setConnection("tls", $this->getIp()->getAsString("std", false), $this->getPort());
				$socket->setSslConnection($this->_sslCertObj);
			} else {
				$socket->setConnection("tcp", $this->getIp()->getAsString("std", false), $this->getPort());
			}
			//reading or writing a message should never take longer than this
			$socket->setClientDefaultMaxReadTime(1000);
			$socket->setClientDefaultMaxWriteTime(1000);
			
			$this->_isInit		= true;
			$this->_initTime	= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
			$this->_sockObj		= $socket;
		}
		return $this;
	}
	public function ingressLoop($subObj)
	{
		if ($this->_isTerm === false) {

			$cTime	= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
			foreach ($this->getSocket()->getClients() as $cObj) {

				try {
					
					$msgs	= $cObj->getMessages();
					if (count ($msgs) > 0) {
						foreach ($msgs as $msg) {
							$this->_penMsgs[]	= array($cObj, $msg);
						}
					} else {
						if (($cTime - $cObj->getLastReceivedTime()) > $this->_hbMax) {
							//have not heard from this client in awhile, send a heartbeat
							if ($cObj->getIsConnected() === true) {
								$cObj->ping("hb-" . $cTime);
							} else {
								throw new \Exception("Socket: " . $cObj->getUuid() . " has disconnected");
							}
						}
					}

				} catch (\Exception $e) {
					//failed send heartbeat, or user exception that floated, get rid of this client
					if ($cObj->getTermStatus() === false) {
						$this->getException($e)->exec($cObj, false);
					}
					$this->removeConnectionBySocket($cObj);
				}
			}
			
			$msgs	= &$this->getPendingIngress();
			foreach ($msgs as $mId => $iMsg) {
				unset($msgs[$mId]);
				$this->handleMessage($iMsg[0], $iMsg[1]);
			}
			
			$reqObjs	= &$this->getPending();
			foreach ($reqObjs as $guid => $reqObj) {
				if ($reqObj->getIsDone() === true) {
					unset($reqObjs[$guid]);
				} elseif ($reqObj->getAge() > $reqObj->getTimeout()) {
					unset($reqObjs[$guid]);
					$reqObj->setError(new \Exception("Timeout"));
					$reqObj->setDone();
				}
			}

		} else {
			$this->terminate();
		}
	}
	protected function handleMessage($cObj, $msg)
	{
		$msgObj   	= @json_decode($msg);
		if (is_object($msgObj) === true) {
			if ($msgObj->event == "transit") {
				$connObj	= $this->getConnectionFromSocket($cObj, true);
				if ($msgObj->type == "edge-egress-request") {
					$reqObj		= $this->getIngress($msgObj);
					$reqObj->setConnection($connObj);
					$this->callIngress($reqObj);
				} elseif ($msgObj->type == "edge-egress-response") {
					
					$respObj	= $this->getResponse($msgObj);
					if (array_key_exists($respObj->getGuid(), $this->_reqObjs) === true) {
						$reqObj		= $this->_reqObjs[$respObj->getGuid()];
						if (is_object($respObj->getError()) === true) {
							$reqObj->setError($respObj->getError());
						}
						$reqObj->setRxData($respObj->getRxData())->setDone();
					} else {
						//request obj must have timed out
						//discard this response
					}
					
				} else {
					throw new \Exception("Not handled for type: " . $msgObj->type);
				}

			} elseif ($msgObj->event == "registration") {
				$reqObj		= $this->getIngress($msgObj);
				$this->register($reqObj, $cObj);
			} else {
				throw new \Exception("Not handled for event: " . $msgObj->event);
			}
			
		} elseif (strpos($msg, "hb-") === 0) {
			//do nothing
		} elseif ($msg == "GoodByeServer" || $msg == "") {
			//firefox seems to send a close without any data on refresh of the page
			$this->removeConnectionBySocket($cObj);
		} else {
			throw new \Exception("ED Server invalid message: " . $msg);
		}
	}
	protected function register($reqObj, $cObj)
	{
		$connObj	= $this->getConnectionFromSocket($cObj, false);
		if (is_object($connObj) === false) {

			//do not add the conn before its validated
			//by setting up the conn we allow the user
			//access to the conn guid that allows more efficient
			//authentication, since the client does not control that guid
			$connObj	= new \MTM\WsEdge\Models\Connections\Client();
			$connObj->setSocket($cObj)->setRole("edge-client");
			$connObj->setParent($this);
			$reqObj->setConnection($connObj);
			//throws on access denied, 
			//there is no requirement as to the structure of the data
			$this->callRegistration($reqObj);
			
			$txObj			= new \stdClass();
			$txObj->guid	= $connObj->getGuid();
			$reqObj->setTxData($txObj)->exec();
			$this->addConnection($connObj);

		} else {
			$reqObj->setError(new \Exception("Already registered"))->exec();
		}
	}
	public function addPending($reqObj)
	{
		$this->_reqObjs[$reqObj->getGuid()]	= $reqObj;
		return $this;
	}
	public function &getPending()
	{
		return $this->_reqObjs;
	}
	public function &getPendingIngress()
	{
		return $this->_penMsgs;
	}
	protected function addConnection($connObj)
	{
		$connObj->setParent($this);
		$this->_connObjs[$connObj->getSocket()->getUuid()]	= $connObj;
		return $this;
	}
	public function getConnections()
	{
		return array_values($this->_connObjs);
	}
	public function getConnectionFromGuid($guid, $throw=true)
	{
		foreach ($this->getConnections() as $connObj) {
			if ($connObj->getGuid() == $guid) {
				return $connObj;
			}
		}
		if ($throw === true) {
			throw new \Exception("Connection is not registered");
		} else {
			return null;
		}
	}
	protected function getConnectionFromSocket($cObj, $throw=true)
	{
		if (array_key_exists($cObj->getUuid(), $this->_connObjs) === true) {
			return $this->_connObjs[$cObj->getUuid()];
		} else if ($throw === true) {
			throw new \Exception("Connection is not registered");
		} else {
			return null;
		}
	}
	protected function removeConnectionBySocket($cObj)
	{
		if (array_key_exists($cObj->getUuid(), $this->_connObjs) === true) {
			unset($this->_connObjs[$cObj->getUuid()]);
			$cObj->terminate(false);
		}
	}
	public function removeConnection($connObj)
	{
		$cObj	= $connObj->getSocket();
		$this->removeConnectionBySocket($cObj);
	}
	public function terminate()
	{
		parent::terminate();
	}
	protected function callRegistration($reqObj)
	{
		$isValid	= call_user_func_array($this->_regCb, array($reqObj));
		if ($isValid === true) {
			return true;
		} else {
			//every other outcome results in access denied
			throw new \Exception("Registration denied");
		}
	}
	
	//commands
	public function getRequest($event=null)
	{
		$reqObj	= new \MTM\WsEdge\Models\Messages\Requests\Egress();
		$reqObj->setParent($this)->setEvent($event);
		return $reqObj;
	}
	public function getResponse($msgObj=null, $connObj=null)
	{
		$reqObj	= new \MTM\WsEdge\Models\Messages\Responses\Egress();
		$reqObj->setParent($this)->setFromObj($msgObj, $connObj);
		return $reqObj;
	}
}