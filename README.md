README
========
Yii2 SimpleSaml for PKPU Dev Team

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist pkpudev/yii2-simplesaml "*"
```

or add

```
"pkpudev/yii2-simplesaml": "*"
```

to the require section of your `composer.json` file.

Usage
------------

Add this to your config 

```
'user' => [
	'class' => 'pkpudev\simplesaml\WebUser',
	'identityClass' => 'path\to\models\User',
	'autoloaderPath'=>'/path/to/simplesamlphp/version/lib/_autoload.php',
	'authSource'=>'your-client',
	'attributesConfig'=>array(
		'id'=>'id',
		'username'=>'user_name',
		'name'=>'full_name',
		'fullname'=>'full_name',
		'email'=>'email',
		// add others
	),
	'superuserCheck' => true,
	'superuserPermissionName' => 'superuserAccess',
],
```