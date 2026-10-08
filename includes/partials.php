<?php
/** Small HTML fragments reused across pages. */

function badge_severity(string $s): string
{
    return '<span class="badge sev-' . e($s) . '">' . e(severity_label($s)) . '</span>';
}

function badge_status(string $s): string
{
    return '<span class="badge st-' . e($s) . '">' . e(status_label($s)) . '</span>';
}

/**
 * Status tracker: Received -> Acknowledged -> Dispatched -> On scene -> Resolved
 * (a false alarm ends the chain early).
 */
function render_timeline(array $report, array $history): void
{
    $times = [];
    foreach ($history as $h) {
        $times[$h['status']] = $h['created_at']; // latest time for each status
    }
    $status = $report['status'];

    if ($status === 'false_alarm') {
        $flow = ['received'];
        foreach (['acknowledged', 'dispatched', 'on_scene'] as $mid) {
            if (isset($times[$mid])) {
                $flow[] = $mid;
            }
        }
        $flow[] = 'false_alarm';
    } else {
        $flow = ['received', 'acknowledged', 'dispatched', 'on_scene', 'resolved'];
    }
    $current = array_search($status, $flow, true);
    if ($current === false) {
        $current = 0;
    }

    echo '<ol class="timeline">';
    foreach ($flow as $i => $step) {
        $cls = 'tl-step';
        if ($i < $current) {
            $cls .= ' done';
        } elseif ($i === $current) {
            $cls .= ' current';
            if ($step === 'resolved') {
                $cls .= ' done';
            }
            if ($step === 'false_alarm') {
                $cls .= ' alarm';
            }
        }
        echo '<li class="' . e($cls) . '"><span class="tl-dot"></span><span class="tl-text"><strong>' . e(status_label($step)) . '</strong>';
        if ($i <= $current && isset($times[$step])) {
            echo '<small>' . e(fmt_time($times[$step])) . ' · ' . e(time_ago($times[$step])) . '</small>';
        } elseif ($i > $current) {
            echo '<small>waiting</small>';
        }
        echo '</span></li>';
    }
    echo '</ol>';
}

/** Messages the officer sent to the reporter (read-only for the reporter). */
function render_officer_messages(array $messages): void
{
    if (!$messages) {
        return;
    }
    echo '<div class="officer-msgs"><h4>Messages from the officer</h4>';
    foreach ($messages as $m) {
        echo '<div class="officer-msg"><p>' . nl2br(e($m['body'])) . '</p><small>' . e(fmt_time($m['created_at'])) . ' · ' . e(time_ago($m['created_at'])) . '</small></div>';
    }
    echo '</div>';
}

/** The reporter's own follow-ups on a case. */
function render_updates(array $updates, string $title = 'Updates posted'): void
{
    if (!$updates) {
        return;
    }
    echo '<div class="updates"><h4>' . e($title) . '</h4><ul>';
    foreach ($updates as $u) {
        echo '<li><span>' . nl2br(e($u['body'])) . '</span><small>' . e(fmt_time($u['created_at'])) . ' · ' . e(time_ago($u['created_at'])) . '</small></li>';
    }
    echo '</ul></div>';
}

function render_flashes(): void
{
    foreach (pull_flashes() as $f) {
        echo '<div class="flash flash-' . e($f['type']) . '" role="status">' . e($f['msg']) . '</div>';
    }
}
