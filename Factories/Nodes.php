<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsEdge\Factories;

class Nodes extends Base
{
	public function getServer($id, $ip, $port)
	{
		$rObj	= new \MTM\WsEdge\Models\Nodes\Server();
		$rObj->setConfiguration($id, $ip, $port);
		return $rObj;
	}
}