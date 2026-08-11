<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$instance_id = wp_unique_id( 'sc-field-spotlights-' );
$field = reset( $fields );
if ( ! is_array( $field ) ) { return; }
$field_number = max( 1, absint( $field['order'] ?? 1 ) );
$stage_id = $instance_id . '-' . sanitize_html_class( (string) $field['key'] );
$master_stage = false;
$include_data = true;
?>
<div id="<?php echo esc_attr( $instance_id ); ?>" class="sc-field-spotlights sc-field-spotlights--single" data-sc-field-spotlights="v4.3.13" data-sc-field-spotlights-mode="single">
    <?php include SC_LIBRARY_DIR . 'templates/field-spotlight-stage.php'; ?>
</div>
