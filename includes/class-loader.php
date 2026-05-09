<?php
/**
 * Registers and runs WordPress action and filter hooks.
 *
 * @package PDMI\Onderhoud
 */

namespace PDMI\Under\Construction;

defined( 'ABSPATH' ) || exit;

/**
 * Collects hook registrations and applies them in a single pass.
 */
class Loader {

	/**
	 * Queued action hooks.
	 *
	 * @var array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}>
	 */
	protected array $actions = array();

	/**
	 * Queued filter hooks.
	 *
	 * @var array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}>
	 */
	protected array $filters = array();

	/**
	 * Queues an action hook for registration.
	 *
	 * @param string $hook          WordPress hook name.
	 * @param object $component     Object that owns the callback method.
	 * @param string $callback      Name of the callback method.
	 * @param int    $priority      Hook priority. Default 10.
	 * @param int    $accepted_args Number of arguments passed to the callback. Default 1.
	 * @return void
	 */
	public function add_action( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Queues a filter hook for registration.
	 *
	 * @param string $hook          WordPress hook name.
	 * @param object $component     Object that owns the callback method.
	 * @param string $callback      Name of the callback method.
	 * @param int    $priority      Hook priority. Default 10.
	 * @param int    $accepted_args Number of arguments passed to the callback. Default 1.
	 * @return void
	 */
	public function add_filter( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Registers all queued hooks with WordPress.
	 *
	 * @return void
	 */
	public function run(): void {
		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
	}
}
