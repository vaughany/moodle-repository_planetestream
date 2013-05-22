<?php

/**
 * Planet eStream v5 Repository Plugin
 *
 * @since 2.0
 * @package    repository_planetestream
 * @copyright  2012 Planet eStream
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

defined('MOODLE_INTERNAL') || die();

$capabilities=array(

    'repository/planetestream:view' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'user' => CAP_ALLOW
        )
    )
);
