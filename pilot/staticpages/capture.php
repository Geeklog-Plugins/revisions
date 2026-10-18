<?php
/**
 * Revisions Static Pages pilot — Geeklog 2.2.2, PHP 5.6+.
 * Staging-only; requires the explicit integration patch in README.
 * Captures the currently stored row before replacement; verifies after save.
 * Does NOT provide restoration or full atomicity across MyISAM.
 */
function REVSP_fields()
{
    return array('sp_title','sp_page_title','sp_content','page_data','postmode',
        'meta_description','meta_keywords','structured_data_type','sp_format',
        'draft_flag','template_flag','template_id','sp_php','sp_prev','sp_next',
        'sp_parent','sp_centerblock','sp_where','sp_inblock','sp_onmenu',
        'sp_label','sp_help','sp_nf','search');
}
function REVSP_snapshot($row)
{
    $filtered = array();
    foreach (REVSP_fields() as $name) {
        $filtered[$name] = isset($row[$name]) ? $row[$name] : null;
    }
    return $filtered;
}
function REVSP_load($spid)
{
    global $_TABLES;
    $id = DB_escapeString($spid);
    $q = DB_query("SELECT * FROM {$_TABLES['staticpage']} WHERE sp_id = '" . $id . "' LIMIT 1", 1);
    if (!$q || DB_numRows($q) < 1) { return false; }
    return REVSP_snapshot(DB_fetchArray($q));
}
function REVSP_hash($value)
{
    ksort($value);
    return hash('sha256', json_encode($value));
}
/** Return operation ID or false. Must be called before DB_save. */
function REVSP_prepare($oldId)
{
    global $_TABLES, $_USER;
    if ($oldId === '') { return false; } // Newly created page.
    $old = REVSP_load($oldId);
    if ($old === false) { return false; } // No preceding page.
    $payload = json_encode($old);
    if ($payload === false) {
        COM_errorLog('Revisions pilot: snapshot JSON encoding failed.');
        return false;
    }
    $op = hash('sha256', uniqid('', true) . ':' . $oldId . ':' . mt_rand());
    $id = DB_escapeString($oldId);
    $json = DB_escapeString($payload);
    $uid = isset($_USER['uid']) ? (int) $_USER['uid'] : 0;
    $checksum = REVSP_hash($old);
    $q = DB_query("INSERT INTO {$_TABLES['revisions_sp_pilot']} "
        . "(operation_id,sp_id,actor_id,old_hash,snapshot_json,status,created_at) VALUES "
        . "('$op','$id',$uid,'$checksum','$json','prepared',NOW())", 1);
    if (!$q) {
        COM_errorLog('Revisions pilot: failed to record snapshot intent.');
        return false;
    }
    return $op;
}
/** Called after DB_save and related save operations. */
function REVSP_finish($operationId, $newId)
{
    global $_TABLES;
    if ($operationId === false) { return; }
    $op = DB_escapeString($operationId);
    $q = DB_query("SELECT old_hash FROM {$_TABLES['revisions_sp_pilot']} "
        . "WHERE operation_id = '$op' AND status = 'prepared' LIMIT 1", 1);
    if (!$q || DB_numRows($q) !== 1) { return; }
    $row = DB_fetchArray($q);
    $new = REVSP_load($newId);
    if ($new === false) {
        // Preserve intent for investigation; do not claim successful revision.
        COM_errorLog('Revisions pilot: page not available after save.');
        return;
    }
    $newHash = REVSP_hash($new);
    $status = ($row['old_hash'] === $newHash) ? 'unchanged' : 'committed';
    $escapedId = DB_escapeString($newId);
    $result = DB_query("UPDATE {$_TABLES['revisions_sp_pilot']} "
        . "SET status = '$status', current_sp_id = '$escapedId', finished_at = NOW() "
        . "WHERE operation_id = '$op' AND status = 'prepared'", 1);
    if (!$result) { COM_errorLog('Revisions pilot: finalization failed.'); }
}
