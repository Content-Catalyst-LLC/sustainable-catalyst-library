<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$instance_id = wp_unique_id( 'sc-field-stack-' );
$field_list = array_values( $fields );
$requested_field = isset( $_GET['sc_publication_field'] ) ? sanitize_title( (string) wp_unslash( $_GET['sc_publication_field'] ) ) : '';
$total_panels = 0;
foreach ( $field_list as $candidate_field ) {
    $total_panels += is_array( $candidate_field['panels'] ?? null ) ? count( $candidate_field['panels'] ) : 0;
}
?>
<div id="<?php echo esc_attr( $instance_id ); ?>" class="sc-field-stack" data-sc-field-stack="v4.3.22.4" data-sc-field-stack-mode="all-fields" aria-label="<?php esc_attr_e( 'Publications by major field', 'sustainable-catalyst-library' ); ?>">
    <section class="sc-field-stack__index" aria-labelledby="<?php echo esc_attr( $instance_id . '-title' ); ?>">
        <div class="sc-field-stack__index-head">
            <div>
                <p class="sc-field-stack__eyebrow"><?php echo esc_html( sprintf( '%d MAJOR FIELDS · %d ARTICLE MAPS', count( $field_list ), $total_panels ) ); ?></p>
                <h3 id="<?php echo esc_attr( $instance_id . '-title' ); ?>">Publications by field</h3>
            </div>
            <p>All major fields are rendered below. Use this index to jump to a field, or scroll through the complete Publications architecture.</p>
        </div>
        <nav class="sc-field-stack__jump-grid" aria-label="<?php esc_attr_e( 'Jump to a Publications field', 'sustainable-catalyst-library' ); ?>">
            <?php foreach ( $field_list as $index => $selector_field ) : ?>
                <?php
                $field_key = sanitize_title( (string) ( $selector_field['key'] ?? '' ) );
                $field_anchor = $instance_id . '-' . sanitize_html_class( $field_key );
                $is_requested = $requested_field && $requested_field === $field_key;
                ?>
                <a class="sc-field-stack__jump<?php echo $is_requested ? ' is-requested' : ''; ?>" href="#<?php echo esc_attr( $field_anchor ); ?>">
                    <span><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
                    <strong><?php echo esc_html( (string) $selector_field['title'] ); ?></strong>
                    <small><?php echo esc_html( (string) count( $selector_field['panels'] ?? array() ) ); ?></small>
                </a>
            <?php endforeach; ?>
        </nav>
    </section>

    <div class="sc-field-stack__fields">
        <?php foreach ( $field_list as $index => $stack_field ) : ?>
            <?php
            $field = $stack_field;
            $field_number = $index + 1;
            $field_key = sanitize_title( (string) ( $field['key'] ?? '' ) );
            $stage_id = $instance_id . '-' . sanitize_html_class( $field_key );
            $master_stage = false;
            $include_data = true;
            ?>
            <div class="sc-field-spotlights sc-field-spotlights--single sc-field-spotlights--stack-item<?php echo $requested_field === $field_key ? ' is-requested' : ''; ?>" data-sc-field-spotlights="v4.3.22.4" data-sc-field-spotlights-mode="single" data-sc-field-spotlights-runtime-state="server" data-stack-field-key="<?php echo esc_attr( $field_key ); ?>">
                <?php include SC_LIBRARY_DIR . 'templates/field-spotlight-stage.php'; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
