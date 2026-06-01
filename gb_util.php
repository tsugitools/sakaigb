<?php

/** Sakai LTI-AGS read-only gradebook extension URI */
define('SAKAI_READONLY_URI', 'https://www.sakailms.org/spec/lti-ags/v2p0/readOnly');

/** Appended when Tsugi reports missing LTI 1.3 launch / key configuration */
define('SAKAI_LTI13_ONLY_NOTE', ' (note this is an LTI 1.3 only tool)');

/**
 * @param string|false|null $message
 * @return string|false|null
 */
function sakaigb_format_lti13_error($message) {
    if ( ! is_string($message) ) return $message;
    $message = trim($message);
    if ( strlen($message) === 0 ) return $message;
    if ( stripos($message, 'LTI 1.3 only tool') !== false ) return $message;
    return $message . SAKAI_LTI13_ONLY_NOTE;
}

/**
 * @param object $lineitem Line item from AGS
 * @return bool
 */
function sakaigb_is_readonly($lineitem) {
    if ( ! is_object($lineitem) ) return false;
    return isset($lineitem->{SAKAI_READONLY_URI}) && $lineitem->{SAKAI_READONLY_URI} === true;
}

/**
 * @param object $member NRPS member
 * @return string
 */
function sakaigb_member_display_name($member) {
    if ( ! is_object($member) ) return '';
    if ( isset($member->name) && strlen(trim($member->name)) > 0 ) {
        return trim($member->name);
    }
    $parts = array();
    if ( isset($member->given_name) ) $parts[] = trim($member->given_name);
    if ( isset($member->family_name) ) $parts[] = trim($member->family_name);
    $name = trim(implode(' ', $parts));
    if ( strlen($name) > 0 ) return $name;
    if ( isset($member->email) && strlen(trim($member->email)) > 0 ) {
        return trim($member->email);
    }
    if ( isset($member->user_id) ) return $member->user_id;
    if ( isset($member->lis_person_sourcedid) ) return $member->lis_person_sourcedid;
    return '(unknown)';
}

/**
 * @param object|false $nrps NRPS response
 * @return array user_id => display name
 */
function sakaigb_build_user_map($nrps) {
    $map = array();
    if ( ! is_object($nrps) || ! isset($nrps->members) || ! is_array($nrps->members) ) {
        return $map;
    }
    foreach ( $nrps->members as $member ) {
        if ( ! isset($member->user_id) ) continue;
        $map[$member->user_id] = sakaigb_member_display_name($member);
    }
    return $map;
}

/**
 * @param object $result AGS result
 * @return string
 */
function sakaigb_format_score($result) {
    if ( ! is_object($result) ) return '';
    $score = isset($result->resultScore) ? $result->resultScore : null;
    $max = isset($result->resultMaximum) ? $result->resultMaximum : null;
    if ( $score === null && $max === null ) return '';
    if ( $max !== null && $max > 0 && $score !== null ) {
        return $score . ' / ' . $max;
    }
    if ( $score !== null ) return (string) $score;
    return '';
}

/**
 * Load line items, per-item detail, all results, and roster via LTI Advantage.
 *
 * @param \Tsugi\Core\LTIX $LTI
 * @param array $debug_log
 * @return array
 */
