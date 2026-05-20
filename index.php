<?php
require_once "../config.php";

use \Tsugi\Core\LTIX;
use \Tsugi\UI\Output;

require_once "util.php";
require_once "gb_util.php";

$LTI = LTIX::requireData();
require_once("nav.php");

$debug_log = array();
$gb = sakaigb_load_gradebook_data($LTI, $debug_log);

$OUTPUT->header();
$OUTPUT->bodyStart();
$OUTPUT->topNav($menu);
$OUTPUT->welcomeUserCourse();

$stats = $gb['stats'];
?>
<p class="lead">
  Sakai gradebook read-only test &mdash;
  <?= (int) $stats['lineitem_count'] ?> line items
  (<?= (int) $stats['writable_count'] ?> writable,
  <?= (int) $stats['readonly_count'] ?> read-only),
  <?= (int) $stats['member_count'] ?> roster members,
  <?= (int) $stats['result_count'] ?> result rows.
</p>
<?php if ( $gb['error'] ) { ?>
<div class="alert alert-danger">
  <strong>Error loading line items:</strong>
  <?= htmlentities($gb['error']) ?>
</div>
<?php } ?>
<?php if ( isset($gb['nrps_error']) ) { ?>
<div class="alert alert-warning">
  <strong>NRPS warning:</strong>
  <?= htmlentities($gb['nrps_error']) ?>
</div>
<?php } ?>

<div id="tabs">
  <ul>
    <li><a href="#tab-gradebook">Gradebook</a></li>
    <li><a href="#tab-lineitems">Line Items</a></li>
    <li><a href="#tab-detail">Line Item Detail</a></li>
    <li><a href="#tab-results">Results</a></li>
    <li><a href="#tab-roster">Roster</a></li>
    <li><a href="#tab-debug">Debug Log</a></li>
  </ul>

  <div id="tab-gradebook">
    <p>Table built from AGS results and NRPS member names. <code>[RO]</code> = Sakai read-only column.</p>
    <div class="table-responsive">
      <table class="table table-striped table-bordered table-sm">
        <thead>
          <tr>
            <th scope="col">Student</th>
<?php foreach ( $gb['column_labels'] as $col_label ) { ?>
            <th scope="col"><?= htmlentities($col_label) ?></th>
<?php } ?>
          </tr>
        </thead>
        <tbody>
<?php
foreach ( $gb['matrix'] as $uid => $row ) {
    $name = isset($gb['row_labels'][$uid]) ? $gb['row_labels'][$uid] : $uid;
?>
          <tr>
            <th scope="row"><?= htmlentities($name) ?></th>
<?php
    foreach ( array_keys($gb['column_labels']) as $col_id ) {
        $cell = isset($row[$col_id]) ? $row[$col_id] : '';
?>
            <td><?= htmlentities($cell) ?></td>
<?php
    }
?>
          </tr>
<?php } ?>
        </tbody>
      </table>
    </div>
<?php if ( count($gb['matrix']) === 0 ) { ?>
    <p class="text-muted">No rows to display (no results or roster members).</p>
<?php } ?>
  </div>

  <div id="tab-lineitems">
    <pre><?php
    if ( count($gb['lineitems']) > 0 ) {
        echo htmlentities(Output::safe_print_r($gb['lineitems']), ENT_SUBSTITUTE);
    } else {
        echo "(none)\n";
    }
?></pre>
  </div>

  <div id="tab-detail">
<?php
foreach ( $gb['lineitems'] as $li ) {
    if ( ! isset($li->id) ) continue;
    $id = $li->id;
    $label = isset($li->label) ? $li->label : $id;
    $detail = isset($gb['lineitems_detail'][$id]) ? $gb['lineitems_detail'][$id] : null;
?>
    <h4><?= htmlentities($label) ?></h4>
    <pre><?php
    if ( is_string($detail) ) {
        echo htmlentities("Error: " . $detail);
    } else if ( $detail ) {
        echo htmlentities(Output::safe_print_r($detail), ENT_SUBSTITUTE);
    } else {
        echo "(not loaded)\n";
    }
?></pre>
<?php } ?>
  </div>

  <div id="tab-results">
<?php
foreach ( $gb['lineitems'] as $li ) {
    if ( ! isset($li->id) ) continue;
    $id = $li->id;
    $label = isset($li->label) ? $li->label : $id;
    $results = isset($gb['results_by_lineitem'][$id]) ? $gb['results_by_lineitem'][$id] : null;
?>
    <h4><?= htmlentities($label) ?></h4>
    <pre><?php
    if ( is_string($results) ) {
        echo htmlentities("Error: " . $results);
    } else if ( is_array($results) ) {
        echo htmlentities(Output::safe_print_r($results), ENT_SUBSTITUTE);
    } else {
        echo "(none)\n";
    }
?></pre>
<?php } ?>
  </div>

  <div id="tab-roster">
    <pre><?php
    if ( is_object($gb['nrps']) ) {
        echo htmlentities(Output::safe_print_r($gb['nrps']), ENT_SUBSTITUTE);
    } else {
        echo "(roster not loaded)\n";
    }
?></pre>
  </div>

<?php print_debug_log('tab-debug', $debug_log); ?>
</div>

<?php
$OUTPUT->footerStart();
?>
<script>
  $( function() { $( "#tabs" ).tabs(); } );
</script>
<?php
$OUTPUT->footerEnd();
