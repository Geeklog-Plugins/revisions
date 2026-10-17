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
if (isset($_POST['action']) && $_POST['action'] === 'reconcile') {
    SEC_checkToken();
    $id = isset($_POST['sp_id']) ? COM_sanitizeID((string) $_POST['sp_id']) : '';
    $idSql = DB_escapeString($id);
    $q = DB_query("SELECT owner_id,group_id,perm_owner,perm_group,perm_members,perm_anon FROM {$_TABLES['staticpage']} WHERE sp_id='$idSql' LIMIT 1", 1);
    $tracking = DB_query("SELECT sp_id FROM {$_TABLES['revisions_baseline']} WHERE sp_id='$idSql' LIMIT 1", 1);
    if ($id === '' || !SEC_hasRights('staticpages.edit') || !$q || DB_numRows($q) !== 1 ||
        !REVISIONS_ADMIN_canEdit(DB_fetchArray($q)) || !$tracking || DB_numRows($tracking) !== 1) {
        $message = 'No tracked page found or insufficient permissions.';
    } else {
        $message = REVISIONS_reconcilePage($id)
            ? 'Tracking checked. Check recent versions below (author 0 indicates recovered edit).'
            : 'Page is not eligible for tracking.';
    }
}

// Read-only revision detail: verify the current page ACL before revealing historical content.
$revisionDetail = '';
if (isset($_GET['revision_id']) && ctype_digit((string) $_GET['revision_id'])) {
    $rid = (int) $_GET['revision_id'];
    $res = DB_query("SELECT revision_id,sp_id,snapshot_json,content_hash,actor_id,created_at "
        . "FROM {$_TABLES['revisions_history']} WHERE revision_id=$rid LIMIT 1",1);
    if ($res && DB_numRows($res) === 1) {
        $rev = DB_fetchArray($res);
        $escapedId = DB_escapeString($rev['sp_id']);
        $aclRes = DB_query("SELECT owner_id,group_id,perm_owner,perm_group,perm_members,perm_anon "
            . "FROM {$_TABLES['staticpage']} WHERE sp_id='$escapedId' LIMIT 1",1);
        if ($aclRes && DB_numRows($aclRes) === 1 && SEC_hasRights('staticpages.edit')
            && REVISIONS_ADMIN_canEdit(DB_fetchArray($aclRes))) {
            $old = json_decode($rev['snapshot_json'], true);
            $current = REVISIONS_snapshot($rev['sp_id']);
            if (is_array($old) && hash('sha256', $rev['snapshot_json']) === $rev['content_hash']) {
                $revisionDetail .= '<section style="border:1px solid #ccc;padding:16px;margin:24px 0">'
                    . '<h3>Revision #' . R_H($rid) . ' — read-only</h3>'
                    . '<p>Page ' . R_H($rev['sp_id']) . ' · ' . R_H($rev['created_at']) . '</p>'
                    . '<p>Historical source is escaped: HTML, PHP and autotags are never executed.</p>'
                    . '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px">';
                foreach (array('sp_title'=>'Title','sp_content'=>'Page content','meta_description'=>'Meta description') as $field=>$label) {
                    $oldValue = isset($old[$field]) ? (string)$old[$field] : '';
                    $currentValue = is_array($current) && isset($current[$field]) ? (string)$current[$field] : '';
                    $revisionDetail .= '<div><h4>' . R_H($label) . '</h4><strong>Archived version</strong>'
                        . '<pre style="white-space:pre-wrap;overflow-wrap:anywhere;border:1px solid #ddd;padding:10px">'
                        . R_H($oldValue) . '</pre><strong>Current version</strong>'
                        . '<pre style="white-space:pre-wrap;overflow-wrap:anywhere;border:1px solid #ddd;padding:10px">'
                        . R_H($currentValue) . '</pre></div>';
                }
                $revisionDetail .= '</div><p>No restore action is provided in this pilot.</p></section>';
            } else {
                $message = 'Archived snapshot could not be verified.';
            }
        } else {
            $message = 'You are not allowed to view this page history.';
        }
    }
}
$out = '<h2>Revisions ' . R_H(plugin_chkVersion_revisions()) . ' — Static Pages pilot</h2><p>Read-only history. Initialize an existing simple Static Page BEFORE editing it. PHP/template pages are excluded. No restore function yet.</p>';
if ($message !== '') { $out .= '<p><strong>' . R_H($message) . '</strong></p>'; }
$token = SEC_createToken();
$out .= '<form method="post" action="' . R_H($_CONF['site_admin_url'] . '/plugins/revisions/index.php') . '">'
    . '<div style="display:flex;flex-wrap:wrap;align-items:end;gap:12px;margin:16px 0 24px">'
    . '<label for="revisions-sp-id" style="display:flex;flex-direction:column;gap:6px;font-weight:600">'
    . 'Static Page ID'
    . '<input id="revisions-sp-id" type="text" name="sp_id" maxlength="128" '
    . 'style="width:min(100%,420px);min-width:260px;padding:9px" required>'
    . '</label>'
    . '<input type="hidden" name="action" value="baseline">'
    . '<input type="hidden" name="' . R_H(CSRF_TOKEN) . '" value="' . R_H($token) . '">'
    . '<button type="submit" style="padding:10px 18px">Start tracking</button>'
    . '</div></form>';
