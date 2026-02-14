<?php
/**
 * Queue Manager
 *
 * Provides a unified interface for background job scheduling.
 * Uses Action Scheduler when available, falls back to WP-Cron.
 *
 * @package BrokenLinkChecker
 * @since 1.1.0
 */

defined('ABSPATH') || exit;

/**
 * Class AWLDLC_Queue_Manager
 *
 * Abstraction layer for background job scheduling
 */
class AWLDLC_Queue_Manager
{
    /**
     * Check if Action Scheduler is available
     *
     * @return bool
     */
    public static function has_action_scheduler()
    {
        return function_exists('as_schedule_single_action') && function_exists('as_has_scheduled_action');
    }

    /**
     * Get the current queue method being used
     *
     * @return string 'action_scheduler' or 'wp_cron'
     */
    public static function get_method()
    {
        return self::has_action_scheduler() ? 'action_scheduler' : 'wp_cron';
    }

    /**
     * Schedule a single action to run once
     *
     * @param int    $timestamp When to run (Unix timestamp)
     * @param string $hook      Action hook name
     * @param array  $args      Arguments to pass to the action
     * @param string $group     Group name for organization
     * @return int|bool Action ID on success (AS) or true (WP-Cron), false on failure
     */
    public static function schedule_single($timestamp, $hook, $args = array(), $group = 'blc')
    {
        if (self::has_action_scheduler()) {
            // Use Action Scheduler
            return as_schedule_single_action($timestamp, $hook, $args, $group);
        } else {
            // Fallback to WP-Cron
            return wp_schedule_single_event($timestamp, $hook, $args);
        }
    }

    /**
     * Schedule a recurring action
     *
     * @param int    $timestamp      When to start (Unix timestamp)
     * @param int    $interval       Interval in seconds
     * @param string $hook           Action hook name
     * @param array  $args           Arguments to pass to the action
     * @param string $group          Group name for organization
     * @return int|bool Action ID on success (AS), true (WP-Cron) or false on failure
     */
    public static function schedule_recurring($timestamp, $interval, $hook, $args = array(), $group = 'blc')
    {
        if (self::has_action_scheduler()) {
            // Use Action Scheduler
            return as_schedule_recurring_action($timestamp, $interval, $hook, $args, $group);
        } else {
            // Fallback to WP-Cron
            $schedules = wp_get_schedules();

            // Find matching schedule or use closest one
            $schedule_name = 'hourly'; // default
            foreach ($schedules as $name => $schedule) {
                if (isset($schedule['interval']) && $schedule['interval'] <= $interval) {
                    $schedule_name = $name;
                }
            }

            return wp_schedule_event($timestamp, $schedule_name, $hook, $args);
        }
    }

    /**
     * Schedule an async action (run as soon as possible)
     *
     * @param string $hook  Action hook name
     * @param array  $args  Arguments to pass to the action
     * @param string $group Group name for organization
     * @return int|bool Action ID or true on success, false on failure
     */
    public static function schedule_async($hook, $args = array(), $group = 'blc')
    {
        if (self::has_action_scheduler()) {
            // Use Action Scheduler async
            return as_enqueue_async_action($hook, $args, $group);
        } else {
            // Fallback: schedule for 5 seconds from now
            return wp_schedule_single_event(time() + 5, $hook, $args);
        }
    }

    /**
     * Check if an action is already scheduled
     *
     * @param string $hook  Action hook name
     * @param array  $args  Arguments (optional, for more specific check)
     * @param string $group Group name (AS only)
     * @return bool
     */
    public static function is_scheduled($hook, $args = null, $group = 'blc')
    {
        if (self::has_action_scheduler()) {
            return as_has_scheduled_action($hook, $args, $group);
        } else {
            return (bool) wp_next_scheduled($hook, $args ?: array());
        }
    }

    /**
     * Cancel/unschedule an action
     *
     * @param string $hook  Action hook name
     * @param array  $args  Arguments
     * @param string $group Group name (AS only)
     * @return int|null Number of actions cancelled (AS) or null (WP-Cron)
     */
    public static function cancel($hook, $args = array(), $group = 'blc')
    {
        if (self::has_action_scheduler()) {
            return as_unschedule_all_actions($hook, $args, $group);
        } else {
            $timestamp = wp_next_scheduled($hook, $args);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook, $args);
            }
            return null;
        }
    }

    /**
     * Cancel all actions for a specific group
     *
     * @param string $group Group name
     * @return int|null Number cancelled (AS) or null (WP-Cron)
     */
    public static function cancel_group($group = 'blc')
    {
        if (self::has_action_scheduler()) {
            // Get all pending actions for this group and cancel them
            $actions = as_get_scheduled_actions(array(
                'group' => $group,
                'status' => ActionScheduler_Store::STATUS_PENDING,
                'per_page' => -1,
            ), 'ids');

            foreach ($actions as $action_id) {
                as_unschedule_action($action_id);
            }

            return count($actions);
        }

        return null;
    }

    /**
     * Get pending actions count for a hook
     *
     * @param string $hook  Action hook name
     * @param string $group Group name (AS only)
     * @return int
     */
    public static function get_pending_count($hook, $group = 'blc')
    {
        if (self::has_action_scheduler()) {
            $actions = as_get_scheduled_actions(array(
                'hook' => $hook,
                'group' => $group,
                'status' => ActionScheduler_Store::STATUS_PENDING,
                'per_page' => -1,
            ), 'ids');

            return count($actions);
        }

        // WP-Cron doesn't track pending count
        return 0;
    }

    /**
     * Get queue status info for admin display
     *
     * @return array
     */
    public static function get_status()
    {
        $status = array(
            'method' => self::get_method(),
            'method_label' => self::has_action_scheduler()
                ? __('Action Scheduler', 'dead-link-checker')
                : __('WP-Cron', 'dead-link-checker'),
            'is_reliable' => self::has_action_scheduler(),
        );

        if (self::has_action_scheduler()) {
            $status['pending_actions'] = self::get_pending_count('awldlc_process_queue');
            $status['version'] = defined('ActionScheduler_Versions::LATEST_VERSION')
                ? ActionScheduler_Versions::LATEST_VERSION
                : __('Unknown', 'dead-link-checker');
        } else {
            $status['pending_actions'] = 0;
            $status['note'] = __('Install WooCommerce or Action Scheduler plugin for more reliable background processing.', 'dead-link-checker');
        }

        return $status;
    }
}
