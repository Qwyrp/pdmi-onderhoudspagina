<?php
/**
 * Loader class responsible for registering WordPress hooks.
 *
 * @package PDMI\Under\Construction
 */

namespace PDMI\Under\Construction;

defined( 'ABSPATH' ) || exit;

/**
 * Manages WordPress hooks for the plugin.
 */
class Loader {

	/**
	 * Actions to register.
	 *
	 * @var array
	 */
	protected $actions = array();

	/**
	 * Filters to register.
	 *
	 * @var array
	 */
	protected $filters = array();

	/**
	 * Adds an action hook to the queue.
	 *
	 * @param string   $hook          Hook name.
	 * @param object   $component     Component instance.
	 * @param string   $callback      Callback method.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted args.
	 *
	 * @return void
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Adds a filter hook to the queue.
	 *
	 * @param string   $hook          Hook name.
	 * @param object   $component     Component instance.
	 * @param string   $callback      Callback method.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted args.
	 *
	 * @return void
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Runs through the hooks and registers them with WordPress.
	 *
	 * @return void
	 */
	public function run() {
		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
	}
}