$q = DB_query("SELECT revision_id,sp_id,actor_id,created_at FROM {$_TABLES['revisions_history']} ORDER BY revision_id DESC LIMIT 50",1);
$tracked = DB_query("SELECT sp_id,updated_at FROM {$_TABLES['revisions_baseline']} ORDER BY updated_at DESC LIMIT 50", 1);
$out .= '<h3>Tracked pages</h3><p>Use Check changes to recover saves not recorded by the automatic callback.</p>';
$out .= '<p><small>Tracking table: ' . R_H($_TABLES['revisions_baseline']) . '; History table: ' . R_H($_TABLES['revisions_history']) . '</small></p>';
if ($tracked && DB_numRows($tracked) > 0) {
    $out .= '<table><thead><tr><th>Page ID</th><th>Last tracking update</th><th>Check changes</th></tr></thead><tbody>';
    while ($t = DB_fetchArray($tracked)) {
        $token = SEC_createToken();
        $out .= '<tr><td>' . R_H($t['sp_id']) . '</td><td>' . R_H($t['updated_at']) . '</td><td>'
            . '<form method="post" action="' . R_H($_CONF['site_admin_url'] . '/plugins/revisions/index.php') . '">'
            . '<input type="hidden" name="sp_id" value="' . R_H($t['sp_id']) . '">'
            . '<input type="hidden" name="action" value="reconcile">'
            . '<input type="hidden" name="' . R_H(CSRF_TOKEN) . '" value="' . R_H($token) . '">'
            . '<button type="submit">Check changes</button></form></td></tr>';
    }
    $out .= '</tbody></table>';
} else {
    $out .= '<p><strong>No pages are being tracked yet.</strong> Add the exact existing Static Page ID above and verify the confirmation message. If the message reports an error, inspect Geeklog error.log for SQL details.</p>';
}
$out .= $revisionDetail;
$out .= '<h3>Recent versions</h3><table><thead><tr><th>Revision</th><th>Page</th><th>Author</th><th>Date</th></tr></thead><tbody>';
if ($q) { while ($r=DB_fetchArray($q)) {
    $detailUrl = $_CONF['site_admin_url'] . '/plugins/revisions/index.php?revision_id=' . (int) $r['revision_id'];
    $out .= '<tr><td><a href="' . R_H($detailUrl) . '">#'.R_H($r['revision_id']).' — View</a></td><td>'.R_H($r['sp_id']).'</td><td>'.R_H($r['actor_id']).'</td><td>'.R_H($r['created_at']).'</td></tr>';
} }
$out .= '</tbody></table><p>Snapshots are stored in database. Select View to compare archived source and current source.</p>';
COM_output(COM_createHTMLDocument($out,array('pagetitle'=>'Revisions')));
