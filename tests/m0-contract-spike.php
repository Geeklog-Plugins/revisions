<?php
/**
 * Revisions M0 contract spike. PHP 5.6 syntax.
 * This is a standalone in-memory simulator, NOT a Geeklog integration.
 */
function revisions_m0_fingerprint($fields)
{
    ksort($fields);
    return hash('sha256', json_encode($fields));
}

class RevisionsM0Store
{
    public $items = array();
    public $history = array();

    public function seed($provider, $id, $fields)
    {
        $this->items[$provider . ':' . $id] = $fields;
    }

    /**
     * Simulates provider-owned validation + pre-write snapshot + write.
     * In production, this sequence requires a persistence/atomicity contract.
     */
    public function save($provider, $id, $expectedFingerprint, $newFields, $simulateFailure)
    {
        $key = $provider . ':' . $id;
        if (!isset($this->items[$key])) {
            return 'not_found';
        }
        $old = $this->items[$key];
        if (revisions_m0_fingerprint($old) !== $expectedFingerprint) {
            return 'conflict';
        }
        if (revisions_m0_fingerprint($old) === revisions_m0_fingerprint($newFields)) {
            return 'unchanged';
        }
        // Prototype atomic behavior: no persistent write until provider save succeeds.
        if ($simulateFailure) {
            return 'failed';
        }
        $this->items[$key] = $newFields;
        if (!isset($this->history[$key])) {
            $this->history[$key] = array();
        }
        $this->history[$key][] = array(
            'provider' => $provider,
            'id' => $id,
            'snapshot' => $old,
            'fingerprint' => revisions_m0_fingerprint($old)
        );
        return 'saved';
    }

    public function restore($provider, $id, $expectedFingerprint, $revisionIndex)
    {
        $key = $provider . ':' . $id;
        if (!isset($this->history[$key][$revisionIndex])) {
            return 'not_found';
        }
        return $this->save(
            $provider,
            $id,
            $expectedFingerprint,
            $this->history[$key][$revisionIndex]['snapshot'],
            false
        );
    }
}

function revisions_m0_assert($actual, $expected, $description)
{
    if ($actual !== $expected) {
        fwrite(STDERR, 'FAIL ' . $description . ': ' . var_export($actual, true) . "\n");
        exit(1);
    }
    echo 'OK ' . $description . "\n";
}

$s = new RevisionsM0Store();
$initialArticle = array('title' => 'Avant', 'introtext' => '[youtube:abc]', 'bodytext' => '<p>Un</p>');
$initialPage = array('sp_title' => 'Page', 'sp_content' => '<b>Source</b>', 'postmode' => 'html');
$s->seed('article', 'story-1', $initialArticle);
$s->seed('staticpages', 'page-1', $initialPage);
$a0 = revisions_m0_fingerprint($initialArticle);
$p0 = revisions_m0_fingerprint($initialPage);
revisions_m0_assert($s->save('article', 'story-1', $a0, $initialArticle, false), 'unchanged', 'unchanged skipped');
revisions_m0_assert($s->save('article', 'story-1', $a0, array('title' => 'Après', 'introtext' => '[youtube:abc]', 'bodytext' => '<p>Deux</p>'), true), 'failed', 'failed provider save');
revisions_m0_assert(count(isset($s->history['article:story-1']) ? $s->history['article:story-1'] : array()), 0, 'no phantom revision');
revisions_m0_assert($s->save('article', 'story-1', $a0, array('title' => 'Après', 'introtext' => '[youtube:abc]', 'bodytext' => '<p>Deux</p>'), false), 'saved', 'article saved');
revisions_m0_assert($s->history['article:story-1'][0]['snapshot'], $initialArticle, 'article old source preserved');
revisions_m0_assert($s->save('article', 'story-1', $a0, array('title' => 'Conflit'), false), 'conflict', 'stale update rejected');
revisions_m0_assert($s->save('staticpages', 'page-1', $p0, array('sp_title' => 'Page 2', 'sp_content' => '<b>Autre</b>', 'postmode' => 'html'), false), 'saved', 'staticpage saved');
revisions_m0_assert($s->history['staticpages:page-1'][0]['snapshot'], $initialPage, 'staticpage old source preserved');
$current = revisions_m0_fingerprint($s->items['article:story-1']);
revisions_m0_assert($s->restore('article', 'story-1', $current, 0), 'saved', 'restore via simulated provider save');
revisions_m0_assert($s->items['article:story-1'], $initialArticle, 'original recovered');
revisions_m0_assert(count($s->history['article:story-1']), 2, 'replaced state preserved on restore');
echo "M0 simulation complete; no Geeklog integration validated.\n";
