<?php

	// Combine
	require_once "../../combine/combine.php";	

	// Define component loaders
	i::component("view", "resources/view", FILE);
	i::component("spa", "resources/spa/{{component_name}}/index", FILE);

	// Define class auto-loaders
	i::classify("\\controller\\:ctl", "app/controller/{{ctl | pascal}}");
	i::classify("\\model\\:md", "app/model//{{md | pascal}}");
	i::classify("\\:vendor\\:pkg\\:cls", "vendor/psr-4/{{vendor}}/{{pkg}}/{{cls | psr4}}");
	i::classify(":cls", "vendor/psr-0/{{cls | psr0}}");

	// Bootstrap
	i::require_dir_once("config");
	
	// Go !!
	i::serve();

?>