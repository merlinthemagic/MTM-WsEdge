<?php
//© 2019 Martin Peter Madsen
namespace MTM\WsEdge;

class Factories
{
	private static $_cStore=array();
	
	//USE: $aFact		= \MTM\WsEdge\Factories::$METHOD_NAME();
	
	public static function getNodes()
	{
		if (array_key_exists(__FUNCTION__, self::$_cStore) === false) {
			self::$_cStore[__FUNCTION__]	= new \MTM\WsEdge\Factories\Nodes();
		}
		return self::$_cStore[__FUNCTION__];
	}
}