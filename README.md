# Combine PHP
v0.2 alpha


## About Combine
Combine is a light-weight web framework in PHP which focuses on minimal bootstrapping, flexible architecture and low verbosity. This framework is being developed by me as a part-time project, for use in quickly prototyping PHP web applications. It supports the following features as of now, with many more features planned - 

- RESTful & Late-binding Routes
- HTTP RPC
- Dependency Injection 
- Lazy loading (PSR-0/4)
- Functional Unit-Testing

Planned features - 

- Object Unit-Testing
- Pipelines
- Plugins
- Server-Sent Events


## Using Combine
Table of Contents

- Getting Started
- Design Principles

- Architecture
	- Coding Paradigms
	- Application Directory Structure

- Concepts
	- Late Binding Through Interpolation
	- Dependency Injection

- Lazy Loading
	- Autoloading Classes
	- Defining Components
	- Loading Components
	- Components & Handlers	
	- Examples	
	
- Routes
	- Defining HTTP Routes
	- Serving HTTP Requests
	- Examples
	
- Hooks
	- Intercepting Function Calls
	- Handling Interceptions
	- Event Pipeline
	- Examples
	

## Getting Started
Combine consists of two files - *combine.php* and *helper.php*. As of right now, those are all the files you need to get started. 

1. Just reference *combine.php* in the *index.php* file of your application using `require_once "combine/combine.php";`. This is the main file that contains the Combine class. Do not edit anything in this file.

2. The *helper.php* file will be automatically loaded. It defines a few shortened constants, referencing the long names in the Combine Class. If they conflict with constants defined by your application, you can edit this file to rename the constants that conflict. This file also defines a PHP *class-alias* for the `Combine` class - `i`.

When using combine, all framework functionalities are to be accessed via calling them statically on the `Combine` class or the short-hand `i` class, like so
```php
Combine::hello();
i::hello();
```
I prefer the short-hand.

In the future, Combine will support feature-based plug-ins, which will be defined in other files.
If you want to start developing your application, skip the next two sections and head over to 'Concepts' topic in this documentation or download the 'demo' folder to quickly get started. 


## Design Principles

- Less framework, more business.
- Abstraction is good if also intuitive.
- Flexibility is better than compromise.
- Low verbosity is better than more boilerplate.

## Architecture
### Coding Paradigm

While as of now a few of Combine's features do not fully support objects (Ex. unit-testing), it is my full intention to support and encourage Object-Oriented development and the same is in the plans for the immediate future. Combine can be used for quickly prototyping APIs etc. While it offers a lot of features that work well with procedural or functional paradigms, it can have MVC architecture for developing apps as well.


### Directory Structure

Combine does not define any fixed directory structure. This is based on the design principal to give maximum flexibility to the developer, considering different ways of interaction with files, front-end frameworks etc. However, it is easy to setup a directory structure for Combine, one that you are used to.


## Concepts
### Late Binding Through Interpolation

Combine uses string interpolation to decide values of parameters at runtime. It uses this in a variety of its features, which allows the user to intelligently craft code which is minimal, readable and yet very flexible.

Take a look at the following string
```
'part1/{{part2}}/part3'
```

The middle section of this string `{{part2}}` is interpolated using the double brace syntax. It means the value of that portion will be decided at runtime based on the value of *some variable named `part2`*.

If the value of the variable `part2` were 'foo', the resulting string would become
```
'part1/foo/part3'
```

There can be multiple such interpolations per string, each non-overlapping, such as
```
'part1/{{part2}}/{{part3}}'
```

If the variable `part3` has a value of equal to 'bar', the reulting string would be
```
'part1/foo/bar'
```

The value of the variables can be mutated at runtime by using *filters*. Combine expects filters to be standard PHP callables or qualified handlers (later on this), that take in a string and return a string. An example of a filter would be the PHP `strtoupper()` function.

The following example shows an Interpolated string with a filter, using a pipe *'|'* operator. ***Note*** that the function call parenthesis is missing.
```
'part1/{{part2}}/{{part3 | strtoupper}}'
```

Which results in
```
'part1/foo/BAR'
```

Multiple filters can be chained one after another, with the order of effect being left to right. Combine includes 3 useful filters - `snake`, `camel`, `pascal`, for restructuring URLs to form meaningful function/class/interface/file names in PHP. 

The value of the variable that fills the interpolated string, is *injected* into it using dependency injection, see next section.


### Dependency Injection

