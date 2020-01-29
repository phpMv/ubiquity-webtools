<?php
namespace Ubiquity\controllers\admin\traits;

use Ubiquity\controllers\admin\popo\ComposerDependency;
use Ajax\semantic\html\elements\HtmlInput;
use Ajax\semantic\html\collections\HtmlMessage;

/**
 * Manages composer dependencies
 * Ubiquity\controllers\admin\traits$ComposerTrait
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.0
 *
 * @property \Ajax\php\ubiquity\JsUtils $jquery
 *
 */
trait ComposerTrait {

	abstract public function showSimpleMessage($content, $type, $title = null, $icon = "info", $timeout = NULL, $staticName = null, $closeAction = null, $toast = false): HtmlMessage;

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
				'optional' => false,
				'category' => 'frontend',
				'class' => 'Ajax\\JsUtils'
			],
			[
				'name' => 'twig/twig',
				'optional' => false,
				'category' => 'templates',
				'class' => 'Twig\\Environment'
			],
			[
				'name' => 'phpmv/ubiquity-reactphp',
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
				'optional' => false,
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
				'optional' => false,
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

	protected function getComposerDataTable() {
		$libs = ComposerDependency::load($this->libraries);
		\usort($libs, function ($left, $right) {
			if ($left->getPart() == $right->getPart())
				return $left->getCategory() <=> $right->getCategory();
		});
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$this->_getAdminViewer()->getComposerDataTable($libs);
		$input = 'let input=$(this).closest("tr").find("._value");';
		$inputSetval = 'if($(this).hasClass("active")){input.val("");}else{input.val($(this).attr("data-part")+":"+$(this).attr("data-ajax"));}';
		$this->jquery->execAtLast('$("#composer-frm").submit(false);$("._remove").click(function(){' . $input . $inputSetval . 'let elm=$(this).closest("tr").find("._u");if($(this).hasClass("active")){elm.unwrap();input.val("");}else{elm.wrap("<strike>");}});$("._remove").state({text:{inactive:"<i class=\'ui icon minus\'></i>Remove",active:"<i class=\'ui icon undo\'></i>To remove"}});');
		$this->jquery->execAtLast('$("._add").click(function(){' . $input . $inputSetval . '$(this).closest("tr").find("._version").html("<input type=\'hidden\' name=\'version[]\'>");let elm=$(this).closest("tr").find("._u");elm.toggleClass("blue");});$("._add").state({text:{inactive:"<i class=\'ui icon plus\'></i>Add",active:"<i class=\'ui icon undo\'></i>To add"}});');
		$this->jquery->postOn('dblclick', '._toUpdate', $baseRoute . '/_dependencyVersions/updatedVersion/', "{name: $(this).attr('data-ajax')}", '$(self)', [
			'attr' => 'data-version',
			'hasLoader' => false,
			'jsCallback' => '$(self).closest("tr").find("._update").val($(self).attr("data-part")+":"+$(self).attr("data-ajax"));'
		]);
		$this->jquery->postOnClick('._add', $baseRoute . '/_dependencyVersions', "{name: $(this).attr('data-ajax')}", '$(self).closest("tr").find("._version")', [
			'jsCondition' => '!$(this).hasClass("active")',
			'ajaxLoader' => false
		]);
		$this->jquery->postFormOnClick('#submit-composer-bt', $baseRoute . '/_updateComposer', 'composer-frm', '#response');
	}

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
		$values = $_POST;
		$response = [];
		$toAdd = [];
		foreach ($values['toAdd'] as $index => $toAddOne) {
			if ($toAddOne != '') {
				list ($require, $pv) = \explode(':', $toAddOne);
				if (($v = $values['version'][$index] ?? '') != '') {
					$pv .= ':' . $v;
				}
				$toAdd[$require][] = $pv;
			}
		}
		$toRemove = [];
		foreach ($values['toRemove'] ?? [] as $index => $toRemoveOne) {
			if ($toRemoveOne != '') {
				unset($values['toUpdate'][$index]);
				list ($require, $pv) = \explode(':', $toRemoveOne);
				$toRemove[$require][] = $pv;
			}
		}
		$updatedVersionIndex = 0;
		foreach ($values['toUpdate'] ?? [] as $index => $toUpdateOne) {
			if ($toUpdateOne != '') {
				list ($require, $pv) = \explode(':', $toUpdateOne);
				if (($v = $values['updatedVersion'][$updatedVersionIndex ++] ?? '') != '') {
					$pv .= ':' . $v;
				}
				$toAdd[$require][] = $pv;
			}
		}
		if (\count($toAdd) > 0) {
			if (isset($toAdd['require'])) {
				$response[] = 'composer require ' . \implode(' ', $toAdd['require']);
			}
			if (isset($toAdd['require-dev'])) {
				$response[] = 'composer require ' . \implode(' ', $toAdd['require-dev']) . ' --dev';
			}
		}
		if (\count($toRemove) > 0) {
			if (isset($toRemove['require'])) {
				$response[] = 'composer remove ' . \implode(' ', $toRemove['require']);
			}
			if (isset($toRemove['require-dev'])) {
				$response[] = 'composer remove ' . \implode(' ', $toRemove['require-dev']) . ' --dev';
			}
		}
		$this->jquery->postFormOnClick('#validate-btn', $this->_getFiles()
			->getAdminBaseRoute() . '/_execComposer', 'composer-update-frm', null, [
			'before' => '$("#response").html("<div style=\'white-space: pre;white-space: pre-line;\' class=\'ui inverted message\'><i class=\'icon close\'></i><div class=\'header\'>Composer update...</div><div id=\'partial\' class=\'content\'><div class=\'ui active slow green double loader\'></div></div></div>");',
			'hasLoader' => false,
			'partial' => "$('#partial').html(response);"
		]);
		$this->jquery->renderView($this->_getFiles()
			->getViewComposerFrm(), [
			'commands' => \implode("\n", $response)
		]);
	}

	public function _execComposer() {
		header('Content-type: text/html; charset=utf-8');

		$this->jquery->execAtLast('$(".message .close").on("click", function() {$(this).closest(".message").transition("fade");});');

		$commands = \explode("\n", $_POST['commands']);
		if (\ob_get_length())
			\ob_end_clean();
		ob_end_flush();
		foreach ($commands as $cmd) {
			echo "<span class='ui teal text'>$cmd</span>\n";
			flush();
			ob_flush();
			$this->liveExecuteCommand($cmd);
		}
		$this->jquery->get($this->_getFiles()
			->getAdminBaseRoute() . '/_refreshComposer', '#dtComposer', [
			'jqueryDone' => 'replaceWith',
			'hasLoader' => false
		]);
		echo $this->jquery->compile();
	}

	public function _refreshComposer() {
		$this->getComposerDataTable();
		$this->jquery->renderView($this->_getFiles()
			->getViewExecComposer());
	}

	private function liveExecuteCommand($cmd) {
		$proc = \popen("$cmd 2>&1", 'r');
		$live_output = "";
		while (! \feof($proc)) {
			$live_output = fread($proc, 4096);
			echo "$live_output";
			flush();
			ob_flush();
		}
		\pclose($proc);
	}
}

