<?php

class trickyImg{
	
	var $gradient_width		=	500;
	var $gradient_height	=	500;
	var $gradient_direction	=	'vertical';
	var $gradient_start		=	'FFF';
	var $gradient_end		=	'000';
	var $opacity			=	80;
	
	function __construct($type = false){

		if(!$type)
			$type = 'pixel';
		
		$this->{$type}();

		header("Content-type: image/png");
		imagepng($this->image);
		imagedestroy($this->image);
		
	}

	function pixel(){
		$this->image = imagecreatetruecolor(1, 1);
		imagesavealpha($this->image, true);
		$opacity = (int) (isset($_GET['o'])) ? $_GET['o'] : $this->opacity;
		
		$this->color = $this->color_prep();
		
		$opacity = (100 - $opacity) * 1.27;
		$allocated = imagecolorallocatealpha($this->image, $this->color['red'], $this->color['green'], $this->color['blue'], $opacity);
		imagefill($this->image, 0, 0, $allocated);
	}
	
	function gradient(){
		
		if(isset($_GET['d']))
			$this->gradient_direction = 'horizontal';
		if(isset($_GET['start']))
			$this->gradient_start = $_GET['start'];
		if(isset($_GET['end']))
			$this->gradient_end = $_GET['end'];
		if(isset($_GET['width']))
			$this->gradient_width = $_GET['width'];
		if(isset($_GET['height']))
			$this->gradient_height = $_GET['height'];			
				
		$this->image = imagecreate($this->gradient_width, $this->gradient_height) or die("Cannot Initialize new GD image stream");
		
		$start = $this->color_prep($this->gradient_start);
		$end = $this->color_prep($this->gradient_end);
		
		$this->{$this->gradient_direction}($start, $end);
		
	}
	
	function vertical($start, $end){
		
		for($y = 0; $y < $this->gradient_height; $y++) {
			foreach($start as $k => $v){
				$diff = $start[$k] - $end[$k];
				$new[$k] = $start[$k] - intval(($diff / $this->gradient_height) * $y);
			}

			$row_color = imagecolorresolve($this->image, $new['red'], $new['green'], $new['blue']);
			
			imagefilledrectangle($this->image, 0, $y, $this->gradient_width, $y, $row_color);
		}
	}
	
	function horizontal($start, $end){
				
		for($x = 0; $x < $this->gradient_width; $x++) {
			foreach($start as $k => $v){
				$diff = $start[$k] - $end[$k];
				$new[$k] = $start[$k] - intval(($diff / $this->gradient_width) * $x);
			};

			$col_color = imagecolorresolve($this->image, $new['red'], $new['green'], $new['blue']);
			
			imagefilledrectangle($this->image, $x, 0, $x, $this->gradient_height, $col_color);
		}
		
	}
	
	function color_prep($color = false){
		if($color){
			$colors = $this->convert_hex($color);
		}
		elseif(isset($_GET['color'])){
			$color = $_GET['color'];
			if(preg_match('/[a-f0-9]{6}|[a-f0-9]{3}/i', $color)){
				$colors = $this->convert_hex($color);
			}
		}
		else{
			$colors = array(
				'red'	=>	(int) (isset($_GET['r'])) ? $_GET['r'] : 0,
				'green'	=>	(int) (isset($_GET['g'])) ? $_GET['g'] : 0,
				'blue'	=>	(int) (isset($_GET['b'])) ? $_GET['b'] : 0
			);
		}
		
		return $colors;
	}
	
	function convert_hex($color){
		if(strlen($color) == 3)
			$color = $this->hex_short_to_long($color);
		return array(
			'red'	=>	hexdec(substr($color, 0, 2)),
			'green'	=>	hexdec(substr($color, 2, 2)),
			'blue'	=>	hexdec(substr($color, 4, 2))
		);
	}
	
	function hex_short_to_long($color){
		for($i = 0; $i < 3; $i++)
			$expanded[$i] = $color[$i] . $color[$i];
		return join('', $expanded);
	}
	
}

$type = $_GET['type'];
if($type != 'gradient')
	$type = 'pixel';

new trickyImg($type);

?>
