<?php
namespace Ubiquity\controllers\admin;

use Ubiquity\controllers\admin\traits\UrlsTrait;

class UbiquityMyAdminFiles {
	use UrlsTrait;

	private $viewBase = "@admin";

	public function getAdminBaseRoute() {
		return "/Admin";
	}

	public function getViewDataIndex() {
		return $this->viewBase . "/data/index.html";
	}

	public function getViewRoutesIndex() {
		return $this->viewBase . "/routes/index.html";
	}

	public function getViewRestIndex() {
		return $this->viewBase . "/rest/index.html";
	}

	public function getViewLogsIndex() {
		return $this->viewBase . "/logs/index.html";
	}

	public function getViewSeoIndex() {
		return $this->viewBase . "/seo/index.html";
	}

	public function getViewTranslateIndex() {
		return $this->viewBase . "/translate/index.html";
	}

	public function getViewRestFormTester() {
		return $this->viewBase . "/rest/formTester.html";
	}

	public function getViewCacheIndex() {
		return $this->viewBase . "/cache/index.html";
	}

	public function getViewControllersIndex() {
		return $this->viewBase . "/controllers/index.html";
	}

	public function getViewControllersFiltering() {
		return $this->viewBase . "/controllers/formFiltering.html";
	}

	public function getViewAddCrudController() {
		return $this->viewBase . "/controllers/formCrudController.html";
	}

	public function getViewAddIndexCrudController() {
		return $this->viewBase . "/controllers/formIndexCrudController.html";
	}

	public function getViewAddAuthController() {
		return $this->viewBase . "/controllers/formAuthController.html";
	}

	public function getViewConfigIndex() {
		return $this->viewBase . "/config/index.html";
	}

	public function getViewConfigRead() {
		return $this->viewBase . "/config/configRead.html";
	}
	
	public function getViewConfigForm() {
		return $this->viewBase . "/config/form.html";
	}

	public function getViewEnvForm() {
		return $this->viewBase . "/config/formEnv.html";
	}

	public function getViewFrmNewDbConnection() {
		return $this->viewBase . "/config/formNewDbConnection.html";
	}

	public function getViewIndex() {
		return $this->viewBase . "/index.html";
	}

	public function getViewIndexCustomizing() {
		return $this->viewBase . "/customize.html";
	}

	public function getViewShowTable() {
		return $this->viewBase . "/data/showTable.html";
	}

	public function getViewEditTable() {
		return $this->viewBase . "/data/editTable.html";
	}

	public function getViewHeader() {
		return $this->viewBase . "/main/vHeader.html";
	}

	public function getViewClassesDiagram() {
		return $this->viewBase . "/data/diagClasses.html";
	}

	public function getViewYumlReverse() {
		return $this->viewBase . "/data/yumlReverse.html";
	}

	public function getViewDatabaseIndex() {
		return $this->viewBase . "/database/index.html";
	}

	public function getViewDatabaseCreate() {
		return $this->viewBase . "/database/create.html";
	}

	public function getViewDatabaseMigrate() {
		return $this->viewBase . "/database/migrate.html";
	}

	public function getViewDatasExport() {
		return $this->viewBase . "/database/export.html";
	}

	public function getViewSeoDetails() {
		return $this->viewBase . "/seo/seoDetails.html";
	}

	public function getViewGitIndex() {
		return $this->viewBase . "/git/index.html";
	}

	public function getViewGitSettings() {
		return $this->viewBase . "/git/formSettings.html";
	}

	public function getViewGitIgnore() {
		return $this->viewBase . "/git/formGitIgnore.html";
	}

	public function getViewGitTabsRefresh() {
		return $this->viewBase . "/git/gitTabs.html";
	}

	public function getViewGitCmdFrm() {
		return $this->viewBase . "/git/execGitCmdFrm.html";
	}

	public function getViewThemesIndex() {
		return $this->viewBase . "/themes/index.html";
	}

	public function getViewMaintenanceIndex() {
		return $this->viewBase . "/maintenance/index.html";
	}

	public function getViewMailerIndex() {
		return $this->viewBase . "/mailer/index.html";
	}

	public function getViewComposerIndex() {
		return $this->viewBase . "/composer/index.html";
	}

	public function getViewComposerFrm() {
		return $this->viewBase . "/composer/updateComposerFrm.html";
	}

	public function getViewExecComposer() {
		return $this->viewBase . "/composer/execComposer.html";
	}

	public function getViewAddDependencyFrm() {
		return $this->viewBase . "/composer/addDependencyFrm.html";
	}

	public function getViewMailerDefinePeriod() {
		return $this->viewBase . "/mailer/sendDelay.html";
	}

	public function getViewNewMailerFrm() {
		return $this->viewBase . "/mailer/formNewMailer.html";
	}

	public function getViewSeeMail() {
		return $this->viewBase . "/mailer/seeMail.html";
	}

	public function getViewSeeMailForm() {
		return $this->viewBase . "/mailer/seeMailForm.html";
	}

	public function getViewMailerConfig() {
		return $this->viewBase . "/mailer/mailerConfig.html";
	}

	public function getViewOAuthIndex() {
		return $this->viewBase . "/oauth/index.html";
	}

	public function getViewOAuthTest() {
		return $this->viewBase . "/oauth/testConnected.html";
	}

	public function getProviderFrm() {
		return $this->viewBase . "/oauth/providerFrm.html";
	}

	public function getOAuthConfigFrm() {
		return $this->viewBase . "/oauth/configFrm.html";
	}

	public function getViewAddOAuthController() {
		return $this->viewBase . "/oauth/oauthControllerFrm.html";
	}

	public function getViewSecurityIndex() {
		return $this->viewBase . "/security/index.html";
	}

	public function getViewSecurityPart() {
		return $this->viewBase . "/security/securityPart.html";
	}

	public function getViewCommandsIndex() {
		return $this->viewBase . "/commands/index.html";
	}

	public function getViewDisplayCommandForm() {
		return $this->viewBase . "/commands/display.html";
	}

	public function getViewDisplayMyCommands() {
		return $this->viewBase . "/commands/myCommands.html";
	}

	public function getViewCommandSuiteFrm() {
		return $this->viewBase . "/commands/commandSuiteFrm.html";
	}

	public function getViewAclsIndex() {
		return $this->viewBase . "/acls/index.html";
	}

	public function getViewDomainForm(){
		return $this->viewBase . "/config/domainFrm.html";
	}
}
