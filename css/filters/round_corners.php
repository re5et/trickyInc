<?php

class round_corners extends trickyInc{
	
	function __construct(&$contents){
		if(strpos($contents, 'round-corners')){
			$round = (strpos($_SERVER['HTTP_USER_AGENT'], 'Firefox') ? '-moz-border-radius' : (strpos($_SERVER['HTTP_USER_AGENT'], 'WebKit') ? '-webkit-border-radius' : false));
			if($round)
				$contents = str_replace('round-corners', $round, $contents);
			else
				$contents = preg_replace('/\sround-corners.+\n/', '', $contents);
		}
	}
	

}