function sakaigb_load_gradebook_data($LTI, &$debug_log) {
    $data = array(
        'error' => null,
        'lineitems' => array(),
        'lineitems_detail' => array(),
        'results_by_lineitem' => array(),
        'nrps' => null,
        'user_map' => array(),
        'matrix' => array(),
        'column_labels' => array(),
        'row_labels' => array(),
        'stats' => array(
            'lineitem_count' => 0,
            'readonly_count' => 0,
            'writable_count' => 0,
            'member_count' => 0,
            'result_count' => 0,
        ),
    );

    $debug_log[] = '--- loadLineItems (full gradebook list) ---';
    $lineitems = $LTI->context->loadLineItems(false, $debug_log);
    if ( is_string($lineitems) ) {
        $data['error'] = sakaigb_format_lti13_error($lineitems);
        return $data;
    }
    if ( ! is_array($lineitems) ) {
        $data['error'] = sakaigb_format_lti13_error('Line items response was not an array');
        return $data;
    }

    $data['lineitems'] = $lineitems;
    $data['stats']['lineitem_count'] = count($lineitems);

    foreach ( $lineitems as $li ) {
        if ( sakaigb_is_readonly($li) ) {
            $data['stats']['readonly_count']++;
        } else {
            $data['stats']['writable_count']++;
        }
    }

    foreach ( $lineitems as $idx => $li ) {
        if ( ! isset($li->id) || ! is_string($li->id) ) {
            $debug_log[] = "Skipping line item at index $idx (no id)";
            continue;
        }
        $id = $li->id;
        $label = isset($li->label) ? $li->label : ('Column ' . ($idx + 1));
        $debug_log[] = '--- loadLineItem: ' . $label . ' ---';
        $detail = $LTI->context->loadLineItem($id, $debug_log);
        if ( is_string($detail) ) {
            $data['lineitems_detail'][$id] = $detail;
        } else {
            $data['lineitems_detail'][$id] = $detail;
        }

        $debug_log[] = '--- loadResults: ' . $label . ' ---';
        $results = $LTI->context->loadResults($id, $debug_log);
        if ( is_string($results) ) {
            $data['results_by_lineitem'][$id] = $results;
        } else if ( is_array($results) ) {
            $data['results_by_lineitem'][$id] = $results;
            $data['stats']['result_count'] += count($results);
        } else {
            $data['results_by_lineitem'][$id] = array();
        }
    }

    $debug_log[] = '--- loadNamesAndRoles ---';
    $nrps = $LTI->context->loadNamesAndRoles(false, $debug_log);
    if ( is_string($nrps) ) {
        $data['nrps_error'] = sakaigb_format_lti13_error($nrps);
    } else {
        $data['nrps'] = $nrps;
        if ( is_object($nrps) && isset($nrps->members) && is_array($nrps->members) ) {
            $data['stats']['member_count'] = count($nrps->members);
        }
    }

    $data['user_map'] = sakaigb_build_user_map($data['nrps']);
    sakaigb_build_matrix($data);
    return $data;
}

/**
 * Build gradebook matrix: rows = users, columns = line items.
 *
 * @param array $data Passed by reference; fills matrix, column_labels, row_labels
 */
function sakaigb_build_matrix(&$data) {
    $columns = array();
    foreach ( $data['lineitems'] as $li ) {
        if ( ! isset($li->id) ) continue;
        $label = isset($li->label) ? $li->label : $li->id;
        if ( sakaigb_is_readonly($li) ) {
            $label .= ' [RO]';
        }
        $columns[$li->id] = $label;
    }
    $data['column_labels'] = $columns;

    $scores = array();
    $user_ids = array();

    foreach ( $data['results_by_lineitem'] as $lineitem_id => $results ) {
        if ( ! is_array($results) ) continue;
        foreach ( $results as $result ) {
            if ( ! is_object($result) || ! isset($result->userId) ) continue;
            $uid = $result->userId;
            $user_ids[$uid] = true;
            if ( ! isset($scores[$uid]) ) $scores[$uid] = array();
            $scores[$uid][$lineitem_id] = sakaigb_format_score($result);
        }
    }

    foreach ( array_keys($data['user_map']) as $uid ) {
        $user_ids[$uid] = true;
    }

    $sorted_uids = array_keys($user_ids);
    usort($sorted_uids, function($a, $b) use ($data) {
        $na = isset($data['user_map'][$a]) ? $data['user_map'][$a] : $a;
        $nb = isset($data['user_map'][$b]) ? $data['user_map'][$b] : $b;
        return strcasecmp($na, $nb);
    });

    $matrix = array();
    $row_labels = array();
    foreach ( $sorted_uids as $uid ) {
        $row_labels[$uid] = isset($data['user_map'][$uid]) ? $data['user_map'][$uid] : $uid;
        $row = array();
        foreach ( array_keys($columns) as $col_id ) {
            $row[$col_id] = isset($scores[$uid][$col_id]) ? $scores[$uid][$col_id] : '';
        }
        $matrix[$uid] = $row;
    }

    $data['matrix'] = $matrix;
    $data['row_labels'] = $row_labels;
}
