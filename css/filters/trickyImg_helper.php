<?php

class trickyImg_helper extends trickyInc{
	
	var $trickyImg_path = '../images/trickyImg.php';
	
	function __construct(&$contents){
		if(strpos($contents, 'gradient:(') || strpos($contents, 'pixel:(')){
			preg_match_all('/(gradient|pixel):\((.+)\)/', $contents, $matches);
			if(is_array($matches))
				foreach($matches[0] as $k => $v){
					$contents = str_replace($v, "background: url({$this->trickyImg_path}?type={$matches[1][$k]}&{$matches[2][$k]})", $contents);
				}
		}
		else
			return;
	}
	

}