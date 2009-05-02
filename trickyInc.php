<?php

class trickyInc {

	// type is css or js
	// options are overrides for instantiation
	function __construct($type = false, $options = false){

		// grab starting time for profiling
		$this->start_time = microtime(true);

		// gotta have this, 'css' or 'js'
		if($type != 'css' && $type != 'js'){
			$this->comment('trickyInc must be called with the type argument, either "css" or "js"');
			die;
		}

		$this->type = $type;

		// get includes path
		global $trickyInc_include_path;
		if(empty($trickyInc_include_path))
			$trickyInc_include_path = '../trickyInc-includes';
		if(is_dir($trickyInc_include_path))	
			$this->include_path = $trickyInc_include_path;
		else
			$this->include_path = false;

		// setup the default options, if you want different defaults, change them here

		// include the browser styles reset before all other content (css only)
		$this->options->reset = true;

		// send the contents of each include through the filters in the filters directory
		$this->options->filter = true;

		// include comments regarding file inclusion
		$this->options->comments = true;

		// how many seconds to wait for a content refresh
		// false / 0 turns caching off
		$this->options->cache = false;

		// default constants files to include, comma seperated without file extensions
		$this->options->constants = '';

		// default stylesheets to include, comma seperated without file extensions
		$this->options->includes = '';	

		// exclude any file containing any of these
		// applys to includes, filters, constants.
		// disable this with 'false'
		$this->options->exclude = array(
			'.tmp', '.svn', '.project', '.bak', 'READ_ME'
		);		

		// enables / disables output compression
		// at the moment this is only runs for css
		$this->options->compress_output = false;

		// enables / disables parsing of browser based conditionals
		$this->options->browser_conditions = true;

		// say thanks via a comment at the very bottom of your final output
		$this->options->plug = true;

		// enables / disables output of a comment at the bottom of final output
		// showing how long it took trickyInc to complete execution
		$this->options->show_generated_time = true;

		// enables / disables output of debug information at bottom of final output
		$this->options->debug = true;

		// disables ability to alter these options via the query string
		// disable this with false
		$this->disable_override = array(
			'comments', 'compress_output', 'excludes', 'debug'
		);

		// if options are passed in during instantiation
		// override option defaults defined above
		if(is_array($options)){
			foreach($options as $option_key => $option_value){
				$this->options->$option_key = $option_value;
			}
		}

		// get the query string options ready for use.
		$this->set_options();

		// grab some information about the browser
		if($this->options->browser_conditions)
			$this->get_browser();

		// if trickyInc has been instantiated, and its not by an extension, do ouput
		if(__CLASS__ === get_class($this))
			$this->output();
	}

