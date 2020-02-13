<?php
namespace Ubiquity\controllers\admin\traits;

/**
 * Contains all methods returning the urls for CRUDControllers
 *
 * @author jc
 *        
 */
trait UrlsTrait {

	/**
	 * To override
	 * Returns the route for refreshing the index route (default : /_refresh_)
	 *
	 * @return string
	 */
	public function getRouteRefresh() {
		return "/_refresh_";
	}

	/**
	 * To override
	 * Returns the route for the detail route, when the user click on a dataTable row (default : /_showModelDetails)
	 *
	 * @return string
	 */
	public function getRouteDetails() {
		return "/_showModelDetails";
	}

	/**
	 * To override
	 * Returns the route for deleting an instance (default : /_deleteModel)
	 *
	 * @return string
	 */
	public function getRouteDelete() {
		return "/_deleteModel";
	}

	/**
	 * To override
	 * Returns the route for editing an instance (default : /_editModel)
	 *
	 * @return string
	 */
	public function getRouteEdit() {
		return "/_editModel";
	}

	/**
	 * To override
	 * Returns the route for displaying an instance (default : /display)
	 *
	 * @return string
	 */
	public function getRouteDisplay() {
		return "/display";
	}

	/**
	 * To override
	 * Returns the route for refreshing the dataTable (default : /_refreshTable)
	 *
	 * @return string
	 */
	public function getRouteRefreshTable() {
		return "/_refreshTable";
	}

	/**
	 * To override
	 * Returns the url associated with a foreign key instance in list
	 *
	 * @param string $model
	 * @return string
	 */
	public function getDetailClickURL($model) {
		return "";
	}
}

