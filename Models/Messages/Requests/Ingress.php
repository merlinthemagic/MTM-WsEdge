<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsEdge\Models\Messages\Requests;

class Ingress extends \MTM\WsEdge\Models\Messages\Base
{
	protected $_type="edge-ingress-request";
	protected $_rxData=null;
	protected $_txTime=null;
	protected $_rsvp=true;
	protected $_sockObj=null;
	
	public function setFromObj($obj)
	{
		if (
			$obj instanceof \stdClass === true
			&& property_exists($obj, "guid") === true
			&& property_exists($obj, "time") === true
			&& property_exists($obj, "type") === true
			&& property_exists($obj, "event") === true
			&& property_exists($obj, "rsvp") === true
			&& property_exists($obj, "data") === true
			&& $obj->type == "edge-egress-request"
		) {
			$decJson	= base64_decode($obj->data, true);
			if ($decJson !== false) {
				$dataObj	= json_decode($decJson);
				if (
					$dataObj instanceof \stdClass === true
					&& property_exists($dataObj, "payload") === true
				) {
					$this->setGuid($obj->guid);
					$this->setRxData($dataObj->payload);
					$this->setEvent($obj->event);
					$this->setRsvp($obj->rsvp);
					return $this;
				}
			}
		}
		throw new \Exception("Ingress message is invalid");
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
	public function setRsvp($bool)
	{
		$this->_rsvp	= $bool;
		return $this;
	}
	public function getRsvp()
	{
		return $this->_rsvp;
	}
	public function exec($throw=true)
	{
		if ($this->_txTime === null && $this->getRsvp() === true) {
			//respond to the edge-egress-request made by a client
			$stdObj					= new \stdClass();
			$stdObj->guid			= $this->getGuid();
			$stdObj->time			= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
			$stdObj->type			= "edge-egress-response";
			$stdObj->event			= $this->getEvent();
			$stdObj->error			= null;
			if (is_object($this->getError()) === true) {
				$stdObj->error			= new \stdClass();
				$stdObj->error->msg		= $this->getError()->getMessage();
				$stdObj->error->code	= $this->getError()->getCode();
			}
			$txData					= new \stdClass();
			$txData->payload		= $this->getTxData();
			
			//no reason to encode again, the peer send will wrap in json
			$stdObj->data			= base64_encode(json_encode($txData, JSON_PRETTY_PRINT));
			
			$this->getConnection()->send($stdObj, $throw);
			$this->_txTime			= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
		}
		return $this;
	}
}