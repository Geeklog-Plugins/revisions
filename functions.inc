<?php
/* Revisions 0.2.0: Static Pages history pilot, PHP 5.6-compatible syntax. */
global $_CONF, $_TABLES, $_DB_table_prefix;
$_TABLES['revisions_baseline'] = $_DB_table_prefix . 'revisions_baseline';
$_TABLES['revisions_history'] = $_DB_table_prefix . 'revisions_history';
function plugin_getadminoption_revisions()
{
    global $_CONF;
    if (!SEC_hasRights('revisions.admin')) { return null; }
    return array('Revisions', $_CONF['site_admin_url'] . '/plugins/revisions/index.php', 0);
}
function plugin_cclabel_revisions()
{
    global $_CONF;
    if (!SEC_hasRights('revisions.admin')) { return array(); }
    return array('Revisions', $_CONF['site_admin_url'] . '/plugins/revisions/index.php');
}
function plugin_chkVersion_revisions()
{
    global $_CONF;
    require_once $_CONF['path'] . 'plugins/revisions/autoinstall.php';
    $spec = plugin_autoinstall_revisions('revisions');
    return $spec['info']['pi_version'];
}
/**
 * Upgrade metadata only when required history storage is present.
 * 0.1.0 -> 0.2.0 introduces no database schema changes.
 */
