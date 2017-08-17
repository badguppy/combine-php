<?php 

	// Alias for short-form usage
	class_alias("Combine", "i");

	// Constant shortcuts
	const AUTO = -1;
	const NONE = 0;
	
	const FILE = Combine::LOAD_FILE;
	const DIR = Combine::LOAD_DIR;
	const ONCE = Combine::LOAD_ONCE;
	
	const GET = Combine::HTTP_METHOD_GET;
	const POST = Combine::HTTP_METHOD_POST;
	const PUT = Combine::HTTP_METHOD_PUT;
	const DELETE = Combine::HTTP_METHOD_DELETE;
	const PATCH = Combine::HTTP_METHOD_PATCH;
	const OPTIONS = Combine::HTTP_METHOD_OPTIONS;
	const HEAD = Combine::HTTP_METHOD_HEAD;
	const CONNECT = Combine::HTTP_METHOD_CONNECT;

	const COOKIE = Combine::HTTP_VARS_COOKIE;	
	
	const BASE = Combine::ROUTE_BASE;
	const REDIRECT = Combine::ROUTE_BASE_REDIRECT;
	const TAIL = Combine::ROUTE_BASE_TAIL;
	const HALT = Combine::ROUTE_HALT;

	const PRE = Combine::HOOK_PRE;

?>
