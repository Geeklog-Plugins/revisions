<?php

/*
 * Revisions Plugin 0.2.4
 * File: autoinstall.php
 * Purpose: Geeklog installation metadata and compatibility hooks
 * Copyright (C) 2026
 * Author: Ben hostellerie DOT org AT gmail DOT com
 * License: GNU General Public License, version 2 or later (see LICENSE).
 * Target: Geeklog 2.2.2; PHP 5.6.40 and later.
 */

/**
 * Describe Geeklog groups, features and plugin-owned tables.
 * @param string $pi_name Plugin name.
 * @return array Plugin installation specification.
 */
function plugin_autoinstall_revisions($pi_name)
{
    return array(
        'info' => array('pi_name'=>'revisions','pi_display_name'=>'Revisions','pi_version'=>'0.2.4','pi_gl_version'=>'2.2.2','pi_homepage'=>'https://github.com/hostellerie/revisions'),
        'groups' => array('Revisions Admin'=>'Manage editorial revisions'),
        'features' => array('revisions.admin'=>'Manage revision history'),
        'mappings' => array('revisions.admin'=>array('Revisions Admin')),
        'tables' => array('revisions_baseline','revisions_history')
    );
}
/**
 * Check PHP and Geeklog minimum versions.
 * @param string $pi_name Plugin name.
 * @return bool True if compatible.
 */
function plugin_compatible_with_this_version_revisions($pi_name)
{
    return version_compare(PHP_VERSION,'5.6.40','>=') && (!defined('VERSION') || version_compare(VERSION,'2.2.2','>='));
}

/**
 * Geeklog 2.2.2 calls this after creating the plugin tables and registering it.
 * Seed all eligible existing Static Pages so tracking is on from installation.
 */
/**
 * Start tracking eligible existing Static Pages after installation.
 * @param string $pi_name Plugin name.
 * @return bool True when initial discovery succeeds.
 */
function plugin_postinstall_revisions($pi_name)
{
    global $_CONF;
    require_once $_CONF['path'] . 'plugins/revisions/functions.inc';
    return REVISIONS_seedAllPages() !== false;
}
