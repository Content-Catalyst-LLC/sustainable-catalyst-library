<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$instance_id = wp_unique_id( 'sc-field-spotlights-stack-' );
$field_list = array_values( $fields );
?>
<div id="<?php echo esc_attr( $instance_id ); ?>" class="sc-field-spotlights-stack" data-sc-field-spotlights-stack="v4.3.18.1" aria-label="Publication fields">
    <?php foreach ( $field_list as $index => $stack_field ) : ?>
        <?php
        $field = $stack_field;
        $field_number = $index + 1;
        $stage_id = $instance_id . '-' . sanitize_html_class( (string) $field['key'] );
        $master_stage = true;
        $include_data = true;
        ?>
        <div class="sc-field-spotlights sc-field-spotlights--master sc-field-spotlights--stack-item" data-sc-field-spotlights="v4.3.18.1" data-sc-field-spotlights-mode="single" data-field-stack-key="<?php echo esc_attr( (string) $field['key'] ); ?>">
            <?php include SC_LIBRARY_DIR . 'templates/field-spotlight-stage.php'; ?>
        </div>
    <?php endforeach; ?>
</div>
