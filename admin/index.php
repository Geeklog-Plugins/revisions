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
function REVISIONS_ADMIN_pageTitle($id) {
    global $_TABLES;
    $safe = DB_escapeString($id);
    $r = DB_query("SELECT sp_title FROM {$_TABLES['staticpage']} WHERE sp_id='$safe' LIMIT 1", 1);
    if ($r && DB_numRows($r) === 1) {
        $page = DB_fetchArray($r);
        if (isset($page['sp_title']) && trim($page['sp_title']) !== '') { return $page['sp_title']; }
    }
    return '(page unavailable)';
}
function REVISIONS_ADMIN_pageRevisionNumber($id, $globalRevisionId) {
    global $_TABLES;
    $safe = DB_escapeString($id);
    $rid = (int)$globalRevisionId;
    $q = DB_query("SELECT COUNT(*) AS total FROM {$_TABLES['revisions_history']} WHERE sp_id='$safe' AND revision_id<=$rid",1);
    if (!$q || DB_numRows($q) !== 1) { return null; }
    $row = DB_fetchArray($q);
    return (int)$row['total'];
}
function R_H($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function REVISIONS_ADMIN_lineDiff($before, $after)
{
    if ($before === $after) { return '<p>No line changes.</p>'; }
    // Diff is read-only and bounded to avoid expensive dynamic programming.
    if (strlen($before) > 50000 || strlen($after) > 50000) {
        return '<p>Content is too long for line comparison; use the source panels above.</p>';
    }
    $a = preg_split('/\\r\\n|\\r|\\n/', $before);
    $b = preg_split('/\\r\\n|\\r|\\n/', $after);
    if (count($a) > 160 || count($b) > 160) {
        return '<p>More than 160 lines: use the full source panels above.</p>';
    }
    $n = count($a); $m = count($b); $dp = array();
    for ($i = 0; $i <= $n; $i++) { $dp[$i] = array_fill(0, $m + 1, 0); }
    for ($i = $n - 1; $i >= 0; $i--) {
        for ($j = $m - 1; $j >= 0; $j--) {
            $dp[$i][$j] = ($a[$i] === $b[$j]) ? 1 + $dp[$i+1][$j+1] : max($dp[$i+1][$j], $dp[$i][$j+1]);
        }
    }
    $html = '<div class="revisions-line-diff" aria-label="Changes by line">';
    $i = 0; $j = 0; $changes = 0;
    while ($i < $n || $j < $m) {
        if ($i < $n && $j < $m && $a[$i] === $b[$j]) { $i++; $j++; continue; }
        if ($j < $m && ($i === $n || $dp[$i][$j+1] >= $dp[$i+1][$j])) {
            $html .= '<div class="revisions-line-added"><span>+ Added: </span><code>' . R_H($b[$j]) . '</code></div>';
            $j++;
        } else {
            $html .= '<div class="revisions-line-removed"><span>− Removed: </span><code>' . R_H($a[$i]) . '</code></div>';
            $i++;
        }
        $changes++;
    }
    return $html . '</div><p>' . (int)$changes . ' changed lines (added and removed).</p>';
}

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
                $localNumber = REVISIONS_ADMIN_pageRevisionNumber($rev['sp_id'], $rid);
                $revisionDetail .= '<section class="revisions-detail"><h2>Revision #' . R_H($localNumber === null ? '?' : $localNumber) . '</h2>'
                    . '<p>' . R_H(REVISIONS_ADMIN_pageTitle($rev['sp_id'])) . ' · ' . R_H($rev['created_at']) . '</p>'
                    . '<p>Archived fields — source values, not rendered HTML.</p>';
                foreach (array('sp_title'=>'Title','meta_description'=>'Meta description') as $field=>$label) {
                    $oldValue = isset($old[$field]) ? (string)$old[$field] : '';
                    $currentValue = is_array($current) && isset($current[$field]) ? (string)$current[$field] : '';
                    $revisionDetail .= '<div class="revisions-summary-field"><h3>' . R_H($label) . '</h3>'
                        . '<label>Archived</label><div class="revisions-value">' . R_H($oldValue) . '</div>'
                        . '<label>Current</label><div class="revisions-value">' . R_H($currentValue) . '</div></div>';
                }
                $rawHtml = isset($old['sp_content']) ? (string)$old['sp_content'] : '';
                $currentSource = is_array($current) && isset($current['sp_content']) ? (string)$current['sp_content'] : '';
                // Sandboxed browser preview: no scripts, same-origin, forms or navigation.
                // CSP also blocks external images, stylesheets, fonts and requests.
                // Never run Geeklog's autotag or PHP renderers on archived source.
                $previewDoc = '<!doctype html><html><head><meta charset="UTF-8">'
                    . '<meta http-equiv="Content-Security-Policy" content="default-src &#39;none&#39;; '
                    . 'script-src &#39;none&#39;; connect-src &#39;none&#39;; form-action &#39;none&#39;; '
                    . 'img-src data:; style-src &#39;unsafe-inline&#39;">'
                    . '<style>body{margin:1rem;font:16px/1.5 sans-serif;overflow-wrap:anywhere}'
                    . 'img,video,iframe{max-width:100%}</style></head><body>'
                    . $rawHtml . '</body></html>';
                $revisionDetail .= '<h3>Archived content</h3>'
                    . '<p>Visual preview is isolated: scripts and Geeklog autotags are not executed. '
                    . 'External resources are blocked, so the appearance can differ from the published page.</p>'
                    . '<div class="revisions-preview-tabs">'
                    . '<input type="radio" id="revisions-tab-preview" name="revisions-content-tab" checked>'
                    . '<label for="revisions-tab-preview">Visual preview</label>'
                    . '<input type="radio" id="revisions-tab-html" name="revisions-content-tab">'
                    . '<label for="revisions-tab-html">HTML source — copy and paste</label>'
                    . '<div class="revisions-tab-panel revisions-tab-panel--preview">'
                    . '<iframe class="revisions-preview-frame" sandbox="" loading="lazy" '
                    . 'referrerpolicy="no-referrer" title="Archived HTML visual preview" srcdoc="'
                    . R_H($previewDoc) . '"></iframe></div>'
                    . '<div class="revisions-tab-panel revisions-tab-panel--html">'
                    . '<textarea readonly rows="14" spellcheck="false" aria-label="Archived HTML source" class="revisions-source">'
                    . R_H($rawHtml) . '</textarea></div></div>';
                $revisionDetail .= '<details class="revisions-detail-fold"><summary>Revision history</summary>';
                $historyId = DB_escapeString($rev['sp_id']);
                $historyRows = DB_query("SELECT revision_id,created_at FROM {$_TABLES['revisions_history']} WHERE sp_id='$historyId' ORDER BY revision_id DESC LIMIT 50",1);
                if ($historyRows) {
                    $revisionDetail .= '<ul class="revisions-history-list">';
                    $historicalNumbers = array();
                    while ($historyRow = DB_fetchArray($historyRows)) {
                        $historyUrl = $_CONF['site_admin_url'] . '/plugins/revisions/index.php?revision_id=' . (int)$historyRow['revision_id'];
                        $itemNumber = REVISIONS_ADMIN_pageRevisionNumber($rev['sp_id'], $historyRow['revision_id']);
                        $revisionDetail .= '<li><a href="' . R_H($historyUrl) . '">Revision #' . ($itemNumber === null ? '?' : (int)$itemNumber) . '</a> — '
                            . R_H($historyRow['created_at']) . '</li>';
                    }
                    $revisionDetail .= '</ul>';
                }
                $revisionDetail .= '<h4>Restore readiness</h4>';
                $currentEncoded = is_array($current) ? REVISIONS_encode($current) : false;
                $trackedHash = false;
                $baselineSql = DB_escapeString($rev['sp_id']);
                $baselineCheck = DB_query("SELECT content_hash FROM {$_TABLES['revisions_baseline']} WHERE sp_id='$baselineSql' LIMIT 1",1);
                if ($baselineCheck && DB_numRows($baselineCheck) === 1) {
                    $baselineRow = DB_fetchArray($baselineCheck);
                    $trackedHash = isset($baselineRow['content_hash']) ? $baselineRow['content_hash'] : false;
                }
                $sourceMatches = $currentEncoded !== false && $trackedHash !== false
                    && hash_equals($trackedHash, hash('sha256', $currentEncoded));
                if ($current === false) {
                    $revisionDetail .= '<p>Current page unavailable or ineligible.</p>';
                } elseif (!$sourceMatches) {
                    $revisionDetail .= '<p>Current content differs from baseline; inspect before restoration.</p>';
                } else {
                    $revisionDetail .= '<p>Current content matches the tracked baseline.</p>';
                }
                $revisionDetail .= '<p>Automatic restoration is not available in this preview.</p></details>';
                $revisionDetail .= '<details class="revisions-detail-fold"><summary>Changed lines</summary>'
                    . REVISIONS_ADMIN_lineDiff($rawHtml, $currentSource) . '</details>';
                $revisionDetail .= '<details class="revisions-detail-fold"><summary>Current version</summary>'
                    . '<pre class="revisions-source revisions-source--pre">' . R_H($currentSource) . '</pre>'
                    . '</details></section>';
            } else {
                $message = 'Archived snapshot could not be verified.';
            }
        } else {
            $message = 'You are not allowed to view this page history.';
        }
    }
}
$section = isset($_GET['section']) ? (string) $_GET['section'] : 'home';
if (isset($_GET['revision_id'])) { $section = 'history'; }
if (isset($_POST['action'])) { $section = 'pages'; }
if (!in_array($section, array('home','pages','history','help'), true)) { $section = 'home'; }
$base = $_CONF['site_admin_url'] . '/plugins/revisions/index.php';
$out = '<link rel="stylesheet" href="' . R_H($_CONF['site_admin_url'] . '/plugins/revisions/admin.css?v=' . plugin_chkVersion_revisions()) . '">'
    . '<h1>Revisions ' . R_H(plugin_chkVersion_revisions()) . '</h1>'
    . '<p>Static Pages history: capture, inspect, and copy earlier editorial source. Restoration is not yet available.</p>';
