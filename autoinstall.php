<?php
function plugin_autoinstall_revisions($pi_name)
{
    return array(
        'info' => array('pi_name'=>'revisions','pi_display_name'=>'Revisions','pi_version'=>'0.2.0','pi_gl_version'=>'2.2.2','pi_homepage'=>'https://github.com/hostellerie/revisions'),
        'groups' => array('Revisions Admin'=>'Manage editorial revisions'),
        'features' => array('revisions.admin'=>'Manage revision history'),
        'mappings' => array('revisions.admin'=>array('Revisions Admin')),
        'tables' => array('revisions_baseline','revisions_history')
    );
}
function plugin_compatible_with_this_version_revisions($pi_name)
{
    return version_compare(PHP_VERSION,'5.6.40','>=') && (!defined('VERSION') || version_compare(VERSION,'2.2.2','>='));
}
