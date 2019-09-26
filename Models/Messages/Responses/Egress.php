<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsEdge\Models\Messages\Responses;

class Egress extends \MTM\WsEdge\Models\Messages\Base
{
	protected $_type="edge-egress-response";
	protected $_rxData=null;
	
	public function setFromObj($obj)
	{
		if ($obj->type == "edge-egress-response") {
			$this->setGuid($obj->guid);
			$this->setEvent($obj->event);
			if ($obj->error !== null) {
				$this->setError(new \Exception($obj->error->msg, $obj->error->code));
			}
			$this->setRxData(json_decode(base64_decode($obj->data, true)));
		
		} else {
			throw new \Exception("Not handled for type: " . $msgObj->type);
		}
		return $this;
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
}