Combine uses data (variables, arrays, json etc..) to form interpolated strings at runtime. Every Combine feature that supports string interpolation will have a *Data store* which it will use to fill the variables in the interpolated string. These data can be obtained from various sources by Combine, based on the feature being used - Ex. HTTP Router can take data from `$_POST` variables etc.

Dependency injection is also used in calling functions which are defined as *handlers* for certain situations - Ex. HTTP route controllers. The dependency is declared implicitely by including it in the handler (controller function) definition. Combine *reflects* on the function to be able to send these values from various data stores - Ex. the combine router will decide routes based on availability of the dependencies that the route handler (controller) needs in it's function definition.


## Lazy Loading
### Autoloading Classes

Combine facilitates a very flexible class-to-file autoloading relation. Combine's autoloader is fully compliant with PSR-0/4 spec. The class directory tree is user-defined, hence making it compliant to any current or future standards. The Combine autoloader uses the PHP function `spl_autoload_register()` to accomplish this feature.

To register an autoloader for a class/interface, call the static method `classify()`. This method take the following two parameters
- *`string`* `$class` A fully qualified (and namespace'd) class name.
- *`string`* `$path` A file path (directory and filename without the '.php' extension). Supports interpolation.

Calling `classify()` will register the autoloader using `spl_autoload_register()` on Combine's static method `autoload()`. The autoloader allows variable namespace segments to be used for deducing the class file path. It can extract the value of the namespace as a variable and make it available for Dependency Injection for creating the file path at runtime. The *extraction sigil* `:` is used to denote a variable namespace segment. The name of the variable is after the sigil. For example
```php
i::classify(":cls", "src/vendor/{{cls}}");
$obj = new foo\bar();
```

The above code will extract the value of the namespace segment `:cls` and use it to form the file path 'src/vendor/foo/bar.php'. Note that the entire qualified namespace'd class name is present in the variable `cls`. The reason for this is that Combine will attach the trailing namespace / class segments to a variable if it occurs at the end of the `$class` string.

Another example is
```php
i::classify("\\foo\\:cls", "src/vendor/{{cls | snake}}");
$obj = new foo\bar\baz();
```

The above code will form the file path 'src/vendor/bar_baz.php' and autoload class `baz`. Note the use of the filter `snake` on the last namespace segment.


### Defining Components

A well-designed class heirarchy and directory tree may not be available when you just want to prototype your application and focus on the business logic. Combine allows you to write procedural code and still be able to modularize it without needing a class. This is called a *Component*. Combine refers to any directory, or file, or a bunch of files as a *Component* and let's you load them as required at runtime. 

A Component is defined by calling the static method `component` of the Combine class. It takes 3 parameters - 
- `*string* $component_type` Defines the type of the component. It is used when loading components of this type.
- `*string* $path` An interpolated string representing the path to be parsed for loading components of this type.
- `*mixed* $logic` The loading logic. This can be 
	- A lambda function that accepts two string parameters - type & name, respectively.
	- It can be a standard PHP callable
	- It can be a qualified *handler*
	- (Most common) It can be a combination of the following flags
		- `DIR` The path is a directory and all .php files inside are to be loaded.
		- `FILE` The path is a php file and it is to be loaded after appending the '.php' extension to it.
		- `ONCE` Execute `require_once`, instead of `require` on the file(s).
		Default flags are `DIR | ONCE`.
		
The following variables are available for interpolation in the `$path` parameter - `component_type`, `component_name`.


### Loading Components

A defined component can be loaded by calling a method of the same name as `$component_type` on the `Combine` (or `i`) class statically, like so
```php
i::component("module", "app/modules", DIR|ONCE);
i::module("somemodulename");

```


### Components & Handlers

Components can also be loaded as an *handler*. An *Handler* is a specific component or a function inside a specific component that handles a particular functionality. A *Qualified Handler* is a string which containes the *component_type* and *component_name* and optionally the function name. 

For example 
```
'module > somemodulename > func'
```

This string denotes a function `func` present inside the 'somdemodulename' module. Note the greater than sign (>) used to seperate the different parts of the qualified handler. Handler strings support namespaces, paths, static method calls (::) and most importantly, interpolation, like so
```
'module > {{component_name}} > {{func_name}}'
```

This assumes that the variables `component_nam` and `func_name` are available to be injected.


### Examples

A few use case scenarios are

Component `module` which does `require_once` on all files inside the 'somemodulename' sub-directory of 'app/modules'.
```php
i::component("module", "app/modules", DIR | ONCE);
i::module("somemodulename");
```

Loading a different sub-directory of module
```php
i::module("someothersubdir");
```

Interpolated directory path - Component 'module' does `require` on a single *somefile.php* file inside 'app/modules'.
```php
i::component("module", "app/modules/{{component_name}}", FILE);
i::module("somefile");
```

Interpolated directory path - Component 'module' does `require_once` on a single *somefile.php* file inside a sub-directory of the same name.
```php
i::component("module", "app/modules/{{component_name}}/{{component_name}}", FILE|ONCE);
i::module("somefile");
```

Component 'plugin' using another component 'module' to define the loading logic of itself.
```php
i::component("plugin", "app/plugins", "module > plugin_loader > load_plugin");
i::plugin("someplugin");
```


## Routes
### Defining HTTP Routes

HTTP Routes are defined by calling the static method `route` of the Combine class. The following parameters are accepted
- `*int* $httpmethods` Defined which HTTP methods this route supports - can be used for RESTful routing. It is a combination of the following flag constants defined in the *helper.php* file - `GET`, `POST`, `PUT`, `DELETE`, `PATCH`, `OPTIONS`, `HEAD` and `CONNECT`
- `*string* $url` The templated URL to match for this route.
- `*mixed* $handler` The handler for this route. Supports interpolation. Can also accept a lambda function.
- `*int* $opts` Options (constant flags) on how to handle the routing, which can be a combination of the following
	- `CHILD` Default. This means this is a child route.
	- `BASE` This is the parent of other nested routes. Undefined routes or fallbacks can happen to this route.
	- `REDIRECT` To be used with `BASE`. Means any fallback to this route will happen via a HTTP 302 redirect.
	- `TAIL` To be used with `BASE`. Means any trailing URL segments upon fallback should be added to the last variable segment.
	- `HALT` Do not fallback from this route. Will stop avalanche fallbacks to parent routes and instead show an error.
- `*int* $httpvars`	Options (constant flags) that define which of the following data stores to be included for dependency injection and interpolation. Defaults to `GET|POST`.
	- `NONE` Make nothing available
	- `GET` Make HTTP GET parameters available.
	- `POST`Make HTTP POST parameters avilable. Combine will use the *Content-Type* request header to parse POST params from the request body. Supports *application/json* and *application/x-www-form-urlencoded*.
	- `COOKIES` Make cookies available.
- `*string* $router` Optional. You can define routes on different routers and chain them together using this. This defines the name of the router to define the route on. Defaults to `Null`.

Combine router allows variable segments to be used for routing. It can also extract the value of the segment as a variable and make it available for Dependency Injection for the handler string or the handler itself. This is similar to how the Combine autoloader for classes work. The *extraction sigil* `:` is used to denote a variable segment in a route URL. The name of the variable is after the sigil. For example
```php
i::route(GET, "app/:var/something", "module > somemodulename > {{var}}");
```

The above code will extract the value of the second URL segment `:var` and use it to interpolate the handler and form the function name. In effect, for an HTTP GET request to 'app/foo/something' have the following effect - 
1. Load component 'module' named 'somemodulename'
2. Execute a function called 'foo', since that is the value of the second URL segment.

In addition to the variables derived from the URL, Combine can also provide variables from `GET`, `POST` and `COOKIE` parameters. The same can be used a key-value pair to interpolate the handler string.

After Combine forms the qualified handler for a matched route, it inspects the handler by *reflecting* on it. Combine tries to understand the function prototype and determines if all the non-optional parameters in the function signature are avail from the data stores. If yes, it calls the function. Else it tries to fallback to a parent route declared as `BASE`.

Combine **NEEDS** ALL the non-optional parameters in the controller function protoype to be available, or the function to be variadic or lacking any parameters. In any other case it will attempt a fallback to a `BASE`.

Combine provides the following extra variables for Interpolation and/or Dependency Injection. **Note** the *%* sign to seperate it from user-defined variables from other data stores like `GET`.
1. `%method%` The current http method being called. This can be used to create RESTful APIs.
2. `%tail%` The trailing url, if defined as a `BASE` route.


## Serving HTTP Requests

To serve the request by reading run-time values parsed by PHP (REQUEST_URI etc.), call the method `serve` on the Combine class.

```php
i::serve();
```


### More Documentation
Coming Soon...
Please ***star*** this repository to support it's development for the public. Your suggestions & criticisms will be appreciated much.