	function set_options(){

		// loop through the options, if any of them are in the query string override
		foreach($this->options as $k => $v){
			// here for backwards compatibility
			$_GET['includes'] = $_GET['inc'];
			if(isset($_GET[$k]) && !is_array($this->options->{$k})){

				// if this option is in disable_override, don't set it
				if(is_array($this->disable_override))
					if(in_array($k, $this->disable_override))
						continue;

				// this bit here makes sure that if you want something false it is
				$false = array('n', 'no', '0', 'false');
				if(in_array(strtolower($_GET[$k]), $false))
					$_GET[$k] = false;
				$this->options->{$k} = $_GET[$k];
			}
		}
		
		// no trickyInc comments if we are compressing
		if($this->options->compress_output)
			$this->options->comments = false;

		// check if caching is on, and cache dir can be written in
		if($this->options->cache && !is_writeable('cache/')){
			$this->comment('cannot write cache, check the permissions on that directory');
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
		if($this->are_there('constants'))
			$this->constants();

		// if there are included files	
		if($this->are_there('includes'))
			$this->includes();

		// if you wanna plug trickyInc
		if($this->options->plug)
			$this->plug();

		// store the final output in a variable, and output it
		$final_output = ob_get_clean();
		
		if($this->options->compress_output)
			$final_output = $this->compress_output($final_output);

		// if caching is on
		if($this->are_there('cache')){

			// write it to file
			file_put_contents($file, $final_output);

		}
		
		// all done
		echo $final_output;

		// if debug is enabled, spit it out
		if($this->options->debug)
			$this->debug();

		// we are done at this point, so show how long
		if($this->options->show_generated_time)
			$this->show_generated_time();;

	}

	function are_there($option){
		return ($this->options->{$option} && is_dir($option));
	}

	function get_browser(){

		$file = $this->include_path . '/browscap.php';
		if($this->file_check($file)){
			require_once($file);
			$bc = new Browscap($this->include_path . '/browscap-cache');
			$this->browser = $bc->getBrowser();
		}

	}

	function reset(){

		$this->comment("css reset, via the king, http://meyerweb.com/eric/thoughts/2007/05/01/reset-reloaded/");
		echo 'html,body,div,span,applet,object,iframe,h1,h2,h3,h4,h5,h6,p,blockquote,pre,a,abbr,acronym,address,big,cite,code,del,dfn,em,font,img,ins,kbd,q,s,samp,small,strike,strong,sub,sup,tt,var,dl,dt,dd,ol,ul,li,fieldset,form,label,legend,table,caption,tbody,tfoot,thead,tr,th,td{margin:0;padding:0;border:0;outline:0;font-weight:inherit;font-style:inherit;font-size:100%;font-family:inherit;vertical-align:baseline}:focus{outline:0}body{line-height:1;color:black;background:white}ol,ul{list-style:none}table{border-collapse:separate;border-spacing:0}caption,th,td{text-align:left;font-weight:normal}blockquote:before,blockquote:after,q:before,q:after{content:""}blockquote,q{quotes:""""}';
		$this->comment("end of css reset");

	}

	function constants(){

		$constants = explode(',', $this->options->constants);
		foreach($constants as $to_include){
			$file = 'constants/' . $to_include . '.php';
			if($this->file_check($file)){
				require_once($file);
				foreach($constants as $k => $v)
					$this->constants[$k] = $v;
			}
		}

	}

	function includes(){

		$includes = explode(',', $this->options->includes);

		// set the file extension for the includes based on type passed in
		$ext = ($this->type == 'css') ? '.css' : '.js';

		foreach($includes as $key => &$include){
			if(strpos($include, '*') !== FALSE && strpos($include, '..') === FALSE){
				if($add = $this->include_all($include))
					array_splice($includes, $key, 1, $add);
				else
					unset($includes[$key]);
			}
			elseif(!strpos($include, $ext))
				$include .= $ext;
		}

		// was causing problems
		unset($include);

		foreach($includes as $include){
			$file = 'includes/' . $include;
			if($this->file_check($file)){
				// get the file contents
				$contents = file_get_contents($file);

				// if constants are found in the include, replace appropriately
				if(property_exists($this, 'constants'))
					foreach($this->constants as $key => $value)
						$contents = str_replace('$'.$key, $value, $contents);

				// if filters is on, pass the contents
				if($this->are_there('filters'))
					$this->filters($contents);

				// if browser conditionals are on, parse them out
				if($this->options->browser_conditions)
					$contents = $this->browser_conditionals($contents);

				$this->comment("included from $file");
				echo $contents;
				$this->comment("end of $file");
			}
		}

	}

	// includes everything at the path provided, is non-recursive
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

	// filters receives contents as a reference so it can change them as needed
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
	
	// check for and handle browser conditionals
	function browser_conditionals($contents){

		if(strpos($contents, 'bc(') === FALSE)
			return $contents;

		preg_match_all('/bc\(\s*([^)]+)\s*,\n?([^)]+)\s*\)\n?/', $contents, $matches);

		if(is_array($matches[1]))
			for($i = 0; $i < count($matches[1]); $i++){
				$pairs = explode('&', trim(strtolower($matches[1][$i])));
				foreach($pairs as $pair){
					if(strpos($pair, '!=') !== FALSE){
						list($key,$value) = explode('!=', $pair);
						$negative_conditions[$key] = $value;
					}
					else{
						list($key,$value) = explode('=', $pair);
						$positive_conditions[$key] = $value;
					}
				}

				$include_it = true;

				if(is_array($positive_conditions))
					foreach($positive_conditions as $condition => $value){
						if($this->browser->{$condition} != $value)
							$include_it = false;
					}

				if(is_array($negative_conditions))
					foreach($negative_conditions as $condition => $value){
						if($this->browser->{$condition} == $value)
							$include_it = false;
					}

				if($include_it){

					$contents = str_ireplace($matches[0][$i], $matches[2][$i], $contents);

				}
				else{

					$contents = str_ireplace($matches[0][$i], '', $contents);

				}

			}

		return $contents;

	}	
	
	function compress_output($output){
		if($this->include_path){
			$min = $this->type . 'min';
			if($this->file_check($this->include_path . "/$min.php")){
				require_once($this->include_path . "/$min.php");
				$minifier = new $min($output);
				return $minifier->minify($output);
			}
		}

	}

	// thank yous are always welcome
	function plug(){

		$this->comment("had some help from trickyInc: http://code.google.com/p/trickyinc/");

	}

	// a utility file check for all included files
	// hopefully to stop anything dangerous, and make sure the file really exists	
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

	function show_generated_time(){
		$current_time = microtime(true);

		$seconds_passed = $current_time - $this->start_time;

		$this->comment("Generated in $seconds_passed seconds.");
	}

	// comment, padded appropriately
	function comment($str){

		// check to see if comments are on
		if($this->options->comments)
			echo "\n/* " . str_pad($str . " ", 77, '*') . "*/\n";

	}

	// this can be used to grab get information from the 
	// query string of the page including trickyInc
	function get($key){

		$q_string = explode('?', $_SERVER['HTTP_REFERER']);
		parse_str($q_string[1], $get);

		if(array_key_exists($key, $get)){
			if($get[$key] || $get[$key] === 0)
				return $get[$key];
		}
		return false;

	}

	// dumps out debug information
	function debug(){

		$trickyInc = get_object_vars($this);
		$this->comment('trickyInc debug');
		echo "/*\n\n";
		var_dump($trickyInc);
		echo "*/\n\n";
		$this->comment('end of debug');

	}
	
}

?>