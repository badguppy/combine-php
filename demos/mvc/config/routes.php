<?php 

	// Define your application routing here
	i::route(GET|POST, "", "view > hello-world", BASE);
	i::route(GET|POST, ":page", "\\controller\\{{page}}::index", BASE|TAIL);

	/*i::route(GET|POST, "a/b", "f3", BASE);
	i::route(GET|POST, "a/b/c", "f3");
	i::route(GET|POST, "a/b/c/d", "f3");
	i::route(GET|POST, "a/b/:vd/:d", "f4", BASE|TAIL);
	i::route(GET|POST, "s/c/:", "view > {{0}}", BASE|TAIL);
	i::route(GET|POST, "s/m/:", "module > {{0}}");
	i::route(GET|POST, "s/m/x", "module > auth");
	i::route(GET|POST|PUT|DELETE, "y/z/:x/:", "special");
	i::route(GET|POST, "api/:func", "{{func | snake}}_{{%tail% | snake}}", BASE);*/

?>