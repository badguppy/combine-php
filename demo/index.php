<?php

	// Combine
	require_once "../combine/combine.php";

	// Timezone
	date_default_timezone_set("Asia/Kolkata");

	// Paths
	i::$url_local = "combine-php/demo";
	i::$url_web = "http://localhost/combine-php/demo";

	// Define components - directory & loading
	i::component("view", "view", FILE);
	i::component("module");

	// Custom functions
	function f1() {
		echo "<br/><b>f1 Called</b><br/>";
		var_dump(func_get_args());
		echo "<br/>";
	}

	function f2() {
		echo "<br/><b>f2 Called</b><br/>";
		var_dump(func_get_args());
		echo "<br/>";
	}

	function f3() {
		echo "<br/><b>f3 Called</b><br/>";
		var_dump(func_get_args());
		echo "<br/>";
	}

	function f4($d, $vd) {
		echo "<br/><b>f4 Called</b><br/>";
		var_dump(func_get_args());
		echo "<br/>";
	}

	function special($u, $w, $x) {
		echo "<br/><b>Special Called</b><br/>";
		var_dump(func_get_args());
		echo "<br/>";
	}

	function test_test_get(int $id) {
		echo "<br/><b>Test_Get Called</b><br/>";
		var_dump(func_get_args());
		echo "<br/>";
	}
	
	i::route(GET|POST, "", "f1", BASE);
	i::route(GET|POST, ":", "f2", BASE|TAIL);
	i::route(GET|POST, "a/b", "f3", BASE);
	i::route(GET|POST, "a/b/c", "f3");
	i::route(GET|POST, "a/b/c/d", "f3");
	i::route(GET|POST, "a/b/:vd/:d", "f4", BASE|TAIL);
	i::route(GET|POST, "s/c/:", "i::view", BASE|TAIL);
	i::route(GET|POST, "s/m/:", "module > {{0}}");
	i::route(GET|POST, "s/m/x", "module > auth");
	i::route(GET|POST|PUT|DELETE, "y/z/:x/:", "special");
	i::route(GET|POST, "api/:func", "{{func | snake}}_{{%tail% | snake}}", BASE|TAIL|HALT);

	//i::$production = true;
	i::serve(GET, "");
	i::serve(GET, "a/b/t/y/u/i/o/p");
	i::serve(GET, "s/m/auth/ggg");
	i::serve(GET, "s/m/x");
	i::serve(GET, "s/c/backend/admin/dash.new2");
	i::serve(GET, "s/c/backend/admin/dash.new");
	i::serve(GET, "y/z/seg1/seg2");	
	i::serve();
	i::serve(GET, "api/test/a/b/c");

	//var_dump(i::$routes);

	exit("<br/><br/>OK");

?>
