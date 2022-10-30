<?php
namespace Ubiquity\controllers\admin\traits;

use Ubiquity\utils\http\URequest;
use Ubiquity\utils\base\UArray;

/**
 * Ubiquity\controllers\admin\traits$ConfigPartTrait
 * This class is part of Ubiquity
 *
 * @author jc
 * @version 1.0.0
 * @property \Ajax\php\ubiquity\JsUtils $jquery
 */
trait ConfigPartTrait {

	private function arrayUpdateRecursive(&$original, &$update, &$toRemove, $key = '', $remove = false) {
		foreach ($original as $k => $v) {
			$nKey = ($key == null) ? $k : ($key . '-' . $k);
			if (\array_key_exists($k, $update)) {
				if (\is_array($update[$k]) && \is_array($v)) {
					$this->arrayUpdateRecursive($original[$k], $update[$k], $toRemove, $nKey, $remove);
				} else {
					if (\array_search($nKey, $toRemove) === false) {
						$original[$k] = $update[$k];
					}
				}
			} else {
				if (\array_search($nKey, $toRemove) === false) {
					$toRemove[] = $nKey;
				}
			}
			if ($remove && \array_search($nKey, $toRemove) !== false) {
				unset($original[$k]);
			}
			unset($update[$k]);
		}
		foreach ($update as $k => $v) {
			if (\array_search($k, $toRemove) === false) {
				$original[$k] = $v;
			}
		}
	}

	private function getConfigPartFromPost(array $result) {
		$postValues = $_POST;
		foreach ($postValues as $key => $value) {
			if ('_toDelete' != $key) {
				if (strpos($key, "-") === false) {
					$result[$key] = $value;
				} else {
					$keys = explode('-', $key);
					$v = &$result;
					foreach ($keys as $k) {
						if (! isset($v[$k])) {
							$v[$k] = [];
						}
						$v = &$v[$k];
					}
					$v = $value;
				}
			}
		}
		return $result;
	}

	private function removeEmpty(&$array) {
		foreach ($array as $k => $value) {
			if ($value == null) {
				unset($array[$k]);
			} elseif (\is_array($value)) {
				if (\count($value) == 0) {
					unset($array[$k]);
				} else {
					$this->removeEmpty($array[$k]);
				}
			}
		}
	}

	private function removeDeletedsFromArray(&$result, $toDeletes) {
		$v = &$result;
		foreach ($toDeletes as $toDeleteOne) {
			if ($toDeleteOne != null) {
				if (strpos($toDeleteOne, "-") === false) {
					unset($result[$toDeleteOne]);
				} else {
					$keys = explode('-', $toDeleteOne);
					$v = &$result;
					$s = \count($keys);
					for ($i = 0; $i < $s - 1; $i ++) {
						$v = &$v[$keys[$i]];
					}
					unset($v[\end($keys)]);
				}
			}
		}
	}

	private function addConfigBehavior() {
		$this->jquery->mouseleave('td', '$(this).find("i._see").css({"visibility":"hidden"});');
		$this->jquery->mouseenter('td', '$(this).find("i._see").css({"visibility":"visible"});');
		$this->jquery->click('._delete', 'let tDf=$("[name=_toDelete]");tDf.closest(".ui.dropdown").dropdown("set selected",$(this).attr("data-name"));');
		$this->jquery->click('.cancel-all','$("[name=_toDelete]").closest(".ui.dropdown").dropdown("clear");');
		$this->jquery->exec('$("._delete").closest("td").css({"min-width":"200px","width":"1%","white-space": "nowrap"});',true);
	}

	private function addSubmitConfigBehavior(array $ids, array $urls, array $callbacks) {
		$this->jquery->postFormOnClick("#save-config-btn", $urls['submit'], 'frmConfig', $ids['response'], [
			'jsCallback' => $callbacks['submit'],
			'hasLoader' => 'internal'
		]);
		$this->jquery->execOn("click", "#bt-Canceledition", $callbacks['cancel']);
		$this->sourcePartBehavior($ids,$urls,'frmConfig','frm-source');
	}

	private function sourcePartBehavior($ids,$urls,$frmConfig='frmConfig',$frmSource='frm-source'){
		$this->jquery->execAtLast("$('._tabConfig .item').tab();");
		$this->jquery->execAtLast("$('._tabConfig .item').tab({'onVisible':function(value){
			if(value=='source'){
			" . $this->jquery->postFormDeferred($urls['source'], $frmConfig, '#tab-source', [
				'hasLoader' => false
			]) . "}else{
			" . $this->jquery->postFormDeferred($urls['form'], $frmSource, $ids['form'], [
				'hasLoader' => false,
				'jqueryDone' => 'replaceWith'
			]) . "
		}
		}});");
	}

	private function refreshConfigFrmPart($original, $identifier = 'frmMailerConfig') {
		$toRemove = [];
		$update = $this->evalPostArray($_POST['src']);
		$this->arrayUpdateRecursive($original, $update, $toRemove);
		$this->getConfigPartFrmDataForm($original, $identifier);
		if (\count($toRemove) > 0) {
			$this->jquery->execAtLast("$('[name=_toDelete]').closest('.ui.dropdown').dropdown('set selected'," . \json_encode($toRemove) . ");");
		}
		$this->jquery->renderView('@framework/main/component.html');
	}

	private function evalPostArray($post):array{
		$filename=\ROOT.'cache/config/tmp.cache.php';
		\file_put_contents($filename,"<?php return $post;");
		return include $filename;
	}

	private function getConfigSourcePart($original, $title, $icon) {
		$toDelete = URequest::post('_toDelete','');
		$toRemove = \explode(',', $toDelete);
		$update = $this->getConfigPartFromPost($original);
		$this->arrayUpdateRecursive($original, $update, $toRemove, '', true);
		$src = UArray::asPhpArray($original, "array", 1, true);
		$frm = $this->jquery->semantic()->htmlForm('frm-source');
		$frm->addContent("<div class='ui ribbon blue label'><i class='ui $icon icon'></i> $title</div><br>");
		$textarea = $frm->addTextarea('src', '', $src, null, 20);
		$frm->addInput('toDeleteSrc', null, 'hidden', $toDelete);
		$frm->setLibraryId('_compo_');
		$textarea->getDataField()->setProperty('data-editor', true);
		$this->_getAdminViewer()->insertAce();

		$this->jquery->renderView('@framework/main/component.html');
	}
}