$navItems = array('home'=>'Overview','pages'=>'Pages with revisions','history'=>'History','help'=>'Help');
$out .= '<nav class="plugin-admin-nav" aria-label="Revisions administration"><div class="plugin-admin-nav__primary">';
foreach ($navItems as $key => $label) {
    $active = $section === $key;
    $out .= '<a class="plugin-admin-nav__item' . ($active ? ' is-active' : '') . '"'
        . ($active ? ' aria-current="page"' : '')
        . ' href="' . R_H($base . '?section=' . $key) . '">' . R_H($label) . '</a>';
}
$out .= '</div></nav>';
if ($message !== '') { $out .= '<p class="revisions-message" role="status">' . R_H($message) . '</p>'; }
if ($section === 'home') {
    $countTracked = DB_query("SELECT COUNT(*) AS cnt FROM {$_TABLES['revisions_baseline']}",1);
    $countHistory = DB_query("SELECT COUNT(*) AS cnt FROM {$_TABLES['revisions_history']}",1);
    $pagesNumber = $countTracked ? DB_fetchArray($countTracked) : array('cnt'=>'?');
    $revsNumber = $countHistory ? DB_fetchArray($countHistory) : array('cnt'=>'?');
    $out .= '<h2>Overview</h2><p><strong>' . R_H($pagesNumber['cnt']) . '</strong> tracked pages · <strong>'
        . R_H($revsNumber['cnt']) . '</strong> archived revisions.</p>'
        . '<p><a href="' . R_H($base . '?section=pages') . '">Browse pages with revisions</a> · '
        . '<a href="' . R_H($base . '?section=history') . '">Browse history</a></p>'
        . '<h3>Getting started</h3><ol><li>Open Pages with revisions after editing a page to see its history.</li>'
        . '<li>Edit any eligible Static Page and save your changes in Geeklog.</li>'
        . '<li>Open History to inspect and copy the previous source.</li></ol>'
        . '<p>PHP and template Static Pages are excluded. No automatic restoration or asset backup is included.</p>';
} elseif ($section === 'pages') {
    $out .= '<h2>Pages with revisions</h2><p>Only Static Pages with archived versions appear here.</p>';
    // Aggregate only the history table; titles are resolved separately to avoid
    // JOIN/GROUP BY incompatibilities across supported MySQL/MariaDB releases.
    $pages = DB_query("SELECT sp_id, COUNT(*) AS revision_count, MAX(created_at) AS last_revision "
        . "FROM {$_TABLES['revisions_history']} GROUP BY sp_id ORDER BY sp_id LIMIT 1000", 1);
    if ($pages === false) {
        COM_errorLog('Revisions: cannot list pages with revisions (SQL query failed).');
        $out .= '<p>Unable to load pages with revisions. Check Geeklog error.log for SQL errors.</p>';
    } elseif (DB_numRows($pages) === 0) {
        $out .= '<p>No archived revisions yet. Revisions appear after eligible Static Pages are edited and saved.</p>';
    } else {
        $pageList = array();
        while ($page = DB_fetchArray($pages)) {
            $page['page_title'] = REVISIONS_ADMIN_pageTitle($page['sp_id']);
            $pageList[] = $page;
        }
        usort($pageList, function ($a, $b) {
            $aMissing = $a['page_title'] === '(page unavailable)';
            $bMissing = $b['page_title'] === '(page unavailable)';
            if ($aMissing !== $bMissing) { return $aMissing ? 1 : -1; }
            $cmp = strcasecmp($a['page_title'], $b['page_title']);
            return $cmp !== 0 ? $cmp : strcmp($a['sp_id'], $b['sp_id']);
        });
        $out .= '<table><thead><tr><th>Page</th><th>Revisions</th><th>Latest revision</th><th>History</th></tr></thead><tbody>';
        foreach ($pageList as $page) {
            $historyUrl = $base . '?section=history&sp_id=' . rawurlencode($page['sp_id']);
            $out .= '<tr><td><strong>' . R_H($page['page_title']) . '</strong>'
                . '<br><small>' . R_H($page['sp_id']) . '</small></td><td>'
                . (int)$page['revision_count'] . '</td><td>' . R_H($page['last_revision']) . '</td>'
                . '<td><a href="' . R_H($historyUrl) . '">View revisions</a></td></tr>';
        }
        $out .= '</tbody></table>';
    }
} elseif ($section === 'history') {
    $pageFilter = isset($_GET['sp_id']) ? trim((string) $_GET['sp_id']) : '';
    $where = $pageFilter !== '' ? " WHERE sp_id='" . DB_escapeString($pageFilter) . "'" : '';
    $q = DB_query("SELECT revision_id,sp_id,actor_id,created_at FROM {$_TABLES['revisions_history']}"
        . $where . " ORDER BY revision_id DESC LIMIT 200",1);
    if ($pageFilter !== '') {
        $out .= '<p><strong>History for: ' . R_H(REVISIONS_ADMIN_pageTitle($pageFilter)) . '</strong> '
            . '<small>' . R_H($pageFilter) . '</small></p>';
    }
    $out .= $revisionDetail;
    if (!isset($_GET['revision_id'])) {
    $out .= '<h3>Revision history</h3><table><thead><tr><th>Revision</th><th>Page</th><th>Author</th><th>Date</th></tr></thead><tbody>';
if ($q) { while ($r=DB_fetchArray($q)) {
    $detailUrl = $_CONF['site_admin_url'] . '/plugins/revisions/index.php?revision_id=' . (int) $r['revision_id'];
    $pageNumber = REVISIONS_ADMIN_pageRevisionNumber($r['sp_id'], $r['revision_id']);
    $out .= '<tr><td><a href="' . R_H($detailUrl) . '">#'.R_H($pageNumber === null ? '?' : $pageNumber).' — View</a></td><td>'.R_H($r['sp_id']).'</td><td>'.R_H($r['actor_id']).'</td><td>'.R_H($r['created_at']).'</td></tr>';
} }
$out .= '</tbody></table><p>Snapshots are stored in database. Select View to compare archived source and current source.</p>';

    }

} else {
    $out .= '<h2>Help</h2><p><strong>How tracking works:</strong> all eligible existing Static Pages are initialized automatically during upgrade and when opening Tracked pages; newly created pages start tracking on their first save. Edit and save, then look in History. An archive contains the earlier stored source.</p>'
        . '<p><strong>Missing updates:</strong> check the automatic save hook and error.log. A recovered change has author ID 0 because the original editor is unknown.</p>'
        . '<p><strong>HTML source:</strong> select View in History, then copy the escaped source from the read-only field into the Geeklog HTML/source editor.</p>'
        . '<p><strong>Limitations:</strong> PHP/template pages, file recovery, automatic restore and multi-writer atomicity are not supported in this pilot.</p>'
        . '<p><a href="https://github.com/hostellerie/revisions">Project documentation</a></p>';
}
COM_output(COM_createHTMLDocument($out,array('pagetitle'=>'Revisions')));