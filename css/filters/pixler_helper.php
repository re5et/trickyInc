<?php

class pixler_helper extends trickyInc{
	
	var $pixler_path = '../images/pixler.php';
	
	function __construct(&$contents){
		if(strpos($contents, 'pixler:(')){
			preg_match_all('/pixler:\((.+)\)/', $contents, $matches);
			if(is_array($matches))
				foreach($matches[0] as $k => $v){
					$contents = str_replace($v, "background: url({$this->pixler_path}?{$matches[1][$k]})", $contents);
				}
		}
		else
			return;
	}
	

}
