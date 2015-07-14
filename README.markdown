Phalcon Debug Widget (2.0.* complatible)
===
[![Latest Version](https://img.shields.io/packagist/v/transactpro/phalcon-translate.svg?style=flat-square)](https://github.com/transactpro/phalcon-translate/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/transactpro/phalcon-translate.svg?style=flat-square)](https://packagist.org/packages/transactpro/phalcon-translate)

Note (How it works):
=====
The debug widget for now is very simplistic and more of a proof-of-concept. It expects you have three services in your dependency injector named "db", "dispatcher" and "view" and that they correspond to those services. When you pass the DI to Phalcon Debug Widget It looks for those specific services and:
- sets them as shared services
- sets the eventManager for them
- Attaches itself to those events

This means passing the DI to the debug widget will alter those services. Generally speaking, a shared db, dispatcher, and view is fine. If you have ideas for other ways to hook in, please open an issue for discussion.



The Phalcon Debug Widget is designed to make development easier by displaying debugging information directly in your browser window. Currently it displays php globals such as $_SESSION as well as outputing resource usage and database queries and connection information. It includes syntax highlighting via [Prismjs.com](http://prismjs.com/).

If it looks familiar, its because its modeled after the [Yii debug toolbar](https://github.com/malyshev/yii-debug-toolbar)


## Installation

```json
"require": {
	"transactpro/phalcon-debug-widget": "~1.0"
}
```

## Usage and Configuration



Define a debug or environment flag in your main index.php file so you can easily disable the Phalcon Debug Widget on production environments. Example:

```php
defined('PHALCONDEBUG') || define('PHALCONDEBUG', true);
```

Add these lines to your index.php file before you handle application.
```php
if (PHALCONDEBUG) {
	$debugWidget = new \PDW\DebugWidget($di);
}

echo $application->handle()->getContent();
```


## Preview

![](/preview.png)

## Attribution:

Bug Icon designed by [Nithin Viswanathan](http://thenounproject.com/nsteve) from the [Noun Project](http://thenounproject.com)

JQuery Syntax Highlighting implemented with [Prismjs.com](http://prismjs.com/)


