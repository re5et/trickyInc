<?php

class trickyInc {
	
	function __construct($type = false){
		
		// gotta have this, 'css' or 'js'
		if(!$type)
			return;
		
		$this->type = $type;
		
		// setup the default options, if you want different defaults, change them here
		
		// include the browser styles reset before all other content (css only)
		$this->options->reset = true;
		
		// send the contents of each include through the filters in the filters directory
		$this->options->filter = true;
		
		// include comments regarding file inclusion
		$this->options->comments = true;

		// how many seconds to wait for a content refresh
		$this->options->cache = false;
		
		// default constants files to include, comma seperated without file extensions
		$this->options->constants = '';
		
		// default stylesheets to include, comma seperated without file extensions
		$this->options->inc = '';	
		
		// exclude any file containing any of these
		// applys to includes, filters, constants
		$this->options->exclude = array(
			'.tmp', '.svn', '.project', '.bak'
		);		
		
		// say thanks via a comment at the very bottom of your final output
		$this->options->plug = false;
		
		// disables overrides from the query string
		$this->allow_query_string_override = true;
		
		// check for option overrides in the query string
		if(!$this->allow_query_string_override)
			$this->set_options();
		
		// get the browser information if available
		$this->browser = $this->get_browser();
		
		// if trickyInc has been instantiated, and its not by an extension, do ouput
		if(__CLASS__ == get_class($this))
			$this->output();
		
	}
	
	function set_options(){
		
		// loop through the options, if any of them are in the query string override
		foreach($this->options as $k => $v){
			if(isset($_GET[$k]) && !is_array($this->options->{$k})){
				// this bit here makes sure that if you want something false it is
				$false = array('n', 'no', '0', 'false');
				if(in_array(strtolower($_GET[$k]), $false))
					$_GET[$k] = false;
				$this->options->{$k} = $_GET[$k];
			}
		}
		
		$this->options->constants = explode(',', $this->options->constants);
		$this->inc = explode(',', $this->options->inc);
		
		// check if caching is on, and cache dir can be written in
		if($this->options->cache && !is_writeable('cache/')){
			$this->open_comment('cannot write cache, check the permissions on that directory');
			// if not, turn caching off
			$this->options->cache = false;
		}
	}
	
