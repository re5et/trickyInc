<?php

class background_img_helper extends trickyInc{
	
	var $img_path = '/assets/trickyInc/css/images/';
	
	function __construct(&$contents){
		if(strpos($contents, 'bg:(')){
			preg_match_all('/bg:\((.+)\)/', $contents, $matches);
			if(is_array($matches))
				foreach($matches[0] as $k => $v){
					$contents = str_replace($v, "background: url({$this->img_path}{$matches[1][$k]})", $contents);
				}
		}
		else
			return;
	}
	

}