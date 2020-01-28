<?php
namespace Ubiquity\controllers\admin\traits;

use Ubiquity\controllers\admin\popo\ComposerDependency;
use Ajax\semantic\html\elements\HtmlInput;

/**
 * Manages composer dependencies
 * Ubiquity\controllers\admin\traits$ComposerTrait
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.0
 *
 */
trait ComposerTrait {

	protected $libraries = [
		'require' => [
			[
				'name' => 'php',
				'optional' => false,
				'category' => 'core',
				'class' => 'stdclass'
			],
			[
				'name' => 'phpmv/ubiquity',
				'optional' => false,
				'category' => 'core',
				'class' => 'Ubiquity\\controllers\\Startup'
			],
			[
				'name' => 'phpmv/ubiquity-mailer',
				'optional' => true,
				'category' => 'tools',
				'class' => 'Ubiquity\\mailer\\MailerManager'
			],
			[
				'name' => 'phpmv/php-mv-ui',
				'optional' => true,
				'category' => 'frontend',
				'class' => 'Ajax\\JsUtils'
			],
			[
				'name' => 'twig/twig',
				'optional' => true,
				'category' => 'templates',
				'class' => 'Twig\\Environment'
			],
			[
				'name' => 'phpmv/ubiquity-react',
				'optional' => true,
				'category' => 'servers',
				'class' => 'Ubiquity\\servers\\react\\ReactServer'
			],
			[
				'name' => 'phpmv/ubiquity-php-pm',
				'optional' => true,
				'category' => 'servers',
				'class' => 'PHPPM\\Ubiquity'
			],
			[
				'name' => 'phpmv/ubiquity-tarantool',
				'optional' => true,
				'category' => 'database',
				'class' => 'Ubiquity\\db\\providers\\tarantool\\TarantoolWrapper'
			],

			[
				'name' => 'phpmv/ubiquity-swoole',
				'optional' => true,
				'category' => 'servers',
				'class' => 'Ubiquity\\servers\\swoole\\SwooleServer'
			],
			[
				'name' => 'phpmv/ubiquity-workerman',
				'optional' => true,
				'category' => 'servers',
				'class' => 'Ubiquity\\servers\\workerman\\WorkermanServer'
			]
		],
		'require-dev' => [
			[
				'name' => 'czproject/git-php',
				'optional' => true,
				'category' => 'tools',
				'class' => 'Cz\\Git\\GitRepository'
			],
			[
				'name' => 'mindplay/annotations',
				'optional' => true,
				'category' => 'core',
				'class' => 'mindplay\\annotations\\Annotation'
			],
			[
				'name' => 'monolog/monolog',
				'optional' => true,
				'category' => 'tools',
				'class' => 'Monolog\\Logger'
			],
			[
				'name' => 'phpmv/ubiquity-webtools',
				'optional' => true,
				'category' => 'core',
				'class' => 'Ubiquity\\controllers\\admin\\UbiquityMyAdminBaseController'
			],
			[
				'name' => 'phpmv/ubiquity-dev',
				'optional' => false,
				'category' => 'core',
				'class' => 'Ubiquity\\controllers\\admin\\popo\\Route'
			]
		]
	];

	public function _dependencyVersions($name = 'version', $version = '') {
		$vendorPackage = $_POST['name'] ?? '';
		list ($vendor, $package) = \explode('/', $vendorPackage);
		$versions = ComposerDependency::getVersions($vendor, $package);
		$dt = new HtmlInput('versions-' . $name, 'text', '', 'version...');
		$dt->setValue(\urldecode($version));
		$dt->setName($name . '[]');
		$dt->addClass('mini');
		$dt->addDataList($versions);
		echo $dt;
	}

	public function _updateComposer() {
		\var_dump($_POST);
	}
}