	// calls individual methods to output stylesheet content
	function output(){
		
		$type = ($this->type == 'css') ? 'css' : 'javascript';
		// set the right header
		header("content-type:text/$type");
		
		// if caching is on, make a static file in the correct cache directory
		// based on the query string received.
		if($this->options->cache){
			
			$cache_key = md5($_SERVER['QUERY_STRING']);
			
			$file = 'cache/' . $cache_key;
			
			// if a cached file exists, serve it up, do nothing else
			if($this->file_check($file)){
				
				$headers = apache_request_headers();
				header("Cache-Control: max-age={$this->options->cache}, must-revalidate");
				
				// if the alotted time has not passed, throw up a not modified
				if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($file))) {
        			header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file)).' GMT', true, 304);
    			}
				// otherwise, serve it up
				else{
					header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file)).' GMT', true, 200);
        			header('Content-Length: '.filesize($file));
					echo file_get_contents($file);
				}
				
				return;
				
			}
			
		}
		
		// start obfuscation so that we can write to cache if needed
		ob_start();
		
		// if reset is true and this is css, include the reset
		if($this->options->reset && $this->type == 'css')
			$this->reset();
		
		// if constants are on, get the replacements ready
		if($this->options->constants)
			$this->constants();
		
		// if there are included files	
		if(is_array($this->inc))
			$this->includes();
			
		// if you wanna plug trickyInc
		if($this->options->plug)
			$this->plug();
		
		// store the final output in a variable, and output it
		$final_output = ob_get_flush();
		
		// if caching is on
		if($this->options->cache){
			
			// write it to file
			file_put_contents($file, $final_output);
			
		}
		
	}
	
	function get_browser(){
		// if capable, get browser information, mostly to be used in filters
		if(ini_get("browscap"))
    		return get_browser(null, true);
		else
			return false;
	}
	
	function reset(){
		
		$this->open_comment("css reset, via the king, http://meyerweb.com/eric/thoughts/2007/05/01/reset-reloaded/");
		echo 'html,body,div,span,applet,object,iframe,h1,h2,h3,h4,h5,h6,p,blockquote,pre,a,abbr,acronym,address,big,cite,code,del,dfn,em,font,img,ins,kbd,q,s,samp,small,strike,strong,sub,sup,tt,var,dl,dt,dd,ol,ul,li,fieldset,form,label,legend,table,caption,tbody,tfoot,thead,tr,th,td{margin:0;padding:0;border:0;outline:0;font-weight:inherit;font-style:inherit;font-size:100%;font-family:inherit;vertical-align:baseline}:focus{outline:0}body{line-height:1;color:black;background:white}ol,ul{list-style:none}table{border-collapse:separate;border-spacing:0}caption,th,td{text-align:left;font-weight:normal}blockquote:before,blockquote:after,q:before,q:after{content:""}blockquote,q{quotes:""""}' . "\n";
		$this->close_comment("end of css reset");
		
	}
	
	function constants(){
		
		foreach($this->options->constants as $constants){
			$file = 'constants/' . $constants . '.php';
			if($this->file_check($file)){
				require_once($file);
				foreach($constants as $k => $v)
					$this->constants[$k] = $v;
			}
		}
		
	}
	
	function includes(){
		
		// set the file extension for the includes based on type passed in
		$ext = ($this->type == 'css') ? '.css' : '.js';
		
		foreach($this->inc as $key => &$include){
			if(strpos($include, '*') !== FALSE && strpos($include, '..') === FALSE){
				if($add = $this->include_all($include))
					array_splice($this->inc, $key, 1, $add);
				else
					unset($this->inc[$key]);
			}
			elseif(!strpos($include, $ext))
				$include .= $ext;
		}
		
		// was causing problems
		unset($include);
		
		foreach($this->inc as $include){
			$file = 'includes/' . $include;
			if($this->file_check($file)){
				// get the file contents
				$contents = file_get_contents($file);
				
				// if constants are found in the include, replace appropriately
				if(property_exists($this, 'constants'))
					foreach($this->constants as $key => $value)
						$contents = str_replace('$'.$key, $value, $contents);
				
				// if filters is on, pass the contents
				if($this->options->filter)
					$this->filters($contents);
				
				$this->open_comment("included from $file");
				echo $contents;
				$this->close_comment("end of $file");
			}
		}
		
	}
	
	function include_all($path){
		
		$path = str_replace('*','',$path);
		
		$location = 'includes/' . $path;

		if(is_dir($location))
			if(is_array($dir_contents = scandir($location)))
				foreach($dir_contents as $file)
					if($this->file_check($location . $file)){
						$temp[] = $path . $file;
					}
					
		if(is_array($temp))
			return $temp;
		else
			return false;
			
	}
	
	// filters receives contents as a reference, otherwise it wouldn't do anything
	function filters(&$contents){
		
		if($dh = opendir('filters'))
			while(($file = readdir($dh)) !== false)
				if($this->file_check('filters/' . $file)){
					// grab the files name, which is the assumed name of the class
					$class = substr($file, 0, strrpos($file, '.'));
					require_once('filters/' . $file);
					// if the included file has the class we are looking for, instantiate it
					new $class($contents);
				}
		
	}
	
	// thank yous are always welcome
	function plug(){
		
		$this->open_comment("had some help from trickyInc: http://code.google.com/p/trickyinc/");
		
	}
	
	// a quick file check, hopefully to stop anything dangerous, and make sure the file really exists	
	function file_check($file){
		
		// stuff we definitely don't want to see in a file name
		$bad = array("../","./","<!--","-->","<",">","'",'"','&','$','#','{','}','[',']','=',';','?',"%20","%22","%3c","%253c","%3e","%0e","%28","%29","%2528","%26","%24","%3f","%3b","%3d");
		
		// if there are excludes, consider them "bad"
		if(is_array($this->options->exclude))
			$bad = array_merge($bad, $this->options->exclude);
		
		// get rid of any of the above
		$file = stripslashes(str_replace($bad, '', $file));
		
		// make sure the file is really a file still
		return is_file($file);
		
	}
	
	// opening comment, padded appropriately
	function open_comment($str){
		
		// check to see if comments are on
		if($this->options->comments)
			echo '/* ' . str_pad($str . " ", 77, '*') . "*/\n\n";
		
	}
	
	// closing comment, padded appropriately
	function close_comment($str){
		
		// check to see if comments are on
		if($this->options->comments)
			echo "\n\n/* " . str_pad($str . " ", 77, '*') . "*/\n\n\n\n";
		
	}
	
}

?>