<?php

	// PHP Version check
	if (version_compare(PHP_VERSION, '7.0.0', '<')) die('Combine requires PHP 7.0.0 or higher!');

	// Green to Go!
	define("COMBINE_VERSION", "1.0");

	// Combine Class
	abstract class Combine {

		// Static class
		// ------------------------------------------------

		private function __construct() {}
		private function __clone() {}
		private function __wakeup() {}

		// Helpers
		// ------------------------------------------------

		// This functions takes a URL string and returns a snake-cased string
		public static function snake(string $str, string $c = "/"): string {
			return str_replace($c, "_", strtolower(trim($str, "/\\ ")));
		}

		// This functions takes a URL string and returns a camel-cased string
		public static function camel(string $str, string $c = "/"): string {
			return lcfirst(self::pascal($str, $c));
		}

		// This functions takes a URL string and returns a snake-cased string
		public static function pascal(string $str, string $c = "/"): string {
			return str_replace($c, "", ucwords(strtolower(trim($str, "/\\ ")), $c));
		}

		// This determines if the given var is an associative array
		public static function is_assoc($var): bool {
			return (is_array($var) && (array_values($var) !== $var));
		}

		// This will sanitize a url
		public static function str_url(string $url): string {
			return preg_replace('#/+#', "/", trim(str_replace("\\", "/", str_replace("..", "", $url)), "/ "));
		}

		// This determines if the given var is a lambda expression 
		public static function is_lambda(&$var): bool {
			return ($var instanceof Closure);
		}

		// This will load a PHP file
		private static function php($path, $once = true) {
			if (!file_exists($path)) self::error("Loader error", "File not found - ".$path);
			else {
				if ($once) require_once $path;
				else require $path;
			}
		}

		// This will load all PHP files inside a directory
		public static function require_dir(string $dir, $once = false) {
			if (!file_exists($dir)) self::error("Loader error", "Directory not found - ".$dir);
			
			foreach (glob($dir."/*.php") as $filename) {
				if ($once) require_once $filename;
				else require $filename;
			}
		}

		// This will load all PHP files inside a directory once
		public static function require_dir_once(string $dir) {
			self::require_dir($dir, true);
		}

		// Interpolator sigil and delimiter
		const INTERPOLATOR_SIGIL = "{{";
		const INTERPOLATOR_DELIM = "}}";

		// This takes in a string and replaces the variable templates with values from data store
		public static function interpolate(string $str, array $params, bool $strict = false): string {
			$str_new = "";
			$pos_curr = 0;

			$len_sigil = strlen(self::INTERPOLATOR_SIGIL);
			$len_delim = strlen(self::INTERPOLATOR_DELIM);

			while($pos_curr < strlen($str) - 1) {

				// Get start
				$pos_start = strpos($str, self::INTERPOLATOR_SIGIL, $pos_curr);
				if ($pos_start === false) {
					$str_new .= substr($str, $pos_curr);
					break;
				}

				// Copy non-template
				if ($pos_start - $pos_curr > 0) $str_new .= substr($str, $pos_curr, $pos_start -  $pos_curr);

				// Get end
				$pos_end = strpos($str, self::INTERPOLATOR_DELIM, $pos_start + $len_sigil);
				if ($pos_end === false) self::error("Interpolation error", "Sigil start (at index ".$pos_start.") without a corresponding end");

				// Extract expression and parse var and filters
				$exp = trim(substr($str, $pos_start + $len_sigil, $pos_end - $pos_start - $len_sigil));
				$exparr = explode("|", $exp);
				$var = trim($exparr[0]);

				// Get variable value
				$val = "";
				if (isset($params[$var])) $val = $params[$var];
				else if($strict) self::error("Interpolation error", "Missing value for template '".$var."'");

				// Apply each filter to value
				for ($i = 1; $i < count($exparr); $i++) {
					$filter = trim($exparr[$i]);
					$val = self::{$filter}($val);
				}

				// Attach filtered value
				$str_new .= $val;

				// Advance
				$pos_curr = $pos_end + $len_delim;				
			}

			// All done
			return $str_new;
		}

		// Combine operation mode
		public static $production = false;

		// Index.php location
		public static $url_local = "";
		public static $url_web = "";

		// Lazy Loaders
		// ------------------------------------------------

		// Component registry
		public static $components;

		// Default component loading logic
		const LOAD_FILE = 1;
		const LOAD_DIR = 2;
		const LOAD_ONCE = 4;	
		const LOAD_NAMESPACE = 8; // Not implemented !

		// This function will define a component and its directory loading logic
		public static function component(string $component_type, $path = null, $logic = self::LOAD_DIR | self::LOAD_ONCE) {
			self::$components[$component_type] = [
				"path" => isset($path) ? trim($path, "\\/ ") : $component_type,
				"logic" => &$logic
			];			
		}	

		// This will load the given component
		public static function load(string $component_type, string $component_name, $buffer = false) {
			if (!isset(self::$components[$component_type])) self::error("logic error","Unknown component type - ".$component_type." (".$component_name.")");

			else {
				if ($buffer) ob_start();
				$component = self::$components[$component_type];

				// Lambda
				if (self::is_lambda($component["logic"])) $component["logic"]($component_name, $component["dir"], $component["once"]);

				// Handler
				else if (!is_int($component["logic"])) i::{$component["logic"]}($component_name, $component["dir"], $component["once"]);

				// Pre-defined loader
				else {

					// Prep	
					$once = ($component["logic"] & self::LOAD_ONCE);
					$path = self::interpolate($component["path"], [
						"component_type" => $component_type, 
						"component_name" => $component_name
					], true);
					
					// Check interpolation
					$flag = false;
					if ($path == $component["path"]) $flag = true;

					// Apply loading logic
					if ($component["logic"] & self::LOAD_FILE) {
						if ($flag) self::php($component["path"]."/".$component_name.".php", $once);
						else self::php($path.".php", $once);
					}

					else if ($component["logic"] & self::LOAD_DIR) {
						if ($flag) self::require_dir($component["path"]."/".$component_name, $once);
						else self::require_dir($path, $once);
					}

					else self::error("Loader error", "Unknown loading logic for component ".$component_type);		
				}

				// Flush buffer
				if ($buffer) return ob_end_flush();
			}
		}

		// Class registry
		public static $autoloads = [];
		private static $autoload_registered = false;

		// This function defines an class autoloading route
		public static function classify(string $class, string $path) {
			
			// Register autoloader
			if (!self::$autoload_registered) {
				spl_autoload_register("Combine::autoload");
				self::$autoload_registered = true;
			}

			// Remove preceding slash if any		
			if ($class[0] == "\\") $class = substr($class, 1);			

			// Parse & Prep
			$segments = explode("\\", $class);
			$depth = 0;
			$vars = [];
			$level = &self::$autoloads;
			$is_var_named = false;

			// Traverse down, creating the tree
			while($depth < count($segments)) {

				// Get next segment
				$segment = $segments[$depth];

				// Parse if segment variable available
				if ($segment[0] == self::EXTRACTOR_SIGIL) {
					$var = "";

					// Is named var ?								
					if (strlen($segment) > 1) {
						$is_var_named = true;
						$var = substr($segment, 1);

						// Check already used segment variable
						if (in_array($var, $vars)) self::error("Autoloader error", "Namespace segment variable (".$var.") used more than once in class ".$class);
					}						
					
					// Add to var to segment variables array, mark this segment as variadic
					$vars[] = $var;
					$segment = "*";						
				}

				// Create segment at this depth if it doesnt exist
				if (!isset($level[$segment])) $level[$segment] = ["\\" => []];
				
				// Proceed to next segment		
				$level = &$level[$segment];		
				if ($depth < count($segments) - 1) $level = &$level["\\"];
				$depth ++;
			}

			// At correct depth now - Add the path and vars
			$level["path"] = $path;
			$level["vars"] = $is_var_named ? $vars : [];
		}

		// This is used as an autoloading logic to match the class routing tree
		public static function autoload(string $class) {

			// Remove preceding slash if any		
			if ($class[0] == "\\") $class = substr($class, 1);			

			// Parse & Prep
			$segments = explode("\\", $class);
			$depth = 0;
			$vars = [];
			$vals = [];
			$level = &self::$autoloads;
			$level_namespace = null;
			$is_trail_var = false;

			// Presets
			$presets = [ '%tail%' => '' ];

			// Helper for dependency injection
			$inject = function(&$vars, &$vals, &$presets, $trail = null) {
				
				// Prep
				$params_interpolate = [];

				// Segment Vars - Do we have any values ?
				if (count($vals) > 0) {

					// Trail Url						
					if (isset($trail)) $vals[count($vals) - 1] .= (strlen($vals[count($vals) - 1]) > 0 ? "/" : "").$trail;

					// Unnamed parameterization
					if (count($vars) == 0) $params_interpolate = $vals;

					// Named parameterization
					else {
						$mixes = 0;
						for($i = 0; $i < count($vars); $i++) {							
							if ($vars[$i] == "") {
								$params_interpolate[$mixes] = $vals[$i];
								$mixes ++;
							}
							else $params_interpolate[$vars[$i]] = $vals[$i];
						}
					}
				}

				// Add presets
				$params_interpolate += $presets;
				
				// Done
				return $params_interpolate;
			};

			// Helper to Autoload
			$load = function(&$level_namespace) use (&$class, &$segments, &$depth, &$vals, &$presets, &$is_trail_var, &$inject) {
				try {

					$trail = ($is_trail_var && ($depth > 0)) ? implode("/", array_slice($segments, $depth)) : null;
					$_presets = $presets;
					$_presets["%tail%"] = $trail ?? '';
					$params_interpolate = $inject($level_namespace["vars"], $vals, $_presets, $trail);
					$path = self::interpolate($level_namespace["path"], $params_interpolate, true);

				}
				catch (Exception $e) {
					if (!self::$production) self::log("Autoloader warning", "Could not interpolate path for ".$class." - '".($e->getMessage())."'");
					return;
				}

				$path = self::str_url($path).'.php';
				if (!file_exists($path)) {
					if (!self::$production) self::log("Autoloader warning", "File ".$path." not found for autoloading ".$class);
				}
				else require_once $path;
			};

			// Traverse down			
			while($depth < count($segments)) {

				// Get next segment
				$segment = $segments[$depth];
				$traverse = false;					
					
				// Variable avail
				if (!isset($level[$segment])) {
					if (isset($level["*"])) {
						$traverse = true;
						$is_trail_var = true;

						$vals[] = $segment;
						$segment = "*";
					}
				}

				// Exact match
				else {
					$traverse = true;
					$is_trail_var = false;
				}

				// Is traversal possible
				if ($traverse) {
					$level = &$level[$segment];
					$level_namespace = $level;

					// Did we run out of more segments
					if ($depth == count($segments) - 1) {
						if (isset($level["path"])) $load($level);
						else if (!self::$production) self::log("Autoloader error", "Could not find path for ".$class);
						return;
					}			
				}

				// Ran out of routes - Was last matched segment variadic and handled ??
				else {
					if ($is_trail_var && isset($level_namespace["path"])) $load($level_namespace);
					else if (!self::$production) self::log("Autoloader error", "Could not find path for ".$class);
					return;
				}
				
				// Proceed to next segment	
				$level = &$level["\\"];
				$depth ++;
			}

			// Ran Out of segments !!!! THIS SHOULD BE UNREACHABLE ??????????
			if (!self::$production) self::log("Autoloader warning", "Could not find path for ".$class);
		}

		// Router
		// ------------------------------------------------

		// Waypoint types
		const ROUTE_CHILD = 0; // This is a child route - no fallbacks to this route
		const ROUTE_BASE = 1; // This is a base route fallback for all nested child routes
		const ROUTE_BASE_REDIRECT = 2; // Fallback requires HTTP redirect to its URL
		const ROUTE_BASE_TAIL = 4; // Fallback must add trailing URL to last variable
		const ROUTE_HALT = 8; // There will be no fallbacks from this route

		// Parameterize HTTP variables when calling handler
		const HTTP_VARS_NONE = 0;
		const HTTP_VARS_GET = 1;
		const HTTP_VARS_POST = 2;
		const HTTP_VARS_COOKIE = 4;

		// Request HTTP method types
		const HTTP_METHOD_GET = 1;		
		const HTTP_METHOD_POST = 2;
		const HTTP_METHOD_PUT = 4;
		const HTTP_METHOD_DELETE = 8;
		const HTTP_METHOD_PATCH = 16;
		const HTTP_METHOD_OPTIONS = 32;
		const HTTP_METHOD_HEAD = 64;
		const HTTP_METHOD_CONNECT = 128;

		// Segment variable sigil
		const EXTRACTOR_SIGIL = ":";		

		// Routes registry
		public static $routes = [];

		// This will create a new route tree or attach to an existing tree
		// The routing tree can be specified by the $router parameter
		// Expects a single formed tree representedby an associative array of the format ["segment": [handler: "", "/": [...]]]
		// Handler is a callable (string or lamba) or a qualified callable (string in the format of component_type.component_name.function_name)
		public static function route(int $httpmethods, string $url, $handler, int $waypoint = self::ROUTE_CHILD, int $httpvars = self::HTTP_VARS_GET | self::HTTP_VARS_POST, string $router = null) {

			// Form methods array
			$methods = [];
			if ($httpmethods & self::HTTP_METHOD_GET) $methods[] = "GET";
			if ($httpmethods & self::HTTP_METHOD_POST) $methods[] = "POST";
			if ($httpmethods & self::HTTP_METHOD_PUT) $methods[] = "PUT";
			if ($httpmethods & self::HTTP_METHOD_DELETE) $methods[] = "DELETE";
			if ($httpmethods & self::HTTP_METHOD_PATCH) $methods[] = "PATCH";
			if ($httpmethods & self::HTTP_METHOD_OPTIONS) $methods[] = "OPTIONS";
			if ($httpmethods & self::HTTP_METHOD_HEAD) $methods[] = "HEAD";
			if ($httpmethods & self::HTTP_METHOD_CONNECT) $methods[] = "CONNECT";

			// Create router if not existant
			if (!isset(self::$routes[$router])) {
				self::$routes[$router] = [
					"GET" => [],
					"POST" => [],
					"PUT" => [],
					"DELETE" => [],
					"PATCH" => [],
					"OPTIONS" => [],
					"HEAD" => [],
					"CONNECT" => []
				];
			}

			// Form tree for each method
			foreach ($methods as $method) {

				// Sanitize & length check for setting root/default handler
				$url = self::str_url($url);
				if (strlen($url) == 0) {
					self::$routes[$router][$method][""] = [
						"handler" => $handler,
						"waypoint" => $waypoint,
						"httpvars" => $httpvars
					];
					continue;
				}

				// Parse & Prep
				$segments = explode("/", $url);
				$depth = 0;
				$vars = [];
				$level = &self::$routes[$router][$method];
				$is_var_named = false;

				// Traverse down, creating the tree
				while($depth < count($segments)) {

					// Get next segment
					$segment = $segments[$depth];

					// Parse if segment variable available
					if ($segment[0] == self::EXTRACTOR_SIGIL) {
						$var = "";

						// Is named var ?								
						if (strlen($segment) > 1) {
							$is_var_named = true;
							$var = substr($segment, 1);

							// Check already used segment variable
							if (in_array($var, $vars)) self::error("Router error", "Segment variable (".$var.") used more than once in URL ".$url);
						}						
						
						// Add to var to segment variables array, mark this segment as variadic
						$vars[] = $var;
						$segment = "*";						
					}

					// Create segment at this depth if it doesnt exist
					if (!isset($level[$segment])) $level[$segment] = ["/" => []];
					
					// Proceed to next segment		
					$level = &$level[$segment];		
					if ($depth < count($segments) - 1) $level = &$level["/"];
					$depth ++;
				}

				// At correct depth now - Add the handler, waypoint, vars
				$level["handler"] = $handler;
				$level["waypoint"] = $waypoint;
				$level["httpvars"] = $httpvars;
				$level["vars"] = $is_var_named ? $vars : [];

			}		
		}

		// This will map an url to a registered route handler
		public static function serve(int $httpmethod = null, string $url = null, string $router = null) {
			
			// Prep method
			if (!isset($httpmethod)) $httpmethod = $_SERVER["REQUEST_METHOD"];
			else {
				if ($httpmethod == self::HTTP_METHOD_GET) $httpmethod = "GET";
				else if ($httpmethod == self::HTTP_METHOD_POST) $httpmethod = "POST";
				else if ($httpmethod == self::HTTP_METHOD_PUT) $httpmethod = "PUT";
				else if ($httpmethod == self::HTTP_METHOD_DELETE) $httpmethod = "DELETE";
				else if ($httpmethod == self::HTTP_METHOD_PATCH) $httpmethod = "PATCH";
				else if ($httpmethod == self::HTTP_METHOD_OPTIONS) $httpmethod = "OPTIONS";
				else if ($httpmethod == self::HTTP_METHOD_HEAD) $httpmethod = "HEAD";
				else if ($httpmethod == self::HTTP_METHOD_CONNECT) $httpmethod = "CONNECT";
				else self::error("Router error", "Invalid HTTP method specified in call to serve()");
			}

			// Prep URL
			if (!isset($url)) {
				$url = self::str_url($_SERVER["REQUEST_URI"]);
				self::$url_local = self::str_url(self::$url_local);

				// Sanity check
				if (strlen(self::$url_local) == 0) self::error("Router error", "'url_local' property must be set in Combine configuration for call to serve() with default parameters.");

				else if (substr($url, 0, strlen(self::$url_local)) != self::$url_local) self::log("Router warning", "Possible invalid property 'url_local' - SERVER['REQUEST_URI'] does not contain the defined value of 'url_local' as a prefix in call to serve() with default parameters - routing could be erroneous");
				
				// Parse anyways
				$url = self::str_url(substr($url, strlen(self::$url_local)));
			}
			else $url = self::str_url($url);

			// Prep preset params
			$presets = [
				"%method%" => strtolower($httpmethod),
				"%tail%" => ""
			];

			// Parse post params
			if (isset($_SERVER["CONTENT_TYPE"])) {
				$content_type = strtolower(trim(explode(";", $_SERVER["CONTENT_TYPE"])[0]));
				if ($content_type == "application/json") {
					try { $_POST = json_decode(file_get_contents("php://input"), true); }
					catch (Exception $e) { self::log("Router warning", "Malformed request body or invalid content-type"); }
				}
			}

			// Helper for dependency injection
			$inject = function($vars, $vals, $httpvars, $presets, $url_trail = null) {
				
				// Prep
				$params = [];
				$params_opt = [];

				// Segment Vars - Do we have any values ?
				if (count($vals) > 0) {

					// Trail Url						
					if (isset($url_trail)) $vals[count($vals) - 1] .= (strlen($vals[count($vals) - 1]) > 0 ? "/" : "").$url_trail;

					// Unnamed parameterization
					if (count($vars) == 0) $params = $vals;

					// Named parameterization
					else {
						$mixed = false;
						$_params = [];

						for($i = 0; $i < count($vars); $i++) {
							if ($vars[$i] == "") {
								$mixed = true;
								$params[] = $vals[$i];
							}
							else $_params[$vars[$i]] = $vals[$i];
						}

						// If mixed named and unnamed, prefer sequential (unnamed) as primary marshall data store
						if ($mixed) $params_opt = $_params;
						else $params = $_params;
					}
				}

				// HTTP Vars		
				if ($httpvars & self::HTTP_VARS_POST) $params_opt += $_POST;
				if ($httpvars & self::HTTP_VARS_GET) $params_opt +=  $_GET;
				if ($httpvars & self::HTTP_VARS_COOKIE) $params_opt +=  $_COOKIE;	

				// Interpolation Vars
				$params_interpolate = array_merge(array_merge($params, $params_opt), $presets);
				
				// Done
				return [$params, $params_opt, $params_interpolate];
			};

			// Length check for routing to root/default handler
			if (strlen($url) == 0) {				
				if (!isset(self::$routes[$router][$httpmethod][""])) {
					if (self::$production) {
						header('HTTP/1.0 404 Not Found', true, 404);
						self::log("Router error", "Route (ROOT) handler is not set");						
						return;
					}
					else self::error("Router error", "Route (ROOT) handler is not set");
				}

				else {
					$level = &self::$routes[$router][$httpmethod][""];
					$fn = null;
					$proceed = false;					

					// Prep handler - will fallback if prep fails
					try {
						$injection = $inject([], [], $level["httpvars"], $presets);
						$fn = self::qualify($level["handler"], $injection[2]);
						$params = self::marshall($fn, $injection[0], $injection[1], true);
						$proceed = true;
					}
					catch (Exception $e) { 
						self::error("Router error", "Could not call route handler (".$fn.") for ".$url." - '".($e->getMessage())."'. Halting at root");
					}

					// Call handler - will not fallback on internal error
					if ($proceed) {
						try {
							if (self::is_lambda($fn)) $fn(...$params);
							else self::{$fn}(...$params);
							return;
						}
						catch (Exception $e) {
							self::error("Router error", "Route handler (".$fn.") for ".$url." threw error '".($e->getMessage())."'");
						}
					}
				}
			}

			else {

				// Parse & Prep
				$segments = explode("/", $url);
				$depth = 0;	
				$level = &self::$routes[$router][$httpmethod];
				$waypoints = [];
				$vals = [];
				$params = [];			

				// Helper for fallback
				$fallback = function() use (&$waypoints, &$inject, &$url, &$segments, &$presets) {
					$index = count($waypoints) - 1;
					$fn = null;
					$params = null;

					// Traverse through each waypoint in reverse order
					while ($index >= 0) {

						// Base - redirect
						if ($waypoints[$index]["waypoint"] & self::ROUTE_BASE_REDIRECT) {
							header('HTTP/1.1 301 Moved Permanently', true, 301);
							header('Location: '.(self::$url_web)."/".$waypoints[$index]["url"]);
						}
						
						// Base - normal
						else {

							// Prep handler - will fallback on prep error if not halt mode	
							$proceed = false;						
							try {

								// Prep Tail						
								$url_trail = ($waypoints[$index]["trail_var"] && ($waypoints[$index]["waypoint"] & self::ROUTE_BASE_TAIL)) ? implode("/", array_slice($segments, $waypoints[$index]["depth"] + 1)) : null;
								$_presets = $presets;
								$_presets["%tail%"] = implode("/", array_slice($segments, $waypoints[$index]["depth"] + 1));

								// Prep dependency injection / data store
								$injection = $inject($waypoints[$index]["vars"], $waypoints[$index]["vals"], $waypoints[$index]["httpvars"], $_presets, $url_trail);

								// Prep handlers
								$fn = self::qualify($waypoints[$index]["handler"], $injection[2]);
								$params = self::marshall($fn, $injection[0], $injection[1], true);
								$proceed = true;	

							}
							catch (Exception $e) {
								if ($waypoints[$index]["waypoint"] & self::ROUTE_HALT) self::error("Router error", "Could not call route handler (".$fn.") for ".$url." - '".($e->getMessage())."'. HALT");
							}

							// Call handler - will not fallback if handler has internal error
							if ($proceed) {
								try {
									if (self::is_lambda($fn)) $fn(...$params);
									else self::{$fn}(...$params);
									return;
								}
								catch (Exception $e) {
									self::error("Router error", "Route handler (".$fn.") for ".$url." threw error '".($e->getMessage())."'");
								}
							}
						}		

						$index--;
					}

					// Ran out of waypoints - nowhere to go				
					if (self::$production) {
						header('HTTP/1.0 404 Not Found', true, 404);
						self::log("Router error", "Route handler is not set for url ".$url);						
					}
					else self::error("Router error", "Route handler is not set for url ".$url);
				};
				
				// Do we have a variable waypoint at ROOT ?
				if (isset(self::$routes[$router][$httpmethod]["*"]["waypoint"])) {
					if (self::$routes[$router][$httpmethod]["*"]["waypoint"] != self::ROUTE_CHILD) {
						$waypoints[] = [
							"waypoint" => self::$routes[$router][$httpmethod]["*"]["waypoint"],
							"url" => "",
							"handler" => self::$routes[$router][$httpmethod]["*"]["handler"] ?? null,
							"vars" => self::$routes[$router][$httpmethod]["*"]["vars"] ?? [],
							"vals" => [$segments[0]],
							"depth" => 0,
							"trail_var" => true,
							"httpvars" => self::$routes[$router][$httpmethod]["*"]["httpvars"]
						];
					}
				}

				// Traverse down
				$is_trail_var = false;
				while ($depth < count($segments)) {

					// Get next segment
					$segment = $segments[$depth];					
					$traverse = false;					
					
					// Variable segment avail
					if (!isset($level[$segment])) {
						if (isset($level["*"])) {
							$traverse = true;
							$is_trail_var = true;

							$vals[] = $segment;
							$segment = "*";
						}
					}

					// Normal segment avail
					else {
						$traverse = true;
						$is_trail_var = false;
					}

					// Is traversal possible
					if ($traverse) {

						// Traverse
						$level = &$level[$segment];

						// Did we run out of more segments
						if ($depth == count($segments) - 1) {

							// Is this level handled ?
							if (isset($level["handler"])) {
								$fn = null;
								$proceed = false;

								// Prep handler - will fallback if prep fails
								try {
									$injection = $inject($level["vars"], $vals, $level["httpvars"], $presets);
									$fn = self::qualify($level["handler"], $injection[2]);
									$params = self::marshall($fn, $injection[0], $injection[1], true);
									$proceed = true;
								}
								catch (Exception $e) { 
									if ($level["waypoint"] & self::ROUTE_HALT) self::error("Router error", "Could not call route handler (".$fn.") for ".$url." - '".($e->getMessage())."'. HALT");
									$fallback();
									return;
								}

								// Call handler - will not fallback on internal error
								if ($proceed) {
									try {
										if (self::is_lambda($fn)) $fn(...$params);
										else self::{$fn}(...$params);
										return;
									}
									catch (Exception $e) {
										self::error("Router error", "Route handler (".$fn.") for ".$url." threw error '".($e->getMessage())."'");
									}
								}
							}

							// Can we fall back to a waypoint ?
							else {
								$fallback();
								return;
							}
						}

						// Do we have a waypoint here ?
						else if (isset($level["waypoint"])) {				
							if ($level["waypoint"] != self::ROUTE_CHILD) {
								$waypoints[] = [
									"waypoint" => $level["waypoint"],
									"url" => implode("/", array_slice($segments, 0, $depth + 1)),
									"handler" => $level["handler"],
									"vars" => $level["vars"] ?? [],
									"vals" => $vals,
									"depth" => $depth,
									"trail_var" => $is_trail_var,
									"httpvars" => $level["httpvars"]
								];
							}
						}
					}

					// Ran out of more routes - fallback to waypoint
					else {
						$fallback();
						return;
					}
					
					// Proceed to next segment	
					$level = &$level["/"];
					$depth ++;
				}

				// Ran Out of segments - fallback to waypoint - !!!! THIS SHOULD BE UNREACHABLE ??????????
				$fallback();
			}
		}		

		// Hooks, function call interception & manipulation
		// ------------------------------------------------

		// This character is used to separate component type, name and function for qualified handler string
		const QUALIFIER_SIGIL = '>';

		// This will load associated components for a handler from a qualified handler and return the callable
		// If no callable is specified, it will return an empty lambda
		public static function qualify(&$handler, array $params_interpolate = []) {
			
			// Lambda
			if (self::is_lambda($handler)) return $handler;

			// Array callable
			elseif (is_array($handler)) {
				// ADD LOGIC

			}
			
			// Unqualified function
			else if (substr_count($handler, self::QUALIFIER_SIGIL) == 0) {
				$handler = trim($handler);
				if (strlen($handler) == 0) self::error("Qualify error", "Blank handler - nothing to qualify");
				else return self::interpolate($handler, $params_interpolate, true);
			}

			// Qualified
			else {
				$_handler = self::interpolate($handler, $params_interpolate, true);

				$pos1 = strpos($_handler, self::QUALIFIER_SIGIL);
				$pos2 = strrpos($_handler, self::QUALIFIER_SIGIL);
				$component_type = trim(strstr($_handler, self::QUALIFIER_SIGIL, true));

				// Dependency only - no function
				if ($pos1 == $pos2) {
					$component_name = trim(strrev(strstr(strrev($_handler), self::QUALIFIER_SIGIL, true)));
					$fn = function(){};
				}

				// Function exists
				else {
					$component_name = trim(substr($_handler, strpos($_handler, self::QUALIFIER_SIGIL) + 1, strrpos($_handler, self::QUALIFIER_SIGIL) - strpos($_handler, self::QUALIFIER_SIGIL) - 1));
					$fn = trim(strrev(strstr(strrev($_handler), self::QUALIFIER_SIGIL, true)));
					if (strlen($fn) == 0) $fn = function(){};				
				}

				// Load dependency & return function
				self::{$component_type}($component_name);
				return $fn;				
			}
		}

		// This will re-arrange arguements in the order the given function accepts them
		public static function marshall(&$fn, array $params, array $params_opt = [], bool $strict = false): array {
			
			// Prep
			$refFunc;
			$args = [];			

			// Lambda
			if (self::is_lambda($fn)) $refFunc = new ReflectionFunction($fn);

			// Array Callable
			else if (is_array($fn)) {
				// Add Logic - fn should already be qualified by now !!!! should this block exist?

			}

			// String-ular callable
			else {
				// Function
				if (strstr($fn, "::") === false) {
					try { $refFunc = new ReflectionFunction($fn); }
					catch(Exception $e) { self::error("Marshall error", "Cannot reflect on inexistent function (".$fn.")"); }
				}

				// Method
				else {
					try { $refFunc = new ReflectionMethod($fn); }
					catch(Exception $e) {
						// Special check for inexistant method for component loaders
						if (isset(self::$components[substr(strstr($fn, "::"), 2)])) $refFunc = new ReflectionFunction(function(string $component_name){});
						else self::error("Marshall error", "Cannot reflect on inexistent method (".$fn.")");
					}
				}
			}

			// Helper - for unavailable arguements to parameters
			$skip = function(string &$var, &$fn, &$param) use ($strict) {
				if ($strict) self::error("Marshall error", "Type conversion failed for given arguement to parameter (".$var.") for function ".$fn);
				else if ($param->isOptional()) return true;
				else return false;
			};
			
			// Does the callable have a definition?
			$num = $refFunc->getNumberOfParameters();
			if ($num > 0) {

				// Sequential marshall
				if (!self::is_assoc($params)) {

					// Add array sequentially to args
					$args = array_slice($params, 0, $num);
					
					// Go over each parameters
					$count = 0;
					foreach($refFunc->getParameters() as $param) {
						$var = $param->getName();	

						// Skip those already marshalled
						if ($count < count($params)) {

							// Type cast 
							if ($param->hasType()) {
								if (!settype($args[$count], (string) $param->getType())) {
									if ($skip($var, $fn, $param)) break;
									else $args[$count] = null;
								}
							}

							$count++;
							continue;
						}															
						
						// Normal marshall
						if ($param->isVariadic() === false) {
							$val = null;
							
							if (isset($params_opt[$var])) {
								$val = $params_opt[$var];
								unset($params_opt[$var]);

								// Type cast
								if ($param->hasType()) {
									if (!settype($val, (string) $param->getType())) {
										if ($skip($var, $fn, $param)) break;
										else $val = null;
									}
								}
							}

							else {
								if ($skip($var, $fn, $param)) break;
								else $val = null;
							}

							$args[] = $val;						
						}
					}
				}

				// Named marshall
				else {
					foreach($refFunc->getParameters() as $param) {
						$var = $param->getName();									
						
						// Normal marshall
						if ($param->isVariadic() === false) {
							$val = null;
							
							if (isset($params[$var])) {
								$val = $params[$var];
								unset($params[$var]);

								// Type cast - on non-opt data store injection
								if ($param->hasType()) {
									if (!settype($val, (string) $param->getType())) {

										// Fall back to opt data store
										if (isset($params_opt[$var])) {
											$val = $params_opt[$var];
											unset($params_opt[$var]);

											// Type cast - on opt data store injection
											if (!settype($val, (string) $param->getType())) {
												if ($skip($var, $fn, $param)) break;
												else $val = null;
											}
										}

										else {
											if ($skip($var, $fn, $param)) break;
											else $val = null;
										}										
									}
								}
							}

							else if (isset($params_opt[$var])) {
								$val = $params_opt[$var];
								unset($params_opt[$var]);

								// Type cast - on opt data store injection
								if ($param->hasType()) {
									if (!settype($val, (string) $param->getType())) {
										if ($skip($var, $fn, $param)) break;
										else $val = null;
									}
								}
							}

							else {
								if ($skip($var, $fn, $param)) break;
								else $val = null;
							}

							$args[] = $val;						
						}

						// Variadic marshall
						else $args = array_merge($args, array_values($params));
					}
				}
			}

			// Undefined parameter list marshall
			else $args = array_values($params);			
			
			return $args;
		}
		
		// Hooks registry
		public static $hooks;

		// This will hook and intercept a global function call.
		public static function hook(string $fn, &$handler, int $priority = 10) {

			// Sanitize the hook - post execution by default !
			if (($fn[0] != '_') && ($fn[strlen($fn) - 1] != '_')) $fn .= "_";

			// Any hooks installed for this expression ??
			if (isset(self::$hooks[$fn])) {

				// Copy existing array
				$_hooks = self::$hooks[$fn];
				self::$hooks[$fn] = [];
				$flag = false;
				$pos = 0;

				// Compare each hook with new hook's priority and add appropriatly
				foreach ($_hooks as $_hook) {
					if (!$flag) {
						$_priority = array_keys($hook)[0];
						$_handler = array_values($hook)[0];

						// Found proper posiion for new hook
						if ($_priority > $priority) {
							self::$hooks[$fn] []= [$priority => $handler];
							$flag = true;
						}
						else $pos ++;
					}

					// Add existing hook anyways
					self::$hooks[$fn] []= $_hook;
				}

				// Is new hook added ?
				if (!$flag) {
					self::$hooks[$fn] []= [$priority => $handler];
					$pos = count(self::$hooks[$fn]) - 1;
				}

				// Return position of new hook
				return $pos;
			}

			// This is the first hook for this expression
			else self::$hooks[$fn] = [[$priority => $handler]];
		}

		// This will remove the specified handler from intercepting a hooked global function call.
		public static function unhook(string $fn, string $handler) {
			self::error("Interceptor error" ,"Unhook is not implemented");
		}

		// This setting allows handler chaining, at the cost of losing ability to send params & result of function call by reference to handlers.
		public static $chaining = false;

		// Function call interceptor
		public static function __callStatic(string $exp, array $args) {

			// Check if component loader call 
			if (isset(self::$components[$exp])) {
				if ((count($args) < 1) || (count($args) > 2)) self::error("Loader error", "Invalid number of arguements");
				else if ((gettype($args[0]) !== "string") || (isset($args[1]) && (gettype($args[1]) !== "boolean"))) self::error("Loader error", "Invalid type of arguement");
				else self::load($exp, ...$args);
				return;
			}

			// Check if function exists and is not a lambda - qualify returns lambda on empty callables
			$fn = self::qualify($exp);
			if (!is_callable($fn)) self::error("Interceptor error", "Function not found - ".$fn);
			if (self::is_lambda($fn)) return;
			
			// Pre-execution hooks
			$pre = "_".$fn;
			if (isset(self::$hooks[$pre]) && (count(self::$hooks[$pre]) > 0)) {
				foreach(self::$hooks[$pre] as $hook) {
					$handler = self::qualify(array_values($hook)[0]);

					if (!is_callable($handler))	self::log("Interceptor error", "Handler (".$handler.") not found for handling - ".$fn."(".implode(", ", $args).")"." pre-execution");
					else {
						try {
							if (self::$chaining) {
								if (self::is_lambda($handler)) $handler($pre, ...$args);
								else self::{$handler}($pre, ...$args);
							}
							else $handler($pre, ...$args);
						}
						catch (Exception $e) { self::log("Interceptor error", "Handler (".$handler.") threw error '".($e->getMessage())."' handling - ".$fn."(".implode(", ", $args).")"." pre-execution"); }
					}
				}
			}

			// Call actual function
			$res = null;
			try { $res = $fn(...$args); }
			catch (Exception $e) { self::error("Interceptor error", "Error '".($e->getMessage())."' executing ".$fn."(".implode(", ", $args).")"); }

			// Post-execution hooks
			$post = $fn."_";
			if (isset(self::$hooks[$post]) && (count(self::$hooks[$post]) > 0)) {
				foreach(self::$hooks[$post] as $hook) {
					$handler = self::qualify(array_values($hook)[0]);

					if (!is_callable($handler))	self::log("Interceptor error", "Handler (".$handler.") not found for handling - ".$fn."(".implode(", ", $args).")"." post-execution");
					else {
						try {
							if (self::$chaining) {
								if (self::is_lambda($handler)) $handler($post, $res, ...$args);
								else self::{$handler}($post, $res, ...$args);
							}
							else $handler($post, $res, ...$args);
						}
						catch (Exception $e) { self::log("Interceptor error", "Handler (".$handler.") threw error '".($e->getMessage())."' handling - ".$fn."(".implode(", ", $args).")"." post-execution"); }
					}
				}
			}

			// All done
			return $res;
		}
		
		// Logger
		// ------------------------------------------------

		// Logger lamba
		public static $logger;

		// Logger interface
		public static function log(string $short, string $long, bool $echo = null) {
			if (is_callable(self::$logger)) self::$logger($short, $long);
			if ((!isset($echo) && !self::$production) || ($echo === true)) {
				echo "<pre>LOGGER: <small><b>".$short." - ".$long."</b><br/><i>Turn on production mode to stop seeing logger msgs.</i></small></pre>";
				ob_flush();
			}		
		}

		// This will trigger an user error and optionally log the error
		public static function error(string $short, string $long, bool $log = true, bool $echo = true) {
			if ($log) self::log($short, $long, $echo);		
			throw new Exception($short." - ".$long);
		}

	}

	// Helper
	require_once __DIR__."/helper.php";	
?>
