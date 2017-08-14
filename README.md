# Combine PHP
v0.1 alpha


## About Combine
Combine is a light-weight web framework in PHP which focuses on minimal bootstrapping, flexible architecture and low verbosity. This framework is being developed by me as a part-time project, for use in quickly prototyping PHP web applications. It supports the following features as of now, with many more features planned - 

- RESTful Routing 
- HTTP RPC
- Dependency Injection 
- Component Loading
- Procedural Unit-Testing

Planned features - 

- PSR-0/4 Spec Autoloading
- Objective Unit-Testing
- Event Pipeline
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

- Components
	- Defining Components
	- Loading Components
	- Components & Handlers
	- Autoloading Classes
	- Examples	
	
- Routes
	- Defining Routes
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

While as of now Combine is not built for use in an object-oriented fashion, with some of it's features not fully supporting objects (Ex. unit-testing), it is my full intention to support and encourage Object-Oriented development and the same is in the plans for the immediate future. 

As of now, Combine can be used for quickly prototyping APIs etc, and offers a lot of features that work well with procedural or functional paradigms. That being said, it is still very usable for full-fledged applications, as we will see in 'Defining Components'  section, that it can have MVC architecture for developing apps.


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


## Components

Combine refers to a directory, or file, or a bunch of files as a *Component*. Components are used for writing modular code, lazy-loading etc. Combine offers features to define directories or files as components and load them as required at runtime. While it does not support class auto-loading (`spl_autoload_register`) yet, it is planned in the very next iteration.


### Defining Components

A Component is defined by calling the `component` method. It takes 3 parameters - 
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



## Autoloading Classes

While not supported as of yet, it the very next feature planned and will be able to offer flexible autoloading with minimal coding. It will utilize same concepts as components


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














