<?php

/**
 * Register a callback before the WordPress test environment loads.
 *
 * @param string   $hook_name     Hook name.
 * @param callable $callback      Hook callback.
 * @param int      $priority      Hook priority.
 * @param int      $accepted_args Accepted argument count.
 *
 * @return true
 */
function tests_add_filter( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
    return true;
}
