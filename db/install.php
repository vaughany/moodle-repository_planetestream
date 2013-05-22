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

function xmldb_repository_planetestream_install() {
    global $CFG;
    $result=true;
    require_once($CFG->dirroot.'/repository/lib.php');
    $planetestreamplugin=new repository_type('planetestream', array(), true);
    if(!$id=$planetestreamplugin->create(true)) {
        $result=false;
    }
    return $result;
}
