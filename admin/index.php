<?php
require_once '../../../lib-common.php';
require_once '../../auth.inc.php';
if (!SEC_inGroup('Root') || !SEC_hasRights('revisions.admin')) {
    COM_output(COM_createHTMLDocument('Access denied'));
    exit;
}
function REVISIONS_ADMIN_canEdit($r) {
    return SEC_hasAccess($r['owner_id'], $r['group_id'], $r['perm_owner'], $r['perm_group'], $r['perm_members'], $r['perm_anon']) >= 3;
}
function R_H($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
global $_TABLES, $_CONF;
$message = '';
if (isset($_POST['action']) && $_POST['action'] === 'baseline') {
    if (!SEC_checkToken()) { $message='Security token invalid.'; }
    else {
        $id = isset($_POST['sp_id']) ? trim((string)$_POST['sp_id']) : '';
        if ($id === '' || !SEC_hasRights('staticpages.edit')) { $message='Not authorized.'; }
        else {
            $id = COM_sanitizeID($id);
            $row = DB_query("SELECT owner_id,group_id,perm_owner,perm_group,perm_members,perm_anon FROM {$_TABLES['staticpage']} WHERE sp_id='" . DB_escapeString($id) . "'",1);
            if (!$row || DB_numRows($row) !== 1 || !REVISIONS_ADMIN_canEdit(DB_fetchArray($row))) {
                $message='Cannot initialize page; check page and access.';
            } else {
                $message=REVISIONS_initialize($id) ? 'Tracking initialized (or already active).' : 'Page not eligible or initialization failed.';
            }
        }
    }
}
$out = '<h2>Revisions 0.1.0 — Static Pages pilot</h2><p>Read-only history. Initialize an existing simple Static Page BEFORE editing it. PHP/template pages are excluded. No restore function yet.</p>';
if ($message !== '') { $out .= '<p><strong>' . R_H($message) . '</strong></p>'; }
$out .= '<form method="post"><label>Static Page ID <input name="sp_id" required></label><input type="hidden" name="action" value="baseline">' . SEC_createToken() . '<button type="submit">Start tracking</button></form>';
$q = DB_query("SELECT revision_id,sp_id,actor_id,created_at FROM {$_TABLES['revisions_history']} ORDER BY revision_id DESC LIMIT 50",1);
$out .= '<h3>Recent versions</h3><table><thead><tr><th>Revision</th><th>Page</th><th>Author</th><th>Date</th></tr></thead><tbody>';
if ($q) { while ($r=DB_fetchArray($q)) {
    $out .= '<tr><td>'.R_H($r['revision_id']).'</td><td>'.R_H($r['sp_id']).'</td><td>'.R_H($r['actor_id']).'</td><td>'.R_H($r['created_at']).'</td></tr>';
} }
$out .= '</tbody></table><p>Snapshots are stored in database. This experimental admin view shows metadata only.</p>';
COM_output(COM_createHTMLDocument($out,array('pagetitle'=>'Revisions')));