function plugin_upgrade_revisions()
{
    global $_CONF, $_TABLES;
    require_once $_CONF['path'] . 'plugins/revisions/autoinstall.php';
    if (!plugin_compatible_with_this_version_revisions('revisions')) {
        COM_errorLog('Revisions: incompatible Geeklog/PHP version during upgrade.');
        return false;
    }
    if (!DB_checkTableExists('revisions_baseline') ||
        !DB_checkTableExists('revisions_history')) {
        COM_errorLog('Revisions: required history tables missing; upgrade aborted.');
        return false;
    }
    // Initialize every existing eligible page before advertising upgrade success.
    if (REVISIONS_seedAllPages() === false) {
        COM_errorLog('Revisions: auto-enrollment failed; upgrade not marked complete.');
        return false;
    }
    $spec = plugin_autoinstall_revisions('revisions');
    $version = DB_escapeString($spec['info']['pi_version']);
    $gl = DB_escapeString($spec['info']['pi_gl_version']);
    $sql = "UPDATE {$_TABLES['plugins']} SET pi_version='$version', "
         . "pi_gl_version='$gl' WHERE pi_name='revisions'";
    if (!DB_query($sql, 1)) {
        COM_errorLog('Revisions: failed updating Geeklog plugin version metadata.');
        return false;
    }
    $stored = DB_getItem($_TABLES['plugins'], 'pi_version', "pi_name='revisions'");
    if ($stored !== $spec['info']['pi_version']) {
        COM_errorLog('Revisions: stored plugin version differs after upgrade.');
        return false;
    }
    return true;
}
function REVISIONS_seedAllPages()
{
    global $_TABLES;
    $q = DB_query("SELECT sp_id FROM {$_TABLES['staticpage']} ORDER BY sp_id", 1);
    if (!$q) { COM_errorLog('Revisions: unable to list Static Pages for automatic tracking.'); return false; }
    $added = 0;
    while ($row = DB_fetchArray($q)) {
        $id = $row['sp_id'];
        $key = DB_escapeString($id);
        $exists = DB_query("SELECT sp_id FROM {$_TABLES['revisions_baseline']} WHERE sp_id='$key' LIMIT 1", 1);
        if (!$exists) { COM_errorLog('Revisions: tracking lookup failed.'); return false; }
        if (DB_numRows($exists) === 0) {
            $snapshot = REVISIONS_snapshot($id);
            if ($snapshot !== false && REVISIONS_initialize($id)) { $added++; }
        }
    }
    return $added;
}
function REVISIONS_fields()
{
    return array('sp_title','sp_page_title','sp_content','page_data','postmode','meta_description',
        'meta_keywords','structured_data_type','sp_format','draft_flag','template_flag','template_id',
        'sp_php','sp_prev','sp_next','sp_parent','sp_centerblock','sp_where','sp_inblock',
        'sp_onmenu','sp_label','sp_help','sp_nf','search');
}
function REVISIONS_extract($row)
{
    $out = array();
    foreach (REVISIONS_fields() as $field) { $out[$field] = isset($row[$field]) ? $row[$field] : null; }
    return $out;
}
function REVISIONS_snapshot($id)
{
    global $_TABLES;
    $id = DB_escapeString($id);
    $q = DB_query("SELECT * FROM {$_TABLES['staticpage']} WHERE sp_id='$id' LIMIT 1", 1);
    if (!$q || DB_numRows($q) !== 1) { return false; }
    $row = DB_fetchArray($q);
    // PHP and template pages require a provider source adapter; fail closed.
    if (!empty($row['sp_php']) || !empty($row['template_flag']) || !empty($row['template_id'])) { return false; }
    return REVISIONS_extract($row);
}
function REVISIONS_encode($snapshot)
{
    $encoded = json_encode($snapshot);
    return $encoded === false ? false : $encoded;
}
function REVISIONS_baseline($id, $snapshot)
{
    global $_TABLES;
    $encoded = REVISIONS_encode($snapshot);
    if ($encoded === false) { return false; }
    $key = DB_escapeString($id);
    $value = DB_escapeString($encoded);
    $sha = hash('sha256',$encoded);
    return (bool) DB_query("REPLACE INTO {$_TABLES['revisions_baseline']} (sp_id,snapshot_json,content_hash,updated_at) VALUES ('$key','$value','$sha',NOW())",1);
}
function REVISIONS_initialize($id)
{
    global $_TABLES;
    $idEscaped = DB_escapeString($id);
    $snap = REVISIONS_snapshot($id);
    if ($snap === false) { return false; }
    $exists = DB_query("SELECT sp_id FROM {$_TABLES['revisions_baseline']} WHERE sp_id='$idEscaped' LIMIT 1",1);
    if ($exists && DB_numRows($exists) === 1) { return true; }
    if (!REVISIONS_baseline($id,$snap)) { return false; }
    // DB_query returning success is not sufficient: read back the durable baseline.
    $confirmed = DB_query("SELECT content_hash FROM {$_TABLES['revisions_baseline']} WHERE sp_id='$idEscaped' LIMIT 1",1);
    if (!$confirmed || DB_numRows($confirmed) !== 1) {
        COM_errorLog('Revisions: tracking initialization could not be verified for page ' . $id);
        return false;
    }
    $row = DB_fetchArray($confirmed);
    $encoded = REVISIONS_encode($snap);
    return $encoded !== false && isset($row['content_hash']) && $row['content_hash'] === hash('sha256', $encoded);
}
function plugin_itemsaved_revisions($id,$type,$old_id='',$sub_type='')
{
    global $_USER;
    $uid = isset($_USER['uid']) ? (int) $_USER['uid'] : 0;
    REVISIONS_captureChange($id,$type,$old_id,$uid);
}
function REVISIONS_captureChange($id,$type,$old_id,$uid)
{
    global $_TABLES;
    if ($type !== 'staticpages' || $id === '') { return; }
    // Opt-in baseline must be initialized before modification.
    $from = $old_id !== '' ? $old_id : $id;
    $fromEscaped = DB_escapeString($from);
    $q = DB_query("SELECT snapshot_json,content_hash FROM {$_TABLES['revisions_baseline']} WHERE sp_id='$fromEscaped' LIMIT 1",1);
    if (!$q) { return; }
    if (DB_numRows($q) !== 1) {
        // For new pages or unseeded pre-existing pages, start from the saved state.
        // Do not invent an earlier version: the post-save hook cannot see it.
        REVISIONS_initialize($id);
        return;
    }
    $old = DB_fetchArray($q);
    $snapshot = REVISIONS_snapshot($id);
    if ($snapshot === false) { return; }
    $nowJson = REVISIONS_encode($snapshot);
    if ($nowJson === false) { return; }
    $nowHash = hash('sha256',$nowJson);
    if ($old['content_hash'] !== $nowHash) {
        $uid = (int) $uid;
        $json = DB_escapeString($old['snapshot_json']);
        $qSave = DB_query("INSERT INTO {$_TABLES['revisions_history']} (sp_id,actor_id,snapshot_json,content_hash,created_at) VALUES ('" . DB_escapeString($id) . "',$uid,'$json','" . DB_escapeString($old['content_hash']) . "',NOW())",1);
        if (!$qSave) { COM_errorLog('Revisions: history insert failed. Baseline not advanced.'); return; }
    }
    if (!REVISIONS_baseline($id,$snapshot)) { COM_errorLog('Revisions: baseline write failed.'); return; }
    if ($old_id !== '' && $old_id !== $id) {
        DB_query("DELETE FROM {$_TABLES['revisions_baseline']} WHERE sp_id='" . DB_escapeString($old_id) . "'",1);
    }
}

/**
 * Read-only reconciliation trigger for a tracked page.
 * Only used by an explicitly authorized administrator action. History author 0
 * means the edit actor could not be established from this delayed scan.
 */
function REVISIONS_reconcilePage($id)
{
    $old = REVISIONS_snapshot($id);
    if ($old === false) { return false; }
    REVISIONS_captureChange($id, 'staticpages', '', 0);
    return true;
}
