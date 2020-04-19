<?php
%namespace%
use Hybridauth\Adapter\AdapterInterface;

 /**
 * Controller %controllerName%
 **/
class %controllerName% extends %baseClass%{

	public function index(){
	}
	
	/**
	* @get("%route%/{name}")
	**/
	public function _oauth(string $name):void {
		parent::_oauth($name);
	}
	
	protected function onConnect(string $name,AdapterInterface $provider){
		//TODO Use $provider->getUserProfile() to get user profile
	}
}
